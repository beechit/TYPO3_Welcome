<?php
namespace TYPO3\CMS\Form\Filter;

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
 * Currency filter
 *
 * @author Patrick Broens <patrick@patrickbroens.nl>
 */
class CurrencyFilter implements \TYPO3\CMS\Form\Filter\FilterInterface {

	/**
	 * Separator between group of thousands
	 * Mostly dot, comma or whitespace
	 *
	 * @var string
	 */
	protected $decimalsPoint;

	/**
	 * Separator between group of thousands
	 * Mostly dot, comma or whitespace
	 *
	 * @var string
	 */
	protected $thousandSeparator;

	/**
	 * Constructor
	 *
	 * @param array $arguments Filter configuration
	 */
	public function __construct($arguments = array()) {
		$this->setDecimalsPoint($arguments['decimalPoint']);
		$this->setThousandSeparator($arguments['thousandSeparator']);
	}

	/**
	 * Set the decimal point character
	 *
	 * @param string $decimalsPoint Character used for decimal point
	 * @return \TYPO3\CMS\Form\Filter\CurrencyFilter
	 */
	public function setDecimalsPoint($decimalsPoint = '.') {
		if (empty($decimalsPoint)) {
			$this->decimalsPoint = '.';
		} else {
			$this->decimalsPoint = (string)$decimalsPoint;
		}
		return $this;
	}

	/**
	 * Set the thousand separator character
	 *
	 * @param string $thousandSeparator Character used for thousand separator
	 * @return \TYPO3\CMS\Form\Filter\CurrencyFilter
	 */
	public function setThousandSeparator($thousandSeparator = ',') {
		if (empty($thousandSeparator)) {
			$this->thousandSeparator = ',';
		} elseif ($thousandSeparator === 'space') {
			$this->thousandSeparator = ' ';
		} elseif ($thousandSeparator === 'none') {
			$this->thousandSeparator = '';
		} else {
			$this->thousandSeparator = (string)$thousandSeparator;
		}
		return $this;
	}

	/**
	 * Change to float with 2 decimals
	 * Change the dot to comma if requested
	 *
	 * @param string $value
	 * @return string
	 */
	public function filter($value) {
		$value = (double) ((string)$value);
		return number_format($value, 2, $this->decimalsPoint, $this->thousandSeparator);
	}

}
