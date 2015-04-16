<?php
namespace TYPO3\CMS\Rtehtmlarea\Controller;

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
 * Front end RTE based on htmlArea
 *
 * @author Stanislas Rolland <typo3(arobas)sjbr.ca>
 */
class FrontendRteController extends \TYPO3\CMS\Rtehtmlarea\RteHtmlAreaBase {

	// External:
	public $RTEWrapStyle = '';

	// Alternative style for RTE wrapper <div> tag.
	public $RTEdivStyle = '';

	// Alternative style for RTE <div> tag.
	// For the editor
	/**
	 * @var string
	 */
	public $elementId;

	/**
	 * @var array
	 */
	public $elementParts;

	/**
	 * @var int
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
	public $RTEsetup = array();

	/**
	 * @var array
	 */
	public $thisConfig = array();

	public $language;

	public $OutputCharset;

	/**
	 * @var array
	 */
	public $specConf;

	/**
	 * @var array
	 */
	public $LOCAL_LANG;

	/**
	 * @var \TYPO3\CMS\Core\Page\PageRenderer
	 */
	protected $pageRenderer;

	/**
	 * Draws the RTE as an iframe
	 *
	 * @param object $parentObject parent object
	 * @param string $table The table name
	 * @param string $field The field name
	 * @param array $row The current row from which field is being rendered
	 * @param array $PA standard content for rendering form fields from TCEforms. See TCEforms for details on this. Includes for instance the value and the form field name, java script actions and more.
	 * @param array $specConf "special" configuration - what is found at position 4 in the types configuration of a field from record, parsed into an array.
	 * @param array $thisConfig Configuration for RTEs; A mix between TSconfig and otherwise. Contains configuration for display, which buttons are enabled, additional transformation information etc.
	 * @param string $RTEtypeVal Record "type" field value.
	 * @param string $RTErelPath Relative path for images/links in RTE; this is used when the RTE edits content from static files where the path of such media has to be transformed forth and back!
	 * @param int $thePidValue PID value of record (true parent page id)
	 * @return string HTML code for RTE!
	 */
	public function drawRTE(&$parentObject, $table, $field, $row, $PA, $specConf, $thisConfig, $RTEtypeVal, $RTErelPath, $thePidValue) {
		$this->TCEform = $parentObject;
		$this->client = $this->clientInfo();
		$this->typoVersion = \TYPO3\CMS\Core\Utility\VersionNumberUtility::convertVersionNumberToInteger(TYPO3_version);
		/* =======================================
		 * INIT THE EDITOR-SETTINGS
		 * =======================================
		 */
		// Get the path to this extension:
		$this->extHttpPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::siteRelPath($this->ID);
		// Get the site URL
		$this->siteURL = $GLOBALS['TSFE']->absRefPrefix ?: '';
		// Get the host URL
		$this->hostURL = '';
		// Element ID + pid
		$this->elementId = $PA['itemFormElName'];
		$this->elementParts[0] = $table;
		$this->elementParts[1] = $row['uid'];
		$this->tscPID = $thePidValue;
		$this->thePid = $thePidValue;
		// Record "type" field value:
		$this->typeVal = $RTEtypeVal;
		// TCA "type" value for record
		// RTE configuration
		$pageTSConfig = $GLOBALS['TSFE']->getPagesTSconfig();
		if (is_array($pageTSConfig) && is_array($pageTSConfig['RTE.'])) {
			$this->RTEsetup = $pageTSConfig['RTE.'];
		}
		if (is_array($thisConfig) && !empty($thisConfig)) {
			$this->thisConfig = $thisConfig;
		} elseif (is_array($this->RTEsetup['default.']) && is_array($this->RTEsetup['default.']['FE.'])) {
			$this->thisConfig = $this->RTEsetup['default.']['FE.'];
		}
		// Special configuration (line) and default extras:
		$this->specConf = $specConf;
		if ($this->thisConfig['forceHTTPS']) {
			$this->extHttpPath = preg_replace('/^(http|https)/', 'https', $this->extHttpPath);
			$this->siteURL = preg_replace('/^(http|https)/', 'https', $this->siteURL);
			$this->hostURL = preg_replace('/^(http|https)/', 'https', $this->hostURL);
		}
		// Register RTE windows:
		$this->TCEform->RTEwindows[] = $PA['itemFormElName'];
		$textAreaId = preg_replace('/[^a-zA-Z0-9_:.-]/', '_', $PA['itemFormElName']);
		$textAreaId = htmlspecialchars(preg_replace('/^[^a-zA-Z]/', 'x', $textAreaId)) . '_' . strval($this->TCEform->RTEcounter);
		/* =======================================
		 * LANGUAGES & CHARACTER SETS
		 * =======================================
		 */
		// Language
		$GLOBALS['TSFE']->initLLvars();
		$this->language = $GLOBALS['TSFE']->lang;
		$this->LOCAL_LANG = \TYPO3\CMS\Core\Utility\GeneralUtility::readLLfile('EXT:' . $this->ID . '/locallang.xlf', $this->language);
		if ($this->language === 'default' || !$this->language) {
			$this->language = 'en';
		}
		$this->contentISOLanguage = $GLOBALS['TSFE']->sys_language_isocode ?: 'en';
		$this->contentLanguageUid = max($row['sys_language_uid'], 0);
		if ($this->contentLanguageUid && \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('static_info_tables')) {
			$tableA = 'sys_language';
			$tableB = 'static_languages';
			$selectFields = $tableA . '.uid,' . $tableB . '.lg_iso_2,' . $tableB . '.lg_country_iso_2';
			$tableAB = $tableA . ' LEFT JOIN ' . $tableB . ' ON ' . $tableA . '.static_lang_isocode=' . $tableB . '.uid';
			$whereClause = $tableA . '.uid = ' . intval($this->contentLanguageUid);
			$whereClause .= \TYPO3\CMS\Backend\Utility\BackendUtility::BEenableFields($tableA);
			$whereClause .= \TYPO3\CMS\Backend\Utility\BackendUtility::deleteClause($tableA);
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($selectFields, $tableAB, $whereClause);
			while ($languageRow = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				$this->contentISOLanguage = strtolower(trim($languageRow['lg_iso_2']) . (trim($languageRow['lg_country_iso_2']) ? '_' . trim($languageRow['lg_country_iso_2']) : ''));
			}
		}
		$this->contentTypo3Language = $this->contentISOLanguage;
		// Character set
		$this->charset = $GLOBALS['TSFE']->renderCharset;
		$this->OutputCharset = $GLOBALS['TSFE']->metaCharset ?: $GLOBALS['TSFE']->renderCharset;
		// Set the charset of the content
		$this->contentCharset = $GLOBALS['TSFE']->csConvObj->charSetArray[$this->contentTypo3Language];
		$this->contentCharset = $this->contentCharset ?: 'utf-8';
		$this->contentCharset = trim($GLOBALS['TSFE']->config['config']['metaCharset']) ?: $this->contentCharset;
		/* =======================================
		 * TOOLBAR CONFIGURATION
		 * =======================================
		 */
		$this->initializeToolbarConfiguration();
		/* =======================================
		 * SET STYLES
		 * =======================================
		 */
		$width = 610;
		if (isset($this->thisConfig['RTEWidthOverride'])) {
			if (strstr($this->thisConfig['RTEWidthOverride'], '%')) {
				if ($this->client['browser'] != 'msie') {
					$width = (int)$this->thisConfig['RTEWidthOverride'] > 0 ? $this->thisConfig['RTEWidthOverride'] : '100%';
				}
			} else {
				$width = (int)$this->thisConfig['RTEWidthOverride'] > 0 ? (int)$this->thisConfig['RTEWidthOverride'] : $width;
			}
		}
		$RTEWidth = strstr($width, '%') ? $width : $width . 'px';
		$height = 380;
		$RTEHeightOverride = (int)$this->thisConfig['RTEHeightOverride'];
		$height = $RTEHeightOverride > 0 ? $RTEHeightOverride : $height;
		$RTEHeight = $height . 'px';
		$editorWrapWidth = '99%';
		$editorWrapHeight = '100%';
		$this->RTEWrapStyle = $this->RTEWrapStyle ?: ($this->RTEdivStyle ?: 'height:' . $editorWrapHeight . '; width:' . $editorWrapWidth . ';');
		$this->RTEdivStyle = $this->RTEdivStyle ?: 'position:relative; left:0px; top:0px; height:' . $RTEHeight . '; width:' . $RTEWidth . '; border: 1px solid black;';
		/* =======================================
		 * LOAD JS, CSS and more
		 * =======================================
		 */
		$this->getPageRenderer();
		// Register RTE in JS
		$this->TCEform->additionalJS_post[] = $this->wrapCDATA($this->registerRTEinJS($this->TCEform->RTEcounter, '', '', '', $textAreaId));
		// Set the save option for the RTE:
		$this->TCEform->additionalJS_submit[] = $this->setSaveRTE($this->TCEform->RTEcounter, $this->TCEform->formName, $textAreaId);
		$this->pageRenderer->loadRequireJs();
		// Loading ExtJs JavaScript files and inline code, if not configured in TS setup
		if (!is_array($GLOBALS['TSFE']->pSetup['javascriptLibs.']['ExtJs.'])) {
			$this->pageRenderer->loadExtJs();
			$this->pageRenderer->enableExtJSQuickTips();
		}
		$this->pageRenderer->addJsFile('sysext/backend/Resources/Public/JavaScript/notifications.js');
		// Preloading the pageStyle and including RTE skin stylesheets
		$this->addPageStyle();
		$this->pageRenderer->addCssFile($this->siteURL . 'typo3/contrib/extjs/resources/css/ext-all-notheme.css');
		$this->pageRenderer->addCssFile($this->siteURL . 'typo3/sysext/t3skin/extjs/xtheme-t3skin.css');
		$this->addSkin();
		// Add RTE JavaScript
		$this->pageRenderer->loadJquery();
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
		// draw the textarea
		$item = $this->triggerField($PA['itemFormElName']) . '
			<div id="pleasewait' . $textAreaId . '" class="pleasewait" style="display: block;" >' . $GLOBALS['TSFE']->csConvObj->conv($GLOBALS['TSFE']->getLLL('Please wait', $this->LOCAL_LANG), $this->charset, $GLOBALS['TSFE']->renderCharset) . '</div>
			<div id="editorWrap' . $textAreaId . '" class="editorWrap" style="visibility: hidden; ' . htmlspecialchars($this->RTEWrapStyle) . '">
			<textarea id="RTEarea' . $textAreaId . '" name="' . htmlspecialchars($PA['itemFormElName']) . '" rows="0" cols="0" style="' . htmlspecialchars($this->RTEdivStyle) . '">' . \TYPO3\CMS\Core\Utility\GeneralUtility::formatForTextarea($value) . '</textarea>
			</div>' . LF;
		return $item;
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
		$this->pageRenderer->addCssFile($href, $relation, 'screen', $title);
	}

	/**
	 * Return the JS-Code for copy the HTML-Code from the editor in the hidden input field.
	 * This is for submit function from the form.
	 *
	 * @param int $RTEcounter: The index number of the RTE editing area.
	 * @param string $form: the name of the form
	 * @param string $textareaId: the id of the textarea
	 * @return string the JS-Code
	 */
	public function setSaveRTE($RTEcounter, $form, $textareaId) {
		return '
		if (RTEarea[\'' . $textareaId . '\'] && !RTEarea[\'' . $textareaId . '\'].deleted) {
			var field = document.getElementById(\'RTEarea' . $textareaId . '\');
			if (field && field.nodeName.toLowerCase() == \'textarea\') {
				field.value = RTEarea[\'' . $textareaId . '\'][\'editor\'].getHTML();
			}
		} else {
			OK = 0;
		}';
	}

	/**
	 * Gets instance of PageRenderer
	 *
	 * @return 	PageRenderer
	 */
	public function getPageRenderer() {
		if (!isset($this->pageRenderer)) {
			$this->pageRenderer = $GLOBALS['TSFE']->getPageRenderer();
			$this->pageRenderer->setBackPath(TYPO3_mainDir);
		}
		return $this->pageRenderer;
	}

	/**
	 * Wrap input string in CDATA enclosure
	 *
	 * @param string $string: input to be wrapped
	 * @return string wrapped string
	 */
	public function wrapCDATA($string) {
		return implode(LF, array(
			'',
			'/*<![CDATA[*/',
			$string,
			'/*]]>*/'
		));
	}

}
