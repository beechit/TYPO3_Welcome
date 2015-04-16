<?php
namespace TYPO3\CMS\Workspaces\Tests\Functional\DataHandling\IRRE\CSV\PublishAll;

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
class ActionTest extends \TYPO3\CMS\Workspaces\Tests\Functional\DataHandling\IRRE\CSV\AbstractActionTestCase {

	/**
	 * @var string
	 */
	protected $assertionDataSetDirectory = 'typo3/sysext/workspaces/Tests/Functional/DataHandling/IRRE/CSV/PublishAll/DataSet/';

	/**
	 * Parent content records
	 */

	protected function setUp() {
		parent::setUp();
	}

	/**
	 * @test
	 * @see DataSet/Assertion/createParentContentRecord.csv
	 */
	public function createParentContent() {
		parent::createParentContent();
		$this->actionService->publishWorkspace(self::VALUE_WorkspaceId);
		$this->assertAssertionDataSet('createParentContent');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageId, 0)->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionHasRecordConstraint()
			->setTable(self::TABLE_Content)->setField('header')->setValues('Testing #1'));
	}

	/**
	 * @test
	 * @see DataSet/Assertion/modifyParentContentRecord.csv
	 */
	public function modifyParentContent() {
		parent::modifyParentContent();
		$this->actionService->publishWorkspace(self::VALUE_WorkspaceId);
		$this->assertAssertionDataSet('modifyParentContent');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageId, 0)->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionHasRecordConstraint()
			->setTable(self::TABLE_Content)->setField('header')->setValues('Testing #1'));
		$this->assertThat($responseSections, $this->getRequestSectionStructureHasRecordConstraint()
			->setRecordIdentifier(self::TABLE_Content . ':' . self::VALUE_ContentIdLast)->setRecordField(self::FIELD_ContentHotel)
			->setTable(self::TABLE_Hotel)->setField('title')->setValues('Hotel #1'));
	}

	/**
	 * @test
	 * @see DataSet/Assertion/deleteParentContentRecord.csv
	 */
	public function deleteParentContent() {
		parent::deleteParentContent();
		$this->actionService->publishWorkspace(self::VALUE_WorkspaceId);
		$this->assertAssertionDataSet('deleteParentContent');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageId, 0)->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionDoesNotHaveRecordConstraint()
			->setTable(self::TABLE_Content)->setField('header')->setValues('Regular Element #2'));
	}

	/**
	 * @test
	 * @see DataSet/Assertion/deleteParentContentRecordAndDiscardDeletedParentRecord.csv
	 */
	public function deleteParentContentAndDiscardDeletedParent() {
		parent::deleteParentContentAndDiscardDeletedParent();
		$this->actionService->publishWorkspace(self::VALUE_WorkspaceId);
		$this->assertAssertionDataSet('deleteParentContentNDiscardDeletedParent');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageId, 0)->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionHasRecordConstraint()
			->setTable(self::TABLE_Content)->setField('header')->setValues('Regular Element #2'));
	}

	/**
	 * @test
	 * @see DataSet/Assertion/copyParentContentRecord.csv
	 */
	public function copyParentContent() {
		parent::copyParentContent();
		$this->actionService->publishWorkspace(self::VALUE_WorkspaceId);
		$this->assertAssertionDataSet('copyParentContent');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageId, 0)->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionStructureHasRecordConstraint()
			->setRecordIdentifier(self::TABLE_Content . ':' . $this->recordIds['newContentId'])->setRecordField(self::FIELD_ContentHotel)
			->setTable(self::TABLE_Hotel)->setField('title')->setValues('Hotel #1'));
	}

	/**
	 * @test
	 * @see DataSet/copyParentContentToDifferentPage.csv
	 */
	public function copyParentContentToDifferentPage() {
		parent::copyParentContentToDifferentPage();
		$this->actionService->publishWorkspace(self::VALUE_WorkspaceId);
		$this->assertAssertionDataSet('copyParentContentToDifferentPage');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageIdTarget, 0)->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionStructureHasRecordConstraint()
			->setRecordIdentifier(self::TABLE_Content . ':' . $this->recordIds['newContentId'])->setRecordField(self::FIELD_ContentHotel)
			->setTable(self::TABLE_Hotel)->setField('title')->setValues('Hotel #1'));
	}

	/**
	 * @test
	 * @see DataSet/localizeParentContentKeep.csv
	 */
	public function localizeParentContentInKeepMode() {
		parent::localizeParentContentInKeepMode();
		$this->actionService->publishWorkspace(self::VALUE_WorkspaceId);
		$this->assertAssertionDataSet('localizeParentContentKeep');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageId, self::VALUE_LanguageId)->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionStructureDoesNotHaveRecordConstraint()
			->setRecordIdentifier(self::TABLE_Content . ':' . self::VALUE_ContentIdLast)->setRecordField(self::FIELD_ContentHotel)
			->setTable(self::TABLE_Hotel)->setField('title')->setValues('[Translate to Dansk:] Hotel #1'));
	}

	/**
	 * @test
	 * @see DataSet/localizeParentContentWAllChildrenKeep.csv
	 */
	public function localizeParentContentWithAllChildrenInKeepMode() {
		parent::localizeParentContentWithAllChildrenInKeepMode();
		$this->actionService->publishWorkspace(self::VALUE_WorkspaceId);
		$this->assertAssertionDataSet('localizeParentContentWAllChildrenKeep');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageId, self::VALUE_LanguageId)->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionStructureDoesNotHaveRecordConstraint()
			->setRecordIdentifier(self::TABLE_Content . ':' . self::VALUE_ContentIdLast)->setRecordField(self::FIELD_ContentHotel)
			->setTable(self::TABLE_Hotel)->setField('title')->setValues('[Translate to Dansk:] Hotel #1'));
	}

	/**
	 * @test
	 * @see DataSet/localizeParentContentSelect.csv
	 */
	public function localizeParentContentInSelectMode() {
		parent::localizeParentContentInSelectMode();
		$this->actionService->publishWorkspace(self::VALUE_WorkspaceId);
		$this->assertAssertionDataSet('localizeParentContentSelect');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageId, self::VALUE_LanguageId)->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionStructureDoesNotHaveRecordConstraint()
			->setRecordIdentifier(self::TABLE_Content . ':' . self::VALUE_ContentIdLast)->setRecordField(self::FIELD_ContentHotel)
			->setTable(self::TABLE_Hotel)->setField('title')->setValues('[Translate to Dansk:] Hotel #1'));
	}

	/**
	 * @test
	 * @see DataSet/localizeParentContentWAllChildrenSelect.csv
	 */
	public function localizeParentContentWithAllChildrenInSelectMode() {
		parent::localizeParentContentWithAllChildrenInSelectMode();
		$this->actionService->publishWorkspace(self::VALUE_WorkspaceId);
		$this->assertAssertionDataSet('localizeParentContentWAllChildrenSelect');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageId, self::VALUE_LanguageId)->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionStructureHasRecordConstraint()
			->setRecordIdentifier(self::TABLE_Content . ':' . self::VALUE_ContentIdLast)->setRecordField(self::FIELD_ContentHotel)
			->setTable(self::TABLE_Hotel)->setField('title')->setValues('[Translate to Dansk:] Hotel #1'));
	}

	/**
	 * @test
	 * @see DataSet/Assertion/changeParentContentRecordSorting.csv
	 */
	public function changeParentContentSorting() {
		parent::changeParentContentSorting();
		$this->actionService->publishWorkspace(self::VALUE_WorkspaceId);
		$this->assertAssertionDataSet('changeParentContentSorting');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageId, 0)->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionStructureHasRecordConstraint()
			->setRecordIdentifier(self::TABLE_Content . ':' . self::VALUE_ContentIdFirst)->setRecordField(self::FIELD_ContentHotel)
			->setTable(self::TABLE_Hotel)->setField('title')->setValues('Hotel #1', 'Hotel #2'));
		$this->assertThat($responseSections, $this->getRequestSectionStructureHasRecordConstraint()
			->setRecordIdentifier(self::TABLE_Content . ':' . self::VALUE_ContentIdLast)->setRecordField(self::FIELD_ContentHotel)
			->setTable(self::TABLE_Hotel)->setField('title')->setValues('Hotel #1'));
	}

	/**
	 * @test
	 * @see DataSet/Assertion/moveParentContentRecordToDifferentPage.csv
	 */
	public function moveParentContentToDifferentPage() {
		parent::moveParentContentToDifferentPage();
		$this->actionService->publishWorkspace(self::VALUE_WorkspaceId);
		$this->assertAssertionDataSet('moveParentContentToDifferentPage');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageIdTarget, 0)->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionHasRecordConstraint()
			->setTable(self::TABLE_Content)->setField('header')->setValues('Regular Element #2'));
		$this->assertThat($responseSections, $this->getRequestSectionStructureHasRecordConstraint()
			->setRecordIdentifier(self::TABLE_Content . ':' . self::VALUE_ContentIdLast)->setRecordField(self::FIELD_ContentHotel)
			->setTable(self::TABLE_Hotel)->setField('title')->setValues('Hotel #1'));
	}

	/**
	 * @test
	 * @see DataSet/Assertion/moveParentContentRecordToDifferentPageAndChangeSorting.csv
	 */
	public function moveParentContentToDifferentPageAndChangeSorting() {
		parent::moveParentContentToDifferentPageAndChangeSorting();
		$this->actionService->publishWorkspace(self::VALUE_WorkspaceId);
		$this->assertAssertionDataSet('moveParentContentToDifferentPageNChangeSorting');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageIdTarget, 0)->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionHasRecordConstraint()
			->setTable(self::TABLE_Content)->setField('header')->setValues('Regular Element #2', 'Regular Element #1'));
		$this->assertThat($responseSections, $this->getRequestSectionStructureHasRecordConstraint()
			->setRecordIdentifier(self::TABLE_Content . ':' . self::VALUE_ContentIdFirst)->setRecordField(self::FIELD_ContentHotel)
			->setTable(self::TABLE_Hotel)->setField('title')->setValues('Hotel #1', 'Hotel #2'));
		$this->assertThat($responseSections, $this->getRequestSectionStructureHasRecordConstraint()
			->setRecordIdentifier(self::TABLE_Content . ':' . self::VALUE_ContentIdLast)->setRecordField(self::FIELD_ContentHotel)
			->setTable(self::TABLE_Hotel)->setField('title')->setValues('Hotel #1'));
	}

	/**
	 * Page records
	 */

	/**
	 * @test
	 * @see DataSet/Assertion/modifyPageRecord.csv
	 */
	public function modifyPage() {
		parent::modifyPage();
		$this->actionService->publishWorkspace(self::VALUE_WorkspaceId);
		$this->assertAssertionDataSet('modifyPage');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageId, 0)->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionHasRecordConstraint()
			->setTable(self::TABLE_Page)->setField('title')->setValues('Testing #1'));
		$this->assertThat($responseSections, $this->getRequestSectionStructureHasRecordConstraint()
			->setRecordIdentifier(self::TABLE_Content . ':' . self::VALUE_ContentIdFirst)->setRecordField(self::FIELD_ContentHotel)
			->setTable(self::TABLE_Hotel)->setField('title')->setValues('Hotel #1', 'Hotel #2'));
	}

	/**
	 * @test
	 * @see DataSet/Assertion/deletePageRecord.csv
	 */
	public function deletePage() {
		parent::deletePage();
		$this->actionService->publishWorkspace(self::VALUE_WorkspaceId);
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
		$this->actionService->publishWorkspace(self::VALUE_WorkspaceId);
		$this->assertAssertionDataSet('copyPage');

		$responseSections = $this->getFrontendResponse($this->recordIds['newPageId'], 0)->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionHasRecordConstraint()
			->setTable(self::TABLE_Hotel)->setField('title')->setValues('Hotel #1', 'Hotel #2', 'Hotel #1'));
	}

	/**
	 * @test
	 * @see DataSet/Assertion/copyPageWHotelBeforeParentContent.csv
	 */
	public function copyPageWithHotelBeforeParentContent() {
		parent::copyPageWithHotelBeforeParentContent();
		$this->actionService->publishWorkspace(self::VALUE_WorkspaceId);
		$this->assertAssertionDataSet('copyPageWHotelBeforeParentContent');

		$responseSections = $this->getFrontendResponse($this->recordIds['newPageId'], 0)->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionHasRecordConstraint()
			->setTable(self::TABLE_Hotel)->setField('title')->setValues('Hotel #1', 'Hotel #2', 'Hotel #1'));
	}

	/**
	 * IRRE Child Records
	 */

	/**
	 * @test
	 * @see DataSet/Assertion/createParentContentRecordWithHotelAndOfferChildRecords.csv
	 */
	public function createParentContentWithHotelAndOfferChildren() {
		parent::createParentContentWithHotelAndOfferChildren();
		$this->actionService->publishWorkspace(self::VALUE_WorkspaceId);
		$this->assertAssertionDataSet('createParentContentNHotelNOfferChildren');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageId, 0)->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionHasRecordConstraint()
			->setTable(self::TABLE_Content)->setField('header')->setValues('Testing #1'));
		$this->assertThat($responseSections, $this->getRequestSectionStructureHasRecordConstraint()
			->setRecordIdentifier(self::TABLE_Content . ':' . $this->recordIds['newContentId'])->setRecordField(self::FIELD_ContentHotel)
			->setTable(self::TABLE_Hotel)->setField('title')->setValues('Hotel #1'));
	}

	/**
	 * @test
	 * @see DataSet/Assertion/createAndCopyParentContentRecordWithHotelAndOfferChildRecords.csv
	 */
	public function createAndCopyParentContentWithHotelAndOfferChildren() {
		parent::createAndCopyParentContentWithHotelAndOfferChildren();
		$this->actionService->publishWorkspace(self::VALUE_WorkspaceId);
		$this->assertAssertionDataSet('createNCopyParentContentNHotelNOfferChildren');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageId, 0)->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionHasRecordConstraint()
			->setTable(self::TABLE_Content)->setField('header')->setValues('Testing #1 (copy 1)'));
		$this->assertThat($responseSections, $this->getRequestSectionStructureHasRecordConstraint()
			->setRecordIdentifier(self::TABLE_Content . ':' . $this->recordIds['newContentId'])->setRecordField(self::FIELD_ContentHotel)
			->setTable(self::TABLE_Hotel)->setField('title')->setValues('Hotel #1'));
		$this->assertThat($responseSections, $this->getRequestSectionStructureHasRecordConstraint()
			->setRecordIdentifier(self::TABLE_Content . ':' . $this->recordIds['copiedContentId'])->setRecordField(self::FIELD_ContentHotel)
			->setTable(self::TABLE_Hotel)->setField('title')->setValues('Hotel #1'));
		$this->assertThat($responseSections, $this->getRequestSectionStructureHasRecordConstraint()
			->setRecordIdentifier(self::TABLE_Hotel . ':' . $this->recordIds['copiedHotelId'])->setRecordField(self::FIELD_HotelOffer)
			->setTable(self::TABLE_Offer)->setField('title')->setValues('Offer #1'));
	}

	/**
	 * @test
	 * @see DataSet/Assertion/createAndCopyParentContentRecordWithHotelAndOfferChildRecordsAndDiscardCopiedParentRecord.csv
	 */
	public function createAndCopyParentContentWithHotelAndOfferChildrenAndDiscardCopiedParent() {
		parent::createAndCopyParentContentWithHotelAndOfferChildrenAndDiscardCopiedParent();
		$this->actionService->publishWorkspace(self::VALUE_WorkspaceId);
		$this->assertAssertionDataSet('createNCopyParentContentNHotelNOfferChildrenNDiscardCopiedParent');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageId, 0)->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionDoesNotHaveRecordConstraint()
			->setTable(self::TABLE_Content)->setField('header')->setValues('Testing #1 (copy 1)'));
	}

	/**
	 * @test
	 * @see DataSet/Assertion/createAndLocalizeParentContentRecordWithHotelAndOfferChildRecords.csv
	 */
	public function createAndLocalizeParentContentWithHotelAndOfferChildren() {
		parent::createAndLocalizeParentContentWithHotelAndOfferChildren();
		$this->actionService->publishWorkspace(self::VALUE_WorkspaceId);
		$this->assertAssertionDataSet('createNLocalizeParentContentNHotelNOfferChildren');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageId, self::VALUE_LanguageId)->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionHasRecordConstraint()
			->setTable(self::TABLE_Content)->setField('header')->setValues('[Translate to Dansk:] Testing #1'));
		$this->assertThat($responseSections, $this->getRequestSectionStructureHasRecordConstraint()
			->setRecordIdentifier(self::TABLE_Content . ':' . $this->recordIds['newContentId'])->setRecordField(self::FIELD_ContentHotel)
			->setTable(self::TABLE_Hotel)->setField('title')->setValues('[Translate to Dansk:] Hotel #1'));
		$this->assertThat($responseSections, $this->getRequestSectionStructureHasRecordConstraint()
			->setRecordIdentifier(self::TABLE_Hotel . ':' . $this->recordIds['localizedHotelId'])->setRecordField(self::FIELD_HotelOffer)
			->setTable(self::TABLE_Offer)->setField('title')->setValues('[Translate to Dansk:] Offer #1'));
	}

	/**
	 * @test
	 * @see DataSet/Assertion/createNLocalizeParentContentNHotelNOfferChildrenNDiscardCreatedParent.csv
	 */
	public function createAndLocalizeParentContentWithHotelAndOfferChildrenAndDiscardCreatedParent() {
		parent::createAndLocalizeParentContentWithHotelAndOfferChildrenAndDiscardCreatedParent();
		$this->actionService->publishWorkspace(self::VALUE_WorkspaceId);
		$this->assertAssertionDataSet('createNLocalizeParentContentNHotelNOfferChildrenNDiscardCreatedParent');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageId, self::VALUE_LanguageId)->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionDoesNotHaveRecordConstraint()
			->setTable(self::TABLE_Content)->setField('header')->setValues('Testing #1', '[Translate to Dansk:] Testing #1'));
	}

	/**
	 * @test
	 * @see DataSet/Assertion/createAndLocalizeParentContentRecordWithHotelAndOfferChildRecordsAndDiscardLocalizedParentRecord.csv
	 */
	public function createAndLocalizeParentContentWithHotelAndOfferChildrenAndDiscardLocalizedParent() {
		parent::createAndLocalizeParentContentWithHotelAndOfferChildrenAndDiscardLocalizedParent();
		$this->actionService->publishWorkspace(self::VALUE_WorkspaceId);
		$this->assertAssertionDataSet('createNLocalizeParentContentNHotelNOfferChildrenNDiscardLocalizedParent');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageId, self::VALUE_LanguageId)->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionDoesNotHaveRecordConstraint()
			->setTable(self::TABLE_Content)->setField('header')->setValues('[Translate to Dansk:] Testing #1'));
	}

	/**
	 * @test
	 * @see DataSet/Assertion/modifyOnlyHotelChildRecord.csv
	 */
	public function modifyOnlyHotelChild() {
		parent::modifyOnlyHotelChild();
		$this->actionService->publishWorkspace(self::VALUE_WorkspaceId);
		$this->assertAssertionDataSet('modifyOnlyHotelChild');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageId, 0)->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionStructureHasRecordConstraint()
			->setRecordIdentifier(self::TABLE_Content . ':' . self::VALUE_ContentIdFirst)->setRecordField(self::FIELD_ContentHotel)
			->setTable(self::TABLE_Hotel)->setField('title')->setValues('Hotel #1', 'Testing #1'));
	}

	/**
	 * @test
	 * @see DataSet/Assertion/modifyParentRecordAndChangeHotelChildRecordsSorting.csv
	 */
	public function modifyParentAndChangeHotelChildrenSorting() {
		parent::modifyParentAndChangeHotelChildrenSorting();
		$this->actionService->publishWorkspace(self::VALUE_WorkspaceId);
		$this->assertAssertionDataSet('modifyParentNChangeHotelChildrenSorting');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageId, 0)->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionStructureHasRecordConstraint()
			->setRecordIdentifier(self::TABLE_Content . ':' . self::VALUE_ContentIdFirst)->setRecordField(self::FIELD_ContentHotel)
			->setTable(self::TABLE_Hotel)->setField('title')->setValues('Hotel #2', 'Hotel #1'));
	}

	/**
	 * @test
	 * @see DataSet/Assertion/modifyParentRecordWithHotelChildRecord.csv
	 */
	public function modifyParentWithHotelChild() {
		parent::modifyParentWithHotelChild();
		$this->actionService->publishWorkspace(self::VALUE_WorkspaceId);
		$this->assertAssertionDataSet('modifyParentNHotelChild');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageId, 0)->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionStructureHasRecordConstraint()
			->setRecordIdentifier(self::TABLE_Content . ':' . self::VALUE_ContentIdFirst)->setRecordField(self::FIELD_ContentHotel)
			->setTable(self::TABLE_Hotel)->setField('title')->setValues('Hotel #1', 'Testing #1'));
	}

	/**
	 * @test
	 * @see DataSet/Assertion/modifyParentRecordWithHotelChildRecordAndDiscardModifiedParentRecord.csv
	 */
	public function modifyParentWithHotelChildAndDiscardModifiedParent() {
		parent::modifyParentWithHotelChildAndDiscardModifiedParent();
		$this->actionService->publishWorkspace(self::VALUE_WorkspaceId);
		$this->assertAssertionDataSet('modifyParentNHotelChildNDiscardModifiedParent');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageId, 0)->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionHasRecordConstraint()
			->setTable(self::TABLE_Content)->setField('header')->setValues('Regular Element #1'));
		// Discarding the parent shall not discard the child records
		$this->assertThat($responseSections, $this->getRequestSectionStructureHasRecordConstraint()
			->setRecordIdentifier(self::TABLE_Content . ':' . self::VALUE_ContentIdFirst)->setRecordField(self::FIELD_ContentHotel)
			->setTable(self::TABLE_Hotel)->setField('title')->setValues('Hotel #1', 'Testing #1'));
		$this->assertThat($responseSections, $this->getRequestSectionStructureDoesNotHaveRecordConstraint()
			->setRecordIdentifier(self::TABLE_Content . ':' . self::VALUE_ContentIdFirst)->setRecordField(self::FIELD_ContentHotel)
			->setTable(self::TABLE_Hotel)->setField('title')->setValues('Hotel #2'));
	}

	/**
	 * @test
	 * @see DataSet/Assertion/modifyParentRecordWithHotelChildRecordAndDiscardAllModifiedRecords.csv
	 */
	public function modifyParentWithHotelChildAndDiscardAll() {
		parent::modifyParentWithHotelChildAndDiscardAll();
		$this->actionService->publishWorkspace(self::VALUE_WorkspaceId);
		$this->assertAssertionDataSet('modifyParentNHotelChildNDiscardAll');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageId, 0)->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionHasRecordConstraint()
			->setTable(self::TABLE_Content)->setField('header')->setValues('Regular Element #1'));
		$this->assertThat($responseSections, $this->getRequestSectionStructureHasRecordConstraint()
			->setRecordIdentifier(self::TABLE_Content . ':' . self::VALUE_ContentIdFirst)->setRecordField(self::FIELD_ContentHotel)
			->setTable(self::TABLE_Hotel)->setField('title')->setValues('Hotel #1', 'Hotel #2'));
	}

	/**
	 * @test
	 * @see DataSet/Assertion/modifyParentRecordAndAddHotelChildRecord.csv
	 */
	public function modifyParentAndAddHotelChild() {
		parent::modifyParentAndAddHotelChild();
		$this->actionService->publishWorkspace(self::VALUE_WorkspaceId);
		$this->assertAssertionDataSet('modifyParentNAddHotelChild');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageId, 0)->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionStructureHasRecordConstraint()
			->setRecordIdentifier(self::TABLE_Content . ':' . self::VALUE_ContentIdLast)->setRecordField(self::FIELD_ContentHotel)
			->setTable(self::TABLE_Hotel)->setField('title')->setValues('Hotel #1', 'Hotel #2'));
	}

	/**
	 * @test
	 * @see DataSet/Assertion/modifyParentRecordAndDeleteHotelChildRecord.csv
	 */
	public function modifyParentAndDeleteHotelChild() {
		parent::modifyParentAndDeleteHotelChild();
		$this->actionService->publishWorkspace(self::VALUE_WorkspaceId);
		$this->assertAssertionDataSet('modifyParentNDeleteHotelChild');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageId, 0)->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionStructureHasRecordConstraint()
			->setRecordIdentifier(self::TABLE_Content . ':' . self::VALUE_ContentIdLast)->setRecordField(self::FIELD_ContentHotel)
			->setTable(self::TABLE_Hotel)->setField('title')->setValues('Hotel #1'));
		$this->assertThat($responseSections, $this->getRequestSectionStructureDoesNotHaveRecordConstraint()
			->setRecordIdentifier(self::TABLE_Content . ':' . self::VALUE_ContentIdLast)->setRecordField(self::FIELD_ContentHotel)
			->setTable(self::TABLE_Hotel)->setField('title')->setValues('Hotel #2'));
	}

	/**
	 * @test
	 * @see DataSet/modifyNDiscardNModifyParentWHotelChild.csv
	 */
	public function modifyAndDiscardAndModifyParentWithHotelChild() {
		parent::modifyAndDiscardAndModifyParentWithHotelChild();
		$this->actionService->publishWorkspace(self::VALUE_WorkspaceId);
		$this->assertAssertionDataSet('modifyNDiscardNModifyParentWHotelChild');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageId, 0, self::VALUE_BackendUserId, self::VALUE_WorkspaceId)->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionDoesNotHaveRecordConstraint()
			->setTable(self::TABLE_Content)->setField('header')->setValues('Testing #1'));
		$this->assertThat($responseSections, $this->getRequestSectionDoesNotHaveRecordConstraint()
			->setTable(self::TABLE_Hotel)->setField('header')->setValues('Testing #1'));
		$this->assertThat($responseSections, $this->getRequestSectionHasRecordConstraint()
			->setTable(self::TABLE_Content)->setField('header')->setValues('Testing #2'));
		$this->assertThat($responseSections, $this->getRequestSectionHasRecordConstraint()
			->setTable(self::TABLE_Hotel)->setField('title')->setValues('Testing #2'));
	}

}
