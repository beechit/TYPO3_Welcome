<?php
namespace TYPO3\CMS\Backend\Form\Element;

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
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Lang\LanguageService;

/**
 * TCEforms wizard for rendering an AJAX selector for records
 *
 * @author Andreas Wolf <andreas.wolf@ikt-werk.de>
 * @author Benjamin Mack <benni@typo3.org>
 */
class SuggestElement {

	/**
	 * @var int Count the number of ajax selectors used
	 */
	public $suggestCount = 0;

	/**
	 * @var string
	 */
	public $cssClass = 'typo3-TCEforms-suggest';

	/**
	 * @var \TYPO3\CMS\Backend\Form\FormEngine
	 */
	public $TCEformsObj;

	/**
	 * Initialize an instance of SuggestElement
	 *
	 * @param \TYPO3\CMS\Backend\Form\FormEngine $tceForms Reference to an TCEforms instance
	 * @return void
	 */
	public function init($tceForms) {
		$this->TCEformsObj = $tceForms;
	}

	/**
	 * Renders an ajax-enabled text field. Also adds required JS
	 *
	 * @param string $fieldname The fieldname in the form
	 * @param string $table The table we render this selector for
	 * @param string $field The field we render this selector for
	 * @param array $row The row which is currently edited
	 * @param array $config The TSconfig of the field
	 * @return string The HTML code for the selector
	 */
	public function renderSuggestSelector($fieldname, $table, $field, array $row, array $config) {
		$languageService = $this->getLanguageService();
		$this->suggestCount++;
		$containerCssClass = $this->cssClass . ' ' . $this->cssClass . '-position-right';
		$suggestId = 'suggest-' . $table . '-' . $field . '-' . $row['uid'];
		$isFlexFormField = $GLOBALS['TCA'][$table]['columns'][$field]['config']['type'] === 'flex';
		if ($isFlexFormField) {
			$fieldPattern = 'data[' . $table . '][' . $row['uid'] . '][';
			$flexformField = str_replace($fieldPattern, '', $fieldname);
			$flexformField = substr($flexformField, 0, -1);
			$field = str_replace(array(']['), '|', $flexformField);
		}
		$selector = '
		<div class="' . $containerCssClass . '" id="' . $suggestId . '">
			<div class="input-group">
				<span class="input-group-addon"><i class="fa fa-search"></i></span>
				<input type="search" id="' . $fieldname . 'Suggest" value="' . $languageService->sL('LLL:EXT:lang/locallang_core.xlf:labels.findRecord') . '" class="form-control ' . $this->cssClass . '-search" />
				<div class="' . $this->cssClass . '-indicator" style="display: none;" id="' . $fieldname . 'SuggestIndicator">
					<img src="' . $GLOBALS['BACK_PATH'] . 'gfx/spinner.gif" alt="' . $languageService->sL('LLL:EXT:lang/locallang_core.xlf:alttext.suggestSearching') . '" />
				</div>
				<div class="' . $this->cssClass . '-choices" style="display: none;" id="' . $fieldname . 'SuggestChoices"></div>
			</div>

		</div>';
		// Get minimumCharacters from TCA
		$minChars = 0;
		if (isset($config['fieldConf']['config']['wizards']['suggest']['default']['minimumCharacters'])) {
			$minChars = (int)$config['fieldConf']['config']['wizards']['suggest']['default']['minimumCharacters'];
		}
		// Overwrite it with minimumCharacters from TSConfig (TCEFORM) if given
		if (isset($config['fieldTSConfig']['suggest.']['default.']['minimumCharacters'])) {
			$minChars = (int)$config['fieldTSConfig']['suggest.']['default.']['minimumCharacters'];
		}
		$minChars = $minChars > 0 ? $minChars : 2;

		// fetch the TCA field type to hand it over to the JS class
		$type = '';
		if (isset($config['fieldConf']['config']['type'])) {
			$type = $config['fieldConf']['config']['type'];
		}

		$jsRow = '';
		if ($isFlexFormField && !MathUtility::canBeInterpretedAsInteger($row['uid'])) {
			// Ff we have a new record, we hand that row over to JS.
			// This way we can properly retrieve the configuration of our wizard
			// if it is shown in a flexform
			$jsRow = serialize($row);
		}

		// Replace "-" with ucwords for the JS object name
		$jsObj = str_replace(' ', '', ucwords(str_replace(array('-', '.'), ' ', GeneralUtility::strtolower($suggestId))));
		$this->TCEformsObj->additionalJS_post[] = '
			var ' . $jsObj . ' = new TCEForms.Suggest("' . $fieldname . '", "' . $table . '", "' . $field . '", "' . $row['uid'] . '", ' . $row['pid'] . ', ' . $minChars . ', "' . $type . '", ' . GeneralUtility::quoteJSvalue($jsRow) . ');' . LF
				. $jsObj . '.defaultValue = "' . GeneralUtility::slashJS($languageService->sL('LLL:EXT:lang/locallang_core.xlf:labels.findRecord')) . '";' . LF;
		return $selector;
	}

	/**
	 * Search a data structure array recursively -- including within nested
	 * (repeating) elements -- for a particular field config.
	 *
	 * @param array $dataStructure The data structure
	 * @param string $fieldName The field name
	 * @return array
	 */
	protected function getNestedDsFieldConfig(array $dataStructure, $fieldName) {
		$fieldConfig = array();
		$elements = $dataStructure['ROOT']['el'] ? $dataStructure['ROOT']['el'] : $dataStructure['el'];
		if (is_array($elements)) {
			foreach ($elements as $k => $ds) {
				if ($k === $fieldName) {
					$fieldConfig = $ds['TCEforms']['config'];
					break;
				} elseif (isset($ds['el'][$fieldName]['TCEforms']['config'])) {
					$fieldConfig = $ds['el'][$fieldName]['TCEforms']['config'];
					break;
				} else {
					$fieldConfig = $this->getNestedDsFieldConfig($ds, $fieldName);
				}
			}
		}
		return $fieldConfig;
	}

	/**
	 * Ajax handler for the "suggest" feature in TCEforms.
	 *
	 * @param array $params The parameters from the AJAX call
	 * @param \TYPO3\CMS\Core\Http\AjaxRequestHandler $ajaxObj The AJAX object representing the AJAX call
	 * @return void
	 */
	public function processAjaxRequest($params, &$ajaxObj) {
		// Get parameters from $_GET/$_POST
		$search = GeneralUtility::_GP('value');
		$table = GeneralUtility::_GP('table');
		$field = GeneralUtility::_GP('field');
		$uid = GeneralUtility::_GP('uid');
		$pageId = GeneralUtility::_GP('pid');
		$newRecordRow = GeneralUtility::_GP('newRecordRow');
		// If the $uid is numeric, we have an already existing element, so get the
		// TSconfig of the page itself or the element container (for non-page elements)
		// otherwise it's a new element, so use given id of parent page (i.e., don't modify it here)
		$row = NULL;
		if (is_numeric($uid)) {
			$row = BackendUtility::getRecord($table, $uid);
			if ($table == 'pages') {
				$pageId = $uid;
			} else {
				$pageId = $row['pid'];
			}
		} else {
			$row = unserialize($newRecordRow);
		}
		$TSconfig = BackendUtility::getPagesTSconfig($pageId);
		$queryTables = array();
		$foreign_table_where = '';
		$fieldConfig = $GLOBALS['TCA'][$table]['columns'][$field]['config'];
		$parts = explode('|', $field);
		if ($GLOBALS['TCA'][$table]['columns'][$parts[0]]['config']['type'] === 'flex') {
			$flexfieldTCAConfig = $GLOBALS['TCA'][$table]['columns'][$parts[0]]['config'];
			$flexformDSArray = BackendUtility::getFlexFormDS($flexfieldTCAConfig, $row, $table, $parts[0]);
			$flexformDSArray = GeneralUtility::resolveAllSheetsInDS($flexformDSArray);
			$flexformElement = $parts[count($parts) - 2];
			$continue = TRUE;
			foreach ($flexformDSArray as $sheet) {
				foreach ($sheet as $dataStructure) {
					$fieldConfig = $this->getNestedDsFieldConfig($dataStructure, $flexformElement);
					if (count($fieldConfig) > 0) {
						$continue = FALSE;
						break;
					}
				}
				if (!$continue) {
					break;
				}
			}
			$field = str_replace('|', '][', $field);
		}
		$wizardConfig = $fieldConfig['wizards']['suggest'];
		if (isset($fieldConfig['allowed'])) {
			if ($fieldConfig['allowed'] === '*') {
				foreach ($GLOBALS['TCA'] as $tableName => $tableConfig) {
					// @todo Refactor function to BackendUtility
					if (empty($tableConfig['ctrl']['hideTable'])
						&& ($GLOBALS['BE_USER']->isAdmin()
							|| (empty($tableConfig['ctrl']['adminOnly'])
								&& (empty($tableConfig['ctrl']['rootLevel'])
									|| !empty($tableConfig['ctrl']['security']['ignoreRootLevelRestriction']))))
					) {
						$queryTables[] = $tableName;
					}
				}
				unset($tableName, $tableConfig);
			} else {
				$queryTables = GeneralUtility::trimExplode(',', $fieldConfig['allowed']);
			}
		} elseif (isset($fieldConfig['foreign_table'])) {
			$queryTables = array($fieldConfig['foreign_table']);
			$foreign_table_where = $fieldConfig['foreign_table_where'];
			// strip ORDER BY clause
			$foreign_table_where = trim(preg_replace('/ORDER[[:space:]]+BY.*/i', '', $foreign_table_where));
		}
		$resultRows = array();
		// fetch the records for each query table. A query table is a table from which records are allowed to
		// be added to the TCEForm selector, originally fetched from the "allowed" config option in the TCA
		foreach ($queryTables as $queryTable) {
			// if the table does not exist, skip it
			if (!is_array($GLOBALS['TCA'][$queryTable]) || !count($GLOBALS['TCA'][$queryTable])) {
				continue;
			}
			$config = (array)$wizardConfig['default'];
			if (is_array($wizardConfig[$queryTable])) {
				\TYPO3\CMS\Core\Utility\ArrayUtility::mergeRecursiveWithOverrule($config, $wizardConfig[$queryTable]);
			}
			// merge the configurations of different "levels" to get the working configuration for this table and
			// field (i.e., go from the most general to the most special configuration)
			if (is_array($TSconfig['TCEFORM.']['suggest.']['default.'])) {
				\TYPO3\CMS\Core\Utility\ArrayUtility::mergeRecursiveWithOverrule($config, $TSconfig['TCEFORM.']['suggest.']['default.']);
			}
			if (is_array($TSconfig['TCEFORM.']['suggest.'][$queryTable . '.'])) {
				\TYPO3\CMS\Core\Utility\ArrayUtility::mergeRecursiveWithOverrule($config, $TSconfig['TCEFORM.']['suggest.'][$queryTable . '.']);
			}
			// use $table instead of $queryTable here because we overlay a config
			// for the input-field here, not for the queried table
			if (is_array($TSconfig['TCEFORM.'][$table . '.'][$field . '.']['suggest.']['default.'])) {
				\TYPO3\CMS\Core\Utility\ArrayUtility::mergeRecursiveWithOverrule($config, $TSconfig['TCEFORM.'][$table . '.'][$field . '.']['suggest.']['default.']);
			}
			if (is_array($TSconfig['TCEFORM.'][$table . '.'][$field . '.']['suggest.'][$queryTable . '.'])) {
				\TYPO3\CMS\Core\Utility\ArrayUtility::mergeRecursiveWithOverrule($config, $TSconfig['TCEFORM.'][$table . '.'][$field . '.']['suggest.'][$queryTable . '.']);
			}
			//process addWhere
			if (!isset($config['addWhere']) && $foreign_table_where) {
				$config['addWhere'] = $foreign_table_where;
			}
			if (isset($config['addWhere'])) {
				$replacement = array(
					'###THIS_UID###' => (int)$uid,
					'###CURRENT_PID###' => (int)$pageId
				);
				if (isset($TSconfig['TCEFORM.'][$table . '.'][$field . '.'])) {
					$fieldTSconfig = $TSconfig['TCEFORM.'][$table . '.'][$field . '.'];
					if (isset($fieldTSconfig['PAGE_TSCONFIG_ID'])) {
						$replacement['###PAGE_TSCONFIG_ID###'] = (int)$fieldTSconfig['PAGE_TSCONFIG_ID'];
					}
					if (isset($fieldTSconfig['PAGE_TSCONFIG_IDLIST'])) {
						$replacement['###PAGE_TSCONFIG_IDLIST###'] = $GLOBALS['TYPO3_DB']->cleanIntList($fieldTSconfig['PAGE_TSCONFIG_IDLIST']);
					}
					if (isset($fieldTSconfig['PAGE_TSCONFIG_STR'])) {
						$replacement['###PAGE_TSCONFIG_STR###'] = $GLOBALS['TYPO3_DB']->quoteStr($fieldTSconfig['PAGE_TSCONFIG_STR'], $fieldConfig['foreign_table']);
					}
				}
				$config['addWhere'] = strtr(' ' . $config['addWhere'], $replacement);
			}
			// instantiate the class that should fetch the records for this $queryTable
			$receiverClassName = $config['receiverClass'];
			if (!class_exists($receiverClassName)) {
				$receiverClassName = \TYPO3\CMS\Backend\Form\Element\SuggestDefaultReceiver::class;
			}
			$receiverObj = GeneralUtility::makeInstance($receiverClassName, $queryTable, $config);
			$params = array('value' => $search);
			$rows = $receiverObj->queryTable($params);
			if (empty($rows)) {
				continue;
			}
			$resultRows = $rows + $resultRows;
			unset($rows);
		}
		$listItems = array();
		if (count($resultRows) > 0) {
			// traverse all found records and sort them
			$rowsSort = array();
			foreach ($resultRows as $key => $row) {
				$rowsSort[$key] = $row['text'];
			}
			asort($rowsSort);
			$rowsSort = array_keys($rowsSort);
			// Limit the number of items in the result list
			$maxItems = $config['maxItemsInResultList'] ?: 10;
			$maxItems = min(count($resultRows), $maxItems);
			// put together the selector entry
			for ($i = 0; $i < $maxItems; $i++) {
				$row = $resultRows[$rowsSort[$i]];
				$rowId = $row['table'] . '-' . $row['uid'] . '-' . $table . '-' . $uid . '-' . $field;
				$listItems[] = '<li' . ($row['class'] != '' ? ' class="' . $row['class'] . '"' : '') . ' id="' . $rowId . '"' . ($row['style'] != '' ? ' style="' . $row['style'] . '"' : '') . '>' . $row['sprite'] . $row['text'] . '</li>';
			}
		}
		if (count($listItems) > 0) {
			$list = implode('', $listItems);
		} else {
			$list = '<li class="suggest-noresults"><i>' . $this->getLanguageService()->sL('LLL:EXT:lang/locallang_core.xlf:labels.noRecordFound') . '</i></li>';
		}
		$list = '<ul class="' . $this->cssClass . '-resultlist">' . $list . '</ul>';
		$ajaxObj->addContent(0, $list);
	}

	/**
	 * @return LanguageService
	 */
	protected function getLanguageService() {
		return $GLOBALS['LANG'];
	}

}
