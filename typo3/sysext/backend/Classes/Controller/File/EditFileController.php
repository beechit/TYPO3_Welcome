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
use TYPO3\CMS\Backend\Utility\IconUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Script Class for rendering the file editing screen
 *
 * @author Kasper Skårhøj <kasperYYYY@typo3.com>
 */
class EditFileController {

	/**
	 * Module content accumulated.
	 *
	 * @var string
	 */
	public $content;

	/**
	 * @var string
	 */
	public $title;

	/**
	 * Document template object
	 *
	 * @var \TYPO3\CMS\Backend\Template\DocumentTemplate
	 */
	public $doc;

	/**
	 * Original input target
	 *
	 * @var string
	 */
	public $origTarget;

	/**
	 * The original target, but validated.
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
	 * the file that is being edited on
	 *
	 * @var \TYPO3\CMS\Core\Resource\AbstractFile
	 */
	protected $fileObject;

	/**
	 * Constructor
	 */
	public function __construct() {
		$GLOBALS['SOBE'] = $this;
		$GLOBALS['BACK_PATH'] = '';

		$this->init();
	}

	/**
	 * Initialize script class
	 *
	 * @return void
	 */
	protected function init() {
		// Setting target, which must be a file reference to a file within the mounts.
		$this->target = ($this->origTarget = ($fileIdentifier = GeneralUtility::_GP('target')));
		$this->returnUrl = GeneralUtility::sanitizeLocalUrl(GeneralUtility::_GP('returnUrl'));
		// create the file object
		if ($fileIdentifier) {
			$this->fileObject = \TYPO3\CMS\Core\Resource\ResourceFactory::getInstance()->retrieveFileOrFolderObject($fileIdentifier);
		}
		// Cleaning and checking target directory
		if (!$this->fileObject) {
			$title = $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_mod_file_list.xlf:paramError', TRUE);
			$message = $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_mod_file_list.xlf:targetNoDir', TRUE);
			throw new \RuntimeException($title . ': ' . $message, 1294586841);
		}
		if ($this->fileObject->getStorage()->getUid() === 0) {
			throw new \TYPO3\CMS\Core\Resource\Exception\InsufficientFileAccessPermissionsException('You are not allowed to access files outside your storages', 1375889832);
		}

		// Setting the title and the icon
		$icon = IconUtility::getSpriteIcon('apps-filetree-root');
		$this->title = $icon . htmlspecialchars($this->fileObject->getStorage()->getName()) . ': ' . htmlspecialchars($this->fileObject->getIdentifier());

		// Setting template object
		$this->doc = GeneralUtility::makeInstance(\TYPO3\CMS\Backend\Template\DocumentTemplate::class);
		$this->doc->setModuleTemplate('EXT:backend/Resources/Private/Templates/file_edit.html');
		$this->doc->backPath = $GLOBALS['BACK_PATH'];
		$this->doc->JScode = $this->doc->wrapScriptTags('
			function backToList() {	//
				top.goToModule("file_list");
			}
		');
		$this->doc->form = '<form action="' . htmlspecialchars(BackendUtility::getModuleUrl('tce_file')) . '" method="post" name="editform">';
	}

	/**
	 * Main function, redering the actual content of the editing page
	 *
	 * @return void
	 */
	public function main() {
		$docHeaderButtons = $this->getButtons();
		$this->content = $this->doc->startPage($GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:file_edit.php.pagetitle'));
		// Hook	before compiling the output
		if (isset($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['typo3/file_edit.php']['preOutputProcessingHook'])) {
			$preOutputProcessingHook = &$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['typo3/file_edit.php']['preOutputProcessingHook'];
			if (is_array($preOutputProcessingHook)) {
				$hookParameters = array(
					'content' => &$this->content,
					'target' => &$this->target
				);
				foreach ($preOutputProcessingHook as $hookFunction) {
					GeneralUtility::callUserFunction($hookFunction, $hookParameters, $this);
				}
			}
		}
		$pageContent = $this->doc->header($GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:file_edit.php.pagetitle') . ' ' . htmlspecialchars($this->fileObject->getName()));
		$pageContent .= $this->doc->spacer(2);
		$code = '';
		$extList = $GLOBALS['TYPO3_CONF_VARS']['SYS']['textfile_ext'];
		try {
			if (!$extList || !GeneralUtility::inList($extList, $this->fileObject->getExtension())) {
				throw new \Exception('Files with that extension are not editable.');
			}
			// Read file content to edit:
			$fileContent = $this->fileObject->getContents();
			// Making the formfields
			$hValue = BackendUtility::getModuleUrl('file_edit', array(
				'target' => $this->origTarget,
				'returnUrl' => $this->returnUrl
			));
			// Edit textarea:
			$code .= '
				<div id="c-edit">
					<textarea rows="30" name="file[editfile][0][data]" wrap="off"' . $this->doc->formWidth(48, TRUE, 'width:98%;height:80%') . ' class="text-monospace enable-tab">' . GeneralUtility::formatForTextarea($fileContent) . '</textarea>
					<input type="hidden" name="file[editfile][0][target]" value="' . $this->fileObject->getUid() . '" />
					<input type="hidden" name="redirect" value="' . htmlspecialchars($hValue) . '" />
					' . \TYPO3\CMS\Backend\Form\FormEngine::getHiddenTokenField('tceAction') . '
				</div>
				<br />';
			// Make shortcut:
			if ($GLOBALS['BE_USER']->mayMakeShortcut()) {
				$docHeaderButtons['shortcut'] = $this->doc->makeShortcutIcon('target', '', 'file_edit', 1);
			} else {
				$docHeaderButtons['shortcut'] = '';
			}
		} catch (\Exception $e) {
			$code .= sprintf($GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:file_edit.php.coundNot'), $extList);
		}
		// Ending of section and outputting editing form:
		$pageContent .= $this->doc->sectionEnd();
		$pageContent .= $code;
		// Hook	after compiling the output
		if (isset($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['typo3/file_edit.php']['postOutputProcessingHook'])) {
			$postOutputProcessingHook = &$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['typo3/file_edit.php']['postOutputProcessingHook'];
			if (is_array($postOutputProcessingHook)) {
				$hookParameters = array(
					'pageContent' => &$pageContent,
					'target' => &$this->target
				);
				foreach ($postOutputProcessingHook as $hookFunction) {
					GeneralUtility::callUserFunction($hookFunction, $hookParameters, $this);
				}
			}
		}
		// Add the HTML as a section:
		$markerArray = array(
			'CSH' => $docHeaderButtons['csh'],
			'FUNC_MENU' => BackendUtility::getFuncMenu($this->id, 'SET[function]', $this->MOD_SETTINGS['function'], $this->MOD_MENU['function']),
			'BUTTONS' => $docHeaderButtons,
			'PATH' => $this->title,
			'CONTENT' => $pageContent
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

	/**
	 * Builds the buttons for the docheader and returns them as an array
	 *
	 * @return array
	 */
	public function getButtons() {
		$buttons = array();
		// CSH button
		$buttons['csh'] = BackendUtility::cshItem('xMOD_csh_corebe', 'file_edit');
		// Save button
		$theIcon = IconUtility::getSpriteIcon('actions-document-save');
		$buttons['SAVE'] = '<a href="#" onclick="document.editform.submit();" title="' . $GLOBALS['LANG']->makeEntities($GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:file_edit.php.submit', TRUE)) . '">' . $theIcon . '</a>';
		// Save and Close button
		$theIcon = IconUtility::getSpriteIcon('actions-document-save-close');
		$buttons['SAVE_CLOSE'] = '<a href="#" onclick="document.editform.redirect.value=\'' . htmlspecialchars($this->returnUrl) . '\'; document.editform.submit();" title="' . $GLOBALS['LANG']->makeEntities($GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:file_edit.php.saveAndClose', TRUE)) . '">' . $theIcon . '</a>';
		// Cancel button
		$theIcon = IconUtility::getSpriteIcon('actions-document-close');
		$buttons['CANCEL'] = '<a href="#" onclick="backToList(); return false;" title="' . $GLOBALS['LANG']->makeEntities($GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:labels.cancel', TRUE)) . '">' . $theIcon . '</a>';
		return $buttons;
	}

}
