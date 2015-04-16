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

/**
 * Generate a page-tree, browsable.
 *
 * @author Kasper Skårhøj <kasperYYYY@typo3.com>
 * @coauthor René Fritz <r.fritz@colorcube.de>
 */
class BrowseTreeView extends \TYPO3\CMS\Backend\Tree\View\AbstractTreeView {

	/**
	 * Initialize, setting what is necessary for browsing pages.
	 * Using the current user.
	 *
	 * @param string $clause Additional clause for selecting pages.
	 * @param string $orderByFields record ORDER BY field
	 * @return void
	 */
	public function init($clause = '', $orderByFields = '') {
		// This will hide records from display - it has nothing todo with user rights!!
		$clauseExcludePidList = '';
		if ($pidList = $GLOBALS['BE_USER']->getTSConfigVal('options.hideRecords.pages')) {
			if ($pidList = $GLOBALS['TYPO3_DB']->cleanIntList($pidList)) {
				$clauseExcludePidList = ' AND pages.uid NOT IN (' . $pidList . ')';
			}
		}
		// This is very important for making trees of pages: Filtering out deleted pages, pages with no access to and sorting them correctly:
		parent::init(' AND ' . $GLOBALS['BE_USER']->getPagePermsClause(1) . ' ' . $clause . $clauseExcludePidList, 'sorting');
		$this->table = 'pages';
		$this->setTreeName('browsePages');
		$this->domIdPrefix = 'pages';
		$this->iconName = '';
		$this->title = $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'];
		$this->MOUNTS = $GLOBALS['BE_USER']->returnWebmounts();
		if ($pidList) {
			// Remove mountpoint if explicitly set in options.hideRecords.pages (see above)
			$hideList = explode(',', $pidList);
			$this->MOUNTS = array_diff($this->MOUNTS, $hideList);
		}
		$this->fieldArray = array_merge($this->fieldArray, array('doktype', 'php_tree_stop', 't3ver_id', 't3ver_state', 't3ver_wsid', 't3ver_move_id'));
		$this->fieldArray = array_merge($this->fieldArray, array('hidden', 'starttime', 'endtime', 'fe_group', 'module', 'extendToSubpages', 'is_siteroot', 'nav_hide'));
	}

	/**
	 * Creates title attribute content for pages.
	 * Uses API function in \TYPO3\CMS\Backend\Utility\BackendUtility which will retrieve lots of useful information for pages.
	 *
	 * @param array $row The table row.
	 * @return string
	 */
	public function getTitleAttrib($row) {
		return BackendUtility::titleAttribForPages($row, '1=1 ' . $this->clause, 0);
	}

	/**
	 * Wrapping the image tag, $icon, for the row, $row (except for mount points)
	 *
	 * @param string $icon The image tag for the icon
	 * @param array $row The row for the current element
	 * @return string The processed icon input value.
	 * @access private
	 */
	public function wrapIcon($icon, $row) {
		// Add title attribute to input icon tag
		$theIcon = $this->addTagAttributes($icon, $this->titleAttrib ? $this->titleAttrib . '="' . $this->getTitleAttrib($row) . '"' : '');
		// Wrap icon in click-menu link.
		if (!$this->ext_IconMode) {
			$theIcon = $GLOBALS['TBE_TEMPLATE']->wrapClickMenuOnIcon($theIcon, $this->treeName, $this->getId($row), 0);
		} elseif ($this->ext_IconMode === 'titlelink') {
			$aOnClick = 'return jumpTo(\'' . $this->getJumpToParam($row) . '\',this,\'' . $this->domIdPrefix . $this->getId($row) . '\',' . $this->bank . ');';
			$theIcon = '<a href="#" onclick="' . htmlspecialchars($aOnClick) . '">' . $theIcon . '</a>';
		}
		return $theIcon;
	}

	/**
	 * Returns the title for the input record. If blank, a "no title" label (localized) will be returned.
	 * Do NOT htmlspecialchar the string from this function - has already been done.
	 *
	 * @param array $row The input row array (where the key "title" is used for the title)
	 * @param int $titleLen Title length (30)
	 * @return string The title.
	 */
	public function getTitleStr($row, $titleLen = 30) {
		$title = parent::getTitleStr($row, $titleLen);
		if (isset($row['is_siteroot']) && $row['is_siteroot'] != 0 && $GLOBALS['BE_USER']->getTSConfigVal('options.pageTree.showDomainNameWithTitle')) {
			$rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('domainName,sorting', 'sys_domain', 'pid=' . $GLOBALS['TYPO3_DB']->quoteStr(($row['uid'] . BackendUtility::deleteClause('sys_domain') . BackendUtility::BEenableFields('sys_domain')), 'sys_domain'), '', 'sorting', 1);
			if (is_array($rows) && count($rows) > 0) {
				$title = sprintf('%s [%s]', $title, htmlspecialchars($rows[0]['domainName']));
			}
		}
		return $title;
	}

	/**
	 * Adds a red "+" to the input string, $str, if the field "php_tree_stop" in the $row (pages) is set
	 *
	 * @param string $str Input string, like a page title for the tree
	 * @param array $row Record row with "php_tree_stop" field
	 * @return string Modified string
	 * @access private
	 */
	public function wrapStop($str, $row) {
		if ($row['php_tree_stop']) {
			$str .= '<span class="typo3-red">
								<a href="' . htmlspecialchars(\TYPO3\CMS\Core\Utility\GeneralUtility::linkThisScript(array('setTempDBmount' => $row['uid']))) . '" class="typo3-red">+</a>
							</span>';
		}
		return $str;
	}

}
