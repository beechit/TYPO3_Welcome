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

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Static methods for filtering
 *
 * @author Patrick Broens <patrick@patrickbroens.nl>
 */
class FilterUtility implements \TYPO3\CMS\Form\Filter\FilterInterface {

	/**
	 * Array with filter objects to use
	 *
	 * @var array
	 */
	protected $filters = array();

	/**
	 * Constructor
	 * Adds the removeXSS filter by default
	 * Never remove these lines, otherwise the forms
	 * will be vulnerable for XSS attacks
	 */
	public function __construct() {
		$removeXssFilter = $this->makeFilter('removeXss');
		$this->addFilter($removeXssFilter);
	}

	/**
	 * Add a filter object to the filter array
	 *
	 * @param \TYPO3\CMS\Form\Filter\FilterInterface $filter The filter
	 * @return \TYPO3\CMS\Form\Utility\FilterUtility
	 */
	public function addFilter(\TYPO3\CMS\Form\Filter\FilterInterface $filter) {
		$this->filters[] = $filter;
		return $this;
	}

	/**
	 * Create a filter object according to class
	 * and sent some arguments
	 *
	 * @param string $class Name of the filter
	 * @param array $arguments Configuration of the filter
	 * @return \TYPO3\CMS\Form\Filter\FilterInterface The filter object
	 */
	public function makeFilter($class, array $arguments = NULL) {
		return self::createFilter($class, $arguments);
	}

	/**
	 * Go through all filters added to the array
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	public function filter($value) {
		if (!empty($this->filters)) {
			/** @var $filter \TYPO3\CMS\Form\Filter\FilterInterface */
			foreach ($this->filters as $filter) {
				$value = $filter->filter($value);
			}
		}
		return $value;
	}

	/**
	 * Call filter through this class with automatic instantiation of filter
	 *
	 * @param string $class
	 * @param mixed $value
	 * @param array $arguments
	 * @return mixed
	 */
	static public function get($class, $value, array $arguments = array()) {
		return self::createFilter($class, $arguments)->filter($value);
	}

	/**
	 * Create a filter object according to class
	 * and sent some arguments
	 *
	 * @param string $class Name of the filter
	 * @param array $arguments Configuration of the filter
	 * @return \TYPO3\CMS\Form\Filter\FilterInterface The filter object
	 */
	static public function createFilter($class, array $arguments = NULL) {
		$class = strtolower((string)$class);
		$className = 'TYPO3\\CMS\\Form\\Filter\\' . ucfirst($class) . 'Filter';
		if (is_null($arguments)) {
			$filter = GeneralUtility::makeInstance($className);
		} else {
			$filter = GeneralUtility::makeInstance($className, $arguments);
		}
		return $filter;
	}

}
