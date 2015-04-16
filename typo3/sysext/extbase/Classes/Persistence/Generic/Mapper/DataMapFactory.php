<?php
namespace TYPO3\CMS\Extbase\Persistence\Generic\Mapper;

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
 * A factory for a data map to map a single table configured in $TCA on a domain object.
 */
class DataMapFactory implements \TYPO3\CMS\Core\SingletonInterface {

	/**
	 * @var \TYPO3\CMS\Extbase\Reflection\ReflectionService
	 * @inject
	 */
	protected $reflectionService;

	/**
	 * @var \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface
	 * @inject
	 */
	protected $configurationManager;

	/**
	 * @var \TYPO3\CMS\Extbase\Object\ObjectManagerInterface
	 * @inject
	 */
	protected $objectManager;

	/**
	 * @var \TYPO3\CMS\Core\Cache\CacheManager
	 * @inject
	 */
	protected $cacheManager;

	/**
	 * @var \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend
	 */
	protected $dataMapCache;

	/**
	 * Lifecycle method
	 *
	 * @return void
	 */
	public function initializeObject() {
		$this->dataMapCache = $this->cacheManager->getCache('extbase_datamapfactory_datamap');
	}

	/**
	 * Builds a data map by adding column maps for all the configured columns in the $TCA.
	 * It also resolves the type of values the column is holding and the typo of relation the column
	 * represents.
	 *
	 * @param string $className The class name you want to fetch the Data Map for
	 * @return \TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMap The data map
	 */
	public function buildDataMap($className) {
		$dataMap = $this->dataMapCache->get(str_replace('\\', '%', $className));
		if ($dataMap === FALSE) {
			$dataMap = $this->buildDataMapInternal($className);
			$this->dataMapCache->set(str_replace('\\', '%', $className), $dataMap);
		}
		return $dataMap;
	}

	/**
	 * Builds a data map by adding column maps for all the configured columns in the $TCA.
	 * It also resolves the type of values the column is holding and the typo of relation the column
	 * represents.
	 *
	 * @param string $className The class name you want to fetch the Data Map for
	 * @throws \TYPO3\CMS\Extbase\Persistence\Generic\Exception\InvalidClassException
	 * @return \TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMap The data map
	 */
	protected function buildDataMapInternal($className) {
		if (!class_exists($className)) {
			throw new \TYPO3\CMS\Extbase\Persistence\Generic\Exception\InvalidClassException('Could not find class definition for name "' . $className . '". This could be caused by a mis-spelling of the class name in the class definition.');
		}
		$recordType = NULL;
		$subclasses = array();
		$tableName = $this->resolveTableName($className);
		$columnMapping = array();
		$frameworkConfiguration = $this->configurationManager->getConfiguration(\TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK);
		$classSettings = $frameworkConfiguration['persistence']['classes'][$className];
		if ($classSettings !== NULL) {
			if (isset($classSettings['subclasses']) && is_array($classSettings['subclasses'])) {
				$subclasses = $this->resolveSubclassesRecursive($frameworkConfiguration['persistence']['classes'], $classSettings['subclasses']);
			}
			if (isset($classSettings['mapping']['recordType']) && $classSettings['mapping']['recordType'] !== '') {
				$recordType = $classSettings['mapping']['recordType'];
			}
			if (isset($classSettings['mapping']['tableName']) && $classSettings['mapping']['tableName'] !== '') {
				$tableName = $classSettings['mapping']['tableName'];
			}
			$classHierarchy = array_merge(array($className), class_parents($className));
			foreach ($classHierarchy as $currentClassName) {
				if (in_array($currentClassName, array(\TYPO3\CMS\Extbase\DomainObject\AbstractEntity::class, \TYPO3\CMS\Extbase\DomainObject\AbstractValueObject::class))) {
					break;
				}
				$currentClassSettings = $frameworkConfiguration['persistence']['classes'][$currentClassName];
				if ($currentClassSettings !== NULL) {
					if (isset($currentClassSettings['mapping']['columns']) && is_array($currentClassSettings['mapping']['columns'])) {
						\TYPO3\CMS\Core\Utility\ArrayUtility::mergeRecursiveWithOverrule($columnMapping, $currentClassSettings['mapping']['columns'], TRUE, FALSE);
					}
				}
			}
		}
		/** @var $dataMap \TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMap */
		$dataMap = $this->objectManager->get(\TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMap::class, $className, $tableName, $recordType, $subclasses);
		$dataMap = $this->addMetaDataColumnNames($dataMap, $tableName);
		// $classPropertyNames = $this->reflectionService->getClassPropertyNames($className);
		$tcaColumnsDefinition = $this->getColumnsDefinition($tableName);
		\TYPO3\CMS\Core\Utility\ArrayUtility::mergeRecursiveWithOverrule($tcaColumnsDefinition, $columnMapping);
		// @todo Is this is too powerful?

		foreach ($tcaColumnsDefinition as $columnName => $columnDefinition) {
			if (isset($columnDefinition['mapOnProperty'])) {
				$propertyName = $columnDefinition['mapOnProperty'];
			} else {
				$propertyName = \TYPO3\CMS\Core\Utility\GeneralUtility::underscoredToLowerCamelCase($columnName);
			}
			// if (in_array($propertyName, $classPropertyNames)) {
			// @todo Enable check for property existence
			$columnMap = $this->createColumnMap($columnName, $propertyName);
			$propertyMetaData = $this->reflectionService->getClassSchema($className)->getProperty($propertyName);
			$columnMap = $this->setType($columnMap, $columnDefinition['config']);
			$columnMap = $this->setRelations($columnMap, $columnDefinition['config'], $propertyMetaData);
			$columnMap = $this->setFieldEvaluations($columnMap, $columnDefinition['config']);
			$dataMap->addColumnMap($columnMap);
		}
		return $dataMap;
	}

	/**
	 * Resolve the table name for the given class name
	 *
	 * @param string $className
	 * @return string The table name
	 */
	protected function resolveTableName($className) {
		$className = ltrim($className, '\\');
		if (strpos($className, '\\') !== FALSE) {
			$classNameParts = explode('\\', $className, 6);
			// Skip vendor and product name for core classes
			if (strpos($className, 'TYPO3\\CMS\\') === 0) {
				$classPartsToSkip = 2;
			} else {
				$classPartsToSkip = 1;
			}
			$tableName = 'tx_' . strtolower(implode('_', array_slice($classNameParts, $classPartsToSkip)));
		} else {
			$tableName = strtolower($className);
		}
		return $tableName;
	}

	/**
	 * Resolves all subclasses for the given set of (sub-)classes.
	 * The whole classes configuration is used to determine all subclasses recursively.
	 *
	 * @param array $classesConfiguration The framework configuration part [persistence][classes].
	 * @param array $subclasses An array of subclasses defined via TypoScript
	 * @return array An numeric array that contains all available subclasses-strings as values.
	 */
	protected function resolveSubclassesRecursive(array $classesConfiguration, array $subclasses) {
		$allSubclasses = array();
		foreach ($subclasses as $subclass) {
			$allSubclasses[] = $subclass;
			if (isset($classesConfiguration[$subclass]['subclasses']) && is_array($classesConfiguration[$subclass]['subclasses'])) {
				$childSubclasses = $this->resolveSubclassesRecursive($classesConfiguration, $classesConfiguration[$subclass]['subclasses']);
				$allSubclasses = array_merge($allSubclasses, $childSubclasses);
			}
		}
		return $allSubclasses;
	}

	/**
	 * Returns the TCA ctrl section of the specified table; or NULL if not set
	 *
	 * @param string $tableName An optional table name to fetch the columns definition from
	 * @return array The TCA columns definition
	 */
	protected function getControlSection($tableName) {
		return is_array($GLOBALS['TCA'][$tableName]['ctrl']) ? $GLOBALS['TCA'][$tableName]['ctrl'] : NULL;
	}

	/**
	 * Returns the TCA columns array of the specified table
	 *
	 * @param string $tableName An optional table name to fetch the columns definition from
	 * @return array The TCA columns definition
	 */
	protected function getColumnsDefinition($tableName) {
		return is_array($GLOBALS['TCA'][$tableName]['columns']) ? $GLOBALS['TCA'][$tableName]['columns'] : array();
	}

	/**
	 * @param DataMap $dataMap
	 * @param string $tableName
	 * @return DataMap
	 */
	protected function addMetaDataColumnNames(\TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMap $dataMap, $tableName) {
		$controlSection = $GLOBALS['TCA'][$tableName]['ctrl'];
		$dataMap->setPageIdColumnName('pid');
		if (isset($controlSection['tstamp'])) {
			$dataMap->setModificationDateColumnName($controlSection['tstamp']);
		}
		if (isset($controlSection['crdate'])) {
			$dataMap->setCreationDateColumnName($controlSection['crdate']);
		}
		if (isset($controlSection['cruser_id'])) {
			$dataMap->setCreatorColumnName($controlSection['cruser_id']);
		}
		if (isset($controlSection['delete'])) {
			$dataMap->setDeletedFlagColumnName($controlSection['delete']);
		}
		if (isset($controlSection['languageField'])) {
			$dataMap->setLanguageIdColumnName($controlSection['languageField']);
		}
		if (isset($controlSection['transOrigPointerField'])) {
			$dataMap->setTranslationOriginColumnName($controlSection['transOrigPointerField']);
		}
		if (isset($controlSection['type'])) {
			$dataMap->setRecordTypeColumnName($controlSection['type']);
		}
		if (isset($controlSection['rootLevel'])) {
			$dataMap->setRootLevel($controlSection['rootLevel']);
		}
		if (isset($controlSection['is_static'])) {
			$dataMap->setIsStatic($controlSection['is_static']);
		}
		if (isset($controlSection['enablecolumns']['disabled'])) {
			$dataMap->setDisabledFlagColumnName($controlSection['enablecolumns']['disabled']);
		}
		if (isset($controlSection['enablecolumns']['starttime'])) {
			$dataMap->setStartTimeColumnName($controlSection['enablecolumns']['starttime']);
		}
		if (isset($controlSection['enablecolumns']['endtime'])) {
			$dataMap->setEndTimeColumnName($controlSection['enablecolumns']['endtime']);
		}
		if (isset($controlSection['enablecolumns']['fe_group'])) {
			$dataMap->setFrontEndUserGroupColumnName($controlSection['enablecolumns']['fe_group']);
		}
		return $dataMap;
	}

	/**
	 * Set the table column type
	 *
	 * @param ColumnMap $columnMap
	 * @param array $columnConfiguration
	 * @return ColumnMap
	 */
	protected function setType(ColumnMap $columnMap, $columnConfiguration) {
		$tableColumnType = (isset($columnConfiguration['type'])) ? $columnConfiguration['type'] : NULL;
		$columnMap->setType(\TYPO3\CMS\Core\DataHandling\TableColumnType::cast($tableColumnType));
		$tableColumnSubType = (isset($columnConfiguration['internal_type'])) ? $columnConfiguration['internal_type'] : NULL;
		$columnMap->setInternalType(\TYPO3\CMS\Core\DataHandling\TableColumnSubType::cast($tableColumnSubType));

		return $columnMap;
	}

	/**
	 * This method tries to determine the type of type of relation to other tables and sets it based on
	 * the $TCA column configuration
	 *
	 * @param ColumnMap $columnMap The column map
	 * @param string $columnConfiguration The column configuration from $TCA
	 * @param array $propertyMetaData The property metadata as delivered by the reflection service
	 * @return ColumnMap
	 */
	protected function setRelations(ColumnMap $columnMap, $columnConfiguration, $propertyMetaData) {
		if (isset($columnConfiguration)) {
			if (isset($columnConfiguration['MM'])) {
				$columnMap = $this->setManyToManyRelation($columnMap, $columnConfiguration);
			} elseif (isset($propertyMetaData['elementType'])) {
				$columnMap = $this->setOneToManyRelation($columnMap, $columnConfiguration);
			} elseif (isset($propertyMetaData['type']) && strpbrk($propertyMetaData['type'], '_\\') !== FALSE) {
				$columnMap = $this->setOneToOneRelation($columnMap, $columnConfiguration);
			} elseif (isset($columnConfiguration['type']) && $columnConfiguration['type'] === 'select' && isset($columnConfiguration['maxitems']) && $columnConfiguration['maxitems'] > 1) {
				$columnMap->setTypeOfRelation(ColumnMap::RELATION_HAS_MANY);
			} else {
				$columnMap->setTypeOfRelation(ColumnMap::RELATION_NONE);
			}

		} else {
			$columnMap->setTypeOfRelation(ColumnMap::RELATION_NONE);
		}
		return $columnMap;
	}

	/**
	 * Sets field evaluations based on $TCA column configuration.
	 *
	 * @param ColumnMap $columnMap The column map
	 * @param NULL|array $columnConfiguration The column configuration from $TCA
	 * @return ColumnMap
	 */
	protected function setFieldEvaluations(ColumnMap $columnMap, array $columnConfiguration = NULL) {
		if (!empty($columnConfiguration['eval'])) {
			$fieldEvaluations = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', $columnConfiguration['eval'], TRUE);
			$dateTimeEvaluations = array('date', 'datetime');

			if (count(array_intersect($dateTimeEvaluations, $fieldEvaluations)) > 0 && !empty($columnConfiguration['dbType'])) {
				$columnMap->setDateTimeStorageFormat($columnConfiguration['dbType']);
			}
		}

		return $columnMap;
	}

	/**
	 * This method sets the configuration for a 1:1 relation based on
	 * the $TCA column configuration
	 *
	 * @param string|ColumnMap $columnMap The column map
	 * @param string $columnConfiguration The column configuration from $TCA
	 * @return ColumnMap
	 */
	protected function setOneToOneRelation(ColumnMap $columnMap, $columnConfiguration) {
		$columnMap->setTypeOfRelation(ColumnMap::RELATION_HAS_ONE);
		$columnMap->setChildTableName($columnConfiguration['foreign_table']);
		$columnMap->setChildTableWhereStatement($columnConfiguration['foreign_table_where']);
		$columnMap->setChildSortByFieldName($columnConfiguration['foreign_sortby']);
		$columnMap->setParentKeyFieldName($columnConfiguration['foreign_field']);
		$columnMap->setParentTableFieldName($columnConfiguration['foreign_table_field']);
		if (is_array($columnConfiguration['foreign_match_fields'])) {
			$columnMap->setRelationTableMatchFields($columnConfiguration['foreign_match_fields']);
		}
		return $columnMap;
	}

	/**
	 * This method sets the configuration for a 1:n relation based on
	 * the $TCA column configuration
	 *
	 * @param string|ColumnMap $columnMap The column map
	 * @param string $columnConfiguration The column configuration from $TCA
	 * @return ColumnMap
	 */
	protected function setOneToManyRelation(ColumnMap $columnMap, $columnConfiguration) {
		$columnMap->setTypeOfRelation(ColumnMap::RELATION_HAS_MANY);
		$columnMap->setChildTableName($columnConfiguration['foreign_table']);
		$columnMap->setChildTableWhereStatement($columnConfiguration['foreign_table_where']);
		$columnMap->setChildSortByFieldName($columnConfiguration['foreign_sortby']);
		$columnMap->setParentKeyFieldName($columnConfiguration['foreign_field']);
		$columnMap->setParentTableFieldName($columnConfiguration['foreign_table_field']);
		if (is_array($columnConfiguration['foreign_match_fields'])) {
			$columnMap->setRelationTableMatchFields($columnConfiguration['foreign_match_fields']);
		}
		return $columnMap;
	}

	/**
	 * This method sets the configuration for a m:n relation based on
	 * the $TCA column configuration
	 *
	 * @param string|ColumnMap $columnMap The column map
	 * @param string $columnConfiguration The column configuration from $TCA
	 * @throws \TYPO3\CMS\Extbase\Persistence\Generic\Exception\UnsupportedRelationException
	 * @return ColumnMap
	 */
	protected function setManyToManyRelation(ColumnMap $columnMap, $columnConfiguration) {
		if (isset($columnConfiguration['MM'])) {
			$columnMap->setTypeOfRelation(ColumnMap::RELATION_HAS_AND_BELONGS_TO_MANY);
			$columnMap->setChildTableName($columnConfiguration['foreign_table']);
			$columnMap->setChildTableWhereStatement($columnConfiguration['foreign_table_where']);
			$columnMap->setRelationTableName($columnConfiguration['MM']);
			if (is_array($columnConfiguration['MM_match_fields'])) {
				$columnMap->setRelationTableMatchFields($columnConfiguration['MM_match_fields']);
			}
			if (is_array($columnConfiguration['MM_insert_fields'])) {
				$columnMap->setRelationTableInsertFields($columnConfiguration['MM_insert_fields']);
			}
			$columnMap->setRelationTableWhereStatement($columnConfiguration['MM_table_where']);
			if (!empty($columnConfiguration['MM_opposite_field'])) {
				$columnMap->setParentKeyFieldName('uid_foreign');
				$columnMap->setChildKeyFieldName('uid_local');
				$columnMap->setChildSortByFieldName('sorting_foreign');
			} else {
				$columnMap->setParentKeyFieldName('uid_local');
				$columnMap->setChildKeyFieldName('uid_foreign');
				$columnMap->setChildSortByFieldName('sorting');
			}
		} else {
			throw new \TYPO3\CMS\Extbase\Persistence\Generic\Exception\UnsupportedRelationException('The given information to build a many-to-many-relation was not sufficient. Check your TCA definitions. mm-relations with IRRE must have at least a defined "MM" or "foreign_selector".', 1268817963);
		}
		if ($this->getControlSection($columnMap->getRelationTableName()) !== NULL) {
			$columnMap->setRelationTablePageIdColumnName('pid');
		}
		return $columnMap;
	}

	/**
	 * Creates the ColumnMap object for the given columnName and propertyName
	 *
	 * @param string $columnName
	 * @param string $propertyName
	 *
	 * @return ColumnMap
	 */
	protected function createColumnMap($columnName, $propertyName) {
		return $this->objectManager->get(\TYPO3\CMS\Extbase\Persistence\Generic\Mapper\ColumnMap::class, $columnName, $propertyName);
	}

}
