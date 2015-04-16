<?php
namespace TYPO3\CMS\Frontend\Tests\Functional\Page;

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

use TYPO3\CMS\Frontend\Page\PageRepository;

/**
 * Test case
 */
class PageRepositoryTest extends \TYPO3\CMS\Core\Tests\FunctionalTestCase {

	protected $coreExtensionsToLoad = array('frontend');

	protected function setUp() {
		parent::setUp();
		$this->importDataSet(__DIR__ . '/../Fixtures/pages.xml');
		$this->pagerepo = new PageRepository();
		$this->pagerepo->init(false);
	}

	/**
	 * @test
	 */
	public function getMenuSingleUidRoot() {
		$rows = $this->pagerepo->getMenu(1, 'uid, title');
		$this->assertArrayHasKey(2, $rows);
		$this->assertArrayHasKey(3, $rows);
		$this->assertArrayHasKey(4, $rows);
		$this->assertCount(3, $rows);
	}

	/**
	 * @test
	 */
	public function getMenuSingleUidSubpage() {
		$rows = $this->pagerepo->getMenu(2, 'uid, title');
		$this->assertArrayHasKey(5, $rows);
		$this->assertArrayHasKey(6, $rows);
		$this->assertArrayHasKey(7, $rows);
		$this->assertCount(3, $rows);
	}

	/**
	 * @test
	 */
	public function getMenuMulipleUid() {
		$rows = $this->pagerepo->getMenu(array(2, 3), 'uid, title');
		$this->assertArrayHasKey(5, $rows);
		$this->assertArrayHasKey(6, $rows);
		$this->assertArrayHasKey(7, $rows);
		$this->assertArrayHasKey(8, $rows);
		$this->assertArrayHasKey(9, $rows);
		$this->assertCount(5, $rows);
	}

	/**
	 * @test
	 */
	public function getMenuPageOverlay() {
		$this->pagerepo->sys_language_uid = 1;

		$rows = $this->pagerepo->getMenu(array(2, 3), 'uid, title');
		$this->assertEquals('Attrappe 1-2-5', $rows[5]['title']);
		$this->assertEquals('Attrappe 1-2-6', $rows[6]['title']);
		$this->assertEquals('Dummy 1-2-7', $rows[7]['title']);
		$this->assertEquals('Dummy 1-3-8', $rows[8]['title']);
		$this->assertEquals('Attrappe 1-3-9', $rows[9]['title']);
		$this->assertCount(5, $rows);
	}

	/**
	 * @test
	 */
	public function getPageOverlayById() {
		$row = $this->pagerepo->getPageOverlay(1, 1);
		$this->assertOverlayRow($row);
		$this->assertEquals('Wurzel 1', $row['title']);
		$this->assertEquals('901', $row['_PAGES_OVERLAY_UID']);
		$this->assertEquals(1, $row['_PAGES_OVERLAY_LANGUAGE']);
	}

	/**
	 * @test
	 */
	public function getPageOverlayByIdWithoutTranslation() {
		$row = $this->pagerepo->getPageOverlay(4, 1);
		$this->assertInternalType('array', $row);
		$this->assertCount(0, $row);
	}

	/**
	 * @test
	 */
	public function getPageOverlayByRow() {
		$orig = $this->pagerepo->getPage(1);
		$row = $this->pagerepo->getPageOverlay($orig, 1);
		$this->assertOverlayRow($row);
		$this->assertEquals(1, $row['uid']);
		$this->assertEquals('Wurzel 1', $row['title']);
		$this->assertEquals('901', $row['_PAGES_OVERLAY_UID']);
		$this->assertEquals(1, $row['_PAGES_OVERLAY_LANGUAGE']);
	}

	/**
	 * @test
	 */
	public function getPageOverlayByRowWithoutTranslation() {
		$orig = $this->pagerepo->getPage(4);
		$row = $this->pagerepo->getPageOverlay($orig, 1);
		$this->assertInternalType('array', $row);
		$this->assertEquals(4, $row['uid']);
		$this->assertEquals('Dummy 1-4', $row['title']);//original title
	}

	/**
	 * @test
	 */
	public function getPagesOverlayByIdSingle() {
		$this->pagerepo->sys_language_uid = 1;
		$rows = $this->pagerepo->getPagesOverlay(array(1));
		$this->assertInternalType('array', $rows);
		$this->assertCount(1, $rows);
		$this->assertArrayHasKey(0, $rows);

		$row = $rows[0];
		$this->assertOverlayRow($row);
		$this->assertEquals('Wurzel 1', $row['title']);
		$this->assertEquals('901', $row['_PAGES_OVERLAY_UID']);
		$this->assertEquals(1, $row['_PAGES_OVERLAY_LANGUAGE']);
	}

	/**
	 * @test
	 */
	public function getPagesOverlayByIdMultiple() {
		$this->pagerepo->sys_language_uid = 1;
		$rows = $this->pagerepo->getPagesOverlay(array(1, 5));
		$this->assertInternalType('array', $rows);
		$this->assertCount(2, $rows);
		$this->assertArrayHasKey(0, $rows);
		$this->assertArrayHasKey(1, $rows);

		$row = $rows[0];
		$this->assertOverlayRow($row);
		$this->assertEquals('Wurzel 1', $row['title']);
		$this->assertEquals('901', $row['_PAGES_OVERLAY_UID']);
		$this->assertEquals(1, $row['_PAGES_OVERLAY_LANGUAGE']);

		$row = $rows[1];
		$this->assertOverlayRow($row);
		$this->assertEquals('Attrappe 1-2-5', $row['title']);
		$this->assertEquals('904', $row['_PAGES_OVERLAY_UID']);
		$this->assertEquals(1, $row['_PAGES_OVERLAY_LANGUAGE']);
	}

	/**
	 * @test
	 */
	public function getPagesOverlayByIdMultipleSomeNotOverlaid() {
		$this->pagerepo->sys_language_uid = 1;
		$rows = $this->pagerepo->getPagesOverlay(array(1, 4, 5, 8));
		$this->assertInternalType('array', $rows);
		$this->assertCount(2, $rows);
		$this->assertArrayHasKey(0, $rows);
		$this->assertArrayHasKey(2, $rows);

		$row = $rows[0];
		$this->assertOverlayRow($row);
		$this->assertEquals('Wurzel 1', $row['title']);

		$row = $rows[2];
		$this->assertOverlayRow($row);
		$this->assertEquals('Attrappe 1-2-5', $row['title']);
	}

	/**
	 * @test
	 */
	public function getPagesOverlayByRowSingle() {
		$origRow = $this->pagerepo->getPage(1);

		$this->pagerepo->sys_language_uid = 1;
		$rows = $this->pagerepo->getPagesOverlay(array($origRow));
		$this->assertInternalType('array', $rows);
		$this->assertCount(1, $rows);
		$this->assertArrayHasKey(0, $rows);

		$row = $rows[0];
		$this->assertOverlayRow($row);
		$this->assertEquals('Wurzel 1', $row['title']);
		$this->assertEquals('901', $row['_PAGES_OVERLAY_UID']);
		$this->assertEquals(1, $row['_PAGES_OVERLAY_LANGUAGE']);
	}

	/**
	 * @test
	 */
	public function getPagesOverlayByRowMultiple() {
		$orig1 = $this->pagerepo->getPage(1);
		$orig2 = $this->pagerepo->getPage(5);

		$this->pagerepo->sys_language_uid = 1;
		$rows = $this->pagerepo->getPagesOverlay(array(1 => $orig1, 5 => $orig2));
		$this->assertInternalType('array', $rows);
		$this->assertCount(2, $rows);
		$this->assertArrayHasKey(1, $rows);
		$this->assertArrayHasKey(5, $rows);

		$row = $rows[1];
		$this->assertOverlayRow($row);
		$this->assertEquals('Wurzel 1', $row['title']);
		$this->assertEquals('901', $row['_PAGES_OVERLAY_UID']);
		$this->assertEquals(1, $row['_PAGES_OVERLAY_LANGUAGE']);

		$row = $rows[5];
		$this->assertOverlayRow($row);
		$this->assertEquals('Attrappe 1-2-5', $row['title']);
		$this->assertEquals('904', $row['_PAGES_OVERLAY_UID']);
		$this->assertEquals(1, $row['_PAGES_OVERLAY_LANGUAGE']);
	}

	/**
	 * @test
	 */
	public function getPagesOverlayByRowMultipleSomeNotOverlaid() {
		$orig1 = $this->pagerepo->getPage(1);
		$orig2 = $this->pagerepo->getPage(7);
		$orig3 = $this->pagerepo->getPage(9);

		$this->pagerepo->sys_language_uid = 1;
		$rows = $this->pagerepo->getPagesOverlay(array($orig1, $orig2, $orig3));
		$this->assertInternalType('array', $rows);
		$this->assertCount(3, $rows);
		$this->assertArrayHasKey(0, $rows);
		$this->assertArrayHasKey(1, $rows);
		$this->assertArrayHasKey(2, $rows);

		$row = $rows[0];
		$this->assertOverlayRow($row);
		$this->assertEquals('Wurzel 1', $row['title']);

		$row = $rows[1];
		$this->assertNotOverlayRow($row);
		$this->assertEquals('Dummy 1-2-7', $row['title']);

		$row = $rows[2];
		$this->assertOverlayRow($row);
		$this->assertEquals('Attrappe 1-3-9', $row['title']);
	}

	protected function assertOverlayRow($row) {
		$this->assertInternalType('array', $row);

		$this->assertArrayHasKey('_PAGES_OVERLAY', $row);
		$this->assertArrayHasKey('_PAGES_OVERLAY_UID', $row);
		$this->assertArrayHasKey('_PAGES_OVERLAY_LANGUAGE', $row);

		$this->assertTrue($row['_PAGES_OVERLAY']);
	}

	protected function assertNotOverlayRow($row) {
		$this->assertInternalType('array', $row);

		$this->assertFalse(isset($row['_PAGES_OVERLAY']));
		$this->assertFalse(isset($row['_PAGES_OVERLAY_UID']));
		$this->assertFalse(isset($row['_PAGES_OVERLAY_LANGUAGE']));
	}
}
