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
class AbstractNodeTest extends \TYPO3\CMS\Core\Tests\UnitTestCase {

	/**
	 * @test
	 */
	public function getNameReturnsSetName() {
		/** @var $node \TYPO3\CMS\Install\FolderStructure\AbstractNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(\TYPO3\CMS\Install\FolderStructure\AbstractNode::class, array('dummy'), array(), '', FALSE);
		$name = $this->getUniqueId('name_');
		$node->_set('name', $name);
		$this->assertSame($name, $node->getName());
	}

	/**
	 * @test
	 */
	public function getTargetPermissionReturnsSetTargetPermission() {
		/** @var $node \TYPO3\CMS\Install\FolderStructure\AbstractNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(\TYPO3\CMS\Install\FolderStructure\AbstractNode::class, array('dummy'), array(), '', FALSE);
		$permission = '1234';
		$node->_set('targetPermission', $permission);
		$this->assertSame($permission, $node->_call('getTargetPermission'));
	}

	/**
	 * @test
	 */
	public function getChildrenReturnsSetChildren() {
		/** @var $node \TYPO3\CMS\Install\FolderStructure\AbstractNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(\TYPO3\CMS\Install\FolderStructure\AbstractNode::class, array('dummy'), array(), '', FALSE);
		$children = array('1234');
		$node->_set('children', $children);
		$this->assertSame($children, $node->_call('getChildren'));
	}

	/**
	 * @test
	 */
	public function getParentReturnsSetParent() {
		/** @var $node \TYPO3\CMS\Install\FolderStructure\AbstractNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(\TYPO3\CMS\Install\FolderStructure\AbstractNode::class, array('dummy'), array(), '', FALSE);
		$parent = $this->getMock(\TYPO3\CMS\Install\FolderStructure\RootNodeInterface::class, array(), array(), '', FALSE);
		$node->_set('parent', $parent);
		$this->assertSame($parent, $node->_call('getParent'));
	}

	/**
	 * @test
	 */
	public function getAbsolutePathCallsParentForPathAndAppendsOwnName() {
		/** @var $node \TYPO3\CMS\Install\FolderStructure\AbstractNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(\TYPO3\CMS\Install\FolderStructure\AbstractNode::class, array('dummy'), array(), '', FALSE);
		$parent = $this->getMock(\TYPO3\CMS\Install\FolderStructure\RootNodeInterface::class, array(), array(), '', FALSE);
		$parentPath = '/foo/bar';
		$parent->expects($this->once())->method('getAbsolutePath')->will($this->returnValue($parentPath));
		$name = $this->getUniqueId('test_');
		$node->_set('parent', $parent);
		$node->_set('name', $name);
		$this->assertSame($parentPath . '/' . $name, $node->getAbsolutePath());
	}

	/**
	 * @test
	 */
	public function isWritableCallsParentIsWritable() {
		/** @var $node \TYPO3\CMS\Install\FolderStructure\AbstractNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(\TYPO3\CMS\Install\FolderStructure\AbstractNode::class, array('dummy'), array(), '', FALSE);
		$parentMock = $this->getMock(\TYPO3\CMS\Install\FolderStructure\NodeInterface::class, array(), array(), '', FALSE);
		$parentMock->expects($this->once())->method('isWritable');
		$node->_set('parent', $parentMock);
		$node->isWritable();
	}

	/**
	 * @test
	 */
	public function isWritableReturnsWritableStatusOfParent() {
		/** @var $node \TYPO3\CMS\Install\FolderStructure\AbstractNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(\TYPO3\CMS\Install\FolderStructure\AbstractNode::class, array('dummy'), array(), '', FALSE);
		$parentMock = $this->getMock(\TYPO3\CMS\Install\FolderStructure\NodeInterface::class, array(), array(), '', FALSE);
		$parentMock->expects($this->once())->method('isWritable')->will($this->returnValue(TRUE));
		$node->_set('parent', $parentMock);
		$this->assertTrue($node->isWritable());
	}

	/**
	 * @test
	 */
	public function existsReturnsTrueIfNodeExists() {
		/** @var $node \TYPO3\CMS\Install\FolderStructure\AbstractNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(\TYPO3\CMS\Install\FolderStructure\AbstractNode::class, array('getAbsolutePath'), array(), '', FALSE);
		$path = PATH_site . 'typo3temp/' . $this->getUniqueId('dir_');
		\TYPO3\CMS\Core\Utility\GeneralUtility::mkdir_deep($path);
		$this->testFilesToDelete[] = $path;
		$node->expects($this->any())->method('getAbsolutePath')->will($this->returnValue($path));
		$this->assertTrue($node->_call('exists'));
	}

	/**
	 * @test
	 */
	public function existsReturnsTrueIfIsLinkAndTargetIsDead() {
		if (TYPO3_OS === 'WIN') {
			$this->markTestSkipped('Test not available on Windows OS.');
		}
		/** @var $node \TYPO3\CMS\Install\FolderStructure\AbstractNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(\TYPO3\CMS\Install\FolderStructure\AbstractNode::class, array('getAbsolutePath'), array(), '', FALSE);
		$path = PATH_site . 'typo3temp/' . $this->getUniqueId('link_');
		$target = PATH_site . 'typo3temp/' . $this->getUniqueId('notExists_');
		symlink($target, $path);
		$this->testFilesToDelete[] = $path;
		$node->expects($this->any())->method('getAbsolutePath')->will($this->returnValue($path));
		$this->assertTrue($node->_call('exists'));
	}

	/**
	 * @test
	 */
	public function existsReturnsFalseIfNodeNotExists() {
		/** @var $node \TYPO3\CMS\Install\FolderStructure\AbstractNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(\TYPO3\CMS\Install\FolderStructure\AbstractNode::class, array('getAbsolutePath'), array(), '', FALSE);
		$path = PATH_site . 'typo3temp/' . $this->getUniqueId('dir_');
		$node->expects($this->any())->method('getAbsolutePath')->will($this->returnValue($path));
		$this->assertFalse($node->_call('exists'));
	}

	/**
	 * @test
	 * @expectedException \TYPO3\CMS\Install\FolderStructure\Exception
	 */
	public function fixPermissionThrowsExceptionIfPermissionAreAlreadyCorrect() {
		/** @var $node \TYPO3\CMS\Install\FolderStructure\AbstractNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(
			\TYPO3\CMS\Install\FolderStructure\AbstractNode::class,
			array('isPermissionCorrect', 'getAbsolutePath'),
			array(),
			'',
			FALSE
		);
		$node->expects($this->any())->method('getAbsolutePath')->will($this->returnValue(''));
		$node->expects($this->once())->method('isPermissionCorrect')->will($this->returnValue(TRUE));
		$node->_call('fixPermission');
	}

	/**
	 * @test
	 */
	public function fixPermissionReturnsNoticeStatusIfPermissionCanNotBeChanged() {
		if (TYPO3_OS === 'WIN') {
			$this->markTestSkipped('Test not available on Windows OS.');
		}
		if (function_exists('posix_getegid') && posix_getegid() === 0) {
			$this->markTestSkipped('Test skipped if run on linux as root');
		}
		/** @var $node \TYPO3\CMS\Install\FolderStructure\AbstractNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(
			\TYPO3\CMS\Install\FolderStructure\AbstractNode::class,
			array('isPermissionCorrect', 'getRelativePathBelowSiteRoot', 'getAbsolutePath'),
			array(),
			'',
			FALSE
		);
		$node->expects($this->any())->method('getRelativePathBelowSiteRoot')->will($this->returnValue(''));
		$node->expects($this->once())->method('isPermissionCorrect')->will($this->returnValue(FALSE));
		$path = PATH_site . 'typo3temp/' . $this->getUniqueId('root_');
		mkdir($path);
		$subPath = $path . '/' . $this->getUniqueId('dir_');
		mkdir($subPath);
		chmod($path, 02000);
		$this->testFilesToDelete[] = $path;
		$node->expects($this->any())->method('getAbsolutePath')->will($this->returnValue($subPath));
		$node->_set('targetPermission', '2770');
		$this->assertInstanceOf(\TYPO3\CMS\Install\Status\NoticeStatus::class, $node->_call('fixPermission'));
		chmod($path, 02770);
	}

	/**
	 * @test
	 */
	public function fixPermissionReturnsNoticeStatusIfPermissionsCanNotBeChanged() {
		if (TYPO3_OS === 'WIN') {
			$this->markTestSkipped('Test not available on Windows OS.');
		}
		if (function_exists('posix_getegid') && posix_getegid() === 0) {
			$this->markTestSkipped('Test skipped if run on linux as root');
		}
		/** @var $node \TYPO3\CMS\Install\FolderStructure\AbstractNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(
			\TYPO3\CMS\Install\FolderStructure\AbstractNode::class,
			array('isPermissionCorrect', 'getRelativePathBelowSiteRoot', 'getAbsolutePath'),
			array(),
			'',
			FALSE
		);
		$node->expects($this->any())->method('getRelativePathBelowSiteRoot')->will($this->returnValue(''));
		$node->expects($this->once())->method('isPermissionCorrect')->will($this->returnValue(FALSE));
		$path = PATH_site . 'typo3temp/' . $this->getUniqueId('root_');
		mkdir($path);
		$subPath = $path . '/' . $this->getUniqueId('dir_');
		mkdir($subPath);
		chmod($path, 02000);
		$this->testFilesToDelete[] = $path;
		$node->expects($this->any())->method('getAbsolutePath')->will($this->returnValue($subPath));
		$node->_set('targetPermission', '2770');
		$this->assertInstanceOf(\TYPO3\CMS\Install\Status\NoticeStatus::class, $node->_call('fixPermission'));
		chmod($path, 02770);
	}

	/**
	 * @test
	 */
	public function fixPermissionReturnsOkStatusIfPermissionCanBeFixedAndSetsPermissionToCorrectValue() {
		if (TYPO3_OS === 'WIN') {
			$this->markTestSkipped('Test not available on Windows OS.');
		}
		/** @var $node \TYPO3\CMS\Install\FolderStructure\AbstractNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(
			\TYPO3\CMS\Install\FolderStructure\AbstractNode::class,
			array('isPermissionCorrect', 'getRelativePathBelowSiteRoot', 'getAbsolutePath'),
			array(),
			'',
			FALSE
		);
		$node->expects($this->any())->method('getRelativePathBelowSiteRoot')->will($this->returnValue(''));
		$node->expects($this->once())->method('isPermissionCorrect')->will($this->returnValue(FALSE));
		$path = PATH_site . 'typo3temp/' . $this->getUniqueId('root_');
		mkdir($path);
		$subPath = $path . '/' . $this->getUniqueId('dir_');
		mkdir($subPath);
		chmod($path, 02770);
		$this->testFilesToDelete[] = $path;
		$node->_set('targetPermission', '2770');
		$node->expects($this->any())->method('getAbsolutePath')->will($this->returnValue($subPath));
		$this->assertInstanceOf(\TYPO3\CMS\Install\Status\OkStatus::class, $node->_call('fixPermission'));
		$resultDirectoryPermissions = substr(decoct(fileperms($subPath)), 1);
		$this->assertSame('2770', $resultDirectoryPermissions);
	}

	/**
	 * @test
	 */
	public function isPermissionCorrectReturnsTrueOnWindowsOs() {
		/** @var $node \TYPO3\CMS\Install\FolderStructure\AbstractNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(\TYPO3\CMS\Install\FolderStructure\AbstractNode::class, array('isWindowsOs'), array(), '', FALSE);
		$node->expects($this->once())->method('isWindowsOs')->will($this->returnValue(TRUE));
		$this->assertTrue($node->_call('isPermissionCorrect'));
	}

	/**
	 * @test
	 */
	public function isPermissionCorrectReturnsFalseIfTargetPermissionAndCurrentPermissionAreNotIdentical() {
		/** @var $node \TYPO3\CMS\Install\FolderStructure\AbstractNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(\TYPO3\CMS\Install\FolderStructure\AbstractNode::class, array('isWindowsOs', 'getCurrentPermission'), array(), '', FALSE);
		$node->expects($this->any())->method('isWindowsOs')->will($this->returnValue(FALSE));
		$node->expects($this->any())->method('getCurrentPermission')->will($this->returnValue('foo'));
		$node->_set('targetPermission', 'bar');
		$this->assertFalse($node->_call('isPermissionCorrect'));
	}

	/**
	 * @test
	 */
	public function getCurrentPermissionReturnsCurrentDirectoryPermission() {
		if (TYPO3_OS === 'WIN') {
			$this->markTestSkipped('Test not available on Windows OS.');
		}
		/** @var $node \TYPO3\CMS\Install\FolderStructure\AbstractNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(\TYPO3\CMS\Install\FolderStructure\AbstractNode::class, array('getAbsolutePath'), array(), '', FALSE);
		$path = PATH_site . 'typo3temp/' . $this->getUniqueId('dir_');
		\TYPO3\CMS\Core\Utility\GeneralUtility::mkdir_deep($path);
		$this->testFilesToDelete[] = $path;
		chmod($path, 02775);
		clearstatcache();
		$node->expects($this->any())->method('getAbsolutePath')->will($this->returnValue($path));
		$this->assertSame('2775', $node->_call('getCurrentPermission'));
	}

	/**
	 * @test
	 */
	public function getCurrentPermissionReturnsCurrentFilePermission() {
		if (TYPO3_OS === 'WIN') {
			$this->markTestSkipped('Test not available on Windows OS.');
		}
		/** @var $node \TYPO3\CMS\Install\FolderStructure\AbstractNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(\TYPO3\CMS\Install\FolderStructure\AbstractNode::class, array('getAbsolutePath'), array(), '', FALSE);
		$file = PATH_site . 'typo3temp/' . $this->getUniqueId('file_');
		touch($file);
		$this->testFilesToDelete[] = $file;
		chmod($file, 0770);
		clearstatcache();
		$node->expects($this->any())->method('getAbsolutePath')->will($this->returnValue($file));
		$this->assertSame('0770', $node->_call('getCurrentPermission'));
	}

	/**
	 * @test
	 * @expectedException \TYPO3\CMS\Install\FolderStructure\Exception\InvalidArgumentException
	 */
	public function getRelativePathBelowSiteRootThrowsExceptionIfGivenPathIsNotBelowPathSiteConstant() {
		/** @var $node \TYPO3\CMS\Install\FolderStructure\AbstractNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(\TYPO3\CMS\Install\FolderStructure\AbstractNode::class, array('dummy'), array(), '', FALSE);
		$node->_call('getRelativePathBelowSiteRoot', '/tmp');
	}

	/**
	 * @test
	 */
	public function getRelativePathCallsGetAbsolutePathIfPathIsNull() {
		/** @var $node \TYPO3\CMS\Install\FolderStructure\AbstractNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(
			\TYPO3\CMS\Install\FolderStructure\AbstractNode::class,
			array('getAbsolutePath'),
			array(),
			'',
			FALSE
		);
		$node->expects($this->once())->method('getAbsolutePath')->will($this->returnValue(PATH_site));
		$node->_call('getRelativePathBelowSiteRoot', NULL);
	}

	/**
	 * @test
	 */
	public function getRelativePathBelowSiteRootReturnsSingleForwardSlashIfGivenPathEqualsPathSiteConstant() {
		/** @var $node \TYPO3\CMS\Install\FolderStructure\AbstractNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(\TYPO3\CMS\Install\FolderStructure\AbstractNode::class, array('dummy'), array(), '', FALSE);
		$result = $node->_call('getRelativePathBelowSiteRoot', PATH_site);
		$this->assertSame('/', $result);
	}

	/**
	 * @test
	 */
	public function getRelativePathBelowSiteRootReturnsSubPath() {
		/** @var $node \TYPO3\CMS\Install\FolderStructure\AbstractNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(\TYPO3\CMS\Install\FolderStructure\AbstractNode::class, array('dummy'), array(), '', FALSE);
		$result = $node->_call('getRelativePathBelowSiteRoot', PATH_site . 'foo/bar');
		$this->assertSame('/foo/bar', $result);
	}

}
