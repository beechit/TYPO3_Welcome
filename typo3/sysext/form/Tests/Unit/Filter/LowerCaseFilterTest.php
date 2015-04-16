<?php
namespace TYPO3\CMS\Form\Tests\Unit\Filter;

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
 * @author Andreas Lappe <nd@kaeufli.ch>
 */
class LowerCaseFilterTest extends \TYPO3\CMS\Core\Tests\UnitTestCase {

	/**
	 * @var \TYPO3\CMS\Form\Filter\LowerCaseFilter
	 */
	protected $subject;

	protected function setUp() {
		$this->subject = new \TYPO3\CMS\Form\Filter\LowerCaseFilter();
		$GLOBALS['TSFE'] = new \stdClass();
		$GLOBALS['TSFE']->csConvObj = new \TYPO3\CMS\Core\Charset\CharsetConverter();
		$GLOBALS['TSFE']->renderCharset = 'utf-8';
	}

	public function dataProvider() {
		return array(
			'a -> a' => array('a', 'a'),
			'A -> a' => array('A', 'a'),
			'AaA -> aaa' => array('AaA', 'aaa'),
			'ÜßbÉØ -> üßbéø' => array('ÜßbÉØ', 'üßbéø'),
			'01A23b -> 01a23b' => array('01A23b', '01a23b'),
		);
	}

	/**
	 * @test
	 * @dataProvider dataProvider
	 */
	public function filterForVariousInputReturnsLowercasedInput($input, $expected) {
		$this->assertSame(
			$expected,
			$this->subject->filter($input)
		);
	}

}
