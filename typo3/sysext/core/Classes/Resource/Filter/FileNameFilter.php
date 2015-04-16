<?php
namespace TYPO3\CMS\Core\Resource\Filter;

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
 * Utility methods for filtering filenames
 *
 * @author Andreas Wolf <andreas.wolf@ikt-werk.de>
 */
class FileNameFilter {

	/**
	 * whether to also show the hidden files (don't show them by default)
	 *
	 * @var bool
	 */
	static protected $showHiddenFilesAndFolders = FALSE;

	/**
	 * Filter method that checks if a file/folder name starts with a dot (e.g. .htaccess)
	 *
	 * We have to use -1 as the „don't include“ return value, as call_user_func() will return FALSE
	 * If calling the method succeeded and thus we can't use that as a return value.
	 *
	 * @param string $itemName
	 * @param string $itemIdentifier
	 * @param string $parentIdentifier
	 * @param array $additionalInformation Additional information (driver dependent) about the inspected item
	 * @param \TYPO3\CMS\Core\Resource\Driver\DriverInterface $driverInstance
	 * @return bool|int -1 if the file should not be included in a listing
	 */
	static public function filterHiddenFilesAndFolders($itemName, $itemIdentifier, $parentIdentifier, array $additionalInformation, \TYPO3\CMS\Core\Resource\Driver\DriverInterface $driverInstance) {
		// Only apply the filter if you want to hide the hidden files
		if (self::$showHiddenFilesAndFolders === FALSE && strpos($itemIdentifier, '/.') !== FALSE) {
			return -1;
		} else {
			return TRUE;
		}
	}

	/**
	 * Gets the info whether the hidden files are also displayed currently
	 *
	 * @static
	 * @return bool
	 */
	static public function getShowHiddenFilesAndFolders() {
		return self::$showHiddenFilesAndFolders;
	}

	/**
	 * set the flag to show (or hide) the hidden files
	 *
	 * @static
	 * @param bool $showHiddenFilesAndFolders
	 * @return bool
	 */
	static public function setShowHiddenFilesAndFolders($showHiddenFilesAndFolders) {
		return self::$showHiddenFilesAndFolders = (bool)$showHiddenFilesAndFolders;
	}

}
