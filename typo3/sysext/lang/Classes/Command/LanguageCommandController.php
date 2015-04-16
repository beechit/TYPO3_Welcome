<?php
namespace TYPO3\CMS\Lang\Command;

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

use TYPO3\CMS\Core\Package\PackageInterface;
use TYPO3\CMS\Core\Package\PackageManager;

/**
 * Language command controller updates translation packages
 */
class LanguageCommandController extends \TYPO3\CMS\Extbase\Mvc\Controller\CommandController {

	/**
	 * @var \TYPO3\CMS\Extbase\SignalSlot\Dispatcher
	 * @inject
	 */
	protected $signalSlotDispatcher;

	/**
	 * @var \TYPO3\CMS\Lang\Service\RegistryService
	 * @inject
	 */
	protected $registryService;

	/**
	 * Update language file for each extension
	 *
	 * @param string $localesToUpdate Comma separated list of locales that needs to be updated
	 * @return void
	 */
	public function updateCommand($localesToUpdate = '') {
		/** @var $translationService \TYPO3\CMS\Lang\Service\TranslationService */
		$translationService = $this->objectManager->get(\TYPO3\CMS\Lang\Service\TranslationService::class);
		/** @var $languageRepository \TYPO3\CMS\Lang\Domain\Repository\LanguageRepository */
		$languageRepository = $this->objectManager->get(\TYPO3\CMS\Lang\Domain\Repository\LanguageRepository::class);
		$locales = array();
		if (!empty($localesToUpdate)) {
			$locales = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', $localesToUpdate, TRUE);
		} else {
			$languages = $languageRepository->findSelected();
			foreach ($languages as $language) {
				/** @var $language \TYPO3\CMS\Lang\Domain\Model\Language */
				$locales[] = $language->getLocale();
			}
		}
		/** @var PackageManager $packageManager */
		$packageManager = $this->objectManager->get(\TYPO3\CMS\Core\Package\PackageManager::class);
		$this->emitPackagesMayHaveChangedSignal();
		$packages = $packageManager->getAvailablePackages();
		foreach ($locales as $locale) {
			/** @var PackageInterface $package */
			foreach ($packages as $package) {
				$extensionKey = $package->getPackageKey();
				$result = $translationService->updateTranslation($extensionKey, $locale);
				if (empty($result[$extensionKey][$locale]['error'])) {
					$this->registryService->set($locale, $GLOBALS['EXEC_TIME']);
				}
			}
		}
	}

	/**
	 * Emits packages may have changed signal
	 */
	protected function emitPackagesMayHaveChangedSignal() {
		$this->signalSlotDispatcher->dispatch('PackageManagement', 'packagesMayHaveChanged');
	}

}
