<?php
namespace TYPO3\CMS\Backend\Clipboard;

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
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * TYPO3 clipboard for records and files
 *
 * @author Kasper Skårhøj <kasperYYYY@typo3.com>
 */
class Clipboard {

	/**
	 * @var int
	 */
	public $numberTabs = 3;

	/**
	 * Clipboard data kept here
	 *
	 * Keys:
	 * 'normal'
	 * 'tab_[x]' where x is >=1 and denotes the pad-number
	 * 'mode'	:	'copy' means copy-mode, default = moving ('cut')
	 * 'el'	:	Array of elements:
	 * DB: keys = '[tablename]|[uid]'	eg. 'tt_content:123'
	 * DB: values = 1 (basically insignificant)
	 * FILE: keys = '_FILE|[shortmd5 of path]'	eg. '_FILE|9ebc7e5c74'
	 * FILE: values = The full filepath, eg. '/www/htdocs/typo3/32/dummy/fileadmin/sem1_3_examples/alternative_index.php'
	 * or 'C:/www/htdocs/typo3/32/dummy/fileadmin/sem1_3_examples/alternative_index.php'
	 *
	 * 'current' pointer to current tab (among the above...)
	 * '_setThumb'	boolean: If set, file thumbnails are shown.
	 *
	 * The virtual tablename '_FILE' will always indicate files/folders. When checking for elements from eg. 'all tables'
	 * (by using an empty string) '_FILE' entries are excluded (so in effect only DB elements are counted)
	 *
	 * @var array
	 */
	public $clipData = array();

	/**
	 * @var int
	 */
	public $changed = 0;

	/**
	 * @var string
	 */
	public $current = '';

	/**
	 * @var string
	 */
	public $backPath = '';

	/**
	 * @var int
	 */
	public $lockToNormal = 0;

	/**
	 * If set, clipboard is displaying files.
	 *
	 * @var int
	 */
	public $fileMode = 0;

	/*****************************************
	 *
	 * Initialize
	 *
	 ****************************************/
	/**
	 * Initialize the clipboard from the be_user session
	 *
	 * @return void
	 */
	public function initializeClipboard() {
		$this->backPath = $GLOBALS['BACK_PATH'];
		// Get data
		$clipData = $GLOBALS['BE_USER']->getModuleData('clipboard', $GLOBALS['BE_USER']->getTSConfigVal('options.saveClipboard') ? '' : 'ses');
		// NumberTabs
		$clNP = $GLOBALS['BE_USER']->getTSConfigVal('options.clipboardNumberPads');
		if (MathUtility::canBeInterpretedAsInteger($clNP) && $clNP >= 0) {
			$this->numberTabs = MathUtility::forceIntegerInRange($clNP, 0, 20);
		}
		// Resets/reinstates the clipboard pads
		$this->clipData['normal'] = is_array($clipData['normal']) ? $clipData['normal'] : array();
		for ($a = 1; $a <= $this->numberTabs; $a++) {
			$this->clipData['tab_' . $a] = is_array($clipData['tab_' . $a]) ? $clipData['tab_' . $a] : array();
		}
		// Setting the current pad pointer ($this->current) and _setThumb (which determines whether or not do show file thumbnails)
		$this->clipData['current'] = ($this->current = isset($this->clipData[$clipData['current']]) ? $clipData['current'] : 'normal');
		$this->clipData['_setThumb'] = $clipData['_setThumb'];
	}

	/**
	 * Call this method after initialization if you want to lock the clipboard to operate on the normal pad only.
	 * Trying to switch pad through ->setCmd will not work.
	 * This is used by the clickmenu since it only allows operation on single elements at a time (that is the "normal" pad)
	 *
	 * @return void
	 */
	public function lockToNormal() {
		$this->lockToNormal = 1;
		$this->current = 'normal';
	}

	/**
	 * The array $cmd may hold various keys which notes some action to take.
	 * Normally perform only one action at a time.
	 * In scripts like db_list.php / filelist/mod1/index.php the GET-var CB is used to control the clipboard.
	 *
	 * Selecting / Deselecting elements
	 * Array $cmd['el'] has keys = element-ident, value = element value (see description of clipData array in header)
	 * Selecting elements for 'copy' should be done by simultaneously setting setCopyMode.
	 *
	 * @param array $cmd Array of actions, see function description
	 * @return void
	 */
	public function setCmd($cmd) {
		if (is_array($cmd['el'])) {
			foreach ($cmd['el'] as $k => $v) {
				if ($this->current == 'normal') {
					unset($this->clipData['normal']);
				}
				if ($v) {
					$this->clipData[$this->current]['el'][$k] = $v;
				} else {
					$this->removeElement($k);
				}
				$this->changed = 1;
			}
		}
		// Change clipboard pad (if not locked to normal)
		if ($cmd['setP']) {
			$this->setCurrentPad($cmd['setP']);
		}
		// Remove element	(value = item ident: DB; '[tablename]|[uid]'    FILE: '_FILE|[shortmd5 hash of path]'
		if ($cmd['remove']) {
			$this->removeElement($cmd['remove']);
			$this->changed = 1;
		}
		// Remove all on current pad (value = pad-ident)
		if ($cmd['removeAll']) {
			$this->clipData[$cmd['removeAll']] = array();
			$this->changed = 1;
		}
		// Set copy mode of the tab
		if (isset($cmd['setCopyMode'])) {
			$this->clipData[$this->current]['mode'] = $this->isElements() ? ($cmd['setCopyMode'] ? 'copy' : '') : '';
			$this->changed = 1;
		}
		// Toggle thumbnail display for files on/off
		if (isset($cmd['setThumb'])) {
			$this->clipData['_setThumb'] = $cmd['setThumb'];
			$this->changed = 1;
		}
	}

	/**
	 * Setting the current pad on clipboard
	 *
	 * @param string $padIdent Key in the array $this->clipData
	 * @return void
	 */
	public function setCurrentPad($padIdent) {
		// Change clipboard pad (if not locked to normal)
		if (!$this->lockToNormal && $this->current != $padIdent) {
			if (isset($this->clipData[$padIdent])) {
				$this->clipData['current'] = ($this->current = $padIdent);
			}
			if ($this->current != 'normal' || !$this->isElements()) {
				$this->clipData[$this->current]['mode'] = '';
			}
			// Setting mode to default (move) if no items on it or if not 'normal'
			$this->changed = 1;
		}
	}

	/**
	 * Call this after initialization and setCmd in order to save the clipboard to the user session.
	 * The function will check if the internal flag ->changed has been set and if so, save the clipboard. Else not.
	 *
	 * @return void
	 */
	public function endClipboard() {
		if ($this->changed) {
			$this->saveClipboard();
		}
		$this->changed = 0;
	}

	/**
	 * Cleans up an incoming element array $CBarr (Array selecting/deselecting elements)
	 *
	 * @param array $CBarr Element array from outside ("key" => "selected/deselected")
	 * @param string $table The 'table which is allowed'. Must be set.
	 * @param bool $removeDeselected Can be set in order to remove entries which are marked for deselection.
	 * @return array Processed input $CBarr
	 */
	public function cleanUpCBC($CBarr, $table, $removeDeselected = 0) {
		if (is_array($CBarr)) {
			foreach ($CBarr as $k => $v) {
				$p = explode('|', $k);
				if ((string)$p[0] != (string)$table || $removeDeselected && !$v) {
					unset($CBarr[$k]);
				}
			}
		}
		return $CBarr;
	}

	/*****************************************
	 *
	 * Clipboard HTML renderings
	 *
	 ****************************************/
	/**
	 * Prints the clipboard
	 *
	 * @return string HTML output
	 */
	public function printClipboard() {
		$out = array();
		$elCount = count($this->elFromTable($this->fileMode ? '_FILE' : ''));
		// Button/menu header:
		$thumb_url = GeneralUtility::linkThisScript(array('CB' => array('setThumb' => $this->clipData['_setThumb'] ? 0 : 1)));
		$rmall_url = GeneralUtility::linkThisScript(array('CB' => array('removeAll' => $this->current)));
		// Copymode Selector menu
		$copymode_url = GeneralUtility::linkThisScript();
		$moveLabel = htmlspecialchars($GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_misc.xlf:moveElements'));
		$copyLabel = htmlspecialchars($GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_misc.xlf:copyElements'));
		$opt = array();
		$opt[] = '<option style="padding-left: 20px; background-image: url(\'' . IconUtility::skinImg($this->backPath, 'gfx/clip_cut.gif', '', 1) . '\'); background-repeat: no-repeat;" value="" ' . ($this->currentMode() == 'copy' ? '' : 'selected="selected"') . '>' . $moveLabel . '</option>';
		$opt[] = '<option style="padding-left: 20px; background-image: url(\'' . IconUtility::skinImg($this->backPath, 'gfx/clip_copy.gif', '', 1) . '\'); background-repeat: no-repeat;" value="1" ' . ($this->currentMode() == 'copy' ? 'selected="selected"' : '') . '>' . $copyLabel . '</option>';
		$copymode_selector = ' <select name="CB[setCopyMode]" onchange="this.form.method=\'POST\'; this.form.action=\'' . htmlspecialchars(($copymode_url . '&CB[setCopyMode]=')) . '\'+(this.options[this.selectedIndex].value); this.form.submit(); return true;" >' . implode('', $opt) . '</select>';
		// Selector menu + clear button
		$opt = array();
		$opt[] = '<option value="" selected="selected">' . $this->clLabel('menu', 'rm') . '</option>';
		// Import / Export link:
		if ($elCount && ExtensionManagementUtility::isLoaded('impexp')) {
			$url = BackendUtility::getModuleUrl('xMOD_tximpexp', $this->exportClipElementParameters());
			$opt[] = '<option value="' . htmlspecialchars(('window.location.href=' . GeneralUtility::quoteJSvalue($url) . ';')) . '">' . $this->clLabel('export', 'rm') . '</option>';
		}
		// Edit:
		if (!$this->fileMode && $elCount) {
			$opt[] = '<option value="' . htmlspecialchars(('window.location.href=\'' . $this->editUrl() . '&returnUrl=\'+top.rawurlencode(window.location.href);')) . '">' . $this->clLabel('edit', 'rm') . '</option>';
		}
		$deleteLink = '';
		// Delete:
		if ($elCount) {
			$deleteLink = '<a class="btn btn-danger" href="' . htmlspecialchars($rmall_url) . '#clip_head">' . IconUtility::getSpriteIcon('actions-document-close', array('title' => $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:buttons.clear', TRUE))) . '</a>';
			if ($GLOBALS['BE_USER']->jsConfirmation(4)) {
				$js = '
			if (confirm(' . GeneralUtility::quoteJSvalue(sprintf($GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:mess.deleteClip'), $elCount)) . ')){
				window.location.href=\'' . $this->deleteUrl(0, ($this->fileMode ? 1 : 0)) . '&redirect=\'+top.rawurlencode(window.location.href);
			}
					';
			} else {
				$js = ' window.location.href=\'' . $this->deleteUrl(0, ($this->fileMode ? 1 : 0)) . '&redirect=\'+top.rawurlencode(window.location.href); ';
			}
			$opt[] = '<option value="' . htmlspecialchars($js) . '">' . $this->clLabel('delete', 'rm') . '</option>';
		}
		$selector_menu = '<select name="_clipMenu" onchange="eval(this.options[this.selectedIndex].value);this.selectedIndex=0;">' . implode('', $opt) . '</select>';
		$out[] = '
			<tr>
				<td nowrap="nowrap" class="col-icon">' . '<a href="' . htmlspecialchars($thumb_url) . '#clip_head">' . '<img' . IconUtility::skinImg($this->backPath, ('gfx/thumb_' . ($this->clipData['_setThumb'] ? 's' : 'n') . '.gif'), 'width="21" height="16"') . ' vspace="2" border="0" title="' . $this->clLabel('thumbmode_clip') . '" alt="" />' . '</a>' . '</td>
				<td nowrap="nowrap" width="95%">' . $copymode_selector . ' ' . $selector_menu . '</td>
				<td nowrap="nowrap" class="col-control">' . $deleteLink . '</td>
			</tr>';
		// Print header and content for the NORMAL tab:
		$out[] = '
			<tr>
				<td colspan="3"><a href="' . htmlspecialchars(GeneralUtility::linkThisScript(array('CB' => array('setP' => 'normal')))) . '#clip_head" title="' . $this->clLabel('normal-description') . '">'
					. IconUtility::getSpriteIcon(('actions-view-table-' . ($this->current == 'normal' ? 'collapse' : 'expand')))
					. $this->padTitleWrap($this->clLabel('normal'), 'normal')
					. '</a></td>
			</tr>';
		if ($this->current == 'normal') {
			$out = array_merge($out, $this->printContentFromTab('normal'));
		}
		// Print header and content for the NUMERIC tabs:
		for ($a = 1; $a <= $this->numberTabs; $a++) {
			$out[] = '
				<tr>
					<td colspan="3"><a href="' . htmlspecialchars(GeneralUtility::linkThisScript(array('CB' => array('setP' => ('tab_' . $a))))) . '#clip_head" title="' . $this->clLabel('cliptabs-description') . '">'
						. IconUtility::getSpriteIcon(('actions-view-table-' . ($this->current == 'tab_' . $a ? 'collapse' : 'expand')))
						. $this->padTitleWrap(sprintf($this->clLabel('cliptabs-name'), $a), ('tab_' . $a))
						. '</a></td>
				</tr>';
			if ($this->current == 'tab_' . $a) {
				$out = array_merge($out, $this->printContentFromTab('tab_' . $a));
			}
		}
		// Wrap accumulated rows in a table:
		$output = '<a name="clip_head"></a>

			<!--
				TYPO3 Clipboard:
			-->
			<div class="row">
				<div class="col-sm-6">
					<div class="panel panel-default">
						<div class="panel-heading">' . BackendUtility::wrapInHelp('xMOD_csh_corebe', 'list_clipboard', $this->clLabel('clipboard', 'buttons')) . '</div>
						<table class="table">
							' . implode('', $out) . '
						</table>
					</div>
				</div>
			</div>
		';
		// Wrap in form tag:
		$output = '<form action="">' . $output . '</form>';
		// Return the accumulated content:
		return $output;
	}

	/**
	 * Print the content on a pad. Called from ->printClipboard()
	 *
	 * @access private
	 * @param string $pad Pad reference
	 * @return array Array with table rows for the clipboard.
	 */
	public function printContentFromTab($pad) {
		$lines = array();
		if (is_array($this->clipData[$pad]['el'])) {
			foreach ($this->clipData[$pad]['el'] as $k => $v) {
				if ($v) {
					list($table, $uid) = explode('|', $k);
					$bgColClass = $table == '_FILE' && $this->fileMode || $table != '_FILE' && !$this->fileMode ? 'bgColor4-20' : 'bgColor4';
					// Rendering files/directories on the clipboard
					if ($table == '_FILE') {
						$fileObject = ResourceFactory::getInstance()->retrieveFileOrFolderObject($v);
						if ($fileObject) {
							$thumb = '';
							$folder = $fileObject instanceof \TYPO3\CMS\Core\Resource\Folder;
							$size = $folder ? '' : '(' . GeneralUtility::formatSize($fileObject->getSize()) . 'bytes)';
							$icon = IconUtility::getSpriteIconForResource($fileObject, array('style' => 'margin: 0 20px;', 'title' => $fileObject->getName() . ' ' . $size));
							if (!$folder && $this->clipData['_setThumb'] && GeneralUtility::inList($GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'], $fileObject->getExtension())) {
								$processedFile = $fileObject->process(\TYPO3\CMS\Core\Resource\ProcessedFile::CONTEXT_IMAGEPREVIEW, array());
								if ($processedFile) {
									$thumbUrl = $processedFile->getPublicUrl(TRUE);
									$thumb .= '<br /><img src="' . htmlspecialchars($thumbUrl) . '" title="' . htmlspecialchars($fileObject->getName()) . '" alt="" />';
								}
							}
							$lines[] = '
								<tr>
									<td nowrap="nowrap" class="col-icon">' . $icon . '</td>
									<td nowrap="nowrap" width="95%">' . $this->linkItemText(htmlspecialchars(GeneralUtility::fixed_lgd_cs($fileObject->getName(), $GLOBALS['BE_USER']->uc['titleLen'])), $fileObject->getName()) . ($pad == 'normal' ? ' <strong>(' . ($this->clipData['normal']['mode'] == 'copy' ? $this->clLabel('copy', 'cm') : $this->clLabel('cut', 'cm')) . ')</strong>' : '') . '&nbsp;' . $thumb . '</td>
									<td nowrap="nowrap" class="col-control">
										<div class="btn-group">
											<a class="btn btn-default" href="#" onclick="' . htmlspecialchars(('top.launchView(\'' . $table . '\', \'' . $v . '\'); return false;')) . '">' . IconUtility::getSpriteIcon('actions-document-info', array('title' => $this->clLabel('info', 'cm'))) . '</a>' . '<a class="btn btn-default" href="' . htmlspecialchars($this->removeUrl('_FILE', GeneralUtility::shortmd5($v))) . '#clip_head">' . IconUtility::getSpriteIcon('actions-selection-delete', array('title' => $this->clLabel('removeItem'))) . '</a>
										</div>
									</td>
								</tr>';
						} else {
							// If the file did not exist (or is illegal) then it is removed from the clipboard immediately:
							unset($this->clipData[$pad]['el'][$k]);
							$this->changed = 1;
						}
					} else {
						// Rendering records:
						$rec = BackendUtility::getRecordWSOL($table, $uid);
						if (is_array($rec)) {
							$lines[] = '
								<tr>
									<td nowrap="nowrap" class="col-icon">' . $this->linkItemText(IconUtility::getSpriteIconForRecord($table, $rec, array('style' => 'margin: 0 20px;', 'title' => htmlspecialchars(BackendUtility::getRecordIconAltText($rec, $table)))), $rec, $table) . '</td>
									<td nowrap="nowrap" width="95%">' . $this->linkItemText(htmlspecialchars(GeneralUtility::fixed_lgd_cs(BackendUtility::getRecordTitle($table, $rec), $GLOBALS['BE_USER']->uc['titleLen'])), $rec, $table) . ($pad == 'normal' ? ' <strong>(' . ($this->clipData['normal']['mode'] == 'copy' ? $this->clLabel('copy', 'cm') : $this->clLabel('cut', 'cm')) . ')</strong>' : '') . '&nbsp;</td>
									<td nowrap="nowrap" class="col-control">
										<div class="btn-group">
											<a class="btn btn-default" href="#" onclick="' . htmlspecialchars(('top.launchView(\'' . $table . '\', \'' . (int)$uid . '\'); return false;')) . '">' . IconUtility::getSpriteIcon('actions-document-info', array('title' => $this->clLabel('info', 'cm'))) . '</a>' . '<a class="btn btn-default" href="' . htmlspecialchars($this->removeUrl($table, $uid)) . '#clip_head">' . IconUtility::getSpriteIcon('actions-selection-delete', array('title' => $this->clLabel('removeItem'))) . '</a>
										</div>
									</td>
								</tr>';
							$localizationData = $this->getLocalizations($table, $rec, $bgColClass, $pad);
							if ($localizationData) {
								$lines[] = $localizationData;
							}
						} else {
							unset($this->clipData[$pad]['el'][$k]);
							$this->changed = 1;
						}
					}
				}
			}
		}
		if (!count($lines)) {
			$lines[] = '
								<tr>
									<td class="col-icon"></td>
									<td colspan="2 nowrap="nowrap" width="95%"><em>(' . $this->clLabel('clipNoEl') . ')</em>&nbsp;</td>
								</tr>';
		}
		$this->endClipboard();
		return $lines;
	}

	/**
	 * Returns true if the clipboard contains elements
	 *
	 * @return bool
	 */
	public function hasElements() {
		foreach ($this->clipData as $data) {
			if (isset($data['el']) && is_array($data['el']) && !empty($data['el'])) {
				return TRUE;
			}
		}

		return FALSE;
	}

	/**
	 * Gets all localizations of the current record.
	 *
	 * @param string $table The table
	 * @param array $parentRec The current record
	 * @param string $bgColClass Class for the background color of a column
	 * @param string $pad Pad reference
	 * @return string HTML table rows
	 */
	public function getLocalizations($table, $parentRec, $bgColClass, $pad) {
		$lines = array();
		$tcaCtrl = $GLOBALS['TCA'][$table]['ctrl'];
		if ($table != 'pages' && BackendUtility::isTableLocalizable($table) && !$tcaCtrl['transOrigPointerTable']) {
			$where = array();
			$where[] = $tcaCtrl['transOrigPointerField'] . '=' . (int)$parentRec['uid'];
			$where[] = $tcaCtrl['languageField'] . '<>0';
			if (isset($tcaCtrl['delete']) && $tcaCtrl['delete']) {
				$where[] = $tcaCtrl['delete'] . '=0';
			}
			if (isset($tcaCtrl['versioningWS']) && $tcaCtrl['versioningWS']) {
				$where[] = 't3ver_wsid=' . $parentRec['t3ver_wsid'];
			}
			$rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('*', $table, implode(' AND ', $where));
			if (is_array($rows)) {
				$modeData = '';
				if ($pad == 'normal') {
					$mode = $this->clipData['normal']['mode'] == 'copy' ? 'copy' : 'cut';
					$modeData = ' <strong>(' . $this->clLabel($mode, 'cm') . ')</strong>';
				}
				foreach ($rows as $rec) {
					$lines[] = '
					<tr>
						<td nowrap="nowrap" class="col-icon">' . IconUtility::getSpriteIconForRecord($table, $rec, array('style' => 'margin-left: 38px;')) . '</td>
						<td nowrap="nowrap" width="95%">' . htmlspecialchars(GeneralUtility::fixed_lgd_cs(BackendUtility::getRecordTitle($table, $rec), $GLOBALS['BE_USER']->uc['titleLen'])) . $modeData . '</td>
						<td nowrap="nowrap" class="col-control"></td>
					</tr>';
				}
			}
		}
		return implode('', $lines);
	}

	/**
	 * Wraps title of pad in bold-tags and maybe the number of elements if any.
	 *
	 * @param string $str String (already htmlspecialchars()'ed)
	 * @param string $pad Pad reference
	 * @return string HTML output (htmlspecialchar'ed content inside of tags.)
	 */
	public function padTitleWrap($str, $pad) {
		$el = count($this->elFromTable($this->fileMode ? '_FILE' : '', $pad));
		if ($el) {
			return '<strong>' . $str . '</strong> (' . ($pad == 'normal' ? ($this->clipData['normal']['mode'] == 'copy' ? $this->clLabel('copy', 'cm') : $this->clLabel('cut', 'cm')) : htmlspecialchars($el)) . ')';
		} else {
			return '<span class="text-muted">' . $str . '</span>';
		}
	}

	/**
	 * Wraps the title of the items listed in link-tags. The items will link to the page/folder where they originate from
	 *
	 * @param string $str Title of element - must be htmlspecialchar'ed on beforehand.
	 * @param mixed $rec If array, a record is expected. If string, its a path
	 * @param string $table Table name
	 * @return string
	 */
	public function linkItemText($str, $rec, $table = '') {
		if (is_array($rec) && $table) {
			if ($this->fileMode) {
				$str = '<span class="text-muted">' . $str . '</span>';
			} else {
				$str = '<a href="' . htmlspecialchars(BackendUtility::getModuleUrl('web_list', array('id' => $rec['pid']), $this->backPath)) . '">' . $str . '</a>';
			}
		} elseif (file_exists($rec)) {
			if (!$this->fileMode) {
				$str = '<span class="text-muted">' . $str . '</span>';
			} else {
				if (ExtensionManagementUtility::isLoaded('filelist')) {
					$str = '<a href="' . htmlspecialchars(($this->backPath . BackendUtility::getModuleUrl('file_list') . '&id=' . dirname($rec))) . '">' . $str . '</a>';
				}
			}
		}
		return $str;
	}

	/**
	 * Returns the select-url for database elements
	 *
	 * @param string $table Table name
	 * @param int $uid Uid of record
	 * @param bool $copy If set, copymode will be enabled
	 * @param bool $deselect If set, the link will deselect, otherwise select.
	 * @param array $baseArray The base array of GET vars to be sent in addition. Notice that current GET vars WILL automatically be included.
	 * @return string URL linking to the current script but with the CB array set to select the element with table/uid
	 */
	public function selUrlDB($table, $uid, $copy = 0, $deselect = 0, $baseArray = array()) {
		$CB = array('el' => array(rawurlencode($table . '|' . $uid) => $deselect ? 0 : 1));
		if ($copy) {
			$CB['setCopyMode'] = 1;
		}
		$baseArray['CB'] = $CB;
		return GeneralUtility::linkThisScript($baseArray);
	}

	/**
	 * Returns the select-url for files
	 *
	 * @param string $path Filepath
	 * @param bool $copy If set, copymode will be enabled
	 * @param bool $deselect If set, the link will deselect, otherwise select.
	 * @param array $baseArray The base array of GET vars to be sent in addition. Notice that current GET vars WILL automatically be included.
	 * @return string URL linking to the current script but with the CB array set to select the path
	 */
	public function selUrlFile($path, $copy = 0, $deselect = 0, $baseArray = array()) {
		$CB = array('el' => array(rawurlencode('_FILE|' . GeneralUtility::shortmd5($path)) => $deselect ? '' : $path));
		if ($copy) {
			$CB['setCopyMode'] = 1;
		}
		$baseArray['CB'] = $CB;
		return GeneralUtility::linkThisScript($baseArray);
	}

	/**
	 * pasteUrl of the element (database and file)
	 * For the meaning of $table and $uid, please read from ->makePasteCmdArray!!!
	 * The URL will point to tce_file or tce_db depending in $table
	 *
	 * @param string $table Tablename (_FILE for files)
	 * @param mixed $uid "destination": can be positive or negative indicating how the paste is done (paste into / paste after)
	 * @param bool $setRedirect If set, then the redirect URL will point back to the current script, but with CB reset.
	 * @param array|NULL $update Additional key/value pairs which should get set in the moved/copied record (via DataHandler)
	 * @return string
	 */
	public function pasteUrl($table, $uid, $setRedirect = TRUE, array $update = NULL) {
		return ($table == '_FILE' ? BackendUtility::getModuleUrl('tce_file', array(), $this->backPath) : BackendUtility::getModuleUrl('tce_db', array(), $this->backPath)) .
			($setRedirect ? '&redirect=' . rawurlencode(GeneralUtility::linkThisScript(array('CB' => ''))) : '') .
			'&vC=' . $GLOBALS['BE_USER']->veriCode() .
			'&prErr=1&uPT=1' .
			'&CB[paste]=' . rawurlencode($table . '|' . $uid) .
			'&CB[pad]=' . $this->current .
			(is_array($update) ? GeneralUtility::implodeArrayForUrl('CB[update]', $update) : '') .
			BackendUtility::getUrlToken('tceAction');
	}

	/**
	 * deleteUrl for current pad
	 *
	 * @param bool $setRedirect If set, then the redirect URL will point back to the current script, but with CB reset.
	 * @param bool $file If set, then the URL will link to the tce_file.php script in the typo3/ dir.
	 * @return string
	 */
	public function deleteUrl($setRedirect = 1, $file = 0) {
		return ($file ? BackendUtility::getModuleUrl('tce_file', array(), $this->backPath) : BackendUtility::getModuleUrl('tce_db', array(), $this->backPath))
			. ($setRedirect ? '&redirect=' . rawurlencode(GeneralUtility::linkThisScript(array('CB' => ''))) : '') . '&vC=' . $GLOBALS['BE_USER']->veriCode()
			. '&prErr=1&uPT=1' . '&CB[delete]=1' . '&CB[pad]=' . $this->current . BackendUtility::getUrlToken('tceAction');
	}

	/**
	 * editUrl of all current elements
	 * ONLY database
	 * Links to alt_doc.php
	 *
	 * @return string The URL to alt_doc.php with parameters.
	 */
	public function editUrl() {
		// All records
		$elements = $this->elFromTable('');
		$editCMDArray = array();
		foreach ($elements as $tP => $value) {
			list($table, $uid) = explode('|', $tP);
			$editCMDArray[] = '&edit[' . $table . '][' . $uid . ']=edit';
		}
		$rU = $this->backPath . 'alt_doc.php?' . implode('', $editCMDArray);
		return $rU;
	}

	/**
	 * Returns the remove-url (file and db)
	 * for file $table='_FILE' and $uid = shortmd5 hash of path
	 *
	 * @param string $table Tablename
	 * @param string $uid Uid integer/shortmd5 hash
	 * @return string URL
	 */
	public function removeUrl($table, $uid) {
		return GeneralUtility::linkThisScript(array('CB' => array('remove' => $table . '|' . $uid)));
	}

	/**
	 * Returns confirm JavaScript message
	 *
	 * @param string $table Table name
	 * @param mixed $rec For records its an array, for files its a string (path)
	 * @param string $type Type-code
	 * @param array $clElements Array of selected elements
	 * @param string $columnLabel Name of the content column
	 * @return string JavaScript "confirm" message
	 */
	public function confirmMsg($table, $rec, $type, $clElements, $columnLabel = '') {
		if ($GLOBALS['BE_USER']->jsConfirmation(2)) {
			$labelKey = 'LLL:EXT:lang/locallang_core.xlf:mess.' . ($this->currentMode() == 'copy' ? 'copy' : 'move') . ($this->current == 'normal' ? '' : 'cb') . '_' . $type;
			$msg = $GLOBALS['LANG']->sL($labelKey . ($columnLabel ? '_colPos': ''));
			if ($table == '_FILE') {
				$thisRecTitle = basename($rec);
				if ($this->current == 'normal') {
					$selItem = reset($clElements);
					$selRecTitle = basename($selItem);
				} else {
					$selRecTitle = count($clElements);
				}
			} else {
				$thisRecTitle = $table == 'pages' && !is_array($rec) ? $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'] : BackendUtility::getRecordTitle($table, $rec);
				if ($this->current == 'normal') {
					$selItem = $this->getSelectedRecord();
					$selRecTitle = $selItem['_RECORD_TITLE'];
				} else {
					$selRecTitle = count($clElements);
				}
			}
			// @TODO
			// This can get removed as soon as the "_colPos" label is translated
			// into all available locallang languages.
			if (!$msg && $columnLabel) {
				$thisRecTitle .= ' | ' . $columnLabel;
				$msg = $GLOBALS['LANG']->sL($labelKey);
			}

			// Message
			$conf = 'confirm(' . GeneralUtility::quoteJSvalue(sprintf($msg, GeneralUtility::fixed_lgd_cs($selRecTitle, 30), GeneralUtility::fixed_lgd_cs($thisRecTitle, 30), GeneralUtility::fixed_lgd_cs($columnLabel, 30))) . ')';
		} else {
			$conf = '';
		}
		return $conf;
	}

	/**
	 * Clipboard label - getting from "EXT:lang/locallang_core.xlf:"
	 *
	 * @param string $key Label Key
	 * @param string $Akey Alternative key to "labels
	 * @return string
	 */
	public function clLabel($key, $Akey = 'labels') {
		return htmlspecialchars($GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:' . $Akey . '.' . $key));
	}

	/**
	 * Creates GET parameters for linking to the export module.
	 *
	 * @return array GET parameters for current clipboard content to be exported
	 */
	protected function exportClipElementParameters() {
		// Init
		$pad = $this->current;
		$params = array();
		$params['tx_impexp[action]'] = 'export';
		// Traverse items:
		if (is_array($this->clipData[$pad]['el'])) {
			foreach ($this->clipData[$pad]['el'] as $k => $v) {
				if ($v) {
					list($table, $uid) = explode('|', $k);
					// Rendering files/directories on the clipboard
					if ($table == '_FILE') {
						if (file_exists($v) && GeneralUtility::isAllowedAbsPath($v)) {
							$params['tx_impexp[' . (is_dir($v) ? 'dir' : 'file') . '][]'] = $v;
						}
					} else {
						// Rendering records:
						$rec = BackendUtility::getRecord($table, $uid);
						if (is_array($rec)) {
							$params['tx_impexp[record][]'] = $table . ':' . $uid;
						}
					}
				}
			}
		}
		return $params;
	}

	/*****************************************
	 *
	 * Helper functions
	 *
	 ****************************************/
	/**
	 * Removes element on clipboard
	 *
	 * @param string $el Key of element in ->clipData array
	 * @return void
	 */
	public function removeElement($el) {
		unset($this->clipData[$this->current]['el'][$el]);
		$this->changed = 1;
	}

	/**
	 * Saves the clipboard, no questions asked.
	 * Use ->endClipboard normally (as it checks if changes has been done so saving is necessary)
	 *
	 * @access private
	 * @return void
	 */
	public function saveClipboard() {
		$GLOBALS['BE_USER']->pushModuleData('clipboard', $this->clipData);
	}

	/**
	 * Returns the current mode, 'copy' or 'cut'
	 *
	 * @return string "copy" or "cut
	 */
	public function currentMode() {
		return $this->clipData[$this->current]['mode'] == 'copy' ? 'copy' : 'cut';
	}

	/**
	 * This traverses the elements on the current clipboard pane
	 * and unsets elements which does not exist anymore or are disabled.
	 *
	 * @return void
	 */
	public function cleanCurrent() {
		if (is_array($this->clipData[$this->current]['el'])) {
			foreach ($this->clipData[$this->current]['el'] as $k => $v) {
				list($table, $uid) = explode('|', $k);
				if ($table != '_FILE') {
					if (!$v || !is_array(BackendUtility::getRecord($table, $uid, 'uid'))) {
						unset($this->clipData[$this->current]['el'][$k]);
						$this->changed = 1;
					}
				} else {
					if (!$v) {
						unset($this->clipData[$this->current]['el'][$k]);
						$this->changed = 1;
					} else {
						try {
							ResourceFactory::getInstance()->retrieveFileOrFolderObject($v);
						} catch (\TYPO3\CMS\Core\Resource\Exception\ResourceDoesNotExistException $e) {
							// The file has been deleted in the meantime, so just remove it silently
							unset($this->clipData[$this->current]['el'][$k]);
						}
					}
				}
			}
		}
	}

	/**
	 * Counts the number of elements from the table $matchTable. If $matchTable is blank, all tables (except '_FILE' of course) is counted.
	 *
	 * @param string $matchTable Table to match/count for.
	 * @param string $pad Can optionally be used to set another pad than the current.
	 * @return array Array with keys from the CB.
	 */
	public function elFromTable($matchTable = '', $pad = '') {
		$pad = $pad ? $pad : $this->current;
		$list = array();
		if (is_array($this->clipData[$pad]['el'])) {
			foreach ($this->clipData[$pad]['el'] as $k => $v) {
				if ($v) {
					list($table, $uid) = explode('|', $k);
					if ($table != '_FILE') {
						if ((!$matchTable || (string)$table == (string)$matchTable) && $GLOBALS['TCA'][$table]) {
							$list[$k] = $pad == 'normal' ? $v : $uid;
						}
					} else {
						if ((string)$table == (string)$matchTable) {
							$list[$k] = $v;
						}
					}
				}
			}
		}
		return $list;
	}

	/**
	 * Verifies if the item $table/$uid is on the current pad.
	 * If the pad is "normal", the mode value is returned if the element existed. Thus you'll know if the item was copy or cut moded...
	 *
	 * @param string $table Table name, (_FILE for files...)
	 * @param int $uid Element uid (path for files)
	 * @return string
	 */
	public function isSelected($table, $uid) {
		$k = $table . '|' . $uid;
		return $this->clipData[$this->current]['el'][$k] ? ($this->current == 'normal' ? $this->currentMode() : 1) : '';
	}

	/**
	 * Returns item record $table,$uid if selected on current clipboard
	 * If table and uid is blank, the first element is returned.
	 * Makes sense only for DB records - not files!
	 *
	 * @param string $table Table name
	 * @param int $uid Element uid
	 * @return array Element record with extra field _RECORD_TITLE set to the title of the record
	 */
	public function getSelectedRecord($table = '', $uid = '') {
		if (!$table && !$uid) {
			$elArr = $this->elFromTable('');
			reset($elArr);
			list($table, $uid) = explode('|', key($elArr));
		}
		if ($this->isSelected($table, $uid)) {
			$selRec = BackendUtility::getRecordWSOL($table, $uid);
			$selRec['_RECORD_TITLE'] = BackendUtility::getRecordTitle($table, $selRec);
			return $selRec;
		}
	}

	/**
	 * Reports if the current pad has elements (does not check file/DB type OR if file/DBrecord exists or not. Only counting array)
	 *
	 * @return bool TRUE if elements exist.
	 */
	public function isElements() {
		return is_array($this->clipData[$this->current]['el']) && count($this->clipData[$this->current]['el']);
	}

	/*****************************************
	 *
	 * FOR USE IN tce_db.php:
	 *
	 ****************************************/
	/**
	 * Applies the proper paste configuration in the $cmd array send to tce_db.php.
	 * $ref is the target, see description below.
	 * The current pad is pasted
	 *
	 * $ref: [tablename]:[paste-uid].
	 * Tablename is the name of the table from which elements *on the current clipboard* is pasted with the 'pid' paste-uid.
	 * No tablename means that all items on the clipboard (non-files) are pasted. This requires paste-uid to be positive though.
	 * so 'tt_content:-3'	means 'paste tt_content elements on the clipboard to AFTER tt_content:3 record
	 * 'tt_content:30'	means 'paste tt_content elements on the clipboard into page with id 30
	 * ':30'	means 'paste ALL database elements on the clipboard into page with id 30
	 * ':-30'	not valid.
	 *
	 * @param string $ref [tablename]:[paste-uid], see description
	 * @param array $CMD Command-array
	 * @param NULL|array If additional values should get set in the copied/moved record this will be an array containing key=>value pairs
	 * @return array Modified Command-array
	 */
	public function makePasteCmdArray($ref, $CMD, array $update = NULL) {
		list($pTable, $pUid) = explode('|', $ref);
		$pUid = (int)$pUid;
		// pUid must be set and if pTable is not set (that means paste ALL elements)
		// the uid MUST be positive/zero (pointing to page id)
		if ($pTable || $pUid >= 0) {
			$elements = $this->elFromTable($pTable);
			// So the order is preserved.
			$elements = array_reverse($elements);
			$mode = $this->currentMode() == 'copy' ? 'copy' : 'move';
			// Traverse elements and make CMD array
			foreach ($elements as $tP => $value) {
				list($table, $uid) = explode('|', $tP);
				if (!is_array($CMD[$table])) {
					$CMD[$table] = array();
				}
				if (is_array($update)) {
					$CMD[$table][$uid][$mode] = array(
						'action' => 'paste',
						'target' => $pUid,
						'update' => $update,
					);
				} else {
					$CMD[$table][$uid][$mode] = $pUid;
				}
				if ($mode == 'move') {
					$this->removeElement($tP);
				}
			}
			$this->endClipboard();
		}
		return $CMD;
	}

	/**
	 * Delete record entries in CMD array
	 *
	 * @param array $CMD Command-array
	 * @return array Modified Command-array
	 */
	public function makeDeleteCmdArray($CMD) {
		// all records
		$elements = $this->elFromTable('');
		foreach ($elements as $tP => $value) {
			list($table, $uid) = explode('|', $tP);
			if (!is_array($CMD[$table])) {
				$CMD[$table] = array();
			}
			$CMD[$table][$uid]['delete'] = 1;
			$this->removeElement($tP);
		}
		$this->endClipboard();
		return $CMD;
	}

	/*****************************************
	 *
	 * FOR USE IN tce_file.php:
	 *
	 ****************************************/
	/**
	 * Applies the proper paste configuration in the $file array send to tce_file.php.
	 * The current pad is pasted
	 *
	 * @param string $ref Reference to element (splitted by "|")
	 * @param array $FILE Command-array
	 * @return array Modified Command-array
	 */
	public function makePasteCmdArray_file($ref, $FILE) {
		list($pTable, $pUid) = explode('|', $ref);
		$elements = $this->elFromTable('_FILE');
		$mode = $this->currentMode() == 'copy' ? 'copy' : 'move';
		// Traverse elements and make CMD array
		foreach ($elements as $tP => $path) {
			$FILE[$mode][] = array('data' => $path, 'target' => $pUid);
			if ($mode == 'move') {
				$this->removeElement($tP);
			}
		}
		$this->endClipboard();
		return $FILE;
	}

	/**
	 * Delete files in CMD array
	 *
	 * @param array $FILE Command-array
	 * @return array Modified Command-array
	 */
	public function makeDeleteCmdArray_file($FILE) {
		$elements = $this->elFromTable('_FILE');
		// Traverse elements and make CMD array
		foreach ($elements as $tP => $path) {
			$FILE['delete'][] = array('data' => $path);
			$this->removeElement($tP);
		}
		$this->endClipboard();
		return $FILE;
	}

}
