<?php
namespace TYPO3\CMS\InfoPagetsconfig\Controller;

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
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * Page TSconfig viewer
 *
 * @author Kasper Skårhøj <kasperYYYY@typo3.com>
 */
class InfoPageTyposcriptConfigController extends \TYPO3\CMS\Backend\Module\AbstractFunctionModule {

	/**
	 * Constructor
	 */
	public function __construct() {
		$GLOBALS['LANG']->includeLLFile('EXT:info_pagetsconfig/locallang.xlf');
	}

	/**
	 * Function menu initialization
	 *
	 * @return array Menu array
	 */
	public function modMenu() {
		$modMenuAdd = array(
			'tsconf_parts' => array(
				0 => $GLOBALS['LANG']->getLL('tsconf_parts_0'),
				1 => $GLOBALS['LANG']->getLL('tsconf_parts_1'),
				'1a' => $GLOBALS['LANG']->getLL('tsconf_parts_1a'),
				'1b' => $GLOBALS['LANG']->getLL('tsconf_parts_1b'),
				'1c' => $GLOBALS['LANG']->getLL('tsconf_parts_1c'),
				'1d' => $GLOBALS['LANG']->getLL('tsconf_parts_1d'),
				'1e' => $GLOBALS['LANG']->getLL('tsconf_parts_1e'),
				'1f' => $GLOBALS['LANG']->getLL('tsconf_parts_1f'),
				'1g' => $GLOBALS['LANG']->getLL('tsconf_parts_1g'),
				2 => 'RTE.',
				5 => 'TCEFORM.',
				6 => 'TCEMAIN.',
				3 => 'TSFE.',
				4 => 'user.',
				99 => $GLOBALS['LANG']->getLL('tsconf_configFields')
			),
			'tsconf_alphaSort' => '1'
		);
		if (!$GLOBALS['BE_USER']->isAdmin()) {
			unset($modMenuAdd['tsconf_parts'][99]);
		}
		return $modMenuAdd;
	}

	/**
	 * Main function of class
	 *
	 * @return string HTML output
	 */
	public function main() {

		if ((int)(GeneralUtility::_GP('id')) === 0) {
			$lang = $this->getLanguageService();
			return $this->pObj->doc->section(
				'',
				'<div class="nowrap"><div class="table-fit"><table class="table table-striped table-hover" id="tsconfig-overview">' .
				'<thead>' .
				'<tr>' .
				'<th>' . $lang->getLL('pagetitle') . '</th>' .
				'<th>' . $lang->getLL('included_tsconfig_files') . '</th>' .
				'<th>' . $lang->getLL('written_tsconfig_lines') . '</th>' .
				'</tr>' .
				'</thead>' .
				'<tbody>' . implode('', $this->getOverviewOfPagesUsingTSConfig()) . '</tbody>' .
				'</table></div>',
				0,
				1
			);
		} else {
			$menu = BackendUtility::getFuncMenu($this->pObj->id, 'SET[tsconf_parts]', $this->pObj->MOD_SETTINGS['tsconf_parts'], $this->pObj->MOD_MENU['tsconf_parts']);
			$menu .= '<div class="checkbox"><label for="checkTsconf_alphaSort">' . BackendUtility::getFuncCheck($this->pObj->id, 'SET[tsconf_alphaSort]', $this->pObj->MOD_SETTINGS['tsconf_alphaSort'], '', '', 'id="checkTsconf_alphaSort"') . $GLOBALS['LANG']->getLL('sort_alphabetic', TRUE) . '</label></div>';
			$theOutput = $this->pObj->doc->header($GLOBALS['LANG']->getLL('tsconf_title'));

			if ($this->pObj->MOD_SETTINGS['tsconf_parts'] == 99) {
				$TSparts = BackendUtility::getPagesTSconfig($this->pObj->id, NULL, TRUE);
				$lines = array();
				$pUids = array();
				foreach ($TSparts as $k => $v) {
					if ($k != 'uid_0') {
						if ($k == 'defaultPageTSconfig') {
							$pTitle = '<strong>' . $GLOBALS['LANG']->getLL('editTSconfig_default', TRUE) . '</strong>';
							$editIcon = '';
						} else {
							$pUids[] = substr($k, 4);
							$row = BackendUtility::getRecordWSOL('pages', substr($k, 4));
							$pTitle = $this->pObj->doc->getHeader('pages', $row, '', FALSE);
							$editIdList = substr($k, 4);
							$params = '&edit[pages][' . $editIdList . ']=edit&columnsOnly=TSconfig';
							$onclickUrl = BackendUtility::editOnClick($params, $GLOBALS['BACK_PATH'], '');
							$editIcon = '<a href="#" onclick="' . htmlspecialchars($onclickUrl) . '" title="' . $GLOBALS['LANG']->getLL('editTSconfig', TRUE) . '">' . \TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIcon('actions-document-open') . '</a>';
						}
						$TScontent = nl2br(htmlspecialchars(trim($v) . LF));
						$tsparser = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\TypoScript\Parser\TypoScriptParser::class);
						$tsparser->lineNumberOffset = 0;
						$TScontent = $tsparser->doSyntaxHighlight(trim($v) . LF);
						$lines[] = '
							<tr><td nowrap="nowrap" class="bgColor5">' . $pTitle . '</td></tr>
							<tr><td nowrap="nowrap" class="bgColor4">' . $TScontent . $editIcon . '</td></tr>
							<tr><td>&nbsp;</td></tr>
						';
					}
				}
				if (count($pUids)) {
					$params = '&edit[pages][' . implode(',', $pUids) . ']=edit&columnsOnly=TSconfig';
					$onclickUrl = BackendUtility::editOnClick($params, $GLOBALS['BACK_PATH'], '');
					$editIcon = '<a href="#" onclick="' . htmlspecialchars($onclickUrl) . '" title="' . $GLOBALS['LANG']->getLL('editTSconfig_all', TRUE) . '">' . \TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIcon('actions-document-open') . '<strong>' . $GLOBALS['LANG']->getLL('editTSconfig_all', TRUE) . '</strong>' . '</a>';
				} else {
					$editIcon = '';
				}
				$theOutput .= $this->pObj->doc->section('', BackendUtility::cshItem(('_MOD_' . $GLOBALS['MCONF']['name']), 'tsconfig_edit', NULL) . $menu . '
						<!-- Edit fields: -->
						<table border="0" cellpadding="0" cellspacing="1">' . implode('', $lines) . '</table><br />' . $editIcon, 0, 1);

			} else {
				// Defined global here!
				$tmpl = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\TypoScript\ExtendedTemplateService::class);

				// Do not log time-performance information
				$tmpl->tt_track = 0;
				$tmpl->fixedLgd = 0;
				$tmpl->linkObjects = 0;
				$tmpl->bType = '';
				$tmpl->ext_expandAllNotes = 1;
				$tmpl->ext_noPMicons = 1;


				switch ($this->pObj->MOD_SETTINGS['tsconf_parts']) {
					case '1':
						$modTSconfig = BackendUtility::getModTSconfig($this->pObj->id, 'mod');
						break;
					case '1a':
						$modTSconfig = $GLOBALS['BE_USER']->getTSConfig('mod.web_layout', BackendUtility::getPagesTSconfig($this->pObj->id));
						break;
					case '1b':
						$modTSconfig = $GLOBALS['BE_USER']->getTSConfig('mod.web_view', BackendUtility::getPagesTSconfig($this->pObj->id));
						break;
					case '1c':
						$modTSconfig = $GLOBALS['BE_USER']->getTSConfig('mod.web_modules', BackendUtility::getPagesTSconfig($this->pObj->id));
						break;
					case '1d':
						$modTSconfig = $GLOBALS['BE_USER']->getTSConfig('mod.web_list', BackendUtility::getPagesTSconfig($this->pObj->id));
						break;
					case '1e':
						$modTSconfig = $GLOBALS['BE_USER']->getTSConfig('mod.web_info', BackendUtility::getPagesTSconfig($this->pObj->id));
						break;
					case '1f':
						$modTSconfig = $GLOBALS['BE_USER']->getTSConfig('mod.web_func', BackendUtility::getPagesTSconfig($this->pObj->id));
						break;
					case '1g':
						$modTSconfig = $GLOBALS['BE_USER']->getTSConfig('mod.web_ts', BackendUtility::getPagesTSconfig($this->pObj->id));
						break;
					case '2':
						$modTSconfig = $GLOBALS['BE_USER']->getTSConfig('RTE', BackendUtility::getPagesTSconfig($this->pObj->id));
						break;
					case '5':
						$modTSconfig = $GLOBALS['BE_USER']->getTSConfig('TCEFORM', BackendUtility::getPagesTSconfig($this->pObj->id));
						break;
					case '6':
						$modTSconfig = $GLOBALS['BE_USER']->getTSConfig('TCEMAIN', BackendUtility::getPagesTSconfig($this->pObj->id));
						break;
					case '3':
						$modTSconfig = $GLOBALS['BE_USER']->getTSConfig('TSFE', BackendUtility::getPagesTSconfig($this->pObj->id));
						break;
					case '4':
						$modTSconfig = $GLOBALS['BE_USER']->getTSConfig('user', BackendUtility::getPagesTSconfig($this->pObj->id));
						break;
					default:
						$modTSconfig['properties'] = BackendUtility::getPagesTSconfig($this->pObj->id);
				}

				$modTSconfig = $modTSconfig['properties'];
				if (!is_array($modTSconfig)) {
					$modTSconfig = array();
				}

				$csh = BackendUtility::cshItem('_MOD_' . $GLOBALS['MCONF']['name'], 'tsconfig_hierarchy', NULL);
				$tree = $tmpl->ext_getObjTree($modTSconfig, '', '', '', '', $this->pObj->MOD_SETTINGS['tsconf_alphaSort']);

				$theOutput .= $this->pObj->doc->section(
					'',
					$csh .
					$menu .
					'<div class="nowrap">' . $tree . '</div>',
					0,
					1
				);
			}
		}

		return $theOutput;
	}

	/**
	 * Renders table rows of all pages containing TSConfig together with its rootline
	 *
	 * @return array
	 */
	protected function getOverviewOfPagesUsingTSConfig() {
		$db = $this->getDatabaseConnection();
		$res = $db->exec_SELECTquery(
			'uid, TSconfig',
			'pages',
			'TSconfig != \'\''
			. BackendUtility::deleteClause('pages')
			. BackendUtility::versioningPlaceholderClause('pages'), 'pages.uid');
		$pageArray = array();
		while ($row = $db->sql_fetch_assoc($res)) {
			$this->setInPageArray($pageArray, BackendUtility::BEgetRootLine($row['uid'], 'AND 1=1'), $row);
		}
		return $this->renderList($pageArray);
	}

	/**
	 * Set page in array
	 * This function is called recursively and builds a multi-dimensional array that reflects the page
	 * hierarchy.
	 *
	 * @param array $hierarchicArray The hierarchic array (passed by reference)
	 * @param array $rootlineArray The rootline array
	 * @param array $row The row from the database containing the uid and TSConfig fields
	 * @return void
	 */
	protected function setInPageArray(&$hierarchicArray, $rootlineArray, $row) {
		ksort($rootlineArray);
		reset($rootlineArray);
		if (!$rootlineArray[0]['uid']) {
			array_shift($rootlineArray);
		}
		$currentElement = current($rootlineArray);
		$hierarchicArray[$currentElement['uid']] = htmlspecialchars($currentElement['title']);
		array_shift($rootlineArray);
		if (count($rootlineArray)) {
			if (!isset($hierarchicArray[($currentElement['uid'] . '.')])) {
				$hierarchicArray[$currentElement['uid'] . '.'] = array();
			}
			$this->setInPageArray($hierarchicArray[$currentElement['uid'] . '.'], $rootlineArray, $row);
		} else {
			$hierarchicArray[$currentElement['uid'] . '_'] = $this->extractLinesFromTSConfig($row);
		}
	}

	/**
	 * Extract the lines of TSConfig from a given pages row
	 *
	 * @param array $row The row from the database containing the uid and TSConfig fields
	 * @return array
	 */
	protected function extractLinesFromTSConfig(array $row) {
		$out = array();
		$includeLines = 0;
		$out['uid'] = $row['uid'];
		$lines = GeneralUtility::trimExplode("\r\n", $row['TSconfig']);
		foreach ($lines as $line) {
			if (strpos($line, '<INCLUDE_TYPOSCRIPT:') !== FALSE) {
				$includeLines++;
			}
		}
		$out['includeLines'] = $includeLines;
		$out['writtenLines'] = (count($lines) - $includeLines);
		return $out;
	}

	/**
	 * Render the list of pages to show.
	 * This function is called recursively
	 *
	 * @param array $pageArray The Page Array
	 * @param array $lines Lines that have been processed up to this point
	 * @param int $pageDepth The level of the current $pageArray being processed
	 * @return array
	 */
	protected function renderList($pageArray, $lines = array(), $pageDepth = 0) {
		$cellStyle = 'padding-left: ' . ($pageDepth * 20) . 'px';
		if (!is_array($pageArray)) {
			return $lines;
		}

		foreach ($pageArray as $identifier => $_) {
			if (!MathUtility::canBeInterpretedAsInteger($identifier)) {
				continue;
			}
			if (isset($pageArray[$identifier . '_'])) {
				$lines[] = '
				<tr>
					<td nowrap style="' . $cellStyle . '">
						<a href="'
					. htmlspecialchars(GeneralUtility::linkThisScript(array('id' => $identifier)))
					. '">'
					. IconUtility::getSpriteIconForRecord(
						'pages',
						BackendUtility::getRecordWSOL('pages', $identifier), array('title' => ('ID: ' . $identifier))
					)
					. GeneralUtility::fixed_lgd_cs($pageArray[$identifier], 30) . '</a></td>
					<td>' . ($pageArray[($identifier . '_')]['includeLines'] === 0 ? '' : $pageArray[($identifier . '_')]['includeLines']) . '</td>
					<td>' . ($pageArray[$identifier . '_']['writtenLines'] === 0 ? '' : $pageArray[$identifier . '_']['writtenLines']) . '</td>
					</tr>';
			} else {
				$lines[] = '<tr>
					<td nowrap style="' . $cellStyle . '">
					' . IconUtility::getSpriteIconForRecord(
						'pages',
						BackendUtility::getRecordWSOL('pages', $identifier))
					. GeneralUtility::fixed_lgd_cs($pageArray[$identifier], 30) . '</td>
					<td></td>
					<td></td>
					</tr>';
			}
			$lines = $this->renderList($pageArray[$identifier . '.'], $lines, $pageDepth + 1);
		}
		return $lines;
	}

}
