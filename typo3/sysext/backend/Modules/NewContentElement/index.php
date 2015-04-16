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
 * Local position map class
 *
 * @author Kasper Skårhøj <kasperYYYY@typo3.com>
 */
class ext_posMap extends \TYPO3\CMS\Backend\Tree\View\PagePositionMap {

	/**
	 * @var bool
	 */
	public $dontPrintPageInsertIcons = 1;

	/**
	 * Wrapping the title of the record - here we just return it.
	 *
	 * @param string $str The title value.
	 * @param array $row The record row.
	 * @return string Wrapped title string.
	 */
	public function wrapRecordTitle($str, $row) {
		return $str;
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
		$location = $this->backPath . 'alt_doc.php?edit[tt_content][' . (is_array($row) ? -$row['uid'] : $pid) . ']=new&defVals[tt_content][colPos]=' . $vv . '&defVals[tt_content][sys_language_uid]=' . $sys_lang . '&returnUrl=' . rawurlencode($GLOBALS['SOBE']->R_URI);
		return 'window.location.href=' . \TYPO3\CMS\Core\Utility\GeneralUtility::quoteJSvalue($location) . '+document.editForm.defValues.value; return false;';
	}

}

$SOBE = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Backend\Controller\ContentElement\NewContentElementController::class);
$SOBE->init();
$SOBE->main();
$SOBE->printContent();