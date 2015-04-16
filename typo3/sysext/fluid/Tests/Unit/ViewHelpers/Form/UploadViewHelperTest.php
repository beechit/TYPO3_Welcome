<?php
namespace TYPO3\CMS\Fluid\Tests\Unit\ViewHelpers\Form;

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
 * Test for the "Upload" Form view helper
 */
class UploadViewHelperTest extends \TYPO3\CMS\Fluid\Tests\Unit\ViewHelpers\Form\FormFieldViewHelperBaseTestcase {

	/**
	 * @var \TYPO3\CMS\Fluid\ViewHelpers\Form\UploadViewHelper
	 */
	protected $viewHelper;

	protected function setUp() {
		parent::setUp();
		$this->viewHelper = $this->getAccessibleMock(\TYPO3\CMS\Fluid\ViewHelpers\Form\UploadViewHelper::class, array('setErrorClassAttribute', 'registerFieldNameForFormTokenGeneration'));
		$this->arguments['name'] = '';
		$this->injectDependenciesIntoViewHelper($this->viewHelper);
		$this->viewHelper->initializeArguments();
	}

	/**
	 * @test
	 */
	public function renderCorrectlySetsTagName() {
		$this->tagBuilder->expects($this->once())->method('setTagName')->with('input');

		$this->viewHelper->initialize();
		$this->viewHelper->render();
	}

	/**
	 * @test
	 */
	public function renderCorrectlySetsTypeNameAndValueAttributes() {
		$mockTagBuilder = $this->getMock(\TYPO3\CMS\Fluid\Core\ViewHelper\TagBuilder::class, array('addAttribute', 'setContent', 'render'), array(), '', FALSE);
		$mockTagBuilder->expects($this->at(0))->method('addAttribute')->with('type', 'file');
		$mockTagBuilder->expects($this->at(1))->method('addAttribute')->with('name', 'someName');
		$this->viewHelper->expects($this->at(0))->method('registerFieldNameForFormTokenGeneration')->with('someName[name]');
		$this->viewHelper->expects($this->at(1))->method('registerFieldNameForFormTokenGeneration')->with('someName[type]');
		$this->viewHelper->expects($this->at(2))->method('registerFieldNameForFormTokenGeneration')->with('someName[tmp_name]');
		$this->viewHelper->expects($this->at(3))->method('registerFieldNameForFormTokenGeneration')->with('someName[error]');
		$this->viewHelper->expects($this->at(4))->method('registerFieldNameForFormTokenGeneration')->with('someName[size]');
		$mockTagBuilder->expects($this->once())->method('render');
		$this->viewHelper->_set('tag', $mockTagBuilder);
		$arguments = array(
			'name' => 'someName'
		);
		$this->viewHelper->setArguments($arguments);
		$this->viewHelper->setViewHelperNode(new \TYPO3\CMS\Fluid\Tests\Unit\ViewHelpers\Form\Fixtures\EmptySyntaxTreeNode());
		$this->viewHelper->initialize();
		$this->viewHelper->render();
	}

	/**
	 * @test
	 */
	public function renderCallsSetErrorClassAttribute() {
		$this->viewHelper->expects($this->once())->method('setErrorClassAttribute');
		$this->viewHelper->render();
	}

	/**
	 * @test
	 */
	public function renderSetsAttributeNameAsArrayIfMultipleIsGiven() {
		/** @var \TYPO3\CMS\Fluid\Core\ViewHelper\TagBuilder $tagBuilder */
		$tagBuilder = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Fluid\Core\ViewHelper\TagBuilder::class);
		$tagBuilder->addAttribute('multiple', 'multiple');
		$this->viewHelper->_set('tag', $tagBuilder);
		$arguments = array(
			'name' => 'someName',
			'multiple' => 'multiple'
		);
		$this->viewHelper->setArguments($arguments);
		$this->viewHelper->initialize();
		$result = $this->viewHelper->render();
		$this->assertEquals('<input multiple="multiple" type="file" name="someName[]" />', $result);
	}

}