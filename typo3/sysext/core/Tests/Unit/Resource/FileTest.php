<?php
namespace TYPO3\CMS\Core\Tests\Unit\Resource;

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

use TYPO3\CMS\Core\Resource\ResourceStorage;

/**
 * Testcase for the file class of the TYPO3 FAL
 *
 * @author Andreas Wolf <andreas.wolf@ikt-werk.de>.
 */
class FileTest extends \TYPO3\CMS\Core\Tests\UnitTestCase {

	/**
	 * @var array A backup of registered singleton instances
	 */
	protected $singletonInstances = array();

	/**
	 * @var ResourceStorage
	 */
	protected $storageMock;

	protected function setUp() {
		$this->singletonInstances = \TYPO3\CMS\Core\Utility\GeneralUtility::getSingletonInstances();
		$this->storageMock = $this->getMock(\TYPO3\CMS\Core\Resource\ResourceStorage::class, array(), array(), '', FALSE);
		$this->storageMock->expects($this->any())->method('getUid')->will($this->returnValue(5));

		$mockedMetaDataRepository = $this->getMock(\TYPO3\CMS\Core\Resource\Index\MetaDataRepository::class);
		$mockedMetaDataRepository->expects($this->any())->method('findByFile')->will($this->returnValue(array('file' => 1)));
		\TYPO3\CMS\Core\Utility\GeneralUtility::setSingletonInstance(\TYPO3\CMS\Core\Resource\Index\MetaDataRepository::class, $mockedMetaDataRepository);
	}

	protected function tearDown() {
		\TYPO3\CMS\Core\Utility\GeneralUtility::resetSingletonInstances($this->singletonInstances);
		parent::tearDown();
	}

	/**
	 * @return \TYPO3\CMS\Core\Resource\File
	 */
	protected function prepareFixture() {
		$fixture = new \TYPO3\CMS\Core\Resource\File(array('testfile'), $this->storageMock);
		return $fixture;
	}

	/**
	 * @test
	 */
	public function commonPropertiesAreAvailableWithOwnGetters() {
		$properties = array(
			'name' => $this->getUniqueId(),
			'storage' => $this->storageMock,
			'size' => 1024
		);
		$fixture = new \TYPO3\CMS\Core\Resource\File($properties, $this->storageMock);
		foreach ($properties as $key => $value) {
			$this->assertEquals($value, call_user_func(array($fixture, 'get' . $key)));
		}
	}

	/**
	 * Tests if a file is seen as indexed if the record has a uid
	 *
	 * @test
	 */
	public function fileIndexStatusIsTrueIfUidIsSet() {
		$fixture = new \TYPO3\CMS\Core\Resource\File(array('uid' => 1), $this->storageMock);
		$this->assertTrue($fixture->isIndexed());
	}

	/**
	 * @test
	 */
	public function updatePropertiesUpdatesFileProperties() {
		$identifier = '/' . $this->getUniqueId();
		$fixture = new \TYPO3\CMS\Core\Resource\File(array('uid' => 1, 'identifier' => '/test'), $this->storageMock);
		$fixture->updateProperties(array('identifier' => $identifier));
		$this->assertEquals($identifier, $fixture->getIdentifier());
	}

	/**
	 * @test
	 */
	public function updatePropertiesLeavesPropertiesUntouchedIfNotSetInNewProperties() {
		$fixture = new \TYPO3\CMS\Core\Resource\File(array('uid' => 1, 'foo' => 'asdf', 'identifier' => '/test'), $this->storageMock);
		$fixture->updateProperties(array('foo' => 'foobar'));
		$this->assertEquals('/test', $fixture->getIdentifier());
		$this->assertEquals('/test', $fixture->getProperty('identifier'));
	}

	/**
	 * @test
	 */
	public function updatePropertiesDiscardsUidIfAlreadySet() {
		$fixture = new \TYPO3\CMS\Core\Resource\File(array('uid' => 1, 'identifier' => '/test'), $this->storageMock);
		$fixture->updateProperties(array('uid' => 3));
		$this->assertEquals(1, $fixture->getUid());
	}

	/**
	 * @test
	 */
	public function updatePropertiesRecordsNamesOfChangedProperties() {
		$fixture = new \TYPO3\CMS\Core\Resource\File(array('uid' => 1, 'foo' => 'asdf', 'baz' => 'fdsw', 'identifier' => '/test'), $this->storageMock);
		$fixture->updateProperties(array('foo' => 'foobar', 'baz' => 'foobaz'));
		$this->assertEquals(array('foo', 'baz'), $fixture->getUpdatedProperties());
	}

	/**
	 * @test
	 */
	public function updatePropertiesDoesNotRecordPropertyNameIfSameValueIsProvided() {
		$fixture = new \TYPO3\CMS\Core\Resource\File(array('uid' => 1, 'foo' => 'asdf', 'identifier' => '/test'), $this->storageMock);
		$fixture->updateProperties(array('foo' => 'asdf'));
		$this->assertEmpty($fixture->getUpdatedProperties());
	}

	/**
	 * @test
	 */
	public function updatePropertiesMarksPropertyAsChangedOnlyOnce() {
		$fixture = new \TYPO3\CMS\Core\Resource\File(array('uid' => 1, 'foo' => 'asdf', 'baz' => 'fdsw', 'identifier' => '/test'), $this->storageMock);
		$fixture->updateProperties(array('foo' => 'foobar', 'baz' => 'foobaz'));
		$fixture->updateProperties(array('foo' => 'fdsw', 'baz' => 'asdf'));
		$this->assertEquals(array('foo', 'baz'), $fixture->getUpdatedProperties());
	}

	/**
	 * @test
	 */
	public function updatePropertiesReloadsStorageObjectIfStorageChanges() {
		$fileProperties = array(
			'uid' => 1,
			'storage' => 'first',
		);
		$subject = $this->getMock(
			\TYPO3\CMS\Core\Resource\File::class,
			array('loadStorage'),
			array($fileProperties, $this->storageMock)
		);
		$mockedNewStorage = $this->getMock(\TYPO3\CMS\Core\Resource\ResourceStorage::class, array(), array(), '', FALSE);
		$mockedResourceFactory = $this->getMock(\TYPO3\CMS\Core\Resource\ResourceFactory::class);
		$mockedResourceFactory
			->expects($this->once())
			->method('getStorageObject')
			->will($this->returnValue($mockedNewStorage));
		\TYPO3\CMS\Core\Utility\GeneralUtility::setSingletonInstance(\TYPO3\CMS\Core\Resource\ResourceFactory::class, $mockedResourceFactory);

		$subject->updateProperties(array('storage' => 'different'));
		$this->assertSame($mockedNewStorage, $subject->getStorage());
	}



/**
	 * @test
	 */
	public function copyToCallsCopyOperationOnTargetFolderStorage() {
		$targetStorage = $this->getMock(\TYPO3\CMS\Core\Resource\ResourceStorage::class, array(), array(), '', FALSE);
		$targetFolder = $this->getMock(\TYPO3\CMS\Core\Resource\Folder::class, array(), array(), '', FALSE);
		$targetFolder->expects($this->any())->method('getStorage')->will($this->returnValue($targetStorage));
		$fixture = new \TYPO3\CMS\Core\Resource\File(array(), $this->storageMock);
		$targetStorage->expects($this->once())->method('copyFile')->with($this->equalTo($fixture), $this->equalTo($targetFolder));
		$fixture->copyTo($targetFolder);
	}

	/**
	 * @test
	 */
	public function moveToCallsMoveOperationOnTargetFolderStorage() {
		$targetStorage = $this->getMock(\TYPO3\CMS\Core\Resource\ResourceStorage::class, array(), array(), '', FALSE);
		$targetFolder = $this->getMock(\TYPO3\CMS\Core\Resource\Folder::class, array(), array(), '', FALSE);
		$targetFolder->expects($this->any())->method('getStorage')->will($this->returnValue($targetStorage));
		$fixture = new \TYPO3\CMS\Core\Resource\File(array(), $this->storageMock);
		$targetStorage->expects($this->once())->method('moveFile')->with($this->equalTo($fixture), $this->equalTo($targetFolder));
		$fixture->moveTo($targetFolder);
	}


	public function filenameExtensionDataProvider() {
		return array(
			array('somefile.jpg', 'somefile', 'jpg'),
			array('SomeFile.PNG', 'SomeFile', 'png'),
			array('somefile', 'somefile', ''),
			array('somefile.tar.gz', 'somefile.tar', 'gz'),
			array('somefile.tar.bz2', 'somefile.tar', 'bz2'),
		);
	}

	/**
	 * @test
	 * @dataProvider filenameExtensionDataProvider
	 */
	public function getNameWithoutExtensionReturnsCorrectName($originalFilename, $expectedBasename) {
		$fixture = new \TYPO3\CMS\Core\Resource\File(array(
			'name' => $originalFilename,
			'identifier' => '/' . $originalFilename
		),
		$this->storageMock);
		$this->assertSame($expectedBasename, $fixture->getNameWithoutExtension());
	}

	/**
	 * @test
	 * @dataProvider filenameExtensionDataProvider
	 */
	public function getExtensionReturnsCorrectExtension($originalFilename, $expectedBasename, $expectedExtension) {
		$fixture = new \TYPO3\CMS\Core\Resource\File(array(
			'name' => $originalFilename,
			'identifier' => '/' . $originalFilename
		), $this->storageMock);
		$this->assertSame($expectedExtension, $fixture->getExtension());
	}

	/**
	 * @test
	 */
	public function hasPropertyReturnsTrueFilePropertyExists() {
		$fixture = new \TYPO3\CMS\Core\Resource\File(array('testproperty' => 'testvalue'), $this->storageMock);
		$this->assertTrue($fixture->hasProperty('testproperty'));
	}

	/**
	 * @test
	 */
	public function hasPropertyReturnsTrueIfMetadataPropertyExists() {
		$fixture = $this->getAccessibleMock(\TYPO3\CMS\Core\Resource\File::class, array('dummy'), array(array(), $this->storageMock));
		$fixture->_set('metaDataLoaded', TRUE);
		$fixture->_set('metaDataProperties', array('testproperty' => 'testvalue'));
		$this->assertTrue($fixture->hasProperty('testproperty'));
	}

}
