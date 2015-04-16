<?php
namespace TYPO3\CMS\Cshmanual\Controller;

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

/**
 * Script Class for rendering the Context Sensitive Help documents,
 * either the single display in the small pop-up window or the full-table view in the larger window.
 *
 * @author Kasper Skårhøj <kasperYYYY@typo3.com>
 */
class HelpModuleController {

	/**
	 * @var string
	 */
	public $allowedHTML = '<strong><em><b><i>';

	/**
	 * For these vars, see init()
	 * If set access to fields and tables is checked. Should be done for TRUE database tables.
	 *
	 * @var bool
	 */
	public $limitAccess;

	/**
	 * The "table" key
	 *
	 * @var string
	 */
	public $table;

	/**
	 * The "field" key
	 *
	 * @var string
	 */
	public $field;

	/**
	 * Key used to point to the right CSH resource
	 * In simple cases, is equal to $table
	 *
	 * @var string
	 */
	protected $mainKey;

	/**
	 * Internal, static: GPvar
	 * Table/Field id
	 *
	 * @var string
	 */
	public $tfID;

	/**
	 * Back (previous tfID)
	 *
	 * @var string
	 */
	public $back;

	/**
	 * If set, then in TOC mode the FULL manual will be printed as well!
	 *
	 * @var bool
	 */
	public $renderALL;

	/**
	 * Content accumulation
	 *
	 * @var string
	 */
	public $content;

	/**
	 * URL to help module
	 *
	 * @var string
	 */
	protected $moduleUrl;

	/**
	 * Initialize the class for various input etc.
	 *
	 * @return void
	 */
	public function init() {
		$this->moduleUrl = BackendUtility::getModuleUrl('help_cshmanual');
		// Setting GPvars:
		$this->tfID = GeneralUtility::_GP('tfID');
		// Sanitizes the tfID using whitelisting.
		if (!preg_match('/^[a-zA-Z0-9_\\-\\.\\*]*$/', $this->tfID)) {
			$this->tfID = '';
		}
		$this->back = GeneralUtility::_GP('back');
		$this->renderALL = GeneralUtility::_GP('renderALL');
		// Set internal table/field to the parts of "tfID" incoming var.
		$identifierParts = explode('.', $this->tfID);
		// The table is the first item
		$this->table = array_shift($identifierParts);
		$this->mainKey = $this->table;
		// The field is the second one
		$this->field = array_shift($identifierParts);
		// There may be extra parts for FlexForms
		if (count($identifierParts) > 0) {
			// There's at least one extra part
			$extraIdentifierInformation = array();
			$extraIdentifierInformation[] = array_shift($identifierParts);
			// If the ds_pointerField contains a comma, it means the choice of FlexForm DS
			// is determined by 2 parameters. In this case we have an extra identifier part
			if (strpos($GLOBALS['TCA'][$this->table]['columns'][$this->field]['config']['ds_pointerField'], ',') !== FALSE) {
				$extraIdentifierInformation[] = array_shift($identifierParts);
			}
			// The remaining parts make up the FlexForm field name itself
			// (reassembled with dots)
			$flexFormField = implode('.', $identifierParts);
			// Assemble a different main key and switch field to use FlexForm field name
			$this->mainKey .= '.' . $this->field;
			foreach ($extraIdentifierInformation as $extraKey) {
				$this->mainKey .= '.' . $extraKey;
			}
			$this->field = $flexFormField;
		}
		// limitAccess is checked if the $this->table really IS a table (and if the user is NOT a translator who should see all!)
		$showAllToUser = BackendUtility::isModuleSetInTBE_MODULES('txllxmltranslateM1') && $GLOBALS['BE_USER']->check('modules', 'txllxmltranslateM1');
		$this->limitAccess = isset($GLOBALS['TCA'][$this->table]) ? !$showAllToUser : FALSE;
		$GLOBALS['LANG']->includeLLFile('EXT:lang/locallang_view_help.xlf', 1);
	}

	/**
	 * Main function, rendering the display
	 *
	 * @return void
	 */
	public function main() {
		if ($this->field == '*') {
			// If ALL fields is supposed to be shown:
			$this->content .= $this->render_Table($this->mainKey);
		} elseif ($this->tfID) {
			// ... otherwise show only single field:
			$this->content .= $this->render_Single($this->mainKey, $this->field);
		} else {
			// Render Table Of Contents if nothing else:
			$this->content .= $this->render_TOC();
		}

		$this->doc = GeneralUtility::makeInstance(\TYPO3\CMS\Backend\Template\DocumentTemplate::class);
		$this->doc->backPath = $GLOBALS['BACK_PATH'];
		$this->doc->setModuleTemplate('EXT:cshmanual/Resources/Private/Templates/cshmanual.html');

		$markers = array('CONTENT' => $this->content);

		$this->content = $this->doc->moduleBody(array(), array(), $markers);
		$this->content = $this->doc->render($GLOBALS['LANG']->getLL('title'), $this->content);
	}

	/**
	 * Outputting the accumulated content to screen
	 *
	 * @return void
	 */
	public function printContent() {
		echo $this->content;
	}

	/************************************
	 * Rendering main modes
	 ************************************/

	/**
	 * Creates Table Of Contents and possibly "Full Manual" mode if selected.
	 *
	 * @return string HTML content
	 */
	public function render_TOC() {
		// Initialize:
		$CSHkeys = array_flip(array_keys($GLOBALS['TCA_DESCR']));
		$TCAkeys = array_keys($GLOBALS['TCA']);
		$outputSections = array();
		$tocArray = array();
		// TYPO3 Core Features:
		$GLOBALS['LANG']->loadSingleTableDescription('xMOD_csh_corebe');
		$this->render_TOC_el('xMOD_csh_corebe', 'core', $outputSections, $tocArray, $CSHkeys);
		// Backend Modules:
		$loadModules = GeneralUtility::makeInstance(\TYPO3\CMS\Backend\Module\ModuleLoader::class);
		$loadModules->load($GLOBALS['TBE_MODULES']);
		foreach ($loadModules->modules as $mainMod => $info) {
			$cshKey = '_MOD_' . $mainMod;
			if ($CSHkeys[$cshKey]) {
				$GLOBALS['LANG']->loadSingleTableDescription($cshKey);
				$this->render_TOC_el($cshKey, 'modules', $outputSections, $tocArray, $CSHkeys);
			}
			if (is_array($info['sub'])) {
				foreach ($info['sub'] as $subMod => $subInfo) {
					$cshKey = '_MOD_' . $mainMod . '_' . $subMod;
					if ($CSHkeys[$cshKey]) {
						$GLOBALS['LANG']->loadSingleTableDescription($cshKey);
						$this->render_TOC_el($cshKey, 'modules', $outputSections, $tocArray, $CSHkeys);
					}
				}
			}
		}
		// Database Tables:
		foreach ($TCAkeys as $table) {
			// Load descriptions for table $table
			$GLOBALS['LANG']->loadSingleTableDescription($table);
			if (is_array($GLOBALS['TCA_DESCR'][$table]['columns']) && $GLOBALS['BE_USER']->check('tables_select', $table)) {
				$this->render_TOC_el($table, 'tables', $outputSections, $tocArray, $CSHkeys);
			}
		}
		// Extensions
		foreach ($CSHkeys as $cshKey => $value) {
			if (GeneralUtility::isFirstPartOfStr($cshKey, 'xEXT_') && !isset($GLOBALS['TCA'][$cshKey])) {
				$GLOBALS['LANG']->loadSingleTableDescription($cshKey);
				$this->render_TOC_el($cshKey, 'extensions', $outputSections, $tocArray, $CSHkeys);
			}
		}
		// Other:
		foreach ($CSHkeys as $cshKey => $value) {
			if (!GeneralUtility::isFirstPartOfStr($cshKey, '_MOD_') && !isset($GLOBALS['TCA'][$cshKey])) {
				$GLOBALS['LANG']->loadSingleTableDescription($cshKey);
				$this->render_TOC_el($cshKey, 'other', $outputSections, $tocArray, $CSHkeys);
			}
		}

		// COMPILE output:
		$output = '';
		$output .= '<h1>' . $GLOBALS['LANG']->getLL('manual_title', TRUE) . '</h1>';
		$output .= '<p class="lead">' . $GLOBALS['LANG']->getLL('description', TRUE) . '</p>';

		$output .= '<h2>' . $GLOBALS['LANG']->getLL('TOC', TRUE) . '</h2>' . $this->render_TOC_makeTocList($tocArray);
		if (!$this->renderALL) {
			$output .= '
				<br/>
				<p class="c-nav"><a href="' . htmlspecialchars($this->moduleUrl) . '&amp;renderALL=1">' . $GLOBALS['LANG']->getLL('full_manual', TRUE) . '</a></p>';
		}
		if ($this->renderALL) {
			$output .= '

				<h2>' . $GLOBALS['LANG']->getLL('full_manual_chapters', TRUE) . '</h2>' . implode('


				<!-- NEW SECTION: -->
				', $outputSections);
		}
		$output .= '<hr /><p class="manual-title">' . BackendUtility::TYPO3_copyRightNotice() . '</p>';
		return $output;
	}

	/**
	 * Creates a TOC list element and renders corresponding HELP content if "renderALL" mode is set.
	 *
	 * @param string $table CSH key / Table name
	 * @param string $tocCat TOC category keyword: "core", "modules", "tables", "other
	 * @param array $outputSections Array for accumulation of rendered HELP Content (in "renderALL" mode). Passed by reference!
	 * @param array $tocArray TOC array; Here TOC index elements are created. Passed by reference!
	 * @param array $CSHkeys CSH keys array. Every item rendered will be unset in this array so finally we can see what CSH keys are not processed yet. Passed by reference!
	 * @return void
	 */
	public function render_TOC_el($table, $tocCat, &$outputSections, &$tocArray, &$CSHkeys) {
		// Render full manual right here!
		if ($this->renderALL) {
			$outputSections[$table] = $this->render_Table($table);
			if ($outputSections[$table]) {
				$outputSections[$table] = '

		<!-- New CSHkey/Table: ' . $table . ' -->
		<p class="c-nav"><a name="ANCHOR_' . $table . '" href="#">' . $GLOBALS['LANG']->getLL('to_top', TRUE) . '</a></p>
		<h2>' . $this->getTableFieldLabel($table) . '</h2>

		' . $outputSections[$table];
				$tocArray[$tocCat][$table] = '<a href="#ANCHOR_' . $table . '">' . $this->getTableFieldLabel($table) . '</a>';
			} else {
				unset($outputSections[$table]);
			}
		} else {
			// Only TOC:
			$tocArray[$tocCat][$table] = '<p><a href="' . htmlspecialchars($this->moduleUrl) . '&amp;tfID=' . rawurlencode(($table . '.*')) . '">' . $this->getTableFieldLabel($table) . '</a></p>';
		}
		// Unset CSH key:
		unset($CSHkeys[$table]);
	}

	/**
	 * Renders the TOC index as a HTML bullet list from TOC array
	 *
	 * @param array $tocArray ToC Array.
	 * @return string HTML bullet list for index.
	 */
	public function render_TOC_makeTocList($tocArray) {
		// The Various manual sections:
		$keys = explode(',', 'core,modules,tables,extensions,other');
		// Create TOC bullet list:
		$output = '';
		foreach ($keys as $tocKey) {
			if (is_array($tocArray[$tocKey])) {
				$output .= '
					<li>' . $GLOBALS['LANG']->getLL(('TOC_' . $tocKey), TRUE) . '
						<ul>
							<li>' . implode('</li>
							<li>', $tocArray[$tocKey]) . '</li>
						</ul>
					</li>';
			}
		}
		// Compile TOC:
		$output = '

			<!-- TOC: -->
			<div class="c-toc">
				<ul>
				' . $output . '
				</ul>
			</div>';
		return $output;
	}

	/**
	 * Render CSH for a full cshKey/table
	 *
	 * @param string $key Full CSH key (may be different from table name)
	 * @param string $table CSH key / table name
	 * @return string HTML output
	 */
	public function render_Table($key, $table = NULL) {
		$output = '';
		// Take default key if not explicitly specified
		if ($table === NULL) {
			$table = $key;
		}
		// Load descriptions for table $table
		$GLOBALS['LANG']->loadSingleTableDescription($key);
		if (is_array($GLOBALS['TCA_DESCR'][$key]['columns']) && (!$this->limitAccess || $GLOBALS['BE_USER']->check('tables_select', $table))) {
			// Initialize variables:
			$parts = array();
			// Reserved for header of table
			$parts[0] = '';
			// Traverse table columns as listed in TCA_DESCR
			foreach ($GLOBALS['TCA_DESCR'][$key]['columns'] as $field => $_) {
				$fieldValue = isset($GLOBALS['TCA'][$key]) && (string)$field !== '' ? $GLOBALS['TCA'][$key]['columns'][$field] : array();
				if (is_array($fieldValue) && (!$this->limitAccess || !$fieldValue['exclude'] || $GLOBALS['BE_USER']->check('non_exclude_fields', $table . ':' . $field))) {
					if (!$field) {
						// Header
						$parts[0] = $this->printItem($key, '', 1);
					} else {
						// Field
						$parts[] = $this->printItem($key, $field, 1);
					}
				}
			}
			if (!$parts[0]) {
				unset($parts[0]);
			}
			$output .= implode('<br />', $parts);
		}
		// TOC link:
		if (!$this->renderALL) {
			$tocLink = '<p class="c-nav"><a href="' . htmlspecialchars($this->moduleUrl) . '">' . $GLOBALS['LANG']->getLL('goToToc', TRUE) . '</a></p>';
			$output = $tocLink . '
				<br/>' . $output . '
				<br />' . $tocLink;
		}
		return $output;
	}

	/**
	 * Renders CSH for a single field.
	 *
	 * @param string $key CSH key / table name
	 * @param string $field Sub key / field name
	 * @return string HTML output
	 */
	public function render_Single($key, $field) {
		$output = '';
		// Load the description field
		$GLOBALS['LANG']->loadSingleTableDescription($key);
		// Render single item
		$output .= $this->printItem($key, $field);
		// Link to Full table description and TOC:
		$getLLKey = $this->limitAccess ? 'fullDescription' : 'fullDescription_module';
		$output .= '<br />
			<p class="c-nav"><a href="' . htmlspecialchars($this->moduleUrl) . '&amp;tfID=' . rawurlencode(($key . '.*')) . '">' . $GLOBALS['LANG']->getLL($getLLKey, TRUE) . '</a></p>
			<p class="c-nav"><a href="' . htmlspecialchars($this->moduleUrl) . '">' . $GLOBALS['LANG']->getLL('goToToc', TRUE) . '</a></p>';
		return $output;
	}

	/************************************
	 * Rendering CSH items
	 ************************************/

	/**
	 * Make seeAlso links from $value
	 *
	 * @param string $value See-also input codes
	 * @param string $anchorTable If $anchorTable is set to a tablename, then references to this table will be made as anchors, not URLs.
	 * @return string See-also links HTML
	 */
	public function make_seeAlso($value, $anchorTable = '') {
		// Split references by comma or linebreak
		$items = preg_split('/[,' . LF . ']/', $value);
		$lines = array();
		foreach ($items as $val) {
			$val = trim($val);
			if ($val) {
				$iP = explode(':', $val);
				$iPUrl = GeneralUtility::trimExplode('|', $val);
				// URL reference:
				if (substr($iPUrl[1], 0, 4) == 'http') {
					$lines[] = '<a href="' . htmlspecialchars($iPUrl[1]) . '" target="_blank"><em>' . htmlspecialchars($iPUrl[0]) . '</em></a>';
				} elseif (substr($iPUrl[1], 0, 5) == 'FILE:') {
					$fileName = GeneralUtility::getFileAbsFileName(substr($iPUrl[1], 5), 1, 1);
					if ($fileName && @is_file($fileName)) {
						$fileName = '../' . \TYPO3\CMS\Core\Utility\PathUtility::stripPathSitePrefix($fileName);
						$lines[] = '<a href="' . htmlspecialchars($fileName) . '" target="_blank"><em>' . htmlspecialchars($iPUrl[0]) . '</em></a>';
					}
				} else {
					// "table" reference
					if (!isset($GLOBALS['TCA'][$iP[0]]) || (!$iP[1] || is_array($GLOBALS['TCA'][$iP[0]]['columns'][$iP[1]])) && (!$this->limitAccess || $GLOBALS['BE_USER']->check('tables_select', $iP[0]) && (!$iP[1] || !$GLOBALS['TCA'][$iP[0]]['columns'][$iP[1]]['exclude'] || $GLOBALS['BE_USER']->check('non_exclude_fields', $iP[0] . ':' . $iP[1])))) {
						// Checking read access:
						if (isset($GLOBALS['TCA_DESCR'][$iP[0]])) {
							// Make see-also link:
							$href = $this->renderALL || $anchorTable && $iP[0] == $anchorTable ? '#' . rawurlencode(implode('.', $iP)) : $this->moduleUrl . '&tfID=' . rawurlencode(implode('.', $iP)) . '&back=' . $this->tfID;
							$label = $this->getTableFieldLabel($iP[0], $iP[1], ' / ');
							$lines[] = '<a href="' . htmlspecialchars($href) . '">' . htmlspecialchars($label) . '</a>';
						}
					}
				}
			}
		}
		return implode('<br />', $lines);
	}

	/**
	 * Will return an image tag with description in italics.
	 *
	 * @param string $images Image file reference (list of)
	 * @param string $descr Description string (divided for each image by line break)
	 * @return string Image HTML codes
	 */
	public function printImage($images, $descr) {
		$code = '';
		// Splitting:
		$imgArray = GeneralUtility::trimExplode(',', $images, TRUE);
		if (count($imgArray)) {
			$descrArray = explode(LF, $descr, count($imgArray));
			foreach ($imgArray as $k => $image) {
				$descr = $descrArray[$k];
				$absImagePath = GeneralUtility::getFileAbsFileName($image, 1, 1);
				if ($absImagePath && @is_file($absImagePath)) {
					$imgFile = \TYPO3\CMS\Core\Utility\PathUtility::stripPathSitePrefix($absImagePath);
					$imgInfo = @getimagesize($absImagePath);
					if (is_array($imgInfo)) {
						$imgFile = '../' . $imgFile;
						$code .= '<br /><img src="' . $imgFile . '" ' . $imgInfo[3] . ' class="c-inlineimg" alt="" /><br />
						';
						$code .= '<p><em>' . htmlspecialchars($descr) . '</em></p>
						';
					} else {
						$code .= '<div style="background-color: red; border: 1px solid black; color: white;">NOT AN IMAGE: ' . $imgFile . '</div>';
					}
				} else {
					$code .= '<div style="background-color: red; border: 1px solid black; color: white;">IMAGE FILE NOT FOUND: ' . $image . '</div>';
				}
			}
		}
		return $code;
	}

	/**
	 * Returns header HTML content
	 *
	 * @param string $str Header text
	 * @param int $type Header type (1, 0)
	 * @return string The HTML for the header.
	 */
	public function headerLine($str, $type = 0) {
		switch ($type) {
			case 1:
				$str = '<h2 class="t3-row-header">' . htmlspecialchars($str) . '</h2>
					';
				break;
			case 0:
				$str = '<h3 class="divider">' . htmlspecialchars($str) . '</h3>
					';
				break;
		}
		return $str;
	}

	/**
	 * Returns prepared content
	 *
	 * @param string $str Content to format.
	 * @return string Formatted content.
	 */
	public function prepareContent($str) {
		return '<p>' . nl2br(trim(strip_tags($str, $this->allowedHTML))) . '</p>
		';
	}

	/**
	 * Prints a single $table/$field information piece
	 * If $anchors is set, then seeAlso references to the same table will be page-anchors, not links.
	 *
	 * @param string $key CSH key / table name
	 * @param string $field Sub key / field name
	 * @param bool $anchors If anchors is to be shown.
	 * @return string HTML content
	 */
	public function printItem($key, $field, $anchors = FALSE) {
		$out = '';
		if ($key && (!$field || is_array($GLOBALS['TCA_DESCR'][$key]['columns'][$field]))) {
			// Make seeAlso references.
			$seeAlsoRes = $this->make_seeAlso($GLOBALS['TCA_DESCR'][$key]['columns'][$field]['seeAlso'], $anchors ? $key : '');
			// Making item:
			$out = '<a name="' . $key . '.' . $field . '"></a>' . $this->headerLine($this->getTableFieldLabel($key, $field), 1) . $this->prepareContent($GLOBALS['TCA_DESCR'][$key]['columns'][$field]['description']) . ($GLOBALS['TCA_DESCR'][$key]['columns'][$field]['details'] ? $this->headerLine(($GLOBALS['LANG']->getLL('details') . ':')) . $this->prepareContent($GLOBALS['TCA_DESCR'][$key]['columns'][$field]['details']) : '') . ($GLOBALS['TCA_DESCR'][$key]['columns'][$field]['syntax'] ? $this->headerLine(($GLOBALS['LANG']->getLL('syntax') . ':')) . $this->prepareContent($GLOBALS['TCA_DESCR'][$key]['columns'][$field]['syntax']) : '') . ($GLOBALS['TCA_DESCR'][$key]['columns'][$field]['image'] ? $this->printImage($GLOBALS['TCA_DESCR'][$key]['columns'][$field]['image'], $GLOBALS['TCA_DESCR'][$key]['columns'][$field]['image_descr']) : '') . ($GLOBALS['TCA_DESCR'][$key]['columns'][$field]['seeAlso'] && $seeAlsoRes ? $this->headerLine(($GLOBALS['LANG']->getLL('seeAlso') . ':')) . '<p>' . $seeAlsoRes . '</p>' : '') . ($this->back ? '<br /><p><a href="' . htmlspecialchars($this->moduleUrl . '&tfID=' . rawurlencode($this->back)) . '" class="typo3-goBack">' . htmlspecialchars($GLOBALS['LANG']->getLL('goBack')) . '</a></p>' : '') . '<br />';
		}
		return $out;
	}

	/**
	 * Returns labels for a given field in a given structure
	 *
	 * @param string $key CSH key / table name
	 * @param string $field Sub key / field name
	 * @return array Table and field labels in a numeric array
	 */
	public function getTableFieldNames($key, $field) {
		$GLOBALS['LANG']->loadSingleTableDescription($key);
		// Define the label for the key
		$keyName = $key;
		if (is_array($GLOBALS['TCA_DESCR'][$key]['columns']['']) && isset($GLOBALS['TCA_DESCR'][$key]['columns']['']['alttitle'])) {
			// If there's an alternative title, use it
			$keyName = $GLOBALS['TCA_DESCR'][$key]['columns']['']['alttitle'];
		} elseif (isset($GLOBALS['TCA'][$key])) {
			// Otherwise, if it's a table, use its title
			$keyName = $GLOBALS['TCA'][$key]['ctrl']['title'];
		} else {
			// If no title was found, make sure to remove any "_MOD_"
			$keyName = preg_replace('/^_MOD_/', '', $key);
		}
		// Define the label for the field
		$fieldName = $field;
		if (is_array($GLOBALS['TCA_DESCR'][$key]['columns'][$field]) && isset($GLOBALS['TCA_DESCR'][$key]['columns'][$field]['alttitle'])) {
			// If there's an alternative title, use it
			$fieldName = $GLOBALS['TCA_DESCR'][$key]['columns'][$field]['alttitle'];
		} elseif (isset($GLOBALS['TCA'][$key]) && isset($GLOBALS['TCA'][$key]['columns'][$field])) {
			// Otherwise, if it's a table, use its title
			$fieldName = $GLOBALS['TCA'][$key]['columns'][$field]['label'];
		}
		return array($keyName, $fieldName);
	}

	/**
	 * Returns composite label for table/field
	 *
	 * @param string $key CSH key / table name
	 * @param string $field Sub key / field name
	 * @param string $mergeToken Token to merge the two strings with
	 * @return string Labels joined with merge token
	 * @see getTableFieldNames()
	 */
	public function getTableFieldLabel($key, $field = '', $mergeToken = ': ') {
		// Get table / field parts:
		list($tableName, $fieldName) = $this->getTableFieldNames($key, $field);
		// Create label:
		$labelString = $GLOBALS['LANG']->sL($tableName) . ($field ? $mergeToken . rtrim(trim($GLOBALS['LANG']->sL($fieldName)), ':') : '');
		return $labelString;
	}

}
