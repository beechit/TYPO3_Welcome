<?php
namespace TYPO3\CMS\Form\View\Mail\Html;

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
use TYPO3\CMS\Form\Domain\Model\Form;

/**
 * Main view layer for Forms.
 *
 * @author Patrick Broens <patrick@patrickbroens.nl>
 */
class HtmlView extends \TYPO3\CMS\Form\View\Mail\Html\Element\ContainerElementView {

	/**
	 * Default layout of this object
	 *
	 * @var string
	 */
	protected $layout = '
		<html>
			<head>
				<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
			</head>
			<body>
				<table cellspacing="0">
					<containerWrap />
				</table>
			</body>
		</html>
	';

	/**
	 * The TypoScript settings for the confirmation
	 *
	 * @var array
	 */
	protected $typoscript = array();

	/**
	 * The localization handler
	 *
	 * @var \TYPO3\CMS\Form\Localization
	 */
	protected $localizationHandler;

	/**
	 * The content object
	 *
	 * @var \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer
	 */
	protected $localCobj;

	/**
	 * Constructor
	 *
	 * @param Form $model
	 * @param array $typoscript
	 */
	public function __construct(Form $model, array $typoscript) {
		$this->localCobj = GeneralUtility::makeInstance(\TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer::class);
		$this->localizationHandler = GeneralUtility::makeInstance(\TYPO3\CMS\Form\Localization::class);
		$this->typoscript = $typoscript;
		parent::__construct($model);
	}

	/**
	 * Set the data for the FORM tag
	 *
	 * @param Form $model The model of the form
	 * @return void
	 */
	public function setData(Form $model) {
		$this->model = (object) $model;
	}

	/**
	 * Start the main DOMdocument for the form
	 * Return it as a string using saveXML() to get a proper formatted output
	 * (when using formatOutput :-)
	 *
	 * @return string XHTML string containing the whole form
	 */
	public function get() {
		$node = $this->render('element', FALSE);
		$content = LF . html_entity_decode($node->saveXML($node->firstChild), ENT_QUOTES, 'UTF-8') . LF;
		return $content;
	}

}
