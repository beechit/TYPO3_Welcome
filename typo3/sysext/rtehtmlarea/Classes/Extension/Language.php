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
 * Language plugin for htmlArea RTE
 *
 * @author Stanislas Rolland <typo3(arobas)sjbr.ca>
 */
class Language extends \TYPO3\CMS\Rtehtmlarea\RteHtmlAreaApi {

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
	protected $pluginName = 'Language';

	/**
	 * Path to this main locallang file of the extension relative to the extension directory
	 *
	 * @var string
	 */
	protected $relativePathToLocallangFile = 'extensions/Language/locallang.xlf';

	/**
	 * Path to the skin file relative to the extension directory
	 *
	 * @var string
	 */
	protected $relativePathToSkin = 'Resources/Public/Css/Skin/Plugins/language.css';

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
	protected $pluginButtons = 'lefttoright,righttoleft,language,showlanguagemarks';

	protected $convertToolbarForHtmlAreaArray = array(
		'lefttoright' => 'LeftToRight',
		'righttoleft' => 'RightToLeft',
		'language' => 'Language',
		'showlanguagemarks' => 'ShowLanguageMarks'
	);

	public function main($parentObject) {
		if (!\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('static_info_tables')) {
			$this->pluginButtons = \TYPO3\CMS\Core\Utility\GeneralUtility::rmFromList('language', $this->pluginButtons);
		}
		return parent::main($parentObject);
	}

	/**
	 * Return JS configuration of the htmlArea plugins registered by the extension
	 *
	 * @param int Relative id of the RTE editing area in the form
	 * @return string JS configuration for registered plugins
	 */
	public function buildJavascriptConfiguration($RTEcounter) {
		$button = 'language';
		$registerRTEinJavascriptString = '';
		if (!is_array($this->thisConfig['buttons.']) || !is_array($this->thisConfig['buttons.'][($button . '.')])) {
			$registerRTEinJavascriptString .= '
			RTEarea[' . $RTEcounter . '].buttons.' . $button . ' = new Object();';
		}
		if ($this->htmlAreaRTE->is_FE()) {
			$first = $GLOBALS['TSFE']->getLLL('No language mark', $this->LOCAL_LANG);
		} else {
			$first = $GLOBALS['LANG']->getLL('No language mark');
		}
		$languages = array('none' => $first);
		$languages = array_flip(array_merge($languages, $this->getLanguages()));
		$languagesJSArray = array();
		foreach ($languages as $key => $value) {
			$languagesJSArray[] = array('text' => $key, 'value' => $value);
		}
		$languagesJSArray = json_encode(array('options' => $languagesJSArray));
		$registerRTEinJavascriptString .= '
			RTEarea[' . $RTEcounter . '].buttons.' . $button . '.dataUrl = "' . ($this->htmlAreaRTE->is_FE() && $GLOBALS['TSFE']->absRefPrefix ? $GLOBALS['TSFE']->absRefPrefix : '') . $this->htmlAreaRTE->writeTemporaryFile('', ($button . '_' . $this->htmlAreaRTE->contentLanguageUid), 'js', $languagesJSArray) . '";';
		return $registerRTEinJavascriptString;
	}

	/**
	 * Getting all languages into an array
	 * where the key is the ISO alpha-2 code of the language
	 * and where the value are the name of the language in the current language
	 * Note: we exclude sacred and constructed languages
	 *
	 * @return array An array of names of languages
	 */
	public function getLanguages() {
		$nameArray = array();
		if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('static_info_tables')) {
			$where = '1=1';
			$table = 'static_languages';
			$lang = \SJBR\StaticInfoTables\Utility\LocalizationUtility::getCurrentLanguage();
			$titleFields = \SJBR\StaticInfoTables\Utility\LocalizationUtility::getLabelFields($table, $lang);
			$prefixedTitleFields = array();
			foreach ($titleFields as $titleField) {
				$prefixedTitleFields[] = $table . '.' . $titleField;
			}
			$labelFields = implode(',', $prefixedTitleFields);
			// Restrict to certain languages
			if (is_array($this->thisConfig['buttons.']) && is_array($this->thisConfig['buttons.']['language.']) && isset($this->thisConfig['buttons.']['language.']['restrictToItems'])) {
				$languageList = implode('\',\'', \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', $GLOBALS['TYPO3_DB']->fullQuoteStr(strtoupper($this->thisConfig['buttons.']['language.']['restrictToItems']), $table)));
				$where .= ' AND ' . $table . '.lg_iso_2 IN (' . $languageList . ')';
			}
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($table . '.lg_iso_2,' . $table . '.lg_country_iso_2,' . $labelFields, $table, $where . ' AND lg_constructed = 0 ' . ($this->htmlAreaRTE->is_FE() ? $GLOBALS['TSFE']->sys_page->enableFields($table) : \TYPO3\CMS\Backend\Utility\BackendUtility::BEenableFields($table) . \TYPO3\CMS\Backend\Utility\BackendUtility::deleteClause($table)));
			$prefixLabelWithCode = (bool)$this->thisConfig['buttons.']['language.']['prefixLabelWithCode'];
			$postfixLabelWithCode = (bool)$this->thisConfig['buttons.']['language.']['postfixLabelWithCode'];
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				$code = strtolower($row['lg_iso_2']) . ($row['lg_country_iso_2'] ? '-' . strtoupper($row['lg_country_iso_2']) : '');
				foreach ($titleFields as $titleField) {
					if ($row[$titleField]) {
						$nameArray[$code] = $prefixLabelWithCode ? $code . ' - ' . $row[$titleField] : ($postfixLabelWithCode ? $row[$titleField] . ' - ' . $code : $row[$titleField]);
						break;
					}
				}
			}
			$GLOBALS['TYPO3_DB']->sql_free_result($res);
			uasort($nameArray, 'strcoll');
		}
		return $nameArray;
	}

	/**
	 * Return an updated array of toolbar enabled buttons
	 *
	 * @param array $show: array of toolbar elements that will be enabled, unless modified here
	 * @return array toolbar button array, possibly updated
	 */
	public function applyToolbarConstraints($show) {
		if (!\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('static_info_tables')) {
			return array_diff($show, array('language'));
		} else {
			return $show;
		}
	}

}
