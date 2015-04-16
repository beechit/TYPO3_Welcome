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
 * Contains COA class object.
 *
 * @author Xavier Perseguers <typo3@perseguers.ch>
 * @author Steffen Kamper <steffen@typo3.org>
 */
class ContentObjectArrayContentObject extends AbstractContentObject {

	/**
	 * Rendering the cObject, COBJ_ARRAY / COA
	 *
	 * @param array $conf Array of TypoScript properties
	 * @return string Output
	 */
	public function render($conf = array()) {
		if (empty($conf)) {
			$GLOBALS['TT']->setTSlogMessage('No elements in this content object array (COBJ_ARRAY, COA).', 2);
			return '';
		}
		if (!empty($conf['if.']) && !$this->cObj->checkIf($conf['if.'])) {
			return '';
		}

		$this->cObj->includeLibs($conf);
		$content = $this->cObj->cObjGet($conf);
		$wrap = isset($conf['wrap.']) ? $this->cObj->stdWrap($conf['wrap'], $conf['wrap.']) : $conf['wrap'];
		if ($wrap) {
			$content = $this->cObj->wrap($content, $wrap);
		}
		if (isset($conf['stdWrap.'])) {
			$content = $this->cObj->stdWrap($content, $conf['stdWrap.']);
		}
		return $content;
	}

}
