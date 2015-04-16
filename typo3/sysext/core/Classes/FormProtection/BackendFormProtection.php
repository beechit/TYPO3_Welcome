<?php
namespace TYPO3\CMS\Core\FormProtection;

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
 * This class provides protection against cross-site request forgery (XSRF/CSRF)
 * for forms in the BE.
 *
 * How to use:
 *
 * For each form in the BE (or link that changes some data), create a token and
 * insert is as a hidden form element. The name of the form element does not
 * matter; you only need it to get the form token for verifying it.
 *
 * <pre>
 * $formToken = TYPO3\CMS\Core\FormProtection\BackendFormProtectionFactory::get()
 * ->generateToken(
 * 'BE user setup', 'edit'
 * );
 * $this->content .= '<input type="hidden" name="formToken" value="' .
 * $formToken . '" />';
 * </pre>
 *
 * The three parameters $formName, $action and $formInstanceName can be
 * arbitrary strings, but they should make the form token as specific as
 * possible. For different forms (e.g. BE user setup and editing a tt_content
 * record) or different records (with different UIDs) from the same table,
 * those values should be different.
 *
 * For editing a tt_content record, the call could look like this:
 *
 * <pre>
 * $formToken = \TYPO3\CMS\Core\FormProtection\BackendFormProtectionFactory::get()
 * ->getFormProtection()->generateToken(
 * 'tt_content', 'edit', $uid
 * );
 * </pre>
 *
 *
 * When processing the data that has been submitted by the form, you can check
 * that the form token is valid like this:
 *
 * <pre>
 * if ($dataHasBeenSubmitted && TYPO3\CMS\Core\FormProtection\BackendFormProtectionFactory::get()
 * ->validateToken(
 * \TYPO3\CMS\Core\Utility\GeneralUtility::_POST('formToken'),
 * 'BE user setup', 'edit
 * )
 * ) {
 * processes the data
 * } else {
 * no need to do anything here as the BE form protection will create a
 * flash message for an invalid token
 * }
 * </pre>
 */
use TYPO3\CMS\Core\Messaging\FlashMessageService;

/**
 * Backend form protection
 *
 * @author Oliver Klee <typo3-coding@oliverklee.de>
 * @author Helmut Hummel <helmut.hummel@typo3.org>
 */
class BackendFormProtection extends AbstractFormProtection {

	/**
	 * Keeps the instance of the user which existed during creation
	 * of the object.
	 *
	 * @var \TYPO3\CMS\Core\Authentication\BackendUserAuthentication
	 */
	protected $backendUser;

	/**
	 * Instance of the registry, which is used to permanently persist
	 * the session token so that it can be restored during re-login.
	 *
	 * @var \TYPO3\CMS\Core\Registry
	 */
	protected $registry;

	/**
	 * Only allow construction if we have a backend session
	 *
	 * @throws \TYPO3\CMS\Core\Error\Exception
	 */
	public function __construct() {
		if (!$this->isAuthorizedBackendSession()) {
			throw new \TYPO3\CMS\Core\Error\Exception('A back-end form protection may only be instantiated if there' . ' is an active back-end session.', 1285067843);
		}
		$this->backendUser = $GLOBALS['BE_USER'];
	}

	/**
	 * Creates or displays an error message telling the user that the submitted
	 * form token is invalid.
	 *
	 * @return void
	 */
	protected function createValidationErrorMessage() {
		/** @var \TYPO3\CMS\Core\Messaging\FlashMessage $flashMessage */
		$flashMessage = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
			\TYPO3\CMS\Core\Messaging\FlashMessage::class,
			$this->getLanguageService()->sL('LLL:EXT:lang/locallang_core.xlf:error.formProtection.tokenInvalid'),
			'',
			\TYPO3\CMS\Core\Messaging\FlashMessage::ERROR,
			!$this->isAjaxRequest()
		);
		/** @var $flashMessageService FlashMessageService */
		$flashMessageService = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(FlashMessageService::class);

		/** @var $defaultFlashMessageQueue \TYPO3\CMS\Core\Messaging\FlashMessageQueue */
		$defaultFlashMessageQueue = $flashMessageService->getMessageQueueByIdentifier();
		$defaultFlashMessageQueue->enqueue($flashMessage);
	}

	/**
	 * Checks if the current request is an Ajax request
	 *
	 * @return bool
	 */
	protected function isAjaxRequest() {
		return (bool)(TYPO3_REQUESTTYPE & TYPO3_REQUESTTYPE_AJAX);
	}

	/**
	 * Retrieves the saved session token or generates a new one.
	 *
	 * @return string
	 */
	protected function retrieveSessionToken() {
		$this->sessionToken = $this->backendUser->getSessionData('formSessionToken');
		if (empty($this->sessionToken)) {
			$this->sessionToken = $this->generateSessionToken();
			$this->persistSessionToken();
		}
		return $this->sessionToken;
	}

	/**
	 * Saves the tokens so that they can be used by a later incarnation of this
	 * class.
	 *
	 * @access private
	 * @return void
	 */
	public function persistSessionToken() {
		$this->backendUser->setAndSaveSessionData('formSessionToken', $this->sessionToken);
	}

	/**
	 * Sets the session token for the user from the registry
	 * and returns it additionally.
	 *
	 * @access private
	 * @return string
	 * @throws \UnexpectedValueException
	 */
	public function setSessionTokenFromRegistry() {
		$this->sessionToken = $this->getRegistry()->get('core', 'formSessionToken:' . $this->backendUser->user['uid']);
		if (empty($this->sessionToken)) {
			throw new \UnexpectedValueException('Failed to restore the session token from the registry.', 1301827270);
		}
		return $this->sessionToken;
	}

	/**
	 * Stores the session token in the registry to have it
	 * available during re-login of the user.
	 *
	 * @access private
	 * @return void
	 */
	public function storeSessionTokenInRegistry() {
		$this->getRegistry()->set('core', 'formSessionToken:' . $this->backendUser->user['uid'], $this->getSessionToken());
	}

	/**
	 * Removes the session token for the user from the registry.
	 *
	 * @access private
	 */
	public function removeSessionTokenFromRegistry() {
		$this->getRegistry()->remove('core', 'formSessionToken:' . $this->backendUser->user['uid']);
	}

	/**
	 * Returns the instance of the registry.
	 *
	 * @return \TYPO3\CMS\Core\Registry
	 */
	protected function getRegistry() {
		if (!$this->registry instanceof \TYPO3\CMS\Core\Registry) {
			$this->registry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Registry::class);
		}
		return $this->registry;
	}

	/**
	 * Inject the registry. Currently only used in unit tests.
	 *
	 * @access private
	 * @param \TYPO3\CMS\Core\Registry $registry
	 * @return void
	 */
	public function injectRegistry(\TYPO3\CMS\Core\Registry $registry) {
		$this->registry = $registry;
	}

	/**
	 * Checks if a user is logged in and the session is active.
	 *
	 * @return bool
	 */
	protected function isAuthorizedBackendSession() {
		return isset($GLOBALS['BE_USER']) && $GLOBALS['BE_USER'] instanceof \TYPO3\CMS\Core\Authentication\BackendUserAuthentication && isset($GLOBALS['BE_USER']->user['uid']);
	}

	/**
	 * Return language service instance
	 *
	 * @return \TYPO3\CMS\Lang\LanguageService
	 */
	protected function getLanguageService() {
		return $GLOBALS['LANG'];
	}

}
