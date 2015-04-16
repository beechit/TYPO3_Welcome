<?php
namespace TYPO3\CMS\Lang\Domain\Repository;

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
 * Language repository
 *
 * @author Sebastian Fischer <typo3@evoweb.de>
 * @author Kai Vogel <k.vogel@reply.de>
 */
class LanguageRepository {

	/**
	 * @var \TYPO3\CMS\Extbase\Object\ObjectManagerInterface
	 * @inject
	 */
	protected $objectManager;

	/**
	 * @var \TYPO3\CMS\Core\Localization\Locales
	 * @inject
	 */
	protected $locales;

	/**
	 * @var \TYPO3\CMS\Lang\Domain\Model\Language[]
	 */
	protected $selectedLocales = array();

	/**
	 * @var \TYPO3\CMS\Lang\Domain\Model\Language[]
	 */
	protected $languages = array();

	/**
	 * @var string
	 */
	protected $configurationPath = 'EXTCONF/lang';

	/**
	 * @var \TYPO3\CMS\Lang\Service\RegistryService
	 * @inject
	 */
	protected $registryService;

	/**
	 * Constructor of the language repository
	 */
	public function __construct() {
		$configurationManager = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Configuration\ConfigurationManager::class);
		try {
			$globalSettings = $configurationManager->getLocalConfigurationValueByPath($this->configurationPath);
			$this->selectedLocales = (array)$globalSettings['availableLanguages'];
		} catch (\Exception $e) {
			$configurationManager->setLocalConfigurationValueByPath(
				$this->configurationPath,
				array('availableLanguages' => array())
			);
		}
	}

	/**
	 * Returns all objects of this repository
	 *
	 * @return \TYPO3\CMS\Lang\Domain\Model\Language[] The language objects
	 */
	public function findAll() {
		if (!count($this->languages)) {
			$languages = $this->locales->getLanguages();
			array_shift($languages);
			foreach ($languages as $locale => $language) {
				$label = htmlspecialchars($GLOBALS['LANG']->sL('LLL:EXT:setup/mod/locallang.xlf:lang_' . $locale));
				if ($label === '') {
					$label = htmlspecialchars($language);
				}
				$this->languages[$locale] = $this->objectManager->get(
					\TYPO3\CMS\Lang\Domain\Model\Language::class,
					$locale,
					$label,
					in_array($locale, $this->selectedLocales),
					$this->registryService->get($locale)
				);
			}
			usort($this->languages, function($a, $b) {
				/** @var $a \TYPO3\CMS\Lang\Domain\Model\Language */
				/** @var $b \TYPO3\CMS\Lang\Domain\Model\Language */
				if ($a->getLabel() == $b->getLabel()) {
					return 0;
				}
				return $a->getLabel() < $b->getLabel() ? -1 : 1;
			});
		}
		return $this->languages;
	}

	/**
	 * Find selected languages
	 *
	 * @return \TYPO3\CMS\Lang\Domain\Model\Language[] The language objects
	 */
	public function findSelected() {
		$languages = $this->findAll();
		$result = array();
		foreach ($languages as $language) {
			if ($language->getSelected()) {
				$result[] = $language;
			}
		}
		return $result;
	}

	/**
	 * Update selected languages
	 *
	 * @param array $languages The languages
	 * @return array Update information
	 */
	public function updateSelectedLanguages($languages) {
			// Add possible dependencies for selected languages
		$dependencies = array();
		foreach ($languages as $language) {
			$dependencies = array_merge($dependencies, $this->locales->getLocaleDependencies($language));
		}
		if (count($dependencies)) {
			$languages = array_unique(array_merge($languages, $dependencies));
		}
		$dir = count($languages) - count($this->selectedLocales);
		$diff = $dir < 0 ? array_diff($this->selectedLocales, $languages) : array_diff($languages, $this->selectedLocales);
		GeneralUtility::makeInstance(\TYPO3\CMS\Core\Configuration\ConfigurationManager::class)->setLocalConfigurationValueByPath(
			$this->configurationPath,
			array('availableLanguages' => $languages)
		);
		return array(
			'success' => count($diff) > 0,
			'dir' => $dir,
			'diff' => array_values($diff),
			'languages' => $languages
		);
	}

	/**
	 * Add a language to list of selected languages
	 *
	 * @param string $locale The locale
	 * @return array Update information
	 */
	public function activateByLocale($locale) {
		$languages = $this->findAll();
		$locales = array();
		foreach ($languages as $language) {
			if ($language->getSelected() || $language->getLocale() === $locale) {
				$locales[] = $language->getLocale();
			}
		}
		return $this->updateSelectedLanguages($locales);
	}

	/**
	 * Remove a language from list of selected languages
	 *
	 * @param string $locale The locale
	 * @return array Update information
	 */
	public function deactivateByLocale($locale) {
		$languages = $this->findAll();
		$locales = array();
		foreach ($languages as $language) {
			if ($language->getSelected() && $language->getLocale() !== $locale) {
				$locales[] = $language->getLocale();
			}
		}
		return $this->updateSelectedLanguages($locales);
	}

}
