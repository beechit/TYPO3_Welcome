<?php
namespace TYPO3\CMS\Frontend\Utility;

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

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * Tools for scripts using the eID feature of index.php
 * Included from index_ts.php
 * Since scripts using the eID feature does not
 * have a full FE environment initialized by default
 * this class seeks to provide functions that can
 * initialize parts of the FE environment as needed,
 * eg. Frontend User session, Database connection etc.
 *
 * @author Kasper Skårhøj <kasperYYYY@typo3.com>
 * @author Dmitry Dulepov <dmitry@typo3.org>
 */
class EidUtility {

	/**
	 * Returns true if within an eID-request. False if not.
	 *
	 * @return bool
	 */
	static public function isEidRequest() {
		return GeneralUtility::_GP('eID') ? TRUE : FALSE;
	}

	/**
	 * Returns the script path associated with the requested eID identifier.
	 *
	 * @return string eID associated script path
	 * @throws \TYPO3\CMS\Core\Exception
	 */
	static public function getEidScriptPath() {
		$eID = GeneralUtility::_GP('eID');
		if (!$eID || !isset($GLOBALS['TYPO3_CONF_VARS']['FE']['eID_include'][$eID])) {
			throw new \TYPO3\CMS\Core\Exception('eID not registered in $GLOBALS[\'TYPO3_CONF_VARS\'][\'FE\'][\'eID_include\'].', 1415714161);
		}
		$scriptPath = GeneralUtility::getFileAbsFileName($GLOBALS['TYPO3_CONF_VARS']['FE']['eID_include'][$eID]);
		if ($scriptPath === '') {
			throw new \TYPO3\CMS\Core\Exception('Registered eID has invalid script path.', 1416391467);
		}
		return $scriptPath;
	}

	/**
	 * Load and initialize Frontend User. Note, this process is slow because
	 * it creates a calls many objects. Call this method only if necessary!
	 *
	 * @return FrontendUserAuthentication Frontend User object (usually known as TSFE->fe_user)
	 */
	static public function initFeUser() {
		// Get TSFE instance. It knows how to initialize the user. We also
		// need TCA because services may need extra tables!
		self::initTCA();
		/** @var $tsfe \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController */
		$tsfe = self::getTSFE();
		$tsfe->initFEuser();
		// Return FE user object:
		return $tsfe->fe_user;
	}

	/**
	 * Initializes $GLOBALS['LANG'] for use in eID scripts.
	 *
	 * @param string $language TYPO3 language code
	 * @return void
	 */
	static public function initLanguage($language = 'default') {
		if (!is_object($GLOBALS['LANG'])) {
			$GLOBALS['LANG'] = GeneralUtility::makeInstance(\TYPO3\CMS\Lang\LanguageService::class);
			$GLOBALS['LANG']->init($language);
		}
	}

	/**
	 * Makes TCA available inside eID
	 *
	 * @return void
	 */
	static public function initTCA() {
		// Some badly made extensions attempt to manipulate TCA in a wrong way
		// (inside ext_localconf.php). Therefore $GLOBALS['TCA'] may become an array
		// but in fact it is not loaded. The check below ensure that
		// TCA is still loaded if such bad extensions are installed
		if (!is_array($GLOBALS['TCA']) || !isset($GLOBALS['TCA']['pages'])) {
			\TYPO3\CMS\Core\Core\Bootstrap::getInstance()->loadCachedTca();
		}
	}

	/**
	 * Makes TCA for the extension available inside eID. Use this function if
	 * you need not to include the whole $GLOBALS['TCA'].
	 *
	 * @param string $extensionKey Extension key
	 * @return void
	 */
	static public function initExtensionTCA($extensionKey) {
		$extTablesPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($extensionKey, 'ext_tables.php');
		if (file_exists($extTablesPath)) {
			$GLOBALS['_EXTKEY'] = $extensionKey;
			require_once $extTablesPath;
			// We do not need to save restore the value of $GLOBALS['_EXTKEY']
			// because it is not defined to anything real outside of
			// ext_tables.php or ext_localconf.php scope.
			unset($GLOBALS['_EXTKEY']);
		}
	}

	/**
	 * Creating a single static cached instance of TSFE to use with this class.
	 *
	 * @return \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController New instance of \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController
	 */
	static private function getTSFE() {
		// Cached instance
		static $tsfe = NULL;
		if (is_null($tsfe)) {
			$tsfe = GeneralUtility::makeInstance(\TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController::class, $GLOBALS['TYPO3_CONF_VARS'], 0, 0);
		}
		return $tsfe;
	}

}
