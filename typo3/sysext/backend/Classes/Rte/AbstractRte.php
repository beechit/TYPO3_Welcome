<?php
namespace TYPO3\CMS\Backend\Rte;

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

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * RTE base class: Delivers browser-detection, TCEforms binding and transformation routines
 * for the "rte" extension, registering it with the RTE API
 * See "rte" extension for usage.
 *
 * @author Kasper Skårhøj <kasperYYYY@typo3.com>
 */
class AbstractRte {

	/**
	 * Error messages regarding non-availability is collected here.
	 *
	 * @var array
	 */
	public $errorLog = array();

	/**
	 * Set this to the extension key of the RTE so it can identify itself.
	 *
	 * @var string
	 */
	public $ID = '';

	/***********************************
	 *
	 * Main API functions;
	 * When you create alternative RTEs, simply override these functions in your parent class.
	 * See the "rte" or "rtehtmlarea" extension as an example!
	 *
	 **********************************/
	/**
	 * Returns TRUE if the RTE is available. Here you check if the browser requirements are met.
	 * If there are reasons why the RTE cannot be displayed you simply enter them as text in ->errorLog
	 *
	 * @return bool TRUE if this RTE object offers an RTE in the current browser environment
	 */
	public function isAvailable() {
		return TRUE;
	}

	/**
	 * Draws the RTE as a form field or whatever is needed (inserts JavaApplet, creates iframe, renders ....)
	 * Default is to output the transformed content in a plain textarea field. This mode is great for debugging transformations!
	 *
	 * @param object $pObj parent object
	 * @param string $table The table name
	 * @param string $field The field name
	 * @param array $row The current row from which field is being rendered
	 * @param array $PA Array of standard content for rendering form fields from TCEforms. See TCEforms for details on this. Includes for instance the value and the form field name, java script actions and more.
	 * @param array $specConf "special" configuration - what is found at position 4 in the types configuration of a field from record, parsed into an array.
	 * @param array $thisConfig Configuration for RTEs; A mix between TSconfig and otherwise. Contains configuration for display, which buttons are enabled, additional transformation information etc.
	 * @param string $RTEtypeVal Record "type" field value.
	 * @param string $RTErelPath Relative path for images/links in RTE; this is used when the RTE edits content from static files where the path of such media has to be transformed forth and back!
	 * @param int $thePidValue PID value of record (true parent page id)
	 * @return string HTML code for RTE!
	 */
	public function drawRTE(&$pObj, $table, $field, $row, $PA, $specConf, $thisConfig, $RTEtypeVal, $RTErelPath, $thePidValue) {
		// Transform value:
		$value = $this->transformContent('rte', $PA['itemFormElValue'], $table, $field, $row, $specConf, $thisConfig, $RTErelPath, $thePidValue);
		// Create item:
		$item = '
			' . $this->triggerField($PA['itemFormElName']) . '
			<textarea name="' . htmlspecialchars($PA['itemFormElName']) . '"' . $pObj->formWidthText('48', 'off') . ' rows="20" wrap="off" style="background-color: #99eebb;">' . GeneralUtility::formatForTextarea($value) . '</textarea>';
		// Return form item:
		return $item;
	}

	/**
	 * Performs transformation of content to/from RTE. The keyword $dirRTE determines the direction.
	 * This function is called in two situations:
	 * a) Right before content from database is sent to the RTE (see ->drawRTE()) it might need transformation
	 * b) When content is sent from the RTE and into the database it might need transformation back again (going on in TCEmain class; You can't affect that.)
	 *
	 * @param string $dirRTE Keyword: "rte" means direction from db to rte, "db" means direction from Rte to DB
	 * @param string $value Value to transform.
	 * @param string $table The table name
	 * @param string $field The field name
	 * @param array $row The current row from which field is being rendered
	 * @param array $specConf "special" configuration - what is found at position 4 in the types configuration of a field from record, parsed into an array.
	 * @param array $thisConfig Configuration for RTEs; A mix between TSconfig and otherwise. Contains configuration for display, which buttons are enabled, additional transformation information etc.
	 * @param string $RTErelPath Relative path for images/links in RTE; this is used when the RTE edits content from static files where the path of such media has to be transformed forth and back!
	 * @param int $pid PID value of record (true parent page id)
	 * @return string Transformed content
	 */
	public function transformContent($dirRTE, $value, $table, $field, $row, $specConf, $thisConfig, $RTErelPath, $pid) {
		if ($specConf['rte_transform']) {
			$p = \TYPO3\CMS\Backend\Utility\BackendUtility::getSpecConfParametersFromArray($specConf['rte_transform']['parameters']);
			// There must be a mode set for transformation
			if ($p['mode']) {
				// Initialize transformation:
				$parseHTML = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Html\RteHtmlParser::class);
				$parseHTML->init($table . ':' . $field, $pid);
				$parseHTML->setRelPath($RTErelPath);
				// Perform transformation:
				$value = $parseHTML->RTE_transform($value, $specConf, $dirRTE, $thisConfig);
			}
		}
		return $value;
	}

	/***********************************
	 *
	 * Helper functions
	 *
	 **********************************/
	/**
	 * Trigger field - this field tells the TCEmain that processing should be done on this value!
	 *
	 * @param string $fieldName Field name of the RTE field.
	 * @return string <input> field of type "hidden" with a flag telling the TCEmain that this fields content should be transformed back to database state.
	 */
	public function triggerField($fieldName) {
		$triggerFieldName = preg_replace('/\\[([^]]+)\\]$/', '[_TRANSFORM_\\1]', $fieldName);
		return '<input type="hidden" name="' . htmlspecialchars($triggerFieldName) . '" value="RTE" />';
	}

}
