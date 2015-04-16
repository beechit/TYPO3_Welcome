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
use TYPO3\CMS\Backend\Utility\IconUtility;
use TYPO3\CMS\Core\Html\HtmlParser;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\HttpUtility;

/**
 * Script Class for rendering the login form
 *
 * @author Kasper Skårhøj <kasperYYYY@typo3.com>
 */
class LoginController {

	const SIGNAL_RenderLoginForm = 'renderLoginForm';

	/**
	 * The URL to redirect to after login.
	 *
	 * @var string
	 */
	public $redirect_url;

	/**
	 * Defines which interface to load (from interface selector)
	 *
	 * @var string
	 */
	public $GPinterface;

	/**
	 * preset username
	 *
	 * @var string
	 */
	public $u;

	/**
	 * preset password
	 *
	 * @var string
	 */
	public $p;

	/**
	 * OpenID URL submitted by form
	 *
	 * @var string
	 */
	protected $openIdUrl;

	/**
	 * If "L" is "OUT", then any logged in used is logged out. If redirect_url is given, we redirect to it
	 *
	 * @var string
	 */
	public $L;

	/**
	 * Login-refresh boolean; The backend will call this script
	 * with this value set when the login is close to being expired
	 * and the form needs to be redrawn.
	 *
	 * @var bool
	 */
	public $loginRefresh;

	/**
	 * Value of forms submit button for login.
	 *
	 * @var string
	 */
	public $commandLI;

	/**
	 * Set to the redirect URL of the form (may be redirect_url or "backend.php")
	 *
	 * @var string
	 */
	public $redirectToURL;

	/**
	 * Content accumulation
	 *
	 * @var string
	 */
	public $content;

	/**
	 * A selector box for selecting value for "interface" may be rendered into this variable
	 *
	 * @var string
	 */
	public $interfaceSelector;

	/**
	 * A selector box for selecting value for "interface" may be rendered into this variable
	 * this will have an onchange action which will redirect the user to the selected interface right away
	 *
	 * @var string
	 */
	public $interfaceSelector_jump;

	/**
	 * A hidden field, if the interface is not set.
	 *
	 * @var string
	 */
	public $interfaceSelector_hidden;

	/**
	 * Additional hidden fields to be placed at the login form
	 *
	 * @var string
	 */
	public $addFields_hidden = '';

	/**
	 * Sets the level of security. *'normal' = clear-text. 'challenged' = hashed
	 * password/username from form in $formfield_uident. 'superchallenged' = hashed password hashed again with username.
	 *
	 * @var string
	 */
	public $loginSecurityLevel = 'superchallenged';

	/**
	 * @var \TYPO3\CMS\Extbase\SignalSlot\Dispatcher
	 */
	protected $signalSlotDispatcher;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Initialize the login box. Will also react on a &L=OUT flag and exit.
	 *
	 * @return void
	 */
	public function init() {
		// We need a PHP session session for most login levels
		session_start();
		$this->redirect_url = GeneralUtility::sanitizeLocalUrl(GeneralUtility::_GP('redirect_url'));
		$this->GPinterface = GeneralUtility::_GP('interface');
		// Grabbing preset username and password, for security reasons this feature only works if SSL is used
		if (GeneralUtility::getIndpEnv('TYPO3_SSL')) {
			$this->u = GeneralUtility::_GP('u');
			$this->p = GeneralUtility::_GP('p');
			$this->openIdUrl = GeneralUtility::_GP('openid_url');
		}
		// If "L" is "OUT", then any logged in is logged out. If redirect_url is given, we redirect to it
		$this->L = GeneralUtility::_GP('L');
		// Login
		$this->loginRefresh = GeneralUtility::_GP('loginRefresh');
		// Value of "Login" button. If set, the login button was pressed.
		$this->commandLI = GeneralUtility::_GP('commandLI');
		// Sets the level of security from conf vars
		if ($GLOBALS['TYPO3_CONF_VARS']['BE']['loginSecurityLevel']) {
			$this->loginSecurityLevel = $GLOBALS['TYPO3_CONF_VARS']['BE']['loginSecurityLevel'];
		}
		// Try to get the preferred browser language
		$preferredBrowserLanguage = $GLOBALS['LANG']->csConvObj->getPreferredClientLanguage(GeneralUtility::getIndpEnv('HTTP_ACCEPT_LANGUAGE'));
		// If we found a $preferredBrowserLanguage and it is not the default language and no be_user is logged in
		// initialize $GLOBALS['LANG'] again with $preferredBrowserLanguage
		if ($preferredBrowserLanguage !== 'default' && empty($GLOBALS['BE_USER']->user['uid'])) {
			$GLOBALS['LANG']->init($preferredBrowserLanguage);
		}
		$GLOBALS['LANG']->includeLLFile('EXT:lang/locallang_login.xlf');
		// Setting the redirect URL to "backend.php" if no alternative input is given
		$this->redirectToURL = $this->redirect_url ?: 'backend.php';
		// Do a logout if the command is set
		if ($this->L == 'OUT' && is_object($GLOBALS['BE_USER'])) {
			$GLOBALS['BE_USER']->logoff();
			HttpUtility::redirect($this->redirect_url);
		}
	}

	/**
	 * Main function - creating the login/logout form
	 *
	 * @return void
	 */
	public function main() {
		// Initialize template object:
		$GLOBALS['TBE_TEMPLATE']->moduleTemplate = $GLOBALS['TBE_TEMPLATE']->getHtmlTemplate('EXT:backend/Resources/Private/Templates/login.html');
		/** @var $pageRenderer \TYPO3\CMS\Core\Page\PageRenderer */
		$pageRenderer = $GLOBALS['TBE_TEMPLATE']->getPageRenderer();
		$pageRenderer->loadJquery();

		// support placeholders for IE9 and lower
		$clientInfo = GeneralUtility::clientInfo();
		if ($clientInfo['BROWSER'] == 'msie' && $clientInfo['VERSION'] <= 9) {
			$pageRenderer->addJsLibrary('placeholders', 'contrib/placeholdersjs/placeholders.jquery.min.js');
		}

		$pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/Login');
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['typo3/index.php']['loginScriptHook'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['typo3/index.php']['loginScriptHook'] as $function) {
				$params = array();
				$JSCode = GeneralUtility::callUserFunction($function, $params, $this);
				if ($JSCode) {
					$GLOBALS['TBE_TEMPLATE']->JScode .= $JSCode;
					break;
				}
			}
		}

		// Checking, if we should make a redirect.
		// Might set JavaScript in the header to close window.
		$this->checkRedirect();
		// Initialize interface selectors:
		$this->makeInterfaceSelectorBox();
		// Creating form based on whether there is a login or not:
		if (empty($GLOBALS['BE_USER']->user['uid'])) {
			$GLOBALS['TBE_TEMPLATE']->form = $this->startForm();
			$loginForm = $this->makeLoginForm();
		} else {
			$GLOBALS['TBE_TEMPLATE']->form = '
				<form action="index.php" method="post" name="loginform">
				<input type="hidden" name="login_status" value="logout" />
				';
			$loginForm = $this->makeLogoutForm();
		}

		// Starting page:
		$this->content .= $GLOBALS['TBE_TEMPLATE']->startPage('TYPO3 CMS Login: ' . $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'], FALSE);
		// Add login form:
		$this->content .= $this->wrapLoginForm($loginForm);
		$this->content .= $GLOBALS['TBE_TEMPLATE']->endPage();
	}

	/**
	 * Outputting the accumulated content to screen
	 *
	 * @return void
	 */
	public function printContent() {
		echo $this->content;
	}

	/*****************************
	 *
	 * Various functions
	 *
	 ******************************/
	/**
	 * Creates the login form
	 * This is drawn when NO login exists.
	 *
	 * @return string HTML output
	 */
	public function makeLoginForm() {
		$content = HtmlParser::getSubpart($GLOBALS['TBE_TEMPLATE']->moduleTemplate, '###LOGIN_FORM###');
		$markers = array(
			'VALUE_USERNAME' => htmlspecialchars($this->u),
			'VALUE_PASSWORD' => htmlspecialchars($this->p),
			'VALUE_OPENID_URL' => htmlspecialchars($this->openIdUrl),
			'VALUE_SUBMIT' => $GLOBALS['LANG']->getLL('labels.submitLogin', TRUE)
		);
		// Show an error message if the login command was successful already, otherwise remove the subpart
		if (!$this->isLoginInProgress()) {
			$content = HtmlParser::substituteSubpart($content, '###LOGIN_ERROR###', '');
		} else {
			$markers['ERROR_MESSAGE'] = $GLOBALS['LANG']->getLL('error.login', TRUE);
			$markers['ERROR_LOGIN_TITLE'] = $GLOBALS['LANG']->getLL('error.login.title', TRUE);
			$markers['ERROR_LOGIN_DESCRIPTION'] = $GLOBALS['LANG']->getLL('error.login.description', TRUE);
		}
		// Remove the interface selector markers if it's not available
		if (!($this->interfaceSelector && !$this->loginRefresh)) {
			$content = HtmlParser::substituteSubpart($content, '###INTERFACE_SELECTOR###', '');
		} else {
			$markers['LABEL_INTERFACE'] = $GLOBALS['LANG']->getLL('labels.interface', TRUE);
			$markers['VALUE_INTERFACE'] = $this->interfaceSelector;
		}
		return HtmlParser::substituteMarkerArray($content, $markers, '###|###');
	}

	/**
	 * Creates the logout form
	 * This is drawn if a user login already exists.
	 *
	 * @return string HTML output
	 */
	public function makeLogoutForm() {
		$content = HtmlParser::getSubpart($GLOBALS['TBE_TEMPLATE']->moduleTemplate, '###LOGOUT_FORM###');
		$markers = array(
			'LABEL_USERNAME' => $GLOBALS['LANG']->getLL('labels.username', TRUE),
			'VALUE_USERNAME' => htmlspecialchars($GLOBALS['BE_USER']->user['username']),
			'VALUE_SUBMIT' => $GLOBALS['LANG']->getLL('labels.submitLogout', TRUE)
		);
		// Remove the interface selector markers if it's not available
		if (!$this->interfaceSelector_jump) {
			$content = HtmlParser::substituteSubpart($content, '###INTERFACE_SELECTOR###', '');
		} else {
			$markers['LABEL_INTERFACE'] = $GLOBALS['LANG']->getLL('labels.interface', TRUE);
			$markers['VALUE_INTERFACE'] = $this->interfaceSelector_jump;
		}
		return HtmlParser::substituteMarkerArray($content, $markers, '###|###');
	}

	/**
	 * Wrapping the login form table in another set of tables etc:
	 *
	 * @param string $content HTML content for the login form
	 * @return string The HTML for the page.
	 */
	public function wrapLoginForm($content) {
		$mainContent = HtmlParser::getSubpart($GLOBALS['TBE_TEMPLATE']->moduleTemplate, '###PAGE###');

		if ($GLOBALS['TBE_STYLES']['logo_login']) {
			$logo = '<img src="' . htmlspecialchars(($GLOBALS['BACK_PATH'] . $GLOBALS['TBE_STYLES']['logo_login'])) . '" alt="" class="t3-login-logo" />';
		} else {
			$logo = '<img' . IconUtility::skinImg($GLOBALS['BACK_PATH'], 'gfx/typo3-transparent@2x.png', 'width="140" height="39"') . ' alt="" class="t3-login-logo t3-default-logo" />';
		}

		$additionalCssClasses = array();
		if ($this->isLoginInProgress()) {
			$additionalCssClasses[] = 'error';
		}
		if ($this->loginRefresh) {
			$additionalCssClasses[] = 'refresh';
		}
		$markers = array(
			'LOGO' => $logo,
			'LOGINBOX_IMAGE' => '',
			'FORM' => $content,
			'NEWS' => $this->makeLoginNews(),
			'COPYRIGHT' => BackendUtility::TYPO3_copyRightNotice($GLOBALS['TYPO3_CONF_VARS']['SYS']['loginCopyrightShowVersion']),
			'CSS_CLASSES' => !empty($additionalCssClasses) ? 'class="' . implode(' ', $additionalCssClasses) . '"' : '',
			'CSS_OPENIDCLASS' => 't3-login-openid-' . (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('openid') ? 'enabled' : 'disabled'),
			// The labels will be replaced later on, thus the other parts above
			// can use these markers as well and it will be replaced
			'HEADLINE' => $GLOBALS['LANG']->getLL('headline', TRUE),
			'INFO_ABOUT' => $GLOBALS['LANG']->getLL('info.about', TRUE),
			'INFO_RELOAD' => $GLOBALS['LANG']->getLL('info.reset', TRUE),
			'INFO' => $GLOBALS['LANG']->getLL('info.cookies_and_js', TRUE),
			'ERROR_JAVASCRIPT' => $GLOBALS['LANG']->getLL('error.javascript', TRUE),
			'ERROR_COOKIES' => $GLOBALS['LANG']->getLL('error.cookies', TRUE),
			'ERROR_COOKIES_IGNORE' => $GLOBALS['LANG']->getLL('error.cookies_ignore', TRUE),
			'ERROR_CAPSLOCK' => $GLOBALS['LANG']->getLL('error.capslock', TRUE),
			'ERROR_FURTHERHELP' => $GLOBALS['LANG']->getLL('error.furtherInformation', TRUE),
			'LABEL_DONATELINK' => $GLOBALS['LANG']->getLL('labels.donate', TRUE),
			'LABEL_USERNAME' => $GLOBALS['LANG']->getLL('labels.username', TRUE),
			'LABEL_OPENID' => $GLOBALS['LANG']->getLL('labels.openId', TRUE),
			'LABEL_PASSWORD' => $GLOBALS['LANG']->getLL('labels.password', TRUE),
			'LABEL_WHATISOPENID' => $GLOBALS['LANG']->getLL('labels.whatIsOpenId', TRUE),
			'LABEL_SWITCHOPENID' => $GLOBALS['LANG']->getLL('labels.switchToOpenId', TRUE),
			'LABEL_SWITCHDEFAULT' => $GLOBALS['LANG']->getLL('labels.switchToDefault', TRUE),
			'CLEAR' => $GLOBALS['LANG']->getLL('clear', TRUE),
			'LOGIN_PROCESS' => $GLOBALS['LANG']->getLL('login_process', TRUE),
			'SITELINK' => '<a href="/">###SITENAME###</a>',
			// Global variables will now be replaced (at last)
			'SITENAME' => htmlspecialchars($GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'])
		);
		$markers = $this->emitRenderLoginFormSignal($markers);
		$mainContent = HtmlParser::substituteMarkerArray($mainContent, $markers, '###|###');

		// OPENID_LOADED
		if (!ExtensionManagementUtility::isLoaded('openid')) {
			$mainContent = HtmlParser::substituteSubpart($mainContent, 'OPENID_LOADED', '');
		}
		return $mainContent;
	}

	/**
	 * Checking, if we should perform some sort of redirection OR closing of windows.
	 *
	 * @return void
	 */
	public function checkRedirect() {
		// Do redirect:
		// If a user is logged in AND a) if either the login is just done (isLoginInProgress) or b) a loginRefresh is done or c) the interface-selector is NOT enabled (If it is on the other hand, it should not just load an interface, because people has to choose then...)
		if (!empty($GLOBALS['BE_USER']->user['uid']) && ($this->isLoginInProgress() || $this->loginRefresh || !$this->interfaceSelector)) {
			// If no cookie has been set previously we tell people that this is a problem. This assumes that a cookie-setting script (like this one) has been hit at least once prior to this instance.
			if (!$_COOKIE[\TYPO3\CMS\Core\Authentication\BackendUserAuthentication::getCookieName()]) {
				if ($this->commandLI == 'setCookie') {
					// we tried it a second time but still no cookie
					// 26/4 2005: This does not work anymore, because the saving of challenge values in $_SESSION means the system will act as if the password was wrong.
					throw new \RuntimeException('Login-error: Yeah, that\'s a classic. No cookies, no TYPO3.<br /><br />Please accept cookies from TYPO3 - otherwise you\'ll not be able to use the system.', 1294586846);
				} else {
					// try it once again - that might be needed for auto login
					$this->redirectToURL = 'index.php?commandLI=setCookie';
				}
			}
			if ($redirectToURL = (string)$GLOBALS['BE_USER']->getTSConfigVal('auth.BE.redirectToURL')) {
				$this->redirectToURL = $redirectToURL;
				$this->GPinterface = '';
			}
			// store interface
			$GLOBALS['BE_USER']->uc['interfaceSetup'] = $this->GPinterface;
			$GLOBALS['BE_USER']->writeUC();
			// Based on specific setting of interface we set the redirect script:
			switch ($this->GPinterface) {
				case 'backend':
					$this->redirectToURL = 'backend.php';
					break;
				case 'frontend':
					$this->redirectToURL = '../';
					break;
			}
			/** @var $formProtection \TYPO3\CMS\Core\FormProtection\BackendFormProtection */
			$formProtection = \TYPO3\CMS\Core\FormProtection\FormProtectionFactory::get();
			// If there is a redirect URL AND if loginRefresh is not set...
			if (!$this->loginRefresh) {
				$formProtection->storeSessionTokenInRegistry();
				HttpUtility::redirect($this->redirectToURL);
			} else {
				$formProtection->setSessionTokenFromRegistry();
				$formProtection->persistSessionToken();
				$GLOBALS['TBE_TEMPLATE']->JScode .= $GLOBALS['TBE_TEMPLATE']->wrapScriptTags('
					if (parent.opener && parent.opener.TYPO3 && parent.opener.TYPO3.LoginRefresh) {
						parent.opener.TYPO3.LoginRefresh.startTask();
						parent.close();
					}
				');
			}
		} elseif (empty($GLOBALS['BE_USER']->user['uid']) && $this->isLoginInProgress()) {
			// Wrong password, wait for 5 seconds
			sleep(5);
		}
	}

	/**
	 * Making interface selector:
	 *
	 * @return void
	 */
	public function makeInterfaceSelectorBox() {
		// Reset variables:
		$this->interfaceSelector = '';
		$this->interfaceSelector_hidden = '';
		$this->interfaceSelector_jump = '';
		// If interfaces are defined AND no input redirect URL in GET vars:
		if ($GLOBALS['TYPO3_CONF_VARS']['BE']['interfaces'] && ($this->isLoginInProgress() || !$this->redirect_url)) {
			$parts = GeneralUtility::trimExplode(',', $GLOBALS['TYPO3_CONF_VARS']['BE']['interfaces']);
			// Only if more than one interface is defined will we show the selector:
			if (count($parts) > 1) {
				// Initialize:
				$labels = array(
					'backend' => $GLOBALS['LANG']->getLL('interface.backend'),
					'frontend' => $GLOBALS['LANG']->getLL('interface.frontend')
				);
				$jumpScript = array(
					'backend' => 'backend.php',
					'frontend' => '../'
				);
				// Traverse the interface keys:
				foreach ($parts as $valueStr) {
					$this->interfaceSelector .= '
							<option value="' . htmlspecialchars($valueStr) . '"' . (GeneralUtility::_GP('interface') == htmlspecialchars($valueStr) ? ' selected="selected"' : '') . '>' . htmlspecialchars($labels[$valueStr]) . '</option>';
					$this->interfaceSelector_jump .= '
							<option value="' . htmlspecialchars($jumpScript[$valueStr]) . '">' . htmlspecialchars($labels[$valueStr]) . '</option>';
				}
				$this->interfaceSelector = '
						<select id="t3-interfaceselector" name="interface" class="form-control t3-interfaces input-lg" tabindex="3">' . $this->interfaceSelector . '
						</select>';
				$this->interfaceSelector_jump = '
						<select id="t3-interfaceselector" name="interface" class="form-control t3-interfaces input-lg" tabindex="3" onchange="window.location.href=this.options[this.selectedIndex].value;">' . $this->interfaceSelector_jump . '
						</select>';
			} elseif (!$this->redirect_url) {
				// If there is only ONE interface value set and no redirect_url is present:
				$this->interfaceSelector_hidden = '<input type="hidden" name="interface" value="' . trim($GLOBALS['TYPO3_CONF_VARS']['BE']['interfaces']) . '" />';
			}
		}
	}

	/**
	 * Returns the login box image, whether the default or an image from the rotation folder.
	 *
	 * @return string HTML image tag.
	 * @deprecated since TYPO3 CMS 7, will be removed in TYPO3 CMS 8, see Deprecation-60559-MakeLoginBoxImage.rst
	 */
	public function makeLoginBoxImage() {
		GeneralUtility::logDeprecatedFunction();
		return '';
	}

	/**
	 * Make login news - renders the HTML content for a list of news shown under
	 * the login form. News data is added through sys_news records
	 *
	 * @return string HTML content
	 * @credits Idea by Jan-Hendrik Heuing
	 */
	public function makeLoginNews() {
		$newsContent = '';
		$systemNews = $this->getSystemNews();
		// Traverse news array IF there are records in it:
		if (is_array($systemNews) && count($systemNews) && !GeneralUtility::_GP('loginRefresh')) {
			/** @var $htmlParser \TYPO3\CMS\Core\Html\RteHtmlParser */
			$htmlParser = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Html\RteHtmlParser::class);
			$htmlParser->procOptions['dontHSC_rte'] = TRUE;

			// Get the main news template, and replace the subpart after looped through
			$newsContent = HtmlParser::getSubpart($GLOBALS['TBE_TEMPLATE']->moduleTemplate, '###LOGIN_NEWS###');
			$newsItemTemplate = HtmlParser::getSubpart($newsContent, '###NEWS_ITEM###');
			$newsItem = '';
			$count = 1;
			foreach ($systemNews as $newsItemData) {
				$additionalClass = '';
				if ($count === 1) {
					$additionalClass = ' first-item';
				} elseif ($count === count($systemNews)) {
					$additionalClass = ' last-item';
				}
				$newsItemContent = $htmlParser->TS_transform_rte($htmlParser->TS_links_rte($newsItemData['content']));
				$newsItemMarker = array(
					'###HEADER###' => htmlspecialchars($newsItemData['header']),
					'###DATE###' => htmlspecialchars($newsItemData['date']),
					'###CONTENT###' => $newsItemContent,
					'###CLASS###' => $additionalClass
				);
				$count++;
				$newsItem .= HtmlParser::substituteMarkerArray($newsItemTemplate, $newsItemMarker);
			}
			$title = $GLOBALS['TYPO3_CONF_VARS']['BE']['loginNewsTitle'] ? $GLOBALS['TYPO3_CONF_VARS']['BE']['loginNewsTitle'] : $GLOBALS['LANG']->getLL('newsheadline');
			$newsContent = HtmlParser::substituteMarker($newsContent, '###NEWS_HEADLINE###', htmlspecialchars($title));
			$newsContent = HtmlParser::substituteSubpart($newsContent, '###NEWS_ITEM###', $newsItem);
		}
		return $newsContent;
	}

	/**
	 * Gets news from sys_news and converts them into a format suitable for
	 * showing them at the login screen.
	 *
	 * @return array An array of login news.
	 */
	protected function getSystemNews() {
		$systemNewsTable = 'sys_news';
		$systemNews = array();
		$systemNewsRecords = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('title, content, crdate', $systemNewsTable, '1=1' . BackendUtility::BEenableFields($systemNewsTable) . BackendUtility::deleteClause($systemNewsTable), '', 'crdate DESC');
		foreach ($systemNewsRecords as $systemNewsRecord) {
			$systemNews[] = array(
				'date' => date($GLOBALS['TYPO3_CONF_VARS']['SYS']['ddmmyy'], $systemNewsRecord['crdate']),
				'header' => $systemNewsRecord['title'],
				'content' => $systemNewsRecord['content']
			);
		}
		return $systemNews;
	}

	/**
	 * Returns the form tag
	 *
	 * @return string Opening form tag string
	 */
	public function startForm() {
		$output = '';
		// The form defaults to 'no login'. This prevents plain
		// text logins to the Backend. The 'sv' extension changes the form to
		// use superchallenged method and rsaauth extension makes rsa authentication.
		$form = '<form action="index.php" method="post" name="loginform" ' . 'onsubmit="alert(\'No authentication methods available. Please, contact your TYPO3 administrator.\');return false">';
		// Call hooks. If they do not return anything, we fail to login
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['typo3/index.php']['loginFormHook'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['typo3/index.php']['loginFormHook'] as $function) {
				$params = array();
				$formCode = GeneralUtility::callUserFunction($function, $params, $this);
				if ($formCode) {
					$form = $formCode;
					break;
				}
			}
		}
		$output .= $form . '<input type="hidden" name="login_status" value="login" />' .
			'<input type="hidden" id="t3-field-userident" name="userident" value="" />' .
			'<input type="hidden" name="redirect_url" value="' . htmlspecialchars($this->redirectToURL) . '" />' .
			'<input type="hidden" name="loginRefresh" value="' . htmlspecialchars($this->loginRefresh) . '" />' .
			$this->interfaceSelector_hidden . $this->addFields_hidden;
		return $output;
	}

	/**
	 * Creates JavaScript for the login form
	 *
	 * @return string JavaScript code
	 * @deprecated since TYPO3 CMS 7, will be removed in TYPO3 CMS 8
	 */
	public function getJScode() {
		GeneralUtility::logDeprecatedFunction();
	}

	/**
	 * Checks if login credentials are currently submitted
	 *
	 * @return bool
	 */
	protected function isLoginInProgress() {
		$username = GeneralUtility::_GP('username');
		return !(empty($username) && empty($this->commandLI));
	}

	/**
	 * Emits the render login form signal
	 *
	 * @param array $markers Array with markers for the login form
	 * @return array Modified markers array
	 */
	protected function emitRenderLoginFormSignal(array $markers) {
		$signalArguments = $this->getSignalSlotDispatcher()->dispatch(\TYPO3\CMS\Backend\Controller\LoginController::class, self::SIGNAL_RenderLoginForm, array($this, $markers));
		return $signalArguments[1];
	}

	/**
	 * Get the SignalSlot dispatcher
	 *
	 * @return \TYPO3\CMS\Extbase\SignalSlot\Dispatcher
	 */
	protected function getSignalSlotDispatcher() {
		if (!isset($this->signalSlotDispatcher)) {
			$this->signalSlotDispatcher = $this->getObjectManager()->get(\TYPO3\CMS\Extbase\SignalSlot\Dispatcher::class);
		}
		return $this->signalSlotDispatcher;
	}

	/**
	 * Get the ObjectManager
	 *
	 * @return \TYPO3\CMS\Extbase\Object\ObjectManager
	 */
	protected function getObjectManager() {
		return GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Object\ObjectManager::class);
	}

}
