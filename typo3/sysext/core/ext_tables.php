<?php
defined('TYPO3_MODE') or die();

/**
 * $GLOBALS['PAGES_TYPES'] defines the various types of pages (field: doktype) the system
 * can handle and what restrictions may apply to them.
 * Here you can set the icon and especially you can define which tables are
 * allowed on a certain pagetype (doktype)
 * NOTE: The 'default' entry in the $GLOBALS['PAGES_TYPES'] array is the 'base' for all
 * types, and for every type the entries simply overrides the entries in the 'default' type!
 */
$GLOBALS['PAGES_TYPES'] = array(
	(string)\TYPO3\CMS\Frontend\Page\PageRepository::DOKTYPE_LINK => array(),
	(string)\TYPO3\CMS\Frontend\Page\PageRepository::DOKTYPE_SHORTCUT => array(),
	(string)\TYPO3\CMS\Frontend\Page\PageRepository::DOKTYPE_BE_USER_SECTION => array(
		'type' => 'web',
		'allowedTables' => '*'
	),
	(string)\TYPO3\CMS\Frontend\Page\PageRepository::DOKTYPE_MOUNTPOINT => array(),
	(string)\TYPO3\CMS\Frontend\Page\PageRepository::DOKTYPE_SPACER => array(
		'type' => 'sys'
	),
	(string)\TYPO3\CMS\Frontend\Page\PageRepository::DOKTYPE_SYSFOLDER => array(
		//  Doktype 254 is a 'Folder' - a general purpose storage folder for whatever you like.
		// In CMS context it's NOT a viewable page. Can contain any element.
		'type' => 'sys',
		'allowedTables' => '*'
	),
	(string)\TYPO3\CMS\Frontend\Page\PageRepository::DOKTYPE_RECYCLER => array(
		// Doktype 255 is a recycle-bin.
		'type' => 'sys',
		'allowedTables' => '*'
	),
	'default' => array(
		'type' => 'web',
		'allowedTables' => 'pages',
		'onlyAllowedTables' => '0'
	),
);

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::allowTableOnStandardPages('sys_category');

/** @var \TYPO3\CMS\Core\Resource\Driver\DriverRegistry $registry */
$registry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Resource\Driver\DriverRegistry::class);
$registry->addDriversToTCA();

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::allowTableOnStandardPages('sys_file_reference');
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::allowTableOnStandardPages('sys_file_collection');

/**
 * $TBE_MODULES contains the structure of the backend modules as they are
 * arranged in main- and sub-modules. Every entry in this array represents a
 * menu item on either first (key) or second level (value from list) in the
 * left menu in the TYPO3 backend
 * For information about adding modules to TYPO3 you should consult the
 * documentation found in "Inside TYPO3"
 */
$GLOBALS['TBE_MODULES'] = array(
	'web' => 'list',
	'file' => '',
	'user' => '',
	'tools' => '',
	'system' => '',
	'help' => ''
);


// Register the page tree core navigation component
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addCoreNavigationComponent('web', 'typo3-pagetree');


/**
 * $TBE_STYLES configures backend styles and colors; Basically this contains
 * all the values that can be used to create new skins for TYPO3.
 * For information about making skins to TYPO3 you should consult the
 * documentation found in "Inside TYPO3"
 */
$GLOBALS['TBE_STYLES'] = array(
	'colorschemes' => array(
		'0' => '#E4E0DB,#CBC7C3,#EDE9E5'
	),
	'borderschemes' => array(
		'0' => array('border:solid 1px black;', 5)
	)
);


/**
 * Setting up $TCA_DESCR - Context Sensitive Help (CSH)
 * For information about using the CSH API in TYPO3 you should consult the
 * documentation found in "Inside TYPO3"
 */
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('pages', 'EXT:lang/locallang_csh_pages.xlf');
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('be_users', 'EXT:lang/locallang_csh_be_users.xlf');
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('be_groups', 'EXT:lang/locallang_csh_be_groups.xlf');
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('sys_filemounts', 'EXT:lang/locallang_csh_sysfilem.xlf');
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('sys_language', 'EXT:lang/locallang_csh_syslang.xlf');
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('sys_news', 'EXT:lang/locallang_csh_sysnews.xlf');
// General Core
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('xMOD_csh_corebe', 'EXT:lang/locallang_csh_corebe.xlf');
// Extension manager
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('_MOD_tools_em', 'EXT:lang/locallang_csh_em.xlf');
// Web > Info
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('_MOD_web_info', 'EXT:lang/locallang_csh_web_info.xlf');
// Web > Func
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('_MOD_web_func', 'EXT:lang/locallang_csh_web_func.xlf');
// Labels for TYPO3 4.5 and greater.
// These labels override the ones set above, while still falling back to the original labels
// if no translation is available.
$GLOBALS['TYPO3_CONF_VARS']['SYS']['locallangXMLOverride']['EXT:lang/locallang_csh_pages.xlf'][] = 'EXT:lang/4.5/locallang_csh_pages.xlf';
$GLOBALS['TYPO3_CONF_VARS']['SYS']['locallangXMLOverride']['EXT:lang/locallang_csh_corebe.xlf'][] = 'EXT:lang/4.5/locallang_csh_corebe.xlf';


/**
 * $FILEICONS defines icons for the various file-formats
 */
$GLOBALS['FILEICONS'] = array(
	'txt' => 'txt.gif',
	'pdf' => 'pdf.gif',
	'doc' => 'doc.gif',
	'ai' => 'ai.gif',
	'bmp' => 'bmp.gif',
	'tif' => 'tif.gif',
	'htm' => 'htm.gif',
	'html' => 'html.gif',
	'pcd' => 'pcd.gif',
	'gif' => 'gif.gif',
	'jpg' => 'jpg.gif',
	'jpeg' => 'jpg.gif',
	'mpg' => 'mpg.gif',
	'mpeg' => 'mpeg.gif',
	'exe' => 'exe.gif',
	'com' => 'exe.gif',
	'zip' => 'zip.gif',
	'tgz' => 'zip.gif',
	'gz' => 'zip.gif',
	'php3' => 'php3.gif',
	'php4' => 'php3.gif',
	'php5' => 'php3.gif',
	'php6' => 'php3.gif',
	'php' => 'php3.gif',
	'ppt' => 'ppt.gif',
	'ttf' => 'ttf.gif',
	'pcx' => 'pcx.gif',
	'png' => 'png.gif',
	'tga' => 'tga.gif',
	'class' => 'java.gif',
	'sxc' => 'sxc.gif',
	'sxw' => 'sxw.gif',
	'xls' => 'xls.gif',
	'swf' => 'swf.gif',
	'swa' => 'flash.gif',
	'dcr' => 'flash.gif',
	'wav' => 'wav.gif',
	'mp3' => 'mp3.gif',
	'avi' => 'avi.gif',
	'au' => 'au.gif',
	'mov' => 'mov.gif',
	'3ds' => '3ds.gif',
	'csv' => 'csv.gif',
	'ico' => 'ico.gif',
	'max' => 'max.gif',
	'ps' => 'ps.gif',
	'tmpl' => 'tmpl.gif',
	'fh3' => 'fh3.gif',
	'inc' => 'inc.gif',
	'mid' => 'mid.gif',
	'psd' => 'psd.gif',
	'xml' => 'xml.gif',
	'rtf' => 'rtf.gif',
	't3x' => 't3x.gif',
	't3d' => 't3d.gif',
	'cdr' => 'cdr.gif',
	'dtd' => 'dtd.gif',
	'sgml' => 'sgml.gif',
	'ani' => 'ani.gif',
	'css' => 'css.gif',
	'eps' => 'eps.gif',
	'js' => 'js.gif',
	'wrl' => 'wrl.gif',
	'default' => 'default.gif'
);


/**
 * Backend sprite icon-names
 */
$GLOBALS['TBE_STYLES']['spriteIconApi']['coreSpriteImageNames'] = array(
	'actions-document-close',
	'actions-document-duplicates-select',
	'actions-document-edit-access',
	'actions-document-export-csv',
	'actions-document-export-t3d',
	'actions-document-history-open',
	'actions-document-import-t3d',
	'actions-document-info',
	'actions-document-localize',
	'actions-document-move',
	'actions-document-new',
	'actions-document-open',
	'actions-document-open-read-only',
	'actions-document-paste-after',
	'actions-document-paste-into',
	'actions-document-save',
	'actions-document-save-close',
	'actions-document-save-new',
	'actions-document-save-view',
	'actions-document-select',
	'actions-document-synchronize',
	'actions-document-view',
	'actions-edit-add',
	'actions-edit-copy',
	'actions-edit-copy-release',
	'actions-edit-cut',
	'actions-edit-cut-release',
	'actions-edit-delete',
	'actions-edit-download',
	'actions-edit-hide',
	'actions-edit-insert-default',
	'actions-edit-localize-status-high',
	'actions-edit-localize-status-low',
	'actions-edit-merge-localization',
	'actions-edit-pick-date',
	'actions-edit-rename',
	'actions-edit-restore',
	'actions-edit-undelete-edit',
	'actions-edit-undo',
	'actions-edit-unhide',
	'actions-edit-upload',
	'actions-input-clear',
	'actions-insert-record',
	'actions-insert-reference',
	'actions-markstate',
	'actions-message-error-close',
	'actions-message-information-close',
	'actions-message-notice-close',
	'actions-message-ok-close',
	'actions-message-warning-close',
	'actions-move-down',
	'actions-move-left',
	'actions-move-move',
	'actions-move-right',
	'actions-move-to-bottom',
	'actions-move-to-top',
	'actions-move-up',
	'actions-page-move',
	'actions-page-new',
	'actions-page-open',
	'actions-selection-delete',
	'actions-system-backend-user-emulate',
	'actions-system-backend-user-switch',
	'actions-system-cache-clear',
	'actions-system-cache-clear-impact-high',
	'actions-system-cache-clear-impact-low',
	'actions-system-cache-clear-impact-medium',
	'actions-system-cache-clear-rte',
	'actions-system-extension-configure',
	'actions-system-extension-documentation',
	'actions-system-extension-download',
	'actions-system-extension-import',
	'actions-system-extension-install',
	'actions-system-extension-sqldump',
	'actions-system-extension-uninstall',
	'actions-system-extension-update',
	'actions-system-extension-update-disabled',
	'actions-system-help-open',
	'actions-system-list-open',
	'actions-system-options-view',
	'actions-system-pagemodule-open',
	'actions-system-refresh',
	'actions-system-shortcut-new',
	'actions-system-tree-search-open',
	'actions-system-typoscript-documentation',
	'actions-system-typoscript-documentation-open',
	'actions-template-new',
	'actions-unmarkstate',
	'actions-version-document-remove',
	'actions-version-page-open',
	'actions-version-swap-version',
	'actions-version-swap-workspace',
	'actions-version-workspace-preview',
	'actions-version-workspace-sendtostage',
	'actions-view-go-back',
	'actions-view-go-down',
	'actions-view-go-forward',
	'actions-view-go-up',
	'actions-view-list-collapse',
	'actions-view-list-expand',
	'actions-view-paging-first',
	'actions-view-paging-first-disabled',
	'actions-view-paging-last',
	'actions-view-paging-last-disabled',
	'actions-view-paging-next',
	'actions-view-paging-next-disabled',
	'actions-view-paging-previous',
	'actions-view-paging-previous-disabled',
	'actions-view-table-collapse',
	'actions-view-table-expand',
	'actions-window-open',
	'apps-clipboard-images',
	'apps-clipboard-list',
	'apps-filetree-folder-add',
	'apps-filetree-folder-default',
	'apps-filetree-folder-list',
	'apps-filetree-folder-locked',
	'apps-filetree-folder-media',
	'apps-filetree-folder-news',
	'apps-filetree-folder-opened',
	'apps-filetree-folder-recycler',
	'apps-filetree-folder-temp',
	'apps-filetree-folder-user',
	'apps-filetree-mount',
	'apps-filetree-root',
	'apps-irre-collapsed',
	'apps-irre-expanded',
	'apps-pagetree-backend-user',
	'apps-pagetree-backend-user-hideinmenu',
	'apps-pagetree-collapse',
	'apps-pagetree-drag-copy-above',
	'apps-pagetree-drag-copy-below',
	'apps-pagetree-drag-move-above',
	'apps-pagetree-drag-move-below',
	'apps-pagetree-drag-move-between',
	'apps-pagetree-drag-move-into',
	'apps-pagetree-drag-new-between',
	'apps-pagetree-drag-new-inside',
	'apps-pagetree-drag-place-denied',
	'apps-pagetree-expand',
	'apps-pagetree-folder-contains-approve',
	'apps-pagetree-folder-contains-board',
	'apps-pagetree-folder-contains-fe_users',
	'apps-pagetree-folder-contains-news',
	'apps-pagetree-folder-contains-shop',
	'apps-pagetree-folder-default',
	'apps-pagetree-page-advanced',
	'apps-pagetree-page-advanced-hideinmenu',
	'apps-pagetree-page-advanced-root',
	'apps-pagetree-page-backend-users',
	'apps-pagetree-page-backend-users-hideinmenu',
	'apps-pagetree-page-backend-users-root',
	'apps-pagetree-page-default',
	'apps-pagetree-page-domain',
	'apps-pagetree-page-frontend-user',
	'apps-pagetree-page-frontend-user-hideinmenu',
	'apps-pagetree-page-frontend-user-root',
	'apps-pagetree-page-frontend-users',
	'apps-pagetree-page-frontend-users-hideinmenu',
	'apps-pagetree-page-frontend-users-root',
	'apps-pagetree-page-mountpoint',
	'apps-pagetree-page-mountpoint-hideinmenu',
	'apps-pagetree-page-mountpoint-root',
	'apps-pagetree-page-no-icon-found',
	'apps-pagetree-page-no-icon-found-hideinmenu',
	'apps-pagetree-page-no-icon-found-root',
	'apps-pagetree-page-not-in-menu',
	'apps-pagetree-page-recycler',
	'apps-pagetree-page-shortcut',
	'apps-pagetree-page-shortcut-external',
	'apps-pagetree-page-shortcut-external-hideinmenu',
	'apps-pagetree-page-shortcut-external-root',
	'apps-pagetree-page-shortcut-hideinmenu',
	'apps-pagetree-page-shortcut-root',
	'apps-pagetree-root',
	'apps-pagetree-spacer',
	'apps-tcatree-select-recursive',
	'apps-toolbar-menu-actions',
	'apps-toolbar-menu-cache',
	'apps-toolbar-menu-opendocs',
	'apps-toolbar-menu-search',
	'apps-toolbar-menu-shortcut',
	'apps-toolbar-menu-workspace',
	'mimetypes-compressed',
	'mimetypes-excel',
	'mimetypes-media-audio',
	'mimetypes-media-flash',
	'mimetypes-media-image',
	'mimetypes-media-video',
	'mimetypes-other-other',
	'mimetypes-pdf',
	'mimetypes-powerpoint',
	'mimetypes-text-css',
	'mimetypes-text-csv',
	'mimetypes-text-html',
	'mimetypes-text-js',
	'mimetypes-text-php',
	'mimetypes-text-text',
	'mimetypes-word',
	'mimetypes-x-content-divider',
	'mimetypes-x-content-domain',
	'mimetypes-x-content-form',
	'mimetypes-x-content-form-search',
	'mimetypes-x-content-header',
	'mimetypes-x-content-html',
	'mimetypes-x-content-image',
	'mimetypes-x-content-link',
	'mimetypes-x-content-list-bullets',
	'mimetypes-x-content-list-files',
	'mimetypes-x-content-login',
	'mimetypes-x-content-menu',
	'mimetypes-x-content-multimedia',
	'mimetypes-x-content-page-language-overlay',
	'mimetypes-x-content-plugin',
	'mimetypes-x-content-script',
	'mimetypes-x-content-table',
	'mimetypes-x-content-template',
	'mimetypes-x-content-template-extension',
	'mimetypes-x-content-template-static',
	'mimetypes-x-content-text',
	'mimetypes-x-content-text-picture',
	'mimetypes-x-sys_action',
	'mimetypes-x-sys_category',
	'mimetypes-x-sys_language',
	'mimetypes-x-sys_news',
	'mimetypes-x-sys_workspace',
	'mimetypes-x_belayout',
	'status-dialog-error',
	'status-dialog-information',
	'status-dialog-notification',
	'status-dialog-ok',
	'status-dialog-warning',
	'status-overlay-access-restricted',
	'status-overlay-deleted',
	'status-overlay-hidden',
	'status-overlay-icon-missing',
	'status-overlay-includes-subpages',
	'status-overlay-locked',
	'status-overlay-scheduled',
	'status-overlay-scheduled-future-end',
	'status-overlay-translated',
	'status-status-checked',
	'status-status-current',
	'status-status-edit-read-only',
	'status-status-icon-missing',
	'status-status-locked',
	'status-status-permission-denied',
	'status-status-permission-granted',
	'status-status-readonly',
	'status-status-reference-hard',
	'status-status-reference-soft',
	'status-status-sorting-asc',
	'status-status-sorting-desc',
	'status-status-sorting-light-asc',
	'status-status-sorting-light-desc',
	'status-status-workspace-draft',
	'status-system-extension-required',
	'status-user-admin',
	'status-user-backend',
	'status-user-frontend',
	'status-user-group-backend',
	'status-user-group-frontend',
	'status-version-1',
	'status-version-2',
	'status-version-3',
	'status-version-4',
	'status-version-5',
	'status-version-6',
	'status-version-7',
	'status-version-8',
	'status-version-9',
	'status-version-10',
	'status-version-11',
	'status-version-12',
	'status-version-13',
	'status-version-14',
	'status-version-15',
	'status-version-16',
	'status-version-17',
	'status-version-18',
	'status-version-19',
	'status-version-20',
	'status-version-21',
	'status-version-22',
	'status-version-23',
	'status-version-24',
	'status-version-25',
	'status-version-26',
	'status-version-27',
	'status-version-28',
	'status-version-29',
	'status-version-30',
	'status-version-31',
	'status-version-32',
	'status-version-33',
	'status-version-34',
	'status-version-35',
	'status-version-36',
	'status-version-37',
	'status-version-38',
	'status-version-39',
	'status-version-40',
	'status-version-41',
	'status-version-42',
	'status-version-43',
	'status-version-44',
	'status-version-45',
	'status-version-46',
	'status-version-47',
	'status-version-48',
	'status-version-49',
	'status-version-50',
	'status-version-no-version',
	'status-warning-in-use',
	'status-warning-lock',
	'treeline-blank',
	'treeline-join',
	'treeline-joinbottom',
	'treeline-jointop',
	'treeline-line',
	'treeline-minus',
	'treeline-minusbottom',
	'treeline-minusonly',
	'treeline-minustop',
	'treeline-plus',
	'treeline-plusbottom',
	'treeline-plusonly',
	'treeline-stopper',
	'empty-icon'
);


$GLOBALS['TBE_STYLES']['spriteIconApi']['spriteIconRecordOverlayPriorities'] = array(
	'deleted',
	'hidden',
	'starttime',
	'endtime',
	'futureendtime',
	'fe_group',
	'protectedSection'
);


$GLOBALS['TBE_STYLES']['spriteIconApi']['spriteIconRecordOverlayNames'] = array(
	'hidden' => 'status-overlay-hidden',
	'fe_group' => 'status-overlay-access-restricted',
	'starttime' => 'status-overlay-scheduled',
	'endtime' => 'status-overlay-scheduled',
	'futureendtime' => 'status-overlay-scheduled-future-end',
	'readonly' => 'status-overlay-locked',
	'deleted' => 'status-overlay-deleted',
	'missing' => 'status-overlay-missing',
	'translated' => 'status-overlay-translated',
	'protectedSection' => 'status-overlay-includes-subpages'
);

// add stylesheets from the core
$GLOBALS['TBE_STYLES']['skins']['core']['stylesheetDirectories']['Base'] = 'EXT:core/Resources/Public/StyleSheets/';

// Adding flags to the sprite manager
if (TYPO3_MODE === 'BE' || TYPO3_MODE === 'FE' && isset($GLOBALS['BE_USER'])) {
	$flagNames = array(
		'multiple',
		'ad', 'ae', 'af', 'ag', 'ai', 'al', 'am', 'an', 'ao', 'ar', 'as', 'at', 'au', 'aw', 'ax', 'az',
		'ba', 'bb', 'bd', 'be', 'bf', 'bg', 'bh', 'bi', 'bj', 'bm', 'bn', 'bo', 'br', 'bs', 'bt', 'bv', 'bw', 'by', 'bz',
		'ca', 'catalonia', 'cc', 'cd', 'cf', 'cg', 'ch', 'ci', 'ck', 'cl', 'cm', 'cn', 'co', 'cr', 'cs', 'cu', 'cv', 'cx', 'cy', 'cz',
		'de', 'dj', 'dk', 'dm', 'do', 'dz',
		'ec', 'ee', 'eg', 'eh', 'england', 'er', 'es', 'et', 'europeanunion',
		'fam', 'fi', 'fj', 'fk', 'fm', 'fo', 'fr',
		'ga', 'gb', 'gd', 'ge', 'gf', 'gh', 'gi', 'gl', 'gm', 'gn', 'gp', 'gq', 'gr', 'gs', 'gt', 'gu', 'gw', 'gy',
		'hk', 'hm', 'hn', 'hr', 'ht', 'hu',
		'id', 'ie', 'il', 'in', 'io', 'iq', 'ir', 'is', 'it',
		'jm', 'jo', 'jp',
		'ke', 'kg', 'kh', 'ki', 'km', 'kn', 'kp', 'kr', 'kw', 'ky', 'kz',
		'la', 'lb', 'lc', 'li', 'lk', 'lr', 'ls', 'lt', 'lu', 'lv', 'ly',
		'ma', 'mc', 'md', 'me', 'mg', 'mh', 'mk', 'ml', 'mm', 'mn', 'mo', 'mp', 'mq', 'mr', 'ms', 'mt', 'mu', 'mv', 'mw', 'mx', 'my', 'mz',
		'na', 'nc', 'ne', 'nf', 'ng', 'ni', 'nl', 'no', 'np', 'nr', 'nu', 'nz',
		'om',
		'pa', 'pe', 'pf', 'pg', 'ph', 'pk', 'pl', 'pm', 'pn', 'pr', 'ps', 'pt', 'pw', 'py',
		'qa', 'qc',
		're', 'ro', 'rs', 'ru', 'rw',
		'sa', 'sb', 'sc', 'scotland', 'sd', 'se', 'sg', 'sh', 'si', 'sj', 'sk', 'sl', 'sm', 'sn', 'so', 'sr', 'st', 'sv', 'sy', 'sz',
		'tc', 'td', 'tf', 'tg', 'th', 'tj', 'tk', 'tl', 'tm', 'tn', 'to', 'tr', 'tt', 'tv', 'tw', 'tz',
		'ua', 'ug', 'um', 'us', 'uy', 'uz',
		'va', 'vc', 've', 'vg', 'vi', 'vn', 'vu',
		'wales', 'wf', 'ws',
		'ye', 'yt',
		'za', 'zm', 'zw'
	);

	$flagIcons = array();
	foreach ($flagNames as $flagName) {
		$flagIcons[] = 'flags-' . $flagName;
		$flagIcons[] = 'flags-' . $flagName . '-overlay';
	}
	\TYPO3\CMS\Backend\Sprite\SpriteManager::addIconSprite($flagIcons);
	unset($flagNames, $flagName, $flagIcons);
}