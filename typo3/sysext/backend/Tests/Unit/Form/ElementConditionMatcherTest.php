<?php
namespace TYPO3\CMS\Backend\Tests\Unit\Form\Element;

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
class ElementConditionMatcherTest extends \TYPO3\CMS\Core\Tests\UnitTestCase {

	/**
	 * @var \TYPO3\CMS\Backend\Form\ElementConditionMatcher
	 */
	protected $subject;

	/**
	 * Sets up this test case.
	 */
	protected function setUp() {
		$this->subject = new \TYPO3\CMS\Backend\Form\ElementConditionMatcher();
	}

	/**
	 * Returns data sets for the test matchConditionStrings
	 * Each dataset is an array with the following elements:
	 * - the condition string
	 * - the current record
	 * - the current flexform value key
	 * - the expected result
	 *
	 * @return array
	 */
	public function conditionStringDataProvider() {
		return array(
			'Invalid condition string' => array(
				'xINVALIDx:',
				array(),
				NULL,
				FALSE,
			),
			'Not loaded extension compares to loaded as FALSE' => array(
				'EXT:neverloadedext:LOADED:TRUE',
				array(),
				NULL,
				FALSE,
			),
			'Not loaded extension compares to not loaded as TRUE' => array(
				'EXT:neverloadedext:LOADED:FALSE',
				array(),
				NULL,
				TRUE,
			),
			'Loaded extension compares to TRUE' => array(
				'EXT:backend:LOADED:TRUE',
				array(),
				NULL,
				TRUE,
			),
			'Loaded extension compares to FALSE' => array(
				'EXT:backend:LOADED:FALSE',
				array(),
				NULL,
				FALSE,
			),
			'Field is not greater zero if not given' => array(
				'FIELD:uid:>:0',
				array(),
				NULL,
				FALSE,
			),
			'Field is not equal 0 if not given' => array(
				'FIELD:uid:=:0',
				array(),
				NULL,
				FALSE,
			),
			'Field value string comparison' => array(
				'FIELD:foo:=:bar',
				array('foo' => 'bar'),
				NULL,
				TRUE,
			),
			'Field value comparison of 1 against multi-value field of 5 returns true' => array(
				'FIELD:content:BIT:1',
				array('content' => '5'),
				NULL,
				TRUE
			),
			'Field value comparison of 2 against multi-value field of 5 returns false' => array(
				'FIELD:content:BIT:2',
				array('content' => '5'),
				NULL,
				FALSE
			),
			'Field value of 5 negated comparison against multi-value field of 5 returns false' => array(
				'FIELD:content:!BIT:5',
				array('content' => '5'),
				NULL,
				FALSE
			),
			'Field value comparison for required value is false for different value' => array(
				'FIELD:foo:REQ:FALSE',
				array('foo' => 'bar'),
				NULL,
				FALSE,
			),
			'Field value string not equal comparison' => array(
				'FIELD:foo:!=:baz',
				array('foo' => 'bar'),
				NULL,
				TRUE,
			),
			'Field value in range' => array(
				'FIELD:uid:-:3-42',
				array('uid' => '23'),
				NULL,
				TRUE,
			),
			'Field value greater than' => array(
				'FIELD:uid:>=:42',
				array('uid' => '23'),
				NULL,
				FALSE,
			),
			'Flexform value invalid comparison' => array(
				'FIELD:foo:=:bar',
				array(
					'foo' => array(
						'vDEF' => 'bar'
					),
				),
				'vDEF',
				TRUE,
			),
			'Flexform value valid comparison' => array(
				'FIELD:parentRec.foo:=:bar',
				array(
					'parentRec' => array(
						'foo' => 'bar'
					),
				),
				'vDEF',
				TRUE,
			),
			'Field is value for default language without flexform' => array(
				'HIDE_L10N_SIBLINGS',
				array(),
				NULL,
				FALSE,
			),
			'Field is value for default language with flexform' => array(
				'HIDE_L10N_SIBLINGS',
				array(),
				'vDEF',
				TRUE,
			),
			'Field is value for default language with sibling' => array(
				'HIDE_L10N_SIBLINGS',
				array(),
				'vEN',
				FALSE,
			),
			'New is TRUE for new comparison with TRUE' => array(
				'REC:NEW:TRUE',
				array('uid' => NULL),
				NULL,
				TRUE,
			),
			'New is FALSE for new comparison with FALSE' => array(
				'REC:NEW:FALSE',
				array('uid' => NULL),
				NULL,
				FALSE,
			),
			'New is FALSE for not new element' => array(
				'REC:NEW:TRUE',
				array('uid' => 42),
				NULL,
				FALSE,
			),
			'New is TRUE for not new element compared to FALSE' => array(
				'REC:NEW:FALSE',
				array('uid' => 42),
				NULL,
				TRUE,
			),
			'Version is TRUE for versioned row' => array(
				'VERSION:IS:TRUE',
				array(
					'uid' => 42,
					'pid' => -1
				),
				NULL,
				TRUE,
			),
			'Version is TRUE for not versioned row compared with FALSE' => array(
				'VERSION:IS:FALSE',
				array(
					'uid' => 42,
					'pid' => 1
				),
				NULL,
				TRUE,
			),
			'Version is TRUE for NULL row compared with TRUE' => array(
				'VERSION:IS:TRUE',
				array(
					'uid' => NULL,
					'pid' => NULL,
				),
				NULL,
				FALSE,
			),
			'Multiple conditions with AND compare to TRUE if all are OK' => array(
				array(
					'AND' => array(
						'FIELD:testField:>:9',
						'FIELD:testField:<:11',
					),
				),
				array(
					'testField' => 10
				),
				NULL,
				TRUE,
			),
			'Multiple conditions with AND compare to FALSE if one fails' => array(
				array(
					'AND' => array(
						'FIELD:testField:>:9',
						'FIELD:testField:<:11',
					)
				),
				array(
					'testField' => 99
				),
				NULL,
				FALSE,
			),
			'Multiple conditions with OR compare to TRUE if one is OK' => array(
				array(
					'OR' => array(
						'FIELD:testField:<:9',
						'FIELD:testField:<:11',
					),
				),
				array(
					'testField' => 10
				),
				NULL,
				TRUE,
			),
			'Multiple conditions with OR compare to FALSE is all fail' => array(
				array(
					'OR' => array(
						'FIELD:testField:<:9',
						'FIELD:testField:<:11',
					),
				),
				array(
					'testField' => 99
				),
				NULL,
				FALSE,
			),
			'Multiple conditions without operator due to misconfiguration compare to TRUE' => array(
				array(
					'' => array(
						'FIELD:testField:<:9',
						'FIELD:testField:>:11',
					)
				),
				array(
					'testField' => 99
				),
				NULL,
				TRUE,
			),
			'Multiple nested conditions evaluate to TRUE' => array(
				array(
					'AND' => array(
						'FIELD:testField:>:9',
						'OR' => array(
							'FIELD:testField:<:100',
							'FIELD:testField:>:-100',
						),
					),
				),
				array(
					'testField' => 10
				),
				NULL,
				TRUE,
			),
			'Multiple nested conditions evaluate to FALSE' => array(
				array(
					'AND' => array(
						'FIELD:testField:>:9',
						'OR' => array(
							'FIELD:testField:<:100',
							'FIELD:testField:>:-100',
						),
					),
				),
				array(
					'testField' => -999
				),
				NULL,
				FALSE,
			),
		);
	}

	/**
	 * @param string $condition
	 * @param array $record
	 * @param string $flexformValueKey
	 * @param string $expectedResult
	 * @dataProvider conditionStringDataProvider
	 * @test
	 */
	public function matchConditionStrings($condition, array $record, $flexformValueKey, $expectedResult) {
		$this->assertEquals($expectedResult, $this->subject->match($condition, $record, $flexformValueKey));
	}

	/**
	 * @test
	 */
	public function matchHideForNonAdminsReturnsTrueIfBackendUserIsAdmin() {
		/** @var $backendUserMock \TYPO3\CMS\Core\Authentication\BackendUserAuthentication|\PHPUnit_Framework_MockObject_MockObject */
		$backendUserMock = $this->getMock(\TYPO3\CMS\Core\Authentication\BackendUserAuthentication::class);
		$backendUserMock
			->expects($this->once())
			->method('isAdmin')
			->will($this->returnValue(TRUE));
		$GLOBALS['BE_USER'] = $backendUserMock;
		$this->assertTrue($this->subject->match('HIDE_FOR_NON_ADMINS'));
	}

	/**
	 * @test
	 */
	public function matchHideForNonAdminsReturnsFalseIfBackendUserIsNotAdmin() {
		/** @var $backendUserMock \TYPO3\CMS\Core\Authentication\BackendUserAuthentication|\PHPUnit_Framework_MockObject_MockObject */
		$backendUserMock = $this->getMock(\TYPO3\CMS\Core\Authentication\BackendUserAuthentication::class);
		$backendUserMock
			->expects($this->once())
			->method('isAdmin')
			->will($this->returnValue(FALSE));
		$GLOBALS['BE_USER'] = $backendUserMock;
		$this->assertFalse($this->subject->match('HIDE_FOR_NON_ADMINS'));
	}

	/**
	 * @test
	 */
	public function matchHideL10NSiblingsExceptAdminReturnsTrueIfBackendUserIsAdmin() {
		/** @var $backendUserMock \TYPO3\CMS\Core\Authentication\BackendUserAuthentication|\PHPUnit_Framework_MockObject_MockObject */
		$backendUserMock = $this->getMock(\TYPO3\CMS\Core\Authentication\BackendUserAuthentication::class);
		$backendUserMock
			->expects($this->once())
			->method('isAdmin')
			->will($this->returnValue(TRUE));
		$GLOBALS['BE_USER'] = $backendUserMock;
		$this->assertTrue($this->subject->match('HIDE_L10N_SIBLINGS:except_admin'), array(), 'vEN');
	}

	/**
	 * @test
	 */
	public function matchHideL10NSiblingsExceptAdminReturnsFalseIfBackendUserIsNotAdmin() {
		/** @var $backendUserMock \TYPO3\CMS\Core\Authentication\BackendUserAuthentication|\PHPUnit_Framework_MockObject_MockObject */
		$backendUserMock = $this->getMock(\TYPO3\CMS\Core\Authentication\BackendUserAuthentication::class);
		$backendUserMock
			->expects($this->once())
			->method('isAdmin')
			->will($this->returnValue(FALSE));
		$GLOBALS['BE_USER'] = $backendUserMock;
		$this->assertFalse($this->subject->match('HIDE_L10N_SIBLINGS:except_admin'), array(), 'vEN');
	}

}
