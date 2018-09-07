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

/**
 * Table tl_sms_course 
 */
$GLOBALS['TL_DCA']['tl_sms_course'] = array(
	// Config
	'config' => array(
		'dataContainer'		=> 'Table',
		'enableVersioning'	=> true,
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
			'fields'				=> array('name'),
			'flag'					=> 1,
			'panelLayout'			=> 'filter;search,limit'
		),
		'label' => array(
			'fields'				=> array('name', 'maxStudents', 'leader', 'teacher'),
			'showColumns'			=> true,
			'label_callback'		=> array('tl_sms_course', 'replaceIds')
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
				'label'				=> &$GLOBALS['TL_LANG']['tl_sms_course']['edit'],
				'href'				=> 'act=edit',
				'icon'				=> 'edit.gif'
			),
			'delete' => array(
				'label'				=> &$GLOBALS['TL_LANG']['tl_sms_course']['delete'],
				'href'				=> 'act=delete',
				'icon'				=> 'delete.gif',
				'attributes'			=> 'onclick="if (!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\')) return false; Backend.getScrollOffset();"'
			),
			'show' => array(
				'label'				=> &$GLOBALS['TL_LANG']['tl_sms_course']['show'],
				'href'				=> 'act=show',
				'icon'				=> 'show.gif'
			)
		)
	),

	// Palettes
	'palettes' => array(
		'__selector__'			=> array(''),
		'default'				=> 'name,description,leader,coLeader,teacher,maxStudents,closed,specials,minForm,maxForm,room'
	),

	// Subpalettes
	'subpalettes' => array(
		''						=> ''
	),

	// Fields
	'fields' => array(
		'id' => array(
			'sql'				=> "int(10) unsigned NOT NULL auto_increment"
		),
		'tstamp' => array(
			'sql'				=> "int(10) unsigned NOT NULL default '0'"
		),
		'name' => array(
			'label'				=> &$GLOBALS['TL_LANG']['tl_sms_course']['name'],
			'exclude'			=> false,
			'inputType'			=> 'text',
			'search'			=> true,
			'sorting'			=> true,
			'eval'				=> array('mandatory' => true, 'tl_class' => 'long'),
			'sql'				=> "varchar(255) NOT NULL default ''"
		),
		'description' => array(
			'label'				=> &$GLOBALS['TL_LANG']['tl_sms_course']['description'],
			'exclude'			=> false,
			'inputType'			=> 'textarea',
			'search'			=> true,
			'sorting'			=> true,
			'eval'				=> array('mandatory' => true),
			'sql'				=> "text NOT NULL"
		),
		'leader' => array(
			'label'				=> &$GLOBALS['TL_LANG']['tl_sms_course']['leader'],
			'exclude'			=> false,
			'serach'			=> true,
			'inputType'			=> 'select',
			'options_callback'	=> array('WBGym\WBGym', 'studentList'),
			'foreignKey'		=> 'tl_member.username',
			'eval'				=> array('chosen' => true, 'includeBlankOption' => true, 'tl_class' => 'w50'),
			'sql'				=> "int(10) unsigned NOT NULL default '0'"
		),
		'coLeader' => array(
			'label'				=> &$GLOBALS['TL_LANG']['tl_sms_course']['coLeader'],
			'exclude'			=> false,
			'search'			=> true,
			'inputType'			=> 'select',
			'options_callback'	=> array('WBGym\WBGym', 'studentList'),
			'foreignKey'		=> 'tl_member.username',
			'eval'				=> array('chosen' => true, 'includeBlankOption' => true, 'tl_class' => 'w50'),
			'sql'				=> "int(10) unsigned NOT NULL default '0'"
		),
		'teacher' => array(
			'label'				=> &$GLOBALS['TL_LANG']['tl_sms_course']['teacher'],
			'exclude'			=> false,
			'search'			=> true,
			'inputType'			=> 'select',
			'options_callback'	=> array('WBGym\WBGym', 'teacherList'),
			'foreignKey'		=> 'tl_member.username',
			'eval'				=> array('chosen' => true, 'includeBlankOption' => true, 'tl_class' => 'w50'),
			'sql'				=> "int(10) unsigned NOT NULL default '0'"
		),
		'specials' => array(
			'label'				=> &$GLOBALS['TL_LANG']['tl_sms_course']['specials'],
			'exclude'			=> false,
			'inputType'			=> 'text',
			'eval'				=> array('tl_class' => 'long'),
			'sql'				=> "varchar(255) NOT NULL default ''"
		),
		'maxStudents' => array(
			'label'				=> &$GLOBALS['TL_LANG']['tl_sms_course']['maxStudents'],
			'exclude'			=> false,
			'search'			=> true,
			'inputType'			=> 'text',
			'eval'				=> array('rgxp' => 'prcnt', 'tl_class' => 'w50'),
			'sql'				=> "int(10) NOT NULL default '0'"
		),
		'minForm' => array(
			'label'				=> &$GLOBALS['TL_LANG']['tl_sms_course']['minForm'],
			'exclude'			=> false,
			'filter'			=> true,
			'search'			=> true,
			'inputType'			=> 'select',
			'default'			=> '5',
			'options'			=> array(5, 6, 7, 8, 9, 10 ,11, 12),
			'eval'				=> array('tl_class' => 'w50'),
			'sql'				=> "int(10) NOT NULL default '5'"
		),
		'maxForm' => array(
			'label'				=> &$GLOBALS['TL_LANG']['tl_sms_course']['maxForm'],
			'exclude'			=> false,
			'filter'			=> true,
			'search'			=> true,
			'inputType'			=> 'select',
			'default'			=> '12',
			'options'			=> array(5, 6, 7, 8, 9, 10, 11, 12),
			'eval'				=> array('tl_class' => 'w50'),
			'sql'				=> "int(10) NOT NULL default '10'"
		),
		'closed' => array(
			'label'				=> &$GLOBALS['TL_LANG']['tl_sms_course']['closed'],
			'exclude'			=> false,
			'filter'			=> true,
			'inputType'			=> 'checkbox',
			'eval'				=> array('tl_class'=> 'clr'),
			'sql'				=> "char(1) NOT NULL default ''"
		),
		'room' => array(
			'label'				=> &$GLOBALS['TL_LANG']['tl_sms_course']['room'],
			'exclude'			=> false,
			'search'			=> true,
			'inputType'			=> 'text',
			'eval'				=> array('tl_class' => 'clr'),
			'sql'				=> "varchar(255) NOT NULL default ''"
		)
		
	)
);

class tl_sms_course extends Backend
{
	public function replaceIds($row, $label, DataContainer $dc, $args) {		

		$args[2] = WBGym\WBGym::student($row['leader']);
		$args[3] = WBGym\WBGym::teacher($row['teacher']);

		return $args;
	}
}

?>