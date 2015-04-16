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

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * TCEforms wizard for rendering an AJAX selector for records
 *
 * @author Steffen Ritter <info@steffen-ritter.net>
 * @author Steffen Kamper <steffen@typo3.org>
 */
class TreeElement extends AbstractFormElement {

	/**
	 * renders the tree as replacement for the selector
	 *
	 * @param string $table The table name of the record
	 * @param string $field The field name which this element is supposed to edit
	 * @param array $row The record data array where the value(s) for the field can be found
	 * @param array $PA An array with additional configuration options.
	 * @param array $config (Redundant) content of $PA['fieldConf']['config'] (for convenience)
	 * @param array $possibleSelectboxItems Items available for selection
	 * @param string $noMatchLabel Label for no-matching-value
	 * @return string The HTML code for the TCEform field
	 */
	public function renderField($table, $field, $row, &$PA, $config, $possibleSelectboxItems, $noMatchLabel) {
		$valueArray = array();
		$selectedNodes = array();
		if (!empty($PA['itemFormElValue'])) {
			$valueArray = explode(',', $PA['itemFormElValue']);
		}
		if (count($valueArray)) {
			foreach ($valueArray as $selectedValue) {
				$temp = explode('|', $selectedValue);
				$selectedNodes[] = $temp[0];
			}
		}
		$allowedUids = array();
		foreach ($possibleSelectboxItems as $item) {
			if ((int)$item[1] > 0) {
				$allowedUids[] = $item[1];
			}
		}
		$treeDataProvider = \TYPO3\CMS\Core\Tree\TableConfiguration\TreeDataProviderFactory::getDataProvider($config, $table, $field, $row);
		$treeDataProvider->setSelectedList(implode(',', $selectedNodes));
		$treeDataProvider->setItemWhiteList($allowedUids);
		$treeDataProvider->initializeTreeData();
		$treeRenderer = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Tree\TableConfiguration\ExtJsArrayTreeRenderer::class);
		$tree = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Tree\TableConfiguration\TableConfigurationTree::class);
		$tree->setDataProvider($treeDataProvider);
		$tree->setNodeRenderer($treeRenderer);
		$treeData = $tree->render();
		$itemArray = array();
		if (is_array($PA['fieldConf']['config']['items'])) {
			foreach ($PA['fieldConf']['config']['items'] as $additionalItem) {
				if ($additionalItem[1] !== '--div--') {
					$item = new \stdClass();
					$item->uid = $additionalItem[1];
					$item->text = $this->getLanguageService()->sL($additionalItem[0]);
					$item->selectable = TRUE;
					$item->leaf = TRUE;
					$item->checked = in_array($additionalItem[1], $selectedNodes);
					if (file_exists(PATH_typo3 . $additionalItem[3])) {
						$item->icon = $additionalItem[3];
					} elseif (trim($additionalItem[3]) !== '') {
						$item->iconCls = \TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIconClasses($additionalItem[3]);
					}
					$itemArray[] = $item;
				}
			}
		}
		$itemArray[] = $treeData;
		$treeData = json_encode($itemArray);
		$id = md5($PA['itemFormElName']);
		if (isset($PA['fieldConf']['config']['size']) && (int)$PA['fieldConf']['config']['size'] > 0) {
			$height = (int)$PA['fieldConf']['config']['size'] * 20;
		} else {
			$height = 280;
		}
		if (isset($PA['fieldConf']['config']['autoSizeMax']) && (int)$PA['fieldConf']['config']['autoSizeMax'] > 0) {
			$autoSizeMax = (int)$PA['fieldConf']['config']['autoSizeMax'] * 20;
		}
		$header = FALSE;
		$expanded = FALSE;
		$width = 280;
		$appearance = $PA['fieldConf']['config']['treeConfig']['appearance'];
		if (is_array($appearance)) {
			$header = $appearance['showHeader'] ? TRUE : FALSE;
			$expanded = $appearance['expandAll'] === TRUE;
			if (isset($appearance['width'])) {
				$width = (int)$appearance['width'];
			}
		}
		$onChange = '';
		if ($PA['fieldChangeFunc']['TBE_EDITOR_fieldChanged']) {
			$onChange = $PA['fieldChangeFunc']['TBE_EDITOR_fieldChanged'];
		}
		// Create a JavaScript code line which will ask the user to save/update the form due to changing the element.
		// This is used for eg. "type" fields and others configured with "requestUpdate"
		if (
			!empty($GLOBALS['TCA'][$table]['ctrl']['type'])
			&& $field === $GLOBALS['TCA'][$table]['ctrl']['type']
			|| !empty($GLOBALS['TCA'][$table]['ctrl']['requestUpdate'])
			&& GeneralUtility::inList(str_replace(' ', '', $GLOBALS['TCA'][$table]['ctrl']['requestUpdate']), $field)
		) {
			if ($GLOBALS['BE_USER']->jsConfirmation(1)) {
				$onChange .= 'if (confirm(TBE_EDITOR.labels.onChangeAlert) && ' . 'TBE_EDITOR.checkSubmit(-1)){ TBE_EDITOR.submitForm() };';
			} else {
				$onChange .= 'if (TBE_EDITOR.checkSubmit(-1)){ TBE_EDITOR.submitForm() };';
			}
		}
		/** @var $pageRenderer \TYPO3\CMS\Core\Page\PageRenderer */
		$pageRenderer = $GLOBALS['SOBE']->doc->getPageRenderer();
		$pageRenderer->loadExtJs();
		$pageRenderer->addJsFile('sysext/backend/Resources/Public/JavaScript/tree.js');
		$pageRenderer->addInlineLanguageLabelFile(\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('lang') . 'locallang_csh_corebe.xlf', 'tcatree');
		$pageRenderer->addExtOnReadyCode('
			TYPO3.Components.Tree.StandardTreeItemData["' . $id . '"] = ' . $treeData . ';
			var tree' . $id . ' = new TYPO3.Components.Tree.StandardTree({
				id: "' . $id . '",
				showHeader: ' . (int)$header . ',
				onChange: "' . $onChange . '",
				countSelectedNodes: ' . count($selectedNodes) . ',
				width: ' . $width . ',
				listeners: {
					click: function(node, event) {
						if (typeof(node.attributes.checked) == "boolean") {
							node.attributes.checked = ! node.attributes.checked;
							node.getUI().toggleCheck(node.attributes.checked);
						}
					},
					dblclick: function(node, event) {
						if (typeof(node.attributes.checked) == "boolean") {
							node.attributes.checked = ! node.attributes.checked;
							node.getUI().toggleCheck(node.attributes.checked);
						}
					},
					checkchange: TYPO3.Components.Tree.TcaCheckChangeHandler,
					collapsenode: function(node) {
						if (node.id !== "root") {
							top.TYPO3.BackendUserSettings.ExtDirect.removeFromList("tcaTrees." + this.ucId, node.attributes.uid);
						}
					},
					expandnode: function(node) {
						if (node.id !== "root") {
							top.TYPO3.BackendUserSettings.ExtDirect.addToList("tcaTrees." + this.ucId, node.attributes.uid);
						}
					},
					beforerender: function(treeCmp) {
						// Check if that tree element is already rendered. It is appended on the first tceforms_inline call.
						if (Ext.fly(treeCmp.getId())) {
							return false;
						}
					}' . ($expanded ? ',
					afterrender: function(treeCmp) {
						treeCmp.expandAll();
					}' : '') . '
				},
				tcaMaxItems: ' . ($PA['fieldConf']['config']['maxitems'] ? (int)$PA['fieldConf']['config']['maxitems'] : 99999) . ',
				tcaSelectRecursiveAllowed: ' . ($appearance['allowRecursiveMode'] ? 'true' : 'false') . ',
				tcaSelectRecursive: false,
				tcaExclusiveKeys: "' . ($PA['fieldConf']['config']['exclusiveKeys'] ? $PA['fieldConf']['config']['exclusiveKeys'] : '') . '",
				ucId: "' . md5(($table . '|' . $field)) . '",
				selModel: TYPO3.Components.Tree.EmptySelectionModel,
				disabled: ' . ($PA['fieldConf']['config']['readOnly'] || $this->isRenderReadonly() ? 'true' : 'false') . '
			});' . LF .
				($autoSizeMax
					? 'tree' . $id . '.bodyStyle = "max-height: ' . $autoSizeMax . 'px;min-height: ' . $height . 'px;";'
					: 'tree' . $id . '.height = ' . $height . ';'
				) . LF .
				'(function() {
					tree' . $id . '.render("tree_' . $id . '");
				}).defer(20);
		');
		$formField = '
			<div class="typo3-tceforms-tree">
				<input class="treeRecord" type="hidden" name="' . htmlspecialchars($PA['itemFormElName']) . '" id="treeinput' . $id . '" value="' . htmlspecialchars($PA['itemFormElValue']) . '" />
			</div>
			<div id="tree_' . $id . '">

			</div>';
		return $formField;
	}

	/**
	 * Dummy handler
	 *
	 * @param string $table The table name of the record
	 * @param string $field The field name which this element is supposed to edit
	 * @param array $row The record data array where the value(s) for the field can be found
	 * @param array $additionalInformation An array with additional configuration options.
	 * @return string The HTML code for the TCEform field
	 */
	public function render($table, $field, $row, &$additionalInformation) {
		// deliberately empty as this class is not used the same way
		return '';
	}

}
