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
 * Testcase for class \TYPO3\CMS\Core\Utility\MathUtility
 *
 * @author Susanne Moog <typo3@susanne-moog.de>
 */
class MathUtilityTest extends \TYPO3\CMS\Core\Tests\UnitTestCase {

	//////////////////////////////////
	// Tests concerning forceIntegerInRange
	//////////////////////////////////
	/**
	 * Data provider for forceIntegerInRangeForcesIntegerIntoBoundaries
	 *
	 * @return array expected values, arithmetic expression
	 */
	public function forceIntegerInRangeForcesIntegerIntoDefaultBoundariesDataProvider() {
		return array(
			'negativeValue' => array(0, -10),
			'normalValue' => array(30, 30),
			'veryHighValue' => array(2000000000, PHP_INT_MAX),
			'zeroValue' => array(0, 0),
			'anotherNormalValue' => array(12309, 12309)
		);
	}

	/**
	 * @test
	 * @dataProvider forceIntegerInRangeForcesIntegerIntoDefaultBoundariesDataProvider
	 */
	public function forceIntegerInRangeForcesIntegerIntoDefaultBoundaries($expected, $value) {
		$this->assertEquals($expected, \TYPO3\CMS\Core\Utility\MathUtility::forceIntegerInRange($value, 0));
	}

	/**
	 * @test
	 */
	public function forceIntegerInRangeSetsDefaultValueIfZeroValueIsGiven() {
		$this->assertEquals(42, \TYPO3\CMS\Core\Utility\MathUtility::forceIntegerInRange('', 0, 2000000000, 42));
	}

	//////////////////////////////////
	// Tests concerning convertToPositiveInteger
	//////////////////////////////////
	/**
	 * @test
	 */
	public function convertToPositiveIntegerReturnsZeroForNegativeValues() {
		$this->assertEquals(0, \TYPO3\CMS\Core\Utility\MathUtility::convertToPositiveInteger(-123));
	}

	/**
	 * @test
	 */
	public function convertToPositiveIntegerReturnsTheInputValueForPositiveValues() {
		$this->assertEquals(123, \TYPO3\CMS\Core\Utility\MathUtility::convertToPositiveInteger(123));
	}

	///////////////////////////////
	// Tests concerning canBeInterpretedAsInteger
	///////////////////////////////
	/**
	 * Data provider for canBeInterpretedAsIntegerReturnsTrue
	 *
	 * @return array Data sets
	 */
	public function functionCanBeInterpretedAsIntegerValidDataProvider() {
		return array(
			'int' => array(32425),
			'negative int' => array(-32425),
			'largest int' => array(PHP_INT_MAX),
			'int as string' => array('32425'),
			'negative int as string' => array('-32425'),
			'zero' => array(0),
			'zero as string' => array('0')
		);
	}

	/**
	 * @test
	 * @dataProvider functionCanBeInterpretedAsIntegerValidDataProvider
	 */
	public function canBeInterpretedAsIntegerReturnsTrue($int) {
		$this->assertTrue(\TYPO3\CMS\Core\Utility\MathUtility::canBeInterpretedAsInteger($int));
	}

	/**
	 * Data provider for canBeInterpretedAsIntegerReturnsFalse
	 *
	 * @return array Data sets
	 */
	public function functionCanBeInterpretedAsIntegerInvalidDataProvider() {
		$objectWithNumericalStringRepresentation = new \TYPO3\CMS\Core\Tests\Unit\Utility\Fixtures\MathUtilityTestClassWithStringRepresentationFixture();
		$objectWithNumericalStringRepresentation->setString('1234');
		$objectWithNonNumericalStringRepresentation = new \TYPO3\CMS\Core\Tests\Unit\Utility\Fixtures\MathUtilityTestClassWithStringRepresentationFixture();
		$objectWithNonNumericalStringRepresentation->setString('foo');
		$objectWithEmptyStringRepresentation = new \TYPO3\CMS\Core\Tests\Unit\Utility\Fixtures\MathUtilityTestClassWithStringRepresentationFixture();
		$objectWithEmptyStringRepresentation->setString('');
		return array(
			'int as string with leading zero' => array('01234'),
			'positive int as string with plus modifier' => array('+1234'),
			'negative int as string with leading zero' => array('-01234'),
			'largest int plus one' => array(PHP_INT_MAX + 1),
			'string' => array('testInt'),
			'empty string' => array(''),
			'int in string' => array('5 times of testInt'),
			'int as string with space after' => array('5 '),
			'int as string with space before' => array(' 5'),
			'int as string with many spaces before' => array('     5'),
			'float' => array(3.14159),
			'float as string' => array('3.14159'),
			'float as string only a dot' => array('10.'),
			'float as string trailing zero would evaluate to int 10' => array('10.0'),
			'float as string trailing zeros	 would evaluate to int 10' => array('10.00'),
			'null' => array(NULL),
			'empty array' => array(array()),
			'int in array' => array(array(32425)),
			'int as string in array' => array(array('32425')),
			'object without string representation' => array(new \stdClass()),
			'object with numerical string representation' => array($objectWithNumericalStringRepresentation),
			'object without numerical string representation' => array($objectWithNonNumericalStringRepresentation),
			'object with empty string representation' => array($objectWithEmptyStringRepresentation)
		);
	}

	/**
	 * @test
	 * @dataProvider functionCanBeInterpretedAsIntegerInvalidDataProvider
	 */
	public function canBeInterpretedAsIntegerReturnsFalse($int) {
		$this->assertFalse(\TYPO3\CMS\Core\Utility\MathUtility::canBeInterpretedAsInteger($int));
	}

	///////////////////////////////
	// Tests concerning canBeInterpretedAsFloat
	///////////////////////////////
	/**
	 * Data provider for canBeInterpretedAsFloatReturnsTrue
	 *
	 * @return array Data sets
	 */
	public function functionCanBeInterpretedAsFloatValidDataProvider() {
		// testcases for Integer apply for float as well
		$intTestcases = $this->functionCanBeInterpretedAsIntegerValidDataProvider();
		$floatTestcases = array(
			'zero as float' => array((float) 0),
			'negative float' => array((float) -7.5),
			'negative float as string with exp #1' => array('-7.5e3'),
			'negative float as string with exp #2' => array('-7.5e03'),
			'negative float as string with exp #3' => array('-7.5e-3'),
			'float' => array(3.14159),
			'float as string' => array('3.14159'),
			'float as string only a dot' => array('10.'),
			'float as string trailing zero' => array('10.0'),
			'float as string trailing zeros' => array('10.00'),
		);
		return array_merge($intTestcases, $floatTestcases);
	}

	/**
	 * @test
	 * @dataProvider functionCanBeInterpretedAsFloatValidDataProvider
	 */
	public function canBeInterpretedAsFloatReturnsTrue($val) {
		$this->assertTrue(\TYPO3\CMS\Core\Utility\MathUtility::canBeInterpretedAsFloat($val));
	}

	/**
	 * Data provider for canBeInterpretedAsFloatReturnsFalse
	 *
	 * @return array Data sets
	 */
	public function functionCanBeInterpretedAsFloatInvalidDataProvider() {
		$objectWithNumericalStringRepresentation = new \TYPO3\CMS\Core\Tests\Unit\Utility\Fixtures\MathUtilityTestClassWithStringRepresentationFixture();
		$objectWithNumericalStringRepresentation->setString('1234');
		$objectWithNonNumericalStringRepresentation = new \TYPO3\CMS\Core\Tests\Unit\Utility\Fixtures\MathUtilityTestClassWithStringRepresentationFixture();
		$objectWithNonNumericalStringRepresentation->setString('foo');
		$objectWithEmptyStringRepresentation = new \TYPO3\CMS\Core\Tests\Unit\Utility\Fixtures\MathUtilityTestClassWithStringRepresentationFixture();
		$objectWithEmptyStringRepresentation->setString('');
		return array(
			// 'int as string with leading zero' => array('01234'),
			// 'positive int as string with plus modifier' => array('+1234'),
			// 'negative int as string with leading zero' => array('-01234'),
			// 'largest int plus one' => array(PHP_INT_MAX + 1),
			'string' => array('testInt'),
			'empty string' => array(''),
			'int in string' => array('5 times of testInt'),
			'int as string with space after' => array('5 '),
			'int as string with space before' => array(' 5'),
			'int as string with many spaces before' => array('     5'),
			'null' => array(NULL),
			'empty array' => array(array()),
			'int in array' => array(array(32425)),
			'int as string in array' => array(array('32425')),
			'negative float as string with invalid chars in exponent' => array('-7.5eX3'),
			'object without string representation' => array(new \stdClass()),
			'object with numerical string representation' => array($objectWithNumericalStringRepresentation),
			'object without numerical string representation' => array($objectWithNonNumericalStringRepresentation),
			'object with empty string representation' => array($objectWithEmptyStringRepresentation)
		);
	}

	/**
	 * @test
	 * @dataProvider functionCanBeInterpretedAsFloatInvalidDataProvider
	 */
	public function canBeInterpretedAsFloatReturnsFalse($int) {
		$this->assertFalse(\TYPO3\CMS\Core\Utility\MathUtility::canBeInterpretedAsFloat($int));
	}

	//////////////////////////////////
	// Tests concerning calculateWithPriorityToAdditionAndSubtraction
	//////////////////////////////////
	/**
	 * Data provider for calculateWithPriorityToAdditionAndSubtraction
	 *
	 * @return array expected values, arithmetic expression
	 */
	public function calculateWithPriorityToAdditionAndSubtractionDataProvider() {
		return array(
			'add' => array(9, '6 + 3'),
			'substract with positive result' => array(3, '6 - 3'),
			'substract with negative result' => array(-3, '3 - 6'),
			'multiply' => array(6, '2 * 3'),
			'divide' => array(2.5, '5 / 2'),
			'modulus' => array(1, '5 % 2'),
			'power' => array(8, '2 ^ 3'),
			'three operands with non integer result' => array(6.5, '5 + 3 / 2'),
			'three operands with power' => array(14, '5 + 3 ^ 2'),
			'three operads with modulus' => array(4, '5 % 2 + 3'),
			'four operands' => array(3, '2 + 6 / 2 - 2'),
			'division by zero when dividing' => array('ERROR: dividing by zero', '2 / 0'),
			'division by zero with modulus' => array('ERROR: dividing by zero', '2 % 0')
		);
	}

	/**
	 * @test
	 * @dataProvider calculateWithPriorityToAdditionAndSubtractionDataProvider
	 */
	public function calculateWithPriorityToAdditionAndSubtractionCorrectlyCalculatesExpression($expected, $expression) {
		$this->assertEquals($expected, \TYPO3\CMS\Core\Utility\MathUtility::calculateWithPriorityToAdditionAndSubtraction($expression));
	}

	//////////////////////////////////
	// Tests concerning calcParenthesis
	//////////////////////////////////
	/**
	 * Data provider for calcParenthesis
	 *
	 * @return array expected values, arithmetic expression
	 */
	public function calculateWithParenthesesDataProvider() {
		return array(
			'starts with parenthesis' => array(18, '(6 + 3) * 2'),
			'ends with parenthesis' => array(6, '2 * (6 - 3)'),
			'multiple parentheses' => array(-6, '(3 - 6) * (4 - 2)'),
			'nested parentheses' => array(22, '2 * (3 + 2 + (3 * 2))'),
			'parenthesis with division' => array(15, '5 / 2 * (3 * 2)')
		);
	}

	/**
	 * @test
	 * @dataProvider calculateWithParenthesesDataProvider
	 */
	public function calculateWithParenthesesCorrectlyCalculatesExpression($expected, $expression) {
		$this->assertEquals($expected, \TYPO3\CMS\Core\Utility\MathUtility::calculateWithParentheses($expression));
	}

	//////////////////////////////////
	// Tests concerning isIntegerInRange
	//////////////////////////////////
	/**
	 * @test
	 */
	public function isIntegerInRangeIncludesLowerBoundary() {
		$this->assertTrue(\TYPO3\CMS\Core\Utility\MathUtility::isIntegerInRange(1, 1, 2));
	}

	/**
	 * @test
	 */
	public function isIntegerInRangeIncludesUpperBoundary() {
		$this->assertTrue(\TYPO3\CMS\Core\Utility\MathUtility::isIntegerInRange(2, 1, 2));
	}

	/**
	 * @test
	 */
	public function isIntegerInRangeAcceptsValueInRange() {
		$this->assertTrue(\TYPO3\CMS\Core\Utility\MathUtility::isIntegerInRange(10, 1, 100));
	}

	/**
	 * @test
	 */
	public function isIntegerInRangeRejectsValueOutsideOfRange() {
		$this->assertFalse(\TYPO3\CMS\Core\Utility\MathUtility::isIntegerInRange(10, 1, 2));
	}

	/**
	 * Data provider or isIntegerInRangeRejectsOtherDataTypes
	 */
	public function isIntegerInRangeRejectsOtherDataTypesDataProvider() {
		return array(
			'negative integer' => array(-1),
			'float' => array(1.5),
			'string' => array('string'),
			'array' => array(array()),
			'object' => array(new \stdClass()),
			'boolean FALSE' => array(FALSE),
			'NULL' => array(NULL)
		);
	}

	/**
	 * @test
	 * @dataProvider isIntegerInRangeRejectsOtherDataTypesDataProvider
	 */
	public function isIntegerInRangeRejectsOtherDataTypes($inputValue) {
		$this->assertFalse(\TYPO3\CMS\Core\Utility\MathUtility::isIntegerInRange($inputValue, 0, 10));
	}

}
