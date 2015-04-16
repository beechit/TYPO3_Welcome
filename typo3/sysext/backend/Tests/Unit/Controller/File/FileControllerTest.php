<?php
namespace TYPO3\CMS\Backend\Tests\Unit\Controller\File;

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
 * Tests for \TYPO3\CMS\Backend\Tests\Unit\Controller\File\FileController
 */
class FileControllerTest extends \TYPO3\CMS\Core\Tests\UnitTestCase {

	/**
	 * @var \TYPO3\CMS\Backend\Controller\File\FileController|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface
	 */
	protected $fileController;

	/**
	 * @var \TYPO3\CMS\Core\Resource\File|\PHPUnit_Framework_MockObject_MockObject
	 */
	protected $fileResourceMock;

	/**
	 * @var \TYPO3\CMS\Core\Resource\Folder|\PHPUnit_Framework_MockObject_MockObject
	 */
	protected $folderResourceMock;

	/**
	 * @var \TYPO3\CMS\Core\Utility\File\ExtendedFileUtility|\PHPUnit_Framework_MockObject_MockObject
	 */
	protected $mockFileProcessor;

	/**
	 * @var \TYPO3\CMS\Core\Http\AjaxRequestHandler|\PHPUnit_Framework_MockObject_MockObject
	 */
	protected $mockAjaxRequestHandler;

	/**
	 * Sets up this test case.
	 */
	protected function setUp() {
		$this->fileResourceMock = $this->getMock(\TYPO3\CMS\Core\Resource\File::class, array('toArray', 'getModificationTime', 'getExtension'), array(), '', FALSE);
		$this->folderResourceMock = $this->getMock(\TYPO3\CMS\Core\Resource\Folder::class, array('getIdentifier'), array(), '', FALSE);
		$this->mockFileProcessor = $this->getMock(\TYPO3\CMS\Core\Utility\File\ExtendedFileUtility::class, array('getErrorMessages'), array(), '', FALSE);

		$this->fileResourceMock->expects($this->any())->method('toArray')->will($this->returnValue(array('id' => 'foo')));
		$this->fileResourceMock->expects($this->any())->method('getModificationTime')->will($this->returnValue(123456789));
		$this->fileResourceMock->expects($this->any())->method('getExtension')->will($this->returnValue('html'));
	}

	/**
	 * @test
	 */
	public function flattenResultDataValueFlattensFileAndFolderResourcesButReturnsAnythingElseAsIs() {
		$this->fileController = $this->getAccessibleMock(\TYPO3\CMS\Backend\Controller\File\FileController::class, array('dummy'));

		$this->folderResourceMock->expects($this->once())->method('getIdentifier')->will($this->returnValue('bar'));

		$this->mockFileProcessor->expects($this->any())->method('getErrorMessages')->will($this->returnValue(array()));

		$this->assertTrue($this->fileController->_call('flattenResultDataValue', TRUE));
		$this->assertSame(array(), $this->fileController->_call('flattenResultDataValue', array()));

		$this->assertSame(
			array(
				'id' => 'foo',
				'date' => '29-11-73',
				'iconClasses' => 't3-icon t3-icon-mimetypes t3-icon-mimetypes-text t3-icon-text-html'
			),
			$this->fileController->_call('flattenResultDataValue', $this->fileResourceMock)
		);

		$this->assertSame(
			'bar',
			$this->fileController->_call('flattenResultDataValue', $this->folderResourceMock)
		);
	}

	/**
	 * @test
	 */
	public function processAjaxRequestDeleteProcessActuallyDoesNotChangeFileData() {
		$this->fileController = $this->getAccessibleMock(\TYPO3\CMS\Backend\Controller\File\FileController::class, array('init', 'main'));
		$this->mockAjaxRequestHandler = $this->getMock(\TYPO3\CMS\Core\Http\AjaxRequestHandler::class, array('addContent', 'setContentFormat'), array(), '', FALSE);

		$fileData = array('delete' => array(TRUE));
		$this->fileController->_set('fileProcessor', $this->mockFileProcessor);
		$this->fileController->_set('fileData', $fileData);
		$this->fileController->_set('redirect', FALSE);

		$this->fileController->expects($this->once())->method('init');
		$this->fileController->expects($this->once())->method('main');
		$this->mockAjaxRequestHandler->expects($this->once())->method('addContent')->with('result', $fileData);
		$this->mockAjaxRequestHandler->expects($this->once())->method('setContentFormat')->with('json');

		$this->fileController->processAjaxRequest(array(), $this->mockAjaxRequestHandler);
	}

	/**
	 * @test
	 */
	public function processAjaxRequestEditFileProcessActuallyDoesNotChangeFileData() {
		$this->fileController = $this->getAccessibleMock(\TYPO3\CMS\Backend\Controller\File\FileController::class, array('init', 'main'));
		$this->mockAjaxRequestHandler = $this->getMock(\TYPO3\CMS\Core\Http\AjaxRequestHandler::class, array('addContent', 'setContentFormat'), array(), '', FALSE);

		$fileData = array('editfile' => array(TRUE));
		$this->fileController->_set('fileProcessor', $this->mockFileProcessor);
		$this->fileController->_set('fileData', $fileData);
		$this->fileController->_set('redirect', FALSE);

		$this->fileController->expects($this->once())->method('init');
		$this->fileController->expects($this->once())->method('main');
		$this->mockAjaxRequestHandler->expects($this->once())->method('addContent')->with('result', $fileData);
		$this->mockAjaxRequestHandler->expects($this->once())->method('setContentFormat')->with('json');

		$this->fileController->processAjaxRequest(array(), $this->mockAjaxRequestHandler);
	}

	/**
	 * @test
	 */
	public function processAjaxRequestUnzipProcessActuallyDoesNotChangeFileData() {
		$this->fileController = $this->getAccessibleMock(\TYPO3\CMS\Backend\Controller\File\FileController::class, array('init', 'main'));
		$this->mockAjaxRequestHandler = $this->getMock(\TYPO3\CMS\Core\Http\AjaxRequestHandler::class, array('addContent', 'setContentFormat'), array(), '', FALSE);

		$fileData = array('unzip' => array(TRUE));
		$this->fileController->_set('fileProcessor', $this->mockFileProcessor);
		$this->fileController->_set('fileData', $fileData);
		$this->fileController->_set('redirect', FALSE);

		$this->fileController->expects($this->once())->method('init');
		$this->fileController->expects($this->once())->method('main');
		$this->mockAjaxRequestHandler->expects($this->once())->method('addContent')->with('result', $fileData);
		$this->mockAjaxRequestHandler->expects($this->once())->method('setContentFormat')->with('json');

		$this->fileController->processAjaxRequest(array(), $this->mockAjaxRequestHandler);
	}

	/**
	 * @test
	 */
	public function processAjaxRequestUploadProcess() {
		$this->fileController = $this->getAccessibleMock(\TYPO3\CMS\Backend\Controller\File\FileController::class, array('init', 'main'));
		$this->mockAjaxRequestHandler = $this->getMock(\TYPO3\CMS\Core\Http\AjaxRequestHandler::class, array('addContent', 'setContentFormat'), array(), '', FALSE);

		$fileData = array('upload' => array(array($this->fileResourceMock)));
		$result = array('upload' => array(array(
			'id' => 'foo',
			'date' => '29-11-73',
			'iconClasses' => 't3-icon t3-icon-mimetypes t3-icon-mimetypes-text t3-icon-text-html'
		)));
		$this->fileController->_set('fileProcessor', $this->mockFileProcessor);
		$this->fileController->_set('fileData', $fileData);
		$this->fileController->_set('redirect', FALSE);

		$this->fileController->expects($this->once())->method('init');
		$this->fileController->expects($this->once())->method('main');
		$this->mockAjaxRequestHandler->expects($this->once())->method('addContent')->with('result', $result);
		$this->mockAjaxRequestHandler->expects($this->once())->method('setContentFormat')->with('json');

		$this->fileController->processAjaxRequest(array(), $this->mockAjaxRequestHandler);
	}

}
