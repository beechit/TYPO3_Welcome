<?php
namespace TYPO3\CMS\Fluid\Tests\Unit\ViewHelpers\Format;

/*                                                                        *
 * This script is backported from the TYPO3 Flow package "TYPO3.Fluid".   *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License, either version 3 of the   *
 * License, or (at your option) any later version.                        *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use TYPO3\CMS\Fluid\Tests\Unit\ViewHelpers\ViewHelperBaseTestcase;

/**
 * Test case
 */
class PrintfViewHelperTest extends ViewHelperBaseTestcase {

	/**
	 * @var \TYPO3\Fluid\ViewHelpers\Format\PrintfViewHelper
	 */
	protected $viewHelper;

	protected function setUp() {
		parent::setUp();
		$this->viewHelper = $this->getMock('TYPO3\CMS\Fluid\ViewHelpers\Format\PrintfViewHelper', array('renderChildren'));
		$this->injectDependenciesIntoViewHelper($this->viewHelper);
		$this->viewHelper->initializeArguments();
	}

	/**
	 * @test
	 */
	public function viewHelperCanUseArrayAsArgument() {
		$this->viewHelper->expects($this->once())->method('renderChildren')->will($this->returnValue('%04d-%02d-%02d'));
		$actualResult = $this->viewHelper->render(array('year' => 2009, 'month' => 4, 'day' => 5));
		$this->assertEquals('2009-04-05', $actualResult);
	}

	/**
	 * @test
	 */
	public function viewHelperCanSwapMultipleArguments() {
		$this->viewHelper->expects($this->once())->method('renderChildren')->will($this->returnValue('%2$s %1$d %3$s %2$s'));
		$actualResult = $this->viewHelper->render(array(123, 'foo', 'bar'));
		$this->assertEquals('foo 123 bar foo', $actualResult);
	}

}
