<?php

/**
 * WBGym
 * 
 * Copyright (C) 2008-2013 Webteam Weinberg-Gymnasium Kleinmachnow
 * 
 * @package     WGBym
 * @author      Marvin Ritter <marvin.ritter@gmail.com>
 * @license     http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */

/**
 * Namespace
 */
namespace WBGym;

class ModuleSMSCourseChoice extends \Module {

    protected $strTemplate = 'wb_sms_course_choice';

    protected $memberId = -1;
    protected $ownCourseId = -1;
    protected $intUserGrade = -1;
    protected $arrCourses = array();
    protected $intMaxWishes = 3;

    protected function init() {
        if (FE_USER_LOGGED_IN) {
            $objMember = \FrontendUser::getInstance();
            $this->memberId = $objMember->id;
            $this->ownCourseId = $this->getLeadingCourseId();
            if ($this->rightOfChoice($objMember)) {
                $this->intUserGrade = $objMember->grade;
            }
        }
    }

    public function generate() {
        //Display a wildcard in the back end
        if (TL_MODE == 'BE') {
            $objTemplate = new \BackendTemplate('be_wildcard');

            $objTemplate->wildcard = '### WBGym Sfw Kurswahl ###';
            $objTemplate->title = 'WBGym Sfw Kurswahl';
            $objTemplate->id = $this->id;
            $objTemplate->link = 'WBGym Sfw Kurswahl';
            $objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id=' . $this->id;

            return $objTemplate->parse();
        }

        return parent::generate();
    }

    protected function compile() {
        
        $this->import('Database');

        if (\Input::get('import') == '1') {
            //$this->Template->import = $this->importFromCSV();
        }
		
        $this->init();

        $this->loadCourses();

        if($_SERVER['REQUEST_METHOD'] == 'POST') {
            $blnSaveSuccess = $this->saveWishes();
            
            if($blnSaveSuccess) $this->Template->saveSuccess = true;
            else $this->Template->saveSuccess = false;
        }

        $this->loadCourses();

        if (\Input::get('export') == '1') {
            //$this->Template->export = $this->exportToCSV();
        }

        $this->Template->arrCourses = $this->sortElectablesToStart($this->arrCourses);

        $this->Template->finalWish = $this->getFinalWish();
        $this->Template->arrUserWishes = $this->intUserGrade > -1 ? $this->getUserWishes() : null;
        $this->Template->intMaxWishes = $this->intMaxWishes;
		$this->Template->vote = $this->intUserGrade > -1 && $this->ownCourseId == -1;
		
        $objMember = \FrontendUser::getInstance();
        $this->Template->teacher = $objMember->teacher;

        $this->Template->ownCourseId = $this->ownCourseId;
        $this->Template->arrOwnCourse = ($this->ownCourseId > -1) ? $this->arrCourses[$this->ownCourseId] : null;
    }
/*
    public function generateAjax() {

        $this->init();

        if ($this->intUserGrade < 0) {
            return false;
        }

        $wish = (int) \Input::get('wish');
        if ($wish < 0 || $wish > 2) {
            return false;
        }
  
        $id = \Input::get('course');
        if ($id != -1) {
            $arrCourse = $this->getCourse($id);
            if (!(is_array($arrCourse) && $this->isElectable($arrCourse))) {
                return false;
            }
            $id = $arrCourse['id'];
        }

        $arrUserWishes = $this->getUserWishes();
        for ($i = 0; $i < count($arrUserWishes); $i++) {
            if ($arrUserWishes[$i] == $id) {
                $arrUserWishes[$i] = -1;
            }
        }
        $arrUserWishes[$wish] = $id;

        $wishes = implode(',', $arrUserWishes);
        $this->Database->prepare("INSERT INTO tl_sms_course_choice (id, tstamp, wishes) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE wishes = ?")
                ->execute($this->memberId, time(), $wishes, $wishes);

        return $arrUserWishes;
    }
*/

    protected function saveWishes() {
        if ($this->intUserGrade < 0) {
            return false;
        }

        $arrPostFields = array(0,1,2);

        $arrUserWishes = $this->getUserWishes();
        
        foreach ($arrPostFields as $wish) {
            $id = \Input::post($wish);

            //check if course exists and is electable
            if ($id != -1) {
                $arrCourse = $this->getCourse($id);
                if (!(is_array($arrCourse) && $this->isElectable($arrCourse))) {
                    return false;
                }
                $id = $arrCourse['id'];
            }

            //courses cannot be elected multiple times
            for ($i = 0; $i <= 2; $i++) {
                if ($arrUserWishes[$i] == $id) {
                    $arrUserWishes[$i] = -1;
                }
            }
            $arrUserWishes[$wish] = $id;
        }

        $wishes = implode(',', $arrUserWishes);
        $this->Database->prepare("INSERT INTO tl_sms_course_choice (id, tstamp, wishes) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE wishes = ?")
                ->execute($this->memberId, time(), $wishes, $wishes);
        return true;
    }


    protected function getUserWishes() {
		if ($this->intUserGrade > 0) {
            $wishes = $this->Database->prepare("SELECT wishes FROM tl_sms_course_choice WHERE id = ? AND finalWish = -1")
                    ->execute($this->memberId);
            if ($wishes = $wishes->fetchAssoc()) {
                $wishes = explode(',', $wishes['wishes']);
                if (count($wishes) > 2) {
                    return $wishes;
                }
            }
        }
        return array(-1, -1, -1);
    }

    protected function getFinalWish() {
        $finalWish = $this->Database->prepare("SELECT finalWish FROM tl_sms_course_choice WHERE id = ?")
                ->execute($this->memberId)
                ->fetchAssoc();
        $finalWish = $finalWish['finalWish'];
        if (isset($this->arrCourses[$finalWish])) {
            return $this->arrCourses[$finalWish];
        }
        return false;
    }
	protected function getFinalWishesByCourse($courseId) {
        $finalWish = $this->Database->prepare("SELECT finalWish FROM tl_sms_course_choice WHERE finalWish = ?")
                ->execute($courseId)
                ->fetchAssoc();
        $finalWish = $finalWish['finalWish'];
            return count($finalWish);
    }

    protected function hasFinalWish() {
        $finalWish = $this->Database->prepare("SELECT COUNT(*) AS c FROM tl_sms_course_choice WHERE id = ? AND finalWish > -1")
                ->execute($this->memberId)
                ->fetchAssoc();
        return $finalWish['c'] > 0;
    }

    protected function rightOfChoice($objMember) {
        if (!SMS::generalRightOfChoice($objMember)) {
            return false;
        }
        
        if ($obj->ownCourseId > 0) {
            return false;
        }

        if ($this->hasFinalWish()) {
            return false;
        }
        
        return true;
    }

    protected function getCourse($courseId) {
        $course = $this->Database->prepare("SELECT * FROM tl_sms_course WHERE id = ?")
                ->execute($courseId);
        if ($course = $course->fetchAssoc()) {
            return $course;
        }
        return false;
    }

    protected function getLeadingCourseId() {
        $course = $this->Database->prepare("SELECT id FROM tl_sms_course WHERE leader = ? OR coLeader = ? LIMIT 0,1")
                ->execute($this->memberId, $this->memberId);
        if ($course = $course->fetchAssoc()) {
            return $course['id'];
        }
        return -1;
    }

    protected function isElectable($arrCourse) {
		return $this->ownCourseId == -1 
		&& SMS::gradeInCourseRange($arrCourse, $this->intUserGrade) 
		&& $arrCourse['maxStudents'] != $this->getFinalWishesByCourse($arrCourse['id']) 
		&& $arrCourse['maxStudents'] != 0 
		&& $arrCourse['closed'] != 1;
    }

    protected function sortElectablesToStart($arrCourses) {
        $courses = array();

        if ($this->ownCourseId > -1) {
            $courses[] = $arrCourses[$this->ownCourseId];
            foreach ($arrCourses as $course) {
                if ($course['id'] != $this->ownCourseId) {
                    $courses[] = $course;
                }
            }
        } else {
            foreach ($arrCourses as $course) {
                if ($course['electable']) {
                    $courses[] = $course;
				}
			}
			foreach ($arrCourses as $course) {
                if (!$course['electable']) {
                    $courses[] = $course;
				}
			}
        }

        return $courses;
    }

    protected function loadCourses() {
        $arrStudents = WBGym::studentList();

        // get all courses
        $courses = $this->Database->query("SELECT * FROM tl_sms_course ORDER BY name");

       

        while ($course = $courses->fetchAssoc()) {


            // can course be electable by the member?
            $course['electable'] = $this->isElectable($course);

            // initialize wish counts
            $course['wishes'] = array(0, 0, 0);

            // replace leader IDs
            $course['leader'] = WBGym::student($course['leader']);
            $course['coLeader'] = WBGym::student($course['coLeader']);
            $course['teacher'] = WBGym::teacher($course['teacher']);

            $course['freePlaces'] = $course['maxStudents'];
            $course['enrolledStudents'] = array();

            /*if ($course['maxForm'] < 11 && $course['maxForm'] > $course['minForm']) {
                // max
                if ($course['minForm'] > 5)
                    $course['formLimit'] .= $course['minForm'] . ' - ' . $course['maxForm'];
                else
                    $course['formLimit'] .= '5 - ' . $course['maxForm'];
            } else {
                // no max
                if ($course['minForm'] > 5)
                    $course['formLimit'] .= $course['minForm'] . ' - 11';
                else
                    $course['formLimit'] .= $GLOBALS['TL_LANG']['SFW']['all'];
            }*/
			
		  if ($course['maxForm'] > $course['minForm']) {
                // interval
                    $course['formLimit'] .= $course['minForm'] . ' - ' . $course['maxForm'];
          } else {
			   // only for one grade
                    $course['formLimit'] .= $course['minForm'];
		  }

            $this->arrCourses[$course['id']] = $course;
        }

        // for the own course the students are saved too
        if ($this->ownCourseId > 0) {
            $this->arrCourses[$this->ownCourseId]['studentWishes'] = array(array(), array(), array());
        }

        $choices = $this->Database->query("SELECT * FROM tl_sms_course_choice");
        while ($choice = $choices->fetchAssoc()) {
            $finalWish = $choice['finalWish'];
            if (isset($this->arrCourses[$finalWish])) {
                if (isset($arrStudents[$choice['id']])) {
                    $this->arrCourses[$finalWish]['freePlaces']--;
                    $this->arrCourses[$finalWish]['enrolledStudents'][] = $arrStudents[$choice['id']];
                }
            } else {
                $wishes = explode(',', $choice['wishes']);
                for ($i = 0; $i < count($wishes); $i++) {
                    $courseId = $wishes[$i];

                    if (isset($this->arrCourses[$courseId])) {
                        $this->arrCourses[$courseId]['wishes'][$i]++;

                         if ($courseId == $this->ownCourseId) {
		            $this->arrCourses[$this->ownCourseId]['studentWishes'][$i][] = $arrStudents[$choice['id']];
			}
                    }
					
					
                }
            }
        }

    }

    protected function importFromCSV() {
        $arrCourses = array();
        $arrLines = explode("\n", file_get_contents(TL_ROOT . '/files/webteam/smskurse2012.csv'));
        $intLines = count($arrLines);

        $arrStudents = WBGym::getInstance()->getStudentList();

        for ($i = 1; $i < $intLines; $i++) {
            $arrLine = explode(';', $arrLines[$i]);
            $errors = array();
            $course = array(
                'name' => trim($arrLine[0]),
                'minForm' => $arrLine[3],
                'maxForm' => $arrLine[4],
                'maxStudents' => $arrLine[5]
            );

            $teacher = trim($arrLine[1]);
            $course['teacher'] = '';
            if (strlen($teacher)) {
                $matches = $this->findTeachers(substr($teacher, 5));
                switch (count($matches)) {
                    case 0:
                        $errors[] = "Lehrer '" . $teacher . "' nicht gefunden";
                        break;

                    case 1:
                        $course['teacher'] = $matches[0][0];
                        break;

                    default:
                        $errors[] = "Mehrere mögliche Lehrer gefunden: " . implode(', ', array_map('array_pop', $matches));
                }
            }

            $course['leader'] = 869; // Simon Krause (SV)
            $course['coLeader'] = 0;
            $course['students'] = array();
            $leaders = explode("), ", $arrLine[2]);
            for ($j = 0; $j < count($leaders); $j++) {
                $student = $leaders[$j];
                $found = false;

                preg_match('/(\w* \w*) .*/', $student, $matches);
                if (count($matches) > 1) {

                    $matchedStudents = $this->findStudents($arrStudents, $matches[1]);
                    if (count($matchedStudents) == 1) {
                        $found = true;
                        switch ($j) {
                            case 0:
                                $course['leader'] = $matchedStudents[0][0];
                                break;

                            case 1:
                                $course['coLeader'] = $matchedStudents[0][0];
                                break;

                            default:
                                $course['students'][] = $matchedStudents[0][0];
                        }
                    }
                }

                if (!$found) {
                    $errors[] = "Schüler '" . $student . "' nicht gefunden";
                }
            }
            $course['maxStudents'] += count($course['students']);

            $course['description'] = implode("\n", $errors);

            $arrCourses[] = $course;
        }

        $this->Database->query("DELETE FROM tl_sms_course");
        $this->Database->query("DELETE FROM tl_sms_course_choice");
        foreach ($arrCourses as $c) {
            $this->Database->prepare("INSERT INTO tl_sms_course (name, description, leader, coLeader, teacher, minForm, maxForm, maxStudents) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                    ->execute($c['name'], $c['description'], $c['leader'], $c['coLeader'], $c['teacher'], $c['minForm'], $c['maxForm'], $c['maxStudents']);

            $id = $this->Database->query("SELECT MAX(id) as maxId FROM tl_sms_course")->fetchAssoc();
            $id = (int) $id['maxId'];
            foreach ($c['students'] as $stu) {
                $this->Database->prepare("INSERT INTO tl_sms_course_choice (id, finalWish) VALUES (?, ?)")
                        ->execute($stu, $id);
            }
        }

        return $arrCourses;
    }

    protected function findTeachers($strName) {
        $matches = array();

        $arrTeachers = WBGym::getInstance()->getTeacherList();
        foreach ($arrTeachers as $id => $strTeacherName) {
            if (strpos($strTeacherName, $strName) !== false) {
                $matches[] = array($id, $strTeacherName);
            }
        }

        return $matches;
    }

    protected function findStudents($arrStudents, $strName) {
        $matches = array();

        foreach ($arrStudents as $id => $strStudentName) {
            if (strpos($strStudentName, $strName) !== false) {
                $matches[] = array($id, $strStudentName);
            }
        }

        return $matches;
    }

    protected function exportToCSV() {
        $strData = "Name;Leiter;Stellvertretender Leiter;Betreuer;Min Klassenstufe;Max Klassenstufe (inklusive);maximale Teilnehmer;feste Teilnehmer\n";

        foreach ($this->arrCourses as $c) {
            $strData .= implode(';', array($c['name'], $c['leader'], $c['coLeader'], $c['teacher'], $c['minForm'], $c['maxForm'], $c['maxStudents'], implode(', ', $c['enrolledStudents']))) . "\n";
        }

        $objFile = new \File('files/schuelervertretung/sms/courses.csv');
        $objFile->write(utf8_encode($strData));
    }

}

?>