<?php
namespace TYPO3\CMS\Extbase\Tests\Unit\Validation\Validator;

/*                                                                        *
 * This script belongs to the Extbase framework.                          *
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

/**
 * Test case
 */
class RegularExpressionValidatorTest extends \TYPO3\CMS\Core\Tests\UnitTestCase {

	protected $validatorClassName = \TYPO3\CMS\Extbase\Validation\Validator\RegularExpressionValidator::class;

	/**
	 * @test
	 * @author Andreas Förthner <andreas.foerthner@netlogix.de>
	 */
	public function regularExpressionValidatorMatchesABasicExpressionCorrectly() {
		$options = array('regularExpression' => '/^simple[0-9]expression$/');
		$validator = $this->getMock($this->validatorClassName, array('translateErrorMessage'), array($options));
		$this->assertFalse($validator->validate('simple1expression')->hasErrors());
		$this->assertTrue($validator->validate('simple1expressions')->hasErrors());
	}

	/**
	 * @test
	 * @author Andreas Förthner <andreas.foerthner@netlogix.de>
	 */
	public function regularExpressionValidatorCreatesTheCorrectErrorIfTheExpressionDidNotMatch() {
		$options = array('regularExpression' => '/^simple[0-9]expression$/');
		$validator = $this->getMock($this->validatorClassName, array('translateErrorMessage'), array($options));
		$errors = $validator->validate('some subject that will not match')->getErrors();
		// we only test for the error code, after the translation Method for message is mocked anyway
		$this->assertEquals(array(new \TYPO3\CMS\Extbase\Validation\Error(NULL, 1221565130)), $errors);
	}

}
