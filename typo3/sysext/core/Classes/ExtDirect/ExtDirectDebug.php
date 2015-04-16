<?php
namespace TYPO3\CMS\Core\ExtDirect;

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
 * Ext Direct Debug
 *
 * @author Stefan Galinski <stefan.galinski@gmail.com>
 */
class ExtDirectDebug {

	/**
	 * Internal debug message array
	 *
	 * @var array
	 */
	protected $debugMessages = array();

	/**
	 * destructor
	 *
	 * Currently empty, but automatically registered and called during
	 * ExtDirect shutdown.
	 *
	 * @see http://forge.typo3.org/issues/25278
	 */
	public function __destruct() {

	}

	/**
	 * Adds a new message of any data type to the internal debug message array.
	 *
	 * @param mixed $message
	 * @return void
	 */
	public function debug($message) {
		$this->debugMessages[] = $message;
	}

	/**
	 * Returns the internal debug messages as a string.
	 *
	 * @return string
	 */
	public function toString() {
		$messagesAsString = '';
		if (count($this->debugMessages)) {
			$messagesAsString = \TYPO3\CMS\Core\Utility\DebugUtility::viewArray($this->debugMessages);
		}
		return $messagesAsString;
	}

}
