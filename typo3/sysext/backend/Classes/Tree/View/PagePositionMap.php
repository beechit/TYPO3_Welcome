<?php
namespace TYPO3\CMS\Backend\Tree\View;

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
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Position map class - generating a page tree / content element list which links for inserting (copy/move) of records.
 * Used for pages / tt_content element wizards of various kinds.
 *
 * @author Kasper Skårhøj <kasperYYYY@typo3.com>
 */
class PagePositionMap {

	// EXTERNAL, static:
	/**
	 * @var string
	 */
	public $moveOrCopy = 'move';

	/**
	 * @var int
	 */
	public $dontPrintPageInsertIcons = 0;

	/**
	 * @var string
	 */
	public $backPath = '';

	// How deep the position page tree will go.
	/**
	 * @var int
	 */
	public $depth = 2;

	// Can be set to the sys_language uid to select content elements for.
	/**
	 * @var string
	 */
	public $cur_sys_language;

	// INTERNAL, dynamic:
	// Request uri
	/**
	 * @var string
	 */
	public $R_URI = '';

	// Element id.
	/**
	 * @var string
	 */
	public $elUid = '';

	// tt_content element uid to move.
	/**
	 * @var string
	 */
	public $moveUid = '';

	// Caching arrays:
	/**
	 * @var array
	 */
	public $getModConfigCache = array();

	/**
	 * @var array
	 */
	public $checkNewPageCache = array();

	// Label keys:
	/**
	 * @var string
	 */
	public $l_insertNewPageHere = 'insertNewPageHere';

	/**
	 * @var string
	 */
	public $l_insertNewRecordHere = 'insertNewRecordHere';

	/**
	 * @var string
	 */
	public $modConfigStr = 'mod.web_list.newPageWiz';

	/**
	 * Page tree implementation class name
	 *
	 * @var string
	 */
	protected $pageTreeClassName = ElementBrowserPageTreeView::class;

	/**
	 * Constructor allowing to set pageTreeImplementation
	 *
	 * @param string $pageTreeClassName
	 */
	public function __construct($pageTreeClassName = NULL) {
		if ($pageTreeClassName !== NULL) {
			$this->pageTreeClassName = $pageTreeClassName;
		}
	}

	/*************************************
	 *
	 * Page position map:
	 *
	 **************************************/
	/**
	 * Creates a "position tree" based on the page tree.
	 *
	 * @param int $id Current page id
	 * @param array $pageinfo Current page record.
	 * @param string $perms_clause Page selection permission clause.
	 * @param string $R_URI Current REQUEST_URI
	 * @return string HTML code for the tree.
	 */
	public function positionTree($id, $pageinfo, $perms_clause, $R_URI) {
		$code = '';
		// Make page tree object:
		/** @var \TYPO3\CMS\Backend\Tree\View\PageTreeView localPageTree */
		$t3lib_pageTree = GeneralUtility::makeInstance($this->pageTreeClassName);
		$t3lib_pageTree->init(' AND ' . $perms_clause);
		$t3lib_pageTree->addField('pid');
		// Initialize variables:
		$this->R_URI = $R_URI;
		$this->elUid = $id;
		// Create page tree, in $this->depth levels.
		$t3lib_pageTree->getTree($pageinfo['pid'], $this->depth);
		if (!$this->dontPrintPageInsertIcons) {
			$code .= $this->JSimgFunc();
		}
		// Initialize variables:
		$saveBlankLineState = array();
		$saveLatestUid = array();
		$latestInvDepth = $this->depth;
		// Traverse the tree:
		foreach ($t3lib_pageTree->tree as $cc => $dat) {
			// Make link + parameters.
			$latestInvDepth = $dat['invertedDepth'];
			$saveLatestUid[$latestInvDepth] = $dat;
			if (isset($t3lib_pageTree->tree[$cc - 1])) {
				$prev_dat = $t3lib_pageTree->tree[$cc - 1];
				// If current page, subpage?
				if ($prev_dat['row']['uid'] == $id) {
					// 1) It must be allowed to create a new page and 2) If there are subpages there is no need to render a subpage icon here - it'll be done over the subpages...
					if (!$this->dontPrintPageInsertIcons && $this->checkNewPageInPid($id) && !($prev_dat['invertedDepth'] > $t3lib_pageTree->tree[$cc]['invertedDepth'])) {
						$code .= '<span class="nobr">' . $this->insertQuadLines($dat['blankLineCode']) . '<img src="clear.gif" width="18" height="8" align="top" alt="" />' . '<a href="#" onclick="' . htmlspecialchars($this->onClickEvent($id, $id, 1)) . '" onmouseover="' . htmlspecialchars(('changeImg(\'mImgSubpage' . $cc . '\',0);')) . '" onmouseout="' . htmlspecialchars(('changeImg(\'mImgSubpage' . $cc . '\',1);')) . '">' . '<img' . IconUtility::skinImg($this->backPath, 'gfx/newrecord_marker_d.gif', 'width="281" height="8"') . ' name="mImgSubpage' . $cc . '" border="0" align="top" title="' . $this->insertlabel() . '" alt="" />' . '</a></span><br />';
					}
				}
				// If going down
				if ($prev_dat['invertedDepth'] > $t3lib_pageTree->tree[$cc]['invertedDepth']) {
					$prevPid = $t3lib_pageTree->tree[$cc]['row']['pid'];
				} elseif ($prev_dat['invertedDepth'] < $t3lib_pageTree->tree[$cc]['invertedDepth']) {
					// If going up
					// First of all the previous level should have an icon:
					if (!$this->dontPrintPageInsertIcons && $this->checkNewPageInPid($prev_dat['row']['pid'])) {
						$prevPid = -$prev_dat['row']['uid'];
						$code .= '<span class="nobr">' . $this->insertQuadLines($dat['blankLineCode']) . '<img src="clear.gif" width="18" height="1" align="top" alt="" />' . '<a href="#" onclick="' . htmlspecialchars($this->onClickEvent($prevPid, $prev_dat['row']['pid'], 2)) . '" onmouseover="' . htmlspecialchars(('changeImg(\'mImgAfter' . $cc . '\',0);')) . '" onmouseout="' . htmlspecialchars(('changeImg(\'mImgAfter' . $cc . '\',1);')) . '">' . '<img' . IconUtility::skinImg($this->backPath, 'gfx/newrecord_marker_d.gif', 'width="281" height="8"') . ' name="mImgAfter' . $cc . '" border="0" align="top" title="' . $this->insertlabel() . '" alt="" />' . '</a></span><br />';
					}
					// Then set the current prevPid
					$prevPid = -$prev_dat['row']['pid'];
				} else {
					// In on the same level
					$prevPid = -$prev_dat['row']['uid'];
				}
			} else {
				// First in the tree
				$prevPid = $dat['row']['pid'];
			}
			if (!$this->dontPrintPageInsertIcons && $this->checkNewPageInPid($dat['row']['pid'])) {
				$code .= '<span class="nobr">' . $this->insertQuadLines($dat['blankLineCode']) . '<a href="#" onclick="' . htmlspecialchars($this->onClickEvent($prevPid, $dat['row']['pid'], 3)) . '" onmouseover="' . htmlspecialchars(('changeImg(\'mImg' . $cc . '\',0);')) . '" onmouseout="' . htmlspecialchars(('changeImg(\'mImg' . $cc . '\',1);')) . '">' . '<img' . IconUtility::skinImg($this->backPath, 'gfx/newrecord_marker_d.gif', 'width="281" height="8"') . ' name="mImg' . $cc . '" border="0" align="top" title="' . $this->insertlabel() . '" alt="" />' . '</a></span><br />';
			}
			// The line with the icon and title:
			$t_code = '<span class="nobr">' . $dat['HTML'] . $this->linkPageTitle($this->boldTitle(htmlspecialchars(GeneralUtility::fixed_lgd_cs($dat['row']['title'], $GLOBALS['BE_USER']->uc['titleLen'])), $dat, $id), $dat['row']) . '</span><br />';
			$code .= $t_code;
		}
		// If the current page was the last in the tree:
		$prev_dat = end($t3lib_pageTree->tree);
		if ($prev_dat['row']['uid'] == $id) {
			if (!$this->dontPrintPageInsertIcons && $this->checkNewPageInPid($id)) {
				$code .= '<span class="nobr">' . $this->insertQuadLines($saveLatestUid[$latestInvDepth]['blankLineCode'], 1) . '<img src="clear.gif" width="18" height="8" align="top" alt="" />' . '<a href="#" onclick="' . $this->onClickEvent($id, $id, 4) . '" onmouseover="' . htmlspecialchars(('changeImg(\'mImgSubpage' . $cc . '\',0);')) . '" onmouseout="' . htmlspecialchars(('changeImg(\'mImgSubpage' . $cc . '\',1);')) . '">' . '<img' . IconUtility::skinImg($this->backPath, 'gfx/newrecord_marker_d.gif', 'width="281" height="8"') . ' name="mImgSubpage' . $cc . '" border="0" align="top" title="' . $this->insertlabel() . '" alt="" />' . '</a></span><br />';
			}
		}
		for ($a = $latestInvDepth; $a <= $this->depth; $a++) {
			$dat = $saveLatestUid[$a];
			$prevPid = -$dat['row']['uid'];
			if (!$this->dontPrintPageInsertIcons && $this->checkNewPageInPid($dat['row']['pid'])) {
				$code .= '<span class="nobr">' . $this->insertQuadLines($dat['blankLineCode'], 1) . '<a href="#" onclick="' . htmlspecialchars($this->onClickEvent($prevPid, $dat['row']['pid'], 5)) . '" onmouseover="' . htmlspecialchars(('changeImg(\'mImgEnd' . $a . '\',0);')) . '" onmouseout="' . htmlspecialchars(('changeImg(\'mImgEnd' . $a . '\',1);')) . '">' . '<img' . IconUtility::skinImg($this->backPath, 'gfx/newrecord_marker_d.gif', 'width="281" height="8"') . ' name="mImgEnd' . $a . '" border="0" align="top" title="' . $this->insertlabel() . '" alt="" />' . '</a></span><br />';
			}
		}
		return $code;
	}

	/**
	 * Creates the JavaScritp for insert new-record rollover image
	 *
	 * @param string $prefix Insert record image prefix.
	 * @return string <script> section
	 */
	public function JSimgFunc($prefix = '') {
		$code = $GLOBALS['TBE_TEMPLATE']->wrapScriptTags('

			var img_newrecord_marker=new Image();
			img_newrecord_marker.src = "' . IconUtility::skinImg($this->backPath, ('gfx/newrecord' . $prefix . '_marker.gif'), '', 1) . '";

			var img_newrecord_marker_d=new Image();
			img_newrecord_marker_d.src = "' . IconUtility::skinImg($this->backPath, ('gfx/newrecord' . $prefix . '_marker_d.gif'), '', 1) . '";

			function changeImg(name,d) {	//
				if (document[name]) {
					if (d) {
						document[name].src = img_newrecord_marker_d.src;
					} else {
						document[name].src = img_newrecord_marker.src;
					}
				}
			}
		');
		return $code;
	}

	/**
	 * Wrap $t_code in bold IF the $dat uid matches $id
	 *
	 * @param string $t_code Title string
	 * @param array $dat Infomation array with record array inside.
	 * @param int $id The current id.
	 * @return string The title string.
	 */
	public function boldTitle($t_code, $dat, $id) {
		if ($dat['row']['uid'] == $id) {
			$t_code = '<strong>' . $t_code . '</strong>';
		}
		return $t_code;
	}

	/**
	 * Creates the onclick event for the insert-icons.
	 *
	 * TSconfig mod.web_list.newPageWiz.overrideWithExtension may contain an extension which provides a module
	 * to be used instead of the normal create new page wizard.
	 *
	 * @param int $pid The pid.
	 * @param int $newPagePID New page id.
	 * @return string Onclick attribute content
	 */
	public function onClickEvent($pid, $newPagePID) {
		$TSconfigProp = $this->getModConfig($newPagePID);
		if ($TSconfigProp['overrideWithExtension']) {
			if (ExtensionManagementUtility::isLoaded($TSconfigProp['overrideWithExtension'])) {
				$onclick = 'window.location.href=\'' . ExtensionManagementUtility::extRelPath($TSconfigProp['overrideWithExtension']) . 'mod1/index.php?cmd=crPage&positionPid=' . $pid . '\';';
				return $onclick;
			}
		}
		$params = '&edit[pages][' . $pid . ']=new&returnNewPageId=1';
		return BackendUtility::editOnClick($params, '', $this->R_URI);
	}

	/**
	 * Get label, htmlspecialchars()'ed
	 *
	 * @return string The localized label for "insert new page here
	 */
	public function insertlabel() {
		return $GLOBALS['LANG']->getLL($this->l_insertNewPageHere, 1);
	}

	/**
	 * Wrapping page title.
	 *
	 * @param string $str Page title.
	 * @param array $rec Page record (?)
	 * @return string Wrapped title.
	 */
	public function linkPageTitle($str, $rec) {
		return $str;
	}

	/**
	 * Checks if the user has permission to created pages inside of the $pid page.
	 * Uses caching so only one regular lookup is made - hence you can call the function multiple times without worrying about performance.
	 *
	 * @param int $pid Page id for which to test.
	 * @return bool
	 */
	public function checkNewPageInPid($pid) {
		if (!isset($this->checkNewPageCache[$pid])) {
			$pidInfo = BackendUtility::getRecord('pages', $pid);
			$this->checkNewPageCache[$pid] = $GLOBALS['BE_USER']->isAdmin() || $GLOBALS['BE_USER']->doesUserHaveAccess($pidInfo, 8);
		}
		return $this->checkNewPageCache[$pid];
	}

	/**
	 * Returns module configuration for a pid.
	 *
	 * @param int $pid Page id for which to get the module configuration.
	 * @return array The properties of teh module configuration for the page id.
	 * @see onClickEvent()
	 */
	public function getModConfig($pid) {
		if (!isset($this->getModConfigCache[$pid])) {
			// Acquiring TSconfig for this PID:
			$this->getModConfigCache[$pid] = BackendUtility::getModTSconfig($pid, $this->modConfigStr);
		}
		return $this->getModConfigCache[$pid]['properties'];
	}

	/**
	 * Insert half/quad lines.
	 *
	 * @param string $codes Keywords for which lines to insert.
	 * @param bool $allBlank If TRUE all lines are just blank clear.gifs
	 * @return string HTML content.
	 */
	public function insertQuadLines($codes, $allBlank = FALSE) {
		$codeA = GeneralUtility::trimExplode(',', $codes . ',line', TRUE);
		$lines = array();
		foreach ($codeA as $code) {
			if ($code == 'blank' || $allBlank) {
				$lines[] = '<img src="clear.gif" width="18" height="8" align="top" alt="" />';
			} else {
				$lines[] = '<img' . IconUtility::skinImg($this->backPath, 'gfx/ol/halfline.gif', 'width="18" height="8"') . ' align="top" alt="" />';
			}
		}
		return implode('', $lines);
	}

	/*************************************
	 *
	 * Content element positioning:
	 *
	 **************************************/
	/**
	 * Creates HTML for inserting/moving content elements.
	 *
	 * @param int $pid page id onto which to insert content element.
	 * @param int $moveUid Move-uid (tt_content element uid?)
	 * @param string $colPosList List of columns to show
	 * @param bool $showHidden If not set, then hidden/starttime/endtime records are filtered out.
	 * @param string $R_URI Request URI
	 * @return string HTML
	 */
	public function printContentElementColumns($pid, $moveUid, $colPosList, $showHidden, $R_URI) {
		$this->R_URI = $R_URI;
		$this->moveUid = $moveUid;
		$colPosArray = GeneralUtility::trimExplode(',', $colPosList, TRUE);
		$lines = array();
		foreach ($colPosArray as $kk => $vv) {
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tt_content', 'pid=' . (int)$pid . ($showHidden ? '' : BackendUtility::BEenableFields('tt_content')) . ' AND colPos=' . (int)$vv . ((string)$this->cur_sys_language !== '' ? ' AND sys_language_uid=' . (int)$this->cur_sys_language : '') . BackendUtility::deleteClause('tt_content') . BackendUtility::versioningPlaceholderClause('tt_content'), '', 'sorting');
			$lines[$vv] = array();
			$lines[$vv][] = $this->insertPositionIcon('', $vv, $kk, $moveUid, $pid);
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				BackendUtility::workspaceOL('tt_content', $row);
				if (is_array($row)) {
					$lines[$vv][] = $this->wrapRecordHeader($this->getRecordHeader($row), $row);
					$lines[$vv][] = $this->insertPositionIcon($row, $vv, $kk, $moveUid, $pid);
				}
			}
			$GLOBALS['TYPO3_DB']->sql_free_result($res);
		}
		return $this->printRecordMap($lines, $colPosArray, $pid);
	}

	/**
	 * Creates the table with the content columns
	 *
	 * @param array $lines Array with arrays of lines for each column
	 * @param array $colPosArray Column position array
	 * @param int $pid The id of the page
	 * @return string HTML
	 */
	public function printRecordMap($lines, $colPosArray, $pid = 0) {
		$count = \TYPO3\CMS\Core\Utility\MathUtility::forceIntegerInRange(count($colPosArray), 1);
		$backendLayout = GeneralUtility::callUserFunction(\TYPO3\CMS\Backend\View\BackendLayoutView::class . '->getSelectedBackendLayout', $pid, $this);
		if (isset($backendLayout['__config']['backend_layout.'])) {
			$GLOBALS['LANG']->includeLLFile('EXT:cms/layout/locallang.xlf');
			$table = '<div class="table-fit"><table class="table table-condensed table-bordered table-vertical-top">';
			$colCount = (int)$backendLayout['__config']['backend_layout.']['colCount'];
			$rowCount = (int)$backendLayout['__config']['backend_layout.']['rowCount'];
			$table .= '<colgroup>';
			for ($i = 0; $i < $colCount; $i++) {
				$table .= '<col style="width:' . 100 / $colCount . '%"></col>';
			}
			$table .= '</colgroup>';
			$table .= '<tbody>';
			$tcaItems = GeneralUtility::callUserFunction(\TYPO3\CMS\Backend\View\BackendLayoutView::class . '->getColPosListItemsParsed', $pid, $this);
			// Cycle through rows
			for ($row = 1; $row <= $rowCount; $row++) {
				$rowConfig = $backendLayout['__config']['backend_layout.']['rows.'][$row . '.'];
				if (!isset($rowConfig)) {
					continue;
				}
				$table .= '<tr>';
				for ($col = 1; $col <= $colCount; $col++) {
					$columnConfig = $rowConfig['columns.'][$col . '.'];
					if (!isset($columnConfig)) {
						continue;
					}
					// Which tt_content colPos should be displayed inside this cell
					$columnKey = (int)$columnConfig['colPos'];
					$head = '';
					foreach ($tcaItems as $item) {
						if ($item[1] == $columnKey) {
							$head = $GLOBALS['LANG']->sL($item[0], TRUE);
						}
					}
					// Render the grid cell
					$table .= '<td'
						. (isset($columnConfig['colspan']) ? ' colspan="' . $columnConfig['colspan'] . '"' : '')
						. (isset($columnConfig['rowspan']) ? ' rowspan="' . $columnConfig['rowspan'] . '"' : '')
						. ' class="col-nowrap col-min'
						. (!isset($columnConfig['colPos']) ? ' warning' : '')
						. (isset($columnConfig['colPos']) && !$head ? ' danger' : '') . '">';
					// Render header
					$table .= '<p>';
					if (isset($columnConfig['colPos']) && $head) {
						$table .= '<strong>' . $this->wrapColumnHeader($head, '', '') . '</strong>';
					} elseif ($columnConfig['colPos']) {
						$table .= '<em>' . $this->wrapColumnHeader($GLOBALS['LANG']->getLL('noAccess'), '', '') . '</em>';
					} else {
						$table .= '<em>' . $this->wrapColumnHeader(($columnConfig['name']?: '') . ' (' . $GLOBALS['LANG']->getLL('notAssigned') . ')', '', '') . '</em>';
					}
					$table .= '</p>';
					// Render lines
					if (isset($columnConfig['colPos']) && $head && !empty($lines[$columnKey])) {
						$table .= '<ul class="list-unstyled">';
						foreach ($lines[$columnKey] as $line) {
							$table .= '<li>' . $line . '</li>';
						}
						$table .= '</ul>';
					}
					$table .= '</td>';
				}
				$table .= '</tr>';
			}
			$table .= '</tbody>';
			$table .= '</table></div>';
		} else {
			// Traverse the columns here:
			$row = '';
			foreach ($colPosArray as $kk => $vv) {
				$row .= '<td class="col-nowrap col-min" width="' . round(100 / $count) . '%">';
				$row .= '<p><strong>' . $this->wrapColumnHeader($GLOBALS['LANG']->sL(BackendUtility::getLabelFromItemlist('tt_content', 'colPos', $vv, $pid), TRUE), $vv) . '</strong></p>';
				if (!empty($lines[$vv])) {
					$row .= '<ul class="list-unstyled">';
					foreach ($lines[$vv] as $line) {
						$row .= '<li>' . $line . '</li>';
					}
					$row .= '</ul>';
				}
				$row .= '</td>';
			}
			$table = '

			<!--
				Map of records in columns:
			-->
			<div class="table-fit">
				<table class="table table-condensed table-bordered table-vertical-top">
					<tr>' . $row . '</tr>
				</table>
			</div>

			';
		}
		return $this->JSimgFunc('2') . $table;
	}

	/**
	 * Wrapping the column header
	 *
	 * @param string $str Header value
	 * @param string $vv Column info.
	 * @return string
	 * @see printRecordMap()
	 */
	public function wrapColumnHeader($str, $vv) {
		return $str;
	}

	/**
	 * Creates a linked position icon.
	 *
	 * @param mixed $row Element row. If this is an array the link will cause an insert after this content element, otherwise
	 * the link will insert at the first position in the column
	 * @param string $vv Column position value.
	 * @param int $kk Column key.
	 * @param int $moveUid Move uid
	 * @param int $pid PID value.
	 * @return string
	 */
	public function insertPositionIcon($row, $vv, $kk, $moveUid, $pid) {
		if (is_array($row) && !empty($row['uid'])) {
			// Use record uid for the hash when inserting after this content element
			$uid = $row['uid'];
		} else {
			// No uid means insert at first position in the column
			$uid = '';
		}
		$cc = hexdec(substr(md5($uid . '-' . $vv . '-' . $kk), 0, 4));
		return '<a href="#" onclick="' . htmlspecialchars($this->onClickInsertRecord($row, $vv, $moveUid, $pid, $this->cur_sys_language)) . '" onmouseover="' . htmlspecialchars(('changeImg(\'mImg' . $cc . '\',0);')) . '" onmouseout="' . htmlspecialchars(('changeImg(\'mImg' . $cc . '\',1);')) . '">' . '<img' . IconUtility::skinImg($this->backPath, 'gfx/newrecord2_marker_d.gif', 'width="100" height="8"') . ' name="mImg' . $cc . '" border="0" align="top" title="' . $GLOBALS['LANG']->getLL($this->l_insertNewRecordHere, 1) . '" alt="" />' . '</a>';
	}

	/**
	 * Create on-click event value.
	 *
	 * @param mixed $row The record. If this is not an array with the record data the insert will be for the first position
	 * in the column
	 * @param string $vv Column position value.
	 * @param int $moveUid Move uid
	 * @param int $pid PID value.
	 * @param int $sys_lang System language (not used currently)
	 * @return string
	 */
	public function onClickInsertRecord($row, $vv, $moveUid, $pid, $sys_lang = 0) {
		$table = 'tt_content';
		if (is_array($row)) {
			$location = BackendUtility::getModuleUrl('tce_db') . '&cmd[' . $table . '][' . $moveUid . '][' . $this->moveOrCopy . ']=-' . $row['uid'] . '&prErr=1&uPT=1&vC=' . $GLOBALS['BE_USER']->veriCode() . BackendUtility::getUrlToken('tceAction');
		} else {
			$location = BackendUtility::getModuleUrl('tce_db') . '&cmd[' . $table . '][' . $moveUid . '][' . $this->moveOrCopy . ']=' . $pid . '&data[' . $table . '][' . $moveUid . '][colPos]=' . $vv . '&prErr=1&vC=' . $GLOBALS['BE_USER']->veriCode() . BackendUtility::getUrlToken('tceAction');
		}
		$location .= '&redirect=' . rawurlencode($this->R_URI);
		// returns to prev. page
		return 'window.location.href=' . GeneralUtility::quoteJSvalue($location) . ';return false;';
	}

	/**
	 * Wrapping the record header  (from getRecordHeader())
	 *
	 * @param string $str HTML content
	 * @param array $row Record array.
	 * @return string HTML content
	 */
	public function wrapRecordHeader($str, $row) {
		return $str;
	}

	/**
	 * Create record header (includes teh record icon, record title etc.)
	 *
	 * @param array $row Record row.
	 * @return string HTML
	 */
	public function getRecordHeader($row) {
		$line = IconUtility::getSpriteIconForRecord('tt_content', $row, array('title' => htmlspecialchars(BackendUtility::getRecordIconAltText($row, 'tt_content'))));
		$line .= BackendUtility::getRecordTitle('tt_content', $row, TRUE);
		return $this->wrapRecordTitle($line, $row);
	}

	/**
	 * Wrapping the title of the record.
	 *
	 * @param string $str The title value.
	 * @param array $row The record row.
	 * @return string Wrapped title string.
	 */
	public function wrapRecordTitle($str, $row) {
		return '<a href="' . htmlspecialchars(GeneralUtility::linkThisScript(array('uid' => (int)$row['uid'], 'moveUid' => ''))) . '">' . $str . '</a>';
	}

}
