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
 * Utility for dealing with database related operations
 *
 * @author Susanne Moog <susanne.moog@typo3.org>
 */
class DatabaseUtility implements \TYPO3\CMS\Core\SingletonInterface {

	/**
	 * @var string
	 */
	const MULTI_LINEBREAKS = '


';
	/**
	 * Dump content for static tables
	 *
	 * @param array $dbFields
	 * @return string
	 */
	public function dumpStaticTables($dbFields) {
		$out = '';
		// Traverse the table list and dump each:
		foreach ($dbFields as $table => $fields) {
			if (is_array($dbFields[$table]['fields'])) {
				$header = $this->dumpHeader();
				$tableHeader = $this->dumpTableHeader($table, $dbFields[$table], TRUE);
				$insertStatements = $this->dumpTableContent($table, $dbFields[$table]['fields']);
				$out .= $header . self::MULTI_LINEBREAKS . $tableHeader . self::MULTI_LINEBREAKS . $insertStatements . self::MULTI_LINEBREAKS;
			}
		}
		return $out;
	}

	/**
	 * Header comments of the SQL dump file
	 *
	 * @return string Table header
	 */
	protected function dumpHeader() {
		return trim('
# TYPO3 Extension Manager dump 1.1
#
# Host: ' . TYPO3_db_host . '    Database: ' . TYPO3_db . '
#--------------------------------------------------------
');
	}

	/**
	 * Dump CREATE TABLE definition
	 *
	 * @param string $table
	 * @param array $fieldKeyInfo
	 * @param bool $dropTableIfExists
	 * @return string
	 */
	protected function dumpTableHeader($table, array $fieldKeyInfo, $dropTableIfExists = FALSE) {
		$lines = array();
		$dump = '';
		// Create field definitions
		if (is_array($fieldKeyInfo['fields'])) {
			foreach ($fieldKeyInfo['fields'] as $fieldN => $data) {
				$lines[] = '  ' . $fieldN . ' ' . $data;
			}
		}
		// Create index key definitions
		if (is_array($fieldKeyInfo['keys'])) {
			foreach ($fieldKeyInfo['keys'] as $fieldN => $data) {
				$lines[] = '  ' . $data;
			}
		}
		// Compile final output:
		if (count($lines)) {
			$dump = trim('
#
# Table structure for table "' . $table . '"
#
' . ($dropTableIfExists ? 'DROP TABLE IF EXISTS ' . $table . ';
' : '') . 'CREATE TABLE ' . $table . ' (
' . implode((',' . LF), $lines) . '
);');
		}
		return $dump;
	}

	/**
	 * Dump table content
	 * Is DBAL compliant, but the dump format is written as MySQL standard.
	 * If the INSERT statements should be imported in a DBMS using other
	 * quoting than MySQL they must first be translated.
	 *
	 * @param string $table Table name
	 * @param array $fieldStructure Field structure
	 * @return string SQL Content of dump (INSERT statements)
	 */
	protected function dumpTableContent($table, array $fieldStructure) {
		// Substitution of certain characters (borrowed from phpMySQL):
		$search = array('\\', '\'', "\0", "\n", "\r", "\x1A");
		$replace = array('\\\\', '\\\'', '\\0', '\\n', '\\r', '\\Z');
		$lines = array();
		// Select all rows from the table:
		$result = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', $table, '');
		// Traverse the selected rows and dump each row as a line in the file:
		while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($result)) {
			$values = array();
			foreach ($fieldStructure as $field => $structure) {
				$values[] = isset($row[$field]) ? '\'' . str_replace($search, $replace, $row[$field]) . '\'' : 'NULL';
			}
			$lines[] = 'INSERT INTO ' . $table . ' VALUES (' . implode(', ', $values) . ');';
		}
		// Free DB result:
		$GLOBALS['TYPO3_DB']->sql_free_result($result);
		// Implode lines and return:
		return implode(LF, $lines);
	}

}
