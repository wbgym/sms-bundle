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
 * Statistics
 */
$GLOBALS['TL_LANG']['wb_sms_choice_admin']['courses']					= array('Kurse', '');
$GLOBALS['TL_LANG']['wb_sms_choice_admin']['leaders']					= array('Kursleiter', 'nur Kursleiter und Stellvertreter');
$GLOBALS['TL_LANG']['wb_sms_choice_admin']['students']					= array('Schüler', "Anzahl Schüler die bei SMS mitmachen, also alle die Kurse wählen sollen plus Kursleiter und fest eingetragende Schüler.\n(5-10 Klässler + Kursleiter aus Klassenstufe 11/12 + fest eingetragen aus Klassenstufe 11/12)");
$GLOBALS['TL_LANG']['wb_sms_choice_admin']['enrolledStudents']			= array('fest eingetragende Schüler', 'Schüler für die der Kurs bereits feststeht, also nur solche die mit einen Kurs leiten aber weder Kursleiter noch dessen Stellvertreter sind');
$GLOBALS['TL_LANG']['wb_sms_choice_admin']['autoEnrolledStudents']		= array('automatisch eingetragende Schüler', '');
$GLOBALS['TL_LANG']['wb_sms_choice_admin']['studentsWithWishes']		= array('Schüler mit Wünschen', '');
$GLOBALS['TL_LANG']['wb_sms_choice_admin']['coursePlaces']				= array('Kursplätze', 'Kursplätze in frei wählbaren Kursen + Plätze in geschlossenen Kursen');
$GLOBALS['TL_LANG']['wb_sms_choice_admin']['freePlaces']				= array('freie Kursplätze', '');
$GLOBALS['TL_LANG']['wb_sms_choice_admin']['studentsWithoutCourse']		= array('Schüler ohne Kurs', '');
$GLOBALS['TL_LANG']['wb_sms_choice_admin']['studentsThatNeedfinalWishAuto'] = array('Schüler für die automatisch ein Kurs bestimmt werden muss.');

/**
 * Actions
 */
$GLOBALS['TL_LANG']['wb_sms_choice_admin']['deleteInvalidCourseChoices']	= 'Ungültige Kurswünsche löschen';
$GLOBALS['TL_LANG']['wb_sms_choice_admin']['deleteAutoEnrollments']	= 'Automatische Zuteilungen löschen';
$GLOBALS['TL_LANG']['wb_sms_choice_admin']['autoChoose']					= 'Automatisch Kurse bestimmen';
$GLOBALS['TL_LANG']['wb_sms_choice_admin']['setFinalWish']					= 'Schüler dem Kurs zuweisen';
$GLOBALS['TL_LANG']['wb_sms_choice_admin']['setFinalWishAuto']					= 'Schüler dem Kurs einmalig zuweisen';
$GLOBALS['TL_LANG']['wb_sms_choice_admin']['writeFiles']					= 'Dateien erstellen';

?>
