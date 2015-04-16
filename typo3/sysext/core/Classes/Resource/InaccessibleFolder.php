<?php
namespace TYPO3\CMS\Core\Resource;

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
 * A representation for an inaccessible folder.
 *
 * If a folder has execution rights you can list it's contents
 * despite the access rights on the subfolders. If a subfolder
 * has no rights it has to be shown anyhow, but marked as
 * inaccessible.
 */
class InaccessibleFolder extends Folder {

	/**
	 * Throws an Exception,
	 * used to prevent duplicate code in all the methods
	 *
	 * @throws Exception\InsufficientFolderReadPermissionsException
	 * @return void
	 */
	protected function throwInaccessibleException() {
		throw new \TYPO3\CMS\Core\Resource\Exception\InsufficientFolderReadPermissionsException(
			'You are trying to use a method on an inaccessible folder.',
			1390290029
		);
	}

	/**
	 * Sets a new name of the folder
	 * currently this does not trigger the "renaming process"
	 * as the name is more seen as a label
	 *
	 * @param string $name The new name
	 * @return void
	 */
	public function setName($name) {
		$this->throwInaccessibleException();
	}


	/**
	 * Returns a publicly accessible URL for this folder
	 *
	 * WARNING: Access to the folder may be restricted by further means, e.g. some
	 * web-based authentication. You have to take care of this yourself.
	 *
	 * @param bool $relativeToCurrentScript Determines whether the URL returned should be relative to the current script, in case it is relative at all (only for the LocalDriver)
	 * @return string
	 */
	public function getPublicUrl($relativeToCurrentScript = FALSE) {
		$this->throwInaccessibleException();
	}

	/**
	 * Returns a list of files in this folder, optionally filtered. There are several filter modes available, see the
	 * FILTER_MODE_* constants for more information.
	 *
	 * For performance reasons the returned items can also be limited to a given range
	 *
	 * @param int $start The item to start at
	 * @param int $numberOfItems The number of items to return
	 * @param int $filterMode The filter mode to use for the file list.
	 * @param bool $recursive
	 * @return \TYPO3\CMS\Core\Resource\File[]
	 */
	public function getFiles($start = 0, $numberOfItems = 0, $filterMode = self::FILTER_MODE_USE_OWN_AND_STORAGE_FILTERS, $recursive = FALSE) {
		$this->throwInaccessibleException();
	}

	/**
	 * Returns amount of all files within this folder, optionally filtered by
	 * the given pattern
	 *
	 * @param array $filterMethods
	 * @param bool $recursive
	 *
	 * @return int
	 */
	public function getFileCount(array $filterMethods = array(), $recursive = FALSE) {
		$this->throwInaccessibleException();
	}

	/**
	 * Returns the object for a subfolder of the current folder, if it exists.
	 *
	 * @param string $name Name of the subfolder
	 *
	 * @throws \InvalidArgumentException
	 * @return Folder
	 */
	public function getSubfolder($name) {
		$this->throwInaccessibleException();
	}

	/**
	 * Returns a list of subfolders
	 *
	 * @param int $start The item to start at
	 * @param int $numberOfItems The number of items to return
	 * @param int $filterMode The filter mode to use for the file list.
	 * @return Folder[]
	 */
	public function getSubfolders($start = 0, $numberOfItems = 0, $filterMode = self::FILTER_MODE_USE_OWN_AND_STORAGE_FILTERS) {
		$this->throwInaccessibleException();
	}

	/**
	 * Adds a file from the local server disk. If the file already exists and
	 * overwriting is disabled,
	 *
	 * @param string $localFilePath
	 * @param string $fileName
	 * @param string $conflictMode possible value are 'cancel', 'replace'
	 * @return File The file object
	 */
	public function addFile($localFilePath, $fileName = NULL, $conflictMode = 'cancel') {
		$this->throwInaccessibleException();
	}

	/**
	 * Adds an uploaded file into the Storage.
	 *
	 * @param array $uploadedFileData contains information about the uploaded file given by $_FILES['file1']
	 * @param string $conflictMode possible value are 'cancel', 'replace'
	 * @return File The file object
	 */
	public function addUploadedFile(array $uploadedFileData, $conflictMode = 'cancel') {
		$this->throwInaccessibleException();
	}

	/**
	 * Renames this folder.
	 *
	 * @param string $newName
	 * @return Folder
	 */
	public function rename($newName) {
		$this->throwInaccessibleException();
	}

	/**
	 * Deletes this folder from its storage. This also means that this object becomes useless.
	 *
	 * @param bool $deleteRecursively
	 * @return bool TRUE if deletion succeeded
	 */
	public function delete($deleteRecursively = TRUE) {
		$this->throwInaccessibleException();
	}

	/**
	 * Creates a new blank file
	 *
	 * @param string $fileName
	 * @return File The new file object
	 */
	public function createFile($fileName) {
		$this->throwInaccessibleException();
	}

	/**
	 * Creates a new folder
	 *
	 * @param string $folderName
	 * @return Folder The new folder object
	 */
	public function createFolder($folderName) {
		$this->throwInaccessibleException();
	}

	/**
	 * Copies folder to a target folder
	 *
	 * @param Folder $targetFolder Target folder to copy to.
	 * @param string $targetFolderName an optional destination fileName
	 * @param string $conflictMode "overrideExistingFile", "renameNewFile" or "cancel
	 * @return Folder New (copied) folder object.
	 */
	public function copyTo(Folder $targetFolder, $targetFolderName = NULL, $conflictMode = 'renameNewFile') {
		$this->throwInaccessibleException();
	}

	/**
	 * Moves folder to a target folder
	 *
	 * @param Folder $targetFolder Target folder to move to.
	 * @param string $targetFolderName an optional destination fileName
	 * @param string $conflictMode "overrideExistingFile", "renameNewFile" or "cancel
	 * @return Folder New (copied) folder object.
	 */
	public function moveTo(Folder $targetFolder, $targetFolderName = NULL, $conflictMode = 'renameNewFile') {
		$this->throwInaccessibleException();
	}

	/**
	 * Checks if a file exists in this folder
	 *
	 * @param string $name
	 * @return bool
	 */
	public function hasFile($name) {
		$this->throwInaccessibleException();
	}

	/**
	 * Checks if a folder exists in this folder.
	 *
	 * @param string $name
	 * @return bool
	 */
	public function hasFolder($name) {
		$this->throwInaccessibleException();
	}


	/**
	 * Updates the properties of this folder, e.g. after re-indexing or moving it.
	 *
	 * NOTE: This method should not be called from outside the File Abstraction Layer (FAL)!
	 *
	 * @param array $properties
	 * @return void
	 * @internal
	 */
	public function updateProperties(array $properties) {
		$this->throwInaccessibleException();
	}


	/**
	 * Sets the filters to use when listing files. These are only used if the filter mode is one of
	 * FILTER_MODE_USE_OWN_FILTERS and FILTER_MODE_USE_OWN_AND_STORAGE_FILTERS
	 *
	 * @param array $filters
	 */
	public function setFileAndFolderNameFilters(array $filters) {
		$this->throwInaccessibleException();
	}




}
