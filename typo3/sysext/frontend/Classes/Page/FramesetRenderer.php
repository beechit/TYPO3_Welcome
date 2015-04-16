<?php
namespace TYPO3\CMS\Frontend\Page;

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
 * Rendering of framesets
 *
 * @author Kasper Skårhøj <kasperYYYY@typo3.com>
 */
class FramesetRenderer {

	/**
	 * Generates a frameset based on input configuration in a TypoScript array.
	 *
	 * @param array $setup The TypoScript properties of the PAGE object property "frameSet.". See link.
	 * @return string A <frameset> tag.
	 * @see \TYPO3\CMS\Frontend\Page\PageGenerator::renderContentWithHeader()
	 */
	public function make($setup) {
		$content = '';
		if (is_array($setup)) {
			$sKeyArray = \TYPO3\CMS\Core\TypoScript\TemplateService::sortedKeyList($setup);
			foreach ($sKeyArray as $theKey) {
				$theValue = $setup[$theKey];
				if ((int)$theKey && ($conf = $setup[$theKey . '.'])) {
					switch ($theValue) {
						case 'FRAME':
							$typeNum = (int)$GLOBALS['TSFE']->tmpl->setup[$conf['obj'] . '.']['typeNum'];
							if (!$conf['src'] && !$typeNum) {
								$typeNum = -1;
							}
							$content .= '<frame' . $this->frameParams($conf, $typeNum) . ' />' . LF;
							break;
						case 'FRAMESET':
							$frameset = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Frontend\Page\FramesetRenderer::class);
							$content .= $frameset->make($conf) . LF;
							break;
					}
				}
			}
			return '<frameset' . $this->framesetParams($setup) . '>' . LF . $content . '</frameset>';
		}
	}

	/**
	 * Creates the attributes for a <frame> tag based on a $conf array and the type number
	 *
	 * @param array $setup Configuration for the parameter generation for the FRAME set. See link
	 * @param int $typeNum The typenumber to use for the link.
	 * @return string String with attributes for the frame-tag. With a prefixed space character.
	 * @access private
	 * @link http://typo3.org/documentation/document-library/references/doc_core_tsref/current/view/7/9/
	 */
	public function frameParams($setup, $typeNum) {
		$paramStr = '';
		$name = $setup['obj'];
		if ($setup['src'] || $setup['src.']) {
			$src = $setup['src'];
			if (is_array($setup['src.'])) {
				$src = $GLOBALS['TSFE']->cObj->stdWrap($src, $setup['src.']);
			}
			$paramStr .= ' src="' . htmlspecialchars($src) . '"';
		} else {
			$LD = $GLOBALS['TSFE']->tmpl->linkData($GLOBALS['TSFE']->page, '', $GLOBALS['TSFE']->no_cache, '', '', ($setup['options'] ? '&' . $setup['options'] : '') . $GLOBALS['TSFE']->cObj->getClosestMPvalueForPage($GLOBALS['TSFE']->page['uid']), (int)$typeNum);
			$finalURL = $LD['totalURL'];
			$paramStr .= ' src="' . htmlspecialchars($finalURL) . '"';
		}
		if ($setup['name']) {
			$paramStr .= ' name="' . $setup['name'] . '"';
		} else {
			$paramStr .= ' name="' . $name . '"';
		}
		if ($setup['params']) {
			$paramStr .= ' ' . $setup['params'];
		}
		return $paramStr;
	}

	/**
	 * Creates the attributes for a <frameset> tag based on a conf array($setup)
	 *
	 * @param array $setup The setup array(TypoScript properties)
	 * @return string Attributes with preceding space.
	 * @access private
	 * @see make()
	 */
	public function framesetParams($setup) {
		$paramStr = '';
		if ($setup['cols']) {
			$paramStr .= ' cols="' . $setup['cols'] . '"';
		}
		if ($setup['rows']) {
			$paramStr .= ' rows="' . $setup['rows'] . '"';
		}
		if ($setup['params']) {
			$paramStr .= ' ' . $setup['params'];
		}
		return $paramStr;
	}

}
