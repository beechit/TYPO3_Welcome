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
 * Contains CASE class object.
 *
 * @author Xavier Perseguers <typo3@perseguers.ch>
 * @author Steffen Kamper <steffen@typo3.org>
 */
class CaseContentObject extends AbstractContentObject {

	/**
	 * Rendering the cObject, CASE
	 *
	 * @param array $conf Array of TypoScript properties
	 * @return string Output
	 */
	public function render($conf = array()) {
		if (!empty($conf['if.']) && !$this->cObj->checkIf($conf['if.'])) {
			return '';
		}

		$setCurrent = isset($conf['setCurrent.']) ? $this->cObj->stdWrap($conf['setCurrent'], $conf['setCurrent.']) : $conf['setCurrent'];
		if ($setCurrent) {
			$this->cObj->data[$this->cObj->currentValKey] = $setCurrent;
		}
		$key = isset($conf['key.']) ? $this->cObj->stdWrap($conf['key'], $conf['key.']) : $conf['key'];
		$key = (string)$conf[$key] !== '' ? $key : 'default';
		// If no "default" property is available, then an empty string is returned
		if ($key === 'default' && $conf['default'] === NULL) {
			$theValue = '';
		} else {
			$theValue = $this->cObj->cObjGetSingle($conf[$key], $conf[$key . '.'], $key);
		}
		if (isset($conf['stdWrap.'])) {
			$theValue = $this->cObj->stdWrap($theValue, $conf['stdWrap.']);
		}
		return $theValue;
	}

}
