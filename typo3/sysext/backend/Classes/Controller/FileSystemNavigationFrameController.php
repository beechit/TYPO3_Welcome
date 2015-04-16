<?php
namespace TYPO3\CMS\Backend\Controller;

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

use TYPO3\CMS\Backend\Utility\IconUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Main script class for rendering of the folder tree
 *
 * @author Kasper Skårhøj <kasperYYYY@typo3.com>
 */
class FileSystemNavigationFrameController {

	// Internal, dynamic:
	// Content accumulates in this variable.
	/**
	 * @var string
	 */
	public $content;

	/**
	 * @var \TYPO3\CMS\Filelist\FileListFolderTree $foldertree the folder tree object
	 */
	public $foldertree;

	/**
	 * document template object
	 *
	 * @var \TYPO3\CMS\Backend\Template\DocumentTemplate
	 */
	public $doc;

	/**
	 * @var string
	 */
	public $backPath;

	// Internal, static: GPvar:
	/**
	 * @var string
	 */
	public $currentSubScript;

	/**
	 * @var bool
	 */
	public $cMR;

	/**
	 * @var array
	 */
	protected $scopeData;

	/**
	 * Constructor
	 */
	public function __construct() {
		$GLOBALS['SOBE'] = $this;
		$GLOBALS['BACK_PATH'] = '';
		$this->init();
	}

	/**
	 * Initialiation of the script class
	 *
	 * @return void
	 */
	protected function init() {
		// Setting backPath
		$this->backPath = $GLOBALS['BACK_PATH'];
		// Setting GPvars:
		$this->currentSubScript = GeneralUtility::_GP('currentSubScript');
		$this->cMR = GeneralUtility::_GP('cMR');

		$scopeData = (string)GeneralUtility::_GP('scopeData');
		$scopeHash = (string)GeneralUtility::_GP('scopeHash');

		if (!empty($scopeData) && GeneralUtility::hmac($scopeData) === $scopeHash) {
			$this->scopeData = unserialize($scopeData);
		}

		// Create folder tree object:
		if (!empty($this->scopeData)) {
			$this->foldertree = GeneralUtility::makeInstance($this->scopeData['class']);
			$this->foldertree->thisScript = $this->scopeData['script'];
			$this->foldertree->ext_noTempRecyclerDirs = $this->scopeData['ext_noTempRecyclerDirs'];
			$GLOBALS['SOBE']->browser = new \stdClass();
			$GLOBALS['SOBE']->browser->mode = $this->scopeData['browser']['mode'];
			$GLOBALS['SOBE']->browser->act = $this->scopeData['browser']['act'];
		} else {
			$this->foldertree = GeneralUtility::makeInstance(\TYPO3\CMS\Filelist\FileListFolderTree::class);
			$this->foldertree->thisScript = 'alt_file_navframe.php';
		}

		$this->foldertree->ext_IconMode = $GLOBALS['BE_USER']->getTSConfigVal('options.folderTree.disableIconLinkToContextmenu');
	}

	/**
	 * initialization for the visual parts of the class
	 * Use template rendering only if this is a non-AJAX call
	 *
	 * @return void
	 */
	public function initPage() {
		// Setting highlight mode:
		$this->doHighlight = !$GLOBALS['BE_USER']->getTSConfigVal('options.pageTree.disableTitleHighlight');
		// Create template object:
		$this->doc = GeneralUtility::makeInstance(\TYPO3\CMS\Backend\Template\DocumentTemplate::class);
		$this->doc->backPath = $GLOBALS['BACK_PATH'];
		$this->doc->setModuleTemplate('EXT:backend/Resources/Private/Templates/alt_file_navframe.html');
		$this->doc->showFlashMessages = FALSE;
		// Adding javascript code for drag&drop and the filetree as well as the click menu code
		$dragDropCode = '
			Tree.ajaxID = "SC_alt_file_navframe::expandCollapse";
			Tree.registerDragDropHandlers()';
		if ($this->doHighlight) {
			$hlClass = $GLOBALS['BE_USER']->workspace === 0 ? 'active' : 'active active-ws wsver' . $GLOBALS['BE_USER']->workspace;
			$dragDropCode .= '
			Tree.highlightClass = "' . $hlClass . '";
			Tree.highlightActiveItem("", top.fsMod.navFrameHighlightedID["file"]);
			';
		}
		// Adding javascript for drag & drop activation and highlighting
		$this->doc->getDragDropCode('folders', $dragDropCode);
		$this->doc->getContextMenuCode();

		// Setting JavaScript for menu.
		$this->doc->JScode .= $this->doc->wrapScriptTags(($this->currentSubScript ? 'top.currentSubScript=unescape("' . rawurlencode($this->currentSubScript) . '");' : '') . '
		// Function, loading the list frame from navigation tree:
		function jumpTo(id, linkObj, highlightID, bank) {
			var theUrl = top.TS.PATH_typo3 + top.currentSubScript ;
			if (theUrl.indexOf("?") != -1) {
				theUrl += "&id=" + id
			} else {
				theUrl += "?id=" + id
			}
			top.fsMod.currentBank = bank;
			top.TYPO3.Backend.ContentContainer.setUrl(theUrl);

			' . ($this->doHighlight ? 'Tree.highlightActiveItem("file", highlightID + "_" + bank);' : '') . '
			if (linkObj) { linkObj.blur(); }
			return false;
		}
		' . ($this->cMR ? ' jumpTo(top.fsMod.recentIds[\'file\'],\'\');' : ''));
	}

	/**
	 * Main function, rendering the folder tree
	 *
	 * @return void
	 */
	public function main() {
		// Produce browse-tree:
		$tree = $this->foldertree->getBrowsableTree();
		// Outputting page tree:
		$this->content .= $tree;
		// Setting up the buttons and markers for docheader
		$docHeaderButtons = $this->getButtons();
		$markers = array(
			'CONTENT' => $this->content
		);
		$subparts = array();
		// Build the <body> for the module
		$this->content = $this->doc->startPage('TYPO3 Folder Tree');
		$this->content .= $this->doc->moduleBody($this->pageinfo, $docHeaderButtons, $markers, $subparts);
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
	 * Create the panel of buttons for submitting the form or otherwise perform operations.
	 *
	 * @return array All available buttons as an assoc. array
	 */
	protected function getButtons() {
		$buttons = array(
			'csh' => '',
			'refresh' => ''
		);
		// Refresh
		$buttons['refresh'] = '<a href="' . htmlspecialchars(GeneralUtility::getIndpEnv('REQUEST_URI')) . '">' . IconUtility::getSpriteIcon('actions-system-refresh') . '</a>';
		// CSH
		$buttons['csh'] = str_replace('typo3-csh-inline', 'typo3-csh-inline show-right', \TYPO3\CMS\Backend\Utility\BackendUtility::cshItem('xMOD_csh_corebe', 'filetree'));
		return $buttons;
	}

	/**********************************
	 *
	 * AJAX Calls
	 *
	 **********************************/
	/**
	 * Makes the AJAX call to expand or collapse the foldertree.
	 * Called by typo3/ajax.php
	 *
	 * @param array $params Additional parameters (not used here)
	 * @param \TYPO3\CMS\Core\Http\AjaxRequestHandler $ajaxObj The AjaxRequestHandler object of this request
	 * @return void
	 */
	public function ajaxExpandCollapse($params, $ajaxObj) {
		$this->init();
		$tree = $this->foldertree->getBrowsableTree();
		if ($this->foldertree->getAjaxStatus() === FALSE) {
			$ajaxObj->setError($tree);
		} else {
			$ajaxObj->addContent('tree', $tree);
		}
	}

}
