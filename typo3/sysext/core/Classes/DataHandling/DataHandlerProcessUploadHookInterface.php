<?php
namespace TYPO3\CMS\Core\DataHandling;

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
 * Interface for classes which hook into TCEmain and do additional processing
 * after the upload of a file.
 *
 * @author Xavier Perseguers <typo3@perseguers.ch>
 */
interface DataHandlerProcessUploadHookInterface {

	/**
	 * Post-process a file upload.
	 *
	 * @param string $filename The uploaded file
	 * @param \TYPO3\CMS\Core\DataHandling\DataHandler $parentObject
	 * @return void
	 */
	public function processUpload_postProcessAction(&$filename, \TYPO3\CMS\Core\DataHandling\DataHandler $parentObject);

}
