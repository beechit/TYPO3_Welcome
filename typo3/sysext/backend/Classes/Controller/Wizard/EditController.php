<?php
namespace TYPO3\CMS\Backend\Controller\Wizard;

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

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\HttpUtility;

/**
 * Script Class for redirecting a backend user to the editing form when an "Edit wizard" link was clicked in TCEforms somewhere
 *
 * @author Kasper Skårhøj <kasperYYYY@typo3.com>
 */
class EditController extends AbstractWizardController {

	/**
	 * Wizard parameters, coming from TCEforms linking to the wizard.
	 *
	 * @var array
	 */
	public $P;

	/**
	 * Boolean; if set, the window will be closed by JavaScript
	 *
	 * @var int
	 */
	public $doClose;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->getLanguageService()->includeLLFile('EXT:lang/locallang_wizards.xlf');
		$GLOBALS['SOBE'] = $this;

		$this->init();
	}

	/**
	 * Initialization of the script
	 *
	 * @return void
	 */
	protected function init() {
		$this->P = GeneralUtility::_GP('P');
		// Used for the return URL to alt_doc.php so that we can close the window.
		$this->doClose = GeneralUtility::_GP('doClose');
	}

	/**
	 * Main function
	 * Makes a header-location redirect to an edit form IF POSSIBLE from the passed data - otherwise the window will just close.
	 *
	 * @return void
	 */
	public function main() {
		if ($this->doClose) {
			$this->closeWindow();
		} else {
			// Initialize:
			$table = $this->P['table'];
			$field = $this->P['field'];
			$config = $GLOBALS['TCA'][$table]['columns'][$field]['config'];
			$fTable = $this->P['currentValue'] < 0 ? $config['neg_foreign_table'] : $config['foreign_table'];
			// Detecting the various allowed field type setups and acting accordingly.
			if (is_array($config) && $config['type'] == 'select' && !$config['MM'] && $config['maxitems'] <= 1 && \TYPO3\CMS\Core\Utility\MathUtility::canBeInterpretedAsInteger($this->P['currentValue']) && $this->P['currentValue'] && $fTable) {
				// SINGLE value:
				$redirectUrl = 'alt_doc.php?returnUrl=' . rawurlencode(BackendUtility::getModuleUrl('wizard_edit', array('doClose' => 1))) . '&edit[' . $fTable . '][' . $this->P['currentValue'] . ']=edit';
				HttpUtility::redirect($redirectUrl);
			} elseif (is_array($config) && $this->P['currentSelectedValues'] && ($config['type'] == 'select' && $config['foreign_table'] || $config['type'] == 'group' && $config['internal_type'] == 'db')) {
				// MULTIPLE VALUES:
				// Init settings:
				$allowedTables = $config['type'] == 'group' ? $config['allowed'] : $config['foreign_table'] . ',' . $config['neg_foreign_table'];
				$prependName = 1;
				$params = '';
				// Selecting selected values into an array:
				$dbAnalysis = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\RelationHandler::class);
				$dbAnalysis->start($this->P['currentSelectedValues'], $allowedTables);
				$value = $dbAnalysis->getValueArray($prependName);
				// Traverse that array and make parameters for alt_doc.php:
				foreach ($value as $rec) {
					$recTableUidParts = GeneralUtility::revExplode('_', $rec, 2);
					$params .= '&edit[' . $recTableUidParts[0] . '][' . $recTableUidParts[1] . ']=edit';
				}
				// Redirect to alt_doc.php:
				HttpUtility::redirect('alt_doc.php?returnUrl=' . rawurlencode(BackendUtility::getModuleUrl('wizard_edit', array('doClose' => 1))) . $params);
			} else {
				$this->closeWindow();
			}
		}
	}

	/**
	 * Printing a little JavaScript to close the open window.
	 *
	 * @return void
	 */
	public function closeWindow() {
		echo '<script language="javascript" type="text/javascript">close();</script>';
		die;
	}

}
