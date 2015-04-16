<?php
return array(
	'ctrl' => array(
		'title' => 'LLL:EXT:lang/locallang_tca.xlf:sys_news',
		'label' => 'title',
		'tstamp' => 'tstamp',
		'crdate' => 'crdate',
		'cruser_id' => 'cruser_id',
		'adminOnly' => TRUE,
		'rootLevel' => TRUE,
		'delete' => 'deleted',
		'enablecolumns' => array(
			'disabled' => 'hidden',
			'starttime' => 'starttime',
			'endtime' => 'endtime'
		),
		'default_sortby' => 'crdate DESC',
		'typeicon_classes' => array(
			'default' => 'mimetypes-x-sys_news'
		)
	),
	'interface' => array(
		'showRecordFieldList' => 'hidden,title,content,starttime,endtime'
	),
	'columns' => array(
		'hidden' => array(
			'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.disable',
			'exclude' => 1,
			'config' => array(
				'type' => 'check',
				'default' => '0'
			)
		),
		'starttime' => array(
			'exclude' => 1,
			'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.starttime',
			'config' => array(
				'type' => 'input',
				'size' => '13',
				'max' => '20',
				'eval' => 'datetime',
				'default' => '0'
			)
		),
		'endtime' => array(
			'exclude' => 1,
			'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.endtime',
			'config' => array(
				'type' => 'input',
				'size' => '13',
				'max' => '20',
				'eval' => 'datetime',
				'default' => '0'
			)
		),
		'title' => array(
			'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.title',
			'config' => array(
				'type' => 'input',
				'size' => '30',
				'max' => '255',
				'eval' => 'required'
			)
		),
		'content' => array(
			'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.text',
			'config' => array(
				'type' => 'text',
				'cols' => '48',
				'rows' => '5',
				'wizards' => array(
					'RTE' => array(
						'notNewRecords' => 1,
						'RTEonly' => 1,
						'type' => 'script',
						'title' => 'LLL:EXT:cms/locallang_ttc.xlf:bodytext.W.RTE',
						'icon' => 'wizard_rte2.gif',
						'module' => array(
							'name' => 'wizard_rte'
						)
					)
				)
			)
		)
	),
	'types' => array(
		'1' => array('showitem' => '
			hidden, title, content;;9;richtext:rte_transform[flag=rte_enabled|mode=ts_css],
			--div--;LLL:EXT:lang/locallang_tca.xlf:sys_news.tabs.access, starttime, endtime')
	)
);
