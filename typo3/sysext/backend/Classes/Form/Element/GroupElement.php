<?php
namespace TYPO3\CMS\Backend\Form\Element;

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
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * Generation of TCEform elements of the type "group"
 */
class GroupElement extends AbstractFormElement {

	/**
	 * This will render a selectorbox into which elements from either
	 * the file system or database can be inserted. Relations.
	 *
	 * @param string $table The table name of the record
	 * @param string $field The field name which this element is supposed to edit
	 * @param array $row The record data array where the value(s) for the field can be found
	 * @param array $additionalInformation An array with additional configuration options.
	 * @return string The HTML code for the TCEform field
	 */
	public function render($table, $field, $row, &$additionalInformation) {

		$config = $additionalInformation['fieldConf']['config'];
		$show_thumbs = $config['show_thumbs'];
		$size = isset($config['size']) ? (int)$config['size'] : $this->formEngine->minimumInputWidth;
		$maxitems = MathUtility::forceIntegerInRange($config['maxitems'], 0);
		if (!$maxitems) {
			$maxitems = 100000;
		}
		$minitems = MathUtility::forceIntegerInRange($config['minitems'], 0);
		$thumbnails = array();
		$allowed = GeneralUtility::trimExplode(',', $config['allowed'], TRUE);
		$disallowed = GeneralUtility::trimExplode(',', $config['disallowed'], TRUE);
		$disabled = ($this->isRenderReadonly() || $config['readOnly']);
		$info = array();
		$additionalInformation['itemFormElID_file'] = $additionalInformation['itemFormElID'] . '_files';

		// whether the list and delete controls should be disabled
		$noList = isset($config['disable_controls']) && GeneralUtility::inList($config['disable_controls'], 'list');
		$noDelete = isset($config['disable_controls']) && GeneralUtility::inList($config['disable_controls'], 'delete');

		// "Extra" configuration; Returns configuration for the field based on settings found in the "types" fieldlist.
		$specConf = BackendUtility::getSpecConfParts($additionalInformation['extra'], $additionalInformation['fieldConf']['defaultExtras']);

		// Register properties in requiredFields and requiredElements
		$this->formEngine->registerRequiredProperty(
			'range',
			$additionalInformation['itemFormElName'],
			array(
				$minitems,
				$maxitems,
				'imgName' => $table . '_' . $row['uid'] . '_' . $field
			)
		);

		// if maxitems==1 then automatically replace the current item (in list and file selector)
		if ($maxitems === 1) {
			$this->formEngine->additionalJS_post[] = 'TBE_EDITOR.clearBeforeSettingFormValueFromBrowseWin[\'' . $additionalInformation['itemFormElName'] . '\'] = {
					itemFormElID_file: \'' . $additionalInformation['itemFormElID_file'] . '\'
				}';
			$additionalInformation['fieldChangeFunc']['TBE_EDITOR_fieldChanged'] = 'setFormValueManipulate(\'' . $additionalInformation['itemFormElName']
				. '\', \'Remove\'); ' . $additionalInformation['fieldChangeFunc']['TBE_EDITOR_fieldChanged'];
		} elseif ($noList) {
			// If the list controls have been removed and the maximum number is reached, remove the first entry to avoid "write once" field
			$additionalInformation['fieldChangeFunc']['TBE_EDITOR_fieldChanged'] = 'setFormValueManipulate(\'' . $additionalInformation['itemFormElName']
				. '\', \'RemoveFirstIfFull\', \'' . $maxitems . '\'); ' . $additionalInformation['fieldChangeFunc']['TBE_EDITOR_fieldChanged'];
		}

		$item = '<input type="hidden" name="' . $additionalInformation['itemFormElName'] . '_mul" value="' . ($config['multiple'] ? 1 : 0) . '"' . $disabled . ' />';

		// Acting according to either "file" or "db" type:
		switch ((string)$config['internal_type']) {
			case 'file_reference':
				$config['uploadfolder'] = '';
				// Fall through
			case 'file':
				// Creating string showing allowed types:
				if (!count($allowed)) {
					$allowed = array('*');
				}
				// Making the array of file items:
				$itemArray = GeneralUtility::trimExplode(',', $additionalInformation['itemFormElValue'], TRUE);
				$fileFactory = ResourceFactory::getInstance();
				// Correct the filename for the FAL items
				foreach ($itemArray as &$fileItem) {
					list($fileUid, $fileLabel) = explode('|', $fileItem);
					if (MathUtility::canBeInterpretedAsInteger($fileUid)) {
						$fileObject = $fileFactory->getFileObject($fileUid);
						$fileLabel = $fileObject->getName();
					}
					$fileItem = $fileUid . '|' . $fileLabel;
				}
				// Showing thumbnails:
				if ($show_thumbs) {
					$imgs = array();
					foreach ($itemArray as $imgRead) {
						$imgP = explode('|', $imgRead);
						$imgPath = rawurldecode($imgP[0]);
						// FAL icon production
						if (MathUtility::canBeInterpretedAsInteger($imgP[0])) {
							$fileObject = $fileFactory->getFileObject($imgP[0]);
							if ($fileObject->isMissing()) {
								$thumbnails[] = array(
									'message' => \TYPO3\CMS\Core\Resource\Utility\BackendUtility::getFlashMessageForMissingFile($fileObject)->render()
								);
							} elseif (GeneralUtility::inList($GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'], $fileObject->getExtension())) {
								$thumbnails[] = array(
									'name' => htmlspecialchars($fileObject->getName()),
									'image' => $fileObject->process(ProcessedFile::CONTEXT_IMAGEPREVIEW, array())->getPublicUrl(TRUE)
								);
							} else {
								// Icon
								$thumbnails[] = array(
									'name' => htmlspecialchars($fileObject->getName()),
									'image' => IconUtility::getSpriteIconForResource($fileObject, array('title' => $fileObject->getName()))
								);
							}
						} else {
							$rowCopy = array();
							$rowCopy[$field] = $imgPath;
							try {
								$thumbnails[] = array(
									'name' => $imgPath,
									'image' => BackendUtility::thumbCode(
										$rowCopy,
										$table,
										$field,
										$this->formEngine->backPath,
										'thumbs.php',
										$config['uploadfolder'],
										0,
										' align="middle"'
									)
								);
							} catch (\Exception $exception) {
								/** @var $flashMessage FlashMessage */
								$message = $exception->getMessage();
								$flashMessage = GeneralUtility::makeInstance(
									FlashMessage::class,
									htmlspecialchars($message), '', FlashMessage::ERROR, TRUE
								);
								/** @var $flashMessageService \TYPO3\CMS\Core\Messaging\FlashMessageService */
								$flashMessageService = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Messaging\FlashMessageService::class);
								$defaultFlashMessageQueue = $flashMessageService->getMessageQueueByIdentifier();
								$defaultFlashMessageQueue->enqueue($flashMessage);
								$logMessage = $message . ' (' . $table . ':' . $row['uid'] . ')';
								GeneralUtility::sysLog($logMessage, 'core', GeneralUtility::SYSLOG_SEVERITY_WARNING);
							}
						}
					}
				}
				// Creating the element:
				$params = array(
					'size' => $size,
					'allowed' => $allowed,
					'disallowed' => $disallowed,
					'dontShowMoveIcons' => $maxitems <= 1,
					'autoSizeMax' => MathUtility::forceIntegerInRange($config['autoSizeMax'], 0),
					'maxitems' => $maxitems,
					'style' => isset($config['selectedListStyle'])
						? ' style="' . htmlspecialchars($config['selectedListStyle']) . '"'
						: ' style="' . $this->formEngine->defaultMultipleSelectorStyle . '"',
					'thumbnails' => $thumbnails,
					'readOnly' => $disabled,
					'noBrowser' => $noList || isset($config['disable_controls']) && GeneralUtility::inList($config['disable_controls'], 'browser'),
					'noList' => $noList,
					'noDelete' => $noDelete
				);
				$item .= $this->formEngine->dbFileIcons(
					$additionalInformation['itemFormElName'],
					'file',
					implode(',', $allowed),
					$itemArray,
					'',
					$params,
					$additionalInformation['onFocus'],
					'',
					'',
					'',
					$config);
				if (!$disabled && !(isset($config['disable_controls']) && GeneralUtility::inList($config['disable_controls'], 'upload'))) {
					// Adding the upload field:
					if ($this->formEngine->edit_docModuleUpload && $config['uploadfolder']) {
						// Insert the multiple attribute to enable HTML5 multiple file upload
						$multipleAttribute = '';
						$multipleFilenameSuffix = '';
						if (isset($config['maxitems']) && $config['maxitems'] > 1) {
							$multipleAttribute = ' multiple="multiple"';
							$multipleFilenameSuffix = '[]';
						}
						$item .= '
							<div id="' . $additionalInformation['itemFormElID_file'] . '">
								<input type="file"' . $multipleAttribute . '
									name="' . $additionalInformation['itemFormElName_file'] . $multipleFilenameSuffix . '"
									size="35" onchange="' . implode('', $additionalInformation['fieldChangeFunc']) . '"
								/>
							</div>';
					}
				}
				break;
			case 'folder':
				// If the element is of the internal type "folder":
				// Array of folder items:
				$itemArray = GeneralUtility::trimExplode(',', $additionalInformation['itemFormElValue'], TRUE);
				// Creating the element:
				$params = array(
					'size' => $size,
					'dontShowMoveIcons' => $maxitems <= 1,
					'autoSizeMax' => MathUtility::forceIntegerInRange($config['autoSizeMax'], 0),
					'maxitems' => $maxitems,
					'style' => isset($config['selectedListStyle'])
						? ' style="' . htmlspecialchars($config['selectedListStyle']) . '"'
						: ' style="' . $this->formEngine->defaultMultipleSelectorStyle . '"',
					'readOnly' => $disabled,
					'noBrowser' => $noList || isset($config['disable_controls']) && GeneralUtility::inList($config['disable_controls'], 'browser'),
					'noList' => $noList
				);
				$item .= $this->formEngine->dbFileIcons(
					$additionalInformation['itemFormElName'],
					'folder',
					'',
					$itemArray,
					'',
					$params,
					$additionalInformation['onFocus']
				);
				break;
			case 'db':
				// If the element is of the internal type "db":
				// Creating string showing allowed types:
				$onlySingleTableAllowed = FALSE;
				$languageService = $this->getLanguageService();

				if ($allowed[0] === '*') {
					$allowedTables = array(
						'name' => htmlspecialchars($languageService->sL('LLL:EXT:lang/locallang_core.xlf:labels.allTables'))
					);
				} elseif ($allowed) {
					$onlySingleTableAllowed = count($allowed) == 1;
					$allowedTables = array();
					foreach ($allowed as $allowedTable) {
						$allowedTables[] = array(
							'name' => htmlspecialchars($languageService->sL($GLOBALS['TCA'][$allowedTable]['ctrl']['title'])),
							'icon' => IconUtility::getSpriteIconForRecord($allowedTable, array()),
							'onClick' => 'setFormValueOpenBrowser(\'db\', \'' . ($additionalInformation['itemFormElName'] . '|||' . $allowedTable) . '\'); return false;'
						);
					}
				}
				$perms_clause = $this->getBackendUserAuthentication()->getPagePermsClause(1);
				$itemArray = array();
				$imgs = array();

				// Thumbnails:
				$temp_itemArray = GeneralUtility::trimExplode(',', $additionalInformation['itemFormElValue'], TRUE);
				foreach ($temp_itemArray as $dbRead) {
					$recordParts = explode('|', $dbRead);
					list($this_table, $this_uid) = BackendUtility::splitTable_Uid($recordParts[0]);
					// For the case that no table was found and only a single table is defined to be allowed, use that one:
					if (!$this_table && $onlySingleTableAllowed) {
						$this_table = $allowed;
					}
					$itemArray[] = array('table' => $this_table, 'id' => $this_uid);
					if (!$disabled && $show_thumbs) {
						$rr = BackendUtility::getRecordWSOL($this_table, $this_uid);
						$thumbnails[] = array(
							'name' => BackendUtility::getRecordTitle($this_table, $rr, TRUE),
							'image' => IconUtility::getSpriteIconForRecord($this_table, $rr),
							'path' => BackendUtility::getRecordPath($rr['pid'], $perms_clause, 15),
							'uid' => $rr['uid'],
							'table' => $this_table
						);
					}
				}
				// Creating the element:
				$params = array(
					'size' => $size,
					'dontShowMoveIcons' => $maxitems <= 1,
					'autoSizeMax' => MathUtility::forceIntegerInRange($config['autoSizeMax'], 0),
					'maxitems' => $maxitems,
					'style' => isset($config['selectedListStyle'])
						? ' style="' . htmlspecialchars($config['selectedListStyle']) . '"'
						: ' style="' . $this->formEngine->defaultMultipleSelectorStyle . '"',
					'info' => $info,
					'allowedTables' => $allowedTables,
					'thumbnails' => $thumbnails,
					'readOnly' => $disabled,
					'noBrowser' => $noList || isset($config['disable_controls']) && GeneralUtility::inList($config['disable_controls'], 'browser'),
					'noList' => $noList
				);
				$item .= $this->formEngine->dbFileIcons(
					$additionalInformation['itemFormElName'],
					'db',
					implode(',', $allowed),
					$itemArray,
					'',
					$params,
					$additionalInformation['onFocus'],
					$table,
					$field,
					$row['uid'],
					$config
				);
				break;
		}
		// Wizards:
		$altItem = '<input type="hidden" name="' . $additionalInformation['itemFormElName'] . '" value="' . htmlspecialchars($additionalInformation['itemFormElValue']) . '" />';
		if (!$disabled) {
			$item = $this->formEngine->renderWizards(
				array(
					$item,
					$altItem
				),
				$config['wizards'],
				$table,
				$row,
				$field,
				$additionalInformation,
				$additionalInformation['itemFormElName'],
				$specConf
			);
		}
		return $item;
	}

}
