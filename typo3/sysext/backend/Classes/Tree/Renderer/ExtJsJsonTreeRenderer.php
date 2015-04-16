<?php
namespace TYPO3\CMS\Backend\Tree\Renderer;

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

/**
 * Renderer for unordered lists
 *
 * @author Steffen Kamper <steffen@typo3.org>
 * @author Steffen Ritter <info@steffen-ritter.net>
 */
class ExtJsJsonTreeRenderer extends \TYPO3\CMS\Backend\Tree\Renderer\AbstractTreeRenderer {

	/**
	 * recursion level
	 *
	 * @var int
	 */
	protected $recursionLevel = 0;

	/**
	 * Renders a node recursive or just a single instance
	 *
	 * @param \TYPO3\CMS\Backend\Tree\TreeRepresentationNode $node
	 * @param bool $recursive
	 * @return array
	 */
	public function renderNode(\TYPO3\CMS\Backend\Tree\TreeRepresentationNode $node, $recursive = TRUE) {
		$nodeArray = $this->getNodeArray($node);
		if ($recursive && $node->hasChildNodes()) {
			$this->recursionLevel++;
			$children = $this->renderNodeCollection($node->getChildNodes());
			$nodeArray['children'] = $children;
			$this->recursionLevel--;
		}
		return $nodeArray;
	}

	/**
	 * Get node array
	 *
	 * @param \TYPO3\CMS\Backend\Tree\TreeRepresentationNode $node
	 * @return array
	 */
	protected function getNodeArray(\TYPO3\CMS\Backend\Tree\TreeRepresentationNode $node) {
		$nodeArray = array(
			'iconCls' => $node->getIcon(),
			'text' => $node->getLabel(),
			'leaf' => !$node->hasChildNodes(),
			'id' => $node->getId(),
			'uid' => $node->getId()
		);

		foreach ($nodeArray as &$nodeItem) {
			if (is_string($nodeItem)) {
				$nodeItem = htmlspecialchars($nodeItem);
			}
		}

		return $nodeArray;
	}

	/**
	 * Renders a node collection recursive or just a single instance
	 *
	 * @param \TYPO3\CMS\Backend\Tree\TreeNodeCollection $node
	 * @param bool $recursive
	 * @return string
	 */
	public function renderTree(\TYPO3\CMS\Backend\Tree\AbstractTree $tree, $recursive = TRUE) {
		$this->recursionLevel = 0;
		$children = $this->renderNode($tree->getRoot(), $recursive);
		return json_encode($children);
	}

	/**
	 * Renders an tree recursive or just a single instance
	 *
	 * @param \TYPO3\CMS\Backend\Tree\AbstractTree $node
	 * @param bool $recursive
	 * @return array
	 */
	public function renderNodeCollection(\TYPO3\CMS\Backend\Tree\TreeNodeCollection $collection, $recursive = TRUE) {
		foreach ($collection as $node) {
			$treeItems[] = $this->renderNode($node, $recursive);
		}
		return $treeItems;
	}

}
