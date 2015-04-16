<?php
namespace TYPO3\CMS\Fluid\Tests\Unit\View;

/*                                                                        *
 * This script is backported from the TYPO3 Flow package "TYPO3.Fluid".   *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License, either version 3   *
 *  of the License, or (at your option) any later version.                *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use TYPO3\CMS\Core\Tests\AccessibleObjectInterface;
use TYPO3\CMS\Core\Tests\UnitTestCase;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContext;
use TYPO3\CMS\Fluid\Core\ViewHelper\TemplateVariableContainer;
use TYPO3\CMS\Fluid\Core\ViewHelper\ViewHelperVariableContainer;
use TYPO3\CMS\Fluid\View\AbstractTemplateView;

/**
 * Test case
 */
class AbstractTemplateViewTest extends UnitTestCase {

	/**
	 * @var AbstractTemplateView|AccessibleObjectInterface
	 */
	protected $view;

	/**
	 * @var RenderingContext|\PHPUnit_Framework_MockObject_MockObject
	 */
	protected $renderingContext;

	/**
	 * @var ViewHelperVariableContainer|\PHPUnit_Framework_MockObject_MockObject
	 */
	protected $viewHelperVariableContainer;

	/**
	 * @var TemplateVariableContainer|\PHPUnit_Framework_MockObject_MockObject
	 */
	protected $templateVariableContainer;

	/**
	 * Sets up this test case
	 *
	 * @return void
	 */
	protected function setUp() {
		$this->templateVariableContainer = $this->getMock(TemplateVariableContainer::class, array('exists', 'remove', 'add'));
		$this->viewHelperVariableContainer = $this->getMock(ViewHelperVariableContainer::class, array('setView'));
		$this->renderingContext = $this->getMock(RenderingContext::class, array('getViewHelperVariableContainer', 'getTemplateVariableContainer'));
		$this->renderingContext->expects($this->any())->method('getViewHelperVariableContainer')->will($this->returnValue($this->viewHelperVariableContainer));
		$this->renderingContext->expects($this->any())->method('getTemplateVariableContainer')->will($this->returnValue($this->templateVariableContainer));
		$this->view = $this->getAccessibleMock(AbstractTemplateView::class, array('getTemplateSource', 'getLayoutSource', 'getPartialSource', 'canRender', 'getTemplateIdentifier', 'getLayoutIdentifier', 'getPartialIdentifier'));
		$this->view->setRenderingContext($this->renderingContext);
	}

	/**
	 * @test
	 */
	public function viewIsPlacedInViewHelperVariableContainer() {
		$this->viewHelperVariableContainer->expects($this->once())->method('setView')->with($this->view);
		$this->view->setRenderingContext($this->renderingContext);
	}

	/**
	 * @test
	 */
	public function assignAddsValueToTemplateVariableContainer() {
		$this->templateVariableContainer->expects($this->at(0))->method('exists')->with('foo')->will($this->returnValue(FALSE));
		$this->templateVariableContainer->expects($this->at(1))->method('add')->with('foo', 'FooValue');
		$this->templateVariableContainer->expects($this->at(2))->method('exists')->with('bar')->will($this->returnValue(FALSE));
		$this->templateVariableContainer->expects($this->at(3))->method('add')->with('bar', 'BarValue');

		$this->view
			->assign('foo', 'FooValue')
			->assign('bar', 'BarValue');
	}

	/**
	 * @test
	 */
	public function assignCanOverridePreviouslyAssignedValues() {
		$this->templateVariableContainer->expects($this->at(0))->method('exists')->with('foo')->will($this->returnValue(FALSE));
		$this->templateVariableContainer->expects($this->at(1))->method('add')->with('foo', 'FooValue');
		$this->templateVariableContainer->expects($this->at(2))->method('exists')->with('foo')->will($this->returnValue(TRUE));
		$this->templateVariableContainer->expects($this->at(3))->method('remove')->with('foo');
		$this->templateVariableContainer->expects($this->at(4))->method('add')->with('foo', 'FooValueOverridden');

		$this->view->assign('foo', 'FooValue');
		$this->view->assign('foo', 'FooValueOverridden');
	}

	/**
	 * @test
	 */
	public function assignMultipleAddsValuesToTemplateVariableContainer() {
		$this->templateVariableContainer->expects($this->at(0))->method('exists')->with('foo')->will($this->returnValue(FALSE));
		$this->templateVariableContainer->expects($this->at(1))->method('add')->with('foo', 'FooValue');
		$this->templateVariableContainer->expects($this->at(2))->method('exists')->with('bar')->will($this->returnValue(FALSE));
		$this->templateVariableContainer->expects($this->at(3))->method('add')->with('bar', 'BarValue');
		$this->templateVariableContainer->expects($this->at(4))->method('exists')->with('baz')->will($this->returnValue(FALSE));
		$this->templateVariableContainer->expects($this->at(5))->method('add')->with('baz', 'BazValue');

		$this->view
			->assignMultiple(array('foo' => 'FooValue', 'bar' => 'BarValue'))
			->assignMultiple(array('baz' => 'BazValue'));
	}

	/**
	 * @test
	 */
	public function assignMultipleCanOverridePreviouslyAssignedValues() {
		$this->templateVariableContainer->expects($this->at(0))->method('exists')->with('foo')->will($this->returnValue(FALSE));
		$this->templateVariableContainer->expects($this->at(1))->method('add')->with('foo', 'FooValue');
		$this->templateVariableContainer->expects($this->at(2))->method('exists')->with('foo')->will($this->returnValue(TRUE));
		$this->templateVariableContainer->expects($this->at(3))->method('remove')->with('foo');
		$this->templateVariableContainer->expects($this->at(4))->method('add')->with('foo', 'FooValueOverridden');
		$this->templateVariableContainer->expects($this->at(5))->method('exists')->with('bar')->will($this->returnValue(FALSE));
		$this->templateVariableContainer->expects($this->at(6))->method('add')->with('bar', 'BarValue');

		$this->view->assign('foo', 'FooValue');
		$this->view->assignMultiple(array('foo' => 'FooValueOverridden', 'bar' => 'BarValue'));
	}

	/**
	 * @return array
	 */
	public function ucFileNameInPathProperlyUpperCasesFileNamesDataProvider() {
		return [
			'keeps ucfirst' => ['LayoutPath', 'LayoutPath'],
			'creates ucfirst' => ['layoutPath', 'LayoutPath'],
			'ucfirst on file name only' => ['some/path/layout', 'some/path/Layout'],
			'keeps ucfirst on file name' => ['some/Path/Layout', 'some/Path/Layout'],
		];
	}

	/**
	 * @param string $path
	 * @param string $expected
	 * @dataProvider ucFileNameInPathProperlyUpperCasesFileNamesDataProvider
	 * @test
	 */
	public function ucFileNameInPathProperlyUpperCasesFileNames($path, $expected) {
		$this->assertSame($expected, $this->view->_call('ucFileNameInPath', $path));
	}

}
