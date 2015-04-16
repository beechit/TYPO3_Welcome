<?php
namespace TYPO3\CMS\Core\Tests\Unit\Cache\Frontend;

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
 * Testcase for the PHP source code cache frontend
 *
 * This file is a backport from FLOW3
 *
 * @author Christian Kuhn <lolli@schwarzbu.ch>
 */
class PhpFrontendTest extends \TYPO3\CMS\Core\Tests\UnitTestCase {

	/**
	 * @test
	 * @expectedException \InvalidArgumentException
	 */
	public function setChecksIfTheIdentifierIsValid() {
		$cache = $this->getMock(\TYPO3\CMS\Core\Cache\Frontend\StringFrontend::class, array('isValidEntryIdentifier'), array(), '', FALSE);
		$cache->expects($this->once())->method('isValidEntryIdentifier')->with('foo')->will($this->returnValue(FALSE));
		$cache->set('foo', 'bar');
	}

	/**
	 * @test
	 */
	public function setPassesPhpSourceCodeTagsAndLifetimeToBackend() {
		$originalSourceCode = 'return "hello world!";';
		$modifiedSourceCode = '<?php' . chr(10) . $originalSourceCode . chr(10) . '#';
		$mockBackend = $this->getMock(\TYPO3\CMS\Core\Cache\Backend\PhpCapableBackendInterface::class, array(), array(), '', FALSE);
		$mockBackend->expects($this->once())->method('set')->with('Foo-Bar', $modifiedSourceCode, array('tags'), 1234);
		$cache = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Cache\Frontend\PhpFrontend::class, 'PhpFrontend', $mockBackend);
		$cache->set('Foo-Bar', $originalSourceCode, array('tags'), 1234);
	}

	/**
	 * @test
	 * @expectedException \TYPO3\CMS\Core\Cache\Exception\InvalidDataException
	 */
	public function setThrowsInvalidDataExceptionOnNonStringValues() {
		$cache = $this->getMock(\TYPO3\CMS\Core\Cache\Frontend\PhpFrontend::class, array('dummy'), array(), '', FALSE);
		$cache->set('Foo-Bar', array());
	}

	/**
	 * @test
	 */
	public function requireOnceCallsTheBackendsRequireOnceMethod() {
		$mockBackend = $this->getMock(\TYPO3\CMS\Core\Cache\Backend\PhpCapableBackendInterface::class, array(), array(), '', FALSE);
		$mockBackend->expects($this->once())->method('requireOnce')->with('Foo-Bar')->will($this->returnValue('hello world!'));
		$cache = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Cache\Frontend\PhpFrontend::class, 'PhpFrontend', $mockBackend);
		$result = $cache->requireOnce('Foo-Bar');
		$this->assertSame('hello world!', $result);
	}

}
