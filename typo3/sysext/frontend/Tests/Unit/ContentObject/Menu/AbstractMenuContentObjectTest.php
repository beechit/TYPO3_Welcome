<?php
namespace TYPO3\CMS\Frontend\Tests\Unit\ContentObject\Menu;

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
 * Test case
 *
 */
class AbstractMenuContentObjectTest extends \TYPO3\CMS\Core\Tests\UnitTestCase {

	/**
	 * @var \TYPO3\CMS\Frontend\ContentObject\Menu\AbstractMenuContentObject
	 */
	protected $subject = NULL;

	/**
	 * Set up this testcase
	 */
	protected function setUp() {
		$proxy = $this->buildAccessibleProxy(\TYPO3\CMS\Frontend\ContentObject\Menu\AbstractMenuContentObject::class);
		$this->subject = new $proxy();
		$GLOBALS['TYPO3_DB'] = $this->getMock(\TYPO3\CMS\Core\Database\DatabaseConnection::class);
		$GLOBALS['TSFE'] = $this->getMock(\TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController::class, array(), array($GLOBALS['TYPO3_CONF_VARS'], 1, 1));
		$GLOBALS['TSFE']->cObj = new \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer();
		$GLOBALS['TSFE']->page = array();
	}

	////////////////////////////////
	// Tests concerning sectionIndex
	////////////////////////////////
	/**
	 * Prepares a test for the method sectionIndex
	 *
	 * @return void
	 */
	protected function prepareSectionIndexTest() {
		$this->subject->sys_page = $this->getMock(\TYPO3\CMS\Frontend\Page\PageRepository::class);
		$this->subject->parent_cObj = $this->getMock(\TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer::class);
	}

	/**
	 * @test
	 */
	public function sectionIndexReturnsEmptyArrayIfTheRequestedPageCouldNotBeFetched() {
		$this->prepareSectionIndexTest();
		$this->subject->sys_page->expects($this->once())->method('getPage')->will($this->returnValue(NULL));
		$result = $this->subject->_call('sectionIndex', 'field');
		$this->assertEquals($result, array());
	}

	/**
	 * @test
	 */
	public function sectionIndexUsesTheInternalIdIfNoPageIdWasGiven() {
		$this->prepareSectionIndexTest();
		$this->subject->id = 10;
		$this->subject->sys_page->expects($this->once())->method('getPage')->will($this->returnValue(NULL))->with(10);
		$result = $this->subject->_call('sectionIndex', 'field');
		$this->assertEquals($result, array());
	}

	/**
	 * @test
	 * @expectedException UnexpectedValueException
	 */
	public function sectionIndexThrowsAnExceptionIfTheInternalQueryFails() {
		$this->prepareSectionIndexTest();
		$this->subject->sys_page->expects($this->once())->method('getPage')->will($this->returnValue(array()));
		$this->subject->parent_cObj->expects($this->once())->method('exec_getQuery')->will($this->returnValue(0));
		$this->subject->_call('sectionIndex', 'field');
	}

	/**
	 * @test
	 */
	public function sectionIndexReturnsOverlaidRowBasedOnTheLanguageOfTheGivenPage() {
		$this->prepareSectionIndexTest();
		$this->subject->mconf['sectionIndex.']['type'] = 'all';
		$GLOBALS['TSFE']->sys_language_contentOL = 1;
		$this->subject->sys_page->expects($this->once())->method('getPage')->will($this->returnValue(array('_PAGES_OVERLAY_LANGUAGE' => 1)));
		$this->subject->parent_cObj->expects($this->once())->method('exec_getQuery')->will($this->returnValue(1));
		$GLOBALS['TYPO3_DB']->expects($this->exactly(2))->method('sql_fetch_assoc')->will($this->onConsecutiveCalls($this->returnValue(array('uid' => 0, 'header' => 'NOT_OVERLAID')), $this->returnValue(FALSE)));
		$this->subject->sys_page->expects($this->once())->method('getRecordOverlay')->will($this->returnValue(array('uid' => 0, 'header' => 'OVERLAID')));
		$result = $this->subject->_call('sectionIndex', 'field');
		$this->assertEquals($result[0]['title'], 'OVERLAID');
	}

	/**
	 * @return array
	 */
	public function sectionIndexFiltersDataProvider() {
		return array(
			'unfiltered fields' => array(
				1,
				array(
					'sectionIndex' => 1,
					'header' => 'foo',
					'header_layout' => 1
				)
			),
			'with unset section index' => array(
				0,
				array(
					'sectionIndex' => 0,
					'header' => 'foo',
					'header_layout' => 1
				)
			),
			'with unset header' => array(
				0,
				array(
					'sectionIndex' => 1,
					'header' => '',
					'header_layout' => 1
				)
			),
			'with header layout 100' => array(
				0,
				array(
					'sectionIndex' => 1,
					'header' => 'foo',
					'header_layout' => 100
				)
			)
		);
	}

	/**
	 * @test
	 * @dataProvider sectionIndexFiltersDataProvider
	 * @param int $expectedAmount
	 * @param array $dataRow
	 */
	public function sectionIndexFilters($expectedAmount, array $dataRow) {
		$this->prepareSectionIndexTest();
		$this->subject->mconf['sectionIndex.']['type'] = 'header';
		$this->subject->sys_page->expects($this->once())->method('getPage')->will($this->returnValue(array()));
		$this->subject->parent_cObj->expects($this->once())->method('exec_getQuery')->will($this->returnValue(1));
		$GLOBALS['TYPO3_DB']->expects($this->exactly(2))->method('sql_fetch_assoc')->will($this->onConsecutiveCalls($this->returnValue($dataRow), $this->returnValue(FALSE)));
		$result = $this->subject->_call('sectionIndex', 'field');
		$this->assertCount($expectedAmount, $result);
	}

	/**
	 * @return array
	 */
	public function sectionIndexQueriesWithDifferentColPosDataProvider() {
		return array(
			'no configuration' => array(
				array(),
				'colPos=0'
			),
			'with useColPos 2' => array(
				array('useColPos' => 2),
				'colPos=2'
			),
			'with useColPos -1' => array(
				array('useColPos' => -1),
				''
			),
			'with stdWrap useColPos' => array(
				array(
					'useColPos.' => array(
						'wrap' => '2|'
					)
				),
				'colPos=2'
			)
		);
	}

	/**
	 * @test
	 * @dataProvider sectionIndexQueriesWithDifferentColPosDataProvider
	 * @param array $configuration
	 * @param string $whereClausePrefix
	 */
	public function sectionIndexQueriesWithDifferentColPos($configuration, $whereClausePrefix) {
		$this->prepareSectionIndexTest();
		$this->subject->sys_page->expects($this->once())->method('getPage')->will($this->returnValue(array()));
		$this->subject->mconf['sectionIndex.'] = $configuration;
		$queryConfiguration = array(
			'pidInList' => 12,
			'orderBy' => 'field',
			'languageField' => 'sys_language_uid',
			'where' => $whereClausePrefix
		);
		$this->subject->parent_cObj->expects($this->once())->method('exec_getQuery')->with('tt_content', $queryConfiguration)->will($this->returnValue(1));
		$this->subject->_call('sectionIndex', 'field', 12);
	}

	////////////////////////////////////
	// Tests concerning menu item states
	////////////////////////////////////
	/**
	 * @return array
	 */
	public function ifsubHasToCheckExcludeUidListDataProvider() {
		return array(
			'none excluded' => array (
				array(12, 34, 56),
				'1, 23, 456',
				TRUE
			),
			'one excluded' => array (
				array(1, 234, 567),
				'1, 23, 456',
				TRUE
			),
			'three excluded' => array (
				array(1, 23, 456),
				'1, 23, 456',
				FALSE
			),
			'empty excludeList' => array (
				array(1, 123, 45),
				'',
				TRUE
			),
			'empty menu' => array (
				array(),
				'1, 23, 456',
				FALSE
			),
		);

	}

	/**
	 * @test
	 * @dataProvider ifsubHasToCheckExcludeUidListDataProvider
	 * @param array $menuItems
	 * @param string $excludeUidList
	 * @param bool $expectedResult
	 */
	public function ifsubHasToCheckExcludeUidList($menuItems, $excludeUidList, $expectedResult) {
		$menu = array();
		foreach ($menuItems as $page) {
			$menu[] = array('uid' => $page);
		}

		$this->prepareSectionIndexTest();
		$this->subject->parent_cObj = $this->getMock(\TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer::class, array());

		$this->subject->sys_page->expects($this->once())->method('getMenu')->will($this->returnValue($menu));
		$this->subject->menuArr = array(
			0 => array('uid' => 1)
		);
		$this->subject->conf['excludeUidList'] = $excludeUidList;

		$this->assertEquals($expectedResult, $this->subject->isItemState('IFSUB', 0));
	}

}
