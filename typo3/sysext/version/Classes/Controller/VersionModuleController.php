<?php
namespace TYPO3\CMS\Version\Controller;

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

use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\Utility\IconUtility;

/**
 * Versioning module, including workspace management
 *
 * @author Kasper Skårhøj <kasperYYYY@typo3.com>
 */
class VersionModuleController extends \TYPO3\CMS\Backend\Module\BaseScriptClass {

	/**
	 * Module configuration
	 *
	 * @var array
	 */
	public $MCONF = array();

	/**
	 * Module menu items
	 *
	 * @var array
	 */
	public $MOD_MENU = array();

	/**
	 * Module session settings
	 *
	 * @var array
	 */
	public $MOD_SETTINGS = array();

	/**
	 * document template object
	 *
	 * @var \TYPO3\CMS\Backend\Template\DocumentTemplate
	 */
	public $doc;

	/**
	 * @var string
	 */
	public $content;

	/**
	 * Accumulated content
	 *
	 * @var int
	 */
	public $showWorkspaceCol = 0;

	/**
	 * @var array
	 */
	public $formatWorkspace_cache = array();

	/**
	 * @var array
	 */
	public $formatCount_cache = array();

	/**
	 * @var array
	 */
	public $targets = array();

	/**
	 * Accumulation of online targets.
	 *
	 * @var string
	 */
	public $pageModule = '';

	/**
	 * Name of page module
	 *
	 * @var bool
	 */
	public $publishAccess = FALSE;

	/**
	 * @var array
	 */
	public $stageIndex = array();

	/**
	 * @var array
	 */
	public $recIndex = array();

	/**
	 * Initialize language files
	 */
	public function __construct() {
		$GLOBALS['LANG']->includeLLFile('EXT:version/locallang.xlf');
	}

	/**
	 * Initialize menu configuration
	 *
	 * @return void
	 */
	public function menuConfig() {
		// CLEANSE SETTINGS
		$this->MOD_SETTINGS = BackendUtility::getModuleData($this->MOD_MENU, GeneralUtility::_GP('SET'), $this->MCONF['name'], 'ses');
	}

	/**
	 * Main function of the module. Write the content to $this->content
	 *
	 * @return void
	 */
	public function main() {
		// Template markers
		$markers = array(
			'CSH' => '',
			'FUNC_MENU' => '',
			'WS_MENU' => '',
			'CONTENT' => ''
		);
		// Setting module configuration:
		$this->MCONF = $GLOBALS['MCONF'];
		$this->REQUEST_URI = str_replace('&sendToReview=1', '', GeneralUtility::getIndpEnv('REQUEST_URI'));
		// Draw the header.
		$this->doc = GeneralUtility::makeInstance(\TYPO3\CMS\Backend\Template\DocumentTemplate::class);
		$this->doc->backPath = $GLOBALS['BACK_PATH'];
		$this->doc->setModuleTemplate('EXT:version/Resources/Private/Templates/version.html');
		// Add styles
		$this->doc->inDocStylesArray[$GLOBALS['MCONF']['name']] = '
.version-diff-1 { background-color: green; }
.version-diff-2 { background-color: red; }
';
		// Setting up the context sensitive menu:
		$this->doc->getContextMenuCode();
		// Getting input data:
		$this->id = (int)GeneralUtility::_GP('id');

		// Record uid. Goes with table name to indicate specific record
		$this->uid = (int)GeneralUtility::_GP('uid');
		// // Record table. Goes with uid to indicate specific record
		$this->table = GeneralUtility::_GP('table');

		$this->details = GeneralUtility::_GP('details');
		// Page id. If set, indicates activation from Web>Versioning module
		$this->diffOnly = GeneralUtility::_GP('diffOnly');
		// Flag. If set, shows only the offline version and with diff-view
		// Force this setting:
		$this->MOD_SETTINGS['expandSubElements'] = TRUE;
		$this->MOD_SETTINGS['diff'] = $this->details || $this->MOD_SETTINGS['diff'] ? 1 : 0;
		// Reading the record:
		$record = BackendUtility::getRecord($this->table, $this->uid);
		if ($record['pid'] == -1) {
			$record = BackendUtility::getRecord($this->table, $record['t3ver_oid']);
		}
		$this->recordFound = is_array($record);
		$pidValue = $this->table === 'pages' ? $this->uid : $record['pid'];
		// Checking access etc.
		if ($this->recordFound && $GLOBALS['TCA'][$this->table]['ctrl']['versioningWS'] && !$this->id) {
			$this->doc->form = '<form action="" method="post">';
			$this->uid = $record['uid'];
			// Might have changed if new live record was found!
			// Access check!
			// The page will show only if there is a valid page and if this page may be viewed by the user
			$this->pageinfo = BackendUtility::readPageAccess($pidValue, $this->perms_clause);
			$access = is_array($this->pageinfo) ? 1 : 0;
			if ($pidValue && $access || $GLOBALS['BE_USER']->user['admin'] && !$pidValue) {
				// JavaScript
				$this->doc->JScode .= $this->doc->wrapScriptTags('

						function hlSubelements(origId, verId, over, diffLayer)	{	//
							if (over) {
								document.getElementById(\'orig_\'+origId).attributes.getNamedItem("class").nodeValue = \'typo3-ver-hl\';
								document.getElementById(\'ver_\'+verId).attributes.getNamedItem("class").nodeValue = \'typo3-ver-hl\';
								if (diffLayer) {
									document.getElementById(\'diff_\'+verId).style.visibility = \'visible\';
								}
							} else {
								document.getElementById(\'orig_\'+origId).attributes.getNamedItem("class").nodeValue = \'typo3-ver\';
								document.getElementById(\'ver_\'+verId).attributes.getNamedItem("class").nodeValue = \'typo3-ver\';
								if (diffLayer) {
									document.getElementById(\'diff_\'+verId).style.visibility = \'hidden\';
								}
							}
						}
				');
				// If another page module was specified, replace the default Page module with the new one
				$newPageModule = trim($GLOBALS['BE_USER']->getTSConfigVal('options.overridePageModule'));
				$this->pageModule = BackendUtility::isModuleSetInTBE_MODULES($newPageModule) ? $newPageModule : 'web_layout';
				// Setting publish access permission for workspace:
				$this->publishAccess = $GLOBALS['BE_USER']->workspacePublishAccess($GLOBALS['BE_USER']->workspace);
				$this->versioningMgm();
			}
			$this->content .= $this->doc->spacer(10);
			// Setting up the buttons and markers for docheader
			$docHeaderButtons = $this->getButtons();
			$markers['CSH'] = $docHeaderButtons['csh'];
			$markers['FUNC_MENU'] = BackendUtility::getFuncMenu($this->id, 'SET[function]', $this->MOD_SETTINGS['function'], $this->MOD_MENU['function']);
			$markers['CONTENT'] = $this->content;
		} else {
			// If no access or id value, create empty document
			$this->content = $this->doc->section($GLOBALS['LANG']->getLL('clickAPage_header'), $GLOBALS['LANG']->getLL('clickAPage_content'), 0, 1);
			// Setting up the buttons and markers for docheader
			$docHeaderButtons = $this->getButtons();
			$markers['CONTENT'] = $this->content;
		}
		// Build the <body> for the module
		$this->content = $this->doc->startPage($GLOBALS['LANG']->getLL('title'));
		$this->content .= $this->doc->moduleBody($this->pageinfo, $docHeaderButtons, $markers);
		$this->content .= $this->doc->endPage();
		$this->content = $this->doc->insertStylesAndJS($this->content);
	}

	/**
	 * Outputs accumulated module content to browser.
	 *
	 * @return void
	 */
	public function printContent() {
		echo $this->content;
	}

	/**
	 * Create the panel of buttons for submitting the form or otherwise perform operations.
	 *
	 * @return array All available buttons as an assoc. array
	 */
	protected function getButtons() {
		$buttons = array(
			'csh' => '',
			'view' => '',
			'record_list' => '',
			'shortcut' => ''
		);
		// CSH
		if ($this->recordFound && $GLOBALS['TCA'][$this->table]['ctrl']['versioningWS']) {
			// View page
			$buttons['view'] = '<a href="#" onclick="' . htmlspecialchars(BackendUtility::viewOnClick($this->pageinfo['uid'], $GLOBALS['BACK_PATH'], BackendUtility::BEgetRootLine($this->pageinfo['uid']))) . '" title="' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:labels.showPage', TRUE) . '">' . IconUtility::getSpriteIcon('actions-document-view') . '</a>';
			// Shortcut
			if ($GLOBALS['BE_USER']->mayMakeShortcut()) {
				$buttons['shortcut'] = $this->doc->makeShortcutIcon('id, edit_record, pointer, new_unique_uid, search_field, search_levels, showLimit', implode(',', array_keys($this->MOD_MENU)), $this->MCONF['name']);
			}
			// If access to Web>List for user, then link to that module.
			$buttons['record_list'] = BackendUtility::getListViewLink(array(
				'id' => $this->pageinfo['uid'],
				'returnUrl' => GeneralUtility::getIndpEnv('REQUEST_URI')
			), '', $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:labels.showList'));
		}
		return $buttons;
	}

	/******************************
	 *
	 * Versioning management
	 *
	 ******************************/
	/**
	 * Management of versions for record
	 *
	 * @return void
	 */
	public function versioningMgm() {
		// Diffing:
		$diff_1 = GeneralUtility::_POST('diff_1');
		$diff_2 = GeneralUtility::_POST('diff_2');
		if (GeneralUtility::_POST('do_diff')) {
			$content = '';
			$content .= '<h3>' . $GLOBALS['LANG']->getLL('diffing') . ':</h3>';
			if ($diff_1 && $diff_2) {
				$diff_1_record = BackendUtility::getRecord($this->table, $diff_1);
				$diff_2_record = BackendUtility::getRecord($this->table, $diff_2);
				if (is_array($diff_1_record) && is_array($diff_2_record)) {
					$t3lib_diff_Obj = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Utility\DiffUtility::class);
					$tRows = array();
					$tRows[] = '
									<tr class="bgColor5 tableheader">
										<td>' . $GLOBALS['LANG']->getLL('fieldname') . '</td>
										<td width="98%">' . $GLOBALS['LANG']->getLL('coloredDiffView') . ':</td>
									</tr>
								';
					foreach ($diff_1_record as $fN => $fV) {
						if ($GLOBALS['TCA'][$this->table]['columns'][$fN] && $GLOBALS['TCA'][$this->table]['columns'][$fN]['config']['type'] !== 'passthrough' && !GeneralUtility::inList('t3ver_label', $fN)) {
							if ((string)$diff_1_record[$fN] !== (string)$diff_2_record[$fN]) {
								$diffres = $t3lib_diff_Obj->makeDiffDisplay(BackendUtility::getProcessedValue($this->table, $fN, $diff_2_record[$fN], 0, 1), BackendUtility::getProcessedValue($this->table, $fN, $diff_1_record[$fN], 0, 1));
								$tRows[] = '
									<tr class="bgColor4">
										<td>' . $fN . '</td>
										<td width="98%">' . $diffres . '</td>
									</tr>
								';
							}
						}
					}
					if (count($tRows) > 1) {
						$content .= '<table border="0" cellpadding="1" cellspacing="1" width="100%">' . implode('', $tRows) . '</table><br /><br />';
					} else {
						$content .= $GLOBALS['LANG']->getLL('recordsMatchesCompletely');
					}
				} else {
					$content .= $GLOBALS['LANG']->getLL('errorRecordsNotFound');
				}
			} else {
				$content .= $GLOBALS['LANG']->getLL('errorDiffSources');
			}
		}
		// Element:
		$record = BackendUtility::getRecord($this->table, $this->uid);
		$recordIcon = IconUtility::getSpriteIconForRecord($this->table, $record);
		$recTitle = BackendUtility::getRecordTitle($this->table, $record, TRUE);
		// Display versions:
		$content .= '
			' . $recordIcon . $recTitle . '
			<form name="theform" action="' . str_replace('&sendToReview=1', '', $this->REQUEST_URI) . '" method="post">
			<table border="0" cellspacing="1" cellpadding="1">';
		$content .= '
				<tr class="bgColor5 tableheader">
					<td>&nbsp;</td>
					<td>&nbsp;</td>
					<td title="' . $GLOBALS['LANG']->getLL('tblHeaderDesc_title') . '">' . $GLOBALS['LANG']->getLL('tblHeader_title') . '</td>
					<td title="' . $GLOBALS['LANG']->getLL('tblHeaderDesc_uid') . '">' . $GLOBALS['LANG']->getLL('tblHeader_uid') . '</td>
					<td title="' . $GLOBALS['LANG']->getLL('tblHeaderDesc_t3ver_oid') . '">' . $GLOBALS['LANG']->getLL('tblHeader_t3ver_oid') . '</td>
					<td title="' . $GLOBALS['LANG']->getLL('tblHeaderDesc_t3ver_id') . '">' . $GLOBALS['LANG']->getLL('tblHeader_t3ver_id') . '</td>
					<td title="' . $GLOBALS['LANG']->getLL('tblHeaderDesc_t3ver_wsid') . '">' . $GLOBALS['LANG']->getLL('tblHeader_t3ver_wsid') . '</td>
					<td title="' . $GLOBALS['LANG']->getLL('tblHeaderDesc_t3ver_state') . '">' . $GLOBALS['LANG']->getLL('tblHeader_t3ver_state') . '</td>
					<td title="' . $GLOBALS['LANG']->getLL('tblHeaderDesc_t3ver_stage') . '">' . $GLOBALS['LANG']->getLL('tblHeader_t3ver_stage') . '</td>
					<td title="' . $GLOBALS['LANG']->getLL('tblHeaderDesc_t3ver_count') . '">' . $GLOBALS['LANG']->getLL('tblHeader_t3ver_count') . '</td>
					<td title="' . $GLOBALS['LANG']->getLL('tblHeaderDesc_pid') . '">' . $GLOBALS['LANG']->getLL('tblHeader_pid') . '</td>
					<td title="' . $GLOBALS['LANG']->getLL('tblHeaderDesc_t3ver_label') . '">' . $GLOBALS['LANG']->getLL('tblHeader_t3ver_label') . '</td>
					<td colspan="2"><input class="btn btn-default" type="submit" name="do_diff" value="' . $GLOBALS['LANG']->getLL('diff') . '" /></td>
				</tr>';
		$versions = BackendUtility::selectVersionsOfRecord($this->table, $this->uid, '*', $GLOBALS['BE_USER']->workspace);
		foreach ($versions as $row) {
			$adminLinks = $this->adminLinks($this->table, $row);
			$content .= '
				<tr class="' . ($row['uid'] != $this->uid ? 'bgColor4' : 'bgColor2 tableheader') . '">
					<td>' . ($row['uid'] != $this->uid ?
						'<a href="' . $this->doc->issueCommand(('&cmd[' . $this->table . '][' . $this->uid . '][version][swapWith]=' . $row['uid'] . '&cmd[' . $this->table . '][' . $this->uid . '][version][action]=swap')) . '" title="' . $GLOBALS['LANG']->getLL('swapWithCurrent', TRUE) . '">' . IconUtility::getSpriteIcon('actions-version-swap-version') . '</a>' :
							IconUtility::getSpriteIcon('status-status-current', array('title' => $GLOBALS['LANG']->getLL('currentOnlineVersion', TRUE)))) . '</td>
					<td nowrap="nowrap">' . $adminLinks . '</td>
					<td nowrap="nowrap">' . BackendUtility::getRecordTitle($this->table, $row, TRUE) . '</td>
					<td>' . $row['uid'] . '</td>
					<td>' . $row['t3ver_oid'] . '</td>
					<td>' . $row['t3ver_id'] . '</td>
					<td>' . $row['t3ver_wsid'] . '</td>
					<td>' . $row['t3ver_state'] . '</td>
					<td>' . $row['t3ver_stage'] . '</td>
					<td>' . $row['t3ver_count'] . '</td>
					<td>' . $row['pid'] . '</td>
					<td nowrap="nowrap"><a href="#" onclick="' . htmlspecialchars(BackendUtility::editOnClick(('&edit[' . $this->table . '][' . $row['uid'] . ']=edit&columnsOnly=t3ver_label'), $this->doc->backPath)) . '" title="' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:cm.edit', TRUE) . '">' . IconUtility::getSpriteIcon('actions-document-open') . '</a>' . htmlspecialchars($row['t3ver_label']) . '</td>
					<td class="version-diff-1"><input type="radio" name="diff_1" value="' . $row['uid'] . '"' . ($diff_1 == $row['uid'] ? ' checked="checked"' : '') . '/></td>
					<td class="version-diff-2"><input type="radio" name="diff_2" value="' . $row['uid'] . '"' . ($diff_2 == $row['uid'] ? ' checked="checked"' : '') . '/></td>
				</tr>';
			// Show sub-content if the table is pages AND it is not the online branch (because that will mostly render the WHOLE tree below - not smart;)
			if ($this->table == 'pages' && $row['uid'] != $this->uid) {
				$sub = $this->pageSubContent($row['uid']);
				if ($sub) {
					$content .= '
						<tr>
							<td></td>
							<td></td>
							<td colspan="10">' . $sub . '</td>
							<td colspan="2"></td>
						</tr>';
				}
			}
		}
		$content .= '</table></form>';
		$this->content .= $this->doc->section($GLOBALS['LANG']->getLL('title'), $content, 0, 1);
		// Create new:
		$content = '

			<form action="' . htmlspecialchars(BackendUtility::getModuleUrl('tce_db', array(), $this->doc->backPath)) . '" method="post">
			' . $GLOBALS['LANG']->getLL('tblHeader_t3ver_label') . ': <input type="text" name="cmd[' . $this->table . '][' . $this->uid . '][version][label]" /><br />
			<br /><input type="hidden" name="cmd[' . $this->table . '][' . $this->uid . '][version][action]" value="new" />
			<input type="hidden" name="prErr" value="1" />
			<input type="hidden" name="redirect" value="' . htmlspecialchars($this->REQUEST_URI) . '" />
			<input class="btn btn-default" type="submit" name="_" value="' . $GLOBALS['LANG']->getLL('createNewVersion') . '" />
			' . \TYPO3\CMS\Backend\Form\FormEngine::getHiddenTokenField('tceAction') . '
			</form>

		';
		$this->content .= $this->doc->spacer(15);
		$this->content .= $this->doc->section($GLOBALS['LANG']->getLL('createNewVersion'), $content, 0, 1);
	}

	/**
	 * Recursively look for children for page version with $pid
	 *
	 * @param int $pid UID of page record for which to look up sub-elements following that version
	 * @param int $c Counter, do not set (limits to 100 levels)
	 * @return string Table with content if any
	 */
	public function pageSubContent($pid, $c = 0) {
		$tableNames = ArrayUtility::removeArrayEntryByValue(array_keys($GLOBALS['TCA']), 'pages');
		$tableNames[] = 'pages';
		$content = '';
		foreach ($tableNames as $tN) {
			// Basically list ALL tables - not only those being copied might be found!
			$mres = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', $tN, 'pid=' . (int)$pid . BackendUtility::deleteClause($tN), '', $GLOBALS['TCA'][$tN]['ctrl']['sortby'] ? $GLOBALS['TCA'][$tN]['ctrl']['sortby'] : '');
			if ($GLOBALS['TYPO3_DB']->sql_num_rows($mres)) {
				$content .= '
					<tr>
						<td colspan="4" class="' . ($GLOBALS['TCA'][$tN]['ctrl']['versioning_followPages'] ? 'bgColor6' : ($tN == 'pages' ? 'bgColor5' : 'bgColor-10')) . '"' . (!$GLOBALS['TCA'][$tN]['ctrl']['versioning_followPages'] && $tN !== 'pages' ? ' style="color: #666666; font-style:italic;"' : '') . '>' . $tN . '</td>
					</tr>';
				while ($subrow = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($mres)) {
					$ownVer = $this->lookForOwnVersions($tN, $subrow['uid']);
					$content .= '
						<tr>
							<td>' . $this->adminLinks($tN, $subrow) . '</td>
							<td>' . $subrow['uid'] . '</td>
							' . ($ownVer > 1 ? '<td style="font-weight: bold; background-color: yellow;"><a href="' .
							htmlspecialchars(BackendUtility::getModuleUrl('web_txversionM1', array('table' => $tN, 'uid' => $subrow['uid']))) .
							'">' . ($ownVer - 1) . '</a></td>' : '<td></td>') . '
							<td width="98%">' . BackendUtility::getRecordTitle($tN, $subrow, TRUE) . '</td>
						</tr>';
					if ($tN == 'pages' && $c < 100) {
						$sub = $this->pageSubContent($subrow['uid'], $c + 1);
						if ($sub) {
							$content .= '
								<tr>
									<td></td>
									<td></td>
									<td></td>
									<td width="98%">' . $sub . '</td>
								</tr>';
						}
					}
				}
			}
			$GLOBALS['TYPO3_DB']->sql_free_result($mres);
		}
		return $content ? '<table border="1" cellpadding="1" cellspacing="0" width="100%">' . $content . '</table>' : '';
	}

	/**
	 * Look for number of versions of a record
	 *
	 * @param string $table Table name
	 * @param int $uid Record uid
	 * @return int Number of versions for record, FALSE if none.
	 */
	public function lookForOwnVersions($table, $uid) {
		$versions = BackendUtility::selectVersionsOfRecord($table, $uid, 'uid');
		if (is_array($versions)) {
			return count($versions);
		}
		return FALSE;
	}

	/**
	 * Administrative links for a table / record
	 *
	 * @param string $table Table name
	 * @param array $row Record for which administrative links are generated.
	 * @return string HTML link tags.
	 */
	public function adminLinks($table, $row) {
		// Edit link:
		$adminLink = '<a href="#" onclick="' . htmlspecialchars(BackendUtility::editOnClick(('&edit[' . $table . '][' . $row['uid'] . ']=edit'), $this->doc->backPath)) . '" title="' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:cm.edit', TRUE) . '">' . IconUtility::getSpriteIcon('actions-document-open') . '</a>';
		// Delete link:
		$adminLink .= '<a href="' . htmlspecialchars($this->doc->issueCommand(('&cmd[' . $table . '][' . $row['uid'] . '][delete]=1'))) . '" title="' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:cm.delete', TRUE) . '">' . IconUtility::getSpriteIcon('actions-edit-delete') . '</a>';
		if ($table === 'pages') {
			// If another page module was specified, replace the default Page module with the new one
			$newPageModule = trim($GLOBALS['BE_USER']->getTSConfigVal('options.overridePageModule'));
			$pageModule = BackendUtility::isModuleSetInTBE_MODULES($newPageModule) ? $newPageModule : 'web_layout';
			// Perform some acccess checks:
			$a_wl = $GLOBALS['BE_USER']->check('modules', 'web_list');
			$a_wp = $GLOBALS['BE_USER']->check('modules', $pageModule);
			$adminLink .= '<a href="#" onclick="top.loadEditId(' . $row['uid'] . ');top.goToModule(\'' . $pageModule . '\'); return false;">' . IconUtility::getSpriteIcon('actions-page-open') . '</a>';
			$adminLink .= '<a href="#" onclick="top.loadEditId(' . $row['uid'] . ');top.goToModule(\'web_list\'); return false;">' . IconUtility::getSpriteIcon('actions-system-list-open') . '</a>';
			// "View page" icon is added:
			$adminLink .= '<a href="#" onclick="' . htmlspecialchars(BackendUtility::viewOnClick($row['uid'], $this->doc->backPath, BackendUtility::BEgetRootLine($row['uid']))) . '">' . IconUtility::getSpriteIcon('actions-document-view') . '</a>';
		} else {
			if ($row['pid'] == -1) {
				$getVars = '&ADMCMD_vPrev[' . rawurlencode(($table . ':' . $row['t3ver_oid'])) . ']=' . $row['uid'];
				// "View page" icon is added:
				$adminLink .= '<a href="#" onclick="' . htmlspecialchars(BackendUtility::viewOnClick($row['_REAL_PID'], $this->doc->backPath, BackendUtility::BEgetRootLine($row['_REAL_PID']), '', '', $getVars)) . '">' . IconUtility::getSpriteIcon('actions-document-view') . '</a>';
			}
		}
		return $adminLink;
	}

}
