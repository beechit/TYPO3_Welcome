<?php
namespace TYPO3\CMS\Extensionmanager\Utility;

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
 * Utility to find and execute class.ext_update.php scripts of extensions
 *
 * @author Christian Kuhn <lolli@schwarzbu.ch>
 */
class UpdateScriptUtility {

	/**
	 * Returns true, if ext_update class says it wants to run.
	 *
	 * @param string $extensionKey extension key
	 * @return mixed NULL, if update is not available, else update script return
	 */
	public function executeUpdateIfNeeded($extensionKey) {
		$this->requireUpdateScript($extensionKey);
		$scriptObject = new \ext_update;
		// old em always assumed the method exist, we do so too.
		// @TODO: Make this smart, let scripts implement interfaces
		// @TODO: Enforce different update class script names per extension
		// @TODO: With different class names it would be easily possible to check for updates in list view.
		$accessReturnValue = $scriptObject->access();

		$result = NULL;
		if ($accessReturnValue === TRUE) {
			// @TODO: With current ext_update construct it is impossible
			// @TODO: to enforce some type of return
			$result = $scriptObject->main();
		}

		return $result;
	}

	/**
	 * Require update script.
	 * Throws exception if update script does not exist, so checkUpdateScriptExists()
	 * should be called before
	 *
	 * @param string $extensionKey
	 * @throws \TYPO3\CMS\Extensionmanager\Exception\ExtensionManagerException
	 */
	protected function requireUpdateScript($extensionKey) {
		if (class_exists('ext_update')) {
			throw new \TYPO3\CMS\Extensionmanager\Exception\ExtensionManagerException(
				'class ext_update for this run does already exist, requiring impossible',
				1359748085
			);
		}

		$fileLocation = $this->getUpdateFileLocation($extensionKey);

		if (!$this->checkUpdateScriptExists($extensionKey)) {
			throw new \TYPO3\CMS\Extensionmanager\Exception\ExtensionManagerException(
				'Requested update script of extension does not exist',
				1359747976
			);

		}
		require $fileLocation;

		if (!class_exists('ext_update')) {
			throw new \TYPO3\CMS\Extensionmanager\Exception\ExtensionManagerException(
				'class.ext_update.php of extension did not declare ext_update class',
				1359748132
			);
		}
	}

	/**
	 * Checks if an update class file exists.
	 * Does not check if some update is needed.
	 *
	 * @param string $extensionKey Extension key
	 * @return bool True, if there is some update script
	 */
	public function checkUpdateScriptExists($extensionKey) {
		$updateScriptFileExists = FALSE;
		if (file_exists($this->getUpdateFileLocation($extensionKey))) {
			$updateScriptFileExists = TRUE;
		}
		return $updateScriptFileExists;
	}

	/**
	 * Determines location of update file.
	 * Does not check if the file exists.
	 *
	 * @param string $extensionKey Extension key
	 * @return string Absolute path to possible update file of extension
	 */
	protected function getUpdateFileLocation($extensionKey) {
		return \TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName(
			'EXT:' . $extensionKey . '/class.ext_update.php',
			FALSE
		);
	}

}
