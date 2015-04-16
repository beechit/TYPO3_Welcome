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
 * Javascript compatibility file for a breaking change to the
 * flashmessage javascript object
 * @deprecated since TYPO3 CMS 7, this file will be removed in TYPO3 CMS 9
 */

// map old Flashmessage API to the new one
if (!TYPO3.Flashmessage) {
	TYPO3.Flashmessage = {};
	TYPO3.Flashmessage.display = function(severity, title, message, duration) {
		if (console !== undefined) {
			console.log('TYPO3.Flashmessage.display is deprecated and will be removed with CMS 9, please use top.TYPO3.Flashmessage.display');
		}
		top.TYPO3.Flashmessage.display(severity, title, message, duration);
	}
}

// map old Severity object to the new one
if (!TYPO3.Severity) {
	TYPO3.Severity = top.TYPO3.Severity;
}
