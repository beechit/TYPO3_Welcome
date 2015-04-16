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

use TYPO3\CMS\Core\Utility\PathUtility;

/**
 * A folder that groups files in a storage. This may be a folder on the local
 * disk, a bucket in Amazon S3 or a user or a tag in Flickr.
 *
 * This object is not persisted in TYPO3 locally, but created on the fly by
 * storage drivers for the folders they "offer".
 *
 * Some folders serve as a physical container for files (e.g. folders on the
 * local disk, S3 buckets or Flickr users). Other folders just group files by a
 * certain criterion, e.g. a tag.
 * The way this is implemented depends on the storage driver.
 *
 * @author Andreas Wolf <andreas.wolf@ikt-werk.de>
 * @author Ingmar Schlecht <ingmar@typo3.org>
 */
class Folder implements FolderInterface {

	/**
	 * The storage this folder belongs to.
	 *
	 * @var ResourceStorage
	 */
	protected $storage;

	/**
	 * The identifier of this folder to identify it on the storage.
	 * On some drivers, this is the path to the folder, but drivers could also just
	 * provide any other unique identifier for this folder on the specific storage.
	 *
	 * @var string
	 */
	protected $identifier;

	/**
	 * The name of this folder
	 *
	 * @var string
	 */
	protected $name;

	/**
	 * The filters this folder should use for a file list.
	 *
	 * @var callable[]
	 */
	protected $fileAndFolderNameFilters = array();

	/**
	 * Modes for filter usage in getFiles()/getFolders()
	 */
	const FILTER_MODE_NO_FILTERS = 0;
	// Merge local filters into storage's filters
	const FILTER_MODE_USE_OWN_AND_STORAGE_FILTERS = 1;
	// Only use the filters provided by the storage
	const FILTER_MODE_USE_STORAGE_FILTERS = 2;
	// Only use the filters provided by the current class
	const FILTER_MODE_USE_OWN_FILTERS = 3;

	/**
	 * Initialization of the folder
	 *
	 * @param ResourceStorage $storage
	 * @param $identifier
	 * @param $name
	 */
	public function __construct(ResourceStorage $storage, $identifier, $name) {
		$this->storage = $storage;
		$this->identifier = rtrim($identifier, '/') . '/';
		$this->name = $name;
	}

	/**
	 * Returns the name of this folder.
	 *
	 * @return string
	 */
	public function getName() {
		return $this->name;
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
		$this->name = $name;
	}

	/**
	 * Returns the storage this folder belongs to.
	 *
	 * @return ResourceStorage
	 */
	public function getStorage() {
		return $this->storage;
	}

	/**
	 * Returns the path of this folder inside the storage. It depends on the
	 * type of storage whether this is a real path or just some unique identifier.
	 *
	 * @return string
	 */
	public function getIdentifier() {
		return $this->identifier;
	}

	/**
	 * Get hashed identifier
	 *
	 * @return string
	 */
	public function getHashedIdentifier() {
		return $this->storage->hashFileIdentifier($this->identifier);
	}

	/**
	 * Returns a combined identifier of this folder, i.e. the storage UID and
	 * the folder identifier separated by a colon ":".
	 *
	 * @return string Combined storage and folder identifier, e.g. StorageUID:folder/path/
	 */
	public function getCombinedIdentifier() {
		return $this->getStorage()->getUid() . ':' . $this->getIdentifier();
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
		return $this->getStorage()->getPublicUrl($this, $relativeToCurrentScript);
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
		// Fallback for compatibility with the old method signature variable $useFilters that was used instead of $filterMode
		if ($filterMode === FALSE) {
			$useFilters = FALSE;
			$backedUpFilters = array();
		} else {
			list($backedUpFilters, $useFilters) = $this->prepareFiltersInStorage($filterMode);
		}

		$fileObjects = $this->storage->getFilesInFolder($this, $start, $numberOfItems, $useFilters, $recursive);

		$this->restoreBackedUpFiltersInStorage($backedUpFilters);

		return $fileObjects;
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
		return count($this->storage->getFileIdentifiersInFolder($this->identifier, TRUE, $recursive));
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
		if (!$this->storage->hasFolderInFolder($name, $this)) {
			throw new \InvalidArgumentException('Folder "' . $name . '" does not exist in "' . $this->identifier . '"', 1329836110);
		}
		/** @var $factory ResourceFactory */
		$factory = ResourceFactory::getInstance();
		$folderObject = $factory->createFolderObject($this->storage, $this->identifier . $name . '/', $name);
		return $folderObject;
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
		list($backedUpFilters, $useFilters) = $this->prepareFiltersInStorage($filterMode);
		$folderObjects = $this->storage->getFoldersInFolder($this, $start, $numberOfItems, $useFilters);
		$this->restoreBackedUpFiltersInStorage($backedUpFilters);
		return $folderObjects;
	}

	/**
	 * Adds a file from the local server disk. If the file already exists and
	 * overwriting is disabled,
	 *
	 * @param string $localFilePath
	 * @param string $fileName
	 * @param string $conflictMode possible value are 'cancel', 'replace', 'changeName'
	 * @return File The file object
	 */
	public function addFile($localFilePath, $fileName = NULL, $conflictMode = 'cancel') {
		$fileName = $fileName ? $fileName : PathUtility::basename($localFilePath);
		return $this->storage->addFile($localFilePath, $this, $fileName, $conflictMode);
	}

	/**
	 * Adds an uploaded file into the Storage.
	 *
	 * @param array $uploadedFileData contains information about the uploaded file given by $_FILES['file1']
	 * @param string $conflictMode possible value are 'cancel', 'replace'
	 * @return File The file object
	 */
	public function addUploadedFile(array $uploadedFileData, $conflictMode = 'cancel') {
		return $this->storage->addUploadedFile($uploadedFileData, $this, $uploadedFileData['name'], $conflictMode);
	}

	/**
	 * Renames this folder.
	 *
	 * @param string $newName
	 * @return Folder
	 */
	public function rename($newName) {
		return $this->storage->renameFolder($this, $newName);
	}

	/**
	 * Deletes this folder from its storage. This also means that this object becomes useless.
	 *
	 * @param bool $deleteRecursively
	 * @return bool TRUE if deletion succeeded
	 */
	public function delete($deleteRecursively = TRUE) {
		return $this->storage->deleteFolder($this, $deleteRecursively);
	}

	/**
	 * Creates a new blank file
	 *
	 * @param string $fileName
	 * @return File The new file object
	 */
	public function createFile($fileName) {
		return $this->storage->createFile($fileName, $this);
	}

	/**
	 * Creates a new folder
	 *
	 * @param string $folderName
	 * @return Folder The new folder object
	 */
	public function createFolder($folderName) {
		return $this->storage->createFolder($folderName, $this);
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
		return $targetFolder->getStorage()->copyFolder($this, $targetFolder, $targetFolderName, $conflictMode);
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
		return $targetFolder->getStorage()->moveFolder($this, $targetFolder, $targetFolderName, $conflictMode);
	}

	/**
	 * Checks if a file exists in this folder
	 *
	 * @param string $name
	 * @return bool
	 */
	public function hasFile($name) {
		return $this->storage->hasFileInFolder($name, $this);
	}

	/**
	 * Checks if a folder exists in this folder.
	 *
	 * @param string $name
	 * @return bool
	 */
	public function hasFolder($name) {
		return $this->storage->hasFolderInFolder($name, $this);
	}

	/**
	 * Check if a file operation (= action) is allowed on this folder
	 *
	 * @param string $action Action that can be read, write or delete
	 * @return bool
	 */
	public function checkActionPermission($action) {
		return $this->getStorage()->checkFolderActionPermission($action, $this);
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
		// Setting identifier and name to update values
		if (isset($properties['identifier'])) {
			$this->identifier = $properties['identifier'];
		}
		if (isset($properties['name'])) {
			$this->name = $properties['name'];
		}
	}

	/**
	 * Prepares the filters in this folder's storage according to a set filter mode.
	 *
	 * @param int $filterMode The filter mode to use; one of the FILTER_MODE_* constants
	 * @return array The backed up filters as an array (NULL if filters were not backed up) and whether to use filters or not (bool)
	 */
	protected function prepareFiltersInStorage($filterMode) {
		$backedUpFilters = NULL;
		$useFilters = TRUE;

		switch ($filterMode) {
			case self::FILTER_MODE_USE_OWN_FILTERS:
				$backedUpFilters = $this->storage->getFileAndFolderNameFilters();
				$this->storage->setFileAndFolderNameFilters($this->fileAndFolderNameFilters);

				break;

			case self::FILTER_MODE_USE_OWN_AND_STORAGE_FILTERS:
				if (count($this->fileAndFolderNameFilters) > 0) {
					$backedUpFilters = $this->storage->getFileAndFolderNameFilters();
					foreach ($this->fileAndFolderNameFilters as $filter) {
						$this->storage->addFileAndFolderNameFilter($filter);
					}
				}

				break;

			case self::FILTER_MODE_USE_STORAGE_FILTERS:
				// nothing to do here

				break;

			case self::FILTER_MODE_NO_FILTERS:
				$useFilters = FALSE;

				break;
		}
		return array($backedUpFilters, $useFilters);
	}

	/**
	 * Restores the filters of a storage.
	 *
	 * @param array $backedUpFilters The filters to restore; might be NULL if no filters have been backed up, in
	 *                               which case this method does nothing.
	 * @see prepareFiltersInStorage()
	 */
	protected function restoreBackedUpFiltersInStorage($backedUpFilters) {
		if ($backedUpFilters !== NULL) {
			$this->storage->setFileAndFolderNameFilters($backedUpFilters);
		}
	}

	/**
	 * Sets the filters to use when listing files. These are only used if the filter mode is one of
	 * FILTER_MODE_USE_OWN_FILTERS and FILTER_MODE_USE_OWN_AND_STORAGE_FILTERS
	 *
	 * @param array $filters
	 */
	public function setFileAndFolderNameFilters(array $filters) {
		$this->fileAndFolderNameFilters = $filters;
	}

	/**
	 * Returns the role of this folder (if any). See FolderInterface::ROLE_* constants for possible values.
	 *
	 * @return int
	 */
	public function getRole() {
		return $this->storage->getRole($this);
	}

	/**
	 * Returns the parent folder.
	 *
	 * In non-hierarchical storages, that always is the root folder.
	 *
	 * The parent folder of the root folder is the root folder.
	 *
	 * @return Folder
	 */
	public function getParentFolder() {
		return $this->getStorage()->getFolder($this->getStorage()->getFolderIdentifierFromFileIdentifier($this->getIdentifier()));
	}

}
