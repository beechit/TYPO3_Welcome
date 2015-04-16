<?php
namespace TYPO3\CMS\Core\Authentication;

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
use TYPO3\CMS\Backend\Utility\BackendUtility;

/**
 * TYPO3 backend user authentication
 * Contains most of the functions used for checking permissions, authenticating users,
 * setting up the user, and API for user from outside.
 * This class contains the configuration of the database fields used plus some
 * functions for the authentication process of backend users.
 *
 * @author Kasper Skårhøj <kasperYYYY@typo3.com>
 * @internal
 */
class BackendUserAuthentication extends \TYPO3\CMS\Core\Authentication\AbstractUserAuthentication {

	/**
	 * Should be set to the usergroup-column (id-list) in the user-record
	 * @var string
	 */
	public $usergroup_column = 'usergroup';

	/**
	 * The name of the group-table
	 * @var string
	 */
	public $usergroup_table = 'be_groups';

	/**
	 * holds lists of eg. tables, fields and other values related to the permission-system. See fetchGroupData
	 * @var array
	 * @internal
	 */
	public $groupData = array(
		'filemounts' => array()
	);

	/**
	 * This array will hold the groups that the user is a member of
	 * @var array
	 */
	public $userGroups = array();

	/**
	 * This array holds the uid's of the groups in the listed order
	 * @var array
	 */
	public $userGroupsUID = array();

	/**
	 * This is $this->userGroupsUID imploded to a comma list... Will correspond to the 'usergroup_cached_list'
	 * @var string
	 */
	public $groupList = '';

	/**
	 * User workspace.
	 * -99 is ERROR (none available)
	 * -1 is offline
	 * 0 is online
	 * >0 is custom workspaces
	 * @var int
	 */
	public $workspace = -99;

	/**
	 * Custom workspace record if any
	 * @var array
	 */
	public $workspaceRec = array();

	/**
	 * Used to accumulate data for the user-group.
	 * DON NOT USE THIS EXTERNALLY!
	 * Use $this->groupData instead
	 * @var array
	 * @internal
	 */
	public $dataLists = array(
		'webmount_list' => '',
		'filemount_list' => '',
		'file_permissions' => '',
		'modList' => '',
		'tables_select' => '',
		'tables_modify' => '',
		'pagetypes_select' => '',
		'non_exclude_fields' => '',
		'explicit_allowdeny' => '',
		'allowed_languages' => '',
		'workspace_perms' => '',
		'custom_options' => ''
	);

	/**
	 * For debugging/display of order in which subgroups are included.
	 * @var array
	 */
	public $includeHierarchy = array();

	/**
	 * List of group_id's in the order they are processed.
	 * @var array
	 */
	public $includeGroupArray = array();

	/**
	 * Set to 'WIN', if windows
	 * @var string
	 */
	public $OS = '';

	/**
	 * Used to accumulate the TSconfig data of the user
	 * @var array
	 */
	public $TSdataArray = array();

	/**
	 * Contains the non-parsed user TSconfig
	 * @var string
	 */
	public $userTS_text = '';

	/**
	 * Contains the parsed user TSconfig
	 * @var array
	 */
	public $userTS = array();

	/**
	 * Set internally if the user TSconfig was parsed and needs to be cached.
	 * @var bool
	 */
	public $userTSUpdated = FALSE;

	/**
	 * Set this from outside if you want the user TSconfig to ALWAYS be parsed and not fetched from cache.
	 * @var bool
	 */
	public $userTS_dontGetCached = FALSE;

	/**
	 * RTE availability errors collected.
	 * @var array
	 */
	public $RTE_errors = array();

	/**
	 * Contains last error message
	 * @var string
	 */
	public $errorMsg = '';

	/**
	 * Cache for checkWorkspaceCurrent()
	 * @var array|NULL
	 */
	public $checkWorkspaceCurrent_cache = NULL;

	/**
	 * @var \TYPO3\CMS\Core\Resource\ResourceStorage[]
	 */
	protected $fileStorages;

	/**
	 * @var array
	 */
	protected $filePermissions;

	/**
	 * Table to use for session data
	 * @var string
	 */
	public $session_table = 'be_sessions';

	/**
	 * Table in database with user data
	 * @var string
	 */
	public $user_table = 'be_users';

	/**
	 * Column for login-name
	 * @var string
	 */
	public $username_column = 'username';

	/**
	 * Column for password
	 * @var string
	 */
	public $userident_column = 'password';

	/**
	 * Column for user-id
	 * @var string
	 */
	public $userid_column = 'uid';

	/**
	 * @var string
	 */
	public $lastLogin_column = 'lastlogin';

	/**
	 * @var array
	 */
	public $enablecolumns = array(
		'rootLevel' => 1,
		'deleted' => 'deleted',
		'disabled' => 'disable',
		'starttime' => 'starttime',
		'endtime' => 'endtime'
	);

	/**
	 * Form field with login-name
	 * @var string
	 */
	public $formfield_uname = 'username';

	/**
	 * Form field with password
	 * @var string
	 */
	public $formfield_uident = 'userident';

	/**
	 * Form field with a unique value which is used to encrypt the password and username
	 * @var string
	 */
	public $formfield_chalvalue = 'challenge';

	/**
	 * Form field with status: *'login', 'logout'
	 * @var string
	 */
	public $formfield_status = 'login_status';

	/**
	 * Decides if the writelog() function is called at login and logout
	 * @var bool
	 */
	public $writeStdLog = TRUE;

	/**
	 * If the writelog() functions is called if a login-attempt has be tried without success
	 * @var bool
	 */
	public $writeAttemptLog = TRUE;

	/**
	 * if > 0 : session-timeout in seconds.
	 * if FALSE/<0 : no timeout.
	 * if string: The string is field name from the user table where the timeout can be found.
	 * @var string|int
	 */
	public $auth_timeout_field = 6000;

	/**
	 * @var bool
	 */
	public $challengeStoredInCookie = TRUE;

	/**
	 * @var int
	 */
	public $firstMainGroup = 0;

	/**
	 * User Config
	 * @var array
	 */
	public $uc;

	/**
	 * User Config Default values:
	 * The array may contain other fields for configuration.
	 * For this, see "setup" extension and "TSConfig" document (User TSconfig, "setup.[xxx]....")
	 * Reserved keys for other storage of session data:
	 * moduleData
	 * moduleSessionID
	 * @var array
	 */
	public $uc_default = array(
		'interfaceSetup' => '',
		// serialized content that is used to store interface pane and menu positions. Set by the logout.php-script
		'moduleData' => array(),
		// user-data for the modules
		'thumbnailsByDefault' => 1,
		'emailMeAtLogin' => 0,
		'startModule' => 'help_AboutmodulesAboutmodules',
		'hideSubmoduleIcons' => 0,
		'titleLen' => 50,
		'edit_showFieldHelp' => 'icon',
		'edit_RTE' => '1',
		'edit_docModuleUpload' => '1',
		'navFrameWidth' => '',
		// Default is 245 pixels
		'navFrameResizable' => 0,
		'resizeTextareas' => 1,
		'resizeTextareas_MaxHeight' => 500,
		'resizeTextareas_Flexible' => 0
	);

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();
		$this->name = self::getCookieName();
		$this->loginType = 'BE';
	}

	/**
	 * Returns TRUE if user is admin
	 * Basically this function evaluates if the ->user[admin] field has bit 0 set. If so, user is admin.
	 *
	 * @return bool
	 */
	public function isAdmin() {
		return is_array($this->user) && ($this->user['admin'] & 1) == 1;
	}

	/**
	 * Returns TRUE if the current user is a member of group $groupId
	 * $groupId must be set. $this->groupList must contain groups
	 * Will return TRUE also if the user is a member of a group through subgroups.
	 *
	 * @param int $groupId Group ID to look for in $this->groupList
	 * @return bool
	 */
	public function isMemberOfGroup($groupId) {
		$groupId = (int)$groupId;
		if ($this->groupList && $groupId) {
			return GeneralUtility::inList($this->groupList, $groupId);
		}
		return FALSE;
	}

	/**
	 * Checks if the permissions is granted based on a page-record ($row) and $perms (binary and'ed)
	 *
	 * Bits for permissions, see $perms variable:
	 *
	 * 1 - Show:	See/Copy page and the pagecontent.
	 * 16- Edit pagecontent: Change/Add/Delete/Move pagecontent.
	 * 2- Edit page: Change/Move the page, eg. change title, startdate, hidden.
	 * 4- Delete page: Delete the page and pagecontent.
	 * 8- New pages: Create new pages under the page.
	 *
	 * @param array $row Is the pagerow for which the permissions is checked
	 * @param int $perms Is the binary representation of the permission we are going to check. Every bit in this number represents a permission that must be set. See function explanation.
	 * @return bool
	 */
	public function doesUserHaveAccess($row, $perms) {
		$userPerms = $this->calcPerms($row);
		return ($userPerms & $perms) == $perms;
	}

	/**
	 * Checks if the page id, $id, is found within the webmounts set up for the user.
	 * This should ALWAYS be checked for any page id a user works with, whether it's about reading, writing or whatever.
	 * The point is that this will add the security that a user can NEVER touch parts outside his mounted
	 * pages in the page tree. This is otherwise possible if the raw page permissions allows for it.
	 * So this security check just makes it easier to make safe user configurations.
	 * If the user is admin OR if this feature is disabled
	 * (fx. by setting TYPO3_CONF_VARS['BE']['lockBeUserToDBmounts']=0) then it returns "1" right away
	 * Otherwise the function will return the uid of the webmount which was first found in the rootline of the input page $id
	 *
	 * @param int $id Page ID to check
	 * @param string $readPerms Content of "->getPagePermsClause(1)" (read-permissions). If not set, they will be internally calculated (but if you have the correct value right away you can save that database lookup!)
	 * @param bool|int $exitOnError If set, then the function will exit with an error message.
	 * @throws \RuntimeException
	 * @return int|NULL The page UID of a page in the rootline that matched a mount point
	 */
	public function isInWebMount($id, $readPerms = '', $exitOnError = 0) {
		if (!$GLOBALS['TYPO3_CONF_VARS']['BE']['lockBeUserToDBmounts'] || $this->isAdmin()) {
			return 1;
		}
		$id = (int)$id;
		// Check if input id is an offline version page in which case we will map id to the online version:
		$checkRec = BackendUtility::getRecord('pages', $id, 'pid,t3ver_oid');
		if ($checkRec['pid'] == -1) {
			$id = (int)$checkRec['t3ver_oid'];
		}
		if (!$readPerms) {
			$readPerms = $this->getPagePermsClause(1);
		}
		if ($id > 0) {
			$wM = $this->returnWebmounts();
			$rL = BackendUtility::BEgetRootLine($id, ' AND ' . $readPerms);
			foreach ($rL as $v) {
				if ($v['uid'] && in_array($v['uid'], $wM)) {
					return $v['uid'];
				}
			}
		}
		if ($exitOnError) {
			throw new \RuntimeException('Access Error: This page is not within your DB-mounts', 1294586445);
		}
		return NULL;
	}

	/**
	 * Checks access to a backend module with the $MCONF passed as first argument
	 *
	 * @param array $conf $MCONF array of a backend module!
	 * @param bool $exitOnError If set, an array will issue an error message and exit.
	 * @throws \RuntimeException
	 * @return bool Will return TRUE if $MCONF['access'] is not set at all, if the BE_USER is admin or if the module is enabled in the be_users/be_groups records of the user (specifically enabled). Will return FALSE if the module name is not even found in $TBE_MODULES
	 */
	public function modAccess($conf, $exitOnError) {
		if (!BackendUtility::isModuleSetInTBE_MODULES($conf['name'])) {
			if ($exitOnError) {
				throw new \RuntimeException('Fatal Error: This module "' . $conf['name'] . '" is not enabled in TBE_MODULES', 1294586446);
			}
			return FALSE;
		}
		// Workspaces check:
		if (
			!empty($conf['workspaces'])
			&& \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('workspaces')
			&& ($this->workspace !== 0 || !GeneralUtility::inList($conf['workspaces'], 'online'))
			&& ($this->workspace !== -1 || !GeneralUtility::inList($conf['workspaces'], 'offline'))
			&& ($this->workspace <= 0 || !GeneralUtility::inList($conf['workspaces'], 'custom'))
		) {
			if ($exitOnError) {
				throw new \RuntimeException('Workspace Error: This module "' . $conf['name'] . '" is not available under the current workspace', 1294586447);
			}
			return FALSE;
		}
		// Returns TRUE if conf[access] is not set at all or if the user is admin
		if (!$conf['access'] || $this->isAdmin()) {
			return TRUE;
		}
		// If $conf['access'] is set but not with 'admin' then we return TRUE, if the module is found in the modList
		$acs = FALSE;
		if (!strstr($conf['access'], 'admin') && $conf['name']) {
			$acs = $this->check('modules', $conf['name']);
		}
		if (!$acs && $exitOnError) {
			throw new \RuntimeException('Access Error: You don\'t have access to this module.', 1294586448);
		} else {
			return $acs;
		}
	}

	/**
	 * Returns a WHERE-clause for the pages-table where user permissions according to input argument, $perms, is validated.
	 * $perms is the "mask" used to select. Fx. if $perms is 1 then you'll get all pages that a user can actually see!
	 * 2^0 = show (1)
	 * 2^1 = edit (2)
	 * 2^2 = delete (4)
	 * 2^3 = new (8)
	 * If the user is 'admin' " 1=1" is returned (no effect)
	 * If the user is not set at all (->user is not an array), then " 1=0" is returned (will cause no selection results at all)
	 * The 95% use of this function is "->getPagePermsClause(1)" which will
	 * return WHERE clauses for *selecting* pages in backend listings - in other words this will check read permissions.
	 *
	 * @param int $perms Permission mask to use, see function description
	 * @return string Part of where clause. Prefix " AND " to this.
	 */
	public function getPagePermsClause($perms) {
		if (is_array($this->user)) {
			if ($this->isAdmin()) {
				return ' 1=1';
			}
			$perms = (int)$perms;
			// Make sure it's integer.
			$str = ' (' . '(pages.perms_everybody & ' . $perms . ' = ' . $perms . ')' . ' OR (pages.perms_userid = '
				. $this->user['uid'] . ' AND pages.perms_user & ' . $perms . ' = ' . $perms . ')';
			// User
			if ($this->groupList) {
				// Group (if any is set)
				$str .= ' OR (pages.perms_groupid in (' . $this->groupList . ') AND pages.perms_group & '
					. $perms . ' = ' . $perms . ')';
			}
			$str .= ')';
			// ****************
			// getPagePermsClause-HOOK
			// ****************
			if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_userauthgroup.php']['getPagePermsClause'])) {
				foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_userauthgroup.php']['getPagePermsClause'] as $_funcRef) {
					$_params = array('currentClause' => $str, 'perms' => $perms);
					$str = GeneralUtility::callUserFunction($_funcRef, $_params, $this);
				}
			}
			return $str;
		} else {
			return ' 1=0';
		}
	}

	/**
	 * Returns a combined binary representation of the current users permissions for the page-record, $row.
	 * The perms for user, group and everybody is OR'ed together (provided that the page-owner is the user
	 * and for the groups that the user is a member of the group.
	 * If the user is admin, 31 is returned	(full permissions for all five flags)
	 *
	 * @param array $row Input page row with all perms_* fields available.
	 * @return int Bitwise representation of the users permissions in relation to input page row, $row
	 */
	public function calcPerms($row) {
		// Return 31 for admin users.
		if ($this->isAdmin()) {
			return 31;
		}
		// Return 0 if page is not within the allowed web mount
		if (!$this->isInWebMount($row['uid'])) {
			return 0;
		}
		$out = 0;
		if (
			isset($row['perms_userid']) && isset($row['perms_user']) && isset($row['perms_groupid'])
			&& isset($row['perms_group']) && isset($row['perms_everybody']) && isset($this->groupList)
		) {
			if ($this->user['uid'] == $row['perms_userid']) {
				$out |= $row['perms_user'];
			}
			if ($this->isMemberOfGroup($row['perms_groupid'])) {
				$out |= $row['perms_group'];
			}
			$out |= $row['perms_everybody'];
		}
		// ****************
		// CALCPERMS hook
		// ****************
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_userauthgroup.php']['calcPerms'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_userauthgroup.php']['calcPerms'] as $_funcRef) {
				$_params = array(
					'row' => $row,
					'outputPermissions' => $out
				);
				$out = GeneralUtility::callUserFunction($_funcRef, $_params, $this);
			}
		}
		return $out;
	}

	/**
	 * Returns TRUE if the RTE (Rich Text Editor) can be enabled for the user
	 * Strictly this is not permissions being checked but rather a series of settings like
	 * a loaded extension, browser/client type and a configuration option in ->uc[edit_RTE]
	 * The reasons for a FALSE return can be found in $this->RTE_errors
	 *
	 * @return bool
	 */
	public function isRTE() {
		// Start:
		$this->RTE_errors = array();
		if (!$this->uc['edit_RTE']) {
			$this->RTE_errors[] = 'RTE is not enabled for user!';
		}
		if (!$GLOBALS['TYPO3_CONF_VARS']['BE']['RTEenabled']) {
			$this->RTE_errors[] = 'RTE is not enabled in $TYPO3_CONF_VARS["BE"]["RTEenabled"]';
		}
		// Acquire RTE object:
		$RTE = BackendUtility::RTEgetObj();
		if (!is_object($RTE)) {
			$this->RTE_errors = array_merge($this->RTE_errors, $RTE);
		}
		if (!count($this->RTE_errors)) {
			return TRUE;
		} else {
			return FALSE;
		}
	}

	/**
	 * Returns TRUE if the $value is found in the list in a $this->groupData[] index pointed to by $type (array key).
	 * Can thus be users to check for modules, exclude-fields, select/modify permissions for tables etc.
	 * If user is admin TRUE is also returned
	 * Please see the document Inside TYPO3 for examples.
	 *
	 * @param string $type The type value; "webmounts", "filemounts", "pagetypes_select", "tables_select", "tables_modify", "non_exclude_fields", "modules
	 * @param string $value String to search for in the groupData-list
	 * @return bool TRUE if permission is granted (that is, the value was found in the groupData list - or the BE_USER is "admin")
	 */
	public function check($type, $value) {
		if (isset($this->groupData[$type])) {
			if ($this->isAdmin() || GeneralUtility::inList($this->groupData[$type], $value)) {
				return TRUE;
			}
		}
		return FALSE;
	}

	/**
	 * Checking the authMode of a select field with authMode set
	 *
	 * @param string $table Table name
	 * @param string $field Field name (must be configured in TCA and of type "select" with authMode set!)
	 * @param string $value Value to evaluation (single value, must not contain any of the chars ":,|")
	 * @param string $authMode Auth mode keyword (explicitAllow, explicitDeny, individual)
	 * @return bool Whether access is granted or not
	 */
	public function checkAuthMode($table, $field, $value, $authMode) {
		// Admin users can do anything:
		if ($this->isAdmin()) {
			return TRUE;
		}
		// Allow all blank values:
		if ((string)$value === '') {
			return TRUE;
		}
		// Certain characters are not allowed in the value
		if (preg_match('/[:|,]/', $value)) {
			return FALSE;
		}
		// Initialize:
		$testValue = $table . ':' . $field . ':' . $value;
		$out = TRUE;
		// Checking value:
		switch ((string)$authMode) {
			case 'explicitAllow':
				if (!GeneralUtility::inList($this->groupData['explicit_allowdeny'], ($testValue . ':ALLOW'))) {
					$out = FALSE;
				}
				break;
			case 'explicitDeny':
				if (GeneralUtility::inList($this->groupData['explicit_allowdeny'], $testValue . ':DENY')) {
					$out = FALSE;
				}
				break;
			case 'individual':
				if (is_array($GLOBALS['TCA'][$table]) && is_array($GLOBALS['TCA'][$table]['columns'][$field])) {
					$items = $GLOBALS['TCA'][$table]['columns'][$field]['config']['items'];
					if (is_array($items)) {
						foreach ($items as $iCfg) {
							if ((string)$iCfg[1] === (string)$value && $iCfg[4]) {
								switch ((string)$iCfg[4]) {
									case 'EXPL_ALLOW':
										if (!GeneralUtility::inList($this->groupData['explicit_allowdeny'], ($testValue . ':ALLOW'))) {
											$out = FALSE;
										}
										break;
									case 'EXPL_DENY':
										if (GeneralUtility::inList($this->groupData['explicit_allowdeny'], $testValue . ':DENY')) {
											$out = FALSE;
										}
										break;
								}
								break;
							}
						}
					}
				}
				break;
		}
		return $out;
	}

	/**
	 * Checking if a language value (-1, 0 and >0 for sys_language records) is allowed to be edited by the user.
	 *
	 * @param int $langValue Language value to evaluate
	 * @return bool Returns TRUE if the language value is allowed, otherwise FALSE.
	 */
	public function checkLanguageAccess($langValue) {
		// The users language list must be non-blank - otherwise all languages are allowed.
		if (trim($this->groupData['allowed_languages']) !== '') {
			$langValue = (int)$langValue;
			// Language must either be explicitly allowed OR the lang Value be "-1" (all languages)
			if ($langValue != -1 && !$this->check('allowed_languages', $langValue)) {
				return FALSE;
			}
		}
		return TRUE;
	}

	/**
	 * Check if user has access to all existing localizations for a certain record
	 *
	 * @param string $table The table
	 * @param array $record The current record
	 * @return bool
	 */
	public function checkFullLanguagesAccess($table, $record) {
		$recordLocalizationAccess = $this->checkLanguageAccess(0);
		if ($recordLocalizationAccess && (BackendUtility::isTableLocalizable($table) || isset($GLOBALS['TCA'][$table]['ctrl']['transForeignTable']))) {
			if (isset($GLOBALS['TCA'][$table]['ctrl']['transForeignTable'])) {
				$l10nTable = $GLOBALS['TCA'][$table]['ctrl']['transForeignTable'];
				$pointerField = $GLOBALS['TCA'][$l10nTable]['ctrl']['transOrigPointerField'];
				$pointerValue = $record['uid'];
			} else {
				$l10nTable = $table;
				$pointerField = $GLOBALS['TCA'][$l10nTable]['ctrl']['transOrigPointerField'];
				$pointerValue = $record[$pointerField] > 0 ? $record[$pointerField] : $record['uid'];
			}
			$recordLocalizations = BackendUtility::getRecordsByField($l10nTable, $pointerField, $pointerValue, '', '', '', '1');
			if (is_array($recordLocalizations)) {
				foreach ($recordLocalizations as $localization) {
					$recordLocalizationAccess = $recordLocalizationAccess
						&& $this->checkLanguageAccess($localization[$GLOBALS['TCA'][$l10nTable]['ctrl']['languageField']]);
					if (!$recordLocalizationAccess) {
						break;
					}
				}
			}
		}
		return $recordLocalizationAccess;
	}

	/**
	 * Checking if a user has editing access to a record from a $GLOBALS['TCA'] table.
	 * The checks does not take page permissions and other "environmental" things into account.
	 * It only deal with record internals; If any values in the record fields disallows it.
	 * For instance languages settings, authMode selector boxes are evaluated (and maybe more in the future).
	 * It will check for workspace dependent access.
	 * The function takes an ID (int) or row (array) as second argument.
	 *
	 * @param string $table Table name
	 * @param mixed $idOrRow If integer, then this is the ID of the record. If Array this just represents fields in the record.
	 * @param bool $newRecord Set, if testing a new (non-existing) record array. Will disable certain checks that doesn't make much sense in that context.
	 * @param bool $deletedRecord Set, if testing a deleted record array.
	 * @param bool $checkFullLanguageAccess Set, whenever access to all translations of the record is required
	 * @return bool TRUE if OK, otherwise FALSE
	 */
	public function recordEditAccessInternals($table, $idOrRow, $newRecord = FALSE, $deletedRecord = FALSE, $checkFullLanguageAccess = FALSE) {
		if (!isset($GLOBALS['TCA'][$table])) {
			return FALSE;
		}
		// Always return TRUE for Admin users.
		if ($this->isAdmin()) {
			return TRUE;
		}
		// Fetching the record if the $idOrRow variable was not an array on input:
		if (!is_array($idOrRow)) {
			if ($deletedRecord) {
				$idOrRow = BackendUtility::getRecord($table, $idOrRow, '*', '', FALSE);
			} else {
				$idOrRow = BackendUtility::getRecord($table, $idOrRow);
			}
			if (!is_array($idOrRow)) {
				$this->errorMsg = 'ERROR: Record could not be fetched.';
				return FALSE;
			}
		}
		// Checking languages:
		if ($GLOBALS['TCA'][$table]['ctrl']['languageField']) {
			// Language field must be found in input row - otherwise it does not make sense.
			if (isset($idOrRow[$GLOBALS['TCA'][$table]['ctrl']['languageField']])) {
				if (!$this->checkLanguageAccess($idOrRow[$GLOBALS['TCA'][$table]['ctrl']['languageField']])) {
					$this->errorMsg = 'ERROR: Language was not allowed.';
					return FALSE;
				} elseif (
					$checkFullLanguageAccess && $idOrRow[$GLOBALS['TCA'][$table]['ctrl']['languageField']] == 0
					&& !$this->checkFullLanguagesAccess($table, $idOrRow)
				) {
					$this->errorMsg = 'ERROR: Related/affected language was not allowed.';
					return FALSE;
				}
			} else {
				$this->errorMsg = 'ERROR: The "languageField" field named "'
					. $GLOBALS['TCA'][$table]['ctrl']['languageField'] . '" was not found in testing record!';
				return FALSE;
			}
		} elseif (
			isset($GLOBALS['TCA'][$table]['ctrl']['transForeignTable']) && $checkFullLanguageAccess &&
			!$this->checkFullLanguagesAccess($table, $idOrRow)
		) {
			return FALSE;
		}
		// Checking authMode fields:
		if (is_array($GLOBALS['TCA'][$table]['columns'])) {
			foreach ($GLOBALS['TCA'][$table]['columns'] as $fieldName => $fieldValue) {
				if (isset($idOrRow[$fieldName])) {
					if (
						$fieldValue['config']['type'] === 'select' && $fieldValue['config']['authMode']
						&& $fieldValue['config']['authMode_enforce'] === 'strict'
					) {
						if (!$this->checkAuthMode($table, $fieldName, $idOrRow[$fieldName], $fieldValue['config']['authMode'])) {
							$this->errorMsg = 'ERROR: authMode "' . $fieldValue['config']['authMode']
								. '" failed for field "' . $fieldName . '" with value "'
								. $idOrRow[$fieldName] . '" evaluated';
							return FALSE;
						}
					}
				}
			}
		}
		// Checking "editlock" feature (doesn't apply to new records)
		if (!$newRecord && $GLOBALS['TCA'][$table]['ctrl']['editlock']) {
			if (isset($idOrRow[$GLOBALS['TCA'][$table]['ctrl']['editlock']])) {
				if ($idOrRow[$GLOBALS['TCA'][$table]['ctrl']['editlock']]) {
					$this->errorMsg = 'ERROR: Record was locked for editing. Only admin users can change this state.';
					return FALSE;
				}
			} else {
				$this->errorMsg = 'ERROR: The "editLock" field named "' . $GLOBALS['TCA'][$table]['ctrl']['editlock']
					. '" was not found in testing record!';
				return FALSE;
			}
		}
		// Checking record permissions
		// THIS is where we can include a check for "perms_" fields for other records than pages...
		// Process any hooks
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_userauthgroup.php']['recordEditAccessInternals'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_userauthgroup.php']['recordEditAccessInternals'] as $funcRef) {
				$params = array(
					'table' => $table,
					'idOrRow' => $idOrRow,
					'newRecord' => $newRecord
				);
				if (!GeneralUtility::callUserFunction($funcRef, $params, $this)) {
					return FALSE;
				}
			}
		}
		// Finally, return TRUE if all is well.
		return TRUE;
	}

	/**
	 * Checks a type of permission against the compiled permission integer,
	 * $compiledPermissions, and in relation to table, $tableName
	 *
	 * @param int $compiledPermissions Could typically be the "compiled permissions" integer returned by ->calcPerms
	 * @param string $tableName Is the tablename to check: If "pages" table then edit,new,delete and editcontent permissions can be checked. Other tables will be checked for "editcontent" only (and $type will be ignored)
	 * @param string $actionType For $tableName='pages' this can be 'edit' (2), 'new' (8 or 16), 'delete' (4), 'editcontent' (16). For all other tables this is ignored. (16 is used)
	 * @return bool
	 * @access public (used by ClickMenuController)
	 */
	public function isPSet($compiledPermissions, $tableName, $actionType = '') {
		if ($this->isAdmin()) {
			$result = TRUE;
		} elseif ($tableName == 'pages') {
			switch ($actionType) {
				case 'edit':
					$result = ($compiledPermissions & 2) !== 0;
					break;
				case 'new':
					// Create new page OR page content
					$result = ($compiledPermissions & 8 + 16) !== 0;
					break;
				case 'delete':
					$result = ($compiledPermissions & 4) !== 0;
					break;
				case 'editcontent':
					$result = ($compiledPermissions & 16) !== 0;
					break;
				default:
					$result = FALSE;
			}
		} else {
			$result = ($compiledPermissions & 16) !== 0;
		}
		return $result;
	}

	/**
	 * Returns TRUE if the BE_USER is allowed to *create* shortcuts in the backend modules
	 *
	 * @return bool
	 */
	public function mayMakeShortcut() {
		return $this->getTSConfigVal('options.enableBookmarks')
			&& !$this->getTSConfigVal('options.mayNotCreateEditBookmarks');
	}

	/**
	 * Checking if editing of an existing record is allowed in current workspace if that is offline.
	 * Rules for editing in offline mode:
	 * - record supports versioning and is an offline version from workspace and has the corrent stage
	 * - or record (any) is in a branch where there is a page which is a version from the workspace
	 *   and where the stage is not preventing records
	 *
	 * @param string $table Table of record
	 * @param array $recData Integer (record uid) or array where fields are at least: pid, t3ver_wsid, t3ver_stage (if versioningWS is set)
	 * @return string String error code, telling the failure state. FALSE=All ok
	 */
	public function workspaceCannotEditRecord($table, $recData) {
		// Only test offline spaces:
		if ($this->workspace !== 0) {
			if (!is_array($recData)) {
				$recData = BackendUtility::getRecord(
					$table,
					$recData,
					'pid' . ($GLOBALS['TCA'][$table]['ctrl']['versioningWS'] ? ',t3ver_wsid,t3ver_stage' : '')
				);
			}
			if (is_array($recData)) {
				// We are testing a "version" (identified by a pid of -1): it can be edited provided
				// that workspace matches and versioning is enabled for the table.
				if ((int)$recData['pid'] === -1) {
					// No versioning, basic error, inconsistency even! Such records should not have a pid of -1!
					if (!$GLOBALS['TCA'][$table]['ctrl']['versioningWS']) {
						return 'Versioning disabled for table';
					} elseif ((int)$recData['t3ver_wsid'] !== $this->workspace) {
						// So does workspace match?
						return 'Workspace ID of record didn\'t match current workspace';
					} else {
						// So is the user allowed to "use" the edit stage within the workspace?
						return $this->workspaceCheckStageForCurrent(0)
							? FALSE
							: 'User\'s access level did not allow for editing';
					}
				} else {
					// We are testing a "live" record:
					// For "Live" records, check that PID for table allows editing
					if ($res = $this->workspaceAllowLiveRecordsInPID($recData['pid'], $table)) {
						// Live records are OK in this branch, but what about the stage of branch point, if any:
						// OK
						return $res > 0
							? FALSE
							: 'Stage for versioning root point and users access level did not allow for editing';
					} else {
						// If not offline and not in versionized branch, output error:
						return 'Online record was not in versionized branch!';
					}
				}
			} else {
				return 'No record';
			}
		} else {
			// OK because workspace is 0
			return FALSE;
		}
	}

	/**
	 * Evaluates if a user is allowed to edit the offline version
	 *
	 * @param string $table Table of record
	 * @param array $recData Integer (record uid) or array where fields are at least: pid, t3ver_wsid, t3ver_stage (if versioningWS is set)
	 * @return string String error code, telling the failure state. FALSE=All ok
	 * @see workspaceCannotEditRecord()
	 */
	public function workspaceCannotEditOfflineVersion($table, $recData) {
		if ($GLOBALS['TCA'][$table]['ctrl']['versioningWS']) {
			if (!is_array($recData)) {
				$recData = BackendUtility::getRecord($table, $recData, 'uid,pid,t3ver_wsid,t3ver_stage');
			}
			if (is_array($recData)) {
				if ((int)$recData['pid'] === -1) {
					return $this->workspaceCannotEditRecord($table, $recData);
				} else {
					return 'Not an offline version';
				}
			} else {
				return 'No record';
			}
		} else {
			return 'Table does not support versioning.';
		}
	}

	/**
	 * Check if "live" records from $table may be created or edited in this PID.
	 * If the answer is FALSE it means the only valid way to create or edit records in the PID is by versioning
	 * If the answer is 1 or 2 it means it is OK to create a record, if -1 it means that it is OK in terms
	 * of versioning because the element was within a versionized branch
	 * but NOT ok in terms of the state the root point had!
	 *
	 * @param int $pid PID value to check for. OBSOLETE!
	 * @param string $table Table name
	 * @return mixed Returns FALSE if a live record cannot be created and must be versionized in order to do so. 2 means a) Workspace is "Live" or workspace allows "live edit" of records from non-versionized tables (and the $table is not versionizable). 1 and -1 means the pid is inside a versionized branch where -1 means that the branch-point did NOT allow a new record according to its state.
	 */
	public function workspaceAllowLiveRecordsInPID($pid, $table) {
		// Always for Live workspace AND if live-edit is enabled
		// and tables are completely without versioning it is ok as well.
		if (
			$this->workspace === 0
			|| $this->workspaceRec['live_edit'] && !$GLOBALS['TCA'][$table]['ctrl']['versioningWS']
			|| $GLOBALS['TCA'][$table]['ctrl']['versioningWS_alwaysAllowLiveEdit']
		) {
			// OK to create for this table.
			return 2;
		} else {
			// If the answer is FALSE it means the only valid way to create or edit records in the PID is by versioning
			return FALSE;
		}
	}

	/**
	 * Evaluates if a record from $table can be created in $pid
	 *
	 * @param int $pid Page id. This value must be the _ORIG_uid if available: So when you have pages versionized as "page" or "element" you must supply the id of the page version in the workspace!
	 * @param string $table Table name
	 * @return bool TRUE if OK.
	 */
	public function workspaceCreateNewRecord($pid, $table) {
		if ($res = $this->workspaceAllowLiveRecordsInPID($pid, $table)) {
			// If LIVE records cannot be created in the current PID due to workspace restrictions, prepare creation of placeholder-record
			if ($res < 0) {
				// Stage for versioning root point and users access level did not allow for editing
				return FALSE;
			}
		} elseif (!$GLOBALS['TCA'][$table]['ctrl']['versioningWS']) {
			// So, if no live records were allowed, we have to create a new version of this record:
			return FALSE;
		}
		return TRUE;
	}

	/**
	 * Evaluates if auto creation of a version of a record is allowed.
	 *
	 * @param string $table Table of the record
	 * @param int $id UID of record
	 * @param int $recpid PID of record
	 * @return bool TRUE if ok.
	 */
	public function workspaceAllowAutoCreation($table, $id, $recpid) {
		// Auto-creation of version: In offline workspace, test if versioning is
		// enabled and look for workspace version of input record.
		// If there is no versionized record found we will create one and save to that.
		if (
			$this->workspace !== 0
			&& $GLOBALS['TCA'][$table]['ctrl']['versioningWS'] && $recpid >= 0
			&& !BackendUtility::getWorkspaceVersionOfRecord($this->workspace, $table, $id, 'uid')
		) {
			// There must be no existing version of this record in workspace.
			return TRUE;
		}
		return FALSE;
	}

	/**
	 * Checks if an element stage allows access for the user in the current workspace
	 * In live workspace (= 0) access is always granted for any stage.
	 * Admins are always allowed.
	 * An option for custom workspaces allows members to also edit when the stage is "Review"
	 *
	 * @param int $stage Stage id from an element: -1,0 = editing, 1 = reviewer, >1 = owner
	 * @return bool TRUE if user is allowed access
	 */
	public function workspaceCheckStageForCurrent($stage) {
		// Always allow for admins
		if ($this->isAdmin()) {
			return TRUE;
		}
		if ($this->workspace !== 0 && \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('workspaces')) {
			$stage = (int)$stage;
			$stat = $this->checkWorkspaceCurrent();
			// Check if custom staging is activated
			$workspaceRec = BackendUtility::getRecord('sys_workspace', $stat['uid']);
			if ($workspaceRec['custom_stages'] > 0 && $stage !== 0 && $stage !== -10) {
				// Get custom stage record
				$workspaceStageRec = BackendUtility::getRecord('sys_workspace_stage', $stage);
				// Check if the user is responsible for the current stage
				if (
					$stat['_ACCESS'] === 'owner'
					|| $stat['_ACCESS'] === 'member'
					&& GeneralUtility::inList($workspaceStageRec['responsible_persons'], 'be_users_' . $this->user['uid'])
				) {
					return TRUE;
				}
				// Check if the user is in a group which is responsible for the current stage
				foreach ($this->userGroupsUID as $groupUid) {
					if (
						$stat['_ACCESS'] === 'owner'
						|| $stat['_ACCESS'] === 'member'
						&& GeneralUtility::inList($workspaceStageRec['responsible_persons'], 'be_groups_' . $groupUid)
					) {
						return TRUE;
					}
				}
			} elseif ($stage == -10 || $stage == -20) {
				if ($stat['_ACCESS'] === 'owner') {
					return TRUE;
				} else {
					return FALSE;
				}
			} else {
				$memberStageLimit = $this->workspaceRec['review_stage_edit'] ? 1 : 0;
				if (
					$stat['_ACCESS'] === 'owner'
					|| $stat['_ACCESS'] === 'reviewer' && $stage <= 1
					|| $stat['_ACCESS'] === 'member' && $stage <= $memberStageLimit
				) {
					return TRUE;
				}
			}
		} else {
			// Always OK for live workspace.
			return TRUE;
		}
		return FALSE;
	}

	/**
	 * Returns TRUE if the user has access to publish content from the workspace ID given.
	 * Admin-users are always granted access to do this
	 * If the workspace ID is 0 (live) all users have access also
	 * For custom workspaces it depends on whether the user is owner OR like with
	 * draft workspace if the user has access to Live workspace.
	 *
	 * @param int $wsid Workspace UID; 0,1+
	 * @return bool Returns TRUE if the user has access to publish content from the workspace ID given.
	 */
	public function workspacePublishAccess($wsid) {
		if ($this->isAdmin()) {
			return TRUE;
		}
		// If no access to workspace, of course you cannot publish!
		$retVal = FALSE;
		$wsAccess = $this->checkWorkspace($wsid);
		if ($wsAccess) {
			switch ($wsAccess['uid']) {
				case 0:
					// Live workspace
					// If access to Live workspace, no problem.
					$retVal = TRUE;
					break;
				default:
					// Custom workspace
					$retVal = $wsAccess['_ACCESS'] === 'owner' || $this->checkWorkspace(0) && !($wsAccess['publish_access'] & 2);
					// Either be an adminuser OR have access to online
					// workspace which is OK as well as long as publishing
					// access is not limited by workspace option.
			}
		}
		return $retVal;
	}

	/**
	 * Workspace swap-mode access?
	 *
	 * @return bool Returns TRUE if records can be swapped in the current workspace, otherwise FALSE
	 */
	public function workspaceSwapAccess() {
		if ($this->workspace > 0 && (int)$this->workspaceRec['swap_modes'] === 2) {
			return FALSE;
		} else {
			return TRUE;
		}
	}

	/**
	 * Returns the value/properties of a TS-object as given by $objectString, eg. 'options.dontMountAdminMounts'
	 * Nice (general!) function for returning a part of a TypoScript array!
	 *
	 * @param string $objectString Pointer to an "object" in the TypoScript array, fx. 'options.dontMountAdminMounts'
	 * @param array|string $config Optional TSconfig array: If array, then this is used and not $this->userTS. If not array, $this->userTS is used.
	 * @return array An array with two keys, "value" and "properties" where "value" is a string with the value of the object string and "properties" is an array with the properties of the object string.
	 */
	public function getTSConfig($objectString, $config = '') {
		if (!is_array($config)) {
			// Getting Root-ts if not sent
			$config = $this->userTS;
		}
		$TSConf = array('value' => NULL, 'properties' => NULL);
		$parts = GeneralUtility::trimExplode('.', $objectString, TRUE, 2);
		$key = $parts[0];
		if ($key !== '') {
			if (count($parts) > 1 && $parts[1] !== '') {
				// Go on, get the next level
				if (is_array($config[$key . '.'])) {
					$TSConf = $this->getTSConfig($parts[1], $config[$key . '.']);
				}
			} else {
				$TSConf['value'] = $config[$key];
				$TSConf['properties'] = $config[$key . '.'];
			}
		}
		return $TSConf;
	}

	/**
	 * Returns the "value" of the $objectString from the BE_USERS "User TSconfig" array
	 *
	 * @param string $objectString Object string, eg. "somestring.someproperty.somesubproperty
	 * @return string The value for that object string (object path)
	 * @see getTSConfig()
	 */
	public function getTSConfigVal($objectString) {
		$TSConf = $this->getTSConfig($objectString);
		return $TSConf['value'];
	}

	/**
	 * Returns the "properties" of the $objectString from the BE_USERS "User TSconfig" array
	 *
	 * @param string $objectString Object string, eg. "somestring.someproperty.somesubproperty
	 * @return array The properties for that object string (object path) - if any
	 * @see getTSConfig()
	 */
	public function getTSConfigProp($objectString) {
		$TSConf = $this->getTSConfig($objectString);
		return $TSConf['properties'];
	}

	/**
	 * Returns an array with the webmounts.
	 * If no webmounts, and empty array is returned.
	 * NOTICE: Deleted pages WILL NOT be filtered out! So if a mounted page has been deleted
	 *         it is STILL coming out as a webmount. This is not checked due to performance.
	 *
	 * @return array
	 */
	public function returnWebmounts() {
		return (string)$this->groupData['webmounts'] != '' ? explode(',', $this->groupData['webmounts']) : array();
	}

	/**
	 * Initializes the given mount points for the current Backend user.
	 *
	 * @param array $mountPointUids Page UIDs that should be used as web mountpoints
	 * @param bool $append If TRUE the given mount point will be appended. Otherwise the current mount points will be replaced.
	 * @return void
	 */
	public function setWebmounts(array $mountPointUids, $append = FALSE) {
		if (empty($mountPointUids)) {
			return;
		}
		if ($append) {
			$currentWebMounts = GeneralUtility::intExplode(',', $this->groupData['webmounts']);
			$mountPointUids = array_merge($currentWebMounts, $mountPointUids);
		}
		$this->groupData['webmounts'] = implode(',', array_unique($mountPointUids));
	}

	/**
	 * Returns TRUE or FALSE, depending if an alert popup (a javascript confirmation) should be shown
	 * call like $GLOBALS['BE_USER']->jsConfirmation($BITMASK)
	 *
	 * 1 - typeChange
	 * 2 - copy/move/paste
	 * 4 - delete
	 * 8 - frontend editing
	 * 128 - other (not used yet)
	 *
	 * @param int $bitmask Bitmask
	 * @return bool TRUE if the confirmation should be shown
	 */
	public function jsConfirmation($bitmask) {
		$alertPopup = $this->getTSConfig('options.alertPopups');
		if (empty($alertPopup['value'])) {
			// Default: show all warnings
			$alertPopup = 255;
		} else {
			$alertPopup = (int)$alertPopup['value'];
		}
		// Show confirmation
		return ($alertPopup & $bitmask) == $bitmask;
	}

	/**
	 * Initializes a lot of stuff like the access-lists, database-mountpoints and filemountpoints
	 * This method is called by ->backendCheckLogin() (from extending BackendUserAuthentication)
	 * if the backend user login has verified OK.
	 * Generally this is required initialization of a backend user.
	 *
	 * @return void
	 * @access private
	 * @see \TYPO3\CMS\Core\TypoScript\Parser\TypoScriptParser
	 */
	public function fetchGroupData() {
		if ($this->user['uid']) {
			// Get lists for the be_user record and set them as default/primary values.
			// Enabled Backend Modules
			$this->dataLists['modList'] = $this->user['userMods'];
			// Add Allowed Languages
			$this->dataLists['allowed_languages'] = $this->user['allowed_languages'];
			// Set user value for workspace permissions.
			$this->dataLists['workspace_perms'] = $this->user['workspace_perms'];
			// User mount points are only added if the user is not an admin as admins do not have visible
			// mountpoints fields. Processing them loads mountpoints defined when the user was a non-admin.
			if (!$this->isAdmin()) {
				// Database mountpoints
				$this->dataLists['webmount_list'] = $this->user['db_mountpoints'];
				// File mountpoints
				$this->dataLists['filemount_list'] = $this->user['file_mountpoints'];
			}
			// Fileoperation permissions
			$this->dataLists['file_permissions'] = $this->user['file_permissions'];
			// Setting default User TSconfig:
			$this->TSdataArray[] = $this->addTScomment('From $GLOBALS["TYPO3_CONF_VARS"]["BE"]["defaultUserTSconfig"]:')
				. $GLOBALS['TYPO3_CONF_VARS']['BE']['defaultUserTSconfig'];
			// Default TSconfig for admin-users
			if ($this->isAdmin()) {
				$this->TSdataArray[] = $this->addTScomment('"admin" user presets:') . '
					admPanel.enable.all = 1
				';
				if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('sys_note')) {
					$this->TSdataArray[] = '
							// Setting defaults for sys_note author / email...
						TCAdefaults.sys_note.author = ' . $this->user['realName'] . '
						TCAdefaults.sys_note.email = ' . $this->user['email'] . '
					';
				}
			}
			// BE_GROUPS:
			// Get the groups...
			// 240203: Since the group-field never contains any references to groups with a prepended table name
			// we think it's safe to just intExplode and re-implode - which should be much faster than the other function call.
			$grList = $this->db->cleanIntList($this->user[$this->usergroup_column]);
			if ($grList) {
				// Fetch groups will add a lot of information to the internal arrays: modules, accesslists, TSconfig etc.
				// Refer to fetchGroups() function.
				$this->fetchGroups($grList);
			}

			// Populating the $this->userGroupsUID -array with the groups in the order in which they were LAST included.!!
			$this->userGroupsUID = array_reverse(array_unique(array_reverse($this->includeGroupArray)));
			// Finally this is the list of group_uid's in the order they are parsed (including subgroups!)
			// and without duplicates (duplicates are presented with their last entrance in the list,
			// which thus reflects the order of the TypoScript in TSconfig)
			$this->groupList = implode(',', $this->userGroupsUID);
			$this->setCachedList($this->groupList);

			// Add the TSconfig for this specific user:
			$this->TSdataArray[] = $this->addTScomment('USER TSconfig field') . $this->user['TSconfig'];
			// Check include lines.
			$this->TSdataArray = \TYPO3\CMS\Core\TypoScript\Parser\TypoScriptParser::checkIncludeLines_array($this->TSdataArray);
			// Imploding with "[global]" will make sure that non-ended confinements with braces are ignored.
			$this->userTS_text = implode(LF . '[GLOBAL]' . LF, $this->TSdataArray);
			if (!$this->userTS_dontGetCached) {
				// Perform TS-Config parsing with condition matching
				$parseObj = GeneralUtility::makeInstance(\TYPO3\CMS\Backend\Configuration\TsConfigParser::class);
				$res = $parseObj->parseTSconfig($this->userTS_text, 'userTS');
				if ($res) {
					$this->userTS = $res['TSconfig'];
					$this->userTSUpdated = (bool)$res['cached'];
				}
			} else {
				// Parsing the user TSconfig (or getting from cache)
				$hash = md5('userTS:' . $this->userTS_text);
				$cachedContent = BackendUtility::getHash($hash);
				if (is_array($cachedContent) && !$this->userTS_dontGetCached) {
					$this->userTS = $cachedContent;
				} else {
					$parseObj = GeneralUtility::makeInstance(\TYPO3\CMS\Core\TypoScript\Parser\TypoScriptParser::class);
					$parseObj->parse($this->userTS_text);
					$this->userTS = $parseObj->setup;
					BackendUtility::storeHash($hash, $this->userTS, 'BE_USER_TSconfig');
					// Update UC:
					$this->userTSUpdated = TRUE;
				}
			}
			// Processing webmounts
			// Admin's always have the root mounted
			if ($this->isAdmin() && !$this->getTSConfigVal('options.dontMountAdminMounts')) {
				$this->dataLists['webmount_list'] = '0,' . $this->dataLists['webmount_list'];
			}
			// The lists are cleaned for duplicates
			$this->groupData['webmounts'] = GeneralUtility::uniqueList($this->dataLists['webmount_list']);
			$this->groupData['pagetypes_select'] = GeneralUtility::uniqueList($this->dataLists['pagetypes_select']);
			$this->groupData['tables_select'] = GeneralUtility::uniqueList($this->dataLists['tables_modify'] . ',' . $this->dataLists['tables_select']);
			$this->groupData['tables_modify'] = GeneralUtility::uniqueList($this->dataLists['tables_modify']);
			$this->groupData['non_exclude_fields'] = GeneralUtility::uniqueList($this->dataLists['non_exclude_fields']);
			$this->groupData['explicit_allowdeny'] = GeneralUtility::uniqueList($this->dataLists['explicit_allowdeny']);
			$this->groupData['allowed_languages'] = GeneralUtility::uniqueList($this->dataLists['allowed_languages']);
			$this->groupData['custom_options'] = GeneralUtility::uniqueList($this->dataLists['custom_options']);
			$this->groupData['modules'] = GeneralUtility::uniqueList($this->dataLists['modList']);
			$this->groupData['file_permissions'] = GeneralUtility::uniqueList($this->dataLists['file_permissions']);
			$this->groupData['workspace_perms'] = $this->dataLists['workspace_perms'];

			// Checking read access to webmounts:
			if (trim($this->groupData['webmounts']) !== '') {
				$webmounts = explode(',', $this->groupData['webmounts']);
				// Explode mounts
				// Selecting all webmounts with permission clause for reading
				$where = 'deleted=0 AND uid IN (' . $this->groupData['webmounts'] . ') AND ' . $this->getPagePermsClause(1);
				$MProws = $this->db->exec_SELECTgetRows('uid', 'pages', $where, '', '', '', 'uid');
				foreach ($webmounts as $idx => $mountPointUid) {
					// If the mount ID is NOT found among selected pages, unset it:
					if ($mountPointUid > 0 && !isset($MProws[$mountPointUid])) {
						unset($webmounts[$idx]);
					}
				}
				// Implode mounts in the end.
				$this->groupData['webmounts'] = implode(',', $webmounts);
			}
			// Setting up workspace situation (after webmounts are processed!):
			$this->workspaceInit();
		}
	}

	/**
	 * Fetches the group records, subgroups and fills internal arrays.
	 * Function is called recursively to fetch subgroups
	 *
	 * @param string $grList Commalist of be_groups uid numbers
	 * @param string $idList List of already processed be_groups-uids so the function will not fall into a eternal recursion.
	 * @return void
	 * @access private
	 */
	public function fetchGroups($grList, $idList = '') {
		// Fetching records of the groups in $grList (which are not blocked by lockedToDomain either):
		$lockToDomain_SQL = ' AND (lockToDomain=\'\' OR lockToDomain IS NULL OR lockToDomain=\'' . GeneralUtility::getIndpEnv('HTTP_HOST') . '\')';
		$whereSQL = 'deleted=0 AND hidden=0 AND pid=0 AND uid IN (' . $grList . ')' . $lockToDomain_SQL;
		// Hook for manipulation of the WHERE sql sentence which controls which BE-groups are included
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_userauthgroup.php']['fetchGroupQuery'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_userauthgroup.php']['fetchGroupQuery'] as $classRef) {
				$hookObj = GeneralUtility::getUserObj($classRef);
				if (method_exists($hookObj, 'fetchGroupQuery_processQuery')) {
					$whereSQL = $hookObj->fetchGroupQuery_processQuery($this, $grList, $idList, $whereSQL);
				}
			}
		}
		$res = $this->db->exec_SELECTquery('*', $this->usergroup_table, $whereSQL);
		// The userGroups array is filled
		while ($row = $this->db->sql_fetch_assoc($res)) {
			$this->userGroups[$row['uid']] = $row;
		}
		$this->db->sql_free_result($res);
		// Traversing records in the correct order
		$include_staticArr = GeneralUtility::intExplode(',', $grList);
		// Traversing list
		foreach ($include_staticArr as $uid) {
			// Get row:
			$row = $this->userGroups[$uid];
			// Must be an array and $uid should not be in the idList, because then it is somewhere previously in the grouplist
			if (is_array($row) && !GeneralUtility::inList($idList, $uid)) {
				// Include sub groups
				if (trim($row['subgroup'])) {
					// Make integer list
					$theList = implode(',', GeneralUtility::intExplode(',', $row['subgroup']));
					// Call recursively, pass along list of already processed groups so they are not recursed again.
					$this->fetchGroups($theList, $idList . ',' . $uid);
				}
				// Add the group uid, current list, TSconfig to the internal arrays.
				$this->includeGroupArray[] = $uid;
				$this->includeHierarchy[] = $idList;
				$this->TSdataArray[] = $this->addTScomment('Group "' . $row['title'] . '" [' . $row['uid'] . '] TSconfig field:') . $row['TSconfig'];
				// Mount group database-mounts
				if (($this->user['options'] & 1) == 1) {
					$this->dataLists['webmount_list'] .= ',' . $row['db_mountpoints'];
				}
				// Mount group file-mounts
				if (($this->user['options'] & 2) == 2) {
					$this->dataLists['filemount_list'] .= ',' . $row['file_mountpoints'];
				}
				// The lists are made: groupMods, tables_select, tables_modify, pagetypes_select, non_exclude_fields, explicit_allowdeny, allowed_languages, custom_options
				$this->dataLists['modList'] .= ',' . $row['groupMods'];
				$this->dataLists['tables_select'] .= ',' . $row['tables_select'];
				$this->dataLists['tables_modify'] .= ',' . $row['tables_modify'];
				$this->dataLists['pagetypes_select'] .= ',' . $row['pagetypes_select'];
				$this->dataLists['non_exclude_fields'] .= ',' . $row['non_exclude_fields'];
				$this->dataLists['explicit_allowdeny'] .= ',' . $row['explicit_allowdeny'];
				$this->dataLists['allowed_languages'] .= ',' . $row['allowed_languages'];
				$this->dataLists['custom_options'] .= ',' . $row['custom_options'];
				$this->dataLists['file_permissions'] .= ',' . $row['file_permissions'];
				// Setting workspace permissions:
				$this->dataLists['workspace_perms'] |= $row['workspace_perms'];
				// If this function is processing the users OWN group-list (not subgroups) AND
				// if the ->firstMainGroup is not set, then the ->firstMainGroup will be set.
				if ($idList === '' && !$this->firstMainGroup) {
					$this->firstMainGroup = $uid;
				}
			}
		}
		// HOOK: fetchGroups_postProcessing
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_userauthgroup.php']['fetchGroups_postProcessing'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_userauthgroup.php']['fetchGroups_postProcessing'] as $_funcRef) {
				$_params = array();
				GeneralUtility::callUserFunction($_funcRef, $_params, $this);
			}
		}
	}

	/**
	 * Updates the field be_users.usergroup_cached_list if the groupList of the user
	 * has changed/is different from the current list.
	 * The field "usergroup_cached_list" contains the list of groups which the user is a member of.
	 * After authentication (where these functions are called...) one can depend on this list being
	 * a representation of the exact groups/subgroups which the BE_USER has membership with.
	 *
	 * @param string $cList The newly compiled group-list which must be compared with the current list in the user record and possibly stored if a difference is detected.
	 * @return void
	 * @access private
	 */
	public function setCachedList($cList) {
		if ((string)$cList != (string)$this->user['usergroup_cached_list']) {
			$this->db->exec_UPDATEquery('be_users', 'uid=' . (int)$this->user['uid'], array('usergroup_cached_list' => $cList));
		}
	}

	/**
	 * Sets up all file storages for a user.
	 * Needs to be called AFTER the groups have been loaded.
	 *
	 * @return void
	 */
	protected function initializeFileStorages() {
		$this->fileStorages = array();
		/** @var $storageRepository \TYPO3\CMS\Core\Resource\StorageRepository */
		$storageRepository = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Resource\StorageRepository::class);
		// Admin users have all file storages visible, without any filters
		if ($this->isAdmin()) {
			$storageObjects = $storageRepository->findAll();
			foreach ($storageObjects as $storageObject) {
				$this->fileStorages[$storageObject->getUid()] = $storageObject;
			}
		} else {
			// Regular users only have storages that are defined in their filemounts
			// Permissions and file mounts for the storage are added in StoragePermissionAspect
			foreach ($this->getFileMountRecords() as $row) {
				if (!array_key_exists((int)$row['base'], $this->fileStorages)) {
					$storageObject = $storageRepository->findByUid($row['base']);
					$this->fileStorages[$storageObject->getUid()] = $storageObject;
				}
			}
		}

		// This has to be called always in order to set certain filters
		$this->evaluateUserSpecificFileFilterSettings();
	}

	/**
	 * Returns an array of category mount points. The category permissions from BE Groups
	 * are also taken into consideration and are merged into User permissions.
	 *
	 * @return array
	 */
	public function getCategoryMountPoints() {
		$categoryMountPoints = '';

		// Category mounts of the groups
		if (is_array($this->userGroups)) {
			foreach ($this->userGroups as $group) {
				if ($group['category_perms']) {
					$categoryMountPoints .= ',' . $group['category_perms'];
				}
			}
		}

		// Category mounts of the user record
		if ($this->user['category_perms']) {
			$categoryMountPoints .= ',' . $this->user['category_perms'];
		}

		// Make the ids unique
		$categoryMountPoints = GeneralUtility::trimExplode(',', $categoryMountPoints);
		$categoryMountPoints = array_filter($categoryMountPoints); // remove empty value
		$categoryMountPoints = array_unique($categoryMountPoints); // remove unique value

		return $categoryMountPoints;
	}

	/**
	 * Returns an array of file mount records, taking workspaces and user home and group home directories into account
	 * Needs to be called AFTER the groups have been loaded.
	 *
	 * @return array
	 * @internal
	 */
	public function getFileMountRecords() {
		static $fileMountRecordCache = array();

		if (!empty($fileMountRecordCache)) {
			return $fileMountRecordCache;
		}

		// Processing file mounts (both from the user and the groups)
		$fileMounts = array_unique(GeneralUtility::intExplode(',', $this->dataLists['filemount_list'], TRUE));

		// Limit file mounts if set in workspace record
		if ($this->workspace > 0 && !empty($this->workspaceRec['file_mountpoints'])) {
			$workspaceFileMounts = GeneralUtility::intExplode(',', $this->workspaceRec['file_mountpoints'], TRUE);
			$fileMounts = array_intersect($fileMounts, $workspaceFileMounts);
		}

		if (!empty($fileMounts)) {
			$orderBy = isset($GLOBALS['TCA']['sys_filemounts']['ctrl']['default_sortby'])
				? $this->db->stripOrderBy($GLOBALS['TCA']['sys_filemounts']['ctrl']['default_sortby'])
				: 'sorting';
			$fileMountRecords = $this->db->exec_SELECTgetRows(
				'*',
				'sys_filemounts',
				'deleted=0 AND hidden=0 AND pid=0 AND uid IN (' . implode(',', $fileMounts) . ')',
				'',
				$orderBy
			);
			foreach ($fileMountRecords as $fileMount) {
				$fileMountRecordCache[$fileMount['base'] . $fileMount['path']] = $fileMount;
			}
		}

		// Read-only file mounts
		$readOnlyMountPoints = trim($GLOBALS['BE_USER']->getTSConfigVal('options.folderTree.altElementBrowserMountPoints'));
		if ($readOnlyMountPoints) {
			// We cannot use the API here but need to fetch the default storage record directly
			// to not instantiate it (which directly applies mount points) before all mount points are resolved!
			$whereClause = 'is_default=1 ' . BackendUtility::BEenableFields('sys_file_storage') . BackendUtility::deleteClause('sys_file_storage');
			$defaultStorageRow = $this->db->exec_SELECTgetSingleRow('uid', 'sys_file_storage', $whereClause);
			$readOnlyMountPointArray = GeneralUtility::trimExplode(',', $readOnlyMountPoints);
			foreach ($readOnlyMountPointArray as $readOnlyMountPoint) {
				$readOnlyMountPointConfiguration = GeneralUtility::trimExplode(':', $readOnlyMountPoint);
				if (count($readOnlyMountPointConfiguration) === 2) {
					// A storage is passed in the configuration
					$storageUid = (int)$readOnlyMountPointConfiguration[0];
					$path = $readOnlyMountPointConfiguration[1];
				} else {
					if (empty($defaultStorageRow)) {
						throw new \RuntimeException('Read only mount points have been defined in User TsConfig without specific storage, but a default storage could not be resolved.', 1404472382);
					}
					// Backwards compatibility: If no storage is passed, we use the default storage
					$storageUid = $defaultStorageRow['uid'];
					$path = $readOnlyMountPointConfiguration[0];
				}
				$fileMountRecordCache[$storageUid . $path] = array(
					'base' => $storageUid,
					'title' => $path,
					'path' => $path,
					'read_only' => TRUE
				);
			}
		}


		// Personal or Group filemounts are not accessible if file mount list is set in workspace record
		if ($this->workspace <= 0 || empty($this->workspaceRec['file_mountpoints'])) {
			// If userHomePath is set, we attempt to mount it
			if ($GLOBALS['TYPO3_CONF_VARS']['BE']['userHomePath']) {
				list($userHomeStorageUid, $userHomeFilter) = explode(':', $GLOBALS['TYPO3_CONF_VARS']['BE']['userHomePath'], 2);
				$userHomeStorageUid = (int)$userHomeStorageUid;
				$userHomeFilter = '/' . ltrim($userHomeFilter, '/');
				if ($userHomeStorageUid > 0) {
					// Try and mount with [uid]_[username]
					$path = $userHomeFilter . $this->user['uid'] . '_' . $this->user['username'] . $GLOBALS['TYPO3_CONF_VARS']['BE']['userUploadDir'];
					$fileMountRecordCache[$userHomeStorageUid . $path] = array(
						'base' => $userHomeStorageUid,
						'title' => $this->user['username'],
						'path' => $path,
						'read_only' => FALSE,
						'user_mount' => TRUE
					);
					// Try and mount with only [uid]
					$path = $userHomeFilter . $this->user['uid'] . $GLOBALS['TYPO3_CONF_VARS']['BE']['userUploadDir'];
					$fileMountRecordCache[$userHomeStorageUid . $path] = array(
						'base' => $userHomeStorageUid,
						'title' => $this->user['username'],
						'path' => $path,
						'read_only' => FALSE,
						'user_mount' => TRUE
					);
				}
			}

			// Mount group home-dirs
			if ((is_array($this->user) && $this->user['options'] & 2) == 2 && $GLOBALS['TYPO3_CONF_VARS']['BE']['groupHomePath'] != '') {
				// If groupHomePath is set, we attempt to mount it
				list($groupHomeStorageUid, $groupHomeFilter) = explode(':', $GLOBALS['TYPO3_CONF_VARS']['BE']['groupHomePath'], 2);
				$groupHomeStorageUid = (int)$groupHomeStorageUid;
				$groupHomeFilter = '/' . ltrim($groupHomeFilter, '/');
				if ($groupHomeStorageUid > 0) {
					foreach ($this->userGroups as $groupData) {
						$path = $groupHomeFilter . $groupData['uid'];
						$fileMountRecordCache[$groupHomeStorageUid . $path] = array(
							'base' => $groupHomeStorageUid,
							'title' => $groupData['title'],
							'path' => $path,
							'read_only' => FALSE,
							'user_mount' => TRUE
						);
					}
				}
			}
		}

		return $fileMountRecordCache;
	}

	/**
	 * Returns an array with the filemounts for the user.
	 * Each filemount is represented with an array of a "name", "path" and "type".
	 * If no filemounts an empty array is returned.
	 *
	 * @api
	 * @return \TYPO3\CMS\Core\Resource\ResourceStorage[]
	 */
	public function getFileStorages() {
		// Initializing file mounts after the groups are fetched
		if ($this->fileStorages === NULL) {
			$this->initializeFileStorages();
		}
		return $this->fileStorages;
	}

	/**
	 * Adds filters based on what the user has set
	 * this should be done in this place, and called whenever needed,
	 * but only when needed
	 *
	 * @return void
	 */
	public function evaluateUserSpecificFileFilterSettings() {
		// Add the option for also displaying the non-hidden files
		if ($this->uc['showHiddenFilesAndFolders']) {
			\TYPO3\CMS\Core\Resource\Filter\FileNameFilter::setShowHiddenFilesAndFolders(TRUE);
		}
	}

	/**
	 * Returns the information about file permissions.
	 * Previously, this was stored in the DB field fileoper_perms now it is file_permissions.
	 * Besides it can be handled via userTSconfig
	 *
	 * permissions.file.default {
	 * addFile = 1
	 * readFile = 1
	 * writeFile = 1
	 * copyFile = 1
	 * moveFile = 1
	 * renameFile = 1
	 * unzipFile = 1
	 * deleteFile = 1
	 *
	 * addFolder = 1
	 * readFolder = 1
	 * writeFolder = 1
	 * copyFolder = 1
	 * moveFolder = 1
	 * renameFolder = 1
	 * deleteFolder = 1
	 * recursivedeleteFolder = 1
	 * }
	 *
	 * # overwrite settings for a specific storageObject
	 * permissions.file.storage.StorageUid {
	 * readFile = 1
	 * recursivedeleteFolder = 0
	 * }
	 *
	 * Please note that these permissions only apply, if the storage has the
	 * capabilities (browseable, writable), and if the driver allows for writing etc
	 *
	 * @api
	 * @return array
	 */
	public function getFilePermissions() {
		if (!isset($this->filePermissions)) {
			$filePermissions = array(
				// File permissions
				'addFile' => FALSE,
				'readFile' => FALSE,
				'writeFile' => FALSE,
				'copyFile' => FALSE,
				'moveFile' => FALSE,
				'renameFile' => FALSE,
				'unzipFile' => FALSE,
				'deleteFile' => FALSE,
				// Folder permissions
				'addFolder' => FALSE,
				'readFolder' => FALSE,
				'writeFolder' => FALSE,
				'copyFolder' => FALSE,
				'moveFolder' => FALSE,
				'renameFolder' => FALSE,
				'deleteFolder' => FALSE,
				'recursivedeleteFolder' => FALSE
			);
			if ($this->isAdmin()) {
				$filePermissions = array_map('is_bool', $filePermissions);
			} else {
				$userGroupRecordPermissions = GeneralUtility::trimExplode(',', $this->groupData['file_permissions'], TRUE);
				array_walk(
					$userGroupRecordPermissions,
					function($permission) use (&$filePermissions) {
						$filePermissions[$permission] = TRUE;
					}
				);

				// Finally overlay any userTSconfig
				$permissionsTsConfig = $this->getTSConfigProp('permissions.file.default');
				if (!empty($permissionsTsConfig)) {
					array_walk(
						$permissionsTsConfig,
						function($value, $permission) use (&$filePermissions) {
							$filePermissions[$permission] = (bool)$value;
						}
					);
				}
			}
			$this->filePermissions = $filePermissions;
		}
		return $this->filePermissions;
	}

	/**
	 * Gets the file permissions for a storage
	 * by merging any storage-specific permissions for a
	 * storage with the default settings.
	 * Admin users will always get the default settings.
	 *
	 * @api
	 * @param \TYPO3\CMS\Core\Resource\ResourceStorage $storageObject
	 * @return array
	 */
	public function getFilePermissionsForStorage(\TYPO3\CMS\Core\Resource\ResourceStorage $storageObject) {
		$finalUserPermissions = $this->getFilePermissions();
		if (!$this->isAdmin()) {
			$storageFilePermissions = $this->getTSConfigProp('permissions.file.storage.' . $storageObject->getUid());
			if (!empty($storageFilePermissions)) {
				array_walk(
					$storageFilePermissions,
					function($value, $permission) use (&$finalUserPermissions) {
						$finalUserPermissions[$permission] = (bool)$value;
					}
				);
			}
		}
		return $finalUserPermissions;
	}

	/**
	 * Returns a \TYPO3\CMS\Core\Resource\Folder object that is used for uploading
	 * files by default.
	 * This is used for RTE and its magic images, as well as uploads
	 * in the TCEforms fields, unless otherwise configured (will be added in the future)
	 *
	 * the default upload folder for a user is the defaultFolder on the first
	 * filestorage/filemount that the user can access
	 * however, you can set the users' upload folder like this:
	 *
	 * options.defaultUploadFolder = 3:myfolder/yourfolder/
	 *
	 * @return \TYPO3\CMS\Core\Resource\Folder|boolean The default upload folder for this user
	 */
	public function getDefaultUploadFolder() {
		$uploadFolder = $this->getTSConfigVal('options.defaultUploadFolder');
		if ($uploadFolder) {
			$uploadFolder = \TYPO3\CMS\Core\Resource\ResourceFactory::getInstance()->getFolderObjectFromCombinedIdentifier($uploadFolder);
		} else {
			foreach($this->getFileStorages() as $storage) {
				try {
					$uploadFolder = $storage->getDefaultFolder();
					break;
				} catch (\TYPO3\CMS\Core\Resource\Exception $folderAccessException) {
					// If the folder is not accessible (no permissions / does not exist) try the next one.
				}
			}
		}
		if ($uploadFolder instanceof \TYPO3\CMS\Core\Resource\Folder) {
			return $uploadFolder;
		} else {
			return FALSE;
		}
	}

	/**
	* Returns a \TYPO3\CMS\Core\Resource\Folder object that could be used for uploading
	* temporary files in user context. The folder _temp_ below the default upload folder
	* of the user is used.
	*
	* @return NULL|\TYPO3\CMS\Core\Resource\Folder
	* @see \TYPO3\CMS\Core\Authentication\BackendUserAuthentication::getDefaultUploadFolder();
	*/
	public function getDefaultUploadTemporaryFolder() {
		$defaultTemporaryFolder = NULL;
		$defaultFolder = $this->getDefaultUploadFolder();

		if ($defaultFolder !== FALSE) {
			$tempFolderName = '_temp_';
			$createFolder = !$defaultFolder->hasFolder($tempFolderName);
			if ($createFolder === TRUE) {
				try {
					$defaultTemporaryFolder = $defaultFolder->createFolder($tempFolderName);
				} catch (\TYPO3\CMS\Core\Resource\Exception $folderAccessException) {}
			} else {
				$defaultTemporaryFolder = $defaultFolder->getSubfolder($tempFolderName);
			}
		}

		return $defaultTemporaryFolder;
	}

	/**
	 * Creates a TypoScript comment with the string text inside.
	 *
	 * @param string $str The text to wrap in comment prefixes and delimiters.
	 * @return string TypoScript comment with the string text inside.
	 */
	public function addTScomment($str) {
		$delimiter = '# ***********************************************';
		$out = $delimiter . LF;
		$lines = GeneralUtility::trimExplode(LF, $str);
		foreach ($lines as $v) {
			$out .= '# ' . $v . LF;
		}
		$out .= $delimiter . LF;
		return $out;
	}

	/**
	 * Initializing workspace.
	 * Called from within this function, see fetchGroupData()
	 *
	 * @return void
	 * @see fetchGroupData()
	 */
	public function workspaceInit() {
		// Initializing workspace by evaluating and setting the workspace, possibly updating it in the user record!
		$this->setWorkspace($this->user['workspace_id']);
		// Limiting the DB mountpoints if there any selected in the workspace record
		$this->initializeDbMountpointsInWorkspace();
		if ($allowed_languages = $this->getTSConfigVal('options.workspaces.allowed_languages.' . $this->workspace)) {
			$this->groupData['allowed_languages'] = $allowed_languages;
			$this->groupData['allowed_languages'] = GeneralUtility::uniqueList($this->groupData['allowed_languages']);
		}
	}

	/**
	 * Limiting the DB mountpoints if there any selected in the workspace record
	 *
	 * @return void
	 */
	protected function initializeDbMountpointsInWorkspace() {
		$dbMountpoints = trim($this->workspaceRec['db_mountpoints']);
		if ($this->workspace > 0 && $dbMountpoints != '') {
			$filteredDbMountpoints = array();
			// Notice: We cannot call $this->getPagePermsClause(1);
			// as usual because the group-list is not available at this point.
			// But bypassing is fine because all we want here is check if the
			// workspace mounts are inside the current webmounts rootline.
			// The actual permission checking on page level is done elsewhere
			// as usual anyway before the page tree is rendered.
			$readPerms = '1=1';
			// Traverse mount points of the
			$dbMountpoints = GeneralUtility::intExplode(',', $dbMountpoints);
			foreach ($dbMountpoints as $mpId) {
				if ($this->isInWebMount($mpId, $readPerms)) {
					$filteredDbMountpoints[] = $mpId;
				}
			}
			// Re-insert webmounts:
			$filteredDbMountpoints = array_unique($filteredDbMountpoints);
			$this->groupData['webmounts'] = implode(',', $filteredDbMountpoints);
		}
	}

	/**
	 * Checking if a workspace is allowed for backend user
	 *
	 * @param mixed $wsRec If integer, workspace record is looked up, if array it is seen as a Workspace record with at least uid, title, members and adminusers columns. Can be faked for workspaces uid 0 and -1 (online and offline)
	 * @param string $fields List of fields to select. Default fields are: uid,title,adminusers,members,reviewers,publish_access,stagechg_notification
	 * @return array Output will also show how access was granted. Admin users will have a true output regardless of input.
	 */
	public function checkWorkspace($wsRec, $fields = 'uid,title,adminusers,members,reviewers,publish_access,stagechg_notification') {
		$retVal = FALSE;
		// If not array, look up workspace record:
		if (!is_array($wsRec)) {
			switch ((string)$wsRec) {
				case '0':
					$wsRec = array('uid' => $wsRec);
					break;
				default:
					if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('workspaces')) {
						$wsRec = $this->db->exec_SELECTgetSingleRow($fields,
							'sys_workspace',
							'pid=0 AND uid=' . (int)$wsRec . BackendUtility::deleteClause('sys_workspace'),
							'',
							'title'
						);
					}
			}
		}
		// If wsRec is set to an array, evaluate it:
		if (is_array($wsRec)) {
			if ($this->isAdmin()) {
				return array_merge($wsRec, array('_ACCESS' => 'admin'));
			} else {
				switch ((string)$wsRec['uid']) {
					case '0':
						$retVal = $this->groupData['workspace_perms'] & 1
							? array_merge($wsRec, array('_ACCESS' => 'online'))
							: FALSE;
						break;
					default:
						// Checking if the guy is admin:
						if (GeneralUtility::inList($wsRec['adminusers'], 'be_users_' . $this->user['uid'])) {
							return array_merge($wsRec, array('_ACCESS' => 'owner'));
						}
						// Checking if he is owner through a user group of his:
						foreach ($this->userGroupsUID as $groupUid) {
							if (GeneralUtility::inList($wsRec['adminusers'], 'be_groups_' . $groupUid)) {
								return array_merge($wsRec, array('_ACCESS' => 'owner'));
							}
						}
						// Checking if he is reviewer user:
						if (GeneralUtility::inList($wsRec['reviewers'], 'be_users_' . $this->user['uid'])) {
							return array_merge($wsRec, array('_ACCESS' => 'reviewer'));
						}
						// Checking if he is reviewer through a user group of his:
						foreach ($this->userGroupsUID as $groupUid) {
							if (GeneralUtility::inList($wsRec['reviewers'], 'be_groups_' . $groupUid)) {
								return array_merge($wsRec, array('_ACCESS' => 'reviewer'));
							}
						}
						// Checking if he is member as user:
						if (GeneralUtility::inList($wsRec['members'], 'be_users_' . $this->user['uid'])) {
							return array_merge($wsRec, array('_ACCESS' => 'member'));
						}
						// Checking if he is member through a user group of his:
						foreach ($this->userGroupsUID as $groupUid) {
							if (GeneralUtility::inList($wsRec['members'], 'be_groups_' . $groupUid)) {
								return array_merge($wsRec, array('_ACCESS' => 'member'));
							}
						}
				}
			}
		}
		return $retVal;
	}

	/**
	 * Uses checkWorkspace() to check if current workspace is available for user.
	 * This function caches the result and so can be called many times with no performance loss.
	 *
	 * @return array See checkWorkspace()
	 * @see checkWorkspace()
	 */
	public function checkWorkspaceCurrent() {
		if (!isset($this->checkWorkspaceCurrent_cache)) {
			$this->checkWorkspaceCurrent_cache = $this->checkWorkspace($this->workspace);
		}
		return $this->checkWorkspaceCurrent_cache;
	}

	/**
	 * Setting workspace ID
	 *
	 * @param int $workspaceId ID of workspace to set for backend user. If not valid the default workspace for BE user is found and set.
	 * @return void
	 */
	public function setWorkspace($workspaceId) {
		// Check workspace validity and if not found, revert to default workspace.
		if (!$this->setTemporaryWorkspace($workspaceId)) {
			$this->setDefaultWorkspace();
		}
		// Unset access cache:
		$this->checkWorkspaceCurrent_cache = NULL;
		// If ID is different from the stored one, change it:
		if ((int)$this->workspace !== (int)$this->user['workspace_id']) {
			$this->user['workspace_id'] = $this->workspace;
			$this->db->exec_UPDATEquery('be_users', 'uid=' . (int)$this->user['uid'], array('workspace_id' => $this->user['workspace_id']));
			$this->simplelog('User changed workspace to "' . $this->workspace . '"');
		}
	}

	/**
	 * Sets a temporary workspace in the context of the current backend user.
	 *
	 * @param int $workspaceId
	 * @return bool
	 */
	public function setTemporaryWorkspace($workspaceId) {
		$result = FALSE;
		$workspaceRecord = $this->checkWorkspace($workspaceId, '*');

		if ($workspaceRecord) {
			$this->workspaceRec = $workspaceRecord;
			$this->workspace = (int)$workspaceId;
			$result = TRUE;
		}

		return $result;
	}

	/**
	 * Sets the default workspace in the context of the current backend user.
	 *
	 * @return void
	 */
	public function setDefaultWorkspace() {
		$this->workspace = (int)$this->getDefaultWorkspace();
		$this->workspaceRec = $this->checkWorkspace($this->workspace, '*');
	}

	/**
	 * Setting workspace preview state for user:
	 *
	 * @param bool $previewState State of user preview.
	 * @return void
	 */
	public function setWorkspacePreview($previewState) {
		$this->user['workspace_preview'] = $previewState;
		$this->db->exec_UPDATEquery('be_users', 'uid=' . (int)$this->user['uid'], array('workspace_preview' => $this->user['workspace_preview']));
	}

	/**
	 * Return default workspace ID for user,
	 * If EXT:workspaces is not installed the user will be pushed the the
	 * Live workspace
	 *
	 * @return int Default workspace id. If no workspace is available it will be "-99
	 */
	public function getDefaultWorkspace() {
		$defaultWorkspace = -99;
		if (!\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('workspaces') || $this->checkWorkspace(0)) {
			// Check online
			$defaultWorkspace = 0;
		} elseif ($this->checkWorkspace(-1)) {
			// Check offline
			$defaultWorkspace = -1;
		} elseif (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('workspaces')) {
			// Traverse custom workspaces:
			$workspaces = $this->db->exec_SELECTgetRows('uid,title,adminusers,members,reviewers', 'sys_workspace', 'pid=0' . BackendUtility::deleteClause('sys_workspace'), '', 'title');
			foreach ($workspaces as $rec) {
				if ($this->checkWorkspace($rec)) {
					$defaultWorkspace = $rec['uid'];
					break;
				}
			}
		}
		return $defaultWorkspace;
	}

	/**
	 * Writes an entry in the logfile/table
	 * Documentation in "TYPO3 Core API"
	 *
	 * @param int $type Denotes which module that has submitted the entry. See "TYPO3 Core API". Use "4" for extensions.
	 * @param int $action Denotes which specific operation that wrote the entry. Use "0" when no sub-categorizing applies
	 * @param int $error Flag. 0 = message, 1 = error (user problem), 2 = System Error (which should not happen), 3 = security notice (admin)
	 * @param int $details_nr The message number. Specific for each $type and $action. This will make it possible to translate errormessages to other languages
	 * @param string $details Default text that follows the message (in english!). Possibly translated by identification through type/action/details_nr
	 * @param array $data Data that follows the log. Might be used to carry special information. If an array the first 5 entries (0-4) will be sprintf'ed with the details-text
	 * @param string $tablename Table name. Special field used by tce_main.php.
	 * @param int|string $recuid Record UID. Special field used by tce_main.php.
	 * @param int|string $recpid Record PID. Special field used by tce_main.php. OBSOLETE
	 * @param int $event_pid The page_uid (pid) where the event occurred. Used to select log-content for specific pages.
	 * @param string $NEWid Special field used by tce_main.php. NEWid string of newly created records.
	 * @param int $userId Alternative Backend User ID (used for logging login actions where this is not yet known).
	 * @return int Log entry ID.
	 */
	public function writelog($type, $action, $error, $details_nr, $details, $data, $tablename = '', $recuid = '', $recpid = '', $event_pid = -1, $NEWid = '', $userId = 0) {
		if (!$userId && isset($this->user['uid'])) {
			$userId = $this->user['uid'];
		}

		$fields_values = array(
			'userid' => (int)$userId,
			'type' => (int)$type,
			'action' => (int)$action,
			'error' => (int)$error,
			'details_nr' => (int)$details_nr,
			'details' => $details,
			'log_data' => serialize($data),
			'tablename' => $tablename,
			'recuid' => (int)$recuid,
			'IP' => (string)GeneralUtility::getIndpEnv('REMOTE_ADDR'),
			'tstamp' => $GLOBALS['EXEC_TIME'],
			'event_pid' => (int)$event_pid,
			'NEWid' => $NEWid,
			'workspace' => $this->workspace
		);
		$this->db->exec_INSERTquery('sys_log', $fields_values);
		return $this->db->sql_insert_id();
	}

	/**
	 * Simple logging function
	 *
	 * @param string $message Log message
	 * @param string $extKey Option extension key / module name
	 * @param int $error Error level. 0 = message, 1 = error (user problem), 2 = System Error (which should not happen), 3 = security notice (admin)
	 * @return int Log entry UID
	 */
	public function simplelog($message, $extKey = '', $error = 0) {
		return $this->writelog(4, 0, $error, 0, ($extKey ? '[' . $extKey . '] ' : '') . $message, array());
	}

	/**
	 * Sends a warning to $email if there has been a certain amount of failed logins during a period.
	 * If a login fails, this function is called. It will look up the sys_log to see if there
	 * have been more than $max failed logins the last $secondsBack seconds (default 3600).
	 * If so, an email with a warning is sent to $email.
	 *
	 * @param string $email Email address
	 * @param int $secondsBack Number of sections back in time to check. This is a kind of limit for how many failures an hour for instance.
	 * @param int $max Max allowed failures before a warning mail is sent
	 * @return void
	 * @access private
	 */
	public function checkLogFailures($email, $secondsBack = 3600, $max = 3) {
		if ($email) {
			// Get last flag set in the log for sending
			$theTimeBack = $GLOBALS['EXEC_TIME'] - $secondsBack;
			$res = $this->db->exec_SELECTquery('tstamp', 'sys_log', 'type=255 AND action=4 AND tstamp>' . (int)$theTimeBack, '', 'tstamp DESC', '1');
			if ($testRow = $this->db->sql_fetch_assoc($res)) {
				$theTimeBack = $testRow['tstamp'];
			}
			$this->db->sql_free_result($res);
			// Check for more than $max number of error failures with the last period.
			$res = $this->db->exec_SELECTquery('*', 'sys_log', 'type=255 AND action=3 AND error<>0 AND tstamp>' . (int)$theTimeBack, '', 'tstamp');
			if ($this->db->sql_num_rows($res) > $max) {
				// OK, so there were more than the max allowed number of login failures - so we will send an email then.
				$subject = 'TYPO3 Login Failure Warning (at ' . $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'] . ')';
				$email_body = 'There have been some attempts (' . $this->db->sql_num_rows($res) . ') to login at the TYPO3
site "' . $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'] . '" (' . GeneralUtility::getIndpEnv('HTTP_HOST') . ').

This is a dump of the failures:

';
				while ($testRows = $this->db->sql_fetch_assoc($res)) {
					$theData = unserialize($testRows['log_data']);
					$email_body .= date(
							$GLOBALS['TYPO3_CONF_VARS']['SYS']['ddmmyy'] . ' ' . $GLOBALS['TYPO3_CONF_VARS']['SYS']['hhmm'],
							$testRows['tstamp']
						) . ':  ' . @sprintf($testRows['details'], (string)$theData[0], (string)$theData[1], (string)$theData[2]);
					$email_body .= LF;
				}
				$from = \TYPO3\CMS\Core\Utility\MailUtility::getSystemFrom();
				/** @var $mail \TYPO3\CMS\Core\Mail\MailMessage */
				$mail = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Mail\MailMessage::class);
				$mail->setTo($email)->setFrom($from)->setSubject($subject)->setBody($email_body);
				$mail->send();
				// Logout written to log
				$this->writelog(255, 4, 0, 3, 'Failure warning (%s failures within %s seconds) sent by email to %s', array($this->db->sql_num_rows($res), $secondsBack, $email));
				$this->db->sql_free_result($res);
			}
		}
	}

	/**
	 * Getter for the cookie name
	 *
	 * @static
	 * @return string returns the configured cookie name
	 */
	static public function getCookieName() {
		$configuredCookieName = trim($GLOBALS['TYPO3_CONF_VARS']['BE']['cookieName']);
		if (empty($configuredCookieName)) {
			$configuredCookieName = 'be_typo_user';
		}
		return $configuredCookieName;
	}

	/**
	 * If TYPO3_CONF_VARS['BE']['enabledBeUserIPLock'] is enabled and
	 * an IP-list is found in the User TSconfig objString "options.lockToIP",
	 * then make an IP comparison with REMOTE_ADDR and return the outcome (TRUE/FALSE)
	 *
	 * @return bool TRUE, if IP address validates OK (or no check is done at all)
	 */
	public function checkLockToIP() {
		$out = 1;
		if ($GLOBALS['TYPO3_CONF_VARS']['BE']['enabledBeUserIPLock']) {
			$IPList = $this->getTSConfigVal('options.lockToIP');
			if (trim($IPList)) {
				$baseIP = GeneralUtility::getIndpEnv('REMOTE_ADDR');
				$out = GeneralUtility::cmpIP($baseIP, $IPList);
			}
		}
		return $out;
	}

	/**
	 * Check if user is logged in and if so, call ->fetchGroupData() to load group information and
	 * access lists of all kind, further check IP, set the ->uc array and send login-notification email if required.
	 * If no user is logged in the default behaviour is to exit with an error message,
	 * but this will happen ONLY if the constant TYPO3_PROCEED_IF_NO_USER is set TRUE.
	 * This function is called right after ->start() in fx. init.php
	 *
	 * @throws \RuntimeException
	 * @return void
	 */
	public function backendCheckLogin() {
		if (empty($this->user['uid'])) {
			if (!defined('TYPO3_PROCEED_IF_NO_USER') || !TYPO3_PROCEED_IF_NO_USER) {
				\TYPO3\CMS\Core\Utility\HttpUtility::redirect($GLOBALS['BACK_PATH']);
			}
		} else {
			// ...and if that's the case, call these functions
			$this->fetchGroupData();
			// The groups are fetched and ready for permission checking in this initialization.
			// Tables.php must be read before this because stuff like the modules has impact in this
			if ($this->checkLockToIP()) {
				if ($this->isUserAllowedToLogin()) {
					// Setting the UC array. It's needed with fetchGroupData first, due to default/overriding of values.
					$this->backendSetUC();
					// Email at login - if option set.
					$this->emailAtLogin();
				} else {
					throw new \RuntimeException('Login Error: TYPO3 is in maintenance mode at the moment. Only administrators are allowed access.', 1294585860);
				}
			} else {
				throw new \RuntimeException('Login Error: IP locking prevented you from being authorized. Can\'t proceed, sorry.', 1294585861);
			}
		}
	}

	/**
	 * If the backend script is in CLI mode, it will try to load a backend user named by the CLI module name (in lowercase)
	 *
	 * @return bool Returns TRUE if a CLI user was loaded, otherwise FALSE!
	 */
	public function checkCLIuser() {
		// First, check if cliMode is enabled:
		if (TYPO3_REQUESTTYPE & TYPO3_REQUESTTYPE_CLI) {
			if (!$this->user['uid']) {
				if (substr($GLOBALS['MCONF']['name'], 0, 5) == '_CLI_') {
					$userName = strtolower($GLOBALS['MCONF']['name']);
					$this->setBeUserByName($userName);
					if ($this->user['uid']) {
						if (!$this->isAdmin()) {
							return TRUE;
						} else {
							fwrite(STDERR, 'ERROR: CLI backend user "' . $userName . '" was ADMIN which is not allowed!' . LF . LF);
							die(3);
						}
					} else {
						fwrite(STDERR, 'ERROR: No backend user named "' . $userName . '" was found!' . LF . LF);
						die(3);
					}
				} else {
					fwrite(STDERR, 'ERROR: Module name, "' . $GLOBALS['MCONF']['name'] . '", was not prefixed with "_CLI_"' . LF . LF);
					die(3);
				}
			} else {
				fwrite(STDERR, 'ERROR: Another user was already loaded which is impossible in CLI mode!' . LF . LF);
				die(3);
			}
		}
		return FALSE;
	}

	/**
	 * Initialize the internal ->uc array for the backend user
	 * Will make the overrides if necessary, and write the UC back to the be_users record if changes has happened
	 *
	 * @return void
	 * @internal
	 */
	public function backendSetUC() {
		// UC - user configuration is a serialized array inside the user object
		// If there is a saved uc we implement that instead of the default one.
		$temp_theSavedUC = unserialize($this->user['uc']);
		if (is_array($temp_theSavedUC)) {
			$this->unpack_uc($temp_theSavedUC);
		}
		// Setting defaults if uc is empty
		$updated = FALSE;
		if (!is_array($this->uc)) {
			$this->uc = array_merge(
				$this->uc_default,
				(array)$GLOBALS['TYPO3_CONF_VARS']['BE']['defaultUC'],
				GeneralUtility::removeDotsFromTS((array)$this->getTSConfigProp('setup.default'))
			);
			$this->overrideUC();
			$updated = TRUE;
		}
		// If TSconfig is updated, update the defaultUC.
		if ($this->userTSUpdated) {
			$this->overrideUC();
			$updated = TRUE;
		}
		// Setting default lang from be_user record.
		if (!isset($this->uc['lang'])) {
			$this->uc['lang'] = $this->user['lang'];
			$updated = TRUE;
		}
		// Setting the time of the first login:
		if (!isset($this->uc['firstLoginTimeStamp'])) {
			$this->uc['firstLoginTimeStamp'] = $GLOBALS['EXEC_TIME'];
			$updated = TRUE;
		}
		// Saving if updated.
		if ($updated) {
			$this->writeUC();
		}
	}

	/**
	 * Override: Call this function every time the uc is updated.
	 * That is 1) by reverting to default values, 2) in the setup-module, 3) userTS changes (userauthgroup)
	 *
	 * @return void
	 * @internal
	 */
	public function overrideUC() {
		$this->uc = array_merge((array)$this->uc, (array)$this->getTSConfigProp('setup.override'));
	}

	/**
	 * Clears the user[uc] and ->uc to blank strings. Then calls ->backendSetUC() to fill it again with reset contents
	 *
	 * @return void
	 * @internal
	 */
	public function resetUC() {
		$this->user['uc'] = '';
		$this->uc = '';
		$this->backendSetUC();
	}

	/**
	 * Will send an email notification to warning_email_address/the login users email address when a login session is just started.
	 * Depends on various parameters whether mails are send and to whom.
	 *
	 * @return void
	 * @access private
	 */
	private function emailAtLogin() {
		if ($this->loginSessionStarted) {
			// Send notify-mail
			$subject = 'At "' . $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'] . '"' . ' from '
				. GeneralUtility::getIndpEnv('REMOTE_ADDR')
				. (GeneralUtility::getIndpEnv('REMOTE_HOST') ? ' (' . GeneralUtility::getIndpEnv('REMOTE_HOST') . ')' : '');
			$msg = sprintf(
				'User "%s" logged in from %s (%s) at "%s" (%s)',
				$this->user['username'],
				GeneralUtility::getIndpEnv('REMOTE_ADDR'),
				GeneralUtility::getIndpEnv('REMOTE_HOST'),
				$GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'],
				GeneralUtility::getIndpEnv('HTTP_HOST')
			);
			// Warning email address
			if ($GLOBALS['TYPO3_CONF_VARS']['BE']['warning_email_addr']) {
				$warn = 0;
				$prefix = '';
				if ((int)$GLOBALS['TYPO3_CONF_VARS']['BE']['warning_mode'] & 1) {
					// first bit: All logins
					$warn = 1;
					$prefix = $this->isAdmin() ? '[AdminLoginWarning]' : '[LoginWarning]';
				}
				if ($this->isAdmin() && (int)$GLOBALS['TYPO3_CONF_VARS']['BE']['warning_mode'] & 2) {
					// second bit: Only admin-logins
					$warn = 1;
					$prefix = '[AdminLoginWarning]';
				}
				if ($warn) {
					$from = \TYPO3\CMS\Core\Utility\MailUtility::getSystemFrom();
					/** @var $mail \TYPO3\CMS\Core\Mail\MailMessage */
					$mail = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Mail\MailMessage::class);
					$mail->setTo($GLOBALS['TYPO3_CONF_VARS']['BE']['warning_email_addr'])->setFrom($from)->setSubject($prefix . ' ' . $subject)->setBody($msg);
					$mail->send();
				}
			}
			// If An email should be sent to the current user, do that:
			if ($this->uc['emailMeAtLogin'] && strstr($this->user['email'], '@')) {
				$from = \TYPO3\CMS\Core\Utility\MailUtility::getSystemFrom();
				/** @var $mail \TYPO3\CMS\Core\Mail\MailMessage */
				$mail = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Mail\MailMessage::class);
				$mail->setTo($this->user['email'])->setFrom($from)->setSubject($subject)->setBody($msg);
				$mail->send();
			}
		}
	}

	/**
	 * Determines whether a backend user is allowed to access the backend.
	 *
	 * The conditions are:
	 * + backend user is a regular user and adminOnly is not defined
	 * + backend user is an admin user
	 * + backend user is used in CLI context and adminOnly is explicitly set to "2"
	 * + backend user is being controlled by an admin user
	 *
	 * @return bool Whether a backend user is allowed to access the backend
	 */
	protected function isUserAllowedToLogin() {
		$isUserAllowedToLogin = FALSE;
		$adminOnlyMode = $GLOBALS['TYPO3_CONF_VARS']['BE']['adminOnly'];
		// Backend user is allowed if adminOnly is not set or user is an admin:
		if (!$adminOnlyMode || $this->isAdmin()) {
			$isUserAllowedToLogin = TRUE;
		} elseif ($adminOnlyMode == 2 && TYPO3_REQUESTTYPE & TYPO3_REQUESTTYPE_CLI) {
			$isUserAllowedToLogin = TRUE;
		} elseif ($this->user['ses_backuserid']) {
			$backendUserId = (int)$this->user['ses_backuserid'];
			$whereAdmin = 'uid=' . $backendUserId . ' AND admin=1' . BackendUtility::BEenableFields('be_users');
			if ($this->db->exec_SELECTcountRows('uid', 'be_users', $whereAdmin) > 0) {
				$isUserAllowedToLogin = TRUE;
			}
		}
		return $isUserAllowedToLogin;
	}

	/**
	 * Logs out the current user and clears the form protection tokens.
	 */
	public function logoff() {
		if (isset($GLOBALS['BE_USER'])) {
			\TYPO3\CMS\Core\FormProtection\FormProtectionFactory::get()->clean();
		}
		parent::logoff();
	}

}
