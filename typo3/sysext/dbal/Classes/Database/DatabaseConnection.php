<?php
namespace TYPO3\CMS\Dbal\Database;

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
 * TYPO3 database abstraction layer
 *
 * @author Kasper Skårhøj <kasper@typo3.com>
 * @author Karsten Dambekalns <k.dambekalns@fishfarm.de>
 * @author Xavier Perseguers <xavier@typo3.org>
 */
class DatabaseConnection extends \TYPO3\CMS\Core\Database\DatabaseConnection {

	/**
	 * @var bool
	 */
	protected $printErrors = FALSE;

	/**
	 * Enable output of SQL errors after query executions.
	 * @var bool
	 */
	public $debug = FALSE;

	/**
	 * Enable debug mode
	 * @var bool
	 */
	public $conf = array();

	/**
	 * Configuration array, copied from TYPO3_CONF_VARS in constructor.
	 * @var array
	 */
	public $mapping = array();

	/**
	 * See manual
	 * @var array
	 */
	protected $table2handlerKeys = array();

	/**
	 * See manual
	 * @var array
	 */
	public $handlerCfg = array(
		'_DEFAULT' => array(
			'type' => 'native',
			'config' => array(
				'username' => '',
				// Set by default (overridden)
				'password' => '',
				// Set by default (overridden)
				'host' => '',
				// Set by default (overridden)
				'database' => '',
				// Set by default (overridden)
				'driver' => '',
				// ONLY "adodb" type; eg. "mysql"
				'sequenceStart' => 1,
				// ONLY "adodb", first number in sequences/serials/...
				'useNameQuote' => 0,
				// ONLY "adodb", whether to use NameQuote() method from ADOdb to quote names
				'quoteClob' => FALSE
			)
		)
	);

	/**
	 * Contains instance of the handler objects as they are created.
	 *
	 * Exception is the native mySQL calls, which are registered as an array with keys
	 * "handlerType" = "native" and
	 * "link" pointing to the link object of the connection.
	 *
	 * @var array
	 */
	public $handlerInstance = array();

	/**
	 * Storage of the handler key of last ( SELECT) query - used for subsequent fetch-row calls etc.
	 * @var string
	 */
	public $lastHandlerKey = '';

	/**
	 * Storage of last SELECT query
	 * @var string
	 */
	protected $lastQuery = '';

	/**
	 * The last parsed query array
	 * @var array
	 */
	protected $lastParsedAndMappedQueryArray = array();

	/**
	 * @var array
	 */
	protected $resourceIdToTableNameMap = array();

	/**
	 * @var array
	 */
	protected $cache_handlerKeyFromTableList = array();

	/**
	 * @var array
	 */
	protected $cache_mappingFromTableList = array();

	/**
	 * parsed SQL from standard DB dump file
	 * @var array
	 */
	public $cache_autoIncFields = array();

	/**
	 * @var array
	 */
	public $cache_fieldType = array();

	/**
	 * @var array
	 */
	public $cache_primaryKeys = array();

	/**
	 * @var string
	 */
	protected $cacheIdentifier = 'DatabaseConnection_fieldInfo';

	/**
	 * SQL parser
	 *
	 * @var \TYPO3\CMS\Core\Database\SqlParser
	 */
	public $SQLparser;

	/**
	 * @var \TYPO3\CMS\Install\Service\SqlSchemaMigrationService
	 */
	protected $installerSql = NULL;

	/**
	 * Cache for queries
	 *
	 * @var \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend
	 */
	protected $queryCache;

	/**
	 * mysql_field_type compatibility map
	 * taken from: http://www.php.net/manual/en/mysqli-result.fetch-field-direct.php#89117
	 * Constant numbers see http://php.net/manual/en/mysqli.constants.php
	 *
	 * @var array
	 */
	protected $mysqlDataTypeMapping = array(
		MYSQLI_TYPE_TINY => 'tinyint',
		MYSQLI_TYPE_CHAR => 'tinyint',
		MYSQLI_TYPE_SHORT => 'smallint',
		MYSQLI_TYPE_LONG => 'int',
		MYSQLI_TYPE_FLOAT => 'float',
		MYSQLI_TYPE_DOUBLE => 'double',
		MYSQLI_TYPE_TIMESTAMP => 'timestamp',
		MYSQLI_TYPE_LONGLONG => 'bigint',
		MYSQLI_TYPE_INT24 => 'mediumint',
		MYSQLI_TYPE_DATE => 'date',
		MYSQLI_TYPE_NEWDATE => 'date',
		MYSQLI_TYPE_TIME => 'time',
		MYSQLI_TYPE_DATETIME => 'datetime',
		MYSQLI_TYPE_YEAR => 'year',
		MYSQLI_TYPE_BIT => 'bit',
		MYSQLI_TYPE_INTERVAL => 'interval',
		MYSQLI_TYPE_ENUM => 'enum',
		MYSQLI_TYPE_SET => 'set',
		MYSQLI_TYPE_TINY_BLOB => 'blob',
		MYSQLI_TYPE_MEDIUM_BLOB => 'blob',
		MYSQLI_TYPE_LONG_BLOB => 'blob',
		MYSQLI_TYPE_BLOB => 'blob',
		MYSQLI_TYPE_VAR_STRING => 'varchar',
		MYSQLI_TYPE_STRING => 'char',
		MYSQLI_TYPE_DECIMAL => 'decimal',
		MYSQLI_TYPE_NEWDECIMAL => 'decimal',
		MYSQLI_TYPE_GEOMETRY => 'geometry'
	);

	/**
	 * @var Specifics\AbstractSpecifics
	 */
	protected $dbmsSpecifics;

	/**
	 * Constructor.
	 * Creates SQL parser object and imports configuration from $TYPO3_CONF_VARS['EXTCONF']['dbal']
	 */
	public function __construct() {
		// Set SQL parser object for internal use:
		$this->SQLparser = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\SqlParser::class, $this);
		$this->installerSql = GeneralUtility::makeInstance(\TYPO3\CMS\Install\Service\SqlSchemaMigrationService::class);
		$this->queryCache = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Cache\CacheManager::class)->getCache('dbal');
		// Set internal variables with configuration:
		$this->conf = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['dbal'];
	}

	/**
	 * Initialize the database connection
	 *
	 * @return void
	 */
	public function initialize() {
		// Set outside configuration:
		if (isset($this->conf['mapping'])) {
			$this->mapping = $this->conf['mapping'];
		}
		if (isset($this->conf['table2handlerKeys'])) {
			$this->table2handlerKeys = $this->conf['table2handlerKeys'];
		}
		if (isset($this->conf['handlerCfg'])) {
			$this->handlerCfg = $this->conf['handlerCfg'];

			if (isset($this->handlerCfg['_DEFAULT']['config']['driver'])) {
				// load DBMS specifics
				$driver = $this->handlerCfg['_DEFAULT']['config']['driver'];
				$className = 'TYPO3\\CMS\\Dbal\\Database\\Specifics\\' . ucfirst(strtolower($driver));
				if (class_exists($className)) {
					if (!is_subclass_of($className, Specifics\AbstractSpecifics::class)) {
						throw new \InvalidArgumentException($className . ' must inherit from ' . Specifics\AbstractSpecifics::class, 1416919866);
					}
					$this->dbmsSpecifics = GeneralUtility::makeInstance($className);
				}
			}
		}
		$this->cacheFieldInfo();
		// Debugging settings:
		$this->printErrors = !empty($this->conf['debugOptions']['printErrors']);
		$this->debug = !empty($this->conf['debugOptions']['enabled']);
	}

	/**
	 * Gets the DBMS specifics object
	 *
	 * @return Specifics\AbstractSpecifics
	 */
	public function getSpecifics() {
		return $this->dbmsSpecifics;
	}

	/**
	 * @return \TYPO3\CMS\Core\Cache\Frontend\PhpFrontend
	 */
	protected function getFieldInfoCache() {
		return GeneralUtility::makeInstance(\TYPO3\CMS\Core\Cache\CacheManager::class)->getCache('cache_phpcode');
	}

	/**
	 * Clears the cached field information file.
	 *
	 * @return void
	 */
	public function clearCachedFieldInfo() {
		$this->getFieldInfoCache()->flushByTag('DatabaseConnection');
	}

	/**
	 * Caches the field information.
	 *
	 * @return void
	 */
	public function cacheFieldInfo() {
		$phpCodeCache = $this->getFieldInfoCache();
		// try to fetch cache
		// cache is flushed when admin_query() is called
		if ($phpCodeCache->has($this->cacheIdentifier)) {
			$fieldInformation = $phpCodeCache->requireOnce($this->cacheIdentifier);
			$this->cache_autoIncFields = $fieldInformation['incFields'];
			$this->cache_fieldType = $fieldInformation['fieldTypes'];
			$this->cache_primaryKeys = $fieldInformation['primaryKeys'];
		} else {
			$this->analyzeCachingTables();
			$this->analyzeExtensionTables();
			$completeFieldInformation = $this->getCompleteFieldInformation();
			$phpCodeCache->set($this->cacheIdentifier, $this->getCacheableString($completeFieldInformation), array('DatabaseConnection'));
		}
	}

	/**
	 * Loop through caching configurations
	 * to find the usage of database backends and
	 * parse and analyze table definitions
	 *
	 * @return void
	 */
	protected function analyzeCachingTables() {
		$this->parseAndAnalyzeSql(\TYPO3\CMS\Core\Cache\Cache::getDatabaseTableDefinitions());
	}

	/**
	 * Loop over all installed extensions
	 * parse and analyze table definitions (if any)
	 *
	 * @return void
	 */
	protected function analyzeExtensionTables() {
		if (isset($GLOBALS['TYPO3_LOADED_EXT']) && (is_array($GLOBALS['TYPO3_LOADED_EXT']) || $GLOBALS['TYPO3_LOADED_EXT'] instanceof \ArrayAccess)) {
			foreach ($GLOBALS['TYPO3_LOADED_EXT'] as $extensionConfiguration) {
				$isArray = (is_array($extensionConfiguration) || $extensionConfiguration instanceof \ArrayAccess);
				if (!$isArray || ($isArray && !isset($extensionConfiguration['ext_tables.sql']))) {
					continue;
				}
				$extensionsSql = file_get_contents($extensionConfiguration['ext_tables.sql']);
				$this->parseAndAnalyzeSql($extensionsSql);
			}
		}
	}

	/**
	 * Parse and analyze given SQL string
	 *
	 * @param $sql
	 * @return void
	 */
	protected function parseAndAnalyzeSql($sql) {
		$parsedSql = $this->installerSql->getFieldDefinitions_fileContent($sql);
		$this->analyzeFields($parsedSql);
	}

	/**
	 * Returns all field information gathered during
	 * analyzing all tables and fields.
	 *
	 * @return array
	 */
	protected function getCompleteFieldInformation() {
		return array('incFields' => $this->cache_autoIncFields, 'fieldTypes' => $this->cache_fieldType, 'primaryKeys' => $this->cache_primaryKeys);
	}

	/**
	 * Creates a PHP code representation of the array that can be cached
	 * in the PHP code cache.
	 *
	 * @param array $fieldInformation
	 * @return string
	 */
	protected function getCacheableString(array $fieldInformation) {
		$cacheString = 'return ';
		$cacheString .= var_export($fieldInformation, TRUE);
		$cacheString .= ';';
		return $cacheString;
	}

	/**
	 * Analyzes fields and adds the extracted information to the field type, auto increment and primary key info caches.
	 *
	 * @param array $parsedExtSQL The output produced by \TYPO3\CMS\Install\Service\SqlSchemaMigrationService->getFieldDefinitions_fileContent()
	 * @return void
	 */
	protected function analyzeFields($parsedExtSQL) {
		foreach ($parsedExtSQL as $table => $tdef) {
			// check if table is mapped
			if (isset($this->mapping[$table])) {
				$table = $this->mapping[$table]['mapTableName'];
			}
			if (is_array($tdef['fields'])) {
				foreach ($tdef['fields'] as $field => $fdefString) {
					$fdef = $this->SQLparser->parseFieldDef($fdefString);
					$fieldType = isset($fdef['fieldType']) ? $fdef['fieldType'] : '';
					$this->cache_fieldType[$table][$field]['type'] = $fieldType;
					$this->cache_fieldType[$table][$field]['metaType'] = $this->MySQLMetaType($fieldType);
					$this->cache_fieldType[$table][$field]['notnull'] = isset($fdef['featureIndex']['NOTNULL']) && !$this->SQLparser->checkEmptyDefaultValue($fdef['featureIndex']) ? 1 : 0;
					if (isset($fdef['featureIndex']['DEFAULT'])) {
						$default = $fdef['featureIndex']['DEFAULT']['value'][0];
						if (isset($fdef['featureIndex']['DEFAULT']['value'][1])) {
							$default = $fdef['featureIndex']['DEFAULT']['value'][1] . $default . $fdef['featureIndex']['DEFAULT']['value'][1];
						}
						$this->cache_fieldType[$table][$field]['default'] = $default;
					}
					if (isset($fdef['featureIndex']['AUTO_INCREMENT'])) {
						$this->cache_autoIncFields[$table] = $field;
					}
					if (isset($tdef['keys']['PRIMARY'])) {
						$this->cache_primaryKeys[$table] = substr($tdef['keys']['PRIMARY'], 13, -1);
					}
				}
			}
		}
	}

	/**
	 * This function builds all definitions for mapped tables and fields
	 *
	 * @param array $fieldInfo
	 * @return array
	 *
	 * @see cacheFieldInfo()
	 */
	protected function mapCachedFieldInfo(array $fieldInfo) {
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['dbal']['mapping'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['dbal']['mapping'] as $mappedTable => $mappedConf) {
				if (array_key_exists($mappedTable, $fieldInfo['incFields'])) {
					$mappedTableAlias = $mappedConf['mapTableName'];
					if (isset($mappedConf['mapFieldNames'][$fieldInfo['incFields'][$mappedTable]])) {
						$fieldInfo['incFields'][$mappedTableAlias] = $mappedConf['mapFieldNames'][$fieldInfo['incFields'][$mappedTable]];
					} else {
						$fieldInfo['incFields'][$mappedTableAlias] = $fieldInfo['incFields'][$mappedTable];
					}
				}
				if (array_key_exists($mappedTable, $fieldInfo['fieldTypes'])) {
					$tempMappedFieldConf = array();
					foreach ($fieldInfo['fieldTypes'][$mappedTable] as $field => $fieldConf) {
						$tempMappedFieldConf[$mappedConf['mapFieldNames'][$field]] = $fieldConf;
					}
					$fieldInfo['fieldTypes'][$mappedConf['mapTableName']] = $tempMappedFieldConf;
				}
				if (array_key_exists($mappedTable, $fieldInfo['primaryKeys'])) {
					$mappedTableAlias = $mappedConf['mapTableName'];
					if (isset($mappedConf['mapFieldNames'][$fieldInfo['primaryKeys'][$mappedTable]])) {
						$fieldInfo['primaryKeys'][$mappedTableAlias] = $mappedConf['mapFieldNames'][$fieldInfo['primaryKeys'][$mappedTable]];
					} else {
						$fieldInfo['primaryKeys'][$mappedTableAlias] = $fieldInfo['primaryKeys'][$mappedTable];
					}
				}
			}
		}
		return $fieldInfo;
	}

	/************************************
	 *
	 * Query Building (Overriding parent methods)
	 * These functions are extending counterparts in the parent class.
	 *
	 **************************************/
	/*
	 * From the ADOdb documentation, this is what we do (_Execute for SELECT, _query for the other actions)Execute()
	 * is the default way to run queries. You can use the low-level functions _Execute() and _query() to reduce query overhead.
	 * Both these functions share the same parameters as Execute().If you do not have any bind parameters or your database
	 * supports binding (without emulation), then you can call _Execute() directly.
	 * Calling this function bypasses bind emulation. Debugging is still supported in _Execute().If you do not require
	 * debugging facilities nor emulated binding, and do not require a recordset to be returned, then you can call _query.
	 * This is great for inserts, updates and deletes. Calling this function bypasses emulated binding, debugging,
	 * and recordset handling. Either the resultid, TRUE or FALSE are returned by _query().
	 */

	/**
	 * Creates and executes an INSERT SQL-statement for $table from the array with field/value pairs $fields_values.
	 * Using this function specifically allows us to handle BLOB and CLOB fields depending on DB
	 *
	 * @param string $table Table name
	 * @param array $fields_values Field values as key=>value pairs. Values will be escaped internally. Typically you would fill an array like "$insertFields" with 'fieldname'=>'value' and pass it to this function as argument.
	 * @param bool|array|string $no_quote_fields See fullQuoteArray()
	 * @return bool|\mysqli_result|object MySQLi result object / DBAL object
	 * @throws \RuntimeException
	 */
	public function exec_INSERTquery($table, $fields_values, $no_quote_fields = FALSE) {
		$pt = $this->debug ? GeneralUtility::milliseconds() : 0;
		// Do field mapping if needed:
		$ORIG_tableName = $table;
		if ($tableArray = $this->map_needMapping($table)) {
			// Field mapping of array:
			$fields_values = $this->map_assocArray($fields_values, $tableArray);
			// Table name:
			if ($this->mapping[$table]['mapTableName']) {
				$table = $this->mapping[$table]['mapTableName'];
			}
		}
		// Select API:
		$this->lastHandlerKey = $this->handler_getFromTableList($table);
		$hType = (string)$this->handlerCfg[$this->lastHandlerKey]['type'];
		$sqlResult = NULL;
		switch ($hType) {
			case 'native':
				$this->lastQuery = $this->INSERTquery($table, $fields_values, $no_quote_fields);
				if (is_string($this->lastQuery)) {
					$sqlResult = $this->query($this->lastQuery);
				} else {
					$sqlResult = $this->query($this->lastQuery[0]);
					$new_id = $this->sql_insert_id();
					$where = $this->cache_autoIncFields[$table] . '=' . $new_id;
					foreach ($this->lastQuery[1] as $field => $content) {
						$stmt = 'UPDATE ' . $this->quoteFromTables($table) . ' SET ' . $this->quoteFromTables($field) . '=' . $this->fullQuoteStr($content, $table) . ' WHERE ' . $this->quoteWhereClause($where);
						$this->query($stmt);
					}
				}
				break;
			case 'adodb':
				// auto generate ID for auto_increment fields if not present (static import needs this!)
				// should we check the table name here (static_*)?
				if (isset($this->cache_autoIncFields[$table])) {
					if (isset($fields_values[$this->cache_autoIncFields[$table]])) {
						$new_id = $fields_values[$this->cache_autoIncFields[$table]];
						if ($table != 'tx_dbal_debuglog') {
							$this->handlerInstance[$this->lastHandlerKey]->last_insert_id = $new_id;
						}
					} else {
						$new_id = $this->handlerInstance[$this->lastHandlerKey]->GenID($table . '_' . $this->cache_autoIncFields[$table], $this->handlerInstance[$this->lastHandlerKey]->sequenceStart);
						$fields_values[$this->cache_autoIncFields[$table]] = $new_id;
						if ($table != 'tx_dbal_debuglog') {
							$this->handlerInstance[$this->lastHandlerKey]->last_insert_id = $new_id;
						}
					}
				}
				$this->lastQuery = $this->INSERTquery($table, $fields_values, $no_quote_fields);
				if (is_string($this->lastQuery)) {
					$sqlResult = $this->handlerInstance[$this->lastHandlerKey]->_query($this->lastQuery, FALSE);
				} else {
					$this->handlerInstance[$this->lastHandlerKey]->StartTrans();
					if ($this->lastQuery[0] !== '') {
						$sqlResult = $this->handlerInstance[$this->lastHandlerKey]->_query($this->lastQuery[0], FALSE);
					}
					if (is_array($this->lastQuery[1])) {
						foreach ($this->lastQuery[1] as $field => $content) {
							if (empty($content)) {
								continue;
							}
							if (isset($this->cache_autoIncFields[$table]) && isset($new_id)) {
								$this->handlerInstance[$this->lastHandlerKey]->UpdateBlob($this->quoteFromTables($table), $field, $content, $this->quoteWhereClause($this->cache_autoIncFields[$table] . '=' . $new_id));
							} elseif (isset($this->cache_primaryKeys[$table])) {
								$where = '';
								$pks = explode(',', $this->cache_primaryKeys[$table]);
								foreach ($pks as $pk) {
									if (isset($fields_values[$pk])) {
										$where .= $pk . '=' . $this->fullQuoteStr($fields_values[$pk], $table) . ' AND ';
									}
								}
								$where = $this->quoteWhereClause($where . '1=1');
								$this->handlerInstance[$this->lastHandlerKey]->UpdateBlob($this->quoteFromTables($table), $field, $content, $where);
							} else {
								$this->handlerInstance[$this->lastHandlerKey]->CompleteTrans(FALSE);
								// Should never ever happen
								throw new \RuntimeException('Could not update BLOB >>>> no WHERE clause found!', 1321860519);
							}
						}
					}
					if (is_array($this->lastQuery[2])) {
						foreach ($this->lastQuery[2] as $field => $content) {
							if (empty($content)) {
								continue;
							}
							if (isset($this->cache_autoIncFields[$table]) && isset($new_id)) {
								$this->handlerInstance[$this->lastHandlerKey]->UpdateClob($this->quoteFromTables($table), $field, $content, $this->quoteWhereClause($this->cache_autoIncFields[$table] . '=' . $new_id));
							} elseif (isset($this->cache_primaryKeys[$table])) {
								$where = '';
								$pks = explode(',', $this->cache_primaryKeys[$table]);
								foreach ($pks as $pk) {
									if (isset($fields_values[$pk])) {
										$where .= $pk . '=' . $this->fullQuoteStr($fields_values[$pk], $table) . ' AND ';
									}
								}
								$where = $this->quoteWhereClause($where . '1=1');
								$this->handlerInstance[$this->lastHandlerKey]->UpdateClob($this->quoteFromTables($table), $field, $content, $where);
							} else {
								$this->handlerInstance[$this->lastHandlerKey]->CompleteTrans(FALSE);
								// Should never ever happen
								throw new \RuntimeException('Could not update CLOB >>>> no WHERE clause found!', 1310027337);
							}
						}
					}
					$this->handlerInstance[$this->lastHandlerKey]->CompleteTrans();
				}
				break;
			case 'userdefined':
				$sqlResult = $this->handlerInstance[$this->lastHandlerKey]->exec_INSERTquery($table, $fields_values, $no_quote_fields);
				break;
		}
		if ($this->printErrors && $this->sql_error()) {
			debug(array($this->lastQuery, $this->sql_error()));
		}
		if ($this->debug) {
			$this->debugHandler('exec_INSERTquery', GeneralUtility::milliseconds() - $pt, array(
				'handlerType' => $hType,
				'args' => array($table, $fields_values),
				'ORIG_tablename' => $ORIG_tableName
			));
		}
		foreach ($this->postProcessHookObjects as $hookObject) {
			$hookObject->exec_INSERTquery_postProcessAction($table, $fields_values, $no_quote_fields, $this);
		}
		// Return output:
		return $sqlResult;
	}

	/**
	 * Creates and executes an INSERT SQL-statement for $table with multiple rows.
	 *
	 * @param string $table Table name
	 * @param array $fields Field names
	 * @param array $rows Table rows. Each row should be an array with field values mapping to $fields
	 * @param bool|array|string $no_quote_fields See fullQuoteArray()
	 * @return bool|\mysqli_result|object MySQLi result object / DBAL object
	 */
	public function exec_INSERTmultipleRows($table, array $fields, array $rows, $no_quote_fields = FALSE) {
		$res = NULL;
		if ((string)$this->handlerCfg[$this->lastHandlerKey]['type'] === 'native') {
			$this->lastHandlerKey = $this->handler_getFromTableList($table);
			$res = $this->query(parent::INSERTmultipleRows($table, $fields, $rows, $no_quote_fields));
		} else {
			foreach ($rows as $row) {
				$fields_values = array();
				foreach ($fields as $key => $value) {
					$fields_values[$value] = $row[$key];
				}
				$res = $this->exec_INSERTquery($table, $fields_values, $no_quote_fields);
			}
		}
		foreach ($this->postProcessHookObjects as $hookObject) {
			$hookObject->exec_INSERTmultipleRows_postProcessAction($table, $fields, $rows, $no_quote_fields, $this);
		}
		return $res;
	}

	/**
	 * Creates and executes an UPDATE SQL-statement for $table where $where-clause (typ. 'uid=...') from the array with field/value pairs $fields_values.
	 * Using this function specifically allow us to handle BLOB and CLOB fields depending on DB
	 *
	 * @param string $table Database tablename
	 * @param string $where WHERE clause, eg. "uid=1". NOTICE: You must escape values in this argument with $this->fullQuoteStr() yourself!
	 * @param array $fields_values Field values as key=>value pairs. Values will be escaped internally. Typically you would fill an array like "$updateFields" with 'fieldname'=>'value' and pass it to this function as argument.
	 * @param bool|array|string $no_quote_fields See fullQuoteArray()
	 * @return bool|\mysqli_result|object MySQLi result object / DBAL object
	 */
	public function exec_UPDATEquery($table, $where, $fields_values, $no_quote_fields = FALSE) {
		$pt = $this->debug ? GeneralUtility::milliseconds() : 0;
		// Do table/field mapping:
		$ORIG_tableName = $table;
		if ($tableArray = $this->map_needMapping($table)) {
			// Field mapping of array:
			$fields_values = $this->map_assocArray($fields_values, $tableArray);
			// Where clause table and field mapping:
			$whereParts = $this->SQLparser->parseWhereClause($where);
			$this->map_sqlParts($whereParts, $tableArray[0]['table']);
			$where = $this->SQLparser->compileWhereClause($whereParts, FALSE);
			// Table name:
			if ($this->mapping[$table]['mapTableName']) {
				$table = $this->mapping[$table]['mapTableName'];
			}
		}
		// Select API
		$this->lastHandlerKey = $this->handler_getFromTableList($table);
		$hType = (string)$this->handlerCfg[$this->lastHandlerKey]['type'];
		$sqlResult = NULL;
		switch ($hType) {
			case 'native':
				$this->lastQuery = $this->UPDATEquery($table, $where, $fields_values, $no_quote_fields);
				if (is_string($this->lastQuery)) {
					$sqlResult = $this->query($this->lastQuery);
				} else {
					$sqlResult = $this->query($this->lastQuery[0]);
					foreach ($this->lastQuery[1] as $field => $content) {
						$stmt = 'UPDATE ' . $this->quoteFromTables($table) . ' SET ' . $this->quoteFromTables($field) . '=' . $this->fullQuoteStr($content, $table) . ' WHERE ' . $this->quoteWhereClause($where);
						$this->query($stmt);
					}
				}
				break;
			case 'adodb':
				$this->lastQuery = $this->UPDATEquery($table, $where, $fields_values, $no_quote_fields);
				if (is_string($this->lastQuery)) {
					$sqlResult = $this->handlerInstance[$this->lastHandlerKey]->_query($this->lastQuery, FALSE);
				} else {
					$this->handlerInstance[$this->lastHandlerKey]->StartTrans();
					if ($this->lastQuery[0] !== '') {
						$sqlResult = $this->handlerInstance[$this->lastHandlerKey]->_query($this->lastQuery[0], FALSE);
					}
					if (is_array($this->lastQuery[1])) {
						foreach ($this->lastQuery[1] as $field => $content) {
							$this->handlerInstance[$this->lastHandlerKey]->UpdateBlob($this->quoteFromTables($table), $field, $content, $this->quoteWhereClause($where));
						}
					}
					if (is_array($this->lastQuery[2])) {
						foreach ($this->lastQuery[2] as $field => $content) {
							$this->handlerInstance[$this->lastHandlerKey]->UpdateClob($this->quoteFromTables($table), $field, $content, $this->quoteWhereClause($where));
						}
					}
					$this->handlerInstance[$this->lastHandlerKey]->CompleteTrans();
				}
				break;
			case 'userdefined':
				$sqlResult = $this->handlerInstance[$this->lastHandlerKey]->exec_UPDATEquery($table, $where, $fields_values, $no_quote_fields);
				break;
		}
		if ($this->printErrors && $this->sql_error()) {
			debug(array($this->lastQuery, $this->sql_error()));
		}
		if ($this->debug) {
			$this->debugHandler('exec_UPDATEquery', GeneralUtility::milliseconds() - $pt, array(
				'handlerType' => $hType,
				'args' => array($table, $where, $fields_values),
				'ORIG_from_table' => $ORIG_tableName
			));
		}
		foreach ($this->postProcessHookObjects as $hookObject) {
			$hookObject->exec_UPDATEquery_postProcessAction($table, $where, $fields_values, $no_quote_fields, $this);
		}
		// Return result:
		return $sqlResult;
	}

	/**
	 * Creates and executes a DELETE SQL-statement for $table where $where-clause
	 *
	 * @param string $table Database tablename
	 * @param string $where WHERE clause, eg. "uid=1". NOTICE: You must escape values in this argument with $this->fullQuoteStr() yourself!
	 * @return bool|\mysqli_result|object MySQLi result object / DBAL object
	 */
	public function exec_DELETEquery($table, $where) {
		$pt = $this->debug ? GeneralUtility::milliseconds() : 0;
		// Do table/field mapping:
		$ORIG_tableName = $table;
		if ($tableArray = $this->map_needMapping($table)) {
			// Where clause:
			$whereParts = $this->SQLparser->parseWhereClause($where);
			$this->map_sqlParts($whereParts, $tableArray[0]['table']);
			$where = $this->SQLparser->compileWhereClause($whereParts, FALSE);
			// Table name:
			if ($this->mapping[$table]['mapTableName']) {
				$table = $this->mapping[$table]['mapTableName'];
			}
		}
		// Select API
		$this->lastHandlerKey = $this->handler_getFromTableList($table);
		$hType = (string)$this->handlerCfg[$this->lastHandlerKey]['type'];
		$sqlResult = NULL;
		switch ($hType) {
			case 'native':
				$this->lastQuery = $this->DELETEquery($table, $where);
				$sqlResult = $this->query($this->lastQuery);
				break;
			case 'adodb':
				$this->lastQuery = $this->DELETEquery($table, $where);
				$sqlResult = $this->handlerInstance[$this->lastHandlerKey]->_query($this->lastQuery, FALSE);
				break;
			case 'userdefined':
				$sqlResult = $this->handlerInstance[$this->lastHandlerKey]->exec_DELETEquery($table, $where);
				break;
		}
		if ($this->printErrors && $this->sql_error()) {
			debug(array($this->lastQuery, $this->sql_error()));
		}
		if ($this->debug) {
			$this->debugHandler('exec_DELETEquery', GeneralUtility::milliseconds() - $pt, array(
				'handlerType' => $hType,
				'args' => array($table, $where),
				'ORIG_from_table' => $ORIG_tableName
			));
		}
		foreach ($this->postProcessHookObjects as $hookObject) {
			$hookObject->exec_DELETEquery_postProcessAction($table, $where, $this);
		}
		// Return result:
		return $sqlResult;
	}

	/**
	 * Creates and executes a SELECT SQL-statement
	 * Using this function specifically allow us to handle the LIMIT feature independently of DB.
	 *
	 * @param string $select_fields List of fields to select from the table. This is what comes right after "SELECT ...". Required value.
	 * @param string $from_table Table(s) from which to select. This is what comes right after "FROM ...". Required value.
	 * @param string $where_clause Additional WHERE clauses put in the end of the query. NOTICE: You must escape values in this argument with $this->fullQuoteStr() yourself! DO NOT PUT IN GROUP BY, ORDER BY or LIMIT!
	 * @param string $groupBy Optional GROUP BY field(s), if none, supply blank string.
	 * @param string $orderBy Optional ORDER BY field(s), if none, supply blank string.
	 * @param string $limit Optional LIMIT value ([begin,]max), if none, supply blank string.
	 * @throws \RuntimeException
	 * @return bool|\mysqli_result|object MySQLi result object / DBAL object
	 */
	public function exec_SELECTquery($select_fields, $from_table, $where_clause, $groupBy = '', $orderBy = '', $limit = '') {
		$pt = $this->debug ? GeneralUtility::milliseconds() : 0;
		// Map table / field names if needed:
		$ORIG_tableName = $from_table;
		// Saving table names in $ORIG_from_table since $from_table is transformed beneath:
		$parsedFromTable = array();
		$remappedParameters = array();
		if ($tableArray = $this->map_needMapping($ORIG_tableName, FALSE, $parsedFromTable)) {
			$from = $parsedFromTable ? $parsedFromTable : $from_table;
			$remappedParameters = $this->map_remapSELECTQueryParts($select_fields, $from, $where_clause, $groupBy, $orderBy);
		}
		// Get handler key and select API:
		if (count($remappedParameters) > 0) {
			$mappedQueryParts = $this->compileSelectParameters($remappedParameters);
			$fromTable = $mappedQueryParts[1];
		} else {
			$fromTable = $from_table;
		}
		$this->lastHandlerKey = $this->handler_getFromTableList($fromTable);
		$hType = (string)$this->handlerCfg[$this->lastHandlerKey]['type'];
		$sqlResult = NULL;
		switch ($hType) {
			case 'native':
				if (count($remappedParameters) > 0) {
					list($select_fields, $from_table, $where_clause, $groupBy, $orderBy) = $this->compileSelectParameters($remappedParameters);
				}
				$this->lastQuery = $this->SELECTquery($select_fields, $from_table, $where_clause, $groupBy, $orderBy, $limit);
				$sqlResult = $this->query($this->lastQuery);
				$this->resourceIdToTableNameMap[serialize($sqlResult)] = $ORIG_tableName;
				break;
			case 'adodb':
				if ($limit != '') {
					$splitLimit = GeneralUtility::intExplode(',', $limit);
					// Splitting the limit values:
					if ($splitLimit[1]) {
						// If there are two parameters, do mapping differently than otherwise:
						$numrows = $splitLimit[1];
						$offset = $splitLimit[0];
					} else {
						$numrows = $splitLimit[0];
						$offset = 0;
					}
					if (count($remappedParameters) > 0) {
						$sqlResult = $this->handlerInstance[$this->lastHandlerKey]->SelectLimit($this->SELECTqueryFromArray($remappedParameters), $numrows, $offset);
					} else {
						$sqlResult = $this->handlerInstance[$this->lastHandlerKey]->SelectLimit($this->SELECTquery($select_fields, $from_table, $where_clause, $groupBy, $orderBy), $numrows, $offset);
					}
					$this->lastQuery = $sqlResult->sql;
				} else {
					if (count($remappedParameters) > 0) {
						$this->lastQuery = $this->SELECTqueryFromArray($remappedParameters);
					} else {
						$this->lastQuery = $this->SELECTquery($select_fields, $from_table, $where_clause, $groupBy, $orderBy);
					}
					$sqlResult = $this->handlerInstance[$this->lastHandlerKey]->_Execute($this->lastQuery);
				}
				if (!is_object($sqlResult)) {
					throw new \RuntimeException('ADOdb could not run this query: ' . $this->lastQuery, 1421053336);
				}
				$sqlResult->TYPO3_DBAL_handlerType = 'adodb';
				// Setting handler type in result object (for later recognition!)
				$sqlResult->TYPO3_DBAL_tableList = $ORIG_tableName;
				break;
			case 'userdefined':
				if (count($remappedParameters) > 0) {
					list($select_fields, $from_table, $where_clause, $groupBy, $orderBy) = $this->compileSelectParameters($remappedParameters);
				}
				$sqlResult = $this->handlerInstance[$this->lastHandlerKey]->exec_SELECTquery($select_fields, $from_table, $where_clause, $groupBy, $orderBy, $limit);
				if (is_object($sqlResult)) {
					$sqlResult->TYPO3_DBAL_handlerType = 'userdefined';
					// Setting handler type in result object (for later recognition!)
					$sqlResult->TYPO3_DBAL_tableList = $ORIG_tableName;
				}
				break;
		}
		if ($this->printErrors && $this->sql_error()) {
			debug(array($this->lastQuery, $this->sql_error()));
		}
		if ($this->debug) {
			$data = array(
				'handlerType' => $hType,
				'args' => array($from_table, $select_fields, $where_clause, $groupBy, $orderBy, $limit),
				'ORIG_from_table' => $ORIG_tableName
			);
			if ($this->conf['debugOptions']['numberRows']) {
				$data['numberRows'] = $this->sql_num_rows($sqlResult);
			}
			$this->debugHandler('exec_SELECTquery', GeneralUtility::milliseconds() - $pt, $data);
		}
		// Return handler.
		return $sqlResult;
	}

	/**
	 * Truncates a table.
	 *
	 * @param string $table Database tablename
	 * @return mixed Result from handler
	 */
	public function exec_TRUNCATEquery($table) {
		$pt = $this->debug ? GeneralUtility::milliseconds() : 0;
		// Do table/field mapping:
		$ORIG_tableName = $table;
		if ($tableArray = $this->map_needMapping($table)) {
			// Table name:
			if ($this->mapping[$table]['mapTableName']) {
				$table = $this->mapping[$table]['mapTableName'];
			}
		}
		// Select API
		$this->lastHandlerKey = $this->handler_getFromTableList($table);
		$hType = (string)$this->handlerCfg[$this->lastHandlerKey]['type'];
		$sqlResult = NULL;
		switch ($hType) {
			case 'native':
				$this->lastQuery = $this->TRUNCATEquery($table);
				$sqlResult = $this->query($this->lastQuery);
				break;
			case 'adodb':
				$this->lastQuery = $this->TRUNCATEquery($table);
				$sqlResult = $this->handlerInstance[$this->lastHandlerKey]->_query($this->lastQuery, FALSE);
				break;
			case 'userdefined':
				$sqlResult = $this->handlerInstance[$this->lastHandlerKey]->exec_TRUNCATEquery($table);
				break;
		}
		if ($this->printErrors && $this->sql_error()) {
			debug(array($this->lastQuery, $this->sql_error()));
		}
		if ($this->debug) {
			$this->debugHandler('exec_TRUNCATEquery', GeneralUtility::milliseconds() - $pt, array(
				'handlerType' => $hType,
				'args' => array($table),
				'ORIG_from_table' => $ORIG_tableName
			));
		}
		foreach ($this->postProcessHookObjects as $hookObject) {
			$hookObject->exec_TRUNCATEquery_postProcessAction($table, $this);
		}
		// Return result:
		return $sqlResult;
	}

	/**
	 * Executes a query.
	 * EXPERIMENTAL since TYPO3 4.4.
	 *
	 * @param array $queryParts SQL parsed by method parseSQL() of \TYPO3\CMS\Core\Database\SqlParser
	 * @return \mysqli_result|object MySQLi result object / DBAL object
	 * @see self::sql_query()
	 */
	protected function exec_query(array $queryParts) {
		switch ($queryParts['type']) {
			case 'SELECT':
				$selectFields = $this->SQLparser->compileFieldList($queryParts['SELECT']);
				$fromTables = $this->SQLparser->compileFromTables($queryParts['FROM']);
				$whereClause = isset($queryParts['WHERE']) ? $this->SQLparser->compileWhereClause($queryParts['WHERE']) : '1=1';
				$groupBy = isset($queryParts['GROUPBY']) ? $this->SQLparser->compileFieldList($queryParts['GROUPBY']) : '';
				$orderBy = isset($queryParts['ORDERBY']) ? $this->SQLparser->compileFieldList($queryParts['ORDERBY']) : '';
				$limit = isset($queryParts['LIMIT']) ? $queryParts['LIMIT'] : '';
				return $this->exec_SELECTquery($selectFields, $fromTables, $whereClause, $groupBy, $orderBy, $limit);
			case 'UPDATE':
				$table = $queryParts['TABLE'];
				$fields = array();
				foreach ($queryParts['FIELDS'] as $fN => $fV) {
					$fields[$fN] = $fV[0];
				}
				$whereClause = isset($queryParts['WHERE']) ? $this->SQLparser->compileWhereClause($queryParts['WHERE']) : '1=1';
				return $this->exec_UPDATEquery($table, $whereClause, $fields);
			case 'INSERT':
				$table = $queryParts['TABLE'];
				$values = array();
				if (isset($queryParts['VALUES_ONLY']) && is_array($queryParts['VALUES_ONLY'])) {
					$fields = $GLOBALS['TYPO3_DB']->cache_fieldType[$table];
					$fc = 0;
					foreach ($fields as $fn => $fd) {
						$values[$fn] = $queryParts['VALUES_ONLY'][$fc++][0];
					}
				} else {
					foreach ($queryParts['FIELDS'] as $fN => $fV) {
						$values[$fN] = $fV[0];
					}
				}
				return $this->exec_INSERTquery($table, $values);
			case 'DELETE':
				$table = $queryParts['TABLE'];
				$whereClause = isset($queryParts['WHERE']) ? $this->SQLparser->compileWhereClause($queryParts['WHERE']) : '1=1';
				return $this->exec_DELETEquery($table, $whereClause);
			case 'TRUNCATETABLE':
				$table = $queryParts['TABLE'];
				return $this->exec_TRUNCATEquery($table);
			default:
				return NULL;
		}
	}

	/**
	 * Central query method. Also checks if there is a database connection.
	 * Use this to execute database queries instead of directly calling $this->link->query()
	 *
	 * @param string $query The query to send to the database
	 * @return bool|\mysqli_result
	 */
	protected function query($query) {
		if (!$this->isConnected()) {
			$this->connectDB();
		}
		return $this->handlerInstance[$this->lastHandlerKey]['link']->query($query);
	}

	/**************************************
	 *
	 * Query building
	 *
	 **************************************/
	/**
	 * Creates an INSERT SQL-statement for $table from the array with field/value pairs $fields_values.
	 *
	 * @param string $table See exec_INSERTquery()
	 * @param array $fields_values See exec_INSERTquery()
	 * @param bool|array|string $no_quote_fields See fullQuoteArray()
	 * @return string|NULL Full SQL query for INSERT, NULL if $rows is empty
	 */
	public function INSERTquery($table, $fields_values, $no_quote_fields = FALSE) {
		// Table and fieldnames should be "SQL-injection-safe" when supplied to this function (contrary to values in the arrays which may be insecure).
		if (!is_array($fields_values) || count($fields_values) === 0) {
			return '';
		}
		foreach ($this->preProcessHookObjects as $hookObject) {
			$hookObject->INSERTquery_preProcessAction($table, $fields_values, $no_quote_fields, $this);
		}
		if (is_string($no_quote_fields)) {
			$no_quote_fields = explode(',', $no_quote_fields);
		} elseif (!is_array($no_quote_fields)) {
			$no_quote_fields = array();
		}
		$blobFields = $clobFields = array();
		$nArr = array();
		$handlerKey = $this->handler_getFromTableList($table);
		$quoteClob = isset($this->handlerCfg[$handlerKey]['config']['quoteClob']) ? $this->handlerCfg[$handlerKey]['config']['quoteClob'] : FALSE;
		foreach ($fields_values as $k => $v) {
			if (!$this->runningNative() && $this->sql_field_metatype($table, $k) == 'B') {
				// we skip the field in the regular INSERT statement, it is only in blobfields
				$blobFields[$this->quoteFieldNames($k)] = $v;
			} elseif (!$this->runningNative() && $this->sql_field_metatype($table, $k) == 'XL') {
				// we skip the field in the regular INSERT statement, it is only in clobfields
				$clobFields[$this->quoteFieldNames($k)] = $quoteClob ? $this->quoteStr($v, $table) : $v;
			} else {
				// Add slashes old-school:
				// cast numerical values
				$mt = $this->sql_field_metatype($table, $k);
				if ($mt[0] == 'I') {
					$v = (int)$v;
				} elseif ($mt[0] == 'F') {
					$v = (double) $v;
				}
				$nArr[$this->quoteFieldNames($k)] = !in_array($k, $no_quote_fields) ? $this->fullQuoteStr($v, $table) : $v;
			}
		}
		if (count($blobFields) || count($clobFields)) {
			$query = array();
			if (count($nArr)) {
				$query[0] = 'INSERT INTO ' . $this->quoteFromTables($table) . '
				(
					' . implode(',
					', array_keys($nArr)) . '
				) VALUES (
					' . implode(',
					', $nArr) . '
				)';
			}
			if (count($blobFields)) {
				$query[1] = $blobFields;
			}
			if (count($clobFields)) {
				$query[2] = $clobFields;
			}
			if (isset($query[0]) && ($this->debugOutput || $this->store_lastBuiltQuery)) {
				$this->debug_lastBuiltQuery = $query[0];
			}
		} else {
			$query = 'INSERT INTO ' . $this->quoteFromTables($table) . '
			(
				' . implode(',
				', array_keys($nArr)) . '
			) VALUES (
				' . implode(',
				', $nArr) . '
			)';
			if ($this->debugOutput || $this->store_lastBuiltQuery) {
				$this->debug_lastBuiltQuery = $query;
			}
		}
		return $query;
	}

	/**
	 * Creates an INSERT SQL-statement for $table with multiple rows.
	 *
	 * @param string $table Table name
	 * @param array $fields Field names
	 * @param array $rows Table rows. Each row should be an array with field values mapping to $fields
	 * @param bool|array|string $no_quote_fields See fullQuoteArray()
	 * @return string|array Full SQL query for INSERT (unless $rows does not contain any elements in which case it will be FALSE)
	 */
	public function INSERTmultipleRows($table, array $fields, array $rows, $no_quote_fields = FALSE) {
		if ((string)$this->handlerCfg[$this->lastHandlerKey]['type'] === 'native') {
			return parent::INSERTmultipleRows($table, $fields, $rows, $no_quote_fields);
		}
		$result = array();
		foreach ($rows as $row) {
			$fields_values = array();
			foreach ($fields as $key => $value) {
				$fields_values[$value] = $row[$key];
			}
			$rowQuery = $this->INSERTquery($table, $fields_values, $no_quote_fields);
			if (is_array($rowQuery)) {
				$result[] = $rowQuery;
			} else {
				$result[][0] = $rowQuery;
			}
		}
		return $result;
	}

	/**
	 * Creates an UPDATE SQL-statement for $table where $where-clause (typ. 'uid=...') from the array with field/value pairs $fields_values.
	 *
	 *
	 * @param string $table See exec_UPDATEquery()
	 * @param string $where See exec_UPDATEquery()
	 * @param array $fields_values See exec_UPDATEquery()
	 * @param bool|array|string $no_quote_fields See fullQuoteArray()
	 * @throws \InvalidArgumentException
	 * @return string Full SQL query for UPDATE
	 */
	public function UPDATEquery($table, $where, $fields_values, $no_quote_fields = FALSE) {
		// Table and fieldnames should be "SQL-injection-safe" when supplied to this function (contrary to values in the arrays which may be insecure).
		if (is_string($where)) {
			foreach ($this->preProcessHookObjects as $hookObject) {
				$hookObject->UPDATEquery_preProcessAction($table, $where, $fields_values, $no_quote_fields, $this);
			}
			$blobFields = $clobFields = array();
			$nArr = array();
			if (is_array($fields_values) && count($fields_values)) {
				if (is_string($no_quote_fields)) {
					$no_quote_fields = explode(',', $no_quote_fields);
				} elseif (!is_array($no_quote_fields)) {
					$no_quote_fields = array();
				}
				$handlerKey = $this->handler_getFromTableList($table);
				$quoteClob = isset($this->handlerCfg[$handlerKey]['config']['quoteClob']) ? $this->handlerCfg[$handlerKey]['config']['quoteClob'] : FALSE;
				foreach ($fields_values as $k => $v) {
					if (!$this->runningNative() && $this->sql_field_metatype($table, $k) == 'B') {
						// we skip the field in the regular UPDATE statement, it is only in blobfields
						$blobFields[$this->quoteFieldNames($k)] = $v;
					} elseif (!$this->runningNative() && $this->sql_field_metatype($table, $k) == 'XL') {
						// we skip the field in the regular UPDATE statement, it is only in clobfields
						$clobFields[$this->quoteFieldNames($k)] = $quoteClob ? $this->quoteStr($v, $table) : $v;
					} else {
						// Add slashes old-school:
						// cast numeric values
						$mt = $this->sql_field_metatype($table, $k);
						if ($mt[0] == 'I') {
							$v = (int)$v;
						} elseif ($mt[0] == 'F') {
							$v = (double) $v;
						}
						$nArr[] = $this->quoteFieldNames($k) . '=' . (!in_array($k, $no_quote_fields) ? $this->fullQuoteStr($v, $table) : $v);
					}
				}
			}
			if (count($blobFields) || count($clobFields)) {
				$query = array();
				if (count($nArr)) {
					$query[0] = 'UPDATE ' . $this->quoteFromTables($table) . '
						SET
							' . implode(',
							', $nArr) . ($where !== '' ? '
						WHERE
							' . $this->quoteWhereClause($where) : '');
				}
				if (count($blobFields)) {
					$query[1] = $blobFields;
				}
				if (count($clobFields)) {
					$query[2] = $clobFields;
				}
				if (isset($query[0]) && ($this->debugOutput || $this->store_lastBuiltQuery)) {
					$this->debug_lastBuiltQuery = $query[0];
				}
			} else {
				$query = 'UPDATE ' . $this->quoteFromTables($table) . '
					SET
						' . implode(',
						', $nArr) . ($where !== '' ? '
					WHERE
						' . $this->quoteWhereClause($where) : '');
				if ($this->debugOutput || $this->store_lastBuiltQuery) {
					$this->debug_lastBuiltQuery = $query;
				}
			}
			return $query;
		} else {
			throw new \InvalidArgumentException('TYPO3 Fatal Error: "Where" clause argument for UPDATE query was not a string in $this->UPDATEquery() !', 1270853887);
		}
	}

	/**
	 * Creates a DELETE SQL-statement for $table where $where-clause
	 *
	 * @param string $table See exec_DELETEquery()
	 * @param string $where See exec_DELETEquery()
	 * @return string Full SQL query for DELETE
	 * @throws \InvalidArgumentException
	 */
	public function DELETEquery($table, $where) {
		if (is_string($where)) {
			foreach ($this->preProcessHookObjects as $hookObject) {
				$hookObject->DELETEquery_preProcessAction($table, $where, $this);
			}
			$table = $this->quoteFromTables($table);
			$where = $this->quoteWhereClause($where);
			$query = 'DELETE FROM ' . $table . ($where !== '' ? ' WHERE ' . $where : '');
			if ($this->debugOutput || $this->store_lastBuiltQuery) {
				$this->debug_lastBuiltQuery = $query;
			}
			return $query;
		} else {
			throw new \InvalidArgumentException('TYPO3 Fatal Error: "Where" clause argument for DELETE query was not a string in $this->DELETEquery() !', 1310027383);
		}
	}

	/**
	 * Creates a SELECT SQL-statement
	 *
	 * @param string $select_fields See exec_SELECTquery()
	 * @param string $from_table See exec_SELECTquery()
	 * @param string $where_clause See exec_SELECTquery()
	 * @param string $groupBy See exec_SELECTquery()
	 * @param string $orderBy See exec_SELECTquery()
	 * @param string $limit See exec_SELECTquery()
	 * @return string Full SQL query for SELECT
	 */
	public function SELECTquery($select_fields, $from_table, $where_clause, $groupBy = '', $orderBy = '', $limit = '') {
		$this->lastHandlerKey = $this->handler_getFromTableList($from_table);
		$hType = (string)$this->handlerCfg[$this->lastHandlerKey]['type'];
		if ($hType === 'adodb' && $this->runningADOdbDriver('postgres')) {
			// Possibly rewrite the LIMIT to be PostgreSQL-compatible
			$splitLimit = GeneralUtility::intExplode(',', $limit);
			// Splitting the limit values:
			if ($splitLimit[1]) {
				// If there are two parameters, do mapping differently than otherwise:
				$numrows = $splitLimit[1];
				$offset = $splitLimit[0];
				$limit = $numrows . ' OFFSET ' . $offset;
			}
		}
		$select_fields = $this->quoteFieldNames($select_fields);
		$from_table = $this->quoteFromTables($from_table);
		$where_clause = $this->quoteWhereClause($where_clause);
		$groupBy = $this->quoteGroupBy($groupBy);
		$orderBy = $this->quoteOrderBy($orderBy);
		// Call parent method to build actual query
		$query = parent::SELECTquery($select_fields, $from_table, $where_clause, $groupBy, $orderBy, $limit);
		if ($this->debugOutput || $this->store_lastBuiltQuery) {
			$this->debug_lastBuiltQuery = $query;
		}
		return $query;
	}

	/**
	 * Creates a SELECT SQL-statement to be used with an ADOdb backend.
	 *
	 * @param array $params parsed parameters: array($select_fields, $from_table, $where_clause, $groupBy, $orderBy)
	 * @return string Full SQL query for SELECT
	 */
	protected function SELECTqueryFromArray(array $params) {
		// $select_fields
		$params[0] = $this->_quoteFieldNames($params[0]);
		// $from_table
		$params[1] = $this->_quoteFromTables($params[1]);
		// $where_clause
		if (count($params[2]) > 0) {
			$params[2] = $this->_quoteWhereClause($params[2]);
		}
		// $group_by
		if (count($params[3]) > 0) {
			$params[3] = $this->_quoteGroupBy($params[3]);
		}
		// $order_by
		if (count($params[4]) > 0) {
			$params[4] = $this->_quoteOrderBy($params[4]);
		}
		// Compile the SELECT parameters
		list($select_fields, $from_table, $where_clause, $groupBy, $orderBy) = $this->compileSelectParameters($params);
		// Call parent method to build actual query
		$query = parent::SELECTquery($select_fields, $from_table, $where_clause, $groupBy, $orderBy);
		if ($this->debugOutput || $this->store_lastBuiltQuery) {
			$this->debug_lastBuiltQuery = $query;
		}
		return $query;
	}

	/**
	 * Compiles and returns an array of SELECTquery parameters (without $limit) to
	 * be used with SELECTquery() or exec_SELECTquery().
	 *
	 * @param array $params
	 * @return array array($select_fields, $from_table, $where_clause, $groupBy, $orderBy)
	 */
	protected function compileSelectParameters(array $params) {
		$select_fields = $this->SQLparser->compileFieldList($params[0]);
		$from_table = $this->SQLparser->compileFromTables($params[1]);
		$where_clause = count($params[2]) > 0 ? $this->SQLparser->compileWhereClause($params[2]) : '';
		$groupBy = count($params[3]) > 0 ? $this->SQLparser->compileFieldList($params[3]) : '';
		$orderBy = count($params[4]) > 0 ? $this->SQLparser->compileFieldList($params[4]) : '';
		return array($select_fields, $from_table, $where_clause, $groupBy, $orderBy);
	}

	/**
	 * Creates a TRUNCATE TABLE SQL-statement
	 *
	 * @param string $table See exec_TRUNCATEquery()
	 * @return string Full SQL query for TRUNCATE TABLE
	 */
	public function TRUNCATEquery($table) {
		foreach ($this->preProcessHookObjects as $hookObject) {
			$hookObject->TRUNCATEquery_preProcessAction($table, $this);
		}
		$table = $this->quoteFromTables($table);
		// Build actual query
		$query = 'TRUNCATE TABLE ' . $table;
		if ($this->debugOutput || $this->store_lastBuiltQuery) {
			$this->debug_lastBuiltQuery = $query;
		}
		return $query;
	}

	/**************************************
	 *
	 * Prepared Query Support
	 *
	 **************************************/
	/**
	 * Creates a SELECT prepared SQL statement.
	 *
	 * @param string $select_fields See exec_SELECTquery()
	 * @param string $from_table See exec_SELECTquery()
	 * @param string $where_clause See exec_SELECTquery()
	 * @param string $groupBy See exec_SELECTquery()
	 * @param string $orderBy See exec_SELECTquery()
	 * @param string $limit See exec_SELECTquery()
	 * @param array $input_parameters An array of values with as many elements as there are bound parameters in the SQL statement being executed. All values are treated as \TYPO3\CMS\Core\Database\PreparedStatement::PARAM_AUTOTYPE.
	 * @return \TYPO3\CMS\Core\Database\PreparedStatement Prepared statement
	 */
	public function prepare_SELECTquery($select_fields, $from_table, $where_clause, $groupBy = '', $orderBy = '', $limit = '', array $input_parameters = array()) {
		$pt = $this->debug ? GeneralUtility::milliseconds() : 0;
		$precompiledParts = array();
		if ($this->queryCache) {
			$cacheKey = 'prepare_SELECTquery-' . \TYPO3\CMS\Dbal\QueryCache::getCacheKey(array(
				'selectFields' => $select_fields,
				'fromTable' => $from_table,
				'whereClause' => $where_clause,
				'groupBy' => $groupBy,
				'orderBy' => $orderBy,
				'limit' => $limit
			));
			if ($this->queryCache->has($cacheKey)) {
				$precompiledParts = $this->queryCache->get($cacheKey);
				if ($this->debug) {
					$data = array(
						'args' => array($from_table, $select_fields, $where_clause, $groupBy, $orderBy, $limit, $input_parameters),
						'precompiledParts' => $precompiledParts
					);
					$this->debugHandler('prepare_SELECTquery (cache hit)', GeneralUtility::milliseconds() - $pt, $data);
				}
			}
		}
		$ORIG_tableName = '';
		if (count($precompiledParts) == 0) {
			// Map table / field names if needed:
			$ORIG_tableName = $from_table;
			// Saving table names in $ORIG_from_table since $from_table is transformed beneath:
			$parsedFromTable = array();
			$queryComponents = array();
			if ($tableArray = $this->map_needMapping($ORIG_tableName, FALSE, $parsedFromTable)) {
				$from = $parsedFromTable ? $parsedFromTable : $from_table;
				$components = $this->map_remapSELECTQueryParts($select_fields, $from, $where_clause, $groupBy, $orderBy);
				$queryComponents['SELECT'] = $components[0];
				$queryComponents['FROM'] = $components[1];
				$queryComponents['WHERE'] = $components[2];
				$queryComponents['GROUPBY'] = $components[3];
				$queryComponents['ORDERBY'] = $components[4];
				$queryComponents['parameters'] = $components[5];
			} else {
				$queryComponents = $this->getQueryComponents($select_fields, $from_table, $where_clause, $groupBy, $orderBy, $limit);
			}
			$queryComponents['ORIG_tableName'] = $ORIG_tableName;
			if (!$this->runningNative()) {
				// Quotes all fields
				$queryComponents['SELECT'] = $this->_quoteFieldNames($queryComponents['SELECT']);
				$queryComponents['FROM'] = $this->_quoteFromTables($queryComponents['FROM']);
				$queryComponents['WHERE'] = $this->_quoteWhereClause($queryComponents['WHERE']);
				$queryComponents['GROUPBY'] = $this->_quoteGroupBy($queryComponents['GROUPBY']);
				$queryComponents['ORDERBY'] = $this->_quoteOrderBy($queryComponents['ORDERBY']);
			}
			$precompiledParts = $this->precompileSELECTquery($queryComponents);
			if ($this->queryCache) {
				try {
					$this->queryCache->set($cacheKey, $precompiledParts);
				} catch (\TYPO3\CMS\Core\Cache\Exception $e) {
					if ($this->debug) {
						GeneralUtility::devLog($e->getMessage(), 'dbal', 1);
					}
				}
			}
		}
		$preparedStatement = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\PreparedStatement::class, '', $from_table, $precompiledParts);
		/* @var $preparedStatement \TYPO3\CMS\Core\Database\PreparedStatement */
		// Bind values to parameters
		foreach ($input_parameters as $key => $value) {
			$preparedStatement->bindValue($key, $value, \TYPO3\CMS\Core\Database\PreparedStatement::PARAM_AUTOTYPE);
		}
		if ($this->debug) {
			$data = array(
				'args' => array($from_table, $select_fields, $where_clause, $groupBy, $orderBy, $limit, $input_parameters),
				'ORIG_from_table' => $ORIG_tableName
			);
			$this->debugHandler('prepare_SELECTquery', GeneralUtility::milliseconds() - $pt, $data);
		}
		// Return prepared statement
		return $preparedStatement;
	}

	/**
	 * Returns the parsed query components.
	 *
	 * @param string $select_fields
	 * @param string $from_table
	 * @param string $where_clause
	 * @param string $groupBy
	 * @param string $orderBy
	 * @param string $limit
	 * @throws \InvalidArgumentException
	 * @return array
	 */
	protected function getQueryComponents($select_fields, $from_table, $where_clause, $groupBy, $orderBy, $limit) {
		$queryComponents = array(
			'SELECT' => '',
			'FROM' => '',
			'WHERE' => '',
			'GROUPBY' => '',
			'ORDERBY' => '',
			'LIMIT' => '',
			'parameters' => array()
		);
		$this->lastHandlerKey = $this->handler_getFromTableList($from_table);
		$hType = (string)$this->handlerCfg[$this->lastHandlerKey]['type'];
		if ($hType === 'adodb' && $this->runningADOdbDriver('postgres')) {
			// Possibly rewrite the LIMIT to be PostgreSQL-compatible
			$splitLimit = GeneralUtility::intExplode(',', $limit);
			// Splitting the limit values:
			if ($splitLimit[1]) {
				// If there are two parameters, do mapping differently than otherwise:
				$numrows = $splitLimit[1];
				$offset = $splitLimit[0];
				$limit = $numrows . ' OFFSET ' . $offset;
			}
		}
		$queryComponents['LIMIT'] = $limit;
		$queryComponents['SELECT'] = $this->SQLparser->parseFieldList($select_fields);
		if ($this->SQLparser->parse_error) {
			throw new \InvalidArgumentException($this->SQLparser->parse_error, 1310027408);
		}
		$queryComponents['FROM'] = $this->SQLparser->parseFromTables($from_table);
		$queryComponents['WHERE'] = $this->SQLparser->parseWhereClause($where_clause, '', $queryComponents['parameters']);
		if (!is_array($queryComponents['WHERE'])) {
			throw new \InvalidArgumentException('Could not parse where clause', 1310027427);
		}
		$queryComponents['GROUPBY'] = $this->SQLparser->parseFieldList($groupBy);
		$queryComponents['ORDERBY'] = $this->SQLparser->parseFieldList($orderBy);
		// Return the query components
		return $queryComponents;
	}

	/**
	 * Precompiles a SELECT prepared SQL statement.
	 *
	 * @param array $components
	 * @return array Precompiled SQL statement
	 */
	protected function precompileSELECTquery(array $components) {
		$parameterWrap = '__' . dechex(time()) . '__';
		foreach ($components['parameters'] as $key => $params) {
			if ($key === '?') {
				foreach ($params as $index => $param) {
					$components['parameters'][$key][$index][0] = $parameterWrap . $param[0] . $parameterWrap;
				}
			} else {
				$components['parameters'][$key][0] = $parameterWrap . $params[0] . $parameterWrap;
			}
		}
		$select_fields = $this->SQLparser->compileFieldList($components['SELECT']);
		$from_table = $this->SQLparser->compileFromTables($components['FROM']);
		$where_clause = $this->SQLparser->compileWhereClause($components['WHERE']);
		$groupBy = $this->SQLparser->compileFieldList($components['GROUPBY']);
		$orderBy = $this->SQLparser->compileFieldList($components['ORDERBY']);
		$limit = $components['LIMIT'];
		$precompiledParts = array();
		$this->lastHandlerKey = $this->handler_getFromTableList($components['ORIG_tableName']);
		$hType = (string)$this->handlerCfg[$this->lastHandlerKey]['type'];
		$precompiledParts['handler'] = $hType;
		$precompiledParts['ORIG_tableName'] = $components['ORIG_tableName'];
		switch ($hType) {
			case 'native':
				$query = parent::SELECTquery($select_fields, $from_table, $where_clause, $groupBy, $orderBy, $limit);
				$precompiledParts['queryParts'] = explode($parameterWrap, $query);
				break;
			case 'adodb':
				$query = parent::SELECTquery($select_fields, $from_table, $where_clause, $groupBy, $orderBy);
				$precompiledParts['queryParts'] = explode($parameterWrap, $query);
				$precompiledParts['LIMIT'] = $limit;
				break;
			case 'userdefined':
				$precompiledParts['queryParts'] = array(
					'SELECT' => $select_fields,
					'FROM' => $from_table,
					'WHERE' => $where_clause,
					'GROUPBY' => $groupBy,
					'ORDERBY' => $orderBy,
					'LIMIT' => $limit
				);
				break;
		}
		return $precompiledParts;
	}

	/**
	 * Prepares a prepared query.
	 *
	 * @param string $query The query to execute
	 * @param array $queryComponents The components of the query to execute
	 * @return bool|\mysqli_statement|\TYPO3\CMS\Dbal\Database\AdodbPreparedStatement
	 * @throws \RuntimeException
	 * @internal This method may only be called by \TYPO3\CMS\Core\Database\PreparedStatement
	 */
	public function prepare_PREPAREDquery($query, array $queryComponents) {
		$pt = $this->debug ? GeneralUtility::milliseconds() : 0;
		// Get handler key and select API:
		$preparedStatement = NULL;
		switch ($queryComponents['handler']) {
			case 'native':
				$this->lastQuery = $query;
				$preparedStatement = parent::prepare_PREPAREDquery($this->lastQuery, $queryComponents);
				$this->resourceIdToTableNameMap[serialize($preparedStatement)] = $queryComponents['ORIG_tableName'];
				break;
			case 'adodb':
				/** @var \TYPO3\CMS\Dbal\Database\AdodbPreparedStatement $preparedStatement */
				$preparedStatement = GeneralUtility::makeInstance(\TYPO3\CMS\Dbal\Database\AdodbPreparedStatement::class, $query, $queryComponents, $this);
				if (!$preparedStatement->prepare()) {
					$preparedStatement = FALSE;
				}
				break;
			case 'userdefined':
				throw new \RuntimeException('prepare_PREPAREDquery is not implemented for userdefined handlers', 1394620167);
				/*
				$queryParts = $queryComponents['queryParts'];
				$preparedStatement = $this->handlerInstance[$this->lastHandlerKey]->exec_SELECTquery($queryParts['SELECT'], $queryParts['FROM'], $queryParts['WHERE'], $queryParts['GROUPBY'], $queryParts['ORDERBY'], $queryParts['LIMIT']);
				if (is_object($preparedStatement)) {
					$preparedStatement->TYPO3_DBAL_handlerType = 'userdefined';
					// Setting handler type in result object (for later recognition!)
					$preparedStatement->TYPO3_DBAL_tableList = $queryComponents['ORIG_tableName'];
				}
				break;
				*/
		}
		if ($this->printErrors && $this->sql_error()) {
			debug(array($this->lastQuery, $this->sql_error()));
		}
		if ($this->debug) {
			$data = array(
				'handlerType' => $queryComponents['handler'],
				'args' => $queryComponents,
				'ORIG_from_table' => $queryComponents['ORIG_tableName']
			);
			$this->debugHandler('prepare_PREPAREDquery', GeneralUtility::milliseconds() - $pt, $data);
		}
		// Return result handler.
		return $preparedStatement;
	}

	/**************************************
	 *
	 * Functions for quoting table/field names
	 *
	 **************************************/
	/**
	 * Quotes components of a SELECT subquery.
	 *
	 * @param array $components	Array of SQL query components
	 * @return array
	 */
	protected function quoteSELECTsubquery(array $components) {
		$components['SELECT'] = $this->_quoteFieldNames($components['SELECT']);
		$components['FROM'] = $this->_quoteFromTables($components['FROM']);
		$components['WHERE'] = $this->_quoteWhereClause($components['WHERE']);
		return $components;
	}

	/**
	 * Quotes field (and table) names with the quote character suitable for the DB being used
	 *
	 * @param string $select_fields List of fields to be used in query to DB
	 * @throws \InvalidArgumentException
	 * @return string Quoted list of fields to be in query to DB
	 */
	public function quoteFieldNames($select_fields) {
		if ($select_fields == '') {
			return '';
		}
		if ($this->runningNative()) {
			return $select_fields;
		}
		$select_fields = $this->SQLparser->parseFieldList($select_fields);
		if ($this->SQLparser->parse_error) {
			throw new \InvalidArgumentException($this->SQLparser->parse_error, 1310027490);
		}
		$select_fields = $this->_quoteFieldNames($select_fields);
		return $this->SQLparser->compileFieldList($select_fields);
	}

	/**
	 * Quotes field (and table) names in a SQL SELECT clause according to DB rules
	 *
	 * @param array $select_fields The parsed fields to quote
	 * @return array
	 * @see quoteFieldNames()
	 */
	protected function _quoteFieldNames(array $select_fields) {
		foreach ($select_fields as $k => $v) {
			if ($select_fields[$k]['field'] != '' && $select_fields[$k]['field'] != '*' && !is_numeric($select_fields[$k]['field'])) {
				$select_fields[$k]['field'] = $this->quoteName($select_fields[$k]['field']);
			}
			if ($select_fields[$k]['table'] != '' && !is_numeric($select_fields[$k]['table'])) {
				$select_fields[$k]['table'] = $this->quoteName($select_fields[$k]['table']);
			}
			if ($select_fields[$k]['as'] != '') {
				$select_fields[$k]['as'] = $this->quoteName($select_fields[$k]['as']);
			}
			if (isset($select_fields[$k]['func_content.']) && $select_fields[$k]['func_content.'][0]['func_content'] != '*') {
				$select_fields[$k]['func_content.'][0]['func_content'] = $this->quoteFieldNames($select_fields[$k]['func_content.'][0]['func_content']);
				$select_fields[$k]['func_content'] = $this->quoteFieldNames($select_fields[$k]['func_content']);
			}
			if (isset($select_fields[$k]['flow-control'])) {
				// Quoting flow-control statements
				if ($select_fields[$k]['flow-control']['type'] === 'CASE') {
					if (isset($select_fields[$k]['flow-control']['case_field'])) {
						$select_fields[$k]['flow-control']['case_field'] = $this->quoteFieldNames($select_fields[$k]['flow-control']['case_field']);
					}
					foreach ($select_fields[$k]['flow-control']['when'] as $key => $when) {
						$select_fields[$k]['flow-control']['when'][$key]['when_value'] = $this->_quoteWhereClause($when['when_value']);
					}
				}
			}
		}
		return $select_fields;
	}

	/**
	 * Quotes table names with the quote character suitable for the DB being used
	 *
	 * @param string $from_table List of tables to be selected from DB
	 * @return string Quoted list of tables to be selected from DB
	 */
	public function quoteFromTables($from_table) {
		if ($from_table === '') {
			return '';
		}
		if ($this->runningNative()) {
			return $from_table;
		}
		$from_table = $this->SQLparser->parseFromTables($from_table);
		$from_table = $this->_quoteFromTables($from_table);
		return $this->SQLparser->compileFromTables($from_table);
	}

	/**
	 * Quotes table names in a SQL FROM clause according to DB rules
	 *
	 * @param array $from_table The parsed FROM clause to quote
	 * @return array
	 * @see quoteFromTables()
	 */
	protected function _quoteFromTables(array $from_table) {
		foreach ($from_table as $k => $v) {
			$from_table[$k]['table'] = $this->quoteName($from_table[$k]['table']);
			if ($from_table[$k]['as'] != '') {
				$from_table[$k]['as'] = $this->quoteName($from_table[$k]['as']);
			}
			if (is_array($v['JOIN'])) {
				foreach ($v['JOIN'] as $joinCnt => $join) {
					$from_table[$k]['JOIN'][$joinCnt]['withTable'] = $this->quoteName($join['withTable']);
					$from_table[$k]['JOIN'][$joinCnt]['as'] = $join['as'] ? $this->quoteName($join['as']) : '';
					foreach ($from_table[$k]['JOIN'][$joinCnt]['ON'] as &$condition) {
						$condition['left']['table'] = $condition['left']['table'] ? $this->quoteName($condition['left']['table']) : '';
						$condition['left']['field'] = $this->quoteName($condition['left']['field']);
						$condition['right']['table'] = $condition['right']['table'] ? $this->quoteName($condition['right']['table']) : '';
						$condition['right']['field'] = $this->quoteName($condition['right']['field']);
					}
				}
			}
		}
		return $from_table;
	}

	/**
	 * Quotes the field (and table) names within a where clause with the quote character suitable for the DB being used
	 *
	 * @param string $where_clause A where clause that can be parsed by parseWhereClause
	 * @throws \InvalidArgumentException
	 * @return string Usable where clause with quoted field/table names
	 */
	public function quoteWhereClause($where_clause) {
		if ($where_clause === '' || $this->runningNative()) {
			return $where_clause;
		}
		$where_clause = $this->SQLparser->parseWhereClause($where_clause);
		if (is_array($where_clause)) {
			$where_clause = $this->_quoteWhereClause($where_clause);
			$where_clause = $this->SQLparser->compileWhereClause($where_clause);
		} else {
			throw new \InvalidArgumentException('Could not parse where clause', 1310027511);
		}
		return $where_clause;
	}

	/**
	 * Quotes field names in a SQL WHERE clause according to DB rules
	 *
	 * @param array $where_clause The parsed WHERE clause to quote
	 * @return array
	 * @see quoteWhereClause()
	 */
	protected function _quoteWhereClause(array $where_clause) {
		foreach ($where_clause as $k => $v) {
			// Look for sublevel:
			if (is_array($where_clause[$k]['sub'])) {
				$where_clause[$k]['sub'] = $this->_quoteWhereClause($where_clause[$k]['sub']);
			} elseif (isset($v['func'])) {
				switch ($where_clause[$k]['func']['type']) {
					case 'EXISTS':
						$where_clause[$k]['func']['subquery'] = $this->quoteSELECTsubquery($v['func']['subquery']);
						break;
					case 'FIND_IN_SET':
						// quoteStr that will be used for Oracle
						$pattern = str_replace($where_clause[$k]['func']['str'][1], '\\' . $where_clause[$k]['func']['str'][1], $where_clause[$k]['func']['str'][0]);
						// table is not really needed and may in fact be empty in real statements
						// but it's not overridden from \TYPO3\CMS\Core\Database\DatabaseConnection at the moment...
						$patternForLike = $this->escapeStrForLike($pattern, $where_clause[$k]['func']['table']);
						$where_clause[$k]['func']['str_like'] = $patternForLike;
						// Intentional fallthrough
					case 'IFNULL':
						// Intentional fallthrough
					case 'LOCATE':
						if ($where_clause[$k]['func']['table'] != '') {
							$where_clause[$k]['func']['table'] = $this->quoteName($v['func']['table']);
						}
						if ($where_clause[$k]['func']['field'] != '') {
							$where_clause[$k]['func']['field'] = $this->quoteName($v['func']['field']);
						}
						break;
				}
			} else {
				if ($where_clause[$k]['table'] != '') {
					$where_clause[$k]['table'] = $this->quoteName($where_clause[$k]['table']);
				}
				if (!is_numeric($where_clause[$k]['field'])) {
					$where_clause[$k]['field'] = $this->quoteName($where_clause[$k]['field']);
				}
				if (isset($where_clause[$k]['calc_table'])) {
					if ($where_clause[$k]['calc_table'] != '') {
						$where_clause[$k]['calc_table'] = $this->quoteName($where_clause[$k]['calc_table']);
					}
					if ($where_clause[$k]['calc_field'] != '') {
						$where_clause[$k]['calc_field'] = $this->quoteName($where_clause[$k]['calc_field']);
					}
				}
			}
			if ($where_clause[$k]['comparator']) {
				if (isset($v['value']['operator'])) {
					foreach ($where_clause[$k]['value']['args'] as $argK => $fieldDef) {
						$where_clause[$k]['value']['args'][$argK]['table'] = $this->quoteName($fieldDef['table']);
						$where_clause[$k]['value']['args'][$argK]['field'] = $this->quoteName($fieldDef['field']);
					}
				} else {
					// Detecting value type; list or plain:
					if (GeneralUtility::inList('NOTIN,IN', strtoupper(str_replace(array(' ', '
', '
', '	'), '', $where_clause[$k]['comparator'])))) {
						if (isset($v['subquery'])) {
							$where_clause[$k]['subquery'] = $this->quoteSELECTsubquery($v['subquery']);
						}
					} else {
						if (
							(!isset($where_clause[$k]['value'][1]) || $where_clause[$k]['value'][1] == '')
							&& is_string($where_clause[$k]['value'][0]) && strstr($where_clause[$k]['value'][0], '.')
						) {
							$where_clause[$k]['value'][0] = $this->quoteFieldNames($where_clause[$k]['value'][0]);
						}
					}
				}
			}
		}
		return $where_clause;
	}

	/**
	 * Quotes the field (and table) names within a group by clause with the quote
	 * character suitable for the DB being used
	 *
	 * @param string $groupBy A group by clause that can by parsed by parseFieldList
	 * @return string Usable group by clause with quoted field/table names
	 */
	protected function quoteGroupBy($groupBy) {
		if ($groupBy === '') {
			return '';
		}
		if ($this->runningNative()) {
			return $groupBy;
		}
		$groupBy = $this->SQLparser->parseFieldList($groupBy);
		$groupBy = $this->_quoteGroupBy($groupBy);
		return $this->SQLparser->compileFieldList($groupBy);
	}

	/**
	 * Quotes field names in a SQL GROUP BY clause according to DB rules
	 *
	 * @param array $groupBy The parsed GROUP BY clause to quote
	 * @return array
	 * @see quoteGroupBy()
	 */
	protected function _quoteGroupBy(array $groupBy) {
		foreach ($groupBy as $k => $v) {
			$groupBy[$k]['field'] = $this->quoteName($groupBy[$k]['field']);
			if ($groupBy[$k]['table'] != '') {
				$groupBy[$k]['table'] = $this->quoteName($groupBy[$k]['table']);
			}
		}
		return $groupBy;
	}

	/**
	 * Quotes the field (and table) names within an order by clause with the quote
	 * character suitable for the DB being used
	 *
	 * @param string $orderBy An order by clause that can by parsed by parseFieldList
	 * @return string Usable order by clause with quoted field/table names
	 */
	protected function quoteOrderBy($orderBy) {
		if ($orderBy === '') {
			return '';
		}
		if ($this->runningNative()) {
			return $orderBy;
		}
		$orderBy = $this->SQLparser->parseFieldList($orderBy);
		$orderBy = $this->_quoteOrderBy($orderBy);
		return $this->SQLparser->compileFieldList($orderBy);
	}

	/**
	 * Quotes field names in a SQL ORDER BY clause according to DB rules
	 *
	 * @param array $orderBy The parsed ORDER BY clause to quote
	 * @return 	array
	 * @see quoteOrderBy()
	 */
	protected function _quoteOrderBy(array $orderBy) {
		foreach ($orderBy as $k => $v) {
			if ($orderBy[$k]['table'] === '' && $v['field'] !== '' && ctype_digit($v['field'])) {
				continue;
			}
			$orderBy[$k]['field'] = $this->quoteName($orderBy[$k]['field']);
			if ($orderBy[$k]['table'] !== '') {
				$orderBy[$k]['table'] = $this->quoteName($orderBy[$k]['table']);
			}
		}
		return $orderBy;
	}

	/**************************************
	 *
	 * Various helper functions
	 *
	 **************************************/
	/**
	 * Escaping and quoting values for SQL statements.
	 *
	 * @param string $str Input string
	 * @param string $table Table name for which to quote string. Just enter the table that the field-value is selected from (and any DBAL will look up which handler to use and then how to quote the string!).
	 * @param bool $allowNull Whether to allow NULL values
	 * @return string Output string; Wrapped in single quotes and quotes in the string (" / ') and \ will be backslashed (or otherwise based on DBAL handler)
	 * @see quoteStr()
	 */
	public function fullQuoteStr($str, $table,  $allowNull = FALSE) {
		if ($allowNull && $str === NULL) {
			return 'NULL';
		}
		return '\'' . $this->quoteStr($str, $table) . '\'';
	}

	/**
	 * Substitution for PHP function "addslashes()"
	 * Use this function instead of the PHP addslashes() function when you build queries - this will prepare your code for DBAL.
	 * NOTICE: You must wrap the output of this function in SINGLE QUOTES to be DBAL compatible. Unless you have to apply the single quotes yourself you should rather use ->fullQuoteStr()!
	 *
	 * @param string $str Input string
	 * @param string $table Table name for which to quote string. Just enter the table that the field-value is selected from (and any DBAL will look up which handler to use and then how to quote the string!).
	 * @throws \RuntimeException
	 * @return string Output string; Quotes (" / ') and \ will be backslashed (or otherwise based on DBAL handler)
	 * @see quoteStr()
	 */
	public function quoteStr($str, $table) {
		$this->lastHandlerKey = $this->handler_getFromTableList($table);
		switch ((string)$this->handlerCfg[$this->lastHandlerKey]['type']) {
			case 'native':
				if ($this->handlerInstance[$this->lastHandlerKey]['link']) {
					if (!$this->isConnected()) {
						$this->connectDB();
					}
					$str = $this->handlerInstance[$this->lastHandlerKey]['link']->real_escape_string($str);
				} else {
					// link may be null when unit testing DBAL
					$str = str_replace('\'', '\\\'', $str);
				}
				break;
			case 'adodb':
				if (!$this->isConnected()) {
					$this->connectDB();
				}
				$str = substr($this->handlerInstance[$this->lastHandlerKey]->qstr($str), 1, -1);
				break;
			case 'userdefined':
				$str = $this->handlerInstance[$this->lastHandlerKey]->quoteStr($str);
				break;
			default:
				throw new \RuntimeException('No handler found!!!', 1310027655);
		}
		return $str;
	}

	/**
	 * Quotes an object name (table name, field, ...)
	 *
	 * @param string $name Object's name
	 * @param string $handlerKey Handler key
	 * @param bool $useBackticks If method NameQuote() is not used, whether to use backticks instead of driver-specific quotes
	 * @return string Properly-quoted object's name
	 */
	public function quoteName($name, $handlerKey = NULL, $useBackticks = FALSE) {
		$handlerKey = $handlerKey ? $handlerKey : $this->lastHandlerKey;
		$useNameQuote = isset($this->handlerCfg[$handlerKey]['config']['useNameQuote']) ? $this->handlerCfg[$handlerKey]['config']['useNameQuote'] : FALSE;
		if ($useNameQuote) {
			// Sometimes DataDictionary is not properly instantiated
			if (!is_object($this->handlerInstance[$handlerKey]->DataDictionary)) {
				$this->handlerInstance[$handlerKey]->DataDictionary = NewDataDictionary($this->handlerInstance[$handlerKey]);
			}
			return $this->handlerInstance[$handlerKey]->DataDictionary->NameQuote($name);
		} else {
			$quote = $useBackticks ? '`' : $this->handlerInstance[$handlerKey]->nameQuote;
			return $quote . $name . $quote;
		}
	}

	/**
	 * Return MetaType for native field type (ADOdb only!)
	 *
	 * @param string $type Native type as reported by admin_get_fields()
	 * @param string $table Table name for which query type string. Important for detection of DBMS handler of the query!
	 * @param int $maxLength
	 * @throws \RuntimeException
	 * @return string Meta type (currently ADOdb syntax only, http://phplens.com/lens/adodb/docs-adodb.htm#metatype)
	 */
	public function MetaType($type, $table, $maxLength = -1) {
		$this->lastHandlerKey = $this->handler_getFromTableList($table);
		$str = '';
		switch ((string)$this->handlerCfg[$this->lastHandlerKey]['type']) {
			case 'native':
				$str = $type;
				break;
			case 'adodb':
				if (in_array($table, $this->cache_fieldType)) {
					$rs = $this->handlerInstance[$this->lastHandlerKey]->SelectLimit('SELECT * FROM ' . $this->quoteFromTables($table), 1);
					$str = $rs->MetaType($type, $maxLength);
				}
				break;
			case 'userdefined':
				$str = $this->handlerInstance[$this->lastHandlerKey]->MetaType($str, $table, $maxLength);
				break;
			default:
				throw new \RuntimeException('No handler found!!!', 1310027685);
		}
		return $str;
	}

	/**
	 * Return MetaType for native MySQL field type
	 *
	 * @param string $t native type as reported as in mysqldump files
	 * @return string Meta type (currently ADOdb syntax only, http://phplens.com/lens/adodb/docs-adodb.htm#metatype)
	 */
	public function MySQLMetaType($t) {
		switch (strtoupper($t)) {
			case 'STRING':

			case 'CHAR':

			case 'VARCHAR':

			case 'TINYBLOB':

			case 'TINYTEXT':

			case 'ENUM':

			case 'SET':
				return 'C';
			case 'TEXT':

			case 'LONGTEXT':

			case 'MEDIUMTEXT':
				return 'XL';
			case 'IMAGE':

			case 'LONGBLOB':

			case 'BLOB':

			case 'MEDIUMBLOB':
				return 'B';
			case 'YEAR':

			case 'DATE':
				return 'D';
			case 'TIME':

			case 'DATETIME':

			case 'TIMESTAMP':
				return 'T';
			case 'FLOAT':

			case 'DOUBLE':
				return 'F';
			case 'INT':

			case 'INTEGER':

			case 'TINYINT':

			case 'SMALLINT':

			case 'MEDIUMINT':

			case 'BIGINT':
				return 'I8';
			default:
				return 'N';
		}
	}

	/**
	 * Return actual MySQL type for meta field type
	 *
	 * @param string $meta Meta type (currenly ADOdb syntax only, http://phplens.com/lens/adodb/docs-adodb.htm#metatype)
	 * @return string Native type as reported as in mysqldump files, uppercase
	 */
	public function MySQLActualType($meta) {
		switch (strtoupper($meta)) {
			case 'C':
				return 'VARCHAR';
			case 'XL':

			case 'X':
				return 'LONGTEXT';
			case 'C2':
				return 'VARCHAR';
			case 'X2':
				return 'LONGTEXT';
			case 'B':
				return 'LONGBLOB';
			case 'D':
				return 'DATE';
			case 'T':
				return 'DATETIME';
			case 'L':
				return 'TINYINT';
			case 'I':

			case 'I1':

			case 'I2':

			case 'I4':

			case 'I8':
				return 'BIGINT';
			case 'F':
				return 'DOUBLE';
			case 'N':
				return 'NUMERIC';
			default:
				return $meta;
		}
	}

	/**************************************
	 *
	 * SQL wrapper functions (Overriding parent methods)
	 * (For use in your applications)
	 *
	 **************************************/
	/**
	 * Returns the error status on the last query() execution
	 *
	 * @return string MySQLi error string.
	 */
	public function sql_error() {
		$output = '';
		switch ($this->handlerCfg[$this->lastHandlerKey]['type']) {
			case 'native':
				$output = $this->handlerInstance[$this->lastHandlerKey]['link']->error;
				break;
			case 'adodb':
				$output = $this->handlerInstance[$this->lastHandlerKey]->ErrorMsg();
				break;
			case 'userdefined':
				$output = $this->handlerInstance[$this->lastHandlerKey]->sql_error();
				break;
		}
		return $output;
	}

	/**
	 * Returns the error number on the last query() execution
	 *
	 * @return int MySQLi error number
	 */
	public function sql_errno() {
		$output = 0;
		switch ($this->handlerCfg[$this->lastHandlerKey]['type']) {
			case 'native':
				$output = $this->handlerInstance[$this->lastHandlerKey]['link']->errno;
				break;
			case 'adodb':
				$output = $this->handlerInstance[$this->lastHandlerKey]->ErrorNo();
				break;
			case 'userdefined':
				$output = $this->handlerInstance[$this->lastHandlerKey]->sql_errno();
				break;
		}
		return $output;
	}

	/**
	 * Returns the number of selected rows.
	 *
	 * @param bool|\mysqli_result|object $res MySQLi result object / DBAL object
	 * @return int Number of resulting rows
	 */
	public function sql_num_rows($res) {
		if ($res === FALSE) {
			return FALSE;
		}
		$handlerType = $this->determineHandlerType($res);
		$output = 0;
		switch ($handlerType) {
			case 'native':
				$output = $res->num_rows;
				break;
			case 'adodb':
				$output = method_exists($res, 'RecordCount') ? $res->RecordCount() : 0;
				break;
			case 'userdefined':
				$output = $res->sql_num_rows();
				break;
		}
		return $output;
	}

	/**
	 * Returns an associative array that corresponds to the fetched row, or FALSE if there are no more rows.
	 * MySQLi fetch_assoc() wrapper function
	 *
	 * @param bool|\mysqli_result|object $res MySQLi result object / DBAL object
	 * @return array|boolean Associative array of result row.
	 */
	public function sql_fetch_assoc($res) {
		$tableList = '';
		$output = FALSE;
		switch ($this->determineHandlerType($res)) {
			case 'native':
				$output = $res->fetch_assoc();
				$key = serialize($res);
				$tableList = $this->resourceIdToTableNameMap[$key];
				unset($this->resourceIdToTableNameMap[$key]);
				// Reading list of tables from SELECT query:
				break;
			case 'adodb':
				// Check if method exists for the current $res object.
				// If a table exists in TCA but not in the db, a error
				// occurred because $res is not a valid object.
				if (method_exists($res, 'FetchRow')) {
					$output = $res->FetchRow();
					$tableList = $res->TYPO3_DBAL_tableList;
					// Reading list of tables from SELECT query:
					// Removing all numeric/integer keys.
					// A workaround because in ADOdb we would need to know what we want before executing the query...
					// MSSQL does not support ADODB_FETCH_BOTH and always returns an assoc. array instead. So
					// we don't need to remove anything.
					if (is_array($output)) {
						if ($this->runningADOdbDriver('mssql')) {
							// MSSQL does not know such thing as an empty string. So it returns one space instead, which we must fix.
							foreach ($output as $key => $value) {
								if ($value === ' ') {
									$output[$key] = '';
								}
							}
						} else {
							foreach ($output as $key => $value) {
								if (is_integer($key)) {
									unset($output[$key]);
								}
							}
						}
					}
				}
				break;
			case 'userdefined':
				$output = $res->sql_fetch_assoc();
				$tableList = $res->TYPO3_DBAL_tableList;
				// Reading list of tables from SELECT query:
				break;
		}
		// Table/Fieldname mapping:
		if (is_array($output)) {
			if ($tables = $this->map_needMapping($tableList, TRUE)) {
				$output = $this->map_assocArray($output, $tables, 1);
			}
		}
		if ($output === NULL) {
			// Needed for compatibility
			$output = FALSE;
		}
		// Return result:
		return $output;
	}

	/**
	 * Returns an array that corresponds to the fetched row, or FALSE if there are no more rows.
	 * The array contains the values in numerical indices.
	 * MySQLi fetch_row() wrapper function
	 *
	 * @param bool|\mysqli_result|object $res MySQLi result object / DBAL object
	 * @return array|boolean Array with result rows.
	 */
	public function sql_fetch_row($res) {
		$output = FALSE;
		switch ($this->determineHandlerType($res)) {
			case 'native':
				$output = $res->fetch_row();
				if ($output === NULL) {
					// Needed for compatibility
					$output = FALSE;
				}
				break;
			case 'adodb':
				// Check if method exists for the current $res object.
				// If a table exists in TCA but not in the db, a error
				// occurred because $res is not a valid object.
				if (method_exists($res, 'FetchRow')) {
					$output = $res->FetchRow();
					// Removing all assoc. keys.
					// A workaround because in ADOdb we would need to know what we want before executing the query...
					// MSSQL does not support ADODB_FETCH_BOTH and always returns an assoc. array instead. So
					// we need to convert resultset.
					if (is_array($output)) {
						$keyIndex = 0;
						foreach ($output as $key => $value) {
							unset($output[$key]);
							if (is_integer($key) || $this->runningADOdbDriver('mssql')) {
								$output[$keyIndex] = $value;
								if ($value === ' ') {
									// MSSQL does not know such thing as an empty string. So it returns one space instead, which we must fix.
									$output[$keyIndex] = '';
								}
								$keyIndex++;
							}
						}
					}
				}
				break;
			case 'userdefined':
				$output = $res->sql_fetch_row();
				break;
		}
		if ($output === NULL) {
			// Needed for compatibility
			$output = FALSE;
		}
		return $output;
	}

	/**
	 * Free result memory
	 * free_result() wrapper function
	 *
	 * @param bool|\mysqli_result|object $res MySQLi result object / DBAL object
	 * @return bool Returns TRUE on success or FALSE on failure.
	 */
	public function sql_free_result($res) {
		if ($res === FALSE) {
			return FALSE;
		}
		$output = TRUE;
		switch ($this->determineHandlerType($res)) {
			case 'native':
				$res->free();
				break;
			case 'adodb':
				if (method_exists($res, 'Close')) {
					$res->Close();
					unset($res);
					$output = TRUE;
				} else {
					$output = FALSE;
				}
				break;
			case 'userdefined':
				unset($res);
				break;
		}
		return $output;
	}

	/**
	 * Determine handler type by result set
	 *
	 * @param bool|\mysqli_result|object $res MySQLi result set / DBAL Object
	 * @return bool|string
	 */
	protected function determineHandlerType($res) {
		if (is_object($res) && !$res instanceof \mysqli_result) {
			$handlerType = $res->TYPO3_DBAL_handlerType;
		} elseif ($res instanceof \mysqli_result) {
			$handlerType = 'native';
		} else {
			$handlerType = FALSE;
		}
		return $handlerType;
	}

	/**
	 * Get the ID generated from the previous INSERT operation
	 *
	 * @return int The uid of the last inserted record.
	 */
	public function sql_insert_id() {
		$output = 0;
		switch ($this->handlerCfg[$this->lastHandlerKey]['type']) {
			case 'native':
				$output = $this->handlerInstance[$this->lastHandlerKey]['link']->insert_id;
				break;
			case 'adodb':
				$output = $this->handlerInstance[$this->lastHandlerKey]->last_insert_id;
				break;
			case 'userdefined':
				$output = $this->handlerInstance[$this->lastHandlerKey]->sql_insert_id();
				break;
		}
		return $output;
	}

	/**
	 * Returns the number of rows affected by the last INSERT, UPDATE or DELETE query
	 *
	 * @return int Number of rows affected by last query
	 */
	public function sql_affected_rows() {
		$output = 0;
		switch ($this->handlerCfg[$this->lastHandlerKey]['type']) {
			case 'native':
				$output = $this->handlerInstance[$this->lastHandlerKey]['link']->affected_rows;
				break;
			case 'adodb':
				$output = $this->handlerInstance[$this->lastHandlerKey]->Affected_Rows();
				break;
			case 'userdefined':
				$output = $this->handlerInstance[$this->lastHandlerKey]->sql_affected_rows();
				break;
		}
		return $output;
	}

	/**
	 * Move internal result pointer
	 *
	 * @param bool|\mysqli_result|object $res MySQLi result object / DBAL object
	 * @param int $seek Seek result number.
	 * @return bool Returns TRUE on success or FALSE on failure.
	 */
	public function sql_data_seek($res, $seek) {
		$output = TRUE;
		switch ($this->determineHandlerType($res)) {
			case 'native':
				$output = $res->data_seek($seek);
				break;
			case 'adodb':
				$output = $res->Move($seek);
				break;
			case 'userdefined':
				$output = $res->sql_data_seek($seek);
				break;
		}
		return $output;
	}

	/**
	 * Get the type of the specified field in a result
	 *
	 * If the first parameter is a string, it is used as table name for the lookup.
	 *
	 * @param string $table MySQL result pointer (of SELECT query) / DBAL object / table name
	 * @param int $field Field index. In case of ADOdb a string (field name!)
	 * @return string Returns the type of the specified field index
	 */
	public function sql_field_metatype($table, $field) {
		// If $table and/or $field are mapped, use the original names instead
		foreach ($this->mapping as $tableName => $tableMapInfo) {
			if (isset($tableMapInfo['mapFieldNames'])) {
				foreach ($tableMapInfo['mapFieldNames'] as $fieldName => $fieldMapInfo) {
					if ($fieldMapInfo === $field) {
						// Field name is mapped => use original name
						$field = $fieldName;
					}
				}
			}
		}
		return $this->cache_fieldType[$table][$field]['metaType'];
	}

	/**
	 * Get the type of the specified field in a result
	 * mysql_field_type() wrapper function
	 *
	 * @param bool|\mysqli_result|object $res MySQLi result object / DBAL object
	 * @param int $pointer Field index.
	 * @return string Returns the name of the specified field index, or FALSE on error
	 */
	public function sql_field_type($res, $pointer) {
		if ($res === NULL) {
			debug(array('no res in sql_field_type!'));
			return 'text';
		} elseif (is_string($res)) {
			if ($res === 'tx_dbal_debuglog') {
				return 'text';
			}
			$handlerType = 'adodb';
		} else {
			$handlerType = $this->determineHandlerType($res);
		}
		$output = '';
		switch ($handlerType) {
			case 'native':
				$metaInfo = $res->fetch_field_direct($pointer);
				if ($metaInfo) {
					$output = $this->mysqlDataTypeMapping[$metaInfo->type];
				} else {
					$output = '';
				}
				break;
			case 'adodb':
				if (is_string($pointer)) {
					$output = $this->cache_fieldType[$res][$pointer]['type'];
				}
				break;
			case 'userdefined':
				$output = $res->sql_field_type($pointer);
				break;
		}
		return $output;
	}

	/**********
	 *
	 * Legacy functions, bound to _DEFAULT handler. (Overriding parent methods)
	 * Deprecated or still experimental.
	 *
	 **********/
	/**
	 * Executes query
	 *
	 * EXPERIMENTAL - This method will make its best to handle the query correctly
	 * but if it cannot, it will simply pass the query to DEFAULT handler.
	 *
	 * You should use exec_* function from this class instead!
	 * If you don't, anything that does not use the _DEFAULT handler will probably break!
	 *
	 * MySQLi query() wrapper function
	 * Beware: Use of this method should be avoided as it is experimentally supported by DBAL. You should consider
	 * using exec_SELECTquery() and similar methods instead.
	 *
	 * @param string $query Query to execute
	 * @return bool|\mysqli_result|object MySQLi result object / DBAL object
	 */
	public function sql_query($query) {
		$globalConfig = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['dbal']);
		if ($globalConfig['sql_query.']['passthrough']) {
			return parent::sql_query($query);
		}
		// This method is heavily used by Extbase, try to handle it with DBAL-native methods
		$queryParts = $this->SQLparser->parseSQL($query);
		if (is_array($queryParts) && GeneralUtility::inList('SELECT,UPDATE,INSERT,DELETE', $queryParts['type'])) {
			return $this->exec_query($queryParts);
		}
		$sqlResult = NULL;
		switch ($this->handlerCfg['_DEFAULT']['type']) {
			case 'native':
				if (!$this->isConnected()) {
					$this->connectDB();
				}
				$sqlResult = $this->handlerInstance['_DEFAULT']['link']->query($query);
				break;
			case 'adodb':
				$sqlResult = $this->handlerInstance['_DEFAULT']->Execute($query);
				$sqlResult->TYPO3_DBAL_handlerType = 'adodb';
				break;
			case 'userdefined':
				$sqlResult = $this->handlerInstance['_DEFAULT']->sql_query($query);
				$sqlResult->TYPO3_DBAL_handlerType = 'userdefined';
				break;
		}
		$this->lastHandlerKey = '_DEFAULT';
		if ($this->printErrors && $this->sql_error()) {
			debug(array($this->lastQuery, $this->sql_error()));
		}
		return $sqlResult;
	}

	/**
	 * Open a (persistent) connection to a MySQL server
	 *
	 * @return bool|void
	 */
	public function sql_pconnect() {
		return $this->handler_init('_DEFAULT');
	}

	/**
	 * Select a SQL database
	 *
	 * @return bool Returns TRUE on success or FALSE on failure.
	 */
	public function sql_select_db() {
		$databaseName = $this->handlerCfg[$this->lastHandlerKey]['config']['database'];
		$ret = TRUE;
		if ((string)$this->handlerCfg[$this->lastHandlerKey]['type'] === 'native') {
			$ret = $this->handlerInstance[$this->lastHandlerKey]['link']->select_db($databaseName);
		}
		if (!$ret) {
			GeneralUtility::sysLog(
				'Could not select MySQL database ' . $databaseName . ': ' . $this->sql_error(),
				'Core',
				GeneralUtility::SYSLOG_SEVERITY_FATAL
			);
		}
		return $ret;
	}

	/**************************************
	 *
	 * SQL admin functions
	 * (For use in the Install Tool and Extension Manager)
	 *
	 **************************************/
	/**
	 * Listing databases from current MySQL connection. NOTICE: It WILL try to select those databases and thus break selection of current database.
	 * This is only used as a service function in the (1-2-3 process) of the Install Tool.
	 * In any case a lookup should be done in the _DEFAULT handler DBMS then.
	 * Use in Install Tool only!
	 *
	 * @return array Each entry represents a database name
	 * @throws \RuntimeException
	 */
	public function admin_get_dbs() {
		$dbArr = array();
		$this->lastHandlerKey = '_DEFAULT';
		switch ($this->handlerCfg['_DEFAULT']['type']) {
			case 'native':
				/** @var \mysqli_result $db_list */
				$db_list = $this->query("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA");
				$oldDb = $this->handlerCfg[$this->lastHandlerKey]['config']['database'];
				while ($row = $db_list->fetch_object()) {
					$this->handlerCfg[$this->lastHandlerKey]['config']['database'] = $row->SCHEMA_NAME;
					if ($this->sql_select_db()) {
						$dbArr[] = $row->SCHEMA_NAME;
					}
				}
				$this->handlerCfg[$this->lastHandlerKey]['config']['database'] = $oldDb;
				$db_list->free();
				break;
			case 'adodb':
				// check needed for install tool - otherwise it will just die because the call to
				// MetaDatabases is done on a stdClass instance
				if (method_exists($this->handlerInstance['_DEFAULT'], 'MetaDatabases')) {
					$sqlDBs = $this->handlerInstance['_DEFAULT']->MetaDatabases();
					if (is_array($sqlDBs)) {
						foreach ($sqlDBs as $k => $theDB) {
							$dbArr[] = $theDB;
						}
					}
				}
				break;
			case 'userdefined':
				$dbArr = $this->handlerInstance['_DEFAULT']->admin_get_tables();
				break;
		}
		return $dbArr;
	}

	/**
	 * Returns the list of tables from the default database, TYPO3_db (quering the DBMS)
	 * In a DBAL this method should 1) look up all tables from the DBMS  of
	 * the _DEFAULT handler and then 2) add all tables *configured* to be managed by other handlers
	 *
	 * @return array Array with tablenames as key and arrays with status information as value
	 */
	public function admin_get_tables() {
		$whichTables = array();
		// Getting real list of tables:
		switch ($this->handlerCfg['_DEFAULT']['type']) {
			case 'native':
				$tables_result = $this->query('SHOW TABLE STATUS FROM `' . TYPO3_db . '`');
				if (!$this->sql_error()) {
					while ($theTable = $this->sql_fetch_assoc($tables_result)) {
						$whichTables[$theTable['Name']] = $theTable;
					}
				}
				$tables_result->free();
				break;
			case 'adodb':
				// check needed for install tool - otherwise it will just die because the call to
				// MetaTables is done on a stdClass instance
				if (method_exists($this->handlerInstance['_DEFAULT'], 'MetaTables')) {
					$sqlTables = $this->handlerInstance['_DEFAULT']->MetaTables('TABLES');
					foreach ($sqlTables as $k => $theTable) {
						if (preg_match('/BIN\\$/', $theTable)) {
							// Skip tables from the Oracle 10 Recycle Bin
							continue;
						}
						$whichTables[$theTable] = $theTable;
					}
				}
				break;
			case 'userdefined':
				$whichTables = $this->handlerInstance['_DEFAULT']->admin_get_tables();
				break;
		}
		// Check mapping:
		if (is_array($this->mapping) && count($this->mapping)) {
			// Mapping table names in reverse, first getting list of real table names:
			$tMap = array();
			foreach ($this->mapping as $tN => $tMapInfo) {
				if (isset($tMapInfo['mapTableName'])) {
					$tMap[$tMapInfo['mapTableName']] = $tN;
				}
			}
			// Do mapping:
			$newList = array();
			foreach ($whichTables as $tN => $tDefinition) {
				if (isset($tMap[$tN])) {
					$tN = $tMap[$tN];
				}
				$newList[$tN] = $tDefinition;
			}
			$whichTables = $newList;
		}
		// Adding tables configured to reside in other DBMS (handler by other handlers than the default):
		if (is_array($this->table2handlerKeys)) {
			foreach ($this->table2handlerKeys as $key => $handlerKey) {
				$whichTables[$key] = $key;
			}
		}
		return $whichTables;
	}

	/**
	 * Returns information about each field in the $table (quering the DBMS)
	 * In a DBAL this should look up the right handler for the table and return compatible information
	 * This function is important not only for the Install Tool but probably for
	 * DBALs as well since they might need to look up table specific information
	 * in order to construct correct queries. In such cases this information should
	 * probably be cached for quick delivery.
	 *
	 * @param string $tableName Table name
	 * @return array Field information in an associative array with fieldname => field row
	 */
	public function admin_get_fields($tableName) {
		$output = array();
		// Do field mapping if needed:
		$ORIG_tableName = $tableName;
		if ($tableArray = $this->map_needMapping($tableName)) {
			// Table name:
			if ($this->mapping[$tableName]['mapTableName']) {
				$tableName = $this->mapping[$tableName]['mapTableName'];
			}
		}
		// Find columns
		$this->lastHandlerKey = $this->handler_getFromTableList($tableName);
		switch ((string)$this->handlerCfg[$this->lastHandlerKey]['type']) {
			case 'native':
				/** @var \mysqli_result $columns_res */
				$columns_res = $this->query('SHOW columns FROM ' . $tableName);
				while ($fieldRow = $columns_res->fetch_assoc()) {
					$output[$fieldRow['Field']] = $fieldRow;
				}
				$columns_res->free();
				break;
			case 'adodb':
				$fieldRows = $this->handlerInstance[$this->lastHandlerKey]->MetaColumns($tableName, FALSE);
				if (is_array($fieldRows)) {
					foreach ($fieldRows as $k => $fieldRow) {
						settype($fieldRow, 'array');
						$fieldRow['Field'] = $fieldRow['name'];
						$ntype = $this->MySQLActualType($this->MetaType($fieldRow['type'], $tableName));
						$ntype .= $fieldRow['max_length'] != -1 ? ($ntype == 'INT' ? '(11)' : '(' . $fieldRow['max_length'] . ')') : '';
						$fieldRow['Type'] = strtolower($ntype);
						$fieldRow['Null'] = '';
						$fieldRow['Key'] = '';
						$fieldRow['Default'] = $fieldRow['default_value'];
						$fieldRow['Extra'] = '';
						$output[$fieldRow['name']] = $fieldRow;
					}
				}
				break;
			case 'userdefined':
				$output = $this->handlerInstance[$this->lastHandlerKey]->admin_get_fields($tableName);
				break;
		}
		// mapping should be done:
		if (is_array($tableArray) && is_array($this->mapping[$ORIG_tableName]['mapFieldNames'])) {
			$revFields = array_flip($this->mapping[$ORIG_tableName]['mapFieldNames']);
			$newOutput = array();
			foreach ($output as $fN => $fInfo) {
				if (isset($revFields[$fN])) {
					$fN = $revFields[$fN];
					$fInfo['Field'] = $fN;
				}
				$newOutput[$fN] = $fInfo;
			}
			$output = $newOutput;
		}
		return $output;
	}

	/**
	 * Returns information about each index key in the $table (quering the DBMS)
	 * In a DBAL this should look up the right handler for the table and return compatible information
	 *
	 * @param string $tableName Table name
	 * @return array Key information in a numeric array
	 */
	public function admin_get_keys($tableName) {
		$output = array();
		// Do field mapping if needed:
		$ORIG_tableName = $tableName;
		if ($tableArray = $this->map_needMapping($tableName)) {
			// Table name:
			if ($this->mapping[$tableName]['mapTableName']) {
				$tableName = $this->mapping[$tableName]['mapTableName'];
			}
		}
		// Find columns
		$this->lastHandlerKey = $this->handler_getFromTableList($tableName);
		switch ((string)$this->handlerCfg[$this->lastHandlerKey]['type']) {
			case 'native':
				/** @var \mysqli_result $keyRes */
				$keyRes = $this->query('SHOW keys FROM ' . $tableName);
				while ($keyRow = $keyRes->fetch_assoc()) {
					$output[] = $keyRow;
				}
				$keyRes->free();
				break;
			case 'adodb':
				$keyRows = $this->handlerInstance[$this->lastHandlerKey]->MetaIndexes($tableName);
				if ($keyRows !== FALSE) {
					foreach ($keyRows as $k => $theKey) {
						$theKey['Table'] = $tableName;
						$theKey['Non_unique'] = (int)(!$theKey['unique']);
						$theKey['Key_name'] = str_replace($tableName . '_', '', $k);
						// the following are probably not needed anyway...
						$theKey['Collation'] = '';
						$theKey['Cardinality'] = '';
						$theKey['Sub_part'] = '';
						$theKey['Packed'] = '';
						$theKey['Null'] = '';
						$theKey['Index_type'] = '';
						$theKey['Comment'] = '';
						// now map multiple fields into multiple rows (we mimic MySQL, remember...)
						$keycols = $theKey['columns'];
						foreach ($keycols as $c => $theCol) {
							$theKey['Seq_in_index'] = $c + 1;
							$theKey['Column_name'] = $theCol;
							$output[] = $theKey;
						}
					}
				}
				$priKeyRow = $this->handlerInstance[$this->lastHandlerKey]->MetaPrimaryKeys($tableName);
				$theKey = array();
				$theKey['Table'] = $tableName;
				$theKey['Non_unique'] = 0;
				$theKey['Key_name'] = 'PRIMARY';
				// the following are probably not needed anyway...
				$theKey['Collation'] = '';
				$theKey['Cardinality'] = '';
				$theKey['Sub_part'] = '';
				$theKey['Packed'] = '';
				$theKey['Null'] = '';
				$theKey['Index_type'] = '';
				$theKey['Comment'] = '';
				// now map multiple fields into multiple rows (we mimic MySQL, remember...)
				if ($priKeyRow !== FALSE) {
					foreach ($priKeyRow as $c => $theCol) {
						$theKey['Seq_in_index'] = $c + 1;
						$theKey['Column_name'] = $theCol;
						$output[] = $theKey;
					}
				}
				break;
			case 'userdefined':
				$output = $this->handlerInstance[$this->lastHandlerKey]->admin_get_keys($tableName);
				break;
		}
		// mapping should be done:
		if (is_array($tableArray) && is_array($this->mapping[$ORIG_tableName]['mapFieldNames'])) {
			$revFields = array_flip($this->mapping[$ORIG_tableName]['mapFieldNames']);
			$newOutput = array();
			foreach ($output as $kN => $kInfo) {
				// Table:
				$kInfo['Table'] = $ORIG_tableName;
				// Column
				if (isset($revFields[$kInfo['Column_name']])) {
					$kInfo['Column_name'] = $revFields[$kInfo['Column_name']];
				}
				// Write it back:
				$newOutput[$kN] = $kInfo;
			}
			$output = $newOutput;
		}
		return $output;
	}

	/**
	 * Returns information about the character sets supported by the current DBM
	 * This function is important not only for the Install Tool but probably for
	 * DBALs as well since they might need to look up table specific information
	 * in order to construct correct queries. In such cases this information should
	 * probably be cached for quick delivery.
	 *
	 * This is used by the Install Tool to convert tables tables with non-UTF8 charsets
	 * Use in Install Tool only!
	 *
	 * @return array Array with Charset as key and an array of "Charset", "Description", "Default collation", "Maxlen" as values
	 */
	public function admin_get_charsets() {
		$output = array();
		if ((string)$this->handlerCfg[$this->lastHandlerKey]['type'] === 'native') {
			/** @var \mysqli_result $columns_res */
			$columns_res = $this->query('SHOW CHARACTER SET');
			if ($columns_res !== FALSE) {
				while ($row = $columns_res->fetch_assoc()) {
					$output[$row['Charset']] = $row;
				}
				$columns_res->free();
			}
		}
		return $output;
	}

	/**
	 * mysqli() wrapper function, used by the Install Tool and EM for all queries regarding management of the database!
	 *
	 * @param string $query Query to execute
	 * @throws \InvalidArgumentException
	 * @return bool|\mysqli_result|object MySQLi result object / DBAL object
	 */
	public function admin_query($query) {
		$parsedQuery = $this->SQLparser->parseSQL($query);
		if (!is_array($parsedQuery)) {
			throw new \InvalidArgumentException('ERROR: Query could not be parsed: "' . htmlspecialchars($parsedQuery) . '". Query: "' . htmlspecialchars($query) . '"', 1310027793);
		}
		$ORIG_table = $parsedQuery['TABLE'];
		// Process query based on type:
		switch ($parsedQuery['type']) {
			case 'CREATETABLE':

			case 'ALTERTABLE':

			case 'DROPTABLE':
				$this->clearCachedFieldInfo();
				$this->map_genericQueryParsed($parsedQuery);
				break;
			case 'INSERT':

			case 'TRUNCATETABLE':
				$this->map_genericQueryParsed($parsedQuery);
				break;
			case 'CREATEDATABASE':
				throw new \InvalidArgumentException('Creating a database with DBAL is not supported. Did you really read the manual?', 1310027716);
				break;
			default:
				throw new \InvalidArgumentException('ERROR: Invalid Query type (' . $parsedQuery['type'] . ') for ->admin_query() function!: "' . htmlspecialchars($query) . '"', 1310027740);
		}
		// Setting query array (for other applications to access if needed)
		$this->lastParsedAndMappedQueryArray = $parsedQuery;
		// Execute query (based on handler derived from the TABLE name which we actually know for once!)
		$result = NULL;
		$this->lastHandlerKey = $this->handler_getFromTableList($ORIG_table);
		switch ((string)$this->handlerCfg[$this->lastHandlerKey]['type']) {
			case 'native':
				// Compiling query:
				$compiledQuery = $this->SQLparser->compileSQL($this->lastParsedAndMappedQueryArray);
				if (in_array($this->lastParsedAndMappedQueryArray['type'], array('INSERT', 'DROPTABLE', 'ALTERTABLE'))) {
					$result = $this->query($compiledQuery);
				} else {
					$result = $this->query($compiledQuery[0]);
				}
				break;
			case 'adodb':
				// Compiling query:
				$compiledQuery = $this->SQLparser->compileSQL($this->lastParsedAndMappedQueryArray);
				switch ($this->lastParsedAndMappedQueryArray['type']) {
					case 'INSERT':
						$result = $this->exec_INSERTquery($this->lastParsedAndMappedQueryArray['TABLE'], $compiledQuery);
						break;
					case 'TRUNCATETABLE':
						$result = $this->exec_TRUNCATEquery($this->lastParsedAndMappedQueryArray['TABLE']);
						break;
					default:
						$result = $this->handlerInstance[$this->lastHandlerKey]->DataDictionary->ExecuteSQLArray($compiledQuery);
				}
				break;
			case 'userdefined':
				// Compiling query:
				$compiledQuery = $this->SQLparser->compileSQL($this->lastParsedAndMappedQueryArray);
				$result = $this->handlerInstance[$this->lastHandlerKey]->admin_query($compiledQuery);
			default:
		}
		return $result;
	}

	/************************************
	 *
	 * Handler management
	 *
	 **************************************/
	/**
	 * Return the handler key pointing to an appropriate database handler as found in $this->handlerCfg array
	 * Notice: TWO or more tables in the table list MUST use the SAME handler key - otherwise a fatal error is thrown!
	 *         (Logically, no database can possibly join two tables from separate sources!)
	 *
	 * @param string $tableList Table list, eg. "pages" or "pages, tt_content" or "pages AS A, tt_content AS B
	 * @throws \RuntimeException
	 * @return string Handler key (see $this->handlerCfg array) for table
	 */
	public function handler_getFromTableList($tableList) {
		$key = $tableList;
		if (!isset($this->cache_handlerKeyFromTableList[$key])) {
			// Get tables separated:
			$_tableList = $tableList;
			$tableArray = $this->SQLparser->parseFromTables($_tableList);
			// If success, traverse the tables:
			if (is_array($tableArray) && count($tableArray)) {
				$outputHandlerKey = '';
				foreach ($tableArray as $vArray) {
					// Find handler key, select "_DEFAULT" if none is specifically configured:
					$handlerKey = $this->table2handlerKeys[$vArray['table']] ? $this->table2handlerKeys[$vArray['table']] : '_DEFAULT';
					// In case of separate handler keys for joined tables:
					if ($outputHandlerKey && $handlerKey != $outputHandlerKey) {
						throw new \RuntimeException('DBAL fatal error: Tables in this list "' . $tableList . '" didn\'t use the same DB handler!', 1310027833);
					}
					$outputHandlerKey = $handlerKey;
				}
				// Check initialized state; if handler is NOT initialized (connected) then we will connect it!
				if (!isset($this->handlerInstance[$outputHandlerKey])) {
					$this->handler_init($outputHandlerKey);
				}
				// Return handler key:
				$this->cache_handlerKeyFromTableList[$key] = $outputHandlerKey;
			} else {
				throw new \RuntimeException('DBAL fatal error: No handler found in handler_getFromTableList() for: "' . $tableList . '" (' . $tableArray . ')', 1310027933);
			}
		}
		return $this->cache_handlerKeyFromTableList[$key];
	}

	/**
	 * Initialize handler (connecting to database)
	 *
	 * @param string $handlerKey Handler key
	 * @return bool If connection went well, return TRUE
	 * @throws \RuntimeException
	 * @see handler_getFromTableList()
	 */
	public function handler_init($handlerKey) {
		if (!isset($this->handlerCfg[$handlerKey]) || !is_array($this->handlerCfg[$handlerKey])) {
			throw new \RuntimeException('ERROR: No handler for key "' . $handlerKey . '"', 1310028018);
		}
		if ($handlerKey === '_DEFAULT') {
			// Overriding the _DEFAULT handler configuration of username, password, localhost and database name:
			$this->handlerCfg[$handlerKey]['config']['username'] = $this->databaseUsername;
			$this->handlerCfg[$handlerKey]['config']['password'] = $this->databaseUserPassword;
			$this->handlerCfg[$handlerKey]['config']['host'] = $this->databaseHost;
			$this->handlerCfg[$handlerKey]['config']['port'] = (int)$this->databasePort;
			$this->handlerCfg[$handlerKey]['config']['database'] = $this->databaseName;
		}
		$cfgArray = $this->handlerCfg[$handlerKey];
		if (!$cfgArray['config']['database']) {
			// Configuration is incomplete
			return FALSE;
		}

		$output = FALSE;
		switch ((string)$cfgArray['type']) {
			case 'native':
				$host = $cfgArray['config']['host'];
				if (!$GLOBALS['TYPO3_CONF_VARS']['SYS']['no_pconnect']) {
					$host = 'p:' . $host;
				}
				$link = mysqli_init();
				$connected = $link->real_connect(
					$host,
					$cfgArray['config']['username'],
					$cfgArray['config']['password'],
					$cfgArray['config']['database'],
					isset($cfgArray['config']['port']) ? $cfgArray['config']['port'] : ''
				);
				if ($connected) {
					// Set handler instance:
					$this->handlerInstance[$handlerKey] = array('handlerType' => 'native', 'link' => $link);

					if ($link->set_charset($this->connectionCharset) === FALSE) {
						GeneralUtility::sysLog(
							'Error setting connection charset to "' . $this->connectionCharset . '"',
							'Core',
							GeneralUtility::SYSLOG_SEVERITY_ERROR
						);
					}

					// For default, set ->link (see \TYPO3\CMS\Core\Database\DatabaseConnection)
					if ($handlerKey === '_DEFAULT') {
						$this->link = $link;
						$this->isConnected = TRUE;
						$this->lastHandlerKey = $handlerKey;
						foreach ($this->initializeCommandsAfterConnect as $command) {
							if ($this->query($command) === FALSE) {
								GeneralUtility::sysLog(
									'Could not initialize DB connection with query "' . $command . '": ' . $this->sql_error(),
									'Core',
									GeneralUtility::SYSLOG_SEVERITY_ERROR
								);
							}
						}
						$this->setSqlMode();
						$this->checkConnectionCharset();
					}

					$output = TRUE;
				} else {
					GeneralUtility::sysLog('Could not connect to MySQL server ' . $cfgArray['config']['host'] . ' with user ' . $cfgArray['config']['username'] . '.', 'Core', 4);
				}
				break;
			case 'adodb':
				$output = TRUE;
				require_once \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('adodb') . 'adodb/adodb.inc.php';
				if (!defined('ADODB_FORCE_NULLS')) {
					define('ADODB_FORCE_NULLS', 1);
				}
				$GLOBALS['ADODB_FORCE_TYPE'] = ADODB_FORCE_VALUE;
				$GLOBALS['ADODB_FETCH_MODE'] = ADODB_FETCH_BOTH;
				$this->handlerInstance[$handlerKey] = ADONewConnection($cfgArray['config']['driver']);
				// Set driver-specific options
				if (isset($cfgArray['config']['driverOptions'])) {
					foreach ($cfgArray['config']['driverOptions'] as $optionName => $optionValue) {
						$optionSetterName = 'set' . ucfirst($optionName);
						if (method_exists($this->handlerInstance[$handlerKey], $optionSetterName)) {
							$this->handlerInstance[$handlerKey]->{$optionSetterName}($optionValue);
						} else {
							$this->handlerInstance[$handlerKey]->{$optionName} = $optionValue;
						}
					}
				}
				if ($GLOBALS['TYPO3_CONF_VARS']['SYS']['no_pconnect']) {
					$this->handlerInstance[$handlerKey]->Connect($cfgArray['config']['host'] . (isset($cfgArray['config']['port']) ? ':' . $cfgArray['config']['port'] : ''), $cfgArray['config']['username'], $cfgArray['config']['password'], $cfgArray['config']['database']);
				} else {
					$this->handlerInstance[$handlerKey]->PConnect($cfgArray['config']['host'] . (isset($cfgArray['config']['port']) ? ':' . $cfgArray['config']['port'] : ''), $cfgArray['config']['username'], $cfgArray['config']['password'], $cfgArray['config']['database']);
				}
				if (!$this->handlerInstance[$handlerKey]->isConnected()) {
					$dsn = $cfgArray['config']['driver'] . '://' . $cfgArray['config']['username'] . ((string)$cfgArray['config']['password'] !== '' ? ':XXXX@' : '') . $cfgArray['config']['host'] . (isset($cfgArray['config']['port']) ? ':' . $cfgArray['config']['port'] : '') . '/' . $cfgArray['config']['database'] . ($GLOBALS['TYPO3_CONF_VARS']['SYS']['no_pconnect'] ? '' : '?persistent=1');
					GeneralUtility::sysLog('Could not connect to DB server using ADOdb on ' . $cfgArray['config']['host'] . ' with user ' . $cfgArray['config']['username'] . '.', 'Core', 4);
					error_log('DBAL error: Connection to ' . $dsn . ' failed. Maybe PHP doesn\'t support the database?');
					$output = FALSE;
				} else {
					$this->handlerInstance[$handlerKey]->DataDictionary = NewDataDictionary($this->handlerInstance[$handlerKey]);
					$this->handlerInstance[$handlerKey]->last_insert_id = 0;
					if (isset($cfgArray['config']['sequenceStart'])) {
						$this->handlerInstance[$handlerKey]->sequenceStart = $cfgArray['config']['sequenceStart'];
					} else {
						$this->handlerInstance[$handlerKey]->sequenceStart = 1;
					}
				}
				break;
			case 'userdefined':
				// Find class file:
				$fileName = GeneralUtility::getFileAbsFileName($cfgArray['config']['classFile']);
				if (@is_file($fileName)) {
					require_once $fileName;
				} else {
					throw new \RuntimeException('DBAL error: "' . $fileName . '" was not a file to include.', 1310027975);
				}
				// Initialize:
				$this->handlerInstance[$handlerKey] = GeneralUtility::makeInstance($cfgArray['config']['class']);
				$this->handlerInstance[$handlerKey]->init($cfgArray, $this);
				if (is_object($this->handlerInstance[$handlerKey])) {
					$output = TRUE;
				}
				break;
			default:
				throw new \RuntimeException('ERROR: Invalid handler type: "' . $cfgArray['type'] . '"', 1310027995);
		}
		return $output;
	}

	/**
	 * Checks if database is connected.
	 *
	 * @return bool
	 */
	public function isConnected() {
		$result = FALSE;
		switch ((string)$this->handlerCfg[$this->lastHandlerKey]['type']) {
			case 'native':
				$result = isset($this->handlerCfg[$this->lastHandlerKey]['link']);
				break;
			case 'adodb':

			case 'userdefined':
				$result = is_object($this->handlerInstance[$this->lastHandlerKey]) && $this->handlerInstance[$this->lastHandlerKey]->isConnected();
				break;
		}
		return $result;
	}

	/**
	 * Checks whether the DBAL is currently inside an operation running on the "native" DB handler (i.e. MySQL)
	 *
	 * @return bool TRUE if running on "native" DB handler (i.e. MySQL)
	 */
	public function runningNative() {
		return (string)$this->handlerCfg[$this->lastHandlerKey]['type'] === 'native';
	}

	/**
	 * Checks whether the ADOdb handler is running with a driver that contains the argument
	 *
	 * @param string $driver Driver name, matched with strstr().
	 * @return bool True if running with the given driver
	 */
	public function runningADOdbDriver($driver) {
		return strpos($this->handlerCfg[$this->lastHandlerKey]['config']['driver'], $driver) !== FALSE;
	}

	/************************************
	 *
	 * Table/Field mapping
	 *
	 **************************************/
	/**
	 * Checks if mapping is needed for a table(list)
	 *
	 * @param string $tableList List of tables in query
	 * @param bool $fieldMappingOnly If TRUE, it will check only if FIELDs are configured and ignore the mapped table name if any.
	 * @param array $parsedTableList Parsed list of tables, should be passed as reference to be reused and prevent double parsing
	 * @return mixed Returns an array of table names (parsed version of input table) if mapping is needed, otherwise just FALSE.
	 */
	protected function map_needMapping($tableList, $fieldMappingOnly = FALSE, array &$parsedTableList = array()) {
		$key = $tableList . '|' . $fieldMappingOnly;
		if (!isset($this->cache_mappingFromTableList[$key])) {
			$this->cache_mappingFromTableList[$key] = FALSE;
			// Default:
			$tables = $this->SQLparser->parseFromTables($tableList);
			if (is_array($tables)) {
				$parsedTableList = $tables;
				foreach ($tables as $tableCfg) {
					if ($fieldMappingOnly) {
						if (is_array($this->mapping[$tableCfg['table']]['mapFieldNames'])) {
							$this->cache_mappingFromTableList[$key] = $tables;
						} elseif (is_array($tableCfg['JOIN'])) {
							foreach ($tableCfg['JOIN'] as $join) {
								if (is_array($this->mapping[$join['withTable']]['mapFieldNames'])) {
									$this->cache_mappingFromTableList[$key] = $tables;
									break;
								}
							}
						}
					} else {
						if (is_array($this->mapping[$tableCfg['table']])) {
							$this->cache_mappingFromTableList[$key] = $tables;
						} elseif (is_array($tableCfg['JOIN'])) {
							foreach ($tableCfg['JOIN'] as $join) {
								if (is_array($this->mapping[$join['withTable']])) {
									$this->cache_mappingFromTableList[$key] = $tables;
									break;
								}
							}
						}
					}
				}
			}
		}
		return $this->cache_mappingFromTableList[$key];
	}

	/**
	 * Takes an associated array with field => value pairs and remaps the field names if configured for this table in $this->mapping array.
	 * Be careful not to map a field name to another existing fields name (although you can use this to swap fieldnames of course...:-)
	 * Observe mapping problems with join-results (more than one table): Joined queries should always prefix the table name to avoid problems with this.
	 * Observe that alias fields are not mapped of course (should not be a problem though)
	 *
	 * @param array $input Input array, associative keys
	 * @param array $tables Array of tables from the query. Normally just one table; many tables in case of a join. NOTICE: for multiple tables (with joins) there MIGHT occur trouble with fields of the same name in the two tables: This function traverses the mapping information for BOTH tables and applies mapping without checking from which table the field really came!
	 * @param bool $rev If TRUE, reverse direction. Default direction is to map an array going INTO the database (thus mapping TYPO3 fieldnames to PHYSICAL field names!)
	 * @return array Output array, with mapped associative keys.
	 */
	protected function map_assocArray($input, $tables, $rev = FALSE) {
		// Traverse tables from query (hopefully only one table):
		foreach ($tables as $tableCfg) {
			$tableKey = $this->getMappingKey($tableCfg['table']);
			if (is_array($this->mapping[$tableKey]['mapFieldNames'])) {
				// Get the map (reversed if needed):
				if ($rev) {
					$theMap = array_flip($this->mapping[$tableKey]['mapFieldNames']);
				} else {
					$theMap = $this->mapping[$tableKey]['mapFieldNames'];
				}
				// Traverse selected record, map fieldnames:
				$output = array();
				foreach ($input as $fN => $value) {
					// Set the field name, change it if found in mapping array:
					if ($theMap[$fN]) {
						$newKey = $theMap[$fN];
					} else {
						$newKey = $fN;
					}
					// Set value to fieldname:
					$output[$newKey] = $value;
				}
				// When done, override the $input array with the result:
				$input = $output;
			}
		}
		// Return input array (which might have been altered in the mean time)
		return $input;
	}

	/**
	 * Remaps table/field names in a SELECT query's parts
	 *
	 * @param mixed $select_fields Either parsed list of tables (SQLparser->parseFromTables()) or list of fields to select from the table. This is what comes right after "SELECT ...". Required value.
	 * @param string $from_table Table(s) from which to select. This is what comes right after "FROM ...". Require value.
	 * @param string $where_clause Where clause. This is what comes right after "WHERE ...". Can be blank.
	 * @param string $groupBy Group by field(s)
	 * @param string $orderBy Order by field(s)
	 * @return array
	 * @see exec_SELECTquery()
	 */
	protected function map_remapSELECTQueryParts($select_fields, $from_table, $where_clause, $groupBy, $orderBy) {
		// Backup current mapping as it may be altered if aliases on mapped tables are found
		$backupMapping = $this->mapping;
		// Tables:
		$tables = is_array($from_table) ? $from_table : $this->SQLparser->parseFromTables($from_table);
		$defaultTable = $tables[0]['table'];
		// Prepare mapping for aliased tables. This will copy the definition of the original table name.
		// The alias is prefixed with a database-incompatible character to prevent naming clash with real table name
		// Further access to $this->mapping should be made through $this->getMappingKey() method
		foreach ($tables as $k => $v) {
			if ($v['as'] && is_array($this->mapping[$v['table']]['mapFieldNames'])) {
				$mappingKey = $this->getFreeMappingKey($v['as']);
				$this->mapping[$mappingKey]['mapFieldNames'] =& $this->mapping[$v['table']]['mapFieldNames'];
			}
			if (is_array($v['JOIN'])) {
				foreach ($v['JOIN'] as $joinCnt => $join) {
					if ($join['as'] && is_array($this->mapping[$join['withTable']]['mapFieldNames'])) {
						$mappingKey = $this->getFreeMappingKey($join['as']);
						$this->mapping[$mappingKey]['mapFieldNames'] =& $this->mapping[$join['withTable']]['mapFieldNames'];
					}
				}
			}
		}
		foreach ($tables as $k => $v) {
			$tableKey = $this->getMappingKey($v['table']);
			if ($this->mapping[$tableKey]['mapTableName']) {
				$tables[$k]['table'] = $this->mapping[$tableKey]['mapTableName'];
			}
			// Mapping JOINS
			if (is_array($v['JOIN'])) {
				foreach ($v['JOIN'] as $joinCnt => $join) {
					// Mapping withTable of the JOIN
					$withTableKey = $this->getMappingKey($join['withTable']);
					if ($this->mapping[$withTableKey]['mapTableName']) {
						$tables[$k]['JOIN'][$joinCnt]['withTable'] = $this->mapping[$withTableKey]['mapTableName'];
					}
					$onPartsArray = array();
					// Mapping ON parts of the JOIN
					if (is_array($tables[$k]['JOIN'][$joinCnt]['ON'])) {
						foreach ($tables[$k]['JOIN'][$joinCnt]['ON'] as &$condition) {
							// Left side of the comparator
							$leftTableKey = $this->getMappingKey($condition['left']['table']);
							if (isset($this->mapping[$leftTableKey]['mapFieldNames'][$condition['left']['field']])) {
								$condition['left']['field'] = $this->mapping[$leftTableKey]['mapFieldNames'][$condition['left']['field']];
							}
							if (isset($this->mapping[$leftTableKey]['mapTableName'])) {
								$condition['left']['table'] = $this->mapping[$leftTableKey]['mapTableName'];
							}
							// Right side of the comparator
							$rightTableKey = $this->getMappingKey($condition['right']['table']);
							if (isset($this->mapping[$rightTableKey]['mapFieldNames'][$condition['right']['field']])) {
								$condition['right']['field'] = $this->mapping[$rightTableKey]['mapFieldNames'][$condition['right']['field']];
							}
							if (isset($this->mapping[$rightTableKey]['mapTableName'])) {
								$condition['right']['table'] = $this->mapping[$rightTableKey]['mapTableName'];
							}
						}
					}
				}
			}
		}
		$fromParts = $tables;
		// Where clause:
		$parameterReferences = array();
		$whereParts = $this->SQLparser->parseWhereClause($where_clause, '', $parameterReferences);
		$this->map_sqlParts($whereParts, $defaultTable);
		// Select fields:
		$selectParts = $this->SQLparser->parseFieldList($select_fields);
		$this->map_sqlParts($selectParts, $defaultTable);
		// Group By fields
		$groupByParts = $this->SQLparser->parseFieldList($groupBy);
		$this->map_sqlParts($groupByParts, $defaultTable);
		// Order By fields
		$orderByParts = $this->SQLparser->parseFieldList($orderBy);
		$this->map_sqlParts($orderByParts, $defaultTable);
		// Restore the original mapping
		$this->mapping = $backupMapping;
		return array($selectParts, $fromParts, $whereParts, $groupByParts, $orderByParts, $parameterReferences);
	}

	/**
	 * Returns the key to be used when retrieving information from $this->mapping. This ensures
	 * that mapping from aliased tables is properly retrieved.
	 *
	 * @param string $tableName
	 * @return string
	 */
	protected function getMappingKey($tableName) {
		// Search deepest alias mapping
		while (isset($this->mapping['*' . $tableName])) {
			$tableName = '*' . $tableName;
		}
		return $tableName;
	}

	/**
	 * Returns a free key to be used to store mapping information in $this->mapping.
	 *
	 * @param string $tableName
	 * @return string
	 */
	protected function getFreeMappingKey($tableName) {
		while (isset($this->mapping[$tableName])) {
			$tableName = '*' . $tableName;
		}
		return $tableName;
	}

	/**
	 * Generic mapping of table/field names arrays (as parsed by \TYPO3\CMS\Core\Database\SqlParser)
	 *
	 * @param array $sqlPartArray Array with parsed SQL parts; Takes both fields, tables, where-parts, group and order-by. Passed by reference.
	 * @param string $defaultTable Default table name to assume if no table is found in $sqlPartArray
	 * @return void
	 * @see map_remapSELECTQueryParts()
	 */
	protected function map_sqlParts(&$sqlPartArray, $defaultTable) {
		$defaultTableKey = $this->getMappingKey($defaultTable);
		// Traverse sql Part array:
		if (is_array($sqlPartArray)) {
			foreach ($sqlPartArray as $k => $v) {
				if (isset($sqlPartArray[$k]['type'])) {
					switch ($sqlPartArray[$k]['type']) {
						case 'flow-control':
							$temp = array($sqlPartArray[$k]['flow-control']);
							$this->map_sqlParts($temp, $defaultTable);
							// Call recursively!
							$sqlPartArray[$k]['flow-control'] = $temp[0];
							break;
						case 'CASE':
							if (isset($sqlPartArray[$k]['case_field'])) {
								$fieldArray = explode('.', $sqlPartArray[$k]['case_field']);
								if (count($fieldArray) == 1 && is_array($this->mapping[$defaultTableKey]['mapFieldNames']) && isset($this->mapping[$defaultTableKey]['mapFieldNames'][$fieldArray[0]])) {
									$sqlPartArray[$k]['case_field'] = $this->mapping[$defaultTableKey]['mapFieldNames'][$fieldArray[0]];
								} elseif (count($fieldArray) == 2) {
									// Map the external table
									$table = $fieldArray[0];
									$tableKey = $this->getMappingKey($table);
									if (isset($this->mapping[$tableKey]['mapTableName'])) {
										$table = $this->mapping[$tableKey]['mapTableName'];
									}
									// Map the field itself
									$field = $fieldArray[1];
									if (is_array($this->mapping[$tableKey]['mapFieldNames']) && isset($this->mapping[$tableKey]['mapFieldNames'][$fieldArray[1]])) {
										$field = $this->mapping[$tableKey]['mapFieldNames'][$fieldArray[1]];
									}
									$sqlPartArray[$k]['case_field'] = $table . '.' . $field;
								}
							}
							foreach ($sqlPartArray[$k]['when'] as $key => $when) {
								$this->map_sqlParts($sqlPartArray[$k]['when'][$key]['when_value'], $defaultTable);
							}
							break;
					}
				}
				// Look for sublevel (WHERE parts only)
				if (is_array($sqlPartArray[$k]['sub'])) {
					$this->map_sqlParts($sqlPartArray[$k]['sub'], $defaultTable);
				} elseif (isset($sqlPartArray[$k]['func'])) {
					switch ($sqlPartArray[$k]['func']['type']) {
						case 'EXISTS':
							$this->map_subquery($sqlPartArray[$k]['func']['subquery']);
							break;
						case 'FIND_IN_SET':

						case 'IFNULL':

						case 'LOCATE':
							// For the field, look for table mapping (generic):
							$t = $sqlPartArray[$k]['func']['table'] ? $sqlPartArray[$k]['func']['table'] : $defaultTable;
							$t = $this->getMappingKey($t);
							if (is_array($this->mapping[$t]['mapFieldNames']) && $this->mapping[$t]['mapFieldNames'][$sqlPartArray[$k]['func']['field']]) {
								$sqlPartArray[$k]['func']['field'] = $this->mapping[$t]['mapFieldNames'][$sqlPartArray[$k]['func']['field']];
							}
							if ($this->mapping[$t]['mapTableName']) {
								$sqlPartArray[$k]['func']['table'] = $this->mapping[$t]['mapTableName'];
							}
							break;
					}
				} else {
					// For the field, look for table mapping (generic):
					$t = $sqlPartArray[$k]['table'] ? $sqlPartArray[$k]['table'] : $defaultTable;
					$t = $this->getMappingKey($t);
					// Mapping field name, if set:
					if (is_array($this->mapping[$t]['mapFieldNames']) && isset($this->mapping[$t]['mapFieldNames'][$sqlPartArray[$k]['field']])) {
						$sqlPartArray[$k]['field'] = $this->mapping[$t]['mapFieldNames'][$sqlPartArray[$k]['field']];
					}
					// Mapping field name in SQL-functions like MIN(), MAX() or SUM()
					if ($this->mapping[$t]['mapFieldNames']) {
						$fieldArray = explode('.', $sqlPartArray[$k]['func_content']);
						if (count($fieldArray) == 1 && is_array($this->mapping[$t]['mapFieldNames']) && isset($this->mapping[$t]['mapFieldNames'][$fieldArray[0]])) {
							$sqlPartArray[$k]['func_content.'][0]['func_content'] = $this->mapping[$t]['mapFieldNames'][$fieldArray[0]];
							$sqlPartArray[$k]['func_content'] = $this->mapping[$t]['mapFieldNames'][$fieldArray[0]];
						} elseif (count($fieldArray) == 2) {
							// Map the external table
							$table = $fieldArray[0];
							$tableKey = $this->getMappingKey($table);
							if (isset($this->mapping[$tableKey]['mapTableName'])) {
								$table = $this->mapping[$tableKey]['mapTableName'];
							}
							// Map the field itself
							$field = $fieldArray[1];
							if (is_array($this->mapping[$tableKey]['mapFieldNames']) && isset($this->mapping[$tableKey]['mapFieldNames'][$fieldArray[1]])) {
								$field = $this->mapping[$tableKey]['mapFieldNames'][$fieldArray[1]];
							}
							$sqlPartArray[$k]['func_content.'][0]['func_content'] = $table . '.' . $field;
							$sqlPartArray[$k]['func_content'] = $table . '.' . $field;
						}
						// Mapping flow-control statements
						if (isset($sqlPartArray[$k]['flow-control'])) {
							if (isset($sqlPartArray[$k]['flow-control']['type'])) {
								$temp = array($sqlPartArray[$k]['flow-control']);
								$this->map_sqlParts($temp, $t);
								// Call recursively!
								$sqlPartArray[$k]['flow-control'] = $temp[0];
							}
						}
					}
					// Do we have a function (e.g., CONCAT)
					if (isset($v['value']['operator'])) {
						foreach ($sqlPartArray[$k]['value']['args'] as $argK => $fieldDef) {
							$tableKey = $this->getMappingKey($fieldDef['table']);
							if (isset($this->mapping[$tableKey]['mapTableName'])) {
								$sqlPartArray[$k]['value']['args'][$argK]['table'] = $this->mapping[$tableKey]['mapTableName'];
							}
							if (is_array($this->mapping[$tableKey]['mapFieldNames']) && isset($this->mapping[$tableKey]['mapFieldNames'][$fieldDef['field']])) {
								$sqlPartArray[$k]['value']['args'][$argK]['field'] = $this->mapping[$tableKey]['mapFieldNames'][$fieldDef['field']];
							}
						}
					}
					// Do we have a subquery (WHERE parts only)?
					if (isset($sqlPartArray[$k]['subquery'])) {
						$this->map_subquery($sqlPartArray[$k]['subquery']);
					}
					// do we have a field name in the value?
					// this is a very simplistic check, beware
					if (!is_numeric($sqlPartArray[$k]['value'][0]) && !isset($sqlPartArray[$k]['value'][1])) {
						$fieldArray = explode('.', $sqlPartArray[$k]['value'][0]);
						if (count($fieldArray) == 1 && is_array($this->mapping[$t]['mapFieldNames']) && isset($this->mapping[$t]['mapFieldNames'][$fieldArray[0]])) {
							$sqlPartArray[$k]['value'][0] = $this->mapping[$t]['mapFieldNames'][$fieldArray[0]];
						} elseif (count($fieldArray) == 2) {
							// Map the external table
							$table = $fieldArray[0];
							$tableKey = $this->getMappingKey($table);
							if (isset($this->mapping[$tableKey]['mapTableName'])) {
								$table = $this->mapping[$tableKey]['mapTableName'];
							}
							// Map the field itself
							$field = $fieldArray[1];
							if (is_array($this->mapping[$tableKey]['mapFieldNames']) && isset($this->mapping[$tableKey]['mapFieldNames'][$fieldArray[1]])) {
								$field = $this->mapping[$tableKey]['mapFieldNames'][$fieldArray[1]];
							}
							$sqlPartArray[$k]['value'][0] = $table . '.' . $field;
						}
					}
					// Map table?
					$tableKey = $this->getMappingKey($sqlPartArray[$k]['table']);
					if ($sqlPartArray[$k]['table'] && $this->mapping[$tableKey]['mapTableName']) {
						$sqlPartArray[$k]['table'] = $this->mapping[$tableKey]['mapTableName'];
					}
				}
			}
		}
	}

	/**
	 * Maps table and field names in a subquery.
	 *
	 * @param array $parsedQuery
	 * @return void
	 */
	protected function map_subquery(&$parsedQuery) {
		// Backup current mapping as it may be altered
		$backupMapping = $this->mapping;
		foreach ($parsedQuery['FROM'] as $k => $v) {
			$mappingKey = $v['table'];
			if ($v['as'] && is_array($this->mapping[$v['table']]['mapFieldNames'])) {
				$mappingKey = $this->getFreeMappingKey($v['as']);
			} else {
				// Should ensure that no alias is defined in the external query
				// which would correspond to a real table name in the subquery
				if ($this->getMappingKey($v['table']) !== $v['table']) {
					$mappingKey = $this->getFreeMappingKey($v['table']);
					// This is the only case when 'mapTableName' should be copied
					$this->mapping[$mappingKey]['mapTableName'] =& $this->mapping[$v['table']]['mapTableName'];
				}
			}
			if ($mappingKey !== $v['table']) {
				$this->mapping[$mappingKey]['mapFieldNames'] =& $this->mapping[$v['table']]['mapFieldNames'];
			}
		}
		// Perform subquery's remapping
		$defaultTable = $parsedQuery['FROM'][0]['table'];
		$this->map_sqlParts($parsedQuery['SELECT'], $defaultTable);
		$this->map_sqlParts($parsedQuery['FROM'], $defaultTable);
		$this->map_sqlParts($parsedQuery['WHERE'], $defaultTable);
		// Restore the mapping
		$this->mapping = $backupMapping;
	}

	/**
	 * Will do table/field mapping on a general \TYPO3\CMS\Core\Database\SqlParser-compliant SQL query
	 * (May still not support all query types...)
	 *
	 * @param array $parsedQuery Parsed QUERY as from \TYPO3\CMS\Core\Database\SqlParser::parseSQL(). NOTICE: Passed by reference!
	 * @throws \InvalidArgumentException
	 * @return void
	 * @see \TYPO3\CMS\Core\Database\SqlParser::parseSQL()
	 */
	protected function map_genericQueryParsed(&$parsedQuery) {
		// Getting table - same for all:
		$table = $parsedQuery['TABLE'];
		if (!$table) {
			throw new \InvalidArgumentException('ERROR, mapping: No table found in parsed Query array...', 1310028048);
		}
		// Do field mapping if needed:
		if ($tableArray = $this->map_needMapping($table)) {
			// Table name:
			if ($this->mapping[$table]['mapTableName']) {
				$parsedQuery['TABLE'] = $this->mapping[$table]['mapTableName'];
			}
			// Based on type, do additional changes:
			switch ($parsedQuery['type']) {
				case 'ALTERTABLE':
					// Changing field name:
					$newFieldName = $this->mapping[$table]['mapFieldNames'][$parsedQuery['FIELD']];
					if ($newFieldName) {
						if ($parsedQuery['FIELD'] == $parsedQuery['newField']) {
							$parsedQuery['FIELD'] = ($parsedQuery['newField'] = $newFieldName);
						} else {
							$parsedQuery['FIELD'] = $newFieldName;
						}
					}
					// Changing key field names:
					if (is_array($parsedQuery['fields'])) {
						$this->map_fieldNamesInArray($table, $parsedQuery['fields']);
					}
					break;
				case 'CREATETABLE':
					// Remapping fields:
					if (is_array($parsedQuery['FIELDS'])) {
						$newFieldsArray = array();
						foreach ($parsedQuery['FIELDS'] as $fN => $fInfo) {
							if ($this->mapping[$table]['mapFieldNames'][$fN]) {
								$fN = $this->mapping[$table]['mapFieldNames'][$fN];
							}
							$newFieldsArray[$fN] = $fInfo;
						}
						$parsedQuery['FIELDS'] = $newFieldsArray;
					}
					// Remapping keys:
					if (is_array($parsedQuery['KEYS'])) {
						foreach ($parsedQuery['KEYS'] as $kN => $kInfo) {
							$this->map_fieldNamesInArray($table, $parsedQuery['KEYS'][$kN]);
						}
					}
					break;
			}
		}
	}

	/**
	 * Re-mapping field names in array
	 *
	 * @param string $table (TYPO3) Table name for fields.
	 * @param array $fieldArray Array of fieldnames to remap. Notice: Passed by reference!
	 * @return void
	 */
	protected function map_fieldNamesInArray($table, &$fieldArray) {
		if (is_array($this->mapping[$table]['mapFieldNames'])) {
			foreach ($fieldArray as $k => $v) {
				if ($this->mapping[$table]['mapFieldNames'][$v]) {
					$fieldArray[$k] = $this->mapping[$table]['mapFieldNames'][$v];
				}
			}
		}
	}

	/**************************************
	 *
	 * Debugging
	 *
	 **************************************/
	/**
	 * Debug handler for query execution
	 *
	 * @param string $function Function name from which this function is called.
	 * @param string $execTime Execution time in ms of the query
	 * @param array $inData In-data of various kinds.
	 * @return void
	 * @access private
	 */
	public function debugHandler($function, $execTime, $inData) {
		// we don't want to log our own log/debug SQL
		$script = \TYPO3\CMS\Core\Utility\PathUtility::stripPathSitePrefix(PATH_thisScript);
		if (substr($script, -strlen('dbal/mod1/index.php')) != 'dbal/mod1/index.php' && !strstr($inData['args'][0], 'tx_dbal_debuglog')) {
			$data = array();
			$errorFlag = 0;
			$joinTable = '';
			if ($this->sql_error()) {
				$data['sqlError'] = $this->sql_error();
				$errorFlag |= 1;
			}
			// if lastQuery is empty (for whatever reason) at least log inData.args
			if (empty($this->lastQuery)) {
				$query = implode(' ', $inData['args']);
			} else {
				$query = $this->lastQuery;
			}
			if ($this->conf['debugOptions']['numberRows']) {
				switch ($function) {
					case 'exec_INSERTquery':

					case 'exec_UPDATEquery':

					case 'exec_DELETEquery':
						$data['numberRows'] = $this->sql_affected_rows();
						break;
					case 'exec_SELECTquery':
						$data['numberRows'] = $inData['numberRows'];
						break;
				}
			}
			if ($this->conf['debugOptions']['backtrace']) {
				$backtrace = debug_backtrace();
				unset($backtrace[0]);
				// skip this very method :)
				$data['backtrace'] = array_slice($backtrace, 0, $this->conf['debugOptions']['backtrace']);
			}
			switch ($function) {
				case 'exec_INSERTquery':

				case 'exec_UPDATEquery':

				case 'exec_DELETEquery':
					$this->debug_log($query, $execTime, $data, $joinTable, $errorFlag, $script);
					break;
				case 'exec_SELECTquery':
					// Get explain data:
					if ($this->conf['debugOptions']['EXPLAIN'] && GeneralUtility::inList('adodb,native', $inData['handlerType'])) {
						$data['EXPLAIN'] = $this->debug_explain($this->lastQuery);
					}
					// Check parsing of Query:
					if ($this->conf['debugOptions']['parseQuery']) {
						$parseResults = array();
						$parseResults['SELECT'] = $this->SQLparser->debug_parseSQLpart('SELECT', $inData['args'][1]);
						$parseResults['FROM'] = $this->SQLparser->debug_parseSQLpart('FROM', $inData['args'][0]);
						$parseResults['WHERE'] = $this->SQLparser->debug_parseSQLpart('WHERE', $inData['args'][2]);
						$parseResults['GROUPBY'] = $this->SQLparser->debug_parseSQLpart('SELECT', $inData['args'][3]);
						// Using select field list syntax
						$parseResults['ORDERBY'] = $this->SQLparser->debug_parseSQLpart('SELECT', $inData['args'][4]);
						// Using select field list syntax
						foreach ($parseResults as $k => $v) {
							if ($v === '') {
								unset($parseResults[$k]);
							}
						}
						if (count($parseResults)) {
							$data['parseError'] = $parseResults;
							$errorFlag |= 2;
						}
					}
					// Checking joinTables:
					if ($this->conf['debugOptions']['joinTables']) {
						if (count(explode(',', $inData['ORIG_from_table'])) > 1) {
							$joinTable = $inData['args'][0];
						}
					}
					// Logging it:
					$this->debug_log($query, $execTime, $data, $joinTable, $errorFlag, $script);
					if (!empty($inData['args'][2])) {
						$this->debug_WHERE($inData['args'][0], $inData['args'][2], $script);
					}
					break;
			}
		}
	}

	/**
	 * Logs the where clause for debugging purposes.
	 *
	 * @param string $table	Table name(s) the query was targeted at
	 * @param string $where	The WHERE clause to be logged
	 * @param string $script The script calling the logging
	 * @return void
	 */
	public function debug_WHERE($table, $where, $script = '') {
		$insertArray = array(
			'tstamp' => $GLOBALS['EXEC_TIME'],
			'beuser_id' => (int)$GLOBALS['BE_USER']->user['uid'],
			'script' => $script,
			'tablename' => $table,
			'whereclause' => $where
		);
		$this->exec_INSERTquery('tx_dbal_debuglog_where', $insertArray);
	}

	/**
	 * Inserts row in the log table
	 *
	 * @param string $query The current query
	 * @param int $ms Execution time of query in milliseconds
	 * @param array $data Data to be stored serialized.
	 * @param string $join Join string if there IS a join.
	 * @param int $errorFlag Error status.
	 * @param string $script The script calling the logging
	 * @return void
	 */
	public function debug_log($query, $ms, $data, $join, $errorFlag, $script = '') {
		if (is_array($query)) {
			$queryToLog = $query[0] . ' --  ';
			if (count($query[1])) {
				$queryToLog .= count($query[1]) . ' BLOB FIELDS: ' . implode(', ', array_keys($query[1]));
			}
			if (count($query[2])) {
				$queryToLog .= count($query[2]) . ' CLOB FIELDS: ' . implode(', ', array_keys($query[2]));
			}
		} else {
			$queryToLog = $query;
		}
		$insertArray = array(
			'tstamp' => $GLOBALS['EXEC_TIME'],
			'beuser_id' => (int)$GLOBALS['BE_USER']->user['uid'],
			'script' => $script,
			'exec_time' => $ms,
			'table_join' => $join,
			'serdata' => serialize($data),
			'query' => $queryToLog,
			'errorFlag' => $errorFlag
		);
		$this->exec_INSERTquery('tx_dbal_debuglog', $insertArray);
	}

	/**
	 * Perform EXPLAIN query on DEFAULT handler!
	 *
	 * @param string $query SELECT Query
	 * @return array The Explain result rows in an array
	 */
	public function debug_explain($query) {
		$output = array();
		$hType = (string)$this->handlerCfg[$this->lastHandlerKey]['type'];
		switch ($hType) {
			case 'native':
				$res = $this->sql_query('EXPLAIN ' . $query);
				while ($row = $this->sql_fetch_assoc($res)) {
					$output[] = $row;
				}
				break;
			case 'adodb':
				switch ($this->handlerCfg['_DEFAULT']['config']['driver']) {
					case 'oci8':
						$this->sql_query('EXPLAIN PLAN ' . $query);
						$output[] = 'EXPLAIN PLAN data logged to default PLAN_TABLE';
						break;
					default:
						$res = $this->sql_query('EXPLAIN ' . $query);
						while ($row = $this->sql_fetch_assoc($res)) {
							$output[] = $row;
						}
				}
				break;
		}
		return $output;
	}

}
