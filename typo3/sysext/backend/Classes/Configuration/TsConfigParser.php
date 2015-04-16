<?php
namespace TYPO3\CMS\Backend\Configuration;

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
 * A TS-Config parsing class which performs condition evaluation
 *
 * @author Kraft Bernhard <kraftb@kraftb.at>
 */
class TsConfigParser extends \TYPO3\CMS\Core\TypoScript\Parser\TypoScriptParser {

	/**
	 * @var array
	 */
	protected $rootLine = array();

	/**
	 * Parses the passed TS-Config using conditions and caching
	 *
	 * @param string $TStext The TSConfig being parsed
	 * @param string $type The type of TSConfig (either "userTS" or "PAGES")
	 * @param int $id The uid of the page being handled
	 * @param array $rootLine The rootline of the page being handled
	 * @return array Array containing the parsed TSConfig and a flag whether the content was retrieved from cache
	 */
	public function parseTSconfig($TStext, $type, $id = 0, array $rootLine = array()) {
		$this->type = $type;
		$this->id = $id;
		$this->rootLine = $rootLine;
		$hash = md5($type . ':' . $TStext);
		$cachedContent = BackendUtility::getHash($hash);
		if (is_array($cachedContent)) {
			$storedData = $cachedContent[0];
			$storedMD5 = $cachedContent[1];
			$storedData['match'] = array();
			$storedData = $this->matching($storedData);
			$checkMD5 = md5(serialize($storedData));
			if ($checkMD5 == $storedMD5) {
				$res = array(
					'TSconfig' => $storedData['TSconfig'],
					'cached' => 1,
					'hash' => $hash
				);
			} else {
				$shash = md5($checkMD5 . $hash);
				$cachedSpec = BackendUtility::getHash($shash);
				if (is_array($cachedSpec)) {
					$storedData = $cachedSpec;
					$res = array(
						'TSconfig' => $storedData['TSconfig'],
						'cached' => 1,
						'hash' => $shash
					);
				} else {
					$storeData = $this->parseWithConditions($TStext);
					BackendUtility::storeHash($shash, $storeData, $type . '_TSconfig');
					$res = array(
						'TSconfig' => $storeData['TSconfig'],
						'cached' => 0,
						'hash' => $shash
					);
				}
			}
		} else {
			$storeData = $this->parseWithConditions($TStext);
			$md5 = md5(serialize($storeData));
			BackendUtility::storeHash($hash, array($storeData, $md5), $type . '_TSconfig');
			$res = array(
				'TSconfig' => $storeData['TSconfig'],
				'cached' => 0,
				'hash' => $hash
			);
		}
		return $res;
	}

	/**
	 * Does the actual parsing using the parent objects "parse" method. Creates the match-Object
	 *
	 * @param string $TSconfig The TSConfig being parsed
	 * @return array Array containing the parsed TSConfig, the encountered sectiosn, the matched sections
	 */
	protected function parseWithConditions($TSconfig) {
		/** @var $matchObj \TYPO3\CMS\Backend\Configuration\TypoScript\ConditionMatching\ConditionMatcher */
		$matchObj = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Backend\Configuration\TypoScript\ConditionMatching\ConditionMatcher::class);
		$matchObj->setRootline($this->rootLine);
		$matchObj->setPageId($this->id);
		$this->parse($TSconfig, $matchObj);
		return array(
			'TSconfig' => $this->setup,
			'sections' => $this->sections,
			'match' => $this->sectionsMatch
		);
	}

	/**
	 * Is just going through an array of conditions to determine which are matching (for getting correct cache entry)
	 *
	 * @param array $cc An array containing the sections to match
	 * @return array The input array with matching sections filled into the "match" key
	 */
	protected function matching(array $cc) {
		if (is_array($cc['sections'])) {
			/** @var $matchObj \TYPO3\CMS\Backend\Configuration\TypoScript\ConditionMatching\ConditionMatcher */
			$matchObj = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Backend\Configuration\TypoScript\ConditionMatching\ConditionMatcher::class);
			$matchObj->setRootline($this->rootLine);
			$matchObj->setPageId($this->id);
			foreach ($cc['sections'] as $key => $pre) {
				if ($matchObj->match($pre)) {
					$cc['match'][$key] = $pre;
				}
			}
		}
		return $cc;
	}

}
