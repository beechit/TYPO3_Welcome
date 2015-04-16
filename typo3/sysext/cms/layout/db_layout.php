<?php
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
 * Module: Web>Page
 *
 * This module lets you view a page in a more Content Management like style than the ordinary record-list
 *
 * @author Kasper Skårhøj <kasperYYYY@typo3.com>
 */
unset($MCONF);
require __DIR__ . '/conf.php';
require $BACK_PATH . 'init.php';
$LANG->includeLLFile('EXT:cms/layout/locallang.xlf');

$BE_USER->modAccess($MCONF, 1);
// Will open up records locked by current user. It's assumed that the locking should end if this script is hit.
\TYPO3\CMS\Backend\Utility\BackendUtility::lockRecords();
/**
 * Local extension of position map class
 *
 * @author Kasper Skårhøj <kasperYYYY@typo3.com>
 */
class ext_posMap extends \TYPO3\CMS\Backend\Tree\View\PagePositionMap {

	/**
	 * @var bool
	 */
	public $dontPrintPageInsertIcons = 1;

	/**
	 * @var string
	 */
	public $l_insertNewRecordHere = 'newContentElement';

	/**
	 * Wrapping the title of the record.
	 *
	 * @param string $str The title value.
	 * @param array $row The record row.
	 * @return string Wrapped title string.
	 */
	public function wrapRecordTitle($str, $row) {
		$aOnClick = 'jumpToUrl(' . \TYPO3\CMS\Core\Utility\GeneralUtility::quoteJSvalue($GLOBALS['SOBE']->local_linkThisScript(array('edit_record' => ('tt_content:' . $row['uid'])))) . ');return false;';
		return '<a href="#" onclick="' . htmlspecialchars($aOnClick) . '">' . $str . '</a>';
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
		$aOnClick = 'jumpToUrl(' . \TYPO3\CMS\Core\Utility\GeneralUtility::quoteJSvalue($GLOBALS['SOBE']->local_linkThisScript(array('edit_record' => ('_EDIT_COL:' . $vv)))) . ');return false;';
		return '<a href="#" onclick="' . htmlspecialchars($aOnClick) . '">' . $str . '</a>';
	}

	/**
	 * Create on-click event value.
	 *
	 * @param array $row The record.
	 * @param string $vv Column position value.
	 * @param int $moveUid Move uid
	 * @param int $pid PID value.
	 * @param int $sys_lang System language
	 * @return string
	 */
	public function onClickInsertRecord($row, $vv, $moveUid, $pid, $sys_lang = 0) {
		if (is_array($row)) {
			$location = $GLOBALS['SOBE']->local_linkThisScript(array('edit_record' => 'tt_content:new/-' . $row['uid'] . '/' . $row['colPos']));
		} else {
			$location = $GLOBALS['SOBE']->local_linkThisScript(array('edit_record' => 'tt_content:new/' . $pid . '/' . $vv));
		}
		return 'jumpToUrl(' . \TYPO3\CMS\Core\Utility\GeneralUtility::quoteJSvalue($location) . ');return false;';
	}

	/**
	 * Wrapping the record header  (from getRecordHeader())
	 *
	 * @param string $str HTML content
	 * @param array $row Record array.
	 * @return string HTML content
	 */
	public function wrapRecordHeader($str, $row) {
		if ($row['uid'] == $this->moveUid) {
			return '<img' . \TYPO3\CMS\Backend\Utility\IconUtility::skinImg($GLOBALS['BACK_PATH'], 'gfx/content_client.gif', 'width="7" height="10"') . ' alt="" />' . $str;
		} else {
			return $str;
		}
	}

}

\TYPO3\CMS\Core\Utility\GeneralUtility::deprecationLog(
	'The page layout class is moved to an own module. Please use BackendUtility::getModuleUrl(\'web_layout\') to link to db_layout.php. This script will be removed with version TYPO3 CMS 8.'
);

$SOBE = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Backend\Controller\PageLayoutController::class);
$SOBE->init();
$SOBE->clearCache();
$SOBE->main();
$SOBE->printContent();
