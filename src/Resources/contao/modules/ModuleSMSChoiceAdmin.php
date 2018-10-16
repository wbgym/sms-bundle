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

class ModuleSMSChoiceAdmin extends \BackendModule {

    protected $strTemplate = 'wb_sms_choice_admin';

    protected $csvSeperator = "\t";

    protected function compile() {

        $this->loadLanguageFile('wb_sms_choice_admin');

        $this->Template->studentList = WBGym::studentList();
        $this->arrCourses = WBGym::fetchAll("SELECT c.* FROM tl_sms_course AS c");
        $this->Template->arrCourses = $this->arrCourses;

        if (strlen(\Input::post('removeInvalidWishes'))) {
            $n = $this->removeInvalidWishes();
            $this->Template->removedInvalidWishes = $n . ' ungültige Wünsche gelöscht.';
        }

        if (strlen(\Input::post('autoChoose'))) {
            $stats = $this->autoChoose();
            $this->Template->autoChoose = $stats;
        }

		if (strlen(\Input::post('deleteAutoEnrollments'))) {
            $stats = $this->deleteAutoEnrollments();
            $this->Template->deleteAutoEnrollments = 'Automatische Zuweisungen wurden gelöscht.';
        }

        if (strlen(\Input::post('writeFiles'))) {
            $this->setStudentList();
            $this->writeCourseFile();
            $this->writeStudentFile(true);
            $this->writeStudentFile(false);
            $this->writeTeacherFile();
        }

        $this->Template->lastFinalCourse = -1;

        if (strlen(\Input::post('setFinalWish'))) {

            $student = (int) \Input::post('student');
            $course = (int) \Input::post('course');
            $this->Database->prepare("INSERT INTO tl_sms_course_choice (id, tstamp, finalWish, finalWishAuto) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE finalWish = ?, finalWishAuto = ?")
                    ->execute($student, time(), $course, $course, $course, $course);

            $studentName = WBGym::getInstance()->student($student);
            $this->Template->lastFinalCourse = $course;
            if ($course > 0) {
                $courseName = $this->arrCourses[$course]['name'];
                $this->Template->setFinalWish = $studentName . ' wurde dem Kurs ' . $courseName . ' zugewiesen.';
            } else {
                $this->Template->setFinalWish = $studentName . ' ist nicht länger einem Kurs zugewiesen.';
            }
        }
        if (strlen(\Input::post('setFinalWishAuto'))) {

            $student = (int) \Input::post('student');
            $course = (int) \Input::post('course');
            $this->Database->prepare(
              "INSERT INTO tl_sms_course_choice (id, tstamp, wishes, finalWish, finalWishAuto) VALUES (?, ?, '-1,-1,-1', -1, ?) ON DUPLICATE KEY UPDATE finalWishAuto=?")
                    ->execute($student, time(), $course, $course);

            $studentName = WBGym::getInstance()->student($student);
            $this->Template->lastFinalCourse = $course;
            if ($course > 0) {
                $courseName = $this->arrCourses[$course]['name'];
                $this->Template->setFinalWishAuto = $studentName . ' wurde dem Kurs ' . $courseName . ' zugewiesen.';
            } else {
                $this->Template->setFinalWishAuto = $studentName . ' ist nicht länger einem Kurs zugewiesen.';
            }
        }

        $counts = array(
            'courses' => count($this->arrCourses),
            'leaders' => $this->countLeaders(),
            'students' => $this->countStudents(),
			'coursePlaces' => $this->countCoursePlaces()['all'] . ' (' . $this->countCoursePlaces()['normal'] . ' + ' . $this->countCoursePlaces()['closed'] . ')',
            'enrolledStudents' => $this->countEnrolledStudents(),
            'studentsWithWishes' => $this->countStudentsWithWishes()
        );
		$counts['autoEnrolledStudents'] = $this->countAutoEnrolledStudents();
		//$counts['freePlaces'] = $this->countCoursePlaces()['all'] - $counts['enrolledStudents'] - $counts['autoEnrolledStudents'];
        $counts['studentsThatNeedfinalWishAuto'] = $counts['students'] - $counts['leaders'] - $counts['enrolledStudents'];
        $counts['studentsWithoutCourse'] = $counts['students'] - $counts['leaders'] - $counts['enrolledStudents'] - $counts['autoEnrolledStudents'];
        $finalWishLeaders = $this->findFinalWishLeaders();
		if($finalWishLeaders) {
			foreach ($finalWishLeaders as $i => $entry) {
				$counts['leadersWithFinalWish'][$i]['fixedStudent'] = WBGym::student($entry['stId']);
				$counts['leadersWithFinalWish'][$i]['fixedCourse'] = WBGym::smsCourse($entry['cfId']);
				$counts['leadersWithFinalWish'][$i]['leadingCourse'] = WBGym::smsCourse($entry['cid']);
			}
		}

        $this->Template->counts = $counts;
    }

    protected function autoChoose() {
        // Alles zurücksetzen (
        $this->Database->query("DELETE FROM tl_sms_course_choice WHERE wishes = '' AND finalWish = -1");
        $this->Database->query("UPDATE tl_sms_course_choice SET finalWishAuto = -1");
        $stats = array();

        // Alle Schüler aus der Datenbank holen die wählen dürfen
        // Zum Ende des Schuljahres stehen schon die neuen Schüler in der Datenbank, die dürfen natürlich nicht wählen
        $students = WBGym::getInstance()->fetchAll("SELECT id, grade, formSelector FROM tl_member WHERE student AND grade > 4 AND grade < 11 AND NOT (grade = 6 AND formSelector > 1)");
        $stats['students']['participatingStudents'] = count($students);

        // Kurseliste kopieren und Schülerwünsche auswerten
        // allWishes und wishesLeft merken sich beide die Schüler die sich diesen Kurs wünschen,
        // Schüler in wishesLeft werden jedoch später entfernt sobald sie einem Kurs zugewiesen werden
        $courses = array();

		foreach ($this->arrCourses as $courseId => $course) {
			// Wenn Kurs geschlossen ist, sollen Plätze nicht automatisch verteilt werden
			if ($course['closed']) {
				$course['freePlaces'] = 0;
			}
			else {
				$course['freePlaces'] = $course['maxStudents'];
			}
            $course['allWishes'] = array(array(), array(), array());
            $course['wishesLeft'] = array(array(), array(), array());
            $courses[$courseId] = $course;

            // Kursleiter aus der Schülerliste löschen
            unset($students[$course['leader']]);
            unset($students[$course['coLeader']]);
        }
        $stats['students']['withoutLeaderAndCoLeader'] = count($students);

        $choices = $this->Database->query("SELECT * FROM tl_sms_course_choice");
        $n = 0;
        while ($choice = $choices->fetchAssoc()) {
            $studentId = $choice['id'];
            // Ist der Schüler bereits fest eingetragen, so braucht er nicht
            // weiter beachtet werden, der Platz im Kurs ist belegt
            if ($choice['finalWish'] > -1) {
                $n++;
                if($students[$studentId]) unset($students[$studentId]); else $stats['test'][] = $students[$studentId]; //wenn Schüler nicht in der Liste ist, bedeutet das, dass er außerhalb 5-10 ist
                $courses[$choice['finalWish']]['freePlaces']--;
            } else {
                // Wenn der Schüler nicht schon in der Schülerliste drin steht ist er nicht berechtigt zu wählen
                if (!isset($students[$studentId])) {
                    $stats['error'][] = 'Schüler ' . $studentId . ' ist nicht berechtigt zu wählen.';
                    continue;
                }
                // $choice['wishes'] = Erstwunsch, Zweitwunsch, Drittwunsch
                $wishes = explode(',', $choice['wishes']);
                $students[$studentId]['wishes'] = $wishes;
                for ($i = 0; $i < count($wishes); $i++) {
                    $w = $wishes[$i];
                    if ($w > 0) {
                        $courses[$w]['allWishes'][$i][] = $studentId;
                        $courses[$w]['wishesLeft'][$i][] = $studentId;
                    }
                }
            }
        }
        $stats['enrolledStudents'] = $n;

        // Schüler initialisieren
        foreach ($students as $studentId => $student) {
            $students[$studentId]['finalWishAuto'] = -1;
            if (!isset($student['wishes'])) {
                $students[$studentId]['wishes'] = array(-1, -1, -1);
            }
        }
        $stats['students']['withoutFixedMembers'] = count($students);

        // Erfolgreich erfüllte Erstwünsche/Zweitwünsche/Drittwünsche merken
        $stats['byWish'] = array(0, 0, 0);

        // Erst alle Erstwünsche, dann alle Zweitwünsche und dann die Dirttwünsche abarbeiten
        for ($w = 0; $w < 3; $w++) {
            foreach ($courses as $courseId => $course) {
                $free = $course['freePlaces'];
                $wishes = $course['wishesLeft'][$w];

                // Wünsche immer mischen, eigentlich wäre es nur notwendig
                // wenn es mehr Schülerwünsche als freie Plätze gibt, allerdings, hilft das shuffeln
                // wieder einen geordneten Array zu erzeugen (mit fortlaufenden Index)
                shuffle($wishes);

                // freie Plätze aktuell halten
                $wishesCount = count($wishes);
                if ($wishesCount >= $free) {
                    $courses[$courseId]['freePlaces'] = 0;
                } else {
                    $courses[$courseId]['freePlaces'] = $free - $wishesCount;
                }

                $maxIndex = min($wishesCount, $free);
                for ($j = 0; $j < $maxIndex; $j++) {
                    $studentId = $wishes[$j];
                    $student = &$students[$studentId];
                    $student['finalWishAuto'] = $courseId;
                    $stats['byWish'][$w]++;

                    // remove other wishes of the students from the course array
                    for ($t = $w + 1; $t < 3; $t++) {
                        $cid = $student['wishes'][$t];
                        if ($cid > -1) {
                            $pos = array_search($studentId, $courses[$cid]['wishesLeft'][$t]);
                            unset($courses[$cid]['wishesLeft'][$t][$pos]);
                        }
                    }
                }
            }
        }
        $stats['students']['afterWishEvaluation'] = count($students);

        // Nun sind noch 2 Schülergruppen übrig,
        // solche die gewählt haben und deren Wünsche nicht erfüllt wurden und welche die gar nicht gewählt haben
        // erst die die gewählt haben
        $n = 0;
        $emptyWishes = array(-1, -1, -1);
        foreach ($students as $studentId => $student) {
            if ($student['finalWishAuto'] == -1 && $student['wishes'] != $emptyWishes) {
                $scores = $this->calculateCourseScores($courses, $student, $students, false);
                $bestScore = $this->arrayPopMax($scores);

                if ($bestScore === false) {
                    $stats['error'][] = WBGym::student($studentId) . ' konnte keinen Kurs zugewiesen werden.';
                } else {
                    $n++;
                    $courseId = $bestScore[0];
                    $students[$studentId]['finalWishAuto'] = $courseId;
                    $courses[$courseId]['freePlaces']--;
                }
            }
        }
        $stats['byScoreWithWishes'] = $n;

        // nun sind nur noch solche übrig die nicht gewählt haben
        $n = 0;
        foreach ($students as $studentId => $student) {
            if ($student['finalWishAuto'] == -1) {
                $scores = $this->calculateCourseScores($courses, $student, $students, true);
                $bestScore = $this->arrayPopMax($scores);

                if ($bestScore === false) {
                    $stats['error'][] = WBGym::student($studentId) . ' konnte keinen Kurs zugewiesen werden.';
                } else {
                    $n++;
                    $courseId = $bestScore[0];
                    $students[$studentId]['finalWishAuto'] = $courseId;
                    $courses[$courseId]['freePlaces']--;
                }
            }
        }
        $stats['byScore'] = $n;

        foreach ($students as $studentId => $student) {
            $this->Database->prepare('INSERT INTO tl_sms_course_choice (id, tstamp, finalWishAuto) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE finalWishAuto = ?')
                    ->execute($studentId, time(), $student['finalWishAuto'], $student['finalWishAuto']);
        }

        $this->Database->query("UPDATE tl_sms_course_choice SET finalWishAuto = finalWish WHERE finalWishAuto = -1");

        return $stats;
    }

    private function arrayPopMax(&$array) {
        if (count($array) == 0) {
            return false;
        }

        $first = true;
        $maxIndex = -1;
        $maxValue = 0;
        foreach ($array as $key => $value) {
            if ($first || $maxValue < $value) {
                $first = false;
                $maxIndex = $key;
                $maxValue = $value;
            }
        }

        if ($first) {
            return false;
        }

        unset($array[$key]);
        return array($maxIndex, $maxValue);
    }

    private function calculateCourseScores($courses, $arrStudent, $students, $balance) {
        $scores = array();
        $wishes = $arrStudent['wishes'];

        // Scores initialisieren
        foreach ($courses as $courseId => $course) {
            $courses[$courseId]['elec'] = $course['freePlaces'] > 0 && SMS::gradeInCourseRange($course, $arrStudent['grade']);
            if ($courses[$courseId]['elec']) {
                $scores[$courseId] = 1;
            }
        }

        // Scores zählen
        for ($w = 0; $w < 3; $w++) {
            if ($wishes[$w] > -1) {
                $course = $courses[$wishes[$w]];
                if ($course['elec']) {
                    for ($i = 0; $i < 3; $i++) {
                        foreach ($course['allWishes'][$i] as $otherStudentId) {
                            $otherStudent = $students[$otherStudentId];
                            for ($t = 0; $t < 3; $t++) {
                                $otherStudentWish = $otherStudent['wishes'][$t];
                                if ($otherStudentWish > -1 && $courses[$otherStudentWish]['elec']) {
                                    $scores[$otherStudentWish] += 4 - $w;
                                }
                            }
                        }
                    }
                }
            }
        }

        if ($balance) {
            foreach ($scores as $courseId => $score) {
                $scores[$courseId] = $score * $courses[$courseId]['freePlaces'] / $courses[$courseId]['maxStudents'];
            }
        }

        return $scores;
    }

    protected function isLeader($studentId) {
        foreach ($this->arrCourses as $course) {
            if ($studentId == $course['leader'] || $studentId == $course['coLeader']) {
                return true;
            }
        }

        return false;
    }

    protected function removeInvalidWishes() {
        $n = 0;

        $choices = $this->Database->query("SELECT * FROM tl_sms_course_choice JOIN tl_member USING(id) WHERE finalWish < 1 AND wishes <> ''");
        while ($choice = $choices->fetchAssoc()) {
            $delete = false;
            $wishes = false;

            if (!SMS::generalRightOfChoice($choice)) {
                $delete = true;
            } else if ($this->isLeader($choice)) {
                $delete = true;
            } else {
                $old = $n;
                $w = explode(',', $choice['wishes']);

                for ($i = 0; $i < count($w); $i++) {
                    if ($w[$i] > -1) {
                        if (!SMS::gradeInCourseRange($this->arrCourses[$w[$i]], $choice['grade'])) {
                            $n++;
                            $w[$i] = -1;
                        }
                    }
                }

                if ($n > $old) {
                    $wishes = $w;
                }
            }


            // lösche Eintrag komplett wenn Schüler nicht wahlberechtig sind
            if ($delete) {
                $this->Database->prepare("DELETE FROM tl_sms_course_choice WHERE id = ?")
                        ->execute($choice['id']);
            }

            if (is_array($wishes)) {
                $this->Database->prepare("UPDATE tl_sms_course_choice SET wishes = ? WHERE id = ?")
                        ->execute(implode(',', $wishes), $choice['id']);
            }
        }
        return $n;
    }

	/*
	* Find Students that are leader/coLeader of a course and have finalWish at the same time
	*/
	protected function findFinalWishLeaders() {
		$students = $this->Database->query("SELECT cou.id AS cid, cou.coLeader, cou.leader, ch.id AS stId FROM tl_sms_course AS cou JOIN tl_sms_course_choice AS ch ON cou.coLeader = ch.id OR ch.id = cou.leader");

		if($students) {
			$n = 0;
			while($student = $students->fetchAssoc()) {
				$fixedCourse = $this->Database->prepare("SELECT finalWish AS cfid FROM tl_sms_course_choice WHERE id=?")->execute($student['stId'])->fetchAssoc();
				$arrStudents[$n] = $student;
				$arrStudents[$n]['cfId'] = $fixedCourse['cfid'];
				$n++;
			}
			return $arrStudents;
		}
		return false;
	}

    protected function setStudentList() {
        $students = array();

        $studs = $this->Database->query("SELECT id, firstname, lastname, grade, formSelector FROM tl_member WHERE student = 1");
        while ($stud = $studs->fetchAssoc()) {
            $stud['name'] = WBGym::studentToString($stud);
            $stud['form'] = $stud['grade'];
            if ($stud['formSelector'] != 0) {
                $stud['form'] .= '/' . $stud['formSelector'];
            }
            $students[$stud['id']] = $stud;
        }
        $this->arrStudents = $students;
    }

	protected function deleteAutoEnrollments() {
		$this->Database->query("UPDATE tl_sms_course_choice SET finalWishAuto = -1 WHERE finalWish = -1 AND finalWishAuto > -1");
		return true;
	}

    protected function writeCourseFile() {
        $strData = implode($this->csvSeperator, array('Name', 'Leiter', 'Stellvertretender Leiter', 'Betreuer', 'Min Klassenstufe', 'Max Klassenstufe (inklusive)', 'maximale Teilnehmer')) . "\n";

        $cs = $this->Database->query("SELECT id FROM tl_sms_course ORDER BY name");
        while ($cid = $cs->fetchAssoc()) {
            $course = $this->arrCourses[$cid['id']];
            $leader = $this->arrStudents[$course['leader']]['name'];
            $coLeader = $this->arrStudents[$course['coLeader']]['name'];
            $teacher = WBGym::teacher($course['teacher']);
            $strData .= implode($this->csvSeperator, array($course['name'], $leader, $coLeader, $teacher, $course['minForm'], $course['maxForm'], $course['maxStudents'])) . "\n";
        }

        $objFile = new \File('files/schuelervertretung/sms/courses.csv');
        $objFile->write($strData);
    }

    protected function writeStudentFile($withWishes) {
        if ($withWishes) {
            $strData = implode($this->csvSeperator, array('Kurs', 'Vorname', 'Nachname', 'Klasse', 'Kursleiter', 'Erstwunsch', 'Zweitwunsch', 'Drittwunsch','alter Kurs')) . "\n";
        } else {
            $strData = implode($this->csvSeperator, array('Kurs', 'Vorname', 'Nachname', 'Klasse', 'Kursleiter','alter Kurs')) . "\n";
        }

        $lastCourse = -1;
        $students = $this->Database->query("SELECT m.id AS studentId, cc.wishes, c.id AS courseId, e.current_course AS oldCourse FROM tl_sms_course_choice AS cc JOIN tl_member AS m USING(id) JOIN tl_sms_course AS c ON c.id = cc.finalWishAuto LEFT JOIN tl_sms_exchange AS e ON m.id = e.student ORDER BY c.name, m.grade, m.firstname");
        while ($stud = $students->fetchAssoc()) {
            $student = $this->arrStudents[$stud['studentId']];
            $course = $this->arrCourses[$stud['courseId']];
			$oldCourse = $stud['oldCourse'] != $stud['courseId'] ? $this->arrCourses[$stud['oldCourse']] : null; //old course which has changed (exchange module)

            if ($course['id'] != $lastCourse) {
                // neuer Kurs
                $lastCourse = $course['id'];

                if ($course['leader'] > -1) {
                    $leader = $this->arrStudents[$course['leader']];
                    $strData .= implode($this->csvSeperator, array($course['name'], $leader['firstname'], $leader['lastname'], $leader['form'], 'Kursleiter')) . "\n";
                }
                if ($course['coLeader'] > -1) {
                    $coLeader = $this->arrStudents[$course['coLeader']];
                    $strData .= implode($this->csvSeperator, array($course['name'], $coLeader['firstname'], $coLeader['lastname'], $coLeader['form'], 'Stellvertreter')) . "\n";
                }
            }

            if ($withWishes) {
                $wishes = explode(',', $stud['wishes']);
                for ($i = 0; $i < 3; $i++) {
                    $wishes[$i] = $wishes[$i] > -1 ? $this->arrCourses[$wishes[$i]]['name'] : '';
                }
                $strData .= implode($this->csvSeperator, array($course['name'], $student['firstname'], $student['lastname'], $student['form'], '', $wishes[0], $wishes[1], $wishes[2], $stud['oldCourse'])) . '' . "\n";
            } else {
                $strData .= implode($this->csvSeperator, array($course['name'], $student['firstname'], $student['lastname'], $student['form'], '', $oldCourse['name'])) . '' . "\n";
            }
        }

        if ($withWishes) {
            $objFile = new \File('files/schuelervertretung/sms/students_with_wishes.csv');
        } else {
            $objFile = new \File('files/schuelervertretung/sms/students.csv');
        }
        $objFile->write($strData);
    }

    protected function writeTeacherFile() {
        // Anzahl Schüler im Kurs ergänzen
        $courses = array();
        foreach ($this->arrCourses as $courseId => $course) {
            $course['students'] = 0;
            $courses[$courseId] = $course;
        }
        $choices = $this->Database->query("SELECT finalWishAuto FROM tl_sms_course_choice");
        while ($choice = $choices->fetchAssoc()) {
            $fwa = $choice['finalWishAuto'];
            if ($fwa > -1) {
                $courses[$fwa]['students']++;
            }
        }

        $strData = implode($this->csvSeperator, array('Lehrer', 'Kurs', 'Kursleiter', 'Stellvertretender Kursleiter', 'Anzahl Schüler im Kurs')) . "\n";

        $teachers = $this->Database->query("SELECT id FROM tl_member WHERE teacher ORDER BY gender, lastname, firstname");
        while ($teacher = $teachers->fetchAssoc()) {
            $teacherId = $teacher['id'];
            $teacherName = WBGym::teacher($teacherId);

            $courseId = $this->courseIdFromTeacher($teacherId);
            if ($courseId > -1) {
                $course = $courses[$courseId];
                $leader = $course['leader'] > -1 ? $this->arrStudents[$course['leader']]['name'] : '';
                $coLeader = $course['coLeader'] > -1 ? $this->arrStudents[$course['coLeader']]['name'] : '';
                $strData .= implode($this->csvSeperator, array($teacherName, $course['name'], $leader, $coLeader, $course['students'])) . "\n";
            } else {
                $strData .= implode($this->csvSeperator, array($teacherName, '', '', '', '')) . "\n";
            }
        }

        $objFile = new \File('files/schuelervertretung/sms/teachers.csv');
        $objFile->write($strData);
    }

    protected function courseIdFromTeacher($teacherId) {
        foreach ($this->arrCourses as $courseId => $course) {
            if ($course['teacher'] == $teacherId) {
                return $courseId;
            }
        }

        return -1;
    }

    protected function countCoursePlaces() {
        $sum = array();

        foreach ($this->arrCourses as $course) {
            if(!$course['closed']) $sum['normal'] += $course['maxStudents'];
			else $sum['closed'] += $course['maxStudents'];
        }
		$sum['all'] = $sum['normal'] + $sum['closed'];

        return $sum;
    }

    protected function countEnrolledStudents() {
        $c = $this->Database->query("SELECT COUNT(*) AS c FROM tl_sms_course_choice WHERE finalWish > -1")
                ->fetchAssoc();
        return $c['c'];
    }

    protected function countAutoEnrolledStudents() {
        $c = $this->Database->query("SELECT COUNT(*) AS c FROM tl_sms_course_choice WHERE finalWish = -1 AND finalWishAuto > -1")
                ->fetchAssoc();
        return $c['c'];
    }

    protected function countLeaders() {
        $n = 0;
        foreach ($this->arrCourses as $course) {
            if ($course['leader'] > 0 && WBGym::student($course['leader'])) {
                $n++;
            }
            if ($course['coLeader'] > 0) {
                $n++;
            }
        }
        return $n;
    }

    protected function countStudents() {
        $underGrade11 = $this->Database->query("SELECT COUNT(*) AS n FROM tl_member WHERE student AND grade > 4 AND grade < 11 AND (grade <> 6 OR formSelector = 1)")
                ->fetchAssoc();

        $over10Leaders = $this->Database->query("SELECT COUNT(*) AS n FROM tl_sms_course AS c JOIN tl_member AS m ON (c.leader = m.id OR c.coLeader = m.id) WHERE m.grade > 10")
                ->fetchAssoc();
        $over10Enrolled = $this->Database->query("SELECT COUNT(*) AS n	FROM tl_sms_course_choice AS c JOIN tl_member AS m USING(id) WHERE m.grade > 10")
                ->fetchAssoc();

        $sum = $underGrade11['n'] + $over10Leaders['n'] + $over10Enrolled['n'];
        return $sum . ' (' . $underGrade11['n'] . ' + ' . $over10Leaders['n'] . ' + ' . $over10Enrolled['n'] . ')';
    }

    protected function countStudentsWithWishes() {
        $c = $this->Database->query("SELECT COUNT(*) AS c FROM tl_sms_course_choice WHERE wishes <> '' AND finalWish = -1")
                ->fetchAssoc();
        return $c['c'];
    }

}

function println($str) {
    $args = func_get_args();
    for ($i = 0; $i < count($args); $i++) {
        echo $args[$i] . '<br/>';
    }
}

function printArray($array) {
    echo '<pre>';
    print_r($array);
    echo '</pre>';
}

?>
