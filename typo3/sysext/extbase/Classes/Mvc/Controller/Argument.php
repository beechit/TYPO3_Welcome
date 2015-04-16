<?php
namespace TYPO3\CMS\Extbase\Mvc\Controller;

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

use TYPO3\CMS\Extbase\Utility\TypeHandlingUtility;

/**
 * A controller argument
 *
 * @api
 */
class Argument {

	/**
	 * @var \TYPO3\CMS\Extbase\Property\PropertyMapper
	 * @inject
	 */
	protected $propertyMapper;

	/**
	 * @var \TYPO3\CMS\Extbase\Mvc\Controller\MvcPropertyMappingConfiguration
	 * @inject
	 */
	protected $propertyMappingConfiguration;

	/**
	 * Name of this argument
	 *
	 * @var string
	 */
	protected $name = '';

	/**
	 * Short name of this argument
	 *
	 * @var string
	 */
	protected $shortName = NULL;

	/**
	 * Data type of this argument's value
	 *
	 * @var string
	 */
	protected $dataType = NULL;

	/**
	 * TRUE if this argument is required
	 *
	 * @var bool
	 */
	protected $isRequired = FALSE;

	/**
	 * Actual value of this argument
	 *
	 * @var mixed
	 */
	protected $value = NULL;

	/**
	 * Default value. Used if argument is optional.
	 *
	 * @var mixed
	 */
	protected $defaultValue = NULL;

	/**
	 * A custom validator, used supplementary to the base validation
	 *
	 * @var \TYPO3\CMS\Extbase\Validation\Validator\ValidatorInterface
	 */
	protected $validator = NULL;

	/**
	 * The validation results. This can be asked if the argument has errors.
	 *
	 * @var \TYPO3\CMS\Extbase\Error\Result
	 */
	protected $validationResults = NULL;

	/**
	 * Constructs this controller argument
	 *
	 * @param string $name Name of this argument
	 * @param string $dataType The data type of this argument
	 * @throws \InvalidArgumentException if $name is not a string or empty
	 * @api
	 */
	public function __construct($name, $dataType) {
		if (!is_string($name)) {
			throw new \InvalidArgumentException('$name must be of type string, ' . gettype($name) . ' given.', 1187951688);
		}
		if ($name === '') {
			throw new \InvalidArgumentException('$name must be a non-empty string.', 1232551853);
		}
		$this->name = $name;
		$this->dataType = TypeHandlingUtility::normalizeType($dataType);
	}

	/**
	 * Returns the name of this argument
	 *
	 * @return string This argument's name
	 * @api
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * Sets the short name of this argument.
	 *
	 * @param string $shortName A "short name" - a single character
	 * @throws \InvalidArgumentException if $shortName is not a character
	 * @return \TYPO3\CMS\Extbase\Mvc\Controller\Argument $this
	 * @api
	 */
	public function setShortName($shortName) {
		if ($shortName !== NULL && (!is_string($shortName) || strlen($shortName) !== 1)) {
			throw new \InvalidArgumentException('$shortName must be a single character or NULL', 1195824959);
		}
		$this->shortName = $shortName;
		return $this;
	}

	/**
	 * Returns the short name of this argument
	 *
	 * @return string This argument's short name
	 * @api
	 */
	public function getShortName() {
		return $this->shortName;
	}

	/**
	 * Returns the data type of this argument's value
	 *
	 * @return string The data type
	 * @api
	 */
	public function getDataType() {
		return $this->dataType;
	}

	/**
	 * Marks this argument to be required
	 *
	 * @param bool $required TRUE if this argument should be required
	 * @return \TYPO3\CMS\Extbase\Mvc\Controller\Argument $this
	 * @api
	 */
	public function setRequired($required) {
		$this->isRequired = (bool)$required;
		return $this;
	}

	/**
	 * Returns TRUE if this argument is required
	 *
	 * @return bool TRUE if this argument is required
	 * @api
	 */
	public function isRequired() {
		return $this->isRequired;
	}

	/**
	 * Sets the default value of the argument
	 *
	 * @param mixed $defaultValue Default value
	 * @return \TYPO3\CMS\Extbase\Mvc\Controller\Argument $this
	 * @api
	 */
	public function setDefaultValue($defaultValue) {
		$this->defaultValue = $defaultValue;
		return $this;
	}

	/**
	 * Returns the default value of this argument
	 *
	 * @return mixed The default value
	 * @api
	 */
	public function getDefaultValue() {
		return $this->defaultValue;
	}

	/**
	 * Sets a custom validator which is used supplementary to the base validation
	 *
	 * @param \TYPO3\CMS\Extbase\Validation\Validator\ValidatorInterface $validator The actual validator object
	 * @return \TYPO3\CMS\Extbase\Mvc\Controller\Argument Returns $this (used for fluent interface)
	 * @api
	 */
	public function setValidator(\TYPO3\CMS\Extbase\Validation\Validator\ValidatorInterface $validator) {
		$this->validator = $validator;
		return $this;
	}

	/**
	 * Returns the set validator
	 *
	 * @return \TYPO3\CMS\Extbase\Validation\Validator\ValidatorInterface The set validator, NULL if none was set
	 * @api
	 */
	public function getValidator() {
		return $this->validator;
	}

	/**
	 * Sets the value of this argument.
	 *
	 * @param mixed $rawValue The value of this argument
	 * @return \TYPO3\CMS\Extbase\Mvc\Controller\Argument
	 * @throws \TYPO3\CMS\Extbase\Mvc\Exception\InvalidArgumentValueException if the argument is not a valid object of type $dataType
	 */
	public function setValue($rawValue) {
		if ($rawValue === NULL) {
			$this->value = NULL;
			return $this;
		}
		if (is_object($rawValue) && $rawValue instanceof $this->dataType) {
			$this->value = $rawValue;
			return $this;
		}
		$this->value = $this->propertyMapper->convert($rawValue, $this->dataType, $this->propertyMappingConfiguration);
		$this->validationResults = $this->propertyMapper->getMessages();
		if ($this->validator !== NULL) {
			// @todo Validation API has also changed!!!
			$validationMessages = $this->validator->validate($this->value);
			$this->validationResults->merge($validationMessages);
		}
		return $this;
	}

	/**
	 * Returns the value of this argument
	 *
	 * @return mixed The value of this argument - if none was set, NULL is returned
	 * @api
	 */
	public function getValue() {
		if ($this->value === NULL) {
			return $this->defaultValue;
		} else {
			return $this->value;
		}
	}

	/**
	 * Return the Property Mapping Configuration used for this argument; can be used by the initialize*action to modify the Property Mapping.
	 *
	 * @return \TYPO3\CMS\Extbase\Mvc\Controller\MvcPropertyMappingConfiguration
	 * @api
	 */
	public function getPropertyMappingConfiguration() {
		return $this->propertyMappingConfiguration;
	}

	/**
	 * @return bool TRUE if the argument is valid, FALSE otherwise
	 * @api
	 */
	public function isValid() {
		return !$this->validationResults->hasErrors();
	}

	/**
	 * @return \TYPO3\CMS\Extbase\Error\Result Validation errors which have occurred.
	 * @api
	 */
	public function getValidationResults() {
		return $this->validationResults;
	}

	/**
	 * Returns a string representation of this argument's value
	 *
	 * @return string
	 * @api
	 */
	public function __toString() {
		return (string)$this->value;
	}

}
