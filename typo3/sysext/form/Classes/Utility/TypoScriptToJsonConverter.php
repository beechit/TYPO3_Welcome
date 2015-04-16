<?php
namespace TYPO3\CMS\Form\Utility;

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

use TYPO3\CMS\Form\Domain\Model\Json\AbstractJsonElement;

/**
 * Typoscript to JSON converter
 *
 * Takes the incoming Typoscript and converts it to Json
 *
 * @author Patrick Broens <patrick@patrickbroens.nl>
 */
class TypoScriptToJsonConverter {

	/**
	 * @var array
	 */
	protected $validationRules;

	/**
	 * Convert TypoScript string to JSON
	 *
	 * @param array $typoscript TypoScript string containing all configuration for the form
	 * @return string The JSON for the form
	 */
	public function convert(array $typoscript) {
		$this->setValidationRules($typoscript);
		$jsonObject = $this->createElement('form', $typoscript);
		return $jsonObject;
	}

	/**
	 * Create element by loading class
	 * and instantiating the object
	 *
	 * @param string $class Type of element
	 * @param array $arguments Configuration array
	 * @return AbstractJsonElement
	 */
	public function createElement($class, array $arguments = array()) {
		$class = strtolower((string)$class);
		$className = 'TYPO3\\CMS\\Form\\Domain\\Model\Json\\' . ucfirst($class) . 'JsonElement';
		$this->addValidationRules($arguments);
		/** @var $object AbstractJsonElement */
		$object = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance($className);
		$object->setParameters($arguments);
		if ($object->childElementsAllowed()) {
			$this->getChildElementsByIntegerKey($object, $arguments);
		}
		return $object;
	}

	/**
	 * Rendering of a "numerical array" of Form objects from TypoScript
	 * Creates new object for each element found
	 *
	 * @param AbstractJsonElement $parentElement Parent model object
	 * @param array $typoscript Configuration array
	 * @return void
	 */
	protected function getChildElementsByIntegerKey(AbstractJsonElement $parentElement, array $typoscript) {
		if (is_array($typoscript)) {
			$keys = \TYPO3\CMS\Core\TypoScript\TemplateService::sortedKeyList($typoscript);
			foreach ($keys as $key) {
				$class = $typoscript[$key];
				if ((int)$key && strpos($key, '.') === FALSE) {
					if (isset($typoscript[$key . '.'])) {
						$elementArguments = $typoscript[$key . '.'];
					} else {
						$elementArguments = array();
					}
					$this->setElementType($parentElement, $class, $elementArguments);
				}
			}
		}
	}

	/**
	 * Set the element type of the object
	 *
	 * Checks if the typoscript object is part of the FORM or has a predefined
	 * class for name or header object
	 *
	 * @param AbstractJsonElement $parentElement The parent object
	 * @param string $class A predefined class
	 * @param array $arguments Configuration array
	 * @return void
	 */
	private function setElementType(AbstractJsonElement $parentElement, $class, array $arguments) {
		if (in_array($class, \TYPO3\CMS\Form\Utility\FormUtility::getInstance()->getFormObjects())) {
			if (strstr($arguments['class'], 'predefined-name')) {
				$class = 'NAME';
			}
			$this->addElement($parentElement, $class, $arguments);
		}
	}

	/**
	 * Add child object to this element
	 *
	 * @param AbstractJsonElement $parentElement The parent object
	 * @param string $class Type of element
	 * @param array $arguments Configuration array
	 * @return void
	 */
	public function addElement(AbstractJsonElement $parentElement, $class, array $arguments) {
		$element = $this->createElement($class, $arguments);
		$parentElement->addElement($element);
	}

	/**
	 * Set the validation rules
	 *
	 * @param array $typoscript Configuration array
	 * @return void
	 */
	protected function setValidationRules(array $typoscript) {
		if (isset($typoscript['rules.']) && is_array($typoscript['rules.'])) {
			$this->validationRules = $typoscript['rules.'];
		}
	}

	/**
	 * Add validation rules to an element if available
	 *
	 * In TypoScript the validation rules belong to the form and are connected
	 * to the elements by name. However, in the wizard, they are added to the
	 * element for usability
	 *
	 * @param array $arguments The element arguments
	 * @return void
	 */
	protected function addValidationRules(array &$arguments) {
		if (!empty($this->validationRules) && isset($arguments['name'])) {
			foreach ($this->validationRules as $key => $ruleName) {
				if ((int)$key && strpos($key, '.') === FALSE) {
					if (isset($this->validationRules[$key . '.'])) {
						$ruleConfiguration = $this->validationRules[$key . '.'];
						if (isset($ruleConfiguration['element']) && $ruleConfiguration['element'] === $arguments['name']) {
							$arguments['validation'][$ruleName] = $ruleConfiguration;
						}
					}
				}
			}
		}
	}

}
