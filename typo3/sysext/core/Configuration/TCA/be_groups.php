<?php
return array(
	'ctrl' => array(
		'label' => 'title',
		'tstamp' => 'tstamp',
		'crdate' => 'crdate',
		'cruser_id' => 'cruser_id',
		'delete' => 'deleted',
		'default_sortby' => 'ORDER BY title',
		'prependAtCopy' => 'LLL:EXT:lang/locallang_general.xlf:LGL.prependAtCopy',
		'adminOnly' => 1,
		'rootLevel' => 1,
		'typeicon_classes' => array(
			'default' => 'status-user-group-backend'
		),
		'enablecolumns' => array(
			'disabled' => 'hidden'
		),
		'title' => 'LLL:EXT:lang/locallang_tca.xlf:be_groups',
		'useColumnsForDefaultValues' => 'lockToDomain, file_permissions',
		'versioningWS_alwaysAllowLiveEdit' => TRUE,
		'searchFields' => 'title'
	),
	'interface' => array(
		'showRecordFieldList' => 'title, db_mountpoints, file_mountpoints, file_permissions, tables_select, tables_modify, pagetypes_select, non_exclude_fields, groupMods, lockToDomain, description'
	),
	'columns' => array(
		'title' => array(
			'label' => 'LLL:EXT:lang/locallang_tca.xlf:be_groups.title',
			'config' => array(
				'type' => 'input',
				'size' => '25',
				'max' => '50',
				'eval' => 'trim,required'
			)
		),
		'db_mountpoints' => array(
			'label' => 'LLL:EXT:lang/locallang_tca.xlf:db_mountpoints',
			'config' => array(
				'type' => 'group',
				'internal_type' => 'db',
				'allowed' => 'pages',
				'size' => '3',
				'maxitems' => 100,
				'autoSizeMax' => 10,
				'show_thumbs' => '1',
				'wizards' => array(
					'suggest' => array(
						'type' => 'suggest'
					)
				)
			)
		),
		'file_mountpoints' => array(
			'label' => 'LLL:EXT:lang/locallang_tca.xlf:file_mountpoints',
			'config' => array(
				'type' => 'select',
				'foreign_table' => 'sys_filemounts',
				'foreign_table_where' => ' AND sys_filemounts.pid=0 ORDER BY sys_filemounts.title',
				'size' => '3',
				'maxitems' => 100,
				'autoSizeMax' => 10,
				'iconsInOptionTags' => 1,
				'wizards' => array(
					'_VERTICAL' => 1,
					'edit' => array(
						'type' => 'popup',
						'title' => 'LLL:EXT:lang/locallang_tca.xlf:file_mountpoints_edit_title',
						'module' => array(
							'name' => 'wizard_edit',
						),
						'popup_onlyOpenIfSelected' => 1,
						'icon' => 'edit2.gif',
						'JSopenParams' => 'height=350,width=580,status=0,menubar=0,scrollbars=1'
					),
					'add' => array(
						'type' => 'script',
						'title' => 'LLL:EXT:lang/locallang_tca.xlf:file_mountpoints_add_title',
						'icon' => 'add.gif',
						'params' => array(
							'table' => 'sys_filemounts',
							'pid' => '0',
							'setValue' => 'prepend'
						),
						'module' => array(
							'name' => 'wizard_add'
						)
					),
					'list' => array(
						'type' => 'script',
						'title' => 'LLL:EXT:lang/locallang_tca.xlf:file_mountpoints_list_title',
						'icon' => 'list.gif',
						'params' => array(
							'table' => 'sys_filemounts',
							'pid' => '0'
						),
						'module' => array(
							'name' => 'wizard_list'
						)
					)
				)
			)
		),
		'file_permissions' => array(
			'label' => 'LLL:EXT:lang/locallang_tca.xlf:be_groups.fileoper_perms',
			'config' => array(
				'type' => 'select',
				'items' => array(
					array('LLL:EXT:lang/locallang_tca.xlf:be_groups.file_permissions.folder', '--div--', 'apps-filetree-folder-default'),
					array('LLL:EXT:lang/locallang_tca.xlf:be_groups.file_permissions.folder_read', 'readFolder', 'apps-filetree-folder-default'),
					array('LLL:EXT:lang/locallang_tca.xlf:be_groups.file_permissions.folder_write', 'writeFolder', 'apps-filetree-folder-default'),
					array('LLL:EXT:lang/locallang_tca.xlf:be_groups.file_permissions.folder_add', 'addFolder', 'apps-filetree-folder-default'),
					array('LLL:EXT:lang/locallang_tca.xlf:be_groups.file_permissions.folder_rename', 'renameFolder', 'apps-filetree-folder-default'),
					array('LLL:EXT:lang/locallang_tca.xlf:be_groups.file_permissions.folder_move', 'moveFolder', 'apps-filetree-folder-default'),
					array('LLL:EXT:lang/locallang_tca.xlf:be_groups.file_permissions.folder_copy', 'copyFolder', 'apps-filetree-folder-default'),
					array('LLL:EXT:lang/locallang_tca.xlf:be_groups.file_permissions.folder_delete', 'deleteFolder', 'apps-filetree-folder-default'),
					array('LLL:EXT:lang/locallang_tca.xlf:be_groups.file_permissions.folder_recursivedelete', 'recursivedeleteFolder', 'apps-filetree-folder-default'),
					array('LLL:EXT:lang/locallang_tca.xlf:be_groups.file_permissions.files', '--div--', 'mimetypes-other-other'),
					array('LLL:EXT:lang/locallang_tca.xlf:be_groups.file_permissions.files_read', 'readFile', 'mimetypes-other-other'),
					array('LLL:EXT:lang/locallang_tca.xlf:be_groups.file_permissions.files_write', 'writeFile', 'mimetypes-other-other'),
					array('LLL:EXT:lang/locallang_tca.xlf:be_groups.file_permissions.files_add', 'addFile', 'mimetypes-other-other'),
					array('LLL:EXT:lang/locallang_tca.xlf:be_groups.file_permissions.files_rename', 'renameFile', 'mimetypes-other-other'),
					array('LLL:EXT:lang/locallang_tca.xlf:be_groups.file_permissions.files_move', 'moveFile', 'mimetypes-other-other'),
					array('LLL:EXT:lang/locallang_tca.xlf:be_groups.file_permissions.files_copy', 'copyFile', 'mimetypes-other-other'),
					array('LLL:EXT:lang/locallang_tca.xlf:be_groups.fileoper_perms_unzip', 'unzipFile', 'mimetypes-other-other'),
					array('LLL:EXT:lang/locallang_tca.xlf:be_groups.file_permissions.files_delete', 'deleteFile', 'mimetypes-other-other')
				),
				'renderMode' => 'checkbox',
				'size' => 17,
				'maxitems' => 17,
				'default' => 'readFolder,writeFolder,addFolder,renameFolder,moveFolder,deleteFolder,readFile,writeFile,addFile,renameFile,moveFile,files_copy,deleteFile'
			)
		),
		'workspace_perms' => array(
			'label' => 'LLL:EXT:lang/locallang_tca.xlf:workspace_perms',
			'config' => array(
				'type' => 'check',
				'items' => array(
					array('LLL:EXT:lang/locallang_tca.xlf:workspace_perms_live', 0)
				),
				'default' => 0
			)
		),
		'pagetypes_select' => array(
			'label' => 'LLL:EXT:lang/locallang_tca.xlf:be_groups.pagetypes_select',
			'config' => array(
				'type' => 'select',
				'special' => 'pagetypes',
				'size' => '5',
				'autoSizeMax' => 50,
				'maxitems' => 20,
				'renderMode' => 'checkbox',
				'iconsInOptionTags' => 1
			)
		),
		'tables_modify' => array(
			'label' => 'LLL:EXT:lang/locallang_tca.xlf:be_groups.tables_modify',
			'config' => array(
				'type' => 'select',
				'special' => 'tables',
				'size' => '5',
				'autoSizeMax' => 50,
				'maxitems' => 100,
				'renderMode' => 'checkbox',
				'iconsInOptionTags' => 1
			)
		),
		'tables_select' => array(
			'label' => 'LLL:EXT:lang/locallang_tca.xlf:be_groups.tables_select',
			'config' => array(
				'type' => 'select',
				'special' => 'tables',
				'size' => '5',
				'autoSizeMax' => 50,
				'maxitems' => 100,
				'renderMode' => 'checkbox',
				'iconsInOptionTags' => 1
			)
		),
		'non_exclude_fields' => array(
			'label' => 'LLL:EXT:lang/locallang_tca.xlf:be_groups.non_exclude_fields',
			'config' => array(
				'type' => 'select',
				'special' => 'exclude',
				'size' => '25',
				'maxitems' => 1000,
				'autoSizeMax' => 50,
				'renderMode' => 'checkbox'
			)
		),
		'explicit_allowdeny' => array(
			'label' => 'LLL:EXT:lang/locallang_tca.xlf:be_groups.explicit_allowdeny',
			'config' => array(
				'type' => 'select',
				'special' => 'explicitValues',
				'maxitems' => 1000,
				'renderMode' => 'checkbox'
			)
		),
		'allowed_languages' => array(
			'label' => 'LLL:EXT:lang/locallang_tca.xlf:allowed_languages',
			'config' => array(
				'type' => 'select',
				'special' => 'languages',
				'maxitems' => 1000,
				'renderMode' => 'checkbox'
			)
		),
		'custom_options' => array(
			'label' => 'LLL:EXT:lang/locallang_tca.xlf:be_groups.custom_options',
			'config' => array(
				'type' => 'select',
				'special' => 'custom',
				'maxitems' => 1000,
				'renderMode' => 'checkbox'
			)
		),
		'hidden' => array(
			'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.disable',
			'config' => array(
				'type' => 'check',
				'default' => '0'
			)
		),
		'lockToDomain' => array(
			'label' => 'LLL:EXT:lang/locallang_tca.xlf:lockToDomain',
			'config' => array(
				'type' => 'input',
				'size' => '20',
				'eval' => 'trim',
				'max' => '50',
				'softref' => 'substitute'
			)
		),
		'groupMods' => array(
			'label' => 'LLL:EXT:lang/locallang_tca.xlf:userMods',
			'config' => array(
				'type' => 'select',
				'special' => 'modListGroup',
				'size' => '5',
				'autoSizeMax' => 50,
				'maxitems' => 100,
				'renderMode' => 'checkbox',
				'iconsInOptionTags' => 1
			)
		),
		'description' => array(
			'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.description',
			'config' => array(
				'type' => 'text',
				'rows' => 5,
				'cols' => 30
			)
		),
		'TSconfig' => array(
			'label' => 'LLL:EXT:lang/locallang_tca.xlf:TSconfig',
			'config' => array(
				'type' => 'text',
				'cols' => '40',
				'rows' => '5',
				'softref' => 'TSconfig'
			),
			'defaultExtras' => 'fixed-font : enable-tab'
		),
		'hide_in_lists' => array(
			'label' => 'LLL:EXT:lang/locallang_tca.xlf:be_groups.hide_in_lists',
			'config' => array(
				'type' => 'check',
				'default' => 0
			)
		),
		'subgroup' => array(
			'label' => 'LLL:EXT:lang/locallang_tca.xlf:be_groups.subgroup',
			'config' => array(
				'type' => 'select',
				'foreign_table' => 'be_groups',
				'foreign_table_where' => 'AND NOT(be_groups.uid = ###THIS_UID###) AND be_groups.hidden=0 ORDER BY be_groups.title',
				'size' => '5',
				'autoSizeMax' => 50,
				'maxitems' => 20,
				'iconsInOptionTags' => 1
			)
		),
		'category_perms' => array(
			'label' => 'LLL:EXT:lang/locallang_tca.xlf:category_perms',
			'config' => array(
				'type' => 'select',
				'foreign_table' => 'sys_category',
				'foreign_table_where' => ' AND (sys_category.sys_language_uid = 0 OR sys_category.l10n_parent = 0) ORDER BY sys_category.sorting',
				'renderMode' => 'tree',
				'treeConfig' => array(
					'parentField' => 'parent',
					'appearance' => array(
						'expandAll' => FALSE,
						'showHeader' => FALSE,
						'maxLevels' => 99,
					),
				),
				'size' => 10,
				'autoSizeMax' => 20,
				'minitems' => 0,
				'maxitems' => 9999
			)
		)
	),
	'types' => array(
		'0' => array('showitem' => 'hidden, title, description, subgroup,
			--div--;LLL:EXT:lang/locallang_tca.xlf:be_groups.tabs.base_rights, groupMods, tables_select, tables_modify, pagetypes_select, non_exclude_fields, explicit_allowdeny, allowed_languages, custom_options,
			--div--;LLL:EXT:lang/locallang_tca.xlf:be_groups.tabs.mounts_and_workspaces, workspace_perms, db_mountpoints, file_mountpoints, file_permissions, category_perms,
			--div--;LLL:EXT:lang/locallang_tca.xlf:be_groups.tabs.options, lockToDomain, hide_in_lists, TSconfig,
			--div--;LLL:EXT:lang/locallang_tca.xlf:be_groups.tabs.extended'),
	)
);
