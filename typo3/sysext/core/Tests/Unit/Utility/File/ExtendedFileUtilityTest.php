<?php
namespace TYPO3\CMS\Core\Tests\Unit\Utility\File;

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
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\File;

/**
 * Testcase for class \TYPO3\CMS\Core\Utility\File\ExtendedFileUtility
 *
 * @author Armin Rüdiger Vieweg <armin@v.ieweg.de>
 */
class ExtendedFileUtilityTest extends \TYPO3\CMS\Core\Tests\UnitTestCase {

	/**
	 * Sets up this testcase
	 */
	protected function setUp() {
		$GLOBALS['LANG'] = $this->getMock(\TYPO3\CMS\Lang\LanguageService::class, array('sL'));
		$GLOBALS['TYPO3_DB'] = $this->getMock(\TYPO3\CMS\Core\Database\DatabaseConnection::class, array());
	}

	/**
	 * @test
	 */
	public function folderHasFilesInUseReturnsTrueIfItHasFiles() {
		$fileUid = 1;
		$file = $this->getMock(File::class, array('getUid'), array(), '', FALSE);
		$file->expects($this->once())->method('getUid')->will($this->returnValue($fileUid));

		$folder = $this->getMock(Folder::class, array('getFiles'), array(), '', FALSE);
		$folder->expects($this->once())
			->method('getFiles')->with(0, 0, Folder::FILTER_MODE_USE_OWN_AND_STORAGE_FILTERS, TRUE)
			->will($this->returnValue(array($file))
		);

		/** @var \TYPO3\CMS\Core\Utility\File\ExtendedFileUtility $subject */
		$subject = $this->getMock(\TYPO3\CMS\Core\Utility\File\ExtendedFileUtility::class, array('addFlashMessage'), array(), '');
		$GLOBALS['TYPO3_DB']->expects($this->once())
			->method('exec_SELECTcountRows')->with('*', 'sys_refindex', 'deleted=0 AND ref_table="sys_file" AND ref_uid IN (' . $fileUid . ') AND tablename<>"sys_file_metadata"')
			->will($this->returnValue(1));

		$GLOBALS['LANG']->expects($this->at(0))->method('sL')
			->with('LLL:EXT:lang/locallang_core.xlf:message.description.folderNotDeletedHasFilesWithReferences')
			->will($this->returnValue('folderNotDeletedHasFilesWithReferences'));
		$GLOBALS['LANG']->expects($this->at(1))->method('sL')
			->with('LLL:EXT:lang/locallang_core.xlf:message.header.folderNotDeletedHasFilesWithReferences')
			->will($this->returnValue('folderNotDeletedHasFilesWithReferences'));

		$result = $subject->folderHasFilesInUse($folder);
		$this->assertTrue($result);
	}

	/**
	 * @test
	 */
	public function folderHasFilesInUseReturnsFalseIfItHasNoFiles() {
		$folder = $this->getMock(Folder::class, array('getFiles'), array(), '', FALSE);
		$folder->expects($this->once())->method('getFiles')->with(0, 0, Folder::FILTER_MODE_USE_OWN_AND_STORAGE_FILTERS, TRUE)->will(
			$this->returnValue(array())
		);

		/** @var \TYPO3\CMS\Core\Utility\File\ExtendedFileUtility $subject */
		$subject = $this->getMock(\TYPO3\CMS\Core\Utility\File\ExtendedFileUtility::class, array('addFlashMessage'), array(), '');
		$this->assertFalse($subject->folderHasFilesInUse($folder));
	}

}