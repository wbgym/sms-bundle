<?php

/**
 * WBGym
 *
 * Copyright (C) 2016 Webteam Weinberg-Gymnasium Kleinmachnow
 *
 * @package   WBGym
 * @author    Johannes Cram <craj.me@gmail.com> && Malte Metze <malte.metze@gmx.de>
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL
 */

/**
 * Namespace
 */
namespace WBGym;

class ModuleSMSExchange extends \Module {

	/**
	* =================================================
	* Dev mode - send notification emails or not
	* =================================================
	* @var bool
	*/
	protected $blnSendMails = true;

	/**
	* =================================================
	* First run of program: find free course places for all wishes
	* =================================================
	* @var bool
	*/
	protected $findAllFreePlaces = false;

	/**
	 * ================================================
	 * Set whether the deadline is near (if so, emails have different headlines)
	 * ================================================
	 * @var bool
	 */
	protected $lastReminder = false;

	/**
	* Template
	* @var string
	*/
	protected $strTemplate = 'wb_sms_exchange';

	/**
	* Courses for the wish form
	* @var array
	*/
	protected $arrWishableCourses = array();

	/**
	* Current course of the current user
	* @var array
	*/
	protected $arrMyCourse = array();

	/**
	* All Entries form tl_sms_exchange
	* @var array
	*/
	protected $arrWishEntries = array();

	/**
	* User Data of partners
	* @var array
	*/
	protected $arrUserData = array();

	/**
	* Is the current user's course fixed?
	* @var bool
	*/
	protected $myCourseIsFixed = false;

	/**
	* Template / View mode (1 = wish form, 2 = search for matches/free places, 3 = exchange courses, 4 = change course (use a free place))
	* @var int;
	*/
	protected $intMode = 0;

	/**
	* Entries where one of the wishes matches with the current user's course
	* @var array
	*/
	protected $arrMatches = array();

	/**
	* Course IDs of wished courses where free places are available
	* @var array
	*/
	protected $arrFreePlaceCourses = array();

	/**
	* Say the template if an entry, which was already accepted by the partner exists
	* @var bool
	*/
	protected $blnHasReactEntry = false;

	/**
	* Redirect-Link for Template (for changing the view mode)
	* @var string;
	*/
	protected $strHref = '';

	/**
	* Contains Error Message
	* @var string
	*/
	protected $error;

	/**
	* Contains Success Message
	* @var string
	*/
	protected $message;

	/**
	* includes the data of an successfully exchanged course
	* @var array
	*/
	protected $successData = array();


	public function generate(){
		if (TL_MODE == 'BE')
		{
			$objTemplate = new \BackendTemplate('be_wildcard');
			$objTemplate->wildcard = '### WBGym SmS Tauschbörse ###';
			$objTemplate->title = $this->headline;
			$objTemplate->id = $this->id;
			$objTemplate->link = $this->name;
			$objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id=' . $this->id;

			return $objTemplate->parse();
		}

		return parent::generate();
	}

	protected function compile(){

		/*
		* Generate Access Denied Page if user is not logged in
		*/
		if(!FE_USER_LOGGED_IN){
			$objHandler = new $GLOBALS['TL_PTY']['error_403']();
			$objHandler->generate($objPage->id);
		}
		/*
		* Initialize
		*/
		$this->Import('FrontendUser', 'User');
		$this->Import('Database');
		$this->Import('Environment');

		$this->loadMyCourse();
		$this->loadWishEntries();
		$this->loadCourses();
		/*
		* Handle GET-Parameters  ===================================
		*/
		if(\Input::get('aktion') != 'success' && $this->getMyWishes()[0]['status'] == 2) {
			$this->redirect(explode('?_', $this->addToUrl('aktion=success'))[0]);
		}
		elseif(\Input::get('aktion') == 'eintragen' && !$this->findReactEntry()) {
			$this->intMode = 1;
			$this->strHref = explode('?_', $this->addToUrl('aktion=suchen'))[0];
		}
		elseif(\Input::get('aktion') == 'suchen' && $this->getMyWishes()) {
			$this->intMode = 2;
			$this->findMatchesOrFreePlaces();
			$this->handleNewMatches();
			$this->strHref = explode('?_', $this->addToUrl('aktion=eintragen'))[0];
		}
		elseif(\Input::get('aktion') == 'success' && $this->getMyWishes()[0]['status'] == 2) {
			$this->intMode = 3;
			$this->handleSuccessMessage();
		}
		/*
		* Redirect if no valid GET parameter is set =========================
		*/
		else{
			if(!\Input::post('FORM_SUBMIT') && !$this->getMyWishes()) {
				$this->redirect(explode('?_', $this->addToUrl('aktion=eintragen'))[0]);
			}
			elseif(!\Input::post('FORM_SUBMIT') && $this->getMyWishes()) {
				$this->redirect(explode('?_', $this->addToUrl('aktion=suchen'))[0]);
			}
			elseif(!\Input::post('FORM_SUBMIT')){
				$this->redirect(explode('?_', $this->addToUrl('aktion=eintragen'))[0]); //start page of module
			}
			elseif(!\Input::post('FORM_SUBMIT') && $this->getMyWishes()[0]['status'] == 2) {
				$this->redirect(explode('?_', $this->addToUrl('aktion=success'))[0]);
			}
		}
		/*
		* Mode submit wishes=================================
		*/
		if(\Input::post('FORM_SUBMIT') == 'submit_wishes') {
			if($this->wishFormIsValid()) {
				$this->registerWishes();
				$this->redirect(explode('?_', $this->addToUrl('aktion=suchen'))[0]);
			} else {
				$this->error = 'similar_wishes';
			}
		}
		/*
		* Mode exchange courses ==========================
		*/
		if(\Input::post('FORM_SUBMIT') == 'execute_match') {
			if(\Input::post('confirm')) {
				$this->handleExecutedMatches();
				$this->redirect(explode('?_', $this->addToUrl('aktion=success'))[0]);
			}
			if(\Input::post('cancel')) {
				$this->handleCancelledMatches();
				$this->findMatchesOrFreePlaces();					//reload to show the change
			}
		}
		if(\Input::post('FORM_SUBMIT') == 'execute_fpc') {
			$this->findMatchesOrFreePlaces();
			if($this->arrFreePlaceCourses) {
				$this->handleExecutedFpcs();
				$this->handleNewFreePlaces($this->findAllFreePlaces, $this->lastReminder);
				$this->redirect(explode('?_', $this->addToUrl('aktion=success'))[0]);
			}
			else {
				$this->error = 'no_longer_available';
				$this->findMatchesOrFreePlaces();
			}
		}

		/*
		* Fill Template =======================================
		*/

		//User Info
		$this->Template->id = $this->User->id;
		$this->Template->name = $this->User->firstname . ' ' . $this->User->lastname;
		$this->Template->grade = $this->User->grade;
		$this->Template->formselector = $this->User->formSelector;
		$this->Template->email = $this->User->email;

		//Course Info
		$this->Template->currentcourse = $this->arrMyCourse['name'];
		$this->Template->myCourseIsFixed = $this->myCourseIsFixed;

		//Data / General Info
		$this->Template->wishes = $this->getMyWishes();
		$this->Template->matches = $this->arrMatches;
		$this->Template->freePlaceCourses = $this->arrFreePlaceCourses;
		$this->Template->error = $this->error;
		$this->Template->message = $this->message;
		$this->Template->courses = $this->arrWishableCourses;
		$this->Template->mode = $this->intMode;
		$this->Template->hasReactEntry = $this->blnHasReactEntry;
		$this->Template->successData = $this->successData;

		//Links
		$this->Template->href = $this->strHref;
		$this->Template->mailLink = "https://webmail.all-inkl.com/index.php?login_name=" . $this->User->email;
		$this->Template->cboxHref = \Environment::get('requestUri') . '/#course-list';
	}

	/**
	* Check if the WishForm is valid
	*
	* @return bool
	*/
	protected function wishFormIsValid() {
		if(!$this->getMyWishes()) {$this->getMyWishes[0]['status'] = 0;} //If no entry exists yet, the exchange status is zero
		if((\Input::post('wish1') == \Input::post('wish2') || \Input::post('wish2') == \Input::post('wish3') || \Input::post('wish1') == \Input::post('wish3')) && ($this->getMyWishes[0]['status'] <= 1)) return false;
		return true;
	}

	/**
	* Get Wish Entry of Student
	*
	* @param int $studentId The Id of the Student
	* @return mixed The DB Entry of tl_sms_exchange or false
	*/
	protected function getWishesOfStudent($studentId){
		if(!isset($this->arrWishEntries[$studentId])) {
			$this->loadWishEntries();
		}
		if($this->arrWishEntries[$studentId]) {
			return $this->formatWishEntry($this->arrWishEntries[$studentId]);
		}
		return false;
	}

	/**
	* Get Wishes of current user
	*
	* @return array The Entry of the current user
	*/
	protected function getMyWishes(){
		return $this->getWishesOfStudent($this->User->id);
	}

	/*
	* Load all entries from tl_sms_exchange and write it into $arrWishEntries
	*
	* @var array $arrWishEntries The content of tl_sms_exchange
	*/
	protected function loadWishEntries(){
		$result = $this->Database->prepare("SELECT * FROM tl_sms_exchange")->execute();
		if($result) {
			while($entry = $result->fetchAssoc()) {
				$this->arrWishEntries[$entry['student']] = $entry;
			}
		} else {
			$this->arrWishEntries = null;
		}
	}

	/**
	* Load all entries fom tl_sms_courses and write it into $arrCourses
	*
	* @var array $arrCourses The content of tl_sms_courses
	*/
	protected function loadCourses(){
		if(!$this->arrMyCourse) {
			$this->arrWishableCourses = null;
		} else {
			$resC = $this->Database->prepare("SELECT * FROM tl_sms_course ORDER BY name")->execute();
			$resE = $this->Database->prepare("SELECT * FROM tl_sms_exchange")->execute();
			while($entry = $resE->fetchAssoc()) {
				$arrWishEntries[] = $entry;
			}

			if($resC) {
				$courses = array();
				while($course = $resC->fetchAssoc()) {
					$course['leader_str'] = WBGym::student($course['leader']);
					$course['coLeader_str'] = WBGym::student($course['coLeader']);

					//find wishes and successful exchanges for course
					foreach($arrWishEntries as $entry) {
						if($entry['current_course'] == $course['id'] && $entry['new_course'] == 0)
							$course['outWishes']++;

						if(($entry['cwish1'] == $course['id'] || $entry['cwish2'] == $course['id'] || $entry['cwish3'] == $course['id']) && $entry['new_course'] == 0) {
							$course['inWishes']++;
							if($entry['student'] == $this->User->id) {
								$course['ownInWish'] = true;
							}
						}
					}
					$courses[$course['id']] = $course;
				}
				//unset non-wishable courses
				unset($courses[$this->arrMyCourse['id']]);
				foreach ($courses as $i => $course) {
					if(!SMS::gradeInCourseRange($course, $this->User->grade) || $course['maxStudents'] == 0 || $course['closed'] == 1) {
						unset($courses[$i]);
					}
				}
				$this->arrWishableCourses = $courses;
			}
		}
	}

	/**
	* Load course of a user from tl_sms_course_choice, collect other information of the course and check if the course choice is fixed
	*
	* @param int $studentId ID of user to search for
	* @return mixed course entry or false if course is fixed
	*/
	protected function loadCourseOfUser($studentId){
		$course = $this->Database->prepare("SELECT tl_sms_course_choice.finalWishAuto AS id, tl_sms_course_choice.finalWish, tl_sms_course.name
									FROM tl_sms_course_choice
									JOIN tl_sms_course ON tl_sms_course_choice.finalWishAuto = tl_sms_course.id
									WHERE tl_sms_course_choice.id = ? LIMIT 1")->execute($studentId);
		$course = $course->fetchAssoc();
		if($course['finalWish'] != '-1') return false; 	//students with fixed courses are not allowed to change the course
		return $course;
	}

	/**
	* Load course of current user from tl_sms_course_choice
	*
	* @var array $arrMyCourse Wish entry of current user
	*/
	protected function loadMyCourse(){
		$this->arrMyCourse = $this->loadCourseOfUser($this->User->id);
	}

	/*
	* Get user data
	*
	* @var		array $arrUserData Class Array to handle multiple queries in one request
	* @param 	integer $uid Id of the user
	* @return 	string EMail of the user
	*/
	protected function getUser($userId) {
		if(!isset($this->arrUserData[$userId]))  {
			$mail = $this->Database->prepare("SELECT * FROM tl_member WHERE id = ?")->limit(1)->execute($userId);
			$this->arrUserData[$userId] = $mail->fetchAssoc();
		}
		return $this->arrUserData[$userId];
	}

	/**
	* Get Status of wish by id
	*
	* @param int $stId id of the student
	* @return int status column of the wish
	*/
	protected function getStatus($stId) {
		if(!isset($this->arrWishEntries)) {
			$this->loadWishEntries();
		}
		return $this->arrWishEntries[$stId]['status'];
	}

	/**
	* Change wish entry property
	*
	* @param int $wishId Id of wish entry
	* @param string $strClm the column to change
	* @param mixed $mxdVal the value for this column of the entry
	*
	* @return bool true if update was successful
	*/
	protected function updateWish($wishId, $strClm, $mxdVal) {
		$result = $this->Database->prepare("UPDATE tl_sms_exchange SET " . $strClm . " =? WHERE id = ?")->execute($mxdVal, $wishId);
		return $result;
	}

	/**
	* Update tl_sms_course_choice -> set new wish
	*
	* @param int $newCourseId the id of the new course to set
	* @param int $userId 			the id of the student
	*/
	protected function updateCourse($newCourseId, $userId) {
		$result = $this->Database->prepare("UPDATE tl_sms_course_choice SET finalWishAuto = ? WHERE id = ?")->execute($newCourseId, $userId);
		return $result;
	}

	/**
	* Add course names & student name to wish entry and sort it
	*
	* @param array The wish entry from dba_close
	* @return array The new array
	*/
	protected function formatWishEntry($arrWish) {
		$w = array (
			//General Info
			array (
				'id'						=> $arrWish['id'],
				'cCourseId'			=> $arrWish['current_course'],
				'cCourseName'		=> WBGym::smsCourse($arrWish['current_course']),
				'studentId'			=> $arrWish['student'],
				'studentName'		=> WBGym::student($arrWish['student']),
				'status'				=> $arrWish['status'],
				'partner'				=> $arrWish['partner']
			),
			//Index 1 => Wish1
			array (
				'id' 		=> $arrWish['cwish1'],
				'name'	=> WBGym::smsCourse($arrWish['cwish1'])
			),
			//Index 2 => Wish2
			array (
				'id' 		=> $arrWish['cwish2'],
				'name'	=> WBGym::smsCourse($arrWish['cwish2'])
			),
			//Index 3 => Wish3
			array(
				'id' 		=> $arrWish['cwish3'],
				'name'	=> WBGym::smsCourse($arrWish['cwish3'])
			)
		);
		return $w;
	}

	/**
	* Write POST data from wish form into DB
	*
	* If an entry already exists, update entry
	* Else create new entry
	* @return void
	*/
	protected function registerWishes() {
		if(\Input::post('FORM_SUBMIT') == 'submit_wishes') {
			if($this->getMyWishes()) {
				$this->Database->prepare("UPDATE tl_sms_exchange SET tstamp = ?,current_course = ?, cwish1 = ?,cwish2 = ?,cwish3 = ? WHERE student = ?")
								->execute(time(), $this->arrMyCourse['id'], \Input::post('wish1'), \Input::post('wish2'), \Input::post('wish3'), $this->User->id);
			} else {
			$this->Database->prepare("INSERT INTO tl_sms_exchange (tstamp,student,current_course, cwish1,cwish2,cwish3,status) VALUES (?,?,?,?,?,?,?)")
								->execute(time(),$this->User->id, $this->arrMyCourse['id'], \Input::post('wish1'), \Input::post('wish2'), \Input::post('wish3'), 0);
			}
		}
	}

	/**
	* Check if entry wishes match with the current's user current course
	* or if a free place is available in one of the user's choices
	*
	* @var array $arrMatches The matching wish entries
	*/
	protected function findMatchesOrFreePlaces() {
		$this->loadWishEntries(); //Reloading for finding matches
		$this->blnHasReactEntry = false; 	//reset

		//find free places
		$arrFreePlaces = $this->findFreePlacesBy('user', $this->User->id);
		if(!empty($arrFreePlaces)) {
			foreach($arrFreePlaces as $wish)
			$this->arrFreePlaceCourses[] = array(
				'id'  		=> $wish,
				'name' 	=> WBGym::smsCourse($wish)
			);
		}

		//find out if there is a match
		foreach($this->arrWishEntries as $i => $wish) {
			if(
				(	//Partner entries only -> not me!
					!$wish[$this->User->id]
				) &&
				(	//Partner's Current Course has to be one of my wishes
					$wish['current_course'] == $this->getWishesOfStudent($this->User->id)[1]['id'] ||
					$wish['current_course'] == $this->getWishesOfStudent($this->User->id)[2]['id'] ||
					$wish['current_course'] == $this->getWishesOfStudent($this->User->id)[3]['id']
				) &&
				(	//My current course has to be one of my partner's wishes
					$this->arrWishEntries[$this->User->id]['current_course'] == $wish['cwish1'] ||
					$this->arrWishEntries[$this->User->id]['current_course'] == $wish['cwish2'] ||
					$this->arrWishEntries[$this->User->id]['current_course'] == $wish['cwish3']
				)
			)
					{
						if($wish['status'] == 2 && $wish['partner'] == $this->User->id) $this->blnHasReactEntry = true;
						if($wish['status'] < 2 || ($wish['status'] == 2 && $wish['partner'] == $this->User->id))
							$this->arrMatches[$i] = $this->formatWishEntry($wish);
					}
		}
	}

	/**
	* Find free course places by wishing user or course
	*
	* @param string $by - 'user' or 'course'
	* @param int $selector - user id of wishing user // course id, all users/courses in array by default
	* @var array $arrWishEntries
	*/
	protected function findFreePlacesBy($by, $selector = 0) {

		$assignments = $this->Database->prepare('SELECT finalWish, finalWishAuto FROM tl_sms_course_choice')->execute();
		$courses = $this->Database->prepare('SELECT id, maxStudents FROM tl_sms_course')->execute();

		//count attendants for every course
		$attendCourse = array();
		while($asgn = $assignments->fetchAssoc()) {
			if($asgn['finalWish'] != -1){
				$attendCourse[$asgn['finalWish']]++;
			}
			elseif($asgn['finalWishAuto'] != -1) {
				$attendCourse[$asgn['finalWishAuto']]++;
			}
		}

		//Find out how many students are allowed for every course
		$maxStudents = array();
		while($course = $courses->fetchAssoc()) {
			$maxStudents[$course['id']] = $course['maxStudents'];
		}

		//find out if there are wished courses with free places for any user
		$freePlaces = array();
		foreach($this->arrWishEntries as $user => $entry) {
			$w = array('cwish1','cwish2','cwish3');
			foreach ($w as $wishnum) {
				if($maxStudents[$entry[$wishnum]] > $attendCourse[$entry[$wishnum]]) {
					$freePlaces['forWishingUser'][$user][] = $entry[$wishnum];
					$freePlaces['inCourse'][$entry[$wishnum]] = true;
				}
			}
		}

		if($by == 'user' && $selector == 0) {
			return $freePlaces['forWishingUser'];
		}
		elseif($by == 'user' && $selector != 0) {
			return $freePlaces['forWishingUser'][$selector];
		}
		elseif($by == 'course' && $selector == 0) {
			return $freePlaces['inCourse'];
		}
		elseif($by == 'course' && $selector != 0) {
			return $freePlaces['inCourse'][$selector];
		}
		return false;
	}

	/**
	* Send Email
	*
	* @param string $strTemplate 	Email Template to user
	* @param string $strSubject 		Email Subject
	* @param string $strMail			Email to send the mail to
	* @param array $arrSearch		Insert Tags to replace
	* @param array $arrReplace		Values to replace
	*
	* @return bool if true, mail delivery was successfull
	*/
	protected function sendMail($strTemplate, $strSubject, $strAddress, $arrSearch, $arrReplace) {
		if($this->blnSendMails) {
			$objMail = new \Email();
			$objMail->from = 'sms@wbgym.de';
			$objMail->fromName = 'SmS-Woche / Webteam Weinberg-Gymnasium';
			$objMail->subject = $strSubject;

			$html = file_get_contents('system/modules/sms/templates_email/' . $strTemplate, true);
			/*
			* Replace own insert tags
			*/
			$objMail->html = str_replace($arrSearch, $arrReplace, $html);

			$objMail->sendTo($strAddress);
			if(!empty($objMail->failures)) {
				\System::log('SmS Exchange Notification E-Mail was sent to ' . $strAddress . ' with Template ' . $strTemplate,__METHOD__,'TL_GENERAL');
				return true;
			}
			return false;
		}
		return true;
	}

	/**
	* Send email to users who are currently not active if a new match was found and increase status
	*
	* @var array $arrMatches The matching wishes, including user info for email delivering
	* @return bool True if the delivery was successful
	*/
	protected function handleNewMatches() {
		if($this->arrMatches) {
			foreach ($this->arrMatches as $match) {
				if($match[0]['status'] == 0) {

					/*
					* Prepare Params for Email Delivery
					*/

					$arrRecipientName = explode(' ', $match[0]['studentName']); //[0] = firstname, [1] = lastname, [2] = grade/class

					$strSubject = 'Wir haben einen Tauschpartner gefunden!';
					$strTemplate = 'wb_matched.html';
					$arrSearch = array(
						'{{recipient_firstname}}',
						'{{partner_name}}',
						'{{course_name}}',
						'{{href}}'
					);
					$arrReplace = array(
						$arrRecipientName[0],
						WBGym::student($this->User->id),
						WBGym::smsCourse($this->getMyWishes()[0]['cCourseId']),
						$this->Environment->base . explode('?_', $this->addToUrl('aktion=suchen'))[0]
					);
					$strEmail = $this->getUser($match[0]['studentId'])['email'];

					//Send Mail
					$this->sendMail($strTemplate, $strSubject, $strEmail, $arrSearch, $arrReplace);

					//Update Wish of current user and possible partner
					$this->updateWish($match[0]['id'], 'status', 1);
					$this->updateWish($this->getWishesOfStudent($this->User->id)[0]['id'], 'status', 1);
				}
			}
		}
	}

	/**
	* Send email to users who are currently not active if a new free place was found
	*
	* @return bool True if the delivery was successful
	*/
	public function handleNewFreePlaces($sendToAll = false, $lastReminder = false) {
		//check if an other user wants the free place of this user
		if($sendToAll == true) //first run of program when there are already wishes
		{
			//contains array with free place courses for every user
			$arrFreePlaceCourses = $this->findFreePlacesBy('user');
			//unset own wish entry
			unset($arrFreePlaceCourses[$this->User->id]);
			//unset entries which already have a new course
			foreach($this->arrWishEntries as $user => $entry) {
				if($entry['status'] > 1) unset($arrFreePlaceCourses[$user]);
			}
		}
		else {
			//contains number of free places for the new free place course
			$arrFreePlaces = $this->findFreePlacesBy('course', $this->arrWishEntries[$this->User->id]['current_course']);
			if($arrFreePlaces == true) { //must be true because user was previously in the course and isn't anymore
				//find users who want the free place
				foreach($this->arrWishEntries as $user => $entry) {
					$w = array('cwish1','cwish2','cwish3');
					foreach ($w as $wishnum) {
						if($entry[$wishnum] == $this->arrWishEntries[$this->User->id]['current_course'] && $entry['status'] == 0) $foundUsers[] = $user;
					}
				}
				if($foundUsers)
					foreach($foundUsers as $user) $arrFreePlaceCourses[$user] = $this->arrWishEntries[$this->User->id]['current_course'];
			}
		}
		if($arrFreePlaceCourses) {
			foreach ($arrFreePlaceCourses as $user => $courses) {

					/*
					* Prepare Params for Email Delivery
					*/

					$arrRecipientName = explode(' ', WBGym::student($user)); //[0] = firstname, [1] = lastname, [2] = grade/class

					if($lastReminder) {
						$strSubject = 'Beeil dich, wenn du deinen SmS-Kursplatz noch wechseln möchtest!';
						$strTemplate = 'wb_freeplace_reminder.html';
					}
					else {
						$strSubject = 'Wir haben einen freien Kursplatz gefunden!';
						$strTemplate = 'wb_freeplace.html';
					}
					$arrSearch = array(
						'{{recipient_firstname}}',
						'{{course_name}}',
						'{{href}}'
					);
					$strCourse = '';
					if(is_array($courses)) {
						foreach ($courses as $course) $strCourse .= WBGym::smsCourse($course) . '<br />';
					}
					else {
						$strCourse = WBGym::smsCourse($courses);
					}
					$arrReplace = array(
						$arrRecipientName[0],
						$strCourse,
						$this->Environment->base . explode('?_', $this->addToUrl('aktion=suchen'))[0]
					);
					$strEmail = $this->getUser($user)['email'];

					//Send Mail
					$this->sendMail($strTemplate, $strSubject, $strEmail, $arrSearch, $arrReplace);
					$arrLog[] = array($strEmail,$strTemplate);

					//update wish to prevent that the student gets the same mail multiple time
					$this->Database->prepare("UPDATE tl_sms_exchange SET status = ? WHERE student = ?")->execute(1,$user);
			}
		}
		return $arrLog;
	}

	/**
	* Set Status of partner wish down and send cancelled mail to partner
	*
	* @var array $arrWishEntries
	*/
	protected function handleCancelledMatches() {
		$this->updateWish(\Input::post('wish_id'), 'status', 1);
		$this->updateWish(\Input::post('wish_id'), 'partner', 0);

		/*
		* Prepare Email for inactive partner
		*/
		if($this->arrWishEntries[\Input::post('user_id')]['status'] == 2) { 	//do not send emails on reload
			$strAddress = $this->getUser(\Input::post('user_id'))['email'];
			$strSubject = 'Dein Tausch wurde von deinem Partner abgebrochen';
			$strTemplate = 'wb_failed_pdid.html';
			$arrSearch = array(
				'{{recipient_firstname}}',
				'{{partner_name}}',
				'{{course_name}}',
				'{{href}}'
			);
			$arrReplace = array(
				$this->getUser(\Input::post('user_id'))['firstname'],
				WBGym::student($this->User->id),
				WBGym::smsCourse($this->getMyWishes()[0]['cCourseId']),
				$this->Environment->base . explode('?_', $this->addToUrl('aktion=suchen'))[0]
			);

			//Send Email
			$this->sendMail($strTemplate, $strSubject, $strAddress, $arrSearch, $arrReplace);

			/*
			* Prepare Email for me
			*/

			$strAddress = $this->User->email;
			$strSubject = 'Du hast das Tauschangebot abgelehnt';
			$strTemplate = 'wb_failed_idid.html';
			$arrSearch = array(
				'{{recipient_firstname}}',
				'{{partner_name}}',
				'{{course_name}}',
			);
			$arrReplace = array(
				$this->User->firstname,
				WBGym::student(\Input::post('user_id')),
				WBGym::smsCourse(\Input::post('course_id'))
			);

			//Send Email
			$this->sendMail($strTemplate, $strSubject, $strAddress, $arrSearch, $arrReplace);
		}

		//Show Template Message
		$this->message = 'cancelled';
	}

	/**
	* Increase "my" status / if the partner hasn't confirmed yet, send reminding mail to partner, else send confirmation mail to both
	*
	* @var array $arrWishEntries
	*/

	protected function handleExecutedMatches() {

		if($this->getMyWishes()[0]['status'] == 1) {	//do not send emails on reload

			/*
			* If the partner has already accepted the exchange
			*/

			if($this->getStatus(\Input::post('user_id')) == 2) {

				// Prepare Email Params for mail for "Me"
				$strAddress = $this->User->email;
				$strSubject = 'Dein SmS-Kurstausch wurde erfolgreich abgeschlossen';
				$strTemplate = 'wb_success_idid.html';
				$arrSearch = array(
					'{{recipient_firstname}}',
					'{{partner_name}}',
					'{{new_course_name}}'
				);
				$arrReplace = array(
					$this->User->firstname,
					WBGym::student(\Input::post('user_id')),
					WBGym::smsCourse(\Input::post('course_id'))
				);
				//Send Mail
				$this->sendMail($strTemplate, $strSubject, $strAddress, $arrSearch, $arrReplace);

				//prepare Email Params for partner
				$strAddress = $this->getUser(\Input::post('user_id'))['email'];
				$strTemplate = 'wb_success_pdid.html';
				$arrSearch = array(
					'{{recipient_firstname}}',
					'{{new_course_name}}',
					'{{partner_name}}'
				);
				$arrReplace = array(
					$this->getUser(\Input::post('user_id'))['firstname'],
					WBGym::smsCourse($this->getMyWishes()[0]['cCourseId']),
					WBGym::student($this->User->id),
				);
				$this->sendMail($strTemplate, $strSubject, $strAddress, $arrSearch, $arrReplace);

				//SET NEW COURSES INTO tl_sms_course_choice
				$this->updateCourse($this->getMyWishes()[0]['cCourseId'], \Input::post('user_id')); 	//update record of partner
				$this->updateCourse(\Input::post('course_id'), $this->User->id);	//update my record
			}

			/*
			* If the partner hasn't accepted the exchange yet ====================
			*/

			else {
				//Prepare Email Params for reminder mail for partner
				$strAddress = $this->getUser(\Input::post('user_id'))['email'];
				$strSubject = 'Du hast ein Tauschangebot erhalten!';
				$strTemplate = 'wb_react_idid.html';
				$arrSearch = array(
					'{{recipient_firstname}}',
					'{{course_name}}',
					'{{partner_name}}',
					'{{href}}'
				);
				$arrReplace = array(
					$this->getUser(\Input::post('user_id'))['firstname'],
					WBGym::smsCourse($this->getMyWishes()[0]['cCourseId']),
					WBGym::student($this->User->id),
					$this->Environment->base . explode('?_', $this->addToUrl('aktion=suchen'))[0]
				);
				//send mail
				$this->sendMail($strTemplate, $strSubject, $strAddress, $arrSearch, $arrReplace);
			}

			//Update Wish Status
			$this->updateWish(($this->getMyWishes()[0]['id']), 'status', 2);
			//Set potentially new course in wish entry
			$this->updateWish(($this->getMyWishes()[0]['id']), 'new_course', \Input::post('course_id'));
			//Set Partner Id for my wish Entry
			$this->updateWish($this->getMyWishes()[0]['id'], 'partner', \Input::post('user_id'));
		}

	}

	protected function handleExecutedFpcs() {
		if($this->getMyWishes()[0]['status'] != 2) {		//do nothing on reload
			$strAddress = $this->User->email;
			$strSubject = 'Du hast erfolgreich den Kurs gewechselt';
			$strTemplate = 'wb_fpc.html';
			$strSearch = array(
				'{{recipient_firstname}}',
				'{{new_course_name}}',
				'{{old_course_name}}'
			);
			$strReplace = array(
				$this->User->firstname,
				WBGym::smsCourse(\Input::post('course_id')),
				WBGym::smsCourse($this->arrWishEntries[$this->User->id]['current_course']),
			);
			$this->sendMail($strTemplate,$strSubject,$strAddress,$strSearch,$strReplace);
			$this->updateWish($this->arrWishEntries[$this->User->id]['id'], 'status', 2);
			$this->updateWish($this->arrWishEntries[$this->User->id]['id'], 'new_course', \Input::post('course_id'));
			$this->updateCourse(\Input::post('course_id'), $this->User->id);
		}
	}

	/*
	* Handle last view mode information
	*/
	protected function handleSuccessMessage() {
	if ($this->getMyWishes()[0]['status'] <= 1) {
			return false;
		}
		$partnerId = $this->getMyWishes()[0]['partner'];

		if($partnerId != 0) {
			if($this->arrWishEntries[$partnerId]['status'] == 2) $this->message = 'success_full';
			else {
				$this->message = 'success_part';
				$this->successData = array(
					'partner_name' => WBGym::student($partnerId),
					'new_course'	=> WBGym::smsCourse($this->arrWishEntries[$partnerId]['current_course'])
				);
			}
		}
		else {
			$this->message = 'success_fpc';
			$this->successData = array(
				'new_course' => WBGym::smsCourse($this->arrWishEntries[$partnerId]['new_course'])
			);
		}
	}

	/**
	* Find out if there is a reactEntry to react one
	*
	* @return mixed False if there are no matches, if there is a react entry, return the id of the entry
	*/
	protected function findReactEntry() {
		if(!$this->arrWishEntries) return false;
		foreach ($this->arrWishEntries as $wish) {
			if($wish['partner'] == $this->User->id && $wish['status'] == 2) {
				$this->blnHasReactEntry = true;
				return $wish['id'];
			}
		}
		return false;
	}
}


?>
