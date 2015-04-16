<?php
namespace TYPO3\CMS\Install\Controller\Action\Common;

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

use TYPO3\CMS\Install\Controller\Action;

/**
 * Show login form
 */
class LoginForm extends Action\AbstractAction {

	/**
	 * Executes the action
	 *
	 * @return string Rendered content
	 */
	protected function executeAction() {
		$this->view->assign('messages', $this->messages);
		return $this->view->render();
	}

}
