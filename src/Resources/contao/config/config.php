<?php

/**
 * WBGym
 *
 * Copyright (C) 2008-2013 Webteam Weinberg-Gymnasium Kleinmachnow
 *
 * @package 	WGBym
 * @author 		Marvin Ritter <marvin.ritter@gmail.com>
 * @license 	http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */

/*
 * Back end modules
 */

$GLOBALS['TL_CSS'][] = 'bundles/wbgymsms/style.css';

array_insert($GLOBALS['BE_MOD'], 2, array('sms' => array(
	'sms_choice_admin' => array(
		'callback'	=> 'WBGym\ModuleSMSChoiceAdmin',
	),
	'sms_courses' => array(
		'tables'	=> array('tl_sms_course'),
	),
	'sms_courses_wishes' => array(
		'tables'	=> array('tl_sms_course_choice'),
	),
	'sms_exchange' => array(
		'tables'	=> array('tl_sms_exchange'),
	)

)));

/*
 * Front end modules
 */
$GLOBALS['FE_MOD']['sms']['wb_sms_course_choice'] = 'WBGym\ModuleSMSCourseChoice';
$GLOBALS['FE_MOD']['sms']['wb_sms_exchange']  = 'WBGym\ModuleSMSExchange';
$GLOBALS['FE_MOD']['sms']['wb_sms_course_add'] = 'WBGym\ModuleSMSCourseAdd';
/*
 * Hooks
 */
$GLOBALS['TL_HOOKS']['ajax'][]			= array('WBGym\ModuleSMSCourseChoice', 'generateAjax');

?>
