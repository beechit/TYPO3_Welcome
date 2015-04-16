<?php
namespace TYPO3\CMS\SysAction;

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
 * This class provides a task for the taskcenter
 *
 * @author Kasper Skårhøj <kasperYYYY@typo3.com>
 * @author Georg Ringer <typo3@ringerge.org>
 */
class ActionTask implements \TYPO3\CMS\Taskcenter\TaskInterface {

	/**
	 * @var \TYPO3\CMS\Taskcenter\Controller\TaskModuleController
	 */
	protected $taskObject;

	/**
	 * @var \TYPO3\CMS\Backend\Form\FormEngine
	 */
	public $t3lib_TCEforms;

	/**
	 * All hook objects get registered here for later use
	 *
	 * @var array
	 */
	protected $hookObjects = array();

	/**
	 * URL to task module
	 *
	 * @var string
	 */
	protected $moduleUrl;

	/**
	 * Constructor
	 */
	public function __construct(\TYPO3\CMS\Taskcenter\Controller\TaskModuleController $taskObject) {
		$this->moduleUrl = BackendUtility::getModuleUrl('user_task');
		$this->taskObject = $taskObject;
		$GLOBALS['LANG']->includeLLFile('EXT:sys_action/locallang.xlf');
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['sys_action']['tx_sysaction_task'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['sys_action']['tx_sysaction_task'] as $classRef) {
				$this->hookObjects[] = GeneralUtility::getUserObj($classRef);
			}
		}
	}

	/**
	 * This method renders the task
	 *
	 * @return string The task as HTML
	 */
	public function getTask() {
		$content = '';
		$show = (int)GeneralUtility::_GP('show');
		foreach ($this->hookObjects as $hookObject) {
			if (method_exists($hookObject, 'getTask')) {
				$show = $hookObject->getTask($show, $this);
			}
		}
		// If no task selected, render the menu
		if ($show == 0) {
			$content .= $this->taskObject->description($GLOBALS['LANG']->getLL('sys_action'), $GLOBALS['LANG']->getLL('description'));
			$content .= $this->renderActionList();
		} else {
			$record = BackendUtility::getRecord('sys_action', $show);
			// If the action is not found
			if (count($record) == 0) {
				$flashMessage = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Messaging\FlashMessage::class, $GLOBALS['LANG']->getLL('action_error-not-found', TRUE), $GLOBALS['LANG']->getLL('action_error'), \TYPO3\CMS\Core\Messaging\FlashMessage::ERROR);
				$content .= $flashMessage->render();
			} else {
				// Render the task
				$content .= $this->taskObject->description($record['title'], $record['description']);
				// Output depends on the type
				switch ($record['type']) {
					case 1:
						$content .= $this->viewNewBackendUser($record);
						break;
					case 2:
						$content .= $this->viewSqlQuery($record);
						break;
					case 3:
						$content .= $this->viewRecordList($record);
						break;
					case 4:
						$content .= $this->viewEditRecord($record);
						break;
					case 5:
						$content .= $this->viewNewRecord($record);
						break;
					default:
						$flashMessage = GeneralUtility::makeInstance(
							\TYPO3\CMS\Core\Messaging\FlashMessage::class,
							$GLOBALS['LANG']->getLL('action_noType', TRUE),
							$GLOBALS['LANG']->getLL('action_error'),
							\TYPO3\CMS\Core\Messaging\FlashMessage::ERROR
						);
						$content .= '<br />' . $flashMessage->render();
				}
			}
		}
		return $content;
	}

	/**
	 * General overview over the task in the taskcenter menu
	 *
	 * @return string Overview as HTML
	 */
	public function getOverview() {
		$content = '<p>' . $GLOBALS['LANG']->getLL('description') . '</p>';
		// Get the actions
		$actionList = $this->getActions();
		if (count($actionList) > 0) {
			$items = '';
			// Render a single action menu item
			foreach ($actionList as $action) {
				$active = GeneralUtility::_GP('show') === $action['uid'] ? ' class="active" ' : '';
				$items .= '<li' . $active . '>
								<a href="' . $action['link'] . '" title="' . htmlspecialchars($action['description']) . '">' . htmlspecialchars($action['title']) . '</a>
							</li>';
			}
			$content .= '<ul>' . $items . '</ul>';
		}
		return $content;
	}

	/**
	 * Get all actions of an user. Admins can see any action, all others only those
	 * which are allowed in sys_action record itself.
	 *
	 * @return array Array holding every needed information of a sys_action
	 */
	protected function getActions() {
		$actionList = array();
		// admins can see any record
		if ($GLOBALS['BE_USER']->isAdmin()) {
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'sys_action', '', '', 'sys_action.sorting');
		} else {
			// Editors can only see the actions which are assigned to a usergroup they belong to
			$additionalWhere = 'be_groups.uid IN (' . ($GLOBALS['BE_USER']->groupList ?: 0) . ')';
			$res = $GLOBALS['TYPO3_DB']->exec_SELECT_mm_query('sys_action.*', 'sys_action', 'sys_action_asgr_mm', 'be_groups', ' AND sys_action.hidden=0 AND ' . $additionalWhere, 'sys_action.uid', 'sys_action.sorting');
		}
		while ($actionRow = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$editActionLink = '';
			// Admins are allowed to edit sys_action records
			if ($GLOBALS['BE_USER']->isAdmin()) {
				$returnUrl = rawurlencode(GeneralUtility::getIndpEnv('REQUEST_URI'));
				$link = GeneralUtility::getIndpEnv('TYPO3_REQUEST_DIR') . $GLOBALS['BACK_PATH'] . 'alt_doc.php?returnUrl=' . $returnUrl . '&edit[sys_action][' . $actionRow['uid'] . ']=edit';
				$editActionLink = '<a class="edit" href="' . $link . '">' . '<img class="icon"' . \TYPO3\CMS\Backend\Utility\IconUtility::skinImg($GLOBALS['BACK_PATH'], 'gfx/edit2.gif') . ' title="' . $GLOBALS['LANG']->getLL('edit-sys_action') . '" alt="" />' . $GLOBALS['LANG']->getLL('edit-sys_action') . '</a>';
			}
			$actionList[] = array(
				'uid' => $actionRow['uid'],
				'title' => $actionRow['title'],
				'description' => $actionRow['description'],
				'descriptionHtml' => nl2br(htmlspecialchars($actionRow['description'])) . $editActionLink,
				'link' => $this->moduleUrl . '&SET[function]=sys_action.tx_sysaction_task&show=' . $actionRow['uid'],
				'icon' => 'EXT:sys_action/sys_action.gif'
			);
		}
		$GLOBALS['TYPO3_DB']->sql_free_result($res);
		return $actionList;
	}

	/**
	 * Render the menu of sys_actions
	 *
	 * @return string List of sys_actions as HTML
	 */
	protected function renderActionList() {
		$content = '';
		// Get the sys_action records
		$actionList = $this->getActions();
		// If any actions are found for the current users
		if (count($actionList) > 0) {
			$content .= $this->taskObject->renderListMenu($actionList);
		} else {
			$flashMessage = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Messaging\FlashMessage::class, $GLOBALS['LANG']->getLL('action_not-found-description', TRUE), $GLOBALS['LANG']->getLL('action_not-found'), \TYPO3\CMS\Core\Messaging\FlashMessage::INFO);
			$content .= $flashMessage->render();
		}
		// Admin users can create a new action
		if ($GLOBALS['BE_USER']->isAdmin()) {
			$returnUrl = rawurlencode($this->moduleUrl);
			$link = GeneralUtility::getIndpEnv('TYPO3_REQUEST_DIR') . $GLOBALS['BACK_PATH'] . 'alt_doc.php?returnUrl=' . $returnUrl . '&edit[sys_action][0]=new';
			$content .= '<p>' .
				'<a href="' . $link . '" title="' . $GLOBALS['LANG']->getLL('new-sys_action') . '">' .
				\TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIcon('actions-document-new', array('class' => 'icon', 'title' => $GLOBALS['LANG']->getLL('new-sys_action'))) .
				$GLOBALS['LANG']->getLL('new-sys_action') .
				'</a></p>';
		}
		return $content;
	}

	/**
	 * Action to create a new BE user
	 *
	 * @param array $record sys_action record
	 * @return string form to create a new user
	 */
	protected function viewNewBackendUser($record) {
		$content = '';
		$beRec = BackendUtility::getRecord('be_users', (int)$record['t1_copy_of_user']);
		// A record is need which is used as copy for the new user
		if (!is_array($beRec)) {
			$flashMessage = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Messaging\FlashMessage::class, $GLOBALS['LANG']->getLL('action_notReady', TRUE), $GLOBALS['LANG']->getLL('action_error'), \TYPO3\CMS\Core\Messaging\FlashMessage::ERROR);
			$content .= $flashMessage->render();
			return $content;
		}
		$vars = GeneralUtility::_POST('data');
		$key = 'NEW';
		if ($vars['sent'] == 1) {
			$errors = array();
			// Basic error checks
			if (!empty($vars['email']) && !GeneralUtility::validEmail($vars['email'])) {
				$errors[] = $GLOBALS['LANG']->getLL('error-wrong-email');
			}
			if (empty($vars['username'])) {
				$errors[] = $GLOBALS['LANG']->getLL('error-username-empty');
			}
			if ($vars['key'] === 'NEW' && empty($vars['password'])) {
				$errors[] = $GLOBALS['LANG']->getLL('error-password-empty');
			}
			if ($vars['key'] !== 'NEW' && !$this->isCreatedByUser($vars['key'], $record)) {
				$errors[] = $GLOBALS['LANG']->getLL('error-wrong-user');
			}
			foreach ($this->hookObjects as $hookObject) {
				if (method_exists($hookObject, 'viewNewBackendUser_Error')) {
					$errors = $hookObject->viewNewBackendUser_Error($vars, $errors, $this);
				}
			}
			// Show errors if there are any
			if (count($errors) > 0) {
				$flashMessage = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Messaging\FlashMessage::class, implode('<br />', $errors), $GLOBALS['LANG']->getLL('action_error'), \TYPO3\CMS\Core\Messaging\FlashMessage::ERROR);
				$content .= $flashMessage->render() . '<br />';
			} else {
				// Save user
				$key = $this->saveNewBackendUser($record, $vars);
				// Success message
				$flashMessage = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Messaging\FlashMessage::class, $vars['key'] === 'NEW' ? $GLOBALS['LANG']->getLL('success-user-created') : $GLOBALS['LANG']->getLL('success-user-updated'), $GLOBALS['LANG']->getLL('success'), \TYPO3\CMS\Core\Messaging\FlashMessage::OK);
				$content .= $flashMessage->render() . '<br />';
			}
		}
		// Load BE user to edit
		if ((int)GeneralUtility::_GP('be_users_uid') > 0) {
			$tmpUserId = (int)GeneralUtility::_GP('be_users_uid');
			// Check if the selected user is created by the current user
			$rawRecord = $this->isCreatedByUser($tmpUserId, $record);
			if ($rawRecord) {
				// Delete user
				if (GeneralUtility::_GP('delete') == 1) {
					$this->deleteUser($tmpUserId, $record['uid']);
				}
				$key = $tmpUserId;
				$vars = $rawRecord;
			}
		}
		$this->JScode();
		$loadDB = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\RelationHandler::class);
		$loadDB->start($vars['db_mountpoints'], 'pages');
		$content .= '<form action="" method="post" enctype="multipart/form-data">
						<fieldset class="fields">
							<legend>' . $GLOBALS['LANG']->getLL('action_t1_legend_generalFields') . '</legend>
							<div class="row">
								<label for="field_disable">' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_general.xlf:LGL.disable') . '</label>
								<input type="checkbox" id="field_disable" name="data[disable]" value="1" class="checkbox" ' . ($vars['disable'] == 1 ? ' checked="checked" ' : '') . ' />
							</div>
							<div class="row">
								<label for="field_realname">' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_general.xlf:LGL.name') . '</label>
								<input type="text" id="field_realname" name="data[realName]" value="' . htmlspecialchars($vars['realName']) . '" />
							</div>
							<div class="row">
								<label for="field_username">' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_tca.xlf:be_users.username') . '</label>
								<input type="text" id="field_username" name="data[username]" value="' . htmlspecialchars($vars['username']) . '" />
							</div>
							<div class="row">
								<label for="field_password">' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_tca.xlf:be_users.password') . '</label>
								<input type="password" id="field_password" name="data[password]" value="" />
							</div>
							<div class="row">
								<label for="field_email">' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_general.xlf:LGL.email') . '</label>
								<input type="text" id="field_email" name="data[email]" value="' . htmlspecialchars($vars['email']) . '" />
							</div>
						</fieldset>
						<fieldset class="fields">
							<legend>' . $GLOBALS['LANG']->getLL('action_t1_legend_configuration') . '</legend>

							<div class="row">
								<label for="field_usergroup">' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_tca.xlf:be_users.usergroup') . '</label>
								<select id="field_usergroup" name="data[usergroup][]" multiple="multiple">
									' . $this->getUsergroups($record, $vars) . '
								</select>
							</div>
							<div class="row">
								<label for="field_db_mountpoints">' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_tca.xlf:be_users.options_db_mounts') . '</label>
								' . $this->t3lib_TCEforms->dbFileIcons('data[db_mountpoints]', 'db', 'pages', $loadDB->itemArray, '', array('size' => 3)) . '
							</div>
							<div class="row">
								<input type="hidden" name="data[key]" value="' . $key . '" />
								<input type="hidden" name="data[sent]" value="1" />
								<input class="btn btn-default" type="submit" value="' . ($key === 'NEW' ? $GLOBALS['LANG']->getLL('action_Create') : $GLOBALS['LANG']->getLL('action_Update')) . '" />
							</div>
						</fieldset>
					</form>';
		$content .= $this->getCreatedUsers($record, $key);
		return $content;
	}

	/**
	 * Delete a BE user and redirect to the action by its id
	 *
	 * @param int $userId Id of the BE user
	 * @param int $actionId Id of the action
	 * @return void
	 */
	protected function deleteUser($userId, $actionId) {
		$GLOBALS['TYPO3_DB']->exec_UPDATEquery('be_users', 'uid=' . $userId, array(
			'deleted' => 1,
			'tstamp' => $GLOBALS['ACCESS_TIME']
		));
		// redirect to the original task
		$redirectUrl = $this->moduleUrl . '&show=' . $actionId;
		\TYPO3\CMS\Core\Utility\HttpUtility::redirect($redirectUrl);
	}

	/**
	 * Check if a BE user is created by the current user
	 *
	 * @param int $id Id of the BE user
	 * @param array $action sys_action record.
	 * @return mixed The record of the BE user if found, otherwise FALSE
	 */
	protected function isCreatedByUser($id, $action) {
		$record = BackendUtility::getRecord('be_users', $id, '*', ' AND cruser_id=' . $GLOBALS['BE_USER']->user['uid'] . ' AND createdByAction=' . $action['uid']);
		if (is_array($record)) {
			return $record;
		} else {
			return FALSE;
		}
	}

	/**
	 * Render all users who are created by the current BE user including a link to edit the record
	 *
	 * @param array $action sys_action record.
	 * @param int $selectedUser Id of a selected user
	 * @return string html list of users
	 */
	protected function getCreatedUsers($action, $selectedUser) {
		$content = '';
		$userList = array();
		// List of users
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'be_users', 'cruser_id=' . $GLOBALS['BE_USER']->user['uid'] . ' AND createdByAction=' . (int)$action['uid'] . BackendUtility::deleteClause('be_users'), '', 'username');
		// Render the user records
		while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$icon = \TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIconForRecord('be_users', $row, array('title' => 'uid=' . $row['uid']));
			$line = $icon . $this->action_linkUserName($row['username'], $row['realName'], $action['uid'], $row['uid']);
			// Selected user
			if ($row['uid'] == $selectedUser) {
				$line = '<strong>' . $line . '</strong>';
			}
			$userList[] = $line;
		}
		$GLOBALS['TYPO3_DB']->sql_free_result($res);
		// If any records found
		if (count($userList)) {
			$content .= '<br />' . $this->taskObject->doc->section($GLOBALS['LANG']->getLL('action_t1_listOfUsers'), implode('<br />', $userList));
		}
		return $content;
	}

	/**
	 * Create a link to edit a user
	 *
	 * @param string $username Username
	 * @param string $realName Real name of the user
	 * @param int $sysActionUid Id of the sys_action record
	 * @param int $userId Id of the user
	 * @return string html link
	 */
	protected function action_linkUserName($username, $realName, $sysActionUid, $userId) {
		if (!empty($realName)) {
			$username .= ' (' . $realName . ')';
		}
		// Link to update the user record
		$href = $this->moduleUrl . '&SET[function]=sys_action.tx_sysaction_task&show=' . (int)$sysActionUid . '&be_users_uid=' . (int)$userId;
		$link = '<a href="' . htmlspecialchars($href) . '">' . htmlspecialchars($username) . '</a>';
		// Link to delete the user record
		$onClick = ' onClick="return confirm(' . GeneralUtility::quoteJSvalue($GLOBALS['LANG']->getLL('lDelete_warning')) . ');"';
		$link .= '
				<a href="' . htmlspecialchars(($href . '&delete=1')) . '" ' . $onClick . '>'
					. \TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIcon('actions-edit-delete') .
				'</a>';
		return $link;
	}

	/**
	 * Save/Update a BE user
	 *
	 * @param array $record Current action record
	 * @param array $vars POST vars
	 * @return int Id of the new/updated user
	 */
	protected function saveNewBackendUser($record, $vars) {
		// Check if the db mount is a page the current user is allowed to.);
		$vars['db_mountpoints'] = $this->fixDbMount($vars['db_mountpoints']);
		// Check if the usergroup is allowed
		$vars['usergroup'] = $this->fixUserGroup($vars['usergroup'], $record);
		$key = $vars['key'];
		$vars['password'] = trim($vars['password']);
		// Check if md5 is used as password encryption
		if ($vars['password'] !== '' && strpos($GLOBALS['TCA']['be_users']['columns']['password']['config']['eval'], 'md5') !== FALSE) {
			$vars['password'] = md5($vars['password']);
		}
		$data = '';
		$newUserId = 0;
		if ($key === 'NEW') {
			$beRec = BackendUtility::getRecord('be_users', (int)$record['t1_copy_of_user']);
			if (is_array($beRec)) {
				$data = array();
				$data['be_users'][$key] = $beRec;
				$data['be_users'][$key]['username'] = $this->fixUsername($vars['username'], $record['t1_userprefix']);
				$data['be_users'][$key]['password'] = $vars['password'];
				$data['be_users'][$key]['realName'] = $vars['realName'];
				$data['be_users'][$key]['email'] = $vars['email'];
				$data['be_users'][$key]['disable'] = (int)$vars['disable'];
				$data['be_users'][$key]['admin'] = 0;
				$data['be_users'][$key]['usergroup'] = $vars['usergroup'];
				$data['be_users'][$key]['db_mountpoints'] = $vars['db_mountpoints'];
				$data['be_users'][$key]['createdByAction'] = $record['uid'];
			}
		} else {
			// Check ownership
			$beRec = BackendUtility::getRecord('be_users', (int)$key);
			if (is_array($beRec) && $beRec['cruser_id'] == $GLOBALS['BE_USER']->user['uid']) {
				$data = array();
				$data['be_users'][$key]['username'] = $this->fixUsername($vars['username'], $record['t1_userprefix']);
				if ($vars['password'] !== '') {
					$data['be_users'][$key]['password'] = $vars['password'];
				}
				$data['be_users'][$key]['realName'] = $vars['realName'];
				$data['be_users'][$key]['email'] = $vars['email'];
				$data['be_users'][$key]['disable'] = (int)$vars['disable'];
				$data['be_users'][$key]['admin'] = 0;
				$data['be_users'][$key]['usergroup'] = $vars['usergroup'];
				$data['be_users'][$key]['db_mountpoints'] = $vars['db_mountpoints'];
				$newUserId = $key;
			}
		}
		// Save/update user by using TCEmain
		if (is_array($data)) {
			$tce = GeneralUtility::makeInstance(\TYPO3\CMS\Core\DataHandling\DataHandler::class);
			$tce->stripslashes_values = 0;
			$tce->start($data, array(), $GLOBALS['BE_USER']);
			$tce->admin = 1;
			$tce->process_datamap();
			$newUserId = (int)$tce->substNEWwithIDs['NEW'];
			if ($newUserId) {
				// Create
				$this->action_createDir($newUserId);
			} else {
				// Update
				$newUserId = (int)$key;
			}
			unset($tce);
		}
		return $newUserId;
	}

	/**
	 * Create the username based on the given username and the prefix
	 *
	 * @param string $username Username
	 * @param string $prefix Prefix
	 * @return string Combined username
	 */
	protected function fixUsername($username, $prefix) {
		return trim($prefix) . trim($username);
	}

	/**
	 * Clean the to be applied usergroups from not allowed ones
	 *
	 * @param array $appliedUsergroups Array of to be applied user groups
	 * @param array $actionRecord The action record
	 * @return array Cleaned array
	 */
	protected function fixUserGroup($appliedUsergroups, $actionRecord) {
		if (is_array($appliedUsergroups)) {
			$cleanGroupList = array();
			// Create an array from the allowed usergroups using the uid as key
			$allowedUsergroups = array_flip(explode(',', $actionRecord['t1_allowed_groups']));
			// Walk through the array and check every uid if it is under the allowed ines
			foreach ($appliedUsergroups as $group) {
				if (isset($allowedUsergroups[$group])) {
					$cleanGroupList[] = $group;
				}
			}
			$appliedUsergroups = $cleanGroupList;
		}
		return $appliedUsergroups;
	}

	/**
	 * Clean the to be applied DB-Mounts from not allowed ones
	 *
	 * @param string $appliedDbMounts List of pages like pages_123,pages456
	 * @return string Cleaned list
	 */
	protected function fixDbMount($appliedDbMounts) {
		// Admins can see any page, no need to check there
		if (!empty($appliedDbMounts) && !$GLOBALS['BE_USER']->isAdmin()) {
			$cleanDbMountList = array();
			$dbMounts = GeneralUtility::trimExplode(',', $appliedDbMounts, TRUE);
			// Walk through every wanted DB-Mount and check if it allowed for the current user
			foreach ($dbMounts as $dbMount) {
				$uid = (int)substr($dbMount, strrpos($dbMount, '_') + 1);
				$page = BackendUtility::getRecord('pages', $uid);
				// Check rootline and access rights
				if ($this->checkRootline($uid) && $GLOBALS['BE_USER']->calcPerms($page)) {
					$cleanDbMountList[] = 'pages_' . $uid;
				}
			}
			// Build the clean list
			$appliedDbMounts = implode(',', $cleanDbMountList);
		}
		return $appliedDbMounts;
	}

	/**
	 * Check if a page is inside the rootline the current user can see
	 *
	 * @param int $pageId Id of the the page to be checked
	 * @return bool Access to the page
	 */
	protected function checkRootline($pageId) {
		$access = FALSE;
		$dbMounts = array_flip(explode(',', trim($GLOBALS['BE_USER']->dataLists['webmount_list'], ',')));
		$rootline = BackendUtility::BEgetRootLine($pageId);
		foreach ($rootline as $page) {
			if (isset($dbMounts[$page['uid']]) && !$access) {
				$access = TRUE;
			}
		}
		return $access;
	}

	/**
	 * Add additional JavaScript to use the tceform select box
	 *
	 * @return void
	 */
	protected function JScode() {
		$this->t3lib_TCEforms = GeneralUtility::makeInstance(\TYPO3\CMS\Backend\Form\FormEngine::class);
		$this->t3lib_TCEforms->backPath = $GLOBALS['BACK_PATH'];
		$this->t3lib_TCEforms->printNeededJSFunctions();
	}

	/**
	 * Create a user directory if defined
	 *
	 * @param int $uid Id of the user record
	 * @return void
	 */
	protected function action_createDir($uid) {
		$path = $this->action_getUserMainDir();
		if ($path) {
			GeneralUtility::mkdir($path . $uid);
			GeneralUtility::mkdir($path . $uid . '/_temp_/');
		}
	}

	/**
	 * Get the path to the user home directory which is set in the localconf.php
	 *
	 * @return string Path
	 */
	protected function action_getUserMainDir() {
		$path = $GLOBALS['TYPO3_CONF_VARS']['BE']['userHomePath'];
		// If path is set and a valid directory
		if ($path && @is_dir($path) && $GLOBALS['TYPO3_CONF_VARS']['BE']['lockRootPath'] && GeneralUtility::isFirstPartOfStr($path, $GLOBALS['TYPO3_CONF_VARS']['BE']['lockRootPath']) && substr($path, -1) == '/') {
			return $path;
		}
	}

	/**
	 * Get all allowed usergroups which can be applied to a user record
	 *
	 * @param array $record sys_action record
	 * @param array $vars Selected be_user record
	 * @return string Rendered user groups
	 */
	protected function getUsergroups($record, $vars) {
		$content = '';
		// Do nothing if no groups are allowed
		if (empty($record['t1_allowed_groups'])) {
			return $content;
		}
		$content .= '<option value=""></option>';
		$grList = GeneralUtility::trimExplode(',', $record['t1_allowed_groups'], TRUE);
		foreach ($grList as $group) {
			$checkGroup = BackendUtility::getRecord('be_groups', $group);
			if (is_array($checkGroup)) {
				$selected = GeneralUtility::inList($vars['usergroup'], $checkGroup['uid']) ? ' selected="selected" ' : '';
				$content .= '<option ' . $selected . 'value="' . $checkGroup['uid'] . '">' . htmlspecialchars($checkGroup['title']) . '</option>';
			}
		}
		return $content;
	}

	/**
	 * Action to create a new record
	 *
	 * @param array $record sys_action record
	 * @return void Redirect to form to create a record
	 */
	protected function viewNewRecord($record) {
		$returnUrl = rawurlencode($this->moduleUrl);
		$link = GeneralUtility::getIndpEnv('TYPO3_REQUEST_DIR') . $GLOBALS['BACK_PATH'] . 'alt_doc.php?returnUrl=' . $returnUrl . '&edit[' . $record['t3_tables'] . '][' . (int)$record['t3_listPid'] . ']=new';
		\TYPO3\CMS\Core\Utility\HttpUtility::redirect($link);
	}

	/**
	 * Action to edit records
	 *
	 * @param array $record sys_action record
	 * @return string list of records
	 */
	protected function viewEditRecord($record) {
		$content = '';
		$actionList = array();
		$dbAnalysis = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\RelationHandler::class);
		$dbAnalysis->setFetchAllFields(TRUE);
		$dbAnalysis->start($record['t4_recordsToEdit'], '*');
		$dbAnalysis->getFromDB();
		// collect the records
		foreach ($dbAnalysis->itemArray as $el) {
			$path = BackendUtility::getRecordPath($el['id'], $this->taskObject->perms_clause, $GLOBALS['BE_USER']->uc['titleLen']);
			$record = BackendUtility::getRecord($el['table'], $dbAnalysis->results[$el['table']][$el['id']]);
			$title = BackendUtility::getRecordTitle($el['table'], $dbAnalysis->results[$el['table']][$el['id']]);
			$description = $GLOBALS['LANG']->sL($GLOBALS['TCA'][$el['table']]['ctrl']['title'], TRUE);
			// @todo: which information could be needful
			if (isset($record['crdate'])) {
				$description .= ' - ' . BackendUtility::dateTimeAge($record['crdate']);
			}
			$actionList[$el['id']] = array(
				'title' => $title,
				'description' => BackendUtility::getRecordTitle($el['table'], $dbAnalysis->results[$el['table']][$el['id']]),
				'descriptionHtml' => $description,
				'link' => $GLOBALS['BACK_PATH'] . 'alt_doc.php?returnUrl=' . rawurlencode(GeneralUtility::getIndpEnv('REQUEST_URI')) . '&edit[' . $el['table'] . '][' . $el['id'] . ']=edit',
				'icon' => \TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIconForRecord($el['table'], $dbAnalysis->results[$el['table']][$el['id']], array('title' => htmlspecialchars($path)))
			);
		}
		// Render the record list
		$content .= $this->taskObject->renderListMenu($actionList);
		return $content;
	}

	/**
	 * Action to view the result of a SQL query
	 *
	 * @param array $record sys_action record
	 * @return string Result of the query
	 */
	protected function viewSqlQuery($record) {
		$content = '';
		if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('lowlevel')) {
			$sql_query = unserialize($record['t2_data']);
			if (!is_array($sql_query) || is_array($sql_query) && strtoupper(substr(trim($sql_query['qSelect']), 0, 6)) === 'SELECT') {
				$actionContent = '';
				$fullsearch = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\QueryView::class);
				$fullsearch->formW = 40;
				$fullsearch->noDownloadB = 1;
				$type = $sql_query['qC']['search_query_makeQuery'];
				if ($sql_query['qC']['labels_noprefix'] === 'on') {
					$GLOBALS['SOBE']->MOD_SETTINGS['labels_noprefix'] = 'on';
				}
				$sqlQuery = $sql_query['qSelect'];
				$queryIsEmpty = FALSE;
				if ($sqlQuery) {
					$res = $GLOBALS['TYPO3_DB']->sql_query($sqlQuery);
					if (!$GLOBALS['TYPO3_DB']->sql_error()) {
						$fullsearch->formW = 48;
						// Additional configuration
						$GLOBALS['SOBE']->MOD_SETTINGS['search_result_labels'] = 1;
						$GLOBALS['SOBE']->MOD_SETTINGS['queryFields'] = $sql_query['qC']['queryFields'];
						$cP = $fullsearch->getQueryResultCode($type, $res, $sql_query['qC']['queryTable']);
						$actionContent = $cP['content'];
						// If the result is rendered as csv or xml, show a download link
						if ($type === 'csv' || $type === 'xml') {
							$actionContent .= '<br /><br /><a href="' . GeneralUtility::getIndpEnv('REQUEST_URI') . '&download_file=1"><strong>' . $GLOBALS['LANG']->getLL('action_download_file') . '</strong></a>';
						}
					} else {
						$actionContent .= $GLOBALS['TYPO3_DB']->sql_error();
					}
				} else {
					// Query is empty (not built)
					$queryIsEmpty = TRUE;
					$flashMessage = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Messaging\FlashMessage::class, $GLOBALS['LANG']->getLL('action_emptyQuery', TRUE), $GLOBALS['LANG']->getLL('action_error'), \TYPO3\CMS\Core\Messaging\FlashMessage::ERROR);
					$content .= '<br />' . $flashMessage->render();
				}
				// Admin users are allowed to see and edit the query
				if ($GLOBALS['BE_USER']->isAdmin()) {
					if (!$queryIsEmpty) {
						$actionContent .= '<hr /> ' . $fullsearch->tableWrap($sql_query['qSelect']);
					}
					$actionContent .= '<br /><a title="' . $GLOBALS['LANG']->getLL('action_editQuery') . '" href="'
						. htmlspecialchars(BackendUtility::getModuleUrl('system_dbint')
							. '&id=' . '&SET[function]=search' . '&SET[search]=query'
							. '&storeControl[STORE]=-' . $record['uid'] . '&storeControl[LOAD]=1')
						. '">
						<img class="icon"' . \TYPO3\CMS\Backend\Utility\IconUtility::skinImg($GLOBALS['BACK_PATH'],
						'gfx/edit2.gif') . ' alt="" />' . $GLOBALS['LANG']->getLL(($queryIsEmpty ? 'action_createQuery'
						: 'action_editQuery')) . '</a><br /><br />';
				}
				$content .= $this->taskObject->doc->section($GLOBALS['LANG']->getLL('action_t2_result'), $actionContent, 0, 1);
			} else {
				// Query is not configured
				$flashMessage = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Messaging\FlashMessage::class, $GLOBALS['LANG']->getLL('action_notReady', TRUE), $GLOBALS['LANG']->getLL('action_error'), \TYPO3\CMS\Core\Messaging\FlashMessage::ERROR);
				$content .= '<br />' . $flashMessage->render();
			}
		} else {
			// Required sysext lowlevel is not installed
			$flashMessage = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Messaging\FlashMessage::class, $GLOBALS['LANG']->getLL('action_lowlevelMissing', TRUE), $GLOBALS['LANG']->getLL('action_error'), \TYPO3\CMS\Core\Messaging\FlashMessage::ERROR);
			$content .= '<br />' . $flashMessage->render();
		}
		return $content;
	}

	/**
	 * Action to create a list of records of a specific table and pid
	 *
	 * @param array $record sys_action record
	 * @return string list of records
	 */
	protected function viewRecordList($record) {
		$content = '';
		$this->id = (int)$record['t3_listPid'];
		$this->table = $record['t3_tables'];
		if ($this->id == 0 || $this->table == '') {
			$flashMessage = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Messaging\FlashMessage::class, $GLOBALS['LANG']->getLL('action_notReady', TRUE), $GLOBALS['LANG']->getLL('action_error'), \TYPO3\CMS\Core\Messaging\FlashMessage::ERROR);
			$content .= '<br />' . $flashMessage->render();
			return $content;
		}
		// Loading current page record and checking access:
		$this->pageinfo = BackendUtility::readPageAccess($this->id, $this->taskObject->perms_clause);
		$access = is_array($this->pageinfo) ? 1 : 0;
		// If there is access to the page, then render the list contents and set up the document template object:
		if ($access) {
			// Initialize the dblist object:
			$dblist = GeneralUtility::makeInstance(\TYPO3\CMS\SysAction\ActionList::class);
			$dblist->script = GeneralUtility::getIndpEnv('REQUEST_URI');
			$dblist->backPath = $GLOBALS['BACK_PATH'];
			$dblist->calcPerms = $GLOBALS['BE_USER']->calcPerms($this->pageinfo);
			$dblist->thumbs = $GLOBALS['BE_USER']->uc['thumbnailsByDefault'];
			$dblist->returnUrl = $this->taskObject->returnUrl;
			$dblist->allFields = 1;
			$dblist->localizationView = 1;
			$dblist->showClipboard = 0;
			$dblist->disableSingleTableView = 1;
			$dblist->pageRow = $this->pageinfo;
			$dblist->counter++;
			$dblist->MOD_MENU = array('bigControlPanel' => '', 'clipBoard' => '', 'localization' => '');
			$dblist->modTSconfig = $this->taskObject->modTSconfig;
			$dblist->dontShowClipControlPanels = (!$this->taskObject->MOD_SETTINGS['bigControlPanel'] && $dblist->clipObj->current == 'normal' && !$this->modTSconfig['properties']['showClipControlPanelsDespiteOfCMlayers']);
			// Initialize the listing object, dblist, for rendering the list:
			$this->pointer = \TYPO3\CMS\Core\Utility\MathUtility::forceIntegerInRange(GeneralUtility::_GP('pointer'), 0, 100000);
			$dblist->start($this->id, $this->table, $this->pointer, $this->taskObject->search_field, $this->taskObject->search_levels, $this->taskObject->showLimit);
			$dblist->setDispFields();
			// Render the list of tables:
			$dblist->generateList();
			// Add JavaScript functions to the page:
			$this->taskObject->doc->JScode = $this->taskObject->doc->wrapScriptTags('

				function jumpExt(URL,anchor) {
					var anc = anchor?anchor:"";
					window.location.href = URL+(T3_THIS_LOCATION?"&returnUrl="+T3_THIS_LOCATION:"")+anc;
					return false;
				}
				function jumpSelf(URL) {
					window.location.href = URL+(T3_RETURN_URL?"&returnUrl="+T3_RETURN_URL:"");
					return false;
				}

				function setHighlight(id) {
					top.fsMod.recentIds["web"]=id;
					top.fsMod.navFrameHighlightedID["web"]="pages"+id+"_"+top.fsMod.currentBank;	// For highlighting

					if (top.content && top.content.nav_frame && top.content.nav_frame.refresh_nav) {
						top.content.nav_frame.refresh_nav();
					}
				}

				' . $dblist->CBfunctions() . '
				function editRecords(table,idList,addParams,CBflag) {
					window.location.href="' . $GLOBALS['BACK_PATH'] . 'alt_doc.php?returnUrl=' . rawurlencode(GeneralUtility::getIndpEnv('REQUEST_URI')) . '&edit["+table+"]["+idList+"]=edit"+addParams;
				}
				function editList(table,idList) {
					var list="";

						// Checking how many is checked, how many is not
					var pointer=0;
					var pos = idList.indexOf(",");
					while (pos!=-1) {
						if (cbValue(table+"|"+idList.substr(pointer,pos-pointer))) {
							list+=idList.substr(pointer,pos-pointer)+",";
						}
						pointer=pos+1;
						pos = idList.indexOf(",",pointer);
					}
					if (cbValue(table+"|"+idList.substr(pointer))) {
						list+=idList.substr(pointer)+",";
					}

					return list ? list : idList;
				}
				T3_THIS_LOCATION = "' . rawurlencode(GeneralUtility::getIndpEnv('REQUEST_URI')) . '";

				if (top.fsMod) top.fsMod.recentIds["web"] = ' . (int)$this->id . ';
			');
			// Setting up the context sensitive menu:
			$this->taskObject->doc->getContextMenuCode();
			// Begin to compile the whole page
			$content .= '<form action="' . htmlspecialchars($dblist->listURL()) . '" method="post" name="dblistForm">' . $dblist->HTMLcode . '<input type="hidden" name="cmd_table" /><input type="hidden" name="cmd" />
						</form>';
			// If a listing was produced, create the page footer with search form etc:
			if ($dblist->HTMLcode) {
				// Making field select box (when extended view for a single table is enabled):
				if ($dblist->table) {
					$tmpBackpath = $GLOBALS['BACK_PATH'];
					$GLOBALS['BACK_PATH'] = '';
					$content .= $dblist->fieldSelectBox($dblist->table);
					$GLOBALS['BACK_PATH'] = $tmpBackpath;
				}
			}
		} else {
			// Not enough rights to access the list view or the page
			$flashMessage = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Messaging\FlashMessage::class, $GLOBALS['LANG']->getLL('action_error-access', TRUE), $GLOBALS['LANG']->getLL('action_error'), \TYPO3\CMS\Core\Messaging\FlashMessage::ERROR);
			$content .= $flashMessage->render();
		}
		return $content;
	}

}
