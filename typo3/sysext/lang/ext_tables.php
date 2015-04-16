<?php
defined('TYPO3_MODE') or die();

if (TYPO3_MODE === 'BE' && !(TYPO3_REQUESTTYPE & TYPO3_REQUESTTYPE_INSTALL)) {

	// Register the backend module
	\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
		'TYPO3.CMS.' . $_EXTKEY,
		'tools',
		'language',
		'after:extensionmanager',
		array(
			'Language' => 'listLanguages, listTranslations, getTranslations, updateLanguage, updateTranslation, activateLanguage, deactivateLanguage',
		),
		array(
			'access' => 'admin',
			'icon' => 'EXT:' . $_EXTKEY . '/Resources/Public/Images/module-lang.png',
			'labels' => 'LLL:EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang_mod.xlf',
		)
	);

}
