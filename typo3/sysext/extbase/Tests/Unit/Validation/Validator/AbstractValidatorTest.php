<?php
namespace TYPO3\CMS\Extbase\Tests\Unit\Validation\Validator;

/*                                                                        *
 * This script belongs to the Extbase framework.                            *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License as published by the *
 * Free Software Foundation, either version 3 of the License, or (at your *
 * option) any later version.                                             *
 *                                                                        *
 * This script is distributed in the hope that it will be useful, but     *
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHAN-    *
 * TABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Lesser       *
 * General Public License for more details.                               *
 *                                                                        *
 * You should have received a copy of the GNU Lesser General Public       *
 * License along with the script.                                         *
 * If not, see http://www.gnu.org/licenses/lgpl.html                      *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use TYPO3\CMS\Core\Tests\UnitTestCase;

/**
 * Testcase for the abstract base-class of validators
 *
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 */
class AbstractValidatorTest extends UnitTestCase {

	/**
	 * @test
	 */
	public function validatorAcceptsSupportedOptions() {
		$inputOptions = array(
			'requiredOption' => 666,
			'demoOption' => 42
		);
		$expectedOptions = $inputOptions;
		$validator = $this->getAccessibleMock(\TYPO3\CMS\Extbase\Tests\Unit\Validation\Validator\Fixture\AbstractValidatorClass::class, array('dummy'), array($inputOptions));
		$this->assertSame($expectedOptions, $validator->_get('options'));
	}

	/**
	 * @test
	 */
	public function validatorHasDefaultOptions() {
		$inputOptions = array('requiredOption' => 666);
		$expectedOptions = array(
			'requiredOption' => 666,
			'demoOption' => PHP_INT_MAX
		);
		$validator = $this->getAccessibleMock(\TYPO3\CMS\Extbase\Tests\Unit\Validation\Validator\Fixture\AbstractValidatorClass::class, array('dummy'), array($inputOptions));
		$this->assertSame($expectedOptions, $validator->_get('options'));
	}

	/**
	 * @test
	 */
	public function validatorThrowsExceptionOnNotSupportedOptions() {
		$inputOptions = array('invalidoption' => 42);
		$this->setExpectedException(\TYPO3\CMS\Extbase\Validation\Exception\InvalidValidationOptionsException::class, '', 1379981890);
		$validator = $this->getAccessibleMock(\TYPO3\CMS\Extbase\Tests\Unit\Validation\Validator\Fixture\AbstractValidatorClass::class, array('dummy'), array($inputOptions));
	}


	/**
	 * @test
	 */
	public function validatorThrowsExceptionOnMissingRequiredOptions() {
		$inputOptions = array();
		$this->setExpectedException(\TYPO3\CMS\Extbase\Validation\Exception\InvalidValidationOptionsException::class, '', 1379981891);
		$validator = $this->getAccessibleMock(\TYPO3\CMS\Extbase\Tests\Unit\Validation\Validator\Fixture\AbstractValidatorClass::class, array('dummy'), array($inputOptions));
	}

}

