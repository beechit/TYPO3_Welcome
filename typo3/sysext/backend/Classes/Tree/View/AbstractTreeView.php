<?php
namespace TYPO3\CMS\Backend\Tree\View;

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
 * Base class for creating a browsable array/page/folder tree in HTML
 *
 * @author Kasper Skårhøj <kasperYYYY@typo3.com>
 * @author René Fritz <r.fritz@colorcube.de>
 */
abstract class AbstractTreeView {

	// EXTERNAL, static:
	// If set, the first element in the tree is always expanded.
	/**
	 * @var int
	 */
	public $expandFirst = 0;

	// If set, then ALL items will be expanded, regardless of stored settings.
	/**
	 * @var int
	 */
	public $expandAll = 0;

	// Holds the current script to reload to.
	/**
	 * @var string
	 */
	public $thisScript = '';

	// Which HTML attribute to use: alt/title. See init().
	/**
	 * @var string
	 */
	public $titleAttrib = 'title';

	// If TRUE, no context menu is rendered on icons. If set to "titlelink" the
	// icon is linked as the title is.
	/**
	 * @var bool
	 */
	public $ext_IconMode = FALSE;

	// If set, the id of the mounts will be added to the internal ids array
	/**
	 * @var int
	 */
	public $addSelfId = 0;

	// Used if the tree is made of records (not folders for ex.)
	/**
	 * @var string
	 */
	public $title = 'no title';

	// If TRUE, a default title attribute showing the UID of the record is shown.
	// This cannot be enabled by default because it will destroy many applications
	// where another title attribute is in fact applied later.
	/**
	 * @var bool
	 */
	public $showDefaultTitleAttribute = FALSE;

	// If TRUE, pages containing child records which has versions will be
	// highlighted in yellow. This might be too expensive in terms
	// of processing power.
	/**
	 * @var bool
	 */
	public $highlightPagesWithVersions = TRUE;

	/**
	 * Needs to be initialized with $GLOBALS['BE_USER']
	 * Done by default in init()
	 *
	 * @var \TYPO3\CMS\Core\Authentication\BackendUserAuthentication
	 */
	public $BE_USER = '';

	/**
	 * Needs to be initialized with e.g. $GLOBALS['BE_USER']->returnWebmounts()
	 * Default setting in init() is 0 => 0
	 * The keys are mount-ids (can be anything basically) and the
	 * values are the ID of the root element (COULD be zero or anything else.
	 * For pages that would be the uid of the page, zero for the pagetree root.)
	 *
	 * @var string
	 */
	public $MOUNTS = '';

	/**
	 * Database table to get the tree data from.
	 * Leave blank if data comes from an array.
	 *
	 * @var string
	 */
	public $table = '';

	/**
	 * Defines the field of $table which is the parent id field (like pid for table pages).
	 *
	 * @var string
	 */
	public $parentField = 'pid';

	/**
	 * WHERE clause used for selecting records for the tree. Is set by function init.
	 * Only makes sense when $this->table is set.
	 *
	 * @see init()
	 * @var string
	 */
	public $clause = '';

	/**
	 * Field for ORDER BY. Is set by function init.
	 * Only makes sense when $this->table is set.
	 *
	 * @see init()
	 * @var string
	 */
	public $orderByFields = '';

	/**
	 * Default set of fields selected from the tree table.
	 * Make SURE that these fields names listed herein are actually possible to select from $this->table (if that variable is set to a TCA table name)
	 *
	 * @see addField()
	 * @var array
	 */
	public $fieldArray = array('uid', 'title');

	/**
	 * List of other fields which are ALLOWED to set (here, based on the "pages" table!)
	 *
	 * @see addField()
	 * @var array
	 */
	public $defaultList = 'uid,pid,tstamp,sorting,deleted,perms_userid,perms_groupid,perms_user,perms_group,perms_everybody,crdate,cruser_id';

	/**
	 * Unique name for the tree.
	 * Used as key for storing the tree into the BE users settings.
	 * Used as key to pass parameters in links.
	 * MUST NOT contain underscore chars.
	 * etc.
	 *
	 * @var string
	 */
	public $treeName = '';

	/**
	 * A prefix for table cell id's which will be wrapped around an item.
	 * Can be used for highlighting by JavaScript.
	 * Needs to be unique if multiple trees are on one HTML page.
	 *
	 * @see printTree()
	 * @var string
	 */
	public $domIdPrefix = 'row';

	/**
	 * Back path for icons
	 *
	 * @var string
	 */
	public $backPath;

	/**
	 * Icon file path.
	 *
	 * @var string
	 */
	public $iconPath = '';

	/**
	 * Icon file name for item icons.
	 *
	 * @var string
	 */
	public $iconName = 'default.gif';

	/**
	 * If TRUE, HTML code is also accumulated in ->tree array during rendering of the tree.
	 * If 2, then also the icon prefix code (depthData) is stored
	 *
	 * @var int
	 */
	public $makeHTML = 1;

	/**
	 * If TRUE, records as selected will be stored internally in the ->recs array
	 *
	 * @var int
	 */
	public $setRecs = 0;

	/**
	 * Sets the associative array key which identifies a new sublevel if arrays are used for trees.
	 * This value has formerly been "subLevel" and "--sublevel--"
	 *
	 * @var string
	 */
	public $subLevelID = '_SUB_LEVEL';

	// *********
	// Internal
	// *********
	// For record trees:
	// one-dim array of the uid's selected.
	/**
	 * @var array
	 */
	public $ids = array();

	// The hierarchy of element uids
	/**
	 * @var array
	 */
	public $ids_hierarchy = array();

	// The hierarchy of versioned element uids
	/**
	 * @var array
	 */
	public $orig_ids_hierarchy = array();

	// Temporary, internal array
	/**
	 * @var array
	 */
	public $buffer_idH = array();

	// For FOLDER trees:
	// Special UIDs for folders (integer-hashes of paths)
	/**
	 * @var array
	 */
	public $specUIDmap = array();

	// For arrays:
	// Holds the input data array
	/**
	 * @var bool
	 */
	public $data = FALSE;

	// Holds an index with references to the data array.
	/**
	 * @var bool
	 */
	public $dataLookup = FALSE;

	// For both types
	// Tree is accumulated in this variable
	/**
	 * @var array
	 */
	public $tree = array();

	// Holds (session stored) information about which items in the tree are unfolded and which are not.
	/**
	 * @var array
	 */
	public $stored = array();

	// Points to the current mountpoint key
	/**
	 * @var int
	 */
	public $bank = 0;

	// Accumulates the displayed records.
	/**
	 * @var array
	 */
	public $recs = array();

	/**
	 * Sets the script url depending on being a module or script request
	 */
	protected function determineScriptUrl() {
		if ($moduleName = \TYPO3\CMS\Core\Utility\GeneralUtility::_GP('M')) {
			$this->thisScript = \TYPO3\CMS\Backend\Utility\BackendUtility::getModuleUrl($moduleName);
		} else {
			$this->thisScript = \TYPO3\CMS\Core\Utility\GeneralUtility::getIndpEnv('SCRIPT_NAME');
		}
	}

	/**
	 * @return string
	 */
	protected function getThisScript() {
		return strpos($this->thisScript, '?') === FALSE ? $this->thisScript . '?' : $this->thisScript . '&';
	}

	/**
	 * Initialize the tree class. Needs to be overwritten
	 * Will set ->fieldsArray, ->backPath and ->clause
	 *
	 * @param string Record WHERE clause
	 * @param string Record ORDER BY field
	 * @return void
	 */
	public function init($clause = '', $orderByFields = '') {
		// Setting BE_USER by default
		$this->BE_USER = $GLOBALS['BE_USER'];
		// Setting title attribute to use.
		$this->titleAttrib = 'title';
		// Setting backpath.
		$this->backPath = $GLOBALS['BACK_PATH'];
		// Setting clause
		if ($clause) {
			$this->clause = $clause;
		}
		if ($orderByFields) {
			$this->orderByFields = $orderByFields;
		}
		if (!is_array($this->MOUNTS)) {
			// Dummy
			$this->MOUNTS = array(0 => 0);
		}
		$this->setTreeName();
		// Setting this to FALSE disables the use of array-trees by default
		$this->data = FALSE;
		$this->dataLookup = FALSE;
	}

	/**
	 * Sets the tree name which is used to identify the tree
	 * Used for JavaScript and other things
	 *
	 * @param string $treeName Default is the table name. Underscores are stripped.
	 * @return void
	 */
	public function setTreeName($treeName = '') {
		$this->treeName = $treeName ?: $this->treeName;
		$this->treeName = $this->treeName ?: $this->table;
		$this->treeName = str_replace('_', '', $this->treeName);
	}

	/**
	 * Adds a fieldname to the internal array ->fieldArray
	 *
	 * @param string $field Field name to
	 * @param bool $noCheck If set, the fieldname will be set no matter what. Otherwise the field name must either be found as key in $GLOBALS['TCA'][$table]['columns'] or in the list ->defaultList
	 * @return void
	 */
	public function addField($field, $noCheck = 0) {
		if ($noCheck || is_array($GLOBALS['TCA'][$this->table]['columns'][$field]) || GeneralUtility::inList($this->defaultList, $field)) {
			$this->fieldArray[] = $field;
		}
	}

	/**
	 * Resets the tree, recs, ids, ids_hierarchy and orig_ids_hierarchy internal variables. Use it if you need it.
	 *
	 * @return void
	 */
	public function reset() {
		$this->tree = array();
		$this->recs = array();
		$this->ids = array();
		$this->ids_hierarchy = array();
		$this->orig_ids_hierarchy = array();
	}

	/*******************************************
	 *
	 * output
	 *
	 *******************************************/
	/**
	 * Will create and return the HTML code for a browsable tree
	 * Is based on the mounts found in the internal array ->MOUNTS (set in the constructor)
	 *
	 * @return string HTML code for the browsable tree
	 */
	public function getBrowsableTree() {
		// Get stored tree structure AND updating it if needed according to incoming PM GET var.
		$this->initializePositionSaving();
		// Init done:
		$treeArr = array();
		// Traverse mounts:
		foreach ($this->MOUNTS as $idx => $uid) {
			// Set first:
			$this->bank = $idx;
			$isOpen = $this->stored[$idx][$uid] || $this->expandFirst;
			// Save ids while resetting everything else.
			$curIds = $this->ids;
			$this->reset();
			$this->ids = $curIds;
			// Set PM icon for root of mount:
			$cmd = $this->bank . '_' . ($isOpen ? '0_' : '1_') . $uid . '_' . $this->treeName;
			$icon = IconUtility::getSpriteIcon('treeline-' . ($isOpen ? 'minus' : 'plus') . 'only');

			$firstHtml = $this->PM_ATagWrap($icon, $cmd);
			// Preparing rootRec for the mount
			if ($uid) {
				$rootRec = $this->getRecord($uid);
				$firstHtml .= $this->getIcon($rootRec);
			} else {
				// Artificial record for the tree root, id=0
				$rootRec = $this->getRootRecord($uid);
				$firstHtml .= $this->getRootIcon($rootRec);
			}
			if (is_array($rootRec)) {
				// In case it was swapped inside getRecord due to workspaces.
				$uid = $rootRec['uid'];
				// Add the root of the mount to ->tree
				$this->tree[] = array('HTML' => $firstHtml, 'row' => $rootRec, 'bank' => $this->bank);
				// If the mount is expanded, go down:
				if ($isOpen) {
					// Set depth:
					$depthD = IconUtility::getSpriteIcon('treeline-blank');
					if ($this->addSelfId) {
						$this->ids[] = $uid;
					}
					$this->getTree($uid, 999, $depthD, '', $rootRec['_SUBCSSCLASS']);
				}
				// Add tree:
				$treeArr = array_merge($treeArr, $this->tree);
			}
		}
		return $this->printTree($treeArr);
	}

	/**
	 * Compiles the HTML code for displaying the structure found inside the ->tree array
	 *
	 * @param array $treeArr "tree-array" - if blank string, the internal ->tree array is used.
	 * @return string The HTML code for the tree
	 */
	public function printTree($treeArr = '') {
		$titleLen = (int)$this->BE_USER->uc['titleLen'];
		if (!is_array($treeArr)) {
			$treeArr = $this->tree;
		}
		$out = '';
		// put a table around it with IDs to access the rows from JS
		// not a problem if you don't need it
		// In XHTML there is no "name" attribute of <td> elements -
		// but Mozilla will not be able to highlight rows if the name
		// attribute is NOT there.
		$out .= '

			<!--
			  TYPO3 tree structure.
			-->
			<table cellpadding="0" cellspacing="0" border="0" id="typo3-tree">';
		foreach ($treeArr as $k => $v) {
			$idAttr = htmlspecialchars($this->domIdPrefix . $this->getId($v['row']) . '_' . $v['bank']);
			$out .= '
				<tr>
					<td id="' . $idAttr . '"' . ($v['row']['_CSSCLASS'] ? ' class="' . $v['row']['_CSSCLASS'] . '"' : '') . '>' . $v['HTML'] . $this->wrapTitle($this->getTitleStr($v['row'], $titleLen), $v['row'], $v['bank']) . '</td>
				</tr>
			';
		}
		$out .= '
			</table>';
		return $out;
	}

	/*******************************************
	 *
	 * rendering parts
	 *
	 *******************************************/
	/**
	 * Generate the plus/minus icon for the browsable tree.
	 *
	 * @param array $row Record for the entry
	 * @param int $a The current entry number
	 * @param int $c The total number of entries. If equal to $a, a "bottom" element is returned.
	 * @param int $nextCount The number of sub-elements to the current element.
	 * @param bool $exp The element was expanded to render subelements if this flag is set.
	 * @return string Image tag with the plus/minus icon.
	 * @access private
	 * @see \TYPO3\CMS\Backend\Tree\View\PageTreeView::PMicon()
	 */
	public function PMicon($row, $a, $c, $nextCount, $exp) {
		$PM = $nextCount ? ($exp ? 'minus' : 'plus') : 'join';
		$BTM = $a == $c ? 'bottom' : '';
		$icon = IconUtility::getSpriteIcon('treeline-' . $PM . $BTM);
		if ($nextCount) {
			$cmd = $this->bank . '_' . ($exp ? '0_' : '1_') . $row['uid'] . '_' . $this->treeName;
			$bMark = $this->bank . '_' . $row['uid'];
			$icon = $this->PM_ATagWrap($icon, $cmd, $bMark);
		}
		return $icon;
	}

	/**
	 * Wrap the plus/minus icon in a link
	 *
	 * @param string $icon HTML string to wrap, probably an image tag.
	 * @param string $cmd Command for 'PM' get var
	 * @param bool $bMark If set, the link will have a anchor point (=$bMark) and a name attribute (=$bMark)
	 * @return string Link-wrapped input string
	 * @access private
	 */
	public function PM_ATagWrap($icon, $cmd, $bMark = '') {
		if ($this->thisScript) {
			if ($bMark) {
				$anchor = '#' . $bMark;
				$name = ' name="' . $bMark . '"';
			}
			$aUrl = $this->getThisScript() . 'PM=' . $cmd . $anchor;
			return '<a href="' . htmlspecialchars($aUrl) . '"' . $name . '>' . $icon . '</a>';
		} else {
			return $icon;
		}
	}

	/**
	 * Wrapping $title in a-tags.
	 *
	 * @param string $title Title string
	 * @param string $row Item record
	 * @param int $bank Bank pointer (which mount point number)
	 * @return string
	 * @access private
	 */
	public function wrapTitle($title, $row, $bank = 0) {
		$aOnClick = 'return jumpTo(\'' . $this->getJumpToParam($row) . '\',this,\'' . $this->domIdPrefix . $this->getId($row) . '\',' . $bank . ');';
		return '<a href="#" onclick="' . htmlspecialchars($aOnClick) . '">' . $title . '</a>';
	}

	/**
	 * Wrapping the image tag, $icon, for the row, $row (except for mount points)
	 *
	 * @param string $icon The image tag for the icon
	 * @param array $row The row for the current element
	 * @return string The processed icon input value.
	 * @access private
	 */
	public function wrapIcon($icon, $row) {
		return $icon;
	}

	/**
	 * Adds attributes to image tag.
	 *
	 * @param string $icon Icon image tag
	 * @param string $attr Attributes to add, eg. ' border="0"'
	 * @return string Image tag, modified with $attr attributes added.
	 */
	public function addTagAttributes($icon, $attr) {
		return preg_replace('/ ?\\/?>$/', '', $icon) . ' ' . $attr . ' />';
	}

	/**
	 * Adds a red "+" to the input string, $str, if the field "php_tree_stop" in the $row (pages) is set
	 *
	 * @param string $str Input string, like a page title for the tree
	 * @param array $row record row with "php_tree_stop" field
	 * @return string Modified string
	 * @access private
	 */
	public function wrapStop($str, $row) {
		if ($row['php_tree_stop']) {
			$str .= '<span class="typo3-red"><a href="' . htmlspecialchars(GeneralUtility::linkThisScript(array('setTempDBmount' => $row['uid']))) . '" class="typo3-red">+</a> </span>';
		}
		return $str;
	}

	/*******************************************
	 *
	 * tree handling
	 *
	 *******************************************/
	/**
	 * Returns TRUE/FALSE if the next level for $id should be expanded - based on
	 * data in $this->stored[][] and ->expandAll flag.
	 * Extending parent function
	 *
	 * @param int $id Record id/key
	 * @return bool
	 * @access private
	 * @see \TYPO3\CMS\Backend\Tree\View\PageTreeView::expandNext()
	 */
	public function expandNext($id) {
		return $this->stored[$this->bank][$id] || $this->expandAll ? 1 : 0;
	}

	/**
	 * Get stored tree structure AND updating it if needed according to incoming PM GET var.
	 *
	 * @return void
	 * @access private
	 */
	public function initializePositionSaving() {
		// Get stored tree structure:
		$this->stored = unserialize($this->BE_USER->uc['browseTrees'][$this->treeName]);
		// PM action
		// (If an plus/minus icon has been clicked, the PM GET var is sent and we
		// must update the stored positions in the tree):
		// 0: mount key, 1: set/clear boolean, 2: item ID (cannot contain "_"), 3: treeName
		$PM = explode('_', GeneralUtility::_GP('PM'));
		if (count($PM) == 4 && $PM[3] == $this->treeName) {
			if (isset($this->MOUNTS[$PM[0]])) {
				// set
				if ($PM[1]) {
					$this->stored[$PM[0]][$PM[2]] = 1;
					$this->savePosition();
				} else {
					unset($this->stored[$PM[0]][$PM[2]]);
					$this->savePosition();
				}
			}
		}
	}

	/**
	 * Saves the content of ->stored (keeps track of expanded positions in the tree)
	 * $this->treeName will be used as key for BE_USER->uc[] to store it in
	 *
	 * @return void
	 * @access private
	 */
	public function savePosition() {
		$this->BE_USER->uc['browseTrees'][$this->treeName] = serialize($this->stored);
		$this->BE_USER->writeUC();
	}

	/******************************
	 *
	 * Functions that might be overwritten by extended classes
	 *
	 ********************************/
	/**
	 * Returns the root icon for a tree/mountpoint (defaults to the globe)
	 *
	 * @param array $rec Record for root.
	 * @return string Icon image tag.
	 */
	public function getRootIcon($rec) {
		return $this->wrapIcon(IconUtility::getSpriteIcon('apps-pagetree-root'), $rec);
	}

	/**
	 * Get icon for the row.
	 * If $this->iconPath and $this->iconName is set, try to get icon based on those values.
	 *
	 * @param array $row Item row.
	 * @return string Image tag.
	 */
	public function getIcon($row) {
		if ($this->iconPath && $this->iconName) {
			$icon = '<img' . IconUtility::skinImg('', ($this->iconPath . $this->iconName), 'width="18" height="16"') . ' alt=""' . ($this->showDefaultTitleAttribute ? ' title="UID: ' . $row['uid'] . '"' : '') . ' />';
		} else {
			$icon = IconUtility::getSpriteIconForRecord($this->table, $row, array(
				'title' => $this->showDefaultTitleAttribute ? 'UID: ' . $row['uid'] : $this->getTitleAttrib($row),
				'class' => 'c-recIcon'
			));
		}
		return $this->wrapIcon($icon, $row);
	}

	/**
	 * Returns the title for the input record. If blank, a "no title" label (localized) will be returned.
	 * Do NOT htmlspecialchar the string from this function - has already been done.
	 *
	 * @param array $row The input row array (where the key "title" is used for the title)
	 * @param int $titleLen Title length (30)
	 * @return string The title.
	 */
	public function getTitleStr($row, $titleLen = 30) {
		$title = htmlspecialchars(GeneralUtility::fixed_lgd_cs($row['title'], $titleLen));
		$title = trim($row['title']) === '' ? '<em>[' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:labels.no_title', TRUE) . ']</em>' : $title;
		return $title;
	}

	/**
	 * Returns the value for the image "title" attribute
	 *
	 * @param array $row The input row array (where the key "title" is used for the title)
	 * @return string The attribute value (is htmlspecialchared() already)
	 * @see wrapIcon()
	 */
	public function getTitleAttrib($row) {
		return htmlspecialchars($row['title']);
	}

	/**
	 * Returns the id from the record (typ. uid)
	 *
	 * @param array $row Record array
	 * @return int The "uid" field value.
	 */
	public function getId($row) {
		return $row['uid'];
	}

	/**
	 * Returns jump-url parameter value.
	 *
	 * @param array $row The record array.
	 * @return string The jump-url parameter.
	 */
	public function getJumpToParam($row) {
		return $this->getId($row);
	}

	/********************************
	 *
	 * tree data buidling
	 *
	 ********************************/
	/**
	 * Fetches the data for the tree
	 *
	 * @param int $uid item id for which to select subitems (parent id)
	 * @param int $depth Max depth (recursivity limit)
	 * @param string $depthData HTML-code prefix for recursive calls.
	 * @param string $blankLineCode ? (internal)
	 * @param string $subCSSclass CSS class to use for <td> sub-elements
	 * @return int The count of items on the level
	 */
	public function getTree($uid, $depth = 999, $depthData = '', $blankLineCode = '', $subCSSclass = '') {
		// Buffer for id hierarchy is reset:
		$this->buffer_idH = array();
		// Init vars
		$depth = (int)$depth;
		$HTML = '';
		$a = 0;
		$res = $this->getDataInit($uid, $subCSSclass);
		$c = $this->getDataCount($res);
		$crazyRecursionLimiter = 999;
		$idH = array();
		// Traverse the records:
		while ($crazyRecursionLimiter > 0 && ($row = $this->getDataNext($res, $subCSSclass))) {
			if (!$GLOBALS['BE_USER']->isInWebMount($row['uid'])) {
				// Current record is not within web mount => skip it
				continue;
			}

			$a++;
			$crazyRecursionLimiter--;
			$newID = $row['uid'];
			if ($newID == 0) {
				throw new \RuntimeException('Endless recursion detected: TYPO3 has detected an error in the database. Please fix it manually (e.g. using phpMyAdmin) and change the UID of ' . $this->table . ':0 to a new value.<br /><br />See <a href="http://forge.typo3.org/issues/16150" target="_blank">forge.typo3.org/issues/16150</a> to get more information about a possible cause.', 1294586383);
			}
			// Reserve space.
			$this->tree[] = array();
			end($this->tree);
			// Get the key for this space
			$treeKey = key($this->tree);
			$LN = $a == $c ? 'blank' : 'line';
			// If records should be accumulated, do so
			if ($this->setRecs) {
				$this->recs[$row['uid']] = $row;
			}
			// Accumulate the id of the element in the internal arrays
			$this->ids[] = ($idH[$row['uid']]['uid'] = $row['uid']);
			$this->ids_hierarchy[$depth][] = $row['uid'];
			$this->orig_ids_hierarchy[$depth][] = $row['_ORIG_uid'] ?: $row['uid'];

			// Make a recursive call to the next level
			$HTML_depthData = $depthData . IconUtility::getSpriteIcon('treeline-' . $LN);
			if ($depth > 1 && $this->expandNext($newID) && !$row['php_tree_stop']) {
				$nextCount = $this->getTree($newID, $depth - 1, $this->makeHTML ? $HTML_depthData : '', $blankLineCode . ',' . $LN, $row['_SUBCSSCLASS']);
				if (count($this->buffer_idH)) {
					$idH[$row['uid']]['subrow'] = $this->buffer_idH;
				}
				// Set "did expand" flag
				$exp = 1;
			} else {
				$nextCount = $this->getCount($newID);
				// Clear "did expand" flag
				$exp = 0;
			}
			// Set HTML-icons, if any:
			if ($this->makeHTML) {
				$HTML = $depthData . $this->PMicon($row, $a, $c, $nextCount, $exp);
				$HTML .= $this->wrapStop($this->getIcon($row), $row);
			}
			// Finally, add the row/HTML content to the ->tree array in the reserved key.
			$this->tree[$treeKey] = array(
				'row' => $row,
				'HTML' => $HTML,
				'HTML_depthData' => $this->makeHTML == 2 ? $HTML_depthData : '',
				'invertedDepth' => $depth,
				'blankLineCode' => $blankLineCode,
				'bank' => $this->bank
			);
		}
		$this->getDataFree($res);
		$this->buffer_idH = $idH;
		return $c;
	}

	/********************************
	 *
	 * Data handling
	 * Works with records and arrays
	 *
	 ********************************/
	/**
	 * Returns the number of records having the parent id, $uid
	 *
	 * @param int $uid Id to count subitems for
	 * @return int
	 * @access private
	 */
	public function getCount($uid) {
		if (is_array($this->data)) {
			$res = $this->getDataInit($uid);
			return $this->getDataCount($res);
		} else {
			return $GLOBALS['TYPO3_DB']->exec_SELECTcountRows('uid', $this->table, $this->parentField . '=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($uid, $this->table) . BackendUtility::deleteClause($this->table) . BackendUtility::versioningPlaceholderClause($this->table) . $this->clause);
		}
	}

	/**
	 * Returns root record for uid (<=0)
	 *
	 * @param int $uid uid, <= 0 (normally, this does not matter)
	 * @return array Array with title/uid keys with values of $this->title/0 (zero)
	 */
	public function getRootRecord($uid) {
		return array('title' => $this->title, 'uid' => 0);
	}

	/**
	 * Returns the record for a uid.
	 * For tables: Looks up the record in the database.
	 * For arrays: Returns the fake record for uid id.
	 *
	 * @param int $uid UID to look up
	 * @return array The record
	 */
	public function getRecord($uid) {
		if (is_array($this->data)) {
			return $this->dataLookup[$uid];
		} else {
			return BackendUtility::getRecordWSOL($this->table, $uid);
		}
	}

	/**
	 * Getting the tree data: Selecting/Initializing data pointer to items for a certain parent id.
	 * For tables: This will make a database query to select all children to "parent"
	 * For arrays: This will return key to the ->dataLookup array
	 *
	 * @param int $parentId parent item id
	 * @param string $subCSSclass Class for sub-elements.
	 * @return mixed Data handle (Tables: An sql-resource, arrays: A parentId integer. -1 is returned if there were NO subLevel.)
	 * @access private
	 */
	public function getDataInit($parentId, $subCSSclass = '') {
		if (is_array($this->data)) {
			if (!is_array($this->dataLookup[$parentId][$this->subLevelID])) {
				$parentId = -1;
			} else {
				reset($this->dataLookup[$parentId][$this->subLevelID]);
			}
			return $parentId;
		} else {
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(implode(',', $this->fieldArray), $this->table, $this->parentField . '=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($parentId, $this->table) . BackendUtility::deleteClause($this->table) . BackendUtility::versioningPlaceholderClause($this->table) . $this->clause, '', $this->orderByFields);
			return $res;
		}
	}

	/**
	 * Getting the tree data: Counting elements in resource
	 *
	 * @param mixed $res Data handle
	 * @return int number of items
	 * @access private
	 * @see getDataInit()
	 */
	public function getDataCount(&$res) {
		if (is_array($this->data)) {
			return count($this->dataLookup[$res][$this->subLevelID]);
		} else {
			$c = $GLOBALS['TYPO3_DB']->sql_num_rows($res);
			return $c;
		}
	}

	/**
	 * Getting the tree data: next entry
	 *
	 * @param mixed $res Data handle
	 * @param string $subCSSclass CSS class for sub elements (workspace related)
	 * @return array item data array OR FALSE if end of elements.
	 * @access private
	 * @see getDataInit()
	 */
	public function getDataNext(&$res, $subCSSclass = '') {
		if (is_array($this->data)) {
			if ($res < 0) {
				$row = FALSE;
			} else {
				list(, $row) = each($this->dataLookup[$res][$this->subLevelID]);
				// Passing on default <td> class for subelements:
				if (is_array($row) && $subCSSclass !== '') {
					$row['_CSSCLASS'] = ($row['_SUBCSSCLASS'] = $subCSSclass);
				}
			}
			return $row;
		} else {
			while ($row = @$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				BackendUtility::workspaceOL($this->table, $row, $this->BE_USER->workspace, TRUE);
				if (is_array($row)) {
					break;
				}
			}
			// Passing on default <td> class for subelements:
			if (is_array($row) && $subCSSclass !== '') {
				if ($this->table === 'pages' && $this->highlightPagesWithVersions && !isset($row['_CSSCLASS']) && count(BackendUtility::countVersionsOfRecordsOnPage($this->BE_USER->workspace, $row['uid']))) {
					$row['_CSSCLASS'] = 'ver-versions';
				}
				if (!isset($row['_CSSCLASS'])) {
					$row['_CSSCLASS'] = $subCSSclass;
				}
				if (!isset($row['_SUBCSSCLASS'])) {
					$row['_SUBCSSCLASS'] = $subCSSclass;
				}
			}
			return $row;
		}
	}

	/**
	 * Getting the tree data: frees data handle
	 *
	 * @param mixed $res Data handle
	 * @return void
	 * @access private
	 */
	public function getDataFree(&$res) {
		if (!is_array($this->data)) {
			$GLOBALS['TYPO3_DB']->sql_free_result($res);
		}
	}

	/**
	 * Used to initialize class with an array to browse.
	 * The array inputted will be traversed and an internal index for lookup is created.
	 * The keys of the input array are perceived as "uid"s of records which means that keys GLOBALLY must be unique like uids are.
	 * "uid" and "pid" "fakefields" are also set in each record.
	 * All other fields are optional.
	 *
	 * @param array $dataArr The input array, see examples below in this script.
	 * @param bool $traverse Internal, for recursion.
	 * @param int $pid Internal, for recursion.
	 * @return void
	 */
	public function setDataFromArray(&$dataArr, $traverse = FALSE, $pid = 0) {
		if (!$traverse) {
			$this->data = &$dataArr;
			$this->dataLookup = array();
			// Add root
			$this->dataLookup[0][$this->subLevelID] = &$dataArr;
		}
		foreach ($dataArr as $uid => $val) {
			$dataArr[$uid]['uid'] = $uid;
			$dataArr[$uid]['pid'] = $pid;
			// Gives quick access to id's
			$this->dataLookup[$uid] = &$dataArr[$uid];
			if (is_array($val[$this->subLevelID])) {
				$this->setDataFromArray($dataArr[$uid][$this->subLevelID], TRUE, $uid);
			}
		}
	}

	/**
	 * Sets the internal data arrays
	 *
	 * @param array $treeArr Content for $this->data
	 * @param array $treeLookupArr Content for $this->dataLookup
	 * @return void
	 */
	public function setDataFromTreeArray(&$treeArr, &$treeLookupArr) {
		$this->data = &$treeArr;
		$this->dataLookup = &$treeLookupArr;
	}

}
