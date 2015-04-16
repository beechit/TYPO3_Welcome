<?php
defined('TYPO3_MODE') or die();

// Register the autopublishing task
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][\TYPO3\CMS\Workspaces\Task\AutoPublishTask::class] = array(
	'extension' => $_EXTKEY,
	'title' => 'LLL:EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang_mod.xlf:autopublishTask.name',
	'description' => 'LLL:EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang_mod.xlf:autopublishTask.description'
);

// Register the cleanup preview links task
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][\TYPO3\CMS\Workspaces\Task\CleanupPreviewLinkTask::class] = array(
	'extension' => $_EXTKEY,
	'title' => 'LLL:EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang_mod.xlf:cleanupPreviewLinkTask.name',
	'description' => 'LLL:EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang_mod.xlf:cleanupPreviewLinkTask.description'
);

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass']['workspaces'] = \TYPO3\CMS\Workspaces\Hook\DataHandlerHook::class;
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_befunc.php']['viewOnClickClass']['workspaces'] = \TYPO3\CMS\Workspaces\Hook\BackendUtilityHook::class;
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['hook_eofe']['workspaces'] = \TYPO3\CMS\Workspaces\Hook\TypoScriptFrontendControllerHook::class . '->hook_eofe';
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['typo3/alt_doc.php']['makeEditForm_accessCheck']['workspaces'] = \TYPO3\CMS\Workspaces\Hook\BackendUtilityHook::class . '->makeEditForm_accessCheck';

// Register workspaces cache if not already done in localconf.php or a previously loaded extension.
if (!is_array($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['workspaces_cache'])) {
	$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['workspaces_cache'] = array(
		'groups' => array('all')
	);
}

if (TYPO3_MODE === 'BE') {
	// If publishing/swapping dependent parent-child references, consider all parents and children
	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addUserTSConfig('options.workspaces.considerReferences = 1');
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/tree/pagetree/class.t3lib_tree_pagetree_dataprovider.php']['postProcessCollections'][] = \TYPO3\CMS\Workspaces\ExtDirect\PagetreeCollectionsProcessor::class;

	$GLOBALS['TYPO3_CONF_VARS']['BE']['toolbarItems'][] = \TYPO3\CMS\Workspaces\Backend\ToolbarItems\WorkspaceSelectorToolbarItem::class;
}
