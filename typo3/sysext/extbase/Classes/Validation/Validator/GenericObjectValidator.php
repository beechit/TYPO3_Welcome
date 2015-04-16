<?php
namespace TYPO3\CMS\Extbase\Validation\Validator;

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

use TYPO3\CMS\Extbase\Reflection\ObjectAccess;

/**
 * A generic object validator which allows for specifying property validators
 */
class GenericObjectValidator extends AbstractValidator implements ObjectValidatorInterface {

	/**
	 * @var \SplObjectStorage[]
	 */
	protected $propertyValidators = array();

	/**
	 * Checks if the given value is valid according to the validator, and returns
	 * the Error Messages object which occurred.
	 *
	 * @param mixed $value The value that should be validated
	 * @return \TYPO3\CMS\Extbase\Error\Result
	 * @api
	 */
	public function validate($value) {
		$this->result = new \TYPO3\CMS\Extbase\Error\Result();
		if ($this->acceptsEmptyValues === FALSE || $this->isEmpty($value) === FALSE) {
			if (!is_object($value)) {
				$this->addError('Object expected, %1$s given.', 1241099149, array(gettype($value)));
			} elseif ($this->isValidatedAlready($value) === FALSE) {
				$this->isValid($value);
			}
		}

		return $this->result;
	}

	/**
	 * Load the property value to be used for validation.
	 *
	 * In case the object is a doctrine proxy, we need to load the real instance first.
	 *
	 * @param object $object
	 * @param string $propertyName
	 * @return mixed
	 */
	protected function getPropertyValue($object, $propertyName) {
		// @todo add support for lazy loading proxies, if needed
		if (ObjectAccess::isPropertyGettable($object, $propertyName)) {
			return ObjectAccess::getProperty($object, $propertyName);
		} else {
			return ObjectAccess::getProperty($object, $propertyName, TRUE);
		}
	}

	/**
	 * Checks if the specified property of the given object is valid, and adds
	 * found errors to the $messages object.
	 *
	 * @param mixed $value The value to be validated
	 * @param \Traversable $validators The validators to be called on the value
	 * @param string $propertyName Name of ther property to check
	 * @return void
	 */
	protected function checkProperty($value, $validators, $propertyName) {
		/** @var \TYPO3\CMS\Extbase\Error\Result $result */
		$result = NULL;
		foreach ($validators as $validator) {
			if ($validator instanceof ObjectValidatorInterface) {
				$validator->setValidatedInstancesContainer($this->validatedInstancesContainer);
			}
			$currentResult = $validator->validate($value);
			if ($currentResult->hasMessages()) {
				if ($result == NULL) {
					$result = $currentResult;
				} else {
					$result->merge($currentResult);
				}
			}
		}
		if ($result != NULL) {
			$this->result->forProperty($propertyName)->merge($result);
		}
	}

	/**
	 * Checks if the given value is valid according to the property validators.
	 *
	 * @param mixed $object The value that should be validated
	 * @return void
	 * @api
	 */
	protected function isValid($object) {
		foreach ($this->propertyValidators as $propertyName => $validators) {
			$propertyValue = $this->getPropertyValue($object, $propertyName);
			$this->checkProperty($propertyValue, $validators, $propertyName);
		}
	}

	/**
	 * Checks the given object can be validated by the validator implementation
	 *
	 * @param mixed $object The object to be checked
	 * @return bool TRUE if the given value is an object
	 * @api
	 */
	public function canValidate($object) {
		return is_object($object);
	}

	/**
	 * Adds the given validator for validation of the specified property.
	 *
	 * @param string $propertyName Name of the property to validate
	 * @param ValidatorInterface $validator The property validator
	 * @return void
	 * @api
	 */
	public function addPropertyValidator($propertyName, ValidatorInterface $validator) {
		if (!isset($this->propertyValidators[$propertyName])) {
			$this->propertyValidators[$propertyName] = new \SplObjectStorage();
		}
		$this->propertyValidators[$propertyName]->attach($validator);
	}

	/**
	 * @param object $object
	 * @return bool
	 */
	protected function isValidatedAlready($object) {
		if ($this->validatedInstancesContainer === NULL) {
			$this->validatedInstancesContainer = new \SplObjectStorage();
		}
		if ($this->validatedInstancesContainer->contains($object)) {
			return TRUE;
		} else {
			$this->validatedInstancesContainer->attach($object);

			return FALSE;
		}
	}

	/**
	 * Returns all property validators - or only validators of the specified property
	 *
	 * @param string $propertyName Name of the property to return validators for
	 * @return array An array of validators
	 */
	public function getPropertyValidators($propertyName = NULL) {
		if ($propertyName !== NULL) {
			return (isset($this->propertyValidators[$propertyName])) ? $this->propertyValidators[$propertyName] : array();
		} else {
			return $this->propertyValidators;
		}
	}

	/**
	 * @var \SplObjectStorage
	 */
	protected $validatedInstancesContainer;

	/**
	 * Allows to set a container to keep track of validated instances.
	 *
	 * @param \SplObjectStorage $validatedInstancesContainer A container to keep track of validated instances
	 * @return void
	 * @api
	 */
	public function setValidatedInstancesContainer(\SplObjectStorage $validatedInstancesContainer) {
		$this->validatedInstancesContainer = $validatedInstancesContainer;
	}

}
