<?php
namespace TYPO3\CMS\Frontend\Page;

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

use TYPO3\CMS\Core\Type\File\ImageInfo;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Service\TypoScriptService;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * Class for starting TypoScript page generation
 *
 * The class is not instantiated as an objects but called directly with the "::" operator.
 * eg: \TYPO3\CMS\Frontend\Page\PageGenerator::pagegenInit()
 *
 * @author Kasper Skårhøj <kasperYYYY@typo3.com>
 */
class PageGenerator {

	/**
	 * Do not render title tag
	 * Typoscript setting: [config][noPageTitle]
	 */
	const NO_PAGE_TITLE = 2;

	/**
	 * Setting some vars in TSFE, primarily based on TypoScript config settings.
	 *
	 * @return void
	 */
	static public function pagegenInit() {
		if ($GLOBALS['TSFE']->page['content_from_pid'] > 0) {
			// make REAL copy of TSFE object - not reference!
			$temp_copy_TSFE = clone $GLOBALS['TSFE'];
			// Set ->id to the content_from_pid value - we are going to evaluate this pid as was it a given id for a page-display!
			$temp_copy_TSFE->id = $GLOBALS['TSFE']->page['content_from_pid'];
			$temp_copy_TSFE->MP = '';
			$temp_copy_TSFE->getPageAndRootlineWithDomain($GLOBALS['TSFE']->config['config']['content_from_pid_allowOutsideDomain'] ? 0 : $GLOBALS['TSFE']->domainStartPage);
			$GLOBALS['TSFE']->contentPid = (int)$temp_copy_TSFE->id;
			unset($temp_copy_TSFE);
		}
		if ($GLOBALS['TSFE']->config['config']['MP_defaults']) {
			$temp_parts = GeneralUtility::trimExplode('|', $GLOBALS['TSFE']->config['config']['MP_defaults'], TRUE);
			foreach ($temp_parts as $temp_p) {
				list($temp_idP, $temp_MPp) = explode(':', $temp_p, 2);
				$temp_ids = GeneralUtility::intExplode(',', $temp_idP);
				foreach ($temp_ids as $temp_id) {
					$GLOBALS['TSFE']->MP_defaults[$temp_id] = $temp_MPp;
				}
			}
		}
		// Global vars...
		$GLOBALS['TSFE']->indexedDocTitle = $GLOBALS['TSFE']->page['title'];
		$GLOBALS['TSFE']->debug = '' . $GLOBALS['TSFE']->config['config']['debug'];
		// Base url:
		if (isset($GLOBALS['TSFE']->config['config']['baseURL'])) {
			$GLOBALS['TSFE']->baseUrl = $GLOBALS['TSFE']->config['config']['baseURL'];
			$GLOBALS['TSFE']->anchorPrefix = substr(GeneralUtility::getIndpEnv('TYPO3_REQUEST_URL'), strlen(GeneralUtility::getIndpEnv('TYPO3_SITE_URL')));
		}
		// Internal and External target defaults
		$GLOBALS['TSFE']->intTarget = '' . $GLOBALS['TSFE']->config['config']['intTarget'];
		$GLOBALS['TSFE']->extTarget = '' . $GLOBALS['TSFE']->config['config']['extTarget'];
		$GLOBALS['TSFE']->fileTarget = '' . $GLOBALS['TSFE']->config['config']['fileTarget'];
		if ($GLOBALS['TSFE']->config['config']['spamProtectEmailAddresses'] === 'ascii') {
			$GLOBALS['TSFE']->spamProtectEmailAddresses = 'ascii';
		} else {
			$GLOBALS['TSFE']->spamProtectEmailAddresses = \TYPO3\CMS\Core\Utility\MathUtility::forceIntegerInRange($GLOBALS['TSFE']->config['config']['spamProtectEmailAddresses'], -10, 10, 0);
		}
		// calculate the absolute path prefix
		if (!empty($GLOBALS['TSFE']->config['config']['absRefPrefix'])) {
			$absRefPrefix = trim($GLOBALS['TSFE']->config['config']['absRefPrefix']);
			if ($absRefPrefix === 'auto') {
				$GLOBALS['TSFE']->absRefPrefix = GeneralUtility::getIndpEnv('TYPO3_SITE_PATH');
			} else {
				$GLOBALS['TSFE']->absRefPrefix = $absRefPrefix;
			}
		} else {
			$GLOBALS['TSFE']->absRefPrefix = '';
		}
		if ($GLOBALS['TSFE']->type && $GLOBALS['TSFE']->config['config']['frameReloadIfNotInFrameset']) {
			$tdlLD = $GLOBALS['TSFE']->tmpl->linkData($GLOBALS['TSFE']->page, '_top', $GLOBALS['TSFE']->no_cache, '');
			$GLOBALS['TSFE']->additionalJavaScript['JSCode'] .= 'if(!parent.' . trim($GLOBALS['TSFE']->sPre) . ' && !parent.view_frame) top.location.href="' . $GLOBALS['TSFE']->baseUrlWrap($tdlLD['totalURL']) . '"';
		}
		$GLOBALS['TSFE']->compensateFieldWidth = '' . $GLOBALS['TSFE']->config['config']['compensateFieldWidth'];
		$GLOBALS['TSFE']->lockFilePath = '' . $GLOBALS['TSFE']->config['config']['lockFilePath'];
		$GLOBALS['TSFE']->lockFilePath = $GLOBALS['TSFE']->lockFilePath ?: $GLOBALS['TYPO3_CONF_VARS']['BE']['fileadminDir'];
		$GLOBALS['TYPO3_CONF_VARS']['GFX']['im_noScaleUp'] = isset($GLOBALS['TSFE']->config['config']['noScaleUp']) ? '' . $GLOBALS['TSFE']->config['config']['noScaleUp'] : $GLOBALS['TYPO3_CONF_VARS']['GFX']['im_noScaleUp'];
		$GLOBALS['TSFE']->TYPO3_CONF_VARS['GFX']['im_noScaleUp'] = $GLOBALS['TYPO3_CONF_VARS']['GFX']['im_noScaleUp'];
		$GLOBALS['TSFE']->ATagParams = trim($GLOBALS['TSFE']->config['config']['ATagParams']) ? ' ' . trim($GLOBALS['TSFE']->config['config']['ATagParams']) : '';
		if ($GLOBALS['TSFE']->config['config']['setJS_mouseOver']) {
			$GLOBALS['TSFE']->setJS('mouseOver');
		}
		if ($GLOBALS['TSFE']->config['config']['setJS_openPic']) {
			$GLOBALS['TSFE']->setJS('openPic');
		}
		$GLOBALS['TSFE']->sWordRegEx = '';
		$GLOBALS['TSFE']->sWordList = GeneralUtility::_GP('sword_list');
		if (is_array($GLOBALS['TSFE']->sWordList)) {
			$space = !empty($GLOBALS['TSFE']->config['config']['sword_standAlone']) ? '[[:space:]]' : '';
			foreach ($GLOBALS['TSFE']->sWordList as $val) {
				if (trim($val) !== '') {
					$GLOBALS['TSFE']->sWordRegEx .= $space . quotemeta($val) . $space . '|';
				}
			}
			$GLOBALS['TSFE']->sWordRegEx = preg_replace('/\\|$/', '', $GLOBALS['TSFE']->sWordRegEx);
		}
		// linkVars
		$GLOBALS['TSFE']->calculateLinkVars();
		// dtdAllowsFrames indicates whether to use the target attribute in links
		$GLOBALS['TSFE']->dtdAllowsFrames = FALSE;
		if ($GLOBALS['TSFE']->config['config']['doctype']) {
			if (in_array(
				(string)$GLOBALS['TSFE']->config['config']['doctype'],
				array('xhtml_trans', 'xhtml_frames', 'xhtml_basic', 'xhtml_2', 'html5'),
				TRUE)
			) {
				$GLOBALS['TSFE']->dtdAllowsFrames = TRUE;
			}
		} else {
			$GLOBALS['TSFE']->dtdAllowsFrames = TRUE;
		}
		// Setting XHTML-doctype from doctype
		if (!$GLOBALS['TSFE']->config['config']['xhtmlDoctype']) {
			$GLOBALS['TSFE']->config['config']['xhtmlDoctype'] = $GLOBALS['TSFE']->config['config']['doctype'];
		}
		if ($GLOBALS['TSFE']->config['config']['xhtmlDoctype']) {
			$GLOBALS['TSFE']->xhtmlDoctype = $GLOBALS['TSFE']->config['config']['xhtmlDoctype'];
			// Checking XHTML-docytpe
			switch ((string)$GLOBALS['TSFE']->config['config']['xhtmlDoctype']) {
				case 'xhtml_trans':

				case 'xhtml_strict':

				case 'xhtml_frames':
					$GLOBALS['TSFE']->xhtmlVersion = 100;
					break;
				case 'xhtml_basic':
					$GLOBALS['TSFE']->xhtmlVersion = 105;
					break;
				case 'xhtml_11':

				case 'xhtml+rdfa_10':
					$GLOBALS['TSFE']->xhtmlVersion = 110;
					break;
				case 'xhtml_2':
					GeneralUtility::deprecationLog('The option "config.xhtmlDoctype=xhtml_2" is deprecated since TYPO3 CMS 7, and will be removed with CMS 8');
					$GLOBALS['TSFE']->xhtmlVersion = 200;
					break;
				default:
					$GLOBALS['TSFE']->getPageRenderer()->setRenderXhtml(FALSE);
					$GLOBALS['TSFE']->xhtmlDoctype = '';
					$GLOBALS['TSFE']->xhtmlVersion = 0;
			}
		} else {
			$GLOBALS['TSFE']->getPageRenderer()->setRenderXhtml(FALSE);
		}
	}

	/**
	 * Returns an array with files to include. These files are the ones set up in TypoScript config.
	 *
	 * @return array Files to include. Paths are relative to PATH_site.
	 */
	static public function getIncFiles() {
		$incFilesArray = array();
		// Get files from config.includeLibrary
		$includeLibrary = trim('' . $GLOBALS['TSFE']->config['config']['includeLibrary']);
		if ($includeLibrary) {
			$incFile = $GLOBALS['TSFE']->tmpl->getFileName($includeLibrary);
			if ($incFile) {
				$incFilesArray[] = $incFile;
			}
		}
		if (is_array($GLOBALS['TSFE']->pSetup['includeLibs.'])) {
			$incLibs = $GLOBALS['TSFE']->pSetup['includeLibs.'];
		} else {
			$incLibs = array();
		}
		if (is_array($GLOBALS['TSFE']->tmpl->setup['includeLibs.'])) {
			// toplevel 'includeLibs' is added to the PAGE.includeLibs. In that way, PAGE-libs get first priority, because if the key already exist, it's not altered. (Due to investigation by me)
			$incLibs += $GLOBALS['TSFE']->tmpl->setup['includeLibs.'];
		}
		if (count($incLibs)) {
			foreach ($incLibs as $theLib) {
				if (!is_array($theLib) && ($incFile = $GLOBALS['TSFE']->tmpl->getFileName($theLib))) {
					$incFilesArray[] = $incFile;
				}
			}
		}
		return $incFilesArray;
	}

	/**
	 * Processing JavaScript handlers
	 *
	 * @return array Array with a) a JavaScript section with event handlers and variables set and b) an array with attributes for the body tag.
	 * @deprecated since TYPO3 CMS 7, will be removed in TYPO3 CMS 8, use JS directly
	 */
	static public function JSeventFunctions() {
		$functions = array();
		$setEvents = array();
		$setBody = array();
		foreach ($GLOBALS['TSFE']->JSeventFuncCalls as $event => $handlers) {
			if (count($handlers)) {
				GeneralUtility::deprecationLog('The usage of $GLOBALS[\'TSFE\']->JSeventFuncCalls is deprecated as of TYPO3 CMS 7. Use Javascript directly.');
				$functions[] = '	function T3_' . $event . 'Wrapper(e) {	' . implode('   ', $handlers) . '	}';
				$setEvents[] = '	document.' . $event . '=T3_' . $event . 'Wrapper;';
				if ($event == 'onload') {
					// Dubiuos double setting breaks on some browser - do we need it?
					$setBody[] = 'onload="T3_onloadWrapper();"';
				}
			}
		}
		return array(count($functions) ? implode(LF, $functions) . LF . implode(LF, $setEvents) : '', $setBody);
	}

	/**
	 * Rendering the page content
	 *
	 * @return void
	 */
	static public function renderContent() {
		// PAGE CONTENT
		$GLOBALS['TT']->incStackPointer();
		$GLOBALS['TT']->push($GLOBALS['TSFE']->sPre, 'PAGE');
		$pageContent = $GLOBALS['TSFE']->cObj->cObjGet($GLOBALS['TSFE']->pSetup);
		if ($GLOBALS['TSFE']->pSetup['wrap']) {
			$pageContent = $GLOBALS['TSFE']->cObj->wrap($pageContent, $GLOBALS['TSFE']->pSetup['wrap']);
		}
		if ($GLOBALS['TSFE']->pSetup['stdWrap.']) {
			$pageContent = $GLOBALS['TSFE']->cObj->stdWrap($pageContent, $GLOBALS['TSFE']->pSetup['stdWrap.']);
		}
		// PAGE HEADER (after content - maybe JS is inserted!
		// if 'disableAllHeaderCode' is set, all the header-code is discarded!
		if ($GLOBALS['TSFE']->config['config']['disableAllHeaderCode']) {
			$GLOBALS['TSFE']->content = $pageContent;
		} else {
			self::renderContentWithHeader($pageContent);
		}
		$GLOBALS['TT']->pull($GLOBALS['TT']->LR ? $GLOBALS['TSFE']->content : '');
		$GLOBALS['TT']->decStackPointer();
	}

	/**
	 * Rendering normal HTML-page with header by wrapping the generated content ($pageContent) in body-tags and setting the header accordingly.
	 *
	 * @param string $pageContent The page content which TypoScript objects has generated
	 * @return void
	 */
	static public function renderContentWithHeader($pageContent) {
		/** @var $pageRenderer \TYPO3\CMS\Core\Page\PageRenderer */
		$pageRenderer = $GLOBALS['TSFE']->getPageRenderer();
		if ($GLOBALS['TSFE']->config['config']['moveJsFromHeaderToFooter']) {
			$pageRenderer->enableMoveJsFromHeaderToFooter();
		}
		if ($GLOBALS['TSFE']->config['config']['pageRendererTemplateFile']) {
			$file = $GLOBALS['TSFE']->tmpl->getFileName($GLOBALS['TSFE']->config['config']['pageRendererTemplateFile']);
			if ($file) {
				$pageRenderer->setTemplateFile($file);
			}
		}
		$headerComment = $GLOBALS['TSFE']->config['config']['headerComment'];
		if (trim($headerComment)) {
			$pageRenderer->addInlineComment(TAB . str_replace(LF, (LF . TAB), trim($headerComment)) . LF);
		}
		// Setting charset:
		$theCharset = $GLOBALS['TSFE']->metaCharset;
		// Reset the content variables:
		$GLOBALS['TSFE']->content = '';
		$htmlTagAttributes = array();
		$htmlLang = $GLOBALS['TSFE']->config['config']['htmlTag_langKey'] ?: ($GLOBALS['TSFE']->sys_language_isocode ?: 'en');
		// Set content direction: (More info: http://www.tau.ac.il/~danon/Hebrew/HTML_and_Hebrew.html)
		if ($GLOBALS['TSFE']->config['config']['htmlTag_dir']) {
			$htmlTagAttributes['dir'] = htmlspecialchars($GLOBALS['TSFE']->config['config']['htmlTag_dir']);
		}
		// Setting document type:
		$docTypeParts = array();
		$xmlDocument = TRUE;
		// Part 1: XML prologue
		switch ((string)$GLOBALS['TSFE']->config['config']['xmlprologue']) {
			case 'none':
				$xmlDocument = FALSE;
				break;
			case 'xml_10':
				$docTypeParts[] = '<?xml version="1.0" encoding="' . $theCharset . '"?>';
				break;
			case 'xml_11':
				$docTypeParts[] = '<?xml version="1.1" encoding="' . $theCharset . '"?>';
				break;
			case '':
				if ($GLOBALS['TSFE']->xhtmlVersion) {
					$docTypeParts[] = '<?xml version="1.0" encoding="' . $theCharset . '"?>';
				}
				break;
			default:
				$docTypeParts[] = $GLOBALS['TSFE']->config['config']['xmlprologue'];
		}
		// Part 2: DTD
		$doctype = $GLOBALS['TSFE']->config['config']['doctype'];
		if ($doctype) {
			switch ($doctype) {
				case 'xhtml_trans':
					$docTypeParts[] = '<!DOCTYPE html
    PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">';
					break;
				case 'xhtml_strict':
					$docTypeParts[] = '<!DOCTYPE html
    PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">';
					break;
				case 'xhtml_frames':
					$docTypeParts[] = '<!DOCTYPE html
    PUBLIC "-//W3C//DTD XHTML 1.0 Frameset//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-frameset.dtd">';
					break;
				case 'xhtml_basic':
					$docTypeParts[] = '<!DOCTYPE html
    PUBLIC "-//W3C//DTD XHTML Basic 1.0//EN"
    "http://www.w3.org/TR/xhtml-basic/xhtml-basic10.dtd">';
					break;
				case 'xhtml_11':
					$docTypeParts[] = '<!DOCTYPE html
    PUBLIC "-//W3C//DTD XHTML 1.1//EN"
    "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">';
					break;
				case 'xhtml_2':
					$docTypeParts[] = '<!DOCTYPE html
    PUBLIC "-//W3C//DTD XHTML 2.0//EN"
    "http://www.w3.org/TR/xhtml2/DTD/xhtml2.dtd">';
					break;
				case 'xhtml+rdfa_10':
					$docTypeParts[] = '<!DOCTYPE html
    PUBLIC "-//W3C//DTD XHTML+RDFa 1.0//EN"
    "http://www.w3.org/MarkUp/DTD/xhtml-rdfa-1.dtd">';
					break;
				case 'html5':
					$docTypeParts[] = '<!DOCTYPE html>';
					if ($xmlDocument) {
						$pageRenderer->setMetaCharsetTag('<meta charset="|" />');
					} else {
						$pageRenderer->setMetaCharsetTag('<meta charset="|">');
					}
					break;
				case 'none':
					break;
				default:
					$docTypeParts[] = $doctype;
			}
		} else {
			$docTypeParts[] = '<!DOCTYPE html>';
			if ($xmlDocument){
				$pageRenderer->setMetaCharsetTag('<meta charset="|" />');
			} else {
				$pageRenderer->setMetaCharsetTag('<meta charset="|">');
			}
		}
		if ($GLOBALS['TSFE']->xhtmlVersion) {
			$htmlTagAttributes['xml:lang'] = $htmlLang;
		}
		if ($GLOBALS['TSFE']->xhtmlVersion < 110 || $doctype === 'html5') {
			$htmlTagAttributes['lang'] = $htmlLang;
		}
		if ($GLOBALS['TSFE']->xhtmlVersion || $doctype === 'html5' && $xmlDocument) {
			// We add this to HTML5 to achieve a slightly better backwards compatibility
			$htmlTagAttributes['xmlns'] = 'http://www.w3.org/1999/xhtml';
			if (is_array($GLOBALS['TSFE']->config['config']['namespaces.'])) {
				foreach ($GLOBALS['TSFE']->config['config']['namespaces.'] as $prefix => $uri) {
					// $uri gets htmlspecialchared later
					$htmlTagAttributes['xmlns:' . htmlspecialchars($prefix)] = $uri;
				}
			}
		}
		// Swap XML and doctype order around (for MSIE / Opera standards compliance)
		if ($GLOBALS['TSFE']->config['config']['doctypeSwitch']) {
			$docTypeParts = array_reverse($docTypeParts);
		}
		// Adding doctype parts:
		if (count($docTypeParts)) {
			$pageRenderer->setXmlPrologAndDocType(implode(LF, $docTypeParts));
		}
		// Begin header section:
		if ($GLOBALS['TSFE']->config['config']['htmlTag_setParams'] !== 'none') {
			$_attr = $GLOBALS['TSFE']->config['config']['htmlTag_setParams'] ? $GLOBALS['TSFE']->config['config']['htmlTag_setParams'] : GeneralUtility::implodeAttributes($htmlTagAttributes);
		} else {
			$_attr = '';
		}
		$htmlTag = '<html' . ($_attr ? ' ' . $_attr : '') . '>';
		if (isset($GLOBALS['TSFE']->config['config']['htmlTag_stdWrap.'])) {
			$htmlTag = $GLOBALS['TSFE']->cObj->stdWrap($htmlTag, $GLOBALS['TSFE']->config['config']['htmlTag_stdWrap.']);
		}
		$pageRenderer->setHtmlTag($htmlTag);
		// Head tag:
		$headTag = $GLOBALS['TSFE']->pSetup['headTag'] ?: '<head>';
		if (isset($GLOBALS['TSFE']->pSetup['headTag.'])) {
			$headTag = $GLOBALS['TSFE']->cObj->stdWrap($headTag, $GLOBALS['TSFE']->pSetup['headTag.']);
		}
		$pageRenderer->setHeadTag($headTag);
		// Setting charset meta tag:
		$pageRenderer->setCharSet($theCharset);
		$pageRenderer->addInlineComment('	This website is powered by TYPO3 - inspiring people to share!
	TYPO3 is a free open source Content Management Framework initially created by Kasper Skaarhoj and licensed under GNU/GPL.
	TYPO3 is copyright ' . TYPO3_copyright_year . ' of Kasper Skaarhoj. Extensions are copyright of their respective owners.
	Information and contribution at ' . TYPO3_URL_ORG . '
');
		if ($GLOBALS['TSFE']->baseUrl) {
			$pageRenderer->setBaseUrl($GLOBALS['TSFE']->baseUrl);
		}
		if ($GLOBALS['TSFE']->pSetup['shortcutIcon']) {
			$favIcon = $GLOBALS['TSFE']->tmpl->getFileName($GLOBALS['TSFE']->pSetup['shortcutIcon']);
			$iconFileInfo = GeneralUtility::makeInstance(ImageInfo::class, PATH_site . $favIcon);
			if ($iconFileInfo->isFile()) {
				$iconMimeType = $iconFileInfo->getMimeType();
				if ($iconMimeType) {
					$iconMimeType = ' type="' . $iconMimeType . '"';
					$pageRenderer->setIconMimeType($iconMimeType);
				}
				$pageRenderer->setFavIcon(GeneralUtility::getIndpEnv('TYPO3_SITE_URL') . $favIcon);
			}
		}
		// Including CSS files
		if (is_array($GLOBALS['TSFE']->tmpl->setup['plugin.'])) {
			$temp_styleLines = array();
			foreach ($GLOBALS['TSFE']->tmpl->setup['plugin.'] as $key => $iCSScode) {
				if (is_array($iCSScode)) {
					if ($iCSScode['_CSS_DEFAULT_STYLE'] && empty($GLOBALS['TSFE']->config['config']['removeDefaultCss'])) {
						if (isset($iCSScode['_CSS_DEFAULT_STYLE.'])) {
							$cssDefaultStyle = $GLOBALS['TSFE']->cObj->stdWrap($iCSScode['_CSS_DEFAULT_STYLE'], $iCSScode['_CSS_DEFAULT_STYLE.']);
						} else {
							$cssDefaultStyle = $iCSScode['_CSS_DEFAULT_STYLE'];
						}
						$temp_styleLines[] = '/* default styles for extension "' . substr($key, 0, -1) . '" */' . LF . $cssDefaultStyle;
					}
					if ($iCSScode['_CSS_PAGE_STYLE'] && empty($GLOBALS['TSFE']->config['config']['removePageCss'])) {
						$cssPageStyle = implode(LF, $iCSScode['_CSS_PAGE_STYLE']);
						if (isset($iCSScode['_CSS_PAGE_STYLE.'])) {
							$cssPageStyle = $GLOBALS['TSFE']->cObj->stdWrap($cssPageStyle, $iCSScode['_CSS_PAGE_STYLE.']);
						}
						$temp_styleLines[] = '/* specific page styles for extension "' . substr($key, 0, -1) . '" */' . LF . $cssPageStyle;
					}
				}
			}
			if (count($temp_styleLines)) {
				if ($GLOBALS['TSFE']->config['config']['inlineStyle2TempFile']) {
					$pageRenderer->addCssFile(self::inline2TempFile(implode(LF, $temp_styleLines), 'css'));
				} else {
					$pageRenderer->addCssInlineBlock('TSFEinlineStyle', implode(LF, $temp_styleLines));
				}
			}
		}
		if ($GLOBALS['TSFE']->pSetup['stylesheet']) {
			$ss = $GLOBALS['TSFE']->tmpl->getFileName($GLOBALS['TSFE']->pSetup['stylesheet']);
			if ($ss) {
				$pageRenderer->addCssFile($ss);
			}
		}
		/**********************************************************************/
		/* config.includeCSS / config.includeCSSLibs
		/**********************************************************************/
		if (is_array($GLOBALS['TSFE']->pSetup['includeCSS.'])) {
			foreach ($GLOBALS['TSFE']->pSetup['includeCSS.'] as $key => $CSSfile) {
				if (!is_array($CSSfile)) {
					$cssFileConfig = &$GLOBALS['TSFE']->pSetup['includeCSS.'][$key . '.'];
					if (isset($cssFileConfig['if.']) && !$GLOBALS['TSFE']->cObj->checkIf($cssFileConfig['if.'])) {
						continue;
					}
					$ss = $cssFileConfig['external'] ? $CSSfile : $GLOBALS['TSFE']->tmpl->getFileName($CSSfile);
					if ($ss) {
						if ($cssFileConfig['import']) {
							if (!$cssFileConfig['external'] && $ss[0] !== '/') {
								// To fix MSIE 6 that cannot handle these as relative paths (according to Ben v Ende)
								$ss = GeneralUtility::dirname(GeneralUtility::getIndpEnv('SCRIPT_NAME')) . '/' . $ss;
							}
							$pageRenderer->addCssInlineBlock('import_' . $key, '@import url("' . htmlspecialchars($ss) . '") ' . htmlspecialchars($cssFileConfig['media']) . ';', empty($cssFileConfig['disableCompression']), $cssFileConfig['forceOnTop'] ? TRUE : FALSE, '');
						} else {
							$pageRenderer->addCssFile(
								$ss,
								$cssFileConfig['alternate'] ? 'alternate stylesheet' : 'stylesheet',
								$cssFileConfig['media'] ?: 'all',
								$cssFileConfig['title'] ?: '',
								empty($cssFileConfig['disableCompression']),
								$cssFileConfig['forceOnTop'] ? TRUE : FALSE,
								$cssFileConfig['allWrap'],
								$cssFileConfig['excludeFromConcatenation'] ? TRUE : FALSE,
								$cssFileConfig['allWrap.']['splitChar']
							);
							unset($cssFileConfig);
						}
					}
				}
			}
		}
		if (is_array($GLOBALS['TSFE']->pSetup['includeCSSLibs.'])) {
			foreach ($GLOBALS['TSFE']->pSetup['includeCSSLibs.'] as $key => $CSSfile) {
				if (!is_array($CSSfile)) {
					$cssFileConfig = &$GLOBALS['TSFE']->pSetup['includeCSSLibs.'][$key . '.'];
					if (isset($cssFileConfig['if.']) && !$GLOBALS['TSFE']->cObj->checkIf($cssFileConfig['if.'])) {
						continue;
					}
					$ss = $cssFileConfig['external'] ? $CSSfile : $GLOBALS['TSFE']->tmpl->getFileName($CSSfile);
					if ($ss) {
						if ($cssFileConfig['import']) {
							if (!$cssFileConfig['external'] && $ss[0] !== '/') {
								// To fix MSIE 6 that cannot handle these as relative paths (according to Ben v Ende)
								$ss = GeneralUtility::dirname(GeneralUtility::getIndpEnv('SCRIPT_NAME')) . '/' . $ss;
							}
							$pageRenderer->addCssInlineBlock('import_' . $key, '@import url("' . htmlspecialchars($ss) . '") ' . htmlspecialchars($cssFileConfig['media']) . ';', empty($cssFileConfig['disableCompression']), $cssFileConfig['forceOnTop'] ? TRUE : FALSE, '');
						} else {
							$pageRenderer->addCssLibrary(
								$ss,
								$cssFileConfig['alternate'] ? 'alternate stylesheet' : 'stylesheet',
								$cssFileConfig['media'] ?: 'all',
								$cssFileConfig['title'] ?: '',
								empty($cssFileConfig['disableCompression']),
								$cssFileConfig['forceOnTop'] ? TRUE : FALSE,
								$cssFileConfig['allWrap'],
								$cssFileConfig['excludeFromConcatenation'] ? TRUE : FALSE,
								$cssFileConfig['allWrap.']['splitChar']
							);
							unset($cssFileConfig);
						}
					}
				}
			}
		}

		// Stylesheets
		$style = '';
		if ($GLOBALS['TSFE']->pSetup['insertClassesFromRTE']) {
			$pageTSConfig = $GLOBALS['TSFE']->getPagesTSconfig();
			$RTEclasses = $pageTSConfig['RTE.']['classes.'];
			if (is_array($RTEclasses)) {
				foreach ($RTEclasses as $RTEclassName => $RTEvalueArray) {
					if ($RTEvalueArray['value']) {
						$style .= '
.' . substr($RTEclassName, 0, -1) . ' {' . $RTEvalueArray['value'] . '}';
					}
				}
			}
			if ($GLOBALS['TSFE']->pSetup['insertClassesFromRTE.']['add_mainStyleOverrideDefs'] && is_array($pageTSConfig['RTE.']['default.']['mainStyleOverride_add.'])) {
				$mSOa_tList = GeneralUtility::trimExplode(',', strtoupper($GLOBALS['TSFE']->pSetup['insertClassesFromRTE.']['add_mainStyleOverrideDefs']), TRUE);
				foreach ($pageTSConfig['RTE.']['default.']['mainStyleOverride_add.'] as $mSOa_key => $mSOa_value) {
					if (!is_array($mSOa_value) && (in_array('*', $mSOa_tList) || in_array($mSOa_key, $mSOa_tList))) {
						$style .= '
' . $mSOa_key . ' {' . $mSOa_value . '}';
					}
				}
			}
		}
		// Setting body tag margins in CSS:
		if (isset($GLOBALS['TSFE']->pSetup['bodyTagMargins']) && $GLOBALS['TSFE']->pSetup['bodyTagMargins.']['useCSS']) {
			$margins = (int)$GLOBALS['TSFE']->pSetup['bodyTagMargins'];
			$style .= '
	BODY {margin: ' . $margins . 'px ' . $margins . 'px ' . $margins . 'px ' . $margins . 'px;}';
		}
		if ($GLOBALS['TSFE']->pSetup['adminPanelStyles']) {
			$style .= '

	/* Default styles for the Admin Panel */
	TABLE.typo3-adminPanel { border: 1px solid black; background-color: #F6F2E6; }
	TABLE.typo3-adminPanel TR.typo3-adminPanel-hRow TD { background-color: #9BA1A8; }
	TABLE.typo3-adminPanel TR.typo3-adminPanel-itemHRow TD { background-color: #ABBBB4; }
	TABLE.typo3-adminPanel TABLE, TABLE.typo3-adminPanel TD { border: 0px; }
	TABLE.typo3-adminPanel TD FONT { font-family: verdana; font-size: 10px; color: black; }
	TABLE.typo3-adminPanel TD A FONT { font-family: verdana; font-size: 10px; color: black; }
	TABLE.typo3-editPanel { border: 1px solid black; background-color: #F6F2E6; }
	TABLE.typo3-editPanel TD { border: 0px; }
			';
		}
		// CSS_inlineStyle from TS
		$style .= trim($GLOBALS['TSFE']->pSetup['CSS_inlineStyle']);
		$style .= $GLOBALS['TSFE']->cObj->cObjGet($GLOBALS['TSFE']->pSetup['cssInline.'], 'cssInline.');
		if (trim($style)) {
			if ($GLOBALS['TSFE']->config['config']['inlineStyle2TempFile']) {
				$pageRenderer->addCssFile(self::inline2TempFile($style, 'css'));
			} else {
				$pageRenderer->addCssInlineBlock('additionalTSFEInlineStyle', $style);
			}
		}
		// Javascript Libraries
		if (is_array($GLOBALS['TSFE']->pSetup['javascriptLibs.'])) {
			if ($GLOBALS['TSFE']->pSetup['javascriptLibs.']['SVG']) {
				$pageRenderer->loadSvg();
				if ($GLOBALS['TSFE']->pSetup['javascriptLibs.']['SVG.']['debug']) {
					$pageRenderer->enableSvgDebug();
				}
				if ($GLOBALS['TSFE']->pSetup['javascriptLibs.']['SVG.']['forceFlash']) {
					$pageRenderer->svgForceFlash();
				}
			}
			if ($GLOBALS['TSFE']->pSetup['javascriptLibs.']['Prototype']) {
				$pageRenderer->loadPrototype();
			}
			if ($GLOBALS['TSFE']->pSetup['javascriptLibs.']['Scriptaculous']) {
				$modules = $GLOBALS['TSFE']->pSetup['javascriptLibs.']['Scriptaculous.']['modules'] ?: '';
				$pageRenderer->loadScriptaculous($modules);
			}
			if ($GLOBALS['TSFE']->pSetup['javascriptLibs.']['ExtCore']) {
				$pageRenderer->loadExtCore();
				if ($GLOBALS['TSFE']->pSetup['javascriptLibs.']['ExtCore.']['debug']) {
					$pageRenderer->enableExtCoreDebug();
				}
			}
			// Include jQuery into the page renderer
			if (!empty($GLOBALS['TSFE']->pSetup['javascriptLibs.']['jQuery'])) {
				$jQueryTS = $GLOBALS['TSFE']->pSetup['javascriptLibs.']['jQuery.'];
				// Check if version / source is set, if not set variable to "NULL" to use the default of the page renderer
				$version = isset($jQueryTS['version']) ? $jQueryTS['version'] : NULL;
				$source = isset($jQueryTS['source']) ? $jQueryTS['source'] : NULL;
				// When "noConflict" is not set or "1" enable the default jQuery noConflict mode, otherwise disable the namespace
				if (!isset($jQueryTS['noConflict']) || !empty($jQueryTS['noConflict'])) {
					// Set namespace to the "noConflict.namespace" value if "noConflict.namespace" has a value
					if (!empty($jQueryTS['noConflict.']['namespace'])) {
						$namespace = $jQueryTS['noConflict.']['namespace'];
					} else {
						$namespace = \TYPO3\CMS\Core\Page\PageRenderer::JQUERY_NAMESPACE_DEFAULT_NOCONFLICT;
					}
				} else {
					$namespace = \TYPO3\CMS\Core\Page\PageRenderer::JQUERY_NAMESPACE_NONE;
				}
				$pageRenderer->loadJQuery($version, $source, $namespace);
			}
			if ($GLOBALS['TSFE']->pSetup['javascriptLibs.']['ExtJs']) {
				$css = $GLOBALS['TSFE']->pSetup['javascriptLibs.']['ExtJs.']['css'] ? TRUE : FALSE;
				$theme = $GLOBALS['TSFE']->pSetup['javascriptLibs.']['ExtJs.']['theme'] ? TRUE : FALSE;
				$adapter = $GLOBALS['TSFE']->pSetup['javascriptLibs.']['ExtJs.']['adapter'] ?: '';
				$pageRenderer->loadExtJs($css, $theme, $adapter);
				if ($GLOBALS['TSFE']->pSetup['javascriptLibs.']['ExtJs.']['debug']) {
					$pageRenderer->enableExtJsDebug();
				}
				if ($GLOBALS['TSFE']->pSetup['javascriptLibs.']['ExtJs.']['quickTips']) {
					$pageRenderer->enableExtJSQuickTips();
				}
			}
		}
		// JavaScript library files
		if (is_array($GLOBALS['TSFE']->pSetup['includeJSlibs.']) || is_array($GLOBALS['TSFE']->pSetup['includeJSLibs.'])) {
			if (!is_array($GLOBALS['TSFE']->pSetup['includeJSlibs.'])) {
				$GLOBALS['TSFE']->pSetup['includeJSlibs.'] = array();
			} else {
				GeneralUtility::deprecationLog('The property page.includeJSlibs is marked for deprecation and will be removed in TYPO3 CMS 8. Please use page.includeJSLibs (with a uppercase L) instead.');
			}
			if (!is_array($GLOBALS['TSFE']->pSetup['includeJSLibs.'])) {
				$GLOBALS['TSFE']->pSetup['includeJSLibs.'] = array();
			}
			ArrayUtility::mergeRecursiveWithOverrule(
				$GLOBALS['TSFE']->pSetup['includeJSLibs.'],
				$GLOBALS['TSFE']->pSetup['includeJSlibs.']
			);
			unset($GLOBALS['TSFE']->pSetup['includeJSlibs.']);
			foreach ($GLOBALS['TSFE']->pSetup['includeJSLibs.'] as $key => $JSfile) {
				if (!is_array($JSfile)) {
					if (isset($GLOBALS['TSFE']->pSetup['includeJSLibs.'][$key . '.']['if.']) && !$GLOBALS['TSFE']->cObj->checkIf($GLOBALS['TSFE']->pSetup['includeJSLibs.'][($key . '.')]['if.'])) {
						continue;
					}
					$ss = $GLOBALS['TSFE']->pSetup['includeJSLibs.'][$key . '.']['external'] ? $JSfile : $GLOBALS['TSFE']->tmpl->getFileName($JSfile);
					if ($ss) {
						$jsFileConfig = &$GLOBALS['TSFE']->pSetup['includeJSLibs.'][$key . '.'];
						$type = $jsFileConfig['type'];
						if (!$type) {
							$type = 'text/javascript';
						}

						$pageRenderer->addJsLibrary(
							$key,
							$ss,
							$type,
							empty($jsFileConfig['disableCompression']),
							$jsFileConfig['forceOnTop'] ? TRUE : FALSE,
							$jsFileConfig['allWrap'],
							$jsFileConfig['excludeFromConcatenation'] ? TRUE : FALSE,
							$jsFileConfig['allWrap.']['splitChar'],
							$jsFileConfig['async'] ? TRUE : FALSE
						);
						unset($jsFileConfig);
					}
				}
			}
		}
		if (is_array($GLOBALS['TSFE']->pSetup['includeJSFooterlibs.'])) {
			foreach ($GLOBALS['TSFE']->pSetup['includeJSFooterlibs.'] as $key => $JSfile) {
				if (!is_array($JSfile)) {
					if (isset($GLOBALS['TSFE']->pSetup['includeJSFooterlibs.'][$key . '.']['if.']) && !$GLOBALS['TSFE']->cObj->checkIf($GLOBALS['TSFE']->pSetup['includeJSFooterlibs.'][($key . '.')]['if.'])) {
						continue;
					}
					$ss = $GLOBALS['TSFE']->pSetup['includeJSFooterlibs.'][$key . '.']['external'] ? $JSfile : $GLOBALS['TSFE']->tmpl->getFileName($JSfile);
					if ($ss) {
						$jsFileConfig = &$GLOBALS['TSFE']->pSetup['includeJSFooterlibs.'][$key . '.'];
						$type = $jsFileConfig['type'];
						if (!$type) {
							$type = 'text/javascript';
						}
						$pageRenderer->addJsFooterLibrary(
							$key,
							$ss,
							$type,
							empty($jsFileConfig['disableCompression']),
							$jsFileConfig['forceOnTop'] ? TRUE : FALSE,
							$jsFileConfig['allWrap'],
							$jsFileConfig['excludeFromConcatenation'] ? TRUE : FALSE,
							$jsFileConfig['allWrap.']['splitChar'],
							$jsFileConfig['async'] ? TRUE : FALSE
						);
						unset($jsFileConfig);
					}
				}
			}
		}
		// JavaScript files
		if (is_array($GLOBALS['TSFE']->pSetup['includeJS.'])) {
			foreach ($GLOBALS['TSFE']->pSetup['includeJS.'] as $key => $JSfile) {
				if (!is_array($JSfile)) {
					if (isset($GLOBALS['TSFE']->pSetup['includeJS.'][$key . '.']['if.']) && !$GLOBALS['TSFE']->cObj->checkIf($GLOBALS['TSFE']->pSetup['includeJS.'][($key . '.')]['if.'])) {
						continue;
					}
					$ss = $GLOBALS['TSFE']->pSetup['includeJS.'][$key . '.']['external'] ? $JSfile : $GLOBALS['TSFE']->tmpl->getFileName($JSfile);
					if ($ss) {
						$jsConfig = &$GLOBALS['TSFE']->pSetup['includeJS.'][$key . '.'];
						$type = $jsConfig['type'];
						if (!$type) {
							$type = 'text/javascript';
						}
						$pageRenderer->addJsFile(
							$ss,
							$type,
							empty($jsConfig['disableCompression']),
							$jsConfig['forceOnTop'] ? TRUE : FALSE,
							$jsConfig['allWrap'],
							$jsConfig['excludeFromConcatenation'] ? TRUE : FALSE,
							$jsConfig['allWrap.']['splitChar'],
							$jsConfig['async'] ? TRUE : FALSE
						);
						unset($jsConfig);
					}
				}
			}
		}
		if (is_array($GLOBALS['TSFE']->pSetup['includeJSFooter.'])) {
			foreach ($GLOBALS['TSFE']->pSetup['includeJSFooter.'] as $key => $JSfile) {
				if (!is_array($JSfile)) {
					if (isset($GLOBALS['TSFE']->pSetup['includeJSFooter.'][$key . '.']['if.']) && !$GLOBALS['TSFE']->cObj->checkIf($GLOBALS['TSFE']->pSetup['includeJSFooter.'][($key . '.')]['if.'])) {
						continue;
					}
					$ss = $GLOBALS['TSFE']->pSetup['includeJSFooter.'][$key . '.']['external'] ? $JSfile : $GLOBALS['TSFE']->tmpl->getFileName($JSfile);
					if ($ss) {
						$jsConfig = &$GLOBALS['TSFE']->pSetup['includeJSFooter.'][$key . '.'];
						$type = $jsConfig['type'];
						if (!$type) {
							$type = 'text/javascript';
						}
						$pageRenderer->addJsFooterFile(
							$ss,
							$type,
							empty($jsConfig['disableCompression']),
							$jsConfig['forceOnTop'] ? TRUE : FALSE,
							$jsConfig['allWrap'],
							$jsConfig['excludeFromConcatenation'] ? TRUE : FALSE,
							$jsConfig['allWrap.']['splitChar'],
							$jsConfig['async'] ? TRUE : FALSE
						);
						unset($jsConfig);
					}
				}
			}
		}
		// Headerdata
		if (is_array($GLOBALS['TSFE']->pSetup['headerData.'])) {
			$pageRenderer->addHeaderData($GLOBALS['TSFE']->cObj->cObjGet($GLOBALS['TSFE']->pSetup['headerData.'], 'headerData.'));
		}
		// Footerdata
		if (is_array($GLOBALS['TSFE']->pSetup['footerData.'])) {
			$pageRenderer->addFooterData($GLOBALS['TSFE']->cObj->cObjGet($GLOBALS['TSFE']->pSetup['footerData.'], 'footerData.'));
		}
		static::generatePageTitle();

		$metaTagsHtml = static::generateMetaTagHtml(
			isset($GLOBALS['TSFE']->pSetup['meta.']) ? $GLOBALS['TSFE']->pSetup['meta.'] : array(),
			$GLOBALS['TSFE']->xhtmlVersion,
			$GLOBALS['TSFE']->cObj
		);
		foreach ($metaTagsHtml as $metaTag) {
			$pageRenderer->addMetaTag($metaTag);
		}

		unset($GLOBALS['TSFE']->additionalHeaderData['JSCode']);
		if (is_array($GLOBALS['TSFE']->config['INTincScript'])) {
			$GLOBALS['TSFE']->additionalHeaderData['JSCode'] = $GLOBALS['TSFE']->JSCode;
			// Storing the JSCode vars...
			$GLOBALS['TSFE']->config['INTincScript_ext']['divKey'] = $GLOBALS['TSFE']->uniqueHash();
			$GLOBALS['TSFE']->config['INTincScript_ext']['additionalHeaderData'] = $GLOBALS['TSFE']->additionalHeaderData;
			// Storing the header-data array
			$GLOBALS['TSFE']->config['INTincScript_ext']['additionalFooterData'] = $GLOBALS['TSFE']->additionalFooterData;
			// Storing the footer-data array
			$GLOBALS['TSFE']->config['INTincScript_ext']['additionalJavaScript'] = $GLOBALS['TSFE']->additionalJavaScript;
			// Storing the JS-data array
			$GLOBALS['TSFE']->config['INTincScript_ext']['additionalCSS'] = $GLOBALS['TSFE']->additionalCSS;
			// Storing the Style-data array
			$GLOBALS['TSFE']->additionalHeaderData = array('<!--HD_' . $GLOBALS['TSFE']->config['INTincScript_ext']['divKey'] . '-->');
			// Clearing the array
			$GLOBALS['TSFE']->additionalFooterData = array('<!--FD_' . $GLOBALS['TSFE']->config['INTincScript_ext']['divKey'] . '-->');
			// Clearing the array
			$GLOBALS['TSFE']->divSection .= '<!--TDS_' . $GLOBALS['TSFE']->config['INTincScript_ext']['divKey'] . '-->';
		} else {
			$GLOBALS['TSFE']->INTincScript_loadJSCode();
		}
		$JSef = self::JSeventFunctions();
		$scriptJsCode = $JSef[0];

		if ($GLOBALS['TSFE']->spamProtectEmailAddresses && $GLOBALS['TSFE']->spamProtectEmailAddresses !== 'ascii') {
			$scriptJsCode = '
			// decrypt helper function
		function decryptCharcode(n,start,end,offset) {
			n = n + offset;
			if (offset > 0 && n > end) {
				n = start + (n - end - 1);
			} else if (offset < 0 && n < start) {
				n = end - (start - n - 1);
			}
			return String.fromCharCode(n);
		}
			// decrypt string
		function decryptString(enc,offset) {
			var dec = "";
			var len = enc.length;
			for(var i=0; i < len; i++) {
				var n = enc.charCodeAt(i);
				if (n >= 0x2B && n <= 0x3A) {
					dec += decryptCharcode(n,0x2B,0x3A,offset);	// 0-9 . , - + / :
				} else if (n >= 0x40 && n <= 0x5A) {
					dec += decryptCharcode(n,0x40,0x5A,offset);	// A-Z @
				} else if (n >= 0x61 && n <= 0x7A) {
					dec += decryptCharcode(n,0x61,0x7A,offset);	// a-z
				} else {
					dec += enc.charAt(i);
				}
			}
			return dec;
		}
			// decrypt spam-protected emails
		function linkTo_UnCryptMailto(s) {
			location.href = decryptString(s,' . $GLOBALS['TSFE']->spamProtectEmailAddresses * -1 . ');
		}
		';
		}
		// Add inline JS
		$inlineJS = '';
		// defined in php
		if (is_array($GLOBALS['TSFE']->inlineJS)) {
			foreach ($GLOBALS['TSFE']->inlineJS as $key => $val) {
				if (!is_array($val)) {
					$inlineJS .= LF . $val . LF;
				}
			}
		}
		// defined in TS with page.inlineJS
		// Javascript inline code
		$inline = $GLOBALS['TSFE']->cObj->cObjGet($GLOBALS['TSFE']->pSetup['jsInline.'], 'jsInline.');
		if ($inline) {
			$inlineJS .= LF . $inline . LF;
		}
		// Javascript inline code for Footer
		$inlineFooterJs = $GLOBALS['TSFE']->cObj->cObjGet($GLOBALS['TSFE']->pSetup['jsFooterInline.'], 'jsFooterInline.');
		// Should minify?
		if ($GLOBALS['TSFE']->config['config']['compressJs']) {
			$pageRenderer->enableCompressJavascript();
			$minifyErrorScript = ($minifyErrorInline = '');
			$scriptJsCode = GeneralUtility::minifyJavaScript($scriptJsCode, $minifyErrorScript);
			if ($minifyErrorScript) {
				$GLOBALS['TT']->setTSlogMessage($minifyErrorScript, 3);
			}
			if ($inlineJS) {
				$inlineJS = GeneralUtility::minifyJavaScript($inlineJS, $minifyErrorInline);
				if ($minifyErrorInline) {
					$GLOBALS['TT']->setTSlogMessage($minifyErrorInline, 3);
				}
			}
			if ($inlineFooterJs) {
				$inlineFooterJs = GeneralUtility::minifyJavaScript($inlineFooterJs, $minifyErrorInline);
				if ($minifyErrorInline) {
					$GLOBALS['TT']->setTSlogMessage($minifyErrorInline, 3);
				}
			}
		}
		if (!$GLOBALS['TSFE']->config['config']['removeDefaultJS']) {
			// inlude default and inlineJS
			if ($scriptJsCode) {
				$pageRenderer->addJsInlineCode('_scriptCode', $scriptJsCode, $GLOBALS['TSFE']->config['config']['compressJs']);
			}
			if ($inlineJS) {
				$pageRenderer->addJsInlineCode('TS_inlineJS', $inlineJS, $GLOBALS['TSFE']->config['config']['compressJs']);
			}
			if ($inlineFooterJs) {
				$pageRenderer->addJsFooterInlineCode('TS_inlineFooter', $inlineFooterJs, $GLOBALS['TSFE']->config['config']['compressJs']);
			}
		} elseif ($GLOBALS['TSFE']->config['config']['removeDefaultJS'] === 'external') {
			/*
			 * This keeps inlineJS from *_INT Objects from being moved to external files.
			 * At this point in frontend rendering *_INT Objects only have placeholders instead
			 * of actual content so moving these placeholders to external files would
			 *     a) break the JS file (syntax errors due to the placeholders)
			 *     b) the needed JS would never get included to the page
			 * Therefore inlineJS from *_INT Objects must not be moved to external files but
			 * kept internal.
			 */
			$inlineJSint = '';
			self::stripIntObjectPlaceholder($inlineJS, $inlineJSint);
			if ($inlineJSint) {
				$pageRenderer->addJsInlineCode('TS_inlineJSint', $inlineJSint, $GLOBALS['TSFE']->config['config']['compressJs']);
			}
			if (trim($scriptJsCode . $inlineJS)) {
				$pageRenderer->addJsFile(self::inline2TempFile($scriptJsCode . $inlineJS, 'js'), 'text/javascript', $GLOBALS['TSFE']->config['config']['compressJs']);
			}
			if ($inlineFooterJs) {
				$inlineFooterJSint = '';
				self::stripIntObjectPlaceholder($inlineFooterJs, $inlineFooterJSint);
				if ($inlineFooterJSint) {
					$pageRenderer->addJsFooterInlineCode('TS_inlineFooterJSint', $inlineFooterJSint, $GLOBALS['TSFE']->config['config']['compressJs']);
				}
				$pageRenderer->addJsFooterFile(self::inline2TempFile($inlineFooterJs, 'js'), 'text/javascript', $GLOBALS['TSFE']->config['config']['compressJs']);
			}
		} else {
			// Include only inlineJS
			if ($inlineJS) {
				$pageRenderer->addJsInlineCode('TS_inlineJS', $inlineJS, $GLOBALS['TSFE']->config['config']['compressJs']);
			}
			if ($inlineFooterJs) {
				$pageRenderer->addJsFooterInlineCode('TS_inlineFooter', $inlineFooterJs, $GLOBALS['TSFE']->config['config']['compressJs']);
			}
		}
		// ExtJS specific code
		if (is_array($GLOBALS['TSFE']->pSetup['inlineLanguageLabel.'])) {
			$pageRenderer->addInlineLanguageLabelArray($GLOBALS['TSFE']->pSetup['inlineLanguageLabel.']);
		}
		if (is_array($GLOBALS['TSFE']->pSetup['inlineSettings.'])) {
			$pageRenderer->addInlineSettingArray('TS', $GLOBALS['TSFE']->pSetup['inlineSettings.']);
		}
		if (is_array($GLOBALS['TSFE']->pSetup['extOnReady.'])) {
			$pageRenderer->addExtOnReadyCode($GLOBALS['TSFE']->cObj->cObjGet($GLOBALS['TSFE']->pSetup['extOnReady.'], 'extOnReady.'));
		}
		// Compression and concatenate settings
		if ($GLOBALS['TSFE']->config['config']['compressCss']) {
			$pageRenderer->enableCompressCss();
		}
		if ($GLOBALS['TSFE']->config['config']['compressJs']) {
			$pageRenderer->enableCompressJavascript();
		}
		if ($GLOBALS['TSFE']->config['config']['concatenateCss']) {
			$pageRenderer->enableConcatenateCss();
		}
		if ($GLOBALS['TSFE']->config['config']['concatenateJs']) {
			$pageRenderer->enableConcatenateJavascript();
		}
		// Backward compatibility for old configuration
		if ($GLOBALS['TSFE']->config['config']['concatenateJsAndCss']) {
			$pageRenderer->enableConcatenateFiles();
		}
		// Add header data block
		if ($GLOBALS['TSFE']->additionalHeaderData) {
			$pageRenderer->addHeaderData(implode(LF, $GLOBALS['TSFE']->additionalHeaderData));
		}
		// Add footer data block
		if ($GLOBALS['TSFE']->additionalFooterData) {
			$pageRenderer->addFooterData(implode(LF, $GLOBALS['TSFE']->additionalFooterData));
		}
		// Header complete, now add content
		if ($GLOBALS['TSFE']->pSetup['frameSet.']) {
			$fs = GeneralUtility::makeInstance(\TYPO3\CMS\Frontend\Page\FramesetRenderer::class);
			$pageRenderer->addBodyContent($fs->make($GLOBALS['TSFE']->pSetup['frameSet.']));
			$pageRenderer->addBodyContent(LF . '<noframes>' . LF);
		}
		// Bodytag:
		if ($GLOBALS['TSFE']->config['config']['disableBodyTag']) {
			$bodyTag = '';
		} else {
			$defBT = $GLOBALS['TSFE']->pSetup['bodyTagCObject'] ? $GLOBALS['TSFE']->cObj->cObjGetSingle($GLOBALS['TSFE']->pSetup['bodyTagCObject'], $GLOBALS['TSFE']->pSetup['bodyTagCObject.'], 'bodyTagCObject') : '';
			if (!$defBT) {
				$defBT = $GLOBALS['TSFE']->defaultBodyTag;
			}
			$bodyTag = $GLOBALS['TSFE']->pSetup['bodyTag'] ? $GLOBALS['TSFE']->pSetup['bodyTag'] : $defBT;
			if ($bgImg = $GLOBALS['TSFE']->cObj->getImgResource($GLOBALS['TSFE']->pSetup['bgImg'], $GLOBALS['TSFE']->pSetup['bgImg.'])) {
				$bodyTag = preg_replace('/>$/', '', trim($bodyTag)) . ' background="' . $GLOBALS['TSFE']->absRefPrefix . $bgImg[3] . '">';
			}
			if (isset($GLOBALS['TSFE']->pSetup['bodyTagMargins'])) {
				$margins = (int)$GLOBALS['TSFE']->pSetup['bodyTagMargins'];
				if ($GLOBALS['TSFE']->pSetup['bodyTagMargins.']['useCSS']) {

				} else {
					$bodyTag = preg_replace('/>$/', '', trim($bodyTag)) . ' leftmargin="' . $margins . '" topmargin="' . $margins . '" marginwidth="' . $margins . '" marginheight="' . $margins . '">';
				}
			}
			if (trim($GLOBALS['TSFE']->pSetup['bodyTagAdd'])) {
				$bodyTag = preg_replace('/>$/', '', trim($bodyTag)) . ' ' . trim($GLOBALS['TSFE']->pSetup['bodyTagAdd']) . '>';
			}
			// Event functions
			if (count($JSef[1])) {
				$bodyTag = preg_replace('/>$/', '', trim($bodyTag)) . ' ' . trim(implode(' ', $JSef[1])) . '>';
			}
		}
		$pageRenderer->addBodyContent(LF . $bodyTag);
		// Div-sections
		if ($GLOBALS['TSFE']->divSection) {
			$pageRenderer->addBodyContent(LF . $GLOBALS['TSFE']->divSection);
		}
		// Page content
		$pageRenderer->addBodyContent(LF . $pageContent);
		if (!empty($GLOBALS['TSFE']->config['INTincScript']) && is_array($GLOBALS['TSFE']->config['INTincScript'])) {
			// Store the serialized pageRenderer in configuration
			$GLOBALS['TSFE']->config['INTincScript_ext']['pageRenderer'] = serialize($pageRenderer);
			// Render complete page, keep placeholders for JavaScript and CSS
			$GLOBALS['TSFE']->content = $pageRenderer->renderPageWithUncachedObjects($GLOBALS['TSFE']->config['INTincScript_ext']['divKey']);
		} else {
			// Render complete page
			$GLOBALS['TSFE']->content = $pageRenderer->render();
		}
		// Ending page
		if ($GLOBALS['TSFE']->pSetup['frameSet.']) {
			$GLOBALS['TSFE']->content .= LF . '</noframes>';
		}
	}

	/*************************
	 *
	 * Helper functions
	 * Remember: Calls internally must still be done on the non-instantiated class: PageGenerator::inline2TempFile()
	 *
	 *************************/
	/**
	 * Searches for placeholder created from *_INT cObjects, removes them from
	 * $searchString and merges them to $intObjects
	 *
	 * @param string $searchString The String which should be cleaned from int-object markers
	 * @param string $intObjects The String the found int-placeholders are moved to (for further processing)
	 */
	static protected function stripIntObjectPlaceholder(&$searchString, &$intObjects) {
		$tempArray = array();
		preg_match_all('/\\<\\!--INT_SCRIPT.[a-z0-9]*--\\>/', $searchString, $tempArray);
		$searchString = preg_replace('/\\<\\!--INT_SCRIPT.[a-z0-9]*--\\>/', '', $searchString);
		$intObjects = implode('', $tempArray[0]);
	}

	/**
	 * Writes string to a temporary file named after the md5-hash of the string
	 *
	 * @param string $str CSS styles / JavaScript to write to file.
	 * @param string $ext Extension: "css" or "js
	 * @return string <script> or <link> tag for the file.
	 */
	static public function inline2TempFile($str, $ext) {
		// Create filename / tags:
		$script = '';
		switch ($ext) {
			case 'js':
				$script = 'typo3temp/javascript_' . substr(md5($str), 0, 10) . '.js';
				break;
			case 'css':
				$script = 'typo3temp/stylesheet_' . substr(md5($str), 0, 10) . '.css';
				break;
		}
		// Write file:
		if ($script) {
			if (!@is_file((PATH_site . $script))) {
				GeneralUtility::writeFile(PATH_site . $script, $str);
			}
		}
		return $script;
	}

	/**
	 * Checks if the value defined in "config.linkVars" contains an allowed value. Otherwise, return FALSE which means the value will not be added to any links.
	 *
	 * @param string $haystack The string in which to find $needle
	 * @param string $needle The string to find in $haystack
	 * @return bool Returns TRUE if $needle matches or is found in $haystack
	 */
	static public function isAllowedLinkVarValue($haystack, $needle) {
		$OK = FALSE;
		// Integer
		if ($needle == 'int' || $needle == 'integer') {
			if (\TYPO3\CMS\Core\Utility\MathUtility::canBeInterpretedAsInteger($haystack)) {
				$OK = TRUE;
			}
		} elseif (preg_match('/^\\/.+\\/[imsxeADSUXu]*$/', $needle)) {
			// Regular expression, only "//" is allowed as delimiter
			if (@preg_match($needle, $haystack)) {
				$OK = TRUE;
			}
		} elseif (strstr($needle, '-')) {
			// Range
			if (\TYPO3\CMS\Core\Utility\MathUtility::canBeInterpretedAsInteger($haystack)) {
				$range = explode('-', $needle);
				if ($range[0] <= $haystack && $range[1] >= $haystack) {
					$OK = TRUE;
				}
			}
		} elseif (strstr($needle, '|')) {
			// List
			// Trim the input
			$haystack = str_replace(' ', '', $haystack);
			if (strstr('|' . $needle . '|', '|' . $haystack . '|')) {
				$OK = TRUE;
			}
		} elseif ((string)$needle === (string)$haystack) {
			// String comparison
			$OK = TRUE;
		}
		return $OK;
	}

	/**
	 * Generate title for page.
	 * Takes the settings [config][noPageTitle], [config][pageTitleFirst], [config][titleTagFunction]
	 * [config][pageTitleSeparator] and [config][noPageTitle] into account.
	 * Furthermore $GLOBALS[TSFE]->altPageTitle is observed.
	 *
	 * @return void
	 */
	static public function generatePageTitle() {
		$pageTitleSeparator = '';

		// check for a custom pageTitleSeparator, and perform stdWrap on it
		if (isset($GLOBALS['TSFE']->config['config']['pageTitleSeparator']) && $GLOBALS['TSFE']->config['config']['pageTitleSeparator'] !== '') {
			$pageTitleSeparator = $GLOBALS['TSFE']->config['config']['pageTitleSeparator'];

			if (isset($GLOBALS['TSFE']->config['config']['pageTitleSeparator.']) && is_array($GLOBALS['TSFE']->config['config']['pageTitleSeparator.'])) {
				$pageTitleSeparator = $GLOBALS['TSFE']->cObj->stdWrap($pageTitleSeparator, $GLOBALS['TSFE']->config['config']['pageTitleSeparator.']);
			} else {
				$pageTitleSeparator .= ' ';
			}
		}

		$titleTagContent = $GLOBALS['TSFE']->tmpl->printTitle(
			$GLOBALS['TSFE']->altPageTitle ?: $GLOBALS['TSFE']->page['title'],
			$GLOBALS['TSFE']->config['config']['noPageTitle'],
			$GLOBALS['TSFE']->config['config']['pageTitleFirst'],
			$pageTitleSeparator
		);
		if ($GLOBALS['TSFE']->config['config']['titleTagFunction']) {
			$titleTagContent = $GLOBALS['TSFE']->cObj->callUserFunction(
				$GLOBALS['TSFE']->config['config']['titleTagFunction'],
				array(),
				$titleTagContent
			);
		}
		// stdWrap around the title tag
		if (isset($GLOBALS['TSFE']->config['config']['pageTitle.']) && is_array($GLOBALS['TSFE']->config['config']['pageTitle.'])) {
			$titleTagContent = $GLOBALS['TSFE']->cObj->stdWrap($titleTagContent, $GLOBALS['TSFE']->config['config']['pageTitle.']);
		}
		if ($titleTagContent !== '' && (int)$GLOBALS['TSFE']->config['config']['noPageTitle'] !== self::NO_PAGE_TITLE) {
			$GLOBALS['TSFE']->getPageRenderer()->setTitle($titleTagContent);
		}
	}

	/**
	 * Generate meta tags from meta tag TypoScript
	 *
	 * @param array $metaTagTypoScript TypoScript configuration for meta tags (e.g. $GLOBALS['TSFE']->pSetup['meta.'])
	 * @param bool $xhtml Whether xhtml tag-style should be used. (e.g. pass $GLOBALS['TSFE']->xhtmlVersion here)
	 * @param ContentObjectRenderer $cObj
	 * @return array Array of HTML meta tags
	 */
	static protected function generateMetaTagHtml(array $metaTagTypoScript, $xhtml, ContentObjectRenderer $cObj) {
		// Add ending slash only to documents rendered as xhtml
		$endingSlash = $xhtml ? ' /' : '';

		$metaTags = array(
			'<meta name="generator" content="TYPO3 ' . TYPO3_branch . ' CMS"' . $endingSlash . '>'
		);

		/** @var TypoScriptService $typoScriptService */
		$typoScriptService = GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Service\TypoScriptService::class);
		$conf = $typoScriptService->convertTypoScriptArrayToPlainArray($metaTagTypoScript);
		foreach ($conf as $key => $properties) {
			if (is_array($properties)) {
				$nodeValue = isset($properties['_typoScriptNodeValue']) ? $properties['_typoScriptNodeValue'] : '';
				$value = trim($cObj->stdWrap($nodeValue, $metaTagTypoScript[$key . '.']));
			} else {
				$value = $properties;
			}
			if ($value !== '') {
				$attribute = 'name';
				if ( (is_array($properties) && !empty($properties['httpEquivalent'])) || strtolower($key) === 'refresh') {
					$attribute = 'http-equiv';
				}
				$metaTags[] = '<meta ' . $attribute . '="' . $key . '" content="' . htmlspecialchars($value) . '"' . $endingSlash . '>';
			}
		}
		return $metaTags;
	}

}
