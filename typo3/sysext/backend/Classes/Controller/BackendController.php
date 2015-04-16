<?php
namespace TYPO3\CMS\Backend\Controller;

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
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class for rendering the TYPO3 backend
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class BackendController {

	/**
	 * @var string
	 */
	protected $content = '';

	/**
	 * @var string
	 */
	protected $css = '';

	/**
	 * @var array
	 */
	protected $cssFiles = array();

	/**
	 * @var string
	 */
	protected $js = '';

	/**
	 * @var array
	 */
	protected $jsFiles = array();

	/**
	 * @var array
	 */
	protected $toolbarItems = array();

	/**
	 * @var int
	 */
	protected $menuWidth = 190;

	/**
	 * @var bool
	 */
	protected $debug;

	/**
	 * @var string
	 */
	protected $templatePath = 'EXT:backend/Resources/Private/Templates/';

	/**
	 * @var \TYPO3\CMS\Backend\Domain\Repository\Module\BackendModuleRepository
	 */
	protected $backendModuleRepository;

	/**
	 * @var \TYPO3\CMS\Backend\Module\ModuleLoader Object for loading backend modules
	 */
	protected $moduleLoader;

	/**
	 * @var \TYPO3\CMS\Core\Page\PageRenderer
	 */
	protected $pageRenderer;

	/**
	 * @return \TYPO3\CMS\Core\Page\PageRenderer
	 */
	public function getPageRenderer() {
		return $this->pageRenderer;
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->backendModuleRepository = GeneralUtility::makeInstance(\TYPO3\CMS\Backend\Domain\Repository\Module\BackendModuleRepository::class);

		// Set debug flag for BE development only
		$this->debug = (int)$GLOBALS['TYPO3_CONF_VARS']['BE']['debug'] === 1;
		// Initializes the backend modules structure for use later.
		$this->moduleLoader = GeneralUtility::makeInstance(\TYPO3\CMS\Backend\Module\ModuleLoader::class);
		$this->moduleLoader->load($GLOBALS['TBE_MODULES']);
		$this->pageRenderer = $GLOBALS['TBE_TEMPLATE']->getPageRenderer();
		$this->pageRenderer->loadExtJS();
		// included for the module menu JavaScript, please note that this is subject to change
		$this->pageRenderer->loadJquery();
		$this->pageRenderer->enableExtJSQuickTips();
		$this->pageRenderer->addJsInlineCode('consoleOverrideWithDebugPanel', '//already done', FALSE);
		$this->pageRenderer->addExtDirectCode();
		// Add default BE javascript
		$this->jsFiles = array(
			'locallang' => $this->getLocalLangFileName(),
			'modernizr' => 'contrib/modernizr/modernizr.min.js',
			'md5' => 'sysext/backend/Resources/Public/JavaScript/md5.js',
			'modulemenu' => 'sysext/backend/Resources/Public/JavaScript/modulemenu.js',
			'evalfield' => 'sysext/backend/Resources/Public/JavaScript/jsfunc.evalfield.js',
			'tabclosemenu' => 'js/extjs/ux/ext.ux.tabclosemenu.js',
			'notifications' => 'sysext/backend/Resources/Public/JavaScript/notifications.js',
			'backend' => 'sysext/backend/Resources/Public/JavaScript/backend.js',
			'debugPanel' => 'js/extjs/debugPanel.js',
			'viewport' => 'js/extjs/viewport.js',
			'iframepanel' => 'sysext/backend/Resources/Public/JavaScript/iframepanel.js',
			'backendcontentiframe' => 'js/extjs/backendcontentiframe.js',
			'viewportConfiguration' => 'js/extjs/viewportConfiguration.js',
			'util' => 'sysext/backend/Resources/Public/JavaScript/util.js'
		);
		if (!$this->debug) {
			$this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/LoginRefresh', 'function(LoginRefresh) {
				LoginRefresh.setLoginFramesetUrl(' . GeneralUtility::quoteJSvalue(BackendUtility::getModuleUrl('login_frameset')) . ');
			}');
		}

		// load FlashMessages functionality
		$this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/FlashMessages');

		// load Modals
		$this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/Modal');

		// load Legacy CSS Support
		$this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/LegacyCssClasses');

		// load the storage API and fill the UC into the PersistentStorage, so no additional AJAX call is needed
		$this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/Storage', 'function(Storage) {
			Storage.Persistent.load(' . json_encode($GLOBALS['BE_USER']->uc) . ');
		}');
		$this->css = '';

		$this->initializeToolbarItems();
		if (isset($GLOBALS['TBE_STYLES']['dims']['leftMenuFrameW'])) {
			$this->menuWidth = (int)$GLOBALS['TBE_STYLES']['dims']['leftMenuFrameW'];
		}
		$this->executeHook('constructPostProcess');
	}

	/**
	 * Initialize toolbar item objects
	 *
	 * @throws \RuntimeException
	 * @return void
	 */
	protected function initializeToolbarItems() {
		$toolbarItemInstances = array();
		$classNameRegistry = $GLOBALS['TYPO3_CONF_VARS']['BE']['toolbarItems'];
		foreach ($classNameRegistry as $className) {
			$toolbarItemInstance = GeneralUtility::makeInstance($className);
			if (!$toolbarItemInstance instanceof \TYPO3\CMS\Backend\Toolbar\ToolbarItemInterface) {
				throw new \RuntimeException(
					'class ' . $className . ' is registered as toolbar item but does not implement'
						. \TYPO3\CMS\Backend\Toolbar\ToolbarItemInterface::class,
					1415958218
				);
			}
			$index = (int)$toolbarItemInstance->getIndex();
			if ($index < 0 || $index > 100) {
				throw new \RuntimeException(
					'getIndex() must return an integer between 0 and 100',
					1415968498
				);
			}
			// Find next free position in array
			while(array_key_exists($index, $toolbarItemInstances)) {
				$index++;
			}
			$toolbarItemInstances[$index] = $toolbarItemInstance;
		}
		ksort($toolbarItemInstances);
		$this->toolbarItems = $toolbarItemInstances;
	}

	/**
	 * Main function generating the BE scaffolding
	 *
	 * @return void
	 */
	public function render() {
		$this->executeHook('renderPreProcess');

		// Prepare the scaffolding, at this point extension may still add javascript and css
		$view = $this->getFluidTemplateObject($this->templatePath . 'Backend/Main.html');
		// @todo: kick logo view class and move all logic to Fluid
		$view->assign('logo', GeneralUtility::makeInstance(\TYPO3\CMS\Backend\View\LogoView::class)->render());
		$view->assign('moduleMenu', $this->generateModuleMenu());
		$view->assign('toolbar', $this->renderToolbar());

		/******************************************************
		 * Now put the complete backend document together
		 ******************************************************/
		foreach ($this->cssFiles as $cssFileName => $cssFile) {
			$this->pageRenderer->addCssFile($cssFile);
			// Load additional css files to overwrite existing core styles
			if (!empty($GLOBALS['TBE_STYLES']['stylesheets'][$cssFileName])) {
				$this->pageRenderer->addCssFile($GLOBALS['TBE_STYLES']['stylesheets'][$cssFileName]);
			}
		}
		if (!empty($this->css)) {
			$this->pageRenderer->addCssInlineBlock('BackendInlineCSS', $this->css);
		}
		foreach ($this->jsFiles as $jsFile) {
			$this->pageRenderer->addJsFile($jsFile);
		}
		$this->generateJavascript();
		$this->pageRenderer->addJsInlineCode('BackendInlineJavascript', $this->js, FALSE);
		$this->loadResourcesForRegisteredNavigationComponents();

		// Add state provider
		$GLOBALS['TBE_TEMPLATE']->setExtDirectStateProvider();
		$states = $GLOBALS['BE_USER']->uc['BackendComponents']['States'];
		// Save states in BE_USER->uc
		$extOnReadyCode = '
			Ext.state.Manager.setProvider(new TYPO3.state.ExtDirectProvider({
				key: "BackendComponents.States",
				autoRead: false
			}));
		';

		if ($states) {
			$extOnReadyCode .= 'Ext.state.Manager.getProvider().initState(' . json_encode($states) . ');';
		}

		$extOnReadyCode .= '
			TYPO3.Backend = new TYPO3.Viewport(TYPO3.Viewport.configuration);
			if (typeof console === "undefined") {
				console = TYPO3.Backend.DebugConsole;
			}
			TYPO3.ContextHelpWindow.init(' . GeneralUtility::quoteJSvalue(BackendUtility::getModuleUrl('help_cshmanual')) . ');';
		$this->pageRenderer->addExtOnReadyCode($extOnReadyCode);
		// Set document title:
		$title = $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'] ? $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'] . ' [TYPO3 CMS ' . TYPO3_version . ']' : 'TYPO3 CMS ' . TYPO3_version;
		// Renders the module page
		$this->content = $GLOBALS['TBE_TEMPLATE']->render($title, $view->render());
		$hookConfiguration = array('content' => &$this->content);
		$this->executeHook('renderPostProcess', $hookConfiguration);
		echo $this->content;
	}

	/**
	 * Loads the css and javascript files of all registered navigation widgets
	 *
	 * @return void
	 */
	protected function loadResourcesForRegisteredNavigationComponents() {
		if (!is_array($GLOBALS['TBE_MODULES']['_navigationComponents'])) {
			return;
		}
		$loadedComponents = array();
		foreach ($GLOBALS['TBE_MODULES']['_navigationComponents'] as $module => $info) {
			if (in_array($info['componentId'], $loadedComponents)) {
				continue;
			}
			$loadedComponents[] = $info['componentId'];
			$component = strtolower(substr($info['componentId'], strrpos($info['componentId'], '-') + 1));
			$componentDirectory = 'components/' . $component . '/';
			if ($info['isCoreComponent']) {
				$absoluteComponentPath = PATH_site . 'typo3/js/extjs/' . $componentDirectory;
				$relativeComponentPath = '../' . str_replace(PATH_site, '', $absoluteComponentPath);
			} else {
				$absoluteComponentPath = ExtensionManagementUtility::extPath($info['extKey']) . $componentDirectory;
				$relativeComponentPath = ExtensionManagementUtility::extRelPath($info['extKey']) . $componentDirectory;
			}
			$cssFiles = GeneralUtility::getFilesInDir($absoluteComponentPath . 'css/', 'css');
			if (file_exists($absoluteComponentPath . 'css/loadorder.txt')) {
				// Don't allow inclusion outside directory
				$loadOrder = str_replace('../', '', GeneralUtility::getUrl($absoluteComponentPath . 'css/loadorder.txt'));
				$cssFilesOrdered = GeneralUtility::trimExplode(LF, $loadOrder, TRUE);
				$cssFiles = array_merge($cssFilesOrdered, $cssFiles);
			}
			foreach ($cssFiles as $cssFile) {
				$this->pageRenderer->addCssFile($relativeComponentPath . 'css/' . $cssFile);
			}
			$jsFiles = GeneralUtility::getFilesInDir($absoluteComponentPath . 'javascript/', 'js');
			if (file_exists($absoluteComponentPath . 'javascript/loadorder.txt')) {
				// Don't allow inclusion outside directory
				$loadOrder = str_replace('../', '', GeneralUtility::getUrl($absoluteComponentPath . 'javascript/loadorder.txt'));
				$jsFilesOrdered = GeneralUtility::trimExplode(LF, $loadOrder, TRUE);
				$jsFiles = array_merge($jsFilesOrdered, $jsFiles);
			}
			foreach ($jsFiles as $jsFile) {
				$this->pageRenderer->addJsFile($relativeComponentPath . 'javascript/' . $jsFile);
			}
			$this->pageRenderer->addInlineSetting('RecordHistory', 'moduleUrl', BackendUtility::getModuleUrl('record_history'));
		}
	}

	/**
	 * Renders the items in the top toolbar
	 *
	 * @return string top toolbar elements as HTML
	 */
	protected function renderToolbar() {
		$toolbar = array();
		foreach ($this->toolbarItems as $toolbarItem) {
			/** @var \TYPO3\CMS\Backend\Toolbar\ToolbarItemInterface $toolbarItem */
			if ($toolbarItem->checkAccess()) {
				$hasDropDown = (bool)$toolbarItem->hasDropDown();
				$additionalAttributes = (array)$toolbarItem->getAdditionalAttributes();

				$liAttributes = array();

				// Merge class: Add dropdown class if hasDropDown, add classes from additonal attributes
				$classes = array();
				if ($hasDropDown) {
					$classes[] = 'dropdown';
				}
				if (isset($additionalAttributes['class'])) {
					$classes[] = $additionalAttributes['class'];
					unset($additionalAttributes['class']);
				}
				$liAttributes[] = 'class="' . implode(' ', $classes) . '"';

				// Add further attributes
				foreach($additionalAttributes as $name => $value) {
					$liAttributes[] = $name . '="' . $value . '"';
				}

				// Create a unique id from class name
				$className = get_class($toolbarItem);
				$className = GeneralUtility::underscoredToLowerCamelCase($className);
				$className = GeneralUtility::camelCaseToLowerCaseUnderscored($className);
				$className = str_replace(array('_', '\\'), '-', $className);
				$liAttributes[] = 'id="' . $className . '"';

				$toolbar[] = '<li ' . implode(' ', $liAttributes) . '>';

				if ($hasDropDown) {
					$toolbar[] = '<a href="#" class="dropdown-toggle" data-toggle="dropdown">';
					$toolbar[] = $toolbarItem->getItem();
					$toolbar[] = '</a>';
					$toolbar[] = '<div class="dropdown-menu" role="menu">';
					$toolbar[] = $toolbarItem->getDropDown();
					$toolbar[] = '</div>';
				} else {
					$toolbar[] = $toolbarItem->getItem();
				}
				$toolbar[] = '</li>';
			}
		}
		return implode(LF, $toolbar);
	}

	/**
	 * Returns the file name  to the LLL JavaScript, containing the localized labels,
	 * which can be used in JavaScript code.
	 *
	 * @return string File name of the JS file, relative to TYPO3_mainDir
	 */
	protected function getLocalLangFileName() {
		$code = $this->generateLocalLang();
		$filePath = 'typo3temp/locallang-BE-' . sha1($code) . '.js';
		if (!file_exists((PATH_site . $filePath))) {
			// writeFileToTypo3tempDir() returns NULL on success (please double-read!)
			if (GeneralUtility::writeFileToTypo3tempDir(PATH_site . $filePath, $code) !== NULL) {
				throw new \RuntimeException('LocalLangFile could not be written to ' . $filePath, 1295193026);
			}
		}
		return '../' . $filePath;
	}

	/**
	 * Reads labels required in JavaScript code from the localization system and returns them as JSON
	 * array in TYPO3.LLL.
	 *
	 * @return string JavaScript code containing the LLL labels in TYPO3.LLL
	 */
	protected function generateLocalLang() {
		$coreLabels = array(
			'waitTitle' => $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:mess.refresh_login_logging_in'),
			'refresh_login_failed' => $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:mess.refresh_login_failed'),
			'refresh_login_failed_message' => $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:mess.refresh_login_failed_message'),
			'refresh_login_title' => sprintf($GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:mess.refresh_login_title'), htmlspecialchars($GLOBALS['BE_USER']->user['username'])),
			'login_expired' => $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:mess.login_expired'),
			'refresh_login_username' => $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:mess.refresh_login_username'),
			'refresh_login_password' => $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:mess.refresh_login_password'),
			'refresh_login_emptyPassword' => $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:mess.refresh_login_emptyPassword'),
			'refresh_login_button' => $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:mess.refresh_login_button'),
			'refresh_logout_button' => $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:mess.refresh_logout_button'),
			'please_wait' => $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:mess.please_wait'),
			'loadingIndicator' => $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:loadingIndicator'),
			'be_locked' => $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:mess.be_locked'),
			'refresh_login_countdown_singular' => $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:mess.refresh_login_countdown_singular'),
			'refresh_login_countdown' => $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:mess.refresh_login_countdown'),
			'login_about_to_expire' => $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:mess.login_about_to_expire'),
			'login_about_to_expire_title' => $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:mess.login_about_to_expire_title'),
			'refresh_login_refresh_button' => $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:mess.refresh_login_refresh_button'),
			'refresh_direct_logout_button' => $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:mess.refresh_direct_logout_button'),
			'tabs_closeAll' => $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:tabs.closeAll'),
			'tabs_closeOther' => $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:tabs.closeOther'),
			'tabs_close' => $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:tabs.close'),
			'tabs_openInBrowserWindow' => $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:tabs.openInBrowserWindow'),
			'csh_tooltip_loading' => $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:csh_tooltip_loading')
		);
		$labels = array(
			'fileUpload' => array(
				'windowTitle',
				'buttonSelectFiles',
				'buttonCancelAll',
				'infoComponentMaxFileSize',
				'infoComponentFileUploadLimit',
				'infoComponentFileTypeLimit',
				'infoComponentOverrideFiles',
				'processRunning',
				'uploadWait',
				'uploadStarting',
				'uploadProgress',
				'uploadSuccess',
				'errorQueueLimitExceeded',
				'errorQueueFileSizeLimit',
				'errorQueueZeroByteFile',
				'errorQueueInvalidFiletype',
				'errorUploadHttp',
				'errorUploadMissingUrl',
				'errorUploadIO',
				'errorUploadSecurityError',
				'errorUploadLimit',
				'errorUploadFailed',
				'errorUploadFileIDNotFound',
				'errorUploadFileValidation',
				'errorUploadFileCancelled',
				'errorUploadStopped',
				'allErrorMessageTitle',
				'allErrorMessageText',
				'allError401',
				'allError2038'
			),
			'liveSearch' => array(
				'title',
				'helpTitle',
				'emptyText',
				'loadingText',
				'listEmptyText',
				'showAllResults',
				'helpDescription',
				'helpDescriptionPages',
				'helpDescriptionContent'
			),
			'viewPort' => array(
				'tooltipModuleMenuSplit',
				'tooltipNavigationContainerSplitDrag',
				'tooltipDebugPanelSplitDrag'
			)
		);
		$generatedLabels = array();
		$generatedLabels['core'] = $coreLabels;
		// First loop over all categories (fileUpload, liveSearch, ..)
		foreach ($labels as $categoryName => $categoryLabels) {
			// Then loop over every single label
			foreach ($categoryLabels as $label) {
				// LLL identifier must be called $categoryName_$label, e.g. liveSearch_loadingText
				$generatedLabels[$categoryName][$label] = $GLOBALS['LANG']->getLL($categoryName . '_' . $label);
			}
		}
		return 'TYPO3.LLL = ' . json_encode($generatedLabels) . ';';
	}

	/**
	 * Generates the JavaScript code for the backend.
	 *
	 * @return void
	 */
	protected function generateJavascript() {

		// Needed for FormEngine manipulation (date picker)
		$dateFormat = ($GLOBALS['TYPO3_CONF_VARS']['SYS']['USdateFormat'] ? array('MM-DD-YYYY', 'HH:mm MM-DD-YYYY') : array('DD-MM-YYYY', 'HH:mm DD-MM-YYYY'));
		$this->pageRenderer->addInlineSetting('DateTimePicker', 'DateFormat', $dateFormat);
		// define the window size of the element browser etc.
		$popupWindowSize = trim($GLOBALS['BE_USER']->getTSConfigVal('options.popupWindowSize'));
		if (!empty($popupWindowSize)) {
			list($popupWindowWidth, $popupWindowHeight) = GeneralUtility::trimExplode('x', $popupWindowSize);
		}
		$popupWindowWidth  = !empty($popupWindowWidth) ? (int)$popupWindowWidth : 700;
		$popupWindowHeight = !empty($popupWindowHeight) ? (int)$popupWindowHeight : 750;

		// define the window size of the popups within the RTE
		$rtePopupWindowSize = trim($GLOBALS['BE_USER']->getTSConfigVal('options.rte.popupWindowSize'));
		if (!empty($rtePopupWindowSize)) {
			list($rtePopupWindowWidth, $rtePopupWindowHeight) = GeneralUtility::trimExplode('x', $rtePopupWindowSize);
		}
		$rtePopupWindowWidth  = !empty($rtePopupWindowWidth) ? (int)$rtePopupWindowWidth : ($popupWindowWidth-200);
		$rtePopupWindowHeight = !empty($rtePopupWindowHeight) ? (int)$rtePopupWindowHeight : ($popupWindowHeight-250);

		$pathTYPO3 = GeneralUtility::dirname(GeneralUtility::getIndpEnv('SCRIPT_NAME')) . '/';
		// If another page module was specified, replace the default Page module with the new one
		$newPageModule = trim($GLOBALS['BE_USER']->getTSConfigVal('options.overridePageModule'));
		$pageModule = BackendUtility::isModuleSetInTBE_MODULES($newPageModule) ? $newPageModule : 'web_layout';
		if (!$GLOBALS['BE_USER']->check('modules', $pageModule)) {
			$pageModule = '';
		}
		// Determine security level from conf vars and default to super challenged
		if ($GLOBALS['TYPO3_CONF_VARS']['BE']['loginSecurityLevel']) {
			$this->loginSecurityLevel = $GLOBALS['TYPO3_CONF_VARS']['BE']['loginSecurityLevel'];
		} else {
			$this->loginSecurityLevel = 'superchallenged';
		}
		$t3Configuration = array(
			'siteUrl' => GeneralUtility::getIndpEnv('TYPO3_SITE_URL'),
			'PATH_typo3' => $pathTYPO3,
			'PATH_typo3_enc' => rawurlencode($pathTYPO3),
			'username' => htmlspecialchars($GLOBALS['BE_USER']->user['username']),
			'uniqueID' => GeneralUtility::shortMD5(uniqid('', TRUE)),
			'securityLevel' => $this->loginSecurityLevel,
			'TYPO3_mainDir' => TYPO3_mainDir,
			'pageModule' => $pageModule,
			'inWorkspace' => $GLOBALS['BE_USER']->workspace !== 0,
			'workspaceFrontendPreviewEnabled' => $GLOBALS['BE_USER']->user['workspace_preview'] ? 1 : 0,
			'veriCode' => $GLOBALS['BE_USER']->veriCode(),
			'denyFileTypes' => PHP_EXTENSIONS_DEFAULT,
			'moduleMenuWidth' => $this->menuWidth - 1,
			'topBarHeight' => isset($GLOBALS['TBE_STYLES']['dims']['topFrameH']) ? (int)$GLOBALS['TBE_STYLES']['dims']['topFrameH'] : 30,
			'showRefreshLoginPopup' => isset($GLOBALS['TYPO3_CONF_VARS']['BE']['showRefreshLoginPopup']) ? (int)$GLOBALS['TYPO3_CONF_VARS']['BE']['showRefreshLoginPopup'] : FALSE,
			'listModulePath' => ExtensionManagementUtility::extRelPath('recordlist') . 'mod1/',
			'debugInWindow' => $GLOBALS['BE_USER']->uc['debugInWindow'] ? 1 : 0,
			'ContextHelpWindows' => array(
				'width' => 600,
				'height' => 400
			),
			'PopupWindow' => array(
				'width' => $popupWindowWidth,
				'height' => $popupWindowHeight
			),
			'RTEPopupWindow' => array(
				'width' => $rtePopupWindowWidth,
				'height' => $rtePopupWindowHeight
			)
		);
		$this->js .= '
	TYPO3.configuration = ' . json_encode($t3Configuration) . ';

	/**
	 * TypoSetup object.
	 */
	function typoSetup() {	//
		this.PATH_typo3 = TYPO3.configuration.PATH_typo3;
		this.PATH_typo3_enc = TYPO3.configuration.PATH_typo3_enc;
		this.username = TYPO3.configuration.username;
		this.uniqueID = TYPO3.configuration.uniqueID;
		this.navFrameWidth = 0;
		this.securityLevel = TYPO3.configuration.securityLevel;
		this.veriCode = TYPO3.configuration.veriCode;
		this.denyFileTypes = TYPO3.configuration.denyFileTypes;
	}
	var TS = new typoSetup();
		//backwards compatibility
	/**
	 * Frameset Module object
	 *
	 * Used in main modules with a frameset for submodules to keep the ID between modules
	 * Typically that is set by something like this in a Web>* sub module:
	 *		if (top.fsMod) top.fsMod.recentIds["web"] = "\'.(int)$this->id.\'";
	 * 		if (top.fsMod) top.fsMod.recentIds["file"] = "...(file reference/string)...";
	 */
	function fsModules() {	//
		this.recentIds=new Array();					// used by frameset modules to track the most recent used id for list frame.
		this.navFrameHighlightedID=new Array();		// used by navigation frames to track which row id was highlighted last time
		this.currentMainLoaded="";
		this.currentBank="0";
	}
	var fsMod = new fsModules();

	top.goToModule = function(modName, cMR_flag, addGetVars) {
		TYPO3.ModuleMenu.App.showModule(modName, addGetVars);
	}
	' . $this->setStartupModule();
		// Check editing of page:
		$this->handlePageEditing();
	}

	/**
	 * Checking if the "&edit" variable was sent so we can open it for editing the page.
	 *
	 * @return void
	 */
	protected function handlePageEditing() {
		// EDIT page:
		$editId = preg_replace('/[^[:alnum:]_]/', '', GeneralUtility::_GET('edit'));
		$editRecord = '';
		if ($editId) {
			// Looking up the page to edit, checking permissions:
			$where = ' AND (' . $GLOBALS['BE_USER']->getPagePermsClause(2) . ' OR ' . $GLOBALS['BE_USER']->getPagePermsClause(16) . ')';
			if (\TYPO3\CMS\Core\Utility\MathUtility::canBeInterpretedAsInteger($editId)) {
				$editRecord = BackendUtility::getRecordWSOL('pages', $editId, '*', $where);
			} else {
				$records = BackendUtility::getRecordsByField('pages', 'alias', $editId, $where);
				if (is_array($records)) {
					$editRecord = reset($records);
					BackendUtility::workspaceOL('pages', $editRecord);
				}
			}
			// If the page was accessible, then let the user edit it.
			if (is_array($editRecord) && $GLOBALS['BE_USER']->isInWebMount($editRecord['uid'])) {
				// Setting JS code to open editing:
				$this->js .= '
		// Load page to edit:
	window.setTimeout("top.loadEditId(' . (int)$editRecord['uid'] . ');", 500);
			';
				// Checking page edit parameter:
				if (!$GLOBALS['BE_USER']->getTSConfigVal('options.bookmark_onEditId_dontSetPageTree')) {
					$bookmarkKeepExpanded = $GLOBALS['BE_USER']->getTSConfigVal('options.bookmark_onEditId_keepExistingExpanded');
					// Expanding page tree:
					BackendUtility::openPageTree((int)$editRecord['pid'], !$bookmarkKeepExpanded);
				}
			} else {
				$this->js .= '
		// Warning about page editing:
	alert(' . GeneralUtility::quoteJSvalue(sprintf($GLOBALS['LANG']->getLL('noEditPage'), $editId)) . ');
			';
			}
		}
	}

	/**
	 * Sets the startup module from either GETvars module and modParams or user configuration.
	 *
	 * @return string the JavaScript code for the startup module
	 */
	protected function setStartupModule() {
		$startModule = preg_replace('/[^[:alnum:]_]/', '', GeneralUtility::_GET('module'));
		if (!$startModule) {
			// start module on first login, will be removed once used the first time
			if (isset($GLOBALS['BE_USER']->uc['startModuleOnFirstLogin'])) {
				$startModule = $GLOBALS['BE_USER']->uc['startModuleOnFirstLogin'];
				unset($GLOBALS['BE_USER']->uc['startModuleOnFirstLogin']);
				$GLOBALS['BE_USER']->writeUC();
			} elseif ($GLOBALS['BE_USER']->uc['startModule']) {
				$startModule = $GLOBALS['BE_USER']->uc['startModule'];
			} elseif ($GLOBALS['BE_USER']->uc['startInTaskCenter']) {
				$startModule = 'user_task';
			}

			// check if the start module has additional parameters, so a redirect to a specific
			// action is possible
			if (strpos($startModule, '->') !== FALSE) {
				list($startModule, $startModuleParameters) = explode('->', $startModule, 2);
			}
		}

		$moduleParameters = GeneralUtility::_GET('modParams');
		// if no GET parameters are set, check if there are parameters given from the UC
		if (!$moduleParameters && $startModuleParameters) {
			$moduleParameters = $startModuleParameters;
		}

		if ($startModule) {
			return '
					// start in module:
				top.startInModule = [\'' . $startModule . '\', ' . GeneralUtility::quoteJSvalue($moduleParameters) . '];
			';
		} else {
			return '';
		}
	}

	/**
	 * Adds a javascript snippet to the backend
	 *
	 * @param string $javascript Javascript snippet
	 * @return void
	 * @throws \InvalidArgumentException
	 */
	public function addJavascript($javascript) {
		// @todo do we need more checks?
		if (!is_string($javascript)) {
			throw new \InvalidArgumentException('parameter $javascript must be of type string', 1195129553);
		}
		$this->js .= $javascript;
	}

	/**
	 * Adds a javscript file to the backend after it has been checked that it exists
	 *
	 * @param string $javascriptFile Javascript file reference
	 * @return bool TRUE if the javascript file was successfully added, FALSE otherwise
	 */
	public function addJavascriptFile($javascriptFile) {
		$jsFileAdded = FALSE;
		// @todo add more checks if necessary
		if (file_exists(GeneralUtility::resolveBackPath(PATH_typo3 . $javascriptFile))) {
			$this->jsFiles[] = $javascriptFile;
			$jsFileAdded = TRUE;
		}
		return $jsFileAdded;
	}

	/**
	 * Adds a css snippet to the backend
	 *
	 * @param string $css Css snippet
	 * @return void
	 */
	public function addCss($css) {
		if (!is_string($css)) {
			throw new \InvalidArgumentException('parameter $css must be of type string', 1195129642);
		}
		$this->css .= $css;
	}

	/**
	 * Adds a css file to the backend after it has been checked that it exists
	 *
	 * @param string $cssFileName The css file's name with out the .css ending
	 * @param string $cssFile Css file reference
	 * @return bool TRUE if the css file was added, FALSE otherwise
	 */
	public function addCssFile($cssFileName, $cssFile) {
		$cssFileAdded = FALSE;
		if (empty($this->cssFiles[$cssFileName])) {
			$this->cssFiles[$cssFileName] = $cssFile;
			$cssFileAdded = TRUE;
		}
		return $cssFileAdded;
	}

	/**
	 * Adds an item to the toolbar, the class file for the toolbar item must be loaded at this point
	 *
	 * @param string $toolbarItemName Toolbar item name, f.e. tx_toolbarExtension_coolItem
	 * @param string $toolbarItemClassName Toolbar item class name, f.e. tx_toolbarExtension_coolItem
	 * @return void
	 * @throws \UnexpectedValueException
	 * @deprecated since TYPO3 CMS 7, will be removed in TYPO3 CMS 8. Toolbar items are registered in $GLOBALS['TYPO3_CONF_VARS']['BE']['toolbarItems'] now.
	 */
	public function addToolbarItem($toolbarItemName, $toolbarItemClassName) {
		GeneralUtility::logDeprecatedFunction();
	}

	/**
	 * Executes defined hooks functions for the given identifier.
	 *
	 * These hook identifiers are valid:
	 * + constructPostProcess
	 * + renderPreProcess
	 * + renderPostProcess
	 *
	 * @param string $identifier Specific hook identifier
	 * @param array $hookConfiguration Additional configuration passed to hook functions
	 * @return void
	 */
	protected function executeHook($identifier, array $hookConfiguration = array()) {
		$options = &$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['typo3/backend.php'];
		if (isset($options[$identifier]) && is_array($options[$identifier])) {
			foreach ($options[$identifier] as $hookFunction) {
				GeneralUtility::callUserFunction($hookFunction, $hookConfiguration, $this);
			}
		}
	}

	/**
	 * loads all modules from the repository
	 * and renders it with a template
	 *
	 * @return string
	 */
	protected function generateModuleMenu() {
		// get all modules except the user modules for the side menu
		$moduleStorage = $this->backendModuleRepository->loadAllowedModules(array('user', 'help'));

		$view = $this->getFluidTemplateObject($this->templatePath . 'ModuleMenu/Main.html');
		$view->assign('modules', $moduleStorage);
		return $view->render();
	}

	/**
	 * Returns the Module menu for the AJAX API
	 *
	 * @param array $params
	 * @param \TYPO3\CMS\Core\Http\AjaxRequestHandler $ajaxRequestHandler
	 * @return void
	 */
	public function getModuleMenuForReload($params, $ajaxRequestHandler) {
		$content = $this->generateModuleMenu();
		$ajaxRequestHandler->addContent('menu', $content);
		$ajaxRequestHandler->setContentFormat('json');
	}

	/**
	 * returns a new standalone view, shorthand function
	 *
	 * @param string $templatePathAndFileName optional the path to set the template path and filename
	 * @return \TYPO3\CMS\Fluid\View\StandaloneView
	 */
	protected function getFluidTemplateObject($templatePathAndFileName = NULL) {
		$view = GeneralUtility::makeInstance(\TYPO3\CMS\Fluid\View\StandaloneView::class);
		if ($templatePathAndFileName) {
			$view->setTemplatePathAndFilename(GeneralUtility::getFileAbsFileName($templatePathAndFileName));
		}
		return $view;
	}

}
