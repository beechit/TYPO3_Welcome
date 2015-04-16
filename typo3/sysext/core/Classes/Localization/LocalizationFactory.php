<?php
namespace TYPO3\CMS\Core\Localization;

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
 * Provides a language parser factory.
 *
 * @author Dominique Feyer <dfeyer@reelpeek.net>
 */
class LocalizationFactory implements \TYPO3\CMS\Core\SingletonInterface {

	/**
	 * @var \TYPO3\CMS\Core\Cache\Frontend\StringFrontend
	 */
	protected $cacheInstance;

	/**
	 * @var int
	 */
	protected $errorMode;

	/**
	 * @var \TYPO3\CMS\Core\Localization\LanguageStore
	 */
	public $store;

	/**
	 * Class constructor
	 */
	public function __construct() {
		$this->initialize();
	}

	/**
	 * Initialize
	 *
	 * @return void
	 */
	protected function initialize() {
		$this->store = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Localization\LanguageStore::class);
		$this->initializeCache();
	}

	/**
	 * Initialize cache instance to be ready to use
	 *
	 * @return void
	 */
	protected function initializeCache() {
		$this->cacheInstance = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Cache\CacheManager::class)->getCache('l10n');
	}

	/**
	 * Returns parsed data from a given file and language key.
	 *
	 * @param string $fileReference Input is a file-reference (see \TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName). That file is expected to be a supported locallang file format
	 * @param string $languageKey Language key
	 * @param string $charset Character set (option); if not set, determined by the language key
	 * @param int $errorMode Error mode (when file could not be found): 0 - syslog entry, 1 - do nothing, 2 - throw an exception$
	 * @param bool $isLocalizationOverride TRUE if $fileReference is a localization override
	 * @return array|boolean
	 */
	public function getParsedData($fileReference, $languageKey, $charset, $errorMode, $isLocalizationOverride = FALSE) {
		try {
			$hash = md5($fileReference . $languageKey . $charset);
			$this->errorMode = $errorMode;
			// Check if the default language is processed before processing other language
			if (!$this->store->hasData($fileReference, 'default') && $languageKey !== 'default') {
				$this->getParsedData($fileReference, 'default', $charset, $this->errorMode);
			}
			// If the content is parsed (local cache), use it
			if ($this->store->hasData($fileReference, $languageKey)) {
				return $this->store->getData($fileReference);
			}

			// If the content is in cache (system cache), use it
			$data = $this->cacheInstance->get($hash);
			if ($data !== FALSE) {
				$this->store->setData($fileReference, $languageKey, $data);
				return $this->store->getData($fileReference, $languageKey);
			}

			$this->store->setConfiguration($fileReference, $languageKey, $charset);
			/** @var $parser \TYPO3\CMS\Core\Localization\Parser\LocalizationParserInterface */
			$parser = $this->store->getParserInstance($fileReference);
			// Get parsed data
			$LOCAL_LANG = $parser->getParsedData($this->store->getAbsoluteFileReference($fileReference), $languageKey, $charset);
			// Override localization
			if (!$isLocalizationOverride && isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['locallangXMLOverride'])) {
				$this->localizationOverride($fileReference, $languageKey, $charset, $errorMode, $LOCAL_LANG);
			}
			// Save parsed data in cache
			$this->store->setData($fileReference, $languageKey, $LOCAL_LANG[$languageKey]);
			// Cache processed data
			$this->cacheInstance->set($hash, $this->store->getDataByLanguage($fileReference, $languageKey));
		} catch (\TYPO3\CMS\Core\Localization\Exception\FileNotFoundException $exception) {
			// Source localization file not found
			$this->store->setData($fileReference, $languageKey, array());
		}
		return $this->store->getData($fileReference);
	}

	/**
	 * Override localization file
	 *
	 * This method merges the content of the override file with the default file
	 *
	 * @param string $fileReference
	 * @param string $languageKey
	 * @param string $charset
	 * @param int $errorMode
	 * @param array $LOCAL_LANG
	 * @return void
	 */
	protected function localizationOverride($fileReference, $languageKey, $charset, $errorMode, array &$LOCAL_LANG) {
		$overrides = array();
		$fileReferenceWithoutExtension = $this->store->getFileReferenceWithoutExtension($fileReference);
		$locallangXMLOverride = $GLOBALS['TYPO3_CONF_VARS']['SYS']['locallangXMLOverride'];
		foreach ($this->store->getSupportedExtensions() as $extension) {
			if (isset($locallangXMLOverride[$languageKey][$fileReferenceWithoutExtension . '.' . $extension]) && is_array($locallangXMLOverride[$languageKey][$fileReferenceWithoutExtension . '.' . $extension])) {
				$overrides = array_merge($overrides, $locallangXMLOverride[$languageKey][$fileReferenceWithoutExtension . '.' . $extension]);
			} elseif (isset($locallangXMLOverride[$fileReferenceWithoutExtension . '.' . $extension]) && is_array($locallangXMLOverride[$fileReferenceWithoutExtension . '.' . $extension])) {
				$overrides = array_merge($overrides, $locallangXMLOverride[$fileReferenceWithoutExtension . '.' . $extension]);
			}
		}
		if (count($overrides) > 0) {
			foreach ($overrides as $overrideFile) {
				$languageOverrideFileName = \TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName($overrideFile);
				\TYPO3\CMS\Core\Utility\ArrayUtility::mergeRecursiveWithOverrule($LOCAL_LANG, $this->getParsedData($languageOverrideFileName, $languageKey, $charset, $errorMode, TRUE));
			}
		}
	}

}
