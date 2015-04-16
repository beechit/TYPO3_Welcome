<?php
namespace TYPO3\CMS\Frontend\ContentObject\Menu;

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

use TYPO3\CMS\Core\TypoScript\TemplateService;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Frontend\Page\PageRepository;

/**
 * Generating navigation / menus from TypoScript
 *
 * Base class. The HMENU content object uses this (or more precisely one of the extension classes).
 * Among others the class generates an array of menuitems. Thereafter functions from the subclasses are called.
 * The class is ALWAYS used through extension classes (like GraphicalMenuContentObject or TextMenuContentObject which are classics) and
 *
 * @author Kasper Skårhøj <kasperYYYY@typo3.com>
 */
class AbstractMenuContentObject {

	/**
	 * tells you which menu-number this is. This is important when getting data from the setup
	 *
	 * @var int
	 */
	public $menuNumber = 1;

	/**
	 * 0 = rootFolder
	 *
	 * @var int
	 */
	public $entryLevel = 0;

	/**
	 * The doktype-number that defines a spacer
	 *
	 * @var string
	 */
	public $spacerIDList = '199';

	/**
	 * Doktypes that define which should not be included in a menu
	 *
	 * @var string
	 */
	public $doktypeExcludeList = '6';

	/**
	 * @var array
	 */
	public $alwaysActivePIDlist = array();

	/**
	 * @var string
	 */
	public $imgNamePrefix = 'img';

	/**
	 * @var int
	 */
	public $imgNameNotRandom = 0;

	/**
	 * @var bool
	 */
	public $debug = 0;

	/**
	 * Loaded with the parent cObj-object when a new HMENU is made
	 *
	 * @var \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer
	 */
	public $parent_cObj;

	/**
	 * @var string
	 */
	public $GMENU_fixKey = 'gmenu';

	/**
	 * accumulation of mount point data
	 *
	 * @var array
	 */
	public $MP_array = array();

	/**
	 * HMENU configuration
	 *
	 * @var array
	 */
	public $conf = array();

	/**
	 * xMENU configuration (TMENU, GMENU etc)
	 *
	 * @var array
	 */
	public $mconf = array();

	/**
	 * template-object
	 *
	 * @var \TYPO3\CMS\Core\TypoScript\TemplateService
	 */
	public $tmpl;

	/**
	 * sys_page-object, pagefunctions
	 *
	 * @var \TYPO3\CMS\Frontend\Page\PageRepository
	 */
	public $sys_page;

	/**
	 * The base page-id of the menu.
	 *
	 * @var int
	 */
	public $id;

	/**
	 * Holds the page uid of the NEXT page in the root line from the page pointed to by entryLevel;
	 * Used to expand the menu automatically if in a certain root line.
	 *
	 * @var string
	 */
	public $nextActive;

	/**
	 * The array of menuItems which is built
	 *
	 * @var array
	 */
	public $menuArr;

	/**
	 * @var string
	 */
	public $hash;

	/**
	 * @var array
	 */
	public $result = array();

	/**
	 * Is filled with an array of page uid numbers + RL parameters which are in the current
	 * root line (used to evaluate whether a menu item is in active state)
	 *
	 * @var string
	 */
	public $rL_uidRegister = '';

	/**
	 * @var string
	 */
	public $INPfixMD5;

	/**
	 * @var array
	 */
	public $I;

	/**
	 * @var string
	 */
	public $WMresult;

	/**
	 * @var string
	 */
	public $WMfreezePrefix;

	/**
	 * @var int
	 */
	public $WMmenuItems;

	/**
	 * @var array
	 */
	public $WMsubmenuObjSuffixes;

	/**
	 * @var string
	 */
	public $WMextraScript;

	/**
	 * Can be set to contain menu item arrays for sub-levels.
	 *
	 * @var string
	 */
	public $alternativeMenuTempArray = '';

	/**
	 * Will be 'id' in XHTML-mode
	 *
	 * @var string
	 */
	public $nameAttribute = 'name';

	/**
	 * TRUE to use cHash in generated link (normally only for the language
	 * selector and if parameters exist in the URL).
	 *
	 * @var bool
	 */
	protected $useCacheHash = FALSE;

	/**
	 * The initialization of the object. This just sets some internal variables.
	 *
	 * @param TemplateService $tmpl The $GLOBALS['TSFE']->tmpl object
	 * @param PageRepository $sys_page The $GLOBALS['TSFE']->sys_page object
	 * @param int $id A starting point page id. This should probably be blank since the 'entryLevel' value will be used then.
	 * @param array $conf The TypoScript configuration for the HMENU cObject
	 * @param int $menuNumber Menu number; 1,2,3. Should probably be '1'
	 * @param string $objSuffix Submenu Object suffix. This offers submenus a way to use alternative configuration for specific positions in the menu; By default "1 = TMENU" would use "1." for the TMENU configuration, but if this string is set to eg. "a" then "1a." would be used for configuration instead (while "1 = " is still used for the overall object definition of "TMENU")
	 * @return bool Returns TRUE on success
	 * @see \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer::HMENU()
	 */
	public function start(&$tmpl, &$sys_page, $id, $conf, $menuNumber, $objSuffix = '') {
		// Init:
		$this->conf = $conf;
		$this->menuNumber = $menuNumber;
		$this->mconf = $conf[$this->menuNumber . $objSuffix . '.'];
		$this->debug = $GLOBALS['TSFE']->debug;
		// In XHTML there is no "name" attribute anymore
		switch ($GLOBALS['TSFE']->xhtmlDoctype) {
			case 'xhtml_strict':

			case 'xhtml_11':

			case 'xhtml_2':

			case 'html5':
				$this->nameAttribute = 'id';
				break;
			default:
				$this->nameAttribute = 'name';
		}
		// Sets the internal vars. $tmpl MUST be the template-object. $sys_page MUST be the sys_page object
		if ($this->conf[$this->menuNumber . $objSuffix] && is_object($tmpl) && is_object($sys_page)) {
			$this->tmpl = $tmpl;
			$this->sys_page = $sys_page;
			// alwaysActivePIDlist initialized:
			if (trim($this->conf['alwaysActivePIDlist']) || isset($this->conf['alwaysActivePIDlist.'])) {
				if (isset($this->conf['alwaysActivePIDlist.'])) {
					$this->conf['alwaysActivePIDlist'] = $this->parent_cObj->stdWrap(
						$this->conf['alwaysActivePIDlist'],
						$this->conf['alwaysActivePIDlist.']
					);
				}
				$this->alwaysActivePIDlist = GeneralUtility::intExplode(',', $this->conf['alwaysActivePIDlist']);
			}
			// 'not in menu' doktypes
			if ($this->conf['excludeDoktypes']) {
				$this->doktypeExcludeList = $GLOBALS['TYPO3_DB']->cleanIntList($this->conf['excludeDoktypes']);
			}
			// EntryLevel
			$this->entryLevel = $this->parent_cObj->getKey(
				isset($conf['entryLevel.']) ? $this->parent_cObj->stdWrap(
					$conf['entryLevel'],
					$conf['entryLevel.']
				) : $conf['entryLevel'],
				$this->tmpl->rootLine
			);
			// Set parent page: If $id not stated with start() then the base-id will be found from rootLine[$this->entryLevel]
			// Called as the next level in a menu. It is assumed that $this->MP_array is set from parent menu.
			if ($id) {
				$this->id = (int)$id;
			} else {
				// This is a BRAND NEW menu, first level. So we take ID from rootline and also find MP_array (mount points)
				$this->id = (int)$this->tmpl->rootLine[$this->entryLevel]['uid'];
				// Traverse rootline to build MP_array of pages BEFORE the entryLevel
				// (MP var for ->id is picked up in the next part of the code...)
				foreach ($this->tmpl->rootLine as $entryLevel => $levelRec) {
					// For overlaid mount points, set the variable right now:
					if ($levelRec['_MP_PARAM'] && $levelRec['_MOUNT_OL']) {
						$this->MP_array[] = $levelRec['_MP_PARAM'];
					}
					// Break when entry level is reached:
					if ($entryLevel >= $this->entryLevel) {
						break;
					}
					// For normal mount points, set the variable for next level.
					if ($levelRec['_MP_PARAM'] && !$levelRec['_MOUNT_OL']) {
						$this->MP_array[] = $levelRec['_MP_PARAM'];
					}
				}
			}
			// Return FALSE if no page ID was set (thus no menu of subpages can be made).
			if ($this->id <= 0) {
				return FALSE;
			}
			// Check if page is a mount point, and if so set id and MP_array
			// (basically this is ONLY for non-overlay mode, but in overlay mode an ID with a mount point should never reach this point anyways, so no harm done...)
			$mount_info = $this->sys_page->getMountPointInfo($this->id);
			if (is_array($mount_info)) {
				$this->MP_array[] = $mount_info['MPvar'];
				$this->id = $mount_info['mount_pid'];
			}
			// Gather list of page uids in root line (for "isActive" evaluation). Also adds the MP params in the path so Mount Points are respected.
			// (List is specific for this rootline, so it may be supplied from parent menus for speed...)
			if (!is_array($this->rL_uidRegister)) {
				$rl_MParray = array();
				foreach ($this->tmpl->rootLine as $v_rl) {
					// For overlaid mount points, set the variable right now:
					if ($v_rl['_MP_PARAM'] && $v_rl['_MOUNT_OL']) {
						$rl_MParray[] = $v_rl['_MP_PARAM'];
					}
					// Add to register:
					$this->rL_uidRegister[] = 'ITEM:' . $v_rl['uid'] .
						(count($rl_MParray)
							? ':' . implode(',', $rl_MParray)
							: ''
						);
					// For normal mount points, set the variable for next level.
					if ($v_rl['_MP_PARAM'] && !$v_rl['_MOUNT_OL']) {
						$rl_MParray[] = $v_rl['_MP_PARAM'];
					}
				}
			}
			// Set $directoryLevel so the following evalution of the nextActive will not return
			// an invalid value if .special=directory was set
			$directoryLevel = 0;
			if ($this->conf['special'] == 'directory') {
				$value = isset($this->conf['special.']['value.']) ? $this->parent_cObj->stdWrap(
					$this->conf['special.']['value'],
					$this->conf['special.']['value.']
				) : $this->conf['special.']['value'];
				if ($value == '') {
					$value = $GLOBALS['TSFE']->page['uid'];
				}
				$directoryLevel = (int)$GLOBALS['TSFE']->tmpl->getRootlineLevel($value);
			}
			// Setting "nextActive": This is the page uid + MPvar of the NEXT page in rootline. Used to expand the menu if we are in the right branch of the tree
			// Notice: The automatic expansion of a menu is designed to work only when no "special" modes (except "directory") are used.
			$startLevel = $directoryLevel ?: $this->entryLevel;
			$currentLevel = $startLevel + $this->menuNumber;
			if (is_array($this->tmpl->rootLine[$currentLevel])) {
				$nextMParray = $this->MP_array;
				if (!count($nextMParray) && !$this->tmpl->rootLine[$currentLevel]['_MOUNT_OL'] && $currentLevel > 0) {
					// Make sure to slide-down any mount point information (_MP_PARAM) to children records in the rootline
					// otherwise automatic expansion will not work
					$parentRecord = $this->tmpl->rootLine[$currentLevel - 1];
					if (isset($parentRecord['_MP_PARAM'])) {
						$nextMParray[] = $parentRecord['_MP_PARAM'];
					}
				}
				// In overlay mode, add next level MPvars as well:
				if ($this->tmpl->rootLine[$currentLevel]['_MOUNT_OL']) {
					$nextMParray[] = $this->tmpl->rootLine[$currentLevel]['_MP_PARAM'];
				}
				$this->nextActive = $this->tmpl->rootLine[$currentLevel]['uid'] .
					(count($nextMParray)
						? ':' . implode(',', $nextMParray)
						: ''
					);
			} else {
				$this->nextActive = '';
			}
			// imgNamePrefix
			if ($this->mconf['imgNamePrefix']) {
				$this->imgNamePrefix = $this->mconf['imgNamePrefix'];
			}
			$this->imgNameNotRandom = $this->mconf['imgNameNotRandom'];
			$retVal = TRUE;
		} else {
			$GLOBALS['TT']->setTSlogMessage('ERROR in menu', 3);
			$retVal = FALSE;
		}
		return $retVal;
	}

	/**
	 * Creates the menu in the internal variables, ready for output.
	 * Basically this will read the page records needed and fill in the internal $this->menuArr
	 * Based on a hash of this array and some other variables the $this->result variable will be loaded either from cache OR by calling the generate() method of the class to create the menu for real.
	 *
	 * @return void
	 */
	public function makeMenu() {
		if ($this->id) {
			$this->useCacheHash = FALSE;

			// Initializing showAccessRestrictedPages
			if ($this->mconf['showAccessRestrictedPages']) {
				// SAVING where_groupAccess
				$SAVED_where_groupAccess = $this->sys_page->where_groupAccess;
				// Temporarily removing fe_group checking!
				$this->sys_page->where_groupAccess = '';
			}

			$menuItems = $this->prepareMenuItems();

			$c = 0;
			$c_b = 0;
			$minItems = (int)($this->mconf['minItems'] ?: $this->conf['minItems']);
			$maxItems = (int)($this->mconf['maxItems'] ?: $this->conf['maxItems']);
			$begin = $this->parent_cObj->calc($this->mconf['begin'] ? $this->mconf['begin'] : $this->conf['begin']);
			$minItemsConf = isset($this->mconf['minItems.']) ? $this->mconf['minItems.'] : (isset($this->conf['minItems.']) ? $this->conf['minItems.'] : NULL);
			$minItems = is_array($minItemsConf) ? $this->parent_cObj->stdWrap($minItems, $minItemsConf) : $minItems;
			$maxItemsConf = isset($this->mconf['maxItems.']) ? $this->mconf['maxItems.'] : (isset($this->conf['maxItems.']) ? $this->conf['maxItems.'] : NULL);
			$maxItems = is_array($maxItemsConf) ? $this->parent_cObj->stdWrap($maxItems, $maxItemsConf) : $maxItems;
			$beginConf = isset($this->mconf['begin.']) ? $this->mconf['begin.'] : (isset($this->conf['begin.']) ? $this->conf['begin.'] : NULL);
			$begin = is_array($beginConf) ? $this->parent_cObj->stdWrap($begin, $beginConf) : $begin;
			$banUidArray = $this->getBannedUids();
			// Fill in the menuArr with elements that should go into the menu:
			$this->menuArr = array();
			foreach ($menuItems as $data) {
				$spacer = GeneralUtility::inList(
						$this->spacerIDList,
						$data['doktype']
					) || $data['ITEM_STATE'] === 'SPC';
				// if item is a spacer, $spacer is set
				if ($this->filterMenuPages($data, $banUidArray, $spacer)) {
					$c_b++;
					// If the beginning item has been reached.
					if ($begin <= $c_b) {
						$this->menuArr[$c] = $data;
						$this->menuArr[$c]['isSpacer'] = $spacer;
						$c++;
						if ($maxItems && $c >= $maxItems) {
							break;
						}
					}
				}
			}
			// Fill in fake items, if min-items is set.
			if ($minItems) {
				while ($c < $minItems) {
					$this->menuArr[$c] = array(
						'title' => '...',
						'uid' => $GLOBALS['TSFE']->id
					);
					$c++;
				}
			}
			//	Passing the menuArr through a user defined function:
			if ($this->mconf['itemArrayProcFunc']) {
				if (!is_array($this->parentMenuArr)) {
					$this->parentMenuArr = array();
				}
				$this->menuArr = $this->userProcess('itemArrayProcFunc', $this->menuArr);
			}
			// Setting number of menu items
			$GLOBALS['TSFE']->register['count_menuItems'] = count($this->menuArr);
			$this->hash = md5(
				serialize($this->menuArr) .
				serialize($this->mconf) .
				serialize($this->tmpl->rootLine) .
				serialize($this->MP_array)
			);
			// Get the cache timeout:
			if ($this->conf['cache_period']) {
				$cacheTimeout = $this->conf['cache_period'];
			} else {
				$cacheTimeout = $GLOBALS['TSFE']->get_cache_timeout();
			}
			$cachedData = $this->sys_page->getHash($this->hash);
			if (!is_array($cachedData)) {
				$this->generate();
				$this->sys_page->storeHash($this->hash, $this->result, 'MENUDATA', $cacheTimeout);
			} else {
				$this->result = $cachedData;
			}
			// End showAccessRestrictedPages
			if ($this->mconf['showAccessRestrictedPages']) {
				// RESTORING where_groupAccess
				$this->sys_page->where_groupAccess = $SAVED_where_groupAccess;
			}
		}
	}

	/**
	 * Gets an array of page rows and removes all, which are not accessible
	 *
	 * @param array $pages
	 * @return array
	 */
	protected function removeInaccessiblePages(array $pages) {
		$banned = $this->getBannedUids();
		$filteredPages = array();
		foreach ($pages as $aPage) {
			if ($this->filterMenuPages($aPage, $banned, $aPage['doktype'] === PageRepository::DOKTYPE_SPACER)) {
				$filteredPages[$aPage['uid']] = $aPage;
			}
		}
		return $filteredPages;
	}

	/**
	 * Main function for retrieving menu items based on the menu type (special or sectionIndex or "normal")
	 *
	 * @return array
	 */
	protected function prepareMenuItems() {
		$menuItems = array();
		$alternativeSortingField = trim($this->mconf['alternativeSortingField']) ?: 'sorting';

		// Additional where clause, usually starts with AND (as usual with all additionalWhere functionality in TS)
		$additionalWhere = isset($this->mconf['additionalWhere']) ? $this->mconf['additionalWhere'] : '';
		if (isset($this->mconf['additionalWhere.'])) {
			$additionalWhere = $this->parent_cObj->stdWrap($additionalWhere, $this->mconf['additionalWhere.']);

		}

		// ... only for the FIRST level of a HMENU
		if ($this->menuNumber == 1 && $this->conf['special']) {
			$value = isset($this->conf['special.']['value.'])
				? $this->parent_cObj->stdWrap($this->conf['special.']['value'],$this->conf['special.']['value.'])
				: $this->conf['special.']['value'];
			switch ($this->conf['special']) {
				case 'userfunction':
					$menuItems = $this->prepareMenuItemsForUserSpecificMenu($value, $alternativeSortingField);
					break;
				case 'language':
					$menuItems = $this->prepareMenuItemsForLanguageMenu($value);
					break;
				case 'directory':
					$menuItems = $this->prepareMenuItemsForDirectoryMenu($value, $alternativeSortingField);
					break;
				case 'list':
					$menuItems = $this->prepareMenuItemsForListMenu($value);
					break;
				case 'updated':
					$menuItems = $this->prepareMenuItemsForUpdatedMenu(
						$value,
						$this->mconf['alternativeSortingField'] ?: FALSE
					);
					break;
				case 'keywords':
					$menuItems = $this->prepareMenuItemsForKeywordsMenu(
						$value,
						$this->mconf['alternativeSortingField'] ?: FALSE
					);
					break;
				case 'categories':
					/** @var \TYPO3\CMS\Frontend\ContentObject\Menu\CategoryMenuUtility $categoryMenuUtility */
					$categoryMenuUtility = GeneralUtility::makeInstance(\TYPO3\CMS\Frontend\ContentObject\Menu\CategoryMenuUtility::class);
					$menuItems = $categoryMenuUtility->collectPages($value, $this->conf['special.'], $this);
					break;
				case 'rootline':
					$menuItems = $this->prepareMenuItemsForRootlineMenu();
					break;
				case 'browse':
					$menuItems = $this->prepareMenuitemsForBrowseMenu($value, $alternativeSortingField, $additionalWhere);
					break;
			}
			if ($this->mconf['sectionIndex']) {
				$sectionIndexes = array();
				foreach ($menuItems as $page) {
					$sectionIndexes = $sectionIndexes + $this->sectionIndex($alternativeSortingField, $page['uid']);
				}
				$menuItems = $sectionIndexes;
			}
		} elseif (is_array($this->alternativeMenuTempArray)) {
			// Setting $menuItems array if not level 1.
			$menuItems = $this->alternativeMenuTempArray;
		} elseif ($this->mconf['sectionIndex']) {
			$menuItems = $this->sectionIndex($alternativeSortingField);
		} else {
			// Default: Gets a hierarchical menu based on subpages of $this->id
			$menuItems = $this->sys_page->getMenu($this->id, '*', $alternativeSortingField, $additionalWhere);
		}
		return $menuItems;
	}

	/**
	 * Fetches all menuitems if special = userfunction is set
	 *
	 * @param string $specialValue The value from special.value
	 * @param string $sortingField The sorting field
	 * @return array
	 */
	protected function prepareMenuItemsForUserSpecificMenu($specialValue, $sortingField) {
		$menuItems = $this->parent_cObj->callUserFunction(
			$this->conf['special.']['userFunc'],
			array_merge($this->conf['special.'], array('value' => $specialValue, '_altSortField' => $sortingField)),
			''
		);
		if (!is_array($menuItems)) {
			$menuItems = array();
		}
		return $menuItems;
	}

	/**
	 * Fetches all menuitems if special = language is set
	 *
	 * @param string $specialValue The value from special.value
	 * @return array
	 */
	protected function prepareMenuItemsForLanguageMenu($specialValue) {
		$menuItems = array();
		// Getting current page record NOT overlaid by any translation:
		$currentPageWithNoOverlay = $this->sys_page->getRawRecord('pages', $GLOBALS['TSFE']->page['uid']);
		// Traverse languages set up:
		$languageItems = GeneralUtility::intExplode(',', $specialValue);
		foreach ($languageItems as $sUid) {
			// Find overlay record:
			if ($sUid) {
				$lRecs = $this->sys_page->getPageOverlay($GLOBALS['TSFE']->page['uid'], $sUid);
			} else {
				$lRecs = array();
			}
			// Checking if the "disabled" state should be set.
			if (GeneralUtility::hideIfNotTranslated($GLOBALS['TSFE']->page['l18n_cfg']) && $sUid &&
				!count($lRecs) || $GLOBALS['TSFE']->page['l18n_cfg'] & 1 &&
				(!$sUid || !count($lRecs)) ||
				!$this->conf['special.']['normalWhenNoLanguage'] && $sUid && !count($lRecs)
			) {
				$iState = $GLOBALS['TSFE']->sys_language_uid == $sUid ? 'USERDEF2' : 'USERDEF1';
			} else {
				$iState = $GLOBALS['TSFE']->sys_language_uid == $sUid ? 'ACT' : 'NO';
			}
			if ($this->conf['addQueryString']) {
				$getVars = $this->parent_cObj->getQueryArguments(
					$this->conf['addQueryString.'],
					array('L' => $sUid),
					TRUE
				);
				$this->analyzeCacheHashRequirements($getVars);
			} else {
				$getVars = '&L=' . $sUid;
			}
			// Adding menu item:
			$menuItems[] = array_merge(
				array_merge($currentPageWithNoOverlay, $lRecs),
				array(
					'ITEM_STATE' => $iState,
					'_ADD_GETVARS' => $getVars,
					'_SAFE' => TRUE
				)
			);
		}
		return $menuItems;
	}

	/**
	 * Fetches all menuitems if special = directory is set
	 *
	 * @param string $specialValue The value from special.value
	 * @param string $sortingField The sorting field
	 * @return array
	 */
	protected function prepareMenuItemsForDirectoryMenu($specialValue, $sortingField) {
		$menuItems = array();
		if ($specialValue == '') {
			$specialValue = $GLOBALS['TSFE']->page['uid'];
		}
		$items = GeneralUtility::intExplode(',', $specialValue);
		foreach ($items as $id) {
			$MP = $this->tmpl->getFromMPmap($id);
			// Checking if a page is a mount page and if so, change the ID and set the MP var properly.
			$mount_info = $this->sys_page->getMountPointInfo($id);
			if (is_array($mount_info)) {
				if ($mount_info['overlay']) {
					// Overlays should already have their full MPvars calculated:
					$MP = $this->tmpl->getFromMPmap($mount_info['mount_pid']);
					$MP = $MP ? $MP : $mount_info['MPvar'];
				} else {
					$MP = ($MP ? $MP . ',' : '') . $mount_info['MPvar'];
				}
				$id = $mount_info['mount_pid'];
			}
			// Get sub-pages:
			$res = $this->parent_cObj->exec_getQuery('pages', array('pidInList' => $id, 'orderBy' => $sortingField));
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				$GLOBALS['TSFE']->sys_page->versionOL('pages', $row, TRUE);
				if (is_array($row)) {
					// Keep mount point?
					$mount_info = $this->sys_page->getMountPointInfo($row['uid'], $row);
					// There is a valid mount point.
					if (is_array($mount_info) && $mount_info['overlay']) {
						// Using "getPage" is OK since we need the check for enableFields
						// AND for type 2 of mount pids we DO require a doktype < 200!
						$mp_row = $this->sys_page->getPage($mount_info['mount_pid']);
						if (count($mp_row)) {
							$row = $mp_row;
							$row['_MP_PARAM'] = $mount_info['MPvar'];
						} else {
							// If the mount point could not be fetched with respect
							// to enableFields, unset the row so it does not become a part of the menu!
							unset($row);
						}
					}
					// Add external MP params, then the row:
					if (is_array($row)) {
						if ($MP) {
							$row['_MP_PARAM'] = $MP . ($row['_MP_PARAM'] ? ',' . $row['_MP_PARAM'] : '');
						}
						$menuItems[$row['uid']] = $this->sys_page->getPageOverlay($row);
					}
				}
			}
		}
		return $menuItems;
	}

	/**
	 * Fetches all menuitems if special = list is set
	 *
	 * @param string $specialValue The value from special.value
	 * @return array
	 */
	protected function prepareMenuItemsForListMenu($specialValue) {
		$menuItems = array();
		if ($specialValue == '') {
			$specialValue = $this->id;
		}
		/** @var \TYPO3\CMS\Core\Database\RelationHandler $loadDB*/
		$loadDB = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\RelationHandler::class);
		$loadDB->setFetchAllFields(TRUE);
		$loadDB->start($specialValue, 'pages');
		$loadDB->additionalWhere['pages'] = $this->parent_cObj->enableFields('pages');
		$loadDB->getFromDB();
		foreach ($loadDB->itemArray as $val) {
			$MP = $this->tmpl->getFromMPmap($val['id']);
			// Keep mount point?
			$mount_info = $this->sys_page->getMountPointInfo($val['id']);
			// There is a valid mount point.
			if (is_array($mount_info) && $mount_info['overlay']) {
				// Using "getPage" is OK since we need the check for enableFields
				// AND for type 2 of mount pids we DO require a doktype < 200!
				$mp_row = $this->sys_page->getPage($mount_info['mount_pid']);
				if (count($mp_row)) {
					$row = $mp_row;
					$row['_MP_PARAM'] = $mount_info['MPvar'];
					// Overlays should already have their full MPvars calculated
					if ($mount_info['overlay']) {
						$MP = $this->tmpl->getFromMPmap($mount_info['mount_pid']);
						if ($MP) {
							unset($row['_MP_PARAM']);
						}
					}
				} else {
					// If the mount point could not be fetched with respect to
					// enableFields, unset the row so it does not become a part of the menu!
					unset($row);
				}
			} else {
				$row = $loadDB->results['pages'][$val['id']];
			}
			// Add versioning overlay for current page (to respect workspaces)
			if (is_array($row)) {
				$this->sys_page->versionOL('pages', $row, TRUE);
			}
			// Add external MP params, then the row:
			if (is_array($row)) {
				if ($MP) {
					$row['_MP_PARAM'] = $MP . ($row['_MP_PARAM'] ? ',' . $row['_MP_PARAM'] : '');
				}
				$menuItems[] = $this->sys_page->getPageOverlay($row);
			}
		}
		return $menuItems;
	}

	/**
	 * Fetches all menuitems if special = updated is set
	 *
	 * @param string $specialValue The value from special.value
	 * @param string $sortingField The sorting field
	 * @return array
	 */
	protected function prepareMenuItemsForUpdatedMenu($specialValue, $sortingField) {
		$menuItems = array();
		if ($specialValue == '') {
			$specialValue = $GLOBALS['TSFE']->page['uid'];
		}
		$items = GeneralUtility::intExplode(',', $specialValue);
		if (MathUtility::canBeInterpretedAsInteger($this->conf['special.']['depth'])) {
			$depth = MathUtility::forceIntegerInRange($this->conf['special.']['depth'], 1, 20);
		} else {
			$depth = 20;
		}
		// Max number of items
		$limit = MathUtility::forceIntegerInRange($this->conf['special.']['limit'], 0, 100);
		$maxAge = (int)$this->parent_cObj->calc($this->conf['special.']['maxAge']);
		if (!$limit) {
			$limit = 10;
		}
		// *'auto', 'manual', 'tstamp'
		$mode = $this->conf['special.']['mode'];
		// Get id's
		$id_list_arr = array();
		foreach ($items as $id) {
			$bA = MathUtility::forceIntegerInRange($this->conf['special.']['beginAtLevel'], 0, 100);
			$id_list_arr[] = $this->parent_cObj->getTreeList(-1 * $id, $depth - 1 + $bA, $bA - 1);
		}
		$id_list = implode(',', $id_list_arr);
		// Get sortField (mode)
		switch ($mode) {
			case 'starttime':
				$sortField = 'starttime';
				break;
			case 'lastUpdated':

			case 'manual':
				$sortField = 'lastUpdated';
				break;
			case 'tstamp':
				$sortField = 'tstamp';
				break;
			case 'crdate':
				$sortField = 'crdate';
				break;
			default:
				$sortField = 'SYS_LASTCHANGED';
		}
		$extraWhere = ($this->conf['includeNotInMenu'] ? '' : ' AND pages.nav_hide=0') . $this->getDoktypeExcludeWhere();
		if ($this->conf['special.']['excludeNoSearchPages']) {
			$extraWhere .= ' AND pages.no_search=0';
		}
		if ($maxAge > 0) {
			$extraWhere .= ' AND ' . $sortField . '>' . ($GLOBALS['SIM_ACCESS_TIME'] - $maxAge);
		}
		$res = $this->parent_cObj->exec_getQuery('pages', array(
			'pidInList' => '0',
			'uidInList' => $id_list,
			'where' => $sortField . '>=0' . $extraWhere,
			'orderBy' => $sortingField ?: $sortField . ' DESC',
			'max' => $limit
		));
		while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$GLOBALS['TSFE']->sys_page->versionOL('pages', $row, TRUE);
			if (is_array($row)) {
				$menuItems[$row['uid']] = $this->sys_page->getPageOverlay($row);
			}
		}
		return $menuItems;
	}

	/**
	 * Fetches all menuitems if special = keywords is set
	 *
	 * @param string $specialValue The value from special.value
	 * @param string $sortingField The sorting field
	 * @return array
	 */
	protected function prepareMenuItemsForKeywordsMenu($specialValue, $sortingField) {
		$menuItems = array();
		list($specialValue) = GeneralUtility::intExplode(',', $specialValue);
		if (!$specialValue) {
			$specialValue = $GLOBALS['TSFE']->page['uid'];
		}
		if ($this->conf['special.']['setKeywords'] || $this->conf['special.']['setKeywords.']) {
			$kw = isset($this->conf['special.']['setKeywords.']) ? $this->parent_cObj->stdWrap($this->conf['special.']['setKeywords'], $this->conf['special.']['setKeywords.']) : $this->conf['special.']['setKeywords'];
		} else {
			// The page record of the 'value'.
			$value_rec = $this->sys_page->getPage($specialValue);
			$kfieldSrc = $this->conf['special.']['keywordsField.']['sourceField'] ? $this->conf['special.']['keywordsField.']['sourceField'] : 'keywords';
			// keywords.
			$kw = trim($this->parent_cObj->keywords($value_rec[$kfieldSrc]));
		}
		// *'auto', 'manual', 'tstamp'
		$mode = $this->conf['special.']['mode'];
		switch ($mode) {
			case 'starttime':
				$sortField = 'starttime';
				break;
			case 'lastUpdated':

			case 'manual':
				$sortField = 'lastUpdated';
				break;
			case 'tstamp':
				$sortField = 'tstamp';
				break;
			case 'crdate':
				$sortField = 'crdate';
				break;
			default:
				$sortField = 'SYS_LASTCHANGED';
		}
		// Depth, limit, extra where
		if (MathUtility::canBeInterpretedAsInteger($this->conf['special.']['depth'])) {
			$depth = MathUtility::forceIntegerInRange($this->conf['special.']['depth'], 0, 20);
		} else {
			$depth = 20;
		}
		// Max number of items
		$limit = MathUtility::forceIntegerInRange($this->conf['special.']['limit'], 0, 100);
		$extraWhere = ' AND pages.uid<>' . $specialValue . ($this->conf['includeNotInMenu'] ? '' : ' AND pages.nav_hide=0') . $this->getDoktypeExcludeWhere();
		if ($this->conf['special.']['excludeNoSearchPages']) {
			$extraWhere .= ' AND pages.no_search=0';
		}
		// Start point
		$eLevel = $this->parent_cObj->getKey(isset($this->conf['special.']['entryLevel.'])
			? $this->parent_cObj->stdWrap($this->conf['special.']['entryLevel'], $this->conf['special.']['entryLevel.'])
			: $this->conf['special.']['entryLevel'], $this->tmpl->rootLine
		);
		$startUid = (int)$this->tmpl->rootLine[$eLevel]['uid'];
		// Which field is for keywords
		$kfield = 'keywords';
		if ($this->conf['special.']['keywordsField']) {
			list($kfield) = explode(' ', trim($this->conf['special.']['keywordsField']));
		}
		// If there are keywords and the startuid is present
		if ($kw && $startUid) {
			$bA = MathUtility::forceIntegerInRange($this->conf['special.']['beginAtLevel'], 0, 100);
			$id_list = $this->parent_cObj->getTreeList(-1 * $startUid, $depth - 1 + $bA, $bA - 1);
			$kwArr = explode(',', $kw);
			foreach ($kwArr as $word) {
				$word = trim($word);
				if ($word) {
					$keyWordsWhereArr[] = $kfield . ' LIKE \'%' . $GLOBALS['TYPO3_DB']->quoteStr($word, 'pages') . '%\'';
				}
			}
			$res = $this->parent_cObj->exec_getQuery('pages', array(
				'pidInList' => '0',
				'uidInList' => $id_list,
				'where' => '(' . implode(' OR ', $keyWordsWhereArr) . ')' . $extraWhere,
				'orderBy' => $sortingField ?: $sortField . ' desc',
				'max' => $limit
			));
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				$GLOBALS['TSFE']->sys_page->versionOL('pages', $row, TRUE);
				if (is_array($row)) {
					$menuItems[$row['uid']] = $this->sys_page->getPageOverlay($row);
				}
			}
		}
		return $menuItems;
	}

	/**
	 * Fetches all menuitems if special = rootline is set
	 *
	 * @return array
	 */
	protected function prepareMenuItemsForRootlineMenu() {
		$menuItems = array();
		$range = isset($this->conf['special.']['range.'])
			? $this->parent_cObj->stdWrap($this->conf['special.']['range'], $this->conf['special.']['range.'])
			: $this->conf['special.']['range'];
		$begin_end = explode('|', $range);
		$begin_end[0] = (int)$begin_end[0];
		if (!MathUtility::canBeInterpretedAsInteger($begin_end[1])) {
			$begin_end[1] = -1;
		}
		$beginKey = $this->parent_cObj->getKey($begin_end[0], $this->tmpl->rootLine);
		$endKey = $this->parent_cObj->getKey($begin_end[1], $this->tmpl->rootLine);
		if ($endKey < $beginKey) {
			$endKey = $beginKey;
		}
		$rl_MParray = array();
		foreach ($this->tmpl->rootLine as $k_rl => $v_rl) {
			// For overlaid mount points, set the variable right now:
			if ($v_rl['_MP_PARAM'] && $v_rl['_MOUNT_OL']) {
				$rl_MParray[] = $v_rl['_MP_PARAM'];
			}
			// Traverse rootline:
			if ($k_rl >= $beginKey && $k_rl <= $endKey) {
				$temp_key = $k_rl;
				$menuItems[$temp_key] = $this->sys_page->getPage($v_rl['uid']);
				if (count($menuItems[$temp_key])) {
					// If there are no specific target for the page, put the level specific target on.
					if (!$menuItems[$temp_key]['target']) {
						$menuItems[$temp_key]['target'] = $this->conf['special.']['targets.'][$k_rl];
						$menuItems[$temp_key]['_MP_PARAM'] = implode(',', $rl_MParray);
					}
				} else {
					unset($menuItems[$temp_key]);
				}
			}
			// For normal mount points, set the variable for next level.
			if ($v_rl['_MP_PARAM'] && !$v_rl['_MOUNT_OL']) {
				$rl_MParray[] = $v_rl['_MP_PARAM'];
			}
		}
		// Reverse order of elements (e.g. "1,2,3,4" gets "4,3,2,1"):
		if (isset($this->conf['special.']['reverseOrder']) && $this->conf['special.']['reverseOrder']) {
			$menuItems = array_reverse($menuItems);
			$rl_MParray = array_reverse($rl_MParray);
		}
		return $menuItems;
	}

	/**
	 * Fetches all menuitems if special = browse is set
	 *
	 * @param string $specialValue The value from special.value
	 * @param string $sortingField The sorting field
	 * @param string $additionalWhere Additional WHERE clause
	 * @return array
	 */
	protected function prepareMenuItemsForBrowseMenu($specialValue, $sortingField, $additionalWhere) {
		$menuItems = array();
		list($specialValue) = GeneralUtility::intExplode(',', $specialValue);
		if (!$specialValue) {
			$specialValue = $GLOBALS['TSFE']->page['uid'];
		}
		// Will not work out of rootline
		if ($specialValue != $this->tmpl->rootLine[0]['uid']) {
			$recArr = array();
			// The page record of the 'value'.
			$value_rec = $this->sys_page->getPage($specialValue);
			// 'up' page cannot be outside rootline
			if ($value_rec['pid']) {
				// The page record of 'up'.
				$recArr['up'] = $this->sys_page->getPage($value_rec['pid']);
			}
			// If the 'up' item was NOT level 0 in rootline...
			if ($recArr['up']['pid'] && $value_rec['pid'] != $this->tmpl->rootLine[0]['uid']) {
				// The page record of "index".
				$recArr['index'] = $this->sys_page->getPage($recArr['up']['pid']);
			}
			// check if certain pages should be excluded
			$additionalWhere .= ($this->conf['includeNotInMenu'] ? '' : ' AND pages.nav_hide=0') . $this->getDoktypeExcludeWhere();
			if ($this->conf['special.']['excludeNoSearchPages']) {
				$additionalWhere .= ' AND pages.no_search=0';
			}
			// prev / next is found
			$prevnext_menu = $this->removeInaccessiblePages($this->sys_page->getMenu($value_rec['pid'], '*', $sortingField, $additionalWhere));
			$lastKey = 0;
			$nextActive = 0;
			foreach ($prevnext_menu as $k_b => $v_b) {
				if ($nextActive) {
					$recArr['next'] = $v_b;
					$nextActive = 0;
				}
				if ($v_b['uid'] == $specialValue) {
					if ($lastKey) {
						$recArr['prev'] = $prevnext_menu[$lastKey];
					}
					$nextActive = 1;
				}
				$lastKey = $k_b;
			}
			reset($prevnext_menu);
			$recArr['first'] = pos($prevnext_menu);
			end($prevnext_menu);
			$recArr['last'] = pos($prevnext_menu);
			// prevsection / nextsection is found
			// You can only do this, if there is a valid page two levels up!
			if (is_array($recArr['index'])) {
				$prevnextsection_menu = $this->removeInaccessiblePages($this->sys_page->getMenu($recArr['index']['uid'], '*', $sortingField, $additionalWhere));
				$lastKey = 0;
				$nextActive = 0;
				foreach ($prevnextsection_menu as $k_b => $v_b) {
					if ($nextActive) {
						$sectionRec_temp = $this->removeInaccessiblePages($this->sys_page->getMenu($v_b['uid'], '*', $sortingField, $additionalWhere));
						if (count($sectionRec_temp)) {
							reset($sectionRec_temp);
							$recArr['nextsection'] = pos($sectionRec_temp);
							end($sectionRec_temp);
							$recArr['nextsection_last'] = pos($sectionRec_temp);
							$nextActive = 0;
						}
					}
					if ($v_b['uid'] == $value_rec['pid']) {
						if ($lastKey) {
							$sectionRec_temp = $this->removeInaccessiblePages($this->sys_page->getMenu($prevnextsection_menu[$lastKey]['uid'], '*', $sortingField, $additionalWhere));
							if (count($sectionRec_temp)) {
								reset($sectionRec_temp);
								$recArr['prevsection'] = pos($sectionRec_temp);
								end($sectionRec_temp);
								$recArr['prevsection_last'] = pos($sectionRec_temp);
							}
						}
						$nextActive = 1;
					}
					$lastKey = $k_b;
				}
			}
			if ($this->conf['special.']['items.']['prevnextToSection']) {
				if (!is_array($recArr['prev']) && is_array($recArr['prevsection_last'])) {
					$recArr['prev'] = $recArr['prevsection_last'];
				}
				if (!is_array($recArr['next']) && is_array($recArr['nextsection'])) {
					$recArr['next'] = $recArr['nextsection'];
				}
			}
			$items = explode('|', $this->conf['special.']['items']);
			$c = 0;
			foreach ($items as $k_b => $v_b) {
				$v_b = strtolower(trim($v_b));
				if ((int)$this->conf['special.'][$v_b . '.']['uid']) {
					$recArr[$v_b] = $this->sys_page->getPage((int)$this->conf['special.'][$v_b . '.']['uid']);
				}
				if (is_array($recArr[$v_b])) {
					$menuItems[$c] = $recArr[$v_b];
					if ($this->conf['special.'][$v_b . '.']['target']) {
						$menuItems[$c]['target'] = $this->conf['special.'][$v_b . '.']['target'];
					}
					$tmpSpecialFields = $this->conf['special.'][$v_b . '.']['fields.'];
					if (is_array($tmpSpecialFields)) {
						foreach ($tmpSpecialFields as $fk => $val) {
							$menuItems[$c][$fk] = $val;
						}
					}
					$c++;
				}
			}
		}
		return $menuItems;
	}

	/**
	 * Analyzes the parameters to find if the link needs a cHash parameter.
	 *
	 * @param string $queryString
	 * @return void
	 */
	protected function analyzeCacheHashRequirements($queryString) {
		$parameters = GeneralUtility::explodeUrl2Array($queryString);
		if (count($parameters) > 0) {
			$cacheHashCalculator = GeneralUtility::makeInstance(\TYPO3\CMS\Frontend\Page\CacheHashCalculator::class);
			/** @var \TYPO3\CMS\Frontend\Page\CacheHashCalculator $cacheHashCalculator */
			$cHashParameters = $cacheHashCalculator->getRelevantParameters($queryString);
			if (count($cHashParameters) > 1) {
				$this->useCacheHash = (
					$GLOBALS['TYPO3_CONF_VARS']['FE']['disableNoCacheParameter'] ||
					!isset($parameters['no_cache']) ||
					!$parameters['no_cache']
				);
			}
		}
	}

	/**
	 * Checks if a page is OK to include in the final menu item array. Pages can be excluded if the doktype is wrong, if they are hidden in navigation, have a uid in the list of banned uids etc.
	 *
	 * @param array $data Array of menu items
	 * @param array $banUidArray Array of page uids which are to be excluded
	 * @param bool $spacer If set, then the page is a spacer.
	 * @return bool Returns TRUE if the page can be safely included.
	 */
	public function filterMenuPages(&$data, $banUidArray, $spacer) {
		$includePage = TRUE;
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['cms/tslib/class.tslib_menu.php']['filterMenuPages'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['cms/tslib/class.tslib_menu.php']['filterMenuPages'] as $classRef) {
				$hookObject = GeneralUtility::getUserObj($classRef);
				if (!$hookObject instanceof \TYPO3\CMS\Frontend\ContentObject\Menu\AbstractMenuFilterPagesHookInterface) {
					throw new \UnexpectedValueException('$hookObject must implement interface ' . \TYPO3\CMS\Frontend\ContentObject\Menu\AbstractMenuFilterPagesHookInterface::class, 1269877402);
				}
				$includePage = $includePage && $hookObject->processFilter($data, $banUidArray, $spacer, $this);
			}
		}
		if (!$includePage) {
			return FALSE;
		}
		if ($data['_SAFE']) {
			return TRUE;
		}
		$uid = $data['uid'];
		// If the spacer-function is not enabled, spacers will not enter the $menuArr
		if ($this->mconf['SPC'] || !$spacer) {
			// Page may not be 'not_in_menu' or 'Backend User Section'
			if (!GeneralUtility::inList($this->doktypeExcludeList, $data['doktype'])) {
				// Not hidden in navigation
				if (!$data['nav_hide'] || $this->conf['includeNotInMenu']) {
					// not in banned uid's
					if (!ArrayUtility::inArray($banUidArray, $uid)) {
						// Checks if the default language version can be shown:
						// Block page is set, if l18n_cfg allows plus: 1) Either default language or 2) another language but NO overlay record set for page!
						$blockPage = $data['l18n_cfg'] & 1 && (!$GLOBALS['TSFE']->sys_language_uid || $GLOBALS['TSFE']->sys_language_uid && !$data['_PAGES_OVERLAY']);
						if (!$blockPage) {
							// Checking if a page should be shown in the menu depending on whether a translation exists:
							$tok = TRUE;
							// There is an alternative language active AND the current page requires a translation:
							if ($GLOBALS['TSFE']->sys_language_uid && GeneralUtility::hideIfNotTranslated($data['l18n_cfg'])) {
								if (!$data['_PAGES_OVERLAY']) {
									$tok = FALSE;
								}
							}
							// Continue if token is TRUE:
							if ($tok) {
								// Checking if "&L" should be modified so links to non-accessible pages will not happen.
								if ($this->conf['protectLvar']) {
									$languageUid = (int)$GLOBALS['TSFE']->config['config']['sys_language_uid'];
									if ($languageUid && ($this->conf['protectLvar'] == 'all' || GeneralUtility::hideIfNotTranslated($data['l18n_cfg']))) {
										$olRec = $GLOBALS['TSFE']->sys_page->getPageOverlay($data['uid'], $languageUid);
										if (!count($olRec)) {
											// If no pages_language_overlay record then page can NOT be accessed in the language pointed to by "&L" and therefore we protect the link by setting "&L=0"
											$data['_ADD_GETVARS'] .= '&L=0';
										}
									}
								}
								return TRUE;
							}
						}
					}
				}
			}
		}
	}

	/**
	 * Generating the per-menu-item configuration arrays based on the settings for item states (NO, RO, ACT, CUR etc) set in ->mconf (config for the current menu object)
	 * Basically it will produce an individual array for each menu item based on the item states. BUT in addition the "optionSplit" syntax for the values is ALSO evaluated here so that all property-values are "option-splitted" and the output will thus be resolved.
	 * Is called from the "generate" functions in the extension classes. The function is processor intensive due to the option split feature in particular. But since the generate function is not always called (since the ->result array may be cached, see makeMenu) it doesn't hurt so badly.
	 *
	 * @param int $splitCount Number of menu items in the menu
	 * @return array An array with two keys: array($NOconf,$ROconf) - where $NOconf contains the resolved configuration for each item when NOT rolled-over and $ROconf contains the ditto for the mouseover state (if any)
	 * @access private
	 */
	public function procesItemStates($splitCount) {
		// Prepare normal settings
		if (!is_array($this->mconf['NO.']) && $this->mconf['NO']) {
			// Setting a blank array if NO=1 and there are no properties.
			$this->mconf['NO.'] = array();
		}
		$NOconf = $this->tmpl->splitConfArray($this->mconf['NO.'], $splitCount);
		// Prepare rollOver settings, overriding normal settings
		$ROconf = array();
		if ($this->mconf['RO']) {
			$ROconf = $this->tmpl->splitConfArray($this->mconf['RO.'], $splitCount);
		}
		// Prepare IFSUB settings, overriding normal settings
		// IFSUB is TRUE if there exist submenu items to the current item
		if ($this->mconf['IFSUB']) {
			// Flag: If $IFSUB is generated
			$IFSUBinit = 0;
			foreach ($NOconf as $key => $val) {
				if ($this->isItemState('IFSUB', $key)) {
					// if this is the first IFSUB element, we must generate IFSUB.
					if (!$IFSUBinit) {
						$IFSUBconf = $this->tmpl->splitConfArray($this->mconf['IFSUB.'], $splitCount);
						if ($this->mconf['IFSUBRO']) {
							$IFSUBROconf = $this->tmpl->splitConfArray($this->mconf['IFSUBRO.'], $splitCount);
						}
						$IFSUBinit = 1;
					}
					// Substitute normal with ifsub
					$NOconf[$key] = $IFSUBconf[$key];
					// If rollOver on normal, we must apply a state for rollOver on the active
					if ($ROconf) {
						// If RollOver on active then apply this
						$ROconf[$key] = $IFSUBROconf[$key] ?: $IFSUBconf[$key];
					}
				}
			}
		}
		// Prepare active settings, overriding normal settings
		if ($this->mconf['ACT']) {
			// Flag: If $ACT is generated
			$ACTinit = 0;
			// Find active
			foreach ($NOconf as $key => $val) {
				if ($this->isItemState('ACT', $key)) {
					// If this is the first 'active', we must generate ACT.
					if (!$ACTinit) {
						$ACTconf = $this->tmpl->splitConfArray($this->mconf['ACT.'], $splitCount);
						// Prepare active rollOver settings, overriding normal active settings
						if ($this->mconf['ACTRO']) {
							$ACTROconf = $this->tmpl->splitConfArray($this->mconf['ACTRO.'], $splitCount);
						}
						$ACTinit = 1;
					}
					// Substitute normal with active
					$NOconf[$key] = $ACTconf[$key];
					// If rollOver on normal, we must apply a state for rollOver on the active
					if ($ROconf) {
						// If RollOver on active then apply this
						$ROconf[$key] = $ACTROconf[$key] ?: $ACTconf[$key];
					}
				}
			}
		}
		// Prepare ACT (active)/IFSUB settings, overriding normal settings
		// ACTIFSUB is TRUE if there exist submenu items to the current item and the current item is active
		if ($this->mconf['ACTIFSUB']) {
			// Flag: If $ACTIFSUB is generated
			$ACTIFSUBinit = 0;
			// Find active
			foreach ($NOconf as $key => $val) {
				if ($this->isItemState('ACTIFSUB', $key)) {
					// If this is the first 'active', we must generate ACTIFSUB.
					if (!$ACTIFSUBinit) {
						$ACTIFSUBconf = $this->tmpl->splitConfArray($this->mconf['ACTIFSUB.'], $splitCount);
						// Prepare active rollOver settings, overriding normal active settings
						if ($this->mconf['ACTIFSUBRO']) {
							$ACTIFSUBROconf = $this->tmpl->splitConfArray($this->mconf['ACTIFSUBRO.'], $splitCount);
						}
						$ACTIFSUBinit = 1;
					}
					// Substitute normal with active
					$NOconf[$key] = $ACTIFSUBconf[$key];
					// If rollOver on normal, we must apply a state for rollOver on the active
					if ($ROconf) {
						// If RollOver on active then apply this
						$ROconf[$key] = $ACTIFSUBROconf[$key] ?: $ACTIFSUBconf[$key];
					}
				}
			}
		}
		// Prepare CUR (current) settings, overriding normal settings
		// CUR is TRUE if the current page equals the item here!
		if ($this->mconf['CUR']) {
			// Flag: If $CUR is generated
			$CURinit = 0;
			foreach ($NOconf as $key => $val) {
				if ($this->isItemState('CUR', $key)) {
					// if this is the first 'current', we must generate CUR. Basically this control is just inherited
					// from the other implementations as current would only exist one time and thats it
					// (unless you use special-features of HMENU)
					if (!$CURinit) {
						$CURconf = $this->tmpl->splitConfArray($this->mconf['CUR.'], $splitCount);
						if ($this->mconf['CURRO']) {
							$CURROconf = $this->tmpl->splitConfArray($this->mconf['CURRO.'], $splitCount);
						}
						$CURinit = 1;
					}
					// Substitute normal with current
					$NOconf[$key] = $CURconf[$key];
					// If rollOver on normal, we must apply a state for rollOver on the active
					if ($ROconf) {
						// If RollOver on active then apply this
						$ROconf[$key] = $CURROconf[$key] ?: $CURconf[$key];
					}
				}
			}
		}
		// Prepare CUR (current)/IFSUB settings, overriding normal settings
		// CURIFSUB is TRUE if there exist submenu items to the current item and the current page equals the item here!
		if ($this->mconf['CURIFSUB']) {
			// Flag: If $CURIFSUB is generated
			$CURIFSUBinit = 0;
			foreach ($NOconf as $key => $val) {
				if ($this->isItemState('CURIFSUB', $key)) {
					// If this is the first 'current', we must generate CURIFSUB.
					if (!$CURIFSUBinit) {
						$CURIFSUBconf = $this->tmpl->splitConfArray($this->mconf['CURIFSUB.'], $splitCount);
						// Prepare current rollOver settings, overriding normal current settings
						if ($this->mconf['CURIFSUBRO']) {
							$CURIFSUBROconf = $this->tmpl->splitConfArray($this->mconf['CURIFSUBRO.'], $splitCount);
						}
						$CURIFSUBinit = 1;
					}
					// Substitute normal with active
					$NOconf[$key] = $CURIFSUBconf[$key];
					// If rollOver on normal, we must apply a state for rollOver on the current
					if ($ROconf) {
						// If RollOver on current then apply this
						$ROconf[$key] = $CURIFSUBROconf[$key] ?: $CURIFSUBconf[$key];
					}
				}
			}
		}
		// Prepare active settings, overriding normal settings
		if ($this->mconf['USR']) {
			// Flag: If $USR is generated
			$USRinit = 0;
			// Find active
			foreach ($NOconf as $key => $val) {
				if ($this->isItemState('USR', $key)) {
					// if this is the first active, we must generate USR.
					if (!$USRinit) {
						$USRconf = $this->tmpl->splitConfArray($this->mconf['USR.'], $splitCount);
						// Prepare active rollOver settings, overriding normal active settings
						if ($this->mconf['USRRO']) {
							$USRROconf = $this->tmpl->splitConfArray($this->mconf['USRRO.'], $splitCount);
						}
						$USRinit = 1;
					}
					// Substitute normal with active
					$NOconf[$key] = $USRconf[$key];
					// If rollOver on normal, we must apply a state for rollOver on the active
					if ($ROconf) {
						// If RollOver on active then apply this
						$ROconf[$key] = $USRROconf[$key] ?: $USRconf[$key];
					}
				}
			}
		}
		// Prepare spacer settings, overriding normal settings
		if ($this->mconf['SPC']) {
			// Flag: If $SPC is generated
			$SPCinit = 0;
			// Find spacers
			foreach ($NOconf as $key => $val) {
				if ($this->isItemState('SPC', $key)) {
					// If this is the first spacer, we must generate SPC.
					if (!$SPCinit) {
						$SPCconf = $this->tmpl->splitConfArray($this->mconf['SPC.'], $splitCount);
						$SPCinit = 1;
					}
					// Substitute normal with spacer
					$NOconf[$key] = $SPCconf[$key];
				}
			}
		}
		// Prepare Userdefined settings
		if ($this->mconf['USERDEF1']) {
			// Flag: If $USERDEF1 is generated
			$USERDEF1init = 0;
			// Find active
			foreach ($NOconf as $key => $val) {
				if ($this->isItemState('USERDEF1', $key)) {
					// If this is the first active, we must generate USERDEF1.
					if (!$USERDEF1init) {
						$USERDEF1conf = $this->tmpl->splitConfArray($this->mconf['USERDEF1.'], $splitCount);
						// Prepare active rollOver settings, overriding normal active settings
						if ($this->mconf['USERDEF1RO']) {
							$USERDEF1ROconf = $this->tmpl->splitConfArray($this->mconf['USERDEF1RO.'], $splitCount);
						}
						$USERDEF1init = 1;
					}
					// Substitute normal with active
					$NOconf[$key] = $USERDEF1conf[$key];
					// If rollOver on normal, we must apply a state for rollOver on the active
					if ($ROconf) {
						// If RollOver on active then apply this
						$ROconf[$key] = $USERDEF1ROconf[$key] ?: $USERDEF1conf[$key];
					}
				}
			}
		}
		// Prepare Userdefined settings
		if ($this->mconf['USERDEF2']) {
			// Flag: If $USERDEF2 is generated
			$USERDEF2init = 0;
			// Find active
			foreach ($NOconf as $key => $val) {
				if ($this->isItemState('USERDEF2', $key)) {
					// If this is the first active, we must generate USERDEF2.
					if (!$USERDEF2init) {
						$USERDEF2conf = $this->tmpl->splitConfArray($this->mconf['USERDEF2.'], $splitCount);
						// Prepare active rollOver settings, overriding normal active settings
						if ($this->mconf['USERDEF2RO']) {
							$USERDEF2ROconf = $this->tmpl->splitConfArray($this->mconf['USERDEF2RO.'], $splitCount);
						}
						$USERDEF2init = 1;
					}
					// Substitute normal with active
					$NOconf[$key] = $USERDEF2conf[$key];
					// If rollOver on normal, we must apply a state for rollOver on the active
					if ($ROconf) {
						// If RollOver on active then apply this
						$ROconf[$key] = $USERDEF2ROconf[$key] ?: $USERDEF2conf[$key];
					}
				}
			}
		}
		return array($NOconf, $ROconf);
	}

	/**
	 * Creates the URL, target and onclick values for the menu item link. Returns them in an array as key/value pairs for <A>-tag attributes
	 * This function doesn't care about the url, because if we let the url be redirected, it will be logged in the stat!!!
	 *
	 * @param int $key Pointer to a key in the $this->menuArr array where the value for that key represents the menu item we are linking to (page record)
	 * @param string $altTarget Alternative target
	 * @param int $typeOverride Alternative type
	 * @return array Returns an array with A-tag attributes as key/value pairs (HREF, TARGET and onClick)
	 * @access private
	 */
	public function link($key, $altTarget = '', $typeOverride = '') {
		// Mount points:
		$MP_var = $this->getMPvar($key);
		$MP_params = $MP_var ? '&MP=' . rawurlencode($MP_var) : '';
		// Setting override ID
		if ($this->mconf['overrideId'] || $this->menuArr[$key]['overrideId']) {
			$overrideArray = array();
			// If a user script returned the value overrideId in the menu array we use that as page id
			$overrideArray['uid'] = $this->mconf['overrideId'] ?: $this->menuArr[$key]['overrideId'];
			$overrideArray['alias'] = '';
			// Clear MP parameters since ID was changed.
			$MP_params = '';
		} else {
			$overrideArray = '';
		}
		// Setting main target:
		if ($altTarget) {
			$mainTarget = $altTarget;
		} elseif ($this->mconf['target.']) {
			$mainTarget = $this->parent_cObj->stdWrap($this->mconf['target'], $this->mconf['target.']);
		} else {
			$mainTarget = $this->mconf['target'];
		}
		// Creating link:
		if ($this->mconf['collapse'] && $this->isActive($this->menuArr[$key]['uid'], $this->getMPvar($key))) {
			$thePage = $this->sys_page->getPage($this->menuArr[$key]['pid']);
			$LD = $this->menuTypoLink($thePage, $mainTarget, '', '', $overrideArray, $this->mconf['addParams'] . $MP_params . $this->menuArr[$key]['_ADD_GETVARS'], $typeOverride);
		} else {
			$LD = $this->menuTypoLink($this->menuArr[$key], $mainTarget, '', '', $overrideArray, $this->mconf['addParams'] . $MP_params . $this->I['val']['additionalParams'] . $this->menuArr[$key]['_ADD_GETVARS'], $typeOverride);
		}
		// Override URL if using "External URL" as doktype with a valid e-mail address:
		if ($this->menuArr[$key]['doktype'] == PageRepository::DOKTYPE_LINK && $this->menuArr[$key]['urltype'] == 3 && GeneralUtility::validEmail($this->menuArr[$key]['url'])) {
			// Create mailto-link using \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer::typolink (concerning spamProtectEmailAddresses):
			$LD['totalURL'] = $this->parent_cObj->typoLink_URL(array('parameter' => $this->menuArr[$key]['url']));
			$LD['target'] = '';
		}

		// Override url if current page is a shortcut
		$shortcut = NULL;
		if ($this->menuArr[$key]['doktype'] == PageRepository::DOKTYPE_SHORTCUT && $this->menuArr[$key]['shortcut_mode'] != PageRepository::SHORTCUT_MODE_RANDOM_SUBPAGE) {

			$menuItem = $this->determineOriginalShortcutPage($this->menuArr[$key]);

			try {
				$shortcut = $GLOBALS['TSFE']->getPageShortcut(
					$menuItem['shortcut'],
					$menuItem['shortcut_mode'],
					$menuItem['uid'],
					20,
					array(),
					TRUE
				);
			} catch (\Exception $ex) {

			}
			if (!is_array($shortcut)) {
				return array();
			}
			// Only setting url, not target
			$LD['totalURL'] = $this->parent_cObj->typoLink_URL(array(
				'parameter' => $shortcut['uid'],
				'additionalParams' => $this->mconf['addParams'] . $MP_params . $this->I['val']['additionalParams'] . $menuItem['_ADD_GETVARS'],
				'linkAccessRestrictedPages' => $this->mconf['showAccessRestrictedPages'] && $this->mconf['showAccessRestrictedPages'] !== 'NONE'
			));
		}
		if ($shortcut) {
			$pageData = $shortcut;
			$pageData['_SHORTCUT_PAGE_UID'] = $this->menuArr[$key]['uid'];
		} else {
			$pageData = $this->menuArr[$key];
		}
		// Manipulation in case of access restricted pages:
		$this->changeLinksForAccessRestrictedPages($LD, $pageData, $mainTarget, $typeOverride);
		// Overriding URL / Target if set to do so:
		if ($this->menuArr[$key]['_OVERRIDE_HREF']) {
			$LD['totalURL'] = $this->menuArr[$key]['_OVERRIDE_HREF'];
			if ($this->menuArr[$key]['_OVERRIDE_TARGET']) {
				$LD['target'] = $this->menuArr[$key]['_OVERRIDE_TARGET'];
			}
		}
		// OnClick open in windows.
		$onClick = '';
		if ($this->mconf['JSWindow']) {
			$conf = $this->mconf['JSWindow.'];
			$url = $LD['totalURL'];
			$LD['totalURL'] = '#';
			$onClick = 'openPic(\'' . $GLOBALS['TSFE']->baseUrlWrap($url) . '\',\'' . ($conf['newWindow'] ? md5($url) : 'theNewPage') . '\',\'' . $conf['params'] . '\'); return false;';
			$GLOBALS['TSFE']->setJS('openPic');
		}
		// look for type and popup
		// following settings are valid in field target:
		// 230								will add type=230 to the link
		// 230 500x600						will add type=230 to the link and open in popup window with 500x600 pixels
		// 230 _blank						will add type=230 to the link and open with target "_blank"
		// 230x450:resizable=0,location=1	will open in popup window with 500x600 pixels with settings "resizable=0,location=1"
		$matches = array();
		$targetIsType = $LD['target'] && MathUtility::canBeInterpretedAsInteger($LD['target']) ? (int)$LD['target'] : FALSE;
		if (preg_match('/([0-9]+[\\s])?(([0-9]+)x([0-9]+))?(:.+)?/s', $LD['target'], $matches) || $targetIsType) {
			// has type?
			if ((int)$matches[1] || $targetIsType) {
				$LD['totalURL'] = $this->parent_cObj->URLqMark($LD['totalURL'], '&type=' . ($targetIsType ?: (int)$matches[1]));
				$LD['target'] = $targetIsType ? '' : trim(substr($LD['target'], strlen($matches[1]) + 1));
			}
			// Open in popup window?
			if ($matches[3] && $matches[4]) {
				$JSparamWH = 'width=' . $matches[3] . ',height=' . $matches[4] . ($matches[5] ? ',' . substr($matches[5], 1) : '');
				$onClick = 'vHWin=window.open('
					. GeneralUtility::quoteJSvalue($GLOBALS['TSFE']->baseUrlWrap($LD['totalURL']))
					. ',\'FEopenLink\',\'' . $JSparamWH . '\');vHWin.focus();return false;';
				$LD['target'] = '';
			}
		}
		// out:
		$list = array();
		// Added this check: What it does is to enter the baseUrl (if set, which it should for "realurl" based sites)
		// as URL if the calculated value is empty. The problem is that no link is generated with a blank URL
		// and blank URLs might appear when the realurl encoding is used and a link to the frontpage is generated.
		$list['HREF'] = (string)$LD['totalURL'] !== '' ? $LD['totalURL'] : $GLOBALS['TSFE']->baseUrl;
		$list['TARGET'] = $LD['target'];
		$list['onClick'] = $onClick;
		return $list;
	}

	/**
	 * Determines original shortcut destination in page overlays.
	 *
	 * Since the pages records used for menu rendering are overlaid by default,
	 * the original 'shortcut' value is lost, if a translation did not define one.
	 * The behaviour in TSFE can be compared to the 'mergeIfNotBlank' feature, but
	 * it's hardcoded there and not related to the mentioned setting at all.
	 *
	 * @param array $page
	 * @return array
	 * @todo Once the page_language_overlay behaviour was removed, this method can be removed again
	 */
	protected function determineOriginalShortcutPage(array $page) {
		// Check if modification is required
		if (
			$GLOBALS['TSFE']->sys_language_uid > 0
			&& empty($page['shortcut'])
			&& !empty($page['uid'])
			&& !empty($page['_PAGES_OVERLAY'])
			&& !empty($page['_PAGES_OVERLAY_UID'])
		) {
			// Using raw record since the record was overlaid and is correct already:
			$originalPage = $this->sys_page->getRawRecord('pages', $page['uid']);

			if (!empty($originalPage['shortcut'])) {
				$page['shortcut'] = $originalPage['shortcut'];
			}
		}

		return $page;
	}

	/**
	 * Will change $LD (passed by reference) if the page is access restricted
	 *
	 * @param array $LD The array from the linkData() function
	 * @param array $page Page array
	 * @param string $mainTarget Main target value
	 * @param string $typeOverride Type number override if any
	 * @return void ($LD passed by reference might be changed.)
	 */
	public function changeLinksForAccessRestrictedPages(&$LD, $page, $mainTarget, $typeOverride) {
		// If access restricted pages should be shown in menus, change the link of such pages to link to a redirection page:
		if ($this->mconf['showAccessRestrictedPages'] && $this->mconf['showAccessRestrictedPages'] !== 'NONE' && !$GLOBALS['TSFE']->checkPageGroupAccess($page)) {
			$thePage = $this->sys_page->getPage($this->mconf['showAccessRestrictedPages']);
			$addParams = str_replace(
				array(
					'###RETURN_URL###',
					'###PAGE_ID###'
				),
				array(
					rawurlencode($LD['totalURL']),
					isset($page['_SHORTCUT_PAGE_UID']) ? $page['_SHORTCUT_PAGE_UID'] : $page['uid']
				),
				$this->mconf['showAccessRestrictedPages.']['addParams']
			);
			$LD = $this->menuTypoLink($thePage, $mainTarget, '', '', '', $addParams, $typeOverride);
		}
	}

	/**
	 * Creates a submenu level to the current level - if configured for.
	 *
	 * @param int $uid Page id of the current page for which a submenu MAY be produced (if conditions are met)
	 * @param string $objSuffix Object prefix, see ->start()
	 * @return string HTML content of the submenu
	 * @access private
	 */
	public function subMenu($uid, $objSuffix = '') {
		// Setting alternative menu item array if _SUB_MENU has been defined in the current ->menuArr
		$altArray = '';
		if (is_array($this->menuArr[$this->I['key']]['_SUB_MENU']) && count($this->menuArr[$this->I['key']]['_SUB_MENU'])) {
			$altArray = $this->menuArr[$this->I['key']]['_SUB_MENU'];
		}
		// Make submenu if the page is the next active
		$menuType = $this->conf[($this->menuNumber + 1) . $objSuffix];
		// stdWrap for expAll
		if (isset($this->mconf['expAll.'])) {
			$this->mconf['expAll'] = $this->parent_cObj->stdWrap($this->mconf['expAll'], $this->mconf['expAll.']);
		}
		if (($this->mconf['expAll'] || $this->isNext($uid, $this->getMPvar($this->I['key'])) || is_array($altArray)) && !$this->mconf['sectionIndex']) {
			try {
				$menuObjectFactory = GeneralUtility::makeInstance(\TYPO3\CMS\Frontend\ContentObject\Menu\MenuContentObjectFactory::class);
				$submenu = $menuObjectFactory->getMenuObjectByType($menuType);
				$submenu->entryLevel = $this->entryLevel + 1;
				$submenu->rL_uidRegister = $this->rL_uidRegister;
				$submenu->MP_array = $this->MP_array;
				if ($this->menuArr[$this->I['key']]['_MP_PARAM']) {
					$submenu->MP_array[] = $this->menuArr[$this->I['key']]['_MP_PARAM'];
				}
				// Especially scripts that build the submenu needs the parent data
				$submenu->parent_cObj = $this->parent_cObj;
				$submenu->parentMenuArr = $this->menuArr;
				// Setting alternativeMenuTempArray (will be effective only if an array)
				if (is_array($altArray)) {
					$submenu->alternativeMenuTempArray = $altArray;
				}
				if ($submenu->start($this->tmpl, $this->sys_page, $uid, $this->conf, $this->menuNumber + 1, $objSuffix)) {
					$submenu->makeMenu();
					// Memorize the current menu item count
					$tempCountMenuObj = $GLOBALS['TSFE']->register['count_MENUOBJ'];
					// Reset the menu item count for the submenu
					$GLOBALS['TSFE']->register['count_MENUOBJ'] = 0;
					$content = $submenu->writeMenu();
					// Restore the item count now that the submenu has been handled
					$GLOBALS['TSFE']->register['count_MENUOBJ'] = $tempCountMenuObj;
					$GLOBALS['TSFE']->register['count_menuItems'] = count($this->menuArr);
					return $content;
				}
			} catch (\TYPO3\CMS\Frontend\ContentObject\Menu\Exception\NoSuchMenuTypeException $e) {
			}
		}
	}

	/**
	 * Returns TRUE if the page with UID $uid is the NEXT page in root line (which means a submenu should be drawn)
	 *
	 * @param int $uid Page uid to evaluate.
	 * @param string $MPvar MPvar for the current position of item.
	 * @return bool TRUE if page with $uid is active
	 * @access private
	 * @see subMenu()
	 */
	public function isNext($uid, $MPvar = '') {
		// Check for always active PIDs:
		if (count($this->alwaysActivePIDlist) && in_array($uid, $this->alwaysActivePIDlist)) {
			return TRUE;
		}
		$testUid = $uid . ($MPvar ? ':' . $MPvar : '');
		if ($uid && $testUid == $this->nextActive) {
			return TRUE;
		}
	}

	/**
	 * Returns TRUE if the page with UID $uid is active (in the current rootline)
	 *
	 * @param int $uid Page uid to evaluate.
	 * @param string $MPvar MPvar for the current position of item.
	 * @return bool TRUE if page with $uid is active
	 * @access private
	 */
	public function isActive($uid, $MPvar = '') {
		// Check for always active PIDs:
		if (count($this->alwaysActivePIDlist) && in_array($uid, $this->alwaysActivePIDlist)) {
			return TRUE;
		}
		$testUid = $uid . ($MPvar ? ':' . $MPvar : '');
		if ($uid && in_array('ITEM:' . $testUid, $this->rL_uidRegister)) {
			return TRUE;
		}
	}

	/**
	 * Returns TRUE if the page with UID $uid is the CURRENT page (equals $GLOBALS['TSFE']->id)
	 *
	 * @param int $uid Page uid to evaluate.
	 * @param string $MPvar MPvar for the current position of item.
	 * @return bool TRUE if page $uid = $GLOBALS['TSFE']->id
	 * @access private
	 */
	public function isCurrent($uid, $MPvar = '') {
		$testUid = $uid . ($MPvar ? ':' . $MPvar : '');
		if ($uid && end($this->rL_uidRegister) === 'ITEM:' . $testUid) {
			return TRUE;
		}
	}

	/**
	 * Returns TRUE if there is a submenu with items for the page id, $uid
	 * Used by the item states "IFSUB", "ACTIFSUB" and "CURIFSUB" to check if there is a submenu
	 *
	 * @param int $uid Page uid for which to search for a submenu
	 * @return bool Returns TRUE if there was a submenu with items found
	 * @access private
	 */
	public function isSubMenu($uid) {
		// Looking for a mount-pid for this UID since if that
		// exists we should look for a subpages THERE and not in the input $uid;
		$mount_info = $this->sys_page->getMountPointInfo($uid);
		if (is_array($mount_info)) {
			$uid = $mount_info['mount_pid'];
		}
		$recs = $this->sys_page->getMenu($uid, 'uid,pid,doktype,mount_pid,mount_pid_ol,nav_hide,shortcut,shortcut_mode,l18n_cfg');
		$hasSubPages = FALSE;
		$bannedUids = $this->getBannedUids();
		foreach ($recs as $theRec) {
			// no valid subpage if the document type is excluded from the menu
			if (GeneralUtility::inList($this->doktypeExcludeList, $theRec['doktype'])) {
				continue;
			}
			// No valid subpage if the page is hidden inside menus and
			// it wasn't forced to show such entries
			if ($theRec['nav_hide'] && !$this->conf['includeNotInMenu']) {
				continue;
			}
			// No valid subpage if the default language should be shown and the page settings
			// are excluding the visibility of the default language
			if (!$GLOBALS['TSFE']->sys_language_uid && GeneralUtility::hideIfDefaultLanguage($theRec['l18n_cfg'])) {
				continue;
			}
			// No valid subpage if the alternative language should be shown and the page settings
			// are requiring a valid overlay but it doesn't exists
			$hideIfNotTranslated = GeneralUtility::hideIfNotTranslated($theRec['l18n_cfg']);
			if ($GLOBALS['TSFE']->sys_language_uid && $hideIfNotTranslated && !$theRec['_PAGES_OVERLAY']) {
				continue;
			}
			// No valid subpage if the subpage is banned by excludeUidList
			if (in_array($theRec['uid'], $bannedUids)) {
				continue;
			}
			$hasSubPages = TRUE;
			break;
		}
		return $hasSubPages;
	}

	/**
	 * Used by procesItemStates() to evaluate if a menu item (identified by $key) is in a certain state.
	 *
	 * @param string $kind The item state to evaluate (SPC, IFSUB, ACT etc... but no xxxRO states of course)
	 * @param int $key Key pointing to menu item from ->menuArr
	 * @return bool True (integer!=0) if match, otherwise FALSE (=0, zero)
	 * @access private
	 * @see procesItemStates()
	 */
	public function isItemState($kind, $key) {
		$natVal = 0;
		// If any value is set for ITEM_STATE the normal evaluation is discarded
		if ($this->menuArr[$key]['ITEM_STATE']) {
			if ((string)$this->menuArr[$key]['ITEM_STATE'] === (string)$kind) {
				$natVal = 1;
			}
		} else {
			switch ($kind) {
				case 'SPC':
					$natVal = $this->menuArr[$key]['isSpacer'];
					break;
				case 'IFSUB':
					$natVal = $this->isSubMenu($this->menuArr[$key]['uid']);
					break;
				case 'ACT':
					$natVal = $this->isActive($this->menuArr[$key]['uid'], $this->getMPvar($key));
					break;
				case 'ACTIFSUB':
					$natVal = $this->isActive($this->menuArr[$key]['uid'], $this->getMPvar($key)) && $this->isSubMenu($this->menuArr[$key]['uid']);
					break;
				case 'CUR':
					$natVal = $this->isCurrent($this->menuArr[$key]['uid'], $this->getMPvar($key));
					break;
				case 'CURIFSUB':
					$natVal = $this->isCurrent($this->menuArr[$key]['uid'], $this->getMPvar($key)) && $this->isSubMenu($this->menuArr[$key]['uid']);
					break;
				case 'USR':
					$natVal = $this->menuArr[$key]['fe_group'];
					break;
			}
		}
		return $natVal;
	}

	/**
	 * Creates an access-key for a TMENU/GMENU menu item based on the menu item titles first letter
	 *
	 * @param string $title Menu item title.
	 * @return array Returns an array with keys "code" ("accesskey" attribute for the img-tag) and "alt" (text-addition to the "alt" attribute) if an access key was defined. Otherwise array was empty
	 * @access private
	 */
	public function accessKey($title) {
		// The global array ACCESSKEY is used to globally control if letters are already used!!
		$result = array();
		$title = trim(strip_tags($title));
		$titleLen = strlen($title);
		for ($a = 0; $a < $titleLen; $a++) {
			$key = strtoupper(substr($title, $a, 1));
			if (preg_match('/[A-Z]/', $key) && !isset($GLOBALS['TSFE']->accessKey[$key])) {
				$GLOBALS['TSFE']->accessKey[$key] = 1;
				$result['code'] = ' accesskey="' . $key . '"';
				$result['alt'] = ' (ALT+' . $key . ')';
				$result['key'] = $key;
				break;
			}
		}
		return $result;
	}

	/**
	 * Calls a user function for processing of internal data.
	 * Used for the properties "IProcFunc" and "itemArrayProcFunc"
	 *
	 * @param string $mConfKey Key pointing for the property in the current ->mconf array holding possibly parameters to pass along to the function/method. Currently the keys used are "IProcFunc" and "itemArrayProcFunc".
	 * @param mixed $passVar A variable to pass to the user function and which should be returned again from the user function. The idea is that the user function modifies this variable according to what you want to achieve and then returns it. For "itemArrayProcFunc" this variable is $this->menuArr, for "IProcFunc" it is $this->I
	 * @return mixed The processed $passVar
	 * @access private
	 */
	public function userProcess($mConfKey, $passVar) {
		if ($this->mconf[$mConfKey]) {
			$funcConf = $this->mconf[$mConfKey . '.'];
			$funcConf['parentObj'] = $this;
			$passVar = $this->parent_cObj->callUserFunction($this->mconf[$mConfKey], $funcConf, $passVar);
		}
		return $passVar;
	}

	/**
	 * Creates the <A> tag parts for the current item (in $this->I, [A1] and [A2]) based on other information in this array (like $this->I['linkHREF'])
	 *
	 * @return void
	 * @access private
	 */
	public function setATagParts() {
		$params = trim($this->I['val']['ATagParams']) . $this->I['accessKey']['code'];
		$params = $params !== '' ? ' ' . $params : '';
		$this->I['A1'] = '<a ' . GeneralUtility::implodeAttributes($this->I['linkHREF'], 1) . $params . '>';
		$this->I['A2'] = '</a>';
	}

	/**
	 * Returns the title for the navigation
	 *
	 * @param string $title The current page title
	 * @param string $nav_title The current value of the navigation title
	 * @return string Returns the navigation title if it is NOT blank, otherwise the page title.
	 * @access private
	 */
	public function getPageTitle($title, $nav_title) {
		return trim($nav_title) !== '' ? $nav_title : $title;
	}

	/**
	 * Return MPvar string for entry $key in ->menuArr
	 *
	 * @param int $key Pointer to element in ->menuArr
	 * @return string MP vars for element.
	 * @see link()
	 */
	public function getMPvar($key) {
		if ($GLOBALS['TYPO3_CONF_VARS']['FE']['enable_mount_pids']) {
			$localMP_array = $this->MP_array;
			// NOTICE: "_MP_PARAM" is allowed to be a commalist of PID pairs!
			if ($this->menuArr[$key]['_MP_PARAM']) {
				$localMP_array[] = $this->menuArr[$key]['_MP_PARAM'];
			}
			$MP_params = count($localMP_array) ? implode(',', $localMP_array) : '';
			return $MP_params;
		}
	}

	/**
	 * Returns where clause part to exclude 'not in menu' pages
	 *
	 * @return string where clause part.
	 * @access private
	 */
	public function getDoktypeExcludeWhere() {
		return $this->doktypeExcludeList ? ' AND pages.doktype NOT IN (' . $this->doktypeExcludeList . ')' : '';
	}

	/**
	 * Returns an array of banned UIDs (from excludeUidList)
	 *
	 * @return array Array of banned UIDs
	 * @access private
	 */
	public function getBannedUids() {
		$excludeUidList = isset($this->conf['excludeUidList.'])
			? $this->parent_cObj->stdWrap($this->conf['excludeUidList'], $this->conf['excludeUidList.'])
			: $this->conf['excludeUidList'];

		if (!trim($excludeUidList)) {
			return array();
		}

		$banUidList = str_replace('current', $GLOBALS['TSFE']->page['uid'], $excludeUidList);
		return GeneralUtility::intExplode(',', $banUidList);
	}

	/**
	 * Calls typolink to create menu item links.
	 *
	 * @param array $page Page record (uid points where to link to)
	 * @param string $oTarget Target frame/window
	 * @param bool $no_cache TRUE if caching should be disabled
	 * @param string $script Alternative script name
	 * @param array $overrideArray Array to override values in $page
	 * @param string $addParams Parameters to add to URL
	 * @param array $typeOverride "type" value
	 * @return array See linkData
	 */
	public function menuTypoLink($page, $oTarget, $no_cache, $script, $overrideArray = '', $addParams = '', $typeOverride = '') {
		$conf = array(
			'parameter' => is_array($overrideArray) && $overrideArray['uid'] ? $overrideArray['uid'] : $page['uid']
		);
		if ($typeOverride && MathUtility::canBeInterpretedAsInteger($typeOverride)) {
			$conf['parameter'] .= ',' . $typeOverride;
		}
		if ($addParams) {
			$conf['additionalParams'] = $addParams;
		}
		if ($no_cache) {
			$conf['no_cache'] = TRUE;
		} elseif ($this->useCacheHash) {
			$conf['useCacheHash'] = TRUE;
		}
		if ($oTarget) {
			$conf['target'] = $oTarget;
		}
		if ($page['sectionIndex_uid']) {
			$conf['section'] = $page['sectionIndex_uid'];
		}
		$conf['linkAccessRestrictedPages'] = $this->mconf['showAccessRestrictedPages'] && $this->mconf['showAccessRestrictedPages'] !== 'NONE';
		$this->parent_cObj->typoLink('|', $conf);
		$LD = $this->parent_cObj->lastTypoLinkLD;
		$LD['totalURL'] = $this->parent_cObj->lastTypoLinkUrl;
		return $LD;
	}

	/**
	 * Generates a list of content objects with sectionIndex enabled
	 * available on a specific page
	 *
	 * Used for menus with sectionIndex enabled
	 *
	 * @param string $altSortField Alternative sorting field
	 * @param int $pid The page id to search for sections
	 * @throws UnexpectedValueException if the query to fetch the content elements unexpectedly fails
	 * @return array
	 */
	protected function sectionIndex($altSortField, $pid = NULL) {
		$pid = (int)($pid ?: $this->id);
		$basePageRow = $this->sys_page->getPage($pid);
		if (!is_array($basePageRow)) {
			return array();
		}
		$configuration = $this->mconf['sectionIndex.'];
		$useColPos = 0;
		if (trim($configuration['useColPos']) !== '' || is_array($configuration['useColPos.'])) {
			$useColPos = $GLOBALS['TSFE']->cObj->stdWrap($configuration['useColPos'], $configuration['useColPos.']);
			$useColPos = (int)$useColPos;
		}
		$selectSetup = array(
			'pidInList' => $pid,
			'orderBy' => $altSortField,
			'languageField' => 'sys_language_uid',
			'where' => $useColPos >= 0 ? 'colPos=' . $useColPos : ''
		);
		$resource = $this->parent_cObj->exec_getQuery('tt_content', $selectSetup);
		if (!$resource) {
			$message = 'SectionIndex: Query to fetch the content elements failed!';
			throw new \UnexpectedValueException($message, 1337334849);
		}
		$result = array();
		while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($resource)) {
			$this->sys_page->versionOL('tt_content', $row);
			if ($GLOBALS['TSFE']->sys_language_contentOL && $basePageRow['_PAGES_OVERLAY_LANGUAGE']) {
				$row = $this->sys_page->getRecordOverlay('tt_content', $row, $basePageRow['_PAGES_OVERLAY_LANGUAGE'], $GLOBALS['TSFE']->sys_language_contentOL);
			}
			if ($this->mconf['sectionIndex.']['type'] !== 'all') {
				$doIncludeInSectionIndex = $row['sectionIndex'] >= 1;
				$doHeaderCheck = $this->mconf['sectionIndex.']['type'] === 'header';
				$isValidHeader = ((int)$row['header_layout'] !== 100 || !empty($this->mconf['sectionIndex.']['includeHiddenHeaders'])) && trim($row['header']) !== '';
				if (!$doIncludeInSectionIndex || $doHeaderCheck && !$isValidHeader) {
					continue;
				}
			}
			if (is_array($row)) {
				$uid = $row['uid'];
				$result[$uid] = $basePageRow;
				$result[$uid]['title'] = $row['header'];
				$result[$uid]['nav_title'] = $row['header'];
				$result[$uid]['subtitle'] = $row['subheader'];
				$result[$uid]['starttime'] = $row['starttime'];
				$result[$uid]['endtime'] = $row['endtime'];
				$result[$uid]['fe_group'] = $row['fe_group'];
				$result[$uid]['media'] = $row['media'];
				$result[$uid]['header_layout'] = $row['header_layout'];
				$result[$uid]['bodytext'] = $row['bodytext'];
				$result[$uid]['image'] = $row['image'];
				$result[$uid]['sectionIndex_uid'] = $uid;
			}
		}
		$GLOBALS['TYPO3_DB']->sql_free_result($resource);
		return $result;
	}

	/**
	 * Returns the sys_page object
	 *
	 * @return \TYPO3\CMS\Frontend\Page\PageRepository
	 */
	public function getSysPage() {
		return $this->sys_page;
	}

	/**
	 * Returns the parent content object
	 *
	 * @return \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer
	 */
	public function getParentContentObject() {
		return $this->parent_cObj;
	}

}
