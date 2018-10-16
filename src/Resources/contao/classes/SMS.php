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

class SMS {

	public static function generalRightOfChoice($member) {
		if (!is_array($member)) {
			$member = array('student' => $member->student, 'grade' => $member->grade, 'formSelector' => $member->formSelector);
		}

		// nur Schüler dürfen wählen
		if (!$member['student']) {
			return false;
		}

		// Schüler der 11. und 12. Klasse nehmen nicht an der Kurswahl teil
		if ($member['grade'] > 10) {
			return false;
		}

		// Schüler die bereits eingetragend sind aber erst ab nächsten Schuljahr an der Schule sind, dürfen auch nicht wählen
		if ($member['grade'] < 5  || ($member['grade'] == 6 && $member['formSelector'] > 1)) {
			return false;
		}

		return true;
	}

	public static function gradeInCourseRange($arrCourse, $intGrade) {
		if ($intGrade == -1) {
			return false;
		}

		if ($intGrade < $arrCourse['minForm']) {
			return false;
		}

		if ($arrCourse['maxForm'] >= $arrCourse['minForm'] && $intGrade > $arrCourse['maxForm']) {
			return false;
		}

		return true;
	}

}

?>
