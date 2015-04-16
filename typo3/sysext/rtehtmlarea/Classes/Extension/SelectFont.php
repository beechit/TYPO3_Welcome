<?php
namespace TYPO3\CMS\Rtehtmlarea\Extension;

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
 * SelectFont extension for htmlArea RTE
 *
 * @author Stanislas Rolland <typo3(arobas)sjbr.ca>
 */
class SelectFont extends \TYPO3\CMS\Rtehtmlarea\RteHtmlAreaApi {

	/**
	 * The key of the extension that is extending htmlArea RTE
	 *
	 * @var string
	 */
	protected $extensionKey = 'rtehtmlarea';

	/**
	 * The name of the plugin registered by the extension
	 *
	 * @var string
	 */
	protected $pluginName = 'SelectFont';

	/**
	 * Path to this main locallang file of the extension relative to the extension directory
	 *
	 * @var string
	 */
	protected $relativePathToLocallangFile = 'extensions/SelectFont/locallang.xlf';

	/**
	 * Path to the skin file relative to the extension directory
	 *
	 * @var string
	 */
	protected $relativePathToSkin = '';

	/**
	 * Reference to the invoking object
	 *
	 * @var \TYPO3\CMS\Rtehtmlarea\RteHtmlAreaBase
	 */
	protected $htmlAreaRTE;

	protected $thisConfig;

	// Reference to RTE PageTSConfig
	protected $toolbar;

	// Reference to RTE toolbar array
	protected $LOCAL_LANG;

	// Frontend language array
	protected $pluginButtons = 'fontstyle,fontsize';

	protected $convertToolbarForHtmlAreaArray = array(
		'fontstyle' => 'FontName',
		'fontsize' => 'FontSize'
	);

	protected $defaultFont = array(
		'fontstyle' => array(
			'Arial' => 'Arial,sans-serif',
			'Arial Black' => '\'Arial Black\',sans-serif',
			'Verdana' => 'Verdana,Arial,sans-serif',
			'Times New Roman' => '\'Times New Roman\',Times,serif',
			'Garamond' => 'Garamond',
			'Lucida Handwriting' => '\'Lucida Handwriting\'',
			'Courier' => 'Courier',
			'Webdings' => 'Webdings',
			'Wingdings' => 'Wingdings'
		),
		'fontsize' => array(
			'Extra small' => '8px',
			'Very small' => '9px',
			'Small' => '10px',
			'Medium' => '12px',
			'Large' => '16px',
			'Very large' => '24px',
			'Extra large' => '32px'
		)
	);

	protected $RTEProperties;

	public function main($parentObject) {
		$enabled = parent::main($parentObject) && $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['rtehtmlarea']['allowStyleAttribute'];
		if ($this->htmlAreaRTE->is_FE()) {
			$this->RTEProperties = $this->htmlAreaRTE->RTEsetup;
		} else {
			$this->RTEProperties = $this->htmlAreaRTE->RTEsetup['properties'];
		}
		return $enabled;
	}

	/**
	 * Return JS configuration of the htmlArea plugins registered by the extension
	 *
	 * @param int Relative id of the RTE editing area in the form
	 * @return string JS configuration for registered plugins
	 */
	public function buildJavascriptConfiguration($RTEcounter) {
		$registerRTEinJavascriptString = '';
		$pluginButtonsArray = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', $this->pluginButtons);
		// Process Page TSConfig configuration for each button
		foreach ($pluginButtonsArray as $buttonId) {
			if (in_array($buttonId, $this->toolbar)) {
				$registerRTEinJavascriptString .= $this->buildJSFontItemsConfig($RTEcounter, $buttonId);
			}
		}
		return $registerRTEinJavascriptString;
	}

	/**
	 * Return Javascript configuration of font faces
	 *
	 * @param int $RTEcounter: The index number of the current RTE editing area within the form.
	 * @param string $buttonId: button id
	 * @return string Javascript configuration of font faces
	 */
	protected function buildJSFontItemsConfig($RTEcounter, $buttonId) {
		$configureRTEInJavascriptString = '';
		$hideItems = '';
		$addItems = array();
		// Getting removal and addition configuration
		if (is_array($this->thisConfig['buttons.']) && is_array($this->thisConfig['buttons.'][$buttonId . '.'])) {
			if ($this->thisConfig['buttons.'][$buttonId . '.']['removeItems']) {
				$hideItems = $this->thisConfig['buttons.'][$buttonId . '.']['removeItems'];
			}
			if ($this->thisConfig['buttons.'][$buttonId . '.']['addItems']) {
				$addItems = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', $this->htmlAreaRTE->cleanList($this->thisConfig['buttons.'][$buttonId . '.']['addItems']), TRUE);
			}
		}
		// Initializing the items array
		$items = array();
		if ($this->htmlAreaRTE->is_FE()) {
			$items['none'] = array($GLOBALS['TSFE']->getLLL($buttonId == 'fontstyle' ? 'Default font' : 'Default size', $this->LOCAL_LANG), 'none');
		} else {
			$items['none'] = array($GLOBALS['LANG']->getLL($buttonId == 'fontstyle' ? 'Default font' : 'Default size'), 'none');
		}
		// Inserting and localizing default items
		if ($hideItems != '*') {
			$index = 0;
			foreach ($this->defaultFont[$buttonId] as $name => $value) {
				if (!\TYPO3\CMS\Core\Utility\GeneralUtility::inList($hideItems, strval(($index + 1)))) {
					if ($this->htmlAreaRTE->is_FE()) {
						$label = $GLOBALS['TSFE']->getLLL($name, $this->LOCAL_LANG);
					} else {
						$label = $GLOBALS['LANG']->getLL($name);
						if (!$label) {
							$label = $name;
						}
					}
					$items[$name] = array($label, $this->htmlAreaRTE->cleanList($value));
				}
				$index++;
			}
		}
		// Adding configured items
		if (is_array($this->RTEProperties[$buttonId == 'fontstyle' ? 'fonts.' : 'fontSizes.'])) {
			foreach ($this->RTEProperties[$buttonId == 'fontstyle' ? 'fonts.' : 'fontSizes.'] as $name => $conf) {
				$name = substr($name, 0, -1);
				if (in_array($name, $addItems)) {
					$label = $this->htmlAreaRTE->getPageConfigLabel($conf['name'], 0);
					$items[$name] = array($label, $this->htmlAreaRTE->cleanList($conf['value']));
				}
			}
		}
		// Seting default item
		if ($this->thisConfig['buttons.'][$buttonId . '.']['defaultItem'] && $items[$this->thisConfig['buttons.'][$buttonId . '.']['defaultItem']]) {
			$items['none'] = array($items[$this->thisConfig['buttons.'][$buttonId . '.']['defaultItem']][0], 'none');
			unset($items[$this->thisConfig['buttons.'][$buttonId . '.']['defaultItem']]);
		}
		// Setting the JS list of options
		$itemsJSArray = array();
		foreach ($items as $name => $option) {
			$itemsJSArray[] = array('text' => $option[0], 'value' => $option[1]);
		}
		if ($this->htmlAreaRTE->is_FE()) {
			$GLOBALS['TSFE']->csConvObj->convArray($itemsJSArray, $this->htmlAreaRTE->OutputCharset, 'utf-8');
		}
		$itemsJSArray = json_encode(array('options' => $itemsJSArray));
		// Adding to button JS configuration
		if (!is_array($this->thisConfig['buttons.']) || !is_array($this->thisConfig['buttons.'][($buttonId . '.')])) {
			$configureRTEInJavascriptString .= '
			RTEarea[' . $RTEcounter . '].buttons.' . $buttonId . ' = new Object();';
		}
		$configureRTEInJavascriptString .= '
			RTEarea[' . $RTEcounter . '].buttons.' . $buttonId . '.dataUrl = "' . ($this->htmlAreaRTE->is_FE() && $GLOBALS['TSFE']->absRefPrefix ? $GLOBALS['TSFE']->absRefPrefix : '') . $this->htmlAreaRTE->writeTemporaryFile('', ($buttonId . '_' . $this->htmlAreaRTE->contentLanguageUid), 'js', $itemsJSArray) . '";';
		return $configureRTEInJavascriptString;
	}

}
