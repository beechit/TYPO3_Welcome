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
 * Contains FILE class object.
 *
 * @author Xavier Perseguers <typo3@perseguers.ch>
 * @author Steffen Kamper <steffen@typo3.org>
 */
class FileContentObject extends AbstractContentObject {

	/**
	 * Rendering the cObject, FILE
	 *
	 * @param array $conf Array of TypoScript properties
	 * @return string Output
	 */
	public function render($conf = array()) {
		$file = isset($conf['file.']) ? $this->cObj->stdWrap($conf['file'], $conf['file.']) : $conf['file'];
		$theValue = $this->cObj->fileResource($file, trim($this->cObj->getAltParam($conf, FALSE)));
		$linkWrap = isset($conf['linkWrap.']) ? $this->cObj->stdWrap($conf['linkWrap'], $conf['linkWrap.']) : $conf['linkWrap'];
		if ($linkWrap) {
			$theValue = $this->cObj->linkWrap($theValue, $linkWrap);
		}
		$wrap = isset($conf['wrap.']) ? $this->cObj->stdWrap($conf['wrap'], $conf['wrap.']) : $conf['wrap'];
		if ($wrap) {
			$theValue = $this->cObj->wrap($theValue, $wrap);
		}
		if (isset($conf['stdWrap.'])) {
			$theValue = $this->cObj->stdWrap($theValue, $conf['stdWrap.']);
		}
		return $theValue;
	}

}
