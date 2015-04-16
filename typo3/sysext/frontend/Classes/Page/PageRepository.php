<?php
namespace TYPO3\CMS\Frontend\Page;

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

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Versioning\VersionState;

/**
 * Page functions, a lot of sql/pages-related functions
 *
 * Mainly used in the frontend but also in some cases in the backend. It's
 * important to set the right $where_hid_del in the object so that the
 * functions operate properly
 *
 * @author Kasper Skårhøj <kasperYYYY@typo3.com>
 * @see \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController::fetch_the_id()
 */
class PageRepository {

	/**
	 * @var array
	 */
	public $urltypes = array('', 'http://', 'ftp://', 'mailto:', 'https://');

	/**
	 * This is not the final clauses. There will normally be conditions for the
	 * hidden, starttime and endtime fields as well. You MUST initialize the object
	 * by the init() function
	 *
	 * @var string
	 */
	public $where_hid_del = ' AND pages.deleted=0';

	/**
	 * Clause for fe_group access
	 *
	 * @var string
	 */
	public $where_groupAccess = '';

	/**
	 * @var int
	 */
	public $sys_language_uid = 0;

	/**
	 * If TRUE, versioning preview of other record versions is allowed. THIS MUST
	 * ONLY BE SET IF the page is not cached and truely previewed by a backend
	 * user!!!
	 *
	 * @var bool
	 */
	public $versioningPreview = FALSE;

	/**
	 * Workspace ID for preview
	 *
	 * @var int
	 */
	public $versioningWorkspaceId = 0;

	/**
	 * @var array
	 */
	public $workspaceCache = array();

	/**
	 * Error string set by getRootLine()
	 *
	 * @var string
	 */
	public $error_getRootLine = '';

	/**
	 * Error uid set by getRootLine()
	 *
	 * @var int
	 */
	public $error_getRootLine_failPid = 0;

	/**
	 * @var array
	 */
	protected $cache_getRootLine = array();

	/**
	 * @var array
	 */
	protected $cache_getPage = array();

	/**
	 * @var array
	 */
	protected $cache_getPage_noCheck = array();

	/**
	 * @var array
	 */
	protected $cache_getPageIdFromAlias = array();

	/**
	 * @var array
	 */
	protected $cache_getMountPointInfo = array();

	/**
	 * @var array
	 */
	protected $tableNamesAllowedOnRootLevel = array(
		'sys_file_metadata',
		'sys_category',
	);

	/**
	 * Named constants for "magic numbers" of the field doktype
	 */
	const DOKTYPE_DEFAULT = 1;
	const DOKTYPE_LINK = 3;
	const DOKTYPE_SHORTCUT = 4;
	const DOKTYPE_BE_USER_SECTION = 6;
	const DOKTYPE_MOUNTPOINT = 7;
	const DOKTYPE_SPACER = 199;
	const DOKTYPE_SYSFOLDER = 254;
	const DOKTYPE_RECYCLER = 255;

	/**
	 * Named constants for "magic numbers" of the field shortcut_mode
	 */
	const SHORTCUT_MODE_NONE = 0;
	const SHORTCUT_MODE_FIRST_SUBPAGE = 1;
	const SHORTCUT_MODE_RANDOM_SUBPAGE = 2;
	const SHORTCUT_MODE_PARENT_PAGE = 3;

	/**
	 * init() MUST be run directly after creating a new template-object
	 * This sets the internal variable $this->where_hid_del to the correct where
	 * clause for page records taking deleted/hidden/starttime/endtime/t3ver_state
	 * into account
	 *
	 * @param bool $show_hidden If $show_hidden is TRUE, the hidden-field is ignored!! Normally this should be FALSE. Is used for previewing.
	 * @return void
	 * @see \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController::fetch_the_id(), \TYPO3\CMS\Tstemplate\Controller\TemplateAnalyzerModuleFunctionController::initialize_editor()
	 */
	public function init($show_hidden) {
		$this->where_groupAccess = '';
		$this->where_hid_del = ' AND pages.deleted=0 ';
		if (!$show_hidden) {
			$this->where_hid_del .= 'AND pages.hidden=0 ';
		}
		$this->where_hid_del .= 'AND pages.starttime<=' . $GLOBALS['SIM_ACCESS_TIME'] . ' AND (pages.endtime=0 OR pages.endtime>' . $GLOBALS['SIM_ACCESS_TIME'] . ') ';
		// Filter out new/deleted place-holder pages in case we are NOT in a
		// versioning preview (that means we are online!)
		if (!$this->versioningPreview) {
			$this->where_hid_del .= ' AND NOT pages.t3ver_state>' . new VersionState(VersionState::DEFAULT_STATE);
		} else {
			// For version previewing, make sure that enable-fields are not
			// de-selecting hidden pages - we need versionOL() to unset them only
			// if the overlay record instructs us to.
			// Copy where_hid_del to other variable (used in relation to versionOL())
			$this->versioningPreview_where_hid_del = $this->where_hid_del;
			// Clear where_hid_del
			$this->where_hid_del = ' AND pages.deleted=0 ';
			// Restrict to live and current workspaces
			$this->where_hid_del .= ' AND (pages.t3ver_wsid=0 OR pages.t3ver_wsid=' . (int)$this->versioningWorkspaceId . ')';
		}
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS'][PageRepository::class]['init'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS'][PageRepository::class]['init'] as $classRef) {
				$hookObject = GeneralUtility::makeInstance($classRef);
				if (!$hookObject instanceof PageRepositoryInitHookInterface) {
					throw new \UnexpectedValueException($hookObject . ' must implement interface TYPO3\\CMS\\Frontend\\Page\\PageRepositoryInitHookInterface', 1379579812);
				}
				$hookObject->init_postProcess($this);
			}
		}
	}

	/**************************
	 *
	 * Selecting page records
	 *
	 **************************/

	/**
	 * Returns the $row for the page with uid = $uid (observing ->where_hid_del)
	 * Any pages_language_overlay will be applied before the result is returned.
	 * If no page is found an empty array is returned.
	 *
	 * @param int $uid The page id to look up.
	 * @param bool $disableGroupAccessCheck If set, the check for group access is disabled. VERY rarely used
	 * @throws \UnexpectedValueException
	 * @return array The page row with overlayed localized fields. Empty it no page.
	 * @see getPage_noCheck()
	 */
	public function getPage($uid, $disableGroupAccessCheck = FALSE) {
		// Hook to manipulate the page uid for special overlay handling
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_page.php']['getPage'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_page.php']['getPage'] as $classRef) {
				$hookObject = GeneralUtility::getUserObj($classRef);
				if (!$hookObject instanceof \TYPO3\CMS\Frontend\Page\PageRepositoryGetPageHookInterface) {
					throw new \UnexpectedValueException('$hookObject must implement interface ' . \TYPO3\CMS\Frontend\Page\PageRepositoryGetPageHookInterface::class, 1251476766);
				}
				$hookObject->getPage_preProcess($uid, $disableGroupAccessCheck, $this);
			}
		}
		$accessCheck = $disableGroupAccessCheck ? '' : $this->where_groupAccess;
		$cacheKey = md5($accessCheck . '-' . $this->where_hid_del . '-' . $this->sys_language_uid);
		if (is_array($this->cache_getPage[$uid][$cacheKey])) {
			return $this->cache_getPage[$uid][$cacheKey];
		}
		$result = array();
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'pages', 'uid=' . (int)$uid . $this->where_hid_del . $accessCheck);
		$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		$GLOBALS['TYPO3_DB']->sql_free_result($res);
		if ($row) {
			$this->versionOL('pages', $row);
			if (is_array($row)) {
				$result = $this->getPageOverlay($row);
			}
		}
		$this->cache_getPage[$uid][$cacheKey] = $result;
		return $result;
	}

	/**
	 * Return the $row for the page with uid = $uid WITHOUT checking for
	 * ->where_hid_del (start- and endtime or hidden). Only "deleted" is checked!
	 *
	 * @param int $uid The page id to look up
	 * @return array The page row with overlayed localized fields. Empty array if no page.
	 * @see getPage()
	 */
	public function getPage_noCheck($uid) {
		if ($this->cache_getPage_noCheck[$uid]) {
			return $this->cache_getPage_noCheck[$uid];
		}
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'pages', 'uid=' . (int)$uid . $this->deleteClause('pages'));
		$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		$GLOBALS['TYPO3_DB']->sql_free_result($res);
		$result = array();
		if ($row) {
			$this->versionOL('pages', $row);
			if (is_array($row)) {
				$result = $this->getPageOverlay($row);
			}
		}
		$this->cache_getPage_noCheck[$uid] = $result;
		return $result;
	}

	/**
	 * Returns the $row of the first web-page in the tree (for the default menu...)
	 *
	 * @param int $uid The page id for which to fetch first subpages (PID)
	 * @return mixed If found: The page record (with overlayed localized fields, if any). If NOT found: blank value (not array!)
	 * @see \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController::fetch_the_id()
	 */
	public function getFirstWebPage($uid) {
		$output = '';
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'pages', 'pid=' . (int)$uid . $this->where_hid_del . $this->where_groupAccess, '', 'sorting', '1');
		$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		$GLOBALS['TYPO3_DB']->sql_free_result($res);
		if ($row) {
			$this->versionOL('pages', $row);
			if (is_array($row)) {
				$output = $this->getPageOverlay($row);
			}
		}
		return $output;
	}

	/**
	 * Returns a pagerow for the page with alias $alias
	 *
	 * @param string $alias The alias to look up the page uid for.
	 * @return int Returns page uid (int) if found, otherwise 0 (zero)
	 * @see \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController::checkAndSetAlias(), ContentObjectRenderer::typoLink()
	 */
	public function getPageIdFromAlias($alias) {
		$alias = strtolower($alias);
		if ($this->cache_getPageIdFromAlias[$alias]) {
			return $this->cache_getPageIdFromAlias[$alias];
		}
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid', 'pages', 'alias=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($alias, 'pages') . ' AND pid>=0 AND pages.deleted=0');
		// "AND pid>=0" because of versioning (means that aliases sent MUST be online!)
		$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		$GLOBALS['TYPO3_DB']->sql_free_result($res);
		if ($row) {
			$this->cache_getPageIdFromAlias[$alias] = $row['uid'];
			return $row['uid'];
		}
		$this->cache_getPageIdFromAlias[$alias] = 0;
		return 0;
	}

	/**
	 * Returns the relevant page overlay record fields
	 *
	 * @param mixed $pageInput If $pageInput is an integer, it's the pid of the pageOverlay record and thus the page overlay record is returned. If $pageInput is an array, it's a page-record and based on this page record the language record is found and OVERLAYED before the page record is returned.
	 * @param int $lUid Language UID if you want to set an alternative value to $this->sys_language_uid which is default. Should be >=0
	 * @throws \UnexpectedValueException
	 * @return array Page row which is overlayed with language_overlay record (or the overlay record alone)
	 */
	public function getPageOverlay($pageInput, $lUid = -1) {
		$rows = $this->getPagesOverlay(array($pageInput), $lUid);
		// Always an array in return
		return count($rows) ? $rows[0] : array();
	}

	/**
	 * Returns the relevant page overlay record fields
	 *
	 * @param array $pagesInput Array of integers or array of arrays. If each value is an integer, it's the pids of the pageOverlay records and thus the page overlay records are returned. If each value is an array, it's page-records and based on this page records the language records are found and OVERLAYED before the page records are returned.
	 * @param int $lUid Language UID if you want to set an alternative value to $this->sys_language_uid which is default. Should be >=0
	 * @throws \UnexpectedValueException
	 * @return array Page rows which are overlayed with language_overlay record.
	 *			   If the input was an array of integers, missing records are not
	 *			   included. If the input were page rows, untranslated pages
	 *			   are returned.
	 */
	public function getPagesOverlay(array $pagesInput, $lUid = -1) {
		if (count($pagesInput) == 0) {
			return array();
		}
		// Initialize:
		if ($lUid < 0) {
			$lUid = $this->sys_language_uid;
		}
		$row = NULL;
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_page.php']['getPageOverlay'])) {
			foreach ($pagesInput as $origPage) {
				foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_page.php']['getPageOverlay'] as $classRef) {
					$hookObject = GeneralUtility::makeInstance($classRef);
					if (!$hookObject instanceof \TYPO3\CMS\Frontend\Page\PageRepositoryGetPageOverlayHookInterface) {
						throw new \UnexpectedValueException('$hookObject must implement interface ' . \TYPO3\CMS\Frontend\Page\PageRepositoryGetPageOverlayHookInterface::class, 1269878881);
					}
					$hookObject->getPageOverlay_preProcess($origPage, $lUid, $this);
				}
			}
		}
		// If language UID is different from zero, do overlay:
		if ($lUid) {
			$fieldArr = GeneralUtility::trimExplode(',', $GLOBALS['TYPO3_CONF_VARS']['FE']['pageOverlayFields'], TRUE);
			$page_ids = array();

			$origPage = reset($pagesInput);
			if (is_array($origPage)) {
				// Make sure that only fields which exist in the first incoming record are overlaid!
				$fieldArr = array_intersect($fieldArr, array_keys($origPage));
			}
			foreach ($pagesInput as $origPage) {
				if (is_array($origPage)) {
					// Was the whole record
					$page_ids[] = $origPage['uid'];
				} else {
					// Was the id
					$page_ids[] = $origPage;
				}
			}
			if (count($fieldArr)) {
				if (!in_array('pid', $fieldArr)) {
					$fieldArr[] = 'pid';
				}
				// NOTE to enabledFields('pages_language_overlay'):
				// Currently the showHiddenRecords of TSFE set will allow
				// pages_language_overlay records to be selected as they are
				// child-records of a page.
				// However you may argue that the showHiddenField flag should
				// determine this. But that's not how it's done right now.
				// Selecting overlay record:
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
					implode(',', $fieldArr),
					'pages_language_overlay',
					'pid IN(' . implode(',', $GLOBALS['TYPO3_DB']->cleanIntArray($page_ids)) . ')'
						. ' AND sys_language_uid=' . (int)$lUid . $this->enableFields('pages_language_overlay'),
					'',
					''
				);
				$overlays = array();
				while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
					$this->versionOL('pages_language_overlay', $row);
					if (is_array($row)) {
						$row['_PAGES_OVERLAY'] = TRUE;
						$row['_PAGES_OVERLAY_UID'] = $row['uid'];
						$row['_PAGES_OVERLAY_LANGUAGE'] = $lUid;
						$origUid = $row['pid'];
						// Unset vital fields that are NOT allowed to be overlaid:
						unset($row['uid']);
						unset($row['pid']);
						$overlays[$origUid] = $row;
					}
				}
				$GLOBALS['TYPO3_DB']->sql_free_result($res);
			}
		}
		// Create output:
		$pagesOutput = array();
		foreach ($pagesInput as $key => $origPage) {
			if (is_array($origPage)) {
				$pagesOutput[$key] = $origPage;
				if (isset($overlays[$origPage['uid']])) {
					// Overwrite the original field with the overlay
					foreach ($overlays[$origPage['uid']] as $fieldName => $fieldValue) {
						if ($fieldName !== 'uid' && $fieldName !== 'pid') {
							if ($this->shouldFieldBeOverlaid('pages_language_overlay', $fieldName, $fieldValue)) {
								$pagesOutput[$key][$fieldName] = $fieldValue;
							}
						}
					}
				}
			} else {
				if (isset($overlays[$origPage])) {
					$pagesOutput[$key] = $overlays[$origPage];
				}
			}
		}
		return $pagesOutput;
	}

	/**
	 * Creates language-overlay for records in general (where translation is found
	 * in records from the same table)
	 *
	 * @param string $table Table name
	 * @param array $row Record to overlay. Must containt uid, pid and $table]['ctrl']['languageField']
	 * @param int $sys_language_content Pointer to the sys_language uid for content on the site.
	 * @param string $OLmode Overlay mode. If "hideNonTranslated" then records without translation will not be returned  un-translated but unset (and return value is FALSE)
	 * @throws \UnexpectedValueException
	 * @return mixed Returns the input record, possibly overlaid with a translation.  But if $OLmode is "hideNonTranslated" then it will return FALSE if no translation is found.
	 */
	public function getRecordOverlay($table, $row, $sys_language_content, $OLmode = '') {
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_page.php']['getRecordOverlay'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_page.php']['getRecordOverlay'] as $classRef) {
				$hookObject = GeneralUtility::getUserObj($classRef);
				if (!$hookObject instanceof \TYPO3\CMS\Frontend\Page\PageRepositoryGetRecordOverlayHookInterface) {
					throw new \UnexpectedValueException('$hookObject must implement interface ' . \TYPO3\CMS\Frontend\Page\PageRepositoryGetRecordOverlayHookInterface::class, 1269881658);
				}
				$hookObject->getRecordOverlay_preProcess($table, $row, $sys_language_content, $OLmode, $this);
			}
		}
		if ($row['uid'] > 0 && ($row['pid'] > 0 || in_array($table, $this->tableNamesAllowedOnRootLevel))) {
			if ($GLOBALS['TCA'][$table] && $GLOBALS['TCA'][$table]['ctrl']['languageField'] && $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField']) {
				if (!$GLOBALS['TCA'][$table]['ctrl']['transOrigPointerTable']) {
					// Will not be able to work with other tables (Just didn't implement it yet;
					// Requires a scan over all tables [ctrl] part for first FIND the table that
					// carries localization information for this table (which could even be more
					// than a single table) and then use that. Could be implemented, but obviously
					// takes a little more....) Will try to overlay a record only if the
					// sys_language_content value is larger than zero.
					if ($sys_language_content > 0) {
						// Must be default language or [All], otherwise no overlaying:
						if ($row[$GLOBALS['TCA'][$table]['ctrl']['languageField']] <= 0) {
							// Select overlay record:
							$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', $table, 'pid=' . (int)$row['pid'] . ' AND ' . $GLOBALS['TCA'][$table]['ctrl']['languageField'] . '=' . (int)$sys_language_content . ' AND ' . $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'] . '=' . (int)$row['uid'] . $this->enableFields($table), '', '', '1');
							$olrow = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
							$GLOBALS['TYPO3_DB']->sql_free_result($res);
							$this->versionOL($table, $olrow);
							// Merge record content by traversing all fields:
							if (is_array($olrow)) {
								if (isset($olrow['_ORIG_uid'])) {
									$row['_ORIG_uid'] = $olrow['_ORIG_uid'];
								}
								if (isset($olrow['_ORIG_pid'])) {
									$row['_ORIG_pid'] = $olrow['_ORIG_pid'];
								}
								foreach ($row as $fN => $fV) {
									if ($fN != 'uid' && $fN != 'pid' && isset($olrow[$fN])) {
										if ($this->shouldFieldBeOverlaid($table, $fN, $olrow[$fN])) {
											$row[$fN] = $olrow[$fN];
										}
									} elseif ($fN == 'uid') {
										$row['_LOCALIZED_UID'] = $olrow['uid'];
									}
								}
							} elseif ($OLmode === 'hideNonTranslated' && $row[$GLOBALS['TCA'][$table]['ctrl']['languageField']] == 0) {
								// Unset, if non-translated records should be hidden. ONLY done if the source
								// record really is default language and not [All] in which case it is allowed.
								unset($row);
							}
						} elseif ($sys_language_content != $row[$GLOBALS['TCA'][$table]['ctrl']['languageField']]) {
							unset($row);
						}
					} else {
						// When default language is displayed, we never want to return a record carrying
						// another language!
						if ($row[$GLOBALS['TCA'][$table]['ctrl']['languageField']] > 0) {
							unset($row);
						}
					}
				}
			}
		}
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_page.php']['getRecordOverlay'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_page.php']['getRecordOverlay'] as $classRef) {
				$hookObject = GeneralUtility::getUserObj($classRef);
				if (!$hookObject instanceof \TYPO3\CMS\Frontend\Page\PageRepositoryGetRecordOverlayHookInterface) {
					throw new \UnexpectedValueException('$hookObject must implement interface ' . \TYPO3\CMS\Frontend\Page\PageRepositoryGetRecordOverlayHookInterface::class, 1269881659);
				}
				$hookObject->getRecordOverlay_postProcess($table, $row, $sys_language_content, $OLmode, $this);
			}
		}
		return $row;
	}

	/************************************************
	 *
	 * Page related: Menu, Domain record, Root line
	 *
	 ************************************************/

	/**
	 * Returns an array with pagerows for subpages with pid=$uid (which is pid
	 * here!). This is used for menus. If there are mount points in overlay mode
	 * the _MP_PARAM field is set to the corret MPvar.
	 *
	 * If the $uid being input does in itself require MPvars to define a correct
	 * rootline these must be handled externally to this function.
	 *
	 * @param int|int[] $uid The page id (or array of page ids) for which to fetch subpages (PID)
	 * @param string $fields List of fields to select. Default is "*" = all
	 * @param string $sortField The field to sort by. Default is "sorting
	 * @param string $addWhere Optional additional where clauses. Like "AND title like '%blabla%'" for instance.
	 * @param bool $checkShortcuts Check if shortcuts exist, checks by default
	 * @return array Array with key/value pairs; keys are page-uid numbers. values are the corresponding page records (with overlayed localized fields, if any)
	 * @see \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController::getPageShortcut(), \TYPO3\CMS\Frontend\ContentObject\Menu\AbstractMenuContentObject::makeMenu()
	 * @see \TYPO3\CMS\WizardCrpages\Controller\CreatePagesWizardModuleFunctionController, \TYPO3\CMS\WizardSortpages\View\SortPagesWizardModuleFunction
	 */
	public function getMenu($uid, $fields = '*', $sortField = 'sorting', $addWhere = '', $checkShortcuts = TRUE) {
		$output = array();
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
			$fields,
			'pages',
			'pid IN (' . implode(',', $GLOBALS['TYPO3_DB']->cleanIntArray((array)$uid)) . ')' . $this->where_hid_del
				. $this->where_groupAccess . ' ' . $addWhere,
			'',
			$sortField
		);
		while (($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))) {
			$this->versionOL('pages', $row, TRUE);
			if (is_array($row)) {
				// Keep mount point:
				$origUid = $row['uid'];
				// $row MUST have "uid", "pid", "doktype", "mount_pid", "mount_pid_ol" fields
				// in it
				$mount_info = $this->getMountPointInfo($origUid, $row);
				// There is a valid mount point.
				if (is_array($mount_info) && $mount_info['overlay']) {
					// Using "getPage" is OK since we need the check for enableFields AND for type 2
					// of mount pids we DO require a doktype < 200!
					$mp_row = $this->getPage($mount_info['mount_pid']);
					if (count($mp_row)) {
						$row = $mp_row;
						$row['_MP_PARAM'] = $mount_info['MPvar'];
					} else {
						unset($row);
					}
				}
				// If shortcut, look up if the target exists and is currently visible
				if ($row['doktype'] == self::DOKTYPE_SHORTCUT && ($row['shortcut'] || $row['shortcut_mode']) && $checkShortcuts) {
					if ($row['shortcut_mode'] == self::SHORTCUT_MODE_NONE) {
						// No shortcut_mode set, so target is directly set in $row['shortcut']
						$searchField = 'uid';
						$searchUid = (int)$row['shortcut'];
					} elseif ($row['shortcut_mode'] == self::SHORTCUT_MODE_FIRST_SUBPAGE || $row['shortcut_mode'] == self::SHORTCUT_MODE_RANDOM_SUBPAGE) {
						// Check subpages - first subpage or random subpage
						$searchField = 'pid';
						// If a shortcut mode is set and no valid page is given to select subpags
						// from use the actual page.
						$searchUid = (int)$row['shortcut'] ?: $row['uid'];
					} elseif ($row['shortcut_mode'] == self::SHORTCUT_MODE_PARENT_PAGE) {
						// Shortcut to parent page
						$searchField = 'uid';
						$searchUid = $row['pid'];
					}
					$count = $GLOBALS['TYPO3_DB']->exec_SELECTcountRows('uid', 'pages', $searchField . '=' . $searchUid . $this->where_hid_del . $this->where_groupAccess . ' ' . $addWhere);
					if (!$count) {
						unset($row);
					}
				} elseif ($row['doktype'] == self::DOKTYPE_SHORTCUT && $checkShortcuts) {
					// Neither shortcut target nor mode is set. Remove the page from the menu.
					unset($row);
				}
				if (is_array($row)) {
					$output[$origUid] = $row;
				}
			}
		}
		$GLOBALS['TYPO3_DB']->sql_free_result($res);
        // Finally load language overlays
		return $this->getPagesOverlay($output);
	}

	/**
	 * Will find the page carrying the domain record matching the input domain.
	 * Might exit after sending a redirect-header IF a found domain record
	 * instructs to do so.
	 *
	 * @param string $domain Domain name to search for. Eg. "www.typo3.com". Typical the HTTP_HOST value.
	 * @param string $path Path for the current script in domain. Eg. "/somedir/subdir". Typ. supplied by \TYPO3\CMS\Core\Utility\GeneralUtility::getIndpEnv('SCRIPT_NAME')
	 * @param string $request_uri Request URI: Used to get parameters from if they should be appended. Typ. supplied by \TYPO3\CMS\Core\Utility\GeneralUtility::getIndpEnv('REQUEST_URI')
	 * @return mixed If found, returns integer with page UID where found. Otherwise blank. Might exit if location-header is sent, see description.
	 * @see \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController::findDomainRecord()
	 */
	public function getDomainStartPage($domain, $path = '', $request_uri = '') {
		$domain = explode(':', $domain);
		$domain = strtolower(preg_replace('/\\.$/', '', $domain[0]));
		// Removing extra trailing slashes
		$path = trim(preg_replace('/\\/[^\\/]*$/', '', $path));
		// Appending to domain string
		$domain .= $path;
		$domain = preg_replace('/\\/*$/', '', $domain);
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('pages.uid,sys_domain.redirectTo,sys_domain.redirectHttpStatusCode,sys_domain.prepend_params', 'pages,sys_domain', 'pages.uid=sys_domain.pid
						AND sys_domain.hidden=0
						AND (sys_domain.domainName=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($domain, 'sys_domain') . ' OR sys_domain.domainName=' . $GLOBALS['TYPO3_DB']->fullQuoteStr(($domain . '/'), 'sys_domain') . ') ' . $this->where_hid_del . $this->where_groupAccess, '', '', 1);
		$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		$GLOBALS['TYPO3_DB']->sql_free_result($res);
		if ($row) {
			if ($row['redirectTo']) {
				$redirectUrl = $row['redirectTo'];
				if ($row['prepend_params']) {
					$redirectUrl = rtrim($redirectUrl, '/');
					$prependStr = ltrim(substr($request_uri, strlen($path)), '/');
					$redirectUrl .= '/' . $prependStr;
				}
				$statusCode = (int)$row['redirectHttpStatusCode'];
				if ($statusCode && defined(\TYPO3\CMS\Core\Utility\HttpUtility::class . '::HTTP_STATUS_' . $statusCode)) {
					\TYPO3\CMS\Core\Utility\HttpUtility::redirect($redirectUrl, constant(\TYPO3\CMS\Core\Utility\HttpUtility::class . '::HTTP_STATUS_' . $statusCode));
				} else {
					\TYPO3\CMS\Core\Utility\HttpUtility::redirect($redirectUrl, \TYPO3\CMS\Core\Utility\HttpUtility::HTTP_STATUS_301);
				}
				die;
			} else {
				return $row['uid'];
			}
		}
	}

	/**
	 * Returns array with fields of the pages from here ($uid) and back to the root
	 *
	 * NOTICE: This function only takes deleted pages into account! So hidden,
	 * starttime and endtime restricted pages are included no matter what.
	 *
	 * Further: If any "recycler" page is found (doktype=255) then it will also block
	 * for the rootline)
	 *
	 * If you want more fields in the rootline records than default such can be added
	 * by listing them in $GLOBALS['TYPO3_CONF_VARS']['FE']['addRootLineFields']
	 *
	 * @param int $uid The page uid for which to seek back to the page tree root.
	 * @param string $MP Commalist of MountPoint parameters, eg. "1-2,3-4" etc. Normally this value comes from the GET var, MP
	 * @param bool $ignoreMPerrors If set, some errors related to Mount Points in root line are ignored.
	 * @throws \Exception
	 * @throws \RuntimeException
	 * @return array Array with page records from the root line as values. The array is ordered with the outer records first and root record in the bottom. The keys are numeric but in reverse order. So if you traverse/sort the array by the numeric keys order you will get the order from root and out. If an error is found (like eternal looping or invalid mountpoint) it will return an empty array.
	 * @see \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController::getPageAndRootline()
	 */
	public function getRootLine($uid, $MP = '', $ignoreMPerrors = FALSE) {
		$rootline = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Utility\RootlineUtility::class, $uid, $MP, $this);
		try {
			return $rootline->get();
		} catch (\RuntimeException $ex) {
			if ($ignoreMPerrors) {
				$this->error_getRootLine = $ex->getMessage();
				if (substr($this->error_getRootLine, -7) == 'uid -1.') {
					$this->error_getRootLine_failPid = -1;
				}
				return array();
			/** @see \TYPO3\CMS\Core\Utility\RootlineUtility::getRecordArray */
			} elseif ($ex->getCode() === 1343589451) {
				return array();
			}
			throw $ex;
		}
	}

	/**
	 * Creates a "path" string for the input root line array titles.
	 * Used for writing statistics.
	 *
	 * @param array $rl A rootline array!
	 * @param int $len The max length of each title from the rootline.
	 * @return string The path in the form "/page title/This is another pageti.../Another page
	 * @see \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController::getConfigArray()
	 */
	public function getPathFromRootline($rl, $len = 20) {
		if (is_array($rl)) {
			$c = count($rl);
			$path = '';
			for ($a = 0; $a < $c; $a++) {
				if ($rl[$a]['uid']) {
					$path .= '/' . GeneralUtility::fixed_lgd_cs(strip_tags($rl[$a]['title']), $len);
				}
			}
			return $path;
		}
	}

	/**
	 * Returns the URL type for the input page row IF the doktype is 3 and not
	 * disabled.
	 *
	 * @param array $pagerow The page row to return URL type for
	 * @param bool $disable A flag to simply disable any output from here.
	 * @return string The URL type from $this->urltypes array. False if not found or disabled.
	 * @see \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController::setExternalJumpUrl()
	 */
	public function getExtURL($pagerow, $disable = 0) {
		if ($pagerow['doktype'] == self::DOKTYPE_LINK && !$disable) {
			$redirectTo = $this->urltypes[$pagerow['urltype']] . $pagerow['url'];
			// If relative path, prefix Site URL:
			$uI = parse_url($redirectTo);
			// Relative path assumed now.
			if (!$uI['scheme'] && $redirectTo[0] !== '/') {
				$redirectTo = GeneralUtility::getIndpEnv('TYPO3_SITE_URL') . $redirectTo;
			}
			return $redirectTo;
		}
	}

	/**
	 * Returns MountPoint id for page
	 *
	 * Does a recursive search if the mounted page should be a mount page itself. It
	 * has a run-away break so it can't go into infinite loops.
	 *
	 * @param int $pageId Page id for which to look for a mount pid. Will be returned only if mount pages are enabled, the correct doktype (7) is set for page and there IS a mount_pid (which has a valid record that is not deleted...)
	 * @param array $pageRec Optional page record for the page id. If not supplied it will be looked up by the system. Must contain at least uid,pid,doktype,mount_pid,mount_pid_ol
	 * @param array $prevMountPids Array accumulating formerly tested page ids for mount points. Used for recursivity brake.
	 * @param int $firstPageUid The first page id.
	 * @return mixed Returns FALSE if no mount point was found, "-1" if there should have been one, but no connection to it, otherwise an array with information about mount pid and modes.
	 * @see \TYPO3\CMS\Frontend\ContentObject\Menu\AbstractMenuContentObject
	 */
	public function getMountPointInfo($pageId, $pageRec = FALSE, $prevMountPids = array(), $firstPageUid = 0) {
		$result = FALSE;
		if ($GLOBALS['TYPO3_CONF_VARS']['FE']['enable_mount_pids']) {
			if (isset($this->cache_getMountPointInfo[$pageId])) {
				return $this->cache_getMountPointInfo[$pageId];
			}
			// Get pageRec if not supplied:
			if (!is_array($pageRec)) {
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid,pid,doktype,mount_pid,mount_pid_ol,t3ver_state', 'pages', 'uid=' . (int)$pageId . ' AND pages.deleted=0 AND pages.doktype<>255');
				$pageRec = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
				$GLOBALS['TYPO3_DB']->sql_free_result($res);
				// Only look for version overlay if page record is not supplied; This assumes
				// that the input record is overlaid with preview version, if any!
				$this->versionOL('pages', $pageRec);
			}
			// Set first Page uid:
			if (!$firstPageUid) {
				$firstPageUid = $pageRec['uid'];
			}
			// Look for mount pid value plus other required circumstances:
			$mount_pid = (int)$pageRec['mount_pid'];
			if (is_array($pageRec) && $pageRec['doktype'] == self::DOKTYPE_MOUNTPOINT && $mount_pid > 0 && !in_array($mount_pid, $prevMountPids)) {
				// Get the mount point record (to verify its general existence):
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid,pid,doktype,mount_pid,mount_pid_ol,t3ver_state', 'pages', 'uid=' . $mount_pid . ' AND pages.deleted=0 AND pages.doktype<>255');
				$mountRec = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
				$GLOBALS['TYPO3_DB']->sql_free_result($res);
				$this->versionOL('pages', $mountRec);
				if (is_array($mountRec)) {
					// Look for recursive mount point:
					$prevMountPids[] = $mount_pid;
					$recursiveMountPid = $this->getMountPointInfo($mount_pid, $mountRec, $prevMountPids, $firstPageUid);
					// Return mount point information:
					$result = $recursiveMountPid ?: array(
						'mount_pid' => $mount_pid,
						'overlay' => $pageRec['mount_pid_ol'],
						'MPvar' => $mount_pid . '-' . $firstPageUid,
						'mount_point_rec' => $pageRec,
						'mount_pid_rec' => $mountRec
					);
				} else {
					// Means, there SHOULD have been a mount point, but there was none!
					$result = -1;
				}
			}
		}
		$this->cache_getMountPointInfo[$pageId] = $result;
		return $result;
	}

	/********************************
	 *
	 * Selecting records in general
	 *
	 ********************************/

	/**
	 * Checks if a record exists and is accessible.
	 * The row is returned if everything's OK.
	 *
	 * @param string $table The table name to search
	 * @param int $uid The uid to look up in $table
	 * @param bool $checkPage If checkPage is set, it's also required that the page on which the record resides is accessible
	 * @return mixed Returns array (the record) if OK, otherwise blank/0 (zero)
	 */
	public function checkRecord($table, $uid, $checkPage = 0) {
		$uid = (int)$uid;
		if (is_array($GLOBALS['TCA'][$table]) && $uid > 0) {
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', $table, 'uid = ' . $uid . $this->enableFields($table));
			$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
			$GLOBALS['TYPO3_DB']->sql_free_result($res);
			if ($row) {
				$this->versionOL($table, $row);
				if (is_array($row)) {
					if ($checkPage) {
						$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid', 'pages', 'uid=' . (int)$row['pid'] . $this->enableFields('pages'));
						$numRows = $GLOBALS['TYPO3_DB']->sql_num_rows($res);
						$GLOBALS['TYPO3_DB']->sql_free_result($res);
						if ($numRows > 0) {
							return $row;
						} else {
							return 0;
						}
					} else {
						return $row;
					}
				}
			}
		}
	}

	/**
	 * Returns record no matter what - except if record is deleted
	 *
	 * @param string $table The table name to search
	 * @param int $uid The uid to look up in $table
	 * @param string $fields The fields to select, default is "*
	 * @param bool $noWSOL If set, no version overlay is applied
	 * @return mixed Returns array (the record) if found, otherwise blank/0 (zero)
	 * @see getPage_noCheck()
	 */
	public function getRawRecord($table, $uid, $fields = '*', $noWSOL = FALSE) {
		$uid = (int)$uid;
		// Excluding pages here so we can ask the function BEFORE TCA gets initialized.
		// Support for this is followed up in deleteClause()...
		if ((is_array($GLOBALS['TCA'][$table]) || $table == 'pages') && $uid > 0) {
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields, $table, 'uid = ' . $uid . $this->deleteClause($table));
			$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
			$GLOBALS['TYPO3_DB']->sql_free_result($res);
			if ($row) {
				if (!$noWSOL) {
					$this->versionOL($table, $row);
				}
				if (is_array($row)) {
					return $row;
				}
			}
		}
	}

	/**
	 * Selects records based on matching a field (ei. other than UID) with a value
	 *
	 * @param string $theTable The table name to search, eg. "pages" or "tt_content
	 * @param string $theField The fieldname to match, eg. "uid" or "alias
	 * @param string $theValue The value that fieldname must match, eg. "123" or "frontpage
	 * @param string $whereClause Optional additional WHERE clauses put in the end of the query. DO NOT PUT IN GROUP BY, ORDER BY or LIMIT!
	 * @param string $groupBy Optional GROUP BY field(s). If none, supply blank string.
	 * @param string $orderBy Optional ORDER BY field(s). If none, supply blank string.
	 * @param string $limit Optional LIMIT value ([begin,]max). If none, supply blank string.
	 * @return mixed Returns array (the record) if found, otherwise nothing (void)
	 */
	public function getRecordsByField($theTable, $theField, $theValue, $whereClause = '', $groupBy = '', $orderBy = '', $limit = '') {
		if (is_array($GLOBALS['TCA'][$theTable])) {
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', $theTable, $theField . '=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($theValue, $theTable) . $this->deleteClause($theTable) . ' ' . $whereClause, $groupBy, $orderBy, $limit);
			$rows = array();
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				if (is_array($row)) {
					$rows[] = $row;
				}
			}
			$GLOBALS['TYPO3_DB']->sql_free_result($res);
			if (count($rows)) {
				return $rows;
			}
		}
	}

	/********************************
	 *
	 * Caching and standard clauses
	 *
	 ********************************/

	/**
	 * Returns data stored for the hash string in the cache "cache_hash"
	 * Can be used to retrieved a cached value, array or object
	 * Can be used from your frontend plugins if you like. It is also used to
	 * store the parsed TypoScript template structures. You can call it directly
	 * like \TYPO3\CMS\Frontend\Page\PageRepository::getHash()
	 *
	 * @param string $hash The hash-string which was used to store the data value
	 * @param int The expiration time (not used anymore)
	 * @return mixed The "data" from the cache
	 * @see tslib_TStemplate::start(), storeHash()
	 */
	static public function getHash($hash, $expTime = 0) {
		$hashContent = NULL;
		$contentHashCache = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Cache\CacheManager::class)->getCache('cache_hash');
		$cacheEntry = $contentHashCache->get($hash);
		if ($cacheEntry) {
			$hashContent = $cacheEntry;
		}
		return $hashContent;
	}

	/**
	 * Stores $data in the 'cache_hash' cache with the hash key, $hash
	 * and visual/symbolic identification, $ident
	 *
	 * Can be used from your frontend plugins if you like. You can call it
	 * directly like \TYPO3\CMS\Frontend\Page\PageRepository::storeHash()
	 *
	 * @param string $hash 32 bit hash string (eg. a md5 hash of a serialized array identifying the data being stored)
	 * @param mixed $data The data to store
	 * @param string $ident Is just a textual identification in order to inform about the content!
	 * @param int $lifetime The lifetime for the cache entry in seconds
	 * @return void
	 * @see tslib_TStemplate::start(), getHash()
	 */
	static public function storeHash($hash, $data, $ident, $lifetime = 0) {
		GeneralUtility::makeInstance(\TYPO3\CMS\Core\Cache\CacheManager::class)->getCache('cache_hash')->set($hash, $data, array('ident_' . $ident), (int)$lifetime);
	}

	/**
	 * Returns the "AND NOT deleted" clause for the tablename given IF
	 * $GLOBALS['TCA'] configuration points to such a field.
	 *
	 * @param string $table Tablename
	 * @return string
	 * @see enableFields()
	 */
	public function deleteClause($table) {
		// Hardcode for pages because TCA might not be loaded yet (early frontend
		// initialization)
		if ($table === 'pages') {
			return ' AND pages.deleted=0';
		} else {
			return $GLOBALS['TCA'][$table]['ctrl']['delete'] ? ' AND ' . $table . '.' . $GLOBALS['TCA'][$table]['ctrl']['delete'] . '=0' : '';
		}
	}

	/**
	 * Returns a part of a WHERE clause which will filter out records with start/end
	 * times or hidden/fe_groups fields set to values that should de-select them
	 * according to the current time, preview settings or user login. Definitely a
	 * frontend function.
	 *
	 * Is using the $GLOBALS['TCA'] arrays "ctrl" part where the key "enablefields"
	 * determines for each table which of these features applies to that table.
	 *
	 * @param string $table Table name found in the $GLOBALS['TCA'] array
	 * @param int $show_hidden If $show_hidden is set (0/1), any hidden-fields in records are ignored. NOTICE: If you call this function, consider what to do with the show_hidden parameter. Maybe it should be set? See ContentObjectRenderer->enableFields where it's implemented correctly.
	 * @param array $ignore_array Array you can pass where keys can be "disabled", "starttime", "endtime", "fe_group" (keys from "enablefields" in TCA) and if set they will make sure that part of the clause is not added. Thus disables the specific part of the clause. For previewing etc.
	 * @param bool $noVersionPreview If set, enableFields will be applied regardless of any versioning preview settings which might otherwise disable enableFields
	 * @throws \InvalidArgumentException
	 * @return string The clause starting like " AND ...=... AND ...=...
	 * @see \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer::enableFields(), deleteClause()
	 */
	public function enableFields($table, $show_hidden = -1, $ignore_array = array(), $noVersionPreview = FALSE) {
		if ($show_hidden === -1 && is_object($GLOBALS['TSFE'])) {
			// If show_hidden was not set from outside and if TSFE is an object, set it
			// based on showHiddenPage and showHiddenRecords from TSFE
			$show_hidden = $table == 'pages' ? $GLOBALS['TSFE']->showHiddenPage : $GLOBALS['TSFE']->showHiddenRecords;
		}
		if ($show_hidden === -1) {
			$show_hidden = 0;
		}
		// If show_hidden was not changed during the previous evaluation, do it here.
		$ctrl = $GLOBALS['TCA'][$table]['ctrl'];
		$query = '';
		if (is_array($ctrl)) {
			// Delete field check:
			if ($ctrl['delete']) {
				$query .= ' AND ' . $table . '.' . $ctrl['delete'] . '=0';
			}
			if ($ctrl['versioningWS']) {
				if (!$this->versioningPreview) {
					// Filter out placeholder records (new/moved/deleted items)
					// in case we are NOT in a versioning preview (that means we are online!)
					$query .= ' AND ' . $table . '.t3ver_state<=' . new VersionState(VersionState::DEFAULT_STATE);
				} else {
					if ($table !== 'pages') {
						// show only records of live and of the current workspace
						// in case we are in a versioning preview
						$query .= ' AND (' .
									$table . '.t3ver_wsid=0 OR ' .
									$table . '.t3ver_wsid=' . (int)$this->versioningWorkspaceId .
									')';
					}
				}

				// Filter out versioned records
				if (!$noVersionPreview && empty($ignore_array['pid'])) {
					$query .= ' AND ' . $table . '.pid<>-1';
				}
			}

			// Enable fields:
			if (is_array($ctrl['enablecolumns'])) {
				// In case of versioning-preview, enableFields are ignored (checked in
				// versionOL())
				if (!$this->versioningPreview || !$ctrl['versioningWS'] || $noVersionPreview) {
					if ($ctrl['enablecolumns']['disabled'] && !$show_hidden && !$ignore_array['disabled']) {
						$field = $table . '.' . $ctrl['enablecolumns']['disabled'];
						$query .= ' AND ' . $field . '=0';
					}
					if ($ctrl['enablecolumns']['starttime'] && !$ignore_array['starttime']) {
						$field = $table . '.' . $ctrl['enablecolumns']['starttime'];
						$query .= ' AND ' . $field . '<=' . $GLOBALS['SIM_ACCESS_TIME'];
					}
					if ($ctrl['enablecolumns']['endtime'] && !$ignore_array['endtime']) {
						$field = $table . '.' . $ctrl['enablecolumns']['endtime'];
						$query .= ' AND (' . $field . '=0 OR ' . $field . '>' . $GLOBALS['SIM_ACCESS_TIME'] . ')';
					}
					if ($ctrl['enablecolumns']['fe_group'] && !$ignore_array['fe_group']) {
						$field = $table . '.' . $ctrl['enablecolumns']['fe_group'];
						$query .= $this->getMultipleGroupsWhereClause($field, $table);
					}
					// Call hook functions for additional enableColumns
					// It is used by the extension ingmar_accessctrl which enables assigning more
					// than one usergroup to content and page records
					if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_page.php']['addEnableColumns'])) {
						$_params = array(
							'table' => $table,
							'show_hidden' => $show_hidden,
							'ignore_array' => $ignore_array,
							'ctrl' => $ctrl
						);
						foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_page.php']['addEnableColumns'] as $_funcRef) {
							$query .= GeneralUtility::callUserFunction($_funcRef, $_params, $this);
						}
					}
				}
			}
		} else {
			throw new \InvalidArgumentException('There is no entry in the $TCA array for the table "' . $table . '". This means that the function enableFields() is ' . 'called with an invalid table name as argument.', 1283790586);
		}
		return $query;
	}

	/**
	 * Creating where-clause for checking group access to elements in enableFields
	 * function
	 *
	 * @param string $field Field with group list
	 * @param string $table Table name
	 * @return string AND sql-clause
	 * @see enableFields()
	 */
	public function getMultipleGroupsWhereClause($field, $table) {
		$memberGroups = GeneralUtility::intExplode(',', $GLOBALS['TSFE']->gr_list);
		$orChecks = array();
		// If the field is empty, then OK
		$orChecks[] = $field . '=\'\'';
		// If the field is NULL, then OK
		$orChecks[] = $field . ' IS NULL';
		// If the field contsains zero, then OK
		$orChecks[] = $field . '=\'0\'';
		foreach ($memberGroups as $value) {
			$orChecks[] = $GLOBALS['TYPO3_DB']->listQuery($field, $value, $table);
		}
		return ' AND (' . implode(' OR ', $orChecks) . ')';
	}

	/**********************
	 *
	 * Versioning Preview
	 *
	 **********************/

	/**
	 * Finding online PID for offline version record
	 *
	 * ONLY active when backend user is previewing records. MUST NEVER affect a site
	 * served which is not previewed by backend users!!!
	 *
	 * Will look if the "pid" value of the input record is -1 (it is an offline
	 * version) and if the table supports versioning; if so, it will translate the -1
	 * PID into the PID of the original record.
	 *
	 * Used whenever you are tracking something back, like making the root line.
	 *
	 * Principle; Record offline! => Find online?
	 *
	 * @param string $table Table name
	 * @param array $rr Record array passed by reference. As minimum, "pid" and "uid" fields must exist! "t3ver_oid" and "t3ver_wsid" is nice and will save you a DB query.
	 * @return void (Passed by ref).
	 * @see \TYPO3\CMS\Backend\Utility\BackendUtility::fixVersioningPid(), versionOL(), getRootLine()
	 */
	public function fixVersioningPid($table, &$rr) {
		if ($this->versioningPreview && is_array($rr) && $rr['pid'] == -1 && ($table == 'pages' || $GLOBALS['TCA'][$table]['ctrl']['versioningWS'])) {
			// Have to hardcode it for "pages" table since TCA is not loaded at this moment!
			// Check values for t3ver_oid and t3ver_wsid:
			if (isset($rr['t3ver_oid']) && isset($rr['t3ver_wsid'])) {
				// If "t3ver_oid" is already a field, just set this:
				$oid = $rr['t3ver_oid'];
				$wsid = $rr['t3ver_wsid'];
			} else {
				// Otherwise we have to expect "uid" to be in the record and look up based
				// on this:
				$newPidRec = $this->getRawRecord($table, $rr['uid'], 't3ver_oid,t3ver_wsid', TRUE);
				if (is_array($newPidRec)) {
					$oid = $newPidRec['t3ver_oid'];
					$wsid = $newPidRec['t3ver_wsid'];
				}
			}
			// If workspace ids matches and ID of current online version is found, look up
			// the PID value of that:
			if ($oid && ((int)$this->versioningWorkspaceId === 0 && $this->checkWorkspaceAccess($wsid) || (int)$wsid === (int)$this->versioningWorkspaceId)) {
				$oidRec = $this->getRawRecord($table, $oid, 'pid', TRUE);
				if (is_array($oidRec)) {
					// SWAP uid as well? Well no, because when fixing a versioning PID happens it is
					// assumed that this is a "branch" type page and therefore the uid should be
					// kept (like in versionOL()). However if the page is NOT a branch version it
					// should not happen - but then again, direct access to that uid should not
					// happen!
					$rr['_ORIG_pid'] = $rr['pid'];
					$rr['pid'] = $oidRec['pid'];
				}
			}
		}
		// Changing PID in case of moving pointer:
		if ($movePlhRec = $this->getMovePlaceholder($table, $rr['uid'], 'pid')) {
			$rr['pid'] = $movePlhRec['pid'];
		}
	}

	/**
	 * Versioning Preview Overlay
	 *
	 * ONLY active when backend user is previewing records. MUST NEVER affect a site
	 * served which is not previewed by backend users!!!
	 *
	 * Generally ALWAYS used when records are selected based on uid or pid. If
	 * records are selected on other fields than uid or pid (eg. "email = ....") then
	 * usage might produce undesired results and that should be evaluated on
	 * individual basis.
	 *
	 * Principle; Record online! => Find offline?
	 *
	 * @param string $table Table name
	 * @param array $row Record array passed by reference. As minimum, the "uid", "pid" and "t3ver_state" fields must exist! The record MAY be set to FALSE in which case the calling function should act as if the record is forbidden to access!
	 * @param bool $unsetMovePointers If set, the $row is cleared in case it is a move-pointer. This is only for preview of moved records (to remove the record from the original location so it appears only in the new location)
	 * @param bool $bypassEnableFieldsCheck Unless this option is TRUE, the $row is unset if enablefields for BOTH the version AND the online record deselects it. This is because when versionOL() is called it is assumed that the online record is already selected with no regards to it's enablefields. However, after looking for a new version the online record enablefields must ALSO be evaluated of course. This is done all by this function!
	 * @return void (Passed by ref).
	 * @see fixVersioningPid(), \TYPO3\CMS\Backend\Utility\BackendUtility::workspaceOL()
	 */
	public function versionOL($table, &$row, $unsetMovePointers = FALSE, $bypassEnableFieldsCheck = FALSE) {
		if ($this->versioningPreview && is_array($row)) {
			// will overlay any movePlhOL found with the real record, which in turn
			// will be overlaid with its workspace version if any.
			$movePldSwap = $this->movePlhOL($table, $row);
			// implode(',',array_keys($row)) = Using fields from original record to make
			// sure no additional fields are selected. This is best for eg. getPageOverlay()
			if ($wsAlt = $this->getWorkspaceVersionOfRecord($this->versioningWorkspaceId, $table, $row['uid'], implode(',', array_keys($row)), $bypassEnableFieldsCheck)) {
				if (is_array($wsAlt)) {
					// Always fix PID (like in fixVersioningPid() above). [This is usually not
					// the important factor for versioning OL]
					// Keep the old (-1) - indicates it was a version...
					$wsAlt['_ORIG_pid'] = $wsAlt['pid'];
					// Set in the online versions PID.
					$wsAlt['pid'] = $row['pid'];
					// For versions of single elements or page+content, preserve online UID and PID
					// (this will produce true "overlay" of element _content_, not any references)
					// For page+content the "_ORIG_uid" should actually be used as PID for selection
					// of tables with "versioning_followPages" enabled.
					$wsAlt['_ORIG_uid'] = $wsAlt['uid'];
					$wsAlt['uid'] = $row['uid'];
					// Translate page alias as well so links are pointing to the _online_ page:
					if ($table === 'pages') {
						$wsAlt['alias'] = $row['alias'];
					}
					// Changing input record to the workspace version alternative:
					$row = $wsAlt;
					// Check if it is deleted/new
					$rowVersionState = VersionState::cast($row['t3ver_state']);
					if (
						$rowVersionState->equals(VersionState::NEW_PLACEHOLDER)
						|| $rowVersionState->equals(VersionState::DELETE_PLACEHOLDER)
					) {
						// Unset record if it turned out to be deleted in workspace
						$row = FALSE;
					}
					// Check if move-pointer in workspace (unless if a move-placeholder is the
					// reason why it appears!):
					// You have to specifically set $unsetMovePointers in order to clear these
					// because it is normally a display issue if it should be shown or not.
					if (
						($rowVersionState->equals(VersionState::MOVE_POINTER)
							&& !$movePldSwap
						) && $unsetMovePointers
					) {
						// Unset record if it turned out to be deleted in workspace
						$row = FALSE;
					}
				} else {
					// No version found, then check if t3ver_state = VersionState::NEW_PLACEHOLDER
					// (online version is dummy-representation)
					// Notice, that unless $bypassEnableFieldsCheck is TRUE, the $row is unset if
					// enablefields for BOTH the version AND the online record deselects it. See
					// note for $bypassEnableFieldsCheck
					if ($wsAlt <= -1 || VersionState::cast($row['t3ver_state'])->indicatesPlaceholder()) {
						// Unset record if it turned out to be "hidden"
						$row = FALSE;
					}
				}
			}
		}
	}

	/**
	 * Checks if record is a move-placeholder
	 * (t3ver_state==VersionState::MOVE_PLACEHOLDER) and if so it will set $row to be
	 * the pointed-to live record (and return TRUE) Used from versionOL
	 *
	 * @param string $table Table name
	 * @param array $row Row (passed by reference) - only online records...
	 * @return bool TRUE if overlay is made.
	 * @see \TYPO3\CMS\Backend\Utility\BackendUtility::movePlhOl()
	 */
	public function movePlhOL($table, &$row) {
		if (
			($table == 'pages'
				|| (int)$GLOBALS['TCA'][$table]['ctrl']['versioningWS'] >= 2
			) && (int)VersionState::cast($row['t3ver_state'])->equals(VersionState::MOVE_PLACEHOLDER)
		) {
			// Only for WS ver 2... (moving)
			// If t3ver_move_id is not found, then find it (but we like best if it is here)
			if (!isset($row['t3ver_move_id'])) {
				$moveIDRec = $this->getRawRecord($table, $row['uid'], 't3ver_move_id', TRUE);
				$moveID = $moveIDRec['t3ver_move_id'];
			} else {
				$moveID = $row['t3ver_move_id'];
			}
			// Find pointed-to record.
			if ($moveID) {
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(implode(',', array_keys($row)), $table, 'uid=' . (int)$moveID . $this->enableFields($table));
				$origRow = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
				$GLOBALS['TYPO3_DB']->sql_free_result($res);
				if ($origRow) {
					$row = $origRow;
					return TRUE;
				}
			}
		}
		return FALSE;
	}

	/**
	 * Returns move placeholder of online (live) version
	 *
	 * @param string $table Table name
	 * @param int $uid Record UID of online version
	 * @param string $fields Field list, default is *
	 * @return array If found, the record, otherwise nothing.
	 * @see \TYPO3\CMS\Backend\Utility\BackendUtility::getMovePlaceholder()
	 */
	public function getMovePlaceholder($table, $uid, $fields = '*') {
		if ($this->versioningPreview) {
			$workspace = (int)$this->versioningWorkspaceId;
			if (($table == 'pages' || (int)$GLOBALS['TCA'][$table]['ctrl']['versioningWS'] >= 2) && $workspace !== 0) {
				// Select workspace version of record:
				$row = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow($fields, $table, 'pid<>-1 AND
						t3ver_state=' . new VersionState(VersionState::MOVE_PLACEHOLDER) . ' AND
						t3ver_move_id=' . (int)$uid . ' AND
						t3ver_wsid=' . (int)$workspace . $this->deleteClause($table));
				if (is_array($row)) {
					return $row;
				}
			}
		}
		return FALSE;
	}

	/**
	 * Select the version of a record for a workspace
	 *
	 * @param int $workspace Workspace ID
	 * @param string $table Table name to select from
	 * @param int $uid Record uid for which to find workspace version.
	 * @param string $fields Field list to select
	 * @param bool $bypassEnableFieldsCheck If TRUE, enablefields are not checked for.
	 * @return mixed If found, return record, otherwise other value: Returns 1 if version was sought for but not found, returns -1/-2 if record (offline/online) existed but had enableFields that would disable it. Returns FALSE if not in workspace or no versioning for record. Notice, that the enablefields of the online record is also tested.
	 * @see \TYPO3\CMS\Backend\Utility\BackendUtility::getWorkspaceVersionOfRecord()
	 */
	public function getWorkspaceVersionOfRecord($workspace, $table, $uid, $fields = '*', $bypassEnableFieldsCheck = FALSE) {
		if ($workspace !== 0 && !empty($GLOBALS['TCA'][$table]['ctrl']['versioningWS'])) {
			$workspace = (int)$workspace;
			$uid = (int)$uid;
			// Have to hardcode it for "pages" table since TCA is not loaded at this moment!
			// Setting up enableFields for version record:
			if ($table == 'pages') {
				$enFields = $this->versioningPreview_where_hid_del;
			} else {
				$enFields = $this->enableFields($table, -1, array(), TRUE);
			}
			// Select workspace version of record, only testing for deleted.
			$newrow = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow($fields, $table, 'pid=-1 AND
					t3ver_oid=' . $uid . ' AND
					t3ver_wsid=' . $workspace . $this->deleteClause($table));
			// If version found, check if it could have been selected with enableFields on
			// as well:
			if (is_array($newrow)) {
				if ($bypassEnableFieldsCheck || $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow('uid', $table, 'pid=-1 AND
						t3ver_oid=' . $uid . ' AND
						t3ver_wsid=' . $workspace . $enFields)) {
					// Return offline version, tested for its enableFields.
					return $newrow;
				} else {
					// Return -1 because offline version was de-selected due to its enableFields.
					return -1;
				}
			} else {
				// OK, so no workspace version was found. Then check if online version can be
				// selected with full enable fields and if so, return 1:
				if ($bypassEnableFieldsCheck || $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow('uid', $table, 'uid=' . $uid . $enFields)) {
					// Means search was done, but no version found.
					return 1;
				} else {
					// Return -2 because the online record was de-selected due to its enableFields.
					return -2;
				}
			}
		}
		// No look up in database because versioning not enabled / or workspace not
		// offline
		return FALSE;
	}

	/**
	 * Checks if user has access to workspace.
	 *
	 * @param int $wsid Workspace ID
	 * @return bool <code>TRUE</code> if has access
	 */
	public function checkWorkspaceAccess($wsid) {
		if (!$GLOBALS['BE_USER'] || !\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('workspaces')) {
			return FALSE;
		}
		if (isset($this->workspaceCache[$wsid])) {
			$ws = $this->workspaceCache[$wsid];
		} else {
			if ($wsid > 0) {
				// No $GLOBALS['TCA'] yet!
				$ws = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow('*', 'sys_workspace', 'uid=' . (int)$wsid . ' AND deleted=0');
				if (!is_array($ws)) {
					return FALSE;
				}
			} else {
				$ws = $wsid;
			}
			$ws = $GLOBALS['BE_USER']->checkWorkspace($ws);
			$this->workspaceCache[$wsid] = $ws;
		}
		return $ws['_ACCESS'] != '';
	}

	/**
	 * Gets file references for a given record field.
	 *
	 * @param string $tableName Name of the table
	 * @param string $fieldName Name of the field
	 * @param array $element The parent element referencing to files
	 * @return array
	 */
	public function getFileReferences($tableName, $fieldName, array $element) {
		/** @var $fileRepository \TYPO3\CMS\Core\Resource\FileRepository */
		$fileRepository = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Resource\FileRepository::class);
		$currentId = !empty($element['uid']) ? $element['uid'] : 0;

		// Fetch the references of the default element
		$references = $fileRepository->findByRelation($tableName, $fieldName, $currentId);

		$localizedId = NULL;
		if (isset($element['_LOCALIZED_UID'])) {
			$localizedId = $element['_LOCALIZED_UID'];
		} elseif (isset($element['_PAGES_OVERLAY_UID'])) {
			$localizedId = $element['_PAGES_OVERLAY_UID'];
		}

		if (!empty($GLOBALS['TCA'][$tableName]['ctrl']['transForeignTable'])) {
			$tableName = $GLOBALS['TCA'][$tableName]['ctrl']['transForeignTable'];
		}

		$isTableLocalizable = (
			!empty($GLOBALS['TCA'][$tableName]['ctrl']['languageField'])
			&& !empty($GLOBALS['TCA'][$tableName]['ctrl']['transOrigPointerField'])
		);
		if ($isTableLocalizable && $localizedId !== NULL) {
			$localizedReferences = $fileRepository->findByRelation($tableName, $fieldName, $localizedId);
			$localizedReferencesValue = $localizedReferences ?: '';
			if ($this->shouldFieldBeOverlaid($tableName, $fieldName, $localizedReferencesValue)) {
				$references = $localizedReferences;
			}
		}

		return $references;
	}

	/**
	 * Determine if a field needs an overlay
	 *
	 * @param string $table TCA tablename
	 * @param string $field TCA fieldname
	 * @param mixed $value Current value of the field
	 * @return bool Returns TRUE if a given record field needs to be overlaid
	 */
	protected function shouldFieldBeOverlaid($table, $field, $value) {
		$l10n_mode = isset($GLOBALS['TCA'][$table]['columns'][$field]['l10n_mode'])
			? $GLOBALS['TCA'][$table]['columns'][$field]['l10n_mode']
			: '';

		$shouldFieldBeOverlaid = TRUE;

		if ($l10n_mode === 'exclude') {
			$shouldFieldBeOverlaid = FALSE;
		} elseif ($l10n_mode === 'mergeIfNotBlank') {
			$checkValue = $value;

			// 0 values are considered blank when coming from a group field
			if (empty($value) && $GLOBALS['TCA'][$table]['columns'][$field]['config']['type'] === 'group') {
				$checkValue = '';
			}

			if ($checkValue === array() || trim($checkValue) === '') {
				$shouldFieldBeOverlaid = FALSE;
			}
		}

		return $shouldFieldBeOverlaid;
	}

}
