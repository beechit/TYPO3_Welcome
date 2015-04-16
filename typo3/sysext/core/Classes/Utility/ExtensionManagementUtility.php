<?php
namespace TYPO3\CMS\Core\Utility;

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

use TYPO3\CMS\Core\Category\CategoryRegistry;
use TYPO3\CMS\Core\Package\PackageManager;

/**
 * Extension Management functions
 *
 * This class is never instantiated, rather the methods inside is called as functions like
 * \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('my_extension');
 *
 * @author Kasper Skårhøj <kasperYYYY@typo3.com>
 */
class ExtensionManagementUtility {

	/**
	 * @var array
	 */
	static protected $extensionKeyMap;

	/**
	 * TRUE, if ext_tables file was read from cache for this script run.
	 * The frontend tends to do that multiple times, but the caching framework does
	 * not allow this (via a require_once call). This variable is used to track
	 * the access to the cache file to read the single ext_tables.php if it was
	 * already read from cache
	 *
	 * @todo See if we can get rid of the 'load multiple times' scenario in fe
	 * @var bool
	 */
	static protected $extTablesWasReadFromCacheOnce = FALSE;

	/**
	 * @var \TYPO3\CMS\Core\Package\PackageManager
	 */
	static protected $packageManager;

	/**
	 * Sets the package manager for all that backwards compatibility stuff,
	 * so it doesn't have to be fetched through the bootstap.
	 *
	 * @param \TYPO3\CMS\Core\Package\PackageManager $packageManager
	 * @internal
	 */
	static public function setPackageManager(PackageManager $packageManager) {
		static::$packageManager = $packageManager;
	}

	/**
	 * @var \TYPO3\CMS\Core\Cache\CacheManager
	 */
	static protected $cacheManager;

	/**
	 * Getter for the cache manager
	 *
	 * @return \TYPO3\CMS\Core\Cache\CacheManager
	 */
	static protected function getCacheManager() {
		if (static::$cacheManager === NULL) {
			static::$cacheManager = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Cache\CacheManager::class);
		}
		return static::$cacheManager;
	}

	/**
	 * @var \TYPO3\CMS\Extbase\SignalSlot\Dispatcher
	 */
	static protected $signalSlotDispatcher;

	/**
	 * Getter for the signal slot dispatcher
	 *
	 * @return \TYPO3\CMS\Extbase\SignalSlot\Dispatcher
	 */
	static protected function getSignalSlotDispatcher() {
		if (static::$signalSlotDispatcher === NULL) {
			static::$signalSlotDispatcher = GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\SignalSlot\Dispatcher::class);
		}
		return static::$signalSlotDispatcher;
	}

	/**************************************
	 *
	 * PATHS and other evaluation
	 *
	 ***************************************/
	/**
	 * Returns TRUE if the extension with extension key $key is loaded.
	 *
	 * @param string $key Extension key to test
	 * @param bool $exitOnError If $exitOnError is TRUE and the extension is not loaded the function will die with an error message
	 * @return bool
	 * @throws \BadFunctionCallException
	 */
	static public function isLoaded($key, $exitOnError = FALSE) {
		$isLoaded = static::$packageManager->isPackageActive($key);
		if ($exitOnError && !$isLoaded) {
			throw new \BadFunctionCallException('TYPO3 Fatal Error: Extension "' . $key . '" is not loaded!', 1270853910);
		}
		return $isLoaded;
	}

	/**
	 * Returns the absolute path to the extension with extension key $key.
	 *
	 * @param $key string Extension key
	 * @param $script string $script is appended to the output if set.
	 * @throws \BadFunctionCallException
	 * @return string
	 */
	static public function extPath($key, $script = '') {
		if (!static::$packageManager->isPackageActive($key)) {
			throw new \BadFunctionCallException('TYPO3 Fatal Error: Extension key "' . $key . '" is NOT loaded!', 1365429656);
		}
		return static::$packageManager->getPackage($key)->getPackagePath() . $script;
	}

	/**
	 * Returns the relative path to the extension as measured from from the TYPO3_mainDir
	 * If the extension is not loaded the function will die with an error message
	 * Useful for images and links from backend
	 *
	 * @param string $key Extension key
	 *
	 * @throws \BadFunctionCallException
	 * @return string
	 */
	static public function extRelPath($key) {
		if (!static::$packageManager->isPackageActive($key)) {
			throw new \BadFunctionCallException('TYPO3 Fatal Error: Extension key "' . $key . '" is NOT loaded!', 1365429673);
		}
		$relativePathToSiteRoot = self::siteRelPath($key);
		if (substr($relativePathToSiteRoot, 0, $typo3MainDirLength = strlen(TYPO3_mainDir)) === TYPO3_mainDir) {
			$relativePathToSiteRoot = substr($relativePathToSiteRoot, $typo3MainDirLength);
		} else {
			$relativePathToSiteRoot = '../' . $relativePathToSiteRoot;
		}
		return $relativePathToSiteRoot;
	}

	/**
	 * Returns the relative path to the extension as measured from the PATH_site (frontend)
	 * If the extension is not loaded the function will die with an error message
	 * Useful for images and links from the frontend
	 *
	 * @param string $key Extension key
	 * @return string
	 */
	static public function siteRelPath($key) {
		return PathUtility::stripPathSitePrefix(self::extPath($key));
	}

	/**
	 * Returns the correct class name prefix for the extension key $key
	 *
	 * @param string $key Extension key
	 * @return string
	 * @internal
	 */
	static public function getCN($key) {
		return strpos($key, 'user_') === 0 ? 'user_' . str_replace('_', '', substr($key, 5)) : 'tx_' . str_replace('_', '', $key);
	}

	/**
	 * Returns the real extension key like 'tt_news' from an extension prefix like 'tx_ttnews'.
	 *
	 * @param string $prefix The extension prefix (e.g. 'tx_ttnews')
	 * @return mixed Real extension key (string)or FALSE (bool) if something went wrong
	 */
	static public function getExtensionKeyByPrefix($prefix) {
		$result = FALSE;
		// Build map of short keys referencing to real keys:
		if (!isset(self::$extensionKeyMap)) {
			self::$extensionKeyMap = array();
			foreach (static::$packageManager->getActivePackages() as $package) {
				$shortKey = str_replace('_', '', $package->getPackageKey());
				self::$extensionKeyMap[$shortKey] = $package->getPackageKey();
			}
		}
		// Lookup by the given short key:
		$parts = explode('_', $prefix);
		if (isset(self::$extensionKeyMap[$parts[1]])) {
			$result = self::$extensionKeyMap[$parts[1]];
		}
		return $result;
	}

	/**
	 * Clears the extension key map.
	 *
	 * @return void
	 */
	static public function clearExtensionKeyMap() {
		self::$extensionKeyMap = NULL;
	}

	/**
	 * Retrieves the version of an installed extension.
	 * If the extension is not installed, this function returns an empty string.
	 *
	 * @param string $key The key of the extension to look up, must not be empty
	 *
	 * @throws \InvalidArgumentException
	 * @throws \TYPO3\CMS\Core\Package\Exception
	 * @return string The extension version as a string in the format "x.y.z",
	 */
	static public function getExtensionVersion($key) {
		if (!is_string($key) || empty($key)) {
			throw new \InvalidArgumentException('Extension key must be a non-empty string.', 1294586096);
		}
		if (!static::isLoaded($key)) {
			return '';
		}
		$version = static::$packageManager->getPackage($key)->getPackageMetaData()->getVersion();
		if (empty($version)) {
			throw new \TYPO3\CMS\Core\Package\Exception('Version number in composer manifest of package "' . $key . '" is missing or invalid', 1395614959);
		}
		return $version;
	}

	/**************************************
	 *
	 *	 Adding BACKEND features
	 *	 (related to core features)
	 *
	 ***************************************/
	/**
	 * Adding fields to an existing table definition in $GLOBALS['TCA']
	 * Adds an array with $GLOBALS['TCA'] column-configuration to the $GLOBALS['TCA']-entry for that table.
	 * This function adds the configuration needed for rendering of the field in TCEFORMS - but it does NOT add the field names to the types lists!
	 * So to have the fields displayed you must also call fx. addToAllTCAtypes or manually add the fields to the types list.
	 * FOR USE IN ext_tables.php FILES or files in Configuration/TCA/Overrides/*.php Use the latter to benefit from TCA caching!
	 *
	 * @param string $table The table name of a table already present in $GLOBALS['TCA'] with a columns section
	 * @param array $columnArray The array with the additional columns (typical some fields an extension wants to add)
	 * @param bool $addTofeInterface DEPRECATED: Usage of feInterface is no longer part of the TYPO3 CMS Core. Please check EXT:statictemplates.
	 * @return void
	 */
	static public function addTCAcolumns($table, $columnArray, $addTofeInterface = FALSE) {
		if (is_array($columnArray) && is_array($GLOBALS['TCA'][$table]) && is_array($GLOBALS['TCA'][$table]['columns'])) {
			// Candidate for array_merge() if integer-keys will some day make trouble...
			$GLOBALS['TCA'][$table]['columns'] = array_merge($GLOBALS['TCA'][$table]['columns'], $columnArray);
			if ($addTofeInterface) {
				GeneralUtility::deprecationLog(
					'Usage of feInterface is no longer part of the TYPO3 CMS Core. Please check EXT:' . $GLOBALS['_EXTKEY'] . '.'
				);
			}
		}
	}

	/**
	 * Makes fields visible in the TCEforms, adding them to the end of (all) "types"-configurations
	 *
	 * Adds a string $string (comma separated list of field names) to all ["types"][xxx]["showitem"] entries for table $table (unless limited by $typeList)
	 * This is needed to have new fields shown automatically in the TCEFORMS of a record from $table.
	 * Typically this function is called after having added new columns (database fields) with the addTCAcolumns function
	 * FOR USE IN ext_tables.php FILES or files in Configuration/TCA/Overrides/*.php Use the latter to benefit from TCA caching!
	 *
	 * @param string $table Table name
	 * @param string $newFieldsString Field list to add.
	 * @param string $typeList List of specific types to add the field list to. (If empty, all type entries are affected)
	 * @param string $position Insert fields before (default) or after one
	 * @return void
	 */
	static public function addToAllTCAtypes($table, $newFieldsString, $typeList = '', $position = '') {
		$newFieldsString = trim($newFieldsString);
		if ($newFieldsString === '' || !is_array($GLOBALS['TCA'][$table]['types'])) {
			return;
		}
		list($positionIdentifier, $entityName) = GeneralUtility::trimExplode(':', $position);
		$palettesChanged = array();

		foreach ($GLOBALS['TCA'][$table]['types'] as $type => &$typeDetails) {
			// skip if we don't want to add the field for this type
			if ($typeList !== '' && !GeneralUtility::inList($typeList, $type)) {
				continue;
			}
			// skip if fields were already added
			if (strpos($typeDetails['showitem'], $newFieldsString) !== FALSE) {
				continue;
			}

			$fieldExists = FALSE;
			$newPosition = '';
			$paletteNames = array();
			if (is_array($GLOBALS['TCA'][$table]['palettes'])) {
				// Get the palette names used in current showitem
				$paletteCount = preg_match_all('/(?:^|,)                    # Line start or a comma
					(?:
					    \\s*\\-\\-palette\\-\\-;[^;]*;([^,$]*)|              # --palette--;label;paletteName
					    \\s*\\b[^;,]+\\b(?:;[^;]*;([^;,]+);?[^;,]*;?)?[^,]*  # field;label;paletteName[;options[;colors]]
					)/x', $typeDetails['showitem'], $paletteMatches);
				if ($paletteCount > 0) {
					$paletteNames = array_filter(array_merge($paletteMatches[1], $paletteMatches[2]));
					if (count($paletteNames)) {
						foreach ($paletteNames as $paletteName) {
							$palette = $GLOBALS['TCA'][$table]['palettes'][$paletteName];
							switch ($positionIdentifier) {
								case 'after':
								case 'before':
									if (preg_match('/\\b' . $entityName . '\\b/', $palette['showitem']) > 0) {
										$newPosition = $positionIdentifier . ':--palette--;;' . $paletteName;
									}
									break;
								case 'replace':
									// check if fields have been added to palette before
									if (isset($palettesChanged[$paletteName])) {
										$fieldExists = TRUE;
										continue;
									}
									if (preg_match('/\\b' . $entityName . '\\b/', $palette['showitem']) > 0) {
										self::addFieldsToPalette($table, $paletteName, $newFieldsString, $position);
										// Memorize that we already changed this palette, in case other types also use it
										$palettesChanged[$paletteName] = TRUE;
										$fieldExists = TRUE;
										continue;
									}
									break;
								default:
									// Intentionally left blank
							}
						}
					}
				}
			}
			if ($fieldExists === FALSE) {
				$typeDetails['showitem'] = self::executePositionedStringInsertion(
					$typeDetails['showitem'],
					$newFieldsString,
					$newPosition !== '' ? $newPosition : $position
				);
			}
		}
		unset($typeDetails);
	}

	/**
	 * Adds new fields to all palettes of an existing field.
	 * If the field does not have a palette yet, it's created automatically and
	 * gets called "generatedFor-$field".
	 *
	 * @param string $table Name of the table
	 * @param string $field Name of the field that has the palette to be extended
	 * @param string $addFields List of fields to be added to the palette
	 * @param string $insertionPosition Insert fields before (default) or after one
	 * @return void
	 */
	static public function addFieldsToAllPalettesOfField($table, $field, $addFields, $insertionPosition = '') {
		$generatedPalette = '';
		$processedPalettes = array();
		if (isset($GLOBALS['TCA'][$table]['columns'][$field])) {
			$types = &$GLOBALS['TCA'][$table]['types'];
			if (is_array($types)) {
				// Iterate through all types and search for the field that defines the palette to be extended:
				foreach ($types as $type => $_) {
					$items = self::explodeItemList($types[$type]['showitem']);
					if (isset($items[$field])) {
						// If the field already has a palette, extend it:
						if ($items[$field]['details']['palette']) {
							$palette = $items[$field]['details']['palette'];
							if (!isset($processedPalettes[$palette])) {
								self::addFieldsToPalette($table, $palette, $addFields, $insertionPosition);
								$processedPalettes[$palette] = TRUE;
							}
						} else {
							if ($generatedPalette) {
								$palette = $generatedPalette;
							} else {
								$palette = ($generatedPalette = 'generatedFor-' . $field);
								self::addFieldsToPalette($table, $palette, $addFields, $insertionPosition);
							}
							$items[$field]['details']['palette'] = $palette;
							$types[$type]['showitem'] = self::generateItemList($items);
						}
					}
				}
			}
		}
	}

	/**
	 * Adds new fields to a palette.
	 * If the palette does not exist yet, it's created automatically.
	 *
	 * @param string $table Name of the table
	 * @param string $palette Name of the palette to be extended
	 * @param string $addFields List of fields to be added to the palette
	 * @param string $insertionPosition Insert fields before (default) or after one
	 * @return void
	 */
	static public function addFieldsToPalette($table, $palette, $addFields, $insertionPosition = '') {
		if (isset($GLOBALS['TCA'][$table])) {
			$paletteData = &$GLOBALS['TCA'][$table]['palettes'][$palette];
			// If palette already exists, merge the data:
			if (is_array($paletteData)) {
				$paletteData['showitem'] = self::executePositionedStringInsertion($paletteData['showitem'], $addFields, $insertionPosition);
			} else {
				$paletteData['showitem'] = self::removeDuplicatesForInsertion($addFields);
			}
		}
	}

	/**
	 * Add an item to a select field item list.
	 *
	 * Warning: Do not use this method for radio or check types, especially not
	 * with $relativeToField and $relativePosition parameters. This would shift
	 * existing database data 'off by one'.
	 *
	 * As an example, this can be used to add an item to tt_content CType select
	 * drop-down after the existing 'mailform' field with these parameters:
	 * - $table = 'tt_content'
	 * - $field = 'CType'
	 * - $item = array(
	 * 'LLL:EXT:cms/locallang_ttc.xlf:CType.I.10',
	 * 'login',
	 * 'i/tt_content_login.gif',
	 * ),
	 * - $relativeToField = mailform
	 * - $relativePosition = after
	 *
	 * @throws \InvalidArgumentException If given parameters are not of correct
	 * @throws \RuntimeException If reference to related position fields can not
	 * @param string $table Name of TCA table
	 * @param string $field Name of TCA field
	 * @param array $item New item to add
	 * @param string $relativeToField Add item relative to existing field
	 * @param string $relativePosition Valid keywords: 'before', 'after'
	 * @return void
	 */
	static public function addTcaSelectItem($table, $field, array $item, $relativeToField = '', $relativePosition = '') {
		if (!is_string($table)) {
			throw new \InvalidArgumentException('Given table is of type "' . gettype($table) . '" but a string is expected.', 1303236963);
		}
		if (!is_string($field)) {
			throw new \InvalidArgumentException('Given field is of type "' . gettype($field) . '" but a string is expected.', 1303236964);
		}
		if (!is_string($relativeToField)) {
			throw new \InvalidArgumentException('Given relative field is of type "' . gettype($relativeToField) . '" but a string is expected.', 1303236965);
		}
		if (!is_string($relativePosition)) {
			throw new \InvalidArgumentException('Given relative position is of type "' . gettype($relativePosition) . '" but a string is expected.', 1303236966);
		}
		if ($relativePosition !== '' && $relativePosition !== 'before' && $relativePosition !== 'after' && $relativePosition !== 'replace') {
			throw new \InvalidArgumentException('Relative position must be either empty or one of "before", "after", "replace".', 1303236967);
		}
		if (!is_array($GLOBALS['TCA'][$table]['columns'][$field]['config']['items'])) {
			throw new \RuntimeException('Given select field item list was not found.', 1303237468);
		}
		// Make sure item keys are integers
		$GLOBALS['TCA'][$table]['columns'][$field]['config']['items'] = array_values($GLOBALS['TCA'][$table]['columns'][$field]['config']['items']);
		if ($relativePosition !== '') {
			// Insert at specified position
			$matchedPosition = ArrayUtility::filterByValueRecursive($relativeToField, $GLOBALS['TCA'][$table]['columns'][$field]['config']['items']);
			if (count($matchedPosition) > 0) {
				$relativeItemKey = key($matchedPosition);
				if ($relativePosition === 'replace') {
					$GLOBALS['TCA'][$table]['columns'][$field]['config']['items'][$relativeItemKey] = $item;
				} else {
					if ($relativePosition === 'before') {
						$offset = $relativeItemKey;
					} else {
						$offset = $relativeItemKey + 1;
					}
					array_splice($GLOBALS['TCA'][$table]['columns'][$field]['config']['items'], $offset, 0, array(0 => $item));
				}
			} else {
				// Insert at new item at the end of the array if relative position was not found
				$GLOBALS['TCA'][$table]['columns'][$field]['config']['items'][] = $item;
			}
		} else {
			// Insert at new item at the end of the array
			$GLOBALS['TCA'][$table]['columns'][$field]['config']['items'][] = $item;
		}
	}

	/**
	 * Gets the TCA configuration for a field handling (FAL) files.
	 *
	 * @param string $fieldName Name of the field to be used
	 * @param array $customSettingOverride Custom field settings overriding the basics
	 * @param string $allowedFileExtensions Comma list of allowed file extensions (e.g. "jpg,gif,pdf")
	 * @param string $disallowedFileExtensions
	 *
	 * @return array
	 */
	static public function getFileFieldTCAConfig($fieldName, array $customSettingOverride = array(), $allowedFileExtensions = '', $disallowedFileExtensions = '') {
		$fileFieldTCAConfig = array(
			'type' => 'inline',
			'foreign_table' => 'sys_file_reference',
			'foreign_field' => 'uid_foreign',
			'foreign_sortby' => 'sorting_foreign',
			'foreign_table_field' => 'tablenames',
			'foreign_match_fields' => array(
				'fieldname' => $fieldName
			),
			'foreign_label' => 'uid_local',
			'foreign_selector' => 'uid_local',
			'foreign_selector_fieldTcaOverride' => array(
				'config' => array(
					'appearance' => array(
						'elementBrowserType' => 'file',
						'elementBrowserAllowed' => $allowedFileExtensions
					)
				)
			),
			'filter' => array(
				array(
					'userFunc' => \TYPO3\CMS\Core\Resource\Filter\FileExtensionFilter::class . '->filterInlineChildren',
					'parameters' => array(
						'allowedFileExtensions' => $allowedFileExtensions,
						'disallowedFileExtensions' => $disallowedFileExtensions
					)
				)
			),
			'appearance' => array(
				'useSortable' => TRUE,
				'headerThumbnail' => array(
					'field' => 'uid_local',
					'width' => '45',
					'height' => '45c',
				),
				'showPossibleLocalizationRecords' => FALSE,
				'showRemovedLocalizationRecords' => FALSE,
				'showSynchronizationLink' => FALSE,
				'showAllLocalizationLink' => FALSE,

				'enabledControls' => array(
					'info' => TRUE,
					'new' => FALSE,
					'dragdrop' => TRUE,
					'sort' => FALSE,
					'hide' => TRUE,
					'delete' => TRUE,
					'localize' => TRUE,
				),
			),
			'behaviour' => array(
				'localizationMode' => 'select',
				'localizeChildrenAtParentLocalization' => TRUE,
			),
		);
		ArrayUtility::mergeRecursiveWithOverrule($fileFieldTCAConfig, $customSettingOverride);
		return $fileFieldTCAConfig;
	}

	/**
	 * Adds a list of new fields to the TYPO3 USER SETTINGS configuration "showitem" list, the array with
	 * the new fields itself needs to be added additionally to show up in the user setup, like
	 * $GLOBALS['TYPO3_USER_SETTINGS']['columns'] += $tempColumns
	 *
	 * @param string $addFields List of fields to be added to the user settings
	 * @param string $insertionPosition Insert fields before (default) or after one
	 * @return void
	 */
	static public function addFieldsToUserSettings($addFields, $insertionPosition = '') {
		$GLOBALS['TYPO3_USER_SETTINGS']['showitem'] = self::executePositionedStringInsertion($GLOBALS['TYPO3_USER_SETTINGS']['showitem'], $addFields, $insertionPosition);
	}

	/**
	 * Inserts as list of data into an existing list.
	 * The insertion position can be defined accordant before of after existing list items.
	 *
	 * Example:
	 * + list: 'field_a, field_b, field_c'
	 * + insertionList: 'field_d, field_e'
	 * + insertionPosition: 'after:field_b'
	 * -> 'field_a, field_b, field_d, field_e, field_c'
	 *
	 * $insertPosition may contain ; and - characters: after:--palette--;;title
	 *
	 * @param string $list The list of items to be extended
	 * @param string $insertionList The list of items to inserted
	 * @param string $insertionPosition Insert fields before (default) or after one
	 * @return string The extended list
	 */
	static protected function executePositionedStringInsertion($list, $insertionList, $insertionPosition = '') {
		$list = $newList = trim($list, ", \t\n\r\0\x0B");

		$insertionList = self::removeDuplicatesForInsertion($insertionList, $list);

		if ($insertionList === '') {
			return $list;
		}
		if ($list === '') {
			return $insertionList;
		}
		if ($insertionPosition === '') {
			return $list . ', ' . $insertionList;
		}

		list($location, $positionName) = GeneralUtility::trimExplode(':', $insertionPosition);
		// The $insertPosition may be a palette: after:--palette--;;title
		// In the $list the palette may contain a LLL string in between the ;;
		// Adjust the regex to match that
		if (strpos($positionName, ';;') !== FALSE) {
			$positionName = str_replace(';;', ';[^;]*;', $positionName);
		}

		$pattern = ('/(^|,\\s*)(' . $positionName . ')(;[^,$]+)?(,|$)/');
		switch ($location) {
			case 'after':
				$newList = preg_replace($pattern, '$1$2$3, ' . $insertionList . '$4', $list);
				break;
			case 'before':
				$newList = preg_replace($pattern, '$1' . $insertionList . ', $2$3$4', $list);
				break;
			case 'replace':
				$newList = preg_replace($pattern, '$1' . $insertionList . '$4', $list);
				break;
			default:
		}

		// When preg_replace did not replace anything; append the $insertionList.
		if ($list === $newList) {
			return $list . ', ' . $insertionList;
		}
		return $newList;
	}

	/**
	 * Compares an existing list of items and a list of items to be inserted
	 * and returns a duplicate-free variant of that insertion list.
	 *
	 * Example:
	 * + list: 'field_a, field_b, field_c'
	 * + insertion: 'field_b, field_d, field_c'
	 * -> new insertion: 'field_d'
	 *
	 * Duplicate values in $insertionList are removed.
	 *
	 * @param string $insertionList The list of items to inserted
	 * @param string $list The list of items to be extended (default: '')
	 * @return string Duplicate-free list of items to be inserted
	 */
	static protected function removeDuplicatesForInsertion($insertionList, $list = '') {
		$insertionListParts = preg_split('/\\s*,\\s*/', $insertionList);
		$listMatches = array();
		if ($list !== '') {
			preg_match_all('/(?:^|,)\\s*\\b([^;,]+)\\b[^,]*/', $list, $listMatches);
			$listMatches = $listMatches[1];
		}

		$cleanInsertionListParts = array();
		foreach ($insertionListParts as $fieldName) {
			if ($fieldName == '--linebreak--' || (!in_array($fieldName, $cleanInsertionListParts) && !in_array($fieldName, $listMatches))) {
				$cleanInsertionListParts[] = $fieldName;
			}
		}
		return implode(', ', $cleanInsertionListParts);
	}

	/**
	 * Generates an array of fields/items with additional information such as e.g. the name of the palette.
	 *
	 * @param string $itemList List of fields/items to be splitted up
	 * @return array An array with the names of the fields/items as keys and additional information
	 */
	static protected function explodeItemList($itemList) {
		$items = array();
		$itemParts = GeneralUtility::trimExplode(',', $itemList, TRUE);
		foreach ($itemParts as $itemPart) {
			$itemDetails = GeneralUtility::trimExplode(';', $itemPart, FALSE, 5);
			$key = $itemDetails[0];
			if (strpos($key, '--') !== FALSE) {
				// If $key is a separator (--div--) or palette (--palette--) then it will be appended by a unique number. This must be removed again when using this value!
				$key .= count($items);
			}
			if (!isset($items[$key])) {
				$items[$key] = array(
					'rawData' => $itemPart,
					'details' => array(
						'field' => $itemDetails[0],
						'label' => $itemDetails[1],
						'palette' => $itemDetails[2],
						'special' => $itemDetails[3],
						'styles' => $itemDetails[4]
					)
				);
			}
		}
		return $items;
	}

	/**
	 * Generates a list of fields/items out of an array provided by the function getFieldsOfFieldList().
	 *
	 * @see explodeItemList
	 * @param array $items The array of fields/items with optional additional information
	 * @param bool $useRawData Use raw data instead of building by using the details (default: FALSE)
	 * @return string The list of fields/items which gets used for $GLOBALS['TCA'][<table>]['types'][<type>]['showitem']
	 */
	static protected function generateItemList(array $items, $useRawData = FALSE) {
		$itemParts = array();
		foreach ($items as $item => $itemDetails) {
			if (strpos($item, '--') !== FALSE) {
				// If $item is a separator (--div--) or palette (--palette--) then it may have been appended by a unique number. This must be stripped away here.
				$item = str_replace(array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9), '', $item);
			}
			if ($useRawData) {
				$itemParts[] = $itemDetails['rawData'];
			} else {
				$itemParts[] = count($itemDetails['details']) > 1 ? implode(';', $itemDetails['details']) : $item;
			}
		}
		return implode(', ', $itemParts);
	}

	/**
	 * Add tablename to default list of allowed tables on pages (in $PAGES_TYPES)
	 * Will add the $table to the list of tables allowed by default on pages as setup by $PAGES_TYPES['default']['allowedTables']
	 * FOR USE IN ext_tables.php FILES
	 *
	 * @param string $table Table name
	 * @return void
	 */
	static public function allowTableOnStandardPages($table) {
		$GLOBALS['PAGES_TYPES']['default']['allowedTables'] .= ',' . $table;
	}

	/**
	 * Adds a ExtJS module (main or sub) to the backend interface
	 * FOR USE IN ext_tables.php FILES
	 *
	 * @static
	 * @param string $extensionName
	 * @param string $mainModuleName Is the main module key
	 * @param string $subModuleName Is the submodule key, if blank a plain main module is generated
	 * @param string $position Passed to \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addModule, see reference there
	 * @param array $moduleConfiguration Icon with array keys: access, icon, labels to configure the module
	 * @throws \InvalidArgumentException
	 */
	static public function addExtJSModule($extensionName, $mainModuleName, $subModuleName = '', $position = '', array $moduleConfiguration = array()) {
		if (empty($extensionName)) {
			throw new \InvalidArgumentException('The extension name must not be empty', 1325938973);
		}
		$extensionKey = GeneralUtility::camelCaseToLowerCaseUnderscored($extensionName);
		$extensionName = str_replace(' ', '', ucwords(str_replace('_', ' ', $extensionName)));
		$defaultModuleConfiguration = array(
			'access' => 'admin',
			'icon' => 'gfx/typo3.png',
			'labels' => '',
			'extRelPath' => self::extRelPath($extensionKey) . 'Classes/'
		);
		// Add mandatory parameter to use new pagetree
		if ($mainModuleName === 'web') {
			$defaultModuleConfiguration['navigationComponentId'] = 'typo3-pagetree';
		}
		ArrayUtility::mergeRecursiveWithOverrule($defaultModuleConfiguration, $moduleConfiguration);
		$moduleConfiguration = $defaultModuleConfiguration;
		if ($subModuleName !== '') {
			$moduleSignature = $mainModuleName . '_' . $subModuleName;
		} else {
			$moduleSignature = $mainModuleName;
		}
		$moduleConfiguration['name'] = $moduleSignature;
		$moduleConfiguration['script'] = 'extjspaneldummy.html';
		$moduleConfiguration['extensionName'] = $extensionName;
		$moduleConfiguration['configureModuleFunction'] = array(ExtensionManagementUtility::class, 'configureModule');
		$GLOBALS['TBE_MODULES']['_configuration'][$moduleSignature] = $moduleConfiguration;
		self::addModule($mainModuleName, $subModuleName, $position);
	}

	/**
	 * This method is called from \TYPO3\CMS\Backend\Module\ModuleLoader::checkMod
	 * and it replaces old conf.php.
	 *
	 * The original function for is called
	 * typo3/sysext/extbase/Classes/Utility/ExtensionUtility.php::configureModule
	 * the referred function can be deprecated now
	 *
	 * @param string $moduleSignature The module name
	 * @param string $modulePath Absolute path to module (not used by Extbase currently)
	 * @return array Configuration of the module
	 */
	static public function configureModule($moduleSignature, $modulePath) {
		$moduleConfiguration = $GLOBALS['TBE_MODULES']['_configuration'][$moduleSignature];
		$iconPathAndFilename = $moduleConfiguration['icon'];
		if (substr($iconPathAndFilename, 0, 4) === 'EXT:') {
			list($extensionKey, $relativePath) = explode('/', substr($iconPathAndFilename, 4), 2);
			$iconPathAndFilename = self::extPath($extensionKey) . $relativePath;
		}
		// @todo skin support
		$moduleLabels = array(
			'tabs_images' => array(
				'tab' => $iconPathAndFilename
			),
			'labels' => array(
				'tablabel' => $GLOBALS['LANG']->sL($moduleConfiguration['labels'] . ':mlang_labels_tablabel'),
				'tabdescr' => $GLOBALS['LANG']->sL($moduleConfiguration['labels'] . ':mlang_labels_tabdescr')
			),
			'tabs' => array(
				'tab' => $GLOBALS['LANG']->sL($moduleConfiguration['labels'] . ':mlang_tabs_tab')
			)
		);
		$GLOBALS['LANG']->addModuleLabels($moduleLabels, $moduleSignature . '_');
		return $moduleConfiguration;
	}

	/**
	 * Adds a module (main or sub) to the backend interface
	 * FOR USE IN ext_tables.php FILES
	 *
	 * @param string $main The main module key, $sub is the submodule key. So $main would be an index in the $TBE_MODULES array and $sub could be an element in the lists there.
	 * @param string $sub The submodule key. If $sub is not set a blank $main module is created.
	 * @param string $position Can be used to set the position of the $sub module within the list of existing submodules for the main module. $position has this syntax: [cmd]:[submodule-key]. cmd can be "after", "before" or "top" (or blank which is default). If "after"/"before" then submodule will be inserted after/before the existing submodule with [submodule-key] if found. If not found, the bottom of list. If "top" the module is inserted in the top of the submodule list.
	 * @param string $path The absolute path to the module. If this value is defined the path is added as an entry in $TBE_MODULES['_PATHS'][  main_sub  ] = $path; and thereby tells the backend where the newly added modules is found in the system.
	 * @param array $moduleConfiguration additional configuration, previously put in "conf.php" of the module directory
	 * @return void
	 */
	static public function addModule($main, $sub = '', $position = '', $path = '', $moduleConfiguration = array()) {
		// If there is already a main module by this name:
		// Adding the submodule to the correct position:
		if (isset($GLOBALS['TBE_MODULES'][$main]) && $sub) {
			list($place, $modRef) = GeneralUtility::trimExplode(':', $position, TRUE);
			$modules = ',' . $GLOBALS['TBE_MODULES'][$main] . ',';
			if ($place === NULL || ($modRef !== NULL && !GeneralUtility::inList($modules, $modRef))) {
				$place = 'bottom';
			}
			$modRef = ',' . $modRef . ',';
			if (!GeneralUtility::inList($modules, $sub)) {
				switch (strtolower($place)) {
					case 'after':
						$modules = str_replace($modRef, $modRef . $sub . ',', $modules);
						break;
					case 'before':
						$modules = str_replace($modRef, ',' . $sub . $modRef, $modules);
						break;
					case 'top':
						$modules = $sub . $modules;
						break;
					case 'bottom':
					default:
						$modules = $modules . $sub;
				}
			}
			// Re-inserting the submodule list:
			$GLOBALS['TBE_MODULES'][$main] = trim($modules, ',');
		} else {
			// Create new main modules with only one submodule, $sub (or none if $sub is blank)
			$GLOBALS['TBE_MODULES'][$main] = $sub;
		}
		$fullModuleSignature = $main . ($sub ? '_' . $sub : '');
		// Adding path:
		if ($path) {
			$GLOBALS['TBE_MODULES']['_PATHS'][$fullModuleSignature] = $path;
		}

		// add additional configuration
		if (is_array($moduleConfiguration) && count($moduleConfiguration) > 0) {
			$GLOBALS['TBE_MODULES']['_configuration'][$fullModuleSignature] = $moduleConfiguration;
		}
	}

	/**
	 * Registers an Ext.Direct component with access restrictions.
	 *
	 * @param string $endpointName
	 * @param string $callbackClass
	 * @param string $moduleName Optional: must be <mainmodule> or <mainmodule>_<submodule>
	 * @param string $accessLevel Optional: can be 'admin' or 'user,group'
	 * @return void
	 */
	static public function registerExtDirectComponent($endpointName, $callbackClass, $moduleName = NULL, $accessLevel = NULL) {
		$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ExtDirect'][$endpointName] = array(
			'callbackClass' => $callbackClass,
			'moduleName' => $moduleName,
			'accessLevel' => $accessLevel
		);
	}

	/**
	 * Registers an Ajax Handler
	 *
	 * @param string $ajaxId Identifier of the handler, that is used in the request
	 * @param string $callbackMethod TYPO3 callback method (className->methodName).
	 * @param bool $csrfTokenCheck Only set this to FALSE if you are sure that the registered handler does not modify any data!
	 */
	static public function registerAjaxHandler($ajaxId, $callbackMethod, $csrfTokenCheck = TRUE) {
		$GLOBALS['TYPO3_CONF_VARS']['BE']['AJAX'][$ajaxId] = array(
			'callbackMethod' => $callbackMethod,
			'csrfTokenCheck' => $csrfTokenCheck
		);
	}

	/**
	 * Adds a module path to $GLOBALS['TBE_MODULES'] for used with the module dispatcher, mod.php
	 * Used only for modules that are not placed in the main/sub menu hierarchy by the traditional mechanism of addModule()
	 * Examples for this is context menu functionality (like import/export) which runs as an independent module through mod.php
	 * FOR USE IN ext_tables.php FILES
	 * Example:  \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addModulePath('xMOD_tximpexp', \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($_EXTKEY).'app/');
	 *
	 * @param string $name The name of the module, refer to conf.php of the module.
	 * @param string $path The absolute path to the module directory inside of which "index.php" and "conf.php" is found.
	 * @return void
	 */
	static public function addModulePath($name, $path) {
		$GLOBALS['TBE_MODULES']['_PATHS'][$name] = $path;
	}

	/**
	 * Adds a "Function menu module" ('third level module') to an existing function menu for some other backend module
	 * The arguments values are generally determined by which function menu this is supposed to interact with
	 * See Inside TYPO3 for information on how to use this function.
	 * FOR USE IN ext_tables.php FILES
	 *
	 * @param string $modname Module name
	 * @param string $className Class name
	 * @param string $classPath Class path, deprecated since 6.2, use auto-loading instead
	 * @param string $title Title of module
	 * @param string $MM_key Menu array key - default is "function
	 * @param string $WS Workspace conditions. Blank means all workspaces, any other string can be a comma list of "online", "offline" and "custom
	 * @return void
	 * @see \TYPO3\CMS\Backend\Module\BaseScriptClass::mergeExternalItems()
	 */
	static public function insertModuleFunction($modname, $className, $classPath = NULL, $title, $MM_key = 'function', $WS = '') {
		$GLOBALS['TBE_MODULES_EXT'][$modname]['MOD_MENU'][$MM_key][$className] = array(
			'name' => $className,
			'path' => NULL,
			'title' => $title,
			'ws' => $WS
		);
	}

	/**
	 * Adds some more content to a key of TYPO3_CONF_VARS array.
	 *
	 * This also tracks which content was added by extensions (in TYPO3_CONF_VARS_extensionAdded)
	 * so that they cannot be editted again through the Install Tool.
	 *
	 * @static
	 * @param string $group The group ('FE', 'BE', 'SYS' ...)
	 * @param string $key The key of this setting within the group
	 * @param string $content The text to add (include leading "\n" in case of multi-line entries)
	 * @return void
	 */
	static public function appendToTypoConfVars($group, $key, $content) {
		$GLOBALS['TYPO3_CONF_VARS_extensionAdded'][$group][$key] .= $content;
		$GLOBALS['TYPO3_CONF_VARS'][$group][$key] .= $content;
	}

	/**
	 * Adds $content to the default Page TSconfig as set in $GLOBALS['TYPO3_CONF_VARS'][BE]['defaultPageTSconfig']
	 * Prefixed with a [GLOBAL] line
	 * FOR USE IN ext_tables.php/ext_localconf.php FILES
	 *
	 * @param string $content Page TSconfig content
	 * @return void
	 */
	static public function addPageTSConfig($content) {
		self::appendToTypoConfVars('BE', 'defaultPageTSconfig', '
[GLOBAL]
' . $content);
	}

	/**
	 * Adds $content to the default User TSconfig as set in $GLOBALS['TYPO3_CONF_VARS'][BE]['defaultUserTSconfig']
	 * Prefixed with a [GLOBAL] line
	 * FOR USE IN ext_tables.php/ext_localconf.php FILES
	 *
	 * @param string $content User TSconfig content
	 * @return void
	 */
	static public function addUserTSConfig($content) {
		self::appendToTypoConfVars('BE', 'defaultUserTSconfig', '
[GLOBAL]
' . $content);
	}

	/**
	 * Adds a reference to a locallang file with $GLOBALS['TCA_DESCR'] labels
	 * FOR USE IN ext_tables.php FILES
	 * eg. \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('pages', 'EXT:lang/locallang_csh_pages.xlf'); for the pages table or \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('_MOD_web_layout', 'EXT:cms/locallang_csh_weblayout.php'); for the Web > Page module.
	 *
	 * @param string $tca_descr_key Description key. Typically a database table (like "pages") but for applications can be other strings, but prefixed with "_MOD_")
	 * @param string $file_ref File reference to locallang file, eg. "EXT:lang/locallang_csh_pages.xlf" (or ".xml")
	 * @return void
	 */
	static public function addLLrefForTCAdescr($tca_descr_key, $file_ref) {
		if ($tca_descr_key) {
			if (!is_array($GLOBALS['TCA_DESCR'][$tca_descr_key])) {
				$GLOBALS['TCA_DESCR'][$tca_descr_key] = array();
			}
			if (!is_array($GLOBALS['TCA_DESCR'][$tca_descr_key]['refs'])) {
				$GLOBALS['TCA_DESCR'][$tca_descr_key]['refs'] = array();
			}
			$GLOBALS['TCA_DESCR'][$tca_descr_key]['refs'][] = $file_ref;
		}
	}

	/**
	 * Registers a navigation component
	 *
	 * @param string $module
	 * @param string $componentId
	 * @param string $extensionKey
	 * @throws \RuntimeException
	 *@return void
	 */
	static public function addNavigationComponent($module, $componentId, $extensionKey = NULL) {
		$extensionKey = $extensionKey ?: $GLOBALS['_EXTKEY'];
		if (!isset($extensionKey)) {
			throw new \RuntimeException('No extensionKey set in addNavigationComponent(). Provide it as third Parameter', 1404068039);
		}
		$GLOBALS['TBE_MODULES']['_navigationComponents'][$module] = array(
			'componentId' => $componentId,
			'extKey' => $extensionKey,
			'isCoreComponent' => FALSE
		);
	}

	/**
	 * Registers a core navigation component
	 *
	 * @param string $module
	 * @param string $componentId
	 * @return void
	 */
	static public function addCoreNavigationComponent($module, $componentId) {
		self::addNavigationComponent($module, $componentId);
		$GLOBALS['TBE_MODULES']['_navigationComponents'][$module]['isCoreComponent'] = TRUE;
	}

	/**************************************
	 *
	 *	 Adding SERVICES features
	 *
	 ***************************************/
	/**
	 * Adds a service to the global services array
	 *
	 * @param string $extKey Extension key
	 * @param string $serviceType Service type, must not be prefixed "tx_" or "Tx_
	 * @param string $serviceKey Service key, must be prefixed "tx_", "Tx_" or "user_
	 * @param array $info Service description array
	 * @return void
	 */
	static public function addService($extKey, $serviceType, $serviceKey, $info) {
		if ($serviceType && is_array($info)) {
			$info['priority'] = max(0, min(100, $info['priority']));
			$GLOBALS['T3_SERVICES'][$serviceType][$serviceKey] = $info;
			$GLOBALS['T3_SERVICES'][$serviceType][$serviceKey]['extKey'] = $extKey;
			$GLOBALS['T3_SERVICES'][$serviceType][$serviceKey]['serviceKey'] = $serviceKey;
			$GLOBALS['T3_SERVICES'][$serviceType][$serviceKey]['serviceType'] = $serviceType;
			// Change the priority (and other values) from $GLOBALS['TYPO3_CONF_VARS']
			// $GLOBALS['TYPO3_CONF_VARS']['T3_SERVICES'][$serviceType][$serviceKey]['priority']
			// even the activation is possible (a unix service might be possible on windows for some reasons)
			if (is_array($GLOBALS['TYPO3_CONF_VARS']['T3_SERVICES'][$serviceType][$serviceKey])) {
				// No check is done here - there might be configuration values only the service type knows about, so
				// we pass everything
				$GLOBALS['T3_SERVICES'][$serviceType][$serviceKey] = array_merge($GLOBALS['T3_SERVICES'][$serviceType][$serviceKey], $GLOBALS['TYPO3_CONF_VARS']['T3_SERVICES'][$serviceType][$serviceKey]);
			}
			// OS check
			// Empty $os means 'not limited to one OS', therefore a check is not needed
			if ($GLOBALS['T3_SERVICES'][$serviceType][$serviceKey]['available'] && $GLOBALS['T3_SERVICES'][$serviceType][$serviceKey]['os'] != '') {
				// TYPO3_OS is not yet defined
				$os_type = stripos(PHP_OS, 'win') !== FALSE && !stripos(PHP_OS, 'darwin') !== FALSE ? 'WIN' : 'UNIX';
				$os = GeneralUtility::trimExplode(',', strtoupper($GLOBALS['T3_SERVICES'][$serviceType][$serviceKey]['os']));
				if (!in_array($os_type, $os)) {
					self::deactivateService($serviceType, $serviceKey);
				}
			}
			// Convert subtype list to array for quicker access
			$GLOBALS['T3_SERVICES'][$serviceType][$serviceKey]['serviceSubTypes'] = array();
			$serviceSubTypes = GeneralUtility::trimExplode(',', $info['subtype']);
			foreach ($serviceSubTypes as $subtype) {
				$GLOBALS['T3_SERVICES'][$serviceType][$serviceKey]['serviceSubTypes'][$subtype] = $subtype;
			}
		}
	}

	/**
	 * Find the available service with highest priority
	 *
	 * @param string $serviceType Service type
	 * @param string $serviceSubType Service sub type
	 * @param mixed $excludeServiceKeys Service keys that should be excluded in the search for a service. Array or comma list.
	 * @return mixed Service info array if a service was found, FALSE otherwise
	 */
	static public function findService($serviceType, $serviceSubType = '', $excludeServiceKeys = array()) {
		$serviceKey = FALSE;
		$serviceInfo = FALSE;
		$priority = 0;
		$quality = 0;
		if (!is_array($excludeServiceKeys)) {
			$excludeServiceKeys = GeneralUtility::trimExplode(',', $excludeServiceKeys, TRUE);
		}
		if (is_array($GLOBALS['T3_SERVICES'][$serviceType])) {
			foreach ($GLOBALS['T3_SERVICES'][$serviceType] as $key => $info) {
				if (in_array($key, $excludeServiceKeys)) {
					continue;
				}
				// Select a subtype randomly
				// Useful to start a service by service key without knowing his subtypes - for testing purposes
				if ($serviceSubType == '*') {
					$serviceSubType = key($info['serviceSubTypes']);
				}
				// This matches empty subtype too
				if ($info['available'] && ($info['subtype'] == $serviceSubType || $info['serviceSubTypes'][$serviceSubType]) && $info['priority'] >= $priority) {
					// Has a lower quality than the already found, therefore we skip this service
					if ($info['priority'] == $priority && $info['quality'] < $quality) {
						continue;
					}
					// Check if the service is available
					$info['available'] = self::isServiceAvailable($serviceType, $key, $info);
					// Still available after exec check?
					if ($info['available']) {
						$serviceKey = $key;
						$priority = $info['priority'];
						$quality = $info['quality'];
					}
				}
			}
		}
		if ($serviceKey) {
			$serviceInfo = $GLOBALS['T3_SERVICES'][$serviceType][$serviceKey];
		}
		return $serviceInfo;
	}

	/**
	 * Find a specific service identified by its key
	 * Note that this completely bypasses the notions of priority and quality
	 *
	 * @param string $serviceKey Service key
	 * @return array Service info array if a service was found
	 * @throws \TYPO3\CMS\Core\Exception
	 */
	static public function findServiceByKey($serviceKey) {
		if (is_array($GLOBALS['T3_SERVICES'])) {
			// Loop on all service types
			// NOTE: we don't care about the actual type, we are looking for a specific key
			foreach ($GLOBALS['T3_SERVICES'] as $serviceType => $servicesPerType) {
				if (isset($servicesPerType[$serviceKey])) {
					$serviceDetails = $servicesPerType[$serviceKey];
					// Test if service is available
					if (self::isServiceAvailable($serviceType, $serviceKey, $serviceDetails)) {
						// We have found the right service, return its information
						return $serviceDetails;
					}
				}
			}
		}
		throw new \TYPO3\CMS\Core\Exception('Service not found for key: ' . $serviceKey, 1319217244);
	}

	/**
	 * Check if a given service is available, based on the executable files it depends on
	 *
	 * @param string $serviceType Type of service
	 * @param string $serviceKey Specific key of the service
	 * @param array $serviceDetails Information about the service
	 * @return bool Service availability
	 */
	static public function isServiceAvailable($serviceType, $serviceKey, $serviceDetails) {
		// If the service depends on external programs - check if they exists
		if (trim($serviceDetails['exec'])) {
			$executables = GeneralUtility::trimExplode(',', $serviceDetails['exec'], TRUE);
			foreach ($executables as $executable) {
				// If at least one executable file is not available, exit early returning FALSE
				if (!CommandUtility::checkCommand($executable)) {
					self::deactivateService($serviceType, $serviceKey);
					return FALSE;
				}
			}
		}
		// The service is available
		return TRUE;
	}

	/**
	 * Deactivate a service
	 *
	 * @param string $serviceType Service type
	 * @param string $serviceKey Service key
	 * @return void
	 */
	static public function deactivateService($serviceType, $serviceKey) {
		// ... maybe it's better to move non-available services to a different array??
		$GLOBALS['T3_SERVICES'][$serviceType][$serviceKey]['available'] = FALSE;
	}

	/**************************************
	 *
	 *	 Adding FRONTEND features
	 *	 (related specifically to "cms" extension)
	 *
	 ***************************************/
	/**
	 * Adds an entry to the list of plugins in content elements of type "Insert plugin"
	 * Takes the $itemArray (label, value[,icon]) and adds to the items-array of $GLOBALS['TCA'][tt_content] elements with CType "listtype" (or another field if $type points to another fieldname)
	 * If the value (array pos. 1) is already found in that items-array, the entry is substituted, otherwise the input array is added to the bottom.
	 * Use this function to add a frontend plugin to this list of plugin-types - or more generally use this function to add an entry to any selectorbox/radio-button set in the TCEFORMS
	 * FOR USE IN ext_tables.php FILES or files in Configuration/TCA/Overrides/*.php Use the latter to benefit from TCA caching!
	 *
	 * @param array $itemArray Numerical array: [0] => Plugin label, [1] => Underscored extension key, [2] => Path to plugin icon relative to TYPO3_mainDir
	 * @param string $type Type (eg. "list_type") - basically a field from "tt_content" table
	 * @param string $extensionKey The extension key
	 * @throws \RuntimeException
	 * @return void
	 */
	static public function addPlugin($itemArray, $type = 'list_type', $extensionKey = NULL) {
		$extensionKey = $extensionKey ?: $GLOBALS['_EXTKEY'];
		if (!isset($extensionKey)) {
			throw new \RuntimeException(
				'No extension key could be determined when calling addPlugin()!'
				. LF
				. 'This method is meant to be called from an ext_tables.php or Configuration/TCA/Overrides file. '
				. 'If you call it from Configuration/TCA/Overrides, the extension key needs to be specified as third parameter. '
				. 'Calling it from any other place e.g. ext_localconf.php does not work and is not supported.',
				1404068038
			);
		}
		if ($extensionKey && !$itemArray[2] && isset($GLOBALS['TYPO3_LOADED_EXT'][$extensionKey]['ext_icon'])) {
			$itemArray[2] = self::extRelPath($extensionKey) . $GLOBALS['TYPO3_LOADED_EXT'][$extensionKey]['ext_icon'];
		}
		if (is_array($GLOBALS['TCA']['tt_content']['columns']) && is_array($GLOBALS['TCA']['tt_content']['columns'][$type]['config']['items'])) {
			foreach ($GLOBALS['TCA']['tt_content']['columns'][$type]['config']['items'] as $k => $v) {
				if ((string)$v[1] === (string)$itemArray[1]) {
					$GLOBALS['TCA']['tt_content']['columns'][$type]['config']['items'][$k] = $itemArray;
					return;
				}
			}
			$GLOBALS['TCA']['tt_content']['columns'][$type]['config']['items'][] = $itemArray;
		}
	}

	/**
	 * Adds an entry to the "ds" array of the tt_content field "pi_flexform".
	 * This is used by plugins to add a flexform XML reference / content for use when they are selected as plugin or content element.
	 *
	 * @param string $piKeyToMatch Plugin key as used in the list_type field. Use the asterisk * to match all list_type values.
	 * @param string $value Either a reference to a flex-form XML file (eg. "FILE:EXT:newloginbox/flexform_ds.xml") or the XML directly.
	 * @param string $CTypeToMatch Value of tt_content.CType (Content Type) to match. The default is "list" which corresponds to the "Insert Plugin" content element.  Use the asterisk * to match all CType values.
	 * @return void
	 * @see addPlugin()
	 */
	static public function addPiFlexFormValue($piKeyToMatch, $value, $CTypeToMatch = 'list') {
		if (is_array($GLOBALS['TCA']['tt_content']['columns']) && is_array($GLOBALS['TCA']['tt_content']['columns']['pi_flexform']['config']['ds'])) {
			$GLOBALS['TCA']['tt_content']['columns']['pi_flexform']['config']['ds'][$piKeyToMatch . ',' . $CTypeToMatch] = $value;
		}
	}

	/**
	 * Adds the $table tablename to the list of tables allowed to be includes by content element type "Insert records"
	 * By using $content_table and $content_field you can also use the function for other tables.
	 * FOR USE IN ext_tables.php FILES or files in Configuration/TCA/Overrides/*.php Use the latter to benefit from TCA caching!
	 *
	 * @param string $table Table name to allow for "insert record
	 * @param string $content_table Table name TO WHICH the $table name is applied. See $content_field as well.
	 * @param string $content_field Field name in the database $content_table in which $table is allowed to be added as a reference ("Insert Record")
	 * @return void
	 */
	static public function addToInsertRecords($table, $content_table = 'tt_content', $content_field = 'records') {
		if (is_array($GLOBALS['TCA'][$content_table]['columns']) && isset($GLOBALS['TCA'][$content_table]['columns'][$content_field]['config']['allowed'])) {
			$GLOBALS['TCA'][$content_table]['columns'][$content_field]['config']['allowed'] .= ',' . $table;
		}
	}

	/**
	 * Add PlugIn to Static Template #43
	 *
	 * When adding a frontend plugin you will have to add both an entry to the TCA definition of tt_content table AND to the TypoScript template which must initiate the rendering.
	 * Since the static template with uid 43 is the "content.default" and practically always used for rendering the content elements it's very useful to have this function automatically adding the necessary TypoScript for calling your plugin. It will also work for the extension "css_styled_content"
	 * $type determines the type of frontend plugin:
	 * + list_type (default) - the good old "Insert plugin" entry
	 * + menu_type - a "Menu/Sitemap" entry
	 * + CType - a new content element type
	 * + header_layout - an additional header type (added to the selection of layout1-5)
	 * + includeLib - just includes the library for manual use somewhere in TypoScript.
	 * (Remember that your $type definition should correspond to the column/items array in $GLOBALS['TCA'][tt_content] where you added the selector item for the element! See addPlugin() function)
	 * FOR USE IN ext_localconf.php FILES
	 *
	 * @param string $key The extension key
	 * @param string $classFile The PHP-class filename relative to the extension root directory. If set to blank a default value is chosen according to convensions.
	 * @param string $suffix Is used as a suffix of the class name (e.g. "_pi1")
	 * @param string $type See description above
	 * @param int $cached If $cached is set as USER content object (cObject) is created - otherwise a USER_INT object is created.
	 *
	 * @return void
	 */
	static public function addPItoST43($key, $classFile = '', $suffix = '', $type = 'list_type', $cached = 0) {
		$classFile = $classFile ? $classFile : 'pi/class.tx_' . str_replace('_', '', $key) . $suffix . '.php';
		$cN = self::getCN($key);
		// General plugin
		$pluginContent = trim('
plugin.' . $cN . $suffix . ' = USER' . ($cached ? '' : '_INT') . '
plugin.' . $cN . $suffix . ' {
	includeLibs = ' . $GLOBALS['TYPO3_LOADED_EXT'][$key]['siteRelPath'] . $classFile . '
	userFunc = ' . $cN . $suffix . '->main
}');
		self::addTypoScript($key, 'setup', '
# Setting ' . $key . ' plugin TypoScript
' . $pluginContent);
		// After ST43
		switch ($type) {
			case 'list_type':
				$addLine = 'tt_content.list.20.' . $key . $suffix . ' = < plugin.' . $cN . $suffix;
				break;
			case 'menu_type':
				$addLine = 'tt_content.menu.20.' . $key . $suffix . ' = < plugin.' . $cN . $suffix;
				break;
			case 'CType':
				$addLine = trim('
tt_content.' . $key . $suffix . ' = COA
tt_content.' . $key . $suffix . ' {
	10 = < lib.stdheader
	20 = < plugin.' . $cN . $suffix . '
}
');
				break;
			case 'header_layout':
				$addLine = 'lib.stdheader.10.' . $key . $suffix . ' = < plugin.' . $cN . $suffix;
				break;
			case 'includeLib':
				$addLine = 'page.1000 = < plugin.' . $cN . $suffix;
				break;
			default:
				$addLine = '';
		}
		if ($addLine) {
			self::addTypoScript($key, 'setup', '
# Setting ' . $key . ' plugin TypoScript
' . $addLine . '
', 43);
		}
	}

	/**
	 * Call this method to add an entry in the static template list found in sys_templates
	 * FOR USE IN ext_tables.php FILES or files in Configuration/TCA/Overrides/*.php Use the latter to benefit from TCA caching!
	 *
	 * @param string $extKey Is of course the extension key
	 * @param string $path Is the path where the template files (fixed names) include_static.txt (integer list of uids from the table "static_templates"), constants.txt, setup.txt, and include_static_file.txt is found (relative to extPath, eg. 'static/'). The file include_static_file.txt, allows you to include other static templates defined in files, from your static template, and thus corresponds to the field 'include_static_file' in the sys_template table. The syntax for this is a comma separated list of static templates to include, like:  EXT:css_styled_content/static/,EXT:da_newsletter_subscription/static/,EXT:cc_random_image/pi2/static/
	 * @param string $title Is the title in the selector box.
	 * @return void
	 * @see addTypoScript()
	 */
	static public function addStaticFile($extKey, $path, $title) {
		if ($extKey && $path && is_array($GLOBALS['TCA']['sys_template']['columns'])) {
			$value = str_replace(',', '', 'EXT:' . $extKey . '/' . $path);
			$itemArray = array(trim($title . ' (' . $extKey . ')'), $value);
			$GLOBALS['TCA']['sys_template']['columns']['include_static_file']['config']['items'][] = $itemArray;
		}
	}

	/**
	 * Adds $content to the default TypoScript setup code as set in $GLOBALS['TYPO3_CONF_VARS'][FE]['defaultTypoScript_setup']
	 * Prefixed with a [GLOBAL] line
	 * FOR USE IN ext_localconf.php FILES
	 *
	 * @param string $content TypoScript Setup string
	 * @return void
	 */
	static public function addTypoScriptSetup($content) {
		self::appendToTypoConfVars('FE', 'defaultTypoScript_setup', '
[GLOBAL]
' . $content);
	}

	/**
	 * Adds $content to the default TypoScript constants code as set in $GLOBALS['TYPO3_CONF_VARS'][FE]['defaultTypoScript_constants']
	 * Prefixed with a [GLOBAL] line
	 * FOR USE IN ext_localconf.php FILES
	 *
	 * @param string $content TypoScript Constants string
	 * @return void
	 */
	static public function addTypoScriptConstants($content) {
		self::appendToTypoConfVars('FE', 'defaultTypoScript_constants', '
[GLOBAL]
' . $content);
	}

	/**
	 * Adds $content to the default TypoScript code for either setup or constants as set in $GLOBALS['TYPO3_CONF_VARS'][FE]['defaultTypoScript_*']
	 * (Basically this function can do the same as addTypoScriptSetup and addTypoScriptConstants - just with a little more hazzle, but also with some more options!)
	 * FOR USE IN ext_localconf.php FILES
	 * Note: As of TYPO3 CMS 6.2, static template #43 (content: default) was replaced with "defaultContentRendering" which makes it
	 * possible that a first extension like css_styled_content registers a "contentRendering" template (= a template that defines default content rendering TypoScript)
	 * by adding itself to $TYPO3_CONF_VARS[FE][contentRenderingTemplates][] = 'myext/Configuration/TypoScript'.
	 * An extension calling addTypoScript('myext', 'setup', $typoScript, 'defaultContentRendering') will add its TypoScript directly after;
	 * For now, "43" and "defaultContentRendering" can be used, but defaultContentRendering is more descriptive and should be used in the future
	 *
	 * @param string $key Is the extension key (informative only).
	 * @param string $type Is either "setup" or "constants" and obviously determines which kind of TypoScript code we are adding.
	 * @param string $content Is the TS content, will be prefixed with a [GLOBAL] line and a comment-header.
	 * @param int|string $afterStaticUid Is either an integer pointing to a uid of a static_template or a string pointing to the "key" of a static_file template ([reduced extension_key]/[local path]). The points is that the TypoScript you add is included only IF that static template is included (and in that case, right after). So effectively the TypoScript you set can specifically overrule settings from those static templates.
	 *
	 * @return void
	 */
	static public function addTypoScript($key, $type, $content, $afterStaticUid = 0) {
		if ($type === 'setup' || $type === 'constants') {
			$content = '

[GLOBAL]
#############################################
## TypoScript added by extension "' . $key . '"
#############################################

' . $content;
			if ($afterStaticUid) {
				$GLOBALS['TYPO3_CONF_VARS']['FE']['defaultTypoScript_' . $type . '.'][$afterStaticUid] .= $content;
				// If 'content (default)' is targeted (static uid 43),
				// the content is added after typoscript of type contentRendering, eg. css_styled_content, see EXT:frontend/TemplateService for that
				if ($afterStaticUid == 43 || $afterStaticUid === 'defaultContentRendering') {
					$GLOBALS['TYPO3_CONF_VARS']['FE']['defaultTypoScript_' . $type . '.']['defaultContentRendering'] .= $content;
				}
			} else {
				$GLOBALS['TYPO3_CONF_VARS']['FE']['defaultTypoScript_' . $type] .= $content;
			}
		}
	}

	/***************************************
	 *
	 * Internal extension management methods
	 *
	 ***************************************/
	/**
	 * Find extension icon
	 *
	 * @param string $extensionPath Path to extension directory.
	 * @param bool $returnFullPath Return full path of file.
	 *
	 * @return string
	 */
	static public function getExtensionIcon($extensionPath, $returnFullPath = FALSE) {
		$icon = '';
		$iconFileTypesToCheckFor = array('png', 'svg', 'gif');
		foreach ($iconFileTypesToCheckFor as $fileType) {
			if (file_exists($extensionPath . 'ext_icon.' . $fileType)) {
				$icon = 'ext_icon.' . $fileType;
				break;
			}
		}
		return $returnFullPath ? $extensionPath . $icon : $icon;
	}

	/**
	 * Execute all ext_localconf.php files of loaded extensions.
	 * The method implements an optionally used caching mechanism that concatenates all
	 * ext_localconf.php files in one file.
	 *
	 * This is an internal method. It is only used during bootstrap and
	 * extensions should not use it!
	 *
	 * @param bool $allowCaching Whether or not to load / create concatenated cache file
	 * @return void
	 * @access private
	 */
	static public function loadExtLocalconf($allowCaching = TRUE) {
		if ($allowCaching) {
			$cacheIdentifier = self::getExtLocalconfCacheIdentifier();
			/** @var $codeCache \TYPO3\CMS\Core\Cache\Frontend\PhpFrontend */
			$codeCache = self::getCacheManager()->getCache('cache_core');
			if ($codeCache->has($cacheIdentifier)) {
				$codeCache->requireOnce($cacheIdentifier);
			} else {
				self::loadSingleExtLocalconfFiles();
				self::createExtLocalconfCacheEntry();
			}
		} else {
			self::loadSingleExtLocalconfFiles();
		}
	}

	/**
	 * Execute ext_localconf.php files from extensions
	 *
	 * @return void
	 */
	static protected function loadSingleExtLocalconfFiles() {
		// This is the main array meant to be manipulated in the ext_localconf.php files
		// In general it is recommended to not rely on it to be globally defined in that
		// scope but to use $GLOBALS['TYPO3_CONF_VARS'] instead.
		// Nevertheless we define it here as global for backwards compatibility.
		global $TYPO3_CONF_VARS;
		foreach ($GLOBALS['TYPO3_LOADED_EXT'] as $_EXTKEY => $extensionInformation) {
			if ((is_array($extensionInformation) || $extensionInformation instanceof \ArrayAccess) && isset($extensionInformation['ext_localconf.php'])) {
				// $_EXTKEY and $_EXTCONF are available in ext_localconf.php
				// and are explicitly set in cached file as well
				$_EXTCONF = isset($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$_EXTKEY]) ? $GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$_EXTKEY] : NULL;
				require $extensionInformation['ext_localconf.php'];
			}
		}
	}

	/**
	 * Create cache entry for concatenated ext_localconf.php files
	 *
	 * @return void
	 */
	static protected function createExtLocalconfCacheEntry() {
		$extensionInformation = $GLOBALS['TYPO3_LOADED_EXT'];
		$phpCodeToCache = array();
		// Set same globals as in loadSingleExtLocalconfFiles()
		$phpCodeToCache[] = '/**';
		$phpCodeToCache[] = ' * Compiled ext_localconf.php cache file';
		$phpCodeToCache[] = ' */';
		$phpCodeToCache[] = '';
		$phpCodeToCache[] = 'global $TYPO3_CONF_VARS, $T3_SERVICES, $T3_VAR;';
		$phpCodeToCache[] = '';
		// Iterate through loaded extensions and add ext_localconf content
		foreach ($extensionInformation as $extensionKey => $extensionDetails) {
			if (isset($extensionDetails['ext_localconf.php']) && $extensionDetails['ext_localconf.php']) {
				// Include a header per extension to make the cache file more readable
				$phpCodeToCache[] = '/**';
				$phpCodeToCache[] = ' * Extension: ' . $extensionKey;
				$phpCodeToCache[] = ' * File: ' . $extensionDetails['ext_localconf.php'];
				$phpCodeToCache[] = ' */';
				$phpCodeToCache[] = '';
				// Set $_EXTKEY and $_EXTCONF for this extension
				$phpCodeToCache[] = '$_EXTKEY = \'' . $extensionKey . '\';';
				$phpCodeToCache[] = '$_EXTCONF = $GLOBALS[\'TYPO3_CONF_VARS\'][\'EXT\'][\'extConf\'][$_EXTKEY];';
				$phpCodeToCache[] = '';
				// Add ext_localconf.php content of extension
				$phpCodeToCache[] = trim(GeneralUtility::getUrl($extensionDetails['ext_localconf.php']));
				$phpCodeToCache[] = '';
				$phpCodeToCache[] = '';
			}
		}
		$phpCodeToCache = implode(LF, $phpCodeToCache);
		// Remove all start and ending php tags from content
		$phpCodeToCache = preg_replace('/<\\?php|\\?>/is', '', $phpCodeToCache);
		self::getCacheManager()->getCache('cache_core')->set(self::getExtLocalconfCacheIdentifier(), $phpCodeToCache);
	}

	/**
	 * Cache identifier of concatenated ext_localconf file
	 *
	 * @return string
	 */
	static protected function getExtLocalconfCacheIdentifier() {
		return 'ext_localconf_' . sha1((TYPO3_version . PATH_site . 'extLocalconf'));
	}

	/**
	 * Wrapper for buildBaseTcaFromSingleFiles handling caching.
	 *
	 * This builds 'base' TCA that is later overloaded by ext_tables.php.
	 *
	 * Use a cache file if exists and caching is allowed.
	 *
	 * This is an internal method. It is only used during bootstrap and
	 * extensions should not use it!
	 *
	 * @param bool $allowCaching Whether or not to load / create concatenated cache file
	 * @return void
	 * @access private
	 */
	static public function loadBaseTca($allowCaching = TRUE) {
		if ($allowCaching) {
			$cacheIdentifier = static::getBaseTcaCacheIdentifier();
			/** @var $codeCache \TYPO3\CMS\Core\Cache\Frontend\PhpFrontend */
			$codeCache = static::getCacheManager()->getCache('cache_core');
			if ($codeCache->has($cacheIdentifier)) {
				// substr is necessary, because the php frontend wraps php code around the cache value
				$GLOBALS['TCA'] = unserialize(substr($codeCache->get($cacheIdentifier), 6, -2));
			} else {
				static::buildBaseTcaFromSingleFiles();
				static::createBaseTcaCacheFile();
			}
		} else {
			static::buildBaseTcaFromSingleFiles();
		}
	}

	/**
	 * Find all Configuration/TCA/* files of extensions and create base TCA from it.
	 * The filename must be the table name in $GLOBALS['TCA'], and the content of
	 * the file should return an array with content of a specific table.
	 *
	 * @return void
	 * @see Extension core, cms, extensionmanager and others for examples.
	 */
	static protected function buildBaseTcaFromSingleFiles() {
		$GLOBALS['TCA'] = array();

		$activePackages = static::$packageManager->getActivePackages();

		// First load "full table" files from Configuration/TCA
		foreach ($activePackages as $package) {
			$tcaConfigurationDirectory = $package->getPackagePath() . 'Configuration/TCA';
			if (is_dir($tcaConfigurationDirectory)) {
				$files = scandir($tcaConfigurationDirectory);
				foreach ($files as $file) {
					if (
						is_file($tcaConfigurationDirectory . '/' . $file)
						&& ($file !== '.')
						&& ($file !== '..')
						&& (substr($file, -4, 4) === '.php')
					) {
						$tcaOfTable = require($tcaConfigurationDirectory . '/' . $file);
						if (is_array($tcaOfTable)) {
							// TCA table name is filename without .php suffix, eg 'sys_notes', not 'sys_notes.php'
							$tcaTableName = substr($file, 0, -4);
							$GLOBALS['TCA'][$tcaTableName] = $tcaOfTable;
						}
					}
				}
			}
		}

		// Apply category stuff
		CategoryRegistry::getInstance()->applyTcaForPreRegisteredTables();

		// Execute override files from Configuration/TCA/Overrides
		foreach ($activePackages as $package) {
			$tcaOverridesPathForPackage = $package->getPackagePath() . 'Configuration/TCA/Overrides';
			if (is_dir($tcaOverridesPathForPackage)) {
				$files = scandir($tcaOverridesPathForPackage);
				foreach ($files as $file) {
					if (
						is_file($tcaOverridesPathForPackage . '/' . $file)
						&& ($file !== '.')
						&& ($file !== '..')
						&& (substr($file, -4, 4) === '.php')
					) {
						require($tcaOverridesPathForPackage . '/' . $file);
					}
				}
			}
		}

		static::emitTcaIsBeingBuiltSignal($GLOBALS['TCA']);
	}

	/**
	 * Emits the signal and uses the result of slots for the final TCA
	 * This means, that *all* slots *must* return the complete TCA to
	 * be effective. If a slot calls methods that manipulate the global array,
	 * it needs to return the global array in the end. To be future proof,
	 * a slot should manipulate the signal argument only and return it
	 * after manipulation.
	 *
	 * @param array $tca
	 */
	static protected function emitTcaIsBeingBuiltSignal(array $tca) {
		list($tca) = static::getSignalSlotDispatcher()->dispatch(__CLASS__, 'tcaIsBeingBuilt', array($tca));
		$GLOBALS['TCA'] = $tca;
	}

	/**
	 * Cache base $GLOBALS['TCA'] to cache file to require the whole thing in one
	 * file for next access instead of cycling through all extensions again.
	 *
	 * @return void
	 */
	static protected function createBaseTcaCacheFile() {
		/** @var $codeCache \TYPO3\CMS\Core\Cache\Frontend\PhpFrontend */
		$codeCache = self::getCacheManager()->getCache('cache_core');
		$codeCache->set(static::getBaseTcaCacheIdentifier(), serialize($GLOBALS['TCA']));
	}

	/**
	 * Cache identifier of base TCA cache entry.
	 *
	 * @return string
	 */
	static protected function getBaseTcaCacheIdentifier() {
		return 'tca_base_' . sha1((TYPO3_version . PATH_site . 'tca'));
	}

	/**
	 * Execute all ext_tables.php files of loaded extensions.
	 * The method implements an optionally used caching mechanism that concatenates all
	 * ext_tables.php files in one file.
	 *
	 * This is an internal method. It is only used during bootstrap and
	 * extensions should not use it!
	 *
	 * @param bool $allowCaching Whether to load / create concatenated cache file
	 * @return void
	 * @access private
	 */
	static public function loadExtTables($allowCaching = TRUE) {
		if ($allowCaching && !self::$extTablesWasReadFromCacheOnce) {
			self::$extTablesWasReadFromCacheOnce = TRUE;
			$cacheIdentifier = self::getExtTablesCacheIdentifier();
			/** @var $codeCache \TYPO3\CMS\Core\Cache\Frontend\PhpFrontend */
			$codeCache = self::getCacheManager()->getCache('cache_core');
			if ($codeCache->has($cacheIdentifier)) {
				$codeCache->requireOnce($cacheIdentifier);
			} else {
				self::loadSingleExtTablesFiles();
				self::createExtTablesCacheEntry();
			}
		} else {
			self::loadSingleExtTablesFiles();
		}
	}

	/**
	 * Load ext_tables.php as single files
	 *
	 * @return void
	 */
	static protected function loadSingleExtTablesFiles() {
		// In general it is recommended to not rely on it to be globally defined in that
		// scope, but we can not prohibit this without breaking backwards compatibility
		global $T3_SERVICES, $T3_VAR, $TYPO3_CONF_VARS;
		global $TBE_MODULES, $TBE_MODULES_EXT, $TCA;
		global $PAGES_TYPES, $TBE_STYLES, $FILEICONS;
		global $_EXTKEY;
		// Load each ext_tables.php file of loaded extensions
		foreach ($GLOBALS['TYPO3_LOADED_EXT'] as $_EXTKEY => $extensionInformation) {
			if ((is_array($extensionInformation) || $extensionInformation instanceof \ArrayAccess) && $extensionInformation['ext_tables.php']) {
				// $_EXTKEY and $_EXTCONF are available in ext_tables.php
				// and are explicitly set in cached file as well
				$_EXTCONF = $GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$_EXTKEY];
				require $extensionInformation['ext_tables.php'];
				static::loadNewTcaColumnsConfigFiles();
			}
		}
	}

	/**
	 * Create concatenated ext_tables.php cache file
	 *
	 * @return void
	 */
	static protected function createExtTablesCacheEntry() {
		$extensionInformation = $GLOBALS['TYPO3_LOADED_EXT'];
		$phpCodeToCache = array();
		// Set same globals as in loadSingleExtTablesFiles()
		$phpCodeToCache[] = '/**';
		$phpCodeToCache[] = ' * Compiled ext_tables.php cache file';
		$phpCodeToCache[] = ' */';
		$phpCodeToCache[] = '';
		$phpCodeToCache[] = 'global $T3_SERVICES, $T3_VAR, $TYPO3_CONF_VARS;';
		$phpCodeToCache[] = 'global $TBE_MODULES, $TBE_MODULES_EXT, $TCA;';
		$phpCodeToCache[] = 'global $PAGES_TYPES, $TBE_STYLES, $FILEICONS;';
		$phpCodeToCache[] = 'global $_EXTKEY;';
		$phpCodeToCache[] = '';
		// Iterate through loaded extensions and add ext_tables content
		foreach ($extensionInformation as $extensionKey => $extensionDetails) {
			if (isset($extensionDetails['ext_tables.php']) && $extensionDetails['ext_tables.php']) {
				// Include a header per extension to make the cache file more readable
				$phpCodeToCache[] = '/**';
				$phpCodeToCache[] = ' * Extension: ' . $extensionKey;
				$phpCodeToCache[] = ' * File: ' . $extensionDetails['ext_tables.php'];
				$phpCodeToCache[] = ' */';
				$phpCodeToCache[] = '';
				// Set $_EXTKEY and $_EXTCONF for this extension
				$phpCodeToCache[] = '$_EXTKEY = \'' . $extensionKey . '\';';
				$phpCodeToCache[] = '$_EXTCONF = $GLOBALS[\'TYPO3_CONF_VARS\'][\'EXT\'][\'extConf\'][$_EXTKEY];';
				$phpCodeToCache[] = '';
				// Add ext_tables.php content of extension
				$phpCodeToCache[] = trim(GeneralUtility::getUrl($extensionDetails['ext_tables.php']));
				$phpCodeToCache[] = '';
				$phpCodeToCache[] = ExtensionManagementUtility::class . '::loadNewTcaColumnsConfigFiles();';
				$phpCodeToCache[] = '';
			}
		}
		$phpCodeToCache = implode(LF, $phpCodeToCache);
		// Remove all start and ending php tags from content
		$phpCodeToCache = preg_replace('/<\\?php|\\?>/is', '', $phpCodeToCache);
		self::getCacheManager()->getCache('cache_core')->set(self::getExtTablesCacheIdentifier(), $phpCodeToCache);
	}

	/**
	 * Loads "columns" of a $TCA table definition if extracted
	 * to a "dynamicConfigFile". This method is called after each
	 * single ext_tables.php files was included to immediately have
	 * the full $TCA ready for the next extension.
	 *
	 * $TCA[$tableName]['ctrl']['dynamicConfigFile'] must be the
	 * absolute path to a file.
	 *
	 * Be aware that 'dynamicConfigFile' is obsolete, and all TCA
	 * table definitions should be moved to Configuration/TCA/tablename.php
	 * to be fully loaded automatically.
	 *
	 * Example:
	 * dynamicConfigFile = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($_EXTKEY) . 'SysNote.php',
	 *
	 * @return void
	 * @throws \RuntimeException
	 * @internal Internal use ONLY. It is called by cache files and can not be protected. Do not call yourself!
	 */
	static public function loadNewTcaColumnsConfigFiles() {
		global $TCA;

		foreach ($TCA as $tableName => $_) {
			if (!isset($TCA[$tableName]['columns'])) {
				$columnsConfigFile = $TCA[$tableName]['ctrl']['dynamicConfigFile'];
				if ($columnsConfigFile) {
					if (GeneralUtility::isAbsPath($columnsConfigFile)) {
						include($columnsConfigFile);
					} else {
						throw new \RuntimeException(
							'Columns configuration file not found',
							1341151261
						);
					}
				}
			}
		}
	}

	/**
	 * Cache identifier for concatenated ext_tables.php files
	 *
	 * @return string
	 */
	static protected function getExtTablesCacheIdentifier() {
		return 'ext_tables_' . sha1((TYPO3_version . PATH_site . 'extTables'));
	}

	/**
	 * Remove cache files from php code cache, grouped by 'system'
	 *
	 * This removes the following cache entries:
	 * - autoloader cache registry
	 * - cache loaded extension array
	 * - ext_localconf concatenation
	 * - ext_tables concatenation
	 *
	 * This method is usually only used by extension that fiddle
	 * with the loaded extensions. An example is the extension
	 * manager and the install tool.
	 *
	 * @return void
	 */
	static public function removeCacheFiles() {
		self::getCacheManager()->flushCachesInGroup('system');
	}

	/**
	 * Gets an array of loaded extension keys
	 *
	 * @return array Loaded extensions
	 */
	static public function getLoadedExtensionListArray() {
		return array_keys(static::$packageManager->getActivePackages());
	}

	/**
	 * Loads given extension
	 *
	 * Warning: This method only works if the ugrade wizard to transform
	 * localconf.php to LocalConfiguration.php was already run
	 *
	 * @param string $extensionKey Extension key to load
	 * @return void
	 * @throws \RuntimeException
	 */
	static public function loadExtension($extensionKey) {
		if (static::$packageManager->isPackageActive($extensionKey)) {
			throw new \RuntimeException('Extension already loaded', 1342345486);
		}
		static::$packageManager->activatePackage($extensionKey);
	}

	/**
	 * Unloads given extension
	 *
	 * Warning: This method only works if the ugrade wizard to transform
	 * localconf.php to LocalConfiguration.php was already run
	 *
	 * @param string $extensionKey Extension key to remove
	 * @return void
	 * @throws \RuntimeException
	 */
	static public function unloadExtension($extensionKey) {
		if (!static::$packageManager->isPackageActive($extensionKey)) {
			throw new \RuntimeException('Extension not loaded', 1342345487);
		}
		static::$packageManager->deactivatePackage($extensionKey);
	}

	/**
	 * Makes a table categorizable by adding value into the category registry.
	 * FOR USE IN ext_localconf.php FILES or files in Configuration/TCA/Overrides/*.php Use the latter to benefit from TCA caching!
	 *
	 * @param string $extensionKey Extension key to be used
	 * @param string $tableName Name of the table to be categorized
	 * @param string $fieldName Name of the field to be used to store categories
	 * @param array $options Additional configuration options
	 * @see addTCAcolumns
	 * @see addToAllTCAtypes
	 */
	static public function makeCategorizable($extensionKey, $tableName, $fieldName = 'categories', array $options = array()) {
		// Update the category registry
		$result = CategoryRegistry::getInstance()->add($extensionKey, $tableName, $fieldName, $options);
		if ($result === FALSE) {
			$message = CategoryRegistry::class . ': no category registered for table "%s". Key was already registered.';
			/** @var $logger \TYPO3\CMS\Core\Log\Logger */
			$logger = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Log\LogManager::class)->getLogger(__CLASS__);
			$logger->warning(
				sprintf($message, $tableName)
			);
		}
	}

}
