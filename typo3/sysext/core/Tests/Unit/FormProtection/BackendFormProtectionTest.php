<?php
namespace TYPO3\CMS\Core\Tests\Unit\FormProtection;

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
 * Testcase
 *
 * @author Oliver Klee <typo3-coding@oliverklee.de>
 */
class BackendFormProtectionTest extends \TYPO3\CMS\Core\Tests\UnitTestCase {

	/**
	 * @var \TYPO3\CMS\Core\FormProtection\BackendFormProtection|\PHPUnit_Framework_MockObject_MockObject|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface
	 */
	protected $subject;

	/**
	 * Backup of current singleton instances
	 */
	protected $singletonInstances;

	/**
	 * Set up
	 */
	protected function setUp() {
		$this->singletonInstances = \TYPO3\CMS\Core\Utility\GeneralUtility::getSingletonInstances();

		$GLOBALS['BE_USER'] = $this->getMock(
			\TYPO3\CMS\Core\Authentication\BackendUserAuthentication::class,
			array('getSessionData', 'setAndSaveSessionData')
		);
		$GLOBALS['BE_USER']->user['uid'] = 1;

		$this->subject = $this->getAccessibleMock(
			\TYPO3\CMS\Core\FormProtection\BackendFormProtection::class,
			array('acquireLock', 'releaseLock', 'getLanguageService', 'isAjaxRequest')
		);
	}

	protected function tearDown() {
		\TYPO3\CMS\Core\Utility\GeneralUtility::resetSingletonInstances($this->singletonInstances);
		parent::tearDown();
	}

	//////////////////////
	// Utility functions
	//////////////////////

	/**
	 * @return \TYPO3\CMS\Core\Authentication\BackendUserAuthentication|\PHPUnit_Framework_MockObject_MockObject
	 */
	protected function getBackendUser() {
		return $GLOBALS['BE_USER'];
	}

	////////////////////////////////////
	// Tests for the utility functions
	////////////////////////////////////

	/**
	 * @test
	 */
	public function getBackendUserReturnsInstanceOfBackendUserAuthenticationClass() {
		$this->assertInstanceOf(
			\TYPO3\CMS\Core\Authentication\BackendUserAuthentication::class,
			$this->getBackendUser()
		);
	}

	//////////////////////////////////////////////////////////
	// Tests concerning the reading and saving of the tokens
	//////////////////////////////////////////////////////////

	/**
	 * @test
	 */
	public function retrieveTokenReadsTokenFromSessionData() {
		$this->getBackendUser()
			->expects($this->once())
			->method('getSessionData')
			->with('formSessionToken')
			->will($this->returnValue(array()));
		$this->subject->_call('retrieveSessionToken');
	}

	/**
	 * @test
	 */
	public function tokenFromSessionDataIsAvailableForValidateToken() {
		$sessionToken = '881ffea2159ac72182557b79dc0c723f5a8d20136f9fab56cdd4f8b3a1dbcfcd';
		$formName = 'foo';
		$action = 'edit';
		$formInstanceName = '42';

		$tokenId = \TYPO3\CMS\Core\Utility\GeneralUtility::hmac(
			$formName . $action . $formInstanceName . $sessionToken
		);

		$this->getBackendUser()
			->expects($this->atLeastOnce())
			->method('getSessionData')
			->with('formSessionToken')
			->will($this->returnValue($sessionToken));

		$this->subject->_call('retrieveSessionToken');

		$this->assertTrue(
			$this->subject->validateToken($tokenId, $formName, $action, $formInstanceName)
		);
	}

	/**
	 * @expectedException \UnexpectedValueException
	 * @test
	 */
	public function restoreSessionTokenFromRegistryThrowsExceptionIfSessionTokenIsEmpty() {
		/** @var $registryMock \TYPO3\CMS\Core\Registry */
		$registryMock = $this->getMock(\TYPO3\CMS\Core\Registry::class);
		$this->subject->injectRegistry($registryMock);
		$this->subject->setSessionTokenFromRegistry();
	}

	/**
	 * @test
	 */
	public function persistSessionTokenWritesTokenToSession() {
		$sessionToken = $this->getUniqueId('test_');
		$this->subject->_set('sessionToken', $sessionToken);
		$this->getBackendUser()
			->expects($this->once())
			->method('setAndSaveSessionData')
			->with('formSessionToken', $sessionToken);
		$this->subject->persistSessionToken();
	}


	//////////////////////////////////////////////////
	// Tests concerning createValidationErrorMessage
	//////////////////////////////////////////////////

	/**
	 * @test
	 */
	public function createValidationErrorMessageAddsFlashMessage() {
		/** @var $flashMessageServiceMock \TYPO3\CMS\Core\Messaging\FlashMessageService|\PHPUnit_Framework_MockObject_MockObject */
		$flashMessageServiceMock = $this->getMock(\TYPO3\CMS\Core\Messaging\FlashMessageService::class);
		\TYPO3\CMS\Core\Utility\GeneralUtility::setSingletonInstance(
			\TYPO3\CMS\Core\Messaging\FlashMessageService::class,
			$flashMessageServiceMock
		);
		$flashMessageQueueMock = $this->getMock(
			\TYPO3\CMS\Core\Messaging\FlashMessageQueue::class,
			array(),
			array(),
			'',
			FALSE
		);
		$flashMessageServiceMock
			->expects($this->once())
			->method('getMessageQueueByIdentifier')
			->will($this->returnValue($flashMessageQueueMock));
		$flashMessageQueueMock
			->expects($this->once())
			->method('enqueue')
			->with($this->isInstanceOf(\TYPO3\CMS\Core\Messaging\FlashMessage::class))
			->will($this->returnCallback(array($this, 'enqueueFlashMessageCallback')));

		$languageServiceMock = $this->getMock(\TYPO3\CMS\Lang\LanguageService::class, array(), array(), '', FALSE);
		$languageServiceMock->expects($this->once())->method('sL')->will($this->returnValue('foo'));
		$this->subject->expects($this->once())->method('getLanguageService')->will($this->returnValue($languageServiceMock));

		$this->subject->_call('createValidationErrorMessage');
	}

	/**
	 * @param \TYPO3\CMS\Core\Messaging\FlashMessage $flashMessage
	 */
	public function enqueueFlashMessageCallback(\TYPO3\CMS\Core\Messaging\FlashMessage $flashMessage) {
		$this->assertEquals(\TYPO3\CMS\Core\Messaging\FlashMessage::ERROR, $flashMessage->getSeverity());
	}

	/**
	 * @test
	 */
	public function createValidationErrorMessageAddsErrorFlashMessageButNotInSessionInAjaxRequest() {
		/** @var $flashMessageServiceMock \TYPO3\CMS\Core\Messaging\FlashMessageService|\PHPUnit_Framework_MockObject_MockObject */
		$flashMessageServiceMock = $this->getMock(\TYPO3\CMS\Core\Messaging\FlashMessageService::class);
		\TYPO3\CMS\Core\Utility\GeneralUtility::setSingletonInstance(
			\TYPO3\CMS\Core\Messaging\FlashMessageService::class,
			$flashMessageServiceMock
		);
		$flashMessageQueueMock = $this->getMock(
			\TYPO3\CMS\Core\Messaging\FlashMessageQueue::class,
			array(),
			array(),
			'',
			FALSE
		);
		$flashMessageServiceMock
			->expects($this->once())
			->method('getMessageQueueByIdentifier')
			->will($this->returnValue($flashMessageQueueMock));
		$flashMessageQueueMock
			->expects($this->once())
			->method('enqueue')
			->with($this->isInstanceOf(\TYPO3\CMS\Core\Messaging\FlashMessage::class))
			->will($this->returnCallback(array($this, 'enqueueAjaxFlashMessageCallback')));

		$languageServiceMock = $this->getMock(\TYPO3\CMS\Lang\LanguageService::class, array(), array(), '', FALSE);
		$languageServiceMock->expects($this->once())->method('sL')->will($this->returnValue('foo'));
		$this->subject->expects($this->once())->method('getLanguageService')->will($this->returnValue($languageServiceMock));

		$this->subject->expects($this->any())->method('isAjaxRequest')->will($this->returnValue(TRUE));
		$this->subject->_call('createValidationErrorMessage');
	}

	/**
	 * @param \TYPO3\CMS\Core\Messaging\FlashMessage $flashMessage
	 */
	public function enqueueAjaxFlashMessageCallback(\TYPO3\CMS\Core\Messaging\FlashMessage $flashMessage) {
		$this->assertFalse($flashMessage->isSessionMessage());
	}

}
