<?php
namespace TYPO3\CMS\Core\Category\Collection;

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
 * Category Collection to handle records attached to a category
 *
 * @author Fabien Udriot <fabien.udriot@typo3.org>
 */
class CategoryCollection extends \TYPO3\CMS\Core\Collection\AbstractRecordCollection implements \TYPO3\CMS\Core\Collection\EditableCollectionInterface {

	/**
	 * The table name collections are stored to
	 *
	 * @var string
	 */
	static protected $storageTableName = 'sys_category';

	/**
	 * Name of the categories-relation field (used in the MM_match_fields/fieldname property of the TCA)
	 *
	 * @var string
	 */
	protected $relationFieldName = 'categories';

	/**
	 * Creates this object.
	 *
	 * @param string $tableName Name of the table to be working on
	 * @param string $fieldName Name of the field where the categories relations are defined
	 * @throws \RuntimeException
	 */
	public function __construct($tableName = NULL, $fieldName = NULL) {
		parent::__construct();
		if (!empty($tableName)) {
			$this->setItemTableName($tableName);
		} elseif (empty($this->itemTableName)) {
			throw new \RuntimeException(\TYPO3\CMS\Core\Category\Collection\CategoryCollection::class . ' needs a valid itemTableName.', 1341826168);
		}
		if (!empty($fieldName)) {
			$this->setRelationFieldName($fieldName);
		}
	}

	/**
	 * Creates a new collection objects and reconstitutes the
	 * given database record to the new object.
	 *
	 * @param array $collectionRecord Database record
	 * @param bool $fillItems Populates the entries directly on load, might be bad for memory on large collections
	 * @return \TYPO3\CMS\Core\Category\Collection\CategoryCollection
	 */
	static public function create(array $collectionRecord, $fillItems = FALSE) {
		/** @var $collection \TYPO3\CMS\Core\Category\Collection\CategoryCollection */
		$collection = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
			CategoryCollection::class,
			$collectionRecord['table_name'],
			$collectionRecord['field_name']
		);
		$collection->fromArray($collectionRecord);
		if ($fillItems) {
			$collection->loadContents();
		}
		return $collection;
	}

	/**
	 * Loads the collections with the given id from persistence
	 * For memory reasons, per default only f.e. title, database-table,
	 * identifier (what ever static data is defined) is loaded.
	 * Entries can be load on first access.
	 *
	 * @param int $id Id of database record to be loaded
	 * @param bool $fillItems Populates the entries directly on load, might be bad for memory on large collections
	 * @param string $tableName Name of table from which entries should be loaded
	 * @param string $fieldName Name of the categories relation field
	 * @return \TYPO3\CMS\Core\Collection\CollectionInterface
	 */
	static public function load($id, $fillItems = FALSE, $tableName = '', $fieldName = '') {
		$collectionRecord = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow(
			'*',
			static::$storageTableName,
			'uid = ' . (int)$id . \TYPO3\CMS\Backend\Utility\BackendUtility::deleteClause(static::$storageTableName)
		);
		$collectionRecord['table_name'] = $tableName;
		$collectionRecord['field_name'] = $fieldName;
		return self::create($collectionRecord, $fillItems);
	}

	/**
	 * Gets the collected records in this collection, by
	 * looking up the MM relations of this record to the
	 * table name defined in the local field 'table_name'.
	 *
	 * @return array
	 */
	protected function getCollectedRecords() {
		$relatedRecords = array();
		// Assemble where clause
		$where = 'AND ' . self::$storageTableName . '.uid = ' . (int)$this->getIdentifier();
		// Add condition on tablenames fields
		$where .= ' AND sys_category_record_mm.tablenames = ' . $this->getDatabaseConnection()->fullQuoteStr(
			$this->getItemTableName(),
			'sys_category_record_mm'
		);
		// Add condition on fieldname field
		$where .= ' AND sys_category_record_mm.fieldname = ' . $this->getDatabaseConnection()->fullQuoteStr(
			$this->getRelationFieldName(),
			'sys_category_record_mm'
		);
		$resource = $this->getDatabaseConnection()->exec_SELECT_mm_query(
			$this->getItemTableName() . '.*',
			self::$storageTableName,
			'sys_category_record_mm',
			$this->getItemTableName(),
			$where
		);
		if ($resource) {
			while ($record = $this->getDatabaseConnection()->sql_fetch_assoc($resource)) {
				$relatedRecords[] = $record;
			}
			$this->getDatabaseConnection()->sql_free_result($resource);
		}
		return $relatedRecords;
	}

	/**
	 * Populates the content-entries of the storage
	 * Queries the underlying storage for entries of the collection
	 * and adds them to the collection data.
	 * If the content entries of the storage had not been loaded on creation
	 * ($fillItems = false) this function is to be used for loading the contents
	 * afterwards.
	 *
	 * @return void
	 */
	public function loadContents() {
		$entries = $this->getCollectedRecords();
		$this->removeAll();
		foreach ($entries as $entry) {
			$this->add($entry);
		}
	}

	/**
	 * Returns an array of the persistable properties and contents
	 * which are processable by TCEmain.
	 * for internal usage in persist only.
	 *
	 * @return array
	 */
	protected function getPersistableDataArray() {
		return array(
			'title' => $this->getTitle(),
			'description' => $this->getDescription(),
			'items' => $this->getItemUidList(TRUE)
		);
	}

	/**
	 * Adds on entry to the collection
	 *
	 * @param mixed $data
	 * @return void
	 */
	public function add($data) {
		$this->storage->push($data);
	}

	/**
	 * Adds a set of entries to the collection
	 *
	 * @param \TYPO3\CMS\Core\Collection\CollectionInterface $other
	 * @return void
	 */
	public function addAll(\TYPO3\CMS\Core\Collection\CollectionInterface $other) {
		foreach ($other as $value) {
			$this->add($value);
		}
	}

	/**
	 * Removes the given entry from collection
	 * Note: not the given "index"
	 *
	 * @param mixed $data
	 * @return void
	 */
	public function remove($data) {
		$offset = 0;
		foreach ($this->storage as $value) {
			if ($value == $data) {
				break;
			}
			$offset++;
		}
		$this->storage->offsetUnset($offset);
	}

	/**
	 * Removes all entries from the collection
	 * collection will be empty afterwards
	 *
	 * @return void
	 */
	public function removeAll() {
		$this->storage = new \SplDoublyLinkedList();
	}

	/**
	 * Gets the current available items.
	 *
	 * @return array
	 */
	public function getItems() {
		$itemArray = array();
		/** @var $item \TYPO3\CMS\Core\Resource\File */
		foreach ($this->storage as $item) {
			$itemArray[] = $item;
		}
		return $itemArray;
	}

	/**
	 * Sets the name of the categories relation field
	 *
	 * @param string $field
	 */
	public function setRelationFieldName($field) {
		$this->relationFieldName = $field;
	}

	/**
	 * Gets the name of the categories relation field
	 *
	 * @return string
	 */
	public function getRelationFieldName() {
		return $this->relationFieldName;
	}

	/**
	 * Getter for the storage table name
	 *
	 * @return string
	 */
	static public function getStorageTableName() {
		return self::$storageTableName;
	}

	/**
	 * Getter for the storage items field
	 *
	 * @return string
	 */
	static public function getStorageItemsField() {
		return self::$storageItemsField;
	}

	/**
	 * Gets the database object.
	 *
	 * @return \TYPO3\CMS\Core\Database\DatabaseConnection
	 */
	protected function getDatabaseConnection() {
		return $GLOBALS['TYPO3_DB'];
	}

}
