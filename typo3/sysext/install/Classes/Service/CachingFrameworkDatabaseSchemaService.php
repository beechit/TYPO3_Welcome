<?php
namespace TYPO3\CMS\Install\Service;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

/**
 * This service provides the sql schema for the caching framework
 */
class CachingFrameworkDatabaseSchemaService {

	/**
	 * Get schema SQL of required cache framework tables.
	 *
	 * This method needs ext_localconf and ext_tables loaded!
	 *
	 * This is a hack, but there was no smarter solution with current cache configuration setup:
	 * ToolController sets the extbase caches to NullBackend to ensure the install tool does not
	 * cache anything. The CacheManager gets the required SQL from database backends only, so we need to
	 * temporarily 'fake' the standard db backends for extbase caches so they are respected.
	 *
	 * Additionally, the extbase_object cache is already in use and instantiated, and the CacheManager singleton
	 * does not allow overriding this definition. The only option at the moment is to 'fake' another cache with
	 * a different name, and then substitute this name in the sql content with the real one.
	 *
	 * @TODO: http://forge.typo3.org/issues/54498
	 * @TODO: It might be possible to reduce this ugly construct by circumventing the 'singleton' of CacheManager by using 'new'
	 *
	 * @return string Cache framework SQL
	 */
	public function getCachingFrameworkRequiredDatabaseSchema() {
		$cacheConfigurationBackup = $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'];
		$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['extbase_datamapfactory_datamap'] = array(
			'groups' => array('system')
		);
		$extbaseObjectFakeName = uniqid('extbase_object');
		$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][$extbaseObjectFakeName] = array(
			'groups' => array('system')
		);
		$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['extbase_reflection'] = array(
			'groups' => array('system')
		);
		$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['extbase_typo3dbbackend_tablecolumns'] = array(
			'groups' => array('system')
		);
		/** @var \TYPO3\CMS\Core\Cache\CacheManager $cacheManager */
		$cacheManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Cache\CacheManager::class);
		$cacheManager->setCacheConfigurations($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']);
		$cacheSqlString = \TYPO3\CMS\Core\Cache\Cache::getDatabaseTableDefinitions();
		$sqlString = str_replace($extbaseObjectFakeName, 'extbase_object', $cacheSqlString);
		$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'] = $cacheConfigurationBackup;
		$cacheManager->setCacheConfigurations($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']);

		return $sqlString;
	}

	/**
	 * A slot method to inject the required caching framework database tables to the
	 * tables definitions string
	 *
	 * @param array $sqlString
	 * @return array
	 */
	public function addCachingFrameworkRequiredDatabaseSchemaToTablesDefinition(array $sqlString) {
		$sqlString[] = $this->getCachingFrameworkRequiredDatabaseSchema();
		return array('sqlString' => $sqlString);
	}

}
