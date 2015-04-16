<?php
namespace TYPO3\CMS\Core\Type\File;

/**
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

use TYPO3\CMS\Core\Type\TypeInterface;

/**
 * A SPL FileInfo class providing general information related to a file.
 */
class FileInfo extends \SplFileInfo implements TypeInterface {

	/**
	 * Return the mime type of a file.
	 *
	 * @return string|FALSE
	 */
	public function getMimeType() {
		$mimeType = FALSE;
		if ($this->isFile()) {
			if (!function_exists('finfo_file')) {
				$fileInfo = new \finfo();
				$mimeType = $fileInfo->file($this->getPathname(), FILEINFO_MIME_TYPE);
			} elseif (function_exists('mime_content_type')) {
				$mimeType = mime_content_type($this->getPathname());
			}
		}

		if (isset($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS'][self::class]['mimeTypeGuessers'])
			&& is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS'][self::class]['mimeTypeGuesser'])
		) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS'][self::class]['mimeTypeGuesser'] as $mimeTypeGuesser) {
				$hookParameters = array(
					'mimeType' => &$mimeType
				);

				\TYPO3\CMS\Core\Utility\GeneralUtility::callUserFunction(
					$mimeTypeGuesser,
					$hookParameters,
					$this
				);
			}
		}

		return $mimeType;
	}
}
