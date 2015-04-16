<?php
defined('TYPO3_MODE') or die();

// Add the service
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addService($_EXTKEY, 'auth', \TYPO3\CMS\Rsaauth\RsaAuthService::class, array(
	'title' => 'RSA authentication',
	'description' => 'Authenticates users by using encrypted passwords',
	'subtype' => 'processLoginDataBE,processLoginDataFE',
	'available' => TRUE,
	'priority' => 60,
	// tx_svauth_sv1 has 50, t3sec_saltedpw has 55. This service must have higher priority!
	'quality' => 60,
	// tx_svauth_sv1 has 50. This service must have higher quality!
	'os' => '',
	'exec' => '',
	// Do not put a dependency on openssh here or service loading will fail!
	'className' => \TYPO3\CMS\Rsaauth\RsaAuthService::class
));

// Add a hook to the BE login form
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['typo3/index.php']['loginFormHook'][$_EXTKEY] = \TYPO3\CMS\Rsaauth\Hook\LoginFormHook::class . '->getLoginFormTag';
// Add hook for user setup module
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/setup/mod/index.php']['setupScriptHook'][$_EXTKEY] = \TYPO3\CMS\Rsaauth\Hook\UserSetupHook::class . '->getLoginScripts';
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/setup/mod/index.php']['modifyUserDataBeforeSave'][$_EXTKEY] = \TYPO3\CMS\Rsaauth\Hook\UserSetupHook::class . '->decryptPassword';
// Add a hook to the FE login form (felogin system extension)
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['felogin']['loginFormOnSubmitFuncs'][$_EXTKEY] = \TYPO3\CMS\Rsaauth\Hook\FrontendLoginHook::class . '->loginFormHook';
// Add a hook to show Backend warnings
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_befunc.php']['displayWarningMessages'][$_EXTKEY] = \TYPO3\CMS\Rsaauth\BackendWarnings::class;

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::registerAjaxHandler(
	'BackendLogin::getRsaPublicKey',
	\TYPO3\CMS\Rsaauth\Backend\AjaxLoginHandler::class . '->getRsaPublicKey',
	FALSE
);

// eID for FrontendLoginRsaPublicKey
$GLOBALS['TYPO3_CONF_VARS']['FE']['eID_include']['FrontendLoginRsaPublicKey'] =
	'EXT:rsaauth/Resources/PHP/FrontendLoginRsaPublicKey.php';

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['typo3/backend.php']['constructPostProcess'][] = \TYPO3\CMS\Rsaauth\Hook\BackendHookForAjaxLogin::class . '->addRsaJsLibraries';