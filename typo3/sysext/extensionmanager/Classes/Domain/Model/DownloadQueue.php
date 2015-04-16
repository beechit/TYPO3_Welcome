<?php
namespace TYPO3\CMS\Extensionmanager\Domain\Model;

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
 * Download Queue - storage for extensions to be downloaded
 *
 * @author Susanne Moog <typo3@susannemoog.de>
 */
class DownloadQueue implements \TYPO3\CMS\Core\SingletonInterface {

	/**
	 * Storage for extensions to be downloaded
	 *
	 * @var Extension[string][string]
	 */
	protected $extensionStorage = array();

	/**
	 * Storage for extensions to be installed
	 *
	 * @var array
	 */
	protected $extensionInstallStorage = array();

	/**
	 * Storage for extensions to be copied
	 *
	 * @var array
	 */
	protected $extensionCopyStorage = array();

	/**
	 * @var \TYPO3\CMS\Extensionmanager\Utility\ListUtility
	 * @inject
	 */
	protected $listUtility;

	/**
	 * Adds an extension to the download queue.
	 * If the extension was already requested in a different version
	 * an exception is thrown.
	 *
	 * @param \TYPO3\CMS\Extensionmanager\Domain\Model\Extension $extension
	 * @param string $stack
	 * @throws \TYPO3\CMS\Extensionmanager\Exception\ExtensionManagerException
	 * @return void
	 */
	public function addExtensionToQueue(\TYPO3\CMS\Extensionmanager\Domain\Model\Extension $extension, $stack = 'download') {
		if (!is_string($stack) || !in_array($stack, array('download', 'update'))) {
			throw new \TYPO3\CMS\Extensionmanager\Exception\ExtensionManagerException('Stack has to be either "download" or "update"', 1342432103);
		}
		if (!isset($this->extensionStorage[$stack])) {
			$this->extensionStorage[$stack] = array();
		}
		if (array_key_exists($extension->getExtensionKey(), $this->extensionStorage[$stack])) {
			if ($this->extensionStorage[$stack][$extension->getExtensionKey()] !== $extension) {
				throw new \TYPO3\CMS\Extensionmanager\Exception\ExtensionManagerException(
					$extension->getExtensionKey() . ' was requested to be downloaded in different versions.',
					1342432101
				);
			}
		}
		$this->extensionStorage[$stack][$extension->getExtensionKey()] = $extension;
	}

	/**
	 * @return array
	 */
	public function getExtensionQueue() {
		return $this->extensionStorage;
	}

	/**
	 * Remove an extension from download queue
	 *
	 * @param \TYPO3\CMS\Extensionmanager\Domain\Model\Extension $extension
	 * @param string $stack Stack to remove extension from (download, update or install)
	 * @throws \TYPO3\CMS\Extensionmanager\Exception\ExtensionManagerException
	 * @return void
	 */
	public function removeExtensionFromQueue(\TYPO3\CMS\Extensionmanager\Domain\Model\Extension $extension, $stack = 'download') {
		if (!is_string($stack) || !in_array($stack, array('download', 'update'))) {
			throw new \TYPO3\CMS\Extensionmanager\Exception\ExtensionManagerException('Stack has to be either "download" or "update"', 1342432104);
		}
		if (array_key_exists($stack, $this->extensionStorage) && is_array($this->extensionStorage[$stack])) {
			if (array_key_exists($extension->getExtensionKey(), $this->extensionStorage[$stack])) {
				unset($this->extensionStorage[$stack][$extension->getExtensionKey()]);
			}
		}
	}

	/**
	 * Adds an extension to the install queue for later installation
	 *
	 * @param string $extensionKey
	 * @return void
	 */
	public function addExtensionToInstallQueue($extensionKey) {
		$this->extensionInstallStorage[$extensionKey] = $extensionKey;
	}

	/**
	 * Removes an extension from the install queue
	 *
	 * @param string $extensionKey
	 * @return void
	 */
	public function removeExtensionFromInstallQueue($extensionKey) {
		if (array_key_exists($extensionKey, $this->extensionInstallStorage)) {
			unset($this->extensionInstallStorage[$extensionKey]);
		}
	}

	/**
	 * Adds an extension to the copy queue for later copying
	 *
	 * @param string $extensionKey
	 * @param string $sourceFolder
	 * @return void
	 */
	public function addExtensionToCopyQueue($extensionKey, $sourceFolder) {
		$this->extensionCopyStorage[$extensionKey] = $sourceFolder;
	}

	/**
	 * Remove an extension from extension copy storage
	 *
	 * @param $extensionKey
	 * @return void
	 */
	public function removeExtensionFromCopyQueue($extensionKey) {
		if (array_key_exists($extensionKey, $this->extensionCopyStorage)) {
			unset($this->extensionCopyStorage[$extensionKey]);
		}
	}

	/**
	 * Gets the extension installation queue
	 *
	 * @return array
	 */
	public function getExtensionInstallStorage() {
		return $this->extensionInstallStorage;
	}

	/**
	 * Gets the extension copy queue
	 *
	 * @return array
	 */
	public function getExtensionCopyStorage() {
		return $this->extensionCopyStorage;
	}

}
