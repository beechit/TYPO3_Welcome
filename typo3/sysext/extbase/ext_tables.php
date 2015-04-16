<?php
defined('TYPO3_MODE') or die();

if (TYPO3_MODE === 'BE') {
	// register Extbase dispatcher for modules
	$TBE_MODULES['_dispatcher'][] = \TYPO3\CMS\Extbase\Core\ModuleRunnerInterface::class;
}
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['reports']['tx_reports']['status']['providers']['extbase'][] = \TYPO3\CMS\Extbase\Utility\ExtbaseRequirementsCheckUtility::class;

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][\TYPO3\CMS\Extbase\Scheduler\Task::class] = array(
	'extension' => $_EXTKEY,
	'title' => 'LLL:EXT:extbase/Resources/Private/Language/locallang_db.xlf:task.name',
	'description' => 'LLL:EXT:extbase/Resources/Private/Language/locallang_db.xlf:task.description',
	'additionalFields' => \TYPO3\CMS\Extbase\Scheduler\FieldProvider::class
);

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['checkFlexFormValue'][] = 'TYPO3\CMS\Extbase\Hook\DataHandler\CheckFlexFormValue';
