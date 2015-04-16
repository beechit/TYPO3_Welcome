<?php
namespace TYPO3\CMS\Backend\Tree\Pagetree;

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

use TYPO3\CMS\Backend\Utility\IconUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Data Provider of the Page Tree
 *
 * @author Stefan Galinski <stefan.galinski@gmail.com>
 */
class ExtdirectTreeDataProvider extends \TYPO3\CMS\Backend\Tree\AbstractExtJsTree {

	/**
	 * Data Provider
	 *
	 * @var \TYPO3\CMS\Backend\Tree\Pagetree\DataProvider
	 */
	protected $dataProvider = NULL;

	/**
	 * Sets the data provider
	 *
	 * @return void
	 */
	protected function initDataProvider() {
		/** @var $dataProvider \TYPO3\CMS\Backend\Tree\Pagetree\DataProvider */
		$dataProvider = GeneralUtility::makeInstance(\TYPO3\CMS\Backend\Tree\Pagetree\DataProvider::class);
		$this->setDataProvider($dataProvider);
	}

	/**
	 * Returns the root node of the tree
	 *
	 * @return array
	 */
	public function getRoot() {
		$this->initDataProvider();
		$node = $this->dataProvider->getRoot();
		return $node->toArray();
	}

	/**
	 * Fetches the next tree level
	 *
	 * @param int $nodeId
	 * @param stdClass $nodeData
	 * @return array
	 */
	public function getNextTreeLevel($nodeId, $nodeData) {
		$this->initDataProvider();
		/** @var $node \TYPO3\CMS\Backend\Tree\Pagetree\PagetreeNode */
		$node = GeneralUtility::makeInstance(\TYPO3\CMS\Backend\Tree\Pagetree\PagetreeNode::class, (array)$nodeData);
		if ($nodeId === 'root') {
			$nodeCollection = $this->dataProvider->getTreeMounts();
		} else {
			$nodeCollection = $this->dataProvider->getNodes($node, $node->getMountPoint());
		}
		return $nodeCollection->toArray();
	}

	/**
	 * Returns a tree that only contains elements that match the given search string
	 *
	 * @param int $nodeId
	 * @param stdClass $nodeData
	 * @param string $searchFilter
	 * @return array
	 */
	public function getFilteredTree($nodeId, $nodeData, $searchFilter) {
		if (strval($searchFilter) === '') {
			return array();
		}
		/** @var $node \TYPO3\CMS\Backend\Tree\Pagetree\PagetreeNode */
		$node = GeneralUtility::makeInstance(\TYPO3\CMS\Backend\Tree\Pagetree\PagetreeNode::class, (array)$nodeData);
		$this->initDataProvider();
		if ($nodeId === 'root') {
			$nodeCollection = $this->dataProvider->getTreeMounts($searchFilter);
		} else {
			$nodeCollection = $this->dataProvider->getFilteredNodes($node, $searchFilter, $node->getMountPoint());
		}
		return $nodeCollection->toArray();
	}

	/**
	 * Returns the localized list of doktypes to display
	 *
	 * Note: The list can be filtered by the user typoscript
	 * option "options.pageTree.doktypesToShowInNewPageDragArea".
	 *
	 * @return array
	 */
	public function getNodeTypes() {
		$doktypeLabelMap = array();
		foreach ($GLOBALS['TCA']['pages']['columns']['doktype']['config']['items'] as $doktypeItemConfig) {
			if ($doktypeItemConfig[1] === '--div--') {
				continue;
			}
			$doktypeLabelMap[$doktypeItemConfig[1]] = $doktypeItemConfig[0];
		}
		$doktypes = GeneralUtility::trimExplode(',', $GLOBALS['BE_USER']->getTSConfigVal('options.pageTree.doktypesToShowInNewPageDragArea'));
		$output = array();
		$allowedDoktypes = GeneralUtility::trimExplode(',', $GLOBALS['BE_USER']->groupData['pagetypes_select']);
		$isAdmin = $GLOBALS['BE_USER']->isAdmin();
		foreach ($doktypes as $doktype) {
			if (!$isAdmin && !in_array($doktype, $allowedDoktypes)) {
				continue;
			}
			$label = $GLOBALS['LANG']->sL($doktypeLabelMap[$doktype], TRUE);
			$spriteIcon = IconUtility::getSpriteIconClasses($GLOBALS['TCA']['pages']['ctrl']['typeicon_classes'][$doktype]);
			$output[] = array(
				'nodeType' => $doktype,
				'cls' => 'typo3-pagetree-topPanel-button',
				'iconCls' => $spriteIcon,
				'title' => $label,
				'tooltip' => $label
			);
		}
		return $output;
	}

	/**
	 * Returns
	 *
	 * @return array
	 */
	public function getIndicators() {
		/** @var $indicatorProvider \TYPO3\CMS\Backend\Tree\Pagetree\Indicator */
		$indicatorProvider = GeneralUtility::makeInstance(\TYPO3\CMS\Backend\Tree\Pagetree\Indicator::class);
		$indicatorHtmlArr = $indicatorProvider->getAllIndicators();
		$indicator = array(
			'html' => implode(' ', $indicatorHtmlArr),
			'_COUNT' => count($indicatorHtmlArr)
		);
		return $indicator;
	}

	/**
	 * Returns the language labels, sprites and configuration options for the pagetree
	 *
	 * @return void
	 */
	public function loadResources() {
		$file = 'LLL:EXT:lang/locallang_core.xlf:';
		$indicators = $this->getIndicators();
		$configuration = array(
			'LLL' => array(
				'copyHint' => $GLOBALS['LANG']->sL($file . 'tree.copyHint', TRUE),
				'fakeNodeHint' => $GLOBALS['LANG']->sL($file . 'mess.please_wait', TRUE),
				'activeFilterMode' => $GLOBALS['LANG']->sL($file . 'tree.activeFilterMode', TRUE),
				'dropToRemove' => $GLOBALS['LANG']->sL($file . 'tree.dropToRemove', TRUE),
				'buttonRefresh' => $GLOBALS['LANG']->sL($file . 'labels.refresh', TRUE),
				'buttonNewNode' => $GLOBALS['LANG']->sL($file . 'tree.buttonNewNode', TRUE),
				'buttonFilter' => $GLOBALS['LANG']->sL($file . 'tree.buttonFilter', TRUE),
				'dropZoneElementRemoved' => $GLOBALS['LANG']->sL($file . 'tree.dropZoneElementRemoved', TRUE),
				'dropZoneElementRestored' => $GLOBALS['LANG']->sL($file . 'tree.dropZoneElementRestored', TRUE),
				'searchTermInfo' => $GLOBALS['LANG']->sL($file . 'tree.searchTermInfo', TRUE),
				'temporaryMountPointIndicatorInfo' => $GLOBALS['LANG']->sl($file . 'labels.temporaryDBmount', TRUE),
				'deleteDialogTitle' => $GLOBALS['LANG']->sL('LLL:EXT:cms/layout/locallang.xlf:deleteItem', TRUE),
				'deleteDialogMessage' => $GLOBALS['LANG']->sL('LLL:EXT:cms/layout/locallang.xlf:deleteWarning', TRUE),
				'recursiveDeleteDialogMessage' => $GLOBALS['LANG']->sL('LLL:EXT:cms/layout/locallang.xlf:recursiveDeleteWarning', TRUE)
			),
			'Configuration' => array(
				'hideFilter' => $GLOBALS['BE_USER']->getTSConfigVal('options.pageTree.hideFilter'),
				'displayDeleteConfirmation' => $GLOBALS['BE_USER']->jsConfirmation(4),
				'canDeleteRecursivly' => $GLOBALS['BE_USER']->uc['recursiveDelete'] == TRUE,
				'disableIconLinkToContextmenu' => $GLOBALS['BE_USER']->getTSConfigVal('options.pageTree.disableIconLinkToContextmenu'),
				'indicator' => $indicators['html'],
				'temporaryMountPoint' => Commands::getMountPointPath()
			),
			'Sprites' => array(
				'Filter' => IconUtility::getSpriteIconClasses('actions-system-tree-search-open'),
				'NewNode' => IconUtility::getSpriteIconClasses('actions-page-new'),
				'Refresh' => IconUtility::getSpriteIconClasses('actions-system-refresh'),
				'InputClear' => IconUtility::getSpriteIconClasses('actions-input-clear'),
				'TrashCan' => IconUtility::getSpriteIconClasses('actions-edit-delete'),
				'TrashCanRestore' => IconUtility::getSpriteIconClasses('actions-edit-restore'),
				'Info' => IconUtility::getSpriteIconClasses('actions-document-info')
			)
		);
		return $configuration;
	}

}
