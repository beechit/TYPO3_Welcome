<?php
namespace TYPO3\CMS\Form\Domain\Factory;

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

use TYPO3\CMS\Core\TimeTracker\TimeTracker;
use TYPO3\CMS\Core\TypoScript\TemplateService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Form\Domain\Model\Element\AbstractElement;

/**
 * Typoscript factory for form
 *
 * Takes the incoming Typoscipt and adds all the necessary form objects
 * according to the configuration.
 *
 * @author Patrick Broens <patrick@patrickbroens.nl>
 */
class TypoScriptFactory implements \TYPO3\CMS\Core\SingletonInterface {

	/**
	 * @var string
	 */
	const PROPERTY_DisableContentElement = 'disableContentElement';

	/**
	 * @var \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer
	 */
	protected $localContentObject;

	/**
	 * @var bool
	 */
	protected $disableContentElement = FALSE;

	/**
	 * @var TimeTracker
	 */
	protected $timeTracker;

	/**
	 * @var \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController
	 */
	protected $frontendController;

	public function __construct() {
		$this->timeTracker = $GLOBALS['TT'];
		$this->frontendController = $GLOBALS['TSFE'];
	}

	/**
	 * Build model from Typoscript
	 *
	 * @param array $typoscript Typoscript containing all configuration
	 * @return \TYPO3\CMS\Form\Domain\Model\Form The form object containing the child elements
	 */
	public function buildModelFromTyposcript(array $typoscript) {
		if (isset($typoscript[self::PROPERTY_DisableContentElement])) {
			$this->setDisableContentElement($typoscript[self::PROPERTY_DisableContentElement]);
		}
		$this->setLayoutHandler($typoscript);
		$form = $this->createElement('form', $typoscript);
		return $form;
	}

	/**
	 * Disables the content element.
	 *
	 * @param bool $disableContentElement
	 * @return void
	 */
	public function setDisableContentElement($disableContentElement) {
		$this->disableContentElement = (bool)$disableContentElement;
	}

	/**
	 * Rendering of a "numerical array" of Form objects from TypoScript
	 * Creates new object for each element found
	 *
	 * @param AbstractElement $parentElement Parent model object
	 * @param array $typoscript Configuration array
	 * @return void
	 * @throws \InvalidArgumentException
	 */
	public function getChildElementsByIntegerKey(AbstractElement $parentElement, array $typoscript) {
		if (is_array($typoscript)) {
			$keys = TemplateService::sortedKeyList($typoscript);
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
		} else {
			throw new \InvalidArgumentException('Container element with id=' . $parentElement->getElementId() . ' has no configuration which means no children.', 1333754854);
		}
	}

	/**
	 * Create and add element by type.
	 * This can be a derived Typoscript object by "<",
	 * a form element, or a regular Typoscript object.
	 *
	 * @param AbstractElement $parentElement The parent for the new element
	 * @param string $class Classname for the element
	 * @param array $arguments Configuration array
	 * @return void
	 */
	public function setElementType(AbstractElement $parentElement, $class, array $arguments) {
		if (in_array($class, \TYPO3\CMS\Form\Utility\FormUtility::getInstance()->getFormObjects())) {
			$this->addElement($parentElement, $class, $arguments);
		} elseif ($this->disableContentElement === FALSE) {
			if ($class[0] === '<') {
				$key = trim(substr($class, 1));
				/** @var $typoscriptParser \TYPO3\CMS\Core\TypoScript\Parser\TypoScriptParser */
				$typoscriptParser = GeneralUtility::makeInstance(\TYPO3\CMS\Core\TypoScript\Parser\TypoScriptParser::class);
				$oldArguments = $arguments;
				list($class, $arguments) = $typoscriptParser->getVal($key, $this->frontendController->tmpl->setup);
				if (is_array($oldArguments) && count($oldArguments)) {
					$arguments = array_replace_recursive($arguments, $oldArguments);
				}
				$this->timeTracker->incStackPointer();
				$contentObject = array(
					'cObj' => $class,
					'cObj.' => $arguments
				);
				$this->addElement($parentElement, 'content', $contentObject);
				$this->timeTracker->decStackPointer();
			} else {
				$contentObject = array(
					'cObj' => $class,
					'cObj.' => $arguments
				);
				$this->addElement($parentElement, 'content', $contentObject);
			}
		}
	}

	/**
	 * Add child object to this element
	 *
	 * @param AbstractElement $parentElement Parent model object
	 * @param string $class Type of element
	 * @param array $arguments Configuration array
	 * @return void
	 */
	public function addElement(AbstractElement $parentElement, $class, array $arguments = array()) {
		$element = $this->createElement($class, $arguments);
		if (method_exists($parentElement, 'addElement')) {
			$parentElement->addElement($element);
		}
	}

	/**
	 * Create element by loading class
	 * and instantiating the object
	 *
	 * @param string $class Type of element
	 * @param array $arguments Configuration array
	 * @return AbstractElement
	 * @throws \InvalidArgumentException
	 */
	public function createElement($class, array $arguments = array()) {
		$class = strtolower((string)$class);
		if ($class === 'form') {
			$className = 'TYPO3\\CMS\\Form\\Domain\\Model\\' . ucfirst($class);
		} else {
			$className = 'TYPO3\\CMS\\Form\\Domain\\Model\\Element\\' . ucfirst($class) . 'Element';
		}
		/* @var $object AbstractElement */
		$object = GeneralUtility::makeInstance($className);
		if ($object->getElementType() === AbstractElement::ELEMENT_TYPE_CONTENT) {
			$object->setData($arguments['cObj'], $arguments['cObj.']);
		} elseif ($object->getElementType() === AbstractElement::ELEMENT_TYPE_PLAIN) {
			/* @var $object \TYPO3\CMS\Form\Domain\Model\Element\AbstractPlainElement */
			$object->setProperties($arguments);
		} elseif ($object->getElementType() === AbstractElement::ELEMENT_TYPE_FORM) {
			$object->setData($arguments['data']);
			$this->reconstituteElement($object, $arguments);
		} else {
			throw new \InvalidArgumentException('Element type "' . $object->getElementType() . '" is not supported.', 1333754878);
		}
		return $object;
	}

	/**
	 * Reconstitutes the domain model of the accordant element.
	 *
	 * @param AbstractElement $element
	 * @param array $arguments Configuration array
	 * @return void
	 */
	protected function reconstituteElement(AbstractElement $element, array $arguments = array()) {
		if (isset($arguments['value.'])) {
			$cObj = $this->getLocalContentObject();
			$arguments['value'] = $cObj->stdWrap($arguments['value'], $arguments['value.']);
		}

		$this->setAttributes($element, $arguments);
		$this->setAdditionals($element, $arguments);
		if (isset($arguments['filters.'])) {
			$this->setFilters($element, $arguments['filters.']);
		}
		$element->setLayout($arguments['layout']);
		$element->setValue($arguments['value']);
		$element->setName($arguments['name']);
		$element->setMessagesFromValidation();
		$element->setErrorsFromValidation();
		$element->checkFilterAndSetIncomingDataFromRequest();
		$this->getChildElementsByIntegerKey($element, $arguments);
	}

	/**
	 * Set the attributes
	 *
	 * @param AbstractElement $element Model object
	 * @param array $arguments Arguments
	 * @return void
	 * @throws \RuntimeException
	 * @throws \InvalidArgumentException
	 */
	public function setAttributes(AbstractElement $element, array $arguments) {
		if ($element->hasAllowedAttributes()) {
			$attributes = $element->getAllowedAttributes();
			$mandatoryAttributes = $element->getMandatoryAttributes();
			foreach ($attributes as $attribute => $value) {
				if (isset($arguments[$attribute]) || isset($arguments[$attribute . '.']) || in_array($attribute, $mandatoryAttributes) || !empty($value)) {
					if ((string)$arguments[$attribute] !== '') {
						$value = $arguments[$attribute];
					} elseif (!empty($arguments[($attribute . '.')])) {
						$value = $arguments[$attribute . '.'];
					}
					try {
						$element->setAttribute($attribute, $value);
					} catch (\Exception $exception) {
						throw new \RuntimeException('Cannot call user function for attribute ' . ucfirst($attribute), 1333754904);
					}
				}
			}
		} else {
			throw new \InvalidArgumentException('The element with id=' . $element->getElementId() . ' has no default attributes set.', 1333754925);
		}
	}

	/**
	 * Set the additionals from Element Typoscript configuration
	 *
	 * @param AbstractElement $element Model object
	 * @param array $arguments Arguments
	 * @return void
	 * @throws \RuntimeException
	 * @throws \InvalidArgumentException
	 */
	public function setAdditionals(AbstractElement $element, array $arguments) {
		if (!empty($arguments)) {
			if ($element->hasAllowedAdditionals()) {
				$additionals = $element->getAllowedAdditionals();
				foreach ($additionals as $additional) {
					if (isset($arguments[$additional . '.']) || isset($arguments[$additional])) {
						if (isset($arguments[$additional]) && isset($arguments[$additional . '.'])) {
							$value = $arguments[$additional . '.'];
							$type = $arguments[$additional];
						} elseif (isset($arguments[$additional . '.'])) {
							$value = $arguments[$additional . '.'];
							$type = 'TEXT';
						} else {
							$value['value'] = $arguments[$additional];
							$type = 'TEXT';
						}
						try {
							$element->setAdditional($additional, $type, $value);
						} catch (\Exception $exception) {
							throw new \RuntimeException('Cannot call user function for additional ' . ucfirst($additional), 1333754941);
						}
					}
					if (isset($arguments['layout.'][$additional]) && $element->additionalIsSet($additional)) {
						$layout = $arguments['layout.'][$additional];
						$element->setAdditionalLayout($additional, $layout);
					}
				}
			} else {
				throw new \InvalidArgumentException('The element with id=' . $element->getElementId() . ' has no additionals set.', 1333754962);
			}
		}
	}

	/**
	 * Add the filters according to the settings in the Typoscript array
	 *
	 * @param AbstractElement $element Model object
	 * @param array $arguments TypoScript
	 * @return void
	 */
	protected function setFilters(AbstractElement $element, array $arguments) {
		$keys = TemplateService::sortedKeyList($arguments);
		foreach ($keys as $key) {
			$class = $arguments[$key];
			if ((int)$key && strpos($key, '.') === FALSE) {
				$filterArguments = $arguments[$key . '.'];
				$filter = $element->makeFilter($class, $filterArguments);
				$element->addFilter($filter);
			}
		}
	}

	/**
	 * Set the layout handler
	 *
	 * @param array $typoscript TypoScript
	 * @return \TYPO3\CMS\Form\Layout The layout handler
	 */
	public function setLayoutHandler(array $typoscript) {
		/** @var $layoutHandler \TYPO3\CMS\Form\Layout */
		$layoutHandler = GeneralUtility::makeInstance(\TYPO3\CMS\Form\Layout::class);
		$layoutHandler->setLayout($this->getLayoutFromTypoScript($typoscript));
		return $layoutHandler;
	}

	/**
	 * Gets the layout that is configured in TypoScript
	 * If no layout is defined, it returns an empty array to use the default.
	 *
	 * @param array $typoscript The TypoScript configuration
	 * @return array $layout The layout but with respecting its TypoScript configuration
	 */
	public function getLayoutFromTypoScript($typoscript) {
		return !empty($typoscript['layout.']) ? $typoscript['layout.'] : array();
	}

	/**
	 * Set the request handler
	 *
	 * @param array $typoscript TypoScript
	 * @return \TYPO3\CMS\Form\Request The request handler
	 */
	public function setRequestHandler($typoscript) {
		$prefix = isset($typoscript['prefix']) ? $typoscript['prefix'] : '';
		$method = isset($typoscript['method']) ? $typoscript['method'] : '';
		/** @var $requestHandler \TYPO3\CMS\Form\Request */
		$requestHandler = GeneralUtility::makeInstance(\TYPO3\CMS\Form\Request::class);
		// singleton
		$requestHandler->setPrefix($prefix);
		$requestHandler->setMethod($method);
		$requestHandler->storeFiles();
		return $requestHandler;
	}

	/**
	 * Set the validation rules
	 *
	 * Makes the validation object and adds rules to it
	 *
	 * @param array $typoscript TypoScript
	 * @return \TYPO3\CMS\Form\Utility\ValidatorUtility The validation object
	 */
	public function setRules(array $typoscript) {
		$rulesTyposcript = isset($typoscript['rules.']) ? $typoscript['rules.'] : NULL;
		/** @var $rulesClass \TYPO3\CMS\Form\Utility\ValidatorUtility */
		$rulesClass = GeneralUtility::makeInstance(\TYPO3\CMS\Form\Utility\ValidatorUtility::class, $rulesTyposcript);
		// singleton
		if (is_array($rulesTyposcript)) {
			$keys = TemplateService::sortedKeyList($rulesTyposcript);
			foreach ($keys as $key) {
				$class = $rulesTyposcript[$key];
				if ((int)$key && strpos($key, '.') === FALSE) {
					$elementArguments = $rulesTyposcript[$key . '.'];
					$rule = $rulesClass->createRule($class, $elementArguments);
					$rule->setFieldName($elementArguments['element']);
					$breakOnError = isset($elementArguments['breakOnError']) ? $elementArguments['breakOnError'] : FALSE;
					$rulesClass->addRule($rule, $elementArguments['element'], $breakOnError);
				}
			}
		}
		return $rulesClass;
	}

	/**
	 * Gets the local content object.
	 *
	 * @return \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer
	 */
	protected function getLocalContentObject() {
		if (!isset($this->localContentObject)) {
			$this->localContentObject = GeneralUtility::makeInstance(\TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer::class);
		}
		return $this->localContentObject;
	}

}
