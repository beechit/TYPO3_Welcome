<?php
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
 * Move element wizard:
 * Moving pages or content elements (tt_content) around in the system via a page tree navigation.
 *
 * @author Kasper Skårhøj <kasperYYYY@typo3.com>
 */
require __DIR__ . '/init.php';

\TYPO3\CMS\Core\Utility\GeneralUtility::deprecationLog(
	'Moving an element is moved to an own module. Please use BackendUtility::getModuleUrl(\'move_element\') to link to move_el.php. This script will be removed in TYPO3 CMS 8.'
);

$moveElementController = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Backend\Controller\ContentElement\MoveElementController::class);
$moveElementController->main();
$moveElementController->printContent();
