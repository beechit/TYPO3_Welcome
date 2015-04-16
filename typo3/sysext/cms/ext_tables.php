<?php
defined('TYPO3_MODE') or die();

if (TYPO3_MODE === 'BE') {
	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('_MOD_web_layout', 'EXT:cms/locallang_csh_weblayout.xlf');
	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('_MOD_web_info', 'EXT:cms/locallang_csh_webinfo.xlf');
	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::insertModuleFunction(
		'web_info',
		\TYPO3\CMS\Frontend\Controller\PageInformationController::class,
		NULL,
		'LLL:EXT:cms/locallang_tca.xlf:mod_tx_cms_webinfo_page'
	);
	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::insertModuleFunction(
		'web_info',
		\TYPO3\CMS\Frontend\Controller\TranslationStatusController::class,
		NULL,
		'LLL:EXT:cms/locallang_tca.xlf:mod_tx_cms_webinfo_lang'
	);
}
// Add allowed records to pages:
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::allowTableOnStandardPages('pages_language_overlay,tt_content,sys_template,sys_domain,backend_layout');
