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
 * Script Class for the rename-file form.
 *
 * @author Kasper Skårhøj <kasperYYYY@typo3.com>
 */
class RenameFileController {

	// Internal, static:
	/**
	 * Document template object
	 *
	 * @var \TYPO3\CMS\Backend\Template\DocumentTemplate
	 */
	public $doc;

	// Name of the filemount
	/**
	 * @var string
	 */
	public $title;

	// Internal, static (GPVar):
	// Set with the target path inputted in &target
	/**
	 * @var string
	 */
	public $target;

	/**
	 * The file or folder object that should be renamed
	 *
	 * @var \TYPO3\CMS\Core\Resource\ResourceInterface $fileOrFolderObject
	 */
	protected $fileOrFolderObject;

	// Return URL of list module.
	/**
	 * @var string
	 */
	public $returnUrl;

	// Internal, dynamic:
	// Accumulating content
	/**
	 * @var string
	 */
	public $content;

	/**
	 * Constructor
	 */
	public function __construct() {
		$GLOBALS['SOBE'] = $this;
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
		// Cleaning and checking target
		if ($this->target) {
			$this->fileOrFolderObject = \TYPO3\CMS\Core\Resource\ResourceFactory::getInstance()->retrieveFileOrFolderObject($this->target);
		}
		if (!$this->fileOrFolderObject) {
			$title = $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_mod_file_list.xlf:paramError', TRUE);
			$message = $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_mod_file_list.xlf:targetNoDir', TRUE);
			throw new \RuntimeException($title . ': ' . $message, 1294586844);
		}
		if ($this->fileOrFolderObject->getStorage()->getUid() === 0) {
			throw new \TYPO3\CMS\Core\Resource\Exception\InsufficientFileAccessPermissionsException('You are not allowed to access files outside your storages', 1375889840);
		}

		// If a folder should be renamed, AND the returnURL should go to the old directory name, the redirect is forced
		// so the redirect will NOT end in a error message
		// this case only happens if you select the folder itself in the foldertree and then use the clickmenu to
		// rename the folder
		if ($this->fileOrFolderObject instanceof \TYPO3\CMS\Core\Resource\Folder) {
			$parsedUrl = parse_url($this->returnUrl);
			$queryParts = GeneralUtility::explodeUrl2Array(urldecode($parsedUrl['query']));
			if ($queryParts['id'] === $this->fileOrFolderObject->getCombinedIdentifier()) {
				$this->returnUrl = str_replace(urlencode($queryParts['id']), urlencode($this->fileOrFolderObject->getStorage()->getRootLevelFolder()->getCombinedIdentifier()), $this->returnUrl);
			}
		}
		// Setting icon and title
		$icon = \TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIcon('apps-filetree-root');
		$this->title = $icon . htmlspecialchars($this->fileOrFolderObject->getStorage()->getName()) . ': ' . htmlspecialchars($this->fileOrFolderObject->getIdentifier());
		// Setting template object
		$this->doc = GeneralUtility::makeInstance(\TYPO3\CMS\Backend\Template\DocumentTemplate::class);
		$this->doc->setModuleTemplate('EXT:backend/Resources/Private/Templates/file_rename.html');
		$this->doc->backPath = $GLOBALS['BACK_PATH'];
		$this->doc->JScode = $this->doc->wrapScriptTags('
			function backToList() {	//
				top.goToModule("file_list");
			}
		');
	}

	/**
	 * Main function, rendering the content of the rename form
	 *
	 * @return void
	 */
	public function main() {
		// Make page header:
		$this->content = $this->doc->startPage($GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:file_rename.php.pagetitle'));
		$pageContent = $this->doc->header($GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:file_rename.php.pagetitle'));
		if ($this->fileOrFolderObject instanceof \TYPO3\CMS\Core\Resource\Folder) {
			$fileIdentifier = $this->fileOrFolderObject->getCombinedIdentifier();
		} else {
			$fileIdentifier = $this->fileOrFolderObject->getUid();
		}
		$pageContent .= '<form action="' . htmlspecialchars(BackendUtility::getModuleUrl('tce_file')) . '" method="post" name="editform" role="form">';
		// Making the formfields for renaming:
		$pageContent .= '

			<div class="form-group">
				<input class="form-control" type="text" name="file[rename][0][target]" value="' . htmlspecialchars($this->fileOrFolderObject->getName()) . '"' . $GLOBALS['TBE_TEMPLATE']->formWidth(40) . ' />
				<input type="hidden" name="file[rename][0][data]" value="' . htmlspecialchars($fileIdentifier) . '" />
			</div>
		';
		// Making submit button:
		$pageContent .= '
			<div class="form-group">
				<input class="btn btn-primary" type="submit" value="' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:file_rename.php.submit', TRUE) . '" />
				<input class="btn btn-danger" type="submit" value="' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:labels.cancel', TRUE) . '" onclick="backToList(); return false;" />
				<input type="hidden" name="redirect" value="' . htmlspecialchars($this->returnUrl) . '" />
				' . \TYPO3\CMS\Backend\Form\FormEngine::getHiddenTokenField('tceAction') . '
			</div>
		';
		$pageContent .= '</form>';
		$docHeaderButtons = array(
			'back' => ''
		);
		$docHeaderButtons['csh'] = BackendUtility::cshItem('xMOD_csh_corebe', 'file_rename');
		// Back
		if ($this->returnUrl) {
			$docHeaderButtons['back'] = '<a href="' . htmlspecialchars(\TYPO3\CMS\Core\Utility\GeneralUtility::linkThisUrl($this->returnUrl)) . '" class="typo3-goBack" title="' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:labels.goBack', TRUE) . '">' . \TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIcon('actions-view-go-back') . '</a>';
		}
		// Add the HTML as a section:
		$markerArray = array(
			'CSH' => $docHeaderButtons['csh'],
			'FUNC_MENU' => BackendUtility::getFuncMenu($this->id, 'SET[function]', $this->MOD_SETTINGS['function'], $this->MOD_MENU['function']),
			'CONTENT' => $pageContent,
			'PATH' => $this->title
		);
		$this->content .= $this->doc->moduleBody(array(), $docHeaderButtons, $markerArray);
		$this->content .= $this->doc->endPage();
		$this->content = $this->doc->insertStylesAndJS($this->content);
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
