<?php
namespace EBT\ExtensionBuilder\Utility;

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011 Sebastian Michaelsen
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

class ExtensionInstallationStatus {
	/**
	 * @var \TYPO3\CMS\Extbase\Object\ObjectManager
	 */
	protected $objectManager = NULL;

	/**
	 * @var \EBT\ExtensionBuilder\Domain\Model\Extension
	 */
	protected $extension = NULL;

	/**
	 * @var \TYPO3\CMS\Extensionmanager\Utility\InstallUtility
	 */
	protected $installTool = NULL;

	/**
	 * @var array[]
	 */
	protected $updateStatements = array();

	/**
	 * @var bool
	 */
	protected $dbUpdateNeeded = FALSE;

	public function __construct() {
		$this->installTool = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Extensionmanager\\Utility\\InstallUtility');
	}

	/**
	 * @param \EBT\ExtensionBuilder\Domain\Model\Extension $extension
	 */
	public function setExtension($extension) {
		$this->extension = $extension;
	}

	public function getStatusMessage() {
		$statusMessage = '';
		$this->checkForDbUpdate($this->extension->getExtensionKey(), $this->extension->getExtensionDir().'ext_tables.sql');

		if ($this->dbUpdateNeeded) {
			$statusMessage .= '<p>Database has to be updated!</p>';
			$typeInfo = array(
				'add' => 'Add fields',
				'change' => 'Change fields',
				'create_table' => 'Create tables'
			);
			$statusMessage .= '<div id="dbUpdateStatementsWrapper"><table>';
			foreach($this->updateStatements as $type => $statements) {

				$statusMessage .= '<tr><td></td><td style="text-align:left;padding-left:15px">' . $typeInfo[$type] . ':</td></tr>';
				foreach($statements as $key => $statement) {
					if($type == 'add') {
						$statusMessage .= '<tr><td><input type="checkbox" name="dbUpdateStatements[]" value="' . $key . '" checked="checked" /></td><td style="text-align:left;padding-left:15px">' . $statement . '</td></tr>';
					} elseif ($type=== 'change') {
						$statusMessage .= '<tr><td><input type="checkbox" name="dbUpdateStatements[]" value="' . $key . '" checked="checked" /></td><td style="text-align:left;padding-left:15px">' . $statement . '</td></tr>';
						$statusMessage .= '<tr><td></td><td style="text-align:left;padding-left:15px">Current value: ' . $this->updateStatements['change_currentValue'][$key] . '</td></tr>';
					} elseif ($type=== 'create_table') {
						$statusMessage .= '<tr><td><input type="checkbox" name="dbUpdateStatements[]" value="' . $key . '" checked="checked" /></td><td style="text-align:left;padding-left:15px;">' . nl2br($statement) . '</td></tr>';
					}
				}
			}
			$statusMessage .= '</table></div>';

		}

		if (!\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded($this->extension->getExtensionKey())) {
			$statusMessage .= '<p>Your Extension is not installed yet.</p>';
		}

		return $statusMessage;
	}

	/**
	 * @param string $extKey
	 * @return void
	 */

	public function checkForDbUpdate($extensionKey) {
		$this->dbUpdateNeeded = FALSE;
		if (ExtensionManagementUtility::isLoaded($extensionKey)) {
			$sqlFile = ExtensionManagementUtility::extPath($extensionKey) . 'ext_tables.sql';
			if (@file_exists($sqlFile)) {
				$this->objectManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Object\\ObjectManager');
				if (class_exists('TYPO3\\CMS\\Install\\Service\\SqlSchemaMigrationService')) {
					/* @var \TYPO3\CMS\Install\Service\SqlSchemaMigrationService $sqlHandler */
					$sqlHandler = $this->objectManager->get('TYPO3\\CMS\\Install\\Service\\SqlSchemaMigrationService');
				} else {
					/* @var \TYPO3\CMS\Install\Sql\SchemaMigrator $sqlHandler */
					$sqlHandler = $this->objectManager->get('TYPO3\\CMS\\Install\\Sql\\SchemaMigrator');
				}
				$sqlContent = \TYPO3\CMS\Core\Utility\GeneralUtility::getUrl($sqlFile);
				/** @var $cacheManager \TYPO3\CMS\Core\Cache\CacheManager */
				$cacheManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Cache\\CacheManager');
				$cacheManager->setCacheConfigurations($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']);
				$sqlContent .= \TYPO3\CMS\Core\Cache\Cache::getDatabaseTableDefinitions();
				$fieldDefinitionsFromFile = $sqlHandler->getFieldDefinitions_fileContent($sqlContent);
				if (count($fieldDefinitionsFromFile)) {
					$fieldDefinitionsFromCurrentDatabase = $sqlHandler->getFieldDefinitions_database();
					$updateTableDefinition = $sqlHandler->getDatabaseExtra($fieldDefinitionsFromFile, $fieldDefinitionsFromCurrentDatabase);
					$this->updateStatements = $sqlHandler->getUpdateSuggestions($updateTableDefinition);
					if (!empty($updateTableDefinition['extra']) || !empty($updateTableDefinition['diff']) || !empty($updateTableDefinition['diff_currentValues'])) {
						$this->dbUpdateNeeded = TRUE;
					}
				}
			}
		}
	}

	public function performDbUpdates($params) {
		$hasErrors = FALSE;
		if (!empty($params['updateStatements']) && !empty($params['extensionKey'])) {
			$this->checkForDbUpdate($params['extensionKey']);
			if ($this->dbUpdateNeeded) {
				foreach($this->updateStatements as $type => $statements) {
					foreach($statements as $key => $statement) {
						if (in_array($type, array('change', 'add', 'create_table')) && in_array($key, $params['updateStatements'])) {
							$res = $this->getDatabaseConnection()->admin_query($statement);
							if ($res === FALSE) {
								$hasErrors = TRUE;
								\TYPO3\CMS\Core\Utility\GeneralUtility::devlog('SQL error','extension_builder',0,array('statement' => $statement, 'error' => $this->getDatabaseConnection()->sql_error()));
							} elseif (is_resource($res) || is_a($res, '\\mysqli_result')) {
								$this->getDatabaseConnection()->sql_free_result($res);
							}
						}
					}
				}
			}
		}
		if ($hasErrors) {
			return array('error' => 'Database could not be updated. Please check it in the update wizzard of the install tool');
		} else {
			return array('success' => 'Database was successfully updated');
		}
	}

	/**
	 * @return bool
	 */
	public function isDbUpdateNeeded() {
		return $this->dbUpdateNeeded;
	}

	/**
	 * @return array
	 */
	public function getUpdateStatements() {
		return $this->updateStatements;
	}

	/**
	 * @return \TYPO3\CMS\Core\Database\DatabaseConnection
	 */
	protected function getDatabaseConnection() {
		return $GLOBALS['TYPO3_DB'];
	}
}
