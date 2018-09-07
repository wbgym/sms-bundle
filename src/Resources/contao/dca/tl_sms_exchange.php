<?php

/**
 * WBGym
 * 
 * Copyright (C) 2008-2013 Webteam Weinberg-Gymnasium Kleinmachnow
 * 
 * @package 	WGBym
 * @author 		Johannes Cram <j-cram@gmx.de>
 * @license 	http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */

/**
 * Table tl_sms_course 
 */
$GLOBALS['TL_DCA']['tl_sms_exchange'] = array(
	// Config
	'config' => array(
		'dataContainer'		=> 'Table',
		'enableVersioning'	=> true,
		'closed'				=> true,
		'sql' 				=> array(
			'keys' => array(
				'id' => 'primary'
			)
		)
	),

	// List
	'list' => array(
		'sorting' => array(
			'mode'					=> 1,
			'fields'					=> array('tstamp'),
			'flag'						=> 2,
			'panelLayout'			=> 'filter;search,limit'
		),
		'label' => array(
			'fields'				=> array('tstamp','current_course', 'student', 'new_course','partner'),
			'showColumns'		=> true,
			'label_callback'		=> array('tl_sms_exchange', 'replaceIds')
		),
		'global_operations' => array(
			'all' => array(
				'label'				=> &$GLOBALS['TL_LANG']['MSC']['all'],
				'href'				=> 'act=select',
				'class'				=> 'header_edit_all',
				'attributes'		=> 'onclick="Backend.getScrollOffset();" accesskey="e"'
			)
		),
		'operations' => array(
			'edit' => array(
				'label'				=> &$GLOBALS['TL_LANG']['tl_sms_exchange']['edit'],
				'href'				=> 'act=edit',
				'icon'				=> 'edit.gif'
			),
			'delete' => array(
				'label'				=> &$GLOBALS['TL_LANG']['tl_sms_exchange']['delete'],
				'href'				=> 'act=delete',
				'icon'				=> 'delete.gif',
				'attributes'			=> 'onclick="if (!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\')) return false; Backend.getScrollOffset();"'
			),
			'show' => array(
				'label'				=> &$GLOBALS['TL_LANG']['tl_sms_exchange']['show'],
				'href'				=> 'act=show',
				'icon'				=> 'show.gif'
			)
		)
	),

	// Palettes
	'palettes' => array(
		'__selector__'			=> array(''),
		'default'				=> '{student_header},student,current_course;{wish_header},cwish1,cwish2,cwish3;{status_header},status'
	),

	// Fields
	'fields' => array(
		'id' => array(
			'sql'				=> "int(10) unsigned NOT NULL auto_increment"
		),
		'tstamp' => array(
			'label'				=> &$GLOBALS['TL_LANG']['tl_sms_exchange']['tstamp'],
			'sql'				=> "int(10) unsigned NOT NULL default '0'"
		),
		'student' => array(
			'label'				=> &$GLOBALS['TL_LANG']['tl_sms_exchange']['student'],
			'exclude'			=> false,
			'search'			=> true,
			'inputType'			=> 'select',
			'options_callback'	=> array('WBGym\WBGym', 'studentList'),
			'foreignKey'		=> 'tl_member.username',
			'eval'				=> array('chosen' => true, 'includeBlankOption' => true, 'tl_class' => 'w50'),
			'sql'				=> "int(10) unsigned NOT NULL default '0'"
		),
		'current_course' => array(
			'label'				=> &$GLOBALS['TL_LANG']['tl_sms_exchange']['current_course'],
			'exclude'			=> false,
			'search'			=> true,
			'filter'			=> true,
			'inputType'			=> 'select',
			'options_callback'	=> array('WBGym\WBGym', 'smsCourseList'),
			'foreignKey'		=> 'tl_sms_course.name',
			'eval'				=> array('readonly' => true, 'disabled' => true, 'tl_class' => 'w50'),
			'sql'				=> "int(10) unsigned NOT NULL default '0'"
		),
		'cwish1' => array(
			'label'				=> &$GLOBALS['TL_LANG']['tl_sms_exchange']['cwish1'],
			'exclude'			=> false,
			'inputType'			=> 'select',
			'options_callback'	=> array('WBGym\WBGym', 'smsCourseList'),
			'foreignKey'		=> 'tl_sms_course.name',
			'eval'				=> array('chosen' => true, 'includeBlankOption' => true, 'tl_class' => 'w50'),
			'sql'				=> "int(10) unsigned NOT NULL default '0'"
		),
		'cwish2' => array(
			'label'				=> &$GLOBALS['TL_LANG']['tl_sms_exchange']['cwish2'],
			'exclude'			=> false,
			'inputType'			=> 'select',
			'options_callback'	=> array('WBGym\WBGym', 'smsCourseList'),
			'foreignKey'		=> 'tl_sms_course.name',
			'eval'				=> array('chosen' => true, 'includeBlankOption' => true, 'tl_class' => 'w50'),
			'sql'				=> "int(10) unsigned NOT NULL default '0'"
		),
		'cwish3' => array(
			'label'				=> &$GLOBALS['TL_LANG']['tl_sms_exchange']['cwish3'],
			'exclude'			=> false,
			'inputType'			=> 'select',
			'options_callback'	=> array('WBGym\WBGym', 'smsCourseList'),
			'foreignKey'		=> 'tl_sms_course.name',
			'eval'				=> array('chosen' => true, 'includeBlankOption' => true, 'tl_class' => 'w50'),
			'sql'				=> "int(10) unsigned NOT NULL default '0'"
		),
		'status' => array(
			'label'				=> &$GLOBALS['TL_LANG']['tl_sms_exchange']['status'],
			'exclude'			=> false,
			'inputType'			=> 'select',
			'options'			=> array(0,1,2),
			'reference'			=> &$GLOBALS['TL_LANG']['tl_sms_exchange']['status_labels'],
			'eval'				=> array('tl_class' => 'w50'),
			'sql'				=> "int(10) unsigned NOT NULL default '0'"
		),
		'partner' => array(
			'label'				=> &$GLOBALS['TL_LANG']['tl_sms_exchange']['partner'],
			'exclude'			=> false,
			'inputType'			=> 'select',
			'options_callback'	=> array('WBGym\WBGym', 'studentList'),
			'foreignKey'		=> 'tl_member.username',
			'eval'				=> array('chosen' => true, 'includeBlankOption' => true, 'tl_class' => 'w50'),
			'sql'				=> "int(10) unsigned NOT NULL default '0'"
		),
		'new_course' => array(
			'label'				=> &$GLOBALS['TL_LANG']['tl_sms_exchange']['new_course'],
			'exclude'			=> false,
			'inputType'			=> 'select',
			'options_callback'	=> array('WBGym\WBGym', 'smsCourseList'),
			'foreignKey'		=> 'tl_sms_course.name',
			'eval'				=> array('chosen' => true, 'includeBlankOption' => true, 'tl_class' => 'w50'),
			'sql'				=> "int(10) unsigned NOT NULL default '0'"
		)
	)
);

class tl_sms_exchange extends Backend
{
	public function replaceIds($row, $label, DataContainer $dc, $args) {		
		
		//check date
		if(date('Ymd') == date('Ymd', $row['tstamp'])) {
			$strChanged = 'Heute, ' . date('H:i', $row['tstamp']);
		}
		elseif(intval(date('Ymd'))-1 == intval(date('Ymd', $row['tstamp']))) {
			$strChanged = 'Gestern, ' . date('H:i', $row['tstamp']);
		}
		elseif(intval(date('Ymd'))-2 == intval(date('Ymd', $row['tstamp']))) {
			$strChanged = 'Vor 2 Tagen';
		}
		elseif(intval(date('Ymd'))-3 == intval(date('Ymd', $row['tstamp']))) {
			$strChanged = 'Vor 3 Tagen';
		}
		else {
			$strChanged = date('d.m.Y',$row['tstamp']);
		}
		
		$args[0] = $strChanged;
		$args[1] = WBGym\WBGym::smsCourse($row['current_course']);
		$args[2] = WBGym\WBGym::student($row['student']) ;
		if($row['new_course'] == 0) 
			$args[3] = '<span style="color:red">&#10007;</span>';
		else 
			$args[3] = '<span style="color:green">&#10003;</span> '. WBGym\WBGym::smsCourse($row['new_course']);
		
		if($row['partner'] == 0) 
			$args[4] = 'kein Partner';
		else
			$args[4] = 'mit Partner';
		

		return $args;
	}
}

?>