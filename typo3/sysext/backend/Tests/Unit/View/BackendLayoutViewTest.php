<?php
namespace TYPO3\CMS\Backend\Tests\Unit\View;

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
 * Testing behaviour of \TYPO3\CMS\Backend\View\BackendLayoutView
 *
 * @author Oliver Hader <oliver.hader@typo3.org>
 */
class BackendLayoutViewTest extends \TYPO3\CMS\Core\Tests\UnitTestCase {

	/**
	 * @var \TYPO3\CMS\Backend\View\BackendLayoutView|\PHPUnit_Framework_MockObject_MockObject
	 */
	protected $backendLayoutView;

	/**
	 * Sets up this test case.
	 */
	protected function setUp() {
		$this->backendLayoutView = $this->getAccessibleMock(
			\TYPO3\CMS\Backend\View\BackendLayoutView::class,
			array('getPage', 'getRootLine'),
			array(), '', FALSE
		);
	}

	/**
	 * @param bool|string $expected
	 * @param array $page
	 * @param array $rootLine
	 * @test
	 * @dataProvider selectedCombinedIdentifierIsDeterminedDataProvider
	 */
	public function selectedCombinedIdentifierIsDetermined($expected, array $page, array $rootLine) {
		$pageId = $page['uid'];

		$this->backendLayoutView->expects($this->once())
			->method('getPage')->with($this->equalTo($pageId))
			->will($this->returnValue($page));
		$this->backendLayoutView->expects($this->any())
			->method('getRootLine')->with($this->equalTo($pageId))
			->will($this->returnValue($rootLine));

		$selectedCombinedIdentifier = $this->backendLayoutView->_call('getSelectedCombinedIdentifier', $pageId);
		$this->assertEquals($expected, $selectedCombinedIdentifier);
	}

	/**
	 * @return array
	 */
	public function selectedCombinedIdentifierIsDeterminedDataProvider() {
		return array(
			'first level w/o layout' => array(
				'0',
				array('uid' => 1, 'pid' => 0, 'backend_layout' => '0', 'backend_layout_next_level' => '0'),
				array(
					array('uid' => 1, 'pid' => 0, 'backend_layout' => '0', 'backend_layout_next_level' => '0'),
					array('uid' => 0, 'pid' => NULL,),
				)
			),
			'first level with layout' => array(
				'1',
				array('uid' => 1, 'pid' => 0, 'backend_layout' => '1', 'backend_layout_next_level' => '0'),
				array(
					array('uid' => 1, 'pid' => 0, 'backend_layout' => '1', 'backend_layout_next_level' => '0'),
					array('uid' => 0, 'pid' => NULL,),
				)
			),
			'first level with provided layout' => array(
				'mine_current',
				array('uid' => 1, 'pid' => 0, 'backend_layout' => 'mine_current', 'backend_layout_next_level' => '0'),
				array(
					array('uid' => 1, 'pid' => 0, 'backend_layout' => 'mine_current', 'backend_layout_next_level' => '0'),
					array('uid' => 0, 'pid' => NULL,),
				)
			),
			'first level with next layout' => array(
				'0',
				array('uid' => 1, 'pid' => 0, 'backend_layout' => '0', 'backend_layout_next_level' => '1'),
				array(
					array('uid' => 1, 'pid' => 0, 'backend_layout' => '0', 'backend_layout_next_level' => '1'),
					array('uid' => 0, 'pid' => NULL,),
				)
			),
			'first level with provided next layout' => array(
				'0',
				array('uid' => 1, 'pid' => 0, 'backend_layout' => '0', 'backend_layout_next_level' => 'mine_next'),
				array(
					array('uid' => 1, 'pid' => 0, 'backend_layout' => '0', 'backend_layout_next_level' => 'mine_next'),
					array('uid' => 0, 'pid' => NULL,),
				)
			),
			'second level w/o layout, first level with layout' => array(
				'0',
				array('uid' => 2, 'pid' => 1, 'backend_layout' => '0', 'backend_layout_next_level' => '0'),
				array(
					array('uid' => 2, 'pid' => 1, 'backend_layout' => '0', 'backend_layout_next_level' => '0'),
					array('uid' => 1, 'pid' => 0, 'backend_layout' => '1', 'backend_layout_next_level' => '0'),
					array('uid' => 0, 'pid' => NULL,),
				)
			),
			'second level w/o layout, first level with next layout' => array(
				'1',
				array('uid' => 2, 'pid' => 1, 'backend_layout' => '0', 'backend_layout_next_level' => '0'),
				array(
					array('uid' => 2, 'pid' => 1, 'backend_layout' => '0', 'backend_layout_next_level' => '0'),
					array('uid' => 1, 'pid' => 0, 'backend_layout' => '0', 'backend_layout_next_level' => '1'),
					array('uid' => 0, 'pid' => NULL,),
				)
			),
			'second level with layout, first level with next layout' => array(
				'2',
				array('uid' => 2, 'pid' => 1, 'backend_layout' => '2', 'backend_layout_next_level' => '0'),
				array(
					array('uid' => 2, 'pid' => 1, 'backend_layout' => '2', 'backend_layout_next_level' => '0'),
					array('uid' => 1, 'pid' => 0, 'backend_layout' => '0', 'backend_layout_next_level' => '1'),
					array('uid' => 0, 'pid' => NULL,),
				)
			),
			'second level with layouts, first level resetting all layouts' => array(
				'1',
				array('uid' => 2, 'pid' => 1, 'backend_layout' => '1', 'backend_layout_next_level' => '1'),
				array(
					array('uid' => 2, 'pid' => 1, 'backend_layout' => '1', 'backend_layout_next_level' => '1'),
					array('uid' => 1, 'pid' => 0, 'backend_layout' => '-1', 'backend_layout_next_level' => '-1'),
					array('uid' => 0, 'pid' => NULL,),
				)
			),
			'second level with provided layouts, first level resetting all layouts' => array(
				'mine_current',
				array('uid' => 2, 'pid' => 1, 'backend_layout' => 'mine_current', 'backend_layout_next_level' => 'mine_next'),
				array(
					array('uid' => 2, 'pid' => 1, 'backend_layout' => 'mine_current', 'backend_layout_next_level' => 'mine_next'),
					array('uid' => 1, 'pid' => 0, 'backend_layout' => '-1', 'backend_layout_next_level' => '-1'),
					array('uid' => 0, 'pid' => NULL,),
				)
			),
			'second level resetting layout, first level with next layout' => array(
				FALSE,
				array('uid' => 2, 'pid' => 1, 'backend_layout' => '-1', 'backend_layout_next_level' => '0'),
				array(
					array('uid' => 2, 'pid' => 1, 'backend_layout' => '-1', 'backend_layout_next_level' => '0'),
					array('uid' => 1, 'pid' => 0, 'backend_layout' => '0', 'backend_layout_next_level' => '1'),
					array('uid' => 0, 'pid' => NULL,),
				)
			),
			'second level resetting next layout, first level with next layout' => array(
				'1',
				array('uid' => 2, 'pid' => 1, 'backend_layout' => '0', 'backend_layout_next_level' => '-1'),
				array(
					array('uid' => 2, 'pid' => 1, 'backend_layout' => '0', 'backend_layout_next_level' => '-1'),
					array('uid' => 1, 'pid' => 0, 'backend_layout' => '0', 'backend_layout_next_level' => '1'),
					array('uid' => 0, 'pid' => NULL,),
				)
			),
			'third level w/o layout, second level resetting layout, first level with next layout' => array(
				'1',
				array('uid' => 3, 'pid' => 2, 'backend_layout' => '0', 'backend_layout_next_level' => '0'),
				array(
					array('uid' => 3, 'pid' => 2, 'backend_layout' => '0', 'backend_layout_next_level' => '0'),
					array('uid' => 2, 'pid' => 1, 'backend_layout' => '-1', 'backend_layout_next_level' => '0'),
					array('uid' => 1, 'pid' => 0, 'backend_layout' => '0', 'backend_layout_next_level' => '1'),
					array('uid' => 0, 'pid' => NULL,),
				)
			),
			'third level w/o layout, second level resetting next layout, first level with next layout' => array(
				FALSE,
				array('uid' => 3, 'pid' => 2, 'backend_layout' => '0', 'backend_layout_next_level' => '0'),
				array(
					array('uid' => 3, 'pid' => 2, 'backend_layout' => '0', 'backend_layout_next_level' => '0'),
					array('uid' => 2, 'pid' => 1, 'backend_layout' => '0', 'backend_layout_next_level' => '-1'),
					array('uid' => 1, 'pid' => 0, 'backend_layout' => '0', 'backend_layout_next_level' => '1'),
					array('uid' => 0, 'pid' => NULL,),
				)
			),
			'third level with provided layouts, second level w/o layout, first level resetting layouts' => array(
				'mine_current',
				array('uid' => 3, 'pid' => 2, 'backend_layout' => 'mine_current', 'backend_layout_next_level' => 'mine_next'),
				array(
					array('uid' => 3, 'pid' => 2, 'backend_layout' => 'mine_current', 'backend_layout_next_level' => 'mine_next'),
					array('uid' => 2, 'pid' => 1, 'backend_layout' => '0', 'backend_layout_next_level' => '0'),
					array('uid' => 1, 'pid' => 0, 'backend_layout' => '-1', 'backend_layout_next_level' => '-1'),
					array('uid' => 0, 'pid' => NULL,),
				)
			),
		);
	}

}