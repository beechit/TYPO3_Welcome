<?php
namespace TYPO3\CMS\Backend\Form;

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

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Lang\LanguageService;
use TYPO3\CMS\Extbase\SignalSlot\Dispatcher;

/**
 * Class for getting and transforming data for display in backend forms (TCEforms)
 *
 * @author Kasper Skårhøj <kasperYYYY@typo3.com>
 */
class DataPreprocessor {

	/**
	 * If set, the records requested are locked.
	 *
	 * @var int
	 */
	public $lockRecords = 0;

	/**
	 * Is set externally if RTE is disabled.
	 *
	 * @var int
	 */
	public $disableRTE = 0;

	/**
	 * If the pid in the command is 'prev' then $prevPageID is used as pid for the record.
	 * This is used to attach new records to other previous records eg. new pages.
	 *
	 * @var string
	 */
	public $prevPageID = '';

	/**
	 * Can be set with an array of default values for tables. First key is table name,
	 * second level keys are field names. Originally this was a GLOBAL array used internally.
	 *
	 * @var array
	 */
	public $defVals = array();

	/**
	 * If set, the processed data is overlaid the raw record.
	 *
	 * @var bool
	 */
	public $addRawData = FALSE;

	/**
	 * Used to register, which items are already loaded!!
	 *
	 * @var array
	 */
	public $regTableItems = array();

	/**
	 * This stores the record data of the loaded records
	 *
	 * @var array
	 */
	public $regTableItems_data = array();

	/**
	 * Contains loadModules object, if used. (for reuse internally)
	 *
	 * @var string
	 */
	public $loadModules = '';

	/***********************************************
	 *
	 * Getting record content, ready for display in TCEforms
	 *
	 ***********************************************/
	/**
	 * A function which can be used for load a batch of records from $table into internal memory of this object.
	 * The function is also used to produce proper default data for new records
	 * Ultimately the function will call renderRecord()
	 *
	 * @param string $table Table name, must be found in $GLOBALS['TCA']
	 * @param string $idList Comma list of id values. If $idList is "prev" then the value from $this->prevPageID is used. NOTICE: If $operation is "new", then negative ids are meant to point to a "previous" record and positive ids are PID values for new records. Otherwise (for existing records that is) it is straight forward table/id pairs.
	 * @param string $operation If "new", then a record with default data is returned. Further, the $id values are meant to be PID values (or if negative, pointing to a previous record). If NOT new, then the table/ids are just pointing to an existing record!
	 * @return void
	 * @see renderRecord()
	 */
	public function fetchRecord($table, $idList, $operation) {
		if ((string)$idList === 'prev') {
			$idList = $this->prevPageID;
		}
		if ($GLOBALS['TCA'][$table]) {
			// For each ID value (int) we
			$ids = GeneralUtility::trimExplode(',', $idList, TRUE);
			foreach ($ids as $id) {
				// If ID is not blank:
				if ((string)$id !== '') {
					// For new records to be created, find default values:
					if ($operation == 'new') {
						// Default values:
						// Used to store default values as found here:
						$newRow = array();
						// Default values as set in userTS:
						$TCAdefaultOverride = $GLOBALS['BE_USER']->getTSConfigProp('TCAdefaults');
						if (is_array($TCAdefaultOverride[$table . '.'])) {
							foreach ($TCAdefaultOverride[$table . '.'] as $theF => $theV) {
								if (isset($GLOBALS['TCA'][$table]['columns'][$theF])) {
									$newRow[$theF] = $theV;
								}
							}
						}
						if ($id < 0) {
							$record = BackendUtility::getRecord($table, abs($id), 'pid');
							$pid = $record['pid'];
							unset($record);
						} else {
							$pid = (int)$id;
						}
						$pageTS = BackendUtility::getPagesTSconfig($pid);
						if (isset($pageTS['TCAdefaults.'])) {
							$TCAPageTSOverride = $pageTS['TCAdefaults.'];
							if (is_array($TCAPageTSOverride[$table . '.'])) {
								foreach ($TCAPageTSOverride[$table . '.'] as $theF => $theV) {
									if (isset($GLOBALS['TCA'][$table]['columns'][$theF])) {
										$newRow[$theF] = $theV;
									}
								}
							}
						}
						// Default values as submitted:
						if (!empty($this->defVals[$table]) && is_array($this->defVals[$table])) {
							foreach ($this->defVals[$table] as $theF => $theV) {
								if (isset($GLOBALS['TCA'][$table]['columns'][$theF])) {
									$newRow[$theF] = $theV;
								}
							}
						}
						// Fetch default values if a previous record exists
						if ($id < 0 && $GLOBALS['TCA'][$table]['ctrl']['useColumnsForDefaultValues']) {
							// Fetches the previous record:
							$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', $table, 'uid=' . abs($id) . BackendUtility::deleteClause($table));
							if ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
								// Gets the list of fields to copy from the previous record.
								$fArr = GeneralUtility::trimExplode(',', $GLOBALS['TCA'][$table]['ctrl']['useColumnsForDefaultValues'], TRUE);
								foreach ($fArr as $theF) {
									if (isset($GLOBALS['TCA'][$table]['columns'][$theF]) && !isset($newRow[$theF])) {
										$newRow[$theF] = $row[$theF];
									}
								}
							}
							$GLOBALS['TYPO3_DB']->sql_free_result($res);
						}
						// Finally, call renderRecord:
						$this->renderRecord($table, uniqid('NEW', TRUE), $id, $newRow);
					} else {
						$id = (int)$id;
						// Fetch database values
						$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', $table, 'uid=' . $id . BackendUtility::deleteClause($table));
						if ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
							BackendUtility::fixVersioningPid($table, $row);
							$this->renderRecord($table, $id, $row['pid'], $row);
							$this->lockRecord($table, $id, $table === 'tt_content' ? $row['pid'] : 0);
						}
						$GLOBALS['TYPO3_DB']->sql_free_result($res);
					}
				}
			}
		}

		$this->emitFetchRecordPostProcessingSignal();
	}

	/**
	 * This function performs processing on the input $row array and stores internally a corresponding array which contains processed values, ready to pass on to the TCEforms rendering in the frontend!
	 * The objective with this function is to prepare the content for handling in TCEforms.
	 * Default values from outside/TSconfig is added by fetchRecord(). In this function default values from TCA is used if a field is NOT defined in $row.
	 * The resulting, processed row is stored in $this->regTableItems_data[$uniqueItemRef], where $uniqueItemRef is "[tablename]_[id-value]"
	 *
	 * @param string $table The table name
	 * @param string $id The uid value of the record (int). Can also be a string (NEW-something) if the record is a NEW record.
	 * @param int $pid The pid integer. For existing records this is of course the row's "pid" field. For new records it can be either a page id (positive) or a pointer to another record from the SAME table (negative) after which the record should be inserted (or on same page)
	 * @param array $row The row of the current record. If NEW record, then it may be loaded with default values (by eg. fetchRecord()).
	 * @return void
	 * @see fetchRecord()
	 */
	public function renderRecord($table, $id, $pid, $row) {
		$dateTimeFormats = $GLOBALS['TYPO3_DB']->getDateTimeFormats($table);
		foreach ($GLOBALS['TCA'][$table]['columns'] as $column => $config) {
			if (isset($config['config']['dbType']) && GeneralUtility::inList('date,datetime', $config['config']['dbType'])) {
				$emptyValue = $dateTimeFormats[$config['config']['dbType']]['empty'];
				$row[$column] = !empty($row[$column]) && $row[$column] !== $emptyValue ? strtotime($row[$column]) : 0;
			}
		}
		// Init:
		$uniqueItemRef = $table . '_' . $id;
		// Fetches the true PAGE TSconfig pid to use later, if needed. (Until now, only for the RTE, but later..., who knows?)
		list($tscPID) = BackendUtility::getTSCpid($table, $id, $pid);
		$TSconfig = BackendUtility::getTCEFORM_TSconfig($table, array_merge($row, array('uid' => $id, 'pid' => $pid)));
		// If the record has not already been loaded (in which case we DON'T do it again)...
		if (!$this->regTableItems[$uniqueItemRef]) {
			$this->regTableItems[$uniqueItemRef] = 1;
			// set "loaded" flag.
			// If the table is pages, set the previous page id internally.
			if ($table == 'pages') {
				$this->prevPageID = $id;
			}
			$this->regTableItems_data[$uniqueItemRef] = $this->renderRecordRaw($table, $id, $pid, $row, $TSconfig, $tscPID);
			// Merges the processed array on-top of the raw one - this is done because some things in TCEforms may need access to other fields than those in the columns configuration!
			if ($this->addRawData && is_array($row) && is_array($this->regTableItems_data[$uniqueItemRef])) {
				$this->regTableItems_data[$uniqueItemRef] = array_merge($row, $this->regTableItems_data[$uniqueItemRef]);
			}
		}
	}

	/**
	 * This function performs processing on the input $row array and stores internally a corresponding array which contains processed values, ready to pass on to the TCEforms rendering in the frontend!
	 * The objective with this function is to prepare the content for handling in TCEforms.
	 * In opposite to renderRecord() this function do not prepare things like fetching TSconfig and others.
	 * The resulting, processed row will be returned.
	 *
	 * @param string $table The table name
	 * @param string $id The uid value of the record (int). Can also be a string (NEW-something) if the record is a NEW record.
	 * @param int $pid The pid integer. For existing records this is of course the row's "pid" field. For new records it can be either a page id (positive) or a pointer to another record from the SAME table (negative) after which the record should be inserted (or on same page)
	 * @param array $row The row of the current record. If NEW record, then it may be loaded with default values (by eg. fetchRecord()).
	 * @param array $TSconfig Tsconfig array
	 * @param int $tscPID PAGE TSconfig pid
	 * @return array Processed record data
	 * @see renderRecord()
	 */
	public function renderRecordRaw($table, $id, $pid, $row, $TSconfig = '', $tscPID = 0) {
		if (!is_array($TSconfig)) {
			$TSconfig = array();
		}
		// Create blank accumulation array:
		$totalRecordContent = array();
		// Traverse the configured columns for the table (TCA):
		// For each column configured, we will perform processing if needed based on the type (eg. for "group" and "select" types this is needed)
		$copyOfColumns = $GLOBALS['TCA'][$table]['columns'];
		foreach ($copyOfColumns as $field => $fieldConfig) {
			// Set $data variable for the field, either inputted value from $row - or if not found, the default value as defined in the "config" array
			if (isset($row[$field])) {
				$data = (string)$row[$field];
			} elseif (!empty($fieldConfig['config']['eval']) && GeneralUtility::inList($fieldConfig['config']['eval'], 'null')) {
				// Field exists but is set to NULL
				if (array_key_exists($field, $row)) {
					$data = NULL;
				// Only use NULL if default value was explicitly set to be backward compatible.
				} elseif (array_key_exists('default', $fieldConfig['config']) && $fieldConfig['config']['default'] === NULL) {
					$data = NULL;
				} else {
					$data = (string)$fieldConfig['config']['default'];
				}
			} else {
				$data = (string)$fieldConfig['config']['default'];
			}
			$data = $this->renderRecord_SW($data, $fieldConfig, $TSconfig, $table, $row, $field);
			$totalRecordContent[$field] = $data;
		}
		// Register items, mostly for external use (overriding the regItem() function)
		foreach ($totalRecordContent as $field => $data) {
			$this->regItem($table, $id, $field, $data);
		}
		// Finally, store the result:
		reset($totalRecordContent);
		return $totalRecordContent;
	}

	/**
	 * Function with the switch() construct which triggers functions for processing of the data value depending on the TCA-config field type.
	 *
	 * @param string $data Value to process
	 * @param array $fieldConfig TCA/columns array for field	(independent of TCA for flexforms - coming from XML then)
	 * @param array $TSconfig TSconfig	(blank for flexforms for now)
	 * @param string $table Table name
	 * @param array $row The row array, always of the real record (also for flexforms)
	 * @param string $field The field
	 * @return string Modified $value
	 */
	public function renderRecord_SW($data, $fieldConfig, $TSconfig, $table, $row, $field) {
		switch ((string)$fieldConfig['config']['type']) {
			case 'group':
				$data = $this->renderRecord_groupProc($data, $fieldConfig, $TSconfig, $table, $row, $field);
				break;
			case 'select':
				$data = $this->renderRecord_selectProc($data, $fieldConfig, $TSconfig, $table, $row, $field);
				break;
			case 'flex':
				$data = $this->renderRecord_flexProc($data, $fieldConfig, $TSconfig, $table, $row, $field);
				break;
			case 'inline':
				$data = $this->renderRecord_inlineProc($data, $fieldConfig, $TSconfig, $table, $row, $field);
				break;
		}
		return $data;
	}

	/**
	 * Processing of the data value in case the field type is "group"
	 *
	 * @param string $data The field value
	 * @param array $fieldConfig TCA field config
	 * @param array $TSconfig TCEform TSconfig for the record
	 * @param string $table Table name
	 * @param array $row The row
	 * @param string $field Field name
	 * @return string The processed input field value ($data)
	 * @access private
	 * @see renderRecord()
	 */
	public function renderRecord_groupProc($data, $fieldConfig, $TSconfig, $table, $row, $field) {
		switch ($fieldConfig['config']['internal_type']) {
			case 'file_reference':
			case 'file':
				// Init array used to accumulate the files:
				$dataAcc = array();
				// Now, load the files into the $dataAcc array, whether stored by MM or as a list of filenames:
				if ($fieldConfig['config']['MM']) {
					$loadDB = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\RelationHandler::class);
					$loadDB->start('', 'files', $fieldConfig['config']['MM'], $row['uid']);
					// Setting dummy startup
					foreach ($loadDB->itemArray as $value) {
						if ($value['id']) {
							$dataAcc[] = rawurlencode($value['id']) . '|' . rawurlencode(PathUtility::basename($value['id']));
						}
					}
				} else {
					$fileList = GeneralUtility::trimExplode(',', $data, TRUE);
					foreach ($fileList as $value) {
						if ($value) {
							$dataAcc[] = rawurlencode($value) . '|' . rawurlencode(PathUtility::basename($value));
						}
					}
				}
				// Implode the accumulation array to a comma separated string:
				$data = implode(',', $dataAcc);
				break;
			case 'db':
				$loadDB = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\RelationHandler::class);
				/** @var $loadDB \TYPO3\CMS\Core\Database\RelationHandler */
				$loadDB->start($data, $fieldConfig['config']['allowed'], $fieldConfig['config']['MM'], $row['uid'], $table, $fieldConfig['config']);
				$loadDB->getFromDB();
				$data = $loadDB->readyForInterface();
				break;
		}
		return $data;
	}

	/**
	 * Processing of the data value in case the field type is "select"
	 *
	 * @param string $data The field value
	 * @param array $fieldConfig TCA field config
	 * @param array $TSconfig TCEform TSconfig for the record
	 * @param string $table Table name
	 * @param array $row The row
	 * @param string $field Field name
	 * @return string The processed input field value ($data)
	 * @access private
	 * @see renderRecord()
	 */
	public function renderRecord_selectProc($data, $fieldConfig, $TSconfig, $table, $row, $field) {
		// Initialize:
		// Current data set.
		$elements = GeneralUtility::trimExplode(',', $data, TRUE);
		// New data set, ready for interface (list of values, rawurlencoded)
		$dataAcc = array();
		// For list selectors (multi-value):
		if ((int)$fieldConfig['config']['maxitems'] > 1 || $fieldConfig['config']['renderMode'] === 'tree') {
			$languageService = $this->getLanguageService();
			// Add regular elements:
			if (!is_array($fieldConfig['config']['items'])) {
				$fieldConfig['config']['items'] = array();
			}
			$fieldConfig['config']['items'] = $this->procesItemArray($fieldConfig['config']['items'], $fieldConfig['config'], $TSconfig[$field], $table, $row, $field);
			foreach ($fieldConfig['config']['items'] as $pvpv) {
				foreach ($elements as $eKey => $value) {
					if ((string)$value === (string)$pvpv[1]) {
						$dataAcc[$eKey] = rawurlencode($pvpv[1]) . '|' . rawurlencode($languageService->sL($pvpv[0]));
					}
				}
			}
			// Add "special"
			if ($fieldConfig['config']['special']) {
				$dataAcc = $this->selectAddSpecial($dataAcc, $elements, $fieldConfig['config']['special']);
			}
			// Add "foreign table" stuff:
			if ($GLOBALS['TCA'][$fieldConfig['config']['foreign_table']]) {
				$dataAcc = $this->selectAddForeign($dataAcc, $elements, $fieldConfig, $field, $TSconfig, $row, $table);
			}
			// Always keep the native order for display in interface:
			ksort($dataAcc);
		} else {
			// Normal, <= 1 -> value without title on it
			if ($GLOBALS['TCA'][$fieldConfig['config']['foreign_table']]) {
				// Getting the data
				$dataIds = $this->getDataIdList($elements, $fieldConfig, $row, $table);
				if (!count($dataIds)) {
					$dataIds = array(0);
				}
				$dataAcc[] = $dataIds[0];
			} else {
				$dataAcc[] = $elements[0];
			}
		}
		return implode(',', $dataAcc);
	}

	/**
	 * Processing of the data value in case the field type is "flex"
	 * MUST NOT be called in case of already INSIDE a flexform!
	 *
	 * @param string $data The field value
	 * @param array $fieldConfig CA field config
	 * @param array $TSconfig TCEform TSconfig for the record
	 * @param string $table Table name
	 * @param array $row The row
	 * @param string $field Field name
	 * @return string The processed input field value ($data)
	 * @access private
	 * @see renderRecord()
	 */
	public function renderRecord_flexProc($data, $fieldConfig, $TSconfig, $table, $row, $field) {
		// Convert the XML data to PHP array:
		if (!is_array($data)) {
			$currentValueArray = GeneralUtility::xml2array($data);
		} else {
			$currentValueArray = $data;
		}
		if (is_array($currentValueArray)) {
			// Get current value array:
			$dataStructArray = BackendUtility::getFlexFormDS($fieldConfig['config'], $row, $table, $field);
			// Manipulate Flexform DS via TSConfig and group access lists
			if (is_array($dataStructArray)) {
				$flexFormHelper = GeneralUtility::makeInstance(\TYPO3\CMS\Backend\Form\FlexFormsHelper::class);
				$dataStructArray = $flexFormHelper->modifyFlexFormDS($dataStructArray, $table, $field, $row, $fieldConfig);
				unset($flexFormHelper);
			}
			if (is_array($dataStructArray)) {
				$currentValueArray['data'] = $this->renderRecord_flexProc_procInData($currentValueArray['data'], $dataStructArray, array($data, $fieldConfig, $TSconfig, $table, $row, $field));
				$flexObj = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Configuration\FlexForm\FlexFormTools::class);
				$data = $flexObj->flexArray2Xml($currentValueArray, TRUE);
			}
		}
		return $data;
	}

	/**
	 * Processing of the content in $totalRecordcontent based on settings in the types-configuration
	 *
	 * @param array $totalRecordContent The array of values which has been processed according to their type (eg. "group" or "select")
	 * @param array $types_fieldConfig The "types" configuration for the current display of fields.
	 * @param int $tscPID PAGE TSconfig PID
	 * @param string $table Table name
	 * @param int $pid PID value
	 * @return array The processed version of $totalRecordContent
	 * @access private
	 * @deprecated since TYPO3 CMS 7, will be removed in TYPO3 CMS 8
	 */
	public function renderRecord_typesProc($totalRecordContent, $types_fieldConfig, $tscPID, $table, $pid) {
		GeneralUtility::logDeprecatedFunction();
		return $totalRecordContent;
	}

	/**
	 * Processing of the data value in case the field type is "inline"
	 * In some parts nearly the same as type "select"
	 *
	 * @param string $data The field value
	 * @param array $fieldConfig TCA field config
	 * @param array $TSconfig TCEform TSconfig for the record
	 * @param string $table Table name
	 * @param array $row The row
	 * @param string $field Field name
	 * @return string The processed input field value ($data)
	 * @access private
	 * @see renderRecord()
	 */
	public function renderRecord_inlineProc($data, $fieldConfig, $TSconfig, $table, $row, $field) {
		// Initialize:
		// Current data set.
		$elements = GeneralUtility::trimExplode(',', $data);
		// New data set, ready for interface (list of values, rawurlencoded)
		$dataAcc = array();
		// At this point all records that CAN be selected is found in $recordList
		// Now, get the data from loadDBgroup based on the input list of values.
		$dataIds = $this->getDataIdList($elements, $fieldConfig, $row, $table);
		// After this we can traverse the loadDBgroup values and match values with the list of possible values in $recordList:
		foreach ($dataIds as $theId) {
			if ($fieldConfig['config']['MM'] || $fieldConfig['config']['foreign_field']) {
				$dataAcc[] = $theId;
			} else {
				foreach ($elements as $eKey => $value) {
					if ((int)$theId === (int)$value) {
						$dataAcc[$eKey] = $theId;
					}
				}
			}
		}
		return implode(',', $dataAcc);
	}

	/***********************************************
	 *
	 * FlexForm processing functions
	 *
	 ***********************************************/
	/**
	 * Function traversing sheets/languages for flex form data structures
	 *
	 * @param array $dataPart Data array
	 * @param array $dataStructArray Data Structure array
	 * @param array $pParams Various parameters to pass-through
	 * @return array Modified $dataPart array.
	 * @access private
	 * @see \TYPO3\CMS\Core\DataHandling\DataHandler::checkValue_flex_procInData(), renderRecord_flexProc_procInData_travDS()
	 */
	public function renderRecord_flexProc_procInData($dataPart, $dataStructArray, $pParams) {
		if (is_array($dataPart)) {
			foreach ($dataPart as $sKey => $sheetDef) {
				list($dataStruct, $actualSheet) = GeneralUtility::resolveSheetDefInDS($dataStructArray, $sKey);
				if (is_array($dataStruct) && $actualSheet == $sKey && is_array($sheetDef)) {
					foreach ($sheetDef as $lKey => $lData) {
						$this->renderRecord_flexProc_procInData_travDS($dataPart[$sKey][$lKey], $dataStruct['ROOT']['el'], $pParams);
					}
				}
			}
		}
		return $dataPart;
	}

	/**
	 * Traverse data array / structure
	 *
	 * @param array $dataValues Data array passed by reference.
	 * @param array $DSelements Data structure
	 * @param array $pParams Various parameters pass-through.
	 * @return void
	 * @see \TYPO3\CMS\Core\DataHandling\DataHandler::checkValue_flex_procInData(), renderRecord_flexProc_procInData_travDS()
	 */
	public function renderRecord_flexProc_procInData_travDS(&$dataValues, $DSelements, $pParams) {
		if (is_array($DSelements)) {
			// For each DS element:
			foreach ($DSelements as $key => $dsConf) {
				// Array/Section:
				if ($DSelements[$key]['type'] == 'array') {
					if (is_array($dataValues[$key]['el'])) {
						if ($DSelements[$key]['section']) {
							foreach ($dataValues[$key]['el'] as $ik => $el) {
								if (is_array($el)) {
									$theKey = key($el);
									if (is_array($dataValues[$key]['el'][$ik][$theKey]['el'])) {
										$this->renderRecord_flexProc_procInData_travDS($dataValues[$key]['el'][$ik][$theKey]['el'], $DSelements[$key]['el'][$theKey]['el'], $pParams);
									}
								}
							}
						} else {
							if (!isset($dataValues[$key]['el'])) {
								$dataValues[$key]['el'] = array();
							}
							$this->renderRecord_flexProc_procInData_travDS($dataValues[$key]['el'], $DSelements[$key]['el'], $pParams);
						}
					}
				} else {
					if (is_array($dsConf['TCEforms']['config']) && is_array($dataValues[$key])) {
						foreach ($dataValues[$key] as $vKey => $data) {
							// $data,$fieldConfig,$TSconfig,$table,$row,$field
							list(, , $CVTSconfig, $CVtable, $CVrow, $CVfield) = $pParams;
							// Set default value:
							if (!isset($dataValues[$key][$vKey])) {
								$dataValues[$key][$vKey] = $dsConf['TCEforms']['config']['default'];
							}
							// Process value:
							$dataValues[$key][$vKey] = $this->renderRecord_SW($dataValues[$key][$vKey], $dsConf['TCEforms'], $CVTSconfig, $CVtable, $CVrow, $CVfield);
						}
					}
				}
			}
		}
	}

	/***********************************************
	 *
	 * Selector box processing functions
	 *
	 ***********************************************/
	/**
	 * Adding "special" types to the $dataAcc array of selector items
	 *
	 * @param array $dataAcc Array with numeric keys, containing values for the selector box, prepared for interface. We are going to add elements to this array as needed.
	 * @param array $elements The array of original elements - basically the field value exploded by ",
	 * @param string $specialKey The "special" key from the TCA config of the field. Determines the type of processing in here.
	 * @return array Modified $dataAcc array
	 * @access private
	 * @see renderRecord_selectProc()
	 */
	public function selectAddSpecial($dataAcc, $elements, $specialKey) {
		$languageService = $this->getLanguageService();
		// Special select types:
		switch ((string)$specialKey) {
			case 'tables':
				$tNames = array_keys($GLOBALS['TCA']);
				foreach ($tNames as $tableName) {
					foreach ($elements as $eKey => $value) {
						if ((string)$tableName === (string)$value) {
							$dataAcc[$eKey] = rawurlencode($value) . '|' . rawurlencode($languageService->sL($GLOBALS['TCA'][$value]['ctrl']['title']));
						}
					}
				}
				break;
			case 'pagetypes':
				$theTypes = $GLOBALS['TCA']['pages']['columns']['doktype']['config']['items'];
				if (is_array($theTypes)) {
					foreach ($theTypes as $theTypesArrays) {
						foreach ($elements as $eKey => $value) {
							if ((string)$theTypesArrays[1] === (string)$value) {
								$dataAcc[$eKey] = rawurlencode($value) . '|' . rawurlencode($languageService->sL($theTypesArrays[0]));
							}
						}
					}
				}
				break;
			case 'exclude':
				$theExcludeFields = BackendUtility::getExcludeFields();
				if (is_array($theExcludeFields)) {
					foreach ($theExcludeFields as $theExcludeFieldsArrays) {
						foreach ($elements as $eKey => $value) {
							if ((string)$theExcludeFieldsArrays[1] === (string)$value) {
								$dataAcc[$eKey] = rawurlencode($value) . '|' . rawurlencode(rtrim($theExcludeFieldsArrays[0], ':'));
							}
						}
					}
				}
				break;
			case 'explicitValues':
				$theTypes = BackendUtility::getExplicitAuthFieldValues();
				foreach ($theTypes as $tableFieldKey => $theTypeArrays) {
					if (is_array($theTypeArrays['items'])) {
						foreach ($theTypeArrays['items'] as $itemValue => $itemContent) {
							foreach ($elements as $eKey => $value) {
								if (($tableFieldKey . ':' . $itemValue . ':' . $itemContent[0]) === (string)$value) {
									$dataAcc[$eKey] = rawurlencode($value) . '|' . rawurlencode(('[' . $itemContent[2] . '] ' . $itemContent[1]));
								}
							}
						}
					}
				}
				break;
			case 'languages':
				$theLangs = BackendUtility::getSystemLanguages();
				foreach ($theLangs as $lCfg) {
					foreach ($elements as $eKey => $value) {
						if ((string)$lCfg[1] === (string)$value) {
							$dataAcc[$eKey] = rawurlencode($value) . '|' . rawurlencode($lCfg[0]);
						}
					}
				}
				break;
			case 'custom':
				$customOptions = $GLOBALS['TYPO3_CONF_VARS']['BE']['customPermOptions'];
				if (is_array($customOptions)) {
					foreach ($customOptions as $coKey => $coValue) {
						if (is_array($coValue['items'])) {
							// Traverse items:
							foreach ($coValue['items'] as $itemKey => $itemCfg) {
								foreach ($elements as $eKey => $value) {
									if (($coKey . ':' . $itemKey) === (string)$value) {
										$dataAcc[$eKey] = rawurlencode($value) . '|' . rawurlencode($languageService->sL($itemCfg[0]));
									}
								}
							}
						}
					}
				}
				break;
			case 'modListGroup':

			case 'modListUser':
				if (!$this->loadModules) {
					$this->loadModules = GeneralUtility::makeInstance(\TYPO3\CMS\Backend\Module\ModuleLoader::class);
					$this->loadModules->load($GLOBALS['TBE_MODULES']);
				}
				$modList = $specialKey == 'modListUser' ? $this->loadModules->modListUser : $this->loadModules->modListGroup;
				foreach ($modList as $theModName) {
					foreach ($elements as $eKey => $value) {
						$label = '';
						// Add label for main module:
						$pp = explode('_', $value);
						if (count($pp) > 1) {
							$label .= $languageService->moduleLabels['tabs'][($pp[0] . '_tab')] . '>';
						}
						// Add modules own label now:
						$label .= $languageService->moduleLabels['tabs'][$value . '_tab'];
						if ((string)$theModName === (string)$value) {
							$dataAcc[$eKey] = rawurlencode($value) . '|' . rawurlencode($label);
						}
					}
				}
				break;
		}
		return $dataAcc;
	}

	/**
	 * Adds the foreign record elements to $dataAcc, if any
	 *
	 * @param array $dataAcc Array with numeric keys, containing values for the selector box, prepared for interface. We are going to add elements to this array as needed.
	 * @param array $elements The array of original elements - basically the field value exploded by ",
	 * @param array $fieldConfig Field configuration from TCA
	 * @param string $field The field name
	 * @param array $TSconfig TSconfig for the record
	 * @param array $row The record
	 * @param array $table The current table
	 * @return array Modified $dataAcc array
	 * @access private
	 * @see renderRecord_selectProc()
	 */
	public function selectAddForeign($dataAcc, $elements, $fieldConfig, $field, $TSconfig, $row, $table) {
		$languageService = $this->getLanguageService();
		// Init:
		$recordList = array();
		// Foreign_table
		$subres = BackendUtility::exec_foreign_table_where_query($fieldConfig, $field, $TSconfig);
		while ($subrow = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($subres)) {
			// Resolve move-placeholder, to check the right uid against $dataIds
			BackendUtility::workspaceOL($fieldConfig['config']['foreign_table'], $subrow);
			$recordList[$subrow['uid']] = BackendUtility::getRecordTitle($fieldConfig['config']['foreign_table'], $subrow);
		}
		$GLOBALS['TYPO3_DB']->sql_free_result($subres);
		// neg_foreign_table
		if (is_array($GLOBALS['TCA'][$fieldConfig['config']['neg_foreign_table']])) {
			$subres = BackendUtility::exec_foreign_table_where_query($fieldConfig, $field, $TSconfig, 'neg_');
			while ($subrow = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($subres)) {
				// Resolve move-placeholder, to check the right uid against $dataIds
				BackendUtility::workspaceOL($fieldConfig['config']['nes_foreign_table'], $subrow);
				$recordList[-$subrow['uid']] = BackendUtility::getRecordTitle($fieldConfig['config']['neg_foreign_table'], $subrow);
			}
			$GLOBALS['TYPO3_DB']->sql_free_result($subres);
		}
		// At this point all records that CAN be selected is found in $recordList
		// Now, get the data from loadDBgroup based on the input list of values.
		$dataIds = $this->getDataIdList($elements, $fieldConfig, $row, $table);
		if ($fieldConfig['config']['MM']) {
			$dataAcc = array();
		}
		// Reset, if MM (which cannot bear anything but real relations!)
		// After this we can traverse the loadDBgroup values and match values with the list of possible values in $recordList:
		foreach ($dataIds as $theId) {
			if (isset($recordList[$theId])) {
				$lPrefix = $languageService->sL($fieldConfig['config'][($theId > 0 ? '' : 'neg_') . 'foreign_table_prefix']);
				if ($fieldConfig['config']['MM'] || $fieldConfig['config']['foreign_field']) {
					$dataAcc[] = rawurlencode($theId) . '|' . rawurlencode(GeneralUtility::fixed_lgd_cs(($lPrefix . strip_tags($recordList[$theId])), $GLOBALS['BE_USER']->uc['titleLen']));
				} else {
					foreach ($elements as $eKey => $value) {
						if ((int)$theId === (int)$value) {
							$dataAcc[$eKey] = rawurlencode($theId) . '|' . rawurlencode(GeneralUtility::fixed_lgd_cs(($lPrefix . strip_tags($recordList[$theId])), $GLOBALS['BE_USER']->uc['titleLen']));
						}
					}
				}
			}
		}
		return $dataAcc;
	}

	/**
	 * Returning the id-list processed by loadDBgroup for the foreign tables.
	 *
	 * @param array $elements The array of original elements - basically the field value exploded by ",
	 * @param array $fieldConfig Field configuration from TCA
	 * @param array $row The data array, currently. Used to set the "local_uid" for selecting MM relation records.
	 * @param string $table Current table name. passed on to \TYPO3\CMS\Core\Database\RelationHandler
	 * @return array An array with ids of the records from the input elements array.
	 * @access private
	 */
	public function getDataIdList($elements, $fieldConfig, $row, $table) {
		// Use given uid (might be the uid of a workspace record)
		$recordId = $row['uid'];
		// If not dealing with MM relations, then always(!) use the default live uid
		if (empty($fieldConfig['config']['MM'])) {
			$recordId = $this->getLiveDefaultId($table, $row['uid']);
		}
		$loadDB = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\RelationHandler::class);
		$loadDB->registerNonTableValues = $fieldConfig['config']['allowNonIdValues'] ? 1 : 0;
		$loadDB->start(implode(',', $elements), $fieldConfig['config']['foreign_table'] . ',' . $fieldConfig['config']['neg_foreign_table'], $fieldConfig['config']['MM'], $recordId, $table, $fieldConfig['config']);
		$idList = $loadDB->convertPosNeg($loadDB->getValueArray(), $fieldConfig['config']['foreign_table'], $fieldConfig['config']['neg_foreign_table']);
		return $idList;
	}

	/**
	 * Processing of selector box items. This includes the automated adding of elements plus user-function processing.
	 *
	 * @param array The elements to process
	 * @param array TCA/columns configuration
	 * @param array TSconfig for the field
	 * @param string The table name
	 * @param array The current row
	 * @param string The field name
	 * @return array The modified input $selItems array
	 * @access private
	 * @see renderRecord_selectProc()
	 */
	public function procesItemArray($selItems, $config, $fieldTSConfig, $table, $row, $field) {
		$selItems = $this->addItems($selItems, $fieldTSConfig['addItems.']);
		if ($config['itemsProcFunc']) {
			$selItems = $this->procItems($selItems, $fieldTSConfig['itemsProcFunc.'], $config, $table, $row, $field);
		}
		return $selItems;
	}

	/**
	 * Adding items from $iArray to $items array
	 *
	 * @param array $items The array of selector box items to which key(value) / value(label) pairs from $iArray will be added.
	 * @param array $iArray The array of elements to add. The keys will become values. The value will become the label.
	 * @return array The modified input $items array
	 * @access private
	 * @see procesItemArray()
	 */
	public function addItems($items, $iArray) {
		if (is_array($iArray)) {
			foreach ($iArray as $value => $label) {
				$items[] = array($label, $value);
			}
		}
		return $items;
	}

	/**
	 * User processing of a selector box array of values.
	 *
	 * @param array $items The array of selector box items
	 * @param array $itemsProcFuncTSconfig TSconfig for the fields itemProcFunc
	 * @param array $config TCA/columns configuration
	 * @param string $table The table name
	 * @param array $row The current row
	 * @param string $field The field name
	 * @return array The modified input $items array
	 * @access private
	 * @see procesItemArray()
	 */
	public function procItems($items, $itemsProcFuncTSconfig, $config, $table, $row, $field) {
		$params = array();
		$params['items'] = &$items;
		$params['config'] = $config;
		$params['TSconfig'] = $itemsProcFuncTSconfig;
		$params['table'] = $table;
		$params['row'] = $row;
		$params['field'] = $field;
		GeneralUtility::callUserFunction($config['itemsProcFunc'], $params, $this);
		return $items;
	}

	/***********************************************
	 *
	 * Helper functions
	 *
	 ***********************************************/
	/**
	 * Sets the lock for a record from table/id, IF $this->lockRecords is set!
	 *
	 * @param string $table The table name
	 * @param int $id The id of the record
	 * @param int $pid The pid of the record
	 * @return void
	 */
	public function lockRecord($table, $id, $pid = 0) {
		if ($this->lockRecords) {
			BackendUtility::lockRecords($table, $id, $pid);
		}
	}

	/**
	 * Dummy function, can be used to "register" records. Used by eg. the "show_item" script.
	 *
	 * @param string $table Table name
	 * @param int $id Record id
	 * @param string $field Field name
	 * @param string $content Field content.
	 * @return void
	 * @access private
	 * @see renderRecord()
	 */
	public function regItem($table, $id, $field, $content) {

	}

	/**
	 * Local wrapper function for LANG->sL (returning language labels)
	 *
	 * @param string $in Language label key
	 * @return string Localized label value.
	 * @access private
	 * @deprecated since TYPO3 CMS 7, will be removed in TYPO3 CMS 8
	 */
	public function sL($in) {
		GeneralUtility::logDeprecatedFunction();
		return $this->getLanguageService()->sL($in);
	}

	/**
	 * Gets the record uid of the live default record. If already
	 * pointing to the live record, the submitted record uid is returned.
	 *
	 * @param string $tableName
	 * @param int $id
	 * @return int
	 */
	protected function getLiveDefaultId($tableName, $id) {
		$liveDefaultId = BackendUtility::getLiveVersionIdOfRecord($tableName, $id);
		if ($liveDefaultId === NULL) {
			$liveDefaultId = $id;
		}
		return $liveDefaultId;
	}

	/**
	 * @return LanguageService
	 */
	protected function getLanguageService() {
		return $GLOBALS['LANG'];
	}

	/**
	 * This method is called at the very end of fetchRecord(). It emits a signal
	 * that can be used to e.g. manipulate the regTableItems_data array to display
	 * that manipulated data in TCEForms.
	 *
	 * @return void
	 */
	protected function emitFetchRecordPostProcessingSignal() {
		$this->getSignalSlotDispatcher()->dispatch(\TYPO3\CMS\Backend\Form\DataPreprocessor::class, 'fetchRecordPostProcessing', array($this));
	}

	/**
	 * @return Dispatcher
	 */
	protected function getSignalSlotDispatcher() {
		return GeneralUtility::makeInstance(Dispatcher::class);
	}

}
