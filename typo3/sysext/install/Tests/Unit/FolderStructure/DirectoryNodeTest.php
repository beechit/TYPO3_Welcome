<?php
namespace TYPO3\CMS\Install\Tests\Unit\FolderStructure;

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
 */
class DirectoryNodeTest extends \TYPO3\CMS\Core\Tests\UnitTestCase {

	/**
	 * @test
	 * @expectedException \TYPO3\CMS\Install\FolderStructure\Exception\InvalidArgumentException
	 */
	public function constructorThrowsExceptionIfParentIsNull() {
		/** @var $node \TYPO3\CMS\Install\FolderStructure\DirectoryNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(\TYPO3\CMS\Install\FolderStructure\DirectoryNode::class, array('dummy'), array(), '', FALSE);
		$node->__construct(array(), NULL);
	}

	/**
	 * @test
	 * @expectedException \TYPO3\CMS\Install\FolderStructure\Exception\InvalidArgumentException
	 */
	public function constructorThrowsExceptionIfNameContainsForwardSlash() {
		$parent = $this->getMock(\TYPO3\CMS\Install\FolderStructure\NodeInterface::class, array(), array(), '', FALSE);
		/** @var $node \TYPO3\CMS\Install\FolderStructure\DirectoryNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(\TYPO3\CMS\Install\FolderStructure\DirectoryNode::class, array('dummy'), array(), '', FALSE);
		$structure = array(
			'name' => 'foo/bar',
		);
		$node->__construct($structure, $parent);
	}

	/**
	 * @test
	 */
	public function constructorCallsCreateChildrenIfChildrenAreSet() {
		$parent = $this->getMock(\TYPO3\CMS\Install\FolderStructure\NodeInterface::class, array(), array(), '', FALSE);
		/** @var $node \TYPO3\CMS\Install\FolderStructure\DirectoryNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(
			\TYPO3\CMS\Install\FolderStructure\DirectoryNode::class,
			array('createChildren'),
			array(),
			'',
			FALSE
		);
		$childArray = array(
			'foo',
		);
		$structure = array(
			'name' => 'foo',
			'children' => $childArray,
		);
		$node->expects($this->once())->method('createChildren')->with($childArray);
		$node->__construct($structure, $parent);
	}

	/**
	 * @test
	 */
	public function constructorSetsParent() {
		$parent = $this->getMock(\TYPO3\CMS\Install\FolderStructure\NodeInterface::class, array(), array(), '', FALSE);
		/** @var $node \TYPO3\CMS\Install\FolderStructure\DirectoryNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(\TYPO3\CMS\Install\FolderStructure\DirectoryNode::class, array('dummy'), array(), '', FALSE);
		$structure = array(
			'name' => 'foo',
		);
		$node->__construct($structure, $parent);
		$this->assertSame($parent, $node->_call('getParent'));
	}

	/**
	 * @test
	 */
	public function constructorSetsTargetPermission() {
		$parent = $this->getMock(\TYPO3\CMS\Install\FolderStructure\NodeInterface::class, array(), array(), '', FALSE);
		/** @var $node \TYPO3\CMS\Install\FolderStructure\DirectoryNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(\TYPO3\CMS\Install\FolderStructure\DirectoryNode::class, array('dummy'), array(), '', FALSE);
		$targetPermission = '2550';
		$structure = array(
			'name' => 'foo',
			'targetPermission' => $targetPermission,
		);
		$node->__construct($structure, $parent);
		$this->assertSame($targetPermission, $node->_call('getTargetPermission'));
	}

	/**
	 * @test
	 */
	public function constructorSetsName() {
		/** @var $node \TYPO3\CMS\Install\FolderStructure\DirectoryNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(\TYPO3\CMS\Install\FolderStructure\DirectoryNode::class, array('dummy'), array(), '', FALSE);
		$parent = $this->getMock(\TYPO3\CMS\Install\FolderStructure\RootNodeInterface::class, array(), array(), '', FALSE);
		$name = $this->getUniqueId('test_');
		$node->__construct(array('name' => $name), $parent);
		$this->assertSame($name, $node->getName());
	}

	/**
	 * @test
	 */
	public function getStatusReturnsArray() {
		/** @var $node \TYPO3\CMS\Install\FolderStructure\DirectoryNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(
			\TYPO3\CMS\Install\FolderStructure\DirectoryNode::class,
			array('getAbsolutePath', 'exists', 'isDirectory', 'isWritable', 'isPermissionCorrect'),
			array(),
			'',
			FALSE
		);
		$path = PATH_site . 'typo3temp/' . $this->getUniqueId('dir_');
		$node->expects($this->any())->method('getAbsolutePath')->will($this->returnValue($path));
		$node->expects($this->any())->method('exists')->will($this->returnValue(TRUE));
		$node->expects($this->any())->method('isDirectory')->will($this->returnValue(TRUE));
		$node->expects($this->any())->method('isPermissionCorrect')->will($this->returnValue(TRUE));
		$node->expects($this->any())->method('isWritable')->will($this->returnValue(TRUE));
		$this->assertInternalType('array', $node->getStatus());
	}

	/**
	 * @test
	 */
	public function getStatusReturnsArrayWithWarningStatusIfDirectoryNotExists() {
		/** @var $node \TYPO3\CMS\Install\FolderStructure\DirectoryNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(
			\TYPO3\CMS\Install\FolderStructure\DirectoryNode::class,
			array('getAbsolutePath', 'exists', 'isDirectory', 'isWritable', 'isPermissionCorrect'),
			array(),
			'',
			FALSE
		);
		$path = PATH_site . 'typo3temp/' . $this->getUniqueId('dir_');
		$node->expects($this->any())->method('getAbsolutePath')->will($this->returnValue($path));
		$node->expects($this->any())->method('exists')->will($this->returnValue(FALSE));
		$node->expects($this->any())->method('isDirectory')->will($this->returnValue(FALSE));
		$node->expects($this->any())->method('isPermissionCorrect')->will($this->returnValue(FALSE));
		$node->expects($this->any())->method('isWritable')->will($this->returnValue(FALSE));
		$statusArray = $node->getStatus();
		/** @var $status \TYPO3\CMS\Install\Status\StatusInterface */
		$status = $statusArray[0];
		$this->assertInstanceOf(\TYPO3\CMS\Install\Status\WarningStatus::class, $status);
	}

	/**
	 * @test
	 */
	public function getStatusReturnsArrayWithErrorStatusIfNodeIsNotADirectory() {
		/** @var $node \TYPO3\CMS\Install\FolderStructure\DirectoryNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(
			\TYPO3\CMS\Install\FolderStructure\DirectoryNode::class,
			array('getAbsolutePath', 'exists', 'isDirectory', 'isWritable', 'isPermissionCorrect'),
			array(),
			'',
			FALSE
		);
		$path = PATH_site . 'typo3temp/' . $this->getUniqueId('dir_');
		touch ($path);
		$this->testFilesToDelete[] = $path;
		$node->expects($this->any())->method('getAbsolutePath')->will($this->returnValue($path));
		$node->expects($this->any())->method('exists')->will($this->returnValue(TRUE));
		$node->expects($this->any())->method('isDirectory')->will($this->returnValue(FALSE));
		$node->expects($this->any())->method('isPermissionCorrect')->will($this->returnValue(TRUE));
		$node->expects($this->any())->method('isWritable')->will($this->returnValue(TRUE));
		$statusArray = $node->getStatus();
		/** @var $status \TYPO3\CMS\Install\Status\StatusInterface */
		$status = $statusArray[0];
		$this->assertInstanceOf(\TYPO3\CMS\Install\Status\ErrorStatus::class, $status);
	}

	/**
	 * @test
	 */
	public function getStatusReturnsArrayWithErrorStatusIfDirectoryExistsButIsNotWritable() {
		/** @var $node \TYPO3\CMS\Install\FolderStructure\DirectoryNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(
			\TYPO3\CMS\Install\FolderStructure\DirectoryNode::class,
			array('getAbsolutePath', 'exists', 'isDirectory', 'isWritable', 'isPermissionCorrect'),
			array(),
			'',
			FALSE
		);
		$path = PATH_site . 'typo3temp/' . $this->getUniqueId('dir_');
		touch ($path);
		$this->testFilesToDelete[] = $path;
		$node->expects($this->any())->method('getAbsolutePath')->will($this->returnValue($path));
		$node->expects($this->any())->method('exists')->will($this->returnValue(TRUE));
		$node->expects($this->any())->method('isDirectory')->will($this->returnValue(TRUE));
		$node->expects($this->any())->method('isPermissionCorrect')->will($this->returnValue(TRUE));
		$node->expects($this->any())->method('isWritable')->will($this->returnValue(FALSE));
		$statusArray = $node->getStatus();
		/** @var $status \TYPO3\CMS\Install\Status\StatusInterface */
		$status = $statusArray[0];
		$this->assertInstanceOf(\TYPO3\CMS\Install\Status\ErrorStatus::class, $status);
	}

	/**
	 * @test
	 */
	public function getStatusReturnsArrayWithNoticeStatusIfDirectoryExistsButPermissionAreNotCorrect() {
		/** @var $node \TYPO3\CMS\Install\FolderStructure\DirectoryNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(
			\TYPO3\CMS\Install\FolderStructure\DirectoryNode::class,
			array('getAbsolutePath', 'exists', 'isDirectory', 'isWritable', 'isPermissionCorrect'),
			array(),
			'',
			FALSE
		);
		$path = PATH_site . 'typo3temp/' . $this->getUniqueId('dir_');
		touch ($path);
		$this->testFilesToDelete[] = $path;
		$node->expects($this->any())->method('getAbsolutePath')->will($this->returnValue($path));
		$node->expects($this->any())->method('exists')->will($this->returnValue(TRUE));
		$node->expects($this->any())->method('isDirectory')->will($this->returnValue(TRUE));
		$node->expects($this->any())->method('isPermissionCorrect')->will($this->returnValue(FALSE));
		$node->expects($this->any())->method('isWritable')->will($this->returnValue(TRUE));
		$statusArray = $node->getStatus();
		/** @var $status \TYPO3\CMS\Install\Status\StatusInterface */
		$status = $statusArray[0];
		$this->assertInstanceOf(\TYPO3\CMS\Install\Status\NoticeStatus::class, $status);
	}

	/**
	 * @test
	 */
	public function getStatusReturnsArrayWithOkStatusIfDirectoryExistsAndPermissionAreCorrect() {
		/** @var $node \TYPO3\CMS\Install\FolderStructure\DirectoryNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(
			\TYPO3\CMS\Install\FolderStructure\DirectoryNode::class,
			array('getAbsolutePath', 'exists', 'isDirectory', 'isWritable', 'isPermissionCorrect'),
			array(),
			'',
			FALSE
		);
		$path = PATH_site . 'typo3temp/' . $this->getUniqueId('dir_');
		touch ($path);
		$this->testFilesToDelete[] = $path;
		$node->expects($this->any())->method('getAbsolutePath')->will($this->returnValue($path));
		$node->expects($this->any())->method('exists')->will($this->returnValue(TRUE));
		$node->expects($this->any())->method('isDirectory')->will($this->returnValue(TRUE));
		$node->expects($this->any())->method('isPermissionCorrect')->will($this->returnValue(TRUE));
		$node->expects($this->any())->method('isWritable')->will($this->returnValue(TRUE));
		$statusArray = $node->getStatus();
		/** @var $status \TYPO3\CMS\Install\Status\StatusInterface */
		$status = $statusArray[0];
		$this->assertInstanceOf(\TYPO3\CMS\Install\Status\OkStatus::class, $status);
	}

	/**
	 * @test
	 */
	public function getStatusCallsGetStatusOnChildren() {
		/** @var $node \TYPO3\CMS\Install\FolderStructure\DirectoryNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(
			\TYPO3\CMS\Install\FolderStructure\DirectoryNode::class,
			array('exists', 'isDirectory', 'isPermissionCorrect', 'getRelativePathBelowSiteRoot', 'isWritable'),
			array(),
			'',
			FALSE
		);
		$node->expects($this->any())->method('exists')->will($this->returnValue(TRUE));
		$node->expects($this->any())->method('isDirectory')->will($this->returnValue(TRUE));
		$node->expects($this->any())->method('isPermissionCorrect')->will($this->returnValue(TRUE));
		$node->expects($this->any())->method('isWritable')->will($this->returnValue(TRUE));
		$childMock1 = $this->getMock(\TYPO3\CMS\Install\FolderStructure\NodeInterface::class, array(), array(), '', FALSE);
		$childMock1->expects($this->once())->method('getStatus')->will($this->returnValue(array()));
		$childMock2 = $this->getMock(\TYPO3\CMS\Install\FolderStructure\NodeInterface::class, array(), array(), '', FALSE);
		$childMock2->expects($this->once())->method('getStatus')->will($this->returnValue(array()));
		$node->_set('children', array($childMock1, $childMock2));
		$node->getStatus();
	}

	/**
	 * @test
	 */
	public function getStatusReturnsArrayWithOwnStatusAndStatusOfChild() {
		/** @var $node \TYPO3\CMS\Install\FolderStructure\DirectoryNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(
			\TYPO3\CMS\Install\FolderStructure\DirectoryNode::class,
			array('exists', 'isDirectory', 'isPermissionCorrect', 'getRelativePathBelowSiteRoot', 'isWritable'),
			array(),
			'',
			FALSE
		);
		$node->expects($this->any())->method('exists')->will($this->returnValue(TRUE));
		$node->expects($this->any())->method('isDirectory')->will($this->returnValue(TRUE));
		$node->expects($this->any())->method('isPermissionCorrect')->will($this->returnValue(TRUE));
		$node->expects($this->any())->method('isWritable')->will($this->returnValue(TRUE));
		$childMock = $this->getMock(\TYPO3\CMS\Install\FolderStructure\NodeInterface::class, array(), array(), '', FALSE);
		$childStatusMock = $this->getMock(\TYPO3\CMS\Install\Status\ErrorStatus::class, array(), array(), '', FALSE);
		$childMock->expects($this->once())->method('getStatus')->will($this->returnValue(array($childStatusMock)));
		$node->_set('children', array($childMock));
		$status = $node->getStatus();
		$statusOfDirectory = $status[0];
		$statusOfChild = $status[1];
		$this->assertInstanceOf(\TYPO3\CMS\Install\Status\OkStatus::class, $statusOfDirectory);
		$this->assertInstanceOf(\TYPO3\CMS\Install\Status\ErrorStatus::class, $statusOfChild);
	}

	/**
	 * @test
	 */
	public function fixCallsFixSelfAndReturnsItsResult() {
		/** @var $node \TYPO3\CMS\Install\FolderStructure\DirectoryNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(
			\TYPO3\CMS\Install\FolderStructure\DirectoryNode::class,
			array('fixSelf'),
			array(),
			'',
			FALSE
		);
		$uniqueReturn = array($this->getUniqueId('foo_'));
		$node->expects($this->once())->method('fixSelf')->will($this->returnValue($uniqueReturn));
		$this->assertSame($uniqueReturn, $node->fix());
	}

	/**
	 * @test
	 */
	public function fixCallsFixOnChildrenAndReturnsMergedResult() {
		/** @var $node \TYPO3\CMS\Install\FolderStructure\DirectoryNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(\TYPO3\CMS\Install\FolderStructure\DirectoryNode::class, array('fixSelf'), array(), '', FALSE);
		$uniqueReturnSelf = $this->getUniqueId('foo_');
		$node->expects($this->once())->method('fixSelf')->will($this->returnValue(array($uniqueReturnSelf)));

		$childMock1 = $this->getMock(\TYPO3\CMS\Install\FolderStructure\NodeInterface::class, array(), array(), '', FALSE);
		$uniqueReturnChild1 = $this->getUniqueId('foo_');
		$childMock1->expects($this->once())->method('fix')->will($this->returnValue(array($uniqueReturnChild1)));

		$childMock2 = $this->getMock(\TYPO3\CMS\Install\FolderStructure\NodeInterface::class, array(), array(), '', FALSE);
		$uniqueReturnChild2 = $this->getUniqueId('foo_');
		$childMock2->expects($this->once())->method('fix')->will($this->returnValue(array($uniqueReturnChild2)));

		$node->_set('children', array($childMock1, $childMock2));

		$this->assertSame(array($uniqueReturnSelf, $uniqueReturnChild1, $uniqueReturnChild2), $node->fix());
	}

	/**
	 * @test
	 */
	public function fixSelfCallsCreateDirectoryIfDirectoryDoesNotExistAndReturnsResult() {
		/** @var $node \TYPO3\CMS\Install\FolderStructure\DirectoryNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(
			\TYPO3\CMS\Install\FolderStructure\DirectoryNode::class,
			array('exists', 'createDirectory', 'isPermissionCorrect'),
			array(),
			'',
			FALSE
		);
		$node->expects($this->once())->method('exists')->will($this->returnValue(FALSE));
		$node->expects($this->any())->method('isPermissionCorrect')->will($this->returnValue(TRUE));
		$uniqueReturn = $this->getUniqueId();
		$node->expects($this->once())->method('createDirectory')->will($this->returnValue($uniqueReturn));
		$this->assertSame(array($uniqueReturn), $node->_call('fixSelf'));
	}

	/**
	 * @test
	 */
	public function fixSelfReturnsErrorStatusIfNodeExistsButIsNotADirectoryAndReturnsResult() {
		/** @var $node \TYPO3\CMS\Install\FolderStructure\DirectoryNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(
			\TYPO3\CMS\Install\FolderStructure\DirectoryNode::class,
			array('exists', 'isWritable', 'getRelativePathBelowSiteRoot', 'isDirectory', 'getAbsolutePath'),
			array(),
			'',
			FALSE
		);
		$node->expects($this->any())->method('exists')->will($this->returnValue(TRUE));
		$node->expects($this->any())->method('isWritable')->will($this->returnValue(TRUE));
		$node->expects($this->any())->method('isDirectory')->will($this->returnValue(FALSE));
		$node->expects($this->any())->method('getRelativePathBelowSiteRoot')->will($this->returnValue(''));
		$node->expects($this->any())->method('getAbsolutePath')->will($this->returnValue(''));
		$result = $node->_call('fixSelf');
		$this->assertInstanceOf(\TYPO3\CMS\Install\Status\ErrorStatus::class, $result[0]);
	}

	/**
	 * @test
	 */
	public function fixSelfCallsFixPermissionIfDirectoryExistsButIsNotWritable() {
		/** @var $node \TYPO3\CMS\Install\FolderStructure\DirectoryNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(
			\TYPO3\CMS\Install\FolderStructure\DirectoryNode::class,
			array('exists', 'isWritable', 'fixPermission'),
			array(),
			'',
			FALSE
		);
		$node->expects($this->any())->method('exists')->will($this->returnValue(TRUE));
		$node->expects($this->any())->method('isWritable')->will($this->returnValue(FALSE));
		$node->expects($this->once())->method('fixPermission')->will($this->returnValue(TRUE));
		$this->assertSame(array(TRUE), $node->_call('fixSelf'));
	}

	/**
	 * @test
	 * @expectedException \TYPO3\CMS\Install\FolderStructure\Exception
	 */
	public function createDirectoryThrowsExceptionIfNodeExists() {
		/** @var $node \TYPO3\CMS\Install\FolderStructure\DirectoryNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(\TYPO3\CMS\Install\FolderStructure\DirectoryNode::class, array('exists', 'getAbsolutePath'), array(), '', FALSE);
		$node->expects($this->once())->method('getAbsolutePath')->will($this->returnValue(''));
		$node->expects($this->once())->method('exists')->will($this->returnValue(TRUE));
		$node->_call('createDirectory');
	}

	/**
	 * @test
	 */
	public function createDirectoryCreatesDirectory() {
		/** @var $node \TYPO3\CMS\Install\FolderStructure\DirectoryNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(\TYPO3\CMS\Install\FolderStructure\DirectoryNode::class, array('exists', 'getAbsolutePath'), array(), '', FALSE);
		$path = PATH_site . 'typo3temp/' . $this->getUniqueId('dir_');
		$this->testFilesToDelete[] = $path;
		$node->expects($this->once())->method('exists')->will($this->returnValue(FALSE));
		$node->expects($this->any())->method('getAbsolutePath')->will($this->returnValue($path));
		$node->_call('createDirectory');
		$this->assertTrue(is_dir($path));
	}

	/**
	 * @test
	 */
	public function createDirectoryReturnsOkStatusIfDirectoryWasCreated() {
		/** @var $node \TYPO3\CMS\Install\FolderStructure\DirectoryNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(\TYPO3\CMS\Install\FolderStructure\DirectoryNode::class, array('exists', 'getAbsolutePath'), array(), '', FALSE);
		$path = PATH_site . 'typo3temp/' . $this->getUniqueId('dir_');
		$this->testFilesToDelete[] = $path;
		$node->expects($this->once())->method('exists')->will($this->returnValue(FALSE));
		$node->expects($this->any())->method('getAbsolutePath')->will($this->returnValue($path));
		$this->assertInstanceOf(\TYPO3\CMS\Install\Status\StatusInterface::class, $node->_call('createDirectory'));
	}

	/**
	 * @test
	 */
	public function createDirectoryReturnsErrorStatusIfDirectoryWasNotCreated() {
		if (TYPO3_OS === 'WIN') {
			$this->markTestSkipped('Test not available on Windows OS.');
		}
		/** @var $node \TYPO3\CMS\Install\FolderStructure\DirectoryNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(\TYPO3\CMS\Install\FolderStructure\DirectoryNode::class, array('exists', 'getAbsolutePath'), array(), '', FALSE);
		$path = PATH_site . 'typo3temp/' . $this->getUniqueId('root_');
		mkdir($path);
		chmod($path, 02550);
		$subPath = $path . '/' . $this->getUniqueId('dir_');
		$this->testFilesToDelete[] = $path;
		$node->expects($this->once())->method('exists')->will($this->returnValue(FALSE));
		$node->expects($this->any())->method('getAbsolutePath')->will($this->returnValue($subPath));
		$this->assertInstanceOf(\TYPO3\CMS\Install\Status\StatusInterface::class, $node->_call('createDirectory'));
	}

	/**
	 * @test
	 * @expectedException \TYPO3\CMS\Install\FolderStructure\Exception\InvalidArgumentException
	 */
	public function createChildrenThrowsExceptionIfAChildTypeIsNotSet() {
		/** @var $node \TYPO3\CMS\Install\FolderStructure\DirectoryNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(\TYPO3\CMS\Install\FolderStructure\DirectoryNode::class, array('dummy'), array(), '', FALSE);
		$brokenStructure = array(
			array(
				'name' => 'foo',
			),
		);
		$node->_call('createChildren', $brokenStructure);
	}

	/**
	 * @test
	 * @expectedException \TYPO3\CMS\Install\FolderStructure\Exception\InvalidArgumentException
	 */
	public function createChildrenThrowsExceptionIfAChildNameIsNotSet() {
		/** @var $node \TYPO3\CMS\Install\FolderStructure\DirectoryNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(\TYPO3\CMS\Install\FolderStructure\DirectoryNode::class, array('dummy'), array(), '', FALSE);
		$brokenStructure = array(
			array(
				'type' => 'foo',
			),
		);
		$node->_call('createChildren', $brokenStructure);
	}

	/**
	 * @test
	 * @expectedException \TYPO3\CMS\Install\FolderStructure\Exception\InvalidArgumentException
	 */
	public function createChildrenThrowsExceptionForMultipleChildrenWithSameName() {
		/** @var $node \TYPO3\CMS\Install\FolderStructure\DirectoryNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(\TYPO3\CMS\Install\FolderStructure\DirectoryNode::class, array('dummy'), array(), '', FALSE);
		$brokenStructure = array(
			array(
				'type' => \TYPO3\CMS\Install\FolderStructure\DirectoryNode::class,
				'name' => 'foo',
			),
			array(
				'type' => \TYPO3\CMS\Install\FolderStructure\DirectoryNode::class,
				'name' => 'foo',
			),
		);
		$node->_call('createChildren', $brokenStructure);
	}

	/**
	 * @test
	 */
	public function getChildrenReturnsCreatedChild() {
		/** @var $node \TYPO3\CMS\Install\FolderStructure\DirectoryNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(\TYPO3\CMS\Install\FolderStructure\DirectoryNode::class, array('dummy'), array(), '', FALSE);
		$parent = $this->getMock(\TYPO3\CMS\Install\FolderStructure\NodeInterface::class, array(), array(), '', FALSE);
		$childName = $this->getUniqueId('test_');
		$structure = array(
			'name' => 'foo',
			'type' => \TYPO3\CMS\Install\FolderStructure\DirectoryNode::class,
			'children' => array(
				array(
					'type' => \TYPO3\CMS\Install\FolderStructure\DirectoryNode::class,
					'name' => $childName,
				),
			),
		);
		$node->__construct($structure, $parent);
		$children = $node->_call('getChildren');
		/** @var $child \TYPO3\CMS\Install\FolderStructure\NodeInterface */
		$child = $children[0];
		$this->assertInstanceOf(\TYPO3\CMS\Install\FolderStructure\DirectoryNode::class, $children[0]);
		$this->assertSame($childName, $child->getName());
	}

	/**
	 * @test
	 */
	public function isWritableReturnsFalseIfNodeDoesNotExist() {
		/** @var $node \TYPO3\CMS\Install\FolderStructure\DirectoryNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(\TYPO3\CMS\Install\FolderStructure\DirectoryNode::class, array('getAbsolutePath'), array(), '', FALSE);
		$path = PATH_site . 'typo3temp/' . $this->getUniqueId('dir_');
		$node->expects($this->any())->method('getAbsolutePath')->will($this->returnValue($path));
		$this->assertFalse($node->isWritable());
	}

	/**
	 * @test
	 */
	public function isWritableReturnsTrueIfNodeExistsAndFileCanBeCreated() {
		/** @var $node \TYPO3\CMS\Install\FolderStructure\DirectoryNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(\TYPO3\CMS\Install\FolderStructure\DirectoryNode::class, array('getAbsolutePath'), array(), '', FALSE);
		$path = PATH_site . 'typo3temp/' . $this->getUniqueId('root_');
		\TYPO3\CMS\Core\Utility\GeneralUtility::mkdir_deep($path);
		$this->testFilesToDelete[] = $path;
		$node->expects($this->any())->method('getAbsolutePath')->will($this->returnValue($path));
		$this->assertTrue($node->isWritable());
	}

	/**
	 * @test
	 */
	public function isWritableReturnsFalseIfNodeExistsButFileCanNotBeCreated() {
		if (TYPO3_OS === 'WIN') {
			$this->markTestSkipped('Test not available on Windows OS.');
		}
		if (function_exists('posix_getegid') && posix_getegid() === 0) {
			$this->markTestSkipped('Test skipped if run on linux as root');
		}
		/** @var $node \TYPO3\CMS\Install\FolderStructure\DirectoryNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(\TYPO3\CMS\Install\FolderStructure\DirectoryNode::class, array('getAbsolutePath'), array(), '', FALSE);
		$path = PATH_site . 'typo3temp/' . $this->getUniqueId('root_');
		\TYPO3\CMS\Core\Utility\GeneralUtility::mkdir_deep($path);
		$this->testFilesToDelete[] = $path;
		chmod($path, 02550);
		$node->expects($this->any())->method('getAbsolutePath')->will($this->returnValue($path));
		$this->assertFalse($node->isWritable());
	}

	/**
	 * @test
	 */
	public function isDirectoryReturnsTrueIfNameIsADirectory() {
		/** @var $node \TYPO3\CMS\Install\FolderStructure\DirectoryNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(\TYPO3\CMS\Install\FolderStructure\DirectoryNode::class, array('getAbsolutePath'), array(), '', FALSE);
		$path = PATH_site . 'typo3temp/' . $this->getUniqueId('dir_');
		\TYPO3\CMS\Core\Utility\GeneralUtility::mkdir_deep($path);
		$this->testFilesToDelete[] = $path;
		$node->expects($this->any())->method('getAbsolutePath')->will($this->returnValue($path));
		$this->assertTrue($node->_call('isDirectory'));
	}

	/**
	 * @test
	 */
	public function isDirectoryReturnsFalseIfNameIsALinkToADirectory() {
		if (TYPO3_OS === 'WIN') {
			$this->markTestSkipped('Test not available on Windows OS.');
		}
		/** @var $node \TYPO3\CMS\Install\FolderStructure\DirectoryNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(\TYPO3\CMS\Install\FolderStructure\DirectoryNode::class, array('getAbsolutePath'), array(), '', FALSE);
		$path = PATH_site . 'typo3temp/' . $this->getUniqueId('root_');
		\TYPO3\CMS\Core\Utility\GeneralUtility::mkdir_deep($path);
		$this->testFilesToDelete[] = $path;
		$link = $this->getUniqueId('link_');
		$dir = $this->getUniqueId('dir_');
		mkdir($path . '/' . $dir);
		symlink($path . '/' . $dir, $path . '/' . $link);
		$node->expects($this->any())->method('getAbsolutePath')->will($this->returnValue($path . '/' . $link));
		$this->assertFalse($node->_call('isDirectory'));
	}

}
