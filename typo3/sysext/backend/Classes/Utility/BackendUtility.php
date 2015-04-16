<?php
namespace TYPO3\CMS\Backend\Utility;

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

use TYPO3\CMS\Backend\Configuration\TranslationConfigurationProvider;
use TYPO3\CMS\Backend\Template\DocumentTemplate;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\FormProtection\FormProtectionFactory;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Resource\Exception\ResourceDoesNotExistException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\TypoScript\Parser\TypoScriptParser;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\HttpUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Core\Versioning\VersionState;
use TYPO3\CMS\Core\Database\DatabaseConnection;
use TYPO3\CMS\Frontend\Page\PageRepository;
use TYPO3\CMS\Lang\LanguageService;

/**
 * Standard functions available for the TYPO3 backend.
 * You are encouraged to use this class in your own applications (Backend Modules)
 * Don't instantiate - call functions with "\TYPO3\CMS\Backend\Utility\BackendUtility::" prefixed the function name.
 *
 * Call ALL methods without making an object!
 * Eg. to get a page-record 51 do this: '\TYPO3\CMS\Backend\Utility\BackendUtility::getRecord('pages',51)'
 *
 * @author Kasper Skårhøj <kasperYYYY@typo3.com>
 */
class BackendUtility {

	/**
	 * Cache the TCA configuration of tables with their types during runtime
	 *
	 * @var array
	 * @see getTCAtypes()
	 */
	static protected $tcaTableTypeConfigurationCache = array();

	/*******************************************
	 *
	 * SQL-related, selecting records, searching
	 *
	 *******************************************/
	/**
	 * Returns the WHERE clause " AND NOT [tablename].[deleted-field]" if a deleted-field
	 * is configured in $GLOBALS['TCA'] for the tablename, $table
	 * This function should ALWAYS be called in the backend for selection on tables which
	 * are configured in $GLOBALS['TCA'] since it will ensure consistent selection of records,
	 * even if they are marked deleted (in which case the system must always treat them as non-existent!)
	 * In the frontend a function, ->enableFields(), is known to filter hidden-field, start- and endtime
	 * and fe_groups as well. But that is a job of the frontend, not the backend. If you need filtering
	 * on those fields as well in the backend you can use ->BEenableFields() though.
	 *
	 * @param string $table Table name present in $GLOBALS['TCA']
	 * @param string $tableAlias Table alias if any
	 * @return string WHERE clause for filtering out deleted records, eg " AND tablename.deleted=0
	 */
	static public function deleteClause($table, $tableAlias = '') {
		if ($GLOBALS['TCA'][$table]['ctrl']['delete']) {
			return ' AND ' . ($tableAlias ?: $table) . '.' . $GLOBALS['TCA'][$table]['ctrl']['delete'] . '=0';
		} else {
			return '';
		}
	}

	/**
	 * Gets record with uid = $uid from $table
	 * You can set $field to a list of fields (default is '*')
	 * Additional WHERE clauses can be added by $where (fx. ' AND blabla = 1')
	 * Will automatically check if records has been deleted and if so, not return anything.
	 * $table must be found in $GLOBALS['TCA']
	 *
	 * @param string $table Table name present in $GLOBALS['TCA']
	 * @param int $uid UID of record
	 * @param string $fields List of fields to select
	 * @param string $where Additional WHERE clause, eg. " AND blablabla = 0
	 * @param bool $useDeleteClause Use the deleteClause to check if a record is deleted (default TRUE)
	 * @return array|NULL Returns the row if found, otherwise NULL
	 */
	static public function getRecord($table, $uid, $fields = '*', $where = '', $useDeleteClause = TRUE) {
		// Ensure we have a valid uid (not 0 and not NEWxxxx) and a valid TCA
		if ((int)$uid && !empty($GLOBALS['TCA'][$table])) {
			$where = 'uid=' . (int)$uid . ($useDeleteClause ? self::deleteClause($table) : '') . $where;
			$row = static::getDatabaseConnection()->exec_SELECTgetSingleRow($fields, $table, $where);
			if ($row) {
				return $row;
			}
		}
		return NULL;
	}

	/**
	 * Like getRecord(), but overlays workspace version if any.
	 *
	 * @param string $table Table name present in $GLOBALS['TCA']
	 * @param int $uid UID of record
	 * @param string $fields List of fields to select
	 * @param string $where Additional WHERE clause, eg. " AND blablabla = 0
	 * @param bool $useDeleteClause Use the deleteClause to check if a record is deleted (default TRUE)
	 * @param bool $unsetMovePointers If TRUE the function does not return a "pointer" row for moved records in a workspace
	 * @return array Returns the row if found, otherwise nothing
	 */
	static public function getRecordWSOL($table, $uid, $fields = '*', $where = '', $useDeleteClause = TRUE, $unsetMovePointers = FALSE) {
		if ($fields !== '*') {
			$internalFields = GeneralUtility::uniqueList($fields . ',uid,pid');
			$row = self::getRecord($table, $uid, $internalFields, $where, $useDeleteClause);
			self::workspaceOL($table, $row, -99, $unsetMovePointers);
			if (is_array($row)) {
				foreach ($row as $key => $_) {
					if (!GeneralUtility::inList($fields, $key) && $key[0] !== '_') {
						unset($row[$key]);
					}
				}
			}
		} else {
			$row = self::getRecord($table, $uid, $fields, $where, $useDeleteClause);
			self::workspaceOL($table, $row, -99, $unsetMovePointers);
		}
		return $row;
	}

	/**
	 * Returns the first record found from $table with $where as WHERE clause
	 * This function does NOT check if a record has the deleted flag set.
	 * $table does NOT need to be configured in $GLOBALS['TCA']
	 * The query used is simply this:
	 * $query = 'SELECT ' . $fields . ' FROM ' . $table . ' WHERE ' . $where;
	 *
	 * @param string $table Table name (not necessarily in TCA)
	 * @param string $where WHERE clause
	 * @param string $fields $fields is a list of fields to select, default is '*'
	 * @return array First row found, if any, FALSE otherwise
	 */
	static public function getRecordRaw($table, $where = '', $fields = '*') {
		$row = FALSE;
		$db = static::getDatabaseConnection();
		if (FALSE !== ($res = $db->exec_SELECTquery($fields, $table, $where, '', '', '1'))) {
			$row = $db->sql_fetch_assoc($res);
			$db->sql_free_result($res);
		}
		return $row;
	}

	/**
	 * Returns records from table, $theTable, where a field ($theField) equals the value, $theValue
	 * The records are returned in an array
	 * If no records were selected, the function returns nothing
	 *
	 * @param string $theTable Table name present in $GLOBALS['TCA']
	 * @param string $theField Field to select on
	 * @param string $theValue Value that $theField must match
	 * @param string $whereClause Optional additional WHERE clauses put in the end of the query. DO NOT PUT IN GROUP BY, ORDER BY or LIMIT!
	 * @param string $groupBy Optional GROUP BY field(s), if none, supply blank string.
	 * @param string $orderBy Optional ORDER BY field(s), if none, supply blank string.
	 * @param string $limit Optional LIMIT value ([begin,]max), if none, supply blank string.
	 * @param bool $useDeleteClause Use the deleteClause to check if a record is deleted (default TRUE)
	 * @return mixed Multidimensional array with selected records (if any is selected)
	 */
	static public function getRecordsByField($theTable, $theField, $theValue, $whereClause = '', $groupBy = '', $orderBy = '', $limit = '', $useDeleteClause = TRUE) {
		if (is_array($GLOBALS['TCA'][$theTable])) {
			$db = static::getDatabaseConnection();
			$res = $db->exec_SELECTquery(
				'*',
				$theTable,
				$theField . '=' . $db->fullQuoteStr($theValue, $theTable) .
					($useDeleteClause ? self::deleteClause($theTable) . ' ' : '') .
					self::versioningPlaceholderClause($theTable) . ' ' .
					$whereClause,
				$groupBy,
				$orderBy,
				$limit
			);
			$rows = array();
			while ($row = $db->sql_fetch_assoc($res)) {
				$rows[] = $row;
			}
			$db->sql_free_result($res);
			if (count($rows)) {
				return $rows;
			}
		}
		return NULL;
	}

	/**
	 * Makes an backwards explode on the $str and returns an array with ($table, $uid).
	 * Example: tt_content_45 => array('tt_content', 45)
	 *
	 * @param string $str [tablename]_[uid] string to explode
	 * @return array
	 */
	static public function splitTable_Uid($str) {
		list($uid, $table) = explode('_', strrev($str), 2);
		return array(strrev($table), strrev($uid));
	}

	/**
	 * Returns a list of pure ints based on $in_list being a list of records with table-names prepended.
	 * Ex: $in_list = "pages_4,tt_content_12,45" would result in a return value of "4,45" if $tablename is "pages" and $default_tablename is 'pages' as well.
	 *
	 * @param string $in_list Input list
	 * @param string $tablename Table name from which ids is returned
	 * @param string $default_tablename $default_tablename denotes what table the number '45' is from (if nothing is prepended on the value)
	 * @return string List of ids
	 */
	static public function getSQLselectableList($in_list, $tablename, $default_tablename) {
		$list = array();
		if ((string)trim($in_list) != '') {
			$tempItemArray = explode(',', trim($in_list));
			foreach ($tempItemArray as $key => $val) {
				$val = strrev($val);
				$parts = explode('_', $val, 2);
				if ((string)trim($parts[0]) != '') {
					$theID = (int)strrev($parts[0]);
					$theTable = trim($parts[1]) ? strrev(trim($parts[1])) : $default_tablename;
					if ($theTable == $tablename) {
						$list[] = $theID;
					}
				}
			}
		}
		return implode(',', $list);
	}

	/**
	 * Backend implementation of enableFields()
	 * Notice that "fe_groups" is not selected for - only disabled, starttime and endtime.
	 * Notice that deleted-fields are NOT filtered - you must ALSO call deleteClause in addition.
	 * $GLOBALS["SIM_ACCESS_TIME"] is used for date.
	 *
	 * @param string $table The table from which to return enableFields WHERE clause. Table name must have a 'ctrl' section in $GLOBALS['TCA'].
	 * @param bool $inv Means that the query will select all records NOT VISIBLE records (inverted selection)
	 * @return string WHERE clause part
	 */
	static public function BEenableFields($table, $inv = FALSE) {
		$ctrl = $GLOBALS['TCA'][$table]['ctrl'];
		$query = array();
		$invQuery = array();
		if (is_array($ctrl)) {
			if (is_array($ctrl['enablecolumns'])) {
				if ($ctrl['enablecolumns']['disabled']) {
					$field = $table . '.' . $ctrl['enablecolumns']['disabled'];
					$query[] = $field . '=0';
					$invQuery[] = $field . '<>0';
				}
				if ($ctrl['enablecolumns']['starttime']) {
					$field = $table . '.' . $ctrl['enablecolumns']['starttime'];
					$query[] = '(' . $field . '<=' . $GLOBALS['SIM_ACCESS_TIME'] . ')';
					$invQuery[] = '(' . $field . '<>0 AND ' . $field . '>' . $GLOBALS['SIM_ACCESS_TIME'] . ')';
				}
				if ($ctrl['enablecolumns']['endtime']) {
					$field = $table . '.' . $ctrl['enablecolumns']['endtime'];
					$query[] = '(' . $field . '=0 OR ' . $field . '>' . $GLOBALS['SIM_ACCESS_TIME'] . ')';
					$invQuery[] = '(' . $field . '<>0 AND ' . $field . '<=' . $GLOBALS['SIM_ACCESS_TIME'] . ')';
				}
			}
		}
		$outQ = $inv ? '(' . implode(' OR ', $invQuery) . ')' : implode(' AND ', $query);
		return $outQ ? ' AND ' . $outQ : '';
	}

	/**
	 * Fetches the localization for a given record.
	 *
	 * @param string $table Table name present in $GLOBALS['TCA']
	 * @param int $uid The uid of the record
	 * @param int $language The uid of the language record in sys_language
	 * @param string $andWhereClause Optional additional WHERE clause (default: '')
	 * @return mixed Multidimensional array with selected records; if none exist, FALSE is returned
	 */
	static public function getRecordLocalization($table, $uid, $language, $andWhereClause = '') {
		$recordLocalization = FALSE;

		// Check if translations are stored in other table
		if (isset($GLOBALS['TCA'][$table]['ctrl']['transForeignTable'])) {
			$table = $GLOBALS['TCA'][$table]['ctrl']['transForeignTable'];
		}

		if (self::isTableLocalizable($table)) {
			$tcaCtrl = $GLOBALS['TCA'][$table]['ctrl'];
			$recordLocalization = self::getRecordsByField($table, $tcaCtrl['transOrigPointerField'], $uid, 'AND ' . $tcaCtrl['languageField'] . '=' . (int)$language . ($andWhereClause ? ' ' . $andWhereClause : ''), '', '', '1');
		}
		return $recordLocalization;
	}

	/*******************************************
	 *
	 * Page tree, TCA related
	 *
	 *******************************************/
	/**
	 * Returns what is called the 'RootLine'. That is an array with information about the page records from a page id ($uid) and back to the root.
	 * By default deleted pages are filtered.
	 * This RootLine will follow the tree all the way to the root. This is opposite to another kind of root line known from the frontend where the rootline stops when a root-template is found.
	 *
	 * @param int $uid Page id for which to create the root line.
	 * @param string $clause Clause can be used to select other criteria. It would typically be where-clauses that stops the process if we meet a page, the user has no reading access to.
	 * @param bool $workspaceOL If TRUE, version overlay is applied. This must be requested specifically because it is usually only wanted when the rootline is used for visual output while for permission checking you want the raw thing!
	 * @return array Root line array, all the way to the page tree root (or as far as $clause allows!)
	 */
	static public function BEgetRootLine($uid, $clause = '', $workspaceOL = FALSE) {
		static $BEgetRootLine_cache = array();
		$output = array();
		$pid = $uid;
		$ident = $pid . '-' . $clause . '-' . $workspaceOL;
		if (is_array($BEgetRootLine_cache[$ident])) {
			$output = $BEgetRootLine_cache[$ident];
		} else {
			$loopCheck = 100;
			$theRowArray = array();
			while ($uid != 0 && $loopCheck) {
				$loopCheck--;
				$row = self::getPageForRootline($uid, $clause, $workspaceOL);
				if (is_array($row)) {
					$uid = $row['pid'];
					$theRowArray[] = $row;
				} else {
					break;
				}
			}
			if ($uid == 0) {
				$theRowArray[] = array('uid' => 0, 'title' => '');
			}
			$c = count($theRowArray);
			foreach ($theRowArray as $val) {
				$c--;
				$output[$c] = array(
					'uid' => $val['uid'],
					'pid' => $val['pid'],
					'title' => $val['title'],
					'TSconfig' => $val['TSconfig'],
					'is_siteroot' => $val['is_siteroot'],
					'storage_pid' => $val['storage_pid'],
					't3ver_oid' => $val['t3ver_oid'],
					't3ver_wsid' => $val['t3ver_wsid'],
					't3ver_state' => $val['t3ver_state'],
					't3ver_stage' => $val['t3ver_stage'],
					'backend_layout_next_level' => $val['backend_layout_next_level']
				);
				if (isset($val['_ORIG_pid'])) {
					$output[$c]['_ORIG_pid'] = $val['_ORIG_pid'];
				}
			}
			$BEgetRootLine_cache[$ident] = $output;
		}
		return $output;
	}

	/**
	 * Gets the cached page record for the rootline
	 *
	 * @param int $uid Page id for which to create the root line.
	 * @param string $clause Clause can be used to select other criteria. It would typically be where-clauses that stops the process if we meet a page, the user has no reading access to.
	 * @param bool $workspaceOL If TRUE, version overlay is applied. This must be requested specifically because it is usually only wanted when the rootline is used for visual output while for permission checking you want the raw thing!
	 * @return array Cached page record for the rootline
	 * @see BEgetRootLine
	 */
	static protected function getPageForRootline($uid, $clause, $workspaceOL) {
		static $getPageForRootline_cache = array();
		$ident = $uid . '-' . $clause . '-' . $workspaceOL;
		if (is_array($getPageForRootline_cache[$ident])) {
			$row = $getPageForRootline_cache[$ident];
		} else {
			$db = static::getDatabaseConnection();
			$res = $db->exec_SELECTquery('pid,uid,title,TSconfig,is_siteroot,storage_pid,t3ver_oid,t3ver_wsid,t3ver_state,t3ver_stage,backend_layout_next_level', 'pages', 'uid=' . (int)$uid . ' ' . self::deleteClause('pages') . ' ' . $clause);
			$row = $db->sql_fetch_assoc($res);
			if ($row) {
				$newLocation = FALSE;
				if ($workspaceOL) {
					self::workspaceOL('pages', $row);
					$newLocation = self::getMovePlaceholder('pages', $row['uid'], 'pid');
				}
				if (is_array($row)) {
					if ($newLocation !== FALSE) {
						$row['pid'] = $newLocation['pid'];
					} else {
						self::fixVersioningPid('pages', $row);
					}
					$getPageForRootline_cache[$ident] = $row;
				}
			}
			$db->sql_free_result($res);
		}
		return $row;
	}

	/**
	 * Opens the page tree to the specified page id
	 *
	 * @param int $pid Page id.
	 * @param bool $clearExpansion If set, then other open branches are closed.
	 * @return void
	 */
	static public function openPageTree($pid, $clearExpansion) {
		$beUser = static::getBackendUserAuthentication();
		// Get current expansion data:
		if ($clearExpansion) {
			$expandedPages = array();
		} else {
			$expandedPages = unserialize($beUser->uc['browseTrees']['browsePages']);
		}
		// Get rootline:
		$rL = self::BEgetRootLine($pid);
		// First, find out what mount index to use (if more than one DB mount exists):
		$mountIndex = 0;
		$mountKeys = array_flip($beUser->returnWebmounts());
		foreach ($rL as $rLDat) {
			if (isset($mountKeys[$rLDat['uid']])) {
				$mountIndex = $mountKeys[$rLDat['uid']];
				break;
			}
		}
		// Traverse rootline and open paths:
		foreach ($rL as $rLDat) {
			$expandedPages[$mountIndex][$rLDat['uid']] = 1;
		}
		// Write back:
		$beUser->uc['browseTrees']['browsePages'] = serialize($expandedPages);
		$beUser->writeUC();
	}

	/**
	 * Returns the path (visually) of a page $uid, fx. "/First page/Second page/Another subpage"
	 * Each part of the path will be limited to $titleLimit characters
	 * Deleted pages are filtered out.
	 *
	 * @param int $uid Page uid for which to create record path
	 * @param string $clause Clause is additional where clauses, eg.
	 * @param int $titleLimit Title limit
	 * @param int $fullTitleLimit Title limit of Full title (typ. set to 1000 or so)
	 * @return mixed Path of record (string) OR array with short/long title if $fullTitleLimit is set.
	 */
	static public function getRecordPath($uid, $clause, $titleLimit, $fullTitleLimit = 0) {
		if (!$titleLimit) {
			$titleLimit = 1000;
		}
		$output = $fullOutput = '/';
		$clause = trim($clause);
		if ($clause !== '' && substr($clause, 0, 3) !== 'AND') {
			$clause = 'AND ' . $clause;
		}
		$data = self::BEgetRootLine($uid, $clause);
		foreach ($data as $record) {
			if ($record['uid'] === 0) {
				continue;
			}
			$output = '/' . GeneralUtility::fixed_lgd_cs(strip_tags($record['title']), $titleLimit) . $output;
			if ($fullTitleLimit) {
				$fullOutput = '/' . GeneralUtility::fixed_lgd_cs(strip_tags($record['title']), $fullTitleLimit) . $fullOutput;
			}
		}
		if ($fullTitleLimit) {
			return array($output, $fullOutput);
		} else {
			return $output;
		}
	}

	/**
	 * Returns an array with the exclude-fields as defined in TCA and FlexForms
	 * Used for listing the exclude-fields in be_groups forms
	 *
	 * @return array Array of arrays with excludeFields (fieldname, table:fieldname) from all TCA entries and from FlexForms (fieldname, table:extkey;sheetname;fieldname)
	 */
	static public function getExcludeFields() {
		$finalExcludeArray = array();

		// Fetch translations for table names
		$tableToTranslation = array();
		$lang = static::getLanguageService();
		// All TCA keys
		foreach ($GLOBALS['TCA'] as $table => $conf) {
			$tableToTranslation[$table] = $lang->sl($conf['ctrl']['title']);
		}
		// Sort by translations
		asort($tableToTranslation);
		foreach ($tableToTranslation as $table => $translatedTable) {
			$excludeArrayTable = array();

			// All field names configured and not restricted to admins
			if (is_array($GLOBALS['TCA'][$table]['columns'])
					&& empty($GLOBALS['TCA'][$table]['ctrl']['adminOnly'])
					&& (empty($GLOBALS['TCA'][$table]['ctrl']['rootLevel']) || !empty($GLOBALS['TCA'][$table]['ctrl']['security']['ignoreRootLevelRestriction']))
			) {
				foreach ($GLOBALS['TCA'][$table]['columns'] as $field => $_) {
					if ($GLOBALS['TCA'][$table]['columns'][$field]['exclude']) {
						// Get human readable names of fields
						$translatedField = $lang->sl($GLOBALS['TCA'][$table]['columns'][$field]['label']);
						// Add entry
						$excludeArrayTable[] = array($translatedTable . ': ' . $translatedField, $table . ':' . $field);
					}
				}
			}
			// All FlexForm fields
			$flexFormArray = static::getRegisteredFlexForms($table);
			foreach ($flexFormArray as $tableField => $flexForms) {
				// Prefix for field label, e.g. "Plugin Options:"
				$labelPrefix = '';
				if (!empty($GLOBALS['TCA'][$table]['columns'][$tableField]['label'])) {
					$labelPrefix = $lang->sl($GLOBALS['TCA'][$table]['columns'][$tableField]['label']);
				}
				// Get all sheets and title
				foreach ($flexForms as $extIdent => $extConf) {
					$extTitle = $lang->sl($extConf['title']);
					// Get all fields in sheet
					foreach ($extConf['ds']['sheets'] as $sheetName => $sheet) {
						if (empty($sheet['ROOT']['el']) || !is_array($sheet['ROOT']['el'])) {
							continue;
						}
						foreach ($sheet['ROOT']['el'] as $fieldName => $field) {
							// Use only excludeable fields
							if (empty($field['TCEforms']['exclude'])) {
								continue;
							}
							$fieldLabel = !empty($field['TCEforms']['label']) ? $lang->sl($field['TCEforms']['label']) : $fieldName;
							$fieldIdent = $table . ':' . $tableField . ';' . $extIdent . ';' . $sheetName . ';' . $fieldName;
							$excludeArrayTable[] = array(trim(($labelPrefix . ' ' . $extTitle), ': ') . ': ' . $fieldLabel, $fieldIdent);
						}
					}
				}
			}
			// Sort fields by the translated value
			if (count($excludeArrayTable) > 0) {
				usort($excludeArrayTable, array(\TYPO3\CMS\Backend\Form\FlexFormsHelper::class, 'compareArraysByFirstValue'));
				$finalExcludeArray = array_merge($finalExcludeArray, $excludeArrayTable);
			}
		}

		return $finalExcludeArray;
	}

	/**
	 * Returns an array with explicit Allow/Deny fields.
	 * Used for listing these field/value pairs in be_groups forms
	 *
	 * @return array Array with information from all of $GLOBALS['TCA']
	 */
	static public function getExplicitAuthFieldValues() {
		// Initialize:
		$lang = static::getLanguageService();
		$adLabel = array(
			'ALLOW' => $lang->sl('LLL:EXT:lang/locallang_core.xlf:labels.allow'),
			'DENY' => $lang->sl('LLL:EXT:lang/locallang_core.xlf:labels.deny')
		);
		// All TCA keys:
		$allowDenyOptions = array();
		foreach ($GLOBALS['TCA'] as $table => $_) {
			// All field names configured:
			if (is_array($GLOBALS['TCA'][$table]['columns'])) {
				foreach ($GLOBALS['TCA'][$table]['columns'] as $field => $_) {
					$fCfg = $GLOBALS['TCA'][$table]['columns'][$field]['config'];
					if ($fCfg['type'] == 'select' && $fCfg['authMode']) {
						// Check for items:
						if (is_array($fCfg['items'])) {
							// Get Human Readable names of fields and table:
							$allowDenyOptions[$table . ':' . $field]['tableFieldLabel'] =
								$lang->sl($GLOBALS['TCA'][$table]['ctrl']['title']) . ': '
								. $lang->sl($GLOBALS['TCA'][$table]['columns'][$field]['label']);
							// Check for items:
							foreach ($fCfg['items'] as $iVal) {
								// Values '' is not controlled by this setting.
								if ((string)$iVal[1] !== '') {
									// Find iMode
									$iMode = '';
									switch ((string)$fCfg['authMode']) {
										case 'explicitAllow':
											$iMode = 'ALLOW';
											break;
										case 'explicitDeny':
											$iMode = 'DENY';
											break;
										case 'individual':
											if ($iVal[4] === 'EXPL_ALLOW') {
												$iMode = 'ALLOW';
											} elseif ($iVal[4] === 'EXPL_DENY') {
												$iMode = 'DENY';
											}
											break;
									}
									// Set iMode
									if ($iMode) {
										$allowDenyOptions[$table . ':' . $field]['items'][$iVal[1]] = array($iMode, $lang->sl($iVal[0]), $adLabel[$iMode]);
									}
								}
							}
						}
					}
				}
			}
		}
		return $allowDenyOptions;
	}

	/**
	 * Returns an array with system languages:
	 *
	 * The property flagIcon returns a string <flags-xx>. The calling party should call
	 * \TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIcon(<flags-xx>) to get an HTML
	 * which will represent the flag of this language.
	 *
	 * @return array Array with languages (title, uid, flagIcon - used with IconUtility::getSpriteIcon)
	 */
	static public function getSystemLanguages() {
		/** @var TranslationConfigurationProvider $translationConfigurationProvider */
		$translationConfigurationProvider = GeneralUtility::makeInstance(\TYPO3\CMS\Backend\Configuration\TranslationConfigurationProvider::class);
		$languages = $translationConfigurationProvider->getSystemLanguages();
		$sysLanguages = array();
		foreach ($languages as $language) {
			if ($language['uid'] !== -1) {
				$sysLanguages[] = array(
					0 => htmlspecialchars($language['title']) . ' [' . $language['uid'] . ']',
					1 => $language['uid'],
					2 => $language['flagIcon']
				);
			}
		}
		return $sysLanguages;
	}

	/**
	 * Gets the original translation pointer table.
	 * For e.g. pages_language_overlay this would be pages.
	 *
	 * @param string $table Name of the table
	 * @return string Pointer table (if any)
	 */
	static public function getOriginalTranslationTable($table) {
		if (!empty($GLOBALS['TCA'][$table]['ctrl']['transOrigPointerTable'])) {
			$table = $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerTable'];
		}

		return $table;
	}

	/**
	 * Determines whether a table is localizable and has the languageField and transOrigPointerField set in $GLOBALS['TCA'].
	 *
	 * @param string $table The table to check
	 * @return bool Whether a table is localizable
	 */
	static public function isTableLocalizable($table) {
		$isLocalizable = FALSE;
		if (isset($GLOBALS['TCA'][$table]['ctrl']) && is_array($GLOBALS['TCA'][$table]['ctrl'])) {
			$tcaCtrl = $GLOBALS['TCA'][$table]['ctrl'];
			$isLocalizable = isset($tcaCtrl['languageField']) && $tcaCtrl['languageField'] && isset($tcaCtrl['transOrigPointerField']) && $tcaCtrl['transOrigPointerField'];
		}
		return $isLocalizable;
	}

	/**
	 * Returns the value of the property localizationMode in the given $config array ($GLOBALS['TCA'][<table>]['columns'][<field>]['config']).
	 * If the table is prepared for localization and no localizationMode is set, 'select' is returned by default.
	 * If the table is not prepared for localization or not defined at all in $GLOBALS['TCA'], FALSE is returned.
	 *
	 * @param string $table The name of the table to lookup in TCA
	 * @param mixed $fieldOrConfig The fieldname (string) or the configuration of the field to check (array)
	 * @return mixed If table is localizable, the set localizationMode is returned (if property is not set, 'select' is returned by default); if table is not localizable, FALSE is returned
	 */
	static public function getInlineLocalizationMode($table, $fieldOrConfig) {
		$localizationMode = FALSE;
		$config = NULL;
		if (is_array($fieldOrConfig) && !empty($fieldOrConfig)) {
			$config = $fieldOrConfig;
		} elseif (is_string($fieldOrConfig) && isset($GLOBALS['TCA'][$table]['columns'][$fieldOrConfig]['config'])) {
			$config = $GLOBALS['TCA'][$table]['columns'][$fieldOrConfig]['config'];
		}
		if (is_array($config) && isset($config['type']) && $config['type'] === 'inline' && self::isTableLocalizable($table)) {
			$localizationMode = isset($config['behaviour']['localizationMode']) && $config['behaviour']['localizationMode']
				? $config['behaviour']['localizationMode']
				: 'select';
			// The mode 'select' is not possible when child table is not localizable at all:
			if ($localizationMode === 'select' && !self::isTableLocalizable($config['foreign_table'])) {
				$localizationMode = FALSE;
			}
		}
		return $localizationMode;
	}

	/**
	 * Returns a page record (of page with $id) with an extra field "_thePath" set to the record path IF the WHERE clause, $perms_clause, selects the record. Thus is works as an access check that returns a page record if access was granted, otherwise not.
	 * If $id is zero a pseudo root-page with "_thePath" set is returned IF the current BE_USER is admin.
	 * In any case ->isInWebMount must return TRUE for the user (regardless of $perms_clause)
	 *
	 * @param int $id Page uid for which to check read-access
	 * @param string $perms_clause This is typically a value generated with static::getBackendUserAuthentication()->getPagePermsClause(1);
	 * @return array Returns page record if OK, otherwise FALSE.
	 */
	static public function readPageAccess($id, $perms_clause) {
		if ((string)$id !== '') {
			$id = (int)$id;
			if (!$id) {
				if (static::getBackendUserAuthentication()->isAdmin()) {
					$path = '/';
					$pageinfo['_thePath'] = $path;
					return $pageinfo;
				}
			} else {
				$pageinfo = self::getRecord('pages', $id, '*', $perms_clause ? ' AND ' . $perms_clause : '');
				if ($pageinfo['uid'] && static::getBackendUserAuthentication()->isInWebMount($id, $perms_clause)) {
					self::workspaceOL('pages', $pageinfo);
					if (is_array($pageinfo)) {
						self::fixVersioningPid('pages', $pageinfo);
						list($pageinfo['_thePath'], $pageinfo['_thePathFull']) = self::getRecordPath((int)$pageinfo['uid'], $perms_clause, 15, 1000);
						return $pageinfo;
					}
				}
			}
		}
		return FALSE;
	}

	/**
	 * Returns the "types" configuration parsed into an array for the record, $rec, from table, $table
	 *
	 * @param string $table Table name (present in TCA)
	 * @param array $rec Record from $table
	 * @param bool $useFieldNameAsKey If $useFieldNameAsKey is set, then the fieldname is associative keys in the return array, otherwise just numeric keys.
	 * @return array|NULL
	 */
	static public function getTCAtypes($table, $rec, $useFieldNameAsKey = FALSE) {
		if ($GLOBALS['TCA'][$table]) {
			// Get type value:
			$fieldValue = self::getTCAtypeValue($table, $rec);
			$cacheIdentifier = $table . '-type-' . $fieldValue . '-fnk-' . $useFieldNameAsKey;

			// Fetch from first-level-cache if available
			if (isset(self::$tcaTableTypeConfigurationCache[$cacheIdentifier])) {
				return self::$tcaTableTypeConfigurationCache[$cacheIdentifier];
			}

			// Get typesConf
			$typesConf = $GLOBALS['TCA'][$table]['types'][$fieldValue];
			// Get fields list and traverse it
			$fieldList = explode(',', $typesConf['showitem']);

			// Add subtype fields e.g. for a valid RTE transformation
			// The RTE runs the DB -> RTE transformation only, if the RTE field is part of the getTCAtypes array
			if (isset($typesConf['subtype_value_field'])) {
				$subType = $rec[$typesConf['subtype_value_field']];
				if (isset($typesConf['subtypes_addlist'][$subType])) {
					$subFields = GeneralUtility::trimExplode(',', $typesConf['subtypes_addlist'][$subType], TRUE);
					$fieldList = array_merge($fieldList, $subFields);
				}
			}

			$altFieldList = array();
			// Traverse fields in types config and parse the configuration into a nice array:
			foreach ($fieldList as $k => $v) {
				list($pFieldName, $pAltTitle, $pPalette, $pSpec) = GeneralUtility::trimExplode(';', $v);
				$defaultExtras = is_array($GLOBALS['TCA'][$table]['columns'][$pFieldName]) ? $GLOBALS['TCA'][$table]['columns'][$pFieldName]['defaultExtras'] : '';
				$specConfParts = self::getSpecConfParts($pSpec, $defaultExtras);
				$fieldList[$k] = array(
					'field' => $pFieldName,
					'title' => $pAltTitle,
					'palette' => $pPalette,
					'spec' => $specConfParts,
					'origString' => $v
				);
				if ($useFieldNameAsKey) {
					$altFieldList[$fieldList[$k]['field']] = $fieldList[$k];
				}
			}
			if ($useFieldNameAsKey) {
				$fieldList = $altFieldList;
			}

			// Add to first-level-cache
			self::$tcaTableTypeConfigurationCache[$cacheIdentifier] = $fieldList;

			// Return array:
			return $fieldList;
		}
		return NULL;
	}

	/**
	 * Returns the "type" value of $rec from $table which can be used to look up the correct "types" rendering section in $GLOBALS['TCA']
	 * If no "type" field is configured in the "ctrl"-section of the $GLOBALS['TCA'] for the table, zero is used.
	 * If zero is not an index in the "types" section of $GLOBALS['TCA'] for the table, then the $fieldValue returned will default to 1 (no matter if that is an index or not)
	 *
	 * Note: This method is very similar to \TYPO3\CMS\Backend\Form\FormEngine::getRTypeNum(),
	 * however, it has two differences:
	 * 1) The method in TCEForms also takes care of localization (which is difficult to do here as the whole infrastructure for language overlays is only in TCEforms).
	 * 2) The $rec array looks different in TCEForms, as in there it's not the raw record but the \TYPO3\CMS\Backend\Form\DataPreprocessor version of it, which changes e.g. how "select"
	 * and "group" field values are stored, which makes different processing of the "foreign pointer field" type field variant necessary.
	 *
	 * @param string $table Table name present in TCA
	 * @param array $row Record from $table
	 * @throws \RuntimeException
	 * @return string Field value
	 * @see getTCAtypes()
	 */
	static public function getTCAtypeValue($table, $row) {
		$typeNum = 0;
		if ($GLOBALS['TCA'][$table]) {
			$field = $GLOBALS['TCA'][$table]['ctrl']['type'];
			if (strpos($field, ':') !== FALSE) {
				list($pointerField, $foreignTableTypeField) = explode(':', $field);
				// Get field value from database if field is not in the $row array
				if (!isset($row[$pointerField])) {
					$localRow = self::getRecord($table, $row['uid'], $pointerField);
					$foreignUid = $localRow[$pointerField];
				} else {
					$foreignUid = $row[$pointerField];
				}
				if ($foreignUid) {
					$fieldConfig = $GLOBALS['TCA'][$table]['columns'][$pointerField]['config'];
					$relationType = $fieldConfig['type'];
					if ($relationType === 'select') {
						$foreignTable = $fieldConfig['foreign_table'];
					} elseif ($relationType === 'group') {
						$allowedTables = explode(',', $fieldConfig['allowed']);
						$foreignTable = $allowedTables[0];
					} else {
						throw new \RuntimeException('TCA foreign field pointer fields are only allowed to be used with group or select field types.', 1325862240);
					}
					$foreignRow = self::getRecord($foreignTable, $foreignUid, $foreignTableTypeField);
					if ($foreignRow[$foreignTableTypeField]) {
						$typeNum = $foreignRow[$foreignTableTypeField];
					}
				}
			} else {
				$typeNum = $row[$field];
			}
			// If that value is an empty string, set it to "0" (zero)
			if (empty($typeNum)) {
				$typeNum = 0;
			}
		}
		// If current typeNum doesn't exist, set it to 0 (or to 1 for historical reasons, if 0 doesn't exist)
		if (!$GLOBALS['TCA'][$table]['types'][$typeNum]) {
			$typeNum = $GLOBALS['TCA'][$table]['types']['0'] ? 0 : 1;
		}
		// Force to string. Necessary for eg '-1' to be recognized as a type value.
		$typeNum = (string)$typeNum;
		return $typeNum;
	}

	/**
	 * Parses a part of the field lists in the "types"-section of $GLOBALS['TCA'] arrays, namely the "special configuration" at index 3 (position 4)
	 * Elements are splitted by ":" and within those parts, parameters are splitted by "|".
	 * Everything is returned in an array and you should rather see it visually than listen to me anymore now...  Check out example in Inside TYPO3
	 *
	 * @param string $str Content from the "types" configuration of TCA (the special configuration) - see description of function
	 * @param string $defaultExtras The ['defaultExtras'] value from field configuration
	 * @return array
	 */
	static public function getSpecConfParts($str, $defaultExtras) {
		// Add defaultExtras:
		$specConfParts = GeneralUtility::trimExplode(':', $defaultExtras . ':' . $str, TRUE);
		$reg = array();
		if (count($specConfParts)) {
			foreach ($specConfParts as $k2 => $v2) {
				unset($specConfParts[$k2]);
				if (preg_match('/(.*)\\[(.*)\\]/', $v2, $reg)) {
					$specConfParts[trim($reg[1])] = array(
						'parameters' => GeneralUtility::trimExplode('|', $reg[2], TRUE)
					);
				} else {
					$specConfParts[trim($v2)] = 1;
				}
			}
		} else {
			$specConfParts = array();
		}
		return $specConfParts;
	}

	/**
	 * Takes an array of "[key] = [value]" strings and returns an array with the keys set as keys pointing to the value.
	 * Better see it in action! Find example in Inside TYPO3
	 *
	 * @param array $pArr Array of "[key] = [value]" strings to convert.
	 * @return array
	 */
	static public function getSpecConfParametersFromArray($pArr) {
		$out = array();
		if (is_array($pArr)) {
			foreach ($pArr as $k => $v) {
				$parts = explode('=', $v, 2);
				if (count($parts) == 2) {
					$out[trim($parts[0])] = trim($parts[1]);
				} else {
					$out[$k] = $v;
				}
			}
		}
		return $out;
	}

	/**
	 * Finds the Data Structure for a FlexForm field
	 *
	 * NOTE ON data structures for deleted records: This function may fail to deliver the data structure
	 * for a record for a few reasons:
	 *  a) The data structure could be deleted (either with deleted-flagged or hard-deleted),
	 *  b) the data structure is fetched using the ds_pointerField_searchParent in which case any
	 *     deleted record on the route to the final location of the DS will make it fail.
	 * In theory, we can solve the problem in the case where records that are deleted-flagged keeps us
	 * from finding the DS - this is done at the markers ###NOTE_A### where we make sure to also select deleted records.
	 * However, we generally want the DS lookup to fail for deleted records since for the working website we expect a
	 * deleted-flagged record to be as inaccessible as one that is completely deleted from the DB. Any way we look
	 * at it, this may lead to integrity problems of the reference index and even lost files if attached.
	 * However, that is not really important considering that a single change to a data structure can instantly
	 * invalidate large amounts of the reference index which we do accept as a cost for the flexform features.
	 * Other than requiring a reference index update, deletion of/changes in data structure or the failure to look
	 * them up when completely deleting records may lead to lost files in the uploads/ folders since those are now
	 * without a proper reference.
	 *
	 * @param array $conf Field config array
	 * @param array $row Record data
	 * @param string $table The table name
	 * @param string $fieldName Optional fieldname passed to hook object
	 * @param bool $WSOL If set, workspace overlay is applied to records. This is correct behaviour for all presentation and export, but NOT if you want a TRUE reflection of how things are in the live workspace.
	 * @param int $newRecordPidValue SPECIAL CASES: Use this, if the DataStructure may come from a parent record and the INPUT row doesn't have a uid yet (hence, the pid cannot be looked up). Then it is necessary to supply a PID value to search recursively in for the DS (used from TCEmain)
	 * @return mixed If array, the data structure was found and returned as an array. Otherwise (string) it is an error message.
	 * @see \TYPO3\CMS\Backend\Form\FormEngine::getSingleField_typeFlex()
	 */
	static public function getFlexFormDS($conf, $row, $table, $fieldName = '', $WSOL = TRUE, $newRecordPidValue = 0) {
		// Get pointer field etc from TCA-config:
		$ds_pointerField = $conf['ds_pointerField'];
		$ds_array = $conf['ds'];
		$ds_tableField = $conf['ds_tableField'];
		$ds_searchParentField = $conf['ds_pointerField_searchParent'];
		// If there is a data source array, that takes precedence
		if (is_array($ds_array)) {
			// If a pointer field is set, take the value from that field in the $row array and use as key.
			if ($ds_pointerField) {
				// Up to two pointer fields can be specified in a comma separated list.
				$pointerFields = GeneralUtility::trimExplode(',', $ds_pointerField);
				// If we have two pointer fields, the array keys should contain both field values separated by comma. The asterisk "*" catches all values. For backwards compatibility, it's also possible to specify only the value of the first defined ds_pointerField.
				if (count($pointerFields) == 2) {
					if ($ds_array[$row[$pointerFields[0]] . ',' . $row[$pointerFields[1]]]) {
						// Check if we have a DS for the combination of both pointer fields values
						$srcPointer = $row[$pointerFields[0]] . ',' . $row[$pointerFields[1]];
					} elseif ($ds_array[$row[$pointerFields[1]] . ',*']) {
						// Check if we have a DS for the value of the first pointer field suffixed with ",*"
						$srcPointer = $row[$pointerFields[1]] . ',*';
					} elseif ($ds_array['*,' . $row[$pointerFields[1]]]) {
						// Check if we have a DS for the value of the second pointer field prefixed with "*,"
						$srcPointer = '*,' . $row[$pointerFields[1]];
					} elseif ($ds_array[$row[$pointerFields[0]]]) {
						// Check if we have a DS for just the value of the first pointer field (mainly for backwards compatibility)
						$srcPointer = $row[$pointerFields[0]];
					} else {
						$srcPointer = NULL;
					}
				} else {
					$srcPointer = $row[$pointerFields[0]];
				}
				$srcPointer = $srcPointer !== NULL && isset($ds_array[$srcPointer]) ? $srcPointer : 'default';
			} else {
				$srcPointer = 'default';
			}
			// Get Data Source: Detect if it's a file reference and in that case read the file and parse as XML. Otherwise the value is expected to be XML.
			if (substr($ds_array[$srcPointer], 0, 5) == 'FILE:') {
				$file = GeneralUtility::getFileAbsFileName(substr($ds_array[$srcPointer], 5));
				if ($file && @is_file($file)) {
					$dataStructArray = GeneralUtility::xml2array(GeneralUtility::getUrl($file));
				} else {
					$dataStructArray = 'The file "' . substr($ds_array[$srcPointer], 5) . '" in ds-array key "' . $srcPointer . '" was not found ("' . $file . '")';
				}
			} else {
				$dataStructArray = GeneralUtility::xml2array($ds_array[$srcPointer]);
			}
		} elseif ($ds_pointerField) {
			// If pointer field AND possibly a table/field is set:
			// Value of field pointed to:
			$srcPointer = $row[$ds_pointerField];
			// Searching recursively back if 'ds_pointerField_searchParent' is defined (typ. a page rootline, or maybe a tree-table):
			if ($ds_searchParentField && !$srcPointer) {
				$rr = self::getRecord($table, $row['uid'], 'uid,' . $ds_searchParentField);
				// Get the "pid" field - we cannot know that it is in the input record! ###NOTE_A###
				if ($WSOL) {
					self::workspaceOL($table, $rr);
					self::fixVersioningPid($table, $rr, TRUE);
				}
				$db = static::getDatabaseConnection();
				$uidAcc = array();
				// Used to avoid looping, if any should happen.
				$subFieldPointer = $conf['ds_pointerField_searchParent_subField'];
				while (!$srcPointer) {
					$res = $db->exec_SELECTquery('uid,' . $ds_pointerField . ',' . $ds_searchParentField . ($subFieldPointer ? ',' . $subFieldPointer : ''), $table, 'uid=' . (int)($newRecordPidValue ?: $rr[$ds_searchParentField]) . self::deleteClause($table));
					$newRecordPidValue = 0;
					$rr = $db->sql_fetch_assoc($res);
					$db->sql_free_result($res);
					// Break if no result from SQL db or if looping...
					if (!is_array($rr) || isset($uidAcc[$rr['uid']])) {
						break;
					}
					$uidAcc[$rr['uid']] = 1;
					if ($WSOL) {
						self::workspaceOL($table, $rr);
						self::fixVersioningPid($table, $rr, TRUE);
					}
					$srcPointer = $subFieldPointer && $rr[$subFieldPointer] ? $rr[$subFieldPointer] : $rr[$ds_pointerField];
				}
			}
			// If there is a srcPointer value:
			if ($srcPointer) {
				if (MathUtility::canBeInterpretedAsInteger($srcPointer)) {
					// If integer, then its a record we will look up:
					list($tName, $fName) = explode(':', $ds_tableField, 2);
					if ($tName && $fName && is_array($GLOBALS['TCA'][$tName])) {
						$dataStructRec = self::getRecord($tName, $srcPointer);
						if ($WSOL) {
							self::workspaceOL($tName, $dataStructRec);
						}
						if (strpos($dataStructRec[$fName], '<') === FALSE) {
							if (is_file(PATH_site . $dataStructRec[$fName])) {
								// The value is a pointer to a file
								$dataStructArray = GeneralUtility::xml2array(GeneralUtility::getUrl(PATH_site . $dataStructRec[$fName]));
							} else {
								$dataStructArray = sprintf('File \'%s\' was not found', $dataStructRec[$fName]);
							}
						} else {
							// No file pointer, handle as being XML (default behaviour)
							$dataStructArray = GeneralUtility::xml2array($dataStructRec[$fName]);
						}
					} else {
						$dataStructArray = 'No tablename (' . $tName . ') or fieldname (' . $fName . ') was found an valid!';
					}
				} else {
					// Otherwise expect it to be a file:
					$file = GeneralUtility::getFileAbsFileName($srcPointer);
					if ($file && @is_file($file)) {
						$dataStructArray = GeneralUtility::xml2array(GeneralUtility::getUrl($file));
					} else {
						// Error message.
						$dataStructArray = 'The file "' . $srcPointer . '" was not found ("' . $file . '")';
					}
				}
			} else {
				// Error message.
				$dataStructArray = 'No source value in fieldname "' . $ds_pointerField . '"';
			}
		} else {
			$dataStructArray = 'No proper configuration!';
		}
		// Hook for post-processing the Flexform DS. Introduces the possibility to configure Flexforms via TSConfig
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_befunc.php']['getFlexFormDSClass'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_befunc.php']['getFlexFormDSClass'] as $classRef) {
				$hookObj = GeneralUtility::getUserObj($classRef);
				if (method_exists($hookObj, 'getFlexFormDS_postProcessDS')) {
					$hookObj->getFlexFormDS_postProcessDS($dataStructArray, $conf, $row, $table, $fieldName);
				}
			}
		}
		return $dataStructArray;
	}

	/**
	 * Returns all registered FlexForm definitions with title and fields
	 *
	 * @param string $table The content table
	 * @return array The data structures with speaking extension title
	 * @see \TYPO3\CMS\Backend\Utility\BackendUtility::getExcludeFields()
	 */
	static public function getRegisteredFlexForms($table = 'tt_content') {
		if (empty($table) || empty($GLOBALS['TCA'][$table]['columns'])) {
			return array();
		}
		$flexForms = array();
		foreach ($GLOBALS['TCA'][$table]['columns'] as $tableField => $fieldConf) {
			if (!empty($fieldConf['config']['type']) && !empty($fieldConf['config']['ds']) && $fieldConf['config']['type'] == 'flex') {
				$flexForms[$tableField] = array();
				unset($fieldConf['config']['ds']['default']);
				// Get pointer fields
				$pointerFields = !empty($fieldConf['config']['ds_pointerField']) ? $fieldConf['config']['ds_pointerField'] : 'list_type,CType';
				$pointerFields = GeneralUtility::trimExplode(',', $pointerFields);
				// Get FlexForms
				foreach ($fieldConf['config']['ds'] as $flexFormKey => $dataStruct) {
					// Get extension identifier (uses second value if it's not empty, "list" or "*", else first one)
					$identFields = GeneralUtility::trimExplode(',', $flexFormKey);
					$extIdent = $identFields[0];
					if (!empty($identFields[1]) && $identFields[1] != 'list' && $identFields[1] != '*') {
						$extIdent = $identFields[1];
					}
					// Load external file references
					if (!is_array($dataStruct)) {
						$file = GeneralUtility::getFileAbsFileName(str_ireplace('FILE:', '', $dataStruct));
						if ($file && @is_file($file)) {
							$dataStruct = GeneralUtility::getUrl($file);
						}
						$dataStruct = GeneralUtility::xml2array($dataStruct);
						if (!is_array($dataStruct)) {
							continue;
						}
					}
					// Get flexform content
					$dataStruct = GeneralUtility::resolveAllSheetsInDS($dataStruct);
					if (empty($dataStruct['sheets']) || !is_array($dataStruct['sheets'])) {
						continue;
					}
					// Use DS pointer to get extension title from TCA
					$title = $extIdent;
					$keyFields = GeneralUtility::trimExplode(',', $flexFormKey);
					foreach ($pointerFields as $pointerKey => $pointerName) {
						if (empty($keyFields[$pointerKey]) || $keyFields[$pointerKey] == '*' || $keyFields[$pointerKey] == 'list') {
							continue;
						}
						if (!empty($GLOBALS['TCA'][$table]['columns'][$pointerName]['config']['items'])) {
							$items = $GLOBALS['TCA'][$table]['columns'][$pointerName]['config']['items'];
							if (!is_array($items)) {
								continue;
							}
							foreach ($items as $itemConf) {
								if (!empty($itemConf[0]) && !empty($itemConf[1]) && $itemConf[1] == $keyFields[$pointerKey]) {
									$title = $itemConf[0];
									break 2;
								}
							}
						}
					}
					$flexForms[$tableField][$extIdent] = array(
						'title' => $title,
						'ds' => $dataStruct
					);
				}
			}
		}
		return $flexForms;
	}

	/*******************************************
	 *
	 * Caching related
	 *
	 *******************************************/
	/**
	 * Stores $data in the 'cache_hash' cache with the hash key, $hash
	 * and visual/symbolic identification, $ident
	 *
	 * IDENTICAL to the function by same name found in \TYPO3\CMS\Frontend\Page\PageRepository
	 *
	 * @param string $hash 32 bit hash string (eg. a md5 hash of a serialized array identifying the data being stored)
	 * @param mixed $data The data to store
	 * @param string $ident $ident is just a textual identification in order to inform about the content!
	 * @return void
	 */
	static public function storeHash($hash, $data, $ident) {
		/** @var CacheManager $cacheManager */
		$cacheManager = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Cache\CacheManager::class);
		$cacheManager->getCache('cache_hash')->set($hash, $data, array('ident_' . $ident), 0);
	}

	/**
	 * Returns data stored for the hash string in the cache "cache_hash"
	 * Can be used to retrieved a cached value, array or object
	 *
	 * IDENTICAL to the function by same name found in \TYPO3\CMS\Frontend\Page\PageRepository
	 *
	 * @param string $hash The hash-string which was used to store the data value
	 * @return mixed The "data" from the cache
	 */
	static public function getHash($hash) {
		/** @var CacheManager $cacheManager */
		$cacheManager = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Cache\CacheManager::class);
		$cacheEntry = $cacheManager->getCache('cache_hash')->get($hash);
		$hashContent = NULL;
		if ($cacheEntry) {
			$hashContent = $cacheEntry;
		}
		return $hashContent;
	}

	/*******************************************
	 *
	 * TypoScript related
	 *
	 *******************************************/
	/**
	 * Returns the Page TSconfig for page with id, $id
	 *
	 * @param int $id Page uid for which to create Page TSconfig
	 * @param array $rootLine If $rootLine is an array, that is used as rootline, otherwise rootline is just calculated
	 * @param bool $returnPartArray If $returnPartArray is set, then the array with accumulated Page TSconfig is returned non-parsed. Otherwise the output will be parsed by the TypoScript parser.
	 * @return array Page TSconfig
	 * @see \TYPO3\CMS\Core\TypoScript\Parser\TypoScriptParser
	 */
	static public function getPagesTSconfig($id, $rootLine = NULL, $returnPartArray = FALSE) {
		static $pagesTSconfig_cacheReference = array();
		static $combinedTSconfig_cache = array();

		$id = (int)$id;
		if ($returnPartArray === FALSE
			&& $rootLine === NULL
			&& isset($pagesTSconfig_cacheReference[$id])
		) {
			return $combinedTSconfig_cache[$pagesTSconfig_cacheReference[$id]];
		} else {
			$TSconfig = array();
			if (!is_array($rootLine)) {
				$useCacheForCurrentPageId = TRUE;
				$rootLine = self::BEgetRootLine($id, '', TRUE);
			} else {
				$useCacheForCurrentPageId = FALSE;
			}

			// Order correctly
			ksort($rootLine);
			$TSdataArray = array();
			// Setting default configuration
			$TSdataArray['defaultPageTSconfig'] = $GLOBALS['TYPO3_CONF_VARS']['BE']['defaultPageTSconfig'];
			foreach ($rootLine as $k => $v) {
				$TSdataArray['uid_' . $v['uid']] = $v['TSconfig'];
			}
			$TSdataArray = static::emitGetPagesTSconfigPreIncludeSignal($TSdataArray, $id, $rootLine, $returnPartArray);
			$TSdataArray = TypoScriptParser::checkIncludeLines_array($TSdataArray);
			if ($returnPartArray) {
				return $TSdataArray;
			}
			// Parsing the page TS-Config
			$pageTS = implode(LF . '[GLOBAL]' . LF, $TSdataArray);
			/* @var $parseObj \TYPO3\CMS\Backend\Configuration\TsConfigParser */
			$parseObj = GeneralUtility::makeInstance(\TYPO3\CMS\Backend\Configuration\TsConfigParser::class);
			$res = $parseObj->parseTSconfig($pageTS, 'PAGES', $id, $rootLine);
			if ($res) {
				$TSconfig = $res['TSconfig'];
			}
			$cacheHash = $res['hash'];
			// Get User TSconfig overlay
			$userTSconfig = static::getBackendUserAuthentication()->userTS['page.'];
			if (is_array($userTSconfig)) {
				ArrayUtility::mergeRecursiveWithOverrule($TSconfig, $userTSconfig);
				$cacheHash .= '_user' . $GLOBALS['BE_USER']->user['uid'];
			}

			if ($useCacheForCurrentPageId) {
				if (!isset($combinedTSconfig_cache[$cacheHash])) {
					$combinedTSconfig_cache[$cacheHash] = $TSconfig;
				}
				$pagesTSconfig_cacheReference[$id] = $cacheHash;
			}
		}
		return $TSconfig;
	}

	/**
	 * Implodes a multi dimensional TypoScript array, $p, into a one-dimensional array (return value)
	 *
	 * @param array $p TypoScript structure
	 * @param string $k Prefix string
	 * @return array Imploded TypoScript objectstring/values
	 * @deprecated since TYPO3 CMS 7, will be removed in TYPO3 CMS 8
	 */
	static public function implodeTSParams($p, $k = '') {
		GeneralUtility::logDeprecatedFunction();
		$implodeParams = array();
		if (is_array($p)) {
			foreach ($p as $kb => $val) {
				if (is_array($val)) {
					$implodeParams = array_merge($implodeParams, self::implodeTSParams($val, $k . $kb));
				} else {
					$implodeParams[$k . $kb] = $val;
				}
			}
		}
		return $implodeParams;
	}

	/*******************************************
	 *
	 * Users / Groups related
	 *
	 *******************************************/
	/**
	 * Returns an array with be_users records of all user NOT DELETED sorted by their username
	 * Keys in the array is the be_users uid
	 *
	 * @param string $fields Optional $fields list (default: username,usergroup,usergroup_cached_list,uid) can be used to set the selected fields
	 * @param string $where Optional $where clause (fx. "AND username='pete'") can be used to limit query
	 * @return array
	 */
	static public function getUserNames($fields = 'username,usergroup,usergroup_cached_list,uid', $where = '') {
		return self::getRecordsSortedByTitle(
			GeneralUtility::trimExplode(',', $fields, TRUE),
			'be_users',
			'username',
			'AND pid=0 ' . $where
		);
	}

	/**
	 * Returns an array with be_groups records (title, uid) of all groups NOT DELETED sorted by their title
	 *
	 * @param string $fields Field list
	 * @param string $where WHERE clause
	 * @return array
	 */
	static public function getGroupNames($fields = 'title,uid', $where = '') {
		return self::getRecordsSortedByTitle(
			GeneralUtility::trimExplode(',', $fields, TRUE),
			'be_groups',
			'title',
			'AND pid=0 ' . $where
		);
	}

	/**
	 * Returns an array of all non-deleted records of a table sorted by a given title field.
	 * The value of the title field will be replaced by the return value
	 * of self::getRecordTitle() before the sorting is performed.
	 *
	 * @param array $fields Fields to select
	 * @param string $table Table name
	 * @param string $titleField Field that will contain the record title
	 * @param string $where Additional where clause
	 * @return array Array of sorted records
	 */
	static protected function getRecordsSortedByTitle(array $fields, $table, $titleField, $where = '') {
		$fieldsIndex = array_flip($fields);
		// Make sure the titleField is amongst the fields when getting sorted
		$fieldsIndex[$titleField] = 1;

		$result = array();
		$db = static::getDatabaseConnection();
		$res = $db->exec_SELECTquery('*', $table, '1=1 ' . $where . self::deleteClause($table));
		while ($record = $db->sql_fetch_assoc($res)) {
			// store the uid, because it might be unset if it's not among the requested $fields
			$recordId = $record['uid'];
			$record[$titleField] = self::getRecordTitle($table, $record);

			// include only the requested fields in the result
			$result[$recordId] = array_intersect_key($record, $fieldsIndex);
		}
		$db->sql_free_result($res);

		// sort records by $sortField. This is not done in the query because the title might have been overwritten by
		// self::getRecordTitle();
		return ArrayUtility::sortArraysByKey($result, $titleField);
	}

	/**
	 * Returns an array with be_groups records (like ->getGroupNames) but:
	 * - if the current BE_USER is admin, then all groups are returned, otherwise only groups that the current user is member of (usergroup_cached_list) will be returned.
	 *
	 * @param string $fields Field list; $fields specify the fields selected (default: title,uid)
	 * @return 	array
	 */
	static public function getListGroupNames($fields = 'title, uid') {
		$beUser = static::getBackendUserAuthentication();
		$exQ = ' AND hide_in_lists=0';
		if (!$beUser->isAdmin()) {
			$exQ .= ' AND uid IN (' . ($beUser->user['usergroup_cached_list'] ?: 0) . ')';
		}
		return self::getGroupNames($fields, $exQ);
	}

	/**
	 * Returns the array $usernames with the names of all users NOT IN $groupArray changed to the uid (hides the usernames!).
	 * If $excludeBlindedFlag is set, then these records are unset from the array $usernames
	 * Takes $usernames (array made by \TYPO3\CMS\Backend\Utility\BackendUtility::getUserNames()) and a $groupArray (array with the groups a certain user is member of) as input
	 *
	 * @param array $usernames User names
	 * @param array $groupArray Group names
	 * @param bool $excludeBlindedFlag If $excludeBlindedFlag is set, then these records are unset from the array $usernames
	 * @return array User names, blinded
	 */
	static public function blindUserNames($usernames, $groupArray, $excludeBlindedFlag = FALSE) {
		if (is_array($usernames) && is_array($groupArray)) {
			foreach ($usernames as $uid => $row) {
				$userN = $uid;
				$set = 0;
				if ($row['uid'] != static::getBackendUserAuthentication()->user['uid']) {
					foreach ($groupArray as $v) {
						if ($v && GeneralUtility::inList($row['usergroup_cached_list'], $v)) {
							$userN = $row['username'];
							$set = 1;
						}
					}
				} else {
					$userN = $row['username'];
					$set = 1;
				}
				$usernames[$uid]['username'] = $userN;
				if ($excludeBlindedFlag && !$set) {
					unset($usernames[$uid]);
				}
			}
		}
		return $usernames;
	}

	/**
	 * Corresponds to blindUserNames but works for groups instead
	 *
	 * @param array $groups Group names
	 * @param array $groupArray Group names (reference)
	 * @param bool $excludeBlindedFlag If $excludeBlindedFlag is set, then these records are unset from the array $usernames
	 * @return array
	 */
	static public function blindGroupNames($groups, $groupArray, $excludeBlindedFlag = FALSE) {
		if (is_array($groups) && is_array($groupArray)) {
			foreach ($groups as $uid => $row) {
				$groupN = $uid;
				$set = 0;
				if (ArrayUtility::inArray($groupArray, $uid)) {
					$groupN = $row['title'];
					$set = 1;
				}
				$groups[$uid]['title'] = $groupN;
				if ($excludeBlindedFlag && !$set) {
					unset($groups[$uid]);
				}
			}
		}
		return $groups;
	}

	/*******************************************
	 *
	 * Output related
	 *
	 *******************************************/
	/**
	 * Returns the difference in days between input $tstamp and $EXEC_TIME
	 *
	 * @param int $tstamp Time stamp, seconds
	 * @return int
	 */
	static public function daysUntil($tstamp) {
		$delta_t = $tstamp - $GLOBALS['EXEC_TIME'];
		return ceil($delta_t / (3600 * 24));
	}

	/**
	 * Returns $tstamp formatted as "ddmmyy" (According to $GLOBALS['TYPO3_CONF_VARS']['SYS']['ddmmyy'])
	 *
	 * @param int $tstamp Time stamp, seconds
	 * @return string Formatted time
	 */
	static public function date($tstamp) {
		return date($GLOBALS['TYPO3_CONF_VARS']['SYS']['ddmmyy'], (int)$tstamp);
	}

	/**
	 * Returns $tstamp formatted as "ddmmyy hhmm" (According to $GLOBALS['TYPO3_CONF_VARS']['SYS']['ddmmyy'] AND $GLOBALS['TYPO3_CONF_VARS']['SYS']['hhmm'])
	 *
	 * @param int $value Time stamp, seconds
	 * @return string Formatted time
	 */
	static public function datetime($value) {
		return date($GLOBALS['TYPO3_CONF_VARS']['SYS']['ddmmyy'] . ' ' . $GLOBALS['TYPO3_CONF_VARS']['SYS']['hhmm'], $value);
	}

	/**
	 * Returns $value (in seconds) formatted as hh:mm:ss
	 * For instance $value = 3600 + 60*2 + 3 should return "01:02:03"
	 *
	 * @param int $value Time stamp, seconds
	 * @param bool $withSeconds Output hh:mm:ss. If FALSE: hh:mm
	 * @return string Formatted time
	 */
	static public function time($value, $withSeconds = TRUE) {
		$hh = floor($value / 3600);
		$min = floor(($value - $hh * 3600) / 60);
		$sec = $value - $hh * 3600 - $min * 60;
		$l = sprintf('%02d', $hh) . ':' . sprintf('%02d', $min);
		if ($withSeconds) {
			$l .= ':' . sprintf('%02d', $sec);
		}
		return $l;
	}

	/**
	 * Returns the "age" in minutes / hours / days / years of the number of $seconds inputted.
	 *
	 * @param int $seconds Seconds could be the difference of a certain timestamp and time()
	 * @param string $labels Labels should be something like ' min| hrs| days| yrs| min| hour| day| year'. This value is typically delivered by this function call: $GLOBALS["LANG"]->sL("LLL:EXT:lang/locallang_core.xlf:labels.minutesHoursDaysYears")
	 * @return string Formatted time
	 */
	static public function calcAge($seconds, $labels = ' min| hrs| days| yrs| min| hour| day| year') {
		$labelArr = explode('|', $labels);
		$absSeconds = abs($seconds);
		$sign = $seconds < 0 ? -1 : 1;
		if ($absSeconds < 3600) {
			$val = round($absSeconds / 60);
			$seconds = $sign * $val . ($val == 1 ? $labelArr[4] : $labelArr[0]);
		} elseif ($absSeconds < 24 * 3600) {
			$val = round($absSeconds / 3600);
			$seconds = $sign * $val . ($val == 1 ? $labelArr[5] : $labelArr[1]);
		} elseif ($absSeconds < 365 * 24 * 3600) {
			$val = round($absSeconds / (24 * 3600));
			$seconds = $sign * $val . ($val == 1 ? $labelArr[6] : $labelArr[2]);
		} else {
			$val = round($absSeconds / (365 * 24 * 3600));
			$seconds = $sign * $val . ($val == 1 ? $labelArr[7] : $labelArr[3]);
		}
		return $seconds;
	}

	/**
	 * Returns a formatted timestamp if $tstamp is set.
	 * The date/datetime will be followed by the age in parenthesis.
	 *
	 * @param int $tstamp Time stamp, seconds
	 * @param int $prefix 1/-1 depending on polarity of age.
	 * @param string $date $date=="date" will yield "dd:mm:yy" formatting, otherwise "dd:mm:yy hh:mm
	 * @return string
	 */
	static public function dateTimeAge($tstamp, $prefix = 1, $date = '') {
		if (!$tstamp) {
			return '';
		}
		$label = static::getLanguageService()->sL('LLL:EXT:lang/locallang_core.xlf:labels.minutesHoursDaysYears');
		$age = ' (' . self::calcAge($prefix * ($GLOBALS['EXEC_TIME'] - $tstamp), $label) . ')';
		return $date === 'date' ? self::date($tstamp) : self::datetime($tstamp) . $age;
	}

	/**
	 * Returns alt="" and title="" attributes with the value of $content.
	 *
	 * @param string $content Value for 'alt' and 'title' attributes (will be htmlspecialchars()'ed before output)
	 * @return string
	 */
	static public function titleAltAttrib($content) {
		$out = '';
		$out .= ' alt="' . htmlspecialchars($content) . '"';
		$out .= ' title="' . htmlspecialchars($content) . '"';
		return $out;
	}

	/**
	 * Resolves file references for a given record.
	 *
	 * @param string $tableName Name of the table of the record
	 * @param string $fieldName Name of the field of the record
	 * @param array $element Record data
	 * @param NULL|int $workspaceId Workspace to fetch data for
	 * @return NULL|\TYPO3\CMS\Core\Resource\FileReference[]
	 */
	static public function resolveFileReferences($tableName, $fieldName, $element, $workspaceId = NULL) {
		if (empty($GLOBALS['TCA'][$tableName]['columns'][$fieldName]['config'])) {
			return NULL;
		}
		$configuration = $GLOBALS['TCA'][$tableName]['columns'][$fieldName]['config'];
		if (empty($configuration['type']) || $configuration['type'] !== 'inline'
			|| empty($configuration['foreign_table']) || $configuration['foreign_table'] !== 'sys_file_reference') {
			return NULL;
		}

		$fileReferences = array();
		/** @var $relationHandler \TYPO3\CMS\Core\Database\RelationHandler */
		$relationHandler = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\RelationHandler::class);
		if ($workspaceId !== NULL) {
			$relationHandler->setWorkspaceId($workspaceId);
		}
		$relationHandler->start($element[$fieldName], $configuration['foreign_table'], $configuration['MM'], $element['uid'], $tableName, $configuration);
		$relationHandler->processDeletePlaceholder();
		$referenceUids = $relationHandler->tableArray[$configuration['foreign_table']];

		foreach ($referenceUids as $referenceUid) {
			try {
				$fileReference = ResourceFactory::getInstance()->getFileReferenceObject($referenceUid, array(), ($workspaceId === 0));
				$fileReferences[$fileReference->getUid()] = $fileReference;
			} catch (\TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException $e) {
				/**
				 * We just catch the exception here
				 * Reasoning: There is nothing an editor or even admin could do
				 */
			}
		}

		return $fileReferences;
	}

	/**
	 * Returns a linked image-tag for thumbnail(s)/fileicons/truetype-font-previews from a database row with a list of image files in a field
	 * All $GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'] extension are made to thumbnails + ttf file (renders font-example)
	 * Thumbsnails are linked to the show_item.php script which will display further details.
	 *
	 * @param array $row Row is the database row from the table, $table.
	 * @param string $table Table name for $row (present in TCA)
	 * @param string $field Field is pointing to the list of image files
	 * @param string $backPath Back path prefix for image tag src="" field
	 * @param string $thumbScript UNUSED since FAL
	 * @param string $uploaddir Optional: $uploaddir is the directory relative to PATH_site where the image files from the $field value is found (Is by default set to the entry in $GLOBALS['TCA'] for that field! so you don't have to!)
	 * @param int $abs UNUSED
	 * @param string $tparams Optional: $tparams is additional attributes for the image tags
	 * @param int|string $size Optional: $size is [w]x[h] of the thumbnail. 64 is default.
	 * @param bool $linkInfoPopup Whether to wrap with a link opening the info popup
	 * @return string Thumbnail image tag.
	 */
	static public function thumbCode($row, $table, $field, $backPath, $thumbScript = '', $uploaddir = NULL, $abs = 0, $tparams = '', $size = '', $linkInfoPopup = TRUE) {
		// Check and parse the size parameter
		$size = trim($size);
		$sizeParts = array(64, 64);
		if ($size) {
			$sizeParts = explode('x', $size . 'x' . $size);
		}
		$thumbData = '';
		$fileReferences = static::resolveFileReferences($table, $field, $row);
		// FAL references
		if ($fileReferences !== NULL) {
			foreach ($fileReferences as $fileReferenceObject) {
				$fileObject = $fileReferenceObject->getOriginalFile();

				if ($fileObject->isMissing()) {
					$flashMessage = \TYPO3\CMS\Core\Resource\Utility\BackendUtility::getFlashMessageForMissingFile($fileObject);
					$thumbData .= $flashMessage->render();
					continue;
				}

				// Web image
				if (GeneralUtility::inList($GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'], $fileReferenceObject->getExtension())) {
					$imageUrl = $fileObject->process(ProcessedFile::CONTEXT_IMAGEPREVIEW, array(
						'width' => $sizeParts[0],
						'height' => $sizeParts[1]
					))->getPublicUrl(TRUE);
					$imgTag = '<img src="' . $imageUrl . '" alt="' . htmlspecialchars($fileReferenceObject->getName()) . '" />';
				} else {
					// Icon
					$imgTag = IconUtility::getSpriteIconForResource($fileObject, array('title' => $fileObject->getName()));
				}
				if ($linkInfoPopup) {
					$onClick = 'top.launchView(\'_FILE\',\'' . $fileObject->getUid() . '\',\'' . $backPath . '\'); return false;';
					$thumbData .= '<a href="#" onclick="' . htmlspecialchars($onClick) . '">' . $imgTag . '</a> ';
				} else {
					$thumbData .= $imgTag;
				}
			}
		} else {
			// Find uploaddir automatically
			if (is_null($uploaddir)) {
				$uploaddir = $GLOBALS['TCA'][$table]['columns'][$field]['config']['uploadfolder'];
			}
			$uploaddir = rtrim($uploaddir, '/');
			// Traverse files:
			$thumbs = GeneralUtility::trimExplode(',', $row[$field], TRUE);
			$thumbData = '';
			foreach ($thumbs as $theFile) {
				if ($theFile) {
					$fileName = trim($uploaddir . '/' . $theFile, '/');
					try {
						/** @var File $fileObject */
						$fileObject = ResourceFactory::getInstance()->retrieveFileOrFolderObject($fileName);
						if ($fileObject->isMissing()) {
							$flashMessage = \TYPO3\CMS\Core\Resource\Utility\BackendUtility::getFlashMessageForMissingFile($fileObject);
							$thumbData .= $flashMessage->render();
							continue;
						}
					} catch (ResourceDoesNotExistException $exception) {
						/** @var FlashMessage $flashMessage */
						$flashMessage = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Messaging\FlashMessage::class,
							htmlspecialchars($exception->getMessage()),
							static::getLanguageService()->sL('LLL:EXT:lang/locallang_core.xlf:warning.file_missing', TRUE),
							FlashMessage::ERROR
						);
						$thumbData .= $flashMessage->render();
						continue;
					}

					$fileExtension = $fileObject->getExtension();
					if ($fileExtension == 'ttf' || GeneralUtility::inList($GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'], $fileExtension)) {
						$imageUrl = $fileObject->process(ProcessedFile::CONTEXT_IMAGEPREVIEW, array(
							'width' => $sizeParts[0],
							'height' => $sizeParts[1]
						))->getPublicUrl(TRUE);
						$image = '<img src="' . htmlspecialchars($imageUrl) . '" hspace="2" border="0" title="' . htmlspecialchars($fileObject->getName()) . '"' . $tparams . ' alt="" />';
						if ($linkInfoPopup) {
							$onClick = 'top.launchView(\'_FILE\', \'' . $fileName . '\',\'\',\'' . $backPath . '\');return false;';
							$thumbData .= '<a href="#" onclick="' . htmlspecialchars($onClick) . '">' . $image . '</a> ';
						} else {
							$thumbData .= $image;
						}
					} else {
						// Gets the icon
						$fileIcon = IconUtility::getSpriteIconForResource($fileObject, array('title' => $fileObject->getName()));
						if ($linkInfoPopup) {
							$onClick = 'top.launchView(\'_FILE\', \'' . $fileName . '\',\'\',\'' . $backPath . '\'); return false;';
							$thumbData .= '<a href="#" onclick="' . htmlspecialchars($onClick) . '">' . $fileIcon . '</a> ';
						} else {
							$thumbData .= $fileIcon;
						}
					}
				}
			}
		}
		return $thumbData;
	}

	/**
	 * Returns single image tag to thumbnail using a thumbnail script (like thumbs.php)
	 *
	 * @param string $thumbScript Must point to "thumbs.php" relative to the script position
	 * @param string $theFile Must be the proper reference to the file that thumbs.php should show
	 * @param string $tparams The additional attributes for the image tag
	 * @param string $size The size of the thumbnail send along to thumbs.php
	 * @return string Image tag
	 */
	static public function getThumbNail($thumbScript, $theFile, $tparams = '', $size = '') {
		$size = trim($size);
		$check = basename($theFile) . ':' . filemtime($theFile) . ':' . $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'];
		$params = '&file=' . rawurlencode($theFile);
		$params .= $size ? '&size=' . $size : '';
		$params .= '&md5sum=' . md5($check);
		$url = $thumbScript . '?' . $params;
		$th = '<img src="' . htmlspecialchars($url) . '" title="' . trim(basename($theFile)) . '"' . ($tparams ? ' ' . $tparams : '') . ' alt="" />';
		return $th;
	}

	/**
	 * Returns title-attribute information for a page-record informing about id, alias, doktype, hidden, starttime, endtime, fe_group etc.
	 *
	 * @param array $row Input must be a page row ($row) with the proper fields set (be sure - send the full range of fields for the table)
	 * @param string $perms_clause This is used to get the record path of the shortcut page, if any (and doktype==4)
	 * @param bool $includeAttrib If $includeAttrib is set, then the 'title=""' attribute is wrapped about the return value, which is in any case htmlspecialchar()'ed already
	 * @return string
	 */
	static public function titleAttribForPages($row, $perms_clause = '', $includeAttrib = TRUE) {
		$lang = static::getLanguageService();
		$parts = array();
		$parts[] = 'id=' . $row['uid'];
		if ($row['alias']) {
			$parts[] = $lang->sL($GLOBALS['TCA']['pages']['columns']['alias']['label']) . ' ' . $row['alias'];
		}
		if ($row['pid'] < 0) {
			$parts[] = 'v#1.' . $row['t3ver_id'];
		}
		switch (VersionState::cast($row['t3ver_state'])) {
			case new VersionState(VersionState::NEW_PLACEHOLDER):
				$parts[] = 'PLH WSID#' . $row['t3ver_wsid'];
				break;
			case new VersionState(VersionState::DELETE_PLACEHOLDER):
				$parts[] = 'Deleted element!';
				break;
			case new VersionState(VersionState::MOVE_PLACEHOLDER):
				$parts[] = 'NEW LOCATION (PLH) WSID#' . $row['t3ver_wsid'];
				break;
			case new VersionState(VersionState::MOVE_POINTER):
				$parts[] = 'OLD LOCATION (PNT) WSID#' . $row['t3ver_wsid'];
				break;
			case new VersionState(VersionState::NEW_PLACEHOLDER_VERSION):
				$parts[] = 'New element!';
				break;
		}
		if ($row['doktype'] == PageRepository::DOKTYPE_LINK) {
			$parts[] = $lang->sL($GLOBALS['TCA']['pages']['columns']['url']['label']) . ' ' . $row['url'];
		} elseif ($row['doktype'] == PageRepository::DOKTYPE_SHORTCUT) {
			if ($perms_clause) {
				$label = self::getRecordPath((int)$row['shortcut'], $perms_clause, 20);
			} else {
				$row['shortcut'] = (int)$row['shortcut'];
				$lRec = self::getRecordWSOL('pages', $row['shortcut'], 'title');
				$label = $lRec['title'] . ' (id=' . $row['shortcut'] . ')';
			}
			if ($row['shortcut_mode'] != PageRepository::SHORTCUT_MODE_NONE) {
				$label .= ', ' . $lang->sL($GLOBALS['TCA']['pages']['columns']['shortcut_mode']['label']) . ' ' . $lang->sL(self::getLabelFromItemlist('pages', 'shortcut_mode', $row['shortcut_mode']));
			}
			$parts[] = $lang->sL($GLOBALS['TCA']['pages']['columns']['shortcut']['label']) . ' ' . $label;
		} elseif ($row['doktype'] == PageRepository::DOKTYPE_MOUNTPOINT) {
			if ($perms_clause) {
				$label = self::getRecordPath((int)$row['mount_pid'], $perms_clause, 20);
			} else {
				$lRec = self::getRecordWSOL('pages', (int)$row['mount_pid'], 'title');
				$label = $lRec['title'] . ' (id=' . $row['mount_pid'] . ')';
			}
			$parts[] = $lang->sL($GLOBALS['TCA']['pages']['columns']['mount_pid']['label']) . ' ' . $label;
			if ($row['mount_pid_ol']) {
				$parts[] = $lang->sL($GLOBALS['TCA']['pages']['columns']['mount_pid_ol']['label']);
			}
		}
		if ($row['nav_hide']) {
			$parts[] = rtrim($lang->sL($GLOBALS['TCA']['pages']['columns']['nav_hide']['label']), ':');
		}
		if ($row['hidden']) {
			$parts[] = $lang->sL('LLL:EXT:lang/locallang_core.xlf:labels.hidden');
		}
		if ($row['starttime']) {
			$parts[] = $lang->sL($GLOBALS['TCA']['pages']['columns']['starttime']['label']) . ' ' . self::dateTimeAge($row['starttime'], -1, 'date');
		}
		if ($row['endtime']) {
			$parts[] = $lang->sL($GLOBALS['TCA']['pages']['columns']['endtime']['label']) . ' ' . self::dateTimeAge($row['endtime'], -1, 'date');
		}
		if ($row['fe_group']) {
			$fe_groups = array();
			foreach (GeneralUtility::intExplode(',', $row['fe_group']) as $fe_group) {
				if ($fe_group < 0) {
					$fe_groups[] = $lang->sL(self::getLabelFromItemlist('pages', 'fe_group', $fe_group));
				} else {
					$lRec = self::getRecordWSOL('fe_groups', $fe_group, 'title');
					$fe_groups[] = $lRec['title'];
				}
			}
			$label = implode(', ', $fe_groups);
			$parts[] = $lang->sL($GLOBALS['TCA']['pages']['columns']['fe_group']['label']) . ' ' . $label;
		}
		$out = htmlspecialchars(implode(' - ', $parts));
		return $includeAttrib ? 'title="' . $out . '"' : $out;
	}

	/**
	 * Returns title-attribute information for ANY record (from a table defined in TCA of course)
	 * The included information depends on features of the table, but if hidden, starttime, endtime and fe_group fields are configured for, information about the record status in regard to these features are is included.
	 * "pages" table can be used as well and will return the result of ->titleAttribForPages() for that page.
	 *
	 * @param array $row Table row; $row is a row from the table, $table
	 * @param string $table Table name
	 * @return 	string
	 */
	static public function getRecordIconAltText($row, $table = 'pages') {
		if ($table == 'pages') {
			$out = self::titleAttribForPages($row, '', 0);
		} else {
			$ctrl = $GLOBALS['TCA'][$table]['ctrl']['enablecolumns'];
			// Uid is added
			$out = 'id=' . $row['uid'];
			if ($table == 'pages' && $row['alias']) {
				$out .= ' / ' . $row['alias'];
			}
			if ($GLOBALS['TCA'][$table]['ctrl']['versioningWS'] && $row['pid'] < 0) {
				$out .= ' - v#1.' . $row['t3ver_id'];
			}
			if ($GLOBALS['TCA'][$table]['ctrl']['versioningWS']) {
				switch (VersionState::cast($row['t3ver_state'])) {
					case new VersionState(VersionState::NEW_PLACEHOLDER):
						$out .= ' - PLH WSID#' . $row['t3ver_wsid'];
						break;
					case new VersionState(VersionState::DELETE_PLACEHOLDER):
						$out .= ' - Deleted element!';
						break;
					case new VersionState(VersionState::MOVE_PLACEHOLDER):
						$out .= ' - NEW LOCATION (PLH) WSID#' . $row['t3ver_wsid'];
						break;
					case new VersionState(VersionState::MOVE_POINTER):
						$out .= ' - OLD LOCATION (PNT)  WSID#' . $row['t3ver_wsid'];
						break;
					case new VersionState(VersionState::NEW_PLACEHOLDER_VERSION):
						$out .= ' - New element!';
						break;
				}
			}
			// Hidden
			$lang = static::getLanguageService();
			if ($ctrl['disabled']) {
				$out .= $row[$ctrl['disabled']] ? ' - ' . $lang->sL('LLL:EXT:lang/locallang_core.xlf:labels.hidden') : '';
			}
			if ($ctrl['starttime']) {
				if ($row[$ctrl['starttime']] > $GLOBALS['EXEC_TIME']) {
					$out .= ' - ' . $lang->sL('LLL:EXT:lang/locallang_core.xlf:labels.starttime') . ':' . self::date($row[$ctrl['starttime']]) . ' (' . self::daysUntil($row[$ctrl['starttime']]) . ' ' . $lang->sL('LLL:EXT:lang/locallang_core.xlf:labels.days') . ')';
				}
			}
			if ($row[$ctrl['endtime']]) {
				$out .= ' - ' . $lang->sL('LLL:EXT:lang/locallang_core.xlf:labels.endtime') . ': ' . self::date($row[$ctrl['endtime']]) . ' (' . self::daysUntil($row[$ctrl['endtime']]) . ' ' . $lang->sL('LLL:EXT:lang/locallang_core.xlf:labels.days') . ')';
			}
		}
		return htmlspecialchars($out);
	}

	/**
	 * Returns the label of the first found entry in an "items" array from $GLOBALS['TCA'] (tablename = $table/fieldname = $col) where the value is $key
	 *
	 * @param string $table Table name, present in $GLOBALS['TCA']
	 * @param string $col Field name, present in $GLOBALS['TCA']
	 * @param string $key items-array value to match
	 * @return string Label for item entry
	 */
	static public function getLabelFromItemlist($table, $col, $key) {
		// Check, if there is an "items" array:
		if (is_array($GLOBALS['TCA'][$table]) && is_array($GLOBALS['TCA'][$table]['columns'][$col]) && is_array($GLOBALS['TCA'][$table]['columns'][$col]['config']['items'])) {
			// Traverse the items-array...
			foreach ($GLOBALS['TCA'][$table]['columns'][$col]['config']['items'] as $v) {
				// ... and return the first found label where the value was equal to $key
				if ((string)$v[1] === (string)$key) {
					return $v[0];
				}
			}
		}
		return '';
	}

	/**
	 * Return the label of a field by additionally checking TsConfig values
	 *
	 * @param int $pageId Page id
	 * @param string $table Table name
	 * @param string $column Field Name
	 * @param string $key item value
	 * @return string Label for item entry
	 */
	static public function getLabelFromItemListMerged($pageId, $table, $column, $key) {
		$pageTsConfig = static::getPagesTSconfig($pageId);
		$label = '';
		if (is_array($pageTsConfig['TCEFORM.']) && is_array($pageTsConfig['TCEFORM.'][$table . '.']) && is_array($pageTsConfig['TCEFORM.'][$table . '.'][$column . '.'])) {
			if (is_array($pageTsConfig['TCEFORM.'][$table . '.'][$column . '.']['addItems.']) && isset($pageTsConfig['TCEFORM.'][$table . '.'][$column . '.']['addItems.'][$key])) {
				$label = $pageTsConfig['TCEFORM.'][$table . '.'][$column . '.']['addItems.'][$key];
			} elseif (is_array($pageTsConfig['TCEFORM.'][$table . '.'][$column . '.']['altLabels.']) && isset($pageTsConfig['TCEFORM.'][$table . '.'][$column . '.']['altLabels.'][$key])) {
				$label = $pageTsConfig['TCEFORM.'][$table . '.'][$column . '.']['altLabels.'][$key];
			}
		}
		if (empty($label)) {
			$tcaValue = self::getLabelFromItemlist($table, $column, $key);
			if (!empty($tcaValue)) {
				$label = $tcaValue;
			}
		}
		return $label;
	}

	/**
	 * Splits the given key with commas and returns the list of all the localized items labels, separated by a comma.
	 * NOTE: this does not take itemsProcFunc into account
	 *
	 * @param string $table Table name, present in TCA
	 * @param string $column Field name
	 * @param string $key Key or comma-separated list of keys.
	 * @return string Comma-separated list of localized labels
	 */
	static public function getLabelsFromItemsList($table, $column, $key) {
		$labels = array();
		$values = GeneralUtility::trimExplode(',', $key, TRUE);
		if (count($values) > 0) {
			// Check if there is an "items" array
			if (is_array($GLOBALS['TCA'][$table]) && is_array($GLOBALS['TCA'][$table]['columns'][$column]) && is_array($GLOBALS['TCA'][$table]['columns'][$column]['config']['items'])) {
				// Loop on all selected values
				foreach ($values as $aValue) {
					foreach ($GLOBALS['TCA'][$table]['columns'][$column]['config']['items'] as $itemConfiguration) {
						// Loop on all available items
						// Keep matches and move on to next value
						if ($aValue == $itemConfiguration[1]) {
							$labels[] = static::getLanguageService()->sL($itemConfiguration[0]);
							break;
						}
					}
				}
			}
		}
		return implode(', ', $labels);
	}

	/**
	 * Returns the label-value for fieldname $col in table, $table
	 * If $printAllWrap is set (to a "wrap") then it's wrapped around the $col value IF THE COLUMN $col DID NOT EXIST in TCA!, eg. $printAllWrap = '<strong>|</strong>' and the fieldname was 'not_found_field' then the return value would be '<strong>not_found_field</strong>'
	 *
	 * @param string $table Table name, present in $GLOBALS['TCA']
	 * @param string $col Field name
	 * @param string $printAllWrap Wrap value - set function description - this parameter is deprecated since TYPO3 6.2 and is removed two versions later. This parameter is a conceptual failure, as the content can then never be HSCed afterwards (which is how the method is used all the time), and then the code would be HSCed twice.
	 * @return string or NULL if $col is not found in the TCA table
	 */
	static public function getItemLabel($table, $col, $printAllWrap = '') {
		// Check if column exists
		if (is_array($GLOBALS['TCA'][$table]) && is_array($GLOBALS['TCA'][$table]['columns'][$col])) {
			return $GLOBALS['TCA'][$table]['columns'][$col]['label'];
		}
		if ($printAllWrap) {
			GeneralUtility::deprecationLog('The third parameter of getItemLabel() is deprecated with TYPO3 CMS 6.2 and will be removed two versions later.');
			$parts = explode('|', $printAllWrap);
			return $parts[0] . $col . $parts[1];
		}

		return NULL;
	}

	/**
	 * Replace field values in given row with values from the original language
	 * if l10n_mode TCA settings require to do so.
	 *
	 * @param string $table Table name
	 * @param array $row Row to fill with original language values
	 * @return array Row with values from the original language
	 */
	static protected function replaceL10nModeFields($table, array $row) {
		$originalUidField = isset($GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'])
			? $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField']
			: '';
		if (empty($row[$originalUidField])) {
			return $row;
		}

		$originalTable = self::getOriginalTranslationTable($table);
		$originalRow = self::getRecord($originalTable, $row[$originalUidField]);
		foreach ($row as $field => $_) {
			$l10n_mode = isset($GLOBALS['TCA'][$originalTable]['columns'][$field]['l10n_mode'])
				? $GLOBALS['TCA'][$originalTable]['columns'][$field]['l10n_mode']
				: '';
			if ($l10n_mode === 'exclude' || ($l10n_mode === 'mergeIfNotBlank' && trim($originalRow[$field]) !== '')) {
				$row[$field] = $originalRow[$field];
			}
		}
		return $row;
	}

	/**
	 * Returns the "title"-value in record, $row, from table, $table
	 * The field(s) from which the value is taken is determined by the "ctrl"-entries 'label', 'label_alt' and 'label_alt_force'
	 *
	 * @param string $table Table name, present in TCA
	 * @param array $row Row from table
	 * @param bool $prep If set, result is prepared for output: The output is cropped to a limited length (depending on BE_USER->uc['titleLen']) and if no value is found for the title, '<em>[No title]</em>' is returned (localized). Further, the output is htmlspecialchars()'ed
	 * @param bool $forceResult If set, the function always returns an output. If no value is found for the title, '[No title]' is returned (localized).
	 * @return string
	 */
	static public function getRecordTitle($table, $row, $prep = FALSE, $forceResult = TRUE) {
		if (is_array($GLOBALS['TCA'][$table])) {
			// If configured, call userFunc
			if ($GLOBALS['TCA'][$table]['ctrl']['label_userFunc']) {
				$params['table'] = $table;
				$params['row'] = $row;
				$params['title'] = '';
				$params['options'] = isset($GLOBALS['TCA'][$table]['ctrl']['label_userFunc_options']) ? $GLOBALS['TCA'][$table]['ctrl']['label_userFunc_options'] : array();

				// Create NULL-reference
				$null = NULL;
				GeneralUtility::callUserFunction($GLOBALS['TCA'][$table]['ctrl']['label_userFunc'], $params, $null);
				$t = $params['title'];
			} else {
				if (is_array($row)) {
					$row = self::replaceL10nModeFields($table, $row);
				}

				// No userFunc: Build label
				$t = self::getProcessedValue($table, $GLOBALS['TCA'][$table]['ctrl']['label'], $row[$GLOBALS['TCA'][$table]['ctrl']['label']], 0, 0, FALSE, $row['uid'], $forceResult);
				if ($GLOBALS['TCA'][$table]['ctrl']['label_alt'] && ($GLOBALS['TCA'][$table]['ctrl']['label_alt_force'] || (string)$t === '')) {
					$altFields = GeneralUtility::trimExplode(',', $GLOBALS['TCA'][$table]['ctrl']['label_alt'], TRUE);
					$tA = array();
					if (!empty($t)) {
						$tA[] = $t;
					}
					foreach ($altFields as $fN) {
						$t = trim(strip_tags($row[$fN]));
						if ((string)$t !== '') {
							$t = self::getProcessedValue($table, $fN, $t, 0, 0, FALSE, $row['uid']);
							if (!$GLOBALS['TCA'][$table]['ctrl']['label_alt_force']) {
								break;
							}
							$tA[] = $t;
						}
					}
					if ($GLOBALS['TCA'][$table]['ctrl']['label_alt_force']) {
						$t = implode(', ', $tA);
					}
				}
			}
			// If the current result is empty, set it to '[No title]' (localized) and prepare for output if requested
			if ($prep || $forceResult) {
				if ($prep) {
					$t = self::getRecordTitlePrep($t);
				}
				if (trim($t) === '') {
					$t = self::getNoRecordTitle($prep);
				}
			}
			return $t;
		}
		return '';
	}

	/**
	 * Crops a title string to a limited length and if it really was cropped, wrap it in a <span title="...">|</span>,
	 * which offers a tooltip with the original title when moving mouse over it.
	 *
	 * @param string $title The title string to be cropped
	 * @param int $titleLength Crop title after this length - if not set, BE_USER->uc['titleLen'] is used
	 * @return string The processed title string, wrapped in <span title="...">|</span> if cropped
	 */
	static public function getRecordTitlePrep($title, $titleLength = 0) {
		// If $titleLength is not a valid positive integer, use BE_USER->uc['titleLen']:
		if (!$titleLength || !MathUtility::canBeInterpretedAsInteger($titleLength) || $titleLength < 0) {
			$titleLength = static::getBackendUserAuthentication()->uc['titleLen'];
		}
		$titleOrig = htmlspecialchars($title);
		$title = htmlspecialchars(GeneralUtility::fixed_lgd_cs($title, $titleLength));
		// If title was cropped, offer a tooltip:
		if ($titleOrig != $title) {
			$title = '<span title="' . $titleOrig . '">' . $title . '</span>';
		}
		return $title;
	}

	/**
	 * Get a localized [No title] string, wrapped in <em>|</em> if $prep is TRUE.
	 *
	 * @param bool $prep Wrap result in <em>|</em>
	 * @return string Localized [No title] string
	 */
	static public function getNoRecordTitle($prep = FALSE) {
		$noTitle = '[' . static::getLanguageService()->sL('LLL:EXT:lang/locallang_core.xlf:labels.no_title', TRUE) . ']';
		if ($prep) {
			$noTitle = '<em>' . $noTitle . '</em>';
		}
		return $noTitle;
	}

	/**
	 * Returns a human readable output of a value from a record
	 * For instance a database record relation would be looked up to display the title-value of that record. A checkbox with a "1" value would be "Yes", etc.
	 * $table/$col is tablename and fieldname
	 * REMEMBER to pass the output through htmlspecialchars() if you output it to the browser! (To protect it from XSS attacks and be XHTML compliant)
	 *
	 * @param string $table Table name, present in TCA
	 * @param string $col Field name, present in TCA
	 * @param string $value The value of that field from a selected record
	 * @param int $fixed_lgd_chars The max amount of characters the value may occupy
	 * @param bool $defaultPassthrough Flag means that values for columns that has no conversion will just be pass through directly (otherwise cropped to 200 chars or returned as "N/A")
	 * @param bool $noRecordLookup If set, no records will be looked up, UIDs are just shown.
	 * @param int $uid Uid of the current record
	 * @param bool $forceResult If BackendUtility::getRecordTitle is used to process the value, this parameter is forwarded.
	 * @return string|NULL
	 */
	static public function getProcessedValue($table, $col, $value, $fixed_lgd_chars = 0, $defaultPassthrough = FALSE, $noRecordLookup = FALSE, $uid = 0, $forceResult = TRUE) {
		if ($col === 'uid') {
			// uid is not in TCA-array
			return $value;
		}
		// Check if table and field is configured
		if (!is_array($GLOBALS['TCA'][$table]) || !is_array($GLOBALS['TCA'][$table]['columns'][$col])) {
			return NULL;
		}
		// Depending on the fields configuration, make a meaningful output value.
		$theColConf = $GLOBALS['TCA'][$table]['columns'][$col]['config'];
		/*****************
		 *HOOK: pre-processing the human readable output from a record
		 ****************/
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_befunc.php']['preProcessValue'])) {
			// Create NULL-reference
			$null = NULL;
			foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_befunc.php']['preProcessValue'] as $_funcRef) {
				GeneralUtility::callUserFunction($_funcRef, $theColConf, $null);
			}
		}
		$l = '';
		$db = static::getDatabaseConnection();
		$lang = static::getLanguageService();
		switch ((string)$theColConf['type']) {
			case 'radio':
				$l = self::getLabelFromItemlist($table, $col, $value);
				$l = $lang->sL($l);
				break;
			case 'inline':
			case 'select':
				if ($theColConf['MM']) {
					if ($uid) {
						// Display the title of MM related records in lists
						if ($noRecordLookup) {
							$MMfield = $theColConf['foreign_table'] . '.uid';
						} else {
							$MMfields = array($theColConf['foreign_table'] . '.' . $GLOBALS['TCA'][$theColConf['foreign_table']]['ctrl']['label']);
							foreach (GeneralUtility::trimExplode(',', $GLOBALS['TCA'][$theColConf['foreign_table']]['ctrl']['label_alt'], TRUE) as $f) {
								$MMfields[] = $theColConf['foreign_table'] . '.' . $f;
							}
							$MMfield = join(',', $MMfields);
						}
						/** @var $dbGroup \TYPO3\CMS\Core\Database\RelationHandler */
						$dbGroup = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\RelationHandler::class);
						$dbGroup->start($value, $theColConf['foreign_table'], $theColConf['MM'], $uid, $table, $theColConf);
						$selectUids = $dbGroup->tableArray[$theColConf['foreign_table']];
						if (is_array($selectUids) && count($selectUids) > 0) {
							$MMres = $db->exec_SELECTquery('uid, ' . $MMfield, $theColConf['foreign_table'], 'uid IN (' . implode(',', $selectUids) . ')' . self::deleteClause($theColConf['foreign_table']));
							$mmlA = array();
							while ($MMrow = $db->sql_fetch_assoc($MMres)) {
								// Keep sorting of $selectUids
								$mmlA[array_search($MMrow['uid'], $selectUids)] = $noRecordLookup ?
									$MMrow['uid'] :
									self::getRecordTitle($theColConf['foreign_table'], $MMrow, FALSE, $forceResult);
							}
							$db->sql_free_result($MMres);
							if (!empty($mmlA)) {
								ksort($mmlA);
								$l = implode('; ', $mmlA);
							} else {
								$l = 'N/A';
							}
						} else {
							$l = 'N/A';
						}
					} else {
						$l = 'N/A';
					}
				} else {
					$l = self::getLabelsFromItemsList($table, $col, $value);
					if ($theColConf['foreign_table'] && !$l && $GLOBALS['TCA'][$theColConf['foreign_table']]) {
						if ($noRecordLookup) {
							$l = $value;
						} else {
							$rParts = array();
							if ($uid && isset($theColConf['foreign_field']) && $theColConf['foreign_field'] !== '') {
								$whereClause = '';
								// Add additional where clause if foreign_match_fields are defined
								$foreignMatchFields = is_array($theColConf['foreign_match_fields']) ? $theColConf['foreign_match_fields'] : array();
								foreach ($foreignMatchFields as $matchField => $matchValue) {
									$whereClause .= ' AND ' . $matchField . '=' . static::getDatabaseConnection()->fullQuoteStr($matchValue, $theColConf['foreign_table']);
								}
								$records = self::getRecordsByField($theColConf['foreign_table'], $theColConf['foreign_field'], $uid, $whereClause);
								if (!empty($records)) {
									foreach ($records as $record) {
										$rParts[] = $record['uid'];
									}
								}
							}
							if (empty($rParts)) {
								$rParts = GeneralUtility::trimExplode(',', $value, TRUE);
							}
							$lA = array();
							foreach ($rParts as $rVal) {
								$rVal = (int)$rVal;
								if ($rVal > 0) {
									$r = self::getRecordWSOL($theColConf['foreign_table'], $rVal);
								} else {
									$r = self::getRecordWSOL($theColConf['neg_foreign_table'], -$rVal);
								}
								if (is_array($r)) {
									$lA[] = $lang->sL(($rVal > 0 ? $theColConf['foreign_table_prefix'] : $theColConf['neg_foreign_table_prefix'])) . self::getRecordTitle(($rVal > 0 ? $theColConf['foreign_table'] : $theColConf['neg_foreign_table']), $r, FALSE, $forceResult);
								} else {
									$lA[] = $rVal ? '[' . $rVal . '!]' : '';
								}
							}
							$l = implode(', ', $lA);
						}
					}
					if (empty($l) && !empty($value)) {
						// Use plain database value when label is empty
						$l = $value;
					}
				}
				break;
			case 'group':
				// resolve the titles for DB records
				if ($theColConf['internal_type'] === 'db') {
					$finalValues = array();
					$relationTableName = $theColConf['allowed'];
					$explodedValues = GeneralUtility::trimExplode(',', $value, TRUE);

					foreach ($explodedValues as $explodedValue) {

						if (MathUtility::canBeInterpretedAsInteger($explodedValue)) {
							$relationTableNameForField = $relationTableName;
						} else {
							list($relationTableNameForField, $explodedValue) = self::splitTable_Uid($explodedValue);
						}

						$relationRecord = static::getRecordWSOL($relationTableNameForField, $explodedValue);
						$finalValues[] = static::getRecordTitle($relationTableNameForField, $relationRecord);
					}

					$l = implode(', ', $finalValues);
				} else {
					$l = implode(', ', GeneralUtility::trimExplode(',', $value, TRUE));
				}
				break;
			case 'check':
				if (!is_array($theColConf['items']) || count($theColConf['items']) == 1) {
					$l = $value ? $lang->sL('LLL:EXT:lang/locallang_common.xlf:yes') : $lang->sL('LLL:EXT:lang/locallang_common.xlf:no');
				} else {
					$lA = array();
					foreach ($theColConf['items'] as $key => $val) {
						if ($value & pow(2, $key)) {
							$lA[] = $lang->sL($val[0]);
						}
					}
					$l = implode(', ', $lA);
				}
				break;
			case 'input':
				// Hide value 0 for dates, but show it for everything else
				if (isset($value)) {
					if (GeneralUtility::inList($theColConf['eval'], 'date')) {
						// Handle native date field
						if (isset($theColConf['dbType']) && $theColConf['dbType'] === 'date') {
							$dateTimeFormats = $db->getDateTimeFormats($table);
							$emptyValue = $dateTimeFormats['date']['empty'];
							$value = $value !== $emptyValue ? strtotime($value) : 0;
						}
						if (!empty($value)) {
							$l = self::date($value) . ' (' . ($GLOBALS['EXEC_TIME'] - $value > 0 ? '-' : '') . self::calcAge(abs(($GLOBALS['EXEC_TIME'] - $value)), $lang->sL('LLL:EXT:lang/locallang_core.xlf:labels.minutesHoursDaysYears')) . ')';
						}
					} elseif (GeneralUtility::inList($theColConf['eval'], 'time')) {
						if (!empty($value)) {
							$l = self::time($value, FALSE);
						}
					} elseif (GeneralUtility::inList($theColConf['eval'], 'timesec')) {
						if (!empty($value)) {
							$l = self::time($value);
						}
					} elseif (GeneralUtility::inList($theColConf['eval'], 'datetime')) {
						// Handle native date/time field
						if (isset($theColConf['dbType']) && $theColConf['dbType'] === 'datetime') {
							$dateTimeFormats = $db->getDateTimeFormats($table);
							$emptyValue = $dateTimeFormats['datetime']['empty'];
							$value = $value !== $emptyValue ? strtotime($value) : 0;
						}
						if (!empty($value)) {
							$l = self::datetime($value);
						}
					} else {
						$l = $value;
					}
				}
				break;
			case 'flex':
				$l = strip_tags($value);
				break;
			default:
				if ($defaultPassthrough) {
					$l = $value;
				} elseif ($theColConf['MM']) {
					$l = 'N/A';
				} elseif ($value) {
					$l = GeneralUtility::fixed_lgd_cs(strip_tags($value), 200);
				}
		}
		// If this field is a password field, then hide the password by changing it to a random number of asterisk (*)
		if (stristr($theColConf['eval'], 'password')) {
			$l = '';
			$randomNumber = rand(5, 12);
			for ($i = 0; $i < $randomNumber; $i++) {
				$l .= '*';
			}
		}
		/*****************
		 *HOOK: post-processing the human readable output from a record
		 ****************/
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_befunc.php']['postProcessValue'])) {
			// Create NULL-reference
			$null = NULL;
			foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_befunc.php']['postProcessValue'] as $_funcRef) {
				$params = array(
					'value' => $l,
					'colConf' => $theColConf
				);
				$l = GeneralUtility::callUserFunction($_funcRef, $params, $null);
			}
		}
		if ($fixed_lgd_chars) {
			return GeneralUtility::fixed_lgd_cs($l, $fixed_lgd_chars);
		} else {
			return $l;
		}
	}

	/**
	 * Same as ->getProcessedValue() but will go easy on fields like "tstamp" and "pid" which are not configured in TCA - they will be formatted by this function instead.
	 *
	 * @param string $table Table name, present in TCA
	 * @param string $fN Field name
	 * @param string $fV Field value
	 * @param int $fixed_lgd_chars The max amount of characters the value may occupy
	 * @param int $uid Uid of the current record
	 * @param bool $forceResult If BackendUtility::getRecordTitle is used to process the value, this parameter is forwarded.
	 * @return string
	 * @see getProcessedValue()
	 */
	static public function getProcessedValueExtra($table, $fN, $fV, $fixed_lgd_chars = 0, $uid = 0, $forceResult = TRUE) {
		$fVnew = self::getProcessedValue($table, $fN, $fV, $fixed_lgd_chars, 1, 0, $uid, $forceResult);
		if (!isset($fVnew)) {
			if (is_array($GLOBALS['TCA'][$table])) {
				if ($fN == $GLOBALS['TCA'][$table]['ctrl']['tstamp'] || $fN == $GLOBALS['TCA'][$table]['ctrl']['crdate']) {
					$fVnew = self::datetime($fV);
				} elseif ($fN == 'pid') {
					// Fetches the path with no regard to the users permissions to select pages.
					$fVnew = self::getRecordPath($fV, '1=1', 20);
				} else {
					$fVnew = $fV;
				}
			}
		}
		return $fVnew;
	}

	/**
	 * Returns file icon name (from $FILEICONS) for the fileextension $ext
	 *
	 * @param string $ext File extension, lowercase
	 * @return string File icon filename
	 */
	static public function getFileIcon($ext) {
		return $GLOBALS['FILEICONS'][$ext] ?: $GLOBALS['FILEICONS']['default'];
	}

	/**
	 * Returns fields for a table, $table, which would typically be interesting to select
	 * This includes uid, the fields defined for title, icon-field.
	 * Returned as a list ready for query ($prefix can be set to eg. "pages." if you are selecting from the pages table and want the table name prefixed)
	 *
	 * @param string $table Table name, present in $GLOBALS['TCA']
	 * @param string $prefix Table prefix
	 * @param array $fields Preset fields (must include prefix if that is used)
	 * @return string List of fields.
	 */
	static public function getCommonSelectFields($table, $prefix = '', $fields = array()) {
		$fields[] = $prefix . 'uid';
		if (isset($GLOBALS['TCA'][$table]['ctrl']['label']) && $GLOBALS['TCA'][$table]['ctrl']['label'] != '') {
			$fields[] = $prefix . $GLOBALS['TCA'][$table]['ctrl']['label'];
		}
		if ($GLOBALS['TCA'][$table]['ctrl']['label_alt']) {
			$secondFields = GeneralUtility::trimExplode(',', $GLOBALS['TCA'][$table]['ctrl']['label_alt'], TRUE);
			foreach ($secondFields as $fieldN) {
				$fields[] = $prefix . $fieldN;
			}
		}
		if ($GLOBALS['TCA'][$table]['ctrl']['versioningWS']) {
			$fields[] = $prefix . 't3ver_id';
			$fields[] = $prefix . 't3ver_state';
			$fields[] = $prefix . 't3ver_wsid';
			$fields[] = $prefix . 't3ver_count';
		}
		if ($GLOBALS['TCA'][$table]['ctrl']['selicon_field']) {
			$fields[] = $prefix . $GLOBALS['TCA'][$table]['ctrl']['selicon_field'];
		}
		if ($GLOBALS['TCA'][$table]['ctrl']['typeicon_column']) {
			$fields[] = $prefix . $GLOBALS['TCA'][$table]['ctrl']['typeicon_column'];
		}
		if (is_array($GLOBALS['TCA'][$table]['ctrl']['enablecolumns'])) {
			if ($GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['disabled']) {
				$fields[] = $prefix . $GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['disabled'];
			}
			if ($GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['starttime']) {
				$fields[] = $prefix . $GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['starttime'];
			}
			if ($GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['endtime']) {
				$fields[] = $prefix . $GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['endtime'];
			}
			if ($GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['fe_group']) {
				$fields[] = $prefix . $GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['fe_group'];
			}
		}
		return implode(',', array_unique($fields));
	}

	/**
	 * Makes a form for configuration of some values based on configuration found in the array $configArray,
	 * with default values from $defaults and a data-prefix $dataPrefix
	 * <form>-tags must be supplied separately
	 * Needs more documentation and examples, in particular syntax for configuration array. See Inside TYPO3.
	 * That's were you can expect to find example, if anywhere.
	 *
	 * @param array $configArray Field configuration code.
	 * @param array $defaults Defaults
	 * @param string $dataPrefix Prefix for formfields
	 * @return string HTML for a form.
	 */
	static public function makeConfigForm($configArray, $defaults, $dataPrefix) {
		$params = $defaults;
		$lines = array();
		if (is_array($configArray)) {
			foreach ($configArray as $fname => $config) {
				if (is_array($config)) {
					$lines[$fname] = '<strong>' . htmlspecialchars($config[1]) . '</strong><br />';
					$lines[$fname] .= $config[2] . '<br />';
					switch ($config[0]) {
						case 'string':

						case 'short':
							$formEl = '<input type="text" name="' . $dataPrefix . '[' . $fname . ']" value="' . $params[$fname] . '"' . static::getDocumentTemplate()->formWidth(($config[0] == 'short' ? 24 : 48)) . ' />';
							break;
						case 'check':
							$formEl = '<input type="hidden" name="' . $dataPrefix . '[' . $fname . ']" value="0" /><input type="checkbox" name="' . $dataPrefix . '[' . $fname . ']" value="1"' . ($params[$fname] ? ' checked="checked"' : '') . ' />';
							break;
						case 'comment':
							$formEl = '';
							break;
						case 'select':
							$opt = array();
							foreach ($config[3] as $k => $v) {
								$opt[] = '<option value="' . htmlspecialchars($k) . '"' . ($params[$fname] == $k ? ' selected="selected"' : '') . '>' . htmlspecialchars($v) . '</option>';
							}
							$formEl = '<select name="' . $dataPrefix . '[' . $fname . ']">' . implode('', $opt) . '</select>';
							break;
						default:
							$formEl = '<strong>Should not happen. Bug in config.</strong>';
					}
					$lines[$fname] .= $formEl;
					$lines[$fname] .= '<br /><br />';
				} else {
					$lines[$fname] = '<hr />';
					if ($config) {
						$lines[$fname] .= '<strong>' . strtoupper(htmlspecialchars($config)) . '</strong><br />';
					}
					if ($config) {
						$lines[$fname] .= '<br />';
					}
				}
			}
		}
		$out = implode('', $lines);
		$out .= '<input class="btn btn-default" type="submit" name="submit" value="Update configuration" />';
		return $out;
	}

	/*******************************************
	 *
	 * Backend Modules API functions
	 *
	 *******************************************/
	/**
	 * Returns help-text icon if configured for.
	 * TCA_DESCR must be loaded prior to this function and static::getBackendUserAuthentication() must
	 * have 'edit_showFieldHelp' set to 'icon', otherwise nothing is returned
	 *
	 * Please note: since TYPO3 4.5 the UX team decided to not use CSH in its former way,
	 * but to wrap the given text (where before the help icon was, and you could hover over it)
	 * Please also note that since TYPO3 4.5 the option to enable help (none, icon only, full text)
	 * was completely removed.
	 *
	 * @param string $table Table name
	 * @param string $field Field name
	 * @param string $BACK_PATH UNUSED
	 * @param bool $force Force display of icon no matter BE_USER setting for help
	 * @return string HTML content for a help icon/text
	 * @deprecated since TYPO3 CMS 7, will be removed in TYPO3 CMS 8, use cshItem() instead
	 */
	static public function helpTextIcon($table, $field, $BACK_PATH = '', $force = FALSE) {
		GeneralUtility::logDeprecatedFunction();
		if (
			is_array($GLOBALS['TCA_DESCR'][$table]) && is_array($GLOBALS['TCA_DESCR'][$table]['columns'][$field])
			&& (isset(static::getBackendUserAuthentication()->uc['edit_showFieldHelp']) || $force)
		) {
			return self::wrapInHelp($table, $field);
		}
		return '';
	}

	/**
	 * Returns CSH help text (description), if configured for, as an array (title, description)
	 *
	 * @param string $table Table name
	 * @param string $field Field name
	 * @return array With keys 'description' (raw, as available in locallang), 'title' (optional), 'moreInfo'
	 */
	static public function helpTextArray($table, $field) {
		if (!isset($GLOBALS['TCA_DESCR'][$table]['columns'])) {
			static::getLanguageService()->loadSingleTableDescription($table);
		}
		$output = array(
			'description' => NULL,
			'title' => NULL,
			'moreInfo' => FALSE
		);
		if (is_array($GLOBALS['TCA_DESCR'][$table]) && is_array($GLOBALS['TCA_DESCR'][$table]['columns'][$field])) {
			$data = $GLOBALS['TCA_DESCR'][$table]['columns'][$field];
			// Add alternative title, if defined
			if ($data['alttitle']) {
				$output['title'] = $data['alttitle'];
			}
			// If we have more information to show
			if ($data['image_descr'] || $data['seeAlso'] || $data['details'] || $data['syntax']) {
				$output['moreInfo'] = TRUE;
			}
			// Add description
			if ($data['description']) {
				$output['description'] = $data['description'];
			}
		}
		return $output;
	}

	/**
	 * Returns CSH help text
	 *
	 * @param string $table Table name
	 * @param string $field Field name
	 * @return string HTML content for help text
	 * @see cshItem()
	 */
	static public function helpText($table, $field) {
		$helpTextArray = self::helpTextArray($table, $field);
		$output = '';
		$arrow = '';
		// Put header before the rest of the text
		if ($helpTextArray['title'] !== NULL) {
			$output .= '<h2 class="t3-row-header">' . $helpTextArray['title'] . '</h2>';
		}
		// Add see also arrow if we have more info
		if ($helpTextArray['moreInfo']) {
			$arrow = IconUtility::getSpriteIcon('actions-view-go-forward');
		}
		// Wrap description and arrow in p tag
		if ($helpTextArray['description'] !== NULL || $arrow) {
			$output .= '<p class="t3-help-short">' . nl2br(htmlspecialchars($helpTextArray['description'])) . $arrow . '</p>';
		}
		return $output;
	}

	/**
	 * API function that wraps the text / html in help text, so if a user hovers over it
	 * the help text will show up
	 * This is the new help API function since TYPO3 4.5, and uses the new behaviour
	 * (hover over text, no icon, no fulltext option, no option to disable the help)
	 *
	 * @param string $table The table name for which the help should be shown
	 * @param string $field The field name for which the help should be shown
	 * @param string $text The text which should be wrapped with the help text
	 * @param array $overloadHelpText Array with text to overload help text
	 * @return string the HTML code ready to render
	 */
	static public function wrapInHelp($table, $field, $text = '', array $overloadHelpText = array()) {
		// Initialize some variables
		$helpText = '';
		$abbrClassAdd = '';
		$wrappedText = $text;
		$hasHelpTextOverload = count($overloadHelpText) > 0;
		// Get the help text that should be shown on hover
		if (!$hasHelpTextOverload) {
			$helpText = self::helpText($table, $field);
		}
		// If there's a help text or some overload information, proceed with preparing an output
		// @todo: right now this is a hard dependency on csh manual, as the whole help system should be moved to
		// the extension. The core provides a API for adding help, and rendering help, but the rendering
		// should be up to the extension itself
		if ((!empty($helpText) || $hasHelpTextOverload) && ExtensionManagementUtility::isLoaded('cshmanual')) {
			// If no text was given, just use the regular help icon
			if ($text == '') {
				$text = IconUtility::getSpriteIcon('actions-system-help-open');
				$abbrClassAdd = '-icon';
			}
			$text = '<abbr class="t3-help-teaser' . $abbrClassAdd . '">' . $text . '</abbr>';
			$wrappedText = '<span class="t3-help-link" href="#" data-table="' . $table . '" data-field="' . $field . '"';
			// The overload array may provide a title and a description
			// If either one is defined, add them to the "data" attributes
			if ($hasHelpTextOverload) {
				if (isset($overloadHelpText['title'])) {
					$wrappedText .= ' data-title="' . htmlspecialchars($overloadHelpText['title']) . '"';
				}
				if (isset($overloadHelpText['description'])) {
					$wrappedText .= ' data-description="' . htmlspecialchars($overloadHelpText['description']) . '"';
				}
			}
		} else {
			$wrappedText = '<span data-table="' . $table . '" data-field="' . $field . '"';
		}
		$wrappedText .= '>' . $text . '</span>';
		return $wrappedText;
	}

	/**
	 * API for getting CSH icons/text for use in backend modules.
	 * TCA_DESCR will be loaded if it isn't already
	 *
	 * @param string $table Table name ('_MOD_'+module name)
	 * @param string $field Field name (CSH locallang main key)
	 * @param string $BACK_PATH Back path, not needed anymore, don't use
	 * @param string $wrap Wrap code for icon-mode, splitted by "|". Not used for full-text mode.
	 * @return string HTML content for help text
	 * @see helpTextIcon()
	 */
	static public function cshItem($table, $field, $BACK_PATH = NULL, $wrap = '') {
		static::getLanguageService()->loadSingleTableDescription($table);
		if (is_array($GLOBALS['TCA_DESCR'][$table])
			&& is_array($GLOBALS['TCA_DESCR'][$table]['columns'][$field])) {
			// Creating short description
			$output = self::wrapInHelp($table, $field);
			if ($output && $wrap) {
				$wrParts = explode('|', $wrap);
				$output = $wrParts[0] . $output . $wrParts[1];
			}
			return $output;
		}
		return '';
	}

	/**
	 * Returns a JavaScript string (for an onClick handler) which will load the alt_doc.php script that shows the form for editing of the record(s) you have send as params.
	 * REMEMBER to always htmlspecialchar() content in href-properties to ampersands get converted to entities (XHTML requirement and XSS precaution)
	 *
	 * @param string $params Parameters sent along to alt_doc.php. This requires a much more details description which you must seek in Inside TYPO3s documentation of the alt_doc.php API. And example could be '&edit[pages][123] = edit' which will show edit form for page record 123.
	 * @param string $backPath Must point back to the TYPO3_mainDir directory (where alt_doc.php is)
	 * @param string $requestUri An optional returnUrl you can set - automatically set to REQUEST_URI.
	 *
	 * @return string
	 * @see \TYPO3\CMS\Backend\Template\DocumentTemplate::issueCommand()
	 */
	static public function editOnClick($params, $backPath = '', $requestUri = '') {
		$returnUrl = $requestUri == -1
			? '\'+T3_THIS_LOCATION+\''
			: rawurlencode($requestUri ?: GeneralUtility::getIndpEnv('REQUEST_URI'));
		$retUrlParam = 'returnUrl=' . $returnUrl;
		return 'window.location.href=\'' . $backPath . 'alt_doc.php?' . $retUrlParam . $params . '\'; return false;';
	}

	/**
	 * Returns a JavaScript string for viewing the page id, $id
	 * It will detect the correct domain name if needed and provide the link with the right back path.
	 * Also it will re-use any window already open.
	 *
	 * @param int $pageUid Page UID
	 * @param string $backPath Must point back to TYPO3_mainDir (where the site is assumed to be one level above)
	 * @param array|NULL $rootLine If root line is supplied the function will look for the first found domain record and use that URL instead (if found)
	 * @param string $anchorSection Optional anchor to the URL
	 * @param string $alternativeUrl An alternative URL that, if set, will ignore other parameters except $switchFocus: It will return the window.open command wrapped around this URL!
	 * @param string $additionalGetVars Additional GET variables.
	 * @param bool $switchFocus If TRUE, then the preview window will gain the focus.
	 * @return string
	 */
	static public function viewOnClick($pageUid, $backPath = '', $rootLine = NULL, $anchorSection = '', $alternativeUrl = '', $additionalGetVars = '', $switchFocus = TRUE) {
		$viewScript = '/index.php?id=';
		if ($alternativeUrl) {
			$viewScript = $alternativeUrl;
		}

		if (
			isset($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_befunc.php']['viewOnClickClass'])
			&& is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_befunc.php']['viewOnClickClass'])
		) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_befunc.php']['viewOnClickClass'] as $funcRef) {
				$hookObj = GeneralUtility::getUserObj($funcRef);
				if (method_exists($hookObj, 'preProcess')) {
					$hookObj->preProcess($pageUid, $backPath, $rootLine, $anchorSection, $viewScript, $additionalGetVars, $switchFocus);
				}
			}
		}

		if ($alternativeUrl) {
			$previewUrl = $viewScript;
		} else {
			$previewUrl = self::createPreviewUrl($pageUid, $rootLine, $anchorSection, $additionalGetVars, $viewScript);
		}

		$onclickCode = 'var previewWin = window.open(\'' . $previewUrl . '\',\'newTYPO3frontendWindow\');' . ($switchFocus ? 'previewWin.focus();' : '');
		return $onclickCode;
	}

	/**
	 * Creates the view-on-click preview URL without any alternative URL.
	 *
	 * @param int $pageUid Page UID
	 * @param array $rootLine If rootline is supplied, the function will look for the first found domain record and use that URL instead
	 * @param string $anchorSection Optional anchor to the URL
	 * @param string $additionalGetVars Additional GET variables.
	 * @param string $viewScript The path to the script used to view the page
	 *
	 * @return string The preview URL
	 */
	static protected function createPreviewUrl($pageUid, $rootLine, $anchorSection, $additionalGetVars, $viewScript) {
		// Look if a fixed preview language should be added:
		$beUser = static::getBackendUserAuthentication();
		$viewLanguageOrder = $beUser->getTSConfigVal('options.view.languageOrder');

		if ((string)$viewLanguageOrder !== '') {
			$suffix = '';
			// Find allowed languages (if none, all are allowed!)
			$allowedLanguages = NULL;
			if (!$beUser->user['admin'] && $beUser->groupData['allowed_languages'] !== '') {
				$allowedLanguages = array_flip(explode(',', $beUser->groupData['allowed_languages']));
			}
			// Traverse the view order, match first occurrence:
			$languageOrder = GeneralUtility::intExplode(',', $viewLanguageOrder);
			foreach ($languageOrder as $langUid) {
				if (is_array($allowedLanguages) && count($allowedLanguages)) {
					// Choose if set.
					if (isset($allowedLanguages[$langUid])) {
						$suffix = '&L=' . $langUid;
						break;
					}
				} else {
					// All allowed since no lang. are listed.
					$suffix = '&L=' . $langUid;
					break;
				}
			}
			// Add it
			$additionalGetVars .= $suffix;
		}

		// Check a mount point needs to be previewed
		$sys_page = GeneralUtility::makeInstance(\TYPO3\CMS\Frontend\Page\PageRepository::class);
		$sys_page->init(FALSE);
		$mountPointInfo = $sys_page->getMountPointInfo($pageUid);

		if ($mountPointInfo && $mountPointInfo['overlay']) {
			$pageUid = $mountPointInfo['mount_pid'];
			$additionalGetVars .= '&MP=' . $mountPointInfo['MPvar'];
		}
		$viewDomain = self::getViewDomain($pageUid, $rootLine);

		return $viewDomain . $viewScript . $pageUid . $additionalGetVars . $anchorSection;
	}

	/**
	 * Builds the frontend view domain for a given page ID with a given root
	 * line.
	 *
	 * @param int $pageId The page ID to use, must be > 0
	 * @param array|NULL $rootLine The root line structure to use
	 * @return string The full domain including the protocol http:// or https://, but without the trailing '/'
	 * @author Michael Klapper <michael.klapper@aoemedia.de>
	 */
	static public function getViewDomain($pageId, $rootLine = NULL) {
		$domain = rtrim(GeneralUtility::getIndpEnv('TYPO3_SITE_URL'), '/');
		if (!is_array($rootLine)) {
			$rootLine = self::BEgetRootLine($pageId);
		}
		// Checks alternate domains
		if (count($rootLine) > 0) {
			$urlParts = parse_url($domain);
			/** @var PageRepository $sysPage */
			$sysPage = GeneralUtility::makeInstance(\TYPO3\CMS\Frontend\Page\PageRepository::class);
			$page = (array)$sysPage->getPage($pageId);
			$protocol = 'http';
			if ($page['url_scheme'] == HttpUtility::SCHEME_HTTPS || $page['url_scheme'] == 0 && GeneralUtility::getIndpEnv('TYPO3_SSL')) {
				$protocol = 'https';
			}
			$previewDomainConfig = static::getBackendUserAuthentication()->getTSConfig('TCEMAIN.previewDomain', self::getPagesTSconfig($pageId));
			if ($previewDomainConfig['value']) {
				$domainName = $previewDomainConfig['value'];
			} else {
				$domainName = self::firstDomainRecord($rootLine);
			}
			if ($domainName) {
				$domain = $domainName;
			} else {
				$domainRecord = self::getDomainStartPage($urlParts['host'], $urlParts['path']);
				$domain = $domainRecord['domainName'];
			}
			if ($domain) {
				$domain = $protocol . '://' . $domain;
			} else {
				$domain = rtrim(GeneralUtility::getIndpEnv('TYPO3_SITE_URL'), '/');
			}
			// Append port number if lockSSLPort is not the standard port 443
			$portNumber = (int)$GLOBALS['TYPO3_CONF_VARS']['BE']['lockSSLPort'];
			if ($portNumber > 0 && $portNumber !== 443 && $portNumber < 65536 && $protocol === 'https') {
				$domain .= ':' . strval($portNumber);
			}
		}
		return $domain;
	}

	/**
	 * Returns the merged User/Page TSconfig for page id, $id.
	 * Please read details about module programming elsewhere!
	 *
	 * @param int $id Page uid
	 * @param string $TSref An object string which determines the path of the TSconfig to return.
	 * @return array
	 */
	static public function getModTSconfig($id, $TSref) {
		$beUser = static::getBackendUserAuthentication();
		$pageTS_modOptions = $beUser->getTSConfig($TSref, static::getPagesTSconfig($id));
		$BE_USER_modOptions = $beUser->getTSConfig($TSref);
		if (is_null($BE_USER_modOptions['value'])) {
			unset($BE_USER_modOptions['value']);
		}
		ArrayUtility::mergeRecursiveWithOverrule($pageTS_modOptions, $BE_USER_modOptions);
		return $pageTS_modOptions;
	}

	/**
	 * Returns a selector box "function menu" for a module
	 * Requires the JS function jumpToUrl() to be available
	 * See Inside TYPO3 for details about how to use / make Function menus
	 *
	 * @param mixed $mainParams The "&id=" parameter value to be sent to the module, but it can be also a parameter array which will be passed instead of the &id=...
	 * @param string $elementName The form elements name, probably something like "SET[...]
	 * @param string $currentValue The value to be selected currently.
	 * @param array	 $menuItems An array with the menu items for the selector box
	 * @param string $script The script to send the &id to, if empty it's automatically found
	 * @param string $addParams Additional parameters to pass to the script.
	 * @return string HTML code for selector box
	 */
	static public function getFuncMenu($mainParams, $elementName, $currentValue, $menuItems, $script = '', $addParams = '') {
		if (!is_array($menuItems) || count($menuItems) <= 1) {
			return '';
		}
		$scriptUrl = self::buildScriptUrl($mainParams, $addParams, $script);
		$options = array();
		foreach ($menuItems as $value => $label) {
			$options[] = '<option value="' . htmlspecialchars($value) . '"' . ((string)$currentValue === (string)$value ? ' selected="selected"' : '') . '>' . htmlspecialchars($label, ENT_COMPAT, 'UTF-8', FALSE) . '</option>';
		}
		if (count($options)) {
			$onChange = 'jumpToUrl(' . GeneralUtility::quoteJSvalue($scriptUrl . '&' . $elementName . '=') . '+this.options[this.selectedIndex].value,this);';
			return '

				<!-- Function Menu of module -->
				<select name="' . $elementName . '" onchange="' . htmlspecialchars($onChange) . '">
					' . implode('
					', $options) . '
				</select>
						';
		}
		return '';
	}

	/**
	 * Checkbox function menu.
	 * Works like ->getFuncMenu() but takes no $menuItem array since this is a simple checkbox.
	 *
	 * @param mixed $mainParams $id is the "&id=" parameter value to be sent to the module, but it can be also a parameter array which will be passed instead of the &id=...
	 * @param string $elementName The form elements name, probably something like "SET[...]
	 * @param string $currentValue The value to be selected currently.
	 * @param string $script The script to send the &id to, if empty it's automatically found
	 * @param string $addParams Additional parameters to pass to the script.
	 * @param string $tagParams Additional attributes for the checkbox input tag
	 * @return string HTML code for checkbox
	 * @see getFuncMenu()
	 */
	static public function getFuncCheck($mainParams, $elementName, $currentValue, $script = '', $addParams = '', $tagParams = '') {
		$scriptUrl = self::buildScriptUrl($mainParams, $addParams, $script);
		$onClick = 'jumpToUrl(' . GeneralUtility::quoteJSvalue($scriptUrl . '&' . $elementName . '=') . '+(this.checked?1:0),this);';

		return
		'<input' .
			' type="checkbox"' .
			' class="checkbox"' .
			' name="' . $elementName . '"' .
			($currentValue ? ' checked="checked"' : '') .
			' onclick="' . htmlspecialchars($onClick) . '"' .
			($tagParams ? ' ' . $tagParams : '') .
			' value="1"' .
		' />';
	}

	/**
	 * Input field function menu
	 * Works like ->getFuncMenu() / ->getFuncCheck() but displays a input field instead which updates the script "onchange"
	 *
	 * @param mixed $mainParams $id is the "&id=" parameter value to be sent to the module, but it can be also a parameter array which will be passed instead of the &id=...
	 * @param string $elementName The form elements name, probably something like "SET[...]
	 * @param string $currentValue The value to be selected currently.
	 * @param int $size Relative size of input field, max is 48
	 * @param string $script The script to send the &id to, if empty it's automatically found
	 * @param string $addParams Additional parameters to pass to the script.
	 * @return string HTML code for input text field.
	 * @see getFuncMenu()
	 */
	static public function getFuncInput($mainParams, $elementName, $currentValue, $size = 10, $script = '', $addParams = '') {
		$scriptUrl = self::buildScriptUrl($mainParams, $addParams, $script);
		$onChange = 'jumpToUrl(' . GeneralUtility::quoteJSvalue($scriptUrl . '&' . $elementName . '=') . '+escape(this.value),this);';
		return '<input type="text"' . static::getDocumentTemplate()->formWidth($size) . ' name="' . $elementName . '" value="' . htmlspecialchars($currentValue) . '" onchange="' . htmlspecialchars($onChange) . '" />';
	}

	/**
	 * Builds the URL to the current script with given arguments
	 *
	 * @param mixed $mainParams $id is the "&id=" parameter value to be sent to the module, but it can be also a parameter array which will be passed instead of the &id=...
	 * @param string $addParams Additional parameters to pass to the script.
	 * @param string $script The script to send the &id to, if empty it's automatically found
	 * @return string The completes script URL
	 */
	protected static function buildScriptUrl($mainParams, $addParams, $script = '') {
		if (!is_array($mainParams)) {
			$mainParams = array('id' => $mainParams);
		}
		if (!$script) {
			$script = basename(PATH_thisScript);
		}
		if ($script === 'mod.php' && GeneralUtility::_GET('M')) {
			$scriptUrl = self::getModuleUrl(GeneralUtility::_GET('M'), $mainParams) . $addParams;
		} else {
			$scriptUrl = $script . '?' . GeneralUtility::implodeArrayForUrl('', $mainParams) . $addParams;
		}

		return $scriptUrl;
	}

	/**
	 * Removes menu items from $itemArray if they are configured to be removed by TSconfig for the module ($modTSconfig)
	 * See Inside TYPO3 about how to program modules and use this API.
	 *
	 * @param array $modTSconfig Module TS config array
	 * @param array $itemArray Array of items from which to remove items.
	 * @param string $TSref $TSref points to the "object string" in $modTSconfig
	 * @return array The modified $itemArray is returned.
	 */
	static public function unsetMenuItems($modTSconfig, $itemArray, $TSref) {
		// Getting TS-config options for this module for the Backend User:
		$conf = static::getBackendUserAuthentication()->getTSConfig($TSref, $modTSconfig);
		if (is_array($conf['properties'])) {
			foreach ($conf['properties'] as $key => $val) {
				if (!$val) {
					unset($itemArray[$key]);
				}
			}
		}
		return $itemArray;
	}

	/**
	 * Call to update the page tree frame (or something else..?) after
	 * use 'updatePageTree' as a first parameter will set the page tree to be updated.
	 *
	 * @param string $set Key to set the update signal. When setting, this value contains strings telling WHAT to set. At this point it seems that the value "updatePageTree" is the only one it makes sense to set. If empty, all update signals will be removed.
	 * @param mixed $params Additional information for the update signal, used to only refresh a branch of the tree
	 * @return void
	 * @see BackendUtility::getUpdateSignalCode()
	 */
	static public function setUpdateSignal($set = '', $params = '') {
		$beUser = static::getBackendUserAuthentication();
		$modData = $beUser->getModuleData(\TYPO3\CMS\Backend\Utility\BackendUtility::class . '::getUpdateSignal', 'ses');
		if ($set) {
			$modData[$set] = array(
				'set' => $set,
				'parameter' => $params
			);
		} else {
			// clear the module data
			$modData = array();
		}
		$beUser->pushModuleData(\TYPO3\CMS\Backend\Utility\BackendUtility::class . '::getUpdateSignal', $modData);
	}

	/**
	 * Call to update the page tree frame (or something else..?) if this is set by the function
	 * setUpdateSignal(). It will return some JavaScript that does the update
	 *
	 * @return string HTML javascript code
	 * @see BackendUtility::setUpdateSignal()
	 * @see \TYPO3\CMS\Backend\Template\DocumentTemplate::sectionEnd
	 */
	static public function getUpdateSignalCode() {
		$signals = array();
		$modData = static::getBackendUserAuthentication()->getModuleData(\TYPO3\CMS\Backend\Utility\BackendUtility::class . '::getUpdateSignal', 'ses');
		if (!count($modData)) {
			return '';
		}
		// Hook: Allows to let TYPO3 execute your JS code
		if (isset($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_befunc.php']['updateSignalHook'])) {
			$updateSignals = $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_befunc.php']['updateSignalHook'];
		} else {
			$updateSignals = array();
		}
		// Loop through all setUpdateSignals and get the JS code
		foreach ($modData as $set => $val) {
			if (isset($updateSignals[$set])) {
				$params = array('set' => $set, 'parameter' => $val['parameter'], 'JScode' => '');
				$ref = NULL;
				GeneralUtility::callUserFunction($updateSignals[$set], $params, $ref);
				$signals[] = $params['JScode'];
			} else {
				switch ($set) {
					case 'updatePageTree':
						$signals[] = '
								if (top && top.TYPO3.Backend.NavigationContainer.PageTree) {
									top.TYPO3.Backend.NavigationContainer.PageTree.refreshTree();
								}
							';
						break;
					case 'updateFolderTree':
						$signals[] = '
								if (top && top.TYPO3.Backend.NavigationIframe) {
									top.TYPO3.Backend.NavigationIframe.refresh();
								}';
						break;
					case 'updateModuleMenu':
						$signals[] = '
								if (top && top.TYPO3.ModuleMenu.App) {
									top.TYPO3.ModuleMenu.App.refreshMenu();
								}';
				}
			}
		}
		$content = implode(LF, $signals);
		// For backwards compatibility, should be replaced
		self::setUpdateSignal();
		return $content;
	}

	/**
	 * Returns an array which is most backend modules becomes MOD_SETTINGS containing values from function menus etc. determining the function of the module.
	 * This is kind of session variable management framework for the backend users.
	 * If a key from MOD_MENU is set in the CHANGED_SETTINGS array (eg. a value is passed to the script from the outside), this value is put into the settings-array
	 * Ultimately, see Inside TYPO3 for how to use this function in relation to your modules.
	 *
	 * @param array $MOD_MENU MOD_MENU is an array that defines the options in menus.
	 * @param array $CHANGED_SETTINGS CHANGED_SETTINGS represents the array used when passing values to the script from the menus.
	 * @param string $modName modName is the name of this module. Used to get the correct module data.
	 * @param string $type If type is 'ses' then the data is stored as session-lasting data. This means that it'll be wiped out the next time the user logs in.
	 * @param string $dontValidateList dontValidateList can be used to list variables that should not be checked if their value is found in the MOD_MENU array. Used for dynamically generated menus.
	 * @param string $setDefaultList List of default values from $MOD_MENU to set in the output array (only if the values from MOD_MENU are not arrays)
	 * @return array The array $settings, which holds a key for each MOD_MENU key and the values of each key will be within the range of values for each menuitem
	 */
	static public function getModuleData($MOD_MENU, $CHANGED_SETTINGS, $modName, $type = '', $dontValidateList = '', $setDefaultList = '') {
		if ($modName && is_string($modName)) {
			// Getting stored user-data from this module:
			$beUser = static::getBackendUserAuthentication();
			$settings = $beUser->getModuleData($modName, $type);
			$changed = 0;
			if (!is_array($settings)) {
				$changed = 1;
				$settings = array();
			}
			if (is_array($MOD_MENU)) {
				foreach ($MOD_MENU as $key => $var) {
					// If a global var is set before entering here. eg if submitted, then it's substituting the current value the array.
					if (is_array($CHANGED_SETTINGS) && isset($CHANGED_SETTINGS[$key])) {
						if (is_array($CHANGED_SETTINGS[$key])) {
							$serializedSettings = serialize($CHANGED_SETTINGS[$key]);
							if ((string)$settings[$key] !== $serializedSettings) {
								$settings[$key] = $serializedSettings;
								$changed = 1;
							}
						} else {
							if ((string)$settings[$key] !== (string)$CHANGED_SETTINGS[$key]) {
								$settings[$key] = $CHANGED_SETTINGS[$key];
								$changed = 1;
							}
						}
					}
					// If the $var is an array, which denotes the existence of a menu, we check if the value is permitted
					if (is_array($var) && (!$dontValidateList || !GeneralUtility::inList($dontValidateList, $key))) {
						// If the setting is an array or not present in the menu-array, MOD_MENU, then the default value is inserted.
						if (is_array($settings[$key]) || !isset($MOD_MENU[$key][$settings[$key]])) {
							$settings[$key] = (string)key($var);
							$changed = 1;
						}
					}
					// Sets default values (only strings/checkboxes, not menus)
					if ($setDefaultList && !is_array($var)) {
						if (GeneralUtility::inList($setDefaultList, $key) && !isset($settings[$key])) {
							$settings[$key] = (string)$var;
						}
					}
				}
			} else {
				die('No menu!');
			}
			if ($changed) {
				$beUser->pushModuleData($modName, $settings);
			}
			return $settings;
		} else {
			die('Wrong module name: "' . $modName . '"');
		}
	}

	/**
	 * Returns the URL to a given module
	 *
	 * @param string $moduleName Name of the module
	 * @param array $urlParameters URL parameters that should be added as key value pairs
	 * @param bool|string $backPathOverride backpath that should be used instead of the global $BACK_PATH
	 * @param bool $returnAbsoluteUrl If set to TRUE, the URL returned will be absolute, $backPathOverride will be ignored in this case
	 * @return string Calculated URL
	 */
	static public function getModuleUrl($moduleName, $urlParameters = array(), $backPathOverride = FALSE, $returnAbsoluteUrl = FALSE) {
		if ($backPathOverride === FALSE) {
			$backPath = isset($GLOBALS['BACK_PATH']) ? $GLOBALS['BACK_PATH'] : '';
		} else {
			$backPath = $backPathOverride;
		}
		$urlParameters = array(
			'M' => $moduleName,
			'moduleToken' => FormProtectionFactory::get()->generateToken('moduleCall', $moduleName)
		) + $urlParameters;
		$url = 'mod.php?' . ltrim(GeneralUtility::implodeArrayForUrl('', $urlParameters, '', TRUE, TRUE), '&');
		if ($returnAbsoluteUrl) {
			return GeneralUtility::getIndpEnv('TYPO3_REQUEST_DIR') . $url;
		} else {
			return $backPath . $url;
		}
	}

	/**
	 * Returns the Ajax URL for a given AjaxID including a CSRF token.
	 *
	 * This method is only called by the core and must not be used by extensions.
	 * Ajax URLs of all registered backend Ajax handlers are automatically published
	 * to JavaScript inline settings: TYPO3.settings.ajaxUrls['ajaxId']
	 *
	 * @param string $ajaxIdentifier Identifier of the AJAX callback
	 * @param array $urlParameters URL parameters that should be added as key value pairs
	 * @param bool $backPathOverride Backpath that should be used instead of the global $BACK_PATH
	 * @param bool $returnAbsoluteUrl If set to TRUE, the URL returned will be absolute, $backPathOverride will be ignored in this case
	 * @return string Calculated URL
	 * @internal
	 */
	static public function getAjaxUrl($ajaxIdentifier, array $urlParameters = array(), $backPathOverride = FALSE, $returnAbsoluteUrl = FALSE) {
		if ($backPathOverride) {
			$backPath = $backPathOverride;
		} else {
			$backPath = isset($GLOBALS['BACK_PATH']) ? $GLOBALS['BACK_PATH'] : '';
		}
		$additionalUrlParameters = array(
			'ajaxID' => $ajaxIdentifier
		);
		if (!empty($GLOBALS['TYPO3_CONF_VARS']['BE']['AJAX'][$ajaxIdentifier]['csrfTokenCheck'])) {
			$additionalUrlParameters['ajaxToken'] = FormProtectionFactory::get()->generateToken('ajaxCall', $ajaxIdentifier);
		}
		$url = 'ajax.php?' . ltrim(GeneralUtility::implodeArrayForUrl('', ($additionalUrlParameters + $urlParameters), '', TRUE, TRUE), '&');
		if ($returnAbsoluteUrl) {
			return GeneralUtility::getIndpEnv('TYPO3_REQUEST_DIR') . $url;
		} else {
			return $backPath . $url;
		}
	}

	/**
	 * Return a link to the list view
	 *
	 * @param array $urlParameters URL parameters that should be added as key value pairs
	 * @param string $linkTitle title for the link tag
	 * @param string $linkText optional link text after the icon
	 * @return string A complete link tag or empty string
	 */
	static public function getListViewLink($urlParameters = array(), $linkTitle = '', $linkText = '') {
		$url = self::getModuleUrl('web_list', $urlParameters);
		if ($url === FALSE) {
			return '';
		} else {
			return '<a href="' . htmlspecialchars($url) . '" title="' . htmlspecialchars($linkTitle) . '">' . IconUtility::getSpriteIcon('actions-system-list-open') . htmlspecialchars($linkText) . '</a>';
		}
	}

	/**
	 * Generates a token and returns a parameter for the URL
	 *
	 * @param string $formName Context of the token
	 * @param string $tokenName The name of the token GET variable
	 * @throws \InvalidArgumentException
	 * @return string A URL GET variable including ampersand
	 */
	static public function getUrlToken($formName = 'securityToken', $tokenName = 'formToken') {
		$formProtection = FormProtectionFactory::get();
		return '&' . $tokenName . '=' . $formProtection->generateToken($formName);
	}

	/*******************************************
	 *
	 * Core
	 *
	 *******************************************/
	/**
	 * Unlock or Lock a record from $table with $uid
	 * If $table and $uid is not set, then all locking for the current BE_USER is removed!
	 *
	 * @param string $table Table name
	 * @param int $uid Record uid
	 * @param int $pid Record pid
	 * @return void
	 * @internal
	 */
	static public function lockRecords($table = '', $uid = 0, $pid = 0) {
		$beUser = static::getBackendUserAuthentication();
		if (isset($beUser->user['uid'])) {
			$user_id = (int)$beUser->user['uid'];
			if ($table && $uid) {
				$fields_values = array(
					'userid' => $user_id,
					'feuserid' => 0,
					'tstamp' => $GLOBALS['EXEC_TIME'],
					'record_table' => $table,
					'record_uid' => $uid,
					'username' => $beUser->user['username'],
					'record_pid' => $pid
				);
				static::getDatabaseConnection()->exec_INSERTquery('sys_lockedrecords', $fields_values);
			} else {
				static::getDatabaseConnection()->exec_DELETEquery('sys_lockedrecords', 'userid=' . (int)$user_id);
			}
		}
	}

	/**
	 * Returns information about whether the record from table, $table, with uid, $uid is currently locked (edited by another user - which should issue a warning).
	 * Notice: Locking is not strictly carried out since locking is abandoned when other backend scripts are activated - which means that a user CAN have a record "open" without having it locked. So this just serves as a warning that counts well in 90% of the cases, which should be sufficient.
	 *
	 * @param string $table Table name
	 * @param int $uid Record uid
	 * @return array
	 * @internal
	 * @see class.db_layout.inc, alt_db_navframe.php, alt_doc.php, db_layout.php
	 */
	static public function isRecordLocked($table, $uid) {
		if (!is_array($GLOBALS['LOCKED_RECORDS'])) {
			$GLOBALS['LOCKED_RECORDS'] = array();
			$db = static::getDatabaseConnection();
			$res = $db->exec_SELECTquery('*', 'sys_lockedrecords', 'sys_lockedrecords.userid<>' . (int)static::getBackendUserAuthentication()->user['uid'] . '
								AND sys_lockedrecords.tstamp > ' . ($GLOBALS['EXEC_TIME'] - 2 * 3600));
			while ($row = $db->sql_fetch_assoc($res)) {
				// Get the type of the user that locked this record:
				if ($row['userid']) {
					$userTypeLabel = 'beUser';
				} elseif ($row['feuserid']) {
					$userTypeLabel = 'feUser';
				} else {
					$userTypeLabel = 'user';
				}
				$lang = static::getLanguageService();
				$userType = $lang->sL('LLL:EXT:lang/locallang_core.xlf:labels.' . $userTypeLabel);
				// Get the username (if available):
				if ($row['username']) {
					$userName = $row['username'];
				} else {
					$userName = $lang->sL('LLL:EXT:lang/locallang_core.xlf:labels.unknownUser');
				}
				$GLOBALS['LOCKED_RECORDS'][$row['record_table'] . ':' . $row['record_uid']] = $row;
				$GLOBALS['LOCKED_RECORDS'][$row['record_table'] . ':' . $row['record_uid']]['msg'] = sprintf($lang->sL('LLL:EXT:lang/locallang_core.xlf:labels.lockedRecordUser'), $userType, $userName, self::calcAge($GLOBALS['EXEC_TIME'] - $row['tstamp'], $lang->sL('LLL:EXT:lang/locallang_core.xlf:labels.minutesHoursDaysYears')));
				if ($row['record_pid'] && !isset($GLOBALS['LOCKED_RECORDS'][($row['record_table'] . ':' . $row['record_pid'])])) {
					$GLOBALS['LOCKED_RECORDS']['pages:' . $row['record_pid']]['msg'] = sprintf($lang->sL('LLL:EXT:lang/locallang_core.xlf:labels.lockedRecordUser_content'), $userType, $userName, self::calcAge($GLOBALS['EXEC_TIME'] - $row['tstamp'], $lang->sL('LLL:EXT:lang/locallang_core.xlf:labels.minutesHoursDaysYears')));
				}
			}
			$db->sql_free_result($res);
		}
		return $GLOBALS['LOCKED_RECORDS'][$table . ':' . $uid];
	}

	/**
	 * Returns select statement for MM relations (as used by TCEFORMs etc)
	 *
	 * @param array $fieldValue Configuration array for the field, taken from $GLOBALS['TCA']
	 * @param string $field Field name
	 * @param array $TSconfig TSconfig array from which to get further configuration settings for the field name
	 * @param string $prefix Prefix string for the key "*foreign_table_where" from $fieldValue array
	 * @return string Part of query
	 * @internal
	 */
	static public function exec_foreign_table_where_query($fieldValue, $field = '', $TSconfig = array(), $prefix = '') {
		$foreign_table = $fieldValue['config'][$prefix . 'foreign_table'];
		$rootLevel = $GLOBALS['TCA'][$foreign_table]['ctrl']['rootLevel'];
		$fTWHERE = $fieldValue['config'][$prefix . 'foreign_table_where'];
		$fTWHERE = static::replaceMarkersInWhereClause($fTWHERE, $foreign_table, $field, $TSconfig);
		$db = static::getDatabaseConnection();
		$wgolParts = $db->splitGroupOrderLimit($fTWHERE);
		// rootLevel = -1 means that elements can be on the rootlevel OR on any page (pid!=-1)
		// rootLevel = 0 means that elements are not allowed on root level
		// rootLevel = 1 means that elements are only on the root level (pid=0)
		if ($rootLevel == 1 || $rootLevel == -1) {
			$pidWhere = $foreign_table . '.pid' . (($rootLevel == -1) ? '<>-1' : '=0');
			$queryParts = array(
				'SELECT' => self::getCommonSelectFields($foreign_table, $foreign_table . '.'),
				'FROM' => $foreign_table,
				'WHERE' => $pidWhere . ' ' . self::deleteClause($foreign_table) . ' ' . $wgolParts['WHERE'],
				'GROUPBY' => $wgolParts['GROUPBY'],
				'ORDERBY' => $wgolParts['ORDERBY'],
				'LIMIT' => $wgolParts['LIMIT']
			);
		} else {
			$pageClause = static::getBackendUserAuthentication()->getPagePermsClause(1);
			if ($foreign_table != 'pages') {
				$queryParts = array(
					'SELECT' => self::getCommonSelectFields($foreign_table, $foreign_table . '.'),
					'FROM' => $foreign_table . ', pages',
					'WHERE' => 'pages.uid=' . $foreign_table . '.pid
								AND pages.deleted=0 ' . self::deleteClause($foreign_table) . ' AND ' . $pageClause . ' ' . $wgolParts['WHERE'],
					'GROUPBY' => $wgolParts['GROUPBY'],
					'ORDERBY' => $wgolParts['ORDERBY'],
					'LIMIT' => $wgolParts['LIMIT']
				);
			} else {
				$queryParts = array(
					'SELECT' => self::getCommonSelectFields($foreign_table, $foreign_table . '.'),
					'FROM' => 'pages',
					'WHERE' => 'pages.deleted=0
								AND ' . $pageClause . ' ' . $wgolParts['WHERE'],
					'GROUPBY' => $wgolParts['GROUPBY'],
					'ORDERBY' => $wgolParts['ORDERBY'],
					'LIMIT' => $wgolParts['LIMIT']
				);
			}
		}
		return $db->exec_SELECT_queryArray($queryParts);
	}

	/**
	 * Replaces all special markers in a where clause.
	 * Special markers are:
	 * ###REC_FIELD_[field name]###
	 * ###THIS_UID### - is current element uid (zero if new).
	 * ###THIS_CID###
	 * ###CURRENT_PID### - is the current page id (pid of the record).
	 * ###STORAGE_PID###
	 * ###SITEROOT###
	 * ###PAGE_TSCONFIG_ID### - a value you can set from Page TSconfig dynamically.
	 * ###PAGE_TSCONFIG_IDLIST### - a value you can set from Page TSconfig dynamically.
	 * ###PAGE_TSCONFIG_STR### - a value you can set from Page TSconfig dynamically.
	 *
	 * @param string $whereClause Where clause with markers
	 * @param string $table Name of the table of the current record row
	 * @param string $field Field name
	 * @param array $tsConfig TSconfig array from which to get further configuration settings for the field name
	 * @return string
	 */
	static public function replaceMarkersInWhereClause($whereClause, $table, $field = '', $tsConfig = array()) {
		$db = static::getDatabaseConnection();
		if (strstr($whereClause, '###REC_FIELD_')) {
			$whereClauseParts = explode('###REC_FIELD_', $whereClause);
			foreach ($whereClauseParts as $key => $value) {
				if ($key) {
					$whereClauseSubarts = explode('###', $value, 2);
					if (substr($whereClauseParts[0], -1) === '\'' && $whereClauseSubarts[1][0] === '\'') {
						$whereClauseParts[$key] = $db->quoteStr($tsConfig['_THIS_ROW'][$whereClauseSubarts[0]], $table) . $whereClauseSubarts[1];
					} else {
						$whereClauseParts[$key] = $db->fullQuoteStr($tsConfig['_THIS_ROW'][$whereClauseSubarts[0]], $table) . $whereClauseSubarts[1];
					}
				}
			}
			$whereClause = implode('', $whereClauseParts);
		}
		return str_replace (
			array (
				'###CURRENT_PID###',
				'###THIS_UID###',
				'###THIS_CID###',
				'###STORAGE_PID###',
				'###SITEROOT###',
				'###PAGE_TSCONFIG_ID###',
				'###PAGE_TSCONFIG_IDLIST###',
				'###PAGE_TSCONFIG_STR###'
			),
			array(
				(int)$tsConfig['_CURRENT_PID'],
				(int)$tsConfig['_THIS_UID'],
				(int)$tsConfig['_THIS_CID'],
				(int)$tsConfig['_STORAGE_PID'],
				(int)$tsConfig['_SITEROOT'],
				(int)$tsConfig[$field]['PAGE_TSCONFIG_ID'],
				$db->cleanIntList($tsConfig[$field]['PAGE_TSCONFIG_IDLIST']),
				$db->quoteStr($tsConfig[$field]['PAGE_TSCONFIG_STR'], $table)
			),
			$whereClause
		);
	}

	/**
	 * Returns TSConfig for the TCEFORM object in Page TSconfig.
	 * Used in TCEFORMs
	 *
	 * @param string $table Table name present in TCA
	 * @param array $row Row from table
	 * @return array
	 */
	static public function getTCEFORM_TSconfig($table, $row) {
		self::fixVersioningPid($table, $row);
		$res = array();
		$typeVal = self::getTCAtypeValue($table, $row);
		// Get main config for the table
		list($TScID, $cPid) = self::getTSCpid($table, $row['uid'], $row['pid']);
		if ($TScID >= 0) {
			$tempConf = static::getBackendUserAuthentication()->getTSConfig('TCEFORM.' . $table, self::getPagesTSconfig($TScID));
			if (is_array($tempConf['properties'])) {
				foreach ($tempConf['properties'] as $key => $val) {
					if (is_array($val)) {
						$fieldN = substr($key, 0, -1);
						$res[$fieldN] = $val;
						unset($res[$fieldN]['types.']);
						if ((string)$typeVal !== '' && is_array($val['types.'][$typeVal . '.'])) {
							ArrayUtility::mergeRecursiveWithOverrule($res[$fieldN], $val['types.'][$typeVal . '.']);
						}
					}
				}
			}
		}
		$res['_CURRENT_PID'] = $cPid;
		$res['_THIS_UID'] = $row['uid'];
		$res['_THIS_CID'] = $row['cid'];
		// So the row will be passed to foreign_table_where_query()
		$res['_THIS_ROW'] = $row;
		$rootLine = self::BEgetRootLine($TScID, '', TRUE);
		foreach ($rootLine as $rC) {
			if (!$res['_STORAGE_PID']) {
				$res['_STORAGE_PID'] = (int)$rC['storage_pid'];
			}
			if (!$res['_SITEROOT']) {
				$res['_SITEROOT'] = $rC['is_siteroot'] ? (int)$rC['uid'] : 0;
			}
		}
		return $res;
	}

	/**
	 * Find the real PID of the record (with $uid from $table).
	 * This MAY be impossible if the pid is set as a reference to the former record or a page (if two records are created at one time).
	 * NOTICE: Make sure that the input PID is never negative because the record was an offline version!
	 * Therefore, you should always use BackendUtility::fixVersioningPid($table,$row); on the data you input before calling this function!
	 *
	 * @param string $table Table name
	 * @param int $uid Record uid
	 * @param int $pid Record pid, could be negative then pointing to a record from same table whose pid to find and return
	 * @return int
	 * @internal
	 * @see \TYPO3\CMS\Core\DataHandling\DataHandler::copyRecord(), getTSCpid()
	 */
	static public function getTSconfig_pidValue($table, $uid, $pid) {
		// If pid is an integer this takes precedence in our lookup.
		if (MathUtility::canBeInterpretedAsInteger($pid)) {
			$thePidValue = (int)$pid;
			// If ref to another record, look that record up.
			if ($thePidValue < 0) {
				$pidRec = self::getRecord($table, abs($thePidValue), 'pid');
				$thePidValue = is_array($pidRec) ? $pidRec['pid'] : -2;
			}
		} else {
			// Try to fetch the record pid from uid. If the uid is 'NEW...' then this will of course return nothing
			$rr = self::getRecord($table, $uid);
			$thePidValue = NULL;
			if (is_array($rr)) {
				// First check if the pid is -1 which means it is a workspaced element. Get the "real" record:
				if ($rr['pid'] == '-1') {
					$rr = self::getRecord($table, $rr['t3ver_oid'], 'pid');
					if (is_array($rr)) {
						$thePidValue = $rr['pid'];
					}
				} else {
					// Returning the "pid" of the record
					$thePidValue = $rr['pid'];
				}
			}
			if (!$thePidValue) {
				// Returns -1 if the record with this pid was not found.
				$thePidValue = -1;
			}
		}
		return $thePidValue;
	}

	/**
	 * Return $uid if $table is pages and $uid is int - otherwise the $pid
	 *
	 * @param string $table Table name
	 * @param int $uid Record uid
	 * @param int $pid Record pid
	 * @return int
	 * @internal
	 */
	static public function getPidForModTSconfig($table, $uid, $pid) {
		$retVal = $table == 'pages' && MathUtility::canBeInterpretedAsInteger($uid) ? $uid : $pid;
		return $retVal;
	}

	/**
	 * Return the real pid of a record and caches the result.
	 * The non-cached method needs database queries to do the job, so this method
	 * can be used if code sometimes calls the same record multiple times to save
	 * some queries. This should not be done if the calling code may change the
	 * same record meanwhile.
	 *
	 * @param string $table Tablename
	 * @param string $uid UID value
	 * @param string $pid PID value
	 * @return array Array of two integers; first is the real PID of a record, second is the PID value for TSconfig.
	 */
	static public function getTSCpidCached($table, $uid, $pid) {
		// A local first level cache
		static $firstLevelCache;

		if (!is_array($firstLevelCache)) {
			$firstLevelCache = array();
		}

		$key = $table . ':' . $uid . ':' . $pid;
		if (!isset($firstLevelCache[$key])) {
			$firstLevelCache[$key] = static::getTSCpid($table, $uid, $pid);
		}
		return $firstLevelCache[$key];
	}

	/**
	 * Returns the REAL pid of the record, if possible. If both $uid and $pid is strings, then pid=-1 is returned as an error indication.
	 *
	 * @param string $table Table name
	 * @param int $uid Record uid
	 * @param int $pid Record pid
	 * @return array Array of two ints; first is the REAL PID of a record and if its a new record negative values are resolved to the true PID, second value is the PID value for TSconfig (uid if table is pages, otherwise the pid)
	 * @internal
	 * @see \TYPO3\CMS\Core\DataHandling\DataHandler::setHistory(), \TYPO3\CMS\Core\DataHandling\DataHandler::process_datamap()
	 */
	static public function getTSCpid($table, $uid, $pid) {
		// If pid is negative (referring to another record) the pid of the other record is fetched and returned.
		$cPid = self::getTSconfig_pidValue($table, $uid, $pid);
		// $TScID is the id of $table = pages, else it's the pid of the record.
		$TScID = self::getPidForModTSconfig($table, $uid, $cPid);
		return array($TScID, $cPid);
	}

	/**
	 * Returns first found domain record "domainName" (without trailing slash) if found in the input $rootLine
	 *
	 * @param array $rootLine Root line array
	 * @return string|NULL Domain name or NULL
	 */
	static public function firstDomainRecord($rootLine) {
		foreach ($rootLine as $row) {
			$dRec = self::getRecordsByField('sys_domain', 'pid', $row['uid'], ' AND redirectTo=\'\' AND hidden=0', '', 'sorting');
			if (is_array($dRec)) {
				$dRecord = reset($dRec);
				return rtrim($dRecord['domainName'], '/');
			}
		}
		return NULL;
	}

	/**
	 * Returns the sys_domain record for $domain, optionally with $path appended.
	 *
	 * @param string $domain Domain name
	 * @param string $path Appended path
	 * @return array Domain record, if found
	 */
	static public function getDomainStartPage($domain, $path = '') {
		$domain = explode(':', $domain);
		$domain = strtolower(preg_replace('/\\.$/', '', $domain[0]));
		// Path is calculated.
		$path = trim(preg_replace('/\\/[^\\/]*$/', '', $path));
		// Stuff
		$domain .= $path;
		$db = static::getDatabaseConnection();
		$res = $db->exec_SELECTquery('sys_domain.*', 'pages,sys_domain', '
			pages.uid=sys_domain.pid
			AND sys_domain.hidden=0
			AND (sys_domain.domainName=' . $db->fullQuoteStr($domain, 'sys_domain') . ' OR sys_domain.domainName='
			. $db->fullQuoteStr(($domain . '/'), 'sys_domain') . ')' . self::deleteClause('pages'), '', '', '1');
		$result = $db->sql_fetch_assoc($res);
		$db->sql_free_result($res);
		return $result;
	}

	/**
	 * Returns overlayered RTE setup from an array with TSconfig. Used in TCEforms and TCEmain
	 *
	 * @param array $RTEprop The properties of Page TSconfig in the key "RTE.
	 * @param string $table Table name
	 * @param string $field Field name
	 * @param string $type Type value of the current record (like from CType of tt_content)
	 * @return array Array with the configuration for the RTE
	 * @internal
	 */
	static public function RTEsetup($RTEprop, $table, $field, $type = '') {
		$thisConfig = is_array($RTEprop['default.']) ? $RTEprop['default.'] : array();
		$thisFieldConf = $RTEprop['config.'][$table . '.'][$field . '.'];
		if (is_array($thisFieldConf)) {
			unset($thisFieldConf['types.']);
			ArrayUtility::mergeRecursiveWithOverrule($thisConfig, $thisFieldConf);
		}
		if ($type && is_array($RTEprop['config.'][$table . '.'][$field . '.']['types.'][$type . '.'])) {
			ArrayUtility::mergeRecursiveWithOverrule($thisConfig, $RTEprop['config.'][$table . '.'][$field . '.']['types.'][$type . '.']);
		}
		return $thisConfig;
	}

	/**
	 * Returns first possible RTE object if available.
	 * Usage: $RTEobj = &BackendUtility::RTEgetObj();
	 *
	 * @return mixed If available, returns RTE object, otherwise an array of messages from possible RTEs
	 */
	static public function &RTEgetObj() {
		// If no RTE object has been set previously, try to create it:
		if (!isset($GLOBALS['T3_VAR']['RTEobj'])) {
			// Set the object string to blank by default:
			$GLOBALS['T3_VAR']['RTEobj'] = array();
			// Traverse registered RTEs:
			if (is_array($GLOBALS['TYPO3_CONF_VARS']['BE']['RTE_reg'])) {
				foreach ($GLOBALS['TYPO3_CONF_VARS']['BE']['RTE_reg'] as $extKey => $rteObjCfg) {
					$rteObj = GeneralUtility::getUserObj($rteObjCfg['objRef']);
					if (is_object($rteObj)) {
						if ($rteObj->isAvailable()) {
							$GLOBALS['T3_VAR']['RTEobj'] = $rteObj;
							break;
						} else {
							$GLOBALS['T3_VAR']['RTEobj'] = array_merge($GLOBALS['T3_VAR']['RTEobj'], $rteObj->errorLog);
						}
					}
				}
			}
			if (!count($GLOBALS['T3_VAR']['RTEobj'])) {
				$GLOBALS['T3_VAR']['RTEobj'][] = 'No RTEs configured at all';
			}
		}
		// Return RTE object (if any!)
		return $GLOBALS['T3_VAR']['RTEobj'];
	}

	/**
	 * Returns soft-reference parser for the softRef processing type
	 * Usage: $softRefObj = &BackendUtility::softRefParserObj('[parser key]');
	 *
	 * @param string $spKey softRef parser key
	 * @return mixed If available, returns Soft link parser object.
	 */
	static public function &softRefParserObj($spKey) {
		// If no softRef parser object has been set previously, try to create it:
		if (!isset($GLOBALS['T3_VAR']['softRefParser'][$spKey])) {
			// Set the object string to blank by default:
			$GLOBALS['T3_VAR']['softRefParser'][$spKey] = '';
			// Now, try to create parser object:
			$objRef = NULL;
			if (isset($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['GLOBAL']['softRefParser'][$spKey])) {
				$objRef = $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['GLOBAL']['softRefParser'][$spKey];
			} elseif (isset($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['GLOBAL']['softRefParser_GL'][$spKey])) {
				GeneralUtility::deprecationLog('The hook softRefParser_GL (used with parser key "'
					. $spKey . '") is deprecated since TYPO3 CMS 7 and will be removed in TYPO3 CMS 8');
				$objRef = $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['GLOBAL']['softRefParser_GL'][$spKey];
			}
			if ($objRef) {
				$softRefParserObj = GeneralUtility::getUserObj($objRef, '');
				if (is_object($softRefParserObj)) {
					$GLOBALS['T3_VAR']['softRefParser'][$spKey] = $softRefParserObj;
				}
			}
		}
		// Return RTE object (if any!)
		return $GLOBALS['T3_VAR']['softRefParser'][$spKey];
	}

	/**
	 * Returns array of soft parser references
	 *
	 * @param string $parserList softRef parser list
	 * @return array Array where the parser key is the key and the value is the parameter string
	 */
	static public function explodeSoftRefParserList($parserList) {
		// Looking for global parsers:
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['GLOBAL']['softRefParser_GL']) && !empty($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['GLOBAL']['softRefParser_GL'])) {
			GeneralUtility::deprecationLog('The hook softRefParser_GL is deprecated since TYPO3 CMS 7 and will be removed in TYPO3 CMS 8');
			$parserList = implode(',', array_keys($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['GLOBAL']['softRefParser_GL'])) . ',' . $parserList;
		}
		// Return immediately if list is blank:
		if ($parserList === '') {
			return FALSE;
		}
		// Otherwise parse the list:
		$keyList = GeneralUtility::trimExplode(',', $parserList, TRUE);
		$output = array();
		foreach ($keyList as $val) {
			$reg = array();
			if (preg_match('/^([[:alnum:]_-]+)\\[(.*)\\]$/', $val, $reg)) {
				$output[$reg[1]] = GeneralUtility::trimExplode(';', $reg[2], TRUE);
			} else {
				$output[$val] = '';
			}
		}
		return $output;
	}

	/**
	 * Returns TRUE if $modName is set and is found as a main- or submodule in $TBE_MODULES array
	 *
	 * @param string $modName Module name
	 * @return bool
	 */
	static public function isModuleSetInTBE_MODULES($modName) {
		$loaded = array();
		foreach ($GLOBALS['TBE_MODULES'] as $mkey => $list) {
			$loaded[$mkey] = 1;
			if (!is_array($list) && trim($list)) {
				$subList = GeneralUtility::trimExplode(',', $list, TRUE);
				foreach ($subList as $skey) {
					$loaded[$mkey . '_' . $skey] = 1;
				}
			}
		}
		return $modName && isset($loaded[$modName]);
	}

	/**
	 * Counting references to a record/file
	 *
	 * @param string $table Table name (or "_FILE" if its a file)
	 * @param string $ref Reference: If table, then int-uid, if _FILE, then file reference (relative to PATH_site)
	 * @param string $msg Message with %s, eg. "There were %s records pointing to this file!
	 * @param string|NULL $count Reference count
	 * @return string Output string (or int count value if no msg string specified)
	 */
	static public function referenceCount($table, $ref, $msg = '', $count = NULL) {
		if ($count === NULL) {
			$db = static::getDatabaseConnection();
			// Look up the path:
			if ($table == '_FILE') {
				if (GeneralUtility::isFirstPartOfStr($ref, PATH_site)) {
					$ref = PathUtility::stripPathSitePrefix($ref);
					$condition = 'ref_string=' . $db->fullQuoteStr($ref, 'sys_refindex');
				} else {
					return '';
				}
			} else {
				$condition = 'ref_uid=' . (int)$ref;
			}
			$count = $db->exec_SELECTcountRows('*', 'sys_refindex', 'ref_table=' . $db->fullQuoteStr($table, 'sys_refindex') . ' AND ' . $condition . ' AND deleted=0');
		}
		return $count ? ($msg ? sprintf($msg, $count) : $count) : '';
	}

	/**
	 * Counting translations of records
	 *
	 * @param string $table Table name
	 * @param string $ref Reference: the record's uid
	 * @param string $msg Message with %s, eg. "This record has %s translation(s) which will be deleted, too!
	 * @return string Output string (or int count value if no msg string specified)
	 */
	static public function translationCount($table, $ref, $msg = '') {
		$count = NULL;
		if (empty($GLOBALS['TCA'][$table]['ctrl']['transForeignTable']) && $GLOBALS['TCA'][$table]['ctrl']['languageField'] && $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'] && !$GLOBALS['TCA'][$table]['ctrl']['transOrigPointerTable']) {
			$where = $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'] . '=' . (int)$ref . ' AND ' . $GLOBALS['TCA'][$table]['ctrl']['languageField'] . '<>0';
			if (!empty($GLOBALS['TCA'][$table]['ctrl']['delete'])) {
				$where .= ' AND ' . $GLOBALS['TCA'][$table]['ctrl']['delete'] . '=0';
			}
			$count = static::getDatabaseConnection()->exec_SELECTcountRows('*', $table, $where);
		}
		return $count ? ($msg ? sprintf($msg, $count) : $count) : '';
	}

	/*******************************************
	 *
	 * Workspaces / Versioning
	 *
	 *******************************************/
	/**
	 * Select all versions of a record, ordered by version id (DESC)
	 *
	 * @param string $table Table name to select from
	 * @param int $uid Record uid for which to find versions.
	 * @param string $fields Field list to select
	 * @param int $workspace Workspace ID, if zero all versions regardless of workspace is found.
	 * @param bool $includeDeletedRecords If set, deleted-flagged versions are included! (Only for clean-up script!)
	 * @param array $row The current record
	 * @return array|NULL Array of versions of table/uid
	 */
	static public function selectVersionsOfRecord($table, $uid, $fields = '*', $workspace = 0, $includeDeletedRecords = FALSE, $row = NULL) {
		$realPid = 0;
		$outputRows = array();
		$workspace = (int)$workspace;
		if ($GLOBALS['TCA'][$table] && $GLOBALS['TCA'][$table]['ctrl']['versioningWS']) {
			if (is_array($row) && !$includeDeletedRecords) {
				$row['_CURRENT_VERSION'] = TRUE;
				$realPid = $row['pid'];
				$outputRows[] = $row;
			} else {
				// Select UID version:
				$row = BackendUtility::getRecord($table, $uid, $fields, '', !$includeDeletedRecords);
				// Add rows to output array:
				if ($row) {
					$row['_CURRENT_VERSION'] = TRUE;
					$realPid = $row['pid'];
					$outputRows[] = $row;
				}
			}
			// Select all offline versions of record:
			$rows = static::getDatabaseConnection()->exec_SELECTgetRows(
				$fields,
				$table,
				'pid=-1 AND uid<>' . (int)$uid . ' AND t3ver_oid=' . (int)$uid
					. ' AND t3ver_wsid' . ($workspace !== 0 ? ' IN (0,' . (int)$workspace . ')' : '=0')
					. ($includeDeletedRecords ? '' : self::deleteClause($table)),
				'',
				't3ver_id DESC'
			);
			// Add rows to output array:
			if (is_array($rows)) {
				$outputRows = array_merge($outputRows, $rows);
			}
			// Set real-pid:
			foreach ($outputRows as $idx => $oRow) {
				$outputRows[$idx]['_REAL_PID'] = $realPid;
			}
			return $outputRows;
		}
		return NULL;
	}

	/**
	 * Find page-tree PID for versionized record
	 * Will look if the "pid" value of the input record is -1 and if the table supports versioning - if so,
	 * it will translate the -1 PID into the PID of the original record
	 * Used whenever you are tracking something back, like making the root line.
	 * Will only translate if the workspace of the input record matches that of the current user (unless flag set)
	 * Principle; Record offline! => Find online?
	 *
	 * If the record had its pid corrected to the online versions pid, then "_ORIG_pid" is set
	 * to the original pid value (-1 of course). The field "_ORIG_pid" is used by various other functions
	 * to detect if a record was in fact in a versionized branch.
	 *
	 * @param string $table Table name
	 * @param array $rr Record array passed by reference. As minimum, "pid" and "uid" fields must exist! "t3ver_oid" and "t3ver_wsid" is nice and will save you a DB query.
	 * @param bool $ignoreWorkspaceMatch Ignore workspace match
	 * @return void
	 * @see PageRepository::fixVersioningPid()
	 */
	static public function fixVersioningPid($table, &$rr, $ignoreWorkspaceMatch = FALSE) {
		if (!ExtensionManagementUtility::isLoaded('version')) {
			return;
		}
		// Check that the input record is an offline version from a table that supports versioning:
		if (is_array($rr) && $rr['pid'] == -1 && $GLOBALS['TCA'][$table]['ctrl']['versioningWS']) {
			// Check values for t3ver_oid and t3ver_wsid:
			if (isset($rr['t3ver_oid']) && isset($rr['t3ver_wsid'])) {
				// If "t3ver_oid" is already a field, just set this:
				$oid = $rr['t3ver_oid'];
				$wsid = $rr['t3ver_wsid'];
			} else {
				$oid = 0;
				$wsid = 0;
				// Otherwise we have to expect "uid" to be in the record and look up based on this:
				$newPidRec = self::getRecord($table, $rr['uid'], 't3ver_oid,t3ver_wsid');
				if (is_array($newPidRec)) {
					$oid = $newPidRec['t3ver_oid'];
					$wsid = $newPidRec['t3ver_wsid'];
				}
			}
			// If ID of current online version is found, look up the PID value of that:
			if ($oid && ($ignoreWorkspaceMatch || (int)$wsid === (int)static::getBackendUserAuthentication()->workspace)) {
				$oidRec = self::getRecord($table, $oid, 'pid');
				if (is_array($oidRec)) {
					$rr['_ORIG_pid'] = $rr['pid'];
					$rr['pid'] = $oidRec['pid'];
				}
			}
		}
	}

	/**
	 * Workspace Preview Overlay
	 * Generally ALWAYS used when records are selected based on uid or pid.
	 * If records are selected on other fields than uid or pid (eg. "email = ....")
	 * then usage might produce undesired results and that should be evaluated on individual basis.
	 * Principle; Record online! => Find offline?
	 * Recently, this function has been modified so it MAY set $row to FALSE.
	 * This happens if a version overlay with the move-id pointer is found in which case we would like a backend preview.
	 * In other words, you should check if the input record is still an array afterwards when using this function.
	 *
	 * @param string $table Table name
	 * @param array $row Record array passed by reference. As minimum, the "uid" and  "pid" fields must exist! Fake fields cannot exist since the fields in the array is used as field names in the SQL look up. It would be nice to have fields like "t3ver_state" and "t3ver_mode_id" as well to avoid a new lookup inside movePlhOL().
	 * @param int $wsid Workspace ID, if not specified will use static::getBackendUserAuthentication()->workspace
	 * @param bool $unsetMovePointers If TRUE the function does not return a "pointer" row for moved records in a workspace
	 * @return void
	 * @see fixVersioningPid()
	 */
	static public function workspaceOL($table, &$row, $wsid = -99, $unsetMovePointers = FALSE) {
		if (!ExtensionManagementUtility::isLoaded('version')) {
			return;
		}
		// If this is FALSE the placeholder is shown raw in the backend.
		// I don't know if this move can be useful for users to toggle. Technically it can help debugging.
		$previewMovePlaceholders = TRUE;
		// Initialize workspace ID
		if ($wsid == -99) {
			$wsid = static::getBackendUserAuthentication()->workspace;
		}
		// Check if workspace is different from zero and record is set:
		if ($wsid !== 0 && is_array($row)) {
			// Check if input record is a move-placeholder and if so, find the pointed-to live record:
			$movePldSwap = NULL;
			$orig_uid = 0;
			$orig_pid = 0;
			if ($previewMovePlaceholders) {
				$orig_uid = $row['uid'];
				$orig_pid = $row['pid'];
				$movePldSwap = self::movePlhOL($table, $row);
			}
			$wsAlt = self::getWorkspaceVersionOfRecord($wsid, $table, $row['uid'], implode(',', array_keys($row)));
			// If version was found, swap the default record with that one.
			if (is_array($wsAlt)) {
				// Check if this is in move-state:
				if ($previewMovePlaceholders && !$movePldSwap && ($table == 'pages' || (int)$GLOBALS['TCA'][$table]['ctrl']['versioningWS'] >= 2) && $unsetMovePointers) {
					// Only for WS ver 2... (moving)
					// If t3ver_state is not found, then find it... (but we like best if it is here...)
					if (!isset($wsAlt['t3ver_state'])) {
						$stateRec = self::getRecord($table, $wsAlt['uid'], 't3ver_state');
						$versionState = VersionState::cast($stateRec['t3ver_state']);
					} else {
						$versionState = VersionState::cast($wsAlt['t3ver_state']);
					}
					if ($versionState->equals(VersionState::MOVE_POINTER)) {
						// @todo Same problem as frontend in versionOL(). See TODO point there.
						$row = FALSE;
						return;
					}
				}
				// Always correct PID from -1 to what it should be
				if (isset($wsAlt['pid'])) {
					// Keep the old (-1) - indicates it was a version.
					$wsAlt['_ORIG_pid'] = $wsAlt['pid'];
					// Set in the online versions PID.
					$wsAlt['pid'] = $row['pid'];
				}
				// For versions of single elements or page+content, swap UID and PID
				$wsAlt['_ORIG_uid'] = $wsAlt['uid'];
				$wsAlt['uid'] = $row['uid'];
				// Backend css class:
				$wsAlt['_CSSCLASS'] = 'ver-element';
				// Changing input record to the workspace version alternative:
				$row = $wsAlt;
			}
			// If the original record was a move placeholder, the uid and pid of that is preserved here:
			if ($movePldSwap) {
				$row['_MOVE_PLH'] = TRUE;
				$row['_MOVE_PLH_uid'] = $orig_uid;
				$row['_MOVE_PLH_pid'] = $orig_pid;
				// For display; To make the icon right for the placeholder vs. the original
				$row['t3ver_state'] = (string)new VersionState(VersionState::MOVE_PLACEHOLDER);
			}
		}
	}

	/**
	 * Checks if record is a move-placeholder (t3ver_state==VersionState::MOVE_PLACEHOLDER) and if so
	 * it will set $row to be the pointed-to live record (and return TRUE)
	 *
	 * @param string $table Table name
	 * @param array $row Row (passed by reference) - must be online record!
	 * @return bool TRUE if overlay is made.
	 * @see PageRepository::movePlhOl()
	 */
	static public function movePlhOL($table, &$row) {
		// Only for WS ver 2... (moving)
		if ($table == 'pages' || (int)$GLOBALS['TCA'][$table]['ctrl']['versioningWS'] >= 2) {
			// If t3ver_move_id or t3ver_state is not found, then find it... (but we like best if it is here...)
			if (!isset($row['t3ver_move_id']) || !isset($row['t3ver_state'])) {
				$moveIDRec = self::getRecord($table, $row['uid'], 't3ver_move_id, t3ver_state');
				$moveID = $moveIDRec['t3ver_move_id'];
				$versionState = VersionState::cast($moveIDRec['t3ver_state']);
			} else {
				$moveID = $row['t3ver_move_id'];
				$versionState = VersionState::cast($row['t3ver_state']);
			}
			// Find pointed-to record.
			if ($versionState->equals(VersionState::MOVE_PLACEHOLDER) && $moveID) {
				if ($origRow = self::getRecord($table, $moveID, implode(',', array_keys($row)))) {
					$row = $origRow;
					return TRUE;
				}
			}
		}
		return FALSE;
	}

	/**
	 * Select the workspace version of a record, if exists
	 *
	 * @param int $workspace Workspace ID
	 * @param string $table Table name to select from
	 * @param int $uid Record uid for which to find workspace version.
	 * @param string $fields Field list to select
	 * @return array If found, return record, otherwise FALSE
	 */
	static public function getWorkspaceVersionOfRecord($workspace, $table, $uid, $fields = '*') {
		if (ExtensionManagementUtility::isLoaded('version')) {
			if ($workspace !== 0 && $GLOBALS['TCA'][$table] && $GLOBALS['TCA'][$table]['ctrl']['versioningWS']) {
				// Select workspace version of record:
				$row = static::getDatabaseConnection()->exec_SELECTgetSingleRow($fields, $table, 'pid=-1 AND ' . 't3ver_oid=' . (int)$uid . ' AND ' . 't3ver_wsid=' . (int)$workspace . self::deleteClause($table));
				if (is_array($row)) {
					return $row;
				}
			}
		}
		return FALSE;
	}

	/**
	 * Returns live version of record
	 *
	 * @param string $table Table name
	 * @param int $uid Record UID of draft, offline version
	 * @param string $fields Field list, default is *
	 * @return array|NULL If found, the record, otherwise NULL
	 */
	static public function getLiveVersionOfRecord($table, $uid, $fields = '*') {
		$liveVersionId = self::getLiveVersionIdOfRecord($table, $uid);
		if (is_null($liveVersionId) === FALSE) {
			return self::getRecord($table, $liveVersionId, $fields);
		}
		return NULL;
	}

	/**
	 * Gets the id of the live version of a record.
	 *
	 * @param string $table Name of the table
	 * @param int $uid Uid of the offline/draft record
	 * @return int The id of the live version of the record (or NULL if nothing was found)
	 */
	static public function getLiveVersionIdOfRecord($table, $uid) {
		$liveVersionId = NULL;
		if (self::isTableWorkspaceEnabled($table)) {
			$currentRecord = self::getRecord($table, $uid, 'pid,t3ver_oid');
			if (is_array($currentRecord) && $currentRecord['pid'] == -1) {
				$liveVersionId = $currentRecord['t3ver_oid'];
			}
		}
		return $liveVersionId;
	}

	/**
	 * Will return where clause de-selecting new(/deleted)-versions from other workspaces.
	 * If in live-workspace, don't show "MOVE-TO-PLACEHOLDERS" records if versioningWS is 2 (allows moving)
	 *
	 * @param string $table Table name
	 * @return string Where clause if applicable.
	 */
	static public function versioningPlaceholderClause($table) {
		if ($GLOBALS['TCA'][$table] && $GLOBALS['TCA'][$table]['ctrl']['versioningWS']) {
			$currentWorkspace = (int)static::getBackendUserAuthentication()->workspace;
			return ' AND (' . $table . '.t3ver_state <= ' . new VersionState(VersionState::DEFAULT_STATE) . ' OR ' . $table . '.t3ver_wsid = ' . $currentWorkspace . ')';
		}
		return '';
	}

	/**
	 * Get additional where clause to select records of a specific workspace (includes live as well).
	 *
	 * @param string $table Table name
	 * @param int $workspaceId Workspace ID
	 * @return string Workspace where clause
	 */
	static public function getWorkspaceWhereClause($table, $workspaceId = NULL) {
		$whereClause = '';
		if (self::isTableWorkspaceEnabled($table)) {
			if (is_null($workspaceId)) {
				$workspaceId = static::getBackendUserAuthentication()->workspace;
			}
			$workspaceId = (int)$workspaceId;
			$pidOperator = $workspaceId === 0 ? '!=' : '=';
			$whereClause = ' AND ' . $table . '.t3ver_wsid=' . $workspaceId . ' AND ' . $table . '.pid' . $pidOperator . '-1';
		}
		return $whereClause;
	}

	/**
	 * Count number of versions on a page
	 *
	 * @param int $workspace Workspace ID
	 * @param int $pageId Page ID
	 * @return array Overview of records
	 */
	static public function countVersionsOfRecordsOnPage($workspace, $pageId) {
		if ((int)$workspace === 0) {
			return array();
		}
		$output = array();
		foreach ($GLOBALS['TCA'] as $tableName => $cfg) {
			if ($tableName != 'pages' && $cfg['ctrl']['versioningWS']) {
				$joinStatement = 'A.t3ver_oid=B.uid';
				// Consider records that are moved to a different page
				if (self::isTableMovePlaceholderAware($tableName)) {
					$movePointer = new VersionState(VersionState::MOVE_POINTER);
					$joinStatement = '(A.t3ver_oid=B.uid AND A.t3ver_state<>' . $movePointer
						. ' OR A.t3ver_oid=B.t3ver_move_id AND A.t3ver_state=' . $movePointer . ')';
				}
				// Select all records from this table in the database from the workspace
				// This joins the online version with the offline version as tables A and B
				$output[$tableName] = static::getDatabaseConnection()->exec_SELECTgetRows(
					'B.uid as live_uid, A.uid as offline_uid',
					$tableName . ' A,' . $tableName . ' B',
					'A.pid=-1' . ' AND B.pid=' . (int)$pageId
						. ' AND A.t3ver_wsid=' . (int)$workspace . ' AND ' . $joinStatement
						. self::deleteClause($tableName, 'A') . self::deleteClause($tableName, 'B')
				);
				if (!is_array($output[$tableName]) || !count($output[$tableName])) {
					unset($output[$tableName]);
				}
			}
		}
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_befunc.php']['countVersionsOfRecordsOnPage'])) {
			$reference = NULL;
			$parameters = array(
				'workspace' => 'workspace',
				'pageId' => $pageId,
				'versions' => &$output,
			);
			foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_befunc.php']['countVersionsOfRecordsOnPage'] as $hookFunction) {
				GeneralUtility::callUserFunction($hookFunction, $parameters, $reference);
			}
		}
		return $output;
	}

	/**
	 * Performs mapping of new uids to new versions UID in case of import inside a workspace.
	 *
	 * @param string $table Table name
	 * @param int $uid Record uid (of live record placeholder)
	 * @return int Uid of offline version if any, otherwise live uid.
	 */
	static public function wsMapId($table, $uid) {
		$wsRec = self::getWorkspaceVersionOfRecord(static::getBackendUserAuthentication()->workspace, $table, $uid, 'uid');
		return is_array($wsRec) ? $wsRec['uid'] : $uid;
	}

	/**
	 * Returns move placeholder of online (live) version
	 *
	 * @param string $table Table name
	 * @param int $uid Record UID of online version
	 * @param string $fields Field list, default is *
	 * @return array If found, the record, otherwise nothing.
	 */
	static public function getMovePlaceholder($table, $uid, $fields = '*') {
		$workspace = static::getBackendUserAuthentication()->workspace;
		if ($workspace !== 0 && $GLOBALS['TCA'][$table] && (int)$GLOBALS['TCA'][$table]['ctrl']['versioningWS'] >= 2) {
			// Select workspace version of record:
			$row = static::getDatabaseConnection()->exec_SELECTgetSingleRow(
				$fields,
				$table,
				'pid<>-1 AND t3ver_state=' . new VersionState(VersionState::MOVE_PLACEHOLDER) . ' AND t3ver_move_id='
					. (int)$uid . ' AND t3ver_wsid=' . (int)$workspace . self::deleteClause($table)
			);
			if (is_array($row)) {
				return $row;
			}
		}
		return FALSE;
	}

	/*******************************************
	 *
	 * Miscellaneous
	 *
	 *******************************************/
	/**
	 * Prints TYPO3 Copyright notice for About Modules etc. modules.
	 *
	 * Warning:
	 * DO NOT prevent this notice from being shown in ANY WAY.
	 * According to the GPL license an interactive application must show such a notice on start-up ('If the program is interactive, make it output a short notice... ' - see GPL.txt)
	 * Therefore preventing this notice from being properly shown is a violation of the license, regardless of whether you remove it or use a stylesheet to obstruct the display.
	 *
	 * @param bool $showVersionNumber Display the version number within the copyright notice?
	 * @return string Text/Image (HTML) for copyright notice.
	 */
	static public function TYPO3_copyRightNotice($showVersionNumber = TRUE) {
		// Copyright Notice
		$loginCopyrightWarrantyProvider = strip_tags(trim($GLOBALS['TYPO3_CONF_VARS']['SYS']['loginCopyrightWarrantyProvider']));
		$loginCopyrightWarrantyURL = strip_tags(trim($GLOBALS['TYPO3_CONF_VARS']['SYS']['loginCopyrightWarrantyURL']));

		$lang = static::getLanguageService();
		$versionNumber = $showVersionNumber ?
				' ' . $lang->sL('LLL:EXT:lang/locallang_login.xlf:version.short') . ' ' .
				htmlspecialchars(TYPO3_version) : '';

		if (strlen($loginCopyrightWarrantyProvider) >= 2 && strlen($loginCopyrightWarrantyURL) >= 10) {
			$warrantyNote = sprintf($lang->sL('LLL:EXT:lang/locallang_login.xlf:warranty.by'), htmlspecialchars($loginCopyrightWarrantyProvider), '<a href="' . htmlspecialchars($loginCopyrightWarrantyURL) . '" target="_blank">', '</a>');
		} else {
			$warrantyNote = sprintf($lang->sL('LLL:EXT:lang/locallang_login.xlf:no.warranty'), '<a href="' . TYPO3_URL_LICENSE . '" target="_blank">', '</a>');
		}
		$cNotice = '<a href="' . TYPO3_URL_GENERAL . '" target="_blank">' .
				$lang->sL('LLL:EXT:lang/locallang_login.xlf:typo3.cms') . $versionNumber . '</a>. ' .
				$lang->sL('LLL:EXT:lang/locallang_login.xlf:copyright') . ' &copy; ' . htmlspecialchars(TYPO3_copyright_year) . ' Kasper Sk&aring;rh&oslash;j. ' .
				$lang->sL('LLL:EXT:lang/locallang_login.xlf:extension.copyright') . ' ' .
				sprintf($lang->sL('LLL:EXT:lang/locallang_login.xlf:details.link'), ('<a href="' . TYPO3_URL_GENERAL . '" target="_blank">' . TYPO3_URL_GENERAL . '</a>')) . ' ' .
				strip_tags($warrantyNote, '<a>') . ' ' .
				sprintf($lang->sL('LLL:EXT:lang/locallang_login.xlf:free.software'), ('<a href="' . TYPO3_URL_LICENSE . '" target="_blank">'), '</a> ') .
				$lang->sL('LLL:EXT:lang/locallang_login.xlf:keep.notice');
		return $cNotice;
	}

	/**
	 * Returns "web" if the $path (absolute) is within the DOCUMENT ROOT - and thereby qualifies as a "web" folder.
	 *
	 * @param string $path Path to evaluate
	 * @return bool
	 */
	static public function getPathType_web_nonweb($path) {
		return GeneralUtility::isFirstPartOfStr($path, GeneralUtility::getIndpEnv('TYPO3_DOCUMENT_ROOT')) ? 'web' : '';
	}

	/**
	 * Creates ADMCMD parameters for the "viewpage" extension / "cms" frontend
	 *
	 * @param array $pageInfo Page record
	 * @return string Query-parameters
	 * @internal
	 */
	static public function ADMCMD_previewCmds($pageInfo) {
		$simUser = '';
		$simTime = '';
		if ($pageInfo['fe_group'] > 0) {
			$simUser = '&ADMCMD_simUser=' . $pageInfo['fe_group'];
		}
		if ($pageInfo['starttime'] > $GLOBALS['EXEC_TIME']) {
			$simTime = '&ADMCMD_simTime=' . $pageInfo['starttime'];
		}
		if ($pageInfo['endtime'] < $GLOBALS['EXEC_TIME'] && $pageInfo['endtime'] != 0) {
			$simTime = '&ADMCMD_simTime=' . ($pageInfo['endtime'] - 1);
		}
		return $simUser . $simTime;
	}

	/**
	 * Returns an array with key=>values based on input text $params
	 * $params is exploded by line-breaks and each line is supposed to be on the syntax [key] = [some value]
	 * These pairs will be parsed into an array an returned.
	 *
	 * @param string $params String of parameters on multiple lines to parse into key-value pairs (see function description)
	 * @return array
	 */
	static public function processParams($params) {
		$paramArr = array();
		$lines = explode(LF, $params);
		foreach ($lines as $val) {
			$val = trim($val);
			if ($val) {
				$pair = explode('=', $val, 2);
				$paramArr[trim($pair[0])] = trim($pair[1]);
			}
		}
		return $paramArr;
	}

	/**
	 * Returns the name of the backend script relative to the TYPO3 main directory.
	 *
	 * @param string $interface Name of the backend interface  (backend, frontend) to look up the script name for. If no interface is given, the interface for the current backend user is used.
	 * @return string The name of the backend script relative to the TYPO3 main directory.
	 */
	static public function getBackendScript($interface = '') {
		if (!$interface) {
			$interface = static::getBackendUserAuthentication()->uc['interfaceSetup'];
		}
		switch ($interface) {
			case 'frontend':
				$script = '../.';
				break;
			case 'backend':

			default:
				$script = 'backend.php';
		}
		return $script;
	}

	/**
	 * Determines whether a table is enabled for workspaces.
	 *
	 * @param string $table Name of the table to be checked
	 * @return bool
	 */
	static public function isTableWorkspaceEnabled($table) {
		return !empty($GLOBALS['TCA'][$table]['ctrl']['versioningWS']);
	}

	/**
	 * Determines whether a table is aware of using move placeholders,
	 * which means 'versioningWS' is set to 2.
	 *
	 * @param string $table
	 * @return bool
	 */
	static public function isTableMovePlaceholderAware($table) {
		return (self::isTableWorkspaceEnabled($table) && (int)$GLOBALS['TCA'][$table]['ctrl']['versioningWS'] === 2);
	}

	/**
	 * Gets the TCA configuration of a field.
	 *
	 * @param string $table Name of the table
	 * @param string $field Name of the field
	 * @return array
	 */
	static public function getTcaFieldConfiguration($table, $field) {
		$configuration = array();
		if (isset($GLOBALS['TCA'][$table]['columns'][$field]['config'])) {
			$configuration = $GLOBALS['TCA'][$table]['columns'][$field]['config'];
		}
		return $configuration;
	}

	/**
	 * Whether to ignore restrictions on a web-mount of a table.
	 * The regular behaviour is that records to be accessed need to be
	 * in a valid user's web-mount.
	 *
	 * @param string $table Name of the table
	 * @return bool
	 */
	static public function isWebMountRestrictionIgnored($table) {
		return !empty($GLOBALS['TCA'][$table]['ctrl']['security']['ignoreWebMountRestriction']);
	}

	/**
	 * Whether to ignore restrictions on root-level records.
	 * The regular behaviour is that records on the root-level (page-id 0)
	 * only can be accessed by admin users.
	 *
	 * @param string $table Name of the table
	 * @return bool
	 */
	static public function isRootLevelRestrictionIgnored($table) {
		return !empty($GLOBALS['TCA'][$table]['ctrl']['security']['ignoreRootLevelRestriction']);
	}

	/**
	 * Get the SignalSlot dispatcher
	 *
	 * @return \TYPO3\CMS\Extbase\SignalSlot\Dispatcher
	 */
	static protected function getSignalSlotDispatcher() {
		return GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\SignalSlot\Dispatcher::class);
	}

	/**
	 * Emits signal to modify the page TSconfig before include
	 *
	 * @param array $TSdataArray Current TSconfig data array - Can be modified by slots!
	 * @param int $id Page ID we are handling
	 * @param array $rootLine Rootline array of page
	 * @param bool $returnPartArray Whether TSdata should be parsed by TS parser or returned as plain text
	 * @return array Modified Data array
	 */
	static protected function emitGetPagesTSconfigPreIncludeSignal(array $TSdataArray, $id, array $rootLine, $returnPartArray) {
		$signalArguments = static::getSignalSlotDispatcher()->dispatch(__CLASS__, 'getPagesTSconfigPreInclude', array($TSdataArray, $id, $rootLine, $returnPartArray));
		return $signalArguments[0];
	}

	/**
	 * @return DatabaseConnection
	 */
	static protected function getDatabaseConnection() {
		return $GLOBALS['TYPO3_DB'];
	}

	/**
	 * @return LanguageService
	 */
	static protected function getLanguageService() {
		return $GLOBALS['LANG'];
	}

	/**
	 * @return BackendUserAuthentication
	 */
	static protected function getBackendUserAuthentication() {
		return $GLOBALS['BE_USER'];
	}

	/**
	 * @return DocumentTemplate
	 */
	static protected function getDocumentTemplate() {
		return $GLOBALS['TBE_TEMPLATE'];
	}

}
