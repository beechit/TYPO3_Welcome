<?php
defined('TYPO3_MODE') or die();

$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Core\Database\DatabaseConnection::class] = array('className' => \TYPO3\CMS\Dbal\Database\DatabaseConnection::class);
$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Core\Database\SqlParser::class] = array('className' => \TYPO3\CMS\Dbal\Database\SqlParser::class);
$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Recordlist\RecordList\DatabaseRecordList::class] = array('className' => \TYPO3\CMS\Dbal\RecordList\DatabaseRecordList::class);

// Register caches if not already done in localconf.php or a previously loaded extension.
if (!is_array($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['dbal'])) {
	$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['dbal'] = array(
		'backend' => \TYPO3\CMS\Core\Cache\Backend\TransientMemoryBackend::class,
		'groups' => array()
	);
}
