<?php
namespace TYPO3\CMS\Frontend\ContentObject;

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
 * Contains CONTENT class object.
 *
 * @author Xavier Perseguers <typo3@perseguers.ch>
 * @author Steffen Kamper <steffen@typo3.org>
 */
class ContentContentObject extends AbstractContentObject {

	/**
	 * Rendering the cObject, CONTENT
	 *
	 * @param array $conf Array of TypoScript properties
	 * @return string Output
	 */
	public function render($conf = array()) {
		if (!empty($conf['if.']) && !$this->cObj->checkIf($conf['if.'])) {
			return '';
		}

		$theValue = '';
		$originalRec = $GLOBALS['TSFE']->currentRecord;
		// If the currentRecord is set, we register, that this record has invoked this function.
		// It's should not be allowed to do this again then!!
		if ($originalRec) {
			++$GLOBALS['TSFE']->recordRegister[$originalRec];
		}
		$conf['table'] = isset($conf['table.']) ? trim($this->cObj->stdWrap($conf['table'], $conf['table.'])) : trim($conf['table']);
		$renderObjName = $conf['renderObj'] ?: '<' . $conf['table'];
		$renderObjKey = $conf['renderObj'] ? 'renderObj' : '';
		$renderObjConf = $conf['renderObj.'];
		$slide = isset($conf['slide.']) ? (int)$this->cObj->stdWrap($conf['slide'], $conf['slide.']) : (int)$conf['slide'];
		if (!$slide) {
			$slide = 0;
		}
		$slideCollect = isset($conf['slide.']['collect.']) ? (int)$this->cObj->stdWrap($conf['slide.']['collect'], $conf['slide.']['collect.']) : (int)$conf['slide.']['collect'];
		if (!$slideCollect) {
			$slideCollect = 0;
		}
		$slideCollectReverse = isset($conf['slide.']['collectReverse.']) ? (int)$this->cObj->stdWrap($conf['slide.']['collectReverse'], $conf['slide.']['collectReverse.']) : (int)$conf['slide.']['collectReverse'];
		$slideCollectReverse = $slideCollectReverse ? TRUE : FALSE;
		$slideCollectFuzzy = isset($conf['slide.']['collectFuzzy.']) ? (int)$this->cObj->stdWrap($conf['slide.']['collectFuzzy'], $conf['slide.']['collectFuzzy.']) : (int)$conf['slide.']['collectFuzzy'];
		if ($slideCollectFuzzy) {
			$slideCollectFuzzy = TRUE;
		} else {
			$slideCollectFuzzy = FALSE;
		}
		if (!$slideCollect) {
			$slideCollectFuzzy = TRUE;
		}
		$again = FALSE;
		$tmpValue = '';
		do {
			$res = $this->cObj->exec_getQuery($conf['table'], $conf['select.']);
			if ($error = $GLOBALS['TYPO3_DB']->sql_error()) {
				$GLOBALS['TT']->setTSlogMessage($error, 3);
			} else {
				$this->cObj->currentRecordTotal = $GLOBALS['TYPO3_DB']->sql_num_rows($res);
				$GLOBALS['TT']->setTSlogMessage('NUMROWS: ' . $GLOBALS['TYPO3_DB']->sql_num_rows($res));
				/** @var $cObj \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer */
				$cObj = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer::class);
				$cObj->setParent($this->cObj->data, $this->cObj->currentRecord);
				$this->cObj->currentRecordNumber = 0;
				$cobjValue = '';
				while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
					// Versioning preview:
					$GLOBALS['TSFE']->sys_page->versionOL($conf['table'], $row, TRUE);
					// Language overlay:
					if (is_array($row) && $GLOBALS['TSFE']->sys_language_contentOL) {
						if ($conf['table'] == 'pages') {
							$row = $GLOBALS['TSFE']->sys_page->getPageOverlay($row);
						} else {
							$row = $GLOBALS['TSFE']->sys_page->getRecordOverlay($conf['table'], $row, $GLOBALS['TSFE']->sys_language_content, $GLOBALS['TSFE']->sys_language_contentOL);
						}
					}
					// Might be unset in the sys_language_contentOL
					if (is_array($row)) {
						// Call hook for possible manipulation of database row for cObj->data
						if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_content_content.php']['modifyDBRow'])) {
							foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_content_content.php']['modifyDBRow'] as $_classRef) {
								$_procObj = \TYPO3\CMS\Core\Utility\GeneralUtility::getUserObj($_classRef);
								$_procObj->modifyDBRow($row, $conf['table']);
							}
						}
						if ($GLOBALS['TYPO3_CONF_VARS']['FE']['activateContentAdapter']) {
							\TYPO3\CMS\Core\Resource\Service\FrontendContentAdapterService::modifyDBRow($row, $conf['table']);
						}
						if (!$GLOBALS['TSFE']->recordRegister[($conf['table'] . ':' . $row['uid'])]) {
							$this->cObj->currentRecordNumber++;
							$cObj->parentRecordNumber = $this->cObj->currentRecordNumber;
							$GLOBALS['TSFE']->currentRecord = $conf['table'] . ':' . $row['uid'];
							$this->cObj->lastChanged($row['tstamp']);
							$cObj->start($row, $conf['table']);
							$tmpValue = $cObj->cObjGetSingle($renderObjName, $renderObjConf, $renderObjKey);
							$cobjValue .= $tmpValue;
						}
					}
				}
				$GLOBALS['TYPO3_DB']->sql_free_result($res);
			}
			if ($slideCollectReverse) {
				$theValue = $cobjValue . $theValue;
			} else {
				$theValue .= $cobjValue;
			}
			if ($slideCollect > 0) {
				$slideCollect--;
			}
			if ($slide) {
				if ($slide > 0) {
					$slide--;
				}
				$conf['select.']['pidInList'] = $this->cObj->getSlidePids($conf['select.']['pidInList'], $conf['select.']['pidInList.']);
				if (isset($conf['select.']['pidInList.'])) {
					unset($conf['select.']['pidInList.']);
				}
				$again = (string)$conf['select.']['pidInList'] !== '';
			}
		} while ($again && $slide && (string)$tmpValue === '' && $slideCollectFuzzy || $slideCollect);

		$wrap = isset($conf['wrap.']) ? $this->cObj->stdWrap($conf['wrap'], $conf['wrap.']) : $conf['wrap'];
		if ($wrap) {
			$theValue = $this->cObj->wrap($theValue, $wrap);
		}
		if (isset($conf['stdWrap.'])) {
			$theValue = $this->cObj->stdWrap($theValue, $conf['stdWrap.']);
		}
		// Restore
		$GLOBALS['TSFE']->currentRecord = $originalRec;
		if ($originalRec) {
			--$GLOBALS['TSFE']->recordRegister[$originalRec];
		}
		return $theValue;
	}

}
