<?php
namespace TYPO3\CMS\Workspaces\ExtDirect;

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
use TYPO3\CMS\Workspaces\Service\StagesService;

/**
 * ExtDirect action handler
 *
 * @author Workspaces Team (http://forge.typo3.org/projects/show/typo3v4-workspaces)
 */
class ActionHandler extends AbstractHandler {

	/**
	 * @var StagesService
	 */
	protected $stageService;

	/**
	 * Creates this object.
	 */
	public function __construct() {
		$this->stageService = GeneralUtility::makeInstance(\TYPO3\CMS\Workspaces\Service\StagesService::class);
	}

	/**
	 * Generates a workspace preview link.
	 *
	 * @param int $uid The ID of the record to be linked
	 * @return string the full domain including the protocol http:// or https://, but without the trailing '/'
	 */
	public function generateWorkspacePreviewLink($uid) {
		return $this->getWorkspaceService()->generateWorkspacePreviewLink($uid);
	}

	/**
	 * Swaps a single record.
	 *
	 * @param string $table
	 * @param int $t3ver_oid
	 * @param int $orig_uid
	 * @return void
	 * @todo What about reporting errors back to the ExtJS interface? /olly/
	 */
	public function swapSingleRecord($table, $t3ver_oid, $orig_uid) {
		$versionRecord = BackendUtility::getRecord($table, $orig_uid);
		$currentWorkspace = $this->setTemporaryWorkspace($versionRecord['t3ver_wsid']);

		$cmd[$table][$t3ver_oid]['version'] = array(
			'action' => 'swap',
			'swapWith' => $orig_uid,
			'swapIntoWS' => 1
		);
		$this->processTcaCmd($cmd);

		$this->setTemporaryWorkspace($currentWorkspace);
	}

	/**
	 * Deletes a single record.
	 *
	 * @param string $table
	 * @param int $uid
	 * @return void
	 * @todo What about reporting errors back to the ExtJS interface? /olly/
	 */
	public function deleteSingleRecord($table, $uid) {
		$versionRecord = BackendUtility::getRecord($table, $uid);
		$currentWorkspace = $this->setTemporaryWorkspace($versionRecord['t3ver_wsid']);

		$cmd[$table][$uid]['version'] = array(
			'action' => 'clearWSID'
		);
		$this->processTcaCmd($cmd);

		$this->setTemporaryWorkspace($currentWorkspace);
	}

	/**
	 * Generates a view link for a page.
	 *
	 * @param string $table
	 * @param string $uid
	 * @return string
	 */
	public function viewSingleRecord($table, $uid) {
		return \TYPO3\CMS\Workspaces\Service\WorkspaceService::viewSingleRecord($table, $uid);
	}

	/**
	 * Executes an action (publish, discard, swap) to a selection set.
	 *
	 * @param \stdClass $parameter
	 * @return array
	 */
	public function executeSelectionAction($parameter) {
		$result = array();

		if (empty($parameter->action) || empty($parameter->selection)) {
			$result['error'] = 'No action or record selection given';
			return $result;
		}

		$commands = array();
		$swapIntoWorkspace = ($parameter->action === 'swap');
		if ($parameter->action === 'publish' || $swapIntoWorkspace) {
			$commands = $this->getPublishSwapCommands($parameter->selection, $swapIntoWorkspace);
		} elseif ($parameter->action === 'discard') {
			$commands = $this->getFlushCommands($parameter->selection);
		}

		$result = $this->processTcaCmd($commands);
		$result['total'] = count($commands);
		return $result;
	}

	/**
	 * Get publish swap commands
	 *
	 * @param array|\stdClass[] $selection
	 * @param bool $swapIntoWorkspace
	 * @return array
	 */
	protected function getPublishSwapCommands(array $selection, $swapIntoWorkspace) {
		$commands = array();
		foreach ($selection as $record) {
			$commands[$record->table][$record->liveId]['version'] = array(
				'action' => 'swap',
				'swapWith' => $record->versionId,
				'swapIntoWS' => (bool)$swapIntoWorkspace,
			);
		}
		return $commands;
	}

	/**
	 * Get flush commands
	 *
	 * @param array|\stdClass[] $selection
	 * @return array
	 */
	protected function getFlushCommands(array $selection) {
		$commands = array();
		foreach ($selection as $record) {
			$commands[$record->table][$record->versionId]['version'] = array(
				'action' => 'clearWSID',
			);
		}
		return $commands;
	}

	/**
	 * Saves the selected columns to be shown to the preferences of the current backend user.
	 *
	 * @param \stdClass $model
	 * @return void
	 */
	public function saveColumnModel($model) {
		$data = array();
		foreach ($model as $column) {
			$data[$column->column] = array(
				'position' => $column->position,
				'hidden' => $column->hidden
			);
		}
		$GLOBALS['BE_USER']->uc['moduleData']['Workspaces'][$GLOBALS['BE_USER']->workspace]['columns'] = $data;
		$GLOBALS['BE_USER']->writeUC();
	}

	public function loadColumnModel() {
		if (is_array($GLOBALS['BE_USER']->uc['moduleData']['Workspaces'][$GLOBALS['BE_USER']->workspace]['columns'])) {
			return $GLOBALS['BE_USER']->uc['moduleData']['Workspaces'][$GLOBALS['BE_USER']->workspace]['columns'];
		} else {
			return array();
		}
	}

	/**
	 * Saves the selected language.
	 *
	 * @param int|string $language
	 * @return void
	 */
	public function saveLanguageSelection($language) {
		if (\TYPO3\CMS\Core\Utility\MathUtility::canBeInterpretedAsInteger($language) === FALSE && $language !== 'all') {
			$language = 'all';
		}
		$GLOBALS['BE_USER']->uc['moduleData']['Workspaces'][$GLOBALS['BE_USER']->workspace]['language'] = $language;
		$GLOBALS['BE_USER']->writeUC();
	}

	/**
	 * Gets the dialog window to be displayed before a record can be sent to the next stage.
	 *
	 * @param int $uid
	 * @param string $table
	 * @param int $t3ver_oid
	 * @return array
	 */
	public function sendToNextStageWindow($uid, $table, $t3ver_oid) {
		$elementRecord = BackendUtility::getRecord($table, $uid);
		$currentWorkspace = $this->setTemporaryWorkspace($elementRecord['t3ver_wsid']);

		if (is_array($elementRecord)) {
			$stageId = $elementRecord['t3ver_stage'];
			if ($this->getStageService()->isValid($stageId)) {
				$nextStage = $this->getStageService()->getNextStage($stageId);
				$result = $this->getSentToStageWindow($nextStage['uid']);
				$result['affects'] = array(
					'table' => $table,
					'nextStage' => $nextStage['uid'],
					't3ver_oid' => $t3ver_oid,
					'uid' => $uid
				);
			} else {
				$result = $this->getErrorResponse('error.stageId.invalid', 1291111644);
			}
		} else {
			$result = $this->getErrorResponse('error.sendToNextStage.noRecordFound', 1287264776);
		}

		$this->setTemporaryWorkspace($currentWorkspace);
		return $result;
	}

	/**
	 * Gets the dialog window to be displayed before a record can be sent to the previous stage.
	 *
	 * @param int $uid
	 * @param string $table
	 * @return array
	 */
	public function sendToPrevStageWindow($uid, $table) {
		$elementRecord = BackendUtility::getRecord($table, $uid);
		$currentWorkspace = $this->setTemporaryWorkspace($elementRecord['t3ver_wsid']);

		if (is_array($elementRecord)) {
			$stageId = $elementRecord['t3ver_stage'];
			if ($this->getStageService()->isValid($stageId)) {
				if ($stageId !== StagesService::STAGE_EDIT_ID) {
					$prevStage = $this->getStageService()->getPrevStage($stageId);
					$result = $this->getSentToStageWindow($prevStage['uid']);
					$result['affects'] = array(
						'table' => $table,
						'uid' => $uid,
						'nextStage' => $prevStage['uid']
					);
				} else {
					// element is already in edit stage, there is no prev stage - return an error message
					$result = $this->getErrorResponse('error.sendToPrevStage.noPreviousStage', 1287264746);
				}
			} else {
				$result = $this->getErrorResponse('error.stageId.invalid', 1291111644);
			}
		} else {
			$result = $this->getErrorResponse('error.sendToNextStage.noRecordFound', 1287264765);
		}

		$this->setTemporaryWorkspace($currentWorkspace);
		return $result;
	}

	/**
	 * Gets the dialog window to be displayed before a record can be sent to a specific stage.
	 *
	 * @param int $nextStageId
	 * @return array
	 */
	public function sendToSpecificStageWindow($nextStageId) {
		$result = $this->getSentToStageWindow($nextStageId);
		$result['affects'] = array(
			'nextStage' => $nextStageId
		);
		return $result;
	}

	/**
	 * Gets a merged variant of recipient defined by uid and custom ones.
	 *
	 * @param array list of recipients
	 * @param string given user string of additional recipients
	 * @param int stage id
	 * @return array
	 */
	public function getRecipientList(array $uidOfRecipients, $additionalRecipients, $stageId) {
		$finalRecipients = array();
		if (!$this->getStageService()->isValid($stageId)) {
			throw new \InvalidArgumentException($GLOBALS['LANG']->sL('LLL:EXT:workspaces/Resources/Private/Language/locallang.xlf:error.stageId.integer'));
		} else {
			$stageId = (int)$stageId;
		}
		$recipients = array();
		foreach ($uidOfRecipients as $userUid) {
			$beUserRecord = BackendUtility::getRecord('be_users', (int)$userUid);
			if (is_array($beUserRecord) && $beUserRecord['email'] !== '') {
				$uc = $beUserRecord['uc'] ? unserialize($beUserRecord['uc']) : array();
				$recipients[$beUserRecord['email']] = array(
					'email' => $beUserRecord['email'],
					'lang' => isset($uc['lang']) ? $uc['lang'] : $beUserRecord['lang']
				);
			}
		}
		// the notification mode can be configured in the workspace stage record
		$notification_mode = (int)$this->getStageService()->getNotificationMode($stageId);
		if ($notification_mode === StagesService::MODE_NOTIFY_ALL || $notification_mode === StagesService::MODE_NOTIFY_ALL_STRICT) {
			// get the default recipients from the stage configuration
			// the default recipients needs to be added in some cases of the notification_mode
			$default_recipients = $this->getStageService()->getResponsibleBeUser($stageId, TRUE);
			foreach ($default_recipients as $default_recipient_uid => $default_recipient_record) {
				if (!isset($recipients[$default_recipient_record['email']])) {
					$uc = $default_recipient_record['uc'] ? unserialize($default_recipient_record['uc']) : array();
					$recipients[$default_recipient_record['email']] = array(
						'email' => $default_recipient_record['email'],
						'lang' => isset($uc['lang']) ? $uc['lang'] : $default_recipient_record['lang']
					);
				}
			}
		}
		if ($additionalRecipients !== '') {
			$emails = GeneralUtility::trimExplode(LF, $additionalRecipients, TRUE);
			$additionalRecipients = array();
			foreach ($emails as $email) {
				$additionalRecipients[$email] = array('email' => $email);
			}
		} else {
			$additionalRecipients = array();
		}
		// We merge $recipients on top of $additionalRecipients because $recipients
		// possibly is more complete with a user language. Furthermore, the list of
		// recipients is automatically unique since we indexed $additionalRecipients
		// and $recipients with the email address
		$allRecipients = array_merge($additionalRecipients, $recipients);
		foreach ($allRecipients as $email => $recipientInformation) {
			if (GeneralUtility::validEmail($email)) {
				$finalRecipients[] = $recipientInformation;
			}
		}
		return $finalRecipients;
	}

	/**
	 * Discard all items from given page id.
	 *
	 * @param int $pageId
	 * @return array
	 * @author Michael Klapper <development@morphodo.com>
	 */
	public function discardStagesFromPage($pageId) {
		$cmdMapArray = array();
		/** @var $workspaceService \TYPO3\CMS\Workspaces\Service\WorkspaceService */
		$workspaceService = GeneralUtility::makeInstance(\TYPO3\CMS\Workspaces\Service\WorkspaceService::class);
		/** @var $stageService StagesService */
		$stageService = GeneralUtility::makeInstance(\TYPO3\CMS\Workspaces\Service\StagesService::class);
		$workspaceItemsArray = $workspaceService->selectVersionsInWorkspace($stageService->getWorkspaceId(), ($filter = 1), ($stage = -99), $pageId, ($recursionLevel = 0), ($selectionType = 'tables_modify'));
		foreach ($workspaceItemsArray as $tableName => $items) {
			foreach ($items as $item) {
				$cmdMapArray[$tableName][$item['uid']]['version']['action'] = 'clearWSID';
			}
		}
		$this->processTcaCmd($cmdMapArray);
		return array(
			'success' => TRUE
		);
	}

	/**
	 * Push the given element collection to the next workspace stage.
	 *
	 * <code>
	 * $parameters->additional = your@mail.com
	 * $parameters->affects->__TABLENAME__
	 * $parameters->comments
	 * $parameters->receipients
	 * $parameters->stageId
	 * </code>
	 *
	 * @param stdClass $parameters
	 * @return array
	 * @author Michael Klapper <development@morphodo.com>
	 */
	public function sentCollectionToStage(\stdClass $parameters) {
		$cmdMapArray = array();
		$comment = $parameters->comments;
		$stageId = $parameters->stageId;
		if (\TYPO3\CMS\Core\Utility\MathUtility::canBeInterpretedAsInteger($stageId) === FALSE) {
			throw new \InvalidArgumentException('Missing "stageId" in $parameters array.', 1319488194);
		}
		if (!is_object($parameters->affects) || count($parameters->affects) == 0) {
			throw new \InvalidArgumentException('Missing "affected items" in $parameters array.', 1319488195);
		}
		$recipients = $this->getRecipientList($parameters->receipients, $parameters->additional, $stageId);
		foreach ($parameters->affects as $tableName => $items) {
			foreach ($items as $item) {
				// Publishing uses live id in command map
				if ($stageId == StagesService::STAGE_PUBLISH_EXECUTE_ID) {
					$cmdMapArray[$tableName][$item->t3ver_oid]['version']['action'] = 'swap';
					$cmdMapArray[$tableName][$item->t3ver_oid]['version']['swapWith'] = $item->uid;
					$cmdMapArray[$tableName][$item->t3ver_oid]['version']['comment'] = $comment;
					$cmdMapArray[$tableName][$item->t3ver_oid]['version']['notificationAlternativeRecipients'] = $recipients;
				// Setting stage uses version id in command map
				} else {
					$cmdMapArray[$tableName][$item->uid]['version']['action'] = 'setStage';
					$cmdMapArray[$tableName][$item->uid]['version']['stageId'] = $stageId;
					$cmdMapArray[$tableName][$item->uid]['version']['comment'] = $comment;
					$cmdMapArray[$tableName][$item->uid]['version']['notificationAlternativeRecipients'] = $recipients;
				}
			}
		}
		$this->processTcaCmd($cmdMapArray);
		return array(
			'success' => TRUE,
			// force refresh after publishing changes
			'refreshLivePanel' => $parameters->stageId == -20 ? TRUE : FALSE
		);
	}

	/**
	 * Process TCA command map array.
	 *
	 * @param array $cmdMapArray
	 * @return array
	 * @author Michael Klapper <development@morphodo.com>
	 */
	protected function processTcaCmd(array $cmdMapArray) {
		$result = array();

		if (empty($cmdMapArray)) {
			$result['error'] = 'No commands given to be processed';
			return $result;
		}

		/** @var \TYPO3\CMS\Core\DataHandling\DataHandler $dataHandler */
		$dataHandler = GeneralUtility::makeInstance(\TYPO3\CMS\Core\DataHandling\DataHandler::class);
		$dataHandler->start(array(), $cmdMapArray);
		$dataHandler->process_cmdmap();

		if ($dataHandler->errorLog) {
			$result['error'] = implode('<br/>', $dataHandler->errorLog);
		}

		return $result;
	}

	/**
	 * Gets an object with this structure:
	 *
	 * affects: object
	 * table
	 * t3ver_oid
	 * nextStage
	 * uid
	 * receipients: array with uids
	 * additional: string
	 * comments: string
	 *
	 * @param stdClass $parameters
	 * @return array
	 */
	public function sendToNextStageExecute(\stdClass $parameters) {
		$cmdArray = array();
		$setStageId = $parameters->affects->nextStage;
		$comments = $parameters->comments;
		$table = $parameters->affects->table;
		$uid = $parameters->affects->uid;
		$t3ver_oid = $parameters->affects->t3ver_oid;

		$elementRecord = BackendUtility::getRecord($table, $uid);
		$currentWorkspace = $this->setTemporaryWorkspace($elementRecord['t3ver_wsid']);

		$recipients = $this->getRecipientList($parameters->receipients, $parameters->additional, $setStageId);
		if ($setStageId == StagesService::STAGE_PUBLISH_EXECUTE_ID) {
			$cmdArray[$table][$t3ver_oid]['version']['action'] = 'swap';
			$cmdArray[$table][$t3ver_oid]['version']['swapWith'] = $uid;
			$cmdArray[$table][$t3ver_oid]['version']['comment'] = $comments;
			$cmdArray[$table][$t3ver_oid]['version']['notificationAlternativeRecipients'] = $recipients;
		} else {
			$cmdArray[$table][$uid]['version']['action'] = 'setStage';
			$cmdArray[$table][$uid]['version']['stageId'] = $setStageId;
			$cmdArray[$table][$uid]['version']['comment'] = $comments;
			$cmdArray[$table][$uid]['version']['notificationAlternativeRecipients'] = $recipients;
		}
		$this->processTcaCmd($cmdArray);
		$result = array(
			'success' => TRUE
		);

		$this->setTemporaryWorkspace($currentWorkspace);
		return $result;
	}

	/**
	 * Gets an object with this structure:
	 *
	 * affects: object
	 * table
	 * t3ver_oid
	 * nextStage
	 * receipients: array with uids
	 * additional: string
	 * comments: string
	 *
	 * @param stdClass $parameters
	 * @return array
	 */
	public function sendToPrevStageExecute(\stdClass $parameters) {
		$cmdArray = array();
		$setStageId = $parameters->affects->nextStage;
		$comments = $parameters->comments;
		$table = $parameters->affects->table;
		$uid = $parameters->affects->uid;

		$elementRecord = BackendUtility::getRecord($table, $uid);
		$currentWorkspace = $this->setTemporaryWorkspace($elementRecord['t3ver_wsid']);

		$recipients = $this->getRecipientList($parameters->receipients, $parameters->additional, $setStageId);
		$cmdArray[$table][$uid]['version']['action'] = 'setStage';
		$cmdArray[$table][$uid]['version']['stageId'] = $setStageId;
		$cmdArray[$table][$uid]['version']['comment'] = $comments;
		$cmdArray[$table][$uid]['version']['notificationAlternativeRecipients'] = $recipients;
		$this->processTcaCmd($cmdArray);
		$result = array(
			'success' => TRUE
		);

		$this->setTemporaryWorkspace($currentWorkspace);
		return $result;
	}

	/**
	 * Gets an object with this structure:
	 *
	 * affects: object
	 * elements: array
	 * 0: object
	 * table
	 * t3ver_oid
	 * uid
	 * 1: object
	 * table
	 * t3ver_oid
	 * uid
	 * nextStage
	 * receipients: array with uids
	 * additional: string
	 * comments: string
	 *
	 * @param stdClass $parameters
	 * @return array
	 */
	public function sendToSpecificStageExecute(\stdClass $parameters) {
		$cmdArray = array();
		$setStageId = $parameters->affects->nextStage;
		$comments = $parameters->comments;
		$elements = $parameters->affects->elements;
		$recipients = $this->getRecipientList($parameters->receipients, $parameters->additional, $setStageId);
		foreach ($elements as $key => $element) {
			if ($setStageId == StagesService::STAGE_PUBLISH_EXECUTE_ID) {
				$cmdArray[$element->table][$element->t3ver_oid]['version']['action'] = 'swap';
				$cmdArray[$element->table][$element->t3ver_oid]['version']['swapWith'] = $element->uid;
				$cmdArray[$element->table][$element->t3ver_oid]['version']['comment'] = $comments;
				$cmdArray[$element->table][$element->t3ver_oid]['version']['notificationAlternativeRecipients'] = $recipients;
			} else {
				$cmdArray[$element->table][$element->uid]['version']['action'] = 'setStage';
				$cmdArray[$element->table][$element->uid]['version']['stageId'] = $setStageId;
				$cmdArray[$element->table][$element->uid]['version']['comment'] = $comments;
				$cmdArray[$element->table][$element->uid]['version']['notificationAlternativeRecipients'] = $recipients;
			}
		}
		$this->processTcaCmd($cmdArray);
		$result = array(
			'success' => TRUE
		);
		return $result;
	}

	/**
	 * Gets the dialog window to be displayed before a record can be sent to a stage.
	 *
	 * @param $nextStageId
	 * @return array
	 */
	protected function getSentToStageWindow($nextStageId) {
		$workspaceRec = BackendUtility::getRecord('sys_workspace', $this->getStageService()->getWorkspaceId());
		$showNotificationFields = FALSE;
		$stageTitle = $this->getStageService()->getStageTitle($nextStageId);
		$result = array(
			'title' => $GLOBALS['LANG']->sL('LLL:EXT:workspaces/Resources/Private/Language/locallang.xlf:actionSendToStage'),
			'items' => array(
				array(
					'xtype' => 'panel',
					'bodyStyle' => 'margin-bottom: 7px; border: none;',
					'html' => $GLOBALS['LANG']->sL('LLL:EXT:workspaces/Resources/Private/Language/locallang.xlf:window.sendToNextStageWindow.itemsWillBeSentTo') . ' ' . $stageTitle
				)
			)
		);
		switch ($nextStageId) {
			case StagesService::STAGE_PUBLISH_EXECUTE_ID:

			case StagesService::STAGE_PUBLISH_ID:
				if (!empty($workspaceRec['publish_allow_notificaton_settings'])) {
					$showNotificationFields = TRUE;
				}
				break;
			case StagesService::STAGE_EDIT_ID:
				if (!empty($workspaceRec['edit_allow_notificaton_settings'])) {
					$showNotificationFields = TRUE;
				}
				break;
			default:
				$allow_notificaton_settings = $this->getStageService()->getPropertyOfCurrentWorkspaceStage($nextStageId, 'allow_notificaton_settings');
				if (!empty($allow_notificaton_settings)) {
					$showNotificationFields = TRUE;
				}
		}
		if ($showNotificationFields == TRUE) {
			$result['items'][] = array(
				'fieldLabel' => $GLOBALS['LANG']->sL('LLL:EXT:workspaces/Resources/Private/Language/locallang.xlf:window.sendToNextStageWindow.sendMailTo'),
				'xtype' => 'checkboxgroup',
				'itemCls' => 'x-check-group-alt',
				'columns' => 1,
				'style' => 'max-height: 200px',
				'autoScroll' => TRUE,
				'items' => array(
					$this->getReceipientsOfStage($nextStageId)
				)
			);
			$result['items'][] = array(
				'fieldLabel' => $GLOBALS['LANG']->sL('LLL:EXT:workspaces/Resources/Private/Language/locallang.xlf:window.sendToNextStageWindow.additionalRecipients'),
				'name' => 'additional',
				'xtype' => 'textarea',
				'width' => 250
			);
		}
		$result['items'][] = array(
			'fieldLabel' => $GLOBALS['LANG']->sL('LLL:EXT:workspaces/Resources/Private/Language/locallang.xlf:window.sendToNextStageWindow.comments'),
			'name' => 'comments',
			'xtype' => 'textarea',
			'width' => 250,
			'value' => $this->getDefaultCommentOfStage($nextStageId)
		);
		return $result;
	}

	/**
	 * Gets all assigned recipients of a particular stage.
	 *
	 * @param int $stage
	 * @return array
	 */
	protected function getReceipientsOfStage($stage) {
		$result = array();
		$recipients = $this->getStageService()->getResponsibleBeUser($stage);
		$default_recipients = $this->getStageService()->getResponsibleBeUser($stage, TRUE);
		foreach ($recipients as $id => $user) {
			if (GeneralUtility::validEmail($user['email'])) {
				$checked = FALSE;
				$disabled = FALSE;
				$name = $user['realName'] ? $user['realName'] : $user['username'];
				// the notification mode can be configured in the workspace stage record
				$notification_mode = (int)$this->getStageService()->getNotificationMode($stage);
				if ($notification_mode === StagesService::MODE_NOTIFY_SOMEONE) {
					// all responsible users are checked per default, as in versions before
					$checked = TRUE;
				} elseif ($notification_mode === StagesService::MODE_NOTIFY_ALL) {
					// the default users are checked only
					if (!empty($default_recipients[$id])) {
						$checked = TRUE;
						$disabled = TRUE;
					} else {
						$checked = FALSE;
					}
				} elseif ($notification_mode === StagesService::MODE_NOTIFY_ALL_STRICT) {
					// all responsible users are checked
					$checked = TRUE;
					$disabled = TRUE;
				}
				$result[] = array(
					'boxLabel' => sprintf('%s (%s)', $name, $user['email']),
					'name' => 'receipients-' . $id,
					'checked' => $checked,
					'disabled' => $disabled
				);
			}
		}
		return $result;
	}

	/**
	 * Gets the default comment of a particular stage.
	 *
	 * @param int $stage
	 * @return string
	 */
	protected function getDefaultCommentOfStage($stage) {
		$result = $this->getStageService()->getPropertyOfCurrentWorkspaceStage($stage, 'default_mailcomment');
		return $result;
	}

	/**
	 * Gets an instance of the Stage service.
	 *
	 * @return StagesService
	 */
	protected function getStageService() {
		if (!isset($this->stageService)) {
			$this->stageService = GeneralUtility::makeInstance(\TYPO3\CMS\Workspaces\Service\StagesService::class);
		}
		return $this->stageService;
	}

	/**
	 * Send all available workspace records to the previous stage.
	 *
	 * @param int $id Current page id to process items to previous stage.
	 * @return array
	 * @author Michael Klapper <development@morphodo.com>
	 */
	public function sendPageToPreviousStage($id) {
		$workspaceService = GeneralUtility::makeInstance(\TYPO3\CMS\Workspaces\Service\WorkspaceService::class);
		$workspaceItemsArray = $workspaceService->selectVersionsInWorkspace($this->stageService->getWorkspaceId(), ($filter = 1), ($stage = -99), $id, ($recursionLevel = 0), ($selectionType = 'tables_modify'));
		list($currentStage, $previousStage) = $this->getStageService()->getPreviousStageForElementCollection($workspaceItemsArray);
		// get only the relevant items for processing
		$workspaceItemsArray = $workspaceService->selectVersionsInWorkspace($this->stageService->getWorkspaceId(), ($filter = 1), $currentStage['uid'], $id, ($recursionLevel = 0), ($selectionType = 'tables_modify'));
		return array(
			'title' => 'Status message: Page send to next stage - ID: ' . $id . ' - Next stage title: ' . $previousStage['title'],
			'items' => $this->getSentToStageWindow($previousStage['uid']),
			'affects' => $workspaceItemsArray,
			'stageId' => $previousStage['uid']
		);
	}

	/**
	 * @param int $id Current Page id to select Workspace items from.
	 * @return array
	 * @author Michael Klapper <development@morphodo.com>
	 */
	public function sendPageToNextStage($id) {
		$workspaceService = GeneralUtility::makeInstance(\TYPO3\CMS\Workspaces\Service\WorkspaceService::class);
		$workspaceItemsArray = $workspaceService->selectVersionsInWorkspace($this->stageService->getWorkspaceId(), ($filter = 1), ($stage = -99), $id, ($recursionLevel = 0), ($selectionType = 'tables_modify'));
		list($currentStage, $nextStage) = $this->getStageService()->getNextStageForElementCollection($workspaceItemsArray);
		// get only the relevant items for processing
		$workspaceItemsArray = $workspaceService->selectVersionsInWorkspace($this->stageService->getWorkspaceId(), ($filter = 1), $currentStage['uid'], $id, ($recursionLevel = 0), ($selectionType = 'tables_modify'));
		return array(
			'title' => 'Status message: Page send to next stage - ID: ' . $id . ' - Next stage title: ' . $nextStage['title'],
			'items' => $this->getSentToStageWindow($nextStage['uid']),
			'affects' => $workspaceItemsArray,
			'stageId' => $nextStage['uid']
		);
	}

	/**
	 * Fetch the current label and visible state of the buttons.
	 *
	 * @param int $id
	 * @return array Contains the visibility state and label of the stage change buttons.
	 * @author Michael Klapper <development@morphodo.com>
	 */
	public function updateStageChangeButtons($id) {
		$stageService = GeneralUtility::makeInstance(\TYPO3\CMS\Workspaces\Service\StagesService::class);
		$workspaceService = GeneralUtility::makeInstance(\TYPO3\CMS\Workspaces\Service\WorkspaceService::class);
		// fetch the next and previous stage
		$workspaceItemsArray = $workspaceService->selectVersionsInWorkspace($stageService->getWorkspaceId(), ($filter = 1), ($stage = -99), $id, ($recursionLevel = 0), ($selectionType = 'tables_modify'));
		list(, $nextStage) = $stageService->getNextStageForElementCollection($workspaceItemsArray);
		list(, $previousStage) = $stageService->getPreviousStageForElementCollection($workspaceItemsArray);
		$toolbarButtons = array(
			'feToolbarButtonNextStage' => array(
				'visible' => is_array($nextStage) && count($nextStage) > 0,
				'text' => $nextStage['title']
			),
			'feToolbarButtonPreviousStage' => array(
				'visible' => is_array($previousStage) && count($previousStage),
				'text' => $previousStage['title']
			),
			'feToolbarButtonDiscardStage' => array(
				'visible' => is_array($nextStage) && count($nextStage) > 0 || is_array($previousStage) && count($previousStage) > 0,
				'text' => $GLOBALS['LANG']->sL('LLL:EXT:workspaces/Resources/Private/Language/locallang.xlf:label_doaction_discard', TRUE)
			)
		);
		return $toolbarButtons;
	}

	/**
	 * @param int $workspaceId
	 * @return int Id of the original workspace
	 * @throws \TYPO3\CMS\Core\Exception
	 */
	protected function setTemporaryWorkspace($workspaceId) {
		$workspaceId = (int)$workspaceId;
		$currentWorkspace = (int)$this->getBackendUser()->workspace;

		if ($currentWorkspace !== $workspaceId) {
			if (!$this->getBackendUser()->setTemporaryWorkspace($workspaceId)) {
				throw new \TYPO3\CMS\Core\Exception(
					'Cannot set temporary workspace to "' . $workspaceId . '"',
					1371484524
				);
			}
		}

		return $currentWorkspace;
	}

	/**
	 * @return \TYPO3\CMS\Core\Authentication\BackendUserAuthentication
	 */
	protected function getBackendUser() {
		return $GLOBALS['BE_USER'];
	}

}
