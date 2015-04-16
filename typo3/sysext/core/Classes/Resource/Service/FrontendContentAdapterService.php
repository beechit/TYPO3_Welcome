<?php
namespace TYPO3\CMS\Core\Resource\Service;

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
 * Tslib content adapter to modify $row array ($cObj->data[]) for backwards compatibility
 *
 * @author Ingmar Schlecht <ingmar@typo3.org>
 * @license http://www.gnu.org/copyleft/gpl.html
 */
class FrontendContentAdapterService {

	/**
	 * Array containing all keys that are allowed in the migrateFields array.
	 *
	 * @var array
	 */
	static protected $availableMigrationFields = array(
		'paths',
		'titleTexts',
		'captions',
		'links',
		'alternativeTexts'
	);

	/**
	 * The name of the table
	 *
	 * @var string
	 */
	static protected $migrateFields = array(
		'tt_content' => array(
			'image' => array(
				'paths' => 'image',
				'titleTexts' => 'titleText',
				'captions' => 'imagecaption',
				'links' => 'image_link',
				'alternativeTexts' => 'altText',
				'__typeMatch' => array(
					'typeField' => 'CType',
					'types' => array('image', 'textpic'),
				)
			),
			'media' => array(
				'paths' => 'media',
				'captions' => 'imagecaption',
				'__typeMatch' => array(
					'typeField' => 'CType',
					'types' => array('uploads'),
				)
			)
		),
		'pages' => array(
			'media' => array(
				'paths' => 'media'
			)
		)
	);

	/**
	 * Modifies the DB row in the CONTENT cObj of \TYPO3\CMS\\Frontend\ContentObject for supplying
	 * backwards compatibility for some file fields which have switched to using
	 * the new File API instead of the old uploads/ folder for storing files.
	 *
	 * This method is called by the render() method of \TYPO3\CMS\Frontend\ContentObject\ContentContentObject
	 *
	 * @param array $row typically an array, but can also be null (in extensions or e.g. FLUID viewhelpers)
	 * @param string $table the database table where the record is from
	 * @throws \RuntimeException
	 * @return void
	 */
	static public function modifyDBRow(&$row, $table) {
		if (isset($row['_MIGRATED']) && $row['_MIGRATED'] === TRUE) {
			return;
		}
		if (array_key_exists($table, static::$migrateFields)) {
			foreach (static::$migrateFields[$table] as $migrateFieldName => $oldFieldNames) {
				if ($row !== NULL && isset($row[$migrateFieldName]) && self::fieldIsInType($migrateFieldName, $table, $row)) {
					$files = self::getPageRepository()->getFileReferences($table, $migrateFieldName, $row);
					$fileFieldContents = array(
						'paths' => array(),
						'titleTexts' => array(),
						'captions' => array(),
						'links' => array(),
						'alternativeTexts' => array(),
						$migrateFieldName . '_fileUids' => array(),
						$migrateFieldName . '_fileReferenceUids' => array(),
					);
					$oldFieldNames[$migrateFieldName . '_fileUids'] = $migrateFieldName . '_fileUids';
					$oldFieldNames[$migrateFieldName . '_fileReferenceUids'] = $migrateFieldName . '_fileReferenceUids';

					foreach ($files as $file) {
						/** @var $file \TYPO3\CMS\Core\Resource\FileReference */
						$fileProperties = $file->getProperties();
						$fileFieldContents['paths'][] = '../../' . $file->getPublicUrl();
						$fileFieldContents['titleTexts'][] = $fileProperties['title'];
						$fileFieldContents['captions'][] = $fileProperties['description'];
						$fileFieldContents['links'][] = $fileProperties['link'];
						$fileFieldContents['alternativeTexts'][] = $fileProperties['alternative'];
						$fileFieldContents[$migrateFieldName . '_fileUids'][] = $file->getOriginalFile()->getUid();
						$fileFieldContents[$migrateFieldName . '_fileReferenceUids'][] = $file->getUid();
					}
					foreach ($oldFieldNames as $oldFieldType => $oldFieldName) {
						if ($oldFieldType === '__typeMatch') {
							continue;
						}
						if ($oldFieldType === 'paths' || substr($oldFieldType, -9) == '_fileUids' || substr($oldFieldType, -18) == '_fileReferenceUids') {
							// For paths and uids, make comma separated list
							$fieldContents = implode(',', $fileFieldContents[$oldFieldType]);
						} else {
							// For all other fields, separate by newline
							$fieldContents = implode(LF, $fileFieldContents[$oldFieldType]);
						}
						$row[$oldFieldName] = $fieldContents;
					}
				}
			}
		}
		$row['_MIGRATED'] = TRUE;
	}

	/**
	 * Registers an additional record type for an existing migration configuration.
	 *
	 * For use in ext_localconf.php files.
	 *
	 * @param string $table Name of the table in the migration configuration
	 * @param string $field Name of the field in the migration configuration
	 * @param string $additionalType The additional type for which the migration should be applied
	 * @throws \RuntimeException
	 * @return void
	 */
	static public function registerAdditionalTypeForMigration($table, $field, $additionalType) {

		if (!isset(static::$migrateFields[$table][$field]['__typeMatch'])) {
			throw new \RuntimeException(
				'Additional types can only be added when there is already an existing type match configuration for the given table and field. '
				. 'Table: "' . $table . '", Field: "' . $field . '", Additional Type: "' . $additionalType . '"',
				1377600978
			);
		}

		self::$migrateFields[$table][$field]['__typeMatch']['types'][] = $additionalType;
	}

	/**
	 * Registers an additional field for migration.
	 *
	 * For use in ext_localconf.php files
	 *
	 * @param string $table Name of the table in the migration configuration
	 * @param string $field Name of the field in the migration configuration
	 * @param string $migrationField The file property that should be migrated, see $availableMigrateFields for available settings
	 * @param string $oldFieldName The name of the field in which the file property should be available
	 * @param string $typeField Optional field that switches the record type, will only have an effect if $types array is provided
	 * @param array $types The record types for which the migration should be active
	 * @throws \InvalidArgumentException
	 */
	static public function registerFieldForMigration($table, $field, $migrationField, $oldFieldName, $typeField = NULL, array $types = array()) {

		if (array_search($migrationField, static::$availableMigrationFields) === FALSE) {
			throw new \InvalidArgumentException('The value for $migrationField "' . $migrationField . '" is invalid.' .
				' Valid values can be found in the $availableMigrationFields array.', 1377600978);
		}

		self::$migrateFields[$table][$field][$migrationField] = $oldFieldName;

		if (isset($typeField) && (count($types) > 0)) {
			self::$migrateFields[$table][$field]['__typeMatch']['types'] = $types;
			self::$migrateFields[$table][$field]['__typeMatch']['typeField'] = (string)$typeField;
		}
	}

	/**
	 * Check if fieldis in type
	 *
	 * @param string $fieldName
	 * @param string $table
	 * @param array $row
	 * @return bool
	 */
	static protected function fieldIsInType($fieldName, $table, array $row) {
		$fieldConfiguration = static::$migrateFields[$table][$fieldName];
		if (empty($fieldConfiguration['__typeMatch'])) {
			return TRUE;
		} else {
			return in_array($row[$fieldConfiguration['__typeMatch']['typeField']], $fieldConfiguration['__typeMatch']['types']);
		}
	}

	/**
	 * @return \TYPO3\CMS\Frontend\Page\PageRepository
	 */
	static protected function getPageRepository() {
		return $GLOBALS['TSFE']->sys_page;
	}

}
