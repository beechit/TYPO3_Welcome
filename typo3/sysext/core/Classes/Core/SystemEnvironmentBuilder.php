<?php
namespace TYPO3\CMS\Core\Core;

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
 * Class to encapsulate base setup of bootstrap.
 *
 * This class contains all code that must be executed by every entry script.
 *
 * It sets up all basic paths, constants, global variables and checks
 * the basic environment TYPO3 runs in.
 *
 * This class does not use any TYPO3 instance specific configuration, it only
 * sets up things based on the server environment and core code. Even with a
 * missing typo3conf/localconf.php this script will be successful.
 *
 * The script aborts execution with an error message if
 * some part fails or conditions are not met.
 *
 * This script is internal code and subject to change.
 * DO NOT use it in own code, or be prepared your code might
 * break in future versions of the core.
 */
class SystemEnvironmentBuilder {

	/**
	 * A list of supported CGI server APIs
	 * NOTICE: This is a duplicate of the SAME array in GeneralUtility!
	 *         It is duplicated here as this information is needed early in bootstrap
	 *         and GeneralUtility is not available yet.
	 * @var array
	 */
	static protected $supportedCgiServerApis = array(
		'fpm-fcgi',
		'cgi',
		'isapi',
		'cgi-fcgi',
		'srv', // HHVM with fastcgi
	);

	/**
	 * Run base setup.
	 * This entry method is used in all scopes (FE, BE, eid, ajax, ...)
	 *
	 * @internal This method should not be used by 3rd party code. It will change without further notice.
	 * @param string $relativePathPart Relative path of the entry script back to document root
	 * @return void
	 */
	static public function run($relativePathPart = '') {
		self::defineBaseConstants();
		self::definePaths($relativePathPart);
		self::checkMainPathsExist();
		self::handleMagicQuotesGpc();
		self::addCorePearPathToIncludePath();
		self::initializeGlobalVariables();
		self::initializeGlobalTimeTrackingVariables();
		self::initializeBasicErrorReporting();
	}

	/**
	 * Define all simple constants that have no dependency to local configuration
	 *
	 * @return void
	 */
	static protected function defineBaseConstants() {
		// This version, branch and copyright
		define('TYPO3_version', '7.1.0');
		define('TYPO3_branch', '7.1');
		define('TYPO3_copyright_year', '1998-2015');

		// TYPO3 external links
		define('TYPO3_URL_GENERAL', 'http://typo3.org/');
		define('TYPO3_URL_ORG', 'http://typo3.org/');
		define('TYPO3_URL_LICENSE', 'http://typo3.org/licenses');
		define('TYPO3_URL_EXCEPTION', 'http://typo3.org/go/exception/CMS/');
		define('TYPO3_URL_MAILINGLISTS', 'http://lists.typo3.org/cgi-bin/mailman/listinfo');
		define('TYPO3_URL_DOCUMENTATION', 'http://typo3.org/documentation/');
		define('TYPO3_URL_DOCUMENTATION_TSREF', 'http://docs.typo3.org/typo3cms/TyposcriptReference/');
		define('TYPO3_URL_DOCUMENTATION_TSCONFIG', 'http://docs.typo3.org/typo3cms/TSconfigReference/');
		define('TYPO3_URL_CONSULTANCY', 'http://typo3.org/support/professional-services/');
		define('TYPO3_URL_CONTRIBUTE', 'http://typo3.org/contribute/');
		define('TYPO3_URL_SECURITY', 'http://typo3.org/teams/security/');
		define('TYPO3_URL_DOWNLOAD', 'http://typo3.org/download/');
		define('TYPO3_URL_SYSTEMREQUIREMENTS', 'http://typo3.org/typo3-cms/overview/requirements/');
		define('TYPO3_URL_DONATE', 'http://typo3.org/donate/online-donation/');
		define('TYPO3_URL_WIKI_OPCODECACHE', 'http://wiki.typo3.org/Opcode_Cache');

		// A null, a tabulator, a linefeed, a carriage return, a substitution, a CR-LF combination
		define('NUL', chr(0));
		define('TAB', chr(9));
		define('LF', chr(10));
		define('CR', chr(13));
		define('SUB', chr(26));
		define('CRLF', CR . LF);

		// Security related constant: Default value of fileDenyPattern
		define('FILE_DENY_PATTERN_DEFAULT', '\\.(php[3-6]?|phpsh|phtml)(\\..*)?$|^\\.htaccess$');
		// Security related constant: List of file extensions that should be registered as php script file extensions
		define('PHP_EXTENSIONS_DEFAULT', 'php,php3,php4,php5,php6,phpsh,inc,phtml');

		// Operating system identifier
		// Either "WIN" or empty string
		define('TYPO3_OS', self::getTypo3Os());

		// Service error constants
		// General error - something went wrong
		define('T3_ERR_SV_GENERAL', -1);
		// During execution it showed that the service is not available and should be ignored. The service itself should call $this->setNonAvailable()
		define('T3_ERR_SV_NOT_AVAIL', -2);
		// Passed subtype is not possible with this service
		define('T3_ERR_SV_WRONG_SUBTYPE', -3);
		// Passed subtype is not possible with this service
		define('T3_ERR_SV_NO_INPUT', -4);
		// File not found which the service should process
		define('T3_ERR_SV_FILE_NOT_FOUND', -20);
		// File not readable
		define('T3_ERR_SV_FILE_READ', -21);
		// File not writable
		define('T3_ERR_SV_FILE_WRITE', -22);
		// Passed subtype is not possible with this service
		define('T3_ERR_SV_PROG_NOT_FOUND', -40);
		// Passed subtype is not possible with this service
		define('T3_ERR_SV_PROG_FAILED', -41);
	}

	/**
	 * Calculate all required base paths and set as constants.
	 *
	 * @param string $relativePathPart Relative path of the entry script back to document root
	 * @return void
	 */
	static protected function definePaths($relativePathPart = '') {
		// Relative path from document root to typo3/ directory
		// Hardcoded to "typo3/"
		define('TYPO3_mainDir', 'typo3/');
		// Absolute path of the entry script that was called
		// All paths are unified between Windows and Unix, so the \ of Windows is substituted to a /
		// Example "/var/www/instance-name/htdocs/typo3conf/ext/wec_map/mod1/index.php"
		// Example "c:/var/www/instance-name/htdocs/typo3/backend.php" for a path in Windows
		if (!defined('PATH_thisScript')) {
			define('PATH_thisScript', self::getPathThisScript());
		}
		// Absolute path of the document root of the instance with trailing slash
		// Example "/var/www/instance-name/htdocs/"
		if (!defined('PATH_site')) {
			define('PATH_site', self::getPathSite($relativePathPart));
		}
		// Absolute path of the typo3 directory of the instance with trailing slash
		// Example "/var/www/instance-name/htdocs/typo3/"
		define('PATH_typo3', PATH_site . TYPO3_mainDir);
		// Relative path (from the PATH_typo3) to a BE module NOT using mod.php dispatcher with trailing slash
		// Example "sysext/perms/mod/" for an extension installed in typo3/sysext/
		// Example "install/" for the install tool entry script
		// Example "../typo3conf/ext/templavoila/mod2/ for an extension installed in typo3conf/ext/
		define('PATH_typo3_mod', defined('TYPO3_MOD_PATH') ? TYPO3_MOD_PATH : '');
		// Absolute path to the typo3conf directory with trailing slash
		// Example "/var/www/instance-name/htdocs/typo3conf/"
		define('PATH_typo3conf', PATH_site . 'typo3conf/');
	}

	/**
	 * Check if path and script file name calculation was successful, exit if not.
	 *
	 * @return void
	 */
	static protected function checkMainPathsExist() {
		if (!is_file(PATH_thisScript)) {
			static::dieWithMessage('Unable to determine path to entry script.');
		}
		if (!is_dir(PATH_typo3 . 'sysext')) {
			static::dieWithMessage('Calculated absolute path to typo3/sysext directory does not exist.' . LF . LF
				. 'Something in the main file, folder and link structure is wrong and must be fixed! A typical document root contains a couple of symbolic links:' . LF
				. '* A symlink "typo3_src" pointing to the TYPO3 CMS core.' . LF
				. '* A symlink "typo3" - the backend entry point - pointing to "typo3_src/typo3"' . LF
				. '* A symlink "index.php" - the frontend entry point - points to "typo3_src/index.php"');
		}
	}

	/**
	 * Compatibility layer for magic quotes
	 *
	 * @return void
	 */
	static protected function handleMagicQuotesGpc() {
		if (!get_magic_quotes_gpc()) {
			\TYPO3\CMS\Core\Utility\GeneralUtility::addSlashesOnArray($_GET);
			\TYPO3\CMS\Core\Utility\GeneralUtility::addSlashesOnArray($_POST);
			$GLOBALS['HTTP_GET_VARS'] = $_GET;
			$GLOBALS['HTTP_POST_VARS'] = $_POST;
		}
	}

	/**
	 * Add typo3/contrib/pear/ as first include folder in
	 * include path, because the shipped PEAR packages use
	 * relative paths to include their files.
	 *
	 * This is required for \TYPO3\CMS\Core\Http\HttpRequest
	 * to work.
	 *
	 * Having the TYPO3 folder first will make sure that the
	 * shipped version is loaded before any local PEAR package,
	 * thus avoiding any incompatibilities with newer or older
	 * versions.
	 *
	 * @return void
	 */
	static protected function addCorePearPathToIncludePath() {
		set_include_path(PATH_typo3 . 'contrib/pear/' . PATH_SEPARATOR . get_include_path());
	}

	/**
	 * Set up / initialize several globals variables
	 *
	 * @return void
	 */
	static protected function initializeGlobalVariables() {
		// Unset variable(s) in global scope (security issue #13959)
		unset($GLOBALS['error']);
		// Set up base information about browser/user-agent
		$GLOBALS['CLIENT'] = \TYPO3\CMS\Core\Utility\GeneralUtility::clientInfo();
		$GLOBALS['TYPO3_MISC'] = array();
		$GLOBALS['T3_VAR'] = array();
		$GLOBALS['T3_SERVICES'] = array();
	}

	/**
	 * Initialize global time tracking variables.
	 * These are helpers to for example output script parsetime at the end of a script.
	 *
	 * @return void
	 */
	static protected function initializeGlobalTimeTrackingVariables() {
		// Set PARSETIME_START to the system time in milliseconds.
		$GLOBALS['PARSETIME_START'] = \TYPO3\CMS\Core\Utility\GeneralUtility::milliseconds();
		// Microtime of (nearly) script start
		$GLOBALS['TYPO3_MISC']['microtime_start'] = microtime(TRUE);
		// EXEC_TIME is set so that the rest of the script has a common value for the script execution time
		$GLOBALS['EXEC_TIME'] = time();
		// $ACCESS_TIME is a common time in minutes for access control
		$GLOBALS['ACCESS_TIME'] = $GLOBALS['EXEC_TIME'] - $GLOBALS['EXEC_TIME'] % 60;
		// $SIM_EXEC_TIME is set to $EXEC_TIME but can be altered later in the script if we want to
		// simulate another execution-time when selecting from eg. a database
		$GLOBALS['SIM_EXEC_TIME'] = $GLOBALS['EXEC_TIME'];
		// If $SIM_EXEC_TIME is changed this value must be set accordingly
		$GLOBALS['SIM_ACCESS_TIME'] = $GLOBALS['ACCESS_TIME'];
	}

	/**
	 * Initialize basic error reporting.
	 *
	 * There are a lot of extensions that have no strict / notice / deprecated free
	 * ext_localconf or ext_tables. Since the final error reporting must be set up
	 * after those extension files are read, a default configuration is needed to
	 * suppress error reporting meanwhile during further bootstrap.
	 *
	 * @return void
	 */
	static protected function initializeBasicErrorReporting() {
		// Core should be notice free at least until this point ...
		error_reporting(E_ALL & ~(E_STRICT | E_NOTICE | E_DEPRECATED));
	}

	/**
	 * Determine the operating system TYPO3 is running on.
	 *
	 * @return string Either 'WIN' if running on Windows, else empty string
	 */
	static protected function getTypo3Os() {
		$typoOs = '';
		if (!stristr(PHP_OS, 'darwin') && stristr(PHP_OS, 'win')) {
			$typoOs = 'WIN';
		}
		return $typoOs;
	}

	/**
	 * Calculate PATH_thisScript
	 *
	 * First step in path calculation: Goal is to find the absolute path of the entry script
	 * that was called without resolving any links. This is important since the TYPO3 entry
	 * points are often linked to a central core location, so we can not use the php magic
	 * __FILE__ here, but resolve the called script path from given server environments.
	 *
	 * This path is important to calculate the document root (PATH_site). The strategy is to
	 * find out the script name that was called in the first place and to subtract the local
	 * part from it to find the document root.
	 *
	 * @return string Absolute path to entry script
	 */
	static protected function getPathThisScript() {
		if (defined('TYPO3_cliMode') && TYPO3_cliMode === TRUE) {
			return self::getPathThisScriptCli();
		} else {
			return self::getPathThisScriptNonCli();
		}
	}

	/**
	 * Calculate path to entry script if not in cli mode.
	 *
	 * Depending on the environment, the script path is found in different $_SERVER variables.
	 *
	 * @return string Absolute path to entry script
	 */
	static protected function getPathThisScriptNonCli() {
		$cgiPath = '';
		if (isset($_SERVER['ORIG_PATH_TRANSLATED'])) {
			$cgiPath = $_SERVER['ORIG_PATH_TRANSLATED'];
		} elseif (isset($_SERVER['PATH_TRANSLATED'])) {
			$cgiPath = $_SERVER['PATH_TRANSLATED'];
		}
		if ($cgiPath && in_array(PHP_SAPI, self::$supportedCgiServerApis, TRUE)) {
			$scriptPath = $cgiPath;
		} else {
			if (isset($_SERVER['ORIG_SCRIPT_FILENAME'])) {
				$scriptPath = $_SERVER['ORIG_SCRIPT_FILENAME'];
			} else {
				$scriptPath = $_SERVER['SCRIPT_FILENAME'];
			}
		}
		// Replace \ to / for Windows
		$scriptPath = str_replace('\\', '/', $scriptPath);
		// Replace double // to /
		$scriptPath = str_replace('//', '/', $scriptPath);
		return $scriptPath;
	}

	/**
	 * Calculate path to entry script if in cli mode.
	 *
	 * First argument of a cli script is the path to the script that was called. If the script does not start
	 * with / (or A:\ for Windows), the path is not absolute yet, and the current working directory is added.
	 *
	 * @return string Absolute path to entry script
	 */
	static protected function getPathThisScriptCli() {
		// Possible relative path of the called script
		if (isset($_SERVER['argv'][0])) {
			$scriptPath = $_SERVER['argv'][0];
		} elseif (isset($_ENV['_'])) {
			$scriptPath = $_ENV['_'];
		} else {
			$scriptPath = $_SERVER['_'];
		}
		// Find out if path is relative or not
		$isRelativePath = FALSE;
		if (TYPO3_OS === 'WIN') {
			if (!preg_match('/^([a-zA-Z]:)?\\\\/', $scriptPath)) {
				$isRelativePath = TRUE;
			}
		} else {
			if ($scriptPath[0] !== '/') {
				$isRelativePath = TRUE;
			}
		}
		// Concatenate path to current working directory with relative path and remove "/./" constructs
		if ($isRelativePath) {
			if (isset($_SERVER['PWD'])) {
				$workingDirectory = $_SERVER['PWD'];
			} else {
				$workingDirectory = getcwd();
			}
			$scriptPath = $workingDirectory . '/' . preg_replace('/\\.\\//', '', $scriptPath);
		}
		return $scriptPath;
	}

	/**
	 * Calculate the document root part to the instance from PATH_thisScript
	 *
	 * There are two ways to hint correct calculation:
	 * Either an explicit specified sub path or the defined constant TYPO3_MOD_PATH. Which one is
	 * used depends on which entry script was called in the first place.
	 *
	 * We have two main scenarios for entry points:
	 * - Directly called documentRoot/index.php (-> FE call or eiD include): index.php sets $relativePathPart to
	 * empty string to hint this code that the document root is identical to the directory the script is located at.
	 * - An indirect include of typo3/init.php (-> a backend module, the install tool, or scripts like thumbs.php).
	 * If init.php is included we distinguish two cases:
	 * -- A backend module defines 'TYPO3_MOD_PATH': This is the case for "old" modules that are not called through
	 * "mod.php" dispatcher, and in the install tool. The TYPO3_MOD_PATH defines the relative path to the typo3/
	 * directory. This is taken as base to calculate the document root.
	 * -- A script includes init.php and does not define 'TYPO3_MOD_PATH': This is the case for the mod.php dispatcher
	 * and other entry scripts like 'cli_dispatch.phpsh' or 'thumbs.php' that are located parallel to init.php. In
	 * this case init.php sets 'typo3/' as $relativePathPart as base to calculate the document root.
	 *
	 * This basically boils down to the following code:
	 * If TYPO3_MOD_PATH is defined, subtract this 'local' part from the entry point directory, else use
	 * $relativePathPart to subtract this from the the script entry point to find out the document root.
	 *
	 * @param string $relativePathPart Relative directory part from document root to script path if TYPO3_MOD_PATH is not used
	 * @return string Absolute path to document root of installation
	 */
	static protected function getPathSite($relativePathPart) {
		// If end of path is not "typo3/" and TYPO3_MOD_PATH is given
		if (defined('TYPO3_MOD_PATH')) {
			return self::getPathSiteByTypo3ModulePath();
		} else {
			return self::getPathSiteByRelativePathPart($relativePathPart);
		}
	}

	/**
	 * Calculate document root by TYPO3_MOD_PATH
	 *
	 * TYPO3_MOD_PATH can have the following values:
	 * - "sysext/extensionName/path/entryScript.php" -> extension is below 'docRoot'/typo3/sysext
	 * - "ext/extensionName/path/entryScript.php" -> extension is below 'docRoot'/typo3/ext
	 * - "../typo3conf/ext/extensionName/path/entryScript.php" -> extension is below 'docRoot'/typo3conf/ext
	 * - "install/index.php" -> install tool in 'docRoot'/typo3/install/
	 *
	 * The method unifies the above and subtracts the calculated path part from PATH_thisScript
	 *
	 * @return string Absolute path to document root of installation
	 */
	static protected function getPathSiteByTypo3ModulePath() {
		if (substr(TYPO3_MOD_PATH, 0, 7) === 'sysext/' || substr(TYPO3_MOD_PATH, 0, 4) === 'ext/' || substr(TYPO3_MOD_PATH, 0, 8) === 'install/') {
			$pathPartRelativeToDocumentRoot = TYPO3_mainDir . TYPO3_MOD_PATH;
		} elseif (substr(TYPO3_MOD_PATH, 0, strlen('../typo3conf/')) === '../typo3conf/') {
			$pathPartRelativeToDocumentRoot = substr(TYPO3_MOD_PATH, 3);
		} else {
			static::dieWithMessage('Unable to determine TYPO3 document root.');
		}
		$entryScriptDirectory = self::getUnifiedDirectoryNameWithTrailingSlash(PATH_thisScript);
		return substr($entryScriptDirectory, 0, -strlen($pathPartRelativeToDocumentRoot));
	}

	/**
	 * Find out document root by subtracting $relativePathPart from PATH_thisScript
	 *
	 * @param string $relativePathPart Relative part of script from document root
	 * @return string Absolute path to document root of installation
	 */
	static protected function getPathSiteByRelativePathPart($relativePathPart) {
		$entryScriptDirectory = self::getUnifiedDirectoryNameWithTrailingSlash(PATH_thisScript);
		if ($relativePathPart !== '') {
			$pathSite = substr($entryScriptDirectory, 0, -strlen($relativePathPart));
		} else {
			$pathSite = $entryScriptDirectory;
		}
		return $pathSite;
	}

	/**
	 * Remove file name from script path and unify for Windows and Unix
	 *
	 * @param string $absolutePath Absolute path to script
	 * @return string Directory name of script file location, unified for Windows and Unix
	 */
	static protected function getUnifiedDirectoryNameWithTrailingSlash($absolutePath) {
		$directory = dirname($absolutePath);
		if (TYPO3_OS === 'WIN') {
			$directory = str_replace('\\', '/', $directory);
		}
		return $directory . '/';
	}

	/**
	 * Echo out a text message and die
	 *
	 * @param string $message
	 */
	static protected function dieWithMessage($message) {
		header('Content-type: text/plain');
		die($message);
	}

}
