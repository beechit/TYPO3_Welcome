<?php
defined('TYPO3_MODE') or die();

if (TYPO3_MODE === 'BE') {
	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('sys_action', 'EXT:sys_action/locallang_csh_sysaction.xlf');
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['taskcenter']['sys_action']['tx_sysaction_task'] = array(
		'title' => 'LLL:EXT:sys_action/locallang_tca.xlf:sys_action',
		'description' => 'LLL:EXT:sys_action/locallang_csh_sysaction.xlf:.description',
		'icon' => 'EXT:sys_action/x-sys_action.png'
	);
}
