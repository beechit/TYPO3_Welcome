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
 * Script Class for adding new items to a group/select field. Performs proper redirection as needed.
 *
 * @author Kasper Skårhøj <kasperYYYY@typo3.com>
 */
class AddController extends AbstractWizardController {

	/**
	 * Content accumulation for the module.
	 *
	 * @var string
	 */
	public $content;

	/**
	 * If set, the TCEmain class is loaded and used to add the returning ID to the parent record.
	 *
	 * @var int
	 */
	public $processDataFlag = 0;

	/**
	 * Create new record -pid (pos/neg). If blank, return immediately
	 *
	 * @var int
	 */
	public $pid;

	/**
	 * The parent table we are working on.
	 *
	 * @var string
	 */
	public $table;

	/**
	 * Loaded with the created id of a record when TCEforms (alt_doc.php) returns ...
	 *
	 * @var int
	 */
	public $id;

	/**
	 * Wizard parameters, coming from TCEforms linking to the wizard.
	 *
	 * @var array
	 */
	public $P;

	/**
	 * Information coming back from alt_doc.php script, telling what the table/id was of the newly created record.
	 *
	 * @var array
	 */
	public $returnEditConf;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->getLanguageService()->includeLLFile('EXT:lang/locallang_wizards.xlf');
		$GLOBALS['SOBE'] = $this;

		$this->init();
	}

	/**
	 * Initialization of the class.
	 *
	 * @return void
	 */
	protected function init() {
		// Init GPvars:
		$this->P = GeneralUtility::_GP('P');
		$this->returnEditConf = GeneralUtility::_GP('returnEditConf');
		// Get this record
		$origRow = BackendUtility::getRecord($this->P['table'], $this->P['uid']);
		// Set table:
		$this->table = $this->P['params']['table'];
		// Get TSconfig for it.
		$TSconfig = BackendUtility::getTCEFORM_TSconfig($this->P['table'], is_array($origRow) ? $origRow : array('pid' => $this->P['pid']));
		// Set [params][pid]
		if (substr($this->P['params']['pid'], 0, 3) === '###' && substr($this->P['params']['pid'], -3) === '###') {
			$keyword = substr($this->P['params']['pid'], 3, -3);
			if (strpos($keyword, 'PAGE_TSCONFIG_') === 0) {
				$this->pid = (int)$TSconfig[$this->P['field']][$keyword];
			} else {
				$this->pid = (int)$TSconfig['_' . $keyword];
			}
		} else {
			$this->pid = (int)$this->P['params']['pid'];
		}
		// Return if new record as parent (not possibly/allowed)
		if ($this->pid === '') {
			HttpUtility::redirect(GeneralUtility::sanitizeLocalUrl($this->P['returnUrl']));
		}
		// Else proceed:
		// If a new id has returned from a newly created record...
		if ($this->returnEditConf) {
			$eC = json_decode($this->returnEditConf, TRUE);
			if (is_array($eC[$this->table]) && \TYPO3\CMS\Core\Utility\MathUtility::canBeInterpretedAsInteger($this->P['uid'])) {
				// Getting id and cmd from returning editConf array.
				reset($eC[$this->table]);
				$this->id = (int)key($eC[$this->table]);
				$cmd = current($eC[$this->table]);
				// ... and if everything seems OK we will register some classes for inclusion and instruct the object to perform processing later.
				if ($this->P['params']['setValue'] && $cmd == 'edit' && $this->id && $this->P['table'] && $this->P['field'] && $this->P['uid']) {
					if ($LiveRec = BackendUtility::getLiveVersionOfRecord($this->table, $this->id, 'uid')) {
						$this->id = $LiveRec['uid'];
					}
					$this->processDataFlag = 1;
				}
			}
		}
	}

	/**
	 * Main function
	 * Will issue a location-header, redirecting either BACK or to a new alt_doc.php instance...
	 *
	 * @return void
	 */
	public function main() {
		if ($this->returnEditConf) {
			if ($this->processDataFlag) {
				// Preparing the data of the parent record...:
				$trData = GeneralUtility::makeInstance(\TYPO3\CMS\Backend\Form\DataPreprocessor::class);
				// 'new'
				$trData->fetchRecord($this->P['table'], $this->P['uid'], '');
				$current = reset($trData->regTableItems_data);
				// If that record was found (should absolutely be...), then init TCEmain and set, prepend or append the record
				if (is_array($current)) {
					$tce = GeneralUtility::makeInstance(\TYPO3\CMS\Core\DataHandling\DataHandler::class);
					$tce->stripslashes_values = 0;
					$data = array();
					$addEl = $this->table . '_' . $this->id;
					// Setting the new field data:
					// If the field is a flexform field, work with the XML structure instead:
					if ($this->P['flexFormPath']) {
						// Current value of flexform path:
						$currentFlexFormData = GeneralUtility::xml2array($current[$this->P['field']]);
						$flexToolObj = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Configuration\FlexForm\FlexFormTools::class);
						$curValueOfFlexform = $flexToolObj->getArrayValueByPath($this->P['flexFormPath'], $currentFlexFormData);
						$insertValue = '';
						switch ((string)$this->P['params']['setValue']) {
							case 'set':
								$insertValue = $addEl;
								break;
							case 'prepend':
								$insertValue = $curValueOfFlexform . ',' . $addEl;
								break;
							case 'append':
								$insertValue = $addEl . ',' . $curValueOfFlexform;
								break;
						}
						$insertValue = implode(',', GeneralUtility::trimExplode(',', $insertValue, TRUE));
						$data[$this->P['table']][$this->P['uid']][$this->P['field']] = array();
						$flexToolObj->setArrayValueByPath($this->P['flexFormPath'], $data[$this->P['table']][$this->P['uid']][$this->P['field']], $insertValue);
					} else {
						switch ((string)$this->P['params']['setValue']) {
							case 'set':
								$data[$this->P['table']][$this->P['uid']][$this->P['field']] = $addEl;
								break;
							case 'prepend':
								$data[$this->P['table']][$this->P['uid']][$this->P['field']] = $current[$this->P['field']] . ',' . $addEl;
								break;
							case 'append':
								$data[$this->P['table']][$this->P['uid']][$this->P['field']] = $addEl . ',' . $current[$this->P['field']];
								break;
						}
						$data[$this->P['table']][$this->P['uid']][$this->P['field']] = implode(',', GeneralUtility::trimExplode(',', $data[$this->P['table']][$this->P['uid']][$this->P['field']], TRUE));
					}
					// Submit the data:
					$tce->start($data, array());
					$tce->process_datamap();
				}
			}
			// Return to the parent alt_doc.php record editing session:
			HttpUtility::redirect(GeneralUtility::sanitizeLocalUrl($this->P['returnUrl']));
		} else {
			// Redirecting to alt_doc.php with instructions to create a new record
			// AND when closing to return back with information about that records ID etc.
			$redirectUrl = 'alt_doc.php?returnUrl=' . rawurlencode(GeneralUtility::getIndpEnv('REQUEST_URI')) . '&returnEditConf=1&edit[' . $this->P['params']['table'] . '][' . $this->pid . ']=new';
			HttpUtility::redirect($redirectUrl);
		}
	}

}
