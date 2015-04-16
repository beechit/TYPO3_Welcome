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

/**
 * Common helper methods.
 *
 * @author Oliver Hader <oliver.hader@typo3.org>
 */
class FormUtility implements \TYPO3\CMS\Core\SingletonInterface {

	/**
	 * @var array
	 */
	protected $formObjects = array();

	/**
	 * Gets a singleton instance of this object.
	 *
	 * @return \TYPO3\CMS\Form\Utility\FormUtility
	 */
	static public function getInstance() {
		return \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(__CLASS__);
	}

	/**
	 * Initializes this object.
	 */
	public function __construct() {
		$this->setFormObjects(array(
			'BUTTON',
			'CHECKBOX',
			'CHECKBOXGROUP',
			'FIELDSET',
			'FILEUPLOAD',
			'FORM',
			'FORM_INT',
			'HEADER',
			'HIDDEN',
			'IMAGEBUTTON',
			'OPTGROUP',
			'OPTION',
			'PASSWORD',
			'RADIO',
			'RADIOGROUP',
			'RESET',
			'SELECT',
			'SUBMIT',
			'TEXTAREA',
			'TEXTBLOCK',
			'TEXTLINE'
		));
	}

	/**
	 * Gets the available form objects.
	 *
	 * @return array
	 */
	public function getFormObjects() {
		return $this->formObjects;
	}

	/**
	 * Sets the available form objects.
	 *
	 * @param array $formObjects
	 * @return void
	 */
	public function setFormObjects(array $formObjects) {
		$this->formObjects = $formObjects;
	}

	/**
	 * Initializes the available form objects.
	 *
	 * @return \TYPO3\CMS\Form\Utility\FormUtility
	 */
	public function initializeFormObjects() {
		// Assign new FORM objects
		foreach ($this->getFormObjects() as $formObject) {
			$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_content.php']['cObjTypeAndClass'][] = array(
				$formObject,
				'EXT:form/Classes/Controller/FormController.php:&TYPO3\\CMS\\Form\\Controller\\FormController'
			);
		}
		return $this;
	}

	/**
	 * Initializes the Page TSconfig properties.
	 *
	 * @return \TYPO3\CMS\Form\Utility\FormUtility
	 */
	public function initializePageTsConfig() {
		\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig('<INCLUDE_TYPOSCRIPT: source="FILE:EXT:form/Configuration/PageTS/modWizards.ts">');
		return $this;
	}

	/**
	 * Gets the last part of the current object's class name.
	 * e.g. for '\TYPO3\CMS\Form\View\Confirmation\Additional\AdditionalElementView' it will be 'Additional'
	 *
	 * @param object $object The object to be used
	 * @param bool $lowercase Whether to convert to lowercase
	 * @return string
	 */
	public function getLastPartOfClassName($object, $lowercase = FALSE) {
		$lastPart = preg_replace('/^.*\\\\([^\\\\]+?)(Additional|Attribute|Json|Element|View)+$/', '${1}', get_class($object), 1);
		if ($lowercase) {
			$lastPart = strtolower($lastPart);
		}
		return $lastPart;
	}

}
