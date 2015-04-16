<?php
namespace TYPO3\CMS\Form\View\Confirmation\Element;

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
 * View object for the text area element
 *
 * @author Patrick Broens <patrick@patrickbroens.nl>
 */
class TextareaElementView extends \TYPO3\CMS\Form\View\Confirmation\Element\AbstractElementView {

	/**
	 * Default layout of this object
	 *
	 * @var string
	 */
	protected $layout = '
		<label />
		<inputvalue />
	';

	/**
	 * Constructor
	 *
	 * @param \TYPO3\CMS\Form\Domain\Model\Element\TextareaElement $model Model for this element
	 */
	public function __construct(\TYPO3\CMS\Form\Domain\Model\Element\TextareaElement $model) {
		parent::__construct($model);
	}

}
