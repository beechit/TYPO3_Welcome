<?php
namespace TYPO3\CMS\Backend\Controller\Wizard;

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
 * Script Class for colorpicker wizard
 *
 * @author Mathias Schreiber <schreiber@wmdb.de>
 * @author Peter Kühn <peter@kuehn.com>
 * @author Kasper Skårhøj <typo3@typo3.com>
 */
class ColorpickerController extends AbstractWizardController {

	/**
	 * Wizard parameters, coming from TCEforms linking to the wizard.
	 *
	 * @var array
	 */
	public $P;

	/**
	 * Value of the current color picked.
	 *
	 * @var string
	 */
	public $colorValue;

	/**
	 * Serialized functions for changing the field...
	 * Necessary to call when the value is transferred to the TCEform since the form might
	 * need to do internal processing. Otherwise the value is simply not be saved.
	 *
	 * @var string
	 */
	public $fieldChangeFunc;

	/**
	 * @var string
	 */
	protected $fieldChangeFuncHash;

	/**
	 * Form name (from opener script)
	 *
	 * @var string
	 */
	public $fieldName;

	/**
	 * Field name (from opener script)
	 *
	 * @var string
	 */
	public $formName;

	/**
	 * ID of element in opener script for which to set color.
	 *
	 * @var string
	 */
	public $md5ID;

	/**
	 * Internal: If FALSE, a frameset is rendered, if TRUE the content of the picker script.
	 *
	 * @var int
	 */
	public $showPicker;

	/**
	 * @var string
	 */
	public $HTMLcolorList = 'aqua,black,blue,fuchsia,gray,green,lime,maroon,navy,olive,purple,red,silver,teal,yellow,white';

	/**
	 * @var string
	 */
	public $pickerImage = '';

	/**
	 * Error message if image not found.
	 *
	 * @var string
	 */
	public $imageError = '';

	/**
	 * Document template object
	 *
	 * @var \TYPO3\CMS\Backend\Template\DocumentTemplate
	 */
	public $doc;

	/**
	 * @var string
	 */
	public $content;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->getLanguageService()->includeLLFile('EXT:lang/locallang_wizards.xlf');
		$GLOBALS['SOBE'] = $this;

		$this->init();
	}

	/**
	 * Initialises the Class
	 *
	 * @return void
	 */
	protected function init() {
		// Setting GET vars (used in frameset script):
		$this->P = GeneralUtility::_GP('P');
		// Setting GET vars (used in colorpicker script):
		$this->colorValue = GeneralUtility::_GP('colorValue');
		$this->fieldChangeFunc = GeneralUtility::_GP('fieldChangeFunc');
		$this->fieldChangeFuncHash = GeneralUtility::_GP('fieldChangeFuncHash');
		$this->fieldName = GeneralUtility::_GP('fieldName');
		$this->formName = GeneralUtility::_GP('formName');
		$this->md5ID = GeneralUtility::_GP('md5ID');
		$this->exampleImg = GeneralUtility::_GP('exampleImg');
		// Resolving image (checking existence etc.)
		$this->imageError = '';
		if ($this->exampleImg) {
			$this->pickerImage = GeneralUtility::getFileAbsFileName($this->exampleImg, 1, 1);
			if (!$this->pickerImage || !@is_file($this->pickerImage)) {
				$this->imageError = 'ERROR: The image, "' . $this->exampleImg . '", could not be found!';
			}
		}
		$update = '';
		if ($this->areFieldChangeFunctionsValid()) {
			// Setting field-change functions:
			$fieldChangeFuncArr = unserialize($this->fieldChangeFunc);
			unset($fieldChangeFuncArr['alert']);
			foreach ($fieldChangeFuncArr as $v) {
				$update .= '
				parent.opener.' . $v;
			}
		}
		// Initialize document object:
		$this->doc = GeneralUtility::makeInstance(\TYPO3\CMS\Backend\Template\DocumentTemplate::class);
		$this->doc->backPath = $this->getBackPath();
		$this->doc->JScode = $this->doc->wrapScriptTags('
			function checkReference() {	//
				if (parent.opener && parent.opener.document && parent.opener.document.' . $this->formName . ' && parent.opener.document.' . $this->formName . '["' . $this->fieldName . '"]) {
					return parent.opener.document.' . $this->formName . '["' . $this->fieldName . '"];
				} else {
					close();
				}
			}
			function changeBGcolor(color) {	// Changes the color in the table sample back in the TCEform.
			    if (parent.opener.document.layers) {
			        parent.opener.document.layers["' . $this->md5ID . '"].bgColor = color;
			    } else if (parent.opener.document.all) {
			        parent.opener.document.all["' . $this->md5ID . '"].style.background = color;
				} else if (parent.opener.document.getElementById && parent.opener.document.getElementById("' . $this->md5ID . '")) {
					parent.opener.document.getElementById("' . $this->md5ID . '").bgColor = color;
				}
			}
			function setValue(input) {	//
				var field = checkReference();
				if (field) {
					field.value = input;
					' . $update . '
					changeBGcolor(input);
				}
			}
			function getValue() {	//
				var field = checkReference();
				return field.value;
			}
		');
		// Start page:
		$this->content .= $this->doc->startPage($this->getLanguageService()->getLL('colorpicker_title'));
	}

	/**
	 * Main Method, rendering either colorpicker or frameset depending on ->showPicker
	 *
	 * @return void
	 */
	public function main() {
		// Show frameset by default:
		if (!GeneralUtility::_GP('showPicker')) {
			$this->frameSet();
		} else {
			// Putting together the items into a form:
			$content = '
				<form name="colorform" method="post" action="' . htmlspecialchars(BackendUtility::getModuleUrl('wizard_colorpicker')) . '">
					' . $this->colorMatrix() . '
					' . $this->colorList() . '
					' . $this->colorImage() . '

						<!-- Value box: -->
					<p class="c-head">' . $this->getLanguageService()->getLL('colorpicker_colorValue', TRUE) . '</p>
					<table border="0" cellpadding="0" cellspacing="3">
						<tr>
							<td>
								<input type="text" ' . $this->doc->formWidth(7) . ' maxlength="10" name="colorValue" value="' . htmlspecialchars($this->colorValue) . '" />
							</td>
							<td style="background-color:' . htmlspecialchars($this->colorValue) . '; border: 1px solid black;">
								<span style="color: black;">' . $this->getLanguageService()->getLL('colorpicker_black', TRUE) . '</span>&nbsp;<span style="color: white;">' . $this->getLanguageService()->getLL('colorpicker_white', TRUE) . '</span>
							</td>
							<td>
								<input class="btn btn-default" type="submit" name="save_close" value="' . $this->getLanguageService()->getLL('colorpicker_setClose', TRUE) . '" />
							</td>
						</tr>
					</table>

						<!-- Hidden fields with values that has to be kept constant -->
					<input type="hidden" name="showPicker" value="1" />
					<input type="hidden" name="fieldChangeFunc" value="' . htmlspecialchars($this->fieldChangeFunc) . '" />
					<input type="hidden" name="fieldChangeFuncHash" value="' . htmlspecialchars($this->fieldChangeFuncHash) . '" />
					<input type="hidden" name="fieldName" value="' . htmlspecialchars($this->fieldName) . '" />
					<input type="hidden" name="formName" value="' . htmlspecialchars($this->formName) . '" />
					<input type="hidden" name="md5ID" value="' . htmlspecialchars($this->md5ID) . '" />
					<input type="hidden" name="exampleImg" value="' . htmlspecialchars($this->exampleImg) . '" />
				</form>';
			// If the save/close button is clicked, then close:
			if (GeneralUtility::_GP('save_close')) {
				$content .= $this->doc->wrapScriptTags('
					setValue(' . GeneralUtility::quoteJSvalue($this->colorValue) . ');
					parent.close();
				');
			}
			// Output:
			$this->content .= $this->doc->section($this->getLanguageService()->getLL('colorpicker_title'), $content, 0, 1);
		}
	}

	/**
	 * Returnes the sourcecode to the browser
	 *
	 * @return void
	 */
	public function printContent() {
		$this->content .= $this->doc->endPage();
		$this->content = $this->doc->insertStylesAndJS($this->content);
		echo $this->content;
	}

	/**
	 * Returns a frameset so our JavaScript Reference isn't lost
	 * Took some brains to figure this one out ;-)
	 * If Peter wouldn't have been I would've gone insane...
	 *
	 * @return void
	 */
	public function frameSet() {
		$this->getDocumentTemplate()->JScode = $this->getDocumentTemplate()->wrapScriptTags('
				if (!window.opener) {
					alert("ERROR: Sorry, no link to main window... Closing");
					close();
				}
		');
		$this->getDocumentTemplate()->startPage($this->getLanguageService()->getLL('colorpicker_title'));

		// URL for the inner main frame:
		$url = BackendUtility::getModuleUrl(
			'wizard_colorpicker',
			array(
				'showPicker' => 1,
				'colorValue' => $this->P['currentValue'],
				'fieldName' => $this->P['itemName'],
				'formName' => $this->P['formName'],
				'exampleImg' => $this->P['exampleImg'],
				'md5ID' => $this->P['md5ID'],
				'fieldChangeFunc' => serialize($this->P['fieldChangeFunc']),
				'fieldChangeFuncHash' => $this->P['fieldChangeFuncHash'],
			)
		);
		$this->content = $this->getDocumentTemplate()->getPageRenderer()->render(\TYPO3\CMS\Core\Page\PageRenderer::PART_HEADER) . '
			<frameset rows="*,1" framespacing="0" frameborder="0" border="0">
				<frame name="content" src="' . htmlspecialchars($url) . '" marginwidth="0" marginheight="0" frameborder="0" scrolling="auto" noresize="noresize" />
				<frame name="menu" src="dummy.php" marginwidth="0" marginheight="0" frameborder="0" scrolling="no" noresize="noresize" />
			</frameset>
		</html>';
	}

	/************************************
	 *
	 * Rendering of various color selectors
	 *
	 ************************************/
	/**
	 * Creates a color matrix table
	 *
	 * @return void
	 */
	public function colorMatrix() {
		$steps = 51;
		// Get colors:
		$color = array();
		for ($rr = 0; $rr < 256; $rr += $steps) {
			for ($gg = 0; $gg < 256; $gg += $steps) {
				for ($bb = 0; $bb < 256; $bb += $steps) {
					$color[] = '#' . substr(('0' . dechex($rr)), -2) . substr(('0' . dechex($gg)), -2) . substr(('0' . dechex($bb)), -2);
				}
			}
		}
		// Traverse colors:
		$columns = 24;
		$rows = 0;
		$tRows = array();
		while (isset($color[$columns * $rows])) {
			$tCells = array();
			for ($i = 0; $i < $columns; $i++) {
				$tCells[] = '
					<td bgcolor="' . $color[($columns * $rows + $i)] . '" onclick="document.colorform.colorValue.value = \'' . $color[($columns * $rows + $i)] . '\'; document.colorform.submit();" title="' . $color[($columns * $rows + $i)] . '">&nbsp;&nbsp;</td>';
			}
			$tRows[] = '
				<tr>' . implode('', $tCells) . '
				</tr>';
			$rows++;
		}
		$table = '
			<p class="c-head">' . $this->getLanguageService()->getLL('colorpicker_fromMatrix', TRUE) . '</p>
			<table border="0" cellpadding="1" cellspacing="1" style="width:100%; border: 1px solid black; cursor:crosshair;">' . implode('', $tRows) . '
			</table>';
		return $table;
	}

	/**
	 * Creates a selector box with all HTML color names.
	 *
	 * @return void
	 */
	public function colorList() {
		// Initialize variables:
		$colors = explode(',', $this->HTMLcolorList);
		$currentValue = strtolower($this->colorValue);
		$opt = array();
		$opt[] = '<option value=""></option>';
		// Traverse colors, making option tags for selector box.
		foreach ($colors as $colorName) {
			$opt[] = '<option style="background-color: ' . $colorName . ';" value="' . htmlspecialchars($colorName) . '"' . ($currentValue == $colorName ? ' selected="selected"' : '') . '>' . htmlspecialchars($colorName) . '</option>';
		}
		// Compile selector box and return result:
		$output = '
			<p class="c-head">' . $this->getLanguageService()->getLL('colorpicker_fromList', TRUE) . '</p>
			<select onchange="document.colorform.colorValue.value = this.options[this.selectedIndex].value; document.colorform.submit(); return false;">
				' . implode('
				', $opt) . '
			</select><br />';
		return $output;
	}

	/**
	 * Creates a color image selector
	 *
	 * @return void
	 */
	public function colorImage() {
		// Handling color-picker image if any:
		if (!$this->imageError) {
			if ($this->pickerImage) {
				if (GeneralUtility::_POST('coords_x')) {
					$this->colorValue = '#' . $this->getIndex(\TYPO3\CMS\Core\Imaging\GraphicalFunctions::imageCreateFromFile($this->pickerImage), GeneralUtility::_POST('coords_x'), GeneralUtility::_POST('coords_y'));
				}
				$pickerFormImage = '
				<p class="c-head">' . $this->getLanguageService()->getLL('colorpicker_fromImage', TRUE) . '</p>
				<input type="image" src="../' . \TYPO3\CMS\Core\Utility\PathUtility::stripPathSitePrefix($this->pickerImage) . '" name="coords" style="cursor:crosshair;" /><br />';
			} else {
				$pickerFormImage = '';
			}
		} else {
			$pickerFormImage = '
			<p class="c-head">' . htmlspecialchars($this->imageError) . '</p>';
		}
		return $pickerFormImage;
	}

	/**
	 * Gets the HTML (Hex) Color Code for the selected pixel of an image
	 * This method handles the correct imageResource no matter what format
	 *
	 * @param resource $im Valid ImageResource returned by \TYPO3\CMS\Core\Imaging\GraphicalFunctions::imageCreateFromFile
	 * @param int $x X-Coordinate of the pixel that should be checked
	 * @param int $y Y-Coordinate of the pixel that should be checked
	 * @return string HEX RGB value for color
	 * @see colorImage()
	 */
	public function getIndex($im, $x, $y) {
		$rgb = ImageColorAt($im, $x, $y);
		$colorrgb = imagecolorsforindex($im, $rgb);
		$index['r'] = dechex($colorrgb['red']);
		$index['g'] = dechex($colorrgb['green']);
		$index['b'] = dechex($colorrgb['blue']);
		foreach ($index as $value) {
			if (strlen($value) == 1) {
				$hexvalue[] = strtoupper('0' . $value);
			} else {
				$hexvalue[] = strtoupper($value);
			}
		}
		$hex = implode('', $hexvalue);
		return $hex;
	}

	/**
	 * Determines whether submitted field change functions are valid
	 * and are coming from the system and not from an external abuse.
	 *
	 * @return bool Whether the submitted field change functions are valid
	 */
	protected function areFieldChangeFunctionsValid() {
		return $this->fieldChangeFunc && $this->fieldChangeFuncHash && $this->fieldChangeFuncHash === GeneralUtility::hmac($this->fieldChangeFunc);
	}

}
