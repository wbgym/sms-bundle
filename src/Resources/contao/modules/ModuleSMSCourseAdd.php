<?php
/**
 * WBGym
 *
 * @copyright (c) 2018 Webteam Weinberg-Gymnasium Kleinmachnow
 * @package   WBGym
 * @author    Markus Mielimonka <markus.mielimonka@t-online.de>
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL
 */

/**
* namespace
*/
namespace WBGym;

use WBGym\WBGym;

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

    public function generate()
    {
        if (TL_MODE == 'BE') {
            # backend wildcard:
            $objTemplate = new \BackendTemplate('be_wildcard');
            $objTemplate->wildcard = '### WBGym SmS Kursanmeldung ###';
            $objTemplate->title = 'SmS Kursanmeldung';
            $objTemplate->id = $this->id;
            $objTemplate->link = 'WBGym SmS Kursanmeldung';
            $objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id=' . $this->id;

            return $objTemplate->parse();
        }

        return parent::generate();
    }
    public function compile()
    {
        global $objPage;
        if (!FE_USER_LOGGED_IN) {
            # deny frontend access if no user is logged in
            $objHandler = new $GLOBALS['TL_PTY']['error_403']();
            $objHandler->generate($objPage->id);
        }

        $this->Import('FrontendUser', 'User');

        $this->Template->formSuccess = false;
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && \Input::post('submit') !== null) {
            #Form submit handling:
            $error = '';
            $valid = $this->validateForm($error);
            $this->Template->errorMsg = $error;
            if ($valid) {
                $this->Template->formSuccess = true;
            }
        }


        # get the users current sms course | false
        $this->Template->user_has_course = $this->isCourseLeader();
        $this->Template->leaderString = WBGym::student($this->User->id);

        /*
        * load cours and their students for selcts
        */
        # filter "Jahrgang" courses (11, 12) for course select:
        $this->Template->courses = array_filter(WBGym::courseList(), function ($item) {
            if (is_numeric($item)) {
                return false;
            }
            return true;
        });
        # generate Array( course => Array ( studentid => studentname))
        $students = $this->Database->prepare('SELECT * FROM tl_member WHERE student = 1 AND course != 0 ORDER BY grade, formSelector, firstname, lastname')->execute()->fetchAllAssoc();
        $courses_students = array();
        foreach ($students as $student) {
            if ($student['id'] == $this->User->id) {
              continue;
            }
            $course = $this->Database->prepare('SELECT * FROM tl_courses WHERE id=? AND `title`!="Jahrgang" LIMIT 0,1')->execute($student['course'])->fetchAllAssoc()[0];
            $courseString = str_replace(' ', '/', WBGym::courseToString($course));

            if ($courseString == '/') {
                continue;
            }

            if (!isset($courses_students[$courseString])) {
                $courses_students[$courseString] = array();
            }
            $courses_students[$courseString][$student['id']] = $student['firstname'].' '.$student['lastname'];
        }
        $this->Template->course_map = $courses_students;
        # get Teachers for teacher select:
        $this->Template->teachers = WBGym::teacherList();
    }

    /**
    * checks the form for it's correctness
    * @var string error (ref), changes to an errormessage on failure
    * @return bool
    */
    public function validateForm(&$error)
    {
        # set default error:
        $error = Null;

        #fetch input:
        $course = array(
        'name' => \Input::post('course_name'),
        'leader' => $this->User->id,
        'description' => \Input::post('description'),
        'specials' => \Input::post('notes') ? \Input::post('notes') : '',
        'closed' => '',
        'maxStudents' => \Input::post('numberOfstudents'),
        'minForm' => \Input::post('minGrade'),
        'maxForm' => \Input::post('maxGrade'),
      );
        # begin checks:
        if (strlen(trim($course['name'])) < 4) {
            $error = 'Dein Kursname ist zu kurz';
            return false;
        }
        if (strlen(trim($course['description'])) < 20) {
            $error = 'Du brauchst eine längere Kursbeschreibung!';
            return false;
        }
        if ($course['minForm'] > $course['maxForm']) {
            $error = 'Dein Kurs muss von mindestens einer Klassenstufe wählbar sein!';
            return false;
        }
        # fetch second leader:
        if (\Input::post('has_second_leader')) {
            $coLeaderCourse = \Input::post('course');
            $user = \Input::post('second_leader')[$coLeaderCourse];
            if ($user == $this->User->id) {
              #NOTE: shouldend happend, you shouldn't be able to select yourself.
              $error = 'Du kannst dich nicht selber vertreten, wenn du allein bist, lass das Feld einfach frei.';
              return false;
            }
            if (!is_numeric($user)) {
                $error = 'Fehler, konnte den 2. Kursleiter nicht zuordnen.';
                return false;
            }
            if ($this->isCourseLeader($user)) {
                $error = 'Die Person, welche du als 2. Leiter angegeben hast Leitet bereits einen anderen Kurs.';
                return false;
            }
            $course['coLeader'] = $user;
        } else {
            # default for Database entry:
            $course['coLeader'] = '0';
        }
        # fetch teacher:
        if (\Input::post('teacher')) {
            $course['teacher'] = \Input::post('teacher');
        } else {
            $error = 'Dein Kurs braucht einen betreuenden Lehrer!';
            return false;
        }
        /*
        * end checks; insert into db
        */
        $course['id'] = ''; $course['tstamp'] = time();
        $res = $this->Database->prepare('INSERT INTO tl_sms_course %s')
          ->set($course)->execute();
        if (!$res) {
          $error = 'Der Kurs konnte zurzeit nicht eingetragen werden bitte versuch es später erneut.';
          return false;
        }
        $this->sendMails($course);
        return true;
    }
    /**
    * gets the course of an user by id
    * @param int id, if Null -> use the user currently logged in
    * @return array|false
    */
    public function isCourseLeader($userId=null)
    {
        $userId = $userId ? $userId : $this->User->id;
        $res = $this->Database->prepare('SELECT *, COUNT(*) as num FROM tl_sms_course WHERE leader=? OR coLeader=?')
        ->execute($userId, $userId)->fetchAllAssoc()[0];
        if ($res['num'] > 0) {
            return $res;
        }
        return false;
    }
    /**
    * gets the course of an teacher by id
    * @param int id, if Null -> use the user currently logged in
    * @return array|true
    * NOTE: despercated. no longer needed, teachers can now supervise multiple courses
    */
    public function teacherHasCourse($id)
    {
        $res = $this->Database->prepare('SELECT *, COUNT(*) as num FROM tl_sms_course WHERE teacher=?')
        ->execute($id)->fetchAllAssoc()[0];
        if ($res['num'] > 0) {
            return true;
        }
        return false;
    }
    /**
    * sends E-Mails to the co_leader and teacher to verify.
    * @param array takes the form data
    */
    public function sendMails($conf)
    {
      if ($conf['coLeader']) {
        $coLeaderEmail = $this->loadUserEmail($conf['coLeader']);
        $this->sendMail('wb_registered_for_course.html', 'Du wurdes als stellvertretender SmS-Kursleiter eingetragen',$coLeaderEmail,
         array('{{headline}}', '{{salutation0}}', '{{salutation1}}' ,'{{salutation2}}'),
         array('Du wurdest als stellvertretender Kursleiter des Kurses '.$conf['name'].' eingetragen', 'dir', 'kannst du', 'dich')
       );
      }
      if ($conf['teacher']) {
        $teacherEmail = $this->loadUserEmail($conf['teacher']);
        $this->sendMail('wb_registered_for_course.html', 'Sie wurden als SmS-Betreuer eingetragen',$teacherEmail,
         array('{{headline}}', '{{salutation0}}', '{{salutation1}}', '{{salutation2}}'),
         array('Sie wurden als Betreuer des Kurses '.$conf['name'].' eingetragen', 'Ihnen', 'können Sie', 'Sie sich')
       );
      }
      // $this->sendMailTr('wb_registered_course_orga.html', 'neuer SmS Kurs', 'markus.mielimonka@wbgym.de', $conf);
    }
    /**
    * sends a single Mail to a user if @var blnSendMails is true
    * @param string $strTemplate 	Email Template to user
  	* @param string $strSubject 		Email Subject
  	* @param string $strMail			Email to send the mail to
  	* @param array $arrSearch		Insert Tags to replace
  	* @param array $arrReplace		Values to replace
  	*
  	* @return bool if true, mail delivery was successfull
    *
    * @see WBGym\ModuleSMSExchange\sendMail (mainly copied).
    * @author +Johannes Cram <craj.me@gmail.com>
    */
    protected function sendMail(string $strTemplate, string $strSubject,string $strAddress, array $arrSearch, array $arrReplace)
    {
        if ($this->blnSendMails) {
            $objMail = new \Email();
            $objMail->from = 'webteam@wbgym.de';
            $objMail->fromName = 'SmS-Woche / Webteam Weinberg-Gymnasium';
            $objMail->subject = $strSubject;

            $html = file_get_contents('bundles/sms-bundle/src/Resources/contao/templates_email/' . $strTemplate, true);
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
    protected function sendMailTr(string $strTemplate, string $strSubject,string $strAddress, array $arrSearchAndReplace)
    {
        if ($this->blnSendMails) {
            $objMail = new \Email();
            $objMail->from = 'webteam@wbgym.de';
            $objMail->fromName = 'SmS-Woche / Webteam Weinberg-Gymnasium';
            $objMail->subject = $strSubject;

            $html = file_get_contents('bundles/sms-bundle/src/Resources/contao/templates_email/' . $strTemplate, true);
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
    * @return string|false
    */
    public function loadUserEmail($uid)
    {
      $res = $this->Database->prepare('SELECT email FROM tl_member WHERE id=?')->execute($uid)->fetchAllAssoc();
      if (count($res) != 1) {
        return false;
      }
      return $res[0]['email'];
    }
}
