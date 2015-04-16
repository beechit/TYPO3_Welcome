<?php
namespace TYPO3\CMS\Backend\Controller\File;

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

/**
 * Script Class for display up to 10 upload fields
 *
 * @author Kasper Skårhøj <kasperYYYY@typo3.com>
 */
class FileUploadController {

	/**
	 * Document template object
	 *
	 * @var \TYPO3\CMS\Backend\Template\DocumentTemplate
	 */
	public $doc;

	/**
	 * Name of the filemount
	 *
	 * @var string
	 */
	public $title;

	/**
	 * Set with the target path inputted in &target
	 *
	 * @var string
	 */
	public $target;

	/**
	 * Return URL of list module.
	 *
	 * @var string
	 */
	public $returnUrl;

	/**
	 * Accumulating content
	 *
	 * @var string
	 */
	public $content;

	/**
	 * The folder object which is the target directory for the upload
	 *
	 * @var \TYPO3\CMS\Core\Resource\Folder $folderObject
	 */
	protected $folderObject;

	/**
	 * Constructor
	 */
	public function __construct() {
		$GLOBALS['SOBE'] = $this;
		$GLOBALS['LANG']->includeLLFile('EXT:lang/locallang_misc.xlf');
		$GLOBALS['BACK_PATH'] = '';

		$this->init();
	}

	/**
	 * Initialize
	 *
	 * @return void
	 */
	protected function init() {
		// Initialize GPvars:
		$this->target = GeneralUtility::_GP('target');
		$this->returnUrl = GeneralUtility::sanitizeLocalUrl(GeneralUtility::_GP('returnUrl'));
		if (!$this->returnUrl) {
			$this->returnUrl = GeneralUtility::getIndpEnv('TYPO3_SITE_URL') . TYPO3_mainDir . BackendUtility::getModuleUrl('file_list') . '&id=' . rawurlencode($this->target);
		}
		// Create the folder object
		if ($this->target) {
			$this->folderObject = \TYPO3\CMS\Core\Resource\ResourceFactory::getInstance()->retrieveFileOrFolderObject($this->target);
		}
		if ($this->folderObject->getStorage()->getUid() === 0) {
			throw new \TYPO3\CMS\Core\Resource\Exception\InsufficientFolderAccessPermissionsException('You are not allowed to access folders outside your storages', 1375889834);
		}

		// Cleaning and checking target directory
		if (!$this->folderObject) {
			$title = $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_mod_file_list.xlf:paramError', TRUE);
			$message = $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_mod_file_list.xlf:targetNoDir', TRUE);
			throw new \RuntimeException($title . ': ' . $message, 1294586843);
		}
		// Setting the title and the icon
		$icon = \TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIcon('apps-filetree-root');
		$this->title = $icon . htmlspecialchars($this->folderObject->getStorage()->getName()) . ': ' . htmlspecialchars($this->folderObject->getIdentifier());
		// Setting template object
		$this->doc = GeneralUtility::makeInstance(\TYPO3\CMS\Backend\Template\DocumentTemplate::class);
		$this->doc->setModuleTemplate('EXT:backend/Resources/Private/Templates/file_upload.html');
		$this->doc->backPath = $GLOBALS['BACK_PATH'];
		$this->doc->form = '<form action="' . htmlspecialchars(BackendUtility::getModuleUrl('tce_file')) . '" method="post" name="editform" enctype="' . $GLOBALS['TYPO3_CONF_VARS']['SYS']['form_enctype'] . '">';
	}

	/**
	 * Main function, rendering the upload file form fields
	 *
	 * @return void
	 */
	public function main() {
		// Make page header:
		$this->content = $this->doc->startPage($GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:file_upload.php.pagetitle'));
		$form = $this->renderUploadForm();
		$pageContent = $this->doc->header($GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:file_upload.php.pagetitle')) . $this->doc->section('', $form);
		// Header Buttons
		$docHeaderButtons = array(
			'csh' => BackendUtility::cshItem('xMOD_csh_corebe', 'file_upload'),
			'back' => ''
		);
		$markerArray = array(
			'CSH' => $docHeaderButtons['csh'],
			'FUNC_MENU' => BackendUtility::getFuncMenu($this->id, 'SET[function]', $this->MOD_SETTINGS['function'], $this->MOD_MENU['function']),
			'CONTENT' => $pageContent,
			'PATH' => $this->title
		);
		// Back
		if ($this->returnUrl) {
			$docHeaderButtons['back'] = '<a href="' . htmlspecialchars(\TYPO3\CMS\Core\Utility\GeneralUtility::linkThisUrl($this->returnUrl)) . '" class="typo3-goBack" title="' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:labels.goBack', TRUE) . '">' . \TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIcon('actions-view-go-back') . '</a>';
		}
		$this->content .= $this->doc->moduleBody(array(), $docHeaderButtons, $markerArray);
		$this->content .= $this->doc->endPage();
		$this->content = $this->doc->insertStylesAndJS($this->content);
	}

	/**
	 * This function renders the upload form
	 *
	 * @return 	string	the HTML form as a string, ready for outputting
	 */
	public function renderUploadForm() {
		// Make checkbox for "overwrite"
		$content = '
			<div id="c-override">
				<p><label for="overwriteExistingFiles"><input type="checkbox" class="checkbox" name="overwriteExistingFiles" id="overwriteExistingFiles" value="1" /> ' . $GLOBALS['LANG']->getLL('overwriteExistingFiles', 1) . '</label></p>
				<p>&nbsp;</p>
				<p>' . $GLOBALS['LANG']->getLL('uploadMultipleFilesInfo', TRUE) . '</p>
			</div>
			';
		// Produce the number of upload-fields needed:
		$content .= '
			<div id="c-upload">
		';
		// Adding 'size="50" ' for the sake of Mozilla!
		$content .= '
				<input type="file" multiple="true" name="upload_1[]" />
				<input type="hidden" name="file[upload][1][target]" value="' . htmlspecialchars($this->folderObject->getCombinedIdentifier()) . '" />
				<input type="hidden" name="file[upload][1][data]" value="1" /><br />
			';
		$content .= '
			</div>
		';
		// Submit button:
		$content .= '
			<div id="c-submit">
				<input type="hidden" name="redirect" value="' . $this->returnUrl . '" /><br />
				' . \TYPO3\CMS\Backend\Form\FormEngine::getHiddenTokenField('tceAction') . '
				<input class="btn btn-default" type="submit" value="' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:file_upload.php.submit', TRUE) . '" />
			</div>
		';
		return $content;
	}

	/**
	 * Outputting the accumulated content to screen
	 *
	 * @return void
	 */
	public function printContent() {
		echo $this->content;
	}

}
