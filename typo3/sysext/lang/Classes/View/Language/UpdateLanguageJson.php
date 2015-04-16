<?php
namespace TYPO3\CMS\Lang\View\Language;

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
 * JSON view for "updateLanguage" action in "Language" controller
 *
 * @author Kai Vogel <k.vogel@reply.de>
 */
class UpdateLanguageJson extends \TYPO3\CMS\Lang\View\AbstractJsonView {

	/**
	 * Returns the response data
	 *
	 * @return array The response data
	 */
	protected function getReponseData() {
		return $this->variables['response'];
	}

}
