<?php
namespace TYPO3\CMS\Backend\Domain\Model\Module;

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
 * Model for menu entries
 *
 * @author Susanne Moog <typo3@susannemoog.de>
 */
class BackendModule {

	/**
	 * @var string
	 */
	protected $title = '';

	/**
	 * @var string
	 */
	protected $name = '';

	/**
	 * @var array
	 */
	protected $icon = array();

	/**
	 * @var string
	 */
	protected $link = '';

	/**
	 * @var string
	 */
	protected $onClick = '';

	/**
	 * @var string
	 */
	protected $description = '';

	/**
	 * @var string
	 */
	protected $navigationComponentId = '';

	/**
	 * @var string
	 */
	protected $navigationFrameScript = '';

	/**
	 * @var string
	 */
	protected $navigationFrameScriptParameters = '';

	/**
	 * @var \SplObjectStorage
	 */
	protected $children;

	/**
	 * construct
	 */
	public function __construct() {
		$this->children = new \SplObjectStorage();
	}

	/**
	 * Set children
	 *
	 * @param \SplObjectStorage $children
	 * @return void
	 */
	public function setChildren($children) {
		$this->children = $children;
	}

	/**
	 * Get children
	 *
	 * @return \SplObjectStorage
	 */
	public function getChildren() {
		return $this->children;
	}

	/**
	 * Add Child
	 *
	 * @param \TYPO3\CMS\Backend\Domain\Model\Module\BackendModule $child
	 * @return void
	 */
	public function addChild(\TYPO3\CMS\Backend\Domain\Model\Module\BackendModule $child) {
		$this->children->attach($child);
	}

	/**
	 * Set icon
	 *
	 * @param array $icon
	 * @return void
	 */
	public function setIcon(array $icon) {
		$this->icon = $icon;
	}

	/**
	 * Get icon
	 *
	 * @return array
	 */
	public function getIcon() {
		return $this->icon;
	}

	/**
	 * Set name
	 *
	 * @param string $name
	 * @return void
	 */
	public function setName($name) {
		$this->name = $name;
	}

	/**
	 * Get name
	 *
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * Set title
	 *
	 * @param string $title
	 * @return void
	 */
	public function setTitle($title) {
		$this->title = $title;
	}

	/**
	 * Get Title
	 *
	 * @return string
	 */
	public function getTitle() {
		return $this->title;
	}

	/**
	 * Set Link
	 *
	 * @param string $link
	 * @return void
	 */
	public function setLink($link) {
		$this->link = $link;
	}

	/**
	 * Get Link
	 *
	 * @return string
	 */
	public function getLink() {
		return $this->link;
	}

	/**
	 * Set Description
	 *
	 * @param string $description
	 * @return void
	 */
	public function setDescription($description) {
		$this->description = $description;
	}

	/**
	 * Get Description
	 *
	 * @return string
	 */
	public function getDescription() {
		return $this->description;
	}

	/**
	 * Set Navigation Component Id
	 *
	 * @param string $navigationComponentId
	 * @return void
	 */
	public function setNavigationComponentId($navigationComponentId) {
		$this->navigationComponentId = $navigationComponentId;
	}

	/**
	 * Get Navigation Component Id
	 *
	 * @return string
	 */
	public function getNavigationComponentId() {
		return $this->navigationComponentId;
	}

	/**
	 * @param string $navigationFrameScript
	 */
	public function setNavigationFrameScript($navigationFrameScript) {
		$this->navigationFrameScript = $navigationFrameScript;
	}

	/**
	 * @return string
	 */
	public function getNavigationFrameScript() {
		return $this->navigationFrameScript;
	}

	/**
	 * @param string $navigationFrameScriptParameters
	 */
	public function setNavigationFrameScriptParameters($navigationFrameScriptParameters) {
		$this->navigationFrameScriptParameters = $navigationFrameScriptParameters;
	}

	/**
	 * @return string
	 */
	public function getNavigationFrameScriptParameters() {
		return $this->navigationFrameScriptParameters;
	}

	/**
	 * Set onClick
	 *
	 * @param string $onClick
	 * @return void
	 */
	public function setOnClick($onClick) {
		$this->onClick = $onClick;
	}

	/**
	 * Get onClick
	 *
	 * @return string
	 */
	public function getOnClick() {
		return $this->onClick;
	}

}
