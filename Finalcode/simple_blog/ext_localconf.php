<?php
if (!defined('TYPO3_MODE')) {
	die('Access denied.');
}

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
	'BeechIt.' . $_EXTKEY,
	'List',
	array(
		'Blog' => 'list, show, new, create',
		
	),
	// non-cacheable actions
	array(
		'Blog' => 'create',
		
	)
);
