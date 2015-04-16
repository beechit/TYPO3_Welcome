<?php
namespace TYPO3\CMS\Frontend\Configuration\TypoScript\ConditionMatching;

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

use TYPO3\CMS\Core\Configuration\TypoScript\ConditionMatching\AbstractConditionMatcher;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Matching TypoScript conditions for frontend disposal.
 *
 * Used with the TypoScript parser. Matches browserinfo
 * and IP numbers for use with templates.
 *
 * @author Kasper Skårhøj <kasperYYYY@typo3.com>
 */
class ConditionMatcher extends AbstractConditionMatcher {

	/**
	 * Evaluates a TypoScript condition given as input,
	 * eg. "[browser=net][...(other conditions)...]"
	 *
	 * @param string $string The condition to match against its criterias.
	 * @return bool Whether the condition matched
	 * @see \TYPO3\CMS\Core\TypoScript\Parser\TypoScriptParser::parse()
	 * @throws \TYPO3\CMS\Core\Configuration\TypoScript\Exception\InvalidTypoScriptConditionException
	 */
	protected function evaluateCondition($string) {
		list($key, $value) = GeneralUtility::trimExplode('=', $string, FALSE, 2);
		$result = $this->evaluateConditionCommon($key, $value);

		if (is_bool($result)) {
			return $result;
		} else {
			switch ($key) {
				case 'usergroup':
					$groupList = $this->getGroupList();
					// '0,-1' is the default usergroups when not logged in!
					if ($groupList != '0,-1') {
						$values = GeneralUtility::trimExplode(',', $value, TRUE);
						foreach ($values as $test) {
							if ($test == '*' || GeneralUtility::inList($groupList, $test)) {
								return TRUE;
							}
						}
					}
					break;
				case 'treeLevel':
					$values = GeneralUtility::trimExplode(',', $value, TRUE);
					$treeLevel = count($this->rootline) - 1;
					foreach ($values as $test) {
						if ($test == $treeLevel) {
							return TRUE;
						}
					}
					break;
				case 'PIDupinRootline':
				case 'PIDinRootline':
					$values = GeneralUtility::trimExplode(',', $value, TRUE);
					if ($key == 'PIDinRootline' || !in_array($this->pageId, $values)) {
						foreach ($values as $test) {
							foreach ($this->rootline as $rlDat) {
								if ($rlDat['uid'] == $test) {
									return TRUE;
								}
							}
						}
					}
					break;
				default:
					$conditionResult = $this->evaluateCustomDefinedCondition($string);
					if ($conditionResult !== NULL) {
						return $conditionResult;
					}
			}
		}

		return FALSE;
	}

	/**
	 * Returns GP / ENV / TSFE vars
	 *
	 * @param string $var Identifier
	 * @return mixed The value of the variable pointed to or NULL if variable did not exist
	 */
	protected function getVariable($var) {
		$vars = explode(':', $var, 2);
		$val = $this->getVariableCommon($vars);
		if (is_null($val)) {
			$splitAgain = explode('|', $vars[1], 2);
			$k = trim($splitAgain[0]);
			if ($k) {
				switch ((string)trim($vars[0])) {
					case 'TSFE':
						$val = $this->getGlobal('TSFE|' . $vars[1]);
						break;
					default:
				}
			}
		}
		return $val;
	}

	/**
	 * Get the usergroup list of the current user.
	 *
	 * @return string The usergroup list of the current user
	 */
	protected function getGroupList() {
		return $this->getTypoScriptFrontendController()->gr_list;
	}

	/**
	 * Determines the current page Id.
	 *
	 * @return int The current page Id
	 */
	protected function determinePageId() {
		return (int)$this->getTypoScriptFrontendController()->id;
	}

	/**
	 * Gets the properties for the current page.
	 *
	 * @return array The properties for the current page.
	 */
	protected function getPage() {
		return $this->getTypoScriptFrontendController()->page;
	}

	/**
	 * Determines the rootline for the current page.
	 *
	 * @return array The rootline for the current page.
	 */
	protected function determineRootline() {
		return (array)$this->getTypoScriptFrontendController()->tmpl->rootLine;
	}

	/**
	 * Get the id of the current user.
	 *
	 * @return int The id of the current user
	 */
	protected function getUserId() {
		return $this->getTypoScriptFrontendController()->fe_user->user['uid'];
	}

	/**
	 * Determines if a user is logged in.
	 *
	 * @return bool Determines if a user is logged in
	 */
	protected function isUserLoggedIn() {
		return (bool)$this->getTypoScriptFrontendController()->loginUser;
	}

	/**
	 * Set/write a log message.
	 *
	 * @param string $message The log message to set/write
	 * @return void
	 */
	protected function log($message) {
		if ($this->getTimeTracker() !== NULL) {
			$this->getTimeTracker()->setTSlogMessage($message, 3);
		}
	}

	/**
	 * @return \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController
	 */
	protected function getTypoScriptFrontendController() {
		return $GLOBALS['TSFE'];
	}

	/**
	 * @return \TYPO3\CMS\Core\TimeTracker\TimeTracker
	 */
	protected function getTimeTracker() {
		return $GLOBALS['TT'];
	}

}
