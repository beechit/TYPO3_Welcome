<?php
namespace TYPO3\CMS\Form\View\Form\Element;

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
 * View object for the option element
 *
 * @author Patrick Broens <patrick@patrickbroens.nl>
 */
class OptionElementView extends \TYPO3\CMS\Form\View\Form\Element\AbstractElementView {

	/**
	 * Default layout of this object
	 *
	 * @var string
	 */
	protected $layout = '
		<option />
	';

	/**
	 * True if element needs no element wrap
	 * like <li>element</li>
	 *
	 * @var bool
	 */
	protected $noWrap = TRUE;

}
