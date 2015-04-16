<?php
defined('TYPO3_MODE') or die();

if (TYPO3_MODE === 'BE') {
	// Take note of conflicting extensions
	$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$_EXTKEY]['conflicts'] = $EM_CONF[$_EXTKEY]['constraints']['conflicts'];
	// Register Status Report Hook
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['reports']['tx_reports']['status']['providers']['htmlArea RTE'][] = \TYPO3\CMS\Rtehtmlarea\Hook\StatusReportConflictsCheckHook::class;
}
