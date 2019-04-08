<?php
declare(strict_types=1);

/**
* WBGym
*
* @copyright 	(c) 2018 Webteam Weinberg-Gymnasium Kleinmachnow
* @package   	WBGym
* @author		Markus Mielimonka <mmi.github@t-online.de>
* @license   	http://www.gnu.org/licenses/gpl-3.0.html GPL
*/

/**
* namespace
*/
namespace WBGym;

use BackendTemplate;
use Input;
use Database\Statement;

/**
* SMSCourseAdd controller
*/
class ModuleSMSCourseAdd extends \Module
{
	protected $strTemplate = 'wb_sms_course_add';

	/**
	* whether mails are send to the coLeader and the teacher of the SMS course
	* @var bool blnSendMails
	*/
	protected $blnSendMails = false;

	public function generate() {
		if (TL_MODE == 'BE') {
			# backend wildcard:
			$objTemplate = new BackendTemplate('be_wildcard');
			$objTemplate->wildcard = '### WBGym SmS Kursanmeldung ###';
			$objTemplate->title = 'SmS Kursanmeldung';
			$objTemplate->id = $this->id;
			$objTemplate->link = 'WBGym SmS Kursanmeldung';
			$objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id=' . $this->id;

			return $objTemplate->parse();
		}
		return parent::generate();
	}

	public function compile() {
		global $objPage;
		if (!FE_USER_LOGGED_IN) {
			# deny frontend access if no user is logged in
			$objHandler = new $GLOBALS['TL_PTY']['error_403']();
			$objHandler->generate($objPage->id);
		}
		$this->Import('FrontendUser', 'User');

		$error = [];
		$this->Template->formSuccess = false;
		$this->hasCourse = $this->userCourse();

		if ($_SERVER['REQUEST_METHOD'] == 'POST' && !is_null(Input::post('submit'))) {
			#fetch input:
			$course = $this->fetch_input();
			#Form submit handling:
			$valid = $this->validateForm($course, $error, is_null($this->hasCourse));
			if ($valid) {
				$this->Template->formSuccess = true;
				if (!$this->saveCourse($course)) $error['general'] = 'Der Kurs konnte zurzeit nicht eingetragen werden bitte versuch es später erneut.';
				elseif (is_null($this->hasCourse)) $this->sendMails($course);
			}
		}
		$this->Template->error = $error;
		# get the users current sms course, if existing
		$this->Template->user_course = $this->hasCourse;
		$this->Template->leaderString = WBGym::student($this->User->id);

		/*
		* load cours and their students for selcts
		*/
		# filter "Jahrgang" courses (11, 12) for course select:
		$this->Template->courses = array_filter(WBGym::courseList(), function (string $item) {
			if (is_numeric($item))
				return false;
			return true;
		});
		# courses: Array( course => Array ( studentid => studentname))
		$this->Template->course_map =  $this->getStudentList();
		# get Teachers for teacher select:
		$this->Template->teachers = WBGym::teacherList();
	}

	/**
	 * fetches all form fields into an associative array.
	 * @return array the form data (database_column => value)
	 */
	protected function fetch_input(): array {
		$input = [
			'name' => trim(Input::post('course_name')),
			'leader' => $this->User->id,
			'description' => trim(Input::post('description')),
			'specials' => trim(Input::post('notes') ) ?? '',
			'closed' => '',
			'maxStudents' => Input::post('numberOfstudents'),
			'minForm' => intval(Input::post('minGrade')) < 5 ? 5 : Input::post('minGrade'),
			'maxForm' => intval(Input::post('maxGrade')) > 10 ? 10 : Input::post('maxGrade'),
			'coLeader' => Input::post('has_second_leader') ? Input::post('second_leader')[Input::post('course')] : '0',
			'teacher' => Input::post('teacher') ?? '0'
		];
		return $input;
	}

	/**
	* checks the form for it's correctness
	* @var array error (ref), associative array of fields & their corresponding errors.
	* @return bool
	*/
	protected function validateForm(array $course, array &$error, bool $first = true): bool {
		# set default error:
		$error = [];
		# begin checks:
		if (strlen($course['name']) < 4) {
			$error['name'] = 'Dein Kursname ist zu kurz';
		}
		if (strlen($course['description']) < 20) {
			$error['description'] = 'Du brauchst eine längere Kursbeschreibung!';
		}
		if ($course['minForm'] > $course['maxForm'])
			$error['grades'] = 'Dein Kurs muss von mindestens einer Klassenstufe wählbar sein!';
		# test second leader:
		$sndCourse = $this->userCourse(intval($course['coLeader']));
		if ($course['coLeader'] == $this->User->id) {
		  # NOTE: shouldn't happend, you shouldn't be able to select yourself.
			$error['second_leader'] = 'Du kannst dich nicht selber vertreten, wenn du allein bist, lass das Feld einfach frei.';
		}
		elseif (!is_numeric($course['coLeader']) && $course['coLeader'] != '0') {
			$error['second_leader'] = 'Fehler, konnte den 2. Kursleiter nicht zuordnen.';
		}
		elseif (!is_null($sndCourse) && $sndCourse['id'] !== $course['id'] && $first) {
			$error['second_leader'] = 'Die Person, welche du als 2. Leiter angegeben hast, leitet bereits einen anderen Kurs.';
		}
		# fetch teacher: (no input field in edit mode)
		if (!$this->isTeacher(intval($course['teacher'])) && $first)
			$error['teacher'] = 'Dein Kurs braucht einen betreuenden Lehrer!';
		if (empty($error)) return true;
		else return false;
	}

	/**
	 * saves a course to the database by either updating or inserting it
	 * @param  array  $course	the course array
	 * @return Statement?		Contao Database Query
	 */
	protected function saveCourse(array $course): ?Statement {
		if (!is_null($this->hasCourse)) return $this->updateCourse($course);
		else return $this->insertCourse($course);
	}

	/**
	 * inserts a new course into the db
	 * @param  array  $course	the course properties
	 * @return Statement?		Contao Database Query
	 */
	protected function insertCourse(array $course): ?Statement {
		$course['tstamp'] = time();
		$course['id'] = '';
		return $this->Database->prepare('INSERT INTO tl_sms_course %s')
		->set($course)->execute();
	}

	/**
	 *
	 * updates a course in the database
	 * @param  array  $dbc		the new course properties
	 * @return Statement?		Contao Database Query
	 */
	protected function updateCourse(array $dbc): ?Statement {
	# unset persisting fields:
		if (isset($dbc['id'])) unset($dbc['id']);
			if (isset($dbc['tstamp'])) unset($dbc['tstamp']);
		if (isset($dbc['coLeader'])) unset($dbc['coLeader']);
		if (isset($dbc['leader'])) unset($dbc['leader']);
		if (isset($dbc['teacher'])) unset($dbc['teacher']);
		return $this->Database->prepare('UPDATE tl_sms_course %s WHERE id=?')->set($dbc)->execute($this->hasCourse['id']);
	}

	/**
	* gets the course of an user by id
	* @param int id, if Null -> use the user currently logged in
	* @return array|false
	*/
	public function userCourse(int $userId=null): ?array {
		$userId = $userId ? $userId : $this->User->id;
		$res = $this->Database->prepare('SELECT *, COUNT(*) as num FROM tl_sms_course WHERE leader=? OR coLeader=?')
		->execute($userId, $userId)->fetchAllAssoc()[0];
		if (intval($res['num']) == 1) {
			return $res;
		}
		return null;
	}

	/**
	 * tests whether a userid refers to a teacher
	 * @param  int   $tid the id of the user to test
	 * @return bool  whteher or not he/she is a teacher
	 */
	public function isTeacher(int $tid): bool {
		$res = $this->Database->prepare('SELECT COUNT(*) as num FROM tl_member WHERE teacher=? AND id=?')
			->execute(1, $tid)->fetchAssoc();
		if (intval($res['num']) !== 1) return false;
		return true;
	}

	/**
	 * @deprecated teachers can now supervise multiple courses
	* gets the course of an teacher by id
	* @param int id, if Null -> use the user currently logged in
	* @return array|true
	*/
	public function teacherHasCourse(int $id): bool {
		$res = $this->Database->prepare('SELECT COUNT(*) as num FROM tl_sms_course WHERE teacher=?')
		->execute($id)->fetchAssoc();
		if ($res['num'] > 0) {
			return true;
		}
		return false;
	}

	public function getStudentList(): array {
		# generate Array( course => Array ( studentid => studentname))
		$students = $this->Database->prepare('SELECT * FROM tl_member WHERE student = 1 AND course != 0 ORDER BY grade, formSelector, firstname, lastname')
			->execute()->fetchAllAssoc();
		$courses_students = [];
		$arrCourses = $this->Database->prepare('SELECT * FROM tl_courses WHERE `title`!="Jahrgang" ORDER BY id')->execute()->fetchAllAssoc();
		$arrCourses = array_reduce($arrCourses, function (&$res, $item) {
			$res[$item['id']] = $item;
			return $res;
		}, []);
		foreach ($students as $student) {
			if ($student['id'] == $this->User->id) {
			  continue;
			}
			$course = $arrCourses[$student['course']];
			$courseString = str_replace(' ', '/', WBGym::courseToString($course));

			if ($courseString == '/') {
				continue;
			}

			if (!isset($courses_students[$courseString])) {
				$courses_students[$courseString] = [];
			}
				$courses_students[$courseString][$student['id']] = $student['firstname'].' '.$student['lastname'];
			}
		return $courses_students;
	}

	/**
	* sends E-Mails to the co_leader and teacher to verify.
	* @param array takes the form data
	* @return bool success?
	*/
	public function sendMails(array $conf): bool {
		$res = [];
		if ($conf['coLeader']) {
			if ($coLeaderEmail = $this->loadUserEmail(intval($conf['coLeader']))) {
				$res[] = $this->sendMail('wb_registered_for_course.html', 'Du wurdest als SmS-Kursleiter eingetragen',$coLeaderEmail,
				['{{headline}}', '{{salutation0}}', '{{salutation1}}' ,'{{salutation2}}'],
				['Du wurdest als stellvertretender Kursleiter des Kurses "'.$conf['name'].'" eingetragen', 'dir', 'kannst du', 'dich']
				);
			}
		}
		if ($conf['teacher']) {
			if ($teacherEmail = $this->loadUserEmail(intval($conf['teacher']))) {
				$res[] = $this->sendMail('wb_registered_for_course.html', 'Sie wurden als SmS-Betreuer eingetragen',$teacherEmail,
				['{{headline}}', '{{salutation0}}', '{{salutation1}}', '{{salutation2}}'],
				['Sie wurden als Betreuer des Kurses "'.$conf['name'].'" eingetragen', 'Ihnen', 'können Sie', 'Sie sich']
				);
			}
		}
		return !in_array(false, $res);
	}

	/**
	* sends a single Mail to a user if @var blnSendMails is true
	* @param string $strTemplate 	Email Template to user
	* @param string $strSubject 	Email Subject
	* @param string $strMail		Email to send the mail to
	* @param array  $arrSearch		Insert Tags to replace
	* @param array  $arrReplace		Values to replace
	*
	* @return bool if true, mail delivery was successfull
	*
	* @see WBGym\ModuleSMSExchange\sendMail (mainly copied).
	* @author +Johannes Cram <craj.me@gmail.com>
	*/
	protected function sendMail(string $strTemplate, string $strSubject,string $strAddress, array $arrSearch, array $arrReplace): bool {
		if ($this->blnSendMails) {
			$objMail = new \Email();
			$objMail->from = 'webteam@wbgym.de';
			$objMail->fromName = 'SmS-Woche / Webteam Weinberg-Gymnasium';
			$objMail->subject = $strSubject;
			\dump(getcwd());
			$html = file_get_contents('../vendor/wbgym/sms-bundle/src/Resources/contao/templates_email/' . $strTemplate, true);
			/*
			* Replace own insert tags
			*/
			$objMail->html = str_replace($arrSearch, $arrReplace, $html);

			$objMail->sendTo($strAddress);
			if (!empty($objMail->failures)) {
				\System::log('SmS CourseAdd Notification E-Mail was sent to ' . $strAddress . ' with Template ' . $strTemplate, __METHOD__, 'TL_GENERAL');
				return true;
			}
			return false;
		}
		return true;
	}

	/**
	* sends a single Mail to a user if @var blnSendMails is true
	* @param string $strTemplate 	Email Template to user
		* @param string $strSubject 		Email Subject
		* @param string $strMail			Email to send the mail to
		* @param array (string=>string) $arrSearch Insert Tags to replace
		*
		* @return bool if true, mail delivery was successfull
	*
	* @see WBGym\ModuleSMSExchange\sendMail (mainly copied).
	* @author +Johannes Cram <craj.me@gmail.com>
	*/
	protected function sendMailTr(string $strTemplate, string $strSubject,string $strAddress, array $arrSearchAndReplace): bool {
		if ($this->blnSendMails) {
			$objMail = new \Email();
			$objMail->from = 'webteam@wbgym.de';
			$objMail->fromName = 'SmS-Woche / Webteam Weinberg-Gymnasium';
			$objMail->subject = $strSubject;

			$html = file_get_contents('vendor/wbgym/sms-bundle/src/Resources/contao/templates_email/' . $strTemplate, true);
			/*
			 * Replace own insert tags
			*/
			 foreach ($arrSearchAndReplace as $key => $value) {
				$arrSearchAndReplace[$key] = Null;
				$arrSearchAndReplace['{{'.$key.'}}'] = $value;
			 }
			$objMail->html = strtr($arrSearchAndReplace, $html);

			$objMail->sendTo($strAddress);
			if (!empty($objMail->failures)) {
				\System::log('SmS CourseAdd Notification E-Mail was sent to ' . $strAddress . ' with Template ' . $strTemplate, __METHOD__, 'TL_GENERAL');
				return true;
			}
			return false;
		}
		return true;
	}

	/**
	* fetches the emailaddress of the user
	* @var int userid
	* @return string?
	*/
	public function loadUserEmail(int $uid): ?string {
		$res = $this->Database->prepare('SELECT email, COUNT(*) as count FROM tl_member WHERE id=?')->execute($uid)->fetchAssoc();
		if (intval($res['count']) != 1) {
			return null;
		}
		return $res['email'];
	}
}
