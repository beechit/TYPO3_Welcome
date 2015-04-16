<?php
namespace TYPO3\CMS\Saltedpasswords\Utility;

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

use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * class providing configuration checks for saltedpasswords.
 *
 * @author Steffen Ritter <info@rs-websystems.de>
 * @since 2009-09-04
 */
class ExtensionManagerConfigurationUtility {

	/**
	 * @var int
	 */
	protected $errorType = FlashMessage::OK;

	/**
	 * @var string
	 */
	protected $header;

	/**
	 * @var string
	 */
	protected $preText;

	/**
	 * @var array
	 */
	protected $problems = array();

	/**
	 * @var array
	 */
	protected $extConf = array();

	/**
	 * Set the error level if no higher level
	 * is set already
	 *
	 * @param string $level One out of error, ok, warning, info
	 * @return void
	 */
	protected function setErrorLevel($level) {
		switch ($level) {
			case 'error':
				$this->errorType = FlashMessage::ERROR;
				$this->header = 'Errors found in your configuration';
				$this->preText = 'SaltedPasswords will not work until these problems have been resolved:<br />';
				break;
			case 'warning':
				if ($this->errorType < FlashMessage::ERROR) {
					$this->errorType = FlashMessage::WARNING;
					$this->header = 'Warnings about your configuration';
					$this->preText = 'SaltedPasswords might behave different than expected:<br />';
				}
				break;
			case 'info':
				if ($this->errorType < FlashMessage::WARNING) {
					$this->errorType = FlashMessage::INFO;
					$this->header = 'Additional information';
					$this->preText = '<br />';
				}
				break;
			case 'ok':
				// @todo Remove INFO condition as it has lower importance
				if ($this->errorType < FlashMessage::WARNING && $this->errorType != FlashMessage::INFO) {
					$this->errorType = FlashMessage::OK;
					$this->header = 'No errors were found';
					$this->preText = 'SaltedPasswords has been configured correctly and works as expected.<br />';
				}
				break;
			default:
		}
	}

	/**
	 * Renders the messages if problems have been found.
	 *
	 * @return array an array with errorType and html code
	 */
	protected function renderMessage() {
		$message = '';
		// If there are problems, render them into an unordered list
		if (count($this->problems) > 0) {
			$message = '<ul><li>###PROBLEMS###</li></ul>';
			$message = str_replace('###PROBLEMS###', implode('<br />&nbsp;</li><li>', $this->problems), $message);
			if ($this->errorType > FlashMessage::OK) {
				$message .= '<br />' .
				'Note, that a wrong configuration might have impact on the security of ' .
				'your TYPO3 installation and the usability of the backend.';
			}
		}
		if (empty($message)) {
			$this->setErrorLevel('ok');
		}
		$message = $this->preText . $message;

		$class = 'default';
		switch ($this->errorType) {
			case FlashMessage::NOTICE:
				$class = 'notice';
				break;
			case FlashMessage::INFO:
				$class = 'info';
				break;
			case FlashMessage::OK:
				$class = 'success';
				break;
			case FlashMessage::WARNING;
				$class = 'warning';
				break;
			case FlashMessage::ERROR:
				$class = 'danger';
				break;
			default:
		}
		$html = '<div class="panel panel-' . $class . '">' .
					'<div class="panel-heading">' . $this->header . '</div>' .
					'<div class="panel-body">' . $message . '</div>' .
				'</div>';
		return array(
			'errorType' => $this->errorType,
			'html' => $html
		);
	}

	/**
	 * Initializes this object.
	 *
	 * @return void
	 */
	private function init() {
		$requestSetup = $this->processPostData((array) $_REQUEST['data']);
		$extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['saltedpasswords']);
		$this->extConf['BE'] = array_merge((array)$extConf['BE.'], (array)$requestSetup['BE.']);
		$this->extConf['FE'] = array_merge((array)$extConf['FE.'], (array)$requestSetup['FE.']);
		$GLOBALS['LANG']->includeLLFile('EXT:saltedpasswords/locallang.xlf');
	}

	/**
	 * Checks the backend configuration and shows a message if necessary.
	 * The method returns an array or the HTML code depends on
	 * $params['propertyName'] is set or not.
	 *
	 * @param array $params Field information to be rendered
	 * @param \TYPO3\CMS\Core\TypoScript\ConfigurationForm $pObj The calling parent object.
	 * @return array|string array with errorType and HTML or only the HTML as string
	 */
	public function checkConfigurationBackend(array $params, $pObj) {
		$this->init();
		$extConf = $this->extConf['BE'];
		// The backend is called over SSL
		$isBackendCalledOverSsl = (
			(int)$GLOBALS['TYPO3_CONF_VARS']['BE']['lockSSL'] > 0
			&& $GLOBALS['TYPO3_CONF_VARS']['BE']['loginSecurityLevel'] !== 'superchallenged'
		);
		$rsaAuthLoaded = ExtensionManagementUtility::isLoaded('rsaauth');
		// SSL configured?
		if ($isBackendCalledOverSsl) {
			$this->setErrorLevel('ok');
			$problems[] = 'The backend is configured to use SaltedPasswords over SSL.';
		} elseif ($rsaAuthLoaded) {
			if (trim($GLOBALS['TYPO3_CONF_VARS']['BE']['loginSecurityLevel']) === 'rsa') {
				if ($this->isRsaAuthBackendAvailable()) {
					$this->setErrorLevel('ok');
					$problems[] = 'The backend is configured to use SaltedPasswords with RSA authentication.';
				} else {
					// This means that login would fail because rsaauth is not working properly
					$this->setErrorLevel('error');
					$problems[] = '<strong>Using the extension "rsaauth" is not possible, as no encryption backend ' .
						'is available. Please install and configure the PHP extension "openssl". ' .
						'See <a href="http://php.net/manual/en/openssl.installation.php" target="_blank">PHP.net</a></strong>.';
				}
			} else {
				// This means that rsaauth is enabled but not used
				$this->setErrorLevel('warning');
				$problems[] = 'The "rsaauth" extension is installed, but TYPO3 is not configured to use it during login.
					Use the Install Tool to set the Login Security Level for the backend to "rsa"
					($TYPO3_CONF_VARS[\'BE\'][\'loginSecurityLevel\'])';
			}
		} else {
			// This means that we don't use any encryption method
			$this->setErrorLevel('warning');
			$problems[] = 'SaltedPasswords is used without any transfer encryption, this means your passwords are sent in plain text.
				Please install rsaauth to secure your passwords submits.<br />
				<ul>
				<li>Install the "rsaauth" extension and use the Install Tool to set the
					Login Security Level for the backend to "rsa"
					($TYPO3_CONF_VARS[\'BE\'][\'loginSecurityLevel\'])</li>

				<li>If you have the option to use SSL, you can also configure your
					backend for SSL usage:<br />
					Use the Install Tool to set the Security-Level for the backend
					to "normal" ($TYPO3_CONF_VARS[\'BE\'][\'loginSecurityLevel\']) and
					the SSL-locking option to a value greater than "0"
					(see description - $TYPO3_CONF_VARS[\'BE\'][\'lockSSL\'])</li>
				</ul>
				<br />
				It is also possible to use "lockSSL" and "rsa" Login Security Level at the same time.';
		}
		// Only saltedpasswords as authsservice
		if ($extConf['onlyAuthService']) {
			// Warn user that the combination with "forceSalted" may lock him
			// out from Backend
			if ($extConf['forceSalted']) {
				$this->setErrorLevel('warning');
				$problems[] = 'SaltedPasswords has been configured to be the only authentication service for
					the backend. Additionally, usage of salted passwords is enforced (forceSalted).
					The result is that there is no chance to login with users not having a salted
					password hash.<br />
					<strong><i>WARNING:</i></strong> This may lock you out of the backend!';
			} else {
				// Inform the user that things like openid won't work anymore
				$this->setErrorLevel('info');
				$problems[] = 'SaltedPasswords has been configured to be the only authentication service for
					the backend. This means that other services like "ipauth", "openid", etc. will
					be ignored (except "rsauth", which is implicitely used).';
			}
		}
		// forceSalted is set
		if ($extConf['forceSalted'] && !$extConf['onlyAuthService']) {
			$this->setErrorLevel('info');
			$problems[] = 'SaltedPasswords has been configured to enforce salted passwords (forceSalted).
				<br />
				This means that only passwords in the format of this extension will succeed for
				login.';
		}
		// updatePasswd wont work with "forceSalted"
		if ($extConf['updatePasswd'] && $extConf['forceSalted']) {
			$this->setErrorLevel('error');
			$problems[] = 'SaltedPasswords is configured wrong and will not work as expected:<br />
				It is not possible to set "updatePasswd" and "forceSalted" at the same time.
				Please disable either one of them.';
		}
		// Check if the configured hash-method is available on system
		$instance = \TYPO3\CMS\Saltedpasswords\Salt\SaltFactory::getSaltingInstance(NULL, 'BE');
		if ($instance === NULL || !$instance->isAvailable()) {
			$this->setErrorLevel('error');
			$problems[] = 'The selected method for hashing your salted passwords is not available on this
				system! Please check your configuration.';
		}
		$this->problems = $problems;
		$result = $this->renderMessage();
		if (!empty($params['propertyName'])) {
			return $result['html'];
		}
		return $result;
	}

	/**
	 * Checks if rsaauth is able to obtain a backend
	 *
	 * @return bool
	 */
	protected function isRsaAuthBackendAvailable() {
		// Try to instantiate an RSAauth backend. If this does not work,
		// it means that OpenSSL is not usable
		/** @var \TYPO3\CMS\Rsaauth\Backend\BackendFactory $rsaauthBackendFactory */
		$rsaauthBackendFactory = GeneralUtility::makeInstance(\TYPO3\CMS\Rsaauth\Backend\BackendFactory::class);
		$backend = $rsaauthBackendFactory->getBackend();
		return $backend !== NULL;
	}

	/**
	 * Checks the frontend configuration and shows a message if necessary.
	 * The method returns an array or the HTML code depends on
	 * $params['propertyName'] is set or not.
	 *
	 * @param array $params Field information to be rendered
	 * @param \TYPO3\CMS\Core\TypoScript\ConfigurationForm $pObj The calling parent object.
	 * @return array|string array with errorType and HTML or only the HTML as string
	 */
	public function checkConfigurationFrontend(array $params, $pObj) {
		$this->init();
		$extConf = $this->extConf['FE'];
		$problems = array();
		if ($extConf['enabled']) {
			// Inform the user if securityLevel in FE is challenged or blank --> extension won't work
			if (!GeneralUtility::inList('normal,rsa', $GLOBALS['TYPO3_CONF_VARS']['FE']['loginSecurityLevel'])) {
				$this->setErrorLevel('info');
				$problems[] = '<strong>IMPORTANT:</strong><br />
					Frontend requirements for SaltedPasswords are not met, therefore the
					authentication will not work even if it was explicitly enabled for frontend
					usage:<br />
					<ul>
						<li>Install the "rsaauth" extension and use the Install Tool to set the
							Login Security Level for the frontend to "rsa"
							($TYPO3_CONF_VARS[\'FE\'][\'loginSecurityLevel\'])</li>

						<li>Alternatively, use the Install Tool to set the Login Security Level
							for the frontend to "normal"
							($TYPO3_CONF_VARS[\'FE\'][\'loginSecurityLevel\'])</li>
					</ul>
					<br />
					Make sure that the Login Security Level is not set to "" or "challenged"!';
			} elseif (trim($GLOBALS['TYPO3_CONF_VARS']['FE']['loginSecurityLevel']) === 'rsa') {
				if (ExtensionManagementUtility::isLoaded('rsaauth')) {
					if ($this->isRsaAuthBackendAvailable()) {
						$this->setErrorLevel('ok');
						$problems[] = 'The frontend is configured to use SaltedPasswords with RSA authentication.';
					} else {
						// This means that login would fail because rsaauth is not working properly
						$this->setErrorLevel('error');
						$problems[] = '<strong>Using the extension "rsaauth" is not possible, as no encryption backend ' .
							'is available. Please install and configure the PHP extension "openssl". ' .
							'See <a href="http://php.net/manual/en/openssl.installation.php" target="_blank">PHP.net</a></strong>.';
					}
				} else {
					// Rsaauth is not installed but configured to be used
					$this->setErrorLevel('warning');
					$problems[] = 'The "rsaauth" extension is not installed, but TYPO3 CMS is configured to use it.' .
						' Either install the extension or adapt the configuration by setting [FE][loginSecurityLevel]' .
						' to "normal" in the Install Tool.';
				}
			}
			// Only saltedpasswords as authsservice
			if ($extConf['onlyAuthService']) {
				// Warn user that the combination with "forceSalted" may lock
				// him out from frontend
				if ($extConf['forceSalted']) {
					$this->setErrorLevel('warning');
					$problems[] = 'SaltedPasswords has been configured to enforce salted passwords (forceSalted).
						<br />
						This means that only passwords in the format of this extension will succeed for
						login.<br />
						<strong><i>IMPORTANT:</i></strong> Because of this, it is not possible to login with
						users not having a salted password hash (e.g. existing frontend users).';
				} else {
					// Inform the user that things like openid won't work anymore
					$this->setErrorLevel('info');
					$problems[] = 'SaltedPasswords has been configured to be the only authentication service for
						frontend logins. This means that other services like "ipauth", "openid", etc.
						will be ignored.';
				}
			}
			// forceSalted is set
			if ($extConf['forceSalted'] && !$extConf['onlyAuthService']) {
				$this->setErrorLevel('warning');
				$problems[] = 'SaltedPasswords has been configured to enforce salted passwords (forceSalted).
					<br />
					This means that only passwords in the format of this extension will succeed for
					login.<br />
					<strong><i>IMPORTANT:</i></strong> This has the effect that passwords that were set
					before SaltedPasswords was used will not work (in fact, they need to be
					redefined).';
			}
			// updatePasswd wont work with "forceSalted"
			if ($extConf['updatePasswd'] && $extConf['forceSalted']) {
				$this->setErrorLevel('error');
				$problems[] = 'SaltedPasswords is configured wrong and will not work as expected:<br />
					It is not possible to set "updatePasswd" and "forceSalted" at the same time.
					Please disable either one of them.';
			}
		} else {
			// Not enabled warning
			$this->setErrorLevel('info');
			$problems[] = 'SaltedPasswords has been disabled for frontend users.';
		}
		$this->problems = $problems;
		$result = $this->renderMessage();
		if (!empty($params['propertyName'])) {
			return $result['html'];
		}
		return $result;
	}

	/**
	 * Renders a selector element that allows to select the hash method to be used.
	 *
	 * @param array $params Field information to be rendered
	 * @param \TYPO3\CMS\Core\TypoScript\ConfigurationForm $pObj The calling parent object.
	 * @param string $disposal The configuration disposal ('FE' or 'BE')
	 * @return string The HTML selector
	 */
	protected function buildHashMethodSelector(array $params, $pObj, $disposal) {
		$this->init();
		$propertyName = $params['propertyName'];
		$unknownVariablePleaseRenameMe = '\'' . substr(md5($propertyName), 0, 10) . '\'';
		$pField = '';
		$registeredMethods = \TYPO3\CMS\Saltedpasswords\Salt\SaltFactory::getRegisteredSaltedHashingMethods();
		foreach ($registeredMethods as $class => $reference) {
			$classInstance = GeneralUtility::getUserObj($reference, 'tx_');
			if ($classInstance instanceof \TYPO3\CMS\Saltedpasswords\Salt\SaltInterface && $classInstance->isAvailable()) {
				$sel = $this->extConf[$disposal]['saltedPWHashingMethod'] == $class ? ' selected="selected" ' : '';
				$label = 'ext.saltedpasswords.title.' . strtolower(end(explode('\\', $class)));
				$pField .= '<option value="' . htmlspecialchars($class) . '"' . $sel . '>' . $GLOBALS['LANG']->getLL($label) . '</option>';
			}
		}
		$pField = '<select id="' . $propertyName . '" name="' . $params['fieldName'] .
			'" onChange="uFormUrl(' . $unknownVariablePleaseRenameMe . ')">' . $pField . '</select>';
		return $pField;
	}

	/**
	 * Renders a selector element that allows to select the hash method to be
	 * used (frontend disposal).
	 *
	 * @param array $params Field information to be rendered
	 * @param \TYPO3\CMS\Core\TypoScript\ConfigurationForm $pObj The calling parent object.
	 * @return string The HTML selector
	 */
	public function buildHashMethodSelectorFE(array $params, $pObj) {
		return $this->buildHashMethodSelector($params, $pObj, 'FE');
	}

	/**
	 * Renders a selector element that allows to select the hash method to
	 * be used (backend disposal)
	 *
	 * @param array $params Field information to be rendered
	 * @param \TYPO3\CMS\Core\TypoScript\ConfigurationForm $pObj The calling parent object.
	 * @return string The HTML selector
	 */
	public function buildHashMethodSelectorBE(array $params, $pObj) {
		return $this->buildHashMethodSelector($params, $pObj, 'BE');
	}

	/**
	 * Processes the information submitted by the user using a POST request and
	 * transforms it to a TypoScript node notation.
	 *
	 * @param array $postArray Incoming POST information
	 * @return array Processed and transformed POST information
	 */
	protected function processPostData(array $postArray = array()) {
		foreach ($postArray as $key => $value) {
			// @todo Explain
			$parts = explode('.', $key, 2);
			if (count($parts) == 2) {
				// @todo Explain
				$value = $this->processPostData(array($parts[1] => $value));
				$postArray[$parts[0] . '.'] = array_merge((array)$postArray[($parts[0] . '.')], $value);
			} else {
				// @todo Explain
				$postArray[$parts[0]] = $value;
			}
		}
		return $postArray;
	}

}
