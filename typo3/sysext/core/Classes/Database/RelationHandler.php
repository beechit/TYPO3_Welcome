<?php
namespace TYPO3\CMS\Core\Database;

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
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Core\Versioning\VersionState;

/**
 * Load database groups (relations)
 * Used to process the relations created by the TCA element types "group" and "select" for database records.
 * Manages MM-relations as well.
 *
 * @author Kasper Skårhøj <kasperYYYY@typo3.com>
 */
class RelationHandler {

	/**
	 * $fetchAllFields if false getFromDB() fetches only uid, pid, thumbnail and label fields (as defined in TCA)
	 *
	 * @var bool
	 */
	protected $fetchAllFields = FALSE;

	/**
	 * If set, values that are not ids in tables are normally discarded. By this options they will be preserved.
	 */
	public $registerNonTableValues = 0;

	/**
	 * Contains the table names as keys. The values are the id-values for each table.
	 * Should ONLY contain proper table names.
	 *
	 * @var array
	 */
	public $tableArray = array();

	/**
	 * Contains items in an numeric array (table/id for each). Tablenames here might be "_NO_TABLE"
	 *
	 * @var array
	 */
	public $itemArray = array();

	/**
	 * Array for NON-table elements
	 *
	 * @var array
	 */
	public $nonTableArray = array();

	/**
	 * @var array
	 */
	public $additionalWhere = array();

	/**
	 * Deleted-column is added to additionalWhere... if this is set...
	 *
	 * @var bool
	 */
	public $checkIfDeleted = TRUE;

	/**
	 * @var array
	 */
	public $dbPaths = array();

	/**
	 * Will contain the first table name in the $tablelist (for positive ids)
	 *
	 * @var string
	 */
	public $firstTable = '';

	/**
	 * Will contain the second table name in the $tablelist (for negative ids)
	 *
	 * @var string
	 */
	public $secondTable = '';

	/**
	 * If TRUE, uid_local and uid_foreign are switched, and the current table
	 * is inserted as tablename - this means you display a foreign relation "from the opposite side"
	 *
	 * @var bool
	 */
	public $MM_is_foreign = FALSE;

	/**
	 * Field name at the "local" side of the MM relation
	 *
	 * @var string
	 */
	public $MM_oppositeField = '';

	/**
	 * Only set if MM_is_foreign is set
	 *
	 * @var string
	 */
	public $MM_oppositeTable = '';

	/**
	 * Only set if MM_is_foreign is set
	 *
	 * @var string
	 */
	public $MM_oppositeFieldConf = '';

	/**
	 * Is empty by default; if MM_is_foreign is set and there is more than one table
	 * allowed (on the "local" side), then it contains the first table (as a fallback)
	 * @var string
	 */
	public $MM_isMultiTableRelationship = '';

	/**
	 * Current table => Only needed for reverse relations
	 *
	 * @var string
	 */
	public $currentTable;

	/**
	 * If a record should be undeleted
	 * (so do not use the $useDeleteClause on \TYPO3\CMS\Backend\Utility\BackendUtility)
	 *
	 * @var bool
	 */
	public $undeleteRecord;

	/**
	 * Array of fields value pairs that should match while SELECT
	 * and will be written into MM table if $MM_insert_fields is not set
	 *
	 * @var array
	 */
	public $MM_match_fields = array();

	/**
	 * This is set to TRUE if the MM table has a UID field.
	 *
	 * @var bool
	 */
	public $MM_hasUidField;

	/**
	 * Array of fields and value pairs used for insert in MM table
	 *
	 * @var array
	 */
	public $MM_insert_fields = array();

	/**
	 * Extra MM table where
	 *
	 * @var string
	 */
	public $MM_table_where = '';

	/**
	 * Usage of a MM field on the opposite relation.
	 *
	 * @var array
	 */
	protected $MM_oppositeUsage;

	/**
	 * @var bool
	 */
	protected $updateReferenceIndex = TRUE;

	/**
	 * @var bool
	 */
	protected $useLiveParentIds = TRUE;

	/**
	 * @var bool
	 */
	protected $useLiveReferenceIds = TRUE;

	/**
	 * @var int
	 */
	protected $workspaceId;

	/**
	 * @var bool
	 */
	protected $purged = FALSE;

	/**
	 * This array will be filled by getFromDB().
	 *
	 * @var array
	 */
	public $results = array();

	/**
	 * Gets the current workspace id.
	 *
	 * @return int
	 */
	public function getWorkspaceId() {
		if (!isset($this->workspaceId)) {
			$this->workspaceId = (int)$GLOBALS['BE_USER']->workspace;
		}
		return $this->workspaceId;
	}

	/**
	 * Sets the current workspace id.
	 *
	 * @param int $workspaceId
	 */
	public function setWorkspaceId($workspaceId) {
		$this->workspaceId = (int)$workspaceId;
	}

	/**
	 * Whether item array has been purged in this instance.
	 *
	 * @return bool
	 */
	public function isPurged() {
		return $this->purged;
	}

	/**
	 * Initialization of the class.
	 *
	 * @param string $itemlist List of group/select items
	 * @param string $tablelist Comma list of tables, first table takes priority if no table is set for an entry in the list.
	 * @param string $MMtable Name of a MM table.
	 * @param int $MMuid Local UID for MM lookup
	 * @param string $currentTable Current table name
	 * @param array $conf TCA configuration for current field
	 * @return void
	 */
	public function start($itemlist, $tablelist, $MMtable = '', $MMuid = 0, $currentTable = '', $conf = array()) {
		$conf = (array)$conf;
		// SECTION: MM reverse relations
		$this->MM_is_foreign = (bool)$conf['MM_opposite_field'];
		$this->MM_oppositeField = $conf['MM_opposite_field'];
		$this->MM_table_where = $conf['MM_table_where'];
		$this->MM_hasUidField = $conf['MM_hasUidField'];
		$this->MM_match_fields = is_array($conf['MM_match_fields']) ? $conf['MM_match_fields'] : array();
		$this->MM_insert_fields = is_array($conf['MM_insert_fields']) ? $conf['MM_insert_fields'] : $this->MM_match_fields;
		$this->currentTable = $currentTable;
		if (!empty($conf['MM_oppositeUsage']) && is_array($conf['MM_oppositeUsage'])) {
			$this->MM_oppositeUsage = $conf['MM_oppositeUsage'];
		}
		if ($this->MM_is_foreign) {
			$tmp = $conf['type'] === 'group' ? $conf['allowed'] : $conf['foreign_table'];
			// Normally, $conf['allowed'] can contain a list of tables,
			// but as we are looking at a MM relation from the foreign side,
			// it only makes sense to allow one one table in $conf['allowed']
			$tmp = GeneralUtility::trimExplode(',', $tmp);
			$this->MM_oppositeTable = $tmp[0];
			unset($tmp);
			// Only add the current table name if there is more than one allowed field
			// We must be sure this has been done at least once before accessing the "columns" part of TCA for a table.
			$this->MM_oppositeFieldConf = $GLOBALS['TCA'][$this->MM_oppositeTable]['columns'][$this->MM_oppositeField]['config'];
			if ($this->MM_oppositeFieldConf['allowed']) {
				$oppositeFieldConf_allowed = explode(',', $this->MM_oppositeFieldConf['allowed']);
				if (count($oppositeFieldConf_allowed) > 1 || $this->MM_oppositeFieldConf['allowed'] === '*') {
					$this->MM_isMultiTableRelationship = $oppositeFieldConf_allowed[0];
				}
			}
		}
		// SECTION:	normal MM relations
		// If the table list is "*" then all tables are used in the list:
		if (trim($tablelist) === '*') {
			$tablelist = implode(',', array_keys($GLOBALS['TCA']));
		}
		// The tables are traversed and internal arrays are initialized:
		$tempTableArray = GeneralUtility::trimExplode(',', $tablelist, TRUE);
		foreach ($tempTableArray as $val) {
			$tName = trim($val);
			$this->tableArray[$tName] = array();
			if ($this->checkIfDeleted && $GLOBALS['TCA'][$tName]['ctrl']['delete']) {
				$fieldN = $tName . '.' . $GLOBALS['TCA'][$tName]['ctrl']['delete'];
				$this->additionalWhere[$tName] .= ' AND ' . $fieldN . '=0';
			}
		}
		if (is_array($this->tableArray)) {
			reset($this->tableArray);
		} else {
			// No tables
			return;
		}
		// Set first and second tables:
		// Is the first table
		$this->firstTable = key($this->tableArray);
		next($this->tableArray);
		// If the second table is set and the ID number is less than zero (later)
		// then the record is regarded to come from the second table...
		$this->secondTable = key($this->tableArray);
		// Now, populate the internal itemArray and tableArray arrays:
		// If MM, then call this function to do that:
		if ($MMtable) {
			if ($MMuid) {
				$this->readMM($MMtable, $MMuid);
				$this->purgeItemArray();
			} else {
				// Revert to readList() for new records in order to load possible default values from $itemlist
				$this->readList($itemlist, $conf);
				$this->purgeItemArray();
			}
		} elseif ($MMuid && $conf['foreign_field']) {
			// If not MM but foreign_field, the read the records by the foreign_field
			$this->readForeignField($MMuid, $conf);
		} else {
			// If not MM, then explode the itemlist by "," and traverse the list:
			$this->readList($itemlist, $conf);
			// Do automatic default_sortby, if any
			if ($conf['foreign_default_sortby']) {
				$this->sortList($conf['foreign_default_sortby']);
			}
		}
	}

	/**
	 * Sets $fetchAllFields
	 *
	 * @param bool $allFields enables fetching of all fields in getFromDB()
	 */
	public function setFetchAllFields($allFields) {
		$this->fetchAllFields = (bool)$allFields;
	}

	/**
	 * Sets whether the reference index shall be updated.
	 *
	 * @param bool $updateReferenceIndex Whether the reference index shall be updated
	 * @return void
	 */
	public function setUpdateReferenceIndex($updateReferenceIndex) {
		$this->updateReferenceIndex = (bool)$updateReferenceIndex;
	}

	/**
	 * @param bool $useLiveParentIds
	 */
	public function setUseLiveParentIds($useLiveParentIds) {
		$this->useLiveParentIds = (bool)$useLiveParentIds;
	}

	/**
	 * @param bool $useLiveReferenceIds
	 */
	public function setUseLiveReferenceIds($useLiveReferenceIds) {
		$this->useLiveReferenceIds = (bool)$useLiveReferenceIds;
	}

	/**
	 * Explodes the item list and stores the parts in the internal arrays itemArray and tableArray from MM records.
	 *
	 * @param string $itemlist Item list
	 * @param array $configuration Parent field configuration
	 * @return void
	 */
	public function readList($itemlist, array $configuration) {
		if ((string)trim($itemlist) != '') {
			$tempItemArray = GeneralUtility::trimExplode(',', $itemlist);
			// Changed to trimExplode 31/3 04; HMENU special type "list" didn't work
			// if there were spaces in the list... I suppose this is better overall...
			foreach ($tempItemArray as $key => $val) {
				// Will be set to "1" if the entry was a real table/id:
				$isSet = 0;
				// Extract table name and id. This is un the formular [tablename]_[id]
				// where table name MIGHT contain "_", hence the reversion of the string!
				$val = strrev($val);
				$parts = explode('_', $val, 2);
				$theID = strrev($parts[0]);
				// Check that the id IS an integer:
				if (MathUtility::canBeInterpretedAsInteger($theID)) {
					// Get the table name: If a part of the exploded string, use that.
					// Otherwise if the id number is LESS than zero, use the second table, otherwise the first table
					$theTable = trim($parts[1])
						? strrev(trim($parts[1]))
						: ($this->secondTable && $theID < 0 ? $this->secondTable : $this->firstTable);
					// If the ID is not blank and the table name is among the names in the inputted tableList
					if (((string)$theID != '' && $theID) && $theTable && isset($this->tableArray[$theTable])) {
						// Get ID as the right value:
						$theID = $this->secondTable ? abs((int)$theID) : (int)$theID;
						// Register ID/table name in internal arrays:
						$this->itemArray[$key]['id'] = $theID;
						$this->itemArray[$key]['table'] = $theTable;
						$this->tableArray[$theTable][] = $theID;
						// Set update-flag:
						$isSet = 1;
					}
				}
				// If it turns out that the value from the list was NOT a valid reference to a table-record,
				// then we might still set it as a NO_TABLE value:
				if (!$isSet && $this->registerNonTableValues) {
					$this->itemArray[$key]['id'] = $tempItemArray[$key];
					$this->itemArray[$key]['table'] = '_NO_TABLE';
					$this->nonTableArray[] = $tempItemArray[$key];
				}
			}

			// Skip if not dealing with IRRE in a CSV list on a workspace
			if ($configuration['type'] !== 'inline' || empty($configuration['foreign_table']) || !empty($configuration['foreign_field'])
				|| !empty($configuration['MM']) || count($this->tableArray) !== 1 || empty($this->tableArray[$configuration['foreign_table']])
				|| (int)$GLOBALS['BE_USER']->workspace === 0 || !BackendUtility::isTableWorkspaceEnabled($configuration['foreign_table'])) {
				return;
			}

			// Fetch live record data
			if ($this->useLiveReferenceIds) {
				foreach ($this->itemArray as &$item) {
					$item['id'] = $this->getLiveDefaultId($item['table'], $item['id']);
				}
			// Directly overlay workspace data
			} else {
				$rows = array();
				$foreignTable = $configuration['foreign_table'];
				foreach ($this->tableArray[$foreignTable] as $itemId) {
					$rows[$itemId] = array('uid' => $itemId);
				}
				$this->itemArray = array();
				foreach ($this->getRecordVersionsIds($foreignTable, $rows) as $row) {
					$this->itemArray[] = array(
						'id' => $row['uid'],
						'table' => $foreignTable,
					);
				}
			}
		}
	}

	/**
	 * Does a sorting on $this->itemArray depending on a default sortby field.
	 * This is only used for automatic sorting of comma separated lists.
	 * This function is only relevant for data that is stored in comma separated lists!
	 *
	 * @param string $sortby The default_sortby field/command (e.g. 'price DESC')
	 * @return void
	 */
	public function sortList($sortby) {
		// Sort directly without fetching addional data
		if ($sortby == 'uid') {
			usort(
				$this->itemArray,
				function ($a, $b) {
					return $a['id'] < $b['id'] ? -1 : 1;
				}
			);
		} elseif (count($this->tableArray) == 1) {
			reset($this->tableArray);
			$table = key($this->tableArray);
			$uidList = implode(',', current($this->tableArray));
			if ($uidList) {
				$this->itemArray = array();
				$this->tableArray = array();
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid', $table, 'uid IN (' . $uidList . ')', '', $sortby);
				while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
					$this->itemArray[] = array('id' => $row['uid'], 'table' => $table);
					$this->tableArray[$table][] = $row['uid'];
				}
				$GLOBALS['TYPO3_DB']->sql_free_result($res);
			}
		}
	}

	/**
	 * Reads the record tablename/id into the internal arrays itemArray and tableArray from MM records.
	 * You can call this function after start if you supply no list to start()
	 *
	 * @param string $tableName MM Tablename
	 * @param int $uid Local UID
	 * @return void
	 */
	public function readMM($tableName, $uid) {
		$key = 0;
		$additionalWhere = '';
		$theTable = NULL;
		// In case of a reverse relation
		if ($this->MM_is_foreign) {
			$uidLocal_field = 'uid_foreign';
			$uidForeign_field = 'uid_local';
			$sorting_field = 'sorting_foreign';
			if ($this->MM_isMultiTableRelationship) {
				$additionalWhere .= ' AND ( tablenames=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($this->currentTable, $tableName);
				// Be backwards compatible! When allowing more than one table after
				// having previously allowed only one table, this case applies.
				if ($this->currentTable == $this->MM_isMultiTableRelationship) {
					$additionalWhere .= ' OR tablenames=\'\'';
				}
				$additionalWhere .= ' ) ';
			}
			$theTable = $this->MM_oppositeTable;
		} else {
			// Default
			$uidLocal_field = 'uid_local';
			$uidForeign_field = 'uid_foreign';
			$sorting_field = 'sorting';
		}
		if ($this->MM_table_where) {
			$additionalWhere .= LF . str_replace('###THIS_UID###', (int)$uid, $this->MM_table_where);
		}
		foreach ($this->MM_match_fields as $field => $value) {
			$additionalWhere .= ' AND ' . $field . '=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($value, $tableName);
		}
		// Select all MM relations:
		$where = $uidLocal_field . '=' . (int)$uid . $additionalWhere;
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', $tableName, $where, '', $sorting_field);
		while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			// Default
			if (!$this->MM_is_foreign) {
				// If tablesnames columns exists and contain a name, then this value is the table, else it's the firstTable...
				$theTable = $row['tablenames'] ?: $this->firstTable;
			}
			if (($row[$uidForeign_field] || $theTable == 'pages') && $theTable && isset($this->tableArray[$theTable])) {
				$this->itemArray[$key]['id'] = $row[$uidForeign_field];
				$this->itemArray[$key]['table'] = $theTable;
				$this->tableArray[$theTable][] = $row[$uidForeign_field];
			} elseif ($this->registerNonTableValues) {
				$this->itemArray[$key]['id'] = $row[$uidForeign_field];
				$this->itemArray[$key]['table'] = '_NO_TABLE';
				$this->nonTableArray[] = $row[$uidForeign_field];
			}
			$key++;
		}
		$GLOBALS['TYPO3_DB']->sql_free_result($res);
	}

	/**
	 * Writes the internal itemArray to MM table:
	 *
	 * @param string $MM_tableName MM table name
	 * @param int $uid Local UID
	 * @param bool $prependTableName If set, then table names will always be written.
	 * @return void
	 */
	public function writeMM($MM_tableName, $uid, $prependTableName = FALSE) {
		// In case of a reverse relation
		if ($this->MM_is_foreign) {
			$uidLocal_field = 'uid_foreign';
			$uidForeign_field = 'uid_local';
			$sorting_field = 'sorting_foreign';
		} else {
			// default
			$uidLocal_field = 'uid_local';
			$uidForeign_field = 'uid_foreign';
			$sorting_field = 'sorting';
		}
		// If there are tables...
		$tableC = count($this->tableArray);
		if ($tableC) {
			// Boolean: does the field "tablename" need to be filled?
			$prep = $tableC > 1 || $prependTableName || $this->MM_isMultiTableRelationship ? 1 : 0;
			$c = 0;
			$additionalWhere_tablenames = '';
			if ($this->MM_is_foreign && $prep) {
				$additionalWhere_tablenames = ' AND tablenames=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($this->currentTable, $MM_tableName);
			}
			$additionalWhere = '';
			// Add WHERE clause if configured
			if ($this->MM_table_where) {
				$additionalWhere .= LF . str_replace('###THIS_UID###', (int)$uid, $this->MM_table_where);
			}
			// Select, update or delete only those relations that match the configured fields
			foreach ($this->MM_match_fields as $field => $value) {
				$additionalWhere .= ' AND ' . $field . '=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($value, $MM_tableName);
			}
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
				$uidForeign_field . ($prep ? ', tablenames' : '') . ($this->MM_hasUidField ? ', uid' : ''),
				$MM_tableName,
				$uidLocal_field . '=' . $uid . $additionalWhere_tablenames . $additionalWhere,
				'',
				$sorting_field
			);
			$oldMMs = array();
			// This array is similar to $oldMMs but also holds the uid of the MM-records, if any (configured by MM_hasUidField).
			// If the UID is present it will be used to update sorting and delete MM-records.
			// This is necessary if the "multiple" feature is used for the MM relations.
			// $oldMMs is still needed for the in_array() search used to look if an item from $this->itemArray is in $oldMMs
			$oldMMs_inclUid = array();
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				if (!$this->MM_is_foreign && $prep) {
					$oldMMs[] = array($row['tablenames'], $row[$uidForeign_field]);
				} else {
					$oldMMs[] = $row[$uidForeign_field];
				}
				$oldMMs_inclUid[] = array($row['tablenames'], $row[$uidForeign_field], $row['uid']);
			}
			$GLOBALS['TYPO3_DB']->sql_free_result($res);
			// For each item, insert it:
			foreach ($this->itemArray as $val) {
				$c++;
				if ($prep || $val['table'] == '_NO_TABLE') {
					// Insert current table if needed
					if ($this->MM_is_foreign) {
						$tablename = $this->currentTable;
					} else {
						$tablename = $val['table'];
					}
				} else {
					$tablename = '';
				}
				if (!$this->MM_is_foreign && $prep) {
					$item = array($val['table'], $val['id']);
				} else {
					$item = $val['id'];
				}
				if (in_array($item, $oldMMs)) {
					$oldMMs_index = array_search($item, $oldMMs);
					// In principle, selecting on the UID is all we need to do
					// if a uid field is available since that is unique!
					// But as long as it "doesn't hurt" we just add it to the where clause. It should all match up.
					$whereClause = $uidLocal_field . '=' . $uid . ' AND ' . $uidForeign_field . '=' . $val['id']
						. ($this->MM_hasUidField ? ' AND uid=' . (int)$oldMMs_inclUid[$oldMMs_index][2] : '');
					if ($tablename) {
						$whereClause .= ' AND tablenames=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($tablename, $MM_tableName);
					}
					$GLOBALS['TYPO3_DB']->exec_UPDATEquery($MM_tableName, $whereClause . $additionalWhere, array($sorting_field => $c));
					// Remove the item from the $oldMMs array so after this
					// foreach loop only the ones that need to be deleted are in there.
					unset($oldMMs[$oldMMs_index]);
					// Remove the item from the $oldMMs_inclUid array so after this
					// foreach loop only the ones that need to be deleted are in there.
					unset($oldMMs_inclUid[$oldMMs_index]);
				} else {
					$insertFields = $this->MM_insert_fields;
					$insertFields[$uidLocal_field] = $uid;
					$insertFields[$uidForeign_field] = $val['id'];
					$insertFields[$sorting_field] = $c;
					if ($tablename) {
						$insertFields['tablenames'] = $tablename;
						$insertFields = $this->completeOppositeUsageValues($tablename, $insertFields);
					}
					$GLOBALS['TYPO3_DB']->exec_INSERTquery($MM_tableName, $insertFields);
					if ($this->MM_is_foreign) {
						$this->updateRefIndex($val['table'], $val['id']);
					}
				}
			}
			// Delete all not-used relations:
			if (is_array($oldMMs) && count($oldMMs) > 0) {
				$removeClauses = array();
				$updateRefIndex_records = array();
				foreach ($oldMMs as $oldMM_key => $mmItem) {
					// If UID field is present, of course we need only use that for deleting.
					if ($this->MM_hasUidField) {
						$removeClauses[] = 'uid=' . (int)$oldMMs_inclUid[$oldMM_key][2];
					} else {
						if (is_array($mmItem)) {
							$removeClauses[] = 'tablenames=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($mmItem[0], $MM_tableName)
								. ' AND ' . $uidForeign_field . '=' . $mmItem[1];
						} else {
							$removeClauses[] = $uidForeign_field . '=' . $mmItem;
						}
					}
					if ($this->MM_is_foreign) {
						if (is_array($mmItem)) {
							$updateRefIndex_records[] = array($mmItem[0], $mmItem[1]);
						} else {
							$updateRefIndex_records[] = array($this->firstTable, $mmItem);
						}
					}
				}
				$deleteAddWhere = ' AND (' . implode(' OR ', $removeClauses) . ')';
				$where = $uidLocal_field . '=' . (int)$uid . $deleteAddWhere . $additionalWhere_tablenames . $additionalWhere;
				$GLOBALS['TYPO3_DB']->exec_DELETEquery($MM_tableName, $where);
				// Update ref index:
				foreach ($updateRefIndex_records as $pair) {
					$this->updateRefIndex($pair[0], $pair[1]);
				}
			}
			// Update ref index; In tcemain it is not certain that this will happen because
			// if only the MM field is changed the record itself is not updated and so the ref-index is not either.
			// This could also have been fixed in updateDB in tcemain, however I decided to do it here ...
			$this->updateRefIndex($this->currentTable, $uid);
		}
	}

	/**
	 * Remaps MM table elements from one local uid to another
	 * Does NOT update the reference index for you, must be called subsequently to do that!
	 *
	 * @param string $MM_tableName MM table name
	 * @param int $uid Local, current UID
	 * @param int $newUid Local, new UID
	 * @param bool $prependTableName If set, then table names will always be written.
	 * @return void
	 */
	public function remapMM($MM_tableName, $uid, $newUid, $prependTableName = FALSE) {
		// In case of a reverse relation
		if ($this->MM_is_foreign) {
			$uidLocal_field = 'uid_foreign';
		} else {
			// default
			$uidLocal_field = 'uid_local';
		}
		// If there are tables...
		$tableC = count($this->tableArray);
		if ($tableC) {
			// Boolean: does the field "tablename" need to be filled?
			$prep = $tableC > 1 || $prependTableName || $this->MM_isMultiTableRelationship;
			$additionalWhere_tablenames = '';
			if ($this->MM_is_foreign && $prep) {
				$additionalWhere_tablenames = ' AND tablenames=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($this->currentTable, $MM_tableName);
			}
			$additionalWhere = '';
			// Add WHERE clause if configured
			if ($this->MM_table_where) {
				$additionalWhere .= LF . str_replace('###THIS_UID###', (int)$uid, $this->MM_table_where);
			}
			// Select, update or delete only those relations that match the configured fields
			foreach ($this->MM_match_fields as $field => $value) {
				$additionalWhere .= ' AND ' . $field . '=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($value, $MM_tableName);
			}
			$where = $uidLocal_field . '=' . (int)$uid . $additionalWhere_tablenames . $additionalWhere;
			$GLOBALS['TYPO3_DB']->exec_UPDATEquery($MM_tableName, $where, array($uidLocal_field => $newUid));
		}
	}

	/**
	 * Reads items from a foreign_table, that has a foreign_field (uid of the parent record) and
	 * stores the parts in the internal array itemArray and tableArray.
	 *
	 * @param int $uid The uid of the parent record (this value is also on the foreign_table in the foreign_field)
	 * @param array $conf TCA configuration for current field
	 * @return void
	 */
	public function readForeignField($uid, $conf) {
		if ($this->useLiveParentIds) {
			$uid = $this->getLiveDefaultId($this->currentTable, $uid);
		}

		$key = 0;
		$uid = (int)$uid;
		$foreign_table = $conf['foreign_table'];
		$foreign_table_field = $conf['foreign_table_field'];
		$useDeleteClause = !$this->undeleteRecord;
		$foreign_match_fields = is_array($conf['foreign_match_fields']) ? $conf['foreign_match_fields'] : array();
		// Search for $uid in foreign_field, and if we have symmetric relations, do this also on symmetric_field
		if ($conf['symmetric_field']) {
			$whereClause = '(' . $conf['foreign_field'] . '=' . $uid . ' OR ' . $conf['symmetric_field'] . '=' . $uid . ')';
		} else {
			$whereClause = $conf['foreign_field'] . '=' . $uid;
		}
		// Use the deleteClause (e.g. "deleted=0") on this table
		if ($useDeleteClause) {
			$whereClause .= BackendUtility::deleteClause($foreign_table);
		}
		// If it's requested to look for the parent uid AND the parent table,
		// add an additional SQL-WHERE clause
		if ($foreign_table_field && $this->currentTable) {
			$whereClause .= ' AND ' . $foreign_table_field . '=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($this->currentTable, $foreign_table);
		}
		// Add additional where clause if foreign_match_fields are defined
		foreach ($foreign_match_fields as $field => $value) {
			$whereClause .= ' AND ' . $field . '=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($value, $foreign_table);
		}
		// Select children from the live(!) workspace only
		if (BackendUtility::isTableWorkspaceEnabled($foreign_table)) {
			$workspaceList = '0,' . $this->getWorkspaceId();
			$whereClause .= ' AND ' . $foreign_table . '.t3ver_wsid IN (' . $workspaceList . ') AND ' . $foreign_table . '.pid<>-1';
		}
		// Get the correct sorting field
		// Specific manual sortby for data handled by this field
		$sortby = '';
		if ($conf['foreign_sortby']) {
			if ($conf['symmetric_sortby'] && $conf['symmetric_field']) {
				// Sorting depends on, from which side of the relation we're looking at it
				$sortby = '
					CASE
						WHEN ' . $conf['foreign_field'] . '=' . $uid . '
						THEN ' . $conf['foreign_sortby'] . '
						ELSE ' . $conf['symmetric_sortby'] . '
					END';
			} else {
				// Regular single-side behaviour
				$sortby = $conf['foreign_sortby'];
			}
		} elseif ($conf['foreign_default_sortby']) {
			// Specific default sortby for data handled by this field
			$sortby = $conf['foreign_default_sortby'];
		} elseif ($GLOBALS['TCA'][$foreign_table]['ctrl']['sortby']) {
			// Manual sortby for all table records
			$sortby = $GLOBALS['TCA'][$foreign_table]['ctrl']['sortby'];
		} elseif ($GLOBALS['TCA'][$foreign_table]['ctrl']['default_sortby']) {
			// Default sortby for all table records
			$sortby = $GLOBALS['TCA'][$foreign_table]['ctrl']['default_sortby'];
		}
		// Strip a possible "ORDER BY" in front of the $sortby value
		$sortby = $GLOBALS['TYPO3_DB']->stripOrderBy($sortby);
		// Get the rows from storage
		$rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('uid', $foreign_table, $whereClause, '', $sortby, '', 'uid');
		if (count($rows)) {
			if (BackendUtility::isTableWorkspaceEnabled($foreign_table) && !$this->useLiveReferenceIds) {
				$rows = $this->getRecordVersionsIds($foreign_table, $rows);
			}
			foreach ($rows as $row) {
				$this->itemArray[$key]['id'] = $row['uid'];
				$this->itemArray[$key]['table'] = $foreign_table;
				$this->tableArray[$foreign_table][] = $row['uid'];
				$key++;
			}
		}
	}

	/**
	 * Write the sorting values to a foreign_table, that has a foreign_field (uid of the parent record)
	 *
	 * @param array $conf TCA configuration for current field
	 * @param int $parentUid The uid of the parent record
	 * @param int $updateToUid If this is larger than zero it will be used as foreign UID instead of the given $parentUid (on Copy)
	 * @param bool $skipSorting Do not update the sorting columns, this could happen for imported values
	 * @return void
	 */
	public function writeForeignField($conf, $parentUid, $updateToUid = 0, $skipSorting = FALSE) {
		if ($this->useLiveParentIds) {
			$parentUid = $this->getLiveDefaultId($this->currentTable, $parentUid);
			if (!empty($updateToUid)) {
				$updateToUid = $this->getLiveDefaultId($this->currentTable, $updateToUid);
			}
		}

		$c = 0;
		$foreign_table = $conf['foreign_table'];
		$foreign_field = $conf['foreign_field'];
		$symmetric_field = $conf['symmetric_field'];
		$foreign_table_field = $conf['foreign_table_field'];
		$foreign_match_fields = is_array($conf['foreign_match_fields']) ? $conf['foreign_match_fields'] : array();
		// If there are table items and we have a proper $parentUid
		if (MathUtility::canBeInterpretedAsInteger($parentUid) && count($this->tableArray)) {
			// If updateToUid is not a positive integer, set it to '0', so it will be ignored
			if (!(MathUtility::canBeInterpretedAsInteger($updateToUid) && $updateToUid > 0)) {
				$updateToUid = 0;
			}
			$considerWorkspaces = ($GLOBALS['BE_USER']->workspace !== 0 && BackendUtility::isTableWorkspaceEnabled($foreign_table));
			$fields = 'uid,pid,' . $foreign_field;
			// Consider the symmetric field if defined:
			if ($symmetric_field) {
				$fields .= ',' . $symmetric_field;
			}
			// Consider workspaces if defined and currently used:
			if ($considerWorkspaces) {
				$fields .= ',t3ver_wsid,t3ver_state,t3ver_oid';
			}
			// Update all items
			foreach ($this->itemArray as $val) {
				$uid = $val['id'];
				$table = $val['table'];
				$row = array();
				// Fetch the current (not overwritten) relation record if we should handle symmetric relations
				if ($symmetric_field || $considerWorkspaces) {
					$row = BackendUtility::getRecord($table, $uid, $fields, '', FALSE);
				}
				$isOnSymmetricSide = FALSE;
				if ($symmetric_field) {
					$isOnSymmetricSide = self::isOnSymmetricSide($parentUid, $conf, $row);
				}
				$updateValues = $foreign_match_fields;
				// No update to the uid is requested, so this is the normal behaviour
				// just update the fields and care about sorting
				if (!$updateToUid) {
					// Always add the pointer to the parent uid
					if ($isOnSymmetricSide) {
						$updateValues[$symmetric_field] = $parentUid;
					} else {
						$updateValues[$foreign_field] = $parentUid;
					}
					// If it is configured in TCA also to store the parent table in the child record, just do it
					if ($foreign_table_field && $this->currentTable) {
						$updateValues[$foreign_table_field] = $this->currentTable;
					}
					// Update sorting columns if not to be skipped
					if (!$skipSorting) {
						// Get the correct sorting field
						// Specific manual sortby for data handled by this field
						$sortby = '';
						if ($conf['foreign_sortby']) {
							$sortby = $conf['foreign_sortby'];
						} elseif ($GLOBALS['TCA'][$foreign_table]['ctrl']['sortby']) {
							// manual sortby for all table records
							$sortby = $GLOBALS['TCA'][$foreign_table]['ctrl']['sortby'];
						}
						// Apply sorting on the symmetric side
						// (it depends on who created the relation, so what uid is in the symmetric_field):
						if ($isOnSymmetricSide && isset($conf['symmetric_sortby']) && $conf['symmetric_sortby']) {
							$sortby = $conf['symmetric_sortby'];
						} else {
							$sortby = $GLOBALS['TYPO3_DB']->stripOrderBy($sortby);
						}
						if ($sortby) {
							$updateValues[$sortby] = ++$c;
						}
					}
				} else {
					if ($isOnSymmetricSide) {
						$updateValues[$symmetric_field] = $updateToUid;
					} else {
						$updateValues[$foreign_field] = $updateToUid;
					}
				}
				// Update accordant fields in the database:
				if (count($updateValues)) {
					$GLOBALS['TYPO3_DB']->exec_UPDATEquery($table, 'uid=' . (int)$uid, $updateValues);
					$this->updateRefIndex($table, $uid);
				}
				// Update accordant fields in the database for workspaces overlays/placeholders:
				if ($considerWorkspaces) {
					// It's the specific versioned record -> update placeholder (if any)
					if (!empty($row['t3ver_oid']) && VersionState::cast($row['t3ver_state'])->equals(VersionState::NEW_PLACEHOLDER_VERSION)) {
						$GLOBALS['TYPO3_DB']->exec_UPDATEquery($table, 'uid=' . (int)$row['t3ver_oid'], $updateValues);
					}
				}
			}
		}
	}

	/**
	 * After initialization you can extract an array of the elements from the object. Use this function for that.
	 *
	 * @param bool $prependTableName If set, then table names will ALWAYS be prepended (unless its a _NO_TABLE value)
	 * @return array A numeric array.
	 */
	public function getValueArray($prependTableName = FALSE) {
		// INIT:
		$valueArray = array();
		$tableC = count($this->tableArray);
		// If there are tables in the table array:
		if ($tableC) {
			// If there are more than ONE table in the table array, then always prepend table names:
			$prep = $tableC > 1 || $prependTableName;
			// Traverse the array of items:
			foreach ($this->itemArray as $val) {
				$valueArray[] = ($prep && $val['table'] != '_NO_TABLE' ? $val['table'] . '_' : '') . $val['id'];
			}
		}
		// Return the array
		return $valueArray;
	}

	/**
	 * Converts id numbers from negative to positive.
	 *
	 * @param array $valueArray Array of [table]_[id] pairs.
	 * @param string $fTable Foreign table (the one used for positive numbers)
	 * @param string $nfTable Negative foreign table
	 * @return array The array with ID integer values, converted to positive for those where the table name was set but did NOT match the positive foreign table.
	 */
	public function convertPosNeg($valueArray, $fTable, $nfTable) {
		if (is_array($valueArray) && $fTable) {
			foreach ($valueArray as $key => $val) {
				$val = strrev($val);
				$parts = explode('_', $val, 2);
				$theID = strrev($parts[0]);
				$theTable = strrev($parts[1]);
				if (MathUtility::canBeInterpretedAsInteger($theID)
					&& (!$theTable || $theTable === (string)$fTable || $theTable === (string)$nfTable)
				) {
					$valueArray[$key] = $theTable && $theTable !== (string)$fTable ? $theID * -1 : $theID;
				}
			}
		}
		return $valueArray;
	}

	/**
	 * Reads all records from internal tableArray into the internal ->results array
	 * where keys are table names and for each table, records are stored with uids as their keys.
	 * If $this->fetchAllFields is false you can save a little memory
	 * since only uid,pid and a few other fields are selected.
	 *
	 * @return array
	 */
	public function getFromDB() {
		// Traverses the tables listed:
		foreach ($this->tableArray as $key => $val) {
			if (is_array($val)) {
				$itemList = implode(',', $val);
				if ($itemList) {
					if ($this->fetchAllFields) {
						$from = '*';
					} else {
						$from = 'uid,pid';
						if ($GLOBALS['TCA'][$key]['ctrl']['label']) {
							// Titel
							$from .= ',' . $GLOBALS['TCA'][$key]['ctrl']['label'];
						}
						if ($GLOBALS['TCA'][$key]['ctrl']['label_alt']) {
							// Alternative Title-Fields
							$from .= ',' . $GLOBALS['TCA'][$key]['ctrl']['label_alt'];
						}
						if ($GLOBALS['TCA'][$key]['ctrl']['thumbnail']) {
							// Thumbnail
							$from .= ',' . $GLOBALS['TCA'][$key]['ctrl']['thumbnail'];
						}
					}
					$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($from, $key, 'uid IN (' . $itemList . ')' . $this->additionalWhere[$key]);
					while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
						$this->results[$key][$row['uid']] = $row;
					}
					$GLOBALS['TYPO3_DB']->sql_free_result($res);
				}
			}
		}
		return $this->results;
	}

	/**
	 * Prepare items from itemArray to be transferred to the TCEforms interface (as a comma list)
	 *
	 * @return string
	 */
	public function readyForInterface() {
		if (!is_array($this->itemArray)) {
			return FALSE;
		}
		$output = array();
		$titleLen = (int)$GLOBALS['BE_USER']->uc['titleLen'];
		foreach ($this->itemArray as $val) {
			$theRow = $this->results[$val['table']][$val['id']];
			if ($theRow && is_array($GLOBALS['TCA'][$val['table']])) {
				$label = GeneralUtility::fixed_lgd_cs(strip_tags(
						BackendUtility::getRecordTitle($val['table'], $theRow)), $titleLen);
				$label = $label ? $label : '[...]';
				$output[] = str_replace(',', '', $val['table'] . '_' . $val['id'] . '|' . rawurlencode($label));
			}
		}
		return implode(',', $output);
	}

	/**
	 * Counts the items in $this->itemArray and puts this value in an array by default.
	 *
	 * @param bool $returnAsArray Whether to put the count value in an array
	 * @return mixed The plain count as integer or the same inside an array
	 */
	public function countItems($returnAsArray = TRUE) {
		$count = count($this->itemArray);
		if ($returnAsArray) {
			$count = array($count);
		}
		return $count;
	}

	/**
	 * Update Reference Index (sys_refindex) for a record
	 * Should be called any almost any update to a record which could affect references inside the record.
	 * (copied from TCEmain)
	 *
	 * @param string $table Table name
	 * @param int $id Record UID
	 * @return array Information concerning modifications delivered by \TYPO3\CMS\Core\Database\ReferenceIndex::updateRefIndexTable()
	 */
	public function updateRefIndex($table, $id) {
		$statisticsArray = array();
		if ($this->updateReferenceIndex) {
			/** @var $refIndexObj \TYPO3\CMS\Core\Database\ReferenceIndex */
			$refIndexObj = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\ReferenceIndex::class);
			if (BackendUtility::isTableWorkspaceEnabled($table)) {
				$refIndexObj->setWorkspaceId($this->getWorkspaceId());
			}
			$statisticsArray = $refIndexObj->updateRefIndexTable($table, $id);
		}
		return $statisticsArray;
	}

	/**
	 * @param NULL|int $workspaceId
	 * @return bool Whether items have been purged
	 */
	public function purgeItemArray($workspaceId = NULL) {
		if ($workspaceId === NULL) {
			$workspaceId = $this->getWorkspaceId();
		} else {
			$workspaceId = (int)$workspaceId;
		}

		// Ensure, only live relations are in the items Array
		if ($workspaceId === 0) {
			$purgeCallback = 'purgeVersionedIds';
		// Otherwise, ensure that live relations are purged if version exists
		} else {
			$purgeCallback = 'purgeLiveVersionedIds';
		}

		$itemArrayHasBeenPurged = $this->purgeItemArrayHandler($purgeCallback);
		$this->purged = ($this->purged || $itemArrayHasBeenPurged);
		return $itemArrayHasBeenPurged;
	}

	/**
	 * Removes items having a delete placeholder from $this->itemArray
	 *
	 * @return bool Whether items have been purged
	 */
	public function processDeletePlaceholder() {
		if (!$this->useLiveReferenceIds || $this->getWorkspaceId() === 0) {
			return FALSE;
		}

		return $this->purgeItemArrayHandler('purgeDeletePlaceholder');
	}

	/**
	 * Handles a purge callback on $this->itemArray
	 *
	 * @param callable $purgeCallback
	 * @return bool Whether items have been purged
	 */
	protected function purgeItemArrayHandler($purgeCallback) {
		$itemArrayHasBeenPurged = FALSE;

		foreach ($this->tableArray as $itemTableName => $itemIds) {
			if (!count($itemIds) || !BackendUtility::isTableWorkspaceEnabled($itemTableName)) {
				continue;
			}

			$purgedItemIds = call_user_func(array($this, $purgeCallback), $itemTableName, $itemIds);
			$removedItemIds = array_diff($itemIds, $purgedItemIds);
			foreach ($removedItemIds as $removedItemId) {
				$this->removeFromItemArray($itemTableName, $removedItemId);
			}
			$this->tableArray[$itemTableName] = $purgedItemIds;
			if (count($removedItemIds)) {
				$itemArrayHasBeenPurged = TRUE;
			}
		}

		return $itemArrayHasBeenPurged;
	}

	/**
	 * Purges ids that are versioned.
	 *
	 * @param string $tableName
	 * @param array $ids
	 * @return array
	 */
	protected function purgeVersionedIds($tableName, array $ids) {
		$ids = $this->getDatabaseConnection()->cleanIntArray($ids);
		$ids = array_combine($ids, $ids);

		$versions = $this->getDatabaseConnection()->exec_SELECTgetRows(
			'uid,t3ver_oid,t3ver_state',
			$tableName,
			'pid=-1 AND t3ver_oid IN (' . implode(',', $ids) . ') AND t3ver_wsid<>0',
			'',
			't3ver_state DESC'
		);

		if (!empty($versions)) {
			foreach ($versions as $version) {
				$versionId = $version['uid'];
				if (isset($ids[$versionId])) {
					unset($ids[$versionId]);
				}
			}
		}

		return array_values($ids);
	}

	/**
	 * Purges ids that are live but have an accordant version.
	 *
	 * @param string $tableName
	 * @param array $ids
	 * @return array
	 */
	protected function purgeLiveVersionedIds($tableName, array $ids) {
		$ids = $this->getDatabaseConnection()->cleanIntArray($ids);
		$ids = array_combine($ids, $ids);

		$versions = $this->getDatabaseConnection()->exec_SELECTgetRows(
			'uid,t3ver_oid,t3ver_state',
			$tableName,
			'pid=-1 AND t3ver_oid IN (' . implode(',', $ids) . ') AND t3ver_wsid<>0',
			'',
			't3ver_state DESC'
		);

		if (!empty($versions)) {
			foreach ($versions as $version) {
				$versionId = $version['uid'];
				$liveId = $version['t3ver_oid'];
				if (isset($ids[$liveId]) && isset($ids[$versionId])) {
					unset($ids[$liveId]);
				}
			}
		}

		return array_values($ids);
	}

	/**
	 * Purges ids that have a delete placeholder
	 *
	 * @param string $tableName
	 * @param array $ids
	 * @return array
	 */
	protected function purgeDeletePlaceholder($tableName, array $ids) {
		$ids = $this->getDatabaseConnection()->cleanIntArray($ids);
		$ids = array_combine($ids, $ids);

		$versions = $this->getDatabaseConnection()->exec_SELECTgetRows(
			'uid,t3ver_oid,t3ver_state',
			$tableName,
			'pid=-1 AND t3ver_oid IN (' . implode(',', $ids) . ') AND t3ver_wsid=' . $this->getWorkspaceId() .
				' AND t3ver_state=' . VersionState::cast(VersionState::DELETE_PLACEHOLDER)
		);

		if (!empty($versions)) {
			foreach ($versions as $version) {
				$liveId = $version['t3ver_oid'];
				if (isset($ids[$liveId])) {
					unset($ids[$liveId]);
				}
			}
		}

		return array_values($ids);
	}

	protected function removeFromItemArray($tableName, $id) {
		foreach ($this->itemArray as $index => $item) {
			if ($item['table'] === $tableName && (string)$item['id'] === (string)$id) {
				unset($this->itemArray[$index]);
				return TRUE;
			}
		}
		return FALSE;
	}

	/**
	 * Checks, if we're looking from the "other" side, the symmetric side, to a symmetric relation.
	 *
	 * @param string $parentUid The uid of the parent record
	 * @param array $parentConf The TCA configuration of the parent field embedding the child records
	 * @param array $childRec The record row of the child record
	 * @return bool Returns TRUE if looking from the symmetric ("other") side to the relation.
	 */
	static public function isOnSymmetricSide($parentUid, $parentConf, $childRec) {
		return MathUtility::canBeInterpretedAsInteger($childRec['uid'])
			&& $parentConf['symmetric_field']
			&& $parentUid == $childRec[$parentConf['symmetric_field']];
	}

	/**
	 * Completes MM values to be written by values from the opposite relation.
	 * This method used MM insert field or MM match fields if defined.
	 *
	 * @param string $tableName Name of the opposite table
	 * @param array $referenceValues Values to be written
	 * @return array Values to be written, possibly modified
	 */
	protected function completeOppositeUsageValues($tableName, array $referenceValues) {
		if (empty($this->MM_oppositeUsage[$tableName]) || count($this->MM_oppositeUsage[$tableName]) > 1) {
			return $referenceValues;
		}

		$fieldName = $this->MM_oppositeUsage[$tableName][0];
		if (empty($GLOBALS['TCA'][$tableName]['columns'][$fieldName]['config'])) {
			return $referenceValues;
		}

		$configuration = $GLOBALS['TCA'][$tableName]['columns'][$fieldName]['config'];
		if (!empty($configuration['MM_insert_fields'])) {
			$referenceValues = array_merge($configuration['MM_insert_fields'], $referenceValues);
		} elseif (!empty($configuration['MM_match_fields'])) {
			$referenceValues = array_merge($configuration['MM_match_fields'], $referenceValues);
		}

		return $referenceValues;
	}

	/**
	 * @param string $tableName
	 * @param array $records
	 * @return array
	 */
	protected function getRecordVersionsIds($tableName, array $records) {
		$workspaceId = (int)$GLOBALS['BE_USER']->workspace;
		$liveIds = array_map('intval', $this->extractValues($records, 'uid'));
		$liveIdList = implode(',', $liveIds);

		if (BackendUtility::isTableMovePlaceholderAware($tableName)) {
			$versions = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
				'uid,t3ver_move_id',
				$tableName,
				't3ver_state=3 AND t3ver_wsid=' . $workspaceId . ' AND t3ver_move_id IN (' . $liveIdList . ')'
			);

			if (!empty($versions)) {
				foreach ($versions as $version) {
					$liveReferenceId = $version['t3ver_move_id'];
					$movePlaceholderId = $version['uid'];
					if (isset($records[$liveReferenceId]) && $records[$movePlaceholderId]) {
						$records[$movePlaceholderId] = $records[$liveReferenceId];
						unset($records[$liveReferenceId]);
					}
				}
				$liveIds = array_map('intval', $this->extractValues($records, 'uid'));
				$records = array_combine($liveIds, array_values($records));
				$liveIdList = implode(',', $liveIds);
			}
		}

		$versions = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'uid,t3ver_oid,t3ver_state',
			$tableName,
			'pid=-1 AND t3ver_oid IN (' . $liveIdList . ') AND t3ver_wsid=' . $workspaceId,
			'',
			't3ver_state DESC'
		);

		if (!empty($versions)) {
			foreach ($versions as $version) {
				$liveId = $version['t3ver_oid'];
				if (isset($records[$liveId])) {
					$records[$liveId] = $version;
				}
			}
		}

		return $records;
	}

	/**
	 * @param array $array
	 * @param string $fieldName
	 * @return array
	 */
	protected function extractValues(array $array, $fieldName) {
		$values = array();
		foreach ($array as $item) {
			$values[] = $item[$fieldName];
		}
		return $values;
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
		return (int)$liveDefaultId;
	}

	/**
	 * @return DatabaseConnection
	 */
	protected function getDatabaseConnection() {
		return $GLOBALS['TYPO3_DB'];
	}

}
