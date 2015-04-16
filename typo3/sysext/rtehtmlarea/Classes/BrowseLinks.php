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

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Script class for the Element Browser window.
 *
 * @author Kasper Skårhøj <kasperYYYY@typo3.com>
 */
class BrowseLinks extends \TYPO3\CMS\Recordlist\Browser\ElementBrowser {

	public $editorNo;
	/**
	 * TYPO3 language code of the content language
	 */
	public $contentTypo3Language;

	public $contentTypo3Charset = 'utf-8';
	/**
	 * Language service object for localization to the content language
	 */
	protected $contentLanguageService;

	public $additionalAttributes = array();

	public $buttonConfig = array();

	public $RTEProperties = array();

	public $anchorTypes = array('page', 'url', 'file', 'mail', 'spec');

	public $classesAnchorDefault = array();

	public $classesAnchorDefaultTitle = array();

	public $classesAnchorClassTitle = array();

	public $classesAnchorDefaultTarget = array();

	public $classesAnchorJSOptions = array();

	protected $defaultLinkTarget;

	public $allowedItems;

	/**
	 * Constructor:
	 * Initializes a lot of variables, setting JavaScript functions in header etc.
	 *
	 * @return void
	 */
	public function init() {
		$this->initVariables();
		// Create content language service
		$this->contentLanguageService = GeneralUtility::makeInstance(\TYPO3\CMS\Lang\LanguageService::class);
		$this->contentLanguageService->init($this->contentTypo3Language);
		$this->initConfiguration();

		$this->initDocumentTemplate();

		// Initializing hooking browsers
		$this->initHookObjects('ext/rtehtmlarea/mod3/class.tx_rtehtmlarea_browse_links.php');
		$this->allowedItems = $this->getAllowedItems('page,file,folder,url,mail,spec');
		$this->initCurrentUrl();
		// Determine nature of current url:
		$this->act = GeneralUtility::_GP('act');
		if (!$this->act) {
			$this->act = $this->curUrlInfo['act'];
		}
		// Setting initial values for link attributes
		$this->initLinkAttributes();

		// Adding RTE JS code
		// also unset the default jumpToUrl() function before
		unset($this->doc->JScodeArray['jumpToUrl']);
		$this->doc->JScodeArray['rtehtmlarea'] = $this->getJSCode();
	}

	/**
	 * Initialize class variables
	 *
	 * @return void
	 */
	public function initVariables() {
		parent::initVariables();

		// Process bparams
		$pArr = explode('|', $this->bparams);
		$pRteArr = explode(':', $pArr[1]);
		$this->editorNo = $pRteArr[0];
		$this->contentTypo3Language = $pRteArr[1];
		$this->RTEtsConfigParams = $pArr[2];
		if (!$this->editorNo) {
			$this->editorNo = GeneralUtility::_GP('editorNo');
			$this->contentTypo3Language = GeneralUtility::_GP('contentTypo3Language');
			$this->RTEtsConfigParams = GeneralUtility::_GP('RTEtsConfigParams');
		}
		$pArr[1] = implode(':', array($this->editorNo, $this->contentTypo3Language, $this->contentTypo3Charset));
		$pArr[2] = $this->RTEtsConfigParams;
		$this->bparams = implode('|', $pArr);
	}

	/**
	 * Initializes the configuration variables
	 *
	 * @return void
	 */
	public function initConfiguration() {
		$this->thisConfig = $this->getRTEConfig();
		$this->buttonConfig = $this->getButtonConfig('link');
	}

	/**
	 * Initialize document template object
	 *
	 * @return void
	 */
	protected function initDocumentTemplate() {
		parent::initDocumentTemplate();
		$this->doc->getContextMenuCode();
		// Apply the same styles as those of the base script
		$this->doc->bodyTagId = 'typo3-browse-links-php';
		$this->doc->getPageRenderer()->addCssFile($this->doc->backPath . \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extRelPath('t3skin') . 'rtehtmlarea/htmlarea.css');
		// Add attributes to body tag. Note: getBodyTagAdditions will invoke the hooks
		$this->doc->bodyTagAdditions = $this->getBodyTagAdditions();
		$this->doc->getPageRenderer()->loadRequireJsModule('TYPO3/CMS/Backend/LegacyTree', 'function(Tree) {
			Tree.ajaxID = "SC_alt_file_navframe::expandCollapse";
		}');
	}

	/**
	 * Initialize $this->curUrlArray and $this->curUrlInfo based on script parameters
	 *
	 * @return void
	 */
	protected function initCurrentUrl() {
		// CurrentUrl - the current link url must be passed around if it exists
		$this->curUrlArray = GeneralUtility::_GP('curUrl');
		if ($this->curUrlArray['all']) {
			$this->curUrlArray = GeneralUtility::get_tag_attributes($this->curUrlArray['all']);
			$this->curUrlArray['href'] = htmlspecialchars_decode($this->curUrlArray['href']);
		}
		// Note: parseCurUrl will invoke the hooks
		$this->curUrlInfo = $this->parseCurUrl($this->curUrlArray['href'], $this->siteURL);
		if (isset($this->curUrlArray['data-htmlarea-external']) && $this->curUrlArray['data-htmlarea-external'] === '1' && $this->curUrlInfo['act'] != 'mail') {
			$this->curUrlInfo['act'] = 'url';
			$this->curUrlInfo['info'] = $this->curUrlArray['href'];
		}
	}

	/**
	 * Get the RTE configuration from Page TSConfig
	 *
	 * @return array RTE configuration array
	 */
	protected function getRTEConfig() {
		$RTEtsConfigParts = explode(':', $this->RTEtsConfigParams);
		$RTEsetup = $GLOBALS['BE_USER']->getTSConfig('RTE', BackendUtility::getPagesTSconfig($RTEtsConfigParts[5]));
		$this->RTEProperties = $RTEsetup['properties'];
		return BackendUtility::RTEsetup($this->RTEProperties, $RTEtsConfigParts[0], $RTEtsConfigParts[2], $RTEtsConfigParts[4]);
	}

	/**
	 * Get the configuration of the button
	 *
	 * @param string $buttonName: the name of the button
	 * @return array the configuration array of the image button
	 */
	protected function getButtonConfig($buttonName) {
		return is_array($this->thisConfig['buttons.']) && is_array($this->thisConfig['buttons.'][$buttonName . '.'])
			? $this->thisConfig['buttons.'][$buttonName . '.']
			: array();
	}

	/**
	 * Initialize the current or default values of the link attributes
	 *
	 * @return void
	 */
	protected function initLinkAttributes() {
		// Initializing the class value
		$this->setClass = isset($this->curUrlArray['class']) ? $this->curUrlArray['class'] : '';
		// Processing the classes configuration
		$classSelected = array();
		if ($this->buttonConfig['properties.']['class.']['allowedClasses']) {
			$classesAnchorArray = GeneralUtility::trimExplode(',', $this->buttonConfig['properties.']['class.']['allowedClasses'], TRUE);
			// Collecting allowed classes and configured default values
			$classesAnchor = array();
			$classesAnchor['all'] = array();
			$titleReadOnly = $this->buttonConfig['properties.']['title.']['readOnly']
				|| $this->buttonConfig[$this->act . '.']['properties.']['title.']['readOnly'];
			if (is_array($this->RTEProperties['classesAnchor.'])) {
				foreach ($this->RTEProperties['classesAnchor.'] as $label => $conf) {
					if (in_array($conf['class'], $classesAnchorArray)) {
						$classesAnchor['all'][] = $conf['class'];
						if (in_array($conf['type'], $this->anchorTypes)) {
							$classesAnchor[$conf['type']][] = $conf['class'];
							if ($this->buttonConfig[$conf['type'] . '.']['properties.']['class.']['default'] == $conf['class']) {
								$this->classesAnchorDefault[$conf['type']] = $conf['class'];
								if ($conf['titleText']) {
									$this->classesAnchorDefaultTitle[$conf['type']] = $this->getLLContent(trim($conf['titleText']));
								}
								if (isset($conf['target'])) {
									$this->classesAnchorDefaultTarget[$conf['type']] = trim($conf['target']);
								}
							}
						}
						if ($titleReadOnly && $conf['titleText']) {
							$this->classesAnchorClassTitle[$conf['class']] = ($this->classesAnchorDefaultTitle[$conf['type']] = $this->getLLContent(trim($conf['titleText'])));
						}
					}
				}
			}
			// Constructing the class selector options
			foreach ($this->anchorTypes as $anchorType) {
				$currentClass = $this->curUrlInfo['act'] === $anchorType ? $this->curUrlArray['class'] : '';
				foreach ($classesAnchorArray as $class) {
					if (!in_array($class, $classesAnchor['all']) || in_array($class, $classesAnchor['all']) && is_array($classesAnchor[$anchorType]) && in_array($class, $classesAnchor[$anchorType])) {
						$selected = '';
						if ($currentClass == $class || !$currentClass && $this->classesAnchorDefault[$anchorType] == $class) {
							$selected = 'selected="selected"';
							$classSelected[$anchorType] = TRUE;
						}
						$classLabel = is_array($this->RTEProperties['classes.']) && is_array($this->RTEProperties['classes.'][$class . '.']) && $this->RTEProperties['classes.'][$class . '.']['name'] ? $this->getPageConfigLabel($this->RTEProperties['classes.'][$class . '.']['name'], 0) : $class;
						$classStyle = is_array($this->RTEProperties['classes.']) && is_array($this->RTEProperties['classes.'][$class . '.']) && $this->RTEProperties['classes.'][$class . '.']['value'] ? $this->RTEProperties['classes.'][$class . '.']['value'] : '';
						$this->classesAnchorJSOptions[$anchorType] .= '<option ' . $selected . ' value="' . $class . '"' . ($classStyle ? ' style="' . $classStyle . '"' : '') . '>' . $classLabel . '</option>';
					}
				}
				if ($this->classesAnchorJSOptions[$anchorType] && !($this->buttonConfig['properties.']['class.']['required'] || $this->buttonConfig[$this->act . '.']['properties.']['class.']['required'])) {
					$selected = '';
					if (!$this->setClass && !$this->classesAnchorDefault[$anchorType]) {
						$selected = 'selected="selected"';
					}
					$this->classesAnchorJSOptions[$anchorType] = '<option ' . $selected . ' value=""></option>' . $this->classesAnchorJSOptions[$anchorType];
				}
			}
		}
		// Initializing the title value
		$this->setTitle = isset($this->curUrlArray['title']) ? $this->curUrlArray['title'] : '';
		// Initializing the target value
		$this->setTarget = isset($this->curUrlArray['target']) ? $this->curUrlArray['target'] : '';
		// Default target
		$this->defaultLinkTarget = $this->classesAnchorDefault[$this->act] && $this->classesAnchorDefaultTarget[$this->act]
			? $this->classesAnchorDefaultTarget[$this->act]
			: (isset($this->buttonConfig[$this->act . '.']['properties.']['target.']['default'])
				? $this->buttonConfig[$this->act . '.']['properties.']['target.']['default']
				: (isset($this->buttonConfig['properties.']['target.']['default'])
					? $this->buttonConfig['properties.']['target.']['default']
					: ''));
		// Initializing additional attributes
		if ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['rtehtmlarea']['plugins']['TYPO3Link']['additionalAttributes']) {
			$addAttributes = GeneralUtility::trimExplode(',', $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['rtehtmlarea']['plugins']['TYPO3Link']['additionalAttributes'], TRUE);
			foreach ($addAttributes as $attribute) {
				$this->additionalAttributes[$attribute] = isset($this->curUrlArray[$attribute]) ? $this->curUrlArray[$attribute] : '';
			}
		}
	}

	/**
	 * Provide the additional parameters to be included in the template body tag
	 *
	 * @return string the body tag additions
	 */
	public function getBodyTagAdditions() {
		$bodyTagAdditions = array();
		// call hook for extra additions
		foreach ($this->hookObjects as $hookObject) {
			if (method_exists($hookObject, 'addBodyTagAdditions')) {
				$bodyTagAdditions = $hookObject->addBodyTagAdditions($bodyTagAdditions);
			}
		}
		return GeneralUtility::implodeAttributes($bodyTagAdditions, TRUE);
	}

	/**
	 * Generate JS code to be used on the link insert/modify dialogue
	 *
	 * @return string the generated JS code
	 */
	public function getJSCode() {
		// BEGIN accumulation of header JavaScript:
		$JScode = '';
		$JScode .= '
			var plugin = window.parent.RTEarea["' . $this->editorNo . '"].editor.getPlugin("TYPO3Link");
			var HTMLArea = window.parent.HTMLArea;
			var add_href=' . GeneralUtility::quoteJSvalue($this->curUrlArray['href'] ? '&curUrl[href]=' . rawurlencode($this->curUrlArray['href']) : '') . ';
			var add_target=' . GeneralUtility::quoteJSvalue($this->setTarget ? '&curUrl[target]=' . rawurlencode($this->setTarget) : '') . ';
			var add_class=' . GeneralUtility::quoteJSvalue($this->setClass ? '&curUrl[class]=' . rawurlencode($this->setClass) : '') . ';
			var add_title=' . GeneralUtility::quoteJSvalue($this->setTitle ? '&curUrl[title]=' . rawurlencode($this->setTitle) : '') . ';
			var add_params=' . GeneralUtility::quoteJSvalue($this->bparams ? '&bparams=' . rawurlencode($this->bparams) : '') . ';
			var additionalValues = ' . (count($this->additionalAttributes) ? json_encode($this->additionalAttributes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) : '{}') . ';';
		// Attributes setting functions
		$JScode .= '
			var cur_href=' . GeneralUtility::quoteJSvalue($this->curUrlArray['href'] ? ($this->curUrlInfo['query'] ? substr($this->curUrlArray['href'], 0, -strlen($this->curUrlInfo['query'])) : $this->curUrlArray['href']) : '') . ';
			var cur_target=' . GeneralUtility::quoteJSvalue($this->setTarget ?: '') . ';
			var cur_class=' . GeneralUtility::quoteJSvalue($this->setClass ?: '') . ';
			var cur_title=' . GeneralUtility::quoteJSvalue($this->setTitle ?: '') . ';

			function browse_links_setTarget(value) {
				cur_target=value;
				add_target="&curUrl[target]="+encodeURIComponent(value);
			}
			function browse_links_setClass(value) {
				cur_class=value;
				add_class="&curUrl[class]="+encodeURIComponent(value);
			}
			function browse_links_setTitle(value) {
				cur_title=value;
				add_title="&curUrl[title]="+encodeURIComponent(value);
			}
			function browse_links_setHref(value) {
				cur_href=value;
				add_href="&curUrl[href]="+value;
			}
			function browse_links_setAdditionalValue(name, value) {
				additionalValues[name] = value;
			}
		';
		// Link setting functions
		$JScode .= '
			function link_typo3Page(id,anchor) {
				var parameters = (document.ltargetform.query_parameters && document.ltargetform.query_parameters.value) ? (document.ltargetform.query_parameters.value.charAt(0) == "&" ? "" : "&") + document.ltargetform.query_parameters.value : "";
				var theLink = \'' . $this->siteURL . '?id=\' + id + parameters + (anchor ? anchor : "");
				if (document.ltargetform.anchor_title) browse_links_setTitle(document.ltargetform.anchor_title.value);
				if (document.ltargetform.anchor_class) browse_links_setClass(document.ltargetform.anchor_class.value);
				if (document.ltargetform.ltarget) browse_links_setTarget(document.ltargetform.ltarget.value);
				if (document.ltargetform.lrel) browse_links_setAdditionalValue("rel", document.ltargetform.lrel.value);
				browse_links_setAdditionalValue("data-htmlarea-external", "");
				plugin.createLink(theLink,cur_target,cur_class,cur_title,additionalValues);
				return false;
			}
			function link_folder(folder) {
				if (folder && folder.substr(0, 5) == "file:") {
					var theLink = \'' . $this->siteURL . '?file:\' + encodeURIComponent(folder.substr(5));
				} else {
					var theLink = \'' . $this->siteURL . '?\' + folder;
				}
				if (document.ltargetform.anchor_title) browse_links_setTitle(document.ltargetform.anchor_title.value);
				if (document.ltargetform.anchor_class) browse_links_setClass(document.ltargetform.anchor_class.value);
				if (document.ltargetform.ltarget) browse_links_setTarget(document.ltargetform.ltarget.value);
				if (document.ltargetform.lrel) browse_links_setAdditionalValue("rel", document.ltargetform.lrel.value);
				browse_links_setAdditionalValue("data-htmlarea-external", "");
				plugin.createLink(theLink,cur_target,cur_class,cur_title,additionalValues);
				return false;
			}
			function link_spec(theLink) {
				if (document.ltargetform.anchor_title) browse_links_setTitle(document.ltargetform.anchor_title.value);
				if (document.ltargetform.anchor_class) browse_links_setClass(document.ltargetform.anchor_class.value);
				if (document.ltargetform.ltarget) browse_links_setTarget(document.ltargetform.ltarget.value);
				browse_links_setAdditionalValue("data-htmlarea-external", "");
				plugin.createLink(theLink,cur_target,cur_class,cur_title,additionalValues);
				return false;
			}
			function link_current() {
				var parameters = (document.ltargetform.query_parameters && document.ltargetform.query_parameters.value) ? (document.ltargetform.query_parameters.value.charAt(0) == "&" ? "" : "&") + document.ltargetform.query_parameters.value : "";
				if (document.ltargetform.anchor_title) browse_links_setTitle(document.ltargetform.anchor_title.value);
				if (document.ltargetform.anchor_class) browse_links_setClass(document.ltargetform.anchor_class.value);
				if (document.ltargetform.ltarget) browse_links_setTarget(document.ltargetform.ltarget.value);
				if (document.ltargetform.lrel) browse_links_setAdditionalValue("rel", document.ltargetform.lrel.value);
				if (cur_href!="http://" && cur_href!="mailto:") {
					plugin.createLink(cur_href + parameters,cur_target,cur_class,cur_title,additionalValues);
				}
				return false;
			}
		';
		// General "jumpToUrl" and launchView functions:
		$JScode .= '
			function jumpToUrl(URL,anchor) {
				if (URL.charAt(0) === \'?\') {
					URL = ' . GeneralUtility::quoteJSvalue($this->getThisScript()) . ' + URL.substring(1);
				}
				var add_editorNo = URL.indexOf("editorNo=")==-1 ? "&editorNo=' . $this->editorNo . '" : "";
				var add_contentTypo3Language = URL.indexOf("contentTypo3Language=")==-1 ? "&contentTypo3Language=' . $this->contentTypo3Language . '" : "";
				var add_act = URL.indexOf("act=")==-1 ? "&act=' . $this->act . '" : "";
				var add_mode = URL.indexOf("mode=")==-1 ? "&mode=' . $this->mode . '" : "";
				var add_additionalValues = "";
				if (plugin.pageTSConfiguration && plugin.pageTSConfiguration.additionalAttributes) {
					var additionalAttributes = plugin.pageTSConfiguration.additionalAttributes.split(",");
					for (var i = additionalAttributes.length; --i >= 0;) {
						if (additionalValues[additionalAttributes[i]] != "") {
							add_additionalValues += "&curUrl[" + additionalAttributes[i] + "]=" + encodeURIComponent(additionalValues[additionalAttributes[i]]);
						}
					}
				}
				var theLocation = URL+add_act+add_editorNo+add_contentTypo3Language+add_mode+add_href+add_target+add_class+add_title+add_additionalValues+add_params+(anchor?anchor:"");
				window.location.href = theLocation;
				return false;
			}
			function launchView(url) {
				var thePreviewWindow="";
				thePreviewWindow = window.open("' . $GLOBALS['BACK_PATH'] . 'show_item.php?table="+url,"ShowItem","height=300,width=410,status=0,menubar=0,resizable=0,location=0,directories=0,scrollbars=1,toolbar=0");
				if (thePreviewWindow && thePreviewWindow.focus) {
					thePreviewWindow.focus();
				}
			}
		';
		// Hook to overwrite or extend javascript functions
		if (isset($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/rtehtmlarea/mod3/class.tx_rtehtmlarea_browse_links.php']['extendJScode']) && is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/rtehtmlarea/mod3/class.tx_rtehtmlarea_browse_links.php']['extendJScode'])) {
			$conf = array();
			$_params = array(
				'conf' => &$conf
			);
			foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/rtehtmlarea/mod3/class.tx_rtehtmlarea_browse_links.php']['extendJScode'] as $objRef) {
				$processor =& GeneralUtility::getUserObj($objRef);
				$JScode .= $processor->extendJScode($_params, $this);
			}
		}
		return $JScode;
	}

	/******************************************************************
	 *
	 * Main functions
	 *
	 ******************************************************************/
	/**
	 * Rich Text Editor (RTE) link selector (MAIN function)
	 * Generates the link selector for the Rich Text Editor.
	 * Can also be used to select links for the TCEforms (see $wiz)
	 *
	 * @param bool $wiz If set, the "remove link" is not shown in the menu: Used for the "Select link" wizard which is used by the TCEforms
	 * @return string Modified content variable.
	 */
	public function main_rte($wiz = FALSE) {
		// Starting content:
		$content = $this->doc->startPage($GLOBALS['LANG']->getLL('Insert/Modify Link', TRUE));
		// Making menu in top:
		$content .= $this->doc->getTabMenuRaw($this->buildMenuArray($wiz, $this->allowedItems));
		// Adding the menu and header to the top of page:
		$content .= $this->printCurrentUrl($this->curUrlInfo['info']) . '<br />';
		// Depending on the current action we will create the actual module content for selecting a link:
		switch ($this->act) {
			case 'mail':
				$extUrl = $this->getEmailSelectorHtml();
				$content .= $this->addAttributesForm($extUrl);
				break;
			case 'url':
				$extUrl = $this->getExternalUrlSelectorHtml();
				$content .= $this->addAttributesForm($extUrl);
				break;
			case 'file':
			case 'folder':
				$content .= $this->addAttributesForm();
				$content .= $this->getFileSelectorHtml(\TYPO3\CMS\Rtehtmlarea\FolderTree::class);
				break;
			case 'spec':
				$content .= $this->getUserLinkSelectorHtml();
				break;
			case 'page':
				$content .= $this->addAttributesForm();
				$content .= $this->getPageSelectorHtml(\TYPO3\CMS\Rtehtmlarea\PageTree::class);
				break;
			default:
				// call hook
				foreach ($this->hookObjects as $hookObject) {
					$content .= $hookObject->getTab($this->act);
				}
		}
		// End page, return content:
		$content .= $this->doc->endPage();
		$content = $this->doc->insertStylesAndJS($content);
		return $content;
	}

	/**
	 * Returns an array definition of the top menu
	 *
	 * @param $wiz
	 * @param $allowedItems
	 * @return array
	 */
	protected function buildMenuArray($wiz, $allowedItems) {
		$menuDef = array();
		if (!$wiz && $this->curUrlArray['href']) {
			$menuDef['removeLink']['isActive'] = $this->act == 'removeLink';
			$menuDef['removeLink']['label'] = $GLOBALS['LANG']->getLL('removeLink', TRUE);
			$menuDef['removeLink']['url'] = '#';
			$menuDef['removeLink']['addParams'] = 'onclick="plugin.unLink();return false;"';
		}
		if (in_array('page', $this->allowedItems)) {
			$menuDef['page']['isActive'] = $this->act == 'page';
			$menuDef['page']['label'] = $GLOBALS['LANG']->getLL('page', TRUE);
			$menuDef['page']['url'] = '#';
			$menuDef['page']['addParams'] = 'onclick="jumpToUrl(' . GeneralUtility::quoteJSvalue('?act=page&mode=' . $this->mode . '&bparams=' . $this->bparams) . ');return false;"';
		}
		if (in_array('file', $this->allowedItems)) {
			$menuDef['file']['isActive'] = $this->act == 'file';
			$menuDef['file']['label'] = $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_browse_links.xlf:file', TRUE);
			$menuDef['file']['url'] = '#';
			$menuDef['file']['addParams'] = 'onclick="jumpToUrl(' . GeneralUtility::quoteJSvalue('?act=file&mode=' . $this->mode . '&bparams=' . $this->bparams) . ');return false;"';
		}
		if (in_array('folder', $this->allowedItems)) {
			$menuDef['folder']['isActive'] = $this->act == 'folder';
			$menuDef['folder']['label'] = $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_browse_links.xlf:folder', TRUE);
			$menuDef['folder']['url'] = '#';
			$menuDef['folder']['addParams'] = 'onclick="jumpToUrl(' . GeneralUtility::quoteJSvalue('?act=folder&mode=' . $this->mode . '&bparams=' . $this->bparams) . ');return false;"';
		}
		if (in_array('url', $this->allowedItems)) {
			$menuDef['url']['isActive'] = $this->act == 'url';
			$menuDef['url']['label'] = $GLOBALS['LANG']->getLL('extUrl', TRUE);
			$menuDef['url']['url'] = '#';
			$menuDef['url']['addParams'] = 'onclick="jumpToUrl(' . GeneralUtility::quoteJSvalue('?act=url&mode=' . $this->mode . '&bparams=' . $this->bparams) . ');return false;"';
		}
		if (in_array('mail', $this->allowedItems)) {
			$menuDef['mail']['isActive'] = $this->act == 'mail';
			$menuDef['mail']['label'] = $GLOBALS['LANG']->getLL('email', TRUE);
			$menuDef['mail']['url'] = '#';
			$menuDef['mail']['addParams'] = 'onclick="jumpToUrl(' . GeneralUtility::quoteJSvalue('?act=mail&mode=' . $this->mode . '&bparams=' . $this->bparams) . ');return false;"';
		}
		if (is_array($this->thisConfig['userLinks.']) && in_array('spec', $this->allowedItems)) {
			$menuDef['spec']['isActive'] = $this->act == 'spec';
			$menuDef['spec']['label'] = $GLOBALS['LANG']->getLL('special', TRUE);
			$menuDef['spec']['url'] = '#';
			$menuDef['spec']['addParams'] = 'onclick="jumpToUrl(' . GeneralUtility::quoteJSvalue('?act=spec&mode=' . $this->mode . '&bparams=' . $this->bparams) . ');return false;"';
		}
		// call hook for extra options
		foreach ($this->hookObjects as $hookObject) {
			$menuDef = $hookObject->modifyMenuDefinition($menuDef);
		}
		return $menuDef;
	}

	/**
	 * Returns HTML of the email link from
	 *
	 * @return string
	 */
	protected function getEmailSelectorHtml() {
		$extUrl = '
			<!--
				Enter mail address:
			-->
			<tr>
				<td>
					<label>
						' . $GLOBALS['LANG']->getLL('emailAddress', TRUE) . ':
					</label>
				</td>
				<td>
					<input type="text" name="lemail"' . $this->doc->formWidth(20)
						. ' value="' . htmlspecialchars(($this->curUrlInfo['act'] == 'mail' ? $this->curUrlInfo['info'] : '')) . '" />
					<input class="btn btn-default" type="submit" value="' . $GLOBALS['LANG']->getLL('setLink', TRUE)
						. '" onclick="browse_links_setTarget(\'\');browse_links_setHref(\'mailto:\'+document.ltargetform.lemail.value);'
						. 'browse_links_setAdditionalValue(\'data-htmlarea-external\', \'\');return link_current();" />
				</td>
			</tr>';
		return $extUrl;
	}

	/**
	 * Returns HTML of the external url link from
	 *
	 * @return string
	 */
	protected function getExternalUrlSelectorHtml() {
		$extUrl = '
			<!--
				Enter External URL:
			-->
			<tr>
				<td>
					<label>
						URL:
					</label>
				</td>
				<td colspan="3">
					<input type="text" name="lurl"' . $this->doc->formWidth(20)
						. ' value="' . htmlspecialchars(($this->curUrlInfo['act'] == 'url' ? $this->curUrlInfo['info'] : 'http://'))
						. '" />
					<input class="btn btn-default" type="submit" value="' . $GLOBALS['LANG']->getLL('setLink', TRUE)
						. '" onclick="if (/^[A-Za-z0-9_+]{1,8}:/.test(document.ltargetform.lurl.value)) { '
						. ' browse_links_setHref(document.ltargetform.lurl.value); } else { browse_links_setHref(\'http://\''
						. '+document.ltargetform.lurl.value); }; browse_links_setAdditionalValue(\'data-htmlarea-external\', \'1\');'
						. 'return link_current();" />
				</td>
			</tr>';
		return $extUrl;
	}

	/**
	 * Returns HTML of the user defined link selector
	 *
	 * @return string
	 */
	protected function getUserLinkSelectorHtml() {
		if (!is_array($this->thisConfig['userLinks.'])) {
			return '';
		}
		$subcats = array();
		$v = $this->thisConfig['userLinks.'];
		foreach ($v as $k2 => $dummyValue) {
			$k2i = (int)$k2;
			if (substr($k2, -1) == '.' && is_array($v[$k2i . '.'])) {
				// Title:
				$title = trim($v[$k2i]);
				if (!$title) {
					$title = $v[$k2i . '.']['url'];
				} else {
					$title = $GLOBALS['LANG']->sL($title);
				}
				// Description:
				$description = $v[$k2i . '.']['description'] ? $GLOBALS['LANG']->sL($v[($k2i . '.')]['description'], TRUE) . '<br />' : '';
				// URL + onclick event:
				$onClickEvent = '';
				if (isset($v[$k2i . '.']['target'])) {
					$onClickEvent .= 'browse_links_setTarget(\'' . $v[($k2i . '.')]['target'] . '\');';
				}
				$v[$k2i . '.']['url'] = str_replace('###_URL###', $this->siteURL, $v[$k2i . '.']['url']);
				if (substr($v[$k2i . '.']['url'], 0, 7) == 'http://' || substr($v[$k2i . '.']['url'], 0, 7) == 'mailto:') {
					$onClickEvent .= 'cur_href=' . GeneralUtility::quoteJSvalue($v[($k2i . '.')]['url']) . ';link_current();';
				} else {
					$onClickEvent .= 'link_spec(' . GeneralUtility::quoteJSvalue($this->siteURL . $v[($k2i . '.')]['url']) . ');';
				}
				// Link:
				$A = array('<a href="#" onclick="' . htmlspecialchars($onClickEvent) . 'return false;">', '</a>');
				// Adding link to menu of user defined links:
				$icon = $this->curUrlInfo['info'] == $v[$k2i . '.']['url']
					? '<img' . \TYPO3\CMS\Backend\Utility\IconUtility::skinImg($GLOBALS['BACK_PATH'], 'gfx/blinkarrow_right.gif', 'width="5" height="9"') . ' class="c-blinkArrowR" alt="" />'
					: '';
				$subcats[$k2i] = '
								<tr>
									<td class="bgColor4">' . $A[0]
					. '<strong>' . htmlspecialchars($title) . $icon . '</strong><br />' . $description . $A[1] . '</td>
								</tr>';
			}
		}
		// Sort by keys:
		ksort($subcats);
		// Add menu to content:
		$content = '
			<!--
				Special userdefined menu:
			-->
						<table border="0" cellpadding="1" cellspacing="1" id="typo3-linkSpecial">
							<tr>
								<td class="bgColor5" class="c-wCell" valign="top"><strong>' . $GLOBALS['LANG']->getLL('special', TRUE) . '</strong></td>
							</tr>
							' . implode('', $subcats) . '
						</table>
						';
		return $content;
	}

	/**
	 * Get the allowed items or tabs
	 *
	 * @param string $items: initial list of possible items
	 * @return array the allowed items
	 */
	public function getAllowedItems($items) {
		$allowedItems = explode(',', $items);
		// Calling hook for extra options
		foreach ($this->hookObjects as $hookObject) {
			$allowedItems = $hookObject->addAllowedItems($allowedItems);
		}
		// Removing items as per configuration
		if (is_array($this->buttonConfig['options.']) && $this->buttonConfig['options.']['removeItems']) {
			$allowedItems = array_diff($allowedItems, GeneralUtility::trimExplode(',', $this->buttonConfig['options.']['removeItems'], TRUE));
		}
		reset($allowedItems);
		if (!in_array($this->act, $allowedItems)) {
			$this->act = current($allowedItems);
		}
		return $allowedItems;
	}

	/**
	 * Creates a form for link attributes
	 *
	 * @param string $rows: html code for some initial rows of the table to be wrapped in form
	 * @return string The HTML code of the form
	 */
	public function addAttributesForm($rows = '') {
		$ltargetForm = '';
		$additionalAttributeFields = '';
		// Add page id, target, class selector box, title and parameters fields:
		$lpageId = $this->addPageIdSelector();
		$queryParameters = $this->addQueryParametersSelector();
		$ltarget = $this->addTargetSelector();
		$lclass = $this->addClassSelector();
		$ltitle = $this->addTitleSelector();
		$rel = $this->addRelField();
		// additional fields for links
		if (isset($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/rtehtmlarea/mod3/class.tx_rtehtmlarea_browse_links.php']['addAttributeFields'])
			&& is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/rtehtmlarea/mod3/class.tx_rtehtmlarea_browse_links.php']['addAttributeFields'])
		) {
			$conf = array();
			$_params = array(
				'conf' => &$conf
			);
			foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/rtehtmlarea/mod3/class.tx_rtehtmlarea_browse_links.php']['addAttributeFields'] as $objRef) {
				$processor =& GeneralUtility::getUserObj($objRef);
				$additionalAttributeFields .= $processor->getAttributefields($_params, $this);
			}
		}
		if ($rows || $lpageId || $queryParameters || $lclass || $ltitle || $ltarget || $rel) {
			$ltargetForm = $this->wrapInForm($rows . $lpageId . $queryParameters . $lclass . $ltitle . $ltarget . $rel . $additionalAttributeFields);
		}
		return $ltargetForm;
	}

	/**
	 * Wrap in form
	 *
	 * @param string $string
	 * @return string
	 */
	public function wrapInForm($string) {
		$form = '
			<!--
				Selecting target for link:
			-->
			<form action="" name="ltargetform" id="ltargetform">
				<table id="typo3-linkTarget" class="htmlarea-window-table">' . $string;
		if ($this->act == $this->curUrlInfo['act'] && $this->act != 'mail' && $this->curUrlArray['href']) {
			$form .= '
					<tr>
						<td>
						</td>
						<td colspan="3">
							<input class="btn btn-default" type="submit" value="' . $GLOBALS['LANG']->getLL('update', TRUE) . '" onclick="'
								. ($this->act == 'url' ? 'browse_links_setAdditionalValue(\'data-htmlarea-external\', \'1\'); ' : '')
								. 'return link_current();" />
						</td>
					</tr>';
		}
		$form .= '
				</table>
			</form>';
		return $form;
	}

	/**
	 * Add page id selector
	 *
	 * @return string
	 */
	public function addPageIdSelector() {
		if ($this->act == 'page' && isset($this->buttonConfig['pageIdSelector.']['enabled'])
			&& $this->buttonConfig['pageIdSelector.']['enabled']
		) {
			return '
				<tr>
					<td>
						<label>
							' . $GLOBALS['LANG']->getLL('page_id', TRUE) . ':
						</label>
					</td>
					<td colspan="3">
						<input type="text" size="6" name="luid" /> <input class="btn btn-default" type="submit" value="'
							. $GLOBALS['LANG']->getLL('setLink', TRUE) . '" onclick="return link_typo3Page(document.ltargetform.luid.value);" />
					</td>
				</tr>';
		}
		return '';
	}

	/**
	 * Add rel field
	 *
	 * @return string
	 */
	public function addRelField() {
		// Unset rel attribute if we changed tab
		$currentRel = $this->curUrlInfo['act'] === $this->act && isset($this->curUrlArray['rel']) ? $this->curUrlArray['rel'] : '';
		if (($this->act == 'page' || $this->act == 'url' || $this->act == 'file')
			&& isset($this->buttonConfig['relAttribute.']['enabled']) && $this->buttonConfig['relAttribute.']['enabled']
		) {
			return '
						<tr>
							<td><label>' . $GLOBALS['LANG']->getLL('linkRelationship', TRUE) . ':</label></td>
							<td colspan="3">
								<input type="text" name="lrel" value="' . $currentRel . '"  '
				. $this->doc->formWidth(30) . ' />
							</td>
						</tr>';
		}
		return '';
	}

	/**
	 * Add query parameter selector
	 *
	 * @return string
	 */
	public function addQueryParametersSelector() {
		if ($this->act == 'page' && isset($this->buttonConfig['queryParametersSelector.']['enabled'])
			&& $this->buttonConfig['queryParametersSelector.']['enabled']
		) {
			return '
						<tr>
							<td><label>' . $GLOBALS['LANG']->getLL('query_parameters', TRUE) . ':</label></td>
							<td colspan="3">
								<input type="text" name="query_parameters" value="' . ($this->curUrlInfo['query'] ?: '')
				. '" ' . $this->doc->formWidth(30) . ' />
							</td>
						</tr>';
		}
		return '';
	}

	/**
	 * Add target selector
	 *
	 * @return string
	 */
	public function addTargetSelector() {
		if ($this->act === 'mail') {
			return '';
		}
		$targetSelectorConfig = array();
		$popupSelectorConfig = array();
		if (is_array($this->buttonConfig['targetSelector.'])) {
			$targetSelectorConfig = $this->buttonConfig['targetSelector.'];
		}
		if (is_array($this->buttonConfig['popupSelector.'])) {
			$popupSelectorConfig = $this->buttonConfig['popupSelector.'];
		}
		// Reset the target to default if we changed tab
		$currentTarget = $this->curUrlInfo['act'] === $this->act && isset($this->curUrlArray['target']) ? $this->curUrlArray['target'] : '';
		$target = $currentTarget ?: $this->defaultLinkTarget;
		$ltarget = '
				<tr id="ltargetrow"' . ($targetSelectorConfig['disabled'] && $popupSelectorConfig['disabled'] ? ' style="display: none;"' : '') . '>
					<td><label>' . $GLOBALS['LANG']->getLL('target', TRUE) . ':</label></td>
					<td><input type="text" name="ltarget" onchange="browse_links_setTarget(this.value);" value="'
					. htmlspecialchars($target) . '"' . $this->doc->formWidth(10) . ' /></td>';
		$ltarget .= '
					<td colspan="2">';
		if (!$targetSelectorConfig['disabled']) {
			$ltarget .= '
						<select name="ltarget_type" onchange="browse_links_setTarget(this.options[this.selectedIndex].value);document.ltargetform.ltarget.value=this.options[this.selectedIndex].value;this.selectedIndex=0;">
							<option></option>
							<option value="_top">' . $GLOBALS['LANG']->getLL('top', TRUE) . '</option>
							<option value="_blank">' . $GLOBALS['LANG']->getLL('newWindow', TRUE) . '</option>
						</select>';
		}
		$ltarget .= '
					</td>
				</tr>';
		if (!$popupSelectorConfig['disabled']) {
			$selectJS = 'if (document.ltargetform.popup_width.options[document.ltargetform.popup_width.selectedIndex].value>0 && document.ltargetform.popup_height.options[document.ltargetform.popup_height.selectedIndex].value>0) {
				document.ltargetform.ltarget.value = document.ltargetform.popup_width.options[document.ltargetform.popup_width.selectedIndex].value+\'x\'+document.ltargetform.popup_height.options[document.ltargetform.popup_height.selectedIndex].value;
				browse_links_setTarget(document.ltargetform.ltarget.value);
				document.ltargetform.popup_width.selectedIndex=0;
				document.ltargetform.popup_height.selectedIndex=0;
			}';
			$ltarget .= '
					<tr>
						<td><label>' . $GLOBALS['LANG']->getLL('target_popUpWindow', TRUE) . ':</label></td>
						<td colspan="3">
							<select name="popup_width" onchange="' . $selectJS . '">
								<option value="0">' . $GLOBALS['LANG']->getLL('target_popUpWindow_width', TRUE) . '</option>
								<option value="300">300</option>
								<option value="400">400</option>
								<option value="500">500</option>
								<option value="600">600</option>
								<option value="700">700</option>
								<option value="800">800</option>
							</select>
							x
							<select name="popup_height" onchange="' . $selectJS . '">
								<option value="0">' . $GLOBALS['LANG']->getLL('target_popUpWindow_height', TRUE) . '</option>
								<option value="200">200</option>
								<option value="300">300</option>
								<option value="400">400</option>
								<option value="500">500</option>
								<option value="600">600</option>
							</select>
						</td>
					</tr>';
		}
		return $ltarget;
	}

	/**
	 * Return html code for the class selector
	 *
	 * @return string the html code to be added to the form
	 */
	public function addClassSelector() {
		$selectClass = '';
		if ($this->classesAnchorJSOptions[$this->act]) {
			$selectClass = '
						<tr>
							<td><label>' . $GLOBALS['LANG']->getLL('anchor_class', TRUE) . ':</label></td>
							<td colspan="3">
								<select name="anchor_class" onchange="' . $this->getClassOnChangeJS() . '">
									' . $this->classesAnchorJSOptions[$this->act] . '
								</select>
							</td>
						</tr>';
		}
		return $selectClass;
	}

	/**
	 * Return JS code for the class selector onChange event
	 *
	 * @return 	string	class selector onChange JS code
	 */
	public function getClassOnChangeJS() {
		return '
					if (document.ltargetform.anchor_class) {
						document.ltargetform.anchor_class.value = document.ltargetform.anchor_class.options[document.ltargetform.anchor_class.selectedIndex].value;
						if (document.ltargetform.anchor_class.value && HTMLArea.classesAnchorSetup) {
							for (var i = HTMLArea.classesAnchorSetup.length; --i >= 0;) {
								var anchorClass = HTMLArea.classesAnchorSetup[i];
								if (anchorClass[\'name\'] == document.ltargetform.anchor_class.value) {
									if (anchorClass[\'titleText\'] && document.ltargetform.anchor_title) {
										document.ltargetform.anchor_title.value = anchorClass[\'titleText\'];
										document.getElementById(\'rtehtmlarea-browse-links-title-readonly\').innerHTML = anchorClass[\'titleText\'];
										browse_links_setTitle(anchorClass[\'titleText\']);
									}
									if (typeof anchorClass[\'target\'] !== \'undefined\') {
										if (document.ltargetform.ltarget) {
											document.ltargetform.ltarget.value = anchorClass[\'target\'];
										}
										browse_links_setTarget(anchorClass[\'target\']);
									} else if (document.ltargetform.ltarget && document.getElementById(\'ltargetrow\').style.display == \'none\') {
											// Reset target to default if field is not displayed and class has no configured target
										document.ltargetform.ltarget.value = \'' . ($this->defaultLinkTarget ?: '') . '\';
										browse_links_setTarget(document.ltargetform.ltarget.value);
									}
									break;
								}
							}
						}
						browse_links_setClass(document.ltargetform.anchor_class.value);
					}
								';
	}

	/**
	 * Add title selector
	 *
	 * @return string
	 */
	public function addTitleSelector() {
		// Reset the title to default if we changed tab
		$currentTitle = $this->curUrlInfo['act'] === $this->act && isset($this->curUrlArray['title']) ? $this->curUrlArray['title'] : '';
		$title = $currentTitle ?: (!$this->classesAnchorDefault[$this->act] ? '' : $this->classesAnchorDefaultTitle[$this->act]);
		$readOnly = isset($this->buttonConfig[$this->act . '.']['properties.']['title.']['readOnly'])
			? $this->buttonConfig[$this->act . '.']['properties.']['title.']['readOnly']
			: (isset($this->buttonConfig['properties.']['title.']['readOnly'])
				? $this->buttonConfig['properties.']['title.']['readOnly']
				: false);
		if ($readOnly) {
			$currentClass = $this->curUrlInfo['act'] === $this->act ? $this->curUrlArray['class'] : '';
			if (!$currentClass) {
				$currentClass = !$this->classesAnchorDefault[$this->act] ? '' : $this->classesAnchorDefault[$this->act];
			}
			$title = $currentClass
				? $this->classesAnchorClassTitle[$currentClass]
				: $this->classesAnchorDefaultTitle[$this->act];
		}
		return '
						<tr>
							<td><label for="rtehtmlarea-browse-links-anchor_title" id="rtehtmlarea-browse-links-title-label">' . $GLOBALS['LANG']->getLL('anchor_title', TRUE) . ':</label></td>
							<td colspan="3">
								<span id="rtehtmlarea-browse-links-title-input" style="display: ' . ($readOnly ? 'none' : 'inline') . ';">
									<input type="text" id="rtehtmlarea-browse-links-anchor_title" name="anchor_title" value="' . $title . '" ' . $this->doc->formWidth(30) . ' />
								</span>
								<span id="rtehtmlarea-browse-links-title-readonly" style="display: ' . ($readOnly ? 'inline' : 'none') . ';">' . $title . '</span>
							</td>
						</tr>';
	}

	/**
	 * Localize a string using the language of the content element rather than the language of the BE interface
	 *
	 * @param string string: the label to be localized
	 * @return string Localized string.
	 */
	public function getLLContent($string) {
		return $this->contentLanguageService->sL($string);
	}

	/**
	 * Localize a label obtained from Page TSConfig
	 *
	 * @param string $string The label to be localized
	 * @param bool $JScharCode If needs to be converted to a array of char numbers
	 * @return string Localized string
	 */
	public function getPageConfigLabel($string, $JScharCode = TRUE) {
		if (substr($string, 0, 4) !== 'LLL:') {
			$label = $string;
		} else {
			$label = $GLOBALS['LANG']->sL(trim($string));
		}
		$label = str_replace('"', '\\"', str_replace('\\\'', '\'', $label));
		return $JScharCode ? GeneralUtility::quoteJSvalue($label) : $label;
	}

}
