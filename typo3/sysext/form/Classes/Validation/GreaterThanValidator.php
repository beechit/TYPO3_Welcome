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

/**
 * Greater than rule
 *
 * @author Patrick Broens <patrick@patrickbroens.nl>
 */
class GreaterThanValidator extends \TYPO3\CMS\Form\Validation\AbstractValidator {

	/**
	 * Constant for localisation
	 *
	 * @var string
	 */
	const LOCALISATION_OBJECT_NAME = 'tx_form_system_validate_greaterthan';

	/**
	 * Minimum value
	 *
	 * @var mixed
	 */
	protected $minimum;

	/**
	 * Constructor
	 *
	 * @param array $arguments Typoscript configuration
	 */
	public function __construct($arguments) {
		$this->setMinimum($arguments['minimum']);
		parent::__construct($arguments);
	}

	/**
	 * Returns TRUE if submitted value validates according to rule
	 *
	 * @return bool
	 * @see \TYPO3\CMS\Form\Validation\ValidatorInterface::isValid()
	 */
	public function isValid() {
		if ($this->requestHandler->has($this->fieldName)) {
			$value = (int)$this->requestHandler->getByMethod($this->fieldName);
			if ($value <= $this->minimum) {
				return FALSE;
			}
		}
		return TRUE;
	}

	/**
	 * Set the minimum value
	 *
	 * @param mixed $minimum Minimum value
	 * @return GreaterThanValidator Rule object
	 */
	public function setMinimum($minimum) {
		$this->minimum = (int)$minimum;
		return $this;
	}

	/**
	 * Substitute makers in the message text
	 * Overrides the abstract
	 *
	 * @param string $message Message text with markers
	 * @return string Message text with substituted markers
	 */
	protected function substituteValues($message) {
		$message = str_replace('%minimum', $this->minimum, $message);
		return $message;
	}

}
