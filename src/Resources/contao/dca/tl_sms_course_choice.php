<?php

/**
 * WBGym
 * 
 * Copyright (C) 2008-2015 Webteam Weinberg-Gymnasium Kleinmachnow
 * 
 * @package 	WGBym
 * @author 		Johannes Cram <j-cram@gmx.de>
 * @license 	http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */

/**
 * Table tl_sms_course_choice
 */
$GLOBALS['TL_DCA']['tl_sms_course_choice'] = array(
	// Config
	'config' => array(
		'dataContainer'		=> 'Table',
		'enableVersioning'	=> false,
		'closed'			=> true,
		'sql' 				=> array(
			'keys' => array(
				'id' => 'primary'
			)
		)
	),
	// List
	'list' => array(
		'sorting' => array(
			'mode'					=> 2,
			'fields'				=> array('finalWishAuto'),
			'flag'					=> 4,
			'filter'				=> array(array('finalWishAuto>?', '-1')),
			'panelLayout'			=> 'sort,filter;search,limit',
		),
		'label' => array(
			'fields'				=> array('finalWishAuto', 'finalWish', 'id'),
			'showColumns'			=> true,
			'label_callback'		=> array('tl_sms_course_choice', 'replaceIds')
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
			'delete' => array(
				'label'				=> &$GLOBALS['TL_LANG']['tl_sms_course_choice']['delete'],
				'href'				=> 'act=delete',
				'icon'				=> 'delete.gif',
				'attributes'			=> 'onclick="if (!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\')) return false; Backend.getScrollOffset();"'
			),
			'show' => array(
				'label'				=> &$GLOBALS['TL_LANG']['tl_sms_course_choice']['show'],
				'href'				=> 'act=show',
				'icon'				=> 'show.gif'
			)
		)
	),

	// Palettes
	'palettes' => array(
		'__selector__'			=> array(''),
		'default'				=> 'id,finalWishAuto,finalWish'
	),

	// Fields
	'fields' => array(
		'id' => array(
			'search'			=> true,
			'label'				=> &$GLOBALS['TL_LANG']['tl_sms_course_choice']['id'],
			'exclude'			=> false,
			'inputType'			=> 'select',
			'options_callback'	=> array('WBGym\WBGym', 'studentList'),
			'foreignKey'		=> 'tl_member.username',
			'eval'				=> array('tl_class' => 'w50'),
			'sql'				=> "int(10) unsigned NOT NULL auto_increment"
		),
		'tstamp' => array(
			'sql'				=> "int(10) unsigned NOT NULL default '0'"
		),
		'wishes' => array(
			'label'				=> &$GLOBALS['TL_LANG']['tl_sms_course_choice']['wishes'],
			'sql'				=> "varchar(64) NOT NULL default ''"
		),
		'finalWish' => array(
			'label'				=> &$GLOBALS['TL_LANG']['tl_sms_course_choice']['finalWish'],
			'exclude'			=> false,
			'inputType'			=> 'select',
			'options_callback'	=> array('tl_sms_course_choice', 'finalWish'),
			'foreignKey'		=> 'tl_sms_course.name',
			'eval'				=> array('chosen' => true, 'includeBlankOption' => true, 'tl_class' => 'w50'),
			'sql'				=> "int(10) NOT NULL default '-1'"
		),
		'finalWishAuto' => array(
			'search'			=> true,
			'filter'			=> true,
			'label'				=> &$GLOBALS['TL_LANG']['tl_sms_course_choice']['finalWishAuto'],
			'foreignKey'		=> 'tl_sms_course.name',
			'eval'				=> array('readonly' => true),
			'sql'				=> "int(10) NOT NULL default '-1'"
		)
	)
);

class tl_sms_course_choice extends Backend
{
	public function replaceIds($row, $label, DataContainer $dc, $args) {		
		$args[0] = WBGym\WBGym::smsCourse($row['finalWishAuto']);
		
		if ($row['finalWish'] > -1) {
			$args[1] = '<span style="color:green">&#10003;</span>'; 
		} else {
			$args[1] = '<span style="color:red">&#10007;</span>'; 
		};
		
		$args[2] = WBGym\WBGym::student($row['id']);
		
		return $args;
	}
	public function finalWish($varValue) {
		if ($varValue > -1) {
			return (array('ja'));
		}
		else{
			return (array('nein'));
		}
	}
}
?>