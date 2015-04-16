<?php
namespace TYPO3\CMS\Core\Tests\Functional\DataHandling\FAL;

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

require_once dirname(dirname(__FILE__)) . '/AbstractDataHandlerActionTestCase.php';

/**
 * Functional test for the DataHandler
 */
abstract class AbstractActionTestCase extends \TYPO3\CMS\Core\Tests\Functional\DataHandling\AbstractDataHandlerActionTestCase {

	const VALUE_PageId = 89;
	const VALUE_PageIdTarget = 90;
	const VALUE_PageIdWebsite = 1;
	const VALUE_ContentIdFirst = 330;
	const VALUE_ContentIdLast = 331;
	const VALUE_FileIdFirst = 1;
	const VALUE_FileIdLast = 21;
	const VALUE_LanguageId = 1;

	const VALUE_FileReferenceContentFirstFileFirst = 126;
	const VALUE_FileReferenceContentFirstFileLast = 127;
	const VALUE_FileReferenceContentLastFileLast = 128;
	const VALUE_FileReferenceContentLastFileFirst = 129;

	const TABLE_Page = 'pages';
	const TABLE_Content = 'tt_content';
	const TABLE_File = 'sys_file';
	const TABLE_FileMetadata = 'sys_file_metadata';
	const TABLE_FileReference = 'sys_file_reference';

	const FIELD_ContentImage = 'image';
	const FIELD_FileReferenceImage = 'uid_local';

	/**
	 * @var string
	 */
	protected $scenarioDataSetDirectory = 'typo3/sysext/core/Tests/Functional/DataHandling/FAL/DataSet/';

	protected function setUp() {
		parent::setUp();
		$this->importScenarioDataSet('LiveDefaultPages');
		$this->importScenarioDataSet('LiveDefaultElements');
		$this->importDataSet(ORIGINAL_ROOT . 'typo3/sysext/core/Tests/Functional/Fixtures/sys_file_storage.xml');

		$this->setUpFrontendRootPage(1, array('typo3/sysext/core/Tests/Functional/Fixtures/Frontend/JsonRenderer.ts'));
		$this->backendUser->workspace = 0;
	}

	/**
	 * Content records
	 */

	/**
	 * @see DataSet/Assertion/modifyContentRecord.csv
	 */
	public function modifyContent() {
		$this->actionService->modifyRecord(self::TABLE_Content, self::VALUE_ContentIdLast, array('header' => 'Testing #1'));
	}

	/**
	 * @see DataSet/Assertion/deleteContentRecord.csv
	 */
	public function deleteContent() {
		$this->actionService->deleteRecord(self::TABLE_Content, self::VALUE_ContentIdLast);
	}

	/**
	 * @see DataSet/Assertion/copyContentRecord.csv
	 */
	public function copyContent() {
		$newTableIds = $this->actionService->copyRecord(self::TABLE_Content, self::VALUE_ContentIdLast, self::VALUE_PageId);
		$this->recordIds['copiedContentId'] = $newTableIds[self::TABLE_Content][self::VALUE_ContentIdLast];
	}

	/**
	 * @see DataSet/Assertion/localizeContentRecord.csv
	 */
	public function localizeContent() {
		$newTableIds = $this->actionService->localizeRecord(self::TABLE_Content, self::VALUE_ContentIdLast, self::VALUE_LanguageId);
		$this->recordIds['localizedContentId'] = $newTableIds[self::TABLE_Content][self::VALUE_ContentIdLast];
	}

	/**
	 * @see DataSet/Assertion/changeContentRecordSorting.csv
	 */
	public function changeContentSorting() {
		$this->actionService->moveRecord(self::TABLE_Content, self::VALUE_ContentIdFirst, -self::VALUE_ContentIdLast);
	}

	/**
	 * @see DataSet/Assertion/moveContentRecordToDifferentPage.csv
	 */
	public function moveContentToDifferentPage() {
		$this->actionService->moveRecord(self::TABLE_Content, self::VALUE_ContentIdLast, self::VALUE_PageIdTarget);
	}

	/**
	 * @see DataSet/Assertion/moveContentRecordToDifferentPageAndChangeSorting.csv
	 */
	public function moveContentToDifferentPageAndChangeSorting() {
		$this->actionService->moveRecord(self::TABLE_Content, self::VALUE_ContentIdLast, self::VALUE_PageIdTarget);
		$this->actionService->moveRecord(self::TABLE_Content, self::VALUE_ContentIdFirst, -self::VALUE_ContentIdLast);
	}

	/**
	 * File references
	 */

	public function createContentWithFileReference() {
		$newTableIds = $this->actionService->createNewRecords(
			self::VALUE_PageId,
			array(
				self::TABLE_Content => array('header' => 'Testing #1', self::FIELD_ContentImage => '__nextUid'),
				self::TABLE_FileReference => array('title' => 'Image #1', self::FIELD_FileReferenceImage => self::VALUE_FileIdFirst),
			)
		);
		$this->recordIds['newContentId'] = $newTableIds[self::TABLE_Content][0];
	}

	public function modifyContentWithFileReference() {
		$this->actionService->modifyRecords(
			self::VALUE_PageId,
			array(
				self::TABLE_Content => array('uid' => self::VALUE_ContentIdLast, 'header' => 'Testing #1', self::FIELD_ContentImage => self::VALUE_FileReferenceContentLastFileLast . ',' . self::VALUE_FileReferenceContentLastFileFirst),
				self::TABLE_FileReference => array('uid' => self::VALUE_FileReferenceContentLastFileFirst, 'title' => 'Image #1'),
			)
		);
	}

	public function modifyContentAndAddFileReference() {
		$this->actionService->modifyRecords(
			self::VALUE_PageId,
			array(
				self::TABLE_Content => array('uid' => self::VALUE_ContentIdLast, self::FIELD_ContentImage => self::VALUE_FileReferenceContentLastFileLast . ',' . self::VALUE_FileReferenceContentLastFileFirst . ',__nextUid'),
				self::TABLE_FileReference => array('uid' => '__NEW', 'title' => 'Image #3', self::FIELD_FileReferenceImage => self::VALUE_FileIdFirst),
			)
		);
	}

	public function modifyContentAndDeleteFileReference() {
		$this->actionService->modifyRecord(
			self::TABLE_Content,
			self::VALUE_ContentIdLast,
			array(self::FIELD_ContentImage => self::VALUE_FileReferenceContentLastFileFirst),
			array(self::TABLE_FileReference => array(self::VALUE_FileReferenceContentLastFileLast))
		);
	}

	public function modifyContentAndDeleteAllFileReference() {
		$this->actionService->modifyRecord(
			self::TABLE_Content,
			self::VALUE_ContentIdLast,
			array(self::FIELD_ContentImage => ''),
			array(self::TABLE_FileReference => array(self::VALUE_FileReferenceContentLastFileFirst, self::VALUE_FileReferenceContentLastFileLast))
		);
	}

}
