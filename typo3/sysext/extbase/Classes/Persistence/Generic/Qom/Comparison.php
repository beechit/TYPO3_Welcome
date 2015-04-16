<?php
namespace TYPO3\CMS\Extbase\Persistence\Generic\Qom;

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

use TYPO3\CMS\Extbase\Persistence\QueryInterface;

/**
 * Filters node-tuples based on the outcome of a binary operation.
 *
 * For any comparison, operand2 always evaluates to a scalar value. In contrast,
 * operand1 may evaluate to an array of values (for example, the value of a multi-valued
 * property), in which case the comparison is separately performed for each element
 * of the array, and the Comparison constraint is satisfied as a whole if the
 * comparison against any element of the array is satisfied.
 *
 * If operand1 and operand2 evaluate to values of different property types, the
 * value of operand2 is converted to the property type of the value of operand1.
 * If the type conversion fails, the query is invalid.
 *
 * If operator is not supported for the property type of operand1, the query is invalid.
 *
 * If operand1 evaluates to null (for example, if the operand evaluates the value
 * of a property which does not exist), the constraint is not satisfied.
 *
 * The OPERATOR_EQUAL_TO operator is satisfied only if the value of operand1
 * equals the value of operand2.
 *
 * The OPERATOR_NOT_EQUAL_TO operator is satisfied unless the value of
 * operand1 equals the value of operand2.
 *
 * The OPERATOR_LESSS_THAN operator is satisfied only if the value of
 * operand1 is ordered before the value of operand2.
 *
 * The OPERATOR_LESS_THAN_OR_EQUAL_TO operator is satisfied unless the value
 * of operand1 is ordered after the value of operand2.
 *
 * The OPERATOR_GREATER_THAN operator is satisfied only if the value of
 * operand1 is ordered after the value of operand2.
 *
 * The OPERATOR_GREATER_THAN_OR_EQUAL_TO operator is satisfied unless the
 * value of operand1 is ordered before the value of operand2.
 *
 * The OPERATOR_LIKE operator is satisfied only if the value of operand1
 * matches the pattern specified by the value of operand2, where in the pattern:
 * the character "%" matches zero or more characters, and
 * the character "_" (underscore) matches exactly one character, and
 * the string "\x" matches the character "x", and
 * all other characters match themselves.
 */
class Comparison implements ComparisonInterface {

	/**
	 * @var PropertyValueInterface
	 */
	protected $operand1;

	/**
	 * @var int
	 */
	protected $operator;

	/**
	 * @var mixed
	 */
	protected $operand2;

	/**
	 * @var string
	 */
	protected $parameterIdentifier;

	/**
	 * Constructs this Comparison instance
	 *
	 * @param PropertyValueInterface $operand1
	 * @param int $operator one of QueryInterface::OPERATOR_*
	 * @param mixed $operand2
	 */
	public function __construct(PropertyValueInterface $operand1, $operator, $operand2) {
		$this->operand1 = $operand1;
		$this->operator = $operator;
		$this->operand2 = $operand2;
	}

	/**
	 * Gets the first operand.
	 *
	 * @return PropertyValueInterface the operand; non-null
	 */
	public function getOperand1() {
		return $this->operand1;
	}

	/**
	 * Gets the operator.
	 *
	 * @return string One of QueryInterface::OPERATOR_*
	 */
	public function getOperator() {
		$operator = $this->operator;

		if ($this->getOperand2() === NULL) {
			if ($operator === QueryInterface::OPERATOR_EQUAL_TO) {
				$operator = QueryInterface::OPERATOR_EQUAL_TO_NULL;
			} elseif ($operator === QueryInterface::OPERATOR_NOT_EQUAL_TO) {
				$operator = QueryInterface::OPERATOR_NOT_EQUAL_TO_NULL;
			}
		}

		return $operator;
	}

	/**
	 * Gets the second operand.
	 *
	 * @return mixed the operand; non-null
	 */
	public function getOperand2() {
		return $this->operand2;
	}

	/**
	 * @param string $parameterIdentifier
	 * @return void
	 */
	public function setParameterIdentifier($parameterIdentifier) {
		$this->parameterIdentifier = $parameterIdentifier;
	}

	/**
	 * @return string
	 */
	public function getParameterIdentifier() {
		return $this->parameterIdentifier;
	}

	/**
	 * Fills an array with the names of all bound variables in the constraints
	 *
	 * @param array &$boundVariables
	 * @return void
	 */
	public function collectBoundVariableNames(&$boundVariables) {
	}

}
