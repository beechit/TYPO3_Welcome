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

use TYPO3\CMS\Core\Utility;

/**
 * Repository for accessing files
 * it also serves as the public API for the indexing part of files in general
 *
 * @author Benjamin Mack <benni@typo3.org>
 * @author Ingmar Schlecht <ingmar@typo3.org>
 */
class ProcessedFileRepository extends AbstractRepository {

	/**
	 * The main object type of this class. In some cases (fileReference) this
	 * repository can also return FileReference objects, implementing the
	 * common FileInterface.
	 *
	 * @var string
	 */
	protected $objectType = \TYPO3\CMS\Core\Resource\ProcessedFile::class;

	/**
	 * Main File object storage table. Note that this repository also works on
	 * the sys_file_reference table when returning FileReference objects.
	 *
	 * @var string
	 */
	protected $table = 'sys_file_processedfile';

	/**
	 * @var ResourceFactory
	 */
	protected $resourceFactory;

	/**
	 * @var \TYPO3\CMS\Core\Database\DatabaseConnection
	 */
	protected $databaseConnection;

	/**
	 * Creates this object.
	 */
	public function __construct() {
		$this->resourceFactory = Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Resource\ResourceFactory::class);
		$this->databaseConnection = $GLOBALS['TYPO3_DB'];
	}

	/**
	 * Creates a ProcessedFile object from a file object and a processing configuration
	 *
	 * @param FileInterface $originalFile
	 * @param string $taskType
	 * @param array $configuration
	 * @return ProcessedFile
	 */
	public function createNewProcessedFileObject(FileInterface $originalFile, $taskType, array $configuration) {
		return Utility\GeneralUtility::makeInstance(
			$this->objectType,
			$originalFile,
			$taskType,
			$configuration
		);
	}

	/**
	 * @param array $databaseRow
	 * @return ProcessedFile
	 */
	protected function createDomainObject(array $databaseRow) {
		$originalFile = $this->resourceFactory->getFileObject((int)$databaseRow['original']);
		$originalFile->setStorage($this->resourceFactory->getStorageObject($originalFile->getProperty('storage')));
		$taskType = $databaseRow['task_type'];
		$configuration = unserialize($databaseRow['configuration']);

		return Utility\GeneralUtility::makeInstance(
			$this->objectType,
			$originalFile,
			$taskType,
			$configuration,
			$databaseRow
		);
	}

	/**
	 * @param ResourceStorage $storage
	 * @param string $identifier
	 *
	 * @return null|ProcessedFile
	 */
	public function findByStorageAndIdentifier(ResourceStorage $storage, $identifier) {
		$processedFileObject = NULL;
		if ($storage->hasFile($identifier)) {
			$databaseRow = $this->databaseConnection->exec_SELECTgetSingleRow(
				'*',
				$this->table,
				'storage = ' . (int)$storage->getUid() .
				' AND identifier = ' . $this->databaseConnection->fullQuoteStr($identifier, $this->table)
			);
			if ($databaseRow) {
				$processedFileObject = $this->createDomainObject($databaseRow);
			}
		}
		return $processedFileObject;
	}
	/**
	 * Adds a processedfile object in the database
	 *
	 * @param ProcessedFile $processedFile
	 * @return void
	 */
	public function add($processedFile) {
		if ($processedFile->isPersisted()) {
			$this->update($processedFile);
		} else {
			$insertFields = $processedFile->toArray();
			$insertFields['crdate'] = $insertFields['tstamp'] = time();
			$insertFields = $this->cleanUnavailableColumns($insertFields);
			$this->databaseConnection->exec_INSERTquery($this->table, $insertFields);
			$uid = $this->databaseConnection->sql_insert_id();
			$processedFile->updateProperties(array('uid' => $uid));
		}
	}

	/**
	 * Updates an existing file object in the database
	 *
	 * @param ProcessedFile $processedFile
	 * @return void
	 */
	public function update($processedFile) {
		if ($processedFile->isPersisted()) {
			$uid = (int)$processedFile->getUid();
			$updateFields = $this->cleanUnavailableColumns($processedFile->toArray());
			$updateFields['tstamp'] = time();
			$this->databaseConnection->exec_UPDATEquery($this->table, 'uid=' . (int)$uid, $updateFields);
		}
	}

	/**
	 * @param \TYPO3\CMS\Core\Resource\File|\TYPO3\CMS\Core\Resource\FileInterface $file
	 * @param string $taskType The task that should be executed on the file
	 * @param array $configuration
	 *
	 * @return ProcessedFile
	 */
	public function findOneByOriginalFileAndTaskTypeAndConfiguration(FileInterface $file, $taskType, array $configuration) {
		$databaseRow = $this->databaseConnection->exec_SELECTgetSingleRow(
			'*',
			$this->table,
			'original=' . (int)$file->getUid() .
				' AND task_type=' . $this->databaseConnection->fullQuoteStr($taskType, $this->table) .
				' AND configurationsha1=' . $this->databaseConnection->fullQuoteStr(sha1(serialize($configuration)), $this->table)
		);

		if (is_array($databaseRow)) {
			$processedFile = $this->createDomainObject($databaseRow);
		} else {
			$processedFile = $this->createNewProcessedFileObject($file, $taskType, $configuration);
		}
		return $processedFile;
	}

	/**
	 * @param FileInterface $file
	 * @return ProcessedFile[]
	 * @throws \InvalidArgumentException
	 */
	public function findAllByOriginalFile(FileInterface $file) {
		if (!$file instanceof File) {
			throw new \InvalidArgumentException('Parameter is no File object but got type "'
				. (is_object($file) ? get_class($file) : gettype($file)) . '"', 1382006142);
		}
		$whereClause = 'original=' . (int)$file->getUid();
		$rows = $this->databaseConnection->exec_SELECTgetRows('*', $this->table, $whereClause);

		$itemList = array();
		if ($rows !== NULL) {
			foreach ($rows as $row) {
				$itemList[] = $this->createDomainObject($row);
			}
		}
		return $itemList;
	}


	/**
	 * Removes all array keys which cannot be persisted
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	protected function cleanUnavailableColumns(array $data) {
		return array_intersect_key($data, $this->databaseConnection->admin_get_fields($this->table));
	}

}