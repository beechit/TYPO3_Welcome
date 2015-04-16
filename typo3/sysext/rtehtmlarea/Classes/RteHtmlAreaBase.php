<?php
namespace TYPO3\CMS\Rtehtmlarea;

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

use TYPO3\CMS\Backend\Form\FormEngine;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * A RTE using the htmlArea editor
 *
 * @author Philipp Borgmann <philipp.borgmann@gmx.de>
 * @author Stanislas Rolland <typo3(arobas)sjbr.ca>
 */
class RteHtmlAreaBase extends \TYPO3\CMS\Backend\Rte\AbstractRte {

	// Configuration of supported browsers
	/**
	 * @var array
	 */
	public $conf_supported_browser = array(
		'msie' => array(
			array(
				'version' => 9.0,
				'system' => array(
					'allowed' => array(
						'winNT',
						'win98',
						'win95'
					)
				)
			)
		),
		'gecko' => array(
			array(
				'version' => 1.8
			)
		),
		'webkit' => array(
			array(
				'version' => 534
			),
			array(
				'version' => 523,
				'system' => array(
					'disallowed' => array(
						'iOS',
						'android'
					)
				)
			)
		),
		'opera' => array(
			array(
				'version' => 9.62,
				'system' => array(
					'disallowed' => array(
						'iOS',
						'android'
					)
				)
			)
		)
	);

	// Always hide these toolbar buttons (TYPO3 button name)
	/**
	 * @var array
	 */
	public $conf_toolbar_hide = array(
		'showhelp'
	);

	// The order of the toolbar: the name is the TYPO3-button name
	/**
	 * @var string
	 */
	public $defaultToolbarOrder;

	// Conversion array: TYPO3 button names to htmlArea button names
	/**
	 * @var array
	 */
	public $convertToolbarForHtmlAreaArray = array(
		'showhelp' => 'ShowHelp',
		'space' => 'space',
		'bar' => 'separator',
		'linebreak' => 'linebreak'
	);

	/**
	 * @var array
	 */
	public $pluginButton = array();

	/**
	 * @var array
	 */
	public $pluginLabel = array();

	// Alternative style for RTE <div> tag.
	public $RTEdivStyle;

	// Relative path to this extension. It ends with "/"
	public $extHttpPath;

	public $backPath = '';

	// TYPO3 site url
	public $siteURL;

	// TYPO3 host url
	public $hostURL;

	// Typo3 version
	public $typoVersion;

	// Identifies the RTE as being the one from the "rtehtmlarea" extension if any external code needs to know
	/**
	 * @var string
	 */
	public $ID = 'rtehtmlarea';

	// If set, the content goes into a regular TEXT area field - for developing testing of transformations.
	/**
	 * @var bool
	 */
	public $debugMode = FALSE;

	// For the editor
	/**
	 * @var array
	 */
	public $client;

	/**
	 * Reference to parent object, which is an instance of the TCEforms
	 *
	 * @var \TYPO3\CMS\Backend\Form\FormEngine
	 */
	public $TCEform;

	/**
	 * @var string
	 */
	public $elementId;

	/**
	 * @var array
	 */
	public $elementParts;

	/**
	 * @var string
	 */
	public $tscPID;

	/**
	 * @var string
	 */
	public $typeVal;

	/**
	 * @var int
	 */
	public $thePid;

	/**
	 * @var array
	 */
	public $RTEsetup;

	/**
	 * @var array
	 */
	public $thisConfig;

	public $language;
	/**
	 * TYPO3 language code of the content language
	 */
	public $contentTypo3Language;
	/**
	 * ISO language code of the content language
	 */
	public $contentISOLanguage;
	/**
	 * Language service object for localization to the content language
	 */
	protected $contentLanguageService;
	public $charset = 'utf-8';

	public $contentCharset = 'utf-8';

	public $OutputCharset = 'utf-8';

	/**
	 * @var string
	 */
	public $editorCSS;

	/**
	 * @var array
	 */
	public $specConf;

	/**
	 * @var array
	 */
	public $toolbar = array();

	// Save the buttons for the toolbar
	/**
	 * @var array
	 */
	public $toolbarOrderArray = array();

	protected $pluginEnabledArray = array();

	// Array of plugin id's enabled in the current RTE editing area
	protected $pluginEnabledCumulativeArray = array();

	// Cumulative array of plugin id's enabled so far in any of the RTE editing areas of the form
	public $registeredPlugins = array();

	// Array of registered plugins indexed by their plugin Id's
	protected $fullScreen = FALSE;
	// Page renderer object
	protected $pageRenderer;

	/**
	 * Returns TRUE if the RTE is available. Here you check if the browser requirements are met.
	 * If there are reasons why the RTE cannot be displayed you simply enter them as text in ->errorLog
	 *
	 * @return bool TRUE if this RTE object offers an RTE in the current browser environment
	 */
	public function isAvailable() {
		$this->client = $this->clientInfo();
		$this->errorLog = array();
		if (!$this->debugMode) {
			// If debug-mode, let any browser through
			$rteIsAvailable = FALSE;
			$rteConfBrowser = $this->conf_supported_browser;
			if (is_array($rteConfBrowser)) {
				foreach ($rteConfBrowser as $browser => $browserConf) {
					if ($browser == $this->client['browser']) {
						// Config for Browser found, check it:
						if (is_array($browserConf)) {
							foreach ($browserConf as $browserConfSub) {
								if ($browserConfSub['version'] <= $this->client['version'] || empty($browserConfSub['version'])) {
									// Version is supported
									if (is_array($browserConfSub['system'])) {
										// Check against allowed systems
										if (is_array($browserConfSub['system']['allowed'])) {
											foreach ($browserConfSub['system']['allowed'] as $system) {
												if (in_array($system, $this->client['all_systems'])) {
													$rteIsAvailable = TRUE;
													break;
												}
											}
										} else {
											// All allowed
											$rteIsAvailable = TRUE;
										}
										// Check against disallowed systems
										if (is_array($browserConfSub['system']['disallowed'])) {
											foreach ($browserConfSub['system']['disallowed'] as $system) {
												if (in_array($system, $this->client['all_systems'])) {
													$rteIsAvailable = FALSE;
													break;
												}
											}
										}
									} else {
										// No system config: system is supported
										$rteIsAvailable = TRUE;
										break;
									}
								}
							}
						} else {
							// no config for this browser found, so all versions or system with this browsers are allow
							$rteIsAvailable = TRUE;
							break;
						}
					}
				}
			} else {

			}
			if (!$rteIsAvailable) {
				$this->errorLog[] = 'RTE: Browser not supported.';
			}
			if (\TYPO3\CMS\Core\Utility\VersionNumberUtility::convertVersionNumberToInteger(TYPO3_version) < 4000000) {
				$rteIsAvailable = FALSE;
				$this->errorLog[] = 'rte: This version of htmlArea RTE cannot run under this version of TYPO3.';
			}
		}
		return $rteIsAvailable;
	}

	/**
	 * Draws the RTE as an iframe
	 *
	 * @param FormEngine $parentObject Reference to parent object, which is an instance of the TCEforms.
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
	public function drawRTE($parentObject, $table, $field, $row, $PA, $specConf, $thisConfig, $RTEtypeVal, $RTErelPath, $thePidValue) {
		$this->TCEform = $parentObject;
		$inline = $this->TCEform->inline;
		$GLOBALS['LANG']->includeLLFile('EXT:' . $this->ID . '/locallang.xlf');
		$this->client = $this->clientInfo();
		$this->typoVersion = \TYPO3\CMS\Core\Utility\VersionNumberUtility::convertVersionNumberToInteger(TYPO3_version);
		$this->userUid = 'BE_' . $GLOBALS['BE_USER']->user['uid'];
		// Draw form element:
		if ($this->debugMode) {
			// Draws regular text area (debug mode)
			$item = parent::drawRTE($this->TCEform, $table, $field, $row, $PA, $specConf, $thisConfig, $RTEtypeVal, $RTErelPath, $thePidValue);
		} else {
			// Draw real RTE
			/* =======================================
			 * INIT THE EDITOR-SETTINGS
			 * =======================================
			 */
			// Set backPath
			$this->backPath = $this->TCEform->backPath;
			// Get the path to this extension:
			$this->extHttpPath = $this->backPath . ExtensionManagementUtility::extRelPath($this->ID);
			// Get the site URL
			$this->siteURL = GeneralUtility::getIndpEnv('TYPO3_SITE_URL');
			// Get the host URL
			$this->hostURL = $this->siteURL . TYPO3_mainDir;
			// Element ID + pid
			$this->elementId = $PA['itemFormElName'];
			// Form element name
			$this->elementParts = explode('][', preg_replace('/\\]$/', '', preg_replace('/^(TSFE_EDIT\\[data\\]\\[|data\\[)/', '', $this->elementId)));
			// Find the page PIDs:
			list($this->tscPID, $this->thePid) = BackendUtility::getTSCpid(trim($this->elementParts[0]), trim($this->elementParts[1]), $thePidValue);
			// Record "types" field value:
			$this->typeVal = $RTEtypeVal;
			// TCA "types" value for record
			// Find "thisConfig" for record/editor:
			unset($this->RTEsetup);
			$this->RTEsetup = $GLOBALS['BE_USER']->getTSConfig('RTE', BackendUtility::getPagesTSconfig($this->tscPID));
			$this->thisConfig = $thisConfig;
			// Special configuration and default extras:
			$this->specConf = $specConf;
			if ($this->thisConfig['forceHTTPS']) {
				$this->extHttpPath = preg_replace('/^(http|https)/', 'https', $this->extHttpPath);
				$this->siteURL = preg_replace('/^(http|https)/', 'https', $this->siteURL);
				$this->hostURL = preg_replace('/^(http|https)/', 'https', $this->hostURL);
			}
			// Register RTE windows
			$this->TCEform->RTEwindows[] = $PA['itemFormElName'];
			$textAreaId = preg_replace('/[^a-zA-Z0-9_:.-]/', '_', $PA['itemFormElName']);
			$textAreaId = htmlspecialchars(preg_replace('/^[^a-zA-Z]/', 'x', $textAreaId));
			/* =======================================
			 * LANGUAGES & CHARACTER SETS
			 * =======================================
			 */
			// Languages: interface and content
			$this->language = $GLOBALS['LANG']->lang;
			if ($this->language === 'default' || !$this->language) {
				$this->language = 'en';
			}
			$this->contentLanguageUid = max($row['sys_language_uid'], 0);
			if ($this->contentLanguageUid) {
				$this->contentISOLanguage = $this->language;
				if (ExtensionManagementUtility::isLoaded('static_info_tables')) {
					$tableA = 'sys_language';
					$tableB = 'static_languages';
					$selectFields = $tableA . '.uid,' . $tableB . '.lg_iso_2,' . $tableB . '.lg_country_iso_2';
					$tableAB = $tableA . ' LEFT JOIN ' . $tableB . ' ON ' . $tableA . '.static_lang_isocode=' . $tableB . '.uid';
					$whereClause = $tableA . '.uid = ' . intval($this->contentLanguageUid);
					$whereClause .= BackendUtility::BEenableFields($tableA);
					$whereClause .= BackendUtility::deleteClause($tableA);
					$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($selectFields, $tableAB, $whereClause);
					while ($languageRow = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
						$this->contentISOLanguage = strtolower(trim($languageRow['lg_iso_2']) . (trim($languageRow['lg_country_iso_2']) ? '_' . trim($languageRow['lg_country_iso_2']) : ''));
					}
				}
			} else {
				$this->contentISOLanguage = trim($this->thisConfig['defaultContentLanguage']) ?: 'en';
				$languageCodeParts = explode('_', $this->contentISOLanguage);
				$this->contentISOLanguage = strtolower($languageCodeParts[0]) . ($languageCodeParts[1] ? '_' . strtoupper($languageCodeParts[1]) : '');
				// Find the configured language in the list of localization locales
				/** @var $locales \TYPO3\CMS\Core\Localization\Locales */
				$locales = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Localization\Locales::class);
				// If not found, default to 'en'
				if (!in_array($this->contentISOLanguage, $locales->getLocales())) {
					$this->contentISOLanguage = 'en';
				}
			}
			// Create content laguage service
			$this->contentLanguageService = GeneralUtility::makeInstance(\TYPO3\CMS\Lang\LanguageService::class);
			$this->contentTypo3Language = $this->contentISOLanguage === 'en' ? 'default' : $this->contentISOLanguage;
			$this->contentLanguageService->init($this->contentTypo3Language);
			/* =======================================
			 * TOOLBAR CONFIGURATION
			 * =======================================
			 */
			$this->initializeToolbarConfiguration();
			/* =======================================
			 * SET STYLES
			 * =======================================
			 */
			// Check if wizard_rte called this for fullscreen edtition
			if (GeneralUtility::_GP('M') === 'wizard_rte') {
				$this->fullScreen = TRUE;
				$RTEWidth = '100%';
				$RTEHeight = '100%';
				$RTEPaddingRight = '0';
				$editorWrapWidth = '100%';
			} else {
				$options = $GLOBALS['BE_USER']->userTS['options.'];
				$RTEWidth = 530 + (isset($options['RTELargeWidthIncrement']) ? (int)$options['RTELargeWidthIncrement'] : 150);
				$RTEWidth -= $inline->getStructureDepth() > 0 ? ($inline->getStructureDepth() + 1) * $inline->getLevelMargin() : 0;
				$RTEWidthOverride = is_object($GLOBALS['BE_USER']) && isset($GLOBALS['BE_USER']->uc['rteWidth']) && trim($GLOBALS['BE_USER']->uc['rteWidth']) ? trim($GLOBALS['BE_USER']->uc['rteWidth']) : trim($this->thisConfig['RTEWidthOverride']);
				if ($RTEWidthOverride) {
					if (strstr($RTEWidthOverride, '%')) {
						if ($this->client['browser'] != 'msie') {
							$RTEWidth = (int)$RTEWidthOverride > 0 ? $RTEWidthOverride : '100%';
						}
					} else {
						$RTEWidth = (int)$RTEWidthOverride > 0 ? (int)$RTEWidthOverride : $RTEWidth;
					}
				}
				$RTEWidth = strstr($RTEWidth, '%') ? $RTEWidth : $RTEWidth . 'px';
				$RTEHeight = 380 + (isset($options['RTELargeHeightIncrement']) ? (int)$options['RTELargeHeightIncrement'] : 0);
				$RTEHeightOverride = is_object($GLOBALS['BE_USER']) && isset($GLOBALS['BE_USER']->uc['rteHeight']) && (int)$GLOBALS['BE_USER']->uc['rteHeight'] ? (int)$GLOBALS['BE_USER']->uc['rteHeight'] : (int)$this->thisConfig['RTEHeightOverride'];
				$RTEHeight = $RTEHeightOverride > 0 ? $RTEHeightOverride : $RTEHeight;
				$RTEPaddingRight = '2px';
				$editorWrapWidth = '99%';
			}
			$editorWrapHeight = '100%';
			$this->RTEdivStyle = 'position:relative; left:0px; top:0px; height:' . $RTEHeight . 'px; width:' . $RTEWidth . '; border: 1px solid black; padding: 2px ' . $RTEPaddingRight . ' 2px 2px;';
			/* =======================================
			 * LOAD CSS AND JAVASCRIPT
			 * =======================================
			 */
			$this->pageRenderer = $GLOBALS['SOBE']->doc->getPageRenderer();
			// Preloading the pageStyle and including RTE skin stylesheets
			$this->addPageStyle();
			$this->addSkin();
			// Register RTE in JS
			$this->TCEform->additionalJS_post[] = $this->registerRTEinJS($this->TCEform->RTEcounter, $table, $row['uid'], $field, $textAreaId);
			// Set the save option for the RTE
			$this->TCEform->additionalJS_submit[] = $this->setSaveRTE($this->TCEform->RTEcounter, $this->TCEform->formName, $textAreaId, $PA['itemFormElName']);
			$this->TCEform->additionalJS_delete[] = $this->setDeleteRTE($this->TCEform->RTEcounter, $this->TCEform->formName, $textAreaId);
			// Loading ExtJs inline code
			$this->pageRenderer->enableExtJSQuickTips();
			// Add TYPO3 notifications JavaScript
			$this->pageRenderer->addJsFile('sysext/backend/Resources/Public/JavaScript/notifications.js');
			// Add RTE JavaScript
			$this->addRteJsFiles($this->TCEform->RTEcounter);
			$this->pageRenderer->addJsFile($this->buildJSMainLangFile($this->TCEform->RTEcounter));
			$this->pageRenderer->addJsInlineCode('HTMLArea-init', $this->getRteInitJsCode(), TRUE);
			/* =======================================
			 * DRAW THE EDITOR
			 * =======================================
			 */
			// Transform value:
			$value = $this->transformContent('rte', $PA['itemFormElValue'], $table, $field, $row, $specConf, $thisConfig, $RTErelPath, $thePidValue);
			// Further content transformation by registered plugins
			foreach ($this->registeredPlugins as $pluginId => $plugin) {
				if ($this->isPluginEnabled($pluginId) && method_exists($plugin, 'transformContent')) {
					$value = $plugin->transformContent($value);
				}
			}
			// Draw the textarea
			$visibility = 'hidden';
			$item = $this->triggerField($PA['itemFormElName']) . '
				<div id="pleasewait' . $textAreaId . '" class="pleasewait" style="display: block;" >' . $GLOBALS['LANG']->getLL('Please wait') . '</div>
				<div id="editorWrap' . $textAreaId . '" class="editorWrap" style="visibility: hidden; width:' . $editorWrapWidth . '; height:' . $editorWrapHeight . ';">
				<textarea id="RTEarea' . $textAreaId . '" name="' . htmlspecialchars($PA['itemFormElName']) . '" rows="0" cols="0" style="' . htmlspecialchars($this->RTEdivStyle, ENT_COMPAT, 'UTF-8', FALSE) . '">' . GeneralUtility::formatForTextarea($value) . '</textarea>
				</div>' . LF;
		}
		// Return form item:
		return $item;
	}

	/**
	 * Add links to content style sheets to document header
	 *
	 * @return void
	 */
	protected function addPageStyle() {
		$contentCssFileNames = $this->getContentCssFileNames();
		foreach ($contentCssFileNames as $contentCssKey => $contentCssFile) {
			$this->addStyleSheet('rtehtmlarea-content-' . $contentCssKey, $contentCssFile, 'htmlArea RTE Content CSS', 'alternate stylesheet');
		}
	}

	/**
	 * Get the name of the contentCSS files to use
	 *
	 * @return array An array of full file name of the content css files to use
	 */
	protected function getContentCssFileNames() {
		$contentCss = is_array($this->thisConfig['contentCSS.']) ? $this->thisConfig['contentCSS.'] : array();
		if (isset($this->thisConfig['contentCSS'])) {
			$contentCss[] = trim($this->thisConfig['contentCSS']);
		}
		$contentCssFiles = array();
		if (count($contentCss)) {
			foreach ($contentCss as $contentCssKey => $contentCssfile) {
				$fileName = trim($contentCssfile);
				$absolutePath = GeneralUtility::getFileAbsFileName($fileName);
				if (file_exists($absolutePath) && filesize($absolutePath)) {
					$contentCssFiles[$contentCssKey] = $this->getFullFileName($fileName);
				}
			}
		}
		// Fallback to default content css file if none of the configured files exists and is not empty
		if (count($contentCssFiles) === 0) {
			$contentCssFiles['default'] = $this->getFullFileName('EXT:' . $this->ID . '/Resources/Public/Css/ContentCss/Default.css');
		}
		return array_unique($contentCssFiles);
	}

	/**
	 * Add links to skin style sheet(s) to document header
	 *
	 * @return void
	 */
	protected function addSkin() {
		// Get skin file name from Page TSConfig if any
		$skinFilename = trim($this->thisConfig['skin']) ?: 'EXT:' . $this->ID . '/Resources/Public/Css/Skin/htmlarea.css';
		$this->editorCSS = $this->getFullFileName($skinFilename);
		$skinDir = dirname($this->editorCSS);
		// Editing area style sheet
		$this->editedContentCSS = $skinDir . '/htmlarea-edited-content.css';
		$this->addStyleSheet('rtehtmlarea-editing-area-skin', $this->editedContentCSS);
		// jQuery UI Resizable style sheet
		$this->addStyleSheet('jquery-ui-resizable', $skinDir . '/jquery-ui-resizable.css');
		// Main skin
		$this->addStyleSheet('rtehtmlarea-skin', $this->editorCSS);
		// Additional icons from registered plugins
		foreach ($this->pluginEnabledCumulativeArray[$this->TCEform->RTEcounter] as $pluginId) {
			if (is_object($this->registeredPlugins[$pluginId])) {
				$pathToSkin = $this->registeredPlugins[$pluginId]->getPathToSkin();
				if ($pathToSkin) {
					$key = $this->registeredPlugins[$pluginId]->getExtensionKey();
					$this->addStyleSheet('rtehtmlarea-plugin-' . $pluginId . '-skin', ($this->is_FE() ? ExtensionManagementUtility::siteRelPath($key) : $this->backPath . ExtensionManagementUtility::extRelPath($key)) . $pathToSkin);
				}
			}
		}
	}

	/**
	 * Add style sheet file to document header
	 *
	 * @param string $key: some key identifying the style sheet
	 * @param string $href: uri to the style sheet file
	 * @param string $title: value for the title attribute of the link element
	 * @param string $relation: value for the rel attribute of the link element
	 * @return void
	 */
	protected function addStyleSheet($key, $href, $title = '', $relation = 'stylesheet') {
		// If it was not known that an RTE-enabled CE would be created when the page was first created, the css would not be added to head
		if (is_object($this->TCEform->inline) && $this->TCEform->inline->isAjaxCall) {
			$this->TCEform->additionalCode_pre[$key] = '<link rel="' . $relation . '" type="text/css" href="' . $href . '" title="' . $title . '" />';
		} else {
			$this->pageRenderer->addCssFile($href, $relation, 'screen', $title);
		}
	}

	/**
	 * Initialize toolbar configuration and enable registered plugins
	 *
	 * @return void
	 */
	protected function initializeToolbarConfiguration() {
		// Enable registred plugins
		$this->enableRegisteredPlugins();
		// Configure toolbar
		$this->setToolbar();
		// Check if some plugins need to be disabled
		$this->setPlugins();
		// Merge the list of enabled plugins with the lists from the previous RTE editing areas on the same form
		$this->pluginEnabledCumulativeArray[$this->TCEform->RTEcounter] = $this->pluginEnabledArray;
		if ($this->TCEform->RTEcounter > 1 && isset($this->pluginEnabledCumulativeArray[$this->TCEform->RTEcounter - 1]) && is_array($this->pluginEnabledCumulativeArray[$this->TCEform->RTEcounter - 1])) {
			$this->pluginEnabledCumulativeArray[$this->TCEform->RTEcounter] = array_unique(array_values(array_merge($this->pluginEnabledArray, $this->pluginEnabledCumulativeArray[$this->TCEform->RTEcounter - 1])));
		}
	}

	/**
	 * Add registered plugins to the array of enabled plugins
	 */
	public function enableRegisteredPlugins() {
		// Traverse registered plugins
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->ID]['plugins'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->ID]['plugins'] as $pluginId => $pluginObjectConfiguration) {
				$plugin = FALSE;
				if (is_array($pluginObjectConfiguration) && count($pluginObjectConfiguration)) {
					$plugin = GeneralUtility::getUserObj($pluginObjectConfiguration['objectReference']);
				}
				if (is_object($plugin)) {
					if ($plugin->main($this)) {
						$this->registeredPlugins[$pluginId] = $plugin;
						// Override buttons from previously registered plugins
						$pluginButtons = GeneralUtility::trimExplode(',', $plugin->getPluginButtons(), TRUE);
						foreach ($this->pluginButton as $previousPluginId => $buttonList) {
							$this->pluginButton[$previousPluginId] = implode(',', array_diff(GeneralUtility::trimExplode(',', $this->pluginButton[$previousPluginId], TRUE), $pluginButtons));
						}
						$this->pluginButton[$pluginId] = $plugin->getPluginButtons();
						$pluginLabels = GeneralUtility::trimExplode(',', $plugin->getPluginLabels(), TRUE);
						foreach ($this->pluginLabel as $previousPluginId => $labelList) {
							$this->pluginLabel[$previousPluginId] = implode(',', array_diff(GeneralUtility::trimExplode(',', $this->pluginLabel[$previousPluginId], TRUE), $pluginLabels));
						}
						$this->pluginLabel[$pluginId] = $plugin->getPluginLabels();
						$this->pluginEnabledArray[] = $pluginId;
					}
				}
			}
		}
		// Process overrides
		$hidePlugins = array();
		foreach ($this->registeredPlugins as $pluginId => $plugin) {
			if ($plugin->addsButtons() && !$this->pluginButton[$pluginId]) {
				$hidePlugins[] = $pluginId;
			}
		}
		$this->pluginEnabledArray = array_unique(array_diff($this->pluginEnabledArray, $hidePlugins));
	}

	/**
	 * Set the toolbar config (only in this PHP-Object, not in JS):
	 */
	public function setToolbar() {
		if ($this->client['browser'] == 'msie' || $this->client['browser'] == 'opera') {
			$this->thisConfig['keepButtonGroupTogether'] = 0;
		}
		$this->defaultToolbarOrder = 'bar, blockstylelabel, blockstyle, textstylelabel, textstyle, linebreak,
			bar, formattext, bold,  strong, italic, emphasis, big, small, insertedtext, deletedtext, citation, code, definition, keyboard, monospaced, quotation, sample, variable, bidioverride, strikethrough, subscript, superscript, underline, span,
			bar, fontstyle, fontsize, bar, formatblock, insertparagraphbefore, insertparagraphafter, blockquote, line,
			bar, left, center, right, justifyfull,
			bar, orderedlist, unorderedlist, definitionlist, definitionitem, outdent, indent,
			bar, language, showlanguagemarks,lefttoright, righttoleft,
			bar, textcolor, bgcolor, textindicator,
			bar, editelement, showmicrodata,
			bar, image, emoticon, insertcharacter, insertsofthyphen, abbreviation, user,
			bar, link, unlink,
			bar, table,' . ($this->thisConfig['hideTableOperationsInToolbar'] && is_array($this->thisConfig['buttons.']) && is_array($this->thisConfig['buttons.']['toggleborders.']) && $this->thisConfig['buttons.']['toggleborders.']['keepInToolbar'] ? ' toggleborders,' : '') . '
			bar, findreplace, spellcheck,
			bar, chMode, inserttag, removeformat, bar, copy, cut, paste, pastetoggle, pastebehaviour, bar, undo, redo, bar, showhelp, about, linebreak,
			' . ($this->thisConfig['hideTableOperationsInToolbar'] ? '' : 'bar, toggleborders,') . ' bar, tableproperties, tablerestyle, bar, rowproperties, rowinsertabove, rowinsertunder, rowdelete, rowsplit, bar,
			columnproperties, columninsertbefore, columninsertafter, columndelete, columnsplit, bar,
			cellproperties, cellinsertbefore, cellinsertafter, celldelete, cellsplit, cellmerge';
		// Additional buttons from registered plugins
		foreach ($this->registeredPlugins as $pluginId => $plugin) {
			if ($this->isPluginEnabled($pluginId)) {
				$this->defaultToolbarOrder = $plugin->addButtonsToToolbar();
			}
		}
		$toolbarOrder = $this->thisConfig['toolbarOrder'] ?: $this->defaultToolbarOrder;
		// Getting rid of undefined buttons
		$this->toolbarOrderArray = array_intersect(GeneralUtility::trimExplode(',', $toolbarOrder, TRUE), GeneralUtility::trimExplode(',', $this->defaultToolbarOrder, TRUE));
		$toolbarOrder = array_unique(array_values($this->toolbarOrderArray));
		// Fetching specConf for field from backend
		$pList = is_array($this->specConf['richtext']['parameters']) ? implode(',', $this->specConf['richtext']['parameters']) : '';
		if ($pList != '*') {
			// If not all
			$show = is_array($this->specConf['richtext']['parameters']) ? $this->specConf['richtext']['parameters'] : array();
			if ($this->thisConfig['showButtons']) {
				if (!GeneralUtility::inList($this->thisConfig['showButtons'], '*')) {
					$show = array_unique(array_merge($show, GeneralUtility::trimExplode(',', $this->thisConfig['showButtons'], TRUE)));
				} else {
					$show = array_unique(array_merge($show, $toolbarOrder));
				}
			}
			if (is_array($this->thisConfig['showButtons.'])) {
				foreach ($this->thisConfig['showButtons.'] as $buttonId => $value) {
					if ($value) {
						$show[] = $buttonId;
					}
				}
				$show = array_unique($show);
			}
		} else {
			$show = $toolbarOrder;
		}
		// Resticting to RTEkeyList for backend user
		if (is_object($GLOBALS['BE_USER'])) {
			$RTEkeyList = isset($GLOBALS['BE_USER']->userTS['options.']['RTEkeyList']) ? $GLOBALS['BE_USER']->userTS['options.']['RTEkeyList'] : '*';
			if ($RTEkeyList != '*') {
				// If not all
				$show = array_intersect($show, GeneralUtility::trimExplode(',', $RTEkeyList, TRUE));
			}
		}
		// Hiding buttons of disabled plugins
		$hideButtons = array('space', 'bar', 'linebreak');
		foreach ($this->pluginButton as $pluginId => $buttonList) {
			if (!$this->isPluginEnabled($pluginId)) {
				$buttonArray = GeneralUtility::trimExplode(',', $buttonList, TRUE);
				foreach ($buttonArray as $button) {
					$hideButtons[] = $button;
				}
			}
		}
		// Hiding labels of disabled plugins
		foreach ($this->pluginLabel as $pluginId => $label) {
			if (!$this->isPluginEnabled($pluginId)) {
				$hideButtons[] = $label;
			}
		}
		// Hiding buttons
		$show = array_diff($show, $this->conf_toolbar_hide, GeneralUtility::trimExplode(',', $this->thisConfig['hideButtons'], TRUE));
		// Apply toolbar constraints from registered plugins
		foreach ($this->registeredPlugins as $pluginId => $plugin) {
			if ($this->isPluginEnabled($pluginId) && method_exists($plugin, 'applyToolbarConstraints')) {
				$show = $plugin->applyToolbarConstraints($show);
			}
		}
		// Getting rid of the buttons for which we have no position
		$show = array_intersect($show, $toolbarOrder);
		$this->toolbar = $show;
	}

	/**
	 * Disable some plugins
	 */
	public function setPlugins() {
		// Disabling a plugin that adds buttons if none of its buttons is in the toolbar
		$hidePlugins = array();
		foreach ($this->pluginButton as $pluginId => $buttonList) {
			if ($this->registeredPlugins[$pluginId]->addsButtons()) {
				$showPlugin = FALSE;
				$buttonArray = GeneralUtility::trimExplode(',', $buttonList, TRUE);
				foreach ($buttonArray as $button) {
					if (in_array($button, $this->toolbar)) {
						$showPlugin = TRUE;
					}
				}
				if (!$showPlugin) {
					$hidePlugins[] = $pluginId;
				}
			}
		}
		$this->pluginEnabledArray = array_diff($this->pluginEnabledArray, $hidePlugins);
		// Hiding labels of disabled plugins
		$hideLabels = array();
		foreach ($this->pluginLabel as $pluginId => $label) {
			if (!$this->isPluginEnabled($pluginId)) {
				$hideLabels[] = $label;
			}
		}
		$this->toolbar = array_diff($this->toolbar, $hideLabels);
		// Adding plugins declared as prerequisites by enabled plugins
		$requiredPlugins = array();
		foreach ($this->registeredPlugins as $pluginId => $plugin) {
			if ($this->isPluginEnabled($pluginId)) {
				$requiredPlugins = array_merge($requiredPlugins, GeneralUtility::trimExplode(',', $plugin->getRequiredPlugins(), TRUE));
			}
		}
		$requiredPlugins = array_unique($requiredPlugins);
		foreach ($requiredPlugins as $pluginId) {
			if (is_object($this->registeredPlugins[$pluginId]) && !$this->isPluginEnabled($pluginId)) {
				$this->pluginEnabledArray[] = $pluginId;
			}
		}
		$this->pluginEnabledArray = array_unique($this->pluginEnabledArray);
		// Completing the toolbar conversion array for htmlArea
		foreach ($this->registeredPlugins as $pluginId => $plugin) {
			if ($this->isPluginEnabled($pluginId)) {
				$this->convertToolbarForHtmlAreaArray = array_unique(array_merge($this->convertToolbarForHtmlAreaArray, $plugin->getConvertToolbarForHtmlAreaArray()));
			}
		}
	}

	/**
	 * Convert the TYPO3 names of buttons into the names for htmlArea RTE
	 *
	 * @param 	string	buttonname (typo3-name)
	 * @return 	string	buttonname (htmlarea-name)
	 */
	public function convertToolbarForHTMLArea($button) {
		return $this->convertToolbarForHtmlAreaArray[$button];
	}

	/**
	 * Add RTE main scripts and plugin scripts
	 *
	 * @param string $RTEcounter:  The index number of the current RTE editing area within the form.
	 * @return void
	 */
	protected function addRteJsFiles($RTEcounter) {
		// Component files. Order is important.
		$components = array(
			'Util/Wrap.open',
			'NameSpace/NameSpace',
			'UserAgent/UserAgent',
			'Util/Util',
			'Util/Color',
			'Util/Resizable',
			'Util/String',
			'Util/Tips',
			'Util/TYPO3',
			'Ajax/Ajax',
			'DOM/DOM',
			'Event/Event',
			'Event/KeyMap',
			'CSS/Parser',
			'DOM/BookMark',
			'DOM/Node',
			'DOM/Selection',
			'DOM/Walker',
			'Configuration/Config',
			'Toolbar/Button',
			'Toolbar/ToolbarText',
			'Toolbar/Select',
			'Extjs/ColorPalette',
			'Extjs/ux/ColorMenu',
			'Extjs/ux/ColorPaletteField',
			'LoremIpsum',
			'Plugin/Plugin'
		);
		$components2 = array(
			'Editor/Toolbar',
			'Editor/Iframe',
			'Editor/TextAreaContainer',
			'Editor/StatusBar',
			'Editor/Framework',
			'Editor/Editor',
			'HTMLArea'
		);
		foreach ($components as $component) {
			$this->pageRenderer->addJsFile($this->getFullFileName('EXT:' . $this->ID . '/Resources/Public/JavaScript/HTMLArea/' . $component . '.js'));
		}
		foreach ($this->pluginEnabledCumulativeArray[$RTEcounter] as $pluginId) {
			$extensionKey = is_object($this->registeredPlugins[$pluginId]) ? $this->registeredPlugins[$pluginId]->getExtensionKey() : $this->ID;
			$fileName = 'EXT:' . $extensionKey . '/Resources/Public/JavaScript/Plugins/' . $pluginId . '.js';
			$absolutePath = GeneralUtility::getFileAbsFileName($fileName);
			if (file_exists($absolutePath)) {
				$this->pageRenderer->addJsFile($this->getFullFileName($fileName));
			} else {
				// Backward compatibility
				$pluginName = strtolower(preg_replace('/([a-z])([A-Z])([a-z])/', '$1-$2$3', $pluginId));
				$fileName = 'EXT:' . $extensionKey . '/htmlarea/plugins/' . $pluginId . '/' . $pluginName . '.js';
				$absolutePath = GeneralUtility::getFileAbsFileName($fileName);
				if (file_exists($absolutePath)) {
					$this->pageRenderer->addJsFile($this->getFullFileName($fileName));
				}
			}
		}
		foreach ($components2 as $component) {
			$this->pageRenderer->addJsFile($this->getFullFileName('EXT:' . $this->ID . '/Resources/Public/JavaScript/HTMLArea/' . $component . '.js'));
		}
		$this->pageRenderer->addJsFile($this->getFullFileName('EXT:' . $this->ID . '/Resources/Public/JavaScript/HTMLArea/Util/Wrap.close.js'));
	}

	/**
	 * Return RTE initialization inline JavaScript code
	 *
	 * @return string RTE initialization inline JavaScript code
	 */
	protected function getRteInitJsCode() {
		return 'require(["TYPO3/CMS/Rtehtmlarea/HTMLArea/HTMLArea"], function (HTMLArea) {
			if (typeof RTEarea === "undefined") {
				RTEarea = new Object();
				RTEarea[0] = new Object();
				RTEarea[0].version = "' . $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->ID]['version'] . '";
				RTEarea[0].editorUrl = "' . $this->extHttpPath . '";
				RTEarea[0].editorCSS = "' . GeneralUtility::createVersionNumberedFilename($this->editorCSS) . '";
				RTEarea[0].editorSkin = "' . dirname($this->editorCSS) . '/";
				RTEarea[0].editedContentCSS = "' . GeneralUtility::createVersionNumberedFilename($this->editedContentCSS) . '";
				RTEarea[0].hostUrl = "' . $this->hostURL . '";
				RTEarea.init = function() {
					if (typeof HTMLArea === "undefined" || !Ext.isReady) {
						window.setTimeout(function () {
							RTEarea.init();
						}, 10);
					} else {
						Ext.QuickTips.init();
						HTMLArea.init();
					}
				};
				RTEarea.initEditor = function(editorNumber) {
					if (typeof HTMLArea === "undefined" || !HTMLArea.isReady) {
						window.setTimeout(function () {
							RTEarea.initEditor(editorNumber);
						}, 40);
					} else {
						HTMLArea.initEditor(editorNumber);
					}
				};
			}
			RTEarea.init();
		});';
	}

	/**
	 * Return the Javascript code for configuring the RTE
	 *
	 * @param int $RTEcounter: The index number of the current RTE editing area within the form.
	 * @param string $table: The table that includes this RTE (optional, necessary for IRRE).
	 * @param string $uid: The uid of that table that includes this RTE (optional, necessary for IRRE).
	 * @param string $field: The field of that record that includes this RTE (optional).
	 * @param string $textAreaId ID of the textarea, to have a unigue number for the editor
	 * @return string the Javascript code for configuring the RTE
	 */
	public function registerRTEinJS($RTEcounter, $table = '', $uid = '', $field = '', $textAreaId = '') {
		$configureRTEInJavascriptString = '
			if (typeof configureEditorInstance === "undefined") {
				configureEditorInstance = new Object();
			}
			configureEditorInstance["' . $textAreaId . '"] = function() {
				if (typeof RTEarea === "undefined" || typeof HTMLArea === "undefined") {
					window.setTimeout("configureEditorInstance[\'' . $textAreaId . '\']();", 40);
				} else {
			editornumber = "' . $textAreaId . '";
			RTEarea[editornumber] = new Object();
			RTEarea[editornumber].RTEtsConfigParams = "&RTEtsConfigParams=' . rawurlencode($this->RTEtsConfigParams()) . '";
			RTEarea[editornumber].number = editornumber;
			RTEarea[editornumber].deleted = false;
			RTEarea[editornumber].textAreaId = "' . $textAreaId . '";
			RTEarea[editornumber].id = "RTEarea" + editornumber;
			RTEarea[editornumber].RTEWidthOverride = "' . (is_object($GLOBALS['BE_USER']) && isset($GLOBALS['BE_USER']->uc['rteWidth']) && trim($GLOBALS['BE_USER']->uc['rteWidth']) ? trim($GLOBALS['BE_USER']->uc['rteWidth']) : trim($this->thisConfig['RTEWidthOverride'])) . '";
			RTEarea[editornumber].RTEHeightOverride = "' . (is_object($GLOBALS['BE_USER']) && isset($GLOBALS['BE_USER']->uc['rteHeight']) && (int)$GLOBALS['BE_USER']->uc['rteHeight'] ? (int)$GLOBALS['BE_USER']->uc['rteHeight'] : (int)$this->thisConfig['RTEHeightOverride']) . '";
			RTEarea[editornumber].resizable = ' . (is_object($GLOBALS['BE_USER']) && isset($GLOBALS['BE_USER']->uc['rteResize']) && $GLOBALS['BE_USER']->uc['rteResize'] ? 'true' : (trim($this->thisConfig['rteResize']) ? 'true' : 'false')) . ';
			RTEarea[editornumber].maxHeight = "' . (is_object($GLOBALS['BE_USER']) && isset($GLOBALS['BE_USER']->uc['rteMaxHeight']) && (int)$GLOBALS['BE_USER']->uc['rteMaxHeight'] ? trim($GLOBALS['BE_USER']->uc['rteMaxHeight']) : ((int)$this->thisConfig['rteMaxHeight'] ?: '2000')) . '";
			RTEarea[editornumber].fullScreen = ' . ($this->fullScreen ? 'true' : 'false') . ';
			RTEarea[editornumber].showStatusBar = ' . (trim($this->thisConfig['showStatusBar']) ? 'true' : 'false') . ';
			RTEarea[editornumber].enableWordClean = ' . (trim($this->thisConfig['enableWordClean']) ? 'true' : 'false') . ';
			RTEarea[editornumber].htmlRemoveComments = ' . (trim($this->thisConfig['removeComments']) ? 'true' : 'false') . ';
			RTEarea[editornumber].disableEnterParagraphs = ' . (trim($this->thisConfig['disableEnterParagraphs']) ? 'true' : 'false') . ';
			RTEarea[editornumber].disableObjectResizing = ' . (trim($this->thisConfig['disableObjectResizing']) ? 'true' : 'false') . ';
			RTEarea[editornumber].removeTrailingBR = ' . (trim($this->thisConfig['removeTrailingBR']) ? 'true' : 'false') . ';
			RTEarea[editornumber].useCSS = ' . (trim($this->thisConfig['useCSS']) ? 'true' : 'false') . ';
			RTEarea[editornumber].keepButtonGroupTogether = ' . (trim($this->thisConfig['keepButtonGroupTogether']) ? 'true' : 'false') . ';
			RTEarea[editornumber].disablePCexamples = ' . (trim($this->thisConfig['disablePCexamples']) ? 'true' : 'false') . ';
			RTEarea[editornumber].showTagFreeClasses = ' . (trim($this->thisConfig['showTagFreeClasses']) ? 'true' : 'false') . ';
			RTEarea[editornumber].useHTTPS = ' . (trim(stristr($this->siteURL, 'https')) || $this->thisConfig['forceHTTPS'] ? 'true' : 'false') . ';
			RTEarea[editornumber].tceformsNested = ' . (is_object($this->TCEform) && method_exists($this->TCEform, 'getDynNestedStack') ? $this->TCEform->getDynNestedStack(TRUE) : '[]') . ';
			RTEarea[editornumber].dialogueWindows = new Object();';
		if (isset($this->thisConfig['dialogueWindows.']['defaultPositionFromTop'])) {
			$configureRTEInJavascriptString .= '
			RTEarea[editornumber].dialogueWindows.positionFromTop = ' . (int)$this->thisConfig['dialogueWindows.']['defaultPositionFromTop'] . ';';
		}
		if (isset($this->thisConfig['dialogueWindows.']['defaultPositionFromLeft'])) {
			$configureRTEInJavascriptString .= '
			RTEarea[editornumber].dialogueWindows.positionFromLeft = ' . (int)$this->thisConfig['dialogueWindows.']['defaultPositionFromLeft'] . ';';
		}
		// The following properties apply only to the backend
		if (!$this->is_FE()) {
			$configureRTEInJavascriptString .= '
			RTEarea[editornumber].sys_language_content = "' . $this->contentLanguageUid . '";
			RTEarea[editornumber].typo3ContentLanguage = "' . $this->contentTypo3Language . '";
			RTEarea[editornumber].typo3ContentCharset = "' . $this->contentCharset . '";
			RTEarea[editornumber].userUid = "' . $this->userUid . '";';
		}
		// Setting the plugin flags
		$configureRTEInJavascriptString .= '
			RTEarea[editornumber].plugin = new Object();
			RTEarea[editornumber].pathToPluginDirectory = new Object();';
		foreach ($this->pluginEnabledArray as $pluginId) {
			$configureRTEInJavascriptString .= '
			RTEarea[editornumber].plugin.' . $pluginId . ' = true;';
			if (is_object($this->registeredPlugins[$pluginId])) {
				$pathToPluginDirectory = $this->registeredPlugins[$pluginId]->getPathToPluginDirectory();
				if ($pathToPluginDirectory) {
					$configureRTEInJavascriptString .= '
			RTEarea[editornumber].pathToPluginDirectory.' . $pluginId . ' = "' . $pathToPluginDirectory . '";';
				}
			}
		}
		// Setting the buttons configuration
		$configureRTEInJavascriptString .= '
			RTEarea[editornumber].buttons = new Object();';
		if (is_array($this->thisConfig['buttons.'])) {
			foreach ($this->thisConfig['buttons.'] as $buttonIndex => $conf) {
				$button = substr($buttonIndex, 0, -1);
				if (is_array($conf)) {
					$configureRTEInJavascriptString .= '
			RTEarea[editornumber].buttons.' . $button . ' = ' . $this->buildNestedJSArray($conf) . ';';
				}
			}
		}
		// Setting the list of tags to be removed if specified in the RTE config
		if (trim($this->thisConfig['removeTags'])) {
			$configureRTEInJavascriptString .= '
			RTEarea[editornumber].htmlRemoveTags = /^(' . implode('|', GeneralUtility::trimExplode(',', $this->thisConfig['removeTags'], TRUE)) . ')$/i;';
		}
		// Setting the list of tags to be removed with their contents if specified in the RTE config
		if (trim($this->thisConfig['removeTagsAndContents'])) {
			$configureRTEInJavascriptString .= '
			RTEarea[editornumber].htmlRemoveTagsAndContents = /^(' . implode('|', GeneralUtility::trimExplode(',', $this->thisConfig['removeTagsAndContents'], TRUE)) . ')$/i;';
		}
		// Setting array of custom tags if specified in the RTE config
		if (!empty($this->thisConfig['customTags'])) {
			$customTags = GeneralUtility::trimExplode(',', $this->thisConfig['customTags'], TRUE);
			if (!empty($customTags)) {
				$configureRTEInJavascriptString .= '
				RTEarea[editornumber].customTags= ' . json_encode($customTags) . ';';
			}
		}
		// Setting array of content css files if specified in the RTE config
		$versionNumberedFileNames = array();
		$contentCssFileNames = $this->getContentCssFileNames();
		foreach ($contentCssFileNames as $contentCssFileName) {
			$versionNumberedFileNames[] = GeneralUtility::createVersionNumberedFilename($contentCssFileName);
		}
		$configureRTEInJavascriptString .= '
			RTEarea[editornumber].pageStyle = ["' . implode('","', $versionNumberedFileNames) . '"];';
		// Process classes configuration
		$classesConfigurationRequired = FALSE;
		foreach ($this->registeredPlugins as $pluginId => $plugin) {
			if ($this->isPluginEnabled($pluginId)) {
				$classesConfigurationRequired = $classesConfigurationRequired || $plugin->requiresClassesConfiguration();
			}
		}
		if ($classesConfigurationRequired) {
			$configureRTEInJavascriptString .= $this->buildJSClassesConfig($RTEcounter);
		}
		// Add Javascript configuration for registered plugins
		foreach ($this->registeredPlugins as $pluginId => $plugin) {
			if ($this->isPluginEnabled($pluginId)) {
				$configureRTEInJavascriptString .= $plugin->buildJavascriptConfiguration('editornumber');
			}
		}
		// Avoid premature reference to HTMLArea when being initially loaded by IRRE Ajax call
		$configureRTEInJavascriptString .= '
			RTEarea[editornumber].toolbar = ' . $this->getJSToolbarArray() . ';
			RTEarea[editornumber].convertButtonId = ' . json_encode(array_flip($this->convertToolbarForHtmlAreaArray)) . ';
			RTEarea.initEditor(editornumber);
				}
			};
			configureEditorInstance["' . $textAreaId . '"]();';
		return $configureRTEInJavascriptString;
	}

	/**
	 * Return TRUE, if the plugin can be loaded
	 *
	 * @param string $pluginId: The identification string of the plugin
	 * @return bool TRUE if the plugin can be loaded
	 */
	public function isPluginEnabled($pluginId) {
		return in_array($pluginId, $this->pluginEnabledArray);
	}

	/**
	 * Return Javascript configuration of classes
	 *
	 * @param int $RTEcounter: The index number of the current RTE editing area within the form.
	 * @return string Javascript configuration of classes
	 */
	public function buildJSClassesConfig($RTEcounter) {
		// Include JS arrays of configured classes
		$configureRTEInJavascriptString = '
			RTEarea[editornumber].classesUrl = "' . ($this->is_FE() && $GLOBALS['TSFE']->absRefPrefix ? $GLOBALS['TSFE']->absRefPrefix : '') . $this->writeTemporaryFile('', ('classes_' . $this->language), 'js', $this->buildJSClassesArray(), TRUE) . '";';
		return $configureRTEInJavascriptString;
	}

	/**
	 * Return JS arrays of classes configuration
	 *
	 * @return string	JS classes arrays
	 */
	public function buildJSClassesArray() {
		if ($this->is_FE()) {
			$RTEProperties = $this->RTEsetup;
		} else {
			$RTEProperties = $this->RTEsetup['properties'];
		}
		// Declare sub-arrays
		$classesArray = array(
			'labels' => array(),
			'values' => array(),
			'noShow' => array(),
			'alternating' => array(),
			'counting' => array(),
			'selectable' => array(),
			'requires' => array(),
			'requiredBy' => array(),
			'XOR' => array()
		);
		$JSClassesArray = '';
		// Scanning the list of classes if specified in the RTE config
		if (is_array($RTEProperties['classes.'])) {
			foreach ($RTEProperties['classes.'] as $className => $conf) {
				$className = rtrim($className, '.');
				$classesArray['labels'][$className] = trim($conf['name']) ? $this->getPageConfigLabel($conf['name'], FALSE) : '';
				$classesArray['values'][$className] = str_replace('\\\'', '\'', $conf['value']);
				if (isset($conf['noShow'])) {
					$classesArray['noShow'][$className] = $conf['noShow'];
				}
				if (is_array($conf['alternating.'])) {
					$classesArray['alternating'][$className] = $conf['alternating.'];
				}
				if (is_array($conf['counting.'])) {
					$classesArray['counting'][$className] = $conf['counting.'];
				}
				if (isset($conf['selectable'])) {
					$classesArray['selectable'][$className] = $conf['selectable'];
				}
				if (isset($conf['requires'])) {
					$classesArray['requires'][$className] = explode(',', GeneralUtility::rmFromList($className, $this->cleanList($conf['requires'])));
				}
			}
			// Remove circularities from classes dependencies
			$requiringClasses = array_keys($classesArray['requires']);
			foreach ($requiringClasses as $requiringClass) {
				if ($this->hasCircularDependency($classesArray, $requiringClass, $requiringClass)) {
					unset($classesArray['requires'][$requiringClass]);
				}
			}
			// Reverse relationship for the dependency checks when removing styles
			$requiringClasses = array_keys($classesArray['requires']);
			foreach ($requiringClasses as $className) {
				foreach ($classesArray['requires'][$className] as $requiredClass) {
					if (!is_array($classesArray['requiredBy'][$requiredClass])) {
						$classesArray['requiredBy'][$requiredClass] = array();
					}
					if (!in_array($className, $classesArray['requiredBy'][$requiredClass])) {
						$classesArray['requiredBy'][$requiredClass][] = $className;
					}
				}
			}
		}
		// Scanning the list of sets of mutually exclusives classes if specified in the RTE config
		if (is_array($RTEProperties['mutuallyExclusiveClasses.'])) {
			foreach ($RTEProperties['mutuallyExclusiveClasses.'] as $listName => $conf) {
				$classSet = GeneralUtility::trimExplode(',', $conf, TRUE);
				$classList = implode(',', $classSet);
				foreach ($classSet as $className) {
					$classesArray['XOR'][$className] = '/^(' . implode('|', GeneralUtility::trimExplode(',', GeneralUtility::rmFromList($className, $classList), TRUE)) . ')$/';
				}
			}
		}
		foreach ($classesArray as $key => $subArray) {
			$JSClassesArray .= 'HTMLArea.classes' . ucfirst($key) . ' = ' . $this->buildNestedJSArray($subArray) . ';' . LF;
		}
		return $JSClassesArray;
	}

	/**
	 * Check for possible circularity in classes dependencies
	 *
	 * @param array $classesArray: reference to the array of classes dependencies
	 * @param string $requiringClass: class requiring at some iteration level from the initial requiring class
	 * @param string $initialClass: initial class from which a circular relationship is being searched
	 * @param int $recursionLevel: depth of recursive call
	 * @return boolean TRUE, if a circular relationship is found
	 */
	protected function hasCircularDependency(&$classesArray, $requiringClass, $initialClass, $recursionLevel = 0) {
		if (is_array($classesArray['requires'][$requiringClass])) {
			if (in_array($initialClass, $classesArray['requires'][$requiringClass])) {
				return TRUE;
			} else {
				if ($recursionLevel++ < 20) {
					foreach ($classesArray['requires'][$requiringClass] as $requiringClass2) {
						if ($this->hasCircularDependency($classesArray, $requiringClass2, $initialClass, $recursionLevel)) {
							return TRUE;
						}
					}
				}
				return FALSE;
			}
		} else {
			return FALSE;
		}
	}

	/**
	 * Translate Page TS Config array in JS nested array definition
	 * Replace 0 values with false
	 * Unquote regular expression values
	 * Replace empty arrays with empty objects
	 *
	 * @param array $conf: Page TSConfig configuration array
	 * @return string nested JS array definition
	 */
	public function buildNestedJSArray($conf) {
		$convertedConf = GeneralUtility::removeDotsFromTS($conf);
		return str_replace(array(':"0"', ':"\\/^(', ')$\\/i"', ':"\\/^(', ')$\\/"', '[]'), array(':false', ':/^(', ')$/i', ':/^(', ')$/', '{}'), json_encode($convertedConf));
	}

	/**
	 * Return a Javascript localization array for htmlArea RTE
	 *
	 * @return string Javascript localization array
	 */
	public function buildJSMainLangArray() {
		$JSLanguageArray = 'HTMLArea.I18N = new Object();' . LF;
		$labelsArray = array('tooltips' => array(), 'msg' => array(), 'dialogs' => array());
		foreach ($labelsArray as $labels => $subArray) {
			$LOCAL_LANG = GeneralUtility::readLLfile('EXT:' . $this->ID . '/htmlarea/locallang_' . $labels . '.xlf', $this->language, 'utf-8');
			if (!empty($LOCAL_LANG[$this->language])) {
				$mergedLocalLang = $LOCAL_LANG['default'];
				\TYPO3\CMS\Core\Utility\ArrayUtility::mergeRecursiveWithOverrule($mergedLocalLang, $LOCAL_LANG[$this->language], TRUE, FALSE);
				$LOCAL_LANG[$this->language] = $mergedLocalLang;
			} else {
				$LOCAL_LANG[$this->language] = $LOCAL_LANG['default'];
			}
			$labelsArray[$labels] = $LOCAL_LANG[$this->language];
		}
		$JSLanguageArray .= 'HTMLArea.I18N = ' . json_encode($labelsArray) . ';' . LF;
		return $JSLanguageArray;
	}

	/**
	 * Writes contents in a file in typo3temp/rtehtmlarea directory and returns the file name
	 *
	 * @param string $sourceFileName: The name of the file from which the contents should be extracted
	 * @param string $label: A label to insert at the beginning of the name of the file
	 * @param string $fileExtension: The file extension of the file, defaulting to 'js'
	 * @param string $contents: The contents to write into the file if no $sourceFileName is provided
	 * @param bool $concatenate Not used anymore
	 * @return string The name of the file writtten to typo3temp/rtehtmlarea
	 */
	public function writeTemporaryFile($sourceFileName = '', $label, $fileExtension = 'js', $contents = '', $concatenate = FALSE) {
		if ($sourceFileName) {
			$output = '';
			$source = GeneralUtility::getFileAbsFileName($sourceFileName);
			$output = file_get_contents($source);
		} else {
			$output = $contents;
		}
		$relativeFilename = 'typo3temp/' . $this->ID . '_' . str_replace('-', '_', $label) . '_' . GeneralUtility::shortMD5((TYPO3_version . $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->ID]['version'] . ($sourceFileName ? $sourceFileName : $output)), 20) . '.' . $fileExtension;
		$destination = PATH_site . $relativeFilename;
		if (!file_exists($destination)) {
			$minifiedJavaScript = '';
			if ($fileExtension == 'js' && $output != '') {
				$minifiedJavaScript = GeneralUtility::minifyJavaScript($output);
			}
			$failure = GeneralUtility::writeFileToTypo3tempDir($destination, $minifiedJavaScript ? $minifiedJavaScript : $output);
			if ($failure) {
				throw new \RuntimeException($failure, 1294585668);
			}
		}
		if ($this->is_FE()) {
			$filename = $relativeFilename;
		} else {
			$filename = ($this->isFrontendEditActive() ? '' : $this->backPath . '../') . $relativeFilename;
		}
		return GeneralUtility::resolveBackPath($filename);
	}

	/**
	 * Return a file name containing the main JS language array for HTMLArea
	 *
	 * @param int $RTEcounter: The index number of the current RTE editing area within the form.
	 * @return string filename
	 */
	public function buildJSMainLangFile($RTEcounter) {
		$contents = $this->buildJSMainLangArray() . LF;
		foreach ($this->pluginEnabledCumulativeArray[$RTEcounter] as $pluginId) {
			$contents .= $this->buildJSLangArray($pluginId) . LF;
		}
		return $this->writeTemporaryFile('', $this->language . '_' . $this->OutputCharset, 'js', $contents, TRUE);
	}

	/**
	 * Return a Javascript localization array for the plugin
	 *
	 * @param string $plugin: identification string of the plugin
	 * @return string Javascript localization array
	 */
	public function buildJSLangArray($plugin) {
		$extensionKey = is_object($this->registeredPlugins[$plugin]) ? $this->registeredPlugins[$plugin]->getExtensionKey() : $this->ID;
		$LOCAL_LANG = GeneralUtility::readLLfile('EXT:' . $extensionKey . '/htmlarea/plugins/' . $plugin . '/locallang.xlf', $this->language, 'utf-8', 1);
		$JSLanguageArray = 'HTMLArea.I18N["' . $plugin . '"] = new Object();' . LF;
		if (is_array($LOCAL_LANG)) {
			if (!empty($LOCAL_LANG[$this->language])) {
				$defaultLocalLang = $LOCAL_LANG['default'];
				\TYPO3\CMS\Core\Utility\ArrayUtility::mergeRecursiveWithOverrule($defaultLocalLang, $LOCAL_LANG[$this->language], TRUE, FALSE);
				$LOCAL_LANG[$this->language] = $defaultLocalLang;
			} else {
				$LOCAL_LANG[$this->language] = $LOCAL_LANG['default'];
			}
			$JSLanguageArray .= 'HTMLArea.I18N["' . $plugin . '"] = ' . json_encode($LOCAL_LANG[$this->language]) . ';' . LF;
		}
		return $JSLanguageArray;
	}

	/**
	 * Return the JS code of the toolbar configuration for the HTMLArea editor
	 *
	 * @return string	the JS code as nested JS arrays
	 */
	protected function getJSToolbarArray() {
		// The toolbar array
		$toolbar = array();
		// The current row;  a "linebreak" ends the current row
		$row = array();
		// The current group; each group is between "bar"s; a "linebreak" ends the current group
		$group = array();
		// Process each toolbar item in the toolbar order list
		foreach ($this->toolbarOrderArray as $item) {
			switch ($item) {
				case 'linebreak':
					// Add row to toolbar if not empty
					if (!empty($group)) {
						$row[] = $group;
						$group = array();
					}
					if (!empty($row)) {
						$toolbar[] = $row;
						$row = array();
					}
					break;
				case 'bar':
					// Add group to row if not empty
					if (!empty($group)) {
						$row[] = $group;
						$group = array();
					}
					break;
				case 'space':
					if (end($group) != $this->convertToolbarForHTMLArea($item)) {
						$group[] = $this->convertToolbarForHTMLArea($item);
					}
					break;
				default:
					if (in_array($item, $this->toolbar)) {
						// Add the item to the group
						$convertedItem = $this->convertToolbarForHTMLArea($item);
						if ($convertedItem) {
							$group[] = $convertedItem;
						}
					}
			}
		}
		// Add the last group and last line, if not empty
		if (!empty($group)) {
			$row[] = $group;
		}
		if (!empty($row)) {
			$toolbar[] = $row;
		}
		return json_encode($toolbar);
	}

	/**
	 * Localize a string using the language of the content element rather than the language of the BE interface
	 *
	 * @param string string: the label to be localized
	 * @return string Localized string.
	 */
	public function getLLContent($string) {
		return GeneralUtility::quoteJSvalue($this->contentLanguageService->sL($string));
	}

	public function getPageConfigLabel($string, $JScharCode = 1) {
		if ($this->is_FE()) {
			if (substr($string, 0, 4) !== 'LLL:') {
				// A pure string coming from Page TSConfig must be in utf-8
				$label = $GLOBALS['TSFE']->csConvObj->conv($GLOBALS['TSFE']->sL(trim($string)), 'utf-8', $this->OutputCharset);
			} else {
				$label = $GLOBALS['TSFE']->csConvObj->conv($GLOBALS['TSFE']->sL(trim($string)), $this->charset, $this->OutputCharset);
			}
			$label = str_replace('"', '\\"', str_replace('\\\'', '\'', $label));
			$label = $JScharCode ? $this->feJScharCode($label) : $label;
		} else {
			if (substr($string, 0, 4) !== 'LLL:') {
				$label = $string;
			} else {
				$label = $GLOBALS['LANG']->sL(trim($string));
			}
			$label = str_replace('"', '\\"', str_replace('\\\'', '\'', $label));
			$label = $JScharCode ? GeneralUtility::quoteJSvalue($label) : $label;
		}
		return $label;
	}

	/**
	 * JavaScript char code
	 *
	 * @param string $str
	 * @return string
	 */
	public function feJScharCode($str) {
		// Convert string to UTF-8:
		if ($this->OutputCharset != 'utf-8') {
			$str = $GLOBALS['TSFE']->csConvObj->utf8_encode($str, $this->OutputCharset);
		}
		// Convert the UTF-8 string into a 'JavaScript-safe' encoded string:
		return GeneralUtility::quoteJSvalue($str);
	}

	/**
	 * Make a file name relative to the PATH_site or to the PATH_typo3
	 *
	 * @param string $filename: a file name of the form EXT:.... or relative to the PATH_site
	 * @return string the file name relative to the PATH_site if in frontend or relative to the PATH_typo3 if in backend
	 */
	public function getFullFileName($filename) {
		if (substr($filename, 0, 4) == 'EXT:') {
			// extension
			list($extKey, $local) = explode('/', substr($filename, 4), 2);
			$newFilename = '';
			if ((string)$extKey !== '' && ExtensionManagementUtility::isLoaded($extKey) && (string)$local !== '') {
				$newFilename = ($this->is_FE() || $this->isFrontendEditActive() ? ExtensionManagementUtility::siteRelPath($extKey) : $this->backPath . ExtensionManagementUtility::extRelPath($extKey)) . $local;
			}
		} else {
			$path = ($this->is_FE() || $this->isFrontendEditActive() ? '' : $this->backPath . '../');
			$newFilename = $path . ($filename[0] === '/' ? substr($filename, 1) : $filename);
		}
		return GeneralUtility::resolveBackPath($newFilename);
	}

	/**
	 * Return the Javascript code for copying the HTML code from the editor into the hidden input field.
	 * This is for submit function of the form.
	 *
	 * @param int $RTEcounter: The index number of the current RTE editing area within the form.
	 * @param string $formName: the name of the form
	 * @param string $textareaId: the id of the textarea
	 * @param string $textareaName: the name of the textarea
	 * @return string Javascript code
	 */
	public function setSaveRTE($RTEcounter, $formName, $textareaId, $textareaName) {
		return 'if (RTEarea["' . $textareaId . '"]) { document.' . $formName . '["' . $textareaName . '"].value = RTEarea["' . $textareaId . '"].editor.getHTML(); } else { OK = 0; };';
	}

	/**
	 * Return the Javascript code for copying the HTML code from the editor into the hidden input field.
	 * This is for submit function of the form.
	 *
	 * @param int $RTEcounter: The index number of the current RTE editing area within the form.
	 * @param string $formName: the name of the form
	 * @param string $textareaId: the id of the textarea
	 * @return string Javascript code
	 */
	public function setDeleteRTE($RTEcounter, $formName, $textareaId) {
		return 'if (RTEarea["' . $textareaId . '"]) { RTEarea["' . $textareaId . '"].deleted = true;}';
	}

	/**
	 * Return TRUE if we are in the FE, but not in the FE editing feature of BE.
	 *
	 * @return bool
	 */
	public function is_FE() {
		return is_object($GLOBALS['TSFE']) && !$this->isFrontendEditActive() && TYPO3_MODE == 'FE';
	}

	/**
	 * Checks whether frontend editing is active.
	 *
	 * @return 		boolean
	 */
	public function isFrontendEditActive() {
		return is_object($GLOBALS['TSFE']) && $GLOBALS['TSFE']->beUserLogin && $GLOBALS['BE_USER']->frontendEdit instanceof \TYPO3\CMS\Core\FrontendEditing\FrontendEditingController;
	}

	/**
	 * Client Browser Information
	 *
	 * @param string $userAgent: The useragent string, \TYPO3\CMS\Core\Utility\GeneralUtility::getIndpEnv('HTTP_USER_AGENT')
	 * @return array Contains keys "useragent", "browser", "version", "system
	 */
	public function clientInfo($userAgent = '') {
		if (!$userAgent) {
			$userAgent = GeneralUtility::getIndpEnv('HTTP_USER_AGENT');
		}
		$browserInfo = \TYPO3\CMS\Core\Utility\ClientUtility::getBrowserInfo($userAgent);
		// Known engines: order is not irrelevant!
		$knownEngines = array('opera', 'msie', 'gecko', 'webkit');
		if (is_array($browserInfo['all'])) {
			foreach ($knownEngines as $engine) {
				if ($browserInfo['all'][$engine]) {
					$browserInfo['browser'] = $engine;
					$browserInfo['version'] = \TYPO3\CMS\Core\Utility\ClientUtility::getVersion($browserInfo['all'][$engine]);
					break;
				}
			}
		}
		return $browserInfo;
	}

	/**
	 * Log usage of deprecated Page TS Config Property
	 *
	 * @param string $deprecatedProperty: Name of deprecated property
	 * @param string $useProperty: Name of property to use instead
	 * @param string $version: Version of TYPO3 in which the property will be removed
	 * @return void
	 */
	public function logDeprecatedProperty($deprecatedProperty, $useProperty, $version) {
		if (!$this->thisConfig['logDeprecatedProperties.']['disabled']) {
			$message = sprintf('RTE Page TSConfig property "%1$s" used on page id #%4$s is DEPRECATED and will be removed in TYPO3 %3$s. Use "%2$s" instead.', $deprecatedProperty, $useProperty, $version, $this->thePid);
			GeneralUtility::deprecationLog($message);
			if (is_object($GLOBALS['BE_USER']) && $this->thisConfig['logDeprecatedProperties.']['logAlsoToBELog']) {
				$message = sprintf($GLOBALS['LANG']->getLL('deprecatedPropertyMessage'), $deprecatedProperty, $useProperty, $version, $this->thePid);
				$GLOBALS['BE_USER']->simplelog($message, $this->ID);
			}
		}
	}

	/***************************
	 *
	 * OTHER FUNCTIONS:	(from Classic RTE)
	 *
	 ***************************/
	/**
	 * @return string
	 */
	public function RTEtsConfigParams() {
		if ($this->is_FE()) {
			return '';
		} else {
			$p = BackendUtility::getSpecConfParametersFromArray($this->specConf['rte_transform']['parameters']);
			return $this->elementParts[0] . ':' . $this->elementParts[1] . ':' . $this->elementParts[2] . ':' . $this->thePid . ':' . $this->typeVal . ':' . $this->tscPID . ':' . $p['imgpath'];
		}
	}

	/**
	 * Clean list
	 *
	 * @param string $str
	 * @return string
	 */
	public function cleanList($str) {
		if (strstr($str, '*')) {
			$str = '*';
		} else {
			$str = implode(',', array_unique(GeneralUtility::trimExplode(',', $str, TRUE)));
		}
		return $str;
	}

	/**
	 * Filter style element
	 *
	 * @param string $elValue
	 * @param string $matchList
	 * @return string
	 */
	public function filterStyleEl($elValue, $matchList) {
		$matchParts = GeneralUtility::trimExplode(',', $matchList, TRUE);
		$styleParts = explode(';', $elValue);
		$nStyle = array();
		foreach ($styleParts as $k => $p) {
			$pp = GeneralUtility::trimExplode(':', $p);
			if ($pp[0] && $pp[1]) {
				foreach ($matchParts as $el) {
					$star = substr($el, -1) == '*';
					if ($pp[0] === (string)$el || $star && GeneralUtility::isFirstPartOfStr($pp[0], substr($el, 0, -1))) {
						$nStyle[] = $pp[0] . ':' . $pp[1];
					} else {
						unset($styleParts[$k]);
					}
				}
			} else {
				unset($styleParts[$k]);
			}
		}
		return implode('; ', $nStyle);
	}
}
