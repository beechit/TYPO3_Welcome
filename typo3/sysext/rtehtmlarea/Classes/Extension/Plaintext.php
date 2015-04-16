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
 * Copy as Plain Text extension for htmlArea RTE
 *
 * @author Stanislas Rolland <typo3(arobas)sjbr.ca>
 */
class Plaintext extends \TYPO3\CMS\Rtehtmlarea\RteHtmlAreaApi {

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
	protected $pluginName = 'PlainText';

	/**
	 * Path to this main locallang file of the extension relative to the extension directory
	 *
	 * @var string
	 */
	protected $relativePathToLocallangFile = '';

	/**
	 * Path to the skin file relative to the extension directory
	 *
	 * @var string
	 */
	protected $relativePathToSkin = 'Resources/Public/Css/Skin/Plugins/plain-text.css';

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
	protected $pluginButtons = 'pastetoggle,pastebehaviour';

	protected $convertToolbarForHtmlAreaArray = array(
		'pastetoggle' => 'PasteToggle',
		'pastebehaviour' => 'PasteBehaviour'
	);

	public function main($parentObject) {
		// Opera has no onPaste event to handle
		return parent::main($parentObject) && $this->htmlAreaRTE->client['browser'] != 'opera';
	}

	/**
	 * Return JS configuration of the htmlArea plugins registered by the extension
	 *
	 * @param int Relative id of the RTE editing area in the form
	 * @return string JS configuration for registered plugins
	 */
	public function buildJavascriptConfiguration($RTEcounter) {
		$registerRTEinJavascriptString = '';
		$button = 'pastebehaviour';
		// Get current TYPO3 User Setting, if available
		if (TYPO3_MODE === 'BE' && \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('setup') && is_array($GLOBALS['TYPO3_USER_SETTINGS']) && is_object($GLOBALS['BE_USER'])) {
			if (!is_array($this->thisConfig['buttons.']) || !is_array($this->thisConfig['buttons.'][($button . '.')])) {
				$registerRTEinJavascriptString .= '
			RTEarea[' . $RTEcounter . '].buttons.' . $button . ' = new Object();';
			}
			$registerRTEinJavascriptString .= '
			RTEarea[' . $RTEcounter . '].buttons.' . $button . '.current = "' . (isset($GLOBALS['BE_USER']->uc['rteCleanPasteBehaviour']) ? $GLOBALS['BE_USER']->uc['rteCleanPasteBehaviour'] : 'plainText') . '";';
		}
		return $registerRTEinJavascriptString;
	}

	/**
	 * Return an updated array of toolbar enabled buttons
	 *
	 * @param array $show: array of toolbar elements that will be enabled, unless modified here
	 * @return array toolbar button array, possibly updated
	 */
	public function applyToolbarConstraints($show) {
		$removeButtons = array();
		// Remove pastebehaviour button if pastetoggle is not configured
		if (!in_array('pastetoggle', $show)) {
			$removeButtons[] = 'pastebehaviour';
		}
		// Remove pastebehaviour button if TYPO3 User Settings are available
		if (TYPO3_MODE === 'BE' && \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('setup') && is_array($GLOBALS['TYPO3_USER_SETTINGS']) && is_object($GLOBALS['BE_USER'])) {
			$removeButtons[] = 'pastebehaviour';
		}
		return array_diff($show, $removeButtons);
	}

}
