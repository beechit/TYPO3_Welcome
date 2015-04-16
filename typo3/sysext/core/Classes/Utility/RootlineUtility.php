<?php
namespace TYPO3\CMS\Core\Utility;

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

use TYPO3\CMS\Frontend\Page\PageRepository;

/**
 * A utility resolving and Caching the Rootline generation
 *
 * @author Steffen Ritter <steffen.ritter@typo3.org>
 */
class RootlineUtility {

	/**
	 * @var int
	 */
	protected $pageUid;

	/**
	 * @var string
	 */
	protected $mountPointParameter;

	/**
	 * @var array
	 */
	protected $parsedMountPointParameters = array();

	/**
	 * @var int
	 */
	protected $languageUid = 0;

	/**
	 * @var int
	 */
	protected $workspaceUid = 0;

	/**
	 * @var bool
	 */
	protected $versionPreview = FALSE;

	/**
	 * @var \TYPO3\CMS\Core\Cache\Frontend\FrontendInterface
	 */
	static protected $cache = NULL;

	/**
	 * @var array
	 */
	static protected $localCache = array();

	/**
	 * Fields to fetch when populating rootline data
	 *
	 * @var array
	 */
	static protected $rootlineFields = array(
		'pid',
		'uid',
		't3ver_oid',
		't3ver_wsid',
		't3ver_state',
		'title',
		'alias',
		'nav_title',
		'media',
		'layout',
		'hidden',
		'starttime',
		'endtime',
		'fe_group',
		'extendToSubpages',
		'doktype',
		'TSconfig',
		'storage_pid',
		'is_siteroot',
		'mount_pid',
		'mount_pid_ol',
		'fe_login_mode',
		'backend_layout_next_level'
	);

	/**
	 * Rootline Context
	 *
	 * @var \TYPO3\CMS\Frontend\Page\PageRepository
	 */
	protected $pageContext;

	/**
	 * @var string
	 */
	protected $cacheIdentifier;

	/**
	 * @var array
	 */
	static protected $pageRecordCache = array();

	/**
	 * @var \TYPO3\CMS\Core\Database\DatabaseConnection
	 */
	protected $databaseConnection;

	/**
	 * @param int $uid
	 * @param string $mountPointParameter
	 * @param \TYPO3\CMS\Frontend\Page\PageRepository $context
	 * @throws \RuntimeException
	 */
	public function __construct($uid, $mountPointParameter = '', PageRepository $context = NULL) {
		$this->pageUid = (int)$uid;
		$this->mountPointParameter = trim($mountPointParameter);
		if ($context === NULL) {
			if (isset($GLOBALS['TSFE']) && is_object($GLOBALS['TSFE']) && is_object($GLOBALS['TSFE']->sys_page)) {
				$this->pageContext = $GLOBALS['TSFE']->sys_page;
			} else {
				$this->pageContext = GeneralUtility::makeInstance(\TYPO3\CMS\Frontend\Page\PageRepository::class);
			}
		} else {
			$this->pageContext = $context;
		}
		$this->initializeObject();
	}

	/**
	 * Initialize a state to work with
	 *
	 * @throws \RuntimeException
	 * @return void
	 */
	protected function initializeObject() {
		$this->languageUid = (int)$this->pageContext->sys_language_uid;
		$this->workspaceUid = (int)$this->pageContext->versioningWorkspaceId;
		$this->versionPreview = $this->pageContext->versioningPreview;
		if ($this->mountPointParameter !== '') {
			if (!$GLOBALS['TYPO3_CONF_VARS']['FE']['enable_mount_pids']) {
				throw new \RuntimeException('Mount-Point Pages are disabled for this installation. Cannot resolve a Rootline for a page with Mount-Points', 1343462896);
			} else {
				$this->parseMountPointParameter();
			}
		}
		if (self::$cache === NULL) {
			self::$cache = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Cache\CacheManager::class)->getCache('cache_rootline');
		}
		self::$rootlineFields = array_merge(self::$rootlineFields, GeneralUtility::trimExplode(',', $GLOBALS['TYPO3_CONF_VARS']['FE']['addRootLineFields'], TRUE));
		self::$rootlineFields = array_unique(self::$rootlineFields);
		$this->databaseConnection = $GLOBALS['TYPO3_DB'];

		$this->cacheIdentifier = $this->getCacheIdentifier();
	}

	/**
	 * Purges all rootline caches.
	 *
	 * Note: This function is intended to be used in unit tests only.
	 *
	 * @return void
	 */
	static public function purgeCaches() {
		self::$localCache = array();
		self::$pageRecordCache = array();
	}

	/**
	 * Constructs the cache Identifier
	 *
	 * @param int $otherUid
	 * @return string
	 */
	public function getCacheIdentifier($otherUid = NULL) {

		$mountPointParameter = (string)$this->mountPointParameter;
		if ($mountPointParameter !== '' && strpos($mountPointParameter, ',') !== FALSE) {
			$mountPointParameter = str_replace(',', '__', $mountPointParameter);
		}

		return implode('_', array(
			$otherUid !== NULL ? (int)$otherUid : $this->pageUid,
			$mountPointParameter,
			$this->languageUid,
			$this->workspaceUid,
			$this->versionPreview ? 1 : 0
		));
	}

	/**
	 * Returns the actual rootline
	 *
	 * @return array
	 */
	public function get() {
		if (!isset(static::$localCache[$this->cacheIdentifier])) {
			$entry = static::$cache->get($this->cacheIdentifier);
			if (!$entry) {
				$this->generateRootlineCache();
			} else {
				static::$localCache[$this->cacheIdentifier] = $entry;
				$depth = count($entry);
				// Populate the root-lines for parent pages as well
				// since they are part of the current root-line
				while ($depth > 1) {
					--$depth;
					$parentCacheIdentifier = $this->getCacheIdentifier($entry[$depth - 1]['uid']);
					// Abort if the root-line of the parent page is
					// already in the local cache data
					if (isset(static::$localCache[$parentCacheIdentifier])) {
						break;
					}
					// Behaves similar to array_shift(), but preserves
					// the array keys - which contain the page ids here
					$entry = array_slice($entry, 1, NULL, TRUE);
					static::$localCache[$parentCacheIdentifier] = $entry;
				}
			}
		}
		return static::$localCache[$this->cacheIdentifier];
	}

	/**
	 * Queries the database for the page record and returns it.
	 *
	 * @param int $uid Page id
	 * @throws \RuntimeException
	 * @return array
	 */
	protected function getRecordArray($uid) {
		$currentCacheIdentifier = $this->getCacheIdentifier($uid);
		if (!isset(self::$pageRecordCache[$currentCacheIdentifier])) {
			$row = $this->databaseConnection->exec_SELECTgetSingleRow(implode(',', self::$rootlineFields), 'pages', 'uid = ' . (int)$uid . ' AND pages.deleted = 0 AND pages.doktype <> ' . PageRepository::DOKTYPE_RECYCLER);
			if (empty($row)) {
				throw new \RuntimeException('Could not fetch page data for uid ' . $uid . '.', 1343589451);
			}
			$this->pageContext->versionOL('pages', $row, FALSE, TRUE);
			$this->pageContext->fixVersioningPid('pages', $row);
			if (is_array($row)) {
				if ($this->languageUid > 0) {
					$row = $this->pageContext->getPageOverlay($row, $this->languageUid);
				}
				$row = $this->enrichWithRelationFields(isset($row['_PAGES_OVERLAY_UID']) ? $row['_PAGES_OVERLAY_UID'] : $uid, $row);
				self::$pageRecordCache[$currentCacheIdentifier] = $row;
			}
		}
		if (!is_array(self::$pageRecordCache[$currentCacheIdentifier])) {
			throw new \RuntimeException('Broken rootline. Could not resolve page with uid ' . $uid . '.', 1343464101);
		}
		return self::$pageRecordCache[$currentCacheIdentifier];
	}

	/**
	 * Resolve relations as defined in TCA and add them to the provided $pageRecord array.
	 *
	 * @param int $uid Page id
	 * @param array $pageRecord Array with page data to add relation data to.
	 * @throws \RuntimeException
	 * @return array $pageRecord with additional relations
	 */
	protected function enrichWithRelationFields($uid, array $pageRecord) {
		$pageOverlayFields = GeneralUtility::trimExplode(',', $GLOBALS['TYPO3_CONF_VARS']['FE']['pageOverlayFields']);
		foreach ($GLOBALS['TCA']['pages']['columns'] as $column => $configuration) {
			if ($this->columnHasRelationToResolve($configuration)) {
				$configuration = $configuration['config'];
				if ($configuration['MM']) {
					/** @var $loadDBGroup \TYPO3\CMS\Core\Database\RelationHandler */
					$loadDBGroup = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\RelationHandler::class);
					$loadDBGroup->start(
						$pageRecord[$column],
						isset($configuration['allowed']) ? $configuration['allowed'] : $configuration['foreign_table'],
						$configuration['MM'],
						$uid,
						'pages',
						$configuration
					);
					$relatedUids = isset($loadDBGroup->tableArray[$configuration['foreign_table']])
						? $loadDBGroup->tableArray[$configuration['foreign_table']]
						: array();
				} else {
					$columnIsOverlaid = in_array($column, $pageOverlayFields, TRUE);
					$table = $configuration['foreign_table'];
					$field = $configuration['foreign_field'];
					$whereClauseParts = array($field . ' = ' . (int)($columnIsOverlaid ? $uid : $pageRecord['uid']));
					if (isset($configuration['foreign_match_fields']) && is_array($configuration['foreign_match_fields'])) {
						foreach ($configuration['foreign_match_fields'] as $field => $value) {
							$whereClauseParts[] = $field . ' = ' . $this->databaseConnection->fullQuoteStr($value, $table);
						}
					}
					if (isset($configuration['foreign_table_field'])) {
						if ((int)$this->languageUid > 0 && $columnIsOverlaid) {
							$whereClauseParts[] = trim($configuration['foreign_table_field']) . ' = \'pages_language_overlay\'';
						} else {
							$whereClauseParts[] = trim($configuration['foreign_table_field']) . ' = \'pages\'';
						}
					}
					if (isset($GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['disabled'])) {
						$whereClauseParts[] = $table . '.' . $GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['disabled'] . ' = 0';
					}
					$whereClause = implode(' AND ', $whereClauseParts);
					$whereClause .= $this->pageContext->deleteClause($table);
					$orderBy = isset($configuration['foreign_sortby']) ? $configuration['foreign_sortby'] : '';
					$rows = $this->databaseConnection->exec_SELECTgetRows('uid', $table, $whereClause, '', $orderBy);
					if (!is_array($rows)) {
						throw new \RuntimeException('Could to resolve related records for page ' . $uid . ' and foreign_table ' . htmlspecialchars($configuration['foreign_table']), 1343589452);
					}
					$relatedUids = array();
					foreach ($rows as $row) {
						$relatedUids[] = $row['uid'];
					}
				}
				$pageRecord[$column] = implode(',', $relatedUids);
			}
		}
		return $pageRecord;
	}

	/**
	 * Checks whether the TCA Configuration array of a column
	 * describes a relation which is not stored as CSV in the record
	 *
	 * @param array $configuration TCA configuration to check
	 * @return bool TRUE, if it describes a non-CSV relation
	 */
	protected function columnHasRelationToResolve(array $configuration) {
		$configuration = $configuration['config'];
		if (!empty($configuration['MM']) && !empty($configuration['type']) && in_array($configuration['type'], array('select', 'inline', 'group'))) {
			return TRUE;
		}
		if (!empty($configuration['foreign_field']) && !empty($configuration['type']) && in_array($configuration['type'], array('select', 'inline'))) {
			return TRUE;
		}
		return FALSE;
	}

	/**
	 * Actual function to generate the rootline and cache it
	 *
	 * @throws \RuntimeException
	 * @return void
	 */
	protected function generateRootlineCache() {
		$page = $this->getRecordArray($this->pageUid);
		// If the current page is a mounted (according to the MP parameter) handle the mount-point
		if ($this->isMountedPage()) {
			$mountPoint = $this->getRecordArray($this->parsedMountPointParameters[$this->pageUid]);
			$page = $this->processMountedPage($page, $mountPoint);
			$parentUid = $mountPoint['pid'];
			// Anyhow after reaching the mount-point, we have to go up that rootline
			unset($this->parsedMountPointParameters[$this->pageUid]);
		} else {
			$parentUid = $page['pid'];
		}
		$cacheTags = array('pageId_' . $page['uid']);
		if ($parentUid > 0) {
			// Get rootline of (and including) parent page
			$mountPointParameter = count($this->parsedMountPointParameters) > 0 ? $this->mountPointParameter : '';
			/** @var $rootline \TYPO3\CMS\Core\Utility\RootlineUtility */
			$rootline = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Utility\RootlineUtility::class, $parentUid, $mountPointParameter, $this->pageContext);
			$rootline = $rootline->get();
			// retrieve cache tags of parent rootline
			foreach ($rootline as $entry) {
				$cacheTags[] = 'pageId_' . $entry['uid'];
				if ($entry['uid'] == $this->pageUid) {
					throw new \RuntimeException('Circular connection in rootline for page with uid ' . $this->pageUid . ' found. Check your mountpoint configuration.', 1343464103);
				}
			}
		} else {
			$rootline = array();
		}
		array_push($rootline, $page);
		krsort($rootline);
		static::$cache->set($this->cacheIdentifier, $rootline, $cacheTags);
		static::$localCache[$this->cacheIdentifier] = $rootline;
	}

	/**
	 * Checks whether the current Page is a Mounted Page
	 * (according to the MP-URL-Parameter)
	 *
	 * @return bool
	 */
	public function isMountedPage() {
		return in_array($this->pageUid, array_keys($this->parsedMountPointParameters));
	}

	/**
	 * Enhances with mount point information or replaces the node if needed
	 *
	 * @param array $mountedPageData page record array of mounted page
	 * @param array $mountPointPageData page record array of mount point page
	 * @throws \RuntimeException
	 * @return array
	 */
	protected function processMountedPage(array $mountedPageData, array $mountPointPageData) {
		if ($mountPointPageData['mount_pid'] != $mountedPageData['uid']) {
			throw new \RuntimeException('Broken rootline. Mountpoint parameter does not match the actual rootline. mount_pid (' . $mountPointPageData['mount_pid'] . ') does not match page uid (' . $mountedPageData['uid'] . ').', 1343464100);
		}
		// Current page replaces the original mount-page
		if ($mountPointPageData['mount_pid_ol']) {
			$mountedPageData['_MOUNT_OL'] = TRUE;
			$mountedPageData['_MOUNT_PAGE'] = array(
				'uid' => $mountPointPageData['uid'],
				'pid' => $mountPointPageData['pid'],
				'title' => $mountPointPageData['title']
			);
		} else {
			// The mount-page is not replaced, the mount-page itself has to be used
			$mountedPageData = $mountPointPageData;
		}
		$mountedPageData['_MOUNTED_FROM'] = $this->pageUid;
		$mountedPageData['_MP_PARAM'] = $this->pageUid . '-' . $mountPointPageData['uid'];
		return $mountedPageData;
	}

	/**
	 * Parse the MountPoint Parameters
	 * Splits the MP-Param via "," for several nested mountpoints
	 * and afterwords registers the mountpoint configurations
	 *
	 * @return void
	 */
	protected function parseMountPointParameter() {
		$mountPoints = GeneralUtility::trimExplode(',', $this->mountPointParameter);
		foreach ($mountPoints as $mP) {
			list($mountedPageUid, $mountPageUid) = GeneralUtility::intExplode('-', $mP);
			$this->parsedMountPointParameters[$mountedPageUid] = $mountPageUid;
		}
	}

}
