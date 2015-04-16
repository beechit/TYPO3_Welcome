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

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * ExtDirect server
 *
 * @author Workspaces Team (http://forge.typo3.org/projects/show/typo3v4-workspaces)
 */
class ExtDirectServer extends AbstractHandler {

	/**
	 * @var \TYPO3\CMS\Workspaces\Service\GridDataService
	 */
	protected $gridDataService;

	/**
	 * @var \TYPO3\CMS\Workspaces\Service\StagesService
	 */
	protected $stagesService;

	/**
	 * Checks integrity of elements before peforming actions on them.
	 *
	 * @param stdClass $parameters
	 * @return array
	 */
	public function checkIntegrity(\stdClass $parameters) {
		$integrity = $this->createIntegrityService($this->getAffectedElements($parameters));
		$integrity->check();
		$response = array(
			'result' => $integrity->getStatusRepresentation()
		);
		return $response;
	}

	/**
	 * Get List of workspace changes
	 *
	 * @param \stdClass $parameter
	 * @return array $data
	 */
	public function getWorkspaceInfos($parameter) {
		// To avoid too much work we use -1 to indicate that every page is relevant
		$pageId = $parameter->id > 0 ? $parameter->id : -1;
		if (!isset($parameter->language) || !\TYPO3\CMS\Core\Utility\MathUtility::canBeInterpretedAsInteger($parameter->language)) {
			$parameter->language = NULL;
		}
		$versions = $this->getWorkspaceService()->selectVersionsInWorkspace($this->getCurrentWorkspace(), 0, -99, $pageId, $parameter->depth, 'tables_select', $parameter->language);
		$data = $this->getGridDataService()->generateGridListFromVersions($versions, $parameter, $this->getCurrentWorkspace());
		return $data;
	}

	/**
	 * Gets the editing history of a record.
	 *
	 * @param stdClass $parameters
	 * @return array
	 */
	public function getHistory($parameters) {
		/** @var $historyService \TYPO3\CMS\Workspaces\Service\HistoryService */
		$historyService = GeneralUtility::makeInstance(\TYPO3\CMS\Workspaces\Service\HistoryService::class);
		$history = $historyService->getHistory($parameters->table, $parameters->liveId);
		return array(
			'data' => $history,
			'total' => count($history)
		);
	}

	/**
	 * Get List of available workspace actions
	 *
	 * @param \stdClass $parameter
	 * @return array $data
	 */
	public function getStageActions(\stdClass $parameter) {
		$currentWorkspace = $this->getCurrentWorkspace();
		$stages = array();
		if ($currentWorkspace != \TYPO3\CMS\Workspaces\Service\WorkspaceService::SELECT_ALL_WORKSPACES) {
			$stages = $this->getStagesService()->getStagesForWSUser();
		}
		$data = array(
			'total' => count($stages),
			'data' => $stages
		);
		return $data;
	}

	/**
	 * Fetch further information to current selected workspace record.
	 *
	 * @param \stdClass $parameter
	 * @return array $data
	 */
	public function getRowDetails($parameter) {
		$diffReturnArray = array();
		$liveReturnArray = array();
		/** @var $t3lib_diff \TYPO3\CMS\Core\Utility\DiffUtility */
		$t3lib_diff = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Utility\DiffUtility::class);
		/** @var $parseObj \TYPO3\CMS\Core\Html\RteHtmlParser */
		$parseObj = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Html\RteHtmlParser::class);
		$liveRecord = BackendUtility::getRecord($parameter->table, $parameter->t3ver_oid);
		$versionRecord = BackendUtility::getRecord($parameter->table, $parameter->uid);
		$icon_Live = \TYPO3\CMS\Backend\Utility\IconUtility::mapRecordTypeToSpriteIconClass($parameter->table, $liveRecord);
		$icon_Workspace = \TYPO3\CMS\Backend\Utility\IconUtility::mapRecordTypeToSpriteIconClass($parameter->table, $versionRecord);
		$stagePosition = $this->getStagesService()->getPositionOfCurrentStage($parameter->stage);
		$fieldsOfRecords = array_keys($liveRecord);
		if ($GLOBALS['TCA'][$parameter->table]) {
			if ($GLOBALS['TCA'][$parameter->table]['interface']['showRecordFieldList']) {
				$fieldsOfRecords = $GLOBALS['TCA'][$parameter->table]['interface']['showRecordFieldList'];
				$fieldsOfRecords = GeneralUtility::trimExplode(',', $fieldsOfRecords, TRUE);
			}
		}
		foreach ($fieldsOfRecords as $fieldName) {
			// check for exclude fields
			if ($GLOBALS['BE_USER']->isAdmin() || $GLOBALS['TCA'][$parameter->table]['columns'][$fieldName]['exclude'] == 0 || GeneralUtility::inList($GLOBALS['BE_USER']->groupData['non_exclude_fields'], $parameter->table . ':' . $fieldName)) {
				// call diff class only if there is a difference
				if ((string)$liveRecord[$fieldName] !== (string)$versionRecord[$fieldName]) {
					// Select the human readable values before diff
					$liveRecord[$fieldName] = BackendUtility::getProcessedValue(
						$parameter->table,
						$fieldName,
						$liveRecord[$fieldName],
						0,
						1,
						FALSE,
						$liveRecord['uid']
					);
					$versionRecord[$fieldName] = BackendUtility::getProcessedValue(
						$parameter->table,
						$fieldName,
						$versionRecord[$fieldName],
						0,
						1,
						FALSE,
						$versionRecord['uid']
					);
					// Get the field's label. If not available, use the field name
					$fieldTitle = $GLOBALS['LANG']->sL(BackendUtility::getItemLabel($parameter->table, $fieldName));
					if (empty($fieldTitle)) {
						$fieldTitle = $fieldName;
					}
					if ($GLOBALS['TCA'][$parameter->table]['columns'][$fieldName]['config']['type'] == 'group' && $GLOBALS['TCA'][$parameter->table]['columns'][$fieldName]['config']['internal_type'] == 'file') {
						$versionThumb = BackendUtility::thumbCode($versionRecord, $parameter->table, $fieldName, '');
						$liveThumb = BackendUtility::thumbCode($liveRecord, $parameter->table, $fieldName, '');
						$diffReturnArray[] = array(
							'field' => $fieldName,
							'label' => $fieldTitle,
							'content' => $versionThumb
						);
						$liveReturnArray[] = array(
							'field' => $fieldName,
							'label' => $fieldTitle,
							'content' => $liveThumb
						);
					} else {
						$diffReturnArray[] = array(
							'field' => $fieldName,
							'label' => $fieldTitle,
							'content' => $t3lib_diff->makeDiffDisplay($liveRecord[$fieldName], $versionRecord[$fieldName])
						);
						$liveReturnArray[] = array(
							'field' => $fieldName,
							'label' => $fieldTitle,
							'content' => $parseObj->TS_images_rte($liveRecord[$fieldName])
						);
					}
				}
			}
		}
		// Hook for modifying the difference and live arrays
		// (this may be used by custom or dynamically-defined fields)
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['workspaces']['modifyDifferenceArray'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['workspaces']['modifyDifferenceArray'] as $className) {
				$hookObject =& GeneralUtility::getUserObj($className);
				$hookObject->modifyDifferenceArray($parameter, $diffReturnArray, $liveReturnArray, $t3lib_diff);
			}
		}
		$commentsForRecord = $this->getCommentsForRecord($parameter->uid, $parameter->table);
		return array(
			'total' => 1,
			'data' => array(
				array(
					'diff' => $diffReturnArray,
					'live_record' => $liveReturnArray,
					'path_Live' => $parameter->path_Live,
					'label_Stage' => $parameter->label_Stage,
					'stage_position' => $stagePosition['position'],
					'stage_count' => $stagePosition['count'],
					'comments' => $commentsForRecord,
					'icon_Live' => $icon_Live,
					'icon_Workspace' => $icon_Workspace
				)
			)
		);
	}

	/**
	 * Gets an array with all sys_log entries and their comments for the given record uid and table
	 *
	 * @param int $uid uid of changed element to search for in log
	 * @param string $table Name of the record's table
	 * @return array
	 */
	public function getCommentsForRecord($uid, $table) {
		$sysLogReturnArray = array();
		$sysLogRows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'log_data,tstamp,userid',
			'sys_log',
			'action=6 and details_nr=30 AND tablename=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($table, 'sys_log')
				. ' AND recuid=' . (int)$uid,
			'',
			'tstamp DESC'
		);
		foreach ($sysLogRows as $sysLogRow) {
			$sysLogEntry = array();
			$data = unserialize($sysLogRow['log_data']);
			$beUserRecord = BackendUtility::getRecord('be_users', $sysLogRow['userid']);
			$sysLogEntry['stage_title'] = $this->getStagesService()->getStageTitle($data['stage']);
			$sysLogEntry['user_uid'] = $sysLogRow['userid'];
			$sysLogEntry['user_username'] = is_array($beUserRecord) ? $beUserRecord['username'] : '';
			$sysLogEntry['tstamp'] = BackendUtility::datetime($sysLogRow['tstamp']);
			$sysLogEntry['user_comment'] = $data['comment'];
			$sysLogReturnArray[] = $sysLogEntry;
		}
		return $sysLogReturnArray;
	}

	/**
	 * Gets all available system languages.
	 *
	 * @return array
	 */
	public function getSystemLanguages() {
		$systemLanguages = array(
			array(
				'uid' => 'all',
				'title' => \TYPO3\CMS\Extbase\Utility\LocalizationUtility::translate('language.allLanguages', 'workspaces'),
				'cls' => \TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIconClasses('empty-empty')
			)
		);
		foreach ($this->getGridDataService()->getSystemLanguages() as $id => $systemLanguage) {
			if ($id < 0) {
				continue;
			}
			$systemLanguages[] = array(
				'uid' => $id,
				'title' => htmlspecialchars($systemLanguage['title']),
				'cls' => \TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIconClasses($systemLanguage['flagIcon'])
			);
		}
		$result = array(
			'total' => count($systemLanguages),
			'data' => $systemLanguages
		);
		return $result;
	}

	/**
	 * Gets the Grid Data Service.
	 *
	 * @return \TYPO3\CMS\Workspaces\Service\GridDataService
	 */
	protected function getGridDataService() {
		if (!isset($this->gridDataService)) {
			$this->gridDataService = GeneralUtility::makeInstance(\TYPO3\CMS\Workspaces\Service\GridDataService::class);
		}
		return $this->gridDataService;
	}

	/**
	 * Gets the Stages Service.
	 *
	 * @return \TYPO3\CMS\Workspaces\Service\StagesService
	 */
	protected function getStagesService() {
		if (!isset($this->stagesService)) {
			$this->stagesService = GeneralUtility::makeInstance(\TYPO3\CMS\Workspaces\Service\StagesService::class);
		}
		return $this->stagesService;
	}

}
