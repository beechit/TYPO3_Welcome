<?php
namespace TYPO3\CMS\Fluid\Tests\Unit\Core\Parser\SyntaxTree;

/*                                                                        *
 * This script is backported from the TYPO3 Flow package "TYPO3.Fluid".   *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License, either version 3   *
 *  of the License, or (at your option) any later version.                *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

/**
 * Test case
 */
class ViewHelperNodeTest extends \TYPO3\CMS\Core\Tests\UnitTestCase {

	/**
	 * Rendering Context
	 *
	 * @var \TYPO3\CMS\Fluid\Core\Rendering\RenderingContext
	 */
	protected $renderingContext;

	/**
	 * Object factory mock
	 *
	 * @var \TYPO3\CMS\Extbase\Object\ObjectManagerInterface
	 */
	protected $mockObjectManager;

	/**
	 * Template Variable Container
	 *
	 * @var \TYPO3\CMS\Fluid\Core\ViewHelper\TemplateVariableContainer
	 */
	protected $templateVariableContainer;

	/**
	 * @var \TYPO3\CMS\Extbase\Mvc\Controller\ControllerContext
	 */
	protected $controllerContext;

	/**
	 * @var \TYPO3\CMS\Fluid\Core\ViewHelper\ViewHelperVariableContainer
	 */
	protected $viewHelperVariableContainer;

	/**
	 * Setup fixture
	 */
	protected function setUp() {
		$this->renderingContext = $this->getAccessibleMock(\TYPO3\CMS\Fluid\Core\Rendering\RenderingContext::class);

		$this->mockObjectManager = $this->getMock(\TYPO3\CMS\Extbase\Object\ObjectManagerInterface::class);
		$this->renderingContext->_set('objectManager', $this->mockObjectManager);

		$this->templateVariableContainer = $this->getMock(\TYPO3\CMS\Fluid\Core\ViewHelper\TemplateVariableContainer::class);
		$this->renderingContext->injectTemplateVariableContainer($this->templateVariableContainer);

		$this->controllerContext = $this->getMock(\TYPO3\CMS\Extbase\Mvc\Controller\ControllerContext::class, array(), array(), '', FALSE);
		$this->renderingContext->setControllerContext($this->controllerContext);

		$this->viewHelperVariableContainer = $this->getMock(\TYPO3\CMS\Fluid\Core\ViewHelper\ViewHelperVariableContainer::class);
		$this->renderingContext->_set('viewHelperVariableContainer', $this->viewHelperVariableContainer);
	}

	/**
	 * @test
	 */
	public function constructorSetsViewHelperAndArguments() {
		$viewHelper = $this->getMock(\TYPO3\CMS\Fluid\Core\ViewHelper\AbstractViewHelper::class);
		$arguments = array('foo' => 'bar');
		$viewHelperNode = $this->getAccessibleMock(\TYPO3\CMS\Fluid\Core\Parser\SyntaxTree\ViewHelperNode::class, array('dummy'), array($viewHelper, $arguments));

		$this->assertEquals(get_class($viewHelper), $viewHelperNode->getViewHelperClassName());
		$this->assertEquals($arguments, $viewHelperNode->_get('arguments'));
	}

	/**
	 * @test
	 */
	public function childNodeAccessFacetWorksAsExpected() {
		$childNode = $this->getMock(\TYPO3\CMS\Fluid\Core\Parser\SyntaxTree\TextNode::class, array(), array('foo'));

		$mockViewHelper = $this->getMock(\TYPO3\CMS\Fluid\Tests\Unit\Core\Parser\Fixtures\ChildNodeAccessFacetViewHelper::class, array('setChildNodes', 'initializeArguments', 'render', 'prepareArguments'));

		$viewHelperNode = new \TYPO3\CMS\Fluid\Core\Parser\SyntaxTree\ViewHelperNode($mockViewHelper, array());
		$viewHelperNode->addChildNode($childNode);

		$mockViewHelper->expects($this->once())->method('setChildNodes')->with($this->equalTo(array($childNode)));

		$viewHelperNode->evaluate($this->renderingContext);
	}

	/**
	 * @test
	 */
	public function initializeArgumentsAndRenderIsCalledByViewHelperNode() {
		$mockViewHelper = $this->getMock(\TYPO3\CMS\Fluid\Core\ViewHelper\AbstractViewHelper::class, array('initializeArgumentsAndRender', 'prepareArguments'));
		$mockViewHelper->expects($this->once())->method('initializeArgumentsAndRender');

		$viewHelperNode = new \TYPO3\CMS\Fluid\Core\Parser\SyntaxTree\ViewHelperNode($mockViewHelper, array());

		$viewHelperNode->evaluate($this->renderingContext);
	}

	/**
	 * @test
	 */
	public function initializeArgumentsAndRenderIsCalledWithCorrectArguments() {
		$arguments = array(
			'param0' => new \TYPO3\CMS\Fluid\Core\ViewHelper\ArgumentDefinition('param1', 'string', 'Hallo', TRUE, NULL, FALSE),
			'param1' => new \TYPO3\CMS\Fluid\Core\ViewHelper\ArgumentDefinition('param1', 'string', 'Hallo', TRUE, NULL, TRUE),
			'param2' => new \TYPO3\CMS\Fluid\Core\ViewHelper\ArgumentDefinition('param2', 'string', 'Hallo', TRUE, NULL, TRUE)
		);

		$mockViewHelper = $this->getMock(\TYPO3\CMS\Fluid\Core\ViewHelper\AbstractViewHelper::class, array('initializeArgumentsAndRender', 'prepareArguments'));
		$mockViewHelper->expects($this->any())->method('prepareArguments')->will($this->returnValue($arguments));
		$mockViewHelper->expects($this->once())->method('initializeArgumentsAndRender');

		$viewHelperNode = new \TYPO3\CMS\Fluid\Core\Parser\SyntaxTree\ViewHelperNode($mockViewHelper, array(
			'param2' => new \TYPO3\CMS\Fluid\Core\Parser\SyntaxTree\TextNode('b'),
			'param1' => new \TYPO3\CMS\Fluid\Core\Parser\SyntaxTree\TextNode('a')
		));

		$viewHelperNode->evaluate($this->renderingContext);
	}

	/**
	 * @test
	 */
	public function evaluateMethodPassesRenderingContextToViewHelper() {
		$mockViewHelper = $this->getMock(\TYPO3\CMS\Fluid\Core\ViewHelper\AbstractViewHelper::class, array('render', 'validateArguments', 'prepareArguments', 'setRenderingContext'));
		$mockViewHelper->expects($this->once())->method('setRenderingContext')->with($this->renderingContext);

		$viewHelperNode = new \TYPO3\CMS\Fluid\Core\Parser\SyntaxTree\ViewHelperNode($mockViewHelper, array());

		$viewHelperNode->evaluate($this->renderingContext);
	}

	/**
	 * @test
	 */
	public function multipleEvaluateCallsShareTheSameViewHelperInstance() {
		$mockViewHelper = $this->getMock(\TYPO3\CMS\Fluid\Core\ViewHelper\AbstractViewHelper::class, array('render', 'validateArguments', 'prepareArguments', 'setViewHelperVariableContainer'));
		$mockViewHelper->expects($this->any())->method('render')->will($this->returnValue('String'));

		$viewHelperNode = new \TYPO3\CMS\Fluid\Core\Parser\SyntaxTree\ViewHelperNode($mockViewHelper, array());

		$viewHelperNode->evaluate($this->renderingContext);
		$viewHelperNode->evaluate($this->renderingContext);
	}

}
