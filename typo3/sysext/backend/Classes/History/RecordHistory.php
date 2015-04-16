<?php
namespace TYPO3\CMS\Backend\History;

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
use TYPO3\CMS\Backend\Utility\IconUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class for the record history display module show_rechis
 *
 * @author Sebastian Kurfürst <sebastian@garbage-group.de>
 */
class RecordHistory {

	/**
	 * Maximum number of sys_history steps to show.
	 *
	 * @var int
	 */
	public $maxSteps = 20;

	/**
	 * Display diff or not (0-no diff, 1-inline)
	 *
	 * @var int
	 */
	public $showDiff = 1;

	/**
	 * On a pages table - show sub elements as well.
	 *
	 * @var int
	 */
	public $showSubElements = 1;

	/**
	 * Show inserts and deletes as well
	 *
	 * @var int
	 */
	public $showInsertDelete = 1;

	/**
	 * Element reference, syntax [tablename]:[uid]
	 *
	 * @var string
	 */
	public $element;

	/**
	 * syslog ID which is not shown anymore
	 *
	 * @var int
	 */
	public $lastSyslogId;

	/**
	 * @var string
	 */
	public $returnUrl;

	/**
	 * @var array
	 */
	public $changeLog;

	/**
	 * @var bool
	 */
	public $showMarked = FALSE;

	/**
	 * @var array
	 */
	protected $recordCache = array();

	/**
	 * @var array
	 */
	protected $pageAccessCache = array();

	/**
	 * Constructor for the class
	 */
	public function __construct() {
		// GPvars:
		$this->element = $this->getArgument('element');
		$this->returnUrl = $this->getArgument('returnUrl');
		$this->lastSyslogId = $this->getArgument('diff');
		$this->rollbackFields = $this->getArgument('rollbackFields');
		// Resolve sh_uid if set
		$this->resolveShUid();
	}

	/**
	 * Main function for the listing of history.
	 * It detects incoming variables like element reference, history element uid etc. and renders the correct screen.
	 *
	 * @return HTML content for the module
	 */
	public function main() {
		$content = '';
		// Single-click rollback
		if ($this->getArgument('revert') && $this->getArgument('sumUp')) {
			$this->rollbackFields = $this->getArgument('revert');
			$this->showInsertDelete = 0;
			$this->showSubElements = 0;
			$element = explode(':', $this->element);
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'sys_history', 'tablename=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($element[0], 'sys_history') . ' AND recuid=' . (int)$element[1], '', 'uid DESC', '1');
			$record = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
			$this->lastSyslogId = $record['sys_log_uid'];
			$this->createChangeLog();
			$completeDiff = $this->createMultipleDiff();
			$this->performRollback($completeDiff);
			\TYPO3\CMS\Core\Utility\HttpUtility::redirect($this->returnUrl);
		}
		// Save snapshot
		if ($this->getArgument('highlight') && !$this->getArgument('settings')) {
			$this->toggleHighlight($this->getArgument('highlight'));
		}
		$content .= $this->displaySettings();
		if ($this->createChangeLog()) {
			if ($this->rollbackFields) {
				$completeDiff = $this->createMultipleDiff();
				$content .= $this->performRollback($completeDiff);
			}
			if ($this->lastSyslogId) {
				$completeDiff = $this->createMultipleDiff();
				$content .= $this->displayMultipleDiff($completeDiff);
			}
			if ($this->element) {
				$content .= $this->displayHistory();
			}
		}
		return $content;
	}

	/*******************************
	 *
	 * database actions
	 *
	 *******************************/
	/**
	 * Toggles highlight state of record
	 *
	 * @param int $uid Uid of sys_history entry
	 * @return void
	 */
	public function toggleHighlight($uid) {
		$uid = (int)$uid;
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('snapshot', 'sys_history', 'uid=' . $uid);
		$tmp = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		$GLOBALS['TYPO3_DB']->exec_UPDATEquery('sys_history', 'uid=' . $uid, array('snapshot' => !$tmp['snapshot']));
	}

	/**
	 * perform rollback
	 *
	 * @param array $diff Diff array to rollback
	 * @return void
	 * @access private
	 */
	public function performRollback($diff) {
		if (!$this->rollbackFields) {
			return 0;
		}
		$reloadPageFrame = 0;
		$rollbackData = explode(':', $this->rollbackFields);
		// PROCESS INSERTS AND DELETES
		// rewrite inserts and deletes
		$cmdmapArray = array();
		if ($diff['insertsDeletes']) {
			switch (count($rollbackData)) {
				case 1:
					// all tables
					$data = $diff['insertsDeletes'];
					break;
				case 2:
					// one record
					if ($diff['insertsDeletes'][$this->rollbackFields]) {
						$data[$this->rollbackFields] = $diff['insertsDeletes'][$this->rollbackFields];
					}
					break;
				case 3:
					// one field in one record -- ignore!
					break;
			}
			if ($data) {
				foreach ($data as $key => $action) {
					$elParts = explode(':', $key);
					if ($action == 1) {
						// inserted records should be deleted
						$cmdmapArray[$elParts[0]][$elParts[1]]['delete'] = 1;
						// When the record is deleted, the contents of the record do not need to be updated
						unset($diff['oldData'][$key]);
						unset($diff['newData'][$key]);
					} elseif ($action == -1) {
						// deleted records should be inserted again
						$cmdmapArray[$elParts[0]][$elParts[1]]['undelete'] = 1;
					}
				}
			}
		}
		// Writes the data:
		if ($cmdmapArray) {
			$tce = GeneralUtility::makeInstance(\TYPO3\CMS\Core\DataHandling\DataHandler::class);
			$tce->stripslashes_values = 0;
			$tce->debug = 0;
			$tce->dontProcessTransformations = 1;
			$tce->start(array(), $cmdmapArray);
			$tce->process_cmdmap();
			unset($tce);
			if (isset($cmdmapArray['pages'])) {
				$reloadPageFrame = 1;
			}
		}
		// PROCESS CHANGES
		// create an array for process_datamap
		$diff_modified = array();
		foreach ($diff['oldData'] as $key => $value) {
			$splitKey = explode(':', $key);
			$diff_modified[$splitKey[0]][$splitKey[1]] = $value;
		}
		switch (count($rollbackData)) {
			case 1:
				// all tables
				$data = $diff_modified;
				break;
			case 2:
				// one record
				$data[$rollbackData[0]][$rollbackData[1]] = $diff_modified[$rollbackData[0]][$rollbackData[1]];
				break;
			case 3:
				// one field in one record
				$data[$rollbackData[0]][$rollbackData[1]][$rollbackData[2]] = $diff_modified[$rollbackData[0]][$rollbackData[1]][$rollbackData[2]];
				break;
		}
		// Removing fields:
		$data = $this->removeFilefields($rollbackData[0], $data);
		// Writes the data:
		$tce = GeneralUtility::makeInstance(\TYPO3\CMS\Core\DataHandling\DataHandler::class);
		$tce->stripslashes_values = 0;
		$tce->debug = 0;
		$tce->dontProcessTransformations = 1;
		$tce->start($data, array());
		$tce->process_datamap();
		unset($tce);
		if (isset($data['pages'])) {
			$reloadPageFrame = 1;
		}
		// Return to normal operation
		$this->lastSyslogId = FALSE;
		$this->rollbackFields = FALSE;
		$this->createChangeLog();
		// Reload page frame if necessary
		if ($reloadPageFrame) {
			return '<script type="text/javascript">
			/*<![CDATA[*/
			if (top.content && top.content.nav_frame && top.content.nav_frame.refresh_nav) {
				top.content.nav_frame.refresh_nav();
			}
			/*]]>*/
			</script>';
		}
	}

	/*******************************
	 *
	 * Display functions
	 *
	 *******************************/
	/**
	 * Displays settings
	 *
	 * @return string HTML code to modify settings
	 */
	public function displaySettings() {
		// Get current selection from UC, merge data, write it back to UC
		$currentSelection = is_array($GLOBALS['BE_USER']->uc['moduleData']['history']) ? $GLOBALS['BE_USER']->uc['moduleData']['history'] : array('maxSteps' => '', 'showDiff' => 1, 'showSubElements' => 1, 'showInsertDelete' => 1);
		$currentSelectionOverride = $this->getArgument('settings');
		if ($currentSelectionOverride) {
			$currentSelection = array_merge($currentSelection, $currentSelectionOverride);
			$GLOBALS['BE_USER']->uc['moduleData']['history'] = $currentSelection;
			$GLOBALS['BE_USER']->writeUC($GLOBALS['BE_USER']->uc);
		}
		// Display selector for number of history entries
		$selector['maxSteps'] = array(
			10 => 10,
			20 => 20,
			50 => 50,
			100 => 100,
			'' => 'maxSteps_all',
			'marked' => 'maxSteps_marked'
		);
		$selector['showDiff'] = array(
			0 => 'showDiff_no',
			1 => 'showDiff_inline'
		);
		$selector['showSubElements'] = array(
			0 => 'no',
			1 => 'yes'
		);
		$selector['showInsertDelete'] = array(
			0 => 'no',
			1 => 'yes'
		);
		// render selectors
		$displayCode = '';
		foreach ($selector as $key => $values) {
			$displayCode .= '<tr><td>' . $GLOBALS['LANG']->getLL($key, 1) . '</td>';
			$displayCode .= '<td><select name="settings[' . $key . ']" onChange="document.settings.submit()" style="width:100px">';
			foreach ($values as $singleKey => $singleVal) {
				$caption = $GLOBALS['LANG']->getLL($singleVal, 1) ?: $singleVal;
				$displayCode .= '<option value="' . $singleKey . '"' . ($singleKey == $currentSelection[$key] ? ' selected="selected"' : '') . '> ' . $caption . '</option>';
			}
			$displayCode .= '</select></td></tr>';
		}
		// set values correctly
		if ($currentSelection['maxSteps'] != 'marked') {
			$this->maxSteps = $currentSelection['maxSteps'] ? (int)$currentSelection['maxSteps'] : '';
		} else {
			$this->showMarked = TRUE;
			$this->maxSteps = FALSE;
		}
		$this->showDiff = (int)$currentSelection['showDiff'];
		$this->showSubElements = (int)$currentSelection['showSubElements'];
		$this->showInsertDelete = (int)$currentSelection['showInsertDelete'];
		$content = '';
		// Get link to page history if the element history is shown
		$elParts = explode(':', $this->element);
		if (!empty($this->element) && $elParts[0] != 'pages') {
			$content .= '<strong>' . $GLOBALS['LANG']->getLL('elementHistory', 1) . '</strong><br />';
			$pid = $this->getRecord($elParts[0], $elParts[1]);

			if ($this->hasPageAccess('pages', $pid['pid'])) {
				$content .= $this->linkPage($GLOBALS['LANG']->getLL('elementHistory_link', 1), array('element' => 'pages:' . $pid['pid']));
			}
		}
		$content .= '<form name="settings" action="' . htmlspecialchars(GeneralUtility::getIndpEnv('TYPO3_REQUEST_URL')) . '" method="post"><table>' . $displayCode . '</table></form>';
		return $GLOBALS['SOBE']->doc->section($GLOBALS['LANG']->getLL('settings', 1), $content, FALSE, TRUE, FALSE, FALSE);
	}

	/**
	 * Shows the full change log
	 *
	 * @return string HTML for list, wrapped in a table.
	 */
	public function displayHistory() {
		$lines = array();
		// Initialize:
		$lines[] = '<thead><tr>
				<th> </th>
				<th>' . $GLOBALS['LANG']->getLL('time', 1) . '</th>
				<th>' . $GLOBALS['LANG']->getLL('age', 1) . '</th>
				<th>' . $GLOBALS['LANG']->getLL('user', 1) . '</th>
				<th>' . $GLOBALS['LANG']->getLL('tableUid', 1) . '</th>
				<th>' . $GLOBALS['LANG']->getLL('differences', 1) . '</th>
				<th>&nbsp;</th>
			</tr></thead>';
		$be_user_array = BackendUtility::getUserNames();
		// Traverse changelog array:
		if (!$this->changeLog) {
			return 0;
		}
		$i = 0;
		foreach ($this->changeLog as $sysLogUid => $entry) {
			// stop after maxSteps
			if ($i > $this->maxSteps && $this->maxSteps) {
				break;
			}
			// Show only marked states
			if (!$entry['snapshot'] && $this->showMarked) {
				continue;
			}
			$i++;
			// Get user names
			$userName = $entry['user'] ? $be_user_array[$entry['user']]['username'] : $GLOBALS['LANG']->getLL('externalChange', 1);
			// Build up single line
			$singleLine = array();
			// Diff link
			$image = IconUtility::getSpriteIcon('actions-view-go-forward', array('title' => $GLOBALS['LANG']->getLL('sumUpChanges', TRUE)));
			$singleLine[] = '<span>' . $this->linkPage($image, array('diff' => $sysLogUid)) . '</span>';
			// remove first link
			$singleLine[] = htmlspecialchars(BackendUtility::datetime($entry['tstamp']));
			// add time
			$singleLine[] = htmlspecialchars(BackendUtility::calcAge($GLOBALS['EXEC_TIME'] - $entry['tstamp'], $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:labels.minutesHoursDaysYears')));
			// add age
			$singleLine[] = htmlspecialchars($userName);
			// add user name
			$singleLine[] = $this->linkPage($this->generateTitle($entry['tablename'], $entry['recuid']), array('element' => $entry['tablename'] . ':' . $entry['recuid']), '', $GLOBALS['LANG']->getLL('linkRecordHistory', 1));
			// add record UID
			// Show insert/delete/diff/changed field names
			if ($entry['action']) {
				// insert or delete of element
				$singleLine[] = '<strong>' . htmlspecialchars($GLOBALS['LANG']->getLL($entry['action'], 1)) . '</strong>';
			} else {
				// Display field names instead of full diff
				if (!$this->showDiff) {
					// Re-write field names with labels
					$tmpFieldList = explode(',', $entry['fieldlist']);
					foreach ($tmpFieldList as $key => $value) {
						$tmp = str_replace(':', '', $GLOBALS['LANG']->sl(BackendUtility::getItemLabel($entry['tablename'], $value), 1));
						if ($tmp) {
							$tmpFieldList[$key] = $tmp;
						} else {
							// remove fields if no label available
							unset($tmpFieldList[$key]);
						}
					}
					$singleLine[] = htmlspecialchars(implode(',', $tmpFieldList));
				} else {
					// Display diff
					$diff = $this->renderDiff($entry, $entry['tablename']);
					$singleLine[] = $diff;
				}
			}
			// Show link to mark/unmark state
			if (!$entry['action']) {
				if ($entry['snapshot']) {
                    $image =  IconUtility::getSpriteIcon('actions-unmarkstate', array('title' => $GLOBALS['LANG']->getLL('unmarkState', TRUE)), array());
				} else {
                    $image =  IconUtility::getSpriteIcon('actions-markstate', array('title' => $GLOBALS['LANG']->getLL('markState', TRUE)), array());
				}
				$singleLine[] = $this->linkPage($image, array('highlight' => $entry['uid']));
			} else {
				$singleLine[] = '';
			}
			// put line together
			$lines[] = '
				<tr>
					<td>' . implode('</td><td>', $singleLine) . '</td>
				</tr>';
		}
		// Finally, put it all together:
		$theCode = '
			<!--
				History (list):
			-->
			<table class="table table-striped table-hover" id="typo3-history">
				' . implode('', $lines) . '
			</table>';
		if ($this->lastSyslogId) {
			$theCode .= '<br />' . $this->linkPage(IconUtility::getSpriteIcon('actions-move-to-bottom', array('title' => $GLOBALS['LANG']->getLL('fullView', TRUE))), array('diff' => ''));
		}
		// Add message about the difference view.
		$flashMessage = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Messaging\FlashMessage::class, $GLOBALS['LANG']->getLL('differenceMsg'), '', \TYPO3\CMS\Core\Messaging\FlashMessage::INFO);
		$theCode .= '<br /><br />' . $flashMessage->render() . '<br />';
		// Add the whole content as a module section:
		return $GLOBALS['SOBE']->doc->section($GLOBALS['LANG']->getLL('changes'), $theCode, FALSE, TRUE);
	}

	/**
	 * Displays a diff over multiple fields including rollback links
	 *
	 * @param array $diff Difference array
	 * @return string HTML output
	 */
	public function displayMultipleDiff($diff) {
		$content = '';
		// Get all array keys needed
		$arrayKeys = array_merge(array_keys($diff['newData']), array_keys($diff['insertsDeletes']), array_keys($diff['oldData']));
		$arrayKeys = array_unique($arrayKeys);
		if ($arrayKeys) {
			foreach ($arrayKeys as $key) {
				$record = '';
				$elParts = explode(':', $key);
				// Turn around diff because it should be a "rollback preview"
				if ($diff['insertsDeletes'][$key] == 1) {
					// insert
					$record .= '<strong>' . $GLOBALS['LANG']->getLL('delete', 1) . '</strong>';
					$record .= '<br />';
				} elseif ($diff['insertsDeletes'][$key] == -1) {
					$record .= '<strong>' . $GLOBALS['LANG']->getLL('insert', 1) . '</strong>';
					$record .= '<br />';
				}
				// Build up temporary diff array
				// turn around diff because it should be a "rollback preview"
				if ($diff['newData'][$key]) {
					$tmpArr['newRecord'] = $diff['oldData'][$key];
					$tmpArr['oldRecord'] = $diff['newData'][$key];
					$record .= $this->renderDiff($tmpArr, $elParts[0], $elParts[1]);
				}
				$elParts = explode(':', $key);
				$titleLine = $this->createRollbackLink($key, $GLOBALS['LANG']->getLL('revertRecord', 1), 1) . $this->generateTitle($elParts[0], $elParts[1]);
				$record = '<div style="margin-left:10px;padding-left:5px;border-left:1px solid black;border-bottom:1px dotted black;padding-bottom:2px;">' . $record . '</div>';
				$content .= $GLOBALS['SOBE']->doc->section($titleLine, $record, FALSE, FALSE, FALSE, TRUE);
			}
			$content = $this->createRollbackLink('ALL', $GLOBALS['LANG']->getLL('revertAll', 1), 0) . '<div style="margin-left:10px;padding-left:5px;border-left:1px solid black;border-bottom:1px dotted black;padding-bottom:2px;">' . $content . '</div>';
		} else {
			$content = $GLOBALS['LANG']->getLL('noDifferences', 1);
		}
		return $GLOBALS['SOBE']->doc->section($GLOBALS['LANG']->getLL('mergedDifferences', 1), $content, FALSE, TRUE, FALSE, TRUE);
	}

	/**
	 * Renders HTML table-rows with the comparison information of an sys_history entry record
	 *
	 * @param array $entry sys_history entry record.
	 * @param string $table The table name
	 * @param int $rollbackUid If set to UID of record, display rollback links
	 * @return string HTML table
	 * @access private
	 */
	public function renderDiff($entry, $table, $rollbackUid = 0) {
		$lines = array();
		if (is_array($entry['newRecord'])) {
			$t3lib_diff_Obj = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Utility\DiffUtility::class);
			$fieldsToDisplay = array_keys($entry['newRecord']);
			foreach ($fieldsToDisplay as $fN) {
				if (is_array($GLOBALS['TCA'][$table]['columns'][$fN]) && $GLOBALS['TCA'][$table]['columns'][$fN]['config']['type'] != 'passthrough') {
					// Create diff-result:
					$diffres = $t3lib_diff_Obj->makeDiffDisplay(BackendUtility::getProcessedValue($table, $fN, $entry['oldRecord'][$fN], 0, 1), BackendUtility::getProcessedValue($table, $fN, $entry['newRecord'][$fN], 0, 1));
					$lines[] = '
						<tr class="bgColor4">
						' . ($rollbackUid ? '<td style="width:33px">' . $this->createRollbackLink(($table . ':' . $rollbackUid . ':' . $fN), $GLOBALS['LANG']->getLL('revertField', 1), 2) . '</td>' : '') . '
							<td style="width:90px"><em>' . $GLOBALS['LANG']->sl(BackendUtility::getItemLabel($table, $fN), 1) . '</em></td>
							<td style="width:300px">' . nl2br($diffres) . '</td>
						</tr>';
				}
			}
		}
		if ($lines) {
			$content = '<table border="0" cellpadding="2" cellspacing="2" id="typo3-history-item">
					' . implode('', $lines) . '
				</table>';
			return $content;
		}
		// error fallback
		return NULL;
	}

	/*******************************
	 *
	 * build up history
	 *
	 *******************************/
	/**
	 * Creates a diff between the current version of the records and the selected version
	 *
	 * @return array Diff for many elements, 0 if no changelog is found
	 */
	public function createMultipleDiff() {
		$insertsDeletes = array();
		$newArr = array();
		$differences = array();
		if (!$this->changeLog) {
			return 0;
		}
		// traverse changelog array
		foreach ($this->changeLog as $key => $value) {
			$field = $value['tablename'] . ':' . $value['recuid'];
			// inserts / deletes
			if ($value['action']) {
				if (!$insertsDeletes[$field]) {
					$insertsDeletes[$field] = 0;
				}
				if ($value['action'] == 'insert') {
					$insertsDeletes[$field]++;
				} else {
					$insertsDeletes[$field]--;
				}
				// unset not needed fields
				if ($insertsDeletes[$field] == 0) {
					unset($insertsDeletes[$field]);
				}
			} else {
				// update fields
				// first row of field
				if (!isset($newArr[$field])) {
					$newArr[$field] = $value['newRecord'];
					$differences[$field] = $value['oldRecord'];
				} else {
					// standard
					$differences[$field] = array_merge($differences[$field], $value['oldRecord']);
				}
			}
		}
		// remove entries where there were no changes effectively
		foreach ($newArr as $record => $value) {
			foreach ($value as $key => $innerVal) {
				if ($newArr[$record][$key] == $differences[$record][$key]) {
					unset($newArr[$record][$key]);
					unset($differences[$record][$key]);
				}
			}
			if (empty($newArr[$record]) && empty($differences[$record])) {
				unset($newArr[$record]);
				unset($differences[$record]);
			}
		}
		return array(
			'newData' => $newArr,
			'oldData' => $differences,
			'insertsDeletes' => $insertsDeletes
		);
	}

	/**
	 * Creates change log including sub-elements, filling $this->changeLog
	 *
	 * @return int
	 */
	public function createChangeLog() {
		$elParts = explode(':', $this->element);

		if (empty($this->element)) {
			return 0;
		}

		$changeLog = $this->getHistoryData($elParts[0], $elParts[1]);
		// get history of tables of this page and merge it into changelog
		if ($elParts[0] == 'pages' && $this->showSubElements && $this->hasPageAccess('pages', $elParts[1])) {
			foreach ($GLOBALS['TCA'] as $tablename => $value) {
				// check if there are records on the page
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid', $tablename, 'pid=' . (int)$elParts[1]);
				while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
					// if there is history data available, merge it into changelog
					if ($newChangeLog = $this->getHistoryData($tablename, $row['uid'])) {
						foreach ($newChangeLog as $key => $value) {
							$changeLog[$key] = $value;
						}
					}
				}
			}
		}
		if (!$changeLog) {
			return 0;
		}
		krsort($changeLog);
		$this->changeLog = $changeLog;
		return 1;
	}

	/**
	 * Gets history and delete/insert data from sys_log and sys_history
	 *
	 * @param string $table DB table name
	 * @param int $uid UID of record
	 * @return array history data of the record
	 */
	public function getHistoryData($table, $uid) {
		// If table is found in $GLOBALS['TCA']:
		if ($GLOBALS['TCA'][$table] && $this->hasTableAccess($table) && $this->hasPageAccess($table, $uid)) {
			$uid = $this->resolveElement($table, $uid);
			// Selecting the $this->maxSteps most recent states:
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('sys_history.*, sys_log.userid', 'sys_history, sys_log', 'sys_history.sys_log_uid = sys_log.uid
							AND sys_history.tablename = ' . $GLOBALS['TYPO3_DB']->fullQuoteStr($table, 'sys_history') . '
							AND sys_history.recuid = ' . (int)$uid, '', 'sys_log.uid DESC', $this->maxSteps);
			// Traversing the result, building up changesArray / changeLog:
			$changeLog = array();
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				// Only history until a certain syslog ID needed
				if ($row['sys_log_uid'] < $this->lastSyslogId && $this->lastSyslogId) {
					continue;
				}
				$hisDat = unserialize($row['history_data']);
				if (is_array($hisDat['newRecord']) && is_array($hisDat['oldRecord'])) {
					// Add hisDat to the changeLog
					$hisDat['uid'] = $row['uid'];
					$hisDat['tstamp'] = $row['tstamp'];
					$hisDat['user'] = $row['userid'];
					$hisDat['snapshot'] = $row['snapshot'];
					$hisDat['fieldlist'] = $row['fieldlist'];
					$hisDat['tablename'] = $row['tablename'];
					$hisDat['recuid'] = $row['recuid'];
					$changeLog[$row['sys_log_uid']] = $hisDat;
				} else {
					debug('ERROR: [getHistoryData]');
					// error fallback
					return 0;
				}
			}
			// SELECT INSERTS/DELETES
			if ($this->showInsertDelete) {
				// Select most recent inserts and deletes // WITHOUT snapshots
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid, userid, action, tstamp', 'sys_log', 'type = 1
							AND (action=1 OR action=3)
							AND tablename = ' . $GLOBALS['TYPO3_DB']->fullQuoteStr($table, 'sys_log') . '
							AND recuid = ' . (int)$uid, '', 'uid DESC', $this->maxSteps);
				while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
					if ($row['uid'] < $this->lastSyslogId && $this->lastSyslogId) {
						continue;
					}
					$hisDat = array();
					switch ($row['action']) {
						case 1:
							// Insert
							$hisDat['action'] = 'insert';
							break;
						case 3:
							// Delete
							$hisDat['action'] = 'delete';
							break;
					}
					$hisDat['tstamp'] = $row['tstamp'];
					$hisDat['user'] = $row['userid'];
					$hisDat['tablename'] = $table;
					$hisDat['recuid'] = $uid;
					$changeLog[$row['uid']] = $hisDat;
				}
			}
			return $changeLog;
		}
		// error fallback
		return 0;
	}

	/*******************************
	 *
	 * Various helper functions
	 *
	 *******************************/
	/**
	 * Generates the title and puts the record title behind
	 *
	 * @param string $table
	 * @param string $uid
	 * @return string
	 */
	public function generateTitle($table, $uid) {
		$out = $table . ':' . $uid;
		if ($labelField = $GLOBALS['TCA'][$table]['ctrl']['label']) {
			$record = $this->getRecord($table, $uid);
			$out .= ' (' . BackendUtility::getRecordTitle($table, $record, TRUE) . ')';
		}
		return $out;
	}

	/**
	 * Creates a link for the rollback
	 *
	 * @param string $key Parameter which is set to rollbackFields
	 * @param string $alt Optional, alternative label and title tag of image
	 * @param int $type Optional, type of rollback: 0 - ALL; 1 - element; 2 - field
	 * @return string HTML output
	 */
	public function createRollbackLink($key, $alt = '', $type = 0) {
		return $this->linkPage('<img ' . IconUtility::skinImg('', ('gfx/revert_' . $type . '.gif'), 'width="33" height="33"') . ' alt="' . $alt . '" title="' . $alt . '" align="middle" />', array('rollbackFields' => $key));
	}

	/**
	 * Creates a link to the same page.
	 *
	 * @param string $str String to wrap in <a> tags (must be htmlspecialchars()'ed prior to calling function)
	 * @param array $inparams Array of key/value pairs to override the default values with.
	 * @param string $anchor Possible anchor value.
	 * @param string $title Possible title.
	 * @return string Link.
	 * @access private
	 */
	public function linkPage($str, $inparams = array(), $anchor = '', $title = '') {
		// Setting default values based on GET parameters:
		$params['element'] = $this->element;
		$params['returnUrl'] = $this->returnUrl;
		$params['diff'] = $this->lastSyslogId;
		// Mergin overriding values:
		$params = array_merge($params, $inparams);
		// Make the link:
		$link = BackendUtility::getModuleUrl('record_history', $params) . ($anchor ? '#' . $anchor : '');
		return '<a href="' . htmlspecialchars($link) . '"' . ($title ? ' title="' . $title . '"' : '') . '>' . $str . '</a>';
	}

	/**
	 * Will traverse the field names in $dataArray and look in $GLOBALS['TCA'] if the fields are of types which cannot be handled by the sys_history (that is currently group types with internal_type set to "file")
	 *
	 * @param string $table Table name
	 * @param array $dataArray The data array
	 * @return array The modified data array
	 * @access private
	 */
	public function removeFilefields($table, $dataArray) {
		if ($GLOBALS['TCA'][$table]) {
			foreach ($GLOBALS['TCA'][$table]['columns'] as $field => $config) {
				if ($config['config']['type'] == 'group' && $config['config']['internal_type'] == 'file') {
					unset($dataArray[$field]);
				}
			}
		}
		return $dataArray;
	}

	/**
	 * Convert input element reference to workspace version if any.
	 *
	 * @param string $table Table of input element
	 * @param int $uid UID of record
	 * @return int converted UID of record
	 */
	public function resolveElement($table, $uid) {
		if (isset($GLOBALS['TCA'][$table])) {
			if ($workspaceVersion = BackendUtility::getWorkspaceVersionOfRecord($GLOBALS['BE_USER']->workspace, $table, $uid, 'uid')) {
				$uid = $workspaceVersion['uid'];
			}
		}
		return $uid;
	}

	/**
	 * Resolve sh_uid (used from log)
	 *
	 * @return void
	 */
	public function resolveShUid() {
		if ($this->getArgument('sh_uid')) {
			$sh_uid = $this->getArgument('sh_uid');
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'sys_history', 'uid=' . (int)$sh_uid);
			$record = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
			$this->element = $record['tablename'] . ':' . $record['recuid'];
			$this->lastSyslogId = $record['sys_log_uid'] - 1;
		}
	}

	/**
	 * Determines whether user has access to a page.
	 *
	 * @param string $table
	 * @param int $uid
	 * @return bool
	 */
	protected function hasPageAccess($table, $uid) {
		$uid = (int)$uid;

		if ($table === 'pages') {
			$pageId = $uid;
		} else {
			$record = $this->getRecord($table, $uid);
			$pageId = $record['pid'];
		}

		if (!isset($this->pageAccessCache[$pageId])) {
			$this->pageAccessCache[$pageId] = BackendUtility::readPageAccess(
				$pageId, $this->getBackendUser()->getPagePermsClause(1)
			);
		}

		return ($this->pageAccessCache[$pageId] !== FALSE);
	}

	/**
	 * Determines whether user has access to a table.
	 *
	 * @param string $table
	 * @return bool
	 */
	protected function hasTableAccess($table) {
		return $this->getBackendUser()->check('tables_select', $table);
	}

	/**
	 * Gets a database record.
	 *
	 * @param string $table
	 * @param int $uid
	 * @return array|NULL
	 */
	protected function getRecord($table, $uid) {
		if (!isset($this->recordCache[$table][$uid])) {
			$this->recordCache[$table][$uid] = BackendUtility::getRecord($table, $uid, '*', '', FALSE);
		}
		return $this->recordCache[$table][$uid];
	}

	/**
	 * Gets the current backend user.
	 *
	 * @return \TYPO3\CMS\Core\Authentication\BackendUserAuthentication
	 */
	protected function getBackendUser() {
		return $GLOBALS['BE_USER'];
	}

	/**
	 * Fetches GET/POST arguments and sanitizes the values for
	 * the expected disposal. Invalid values will be converted
	 * to an empty string.
	 *
	 * @param string $name Name of the argument
	 * @return array|string|integer
	 */
	protected function getArgument($name) {
		$value = GeneralUtility::_GP($name);

		switch ($name) {
			case 'element':
				if ($value !== '' && !preg_match('#^[a-z0-9_.]+:[0-9]+$#i', $value)) {
					$value = '';
				}
				break;
			case 'rollbackFields':
			case 'revert':
				if ($value !== '' && !preg_match('#^[a-z0-9_.]+(:[0-9]+(:[a-z0-9_.]+)?)?$#i', $value)) {
					$value = '';
				}
				break;
			case 'returnUrl':
				$value = GeneralUtility::sanitizeLocalUrl($value);
				break;
			case 'diff':
			case 'highlight':
			case 'sh_uid':
				$value = (int)$value;
				break;
			case 'settings':
				if (!is_array($value)) {
					$value = array();
				}
				break;
			default:
				$value = '';
		}

		return $value;
	}

}
