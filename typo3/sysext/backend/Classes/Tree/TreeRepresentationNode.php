<?php
namespace TYPO3\CMS\Backend\Tree;

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
 * Representation Tree Node
 *
 * @author Stefan Galinski <stefan.galinski@gmail.com>
 * @author Steffen Ritter <info@steffen-ritter.net>
 */
class TreeRepresentationNode extends \TYPO3\CMS\Backend\Tree\TreeNode {

	/**
	 * Node Label
	 *
	 * @var string
	 */
	protected $label = '';

	/**
	 * Node Type
	 *
	 * @var string
	 */
	protected $type = '';

	/**
	 * Node CSS Class
	 *
	 * @var string
	 */
	protected $class = '';

	/**
	 * Node Icon
	 *
	 * @var string
	 */
	protected $icon = '';

	/**
	 * Callback function that is called e.g after a click on the label
	 *
	 * @var string
	 */
	protected $callbackAction = '';

	/**
	 * @param string $class
	 * @return void
	 */
	public function setClass($class) {
		$this->class = $class;
	}

	/**
	 * @return string
	 */
	public function getClass() {
		return $this->class;
	}

	/**
	 * @param string $icon
	 * @return void
	 */
	public function setIcon($icon) {
		$this->icon = $icon;
	}

	/**
	 * @return string
	 */
	public function getIcon() {
		return $this->icon;
	}

	/**
	 * @param string $label
	 */
	public function setLabel($label) {
		$this->label = $label;
	}

	/**
	 * @return string
	 */
	public function getLabel() {
		return $this->label;
	}

	/**
	 * @param string $type
	 * @return void
	 */
	public function setType($type) {
		$this->type = $type;
	}

	/**
	 * @return string
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * Sets the callback action
	 *
	 * @param string $callbackAction
	 * @return void
	 */
	public function setCallbackAction($callbackAction) {
		$this->callbackAction = $callbackAction;
	}

	/**
	 * Returns the callback action
	 *
	 * @return string
	 */
	public function getCallbackAction() {
		return $this->callbackAction;
	}

	/**
	 * Returns the node in an array representation that can be used for serialization
	 *
	 * @param bool $addChildNodes
	 * @return array
	 */
	public function toArray($addChildNodes = TRUE) {
		$arrayRepresentation = parent::toArray();
		$arrayRepresentation = array_merge($arrayRepresentation, array(
			'label' => $this->label,
			'type' => $this->type,
			'class' => $this->class,
			'icon' => $this->icon,
			'callbackAction' => $this->callbackAction
		));
		return $arrayRepresentation;
	}

	/**
	 * Sets data of the node by a given data array
	 *
	 * @param array $data
	 * @return void
	 */
	public function dataFromArray($data) {
		parent::dataFromArray($data);
		$this->setLabel($data['label']);
		$this->setType($data['type']);
		$this->setClass($data['class']);
		$this->setIcon($data['icon']);
		$this->setCallbackAction($data['callbackAction']);
	}

}
