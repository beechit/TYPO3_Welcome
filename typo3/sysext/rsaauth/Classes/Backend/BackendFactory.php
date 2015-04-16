<?php
namespace TYPO3\CMS\Rsaauth\Backend;

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
 * This class contains a factory for the RSA backends.
 *
 * @author Dmitry Dulepov <dmitry@typo3.org>
 */
class BackendFactory {

	/**
	 * A list of all available backends. Currently this list cannot be extended.
	 * This is for security reasons to avoid inserting some dummy backend to
	 * the list.
	 *
	 * @var array
	 */
	static protected $availableBackends = array(
		\TYPO3\CMS\Rsaauth\Backend\PhpBackend::class,
		\TYPO3\CMS\Rsaauth\Backend\CommandLineBackend::class
	);

	/**
	 * A flag that tells if the factory is initialized. This is to prevent
	 * continuous creation of backends in case if none of them is available.
	 *
	 * @var bool
	 */
	static protected $initialized = FALSE;

	/**
	 * A selected backend. This member is set in the getBackend() function. It
	 * will not be an abstract backend as shown below but a real class, which is
	 * derived from the \TYPO3\CMS\Rsaauth\Backend\AbstractBackend.
	 *
	 * @var \TYPO3\CMS\Rsaauth\Backend\AbstractBackend
	 */
	static protected $selectedBackend = NULL;

	/**
	 * Obtains a backend. This function will return a non-abstract class, which
	 * is derived from the \TYPO3\CMS\Rsaauth\Backend\AbstractBackend. Applications should
	 * not use any methods that are not declared in the \TYPO3\CMS\Rsaauth\Backend\AbstractBackend.
	 *
	 * @return \TYPO3\CMS\Rsaauth\Backend\AbstractBackend A backend
	 */
	static public function getBackend() {
		if (!self::$initialized) {
			// Backend does not exist yet. Create it.
			foreach (self::$availableBackends as $backend) {
				$backendObject = \TYPO3\CMS\Core\Utility\GeneralUtility::getUserObj($backend);
				// Check that it is derived from the proper base class
				if ($backendObject instanceof AbstractBackend) {
					/** @var $backendObject \TYPO3\CMS\Rsaauth\Backend\AbstractBackend */
					if ($backendObject->isAvailable()) {
						// The backend is available, save it and stop the loop
						self::$selectedBackend = $backendObject;
						self::$initialized = TRUE;
						break;
					}
					// Attempt to force destruction of the object
					unset($backendObject);
				}
			}
		}
		return self::$selectedBackend;
	}

}
