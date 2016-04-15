<?php
namespace BeechIt\SimpleBlog\Tests\Unit\Controller;
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2016 Ruud Silvrants <t3ext@beech.it>, BeechIt
 *  			
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Test case for class BeechIt\SimpleBlog\Controller\BlogController.
 *
 * @author Ruud Silvrants <t3ext@beech.it>
 */
class BlogControllerTest extends \TYPO3\CMS\Core\Tests\UnitTestCase
{

	/**
	 * @var \BeechIt\SimpleBlog\Controller\BlogController
	 */
	protected $subject = NULL;

	public function setUp()
	{
		$this->subject = $this->getMock('BeechIt\\SimpleBlog\\Controller\\BlogController', array('redirect', 'forward', 'addFlashMessage'), array(), '', FALSE);
	}

	public function tearDown()
	{
		unset($this->subject);
	}

	/**
	 * @test
	 */
	public function listActionFetchesAllBlogsFromRepositoryAndAssignsThemToView()
	{

		$allBlogs = $this->getMock('TYPO3\\CMS\\Extbase\\Persistence\\ObjectStorage', array(), array(), '', FALSE);

		$blogRepository = $this->getMock('BeechIt\\SimpleBlog\\Domain\\Repository\\BlogRepository', array('findAll'), array(), '', FALSE);
		$blogRepository->expects($this->once())->method('findAll')->will($this->returnValue($allBlogs));
		$this->inject($this->subject, 'blogRepository', $blogRepository);

		$view = $this->getMock('TYPO3\\CMS\\Extbase\\Mvc\\View\\ViewInterface');
		$view->expects($this->once())->method('assign')->with('blogs', $allBlogs);
		$this->inject($this->subject, 'view', $view);

		$this->subject->listAction();
	}

	/**
	 * @test
	 */
	public function showActionAssignsTheGivenBlogToView()
	{
		$blog = new \BeechIt\SimpleBlog\Domain\Model\Blog();

		$view = $this->getMock('TYPO3\\CMS\\Extbase\\Mvc\\View\\ViewInterface');
		$this->inject($this->subject, 'view', $view);
		$view->expects($this->once())->method('assign')->with('blog', $blog);

		$this->subject->showAction($blog);
	}

	/**
	 * @test
	 */
	public function createActionAddsTheGivenBlogToBlogRepository()
	{
		$blog = new \BeechIt\SimpleBlog\Domain\Model\Blog();

		$blogRepository = $this->getMock('BeechIt\\SimpleBlog\\Domain\\Repository\\BlogRepository', array('add'), array(), '', FALSE);
		$blogRepository->expects($this->once())->method('add')->with($blog);
		$this->inject($this->subject, 'blogRepository', $blogRepository);

		$this->subject->createAction($blog);
	}
}
