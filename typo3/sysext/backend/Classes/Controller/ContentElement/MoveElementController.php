<?php
namespace TYPO3\CMS\Backend\Controller\ContentElement;

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
 * Script Class for rendering the move-element wizard display
 *
 * @author Kasper Skårhøj <kasperYYYY@typo3.com>
 */
class MoveElementController {

	/**
	 * @var int
	 */
	public $sys_language = 0;

	/**
	 * @var int
	 */
	public $page_id;

	/**
	 * @var string
	 */
	public $table;

	/**
	 * @var string
	 */
	public $R_URI;

	/**
	 * @var int
	 */
	public $input_moveUid;

	/**
	 * @var int
	 */
	public $moveUid;

	/**
	 * @var int
	 */
	public $makeCopy;

	/**
	 * Document template object
	 *
	 * @var \TYPO3\CMS\Backend\Template\DocumentTemplate
	 */
	public $doc;

	/**
	 * Pages-select clause
	 *
	 * @var string
	 */
	public $perms_clause;

	/**
	 * Content for module accumulated here.
	 *
	 * @var string
	 */
	public $content;

	/**
	 * Constructor
	 */
	public function __construct() {
		$GLOBALS['LANG']->includeLLFile('EXT:lang/locallang_misc.xlf');
		$GLOBALS['SOBE'] = $this;
		$this->init();
	}

	/**
	 * Constructor, initializing internal variables.
	 *
	 * @return void
	 */
	public function init() {
		// Setting internal vars:
		$this->sys_language = (int)GeneralUtility::_GP('sys_language');
		$this->page_id = (int)GeneralUtility::_GP('uid');
		$this->table = GeneralUtility::_GP('table');
		$this->R_URI = GeneralUtility::sanitizeLocalUrl(GeneralUtility::_GP('returnUrl'));
		$this->input_moveUid = GeneralUtility::_GP('moveUid');
		$this->moveUid = $this->input_moveUid ? $this->input_moveUid : $this->page_id;
		$this->makeCopy = GeneralUtility::_GP('makeCopy');
		// Select-pages where clause for read-access:
		$this->perms_clause = $GLOBALS['BE_USER']->getPagePermsClause(1);
		// Starting the document template object:
		$this->doc = GeneralUtility::makeInstance(\TYPO3\CMS\Backend\Template\DocumentTemplate::class);
		$this->doc->backPath = $GLOBALS['BACK_PATH'];
		$this->doc->setModuleTemplate('EXT:backend/Resources/Private/Templates/move_el.html');
		$this->doc->JScode = '';
		// Starting document content (header):
		$this->content = '';
		$this->content .= $this->doc->header($GLOBALS['LANG']->getLL('movingElement'));
	}

	/**
	 * Creating the module output.
	 *
	 * @return void
	 */
	public function main() {
		if ($this->page_id) {
			// Get record for element:
			$elRow = BackendUtility::getRecordWSOL($this->table, $this->moveUid);
			// Headerline: Icon, record title:
			$hline = IconUtility::getSpriteIconForRecord($this->table, $elRow, array('id' => 'c-recIcon', 'title' => htmlspecialchars(BackendUtility::getRecordIconAltText($elRow, $this->table))));
			$hline .= BackendUtility::getRecordTitle($this->table, $elRow, TRUE);
			// Make-copy checkbox (clicking this will reload the page with the GET var makeCopy set differently):
			$hline .= $this->doc->spacer(5);
			$onClick = 'window.location.href=\'' . GeneralUtility::linkThisScript(array('makeCopy' => !$this->makeCopy)) . '\';';
			$hline .= $this->doc->spacer(5);
			$hline .= '<input type="hidden" name="makeCopy" value="0" />' . '<input type="checkbox" name="makeCopy" id="makeCopy" value="1"' . ($this->makeCopy ? ' checked="checked"' : '') . ' onclick="' . htmlspecialchars($onClick) . '" /> <label for="makeCopy" class="t3-label-valign-top">' . $GLOBALS['LANG']->getLL('makeCopy', 1) . '</label>';
			// Add the header-content to the module content:
			$this->content .= $this->doc->section('', $hline, FALSE, TRUE);
			$this->content .= $this->doc->spacer(20);
			// Reset variable to pick up the module content in:
			$code = '';
			// IF the table is "pages":
			if ((string)$this->table == 'pages') {
				// Get page record (if accessible):
				$pageinfo = BackendUtility::readPageAccess($this->page_id, $this->perms_clause);
				if (is_array($pageinfo) && $GLOBALS['BE_USER']->isInWebMount($pageinfo['pid'], $this->perms_clause)) {
					// Initialize the position map:
					$posMap = GeneralUtility::makeInstance(\TYPO3\CMS\Backend\Tree\View\PageMovingPagePositionMap::class);
					$posMap->moveOrCopy = $this->makeCopy ? 'copy' : 'move';
					// Print a "go-up" link IF there is a real parent page (and if the user has read-access to that page).
					if ($pageinfo['pid']) {
						$pidPageInfo = BackendUtility::readPageAccess($pageinfo['pid'], $this->perms_clause);
						if (is_array($pidPageInfo)) {
							if ($GLOBALS['BE_USER']->isInWebMount($pidPageInfo['pid'], $this->perms_clause)) {
								$code .= '<a href="' . htmlspecialchars(GeneralUtility::linkThisScript(array('uid' => (int)$pageinfo['pid'], 'moveUid' => $this->moveUid))) . '">' . IconUtility::getSpriteIcon('actions-view-go-up') . BackendUtility::getRecordTitle('pages', $pidPageInfo, TRUE) . '</a><br />';
							} else {
								$code .= IconUtility::getSpriteIconForRecord('pages', $pidPageInfo) . BackendUtility::getRecordTitle('pages', $pidPageInfo, TRUE) . '<br />';
							}
						}
					}
					// Create the position tree:
					$code .= $posMap->positionTree($this->page_id, $pageinfo, $this->perms_clause, $this->R_URI);
				}
			}
			// IF the table is "tt_content":
			if ((string)$this->table == 'tt_content') {
				// First, get the record:
				$tt_content_rec = BackendUtility::getRecord('tt_content', $this->moveUid);
				// ?
				if (!$this->input_moveUid) {
					$this->page_id = $tt_content_rec['pid'];
				}
				// Checking if the parent page is readable:
				$pageinfo = BackendUtility::readPageAccess($this->page_id, $this->perms_clause);
				if (is_array($pageinfo) && $GLOBALS['BE_USER']->isInWebMount($pageinfo['pid'], $this->perms_clause)) {
					// Initialize the position map:
					$posMap = GeneralUtility::makeInstance(\TYPO3\CMS\Backend\Tree\View\ContentMovingPagePositionMap::class);
					$posMap->moveOrCopy = $this->makeCopy ? 'copy' : 'move';
					$posMap->cur_sys_language = $this->sys_language;
					// Headerline for the parent page: Icon, record title:
					$hline = IconUtility::getSpriteIconForRecord('pages', $pageinfo, array('title' => htmlspecialchars(BackendUtility::getRecordIconAltText($pageinfo, 'pages'))));
					$hline .= BackendUtility::getRecordTitle('pages', $pageinfo, TRUE);
					// Load SHARED page-TSconfig settings and retrieve column list from there, if applicable:
					// SHARED page-TSconfig settings.
					$modTSconfig_SHARED = BackendUtility::getModTSconfig($this->page_id, 'mod.SHARED');
					$colPosArray = GeneralUtility::callUserFunction(\TYPO3\CMS\Backend\View\BackendLayoutView::class . '->getColPosListItemsParsed', $this->page_id, $this);
					$colPosIds = array();
					foreach ($colPosArray as $colPos) {
						$colPosIds[] = $colPos[1];
					}
					// Removing duplicates, if any
					$colPosList = implode(',', array_unique($colPosIds));
					// Adding parent page-header and the content element columns from position-map:
					$code = $hline . '<br />';
					$code .= $posMap->printContentElementColumns($this->page_id, $this->moveUid, $colPosList, 1, $this->R_URI);
					// Print a "go-up" link IF there is a real parent page (and if the user has read-access to that page).
					$code .= '<br /><br />';
					if ($pageinfo['pid']) {
						$pidPageInfo = BackendUtility::readPageAccess($pageinfo['pid'], $this->perms_clause);
						if (is_array($pidPageInfo)) {
							if ($GLOBALS['BE_USER']->isInWebMount($pidPageInfo['pid'], $this->perms_clause)) {
								$code .= '<a href="' . htmlspecialchars(GeneralUtility::linkThisScript(array(
									'uid' => (int)$pageinfo['pid'],
									'moveUid' => $this->moveUid
								))) . '">' . IconUtility::getSpriteIcon('actions-view-go-up') . BackendUtility::getRecordTitle('pages', $pidPageInfo, TRUE) . '</a><br />';
							} else {
								$code .= IconUtility::getSpriteIconForRecord('pages', $pidPageInfo) . BackendUtility::getRecordTitle('pages', $pidPageInfo, TRUE) . '<br />';
							}
						}
					}
					// Create the position tree (for pages):
					$code .= $posMap->positionTree($this->page_id, $pageinfo, $this->perms_clause, $this->R_URI);
				}
			}
			// Add the $code content as a new section to the module:
			$this->content .= $this->doc->section($GLOBALS['LANG']->getLL('selectPositionOfElement'), $code, FALSE, TRUE);
		}
		// Setting up the buttons and markers for docheader
		$docHeaderButtons = $this->getButtons();
		$markers['CSH'] = $docHeaderButtons['csh'];
		$markers['CONTENT'] = $this->content;
		// Build the <body> for the module
		$this->content = $this->doc->startPage($GLOBALS['LANG']->getLL('movingElement'));
		$this->content .= $this->doc->moduleBody($this->pageinfo, $docHeaderButtons, $markers);
		$this->content .= $this->doc->endPage();
		$this->content = $this->doc->insertStylesAndJS($this->content);
	}

	/**
	 * Print out the accumulated content:
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
			'back' => ''
		);
		if ($this->page_id) {
			if ((string)$this->table == 'pages') {
				$buttons['csh'] = BackendUtility::cshItem('xMOD_csh_corebe', 'move_el_pages');
			} elseif ((string)$this->table == 'tt_content') {
				$buttons['csh'] = BackendUtility::cshItem('xMOD_csh_corebe', 'move_el_cs');
			}
			if ($this->R_URI) {
				$buttons['back'] = '<a href="' . htmlspecialchars($this->R_URI) . '" class="typo3-goBack" title="' . $GLOBALS['LANG']->getLL('goBack', TRUE) . '">' . IconUtility::getSpriteIcon('actions-view-go-back') . '</a>';
			}
		}
		return $buttons;
	}

}
