<?php
return array(
	'ctrl' => array(
		'label' => 'title',
		'tstamp' => 'tstamp',
		'default_sortby' => 'ORDER BY title',
		'sortby' => 'sorting',
		'prependAtCopy' => 'LLL:EXT:lang/locallang_general.xlf:LGL.prependAtCopy',
		'title' => 'LLL:EXT:sys_action/locallang_tca.xlf:sys_action',
		'crdate' => 'crdate',
		'cruser_id' => 'cruser_id',
		'adminOnly' => 1,
		'rootLevel' => -1,
		'setToDefaultOnCopy' => 'assign_to_groups',
		'enablecolumns' => array(
			'disabled' => 'hidden'
		),
		'typeicon_classes' => array(
			'default' => 'mimetypes-x-sys_action'
		),
		'type' => 'type',
		'iconfile' => \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extRelPath('sys_action') . 'x-sys_action.png',
	),
	'interface' => array(
		'showRecordFieldList' => 'hidden,title,type,description,assign_to_groups'
	),
	'columns' => array(
		'title' => array(
			'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.title',
			'config' => array(
				'type' => 'input',
				'size' => '25',
				'max' => '256',
				'eval' => 'trim,required'
			)
		),
		'description' => array(
			'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.description',
			'config' => array(
				'type' => 'text',
				'rows' => 10,
				'cols' => 48
			)
		),
		'hidden' => array(
			'label' => 'LLL:EXT:sys_action/locallang_tca.xlf:sys_action.hidden',
			'config' => array(
				'type' => 'check'
			)
		),
		'type' => array(
			'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.type',
			'config' => array(
				'type' => 'select',
				'items' => array(
					array('', '0'),
					array('LLL:EXT:sys_action/locallang_tca.xlf:sys_action.type.1', '1'),
					array('LLL:EXT:sys_action/locallang_tca.xlf:sys_action.type.2', '2'),
					array('LLL:EXT:sys_action/locallang_tca.xlf:sys_action.type.3', '3'),
					array('LLL:EXT:sys_action/locallang_tca.xlf:sys_action.type.4', '4'),
					array('LLL:EXT:sys_action/locallang_tca.xlf:sys_action.type.5', '5')
				)
			)
		),
		'assign_to_groups' => array(
			'label' => 'LLL:EXT:sys_action/locallang_tca.xlf:sys_action.assign_to_groups',
			'config' => array(
				'type' => 'select',
				'foreign_table' => 'be_groups',
				'foreign_table_where' => 'ORDER BY be_groups.title',
				'MM' => 'sys_action_asgr_mm',
				'size' => '10',
				'minitems' => '0',
				'maxitems' => '200',
				'autoSizeMax' => '10'
			)
		),
		't1_userprefix' => array(
			'label' => 'LLL:EXT:sys_action/locallang_tca.xlf:sys_action.t1_userprefix',
			'config' => array(
				'type' => 'input',
				'size' => '25',
				'max' => '10',
				'eval' => 'trim'
			)
		),
		't1_allowed_groups' => array(
			'label' => 'LLL:EXT:sys_action/locallang_tca.xlf:sys_action.t1_allowed_groups',
			'config' => array(
				'type' => 'select',
				'foreign_table' => 'be_groups',
				'foreign_table_where' => 'ORDER BY be_groups.title',
				'size' => '10',
				'maxitems' => '20',
				'autoSizeMax' => '10'
			)
		),
		't1_create_user_dir' => array(
			'label' => 'LLL:EXT:sys_action/locallang_tca.xlf:sys_action.t1_create_user_dir',
			'config' => array(
				'type' => 'check'
			)
		),
		't1_copy_of_user' => array(
			'label' => 'LLL:EXT:sys_action/locallang_tca.xlf:sys_action.t1_copy_of_user',
			'config' => array(
				'type' => 'group',
				'internal_type' => 'db',
				'allowed' => 'be_users',
				'size' => '1',
				'maxitems' => '1',
				'minitems' => '1',
				'show_thumbs' => '1',
				'wizards' => array(
					'suggest' => array(
						'type' => 'suggest'
					)
				)
			)
		),
		't3_listPid' => array(
			'label' => 'LLL:EXT:sys_action/locallang_tca.xlf:sys_action.t3_listPid',
			'config' => array(
				'type' => 'group',
				'internal_type' => 'db',
				'allowed' => 'pages',
				'size' => '1',
				'maxitems' => '1',
				'minitems' => '1',
				'show_thumbs' => '1',
				'wizards' => array(
					'suggest' => array(
						'type' => 'suggest'
					)
				)
			)
		),
		't3_tables' => array(
			'label' => 'LLL:EXT:sys_action/locallang_tca.xlf:sys_action.t3_tables',
			'config' => array(
				'type' => 'select',
				'special' => 'tables',
				'items' => array(
					array('', '')
				)
			)
		),
		't4_recordsToEdit' => array(
			'label' => 'LLL:EXT:sys_action/locallang_tca.xlf:sys_action.t4_recordsToEdit',
			'config' => array(
				'type' => 'group',
				'internal_type' => 'db',
				'allowed' => '*',
				'prepend_tname' => 1,
				'size' => '5',
				'maxitems' => '50',
				'minitems' => '1',
				'show_thumbs' => '1',
				'wizards' => array(
					'suggest' => array(
						'type' => 'suggest'
					)
				)
			)
		)
	),
	'types' => array(
		'0' => array('showitem' => '
			type,
			title,
			description,
			--div--;LLL:EXT:sys_action/locallang_tca.xlf:sys_action.tab.access,
				hidden,
				assign_to_groups
		'),
		'1' => array('showitem' => '
			type,
			title,
			description,
			--div--;LLL:EXT:sys_action/locallang_tca.xlf:sys_action.tab.create_user.settings,
				t1_userprefix,t1_copy_of_user,
				t1_allowed_groups,
				t1_create_user_dir,
			--div--;LLL:EXT:sys_action/locallang_tca.xlf:sys_action.tab.access,
				hidden,
				assign_to_groups
		'),
		'2' => array('showitem' => '
			type,
			title,
			description,
			--div--,
			--div--;LLL:EXT:sys_action/locallang_tca.xlf:sys_action.tab.access,
				hidden,
				assign_to_groups
		'),
		'3' => array('showitem' => '
			type,
			title,
			description,
			--div--;LLL:EXT:sys_action/locallang_tca.xlf:sys_action.tab.record_list.settings,
				t3_listPid,
				t3_tables,
			--div--;LLL:EXT:sys_action/locallang_tca.xlf:sys_action.tab.access,
				hidden,
				assign_to_groups
		'),
		'4' => array('showitem' => '
			type,
			title,
			description,
			--div--;LLL:EXT:sys_action/locallang_tca.xlf:sys_action.tab.edit_records.settings,
				t4_recordsToEdit,
			--div--;LLL:EXT:sys_action/locallang_tca.xlf:sys_action.tab.access,
				hidden,
				assign_to_groups
		'),
		'5' => array('showitem' => '
			type,
			title,
			description,
			--div--;LLL:EXT:sys_action/locallang_tca.xlf:sys_action.tab.new_record.settings,
				t3_listPid;LLL:EXT:sys_action/locallang_tca.xlf:sys_action.new_record.pid,
				t3_tables;LLL:EXT:sys_action/locallang_tca.xlf:sys_action.new_record.tablename,
			--div--;LLL:EXT:sys_action/locallang_tca.xlf:sys_action.tab.access,
				hidden,
				assign_to_groups
		')
	)
);
