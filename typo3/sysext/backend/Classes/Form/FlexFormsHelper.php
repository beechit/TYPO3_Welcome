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

use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Contains FlexForm manipulation methods as part of the TCEforms
 *
 * @author Kai Vogel <kai.vogel(at)speedprogs.de>
 */
class FlexFormsHelper extends \TYPO3\CMS\Backend\Form\FormEngine {

	/**
	 * Options that will be removed from config after creating items for a select to prevent double parsing
	 *
	 * @var array
	 */
	protected $removeSelectConfig = array(
		'itemsProcFunc',
		'foreign_table',
		'foreign_table_where',
		'foreign_table_prefix',
		'foreign_table_loadIcons',
		'neg_foreign_table',
		'neg_foreign_table_where',
		'neg_foreign_table_prefix',
		'neg_foreign_table_loadIcons',
		'neg_foreign_table_imposeValueField',
		'fileFolder',
		'fileFolder_extList',
		'fileFolder_recursions',
		'MM',
		'MM_opposite_field',
		'MM_match_fields',
		'MM_insert_fields',
		'MM_table_where',
		'MM_hasUidField',
		'special'
	);

	/**
	 * Modify the Data Structure of a FlexForm field via TSconfig and group access lists
	 *
	 * @param array $dataStructure The data structure of the FlexForm field
	 * @param string $table The table name of the record
	 * @param string $tableField The field name
	 * @param array $tableRow The record data
	 * @param array $tableConf Additional configuration options
	 * @return array Modified FlexForm DS
	 * @see \TYPO3\CMS\Backend\Form\FormEngine::getSingleField_typeFlex()
	 */
	public function modifyFlexFormDS(array $dataStructure, $table, $tableField, array $tableRow, array $tableConf) {
		$singleSheet = !isset($dataStructure['sheets']) || !is_array($dataStructure['sheets']);
		$metaConf = !empty($dataStructure['meta']) ? $dataStructure['meta'] : array();
		$sheetConf = array();
		// Get extension identifier (uses second pointer field if it's value is not empty,
		// "list" or "*", else it must be a plugin and first one will be used)
		$pointerFields = !empty($tableConf['config']['ds_pointerField']) ? $tableConf['config']['ds_pointerField'] : 'list_type,CType';
		$pointerFields = GeneralUtility::trimExplode(',', $pointerFields);
		$flexformIdentifier = !empty($tableRow[$pointerFields[0]]) ? $tableRow[$pointerFields[0]] : '';
		if (!empty($tableRow[$pointerFields[1]]) && $tableRow[$pointerFields[1]] != 'list' && $tableRow[$pointerFields[1]] != '*') {
			$flexformIdentifier = $tableRow[$pointerFields[1]];
		}
		if (empty($flexformIdentifier)) {
			return $dataStructure;
		}
		// Get field configuration from page TSConfig
		$TSconfig = $this->setTSconfig($table, $tableRow);
		if (!empty($TSconfig[$tableField][($flexformIdentifier . '.')])) {
			$sheetConf = GeneralUtility::removeDotsFromTS($TSconfig[$tableField][$flexformIdentifier . '.']);
		}
		// Get non-exclude-fields from group access lists
		$nonExcludeFields = $this->getFlexFormNonExcludeFields($table, $tableField, $flexformIdentifier);
		// Load complete DS, including external file references
		$dataStructure = GeneralUtility::resolveAllSheetsInDS($dataStructure);
		// Modify language handling in meta configuration
		if (isset($sheetConf['langDisable'])) {
			$metaConf['langDisable'] = $sheetConf['langDisable'];
		}
		if (isset($sheetConf['langChildren'])) {
			$metaConf['langChildren'] = $sheetConf['langChildren'];
		}
		// Modify flexform sheets
		foreach ($dataStructure['sheets'] as $sheetName => $sheet) {
			if (empty($sheet['ROOT']['el']) || !is_array($sheet['ROOT']['el'])) {
				continue;
			}
			// Remove whole sheet (tab) if disabled
			if (!empty($sheetConf[$sheetName]['disabled'])) {
				unset($dataStructure['sheets'][$sheetName]);
				continue;
			}
			// Rename sheet (tab)
			if (!empty($sheetConf[$sheetName]['sheetTitle'])) {
				$dataStructure['sheets'][$sheetName]['ROOT']['TCEforms']['sheetTitle'] = $sheetConf[$sheetName]['sheetTitle'];
			}
			// Set sheet description (tab)
			if (!empty($sheetConf[$sheetName]['sheetDescription'])) {
				$dataStructure['sheets'][$sheetName]['ROOT']['TCEforms']['sheetDescription'] = $sheetConf[$sheetName]['sheetDescription'];
			}
			// Set sheet short description (tab)
			if (!empty($sheetConf[$sheetName]['sheetShortDescr'])) {
				$dataStructure['sheets'][$sheetName]['ROOT']['TCEforms']['sheetShortDescr'] = $sheetConf[$sheetName]['sheetShortDescr'];
			}
			// Modify all configured fields in sheet (tab)
			$dataStructure['sheets'][$sheetName]['ROOT']['el'] = $this->modifySingleFlexFormSheet($sheet['ROOT']['el'], $table, $tableField, $tableRow, !empty($sheetConf[$sheetName]) ? $sheetConf[$sheetName] : array(), !empty($nonExcludeFields[$sheetName]) ? $nonExcludeFields[$sheetName] : array());
			// Remove empty sheet (tab)
			if (empty($dataStructure['sheets'][$sheetName]['ROOT']['el'])) {
				unset($dataStructure['sheets'][$sheetName]);
			}
		}
		// Recover single flexform structure
		if ($singleSheet && isset($dataStructure['sheets']['sDEF'])) {
			$dataStructure = $dataStructure['sheets']['sDEF'];
		}
		// Recover meta configuration
		if (!empty($metaConf)) {
			$dataStructure['meta'] = $metaConf;
		}
		return $dataStructure;
	}

	/**
	 * Modify a single FlexForm sheet according to given configuration
	 *
	 * @param array $sheet Flexform sheet to manipulate
	 * @param string $table The table name
	 * @param string $tableField The field name
	 * @param array $tableRow The record data
	 * @param array $sheetConf Sheet configuration
	 * @param array $nonExcludeFields Non-exclude-fields for this sheet
	 * @return array Modified sheet
	 * @see \TYPO3\CMS\Backend\Form\FlexFormsHelper::modifyFlexFormDS()
	 */
	public function modifySingleFlexFormSheet(array $sheet, $table, $tableField, array $tableRow, array $sheetConf, array $nonExcludeFields) {
		if (empty($sheet) || empty($table) || empty($tableField) || empty($tableRow)) {
			return $sheet;
		}
		// Modify fields
		foreach ($sheet as $fieldName => $field) {
			// Remove excluded fields
			if (!$GLOBALS['BE_USER']->isAdmin() && !empty($field['TCEforms']['exclude']) && empty($nonExcludeFields[$fieldName])) {
				unset($sheet[$fieldName]);
				continue;
			}
			// Stop here if no TSConfig was found for this field
			if (empty($sheetConf[$fieldName]) || !is_array($sheetConf[$fieldName])) {
				continue;
			}
			// Remove disabled fields
			if (!empty($sheetConf[$fieldName]['disabled'])) {
				unset($sheet[$fieldName]);
				continue;
			}
			$fieldConf = $sheetConf[$fieldName];
			$removeItems = !empty($fieldConf['removeItems']) ? GeneralUtility::trimExplode(',', $fieldConf['removeItems'], TRUE) : array();
			$keepItems = !empty($fieldConf['keepItems']) ? GeneralUtility::trimExplode(',', $fieldConf['keepItems'], TRUE) : array();
			$renameItems = !empty($fieldConf['altLabels']) && is_array($fieldConf['altLabels']) ? $fieldConf['altLabels'] : array();
			$changeIcons = !empty($fieldConf['altIcons']) && is_array($fieldConf['altIcons']) ? $fieldConf['altIcons'] : array();
			$addItems = !empty($fieldConf['addItems']) && is_array($fieldConf['addItems']) ? $fieldConf['addItems'] : array();
			unset($fieldConf['removeItems']);
			unset($fieldConf['keepItems']);
			unset($fieldConf['altLabels']);
			unset($fieldConf['altIcons']);
			unset($fieldConf['addItems']);
			// Manipulate field
			if (!empty($field['TCEforms']) && is_array($field['TCEforms'])) {
				$sheet[$fieldName]['TCEforms'] = $field['TCEforms'];
				ArrayUtility::mergeRecursiveWithOverrule($sheet[$fieldName]['TCEforms'], $fieldConf);
			}
			// Manipulate only select fields, other field types will stop here
			if (empty($field['TCEforms']['config']['type']) || $field['TCEforms']['config']['type'] != 'select' || $field['TCEforms']['config']['renderMode'] === 'tree') {
				continue;
			}
			// Getting the selector box items from system
			$selItems = $this->addSelectOptionsToItemArray($this->initItemArray($field['TCEforms']), $field['TCEforms'], $this->setTSconfig($table, $tableRow), $tableField);

			// Possibly filter some items
			$selItems = ArrayUtility::keepItemsInArray(
				$selItems,
				$keepItems,
				function ($value) {
					return $value[1];
				}
			);

			// Possibly add some items
			$selItems = $this->addItems($selItems, $addItems);
			// Process items by a user function
			if (!empty($field['TCEforms']['config']['itemsProcFunc'])) {
				$selItems = $this->procItems($selItems, $fieldConf['config'], $field['TCEforms']['config'], $table, $tableRow, $tableField);
			}
			// Remove special configuration options after creating items to prevent double parsing
			foreach ($this->removeSelectConfig as $option) {
				unset($sheet[$fieldName]['TCEforms']['config'][$option]);
			}
			// Rename and remove items or change item icon in select
			if ((!empty($removeItems) || !empty($renameItems) || !empty($changeIcons)) && !empty($selItems) && is_array($selItems)) {
				foreach ($selItems as $itemKey => $itemConf) {
					// Option has no key, no manipulation possible
					if (!isset($itemConf[1])) {
						continue;
					}
					// Remove
					foreach ($removeItems as $removeKey => $removeValue) {
						if (strcasecmp($removeValue, $itemConf[1]) == 0) {
							unset($selItems[$itemKey]);
							unset($removeItems[$removeKey]);
						}
					}
					// Rename
					foreach ($renameItems as $renameKey => $renameValue) {
						if (strcasecmp($renameKey, $itemConf[1]) == 0) {
							$selItems[$itemKey][0] = htmlspecialchars($renameValue);
							unset($renameItems[$renameKey]);
						}
					}
					// Change icon
					foreach ($changeIcons as $iconKey => $iconValue) {
						if (strcasecmp($iconKey, $itemConf[1]) == 0) {
							$selItems[$itemKey][2] = $iconValue;
							unset($changeIcons[$iconKey]);
						}
					}
				}
			}
			$sheet[$fieldName]['TCEforms']['config']['items'] = $selItems;
		}
		return $sheet;
	}

	/**
	 * Get FlexForm non-exclude-fields for current backend user
	 *
	 * @param string $table The table name
	 * @param string $tableField The field name
	 * @param string $extIdent The extension identifier
	 * @return array All non_exclude_fields from FlexForms
	 * @see \TYPO3\CMS\Backend\Form\FormEngine::getSingleField_typeFlex()
	 */
	protected function getFlexFormNonExcludeFields($table, $tableField, $extIdent) {
		if (empty($GLOBALS['BE_USER']->groupData['non_exclude_fields']) || empty($table) || empty($tableField) || empty($extIdent)) {
			return array();
		}
		$accessListFields = GeneralUtility::trimExplode(',', $GLOBALS['BE_USER']->groupData['non_exclude_fields']);
		$identPrefix = $table . ':' . $tableField . ';' . $extIdent . ';';
		$nonExcludeFields = array();
		// Collect only FlexForm fields
		foreach ($accessListFields as $field) {
			if (strpos($field, $identPrefix) !== FALSE) {
				list(, , $sheetName, $fieldName) = explode(';', $field);
				$nonExcludeFields[$sheetName][$fieldName] = TRUE;
			}
		}
		return $nonExcludeFields;
	}

	/**
	 * Compare two arrays by their first value
	 *
	 * @param array $array1 First array
	 * @param array $array2 Second array
	 * @return int Negative int if first array is lower, zero if both are identical, and positive if second is higher
	 */
	static public function compareArraysByFirstValue(array $array1, array $array2) {
		$array1 = reset($array1);
		$array2 = reset($array2);
		if (is_string($array1) && is_string($array2)) {
			return strcasecmp($array1, $array2);
		}
		return 0;
	}

}
