<?php
namespace TYPO3\CMS\Core\Tests\Functional\DataHandling\IRRE\CSV\Modify;

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
class ActionTest extends \TYPO3\CMS\Core\Tests\Functional\DataHandling\IRRE\CSV\AbstractActionTestCase {

	/**
	 * @var string
	 */
	protected $assertionDataSetDirectory = 'typo3/sysext/core/Tests/Functional/DataHandling/IRRE/CSV/Modify/DataSet/';

	/**
	 * Parent content records
	 */

	/**
	 * @test
	 * @see DataSet/Assertion/createParentContentRecord.csv
	 */
	public function createParentContent() {
		parent::createParentContent();
		$this->assertAssertionDataSet('createParentContent');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageId)->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionHasRecordConstraint()
			->setTable(self::TABLE_Content)->setField('header')->setValues('Testing #1'));
	}

	/**
	 * @test
	 * @see DataSet/Assertion/modifyParentContentRecord.csv
	 */
	public function modifyParentContent() {
		parent::modifyParentContent();
		$this->assertAssertionDataSet('modifyParentContent');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageId)->getResponseSections();
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
		$this->assertAssertionDataSet('deleteParentContent');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageId)->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionDoesNotHaveRecordConstraint()
			->setTable(self::TABLE_Content)->setField('header')->setValues('Regular Element #2'));
	}

	/**
	 * @test
	 * @see DataSet/Assertion/copyParentContentRecord.csv
	 */
	public function copyParentContent() {
		parent::copyParentContent();
		$this->assertAssertionDataSet('copyParentContent');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageId)->getResponseSections();
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
		$this->assertAssertionDataSet('copyParentContentToDifferentPage');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageIdTarget)->getResponseSections();
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
		$this->assertAssertionDataSet('changeParentContentSorting');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageId)->getResponseSections();
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
		$this->assertAssertionDataSet('moveParentContentToDifferentPage');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageIdTarget)->getResponseSections();
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
		$this->assertAssertionDataSet('moveParentContentToDifferentPageNChangeSorting');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageIdTarget)->getResponseSections();
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
		$this->assertAssertionDataSet('modifyPage');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageId)->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionHasRecordConstraint()
			->setTable(self::TABLE_Page)->setField('title')->setValues('Testing #1'));
	}

	/**
	 * @test
	 * @see DataSet/Assertion/deletePageRecord.csv
	 */
	public function deletePage() {
		parent::deletePage();
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
		$this->assertAssertionDataSet('copyPage');

		$responseSections = $this->getFrontendResponse($this->recordIds['newPageId'])->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionHasRecordConstraint()
			->setTable(self::TABLE_Hotel)->setField('title')->setValues('Hotel #1', 'Hotel #2', 'Hotel #1'));
	}

	/**
	 * @test
	 * @see DataSet/Assertion/copyPageWHotelBeforeParentContent.csv
	 */
	public function copyPageWithHotelBeforeParentContent() {
		parent::copyPageWithHotelBeforeParentContent();
		$this->assertAssertionDataSet('copyPageWHotelBeforeParentContent');

		$responseSections = $this->getFrontendResponse($this->recordIds['newPageId'])->getResponseSections();
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
		$this->assertAssertionDataSet('createParentContentNHotelNOfferChildren');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageId)->getResponseSections();
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
		$this->assertAssertionDataSet('createNCopyParentContentNHotelNOfferChildren');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageId)->getResponseSections();
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
	 * @see DataSet/Assertion/createAndLocalizeParentContentRecordWithHotelAndOfferChildRecords.csv
	 */
	public function createAndLocalizeParentContentWithHotelAndOfferChildren() {
		parent::createAndLocalizeParentContentWithHotelAndOfferChildren();
		$this->assertAssertionDataSet('createNLocalizeParentContentNHotelNOfferChildren');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageId, self::VALUE_LanguageId)->getResponseSections();
		// Content record gets overlaid, thus using newContentId
		$this->assertThat($responseSections, $this->getRequestSectionStructureHasRecordConstraint()
			->setRecordIdentifier(self::TABLE_Content . ':' . $this->recordIds['newContentId'])->setRecordField(self::FIELD_ContentHotel)
			->setTable(self::TABLE_Hotel)->setField('title')->setValues('[Translate to Dansk:] Hotel #1'));
		// Content record directly points to localized child, thus using localizedHotelId
		$this->assertThat($responseSections, $this->getRequestSectionStructureHasRecordConstraint()
			->setRecordIdentifier(self::TABLE_Hotel . ':' . $this->recordIds['localizedHotelId'])->setRecordField(self::FIELD_HotelOffer)
			->setTable(self::TABLE_Offer)->setField('title')->setValues('[Translate to Dansk:] Offer #1'));
	}

	/**
	 * @test
	 * @see DataSet/Assertion/modifyOnlyHotelChildRecord.csv
	 */
	public function modifyOnlyHotelChild() {
		parent::modifyOnlyHotelChild();
		$this->assertAssertionDataSet('modifyOnlyHotelChild');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageId)->getResponseSections();
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
		$this->assertAssertionDataSet('modifyParentNChangeHotelChildrenSorting');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageId)->getResponseSections();
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
		$this->assertAssertionDataSet('modifyParentNHotelChild');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageId)->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionStructureHasRecordConstraint()
			->setRecordIdentifier(self::TABLE_Content . ':' . self::VALUE_ContentIdFirst)->setRecordField(self::FIELD_ContentHotel)
			->setTable(self::TABLE_Hotel)->setField('title')->setValues('Hotel #1', 'Testing #1'));
	}

	/**
	 * @test
	 * @see DataSet/Assertion/modifyParentRecordAndAddHotelChildRecord.csv
	 */
	public function modifyParentAndAddHotelChild() {
		parent::modifyParentAndAddHotelChild();
		$this->assertAssertionDataSet('modifyParentNAddHotelChild');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageId)->getResponseSections();
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
		$this->assertAssertionDataSet('modifyParentNDeleteHotelChild');

		$responseSections = $this->getFrontendResponse(self::VALUE_PageId)->getResponseSections();
		$this->assertThat($responseSections, $this->getRequestSectionStructureHasRecordConstraint()
			->setRecordIdentifier(self::TABLE_Content . ':' . self::VALUE_ContentIdLast)->setRecordField(self::FIELD_ContentHotel)
			->setTable(self::TABLE_Hotel)->setField('title')->setValues('Hotel #1'));
		$this->assertThat($responseSections, $this->getRequestSectionStructureDoesNotHaveRecordConstraint()
			->setRecordIdentifier(self::TABLE_Content . ':' . self::VALUE_ContentIdLast)->setRecordField(self::FIELD_ContentHotel)
			->setTable(self::TABLE_Hotel)->setField('title')->setValues('Hotel #2'));
	}

}
