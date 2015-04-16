<?php
namespace TYPO3\CMS\Form\Validation;

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

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Form\Request;

/**
 * Abstract for validation
 *
 * @author Patrick Broens <patrick@patrickbroens.nl>
 */
abstract class AbstractValidator implements \TYPO3\CMS\Form\Validation\ValidatorInterface {

	/**
	 * The name of the field for the rule
	 *
	 * @var string
	 */
	protected $fieldName;

	/**
	 * Message of the rule as cObj
	 * This variable will contain two keys:
	 * $message['cObj] = TEXT
	 * $message['cObj.] = array('value', 'This is the message')
	 *
	 * @var array
	 */
	protected $message = array();

	/**
	 * Possible error message after validation as cObj
	 * This variable will contain two keys:
	 * $message['cObj] = TEXT
	 * $message['cObj.] = array('value', 'This is the error')
	 *
	 * @var array
	 */
	protected $error = array();

	/**
	 * Display the message like mandatory
	 *
	 * @var bool
	 */
	protected $showMessage = TRUE;

	/**
	 * Localization handler object
	 *
	 * @var \TYPO3\CMS\Form\Localization
	 */
	protected $localizationHandler;

	/**
	 * Request handler object
	 *
	 * @var Request
	 */
	protected $requestHandler;

	/**
	 * The content object
	 *
	 * @var \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer
	 */
	protected $localCobj;

	/**
	 * Inject the Request Handler
	 *
	 * @param Request $requestHandler
	 * @return void
	 */
	public function injectRequestHandler(Request $requestHandler) {
		$this->requestHandler = $requestHandler;
	}

	/**
	 * Constructor
	 *
	 * @param array $arguments Typoscript configuration for the validation rule
	 */
	public function __construct($arguments) {
		$this->localCobj = GeneralUtility::makeInstance(\TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer::class);
		$this->injectRequestHandler(GeneralUtility::makeInstance(Request::class));
		$this->localizationHandler = GeneralUtility::makeInstance(\TYPO3\CMS\Form\Localization::class);
		$this->setFieldName($arguments['element']);
		$this->setMessage($arguments['message.'], $arguments['message']);
		$this->setShowMessage($arguments['showMessage']);
		$this->setError($arguments['error.'], $arguments['error']);
	}

	/**
	 * Set the fieldName
	 *
	 * @param string $fieldName The field name
	 * @return ValidatorInterface The rule object
	 */
	public function setFieldName($fieldName) {
		$this->fieldName = (string)$fieldName;
		return $this;
	}

	/**
	 * Returns the field name
	 *
	 * @return string The field name
	 */
	public function getFieldName() {
		return $this->fieldName;
	}

	/**
	 * Returns the message
	 *
	 * @return array Typoscript for cObj
	 */
	public function getMessage() {
		return $this->message;
	}

	/**
	 * Returns the error
	 *
	 * @return array Typoscript for cObj
	 */
	public function getError() {
		return $this->error;
	}

	/**
	 * Set the message, like 'required' for the validation rule
	 * and substitutes markers for values, like %maximum
	 *
	 * The output will be a Typoscript array to use as cObj
	 * If no parameter is given, it will take the default locallang label
	 * If only first parameter, then it's supposed to be a TEXT cObj
	 * When both are filled, it's supposed to be a cObj made by the administrator
	 * In the last case, no markers will be substituted
	 *
	 * @param string|array $message Message as string or TS
	 * @param string $type Name of the cObj
	 * @return void
	 */
	public function setMessage($message = '', $type = 'TEXT') {
		if (empty($message)) {
			if (!empty($type)) {
				$message = $type;
				$type = 'TEXT';
			} else {
				$type = 'TEXT';
				$message = $this->getLocalLanguageLabel('message');
			}
			$value['value'] = $this->substituteValues($message);
		} elseif (!is_array($message)) {
			$value['value'] = $this->substituteValues($message);
		} else {
			$value = $message;
		}
		$this->message['cObj'] = (string)$type;
		$this->message['cObj.'] = $value;
	}

	/**
	 * Set the error, like 'This field is required' for the validation rule
	 *
	 * The output will be a Typoscript array to use as cObj
	 * If no parameter is given, it will take the default locallang label
	 * If only first parameter, then it's supposed to be a TEXT cObj
	 * When both are filled, it's supposed to be a cObj made by the administrator
	 * In the last case, no markers will be substituted
	 *
	 * @param mixed $error Message as string or TS
	 * @param string $type Name of the cObj
	 * @return void
	 */
	public function setError($error = '', $type = 'TEXT') {
		if (empty($error)) {
			if (!empty($type)) {
				$error = $type;
				$type = 'TEXT';
			} else {
				$type = 'TEXT';
				$error = $this->getLocalLanguageLabel('error');
			}
			$value['value'] = $this->substituteValues($error);
		} elseif (!is_array($error)) {
			$value['value'] = $this->substituteValues($error);
		} else {
			$value = $error;
		}
		$this->error['cObj'] = (string)$type;
		$this->error['cObj.'] = $value;
	}

	/**
	 * Set if message needs to be displayed
	 *
	 * @param bool $show TRUE is display
	 * @return ValidatorInterface The rule object
	 */
	public function setShowMessage($show) {
		if ($show === NULL) {
			$this->showMessage = TRUE;
		} else {
			$this->showMessage = (bool)$show;
		}
		return $this;
	}

	/**
	 * Returns TRUE when message needs to be displayed
	 *
	 * @return bool
	 */
	public function messageMustBeDisplayed() {
		return $this->showMessage;
	}

	/**
	 * Substitute makers in the message text
	 * In some cases this method will be override by rule class
	 *
	 * @param string $message Message text with markers
	 * @return string Message text with substituted markers
	 */
	protected function substituteValues($message) {
		return $message;
	}

	/**
	 * Get the local language label(s) for the message
	 * In some cases this method will be override by rule class
	 *
	 * @param string $type The type
	 * @return string The local language message label
	 */
	protected function getLocalLanguageLabel($type) {
		$label = static::LOCALISATION_OBJECT_NAME . '.' . $type;
		$message = $this->localizationHandler->getLocalLanguageLabel($label);
		return $message;
	}

}
