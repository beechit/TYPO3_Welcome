<?php
namespace TYPO3\CMS\Backend\ClickMenu;

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
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Backend\Clipboard\Clipboard;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Lang\LanguageService;

/**
 * Class for generating the click menu
 *
 * @author Kasper Skårhøj <kasperYYYY@typo3.com>
 * @internal
 */
class ClickMenu {

	/**
	 * Defines if the click menu is first level or second.
	 * Second means the click menu is triggered from another menu.
	 *
	 * @var int
	 */
	public $cmLevel = 0;

	/**
	 * Clipboard array (submitted by eg. pressing the paste button)
	 *
	 * @var bool
	 */
	public $CB;

	/**
	 * If set, the calling document should be in the listframe of a frameset.
	 *
	 * @var bool
	 */
	public $listFrame = FALSE;

	/**
	 * If set, the menu is about database records, not files. (set if part 2 [1] of the item-var is NOT blank)
	 *
	 * @var bool
	 */
	public $isDBmenu = FALSE;

	/**
	 * If TRUE, the "content" frame is always used for reference (when condensed mode is enabled)
	 *
	 * @var bool
	 */
	public $alwaysContentFrame = FALSE;

	/**
	 * Stores the parts of the input $item string, splitted by "|":
	 * [0] = table/file, [1] = uid/blank, [2] = flag: If set, listFrame,
	 * If "2" then "content frame" is forced  [3] = ("+" prefix = disable
	 * all by default, enable these. Default is to disable) Items key list
	 *
	 * @var array
	 */
	public $iParts = array();

	/**
	 * Contains list of keywords of items to disable in the menu
	 *
	 * @var array
	 */
	public $disabledItems = array();

	/**
	 * If TRUE, Show icons on the left.
	 *
	 * @var bool
	 */
	public $leftIcons = FALSE;

	/**
	 * Array of classes to be used for user processing of the menu content.
	 * This is for the API of adding items to the menu from outside.
	 *
	 * @var array
	 */
	public $extClassArray = array();

	/**
	 * Set, when edit icon is drawn.
	 *
	 * @var bool
	 */
	public $editPageIconSet = FALSE;

	/**
	 * Set to TRUE, if editing of the element is OK.
	 *
	 * @var bool
	 */
	public $editOK = FALSE;

	/**
	 * The current record
	 *
	 * @var array
	 */
	public $rec = array();

	/**
	 * Clipboard set from the outside
	 * Declared as public for now, should become protected
	 * soon-ish
	 * @var Clipboard;
	 */
	public $clipObj;

	/**
	 * The current page record
	 * @var array
	 */
	protected $pageinfo;

	/**
	 * Language Service property. Used to access localized labels
	 *
	 * @var LanguageService
	 */
	protected $languageService;

	/**
	 * @var BackendUserAuthentication
	 */
	protected $backendUser;

	/**
	 * @param LanguageService $languageService Language Service to inject
	 * @param BackendUserAuthentication $backendUser
	 */
	public function __construct(LanguageService $languageService = NULL, BackendUserAuthentication $backendUser = NULL) {
			$this->languageService = $languageService ?: $GLOBALS['LANG'];
			$this->backendUser = $backendUser ?: $GLOBALS['BE_USER'];
		}

	/**
	 * Initialize click menu
	 *
	 * @return string The clickmenu HTML content
	 */
	public function init() {
		$CMcontent = '';
		// Setting GPvars:
		$this->cmLevel = (int)GeneralUtility::_GP('cmLevel');
		$this->CB = GeneralUtility::_GP('CB');

		// Deal with Drag&Drop context menus
		if ((string)GeneralUtility::_GP('dragDrop') !== '') {
			return $this->printDragDropClickMenu(GeneralUtility::_GP('dragDrop'), GeneralUtility::_GP('srcId'), GeneralUtility::_GP('dstId'));
		}
		// Can be set differently as well
		$this->iParts[0] = GeneralUtility::_GP('table');
		$this->iParts[1] = GeneralUtility::_GP('uid');
		$this->iParts[2] = GeneralUtility::_GP('listFr');
		$this->iParts[3] = GeneralUtility::_GP('enDisItems');
		// Setting flags:
		if ($this->iParts[2]) {
			$this->listFrame = TRUE;
		}
		if ($this->iParts[2] == 2) {
			$this->alwaysContentFrame = TRUE;
		}
		if (isset($this->iParts[1]) && $this->iParts[1] !== '') {
			$this->isDBmenu = TRUE;
		}
		$TSkey = ($this->isDBmenu ? 'page' : 'folder') . ($this->listFrame ? 'List' : 'Tree');
		$this->disabledItems = GeneralUtility::trimExplode(',', $this->backendUser->getTSConfigVal('options.contextMenu.' . $TSkey . '.disableItems'), TRUE);
		$this->leftIcons = (bool)$this->backendUser->getTSConfigVal('options.contextMenu.options.leftIcons');
		// &cmLevel flag detected (2nd level menu)
		if (!$this->cmLevel) {
			// Make 1st level clickmenu:
			if ($this->isDBmenu) {
				$CMcontent = $this->printDBClickMenu($this->iParts[0], $this->iParts[1]);
			} else {
				$CMcontent = $this->printFileClickMenu($this->iParts[0]);
			}
		} else {
			// Make 2nd level clickmenu (only for DBmenus)
			if ($this->isDBmenu) {
				$CMcontent = $this->printNewDBLevel($this->iParts[0], $this->iParts[1]);
			}
		}
		// Return clickmenu content:
		return $CMcontent;
	}

	/***************************************
	 *
	 * DATABASE
	 *
	 ***************************************/
	/**
	 * Make 1st level clickmenu:
	 *
	 * @param string $table Table name
	 * @param int $uid UID for the current record.
	 * @return string HTML content
	 */
	public function printDBClickMenu($table, $uid) {
		$uid = (int)$uid;
		// Get record:
		$this->rec = BackendUtility::getRecordWSOL($table, $uid);
		$menuItems = array();
		$root = 0;
		$DBmount = FALSE;
		// Rootlevel
		if ($table === 'pages' && $uid === 0) {
			$root = 1;
		}
		// DB mount
		if ($table === 'pages' && in_array($uid, $this->backendUser->returnWebmounts())) {
			$DBmount = TRUE;
		}
		// Used to hide cut,copy icons for l10n-records
		$l10nOverlay = FALSE;
		// Should only be performed for overlay-records within the same table
		if (BackendUtility::isTableLocalizable($table) && !isset($GLOBALS['TCA'][$table]['ctrl']['transOrigPointerTable'])) {
			$l10nOverlay = (int)$this->rec[$GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField']] != 0;
		}
		// If record found (or root), go ahead and fill the $menuItems array which will contain data for the elements to render.
		if (is_array($this->rec) || $root) {
			// Get permissions
			$lCP = $this->backendUser->calcPerms(BackendUtility::getRecord('pages', $table === 'pages' ? $this->rec['uid'] : $this->rec['pid']));
			// View
			if (!in_array('view', $this->disabledItems)) {
				if ($table === 'pages') {
					$menuItems['view'] = $this->DB_view($uid);
				}
				if ($table === 'tt_content') {
					$ws_rec = BackendUtility::getRecordWSOL($table, $this->rec['uid']);
					$menuItems['view'] = $this->DB_view($ws_rec['pid']);
				}
			}
			// Edit:
			if (!$root && ($this->backendUser->isPSet($lCP, $table, 'edit') || $this->backendUser->isPSet($lCP, $table, 'editcontent'))) {
				if (!in_array('edit', $this->disabledItems)) {
					$menuItems['edit'] = $this->DB_edit($table, $uid);
				}
				$this->editOK = TRUE;
			}
			// New:
			if (!in_array('new', $this->disabledItems) && $this->backendUser->isPSet($lCP, $table, 'new')) {
				$menuItems['new'] = $this->DB_new($table, $uid);
			}
			// Info:
			if (!in_array('info', $this->disabledItems) && !$root) {
				$menuItems['info'] = $this->DB_info($table, $uid);
			}
			$menuItems['spacer1'] = 'spacer';
			// Copy:
			if (!in_array('copy', $this->disabledItems) && !$root && !$DBmount && !$l10nOverlay) {
				$menuItems['copy'] = $this->DB_copycut($table, $uid, 'copy');
			}
			// Cut:
			if (!in_array('cut', $this->disabledItems) && !$root && !$DBmount && !$l10nOverlay) {
				$menuItems['cut'] = $this->DB_copycut($table, $uid, 'cut');
			}
			// Paste:
			$elFromAllTables = count($this->clipObj->elFromTable(''));
			if (!in_array('paste', $this->disabledItems) && $elFromAllTables) {
				$selItem = $this->clipObj->getSelectedRecord();
				$elInfo = array(
					GeneralUtility::fixed_lgd_cs($selItem['_RECORD_TITLE'], $this->backendUser->uc['titleLen']),
					$root ? $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'] : GeneralUtility::fixed_lgd_cs(BackendUtility::getRecordTitle($table, $this->rec), $this->backendUser->uc['titleLen']),
					$this->clipObj->currentMode()
				);
				if ($table === 'pages' && $lCP & 8) {
					if ($elFromAllTables) {
						$menuItems['pasteinto'] = $this->DB_paste('', $uid, 'into', $elInfo);
					}
				}
				$elFromTable = count($this->clipObj->elFromTable($table));
				if (!$root && !$DBmount && $elFromTable && $GLOBALS['TCA'][$table]['ctrl']['sortby']) {
					$menuItems['pasteafter'] = $this->DB_paste($table, -$uid, 'after', $elInfo);
				}
			}
			$localItems = array();
			if (!$this->cmLevel && !in_array('moreoptions', $this->disabledItems, TRUE)) {
				// Creating menu items here:
				if ($this->editOK) {
					$localItems[] = 'spacer';
					$localItems['moreoptions'] = $this->linkItem(
						$this->label('more'),
						'',
						'TYPO3.ClickMenu.fetch(' . GeneralUtility::quoteJSvalue(GeneralUtility::linkThisScript() . '&cmLevel=1&subname=moreoptions') . ');return false;',
						FALSE,
						TRUE
					);
					$menuItemHideUnhideAllowed = FALSE;
					$hiddenField = '';
					// Check if column for disabled is defined
					if (isset($GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['disabled'])) {
						$hiddenField = $GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['disabled'];
						if (
							$hiddenField !== '' && !empty($GLOBALS['TCA'][$table]['columns'][$hiddenField]['exclude'])
							&& $this->backendUser->check('non_exclude_fields', $table . ':' . $hiddenField)
						) {
							$menuItemHideUnhideAllowed = TRUE;
						}
					}
					if ($menuItemHideUnhideAllowed && !in_array('hide', $this->disabledItems, TRUE)) {
						$localItems['hide'] = $this->DB_hideUnhide($table, $this->rec, $hiddenField);
					}
					$anyEnableColumnsFieldAllowed = FALSE;
					// Check if columns are defined
					if (isset($GLOBALS['TCA'][$table]['ctrl']['enablecolumns'])) {
						$columnsToCheck = $GLOBALS['TCA'][$table]['ctrl']['enablecolumns'];
						if ($table === 'pages' && !empty($columnsToCheck)) {
							$columnsToCheck[] = 'extendToSubpages';
						}
						foreach ($columnsToCheck as $currentColumn) {
							if (
								!empty($GLOBALS['TCA'][$table]['columns'][$currentColumn]['exclude'])
								&& $this->backendUser->check('non_exclude_fields', $table . ':' . $currentColumn)
							) {
								$anyEnableColumnsFieldAllowed = TRUE;
							}
						}
					}
					if ($anyEnableColumnsFieldAllowed && !in_array('edit_access', $this->disabledItems, TRUE)) {
						$localItems['edit_access'] = $this->DB_editAccess($table, $uid);
					}
					if ($table === 'pages' && $this->editPageIconSet && !in_array('edit_pageproperties', $this->disabledItems, TRUE)) {
						$localItems['edit_pageproperties'] = $this->DB_editPageProperties($uid);
					}
				}
				// Find delete element among the input menu items and insert the local items just before that:
				$c = 0;
				$deleteFound = FALSE;
				foreach ($menuItems as $key => $value) {
					$c++;
					if ($key === 'delete') {
						$deleteFound = TRUE;
						break;
					}
				}
				if ($deleteFound) {
					// .. subtract two... (delete item + its spacer element...)
					$c -= 2;
					// and insert the items just before the delete element.
					array_splice($menuItems, $c, 0, $localItems);
				} else {
					$menuItems = array_merge($menuItems, $localItems);
				}
			}

			// Delete:
			$elInfo = array(GeneralUtility::fixed_lgd_cs(BackendUtility::getRecordTitle($table, $this->rec), $this->backendUser->uc['titleLen']));
			if (!in_array('delete', $this->disabledItems) && !$root && !$DBmount && $this->backendUser->isPSet($lCP, $table, 'delete')) {
				$menuItems['spacer2'] = 'spacer';
				$menuItems['delete'] = $this->DB_delete($table, $uid, $elInfo);
			}
			if (!in_array('history', $this->disabledItems)) {
				$menuItems['history'] = $this->DB_history($table, $uid, $elInfo);
			}
		}
		// Adding external elements to the menuItems array
		$menuItems = $this->processingByExtClassArray($menuItems, $table, $uid);
		// Processing by external functions?
		$menuItems = $this->externalProcessingOfDBMenuItems($menuItems);
		if (!is_array($this->rec)) {
			$this->rec = array();
		}
		// Return the printed elements:
		return $this->printItems($menuItems, $root ? IconUtility::getSpriteIcon('apps-pagetree-root') . htmlspecialchars($GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename']) : IconUtility::getSpriteIconForRecord($table, $this->rec, array('title' => htmlspecialchars(BackendUtility::getRecordIconAltText($this->rec, $table)))) . BackendUtility::getRecordTitle($table, $this->rec, TRUE));
	}

	/**
	 * Make 2nd level clickmenu (only for DBmenus)
	 *
	 * @param string $table Table name
	 * @param int $uid UID for the current record.
	 * @return string HTML content
	 */
	public function printNewDBLevel($table, $uid) {
		$localItems = [];
		$uid = (int)$uid;
		// Setting internal record to the table/uid :
		$this->rec = BackendUtility::getRecordWSOL($table, $uid);
		$menuItems = array();
		$root = 0;
		// Rootlevel
		if ($table === 'pages' && $uid === 0) {
			$root = 1;
		}
		// If record was found, check permissions and get menu items.
		if (is_array($this->rec) || $root) {
			$lCP = $this->backendUser->calcPerms(BackendUtility::getRecord('pages', $table === 'pages' ? $this->rec['uid'] : $this->rec['pid']));
			// Edit:
			if (!$root && ($this->backendUser->isPSet($lCP, $table, 'edit') || $this->backendUser->isPSet($lCP, $table, 'editcontent'))) {
				$this->editOK = TRUE;
			}
			$menuItems = $this->processingByExtClassArray($menuItems, $table, $uid);
		}

		$subname = GeneralUtility::_GP('subname');
		if ($subname === 'moreoptions') {
			// If the page can be edited, then show this:
			if ($this->editOK) {
				if (($table === 'pages' || $table === 'tt_content') && !in_array('move_wizard', $this->disabledItems, TRUE)) {
					$localItems['move_wizard'] = $this->DB_moveWizard($table, $uid, $this->rec);
				}
				if (($table === 'pages' || $table === 'tt_content') && !in_array('new_wizard', $this->disabledItems, TRUE)) {
					$localItems['new_wizard'] = $this->DB_newWizard($table, $uid, $this->rec);
				}
				if ($table === 'pages' && !in_array('perms', $this->disabledItems, TRUE) && $this->backendUser->check('modules', 'system_BeuserTxPermission')) {
					$localItems['perms'] = $this->DB_perms($table, $uid, $this->rec);
				}
				if (!in_array('db_list', $this->disabledItems, TRUE) && $this->backendUser->check('modules', 'web_list')) {
					$localItems['db_list'] = $this->DB_db_list($table, $uid, $this->rec);
				}
			}
			// Temporary mount point item:
			if ($table === 'pages') {
				$localItems['temp_mount_point'] = $this->DB_tempMountPoint($uid);
			}
			// Merge the locally created items into the current menu items passed to this function.
			$menuItems = array_merge($menuItems, $localItems);
		}

		// Return the printed elements:
		if (!is_array($menuItems)) {
			$menuItems = array();
		}
		return $this->printItems($menuItems, $root ? IconUtility::getSpriteIcon('apps-pagetree-root') . htmlspecialchars($GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename']) : IconUtility::getSpriteIconForRecord($table, $this->rec, array('title' => htmlspecialchars(BackendUtility::getRecordIconAltText($this->rec, $table)))) . BackendUtility::getRecordTitle($table, $this->rec, TRUE));
	}

	/**
	 * Processing the $menuItems array (for extension classes) (DATABASE RECORDS)
	 *
	 * @param array $menuItems Array for manipulation.
	 * @return array Processed $menuItems array
	 */
	public function externalProcessingOfDBMenuItems($menuItems) {
		return $menuItems;
	}

	/**
	 * Processing the $menuItems array by external classes (typ. adding items)
	 *
	 * @param array $menuItems Array for manipulation.
	 * @param string $table Table name
	 * @param int $uid UID for the current record.
	 * @return array Processed $menuItems array
	 */
	public function processingByExtClassArray($menuItems, $table, $uid) {
		if (is_array($this->extClassArray)) {
			foreach ($this->extClassArray as $conf) {
				$obj = GeneralUtility::makeInstance($conf['name']);
				$menuItems = $obj->main($this, $menuItems, $table, $uid);
			}
		}
		return $menuItems;
	}

	/**
	 * Returning JavaScript for the onClick event linking to the input URL.
	 *
	 * @param string $url The URL relative to TYPO3_mainDir
	 * @param string $retUrl The return_url-parameter
	 * @param bool $hideCM If set, the "hideCM()" will be called
	 * @param string $overrideLoc If set, gives alternative location to load in (for example top frame or somewhere else)
	 * @return string JavaScript for an onClick event.
	 */
	public function urlRefForCM($url, $retUrl = '', $hideCM = TRUE, $overrideLoc = '') {
		$loc = 'top.content.list_frame';
		return ($overrideLoc ? 'var docRef=' . $overrideLoc : 'var docRef=(top.content.list_frame)?top.content.list_frame:' . $loc)
			. '; docRef.location.href=top.TS.PATH_typo3+\'' . $url . '\'' . ($retUrl ? '+\'&' . $retUrl . '=\'+top.rawurlencode('
			. $this->frameLocation('docRef.document') . '.pathname+' . $this->frameLocation('docRef.document') . '.search)' : '')
			. ';';
	}

	/**
	 * Adding CM element for Clipboard "copy" and "cut"
	 *
	 * @param string $table Table name
	 * @param int $uid UID for the current record.
	 * @param string $type Type: "copy" or "cut
	 * @return array Item array, element in $menuItems
	 * @internal
	 */
	public function DB_copycut($table, $uid, $type) {
		$isSel = '';
		if ($this->clipObj->current === 'normal') {
			$isSel = $this->clipObj->isSelected($table, $uid);
		}
		$addParam = array();
		if ($this->listFrame) {
			$addParam['reloadListFrame'] = $this->alwaysContentFrame ? 2 : 1;
		}
		return $this->linkItem($this->label($type), IconUtility::getSpriteIcon('actions-edit-' . $type . ($isSel === $type ? '-release' : '')), 'TYPO3.ClickMenu.fetch(' . GeneralUtility::quoteJSvalue($this->clipObj->selUrlDB($table, $uid, ($type === 'copy' ? 1 : 0), ($isSel == $type), $addParam)) . ');return false;');
	}

	/**
	 * Adding CM element for Clipboard "paste into"/"paste after"
	 * NOTICE: $table and $uid should follow the special syntax for paste, see clipboard-class :: pasteUrl();
	 *
	 * @param string $table Table name
	 * @param int $uid UID for the current record. NOTICE: Special syntax!
	 * @param string $type Type: "into" or "after
	 * @param array $elInfo Contains instructions about whether to copy or cut an element.
	 * @return array Item array, element in $menuItems
	 * @see \TYPO3\CMS\Backend\Clipboard\Clipboard::pasteUrl()
	 * @internal
	 */
	public function DB_paste($table, $uid, $type, $elInfo) {
		$loc = 'top.content.list_frame';
		if ($this->backendUser->jsConfirmation(2)) {
			$conf = $loc . ' && confirm(' . GeneralUtility::quoteJSvalue(sprintf($this->languageService->sL(('LLL:EXT:lang/locallang_core.xlf:mess.' . ($elInfo[2] === 'copy' ? 'copy' : 'move') . '_' . $type)), $elInfo[0], $elInfo[1])) . ')';
		} else {
			$conf = $loc;
		}
		$editOnClick = 'if(' . $conf . '){' . $loc . '.location.href=top.TS.PATH_typo3+\'' . $this->clipObj->pasteUrl($table, $uid, 0) . '&redirect=\'+top.rawurlencode(' . $this->frameLocation(($loc . '.document')) . '.pathname+' . $this->frameLocation(($loc . '.document')) . '.search);}';
		return $this->linkItem($this->label('paste' . $type), IconUtility::getSpriteIcon('actions-document-paste-' . $type), $editOnClick . 'return false;');
	}

	/**
	 * Adding CM element for Info
	 *
	 * @param string $table Table name
	 * @param int $uid UID for the current record.
	 * @return array Item array, element in $menuItems
	 * @internal
	 */
	public function DB_info($table, $uid) {
		return $this->linkItem($this->label('info'), IconUtility::getSpriteIcon('actions-document-info'), 'top.launchView(\'' . $table . '\', \'' . $uid . '\');');
	}

	/**
	 * Adding CM element for History
	 *
	 * @param string $table Table name
	 * @param int $uid UID for the current record.
	 * @return array Item array, element in $menuItems
	 * @internal
	 */
	public function DB_history($table, $uid) {
		$url = BackendUtility::getModuleUrl('record_history', array('element' => $table . ':' . $uid));
		return $this->linkItem($this->languageService->makeEntities($this->languageService->getLL('CM_history')), IconUtility::getSpriteIcon('actions-document-history-open'), $this->urlRefForCM($url, 'returnUrl'), 0);
	}

	/**
	 * Adding CM element for Permission setting
	 *
	 * @param string $table Table name
	 * @param int $uid UID for the current record.
	 * @param array $rec The "pages" record with "perms_*" fields inside.
	 * @return array Item array, element in $menuItems
	 * @internal
	 */
	public function DB_perms($table, $uid, $rec) {
		if (!ExtensionManagementUtility::isLoaded('beuser')) {
			return '';
		}

		$parameters = array(
			'id' => $uid,
		);

		if ($rec['perms_userid'] == $this->backendUser->user['uid'] || $this->backendUser->isAdmin()) {
			$parameters['return_id'] = $uid;
			$parameters['edit'] = '1';
		}

		$url = BackendUtility::getModuleUrl('system_BeuserTxPermission', $parameters);
		return $this->linkItem($this->languageService->makeEntities($this->languageService->getLL('CM_perms')), IconUtility::getSpriteIcon('status-status-locked'), $this->urlRefForCM($url), 0);
	}

	/**
	 * Adding CM element for DBlist
	 *
	 * @param string $table Table name
	 * @param int $uid UID for the current record.
	 * @param array $rec Record of the element (needs "pid" field if not pages-record)
	 * @return array Item array, element in $menuItems
	 * @internal
	 */
	public function DB_db_list($table, $uid, $rec) {
		$urlParams = array();
		$urlParams['id'] = $table === 'pages' ? $uid : $rec['pid'];
		$urlParams['table'] = $table === 'pages' ? '' : $table;
		$url = BackendUtility::getModuleUrl('web_list', $urlParams, '', TRUE);
		return $this->linkItem($this->languageService->makeEntities($this->languageService->getLL('CM_db_list')), IconUtility::getSpriteIcon('actions-system-list-open'), 'top.nextLoadModuleUrl=\'' . $url . '\';top.goToModule(\'web_list\', 1);', 0);
	}

	/**
	 * Adding CM element for Moving wizard
	 *
	 * @param string $table Table name
	 * @param int $uid UID for the current record.
	 * @param array $rec Record. Needed for tt-content elements which will have the sys_language_uid sent
	 * @return array Item array, element in $menuItems
	 * @internal
	 */
	public function DB_moveWizard($table, $uid, $rec) {
		// Hardcoded field for tt_content elements.
		$url = BackendUtility::getModuleUrl('move_element') . '&table=' . $table . '&uid=' . $uid;
		$url .= ($table === 'tt_content' ? '&sys_language_uid=' . (int)$rec['sys_language_uid'] : '');
		return $this->linkItem($this->languageService->makeEntities($this->languageService->getLL('CM_moveWizard' . ($table === 'pages' ? '_page' : ''))), IconUtility::getSpriteIcon('actions-' . ($table === 'pages' ? 'page' : 'document') . '-move'), $this->urlRefForCM($url, 'returnUrl'), 0);
	}

	/**
	 * Adding CM element for Create new wizard (either db_new.php or BackendUtility::getModuleUrl('new_content_element') or custom wizard)
	 *
	 * @param string $table Table name
	 * @param int $uid UID for the current record.
	 * @param array $rec Record.
	 * @return array Item array, element in $menuItems
	 * @internal
	 */
	public function DB_newWizard($table, $uid, $rec) {
		//  If mod.web_list.newContentWiz.overrideWithExtension is set, use that extension's create new content wizard instead:
		$tmpTSc = BackendUtility::getModTSconfig($this->pageinfo['uid'], 'mod.web_list');
		$tmpTSc = $tmpTSc['properties']['newContentWiz.']['overrideWithExtension'];

		$newContentWizScriptPath = ExtensionManagementUtility::isLoaded($tmpTSc) ? ExtensionManagementUtility::extRelPath($tmpTSc) . 'mod1/db_new_content_el.php?' : BackendUtility::getModuleUrl('new_content_element') . '&';
		$url = $table === 'pages' ? 'db_new.php?id=' . $uid . '&pagesOnly=1' : $newContentWizScriptPath . 'id=' . $rec['pid'] . '&sys_language_uid=' . (int)$rec['sys_language_uid'];
		return $this->linkItem($this->languageService->makeEntities($this->languageService->getLL('CM_newWizard')), IconUtility::getSpriteIcon('actions-' . ($table === 'pages' ? 'page' : 'document') . '-new'), $this->urlRefForCM($url, 'returnUrl'), 0);
	}

	/**
	 * Adding CM element for Editing of the access related fields of a table (disable, starttime, endtime, fe_groups)
	 *
	 * @param string $table Table name
	 * @param int $uid UID for the current record.
	 * @return array Item array, element in $menuItems
	 * @internal
	 */
	public function DB_editAccess($table, $uid) {
		$addParam = '&columnsOnly=' . rawurlencode((implode(',', $GLOBALS['TCA'][$table]['ctrl']['enablecolumns']) . ($table === 'pages' ? ',extendToSubpages' : '')));
		$url = 'alt_doc.php?edit[' . $table . '][' . $uid . ']=edit' . $addParam;
		return $this->linkItem($this->languageService->makeEntities($this->languageService->getLL('CM_editAccess')), IconUtility::getSpriteIcon('actions-document-edit-access'), $this->urlRefForCM($url, 'returnUrl'), 1);
	}

	/**
	 * Adding CM element for edit page properties
	 *
	 * @param int $uid page uid to edit (PID)
	 * @return array Item array, element in $menuItems
	 * @internal
	 */
	public function DB_editPageProperties($uid) {
		$url = 'alt_doc.php?edit[pages][' . $uid . ']=edit';
		return $this->linkItem($this->languageService->makeEntities($this->languageService->getLL('CM_editPageProperties')), IconUtility::getSpriteIcon('actions-page-open'), $this->urlRefForCM($url, 'returnUrl'), 1);
	}

	/**
	 * Adding CM element for regular editing of the element!
	 *
	 * @param string $table Table name
	 * @param int $uid UID for the current record.
	 * @return array Item array, element in $menuItems
	 * @internal
	 */
	public function DB_edit($table, $uid) {
		// If another module was specified, replace the default Page module with the new one
		$newPageModule = trim($this->backendUser->getTSConfigVal('options.overridePageModule'));
		$pageModule = BackendUtility::isModuleSetInTBE_MODULES($newPageModule) ? $newPageModule : 'web_layout';
		$editOnClick = '';
		$loc = 'top.content.list_frame';
		$addParam = '';
		$theIcon = 'actions-document-open';
		if ($this->iParts[0] === 'pages' && $this->iParts[1] && $this->backendUser->check('modules', $pageModule)) {
			$this->editPageIconSet = TRUE;
			if ($this->backendUser->uc['classicPageEditMode']) {
				$addParam = '&editRegularContentFromId=' . (int)$this->iParts[1];
			} else {
				$editOnClick = 'if(' . $loc . '){' . $loc . '.location.href=top.TS.PATH_typo3+\'alt_doc.php?returnUrl=\'+top.rawurlencode(' . $this->frameLocation(($loc . '.document')) . '.pathname+' . $this->frameLocation(($loc . '.document')) . '.search)+\'&edit[' . $table . '][' . $uid . ']=edit' . $addParam . '\';}';
			}
		}
		if (!$editOnClick) {
			$editOnClick = 'if(' . $loc . '){' . $loc . '.location.href=top.TS.PATH_typo3+\'alt_doc.php?returnUrl=\'+top.rawurlencode(' . $this->frameLocation(($loc . '.document')) . '.pathname+' . $this->frameLocation(($loc . '.document')) . '.search)+\'&edit[' . $table . '][' . $uid . ']=edit' . $addParam . '\';}';
		}
		return $this->linkItem($this->label('edit'), IconUtility::getSpriteIcon($theIcon), $editOnClick . ';');
	}

	/**
	 * Adding CM element for regular Create new element
	 *
	 * @param string $table Table name
	 * @param int $uid UID for the current record.
	 * @return array Item array, element in $menuItems
	 * @internal
	 */
	public function DB_new($table, $uid) {
		$loc = 'top.content.list_frame';
		$editOnClick = 'if(' . $loc . '){' . $loc . '.location.href=top.TS.PATH_typo3+\'' . ($this->listFrame ? 'alt_doc.php?returnUrl=\'+top.rawurlencode(' . $this->frameLocation(($loc . '.document')) . '.pathname+' . $this->frameLocation(($loc . '.document')) . '.search)+\'&edit[' . $table . '][-' . $uid . ']=new\'' : 'db_new.php?id=' . (int)$uid . '\'') . ';}';
		return $this->linkItem($this->label('new'), IconUtility::getSpriteIcon('actions-' . ($table === 'pages' ? 'page' : 'document') . '-new'), $editOnClick . ';');
	}

	/**
	 * Adding CM element for Delete
	 *
	 * @param string $table Table name
	 * @param int $uid UID for the current record.
	 * @param array $elInfo Label for including in the confirmation message, EXT:lang/locallang_core.xlf:mess.delete
	 * @return array Item array, element in $menuItems
	 * @internal
	 */
	public function DB_delete($table, $uid, $elInfo) {
		$loc = 'top.content.list_frame';
		if ($this->backendUser->jsConfirmation(4)) {
			$conf = 'confirm(' . GeneralUtility::quoteJSvalue((sprintf($this->languageService->sL('LLL:EXT:lang/locallang_core.xlf:mess.delete'), $elInfo[0]) . BackendUtility::referenceCount($table, $uid, ' (There are %s reference(s) to this record!)') . BackendUtility::translationCount($table, $uid, (' ' . $this->languageService->sL('LLL:EXT:lang/locallang_core.xlf:labels.translationsOfRecord'))))) . ')';
		} else {
			$conf = '1==1';
		}
		$editOnClick = 'if(' . $loc . ' && ' . $conf . ' ){' . $loc . '.location.href=top.TS.PATH_typo3+' .
			GeneralUtility::quoteJSvalue(BackendUtility::getModuleUrl('tce_db') . '&redirect=') . '+top.rawurlencode(' .
			$this->frameLocation($loc . '.document') . '.pathname+' . $this->frameLocation(($loc . '.document')) . '.search)+' .
			GeneralUtility::quoteJSvalue(
				'&cmd[' . $table . '][' . $uid . '][delete]=1&prErr=1&vC=' . $this->backendUser->veriCode() . BackendUtility::getUrlToken('tceAction')
			) . ';};';
		if ($table === 'pages') {
			$editOnClick .= 'top.nav.refresh.defer(500, top.nav);';
		}
		return $this->linkItem($this->label('delete'), IconUtility::getSpriteIcon('actions-edit-delete'), $editOnClick . 'return false;');
	}

	/**
	 * Adding CM element for View Page
	 *
	 * @param int $id Page uid (PID)
	 * @param string $anchor Anchor, if any
	 * @return array Item array, element in $menuItems
	 * @internal
	 */
	public function DB_view($id, $anchor = '') {
		return $this->linkItem($this->label('view'), IconUtility::getSpriteIcon('actions-document-view'), BackendUtility::viewOnClick($id, '', NULL, $anchor) . ';');
	}

	/**
	 * Adding element for setting temporary mount point.
	 *
	 * @param int $page_id Page uid (PID)
	 * @return array Item array, element in $menuItems
	 * @internal
	 */
	public function DB_tempMountPoint($page_id) {
		return $this->linkItem($this->label('tempMountPoint'), IconUtility::getSpriteIcon('apps-pagetree-page-mountpoint'), 'if (top.content.nav_frame) {
				var node = top.TYPO3.Backend.NavigationContainer.PageTree.getSelected();
				if (node === null) {
					return false;
				}

				var useNode = {
					attributes: {
						nodeData: {
							id: ' . (int)$page_id . '
						}
					}
				};

				node.ownerTree.commandProvider.mountAsTreeRoot(useNode, node.ownerTree);
			}
			');
	}

	/**
	 * Adding CM element for hide/unhide of the input record
	 *
	 * @param string $table Table name
	 * @param array $rec Record array
	 * @param string $hideField Name of the hide field
	 * @return array Item array, element in $menuItems
	 * @internal
	 */
	public function DB_hideUnhide($table, $rec, $hideField) {
		return $this->DB_changeFlag($table, $rec, $hideField, $this->label(($rec[$hideField] ? 'un' : '') . 'hide'), 'hide');
	}

	/**
	 * Adding CM element for a flag field of the input record
	 *
	 * @param string $table Table name
	 * @param array $rec Record array
	 * @param string $flagField Name of the flag field
	 * @param string $title Menu item Title
	 * @return array Item array, element in $menuItems
	 */
	public function DB_changeFlag($table, $rec, $flagField, $title) {
		$uid = $rec['_ORIG_uid'] ?: $rec['uid'];
		$loc = 'top.content.list_frame';
		$editOnClick = 'if(' . $loc . '){' . $loc . '.location.href=top.TS.PATH_typo3+' .
			GeneralUtility::quoteJSvalue(BackendUtility::getModuleUrl('tce_db') . '&redirect=') . '+top.rawurlencode(' .
			$this->frameLocation($loc . '.document') . '.pathname+' . $this->frameLocation(($loc . '.document')) . '.search)+' .
			GeneralUtility::quoteJSvalue(
				'&data[' . $table . '][' . $uid . '][' . $flagField . ']=' . ($rec[$flagField] ? 0 : 1) . '&prErr=1&vC=' . $this->backendUser->veriCode() . BackendUtility::getUrlToken('tceAction')
			) . ';};';
		if ($table === 'pages') {
			$editOnClick .= 'top.nav.refresh.defer(500, top.nav);';
		}
		return $this->linkItem($title, IconUtility::getSpriteIcon('actions-edit-' . ($rec[$flagField] ? 'un' : '') . 'hide'), $editOnClick . 'return false;', 1);
	}

	/***************************************
	 *
	 * FILE
	 *
	 ***************************************/
	/**
	 * Make 1st level clickmenu:
	 *
	 * @param string $combinedIdentifier The combined identifier
	 * @return string HTML content
	 * @see \TYPO3\CMS\Core\Resource\ResourceFactory::retrieveFileOrFolderObject()
	 */
	public function printFileClickMenu($combinedIdentifier) {
		$icon = '';
		$identifier = '';
		$menuItems = array();
		$combinedIdentifier = rawurldecode($combinedIdentifier);
		$fileObject = ResourceFactory::getInstance()
				->retrieveFileOrFolderObject($combinedIdentifier);
		if ($fileObject) {
			$folder = FALSE;
			$isStorageRoot = FALSE;
			$isOnline = TRUE;
			$userMayViewStorage = FALSE;
			$userMayEditStorage = FALSE;
			$identifier = $fileObject->getCombinedIdentifier();
			if ($fileObject instanceof Folder) {
				$icon = IconUtility::getSpriteIconForResource($fileObject, array(
					'class' => 'absmiddle',
					'title' => htmlspecialchars($fileObject->getName())
				));
				$folder = TRUE;
				if ($fileObject->getIdentifier() === $fileObject->getStorage()->getRootLevelFolder()->getIdentifier()) {
					$isStorageRoot = TRUE;
					if ($this->backendUser->check('tables_select', 'sys_file_storage')) {
						$userMayViewStorage = TRUE;
					}
					if ($this->backendUser->check('tables_modify', 'sys_file_storage')) {
						$userMayEditStorage = TRUE;
					}
				}
				if (!$fileObject->getStorage()->isOnline()) {
					$isOnline = FALSE;
				}
			} else {
				$icon = IconUtility::getSpriteIconForResource($fileObject, array(
					'class' => 'absmiddle',
					'title' => htmlspecialchars($fileObject->getName() . ' (' . GeneralUtility::formatSize($fileObject->getSize()) . ')')
				));
			}
			// Hide
			if (!in_array('hide', $this->disabledItems) && $isStorageRoot && $userMayEditStorage) {
				$record = BackendUtility::getRecord('sys_file_storage', $fileObject->getStorage()->getUid());
				$menuItems['hide'] = $this->DB_changeFlag(
					'sys_file_storage',
					$record,
					'is_online',
					$this->label($record['is_online'] ? 'offline' : 'online'),
					'hide'
				);
			}
			// Edit
			if (!in_array('edit', $this->disabledItems) && $fileObject->checkActionPermission('write')) {
				if (!$folder && !$isStorageRoot && $fileObject->isIndexed() && $this->backendUser->check('tables_modify', 'sys_file_metadata')) {
					$metaData = $fileObject->_getMetaData();
					$menuItems['edit2'] = $this->DB_edit('sys_file_metadata', $metaData['uid']);
				}
				if (!$folder && GeneralUtility::inList($GLOBALS['TYPO3_CONF_VARS']['SYS']['textfile_ext'], $fileObject->getExtension()) && $fileObject->checkActionPermission('write')) {
					$menuItems['edit'] = $this->FILE_launch($identifier, 'file_edit', 'editcontent', 'actions-page-open');
				} elseif ($isStorageRoot && $userMayEditStorage) {
					$menuItems['edit'] = $this->DB_edit('sys_file_storage', $fileObject->getStorage()->getUid());
				}
			}
			// Rename
			if (!in_array('rename', $this->disabledItems) && !$isStorageRoot && $fileObject->checkActionPermission('rename')) {
				$menuItems['rename'] = $this->FILE_launch($identifier, 'file_rename', 'rename', 'actions-edit-rename');
			}
			// Upload
			if (!in_array('upload', $this->disabledItems) && $folder && $isOnline && $fileObject->checkActionPermission('write')) {
				$menuItems['upload'] = $this->FILE_launch($identifier, 'file_upload', 'upload', 'actions-edit-upload');
			}
			// New
			if (!in_array('new', $this->disabledItems) && $folder && $isOnline && $fileObject->checkActionPermission('write')) {
				$menuItems['new'] = $this->FILE_launch($identifier, 'file_newfolder', 'new', 'actions-document-new');
			}
			// Info
			if (!in_array('info', $this->disabledItems) && $fileObject->checkActionPermission('read')) {
				if ($isStorageRoot && $userMayViewStorage) {
					$menuItems['info'] = $this->DB_info('sys_file_storage', $fileObject->getStorage()->getUid());
				} elseif (!$folder) {
					$menuItems['info'] = $this->fileInfo($identifier);
				}
			}
			$menuItems[] = 'spacer';
			// Copy:
			if (!in_array('copy', $this->disabledItems) && !$isStorageRoot && $fileObject->checkActionPermission('read')) {
				$menuItems['copy'] = $this->FILE_copycut($identifier, 'copy');
			}
			// Cut:
			if (!in_array('cut', $this->disabledItems) && !$isStorageRoot && $fileObject->checkActionPermission('move')) {
				$menuItems['cut'] = $this->FILE_copycut($identifier, 'cut');
			}
			// Paste:
			$elFromAllTables = count($this->clipObj->elFromTable('_FILE'));
			if (!in_array('paste', $this->disabledItems) && $elFromAllTables && $folder && $fileObject->checkActionPermission('write')) {
				$elArr = $this->clipObj->elFromTable('_FILE');
				$selItem = reset($elArr);
				$elInfo = array(
					basename($selItem),
					basename($identifier),
					$this->clipObj->currentMode()
				);
				$clickedFileOrFolder = ResourceFactory::getInstance()->retrieveFileOrFolderObject($combinedIdentifier);
				$fileOrFolderInClipBoard = ResourceFactory::getInstance()->retrieveFileOrFolderObject($selItem);
				if (!$fileOrFolderInClipBoard instanceof Folder || !$fileOrFolderInClipBoard->getStorage()->isWithinFolder($fileOrFolderInClipBoard, $clickedFileOrFolder)) {
					$menuItems['pasteinto'] = $this->FILE_paste($identifier, $selItem, $elInfo);
				}
			}
			$menuItems[] = 'spacer';
			// Delete:
			if (!in_array('delete', $this->disabledItems) && $fileObject->checkActionPermission('delete')) {
				if ($isStorageRoot && $userMayEditStorage) {
					$elInfo = array(GeneralUtility::fixed_lgd_cs($fileObject->getStorage()->getName(), $this->backendUser->uc['titleLen']));
					$menuItems['delete'] = $this->DB_delete('sys_file_storage', $fileObject->getStorage()->getUid(), $elInfo);
				} elseif (!$isStorageRoot) {
					$menuItems['delete'] = $this->FILE_delete($identifier);
				}
			}
		}
		// Adding external elements to the menuItems array
		$menuItems = $this->processingByExtClassArray($menuItems, $identifier, 0);
		// Processing by external functions?
		$menuItems = $this->externalProcessingOfFileMenuItems($menuItems);
		// Return the printed elements:
		return $this->printItems($menuItems, $icon . $fileObject->getName());
	}

	/**
	 * Processing the $menuItems array (for extension classes) (FILES)
	 *
	 * @param array $menuItems Array for manipulation.
	 * @return array Processed $menuItems array
	 */
	public function externalProcessingOfFileMenuItems($menuItems) {
		return $menuItems;
	}

	/**
	 * Multi-function for adding an entry to the $menuItems array
	 *
	 * @param string $path Path to the file/directory (target)
	 * @param string $moduleName Script (deprecated) or module name (e.g. file_edit) to pass &target= to
	 * @param string $type "type" is the code which fetches the correct label for the element from "cm.
	 * @param string $iconName
	 * @param bool $noReturnUrl If set, the return URL parameter will not be set in the link
	 * @return array Item array, element in $menuItems
	 * @internal
	 */
	public function FILE_launch($path, $moduleName, $type, $iconName, $noReturnUrl = FALSE) {
		$loc = 'top.content.list_frame';

		if (strpos($moduleName, '.php') !== FALSE) {
			GeneralUtility::deprecationLog(
				'Using a php file directly in ClickMenu is deprecated since TYPO3 CMS 7, and will be removed in CMS 8.'
				. ' Register the class as module and use BackendUtility::getModuleUrl() to get the right link.'
				. ' For examples how to do this see ext_tables.php of EXT:backend.'
			);
			$scriptUrl = $moduleName;
		} else {
			$scriptUrl = BackendUtility::getModuleUrl($moduleName);
		}

		$editOnClick = 'if(' . $loc . '){' . $loc . '.location.href=top.TS.PATH_typo3+' . GeneralUtility::quoteJSvalue($scriptUrl . '&target=' . rawurlencode($path)) . ($noReturnUrl ? '' : '+\'&returnUrl=\'+top.rawurlencode(' . $this->frameLocation($loc . '.document') . '.pathname+' . $this->frameLocation($loc . '.document') . '.search)') . ';}';
		return $this->linkItem($this->label($type), IconUtility::getSpriteIcon($iconName), $editOnClick . 'top.nav.refresh();');
	}

	/**
	 * Returns element for copy or cut of files.
	 *
	 * @param string $path Path to the file/directory (target)
	 * @param string $type Type: "copy" or "cut
	 * @return array Item array, element in $menuItems
	 * @internal
	 */
	public function FILE_copycut($path, $type) {
		$isSel = '';
		// Pseudo table name for use in the clipboard.
		$table = '_FILE';
		$uid = GeneralUtility::shortmd5($path);
		if ($this->clipObj->current === 'normal') {
			$isSel = $this->clipObj->isSelected($table, $uid);
		}
		$addParam = array();
		if ($this->listFrame) {
			$addParam['reloadListFrame'] = $this->alwaysContentFrame ? 2 : 1;
		}
		return $this->linkItem($this->label($type), IconUtility::getSpriteIcon('actions-edit-' . $type . ($isSel === $type ? '-release' : '')), 'TYPO3.ClickMenu.fetch(' . GeneralUtility::quoteJSvalue($this->clipObj->selUrlFile($path, ($type === 'copy' ? 1 : 0), ($isSel == $type), $addParam)) . ');return false;');
	}

	/**
	 * Creates element for deleting of target
	 *
	 * @param string $path Path to the file/directory (target)
	 * @return array Item array, element in $menuItems
	 * @internal
	 */
	public function FILE_delete($path) {
		$loc = 'top.content.list_frame';
		if ($this->backendUser->jsConfirmation(4)) {
			$conf = 'confirm(' . GeneralUtility::quoteJSvalue((sprintf($this->languageService->sL('LLL:EXT:lang/locallang_core.xlf:mess.delete'), basename($path)) . BackendUtility::referenceCount('_FILE', $path, ' (There are %s reference(s) to this file!)'))) . ')';
		} else {
			$conf = '1==1';
		}
		$editOnClick = 'if(' . $loc . ' && ' . $conf . ' ){' . $loc . '.location.href=top.TS.PATH_typo3+' .
			GeneralUtility::quoteJSvalue(BackendUtility::getModuleUrl('tce_file') . '&redirect=') . '+top.rawurlencode(' .
			$this->frameLocation(($loc . '.document')) . '.pathname+' . $this->frameLocation(($loc . '.document')) . '.search)+' .
			GeneralUtility::quoteJSvalue(
				'&file[delete][0][data]=' . rawurlencode($path) . '&vC=' . $this->backendUser->veriCode() . BackendUtility::getUrlToken('tceAction')
			) . ';};';
		return $this->linkItem($this->label('delete'), IconUtility::getSpriteIcon('actions-edit-delete'), $editOnClick . 'return false;');
	}

	/**
	 * Creates element for pasting files.
	 *
	 * @param string $path Path to the file/directory (target)
	 * @param string $target target - NOT USED.
	 * @param array $elInfo Various values for the labels.
	 * @return array Item array, element in $menuItems
	 * @internal
	 */
	public function FILE_paste($path, $target, $elInfo) {
		$loc = 'top.content.list_frame';
		if ($this->backendUser->jsConfirmation(2)) {
			$conf = $loc . ' && confirm(' . GeneralUtility::quoteJSvalue(sprintf($this->languageService->sL(('LLL:EXT:lang/locallang_core.xlf:mess.' . ($elInfo[2] === 'copy' ? 'copy' : 'move') . '_into')), $elInfo[0], $elInfo[1])) . ')';
		} else {
			$conf = $loc;
		}
		$editOnClick = 'if(' . $conf . '){' . $loc . '.location.href=top.TS.PATH_typo3+\'' . $this->clipObj->pasteUrl('_FILE', $path, 0) . '&redirect=\'+top.rawurlencode(' . $this->frameLocation(($loc . '.document')) . '.pathname+' . $this->frameLocation(($loc . '.document')) . '.search);  };top.nav.refresh();';
		return $this->linkItem($this->label('pasteinto'), IconUtility::getSpriteIcon('actions-document-paste-into'), $editOnClick . 'return false;');
	}

	/**
	 * Adding ClickMenu element for file info
	 *
	 * @param string $identifier The combined identifier of the file.
	 * @return array Item array, element in $menuItems
	 */
	protected function fileInfo($identifier) {
		return $this->DB_info('_FILE', $identifier);
	}

	/***************************************
	 *
	 * DRAG AND DROP
	 *
	 ***************************************/
	/**
	 * Make 1st level clickmenu:
	 *
	 * @param string $table The absolute path
	 * @param int $srcId UID for the current record.
	 * @param int $dstId Destination ID
	 * @return string HTML content
	 */
	public function printDragDropClickMenu($table, $srcId, $dstId) {
		$menuItems = array();
		// If the drag and drop menu should apply to PAGES use this set of menu items
		if ($table === 'pages') {
			// Move Into:
			$menuItems['movePage_into'] = $this->dragDrop_copymovepage($srcId, $dstId, 'move', 'into');
			// Move After:
			$menuItems['movePage_after'] = $this->dragDrop_copymovepage($srcId, $dstId, 'move', 'after');
			// Copy Into:
			$menuItems['copyPage_into'] = $this->dragDrop_copymovepage($srcId, $dstId, 'copy', 'into');
			// Copy After:
			$menuItems['copyPage_after'] = $this->dragDrop_copymovepage($srcId, $dstId, 'copy', 'after');
		}
		// If the drag and drop menu should apply to FOLDERS use this set of menu items
		if ($table === 'folders') {
			// Move Into:
			$menuItems['moveFolder_into'] = $this->dragDrop_copymovefolder($srcId, $dstId, 'move');
			// Copy Into:
			$menuItems['copyFolder_into'] = $this->dragDrop_copymovefolder($srcId, $dstId, 'copy');
		}
		// Adding external elements to the menuItems array
		$menuItems = $this->processingByExtClassArray($menuItems, 'dragDrop_' . $table, $srcId);
		// to extend this, you need to apply a Context Menu to a "virtual" table called "dragDrop_pages" or similar
		// Processing by external functions?
		$menuItems = $this->externalProcessingOfDBMenuItems($menuItems);
		// Return the printed elements:
		return $this->printItems($menuItems, IconUtility::getSpriteIconForRecord($table, $this->rec, array('title' => BackendUtility::getRecordTitle($table, $this->rec, TRUE))));
	}

	/**
	 * Processing the $menuItems array (for extension classes) (DRAG'N DROP)
	 *
	 * @param array $menuItems Array for manipulation.
	 * @return array Processed $menuItems array
	 */
	public function externalProcessingOfDragDropMenuItems($menuItems) {
		return $menuItems;
	}

	/**
	 * Adding CM element for Copying/Moving a Page Into/After from a drag & drop action
	 *
	 * @param int $srcUid source UID code for the record to modify
	 * @param int $dstUid destination UID code for the record to modify
	 * @param string $action Action code: either "move" or "copy
	 * @param string $into Parameter code: either "into" or "after
	 * @return array Item array, element in $menuItems
	 * @internal
	 */
	public function dragDrop_copymovepage($srcUid, $dstUid, $action, $into) {
		$negativeSign = $into === 'into' ? '' : '-';
		$loc = 'top.content.list_frame';
		$editOnClick = 'if(' . $loc . '){' . $loc . '.document.location=top.TS.PATH_typo3+' .
			GeneralUtility::quoteJSvalue(BackendUtility::getModuleUrl('tce_db') . '&redirect=') . '+top.rawurlencode(' .
			$this->frameLocation(($loc . '.document')) . '.pathname+' . $this->frameLocation(($loc . '.document')) . '.search)+' .
			GeneralUtility::quoteJSvalue(
				'&cmd[pages][' . $srcUid . '][' . $action . ']=' . $negativeSign . $dstUid . '&prErr=1&vC=' .
				$this->backendUser->veriCode() . BackendUtility::getUrlToken('tceAction')
			) . ';};top.nav.refresh();';
		return $this->linkItem($this->label($action . 'Page_' . $into), IconUtility::getSpriteIcon('actions-document-paste-' . $into), $editOnClick . 'return false;', 0);
	}

	/**
	 * Adding CM element for Copying/Moving a Folder Into from a drag & drop action
	 *
	 * @param string $srcPath source path for the record to modify
	 * @param string $dstPath destination path for the records to modify
	 * @param string $action Action code: either "move" or "copy
	 * @return array Item array, element in $menuItems
	 * @internal
	 */
	public function dragDrop_copymovefolder($srcPath, $dstPath, $action) {
		$loc = 'top.content.list_frame';
		$editOnClick = 'if(' . $loc . '){' . $loc . '.document.location=top.TS.PATH_typo3+' .
			GeneralUtility::quoteJSvalue(BackendUtility::getModuleUrl('tce_file') . '&redirect=') . '+top.rawurlencode(' .
			$this->frameLocation(($loc . '.document')) . '.pathname+' . $this->frameLocation(($loc . '.document')) . '.search)+' .
			GeneralUtility::quoteJSvalue(
				'&file[' . $action . '][0][data]=' . $srcPath . '&file[' . $action . '][0][target]=' . $dstPath . '&prErr=1&vC=' .
				$this->backendUser->veriCode() . BackendUtility::getUrlToken('tceAction')
			) . ';};top.nav.refresh();';
		return $this->linkItem($this->label($action . 'Folder_into'), IconUtility::getSpriteIcon('apps-pagetree-drag-move-into'), $editOnClick . 'return false;', 0);
	}

	/***************************************
	 *
	 * COMMON
	 *
	 **************************************/
	/**
	 * Prints the items from input $menuItems array - as JS section for writing to the div-layers.
	 *
	 * @param array $menuItems Array
	 * @return string HTML code
	 */
	public function printItems($menuItems) {
		// Enable/Disable items
		$menuItems = $this->enableDisableItems($menuItems);
		// Clean up spacers
		$menuItems = $this->cleanUpSpacers($menuItems);
		// Adding JS part and return the content
		return $this->printLayerJScode($menuItems);
	}

	/**
	 * Create the JavaScript section
	 *
	 * @param array $menuItems The $menuItems array to print
	 * @return string The JavaScript section which will print the content of the CM to the div-layer in the target frame.
	 */
	public function printLayerJScode($menuItems) {
		// Clipboard must not be submitted - then it's probably a copy/cut situation.
		if ($this->isCMlayers()) {
			// Create the table displayed in the clickmenu layer:
			// Wrap the inner table in another table to create outer border:
			$CMtable = '
				<div class="typo3-CSM-wrapperCM">
				<table border="0" cellpadding="0" cellspacing="0" class="typo3-CSM">
					' . implode('', $this->menuItemsForClickMenu($menuItems)) . '
				</table></div>';
			return '<data><clickmenu><htmltable><![CDATA[' . $CMtable . ']]></htmltable><cmlevel>' . $this->cmLevel . '</cmlevel></clickmenu></data>';
		}
	}

	/**
	 * Wrapping the input string in a table with background color 4 and a black border style.
	 * For the pop-up menu
	 *
	 * @param string $str HTML content to wrap in table.
	 * @return string
	 * @deprecated since TYPO3 CMS 7, will be removed in TYPO3 CMS 8
	 */
	public function wrapColorTableCM($str) {
		GeneralUtility::logDeprecatedFunction();
		return '<div class="typo3-CSM-wrapperCM">
			' . $str . '
			</div>';
	}

	/**
	 * Traverses the menuItems and generates an output array for implosion in the CM div-layers table.
	 *
	 * @param array $menuItems Array
	 * @return array array for implosion in the CM div-layers table.
	 */
	public function menuItemsForClickMenu($menuItems) {
		$out = array();
		foreach ($menuItems as $cc => $i) {
			// MAKE horizontal spacer
			if (is_string($i) && $i === 'spacer') {
				$out[] = '
					<tr style="height: 1px;" class="bgColor2">
						<td colspan="2"></td>
					</tr>';
			} else {
				// Just make normal element:
				$onClick = $i[3];
				$onClick = preg_replace('/return[[:space:]]+hideCM\\(\\)[[:space:]]*;/i', '', $onClick);
				$onClick = preg_replace('/return[[:space:]]+false[[:space:]]*;/i', '', $onClick);
				$onClick = preg_replace('/hideCM\\(\\);/i', '', $onClick);
				if (!$i[5]) {
					$onClick .= 'TYPO3.ClickMenu.hideAll();';
				}
				$CSM = ' oncontextmenu="this.click();return false;"';
				$out[] = '
					<tr class="typo3-CSM-itemRow" onclick="' . htmlspecialchars($onClick) . '"' . $CSM . '>
						' . (!$this->leftIcons ? '<td class="typo3-CSM-item">' . $i[1] . '</td><td align="center">' . $i[2] . '</td>' : '<td align="center">' . $i[2] . '</td><td class="typo3-CSM-item">' . $i[1] . '</td>') . '
					</tr>';
			}
		}
		return $out;
	}

	/**
	 * Adds or inserts a menu item
	 * Can be used to set the position of new menu entries within the list of existing menu entries. Has this syntax: [cmd]:[menu entry key],[cmd].... cmd can be "after", "before" or "top" (or blank/"bottom" which is default). If "after"/"before" then menu items will be inserted after/before the existing entry with [menu entry key] if found. "after-spacer" and "before-spacer" do the same, but inserts before or after an item and a spacer. If not found, the bottom of list. If "top" the items are inserted in the top of the list.
	 *
	 * @param array $menuItems Menu items array
	 * @param array $newMenuItems Menu items array to insert
	 * @param string $position Position command string. Has this syntax: [cmd]:[menu entry key],[cmd].... cmd can be "after", "before" or "top" (or blank/"bottom" which is default). If "after"/"before" then menu items will be inserted after/before the existing entry with [menu entry key] if found. "after-spacer" and "before-spacer" do the same, but inserts before or after an item and a spacer. If not found, the bottom of list. If "top" the items are inserted in the top of the list.
	 * @return array Menu items array, processed.
	 */
	public function addMenuItems($menuItems, $newMenuItems, $position = '') {
		if (is_array($newMenuItems)) {
			if ($position) {
				$posArr = GeneralUtility::trimExplode(',', $position, TRUE);
				foreach ($posArr as $pos) {
					list($place, $menuEntry) = GeneralUtility::trimExplode(':', $pos, TRUE);
					list($place, $placeExtra) = GeneralUtility::trimExplode('-', $place, TRUE);
					// Bottom
					$pointer = count($menuItems);
					$found = FALSE;
					if ($place) {
						switch (strtolower($place)) {
							case 'after':
							case 'before':
								if ($menuEntry) {
									$p = 1;
									reset($menuItems);
									while (TRUE) {
										if ((string)key($menuItems) === $menuEntry) {
											$pointer = $p;
											$found = TRUE;
											break;
										}
										if (!next($menuItems)) {
											break;
										}
										$p++;
									}
									if (!$found) {
										break;
									}
									if ($place === 'before') {
										$pointer--;
										if ($placeExtra === 'spacer' and prev($menuItems) === 'spacer') {
											$pointer--;
										}
									} elseif ($place === 'after') {
										if ($placeExtra === 'spacer' and next($menuItems) === 'spacer') {
											$pointer++;
										}
									}
								}
								break;
							default:
								if (strtolower($place) === 'top') {
									$pointer = 0;
								} else {
									$pointer = count($menuItems);
								}
								$found = TRUE;
						}
					}
					if ($found) {
						break;
					}
				}
			}
			$pointer = max(0, $pointer);
			$menuItemsBefore = array_slice($menuItems, 0, $pointer ?: 0);
			$menuItemsAfter = array_slice($menuItems, $pointer);
			$menuItems = $menuItemsBefore + $newMenuItems + $menuItemsAfter;
		}
		return $menuItems;
	}

	/**
	 * Creating an array with various elements for the clickmenu entry
	 *
	 * @param string $str The label, htmlspecialchar'ed already
	 * @param string $icon <img>-tag for the icon
	 * @param string $onClick JavaScript onclick event for label/icon
	 * @param int $onlyCM ==1 and the element will NOT appear in clickmenus in the topframe (unless clickmenu is totally unavailable)! ==2 and the item will NEVER appear in top frame. (This is mostly for "less important" options since the top frame is not capable of holding so many elements horizontally)
	 * @param bool $dontHide If set, the clickmenu layer will not hide itself onclick - used for secondary menus to appear...
	 * @return array $menuItem entry with 6 numerical entries: [0] is the HTML for display of the element with link and icon an mouseover etc., [1]-[5] is simply the input params passed through!
	 */
	public function linkItem($str, $icon, $onClick, $onlyCM = 0, $dontHide = 0) {
		$onClick = str_replace('top.loadTopMenu', 'showClickmenu_raw', $onClick);
		return array(
			'<a href="#" onclick="' . htmlspecialchars($onClick) . '">' . $str . $icon . '</a>',
			$str,
			$icon,
			$onClick,
			$onlyCM,
			$dontHide
		);
	}

	/**
	 * Returns the input string IF not a user setting has disabled display of icons.
	 *
	 * @param string $iconCode The icon-image tag
	 * @return string The icon-image tag prefixed with space char IF the icon should be printed at all due to user settings
	 * @deprecated since TYPO3 CMS 7, will be removed in TYPO3 CMS 8
	 */
	public function excludeIcon($iconCode) {
		GeneralUtility::logDeprecatedFunction();
		return $this->backendUser->uc['noMenuMode'] && $this->backendUser->uc['noMenuMode'] !== 'icons' ? '' : ' ' . $iconCode;
	}

	/**
	 * Enabling / Disabling items based on list provided from GET var ($this->iParts[3])
	 *
	 * @param array $menuItems Menu items array
	 * @return array Menu items array, processed.
	 */
	public function enableDisableItems($menuItems) {
		if ($this->iParts[3]) {
			// Detect "only" mode: (only showing listed items)
			if ($this->iParts[3][0] === '+') {
				$this->iParts[3] = substr($this->iParts[3], 1);
				$only = TRUE;
			} else {
				$only = FALSE;
			}
			// Do filtering:
			// Transfer ONLY elements which are mentioned (or are spacers)
			if ($only) {
				$newMenuArray = array();
				foreach ($menuItems as $key => $value) {
					if (GeneralUtility::inList($this->iParts[3], $key) || is_string($value) && $value === 'spacer') {
						$newMenuArray[$key] = $value;
					}
				}
				$menuItems = $newMenuArray;
			} else {
				// Traverse all elements except those listed (just unsetting them):
				$elements = GeneralUtility::trimExplode(',', $this->iParts[3], TRUE);
				foreach ($elements as $value) {
					unset($menuItems[$value]);
				}
			}
		}
		// Return processed menu items:
		return $menuItems;
	}

	/**
	 * Clean up spacers; Will remove any spacers in the start/end of menu items array plus any duplicates.
	 *
	 * @param array $menuItems Menu items array
	 * @return array Menu items array, processed.
	 */
	public function cleanUpSpacers($menuItems) {
		// Remove doubles:
		$prevItemWasSpacer = FALSE;
		foreach ($menuItems as $key => $value) {
			if (is_string($value) && $value === 'spacer') {
				if ($prevItemWasSpacer) {
					unset($menuItems[$key]);
				}
				$prevItemWasSpacer = TRUE;
			} else {
				$prevItemWasSpacer = FALSE;
			}
		}
		// Remove first:
		reset($menuItems);
		$key = key($menuItems);
		$value = current($menuItems);
		if (is_string($value) && $value === 'spacer') {
			unset($menuItems[$key]);
		}
		// Remove last:
		end($menuItems);
		$key = key($menuItems);
		$value = current($menuItems);
		if (is_string($value) && $value === 'spacer') {
			unset($menuItems[$key]);
		}
		// Return processed menu items:
		return $menuItems;
	}

	/**
	 * Get label from locallang_core.xlf:cm.*
	 *
	 * @param string $label The "cm."-suffix to get.
	 * @return string
	 */
	public function label($label) {
		return $this->languageService->makeEntities($this->languageService->sL('LLL:EXT:lang/locallang_core.xlf:cm.' . $label, TRUE));
	}

	/**
	 * Returns TRUE if there should be writing to the div-layers (commands sent to clipboard MUST NOT write to div-layers)
	 *
	 * @return bool
	 */
	public function isCMlayers() {
		return !$this->CB;
	}

	/**
	 * Appends ".location" to input string
	 *
	 * @param string $str Input string, probably a JavaScript document reference
	 * @return string
	 */
	public function frameLocation($str) {
		return $str . '.location';
	}

}
