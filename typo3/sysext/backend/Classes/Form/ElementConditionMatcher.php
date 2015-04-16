<?php
namespace TYPO3\CMS\Backend\Form;

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

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

/**
 * Class ElementConditionMatcher implements the TCA 'displayCond' option.
 * The display condition is a colon separated string which describes
 * the condition to decide whether a form field should be displayed.
 */
class ElementConditionMatcher {

	/**
	 * @var string
	 */
	protected $flexformValueKey = '';

	/**
	 * @var array
	 */
	protected $record = array();

	/**
	 * Evaluates the provided condition and returns TRUE if the form
	 * element should be displayed.
	 *
	 * The condition string is separated by colons and the first part
	 * indicates what type of evaluation should be performed.
	 *
	 * @param string $displayCondition
	 * @param array $record
	 * @param string $flexformValueKey
	 * @param int $recursionLevel Internal level of recursion
	 * @return bool TRUE if condition evaluates successfully
	 */
	public function match($displayCondition, array $record = array(), $flexformValueKey = '', $recursionLevel = 0) {
		if ($recursionLevel > 99) {
			// This should not happen, treat as misconfiguration
			return TRUE;
		}
		if (!is_array($displayCondition)) {
			// DisplayCondition is not an array - just get its value
			$result = $this->matchSingle($displayCondition, $record, $flexformValueKey);
		} else {
			// Multiple conditions given as array ('AND|OR' => condition array)
			$conditionEvaluations = array(
				'AND' => array(),
				'OR' => array(),
			);
			foreach ($displayCondition as $logicalOperator => $groupedDisplayConditions) {
				$logicalOperator = strtoupper($logicalOperator);
				if (($logicalOperator !== 'AND' && $logicalOperator !== 'OR') || !is_array($groupedDisplayConditions)) {
					// Invalid line. Skip it.
					continue;
				} else {
					foreach ($groupedDisplayConditions as $key => $singleDisplayCondition) {
						$key = strtoupper($key);
						if (($key === 'AND' || $key === 'OR') && is_array($singleDisplayCondition)) {
							// Recursion statement: condition is 'AND' or 'OR' and is pointing to an array (should be conditions again)
							$conditionEvaluations[$logicalOperator][] = $this->match(
								array($key => $singleDisplayCondition),
								$record,
								$flexformValueKey,
								$recursionLevel + 1
							);
						} else {
							// Condition statement: collect evaluation of this single condition.
							$conditionEvaluations[$logicalOperator][] = $this->matchSingle(
								$singleDisplayCondition,
								$record,
								$flexformValueKey
							);
						}
					}
				}
			}
			if (count($conditionEvaluations['OR']) > 0 && in_array(TRUE, $conditionEvaluations['OR'], TRUE)) {
				// There are OR conditions and at least one of them is TRUE
				$result = TRUE;
			} elseif (count($conditionEvaluations['AND']) > 0 && !in_array(FALSE, $conditionEvaluations['AND'], TRUE)) {
				// There are AND conditions and none of them is FALSE
				$result = TRUE;
			} elseif (count($conditionEvaluations['OR']) > 0 || count($conditionEvaluations['AND']) > 0) {
				// There are some conditions. But no OR was TRUE and at least one AND was FALSE
				$result = FALSE;
			} else {
				// There are no proper conditions - misconfiguration. Return TRUE.
				$result = TRUE;
			}
		}
		return $result;
	}

	/**
	 * Evaluates the provided condition and returns TRUE if the form
	 * element should be displayed.
	 *
	 * The condition string is separated by colons and the first part
	 * indicates what type of evaluation should be performed.
	 *
	 * @param string $displayCondition
	 * @param array $record
	 * @param string $flexformValueKey
	 * @return bool
	 * @see match()
	 */
	protected function matchSingle($displayCondition, array $record = array(), $flexformValueKey = '') {
		$this->record = $record;
		$this->flexformValueKey = $flexformValueKey;
		$result = FALSE;
		list($matchType, $condition) = explode(':', $displayCondition, 2);
		switch ($matchType) {
			case 'EXT':
				$result = $this->matchExtensionCondition($condition);
				break;
			case 'FIELD':
				$result = $this->matchFieldCondition($condition);
				break;
			case 'HIDE_FOR_NON_ADMINS':
				$result = $this->matchHideForNonAdminsCondition();
				break;
			case 'HIDE_L10N_SIBLINGS':
				$result = $this->matchHideL10nSiblingsCondition($condition);
				break;
			case 'REC':
				$result = $this->matchRecordCondition($condition);
				break;
			case 'VERSION':
				$result = $this->matchVersionCondition($condition);
				break;
			case 'USER':
				$result = $this->matchUserCondition($condition);
				break;
		}
		return $result;
	}

	/**
	 * Evaluates conditions concerning extensions
	 *
	 * Example:
	 * "EXT:saltedpasswords:LOADED:TRUE" => TRUE, if extension saltedpasswords is loaded.
	 *
	 * @param string $condition
	 * @return bool
	 */
	protected function matchExtensionCondition($condition) {
		$result = FALSE;
		list($extensionKey, $operator, $operand) = explode(':', $condition, 3);
		if ($operator === 'LOADED') {
			if (strtoupper($operand) === 'TRUE') {
				$result = ExtensionManagementUtility::isLoaded($extensionKey);
			} elseif (strtoupper($operand) === 'FALSE') {
				$result = !ExtensionManagementUtility::isLoaded($extensionKey);
			}
		}
		return $result;
	}

	/**
	 * Evaluates conditions concerning a field of the current record.
	 * Requires a record set via ->setRecord()
	 *
	 * Example:
	 * "FIELD:sys_language_uid:>:0" => TRUE, if the field 'sys_language_uid' is greater than 0
	 *
	 * @param string $condition
	 * @return bool
	 */
	protected function matchFieldCondition($condition) {
		list($fieldName, $operator, $operand) = explode(':', $condition, 3);
		if ($this->flexformValueKey) {
			if (strpos($fieldName, 'parentRec.') !== FALSE) {
				$fieldNameParts = explode('.', $fieldName, 2);
				$fieldValue = $this->record['parentRec'][$fieldNameParts[1]];
			} else {
				$fieldValue = $this->record[$fieldName][$this->flexformValueKey];
			}
		} else {
			$fieldValue = $this->record[$fieldName];
		}

		$result = FALSE;
		switch ($operator) {
			case 'REQ':
				if (strtoupper($operand) === 'TRUE') {
					$result = (bool)$fieldValue;
				} else {
					$result = !$fieldValue;
				}
				break;
			case '>':
				$result = $fieldValue > $operand;
				break;
			case '<':
				$result = $fieldValue < $operand;
				break;
			case '>=':
				$result = $fieldValue >= $operand;
				break;
			case '<=':
				$result = $fieldValue <= $operand;
				break;
			case '-':
			case '!-':
				list($minimum, $maximum) = explode('-', $operand);
				$result = $fieldValue >= $minimum && $fieldValue <= $maximum;
				if ($operator[0] === '!') {
					$result = !$result;
				}
				break;
			case 'IN':
			case '!IN':
			case '=':
			case '!=':
				$result = \TYPO3\CMS\Core\Utility\GeneralUtility::inList($operand, $fieldValue);
				if ($operator[0] === '!') {
					$result = !$result;
				}
				break;
			case 'BIT':
			case '!BIT':
				$result = ((int)$fieldValue & $operand) ? TRUE : FALSE;
				if ($operator[0] === '!') {
					$result = !$result;
				}
				break;
		}
		return $result;
	}

	/**
	 * Evaluates TRUE if current backend user is an admin.
	 *
	 * @return bool
	 */
	protected function matchHideForNonAdminsCondition() {
		return (bool)$this->getBackendUser()->isAdmin();
	}

	/**
	 * Evaluates whether the field is a value for the default language.
	 * Works only for <langChildren>=1, otherwise it has no effect.
	 *
	 * @param string $condition
	 * @return bool
	 */
	protected function matchHideL10nSiblingsCondition($condition) {
		$result = FALSE;
		if ($this->flexformValueKey === 'vDEF') {
			$result = TRUE;
		} elseif ($condition === 'except_admin' && $this->getBackendUser()->isAdmin()) {
			$result = TRUE;
		}
		return $result;
	}

	/**
	 * Evaluates conditions concerning the status of the current record.
	 * Requires a record set via ->setRecord()
	 *
	 * Example:
	 * "REC:NEW:FALSE" => TRUE, if the record is already persisted (has a uid > 0)
	 *
	 * @param string $condition
	 * @return bool
	 */
	protected function matchRecordCondition($condition) {
		$result = FALSE;
		list($operator, $operand) = explode(':', $condition, 2);
		if ($operator === 'NEW') {
			if (strtoupper($operand) === 'TRUE') {
				$result = !((int)$this->record['uid'] > 0);
			} elseif (strtoupper($operand) === 'FALSE') {
				$result = ((int)$this->record['uid'] > 0);
			}
		}
		return $result;
	}

	/**
	 * Evaluates whether the current record is versioned.
	 * Requires a record set via ->setRecord()
	 *
	 * @param string $condition
	 * @return bool
	 */
	protected function matchVersionCondition($condition) {
		$result = FALSE;
		list($operator, $operand) = explode(':', $condition, 2);
		if ($operator === 'IS') {
			$isNewRecord = !((int)$this->record['uid'] > 0);
			// Detection of version can be done be detecting the workspace of the user
			$isUserInWorkspace = $this->getBackendUser()->workspace > 0;
			if ((int)$this->record['pid'] === -1 || (int)$this->record['_ORIG_pid'] === -1) {
				$isRecordDetectedAsVersion = TRUE;
			} else {
				$isRecordDetectedAsVersion = FALSE;
			}
			// New records in a workspace are not handled as a version record
			// if it's no new version, we detect versions like this:
			// -- if user is in workspace: always TRUE
			// -- if editor is in live ws: only TRUE if pid == -1
			$isVersion = ($isUserInWorkspace || $isRecordDetectedAsVersion) && !$isNewRecord;
			if (strtoupper($operand) === 'TRUE') {
				$result = $isVersion;
			} elseif (strtoupper($operand) === 'FALSE') {
				$result = !$isVersion;
			}
		}
		return $result;
	}

	/**
	 * Get current backend user
	 *
	 * @return \TYPO3\CMS\Core\Authentication\BackendUserAuthentication
	 */
	protected function getBackendUser() {
		return $GLOBALS['BE_USER'];
	}

	/**
	 * Evaluates via the referenced user-defined method
	 *
	 * @param string $condition
	 * @return bool
	 */
	protected function matchUserCondition($condition) {
		$conditionParameters = explode(':', $condition);
		$userFunction = array_shift($conditionParameters);

		$parameter = array(
			'record' => $this->record,
			'flexformValueKey' => $this->flexformValueKey,
			'conditionParameters' => $conditionParameters
		);

		return (bool)\TYPO3\CMS\Core\Utility\GeneralUtility::callUserFunction($userFunction, $parameter, $this);
	}
}
