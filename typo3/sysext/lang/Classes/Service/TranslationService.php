<?php
namespace TYPO3\CMS\Lang\Service;

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
 * Translation service
 *
 * @author Kai Vogel <k.vogel@reply.de>
 */
class TranslationService implements \TYPO3\CMS\Core\SingletonInterface {

	/**
	 * Status codes for AJAX response
	 */
	const TRANSLATION_NOT_AVAILABLE = 0;
	const TRANSLATION_AVAILABLE = 1;
	const TRANSLATION_FAILED = 2;
	const TRANSLATION_OK = 3;
	const TRANSLATION_INVALID = 4;
	const TRANSLATION_UPDATED = 5;

	/**
	 * @var \TYPO3\CMS\Lang\Service\TerService
	 * @inject
	 */
	protected $terService;

	/**
	 * @var string
	 */
	protected $mirrorUrl = '';

	/**
	 * @param \TYPO3\CMS\Extensionmanager\Utility\Repository\Helper $helper The helper
	 * @return void
	 */
	public function injectRepositoryHelper(\TYPO3\CMS\Extensionmanager\Utility\Repository\Helper $helper) {
		$this->mirrorUrl = $helper->getMirrors()->getMirrorUrl();
	}

	/**
	 * Update translation for given extension
	 *
	 * @param string $extensionKey The extension key
	 * @param mixed $locales Comma separated list or array of locales
	 * @return array Update information
	 */
	public function updateTranslation($extensionKey, $locales) {
		if (is_string($locales)) {
			$locales = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', $locales);
		}
		$locales = array_flip((array) $locales);
		foreach ($locales as $locale => $key) {
			$state = static::TRANSLATION_INVALID;
			try {
				$state = $this->updateTranslationForExtension($extensionKey, $locale);
			} catch (\Exception $exception) {
				$error = $exception->getMessage();
			}
			$locales[$locale] = array(
				'state'  => $state,
				'error'  => $error,
			);
		}
		return $locales;
	}

	/**
	 * Update the translation for an extension
	 *
	 * @param string $extensionKey The extension key
	 * @param string $locale Locale to update
	 * @return int Translation state
	 */
	protected function updateTranslationForExtension($extensionKey, $locale) {
		if (empty($extensionKey) || empty($locale)) {
			return static::TRANSLATION_INVALID;
		}
		$state = static::TRANSLATION_FAILED;
		$updateResult = $this->terService->updateTranslation($extensionKey, $locale, $this->mirrorUrl);
		if ($updateResult === TRUE) {
			$state = static::TRANSLATION_UPDATED;
		}
		return $state;
	}

}
