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

use \org\bovigo\vfs\vfsStream;

/**
 * Testcase for the storage collection class of the TYPO3 FAL
 *
 * @author Andreas Wolf <andreas.wolf@ikt-werk.de>
 */
class FolderTest extends \TYPO3\CMS\Core\Tests\UnitTestCase {

	/**
	 * @var array A backup of registered singleton instances
	 */
	protected $singletonInstances = array();

	protected $basedir = 'basedir';

	protected function setUp() {
		$this->singletonInstances = \TYPO3\CMS\Core\Utility\GeneralUtility::getSingletonInstances();
		vfsStream::setup($this->basedir);
	}

	protected function tearDown() {
		\TYPO3\CMS\Core\Utility\GeneralUtility::resetSingletonInstances($this->singletonInstances);
		parent::tearDown();
	}

	protected function createFolderFixture($path, $name, $mockedStorage = NULL) {
		if ($mockedStorage === NULL) {
			$mockedStorage = $this->getMock(\TYPO3\CMS\Core\Resource\ResourceStorage::class, array(), array(), '', FALSE);
		}
		return new \TYPO3\CMS\Core\Resource\Folder($mockedStorage, $path, $name, 0);
	}

	/**
	 * @test
	 */
	public function constructorArgumentsAreAvailableAtRuntime() {
		$path = $this->getUniqueId();
		$name = $this->getUniqueId();
		$mockedStorage = $this->getMock(\TYPO3\CMS\Core\Resource\ResourceStorage::class, array(), array(), '', FALSE);
		$fixture = $this->createFolderFixture($path, $name, $mockedStorage);
		$this->assertSame($mockedStorage, $fixture->getStorage());
		$this->assertStringStartsWith($path, $fixture->getIdentifier());
		$this->assertSame($name, $fixture->getName());
	}

	/**
	 * @test
	 */
	public function propertiesCanBeUpdated() {
		$fixture = $this->createFolderFixture('/somePath', 'someName');
		$fixture->updateProperties(array('identifier' => '/someOtherPath', 'name' => 'someNewName'));
		$this->assertSame('someNewName', $fixture->getName());
		$this->assertSame('/someOtherPath', $fixture->getIdentifier());
	}

	/**
	 * @test
	 */
	public function propertiesAreNotUpdatedIfNotSetInInput() {
		$fixture = $this->createFolderFixture('/somePath/someName/', 'someName');
		$fixture->updateProperties(array('identifier' => '/someOtherPath'));
		$this->assertSame('someName', $fixture->getName());
	}

	/**
	 * @test
	 */
	public function getFilesReturnsArrayWithFilenamesAsKeys() {
		$mockedStorage = $this->getMock(\TYPO3\CMS\Core\Resource\ResourceStorage::class, array(), array(), '', FALSE);
		$mockedStorage->expects($this->once())->method('getFilesInFolder')->will($this->returnValue(array(
				'somefile.png' => array(
					'name' => 'somefile.png'
				),
				'somefile.jpg' => array(
					'name' => 'somefile.jpg'
				)
			)
		));
		$fixture = $this->createFolderFixture('/somePath', 'someName', $mockedStorage);

		$fileList = $fixture->getFiles();

		$this->assertSame(array('somefile.png', 'somefile.jpg'), array_keys($fileList));
	}

	/**
	 * @test
	 */
	public function getFilesHandsOverRecursiveFALSEifNotExplicitlySet() {
		$mockedStorage = $this->getMock(\TYPO3\CMS\Core\Resource\ResourceStorage::class, array(), array(), '', FALSE);
		$mockedStorage
			->expects($this->once())
			->method('getFilesInFolder')
			->with($this->anything(), $this->anything(), $this->anything(), $this->anything(), FALSE)
			->will($this->returnValue(array()));

		$fixture = $this->createFolderFixture('/somePath', 'someName', $mockedStorage);
		$fixture->getFiles();
	}

	/**
	 * @test
	 */
	public function getFilesHandsOverRecursiveTRUEifSet() {
		$mockedStorage = $this->getMock(\TYPO3\CMS\Core\Resource\ResourceStorage::class, array(), array(), '', FALSE);
		$mockedStorage
			->expects($this->once())
			->method('getFilesInFolder')
			->with($this->anything(), $this->anything(), $this->anything(), $this->anything(), TRUE)
			->will($this->returnValue(array()));

		$fixture = $this->createFolderFixture('/somePath', 'someName', $mockedStorage);
		$fixture->getFiles(0, 0, \TYPO3\CMS\Core\Resource\Folder::FILTER_MODE_USE_OWN_AND_STORAGE_FILTERS, TRUE);
	}

	/**
	 * @test
	 */
	public function getSubfolderCallsFactoryWithCorrectArguments() {
		$mockedStorage = $this->getMock(\TYPO3\CMS\Core\Resource\ResourceStorage::class, array(), array(), '', FALSE);
		$mockedStorage->expects($this->once())->method('hasFolderInFolder')->with($this->equalTo('someSubfolder'))->will($this->returnValue(TRUE));
		$mockedFactory = $this->getMock(\TYPO3\CMS\Core\Resource\ResourceFactory::class);
		$mockedFactory->expects($this->once())->method('createFolderObject')->with($mockedStorage, '/somePath/someFolder/someSubfolder/', 'someSubfolder');
		\TYPO3\CMS\Core\Utility\GeneralUtility::setSingletonInstance(\TYPO3\CMS\Core\Resource\ResourceFactory::class, $mockedFactory);
		$fixture = $this->createFolderFixture('/somePath/someFolder/', 'someFolder', $mockedStorage);
		$fixture->getSubfolder('someSubfolder');
	}

	/**
	 * @test
	 */
	public function getParentFolderGetsParentFolderFromStorage() {
		$parentIdentifier = '/parent/';
		$currentIdentifier = '/parent/current/';

		$parentFolderFixture = $this->createFolderFixture($parentIdentifier, 'parent');
		$mockedStorage = $this->getMock(\TYPO3\CMS\Core\Resource\ResourceStorage::class, array('getFolderIdentifierFromFileIdentifier', 'getFolder'), array(), '', FALSE);
		$mockedStorage->expects($this->once())->method('getFolderIdentifierFromFileIdentifier')->with($currentIdentifier)->will($this->returnValue($parentIdentifier));
		$mockedStorage->expects($this->once())->method('getFolder')->with($parentIdentifier)->will($this->returnValue($parentFolderFixture));

		$currentFolderFixture = $this->createFolderFixture($currentIdentifier, 'current', $mockedStorage);

		$this->assertSame($parentFolderFixture, $currentFolderFixture->getParentFolder());
	}

}
