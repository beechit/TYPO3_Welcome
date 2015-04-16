<?php
namespace TYPO3\CMS\Core\Tests\Unit\Utility;

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
 * Testcase for the \TYPO3\CMS\Core\Utility\MailUtility class.
 *
 * @author Oliver Klee <typo3-coding@oliverklee.de>
 */
class MailUtilityTest extends \TYPO3\CMS\Core\Tests\UnitTestCase {

	/**
	 * @var array A backup of registered singleton instances
	 */
	protected $singletonInstances = array();

	protected function setUp() {
		$this->singletonInstances = \TYPO3\CMS\Core\Utility\GeneralUtility::getSingletonInstances();
	}

	protected function tearDown() {
		\TYPO3\CMS\Core\Utility\GeneralUtility::resetSingletonInstances($this->singletonInstances);
		parent::tearDown();
	}

	/**
	 * @test
	 */
	public function breakLinesForEmailReturnsEmptyStringIfEmptryStringIsGiven() {
		$this->assertEmpty(\TYPO3\CMS\Core\Utility\MailUtility::breakLinesForEmail(''));
	}

	/**
	 * @test
	 */
	public function breakLinesForEmailReturnsOneLineIfCharWithIsNotExceeded() {
		$newlineChar = LF;
		$lineWidth = 76;
		$str = 'This text is not longer than 76 chars and therefore will not be broken.';
		$returnString = \TYPO3\CMS\Core\Utility\MailUtility::breakLinesForEmail($str, $newlineChar, $lineWidth);
		$this->assertEquals(1, count(explode($newlineChar, $returnString)));
	}

	/**
	 * @test
	 */
	public function breakLinesForEmailBreaksTextIfCharWithIsExceeded() {
		$newlineChar = LF;
		$lineWidth = 50;
		$str = 'This text is longer than 50 chars and therefore will be broken.';
		$returnString = \TYPO3\CMS\Core\Utility\MailUtility::breakLinesForEmail($str, $newlineChar, $lineWidth);
		$this->assertEquals(2, count(explode($newlineChar, $returnString)));
	}

	/**
	 * @test
	 */
	public function breakLinesForEmailBreaksTextWithNoSpaceFoundBeforeLimit() {
		$newlineChar = LF;
		$lineWidth = 10;
		// first space after 20 chars (more than $lineWidth)
		$str = 'abcdefghijklmnopqrst uvwxyz 123456';
		$returnString = \TYPO3\CMS\Core\Utility\MailUtility::breakLinesForEmail($str, $newlineChar, $lineWidth);
		$this->assertEquals($returnString, 'abcdefghijklmnopqrst' . LF . 'uvwxyz' . LF . '123456');
	}

	/**
	 * @test
	 */
	public function breakLinesForEmailBreaksTextIfLineIsLongerThanTheLineWidth() {
		$str = 'Mein Link auf eine News (Link: http://zzzzzzzzzzzzz.xxxxxxxxx.de/index.php?id=10&tx_ttnews%5Btt_news%5D=1&cHash=66f5af320da29b7ae1cda49047ca7358)';
		$returnString = \TYPO3\CMS\Core\Utility\MailUtility::breakLinesForEmail($str);
		$this->assertEquals($returnString, 'Mein Link auf eine News (Link:' . LF . 'http://zzzzzzzzzzzzz.xxxxxxxxx.de/index.php?id=10&tx_ttnews%5Btt_news%5D=1&cHash=66f5af320da29b7ae1cda49047ca7358)');
	}

	/**
	 * Data provider for parseAddressesTest
	 *
	 * @return array Data sets
	 */
	public function parseAddressesProvider() {
		return array(
			'name &ltemail&gt;' => array('name <email@example.org>', array('email@example.org' => 'name')),
			'&lt;email&gt;' => array('<email@example.org>', array('email@example.org')),
			'@localhost' => array('@localhost', array()),
			'000@example.com' => array('000@example.com', array('000@example.com')),
			'email' => array('email@example.org', array('email@example.org')),
			'email1,email2' => array('email1@example.org,email2@example.com', array('email1@example.org', 'email2@example.com')),
			'name &ltemail&gt;,email2' => array('name <email1@example.org>,email2@example.com', array('email1@example.org' => 'name', 'email2@example.com')),
			'"last, first" &lt;name@example.org&gt;' => array('"last, first" <email@example.org>', array('email@example.org' => '"last, first"')),
			'email,name &ltemail&gt;,"last, first" &lt;name@example.org&gt;' => array(
				'email1@example.org, name <email2@example.org>, "last, first" <email3@example.org>',
				array(
					'email1@example.org',
					'email2@example.org' => 'name',
					'email3@example.org' => '"last, first"'
				)
			)
		);
	}

	/**
	 * @test
	 * @dataProvider parseAddressesProvider
	 */
	public function parseAddressesTest($source, $addressList) {
		$returnArray = \TYPO3\CMS\Core\Utility\MailUtility::parseAddresses($source);
		$this->assertEquals($addressList, $returnArray);
	}

}
