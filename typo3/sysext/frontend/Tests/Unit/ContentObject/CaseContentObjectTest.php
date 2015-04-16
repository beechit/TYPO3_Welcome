<?php
namespace TYPO3\CMS\Frontend\Tests\Unit\ContentObject;

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
use TYPO3\CMS\Core\Tests\UnitTestCase;
use TYPO3\CMS\Frontend\ContentObject\CaseContentObject;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\TextContentObject;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Core\TypoScript\TemplateService;
use TYPO3\CMS\Frontend\Page\PageRepository;
use TYPO3\CMS\Core\Charset\CharsetConverter;

/**
 * Testcase for TYPO3\CMS\Frontend\ContentObject\CaseContentObject
 *
 * @author Philipp Gampe <philipp.gampe@typo3.org>
 */
class CaseContentObjectTest extends UnitTestCase {

	/**
	 * @var CaseContentObject|\PHPUnit_Framework_MockObject_MockObject
	 */
	protected $subject = NULL;

	/**
	 * Set up
	 */
	protected function setUp() {
		/** @var TypoScriptFrontendController $tsfe */
		$tsfe = $this->getMock(TypoScriptFrontendController::class, array('dummy'), array(), '', FALSE);
		$tsfe->tmpl = $this->getMock(TemplateService::class, array('dummy'));
		$tsfe->config = array();
		$tsfe->page = array();
		$tsfe->sys_page = $this->getMock(PageRepository::class, array('getRawRecord'));
		$tsfe->csConvObj = new CharsetConverter();
		$tsfe->renderCharset = 'utf-8';
		$GLOBALS['TSFE'] = $tsfe;

		$contentObjectRenderer = new ContentObjectRenderer();
		$contentObjectRenderer->setContentObjectClassMap(array(
			'CASE' => CaseContentObject::class,
			'TEXT' => TextContentObject::class,
		));
		$this->subject = new CaseContentObject($contentObjectRenderer);
	}

	/**
	 * @test
	 */
	public function renderReturnsEmptyStringIfNoKeyMatchesAndIfNoDefaultObjectIsSet() {
		$conf = array(
			'key' => 'not existing'
		);
		$this->assertSame('', $this->subject->render($conf));
	}

	/**
	 * @test
	 */
	public function renderReturnsContentFromDefaultObjectIfKeyDoesNotExist() {
		$conf = array(
			'key' => 'not existing',
			'default' => 'TEXT',
			'default.' => array(
				'value' => 'expected value'
			),
		);
		$this->assertSame('expected value', $this->subject->render($conf));
	}
}
