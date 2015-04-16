<?php
namespace TYPO3\CMS\Workspaces\Tests\Functional\DataHandling\Regular\Publish;

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

require_once dirname(dirname(__FILE__)) . '/AbstractActionTestCase.php';

/**
 * Functional test for the DataHandler
 */
class ActionTest extends \TYPO3\CMS\Workspaces\Tests\Functional\DataHandling\Regular\AbstractActionTestCase {

	/**
	 * @var string
	 */
	protected $assertionDataSetDirectory = 'typo3/sysext/workspaces/Tests/Functional/DataHandling/Regular/Publish/DataSet/';

	/**
	 * Content records
	 */

	/**
	 * @test
	 * @see DataSet/Assertion/createContentRecords.csv
	 */
	public function createContents() {
		parent::createContents();
		$this->actionService->publishRecords(
			array(
				self::TABLE_Content => array($this->recordIds['newContentIdFirst'], $this->recordIds['newContentIdLast']),
			)
		);
		$this->assertAssertionDataSet('createContents');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageId, 0)->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionHasRecordConstraint()
			->setTable(self::TABLE_Content)->setField('header')->setValues('Testing #1', 'Testing #2'));
	}

	/**
	 * @test
	 * @see DataSet/Assertion/createContentRecordAndDiscardCreatedContentRecord.csv
	 */
	public function createContentAndDiscardCreatedContent() {
		parent::createContentAndDiscardCreatedContent();
		// Actually this is not required, since there's nothing to publish... but it's a test case!
		$this->actionService->publishRecord(self::TABLE_Content, $this->recordIds['newContentId'], FALSE);
		$this->assertAssertionDataSet('createContentNDiscardCreatedContent');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageId, 0)->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionDoesNotHaveRecordConstraint()
			->setTable(self::TABLE_Content)->setField('header')->setValues('Testing #1'));
	}

	/**
	 * @test
	 * @see DataSet/Assertion/createAndCopyContentRecordAndDiscardCopiedContentRecord.csv
	 */
	public function createAndCopyContentAndDiscardCopiedContent() {
		parent::createAndCopyContentAndDiscardCopiedContent();
		$this->actionService->publishRecord(self::TABLE_Content, $this->recordIds['newContentId']);
		// Actually this is not required, since there's nothing to publish... but it's a test case!
		$this->actionService->publishRecord(self::TABLE_Content, $this->recordIds['copiedContentId'], FALSE);
		$this->assertAssertionDataSet('createNCopyContentNDiscardCopiedContent');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageId, 0)->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionHasRecordConstraint()
			->setTable(self::TABLE_Content)->setField('header')->setValues('Testing #1'));
		$this->assertThat($responseSections, $this->getRequestSectionDoesNotHaveRecordConstraint()
			->setTable(self::TABLE_Content)->setField('header')->setValues('Testing #1 (copy 1)'));
	}

	/**
	 * @test
	 * @see DataSet/Assertion/modifyContentRecord.csv
	 */
	public function modifyContent() {
		parent::modifyContent();
		$this->actionService->publishRecord(self::TABLE_Content, self::VALUE_ContentIdSecond);
		$this->assertAssertionDataSet('modifyContent');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageId, 0)->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionHasRecordConstraint()
			->setTable(self::TABLE_Content)->setField('header')->setValues('Testing #1'));
	}

	/**
	 * @test
	 * @see DataSet/Assertion/deleteContentRecord.csv
	 */
	public function deleteContent() {
		parent::deleteContent();
		$this->actionService->publishRecord(self::TABLE_Content, self::VALUE_ContentIdSecond);
		$this->assertAssertionDataSet('deleteContent');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageId, 0)->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionHasRecordConstraint()
			->setTable(self::TABLE_Content)->setField('header')->setValues('Regular Element #1'));
		$this->assertThat($responseSections, $this->getRequestSectionDoesNotHaveRecordConstraint()
			->setTable(self::TABLE_Content)->setField('header')->setValues('Regular Element #2'));
	}

	/**
	 * @test
	 * @see DataSet/deleteLocalizedContentNDeleteContent.csv
	 */
	public function deleteLocalizedContentAndDeleteContent() {
		parent::deleteLocalizedContentAndDeleteContent();
		$this->actionService->publishRecords(
			array(
				self::TABLE_Content => array(self::VALUE_ContentIdThird, self::VALUE_ContentIdThirdLocalized),
			)
		);
		$this->assertAssertionDataSet('deleteLocalizedContentNDeleteContent');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageId, self::VALUE_LanguageId)->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionDoesNotHaveRecordConstraint()
			->setTable(self::TABLE_Content)->setField('header')->setValues('Regular Element #3', '[Translate to Dansk:] Regular Element #3'));
	}

	/**
	 * @test
	 * @see DataSet/Assertion/copyContentRecord.csv
	 */
	public function copyContent() {
		parent::copyContent();
		$this->actionService->publishRecord(self::TABLE_Content, $this->recordIds['copiedContentId']);
		$this->assertAssertionDataSet('copyContent');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageId, 0)->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionHasRecordConstraint()
			->setTable(self::TABLE_Content)->setField('header')->setValues('Regular Element #2 (copy 1)'));
	}

	/**
	 * @test
	 * @see DataSet/Assertion/localizeContentRecord.csv
	 */
	public function localizeContent() {
		parent::localizeContent();
		$this->actionService->publishRecord(self::TABLE_Content, $this->recordIds['localizedContentId']);
		$this->assertAssertionDataSet('localizeContent');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageId, self::VALUE_LanguageId)->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionHasRecordConstraint()
			->setTable(self::TABLE_Content)->setField('header')->setValues('Regular Element #1', '[Translate to Dansk:] Regular Element #2'));
	}

	/**
	 * @test
	 * @see DataSet/Assertion/changeContentRecordSorting.csv
	 */
	public function changeContentSorting() {
		parent::changeContentSorting();
		$this->actionService->publishRecord(self::TABLE_Content, self::VALUE_ContentIdFirst);
		$this->assertAssertionDataSet('changeContentSorting');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageId, 0)->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionHasRecordConstraint()
			->setTable(self::TABLE_Content)->setField('header')->setValues('Regular Element #1', 'Regular Element #2'));
	}

	/**
	 * @test
	 * @see DataSet/Assertion/moveContentRecordToDifferentPage.csv
	 */
	public function moveContentToDifferentPage() {
		parent::moveContentToDifferentPage();
		$this->actionService->publishRecord(self::TABLE_Content, self::VALUE_ContentIdSecond);
		$this->assertAssertionDataSet('moveContentToDifferentPage');

		$responseSectionsSource = $this->getFrontendResponse(self::VALUE_PageId, 0)->getResponseSections();
		$this->assertThat($responseSectionsSource, $this->getRequestSectionHasRecordConstraint()
			->setTable(self::TABLE_Content)->setField('header')->setValues('Regular Element #1'));
		$responseSectionsTarget = $this->getFrontendResponse(self::VALUE_PageIdTarget, 0)->getResponseSections();
		$this->assertThat($responseSectionsTarget, $this->getRequestSectionHasRecordConstraint()
			->setTable(self::TABLE_Content)->setField('header')->setValues('Regular Element #2'));
	}

	/**
	 * @test
	 * @see DataSet/Assertion/moveContentRecordToDifferentPageAndChangeSorting.csv
	 */
	public function moveContentToDifferentPageAndChangeSorting() {
		parent::moveContentToDifferentPageAndChangeSorting();
		$this->actionService->publishRecords(
			array(
				self::TABLE_Content => array(self::VALUE_ContentIdFirst, self::VALUE_ContentIdSecond),
			)
		);
		$this->assertAssertionDataSet('moveContentToDifferentPageNChangeSorting');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageIdTarget, 0)->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionHasRecordConstraint()
			->setTable(self::TABLE_Content)->setField('header')->setValues('Regular Element #1', 'Regular Element #2'));
	}

	/**
	 * Page records
	 */

	/**
	 * @test
	 * @see DataSet/Assertion/createPageRecord.csv
	 */
	public function createPage() {
		parent::createPage();
		$this->actionService->publishRecord(self::TABLE_Page, $this->recordIds['newPageId']);
		$this->assertAssertionDataSet('createPage');

		$responseSections = $this->getFrontendResponse($this->recordIds['newPageId'], 0)->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionHasRecordConstraint()
			->setTable(self::TABLE_Page)->setField('title')->setValues('Testing #1'));
	}

	/**
	 * @test
	 * @see DataSet/Assertion/modifyPageRecord.csv
	 */
	public function modifyPage() {
		parent::modifyPage();
		$this->actionService->publishRecord(self::TABLE_Page, self::VALUE_PageId);
		$this->assertAssertionDataSet('modifyPage');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageId, 0)->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionHasRecordConstraint()
			->setTable(self::TABLE_Page)->setField('title')->setValues('Testing #1'));
	}

	/**
	 * @test
	 * @see DataSet/Assertion/deletePageRecord.csv
	 */
	public function deletePage() {
		parent::deletePage();
		$this->actionService->publishRecord(self::TABLE_Page, self::VALUE_PageId);
		$this->assertAssertionDataSet('deletePage');

		$response = $this->getFrontendResponse(self::VALUE_PageId, 0, 0, 0, FALSE);
		$this->assertContains('PageNotFoundException', $response->getError());
	}

	/**
	 * @test
	 * @see DataSet/Assertion/copyPageRecord.csv
	 */
	public function copyPage() {
		parent::copyPage();
		$this->actionService->publishRecords(
			array(
				self::TABLE_Page => array($this->recordIds['newPageId']),
				self::TABLE_Content => array($this->recordIds['newContentIdFirst'], $this->recordIds['newContentIdLast']),
			)
		);
		$this->assertAssertionDataSet('copyPage');

		$responseSections = $this->getFrontendResponse($this->recordIds['newPageId'], 0)->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionHasRecordConstraint()
			->setTable(self::TABLE_Page)->setField('title')->setValues('Relations'));
	}

	/**
	 * @test
	 * @see DataSet/Assertion/localizePageRecord.csv
	 */
	public function localizePage() {
		parent::localizePage();
		$this->actionService->publishRecord(self::TABLE_PageOverlay, $this->recordIds['localizedPageOverlayId']);
		$this->assertAssertionDataSet('localizePage');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageId, self::VALUE_LanguageId)->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionHasRecordConstraint()
			->setTable(self::TABLE_Page)->setField('title')->setValues('[Translate to Dansk:] Relations'));
	}

	/**
	 * @test
	 * @see DataSet/Assertion/changePageRecordSorting.csv
	 */
	public function changePageSorting() {
		parent::changePageSorting();
		$this->actionService->publishRecord(self::TABLE_Page, self::VALUE_PageId);
		$this->assertAssertionDataSet('changePageSorting');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageId, 0)->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionHasRecordConstraint()
			->setTable(self::TABLE_Page)->setField('title')->setValues('Relations'));
		$this->assertThat($responseSections, $this->getRequestSectionHasRecordConstraint()
			->setTable(self::TABLE_Content)->setField('header')->setValues('Regular Element #1', 'Regular Element #2'));
	}

	/**
	 * @test
	 * @see DataSet/Assertion/movePageRecordToDifferentPage.csv
	 */
	public function movePageToDifferentPage() {
		parent::movePageToDifferentPage();
		$this->actionService->publishRecord(self::TABLE_Page, self::VALUE_PageId);
		$this->assertAssertionDataSet('movePageToDifferentPage');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageId, 0)->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionHasRecordConstraint()
			->setTable(self::TABLE_Page)->setField('title')->setValues('Relations'));
		$this->assertThat($responseSections, $this->getRequestSectionHasRecordConstraint()
			->setTable(self::TABLE_Content)->setField('header')->setValues('Regular Element #1', 'Regular Element #2'));
	}

	/**
	 * @test
	 * @see DataSet/Assertion/movePageRecordToDifferentPageAndChangeSorting.csv
	 */
	public function movePageToDifferentPageAndChangeSorting() {
		parent::movePageToDifferentPageAndChangeSorting();
		$this->actionService->publishRecords(
			array(
				self::TABLE_Page => array(self::VALUE_PageId, self::VALUE_PageIdTarget),
			)
		);
		$this->assertAssertionDataSet('movePageToDifferentPageNChangeSorting');

		$responseSectionsPage = $this->getFrontendResponse(self::VALUE_PageId, 0)->getResponseSections();
		$this->assertThat($responseSectionsPage, $this->getRequestSectionHasRecordConstraint()
			->setTable(self::TABLE_Page)->setField('title')->setValues('Relations'));
		$this->assertThat($responseSectionsPage, $this->getRequestSectionHasRecordConstraint()
			->setTable(self::TABLE_Content)->setField('header')->setValues('Regular Element #1', 'Regular Element #2'));
		$responseSectionsWebsite = $this->getFrontendResponse(self::VALUE_PageIdWebsite, 0)->getResponseSections();
		$this->assertThat($responseSectionsWebsite, $this->getRequestSectionStructureHasRecordConstraint()
			->setRecordIdentifier(self::TABLE_Page . ':' . self::VALUE_PageIdWebsite)->setRecordField('__pages')
			->setTable(self::TABLE_Page)->setField('title')->setValues('Target', 'Relations', 'DataHandlerTest'));
	}

	/**
	 * @test
	 * @see DataSet/Assertion/movePageRecordToDifferentPageAndCreatePageRecordAfterMovedPageRecord.csv
	 * @see http://forge.typo3.org/issues/33104
	 * @see http://forge.typo3.org/issues/55573
	 */
	public function movePageToDifferentPageAndCreatePageAfterMovedPage() {
		parent::movePageToDifferentPageAndCreatePageAfterMovedPage();
		$this->actionService->publishRecords(
			array(
				self::TABLE_Page => array(self::VALUE_PageIdTarget, $this->recordIds['newPageId']),
			)
		);
		$this->assertAssertionDataSet('movePageToDifferentPageNCreatePageAfterMovedPage');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageIdWebsite, 0)->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionStructureHasRecordConstraint()
			->setRecordIdentifier(self::TABLE_Page . ':' . self::VALUE_PageIdWebsite)->setRecordField('__pages')
			->setTable(self::TABLE_Page)->setField('title')->setValues('Target', 'Testing #1', 'DataHandlerTest'));
	}

}
