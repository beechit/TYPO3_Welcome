<?php
namespace TYPO3\CMS\Form\Controller;

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
 * Main controller for Forms.  All requests come through this class
 * and are routed to the model and view layers for processing.
 *
 * @author Patrick Broens <patrick@patrickbroens.nl>
 */
class FormController {

	/**
	 * The TypoScript array
	 *
	 * @var array
	 */
	protected $typoscript = array();

	/**
	 * @var \TYPO3\CMS\Form\Domain\Factory\TypoScriptFactory
	 */
	protected $typoscriptFactory;

	/**
	 * @var \TYPO3\CMS\Form\Localization
	 */
	protected $localizationHandler;

	/**
	 * @var \TYPO3\CMS\Form\Request
	 */
	protected $requestHandler;

	/**
	 * @var \TYPO3\CMS\Form\Layout
	 */
	protected $layoutHandler;

	/**
	 * @var \TYPO3\CMS\Form\Utility\ValidatorUtility
	 */
	protected $validate;

	/**
	 * Initialisation
	 *
	 * @param array $typoscript TS configuration for this cObject
	 * @return void
	 */
	public function initialize(array $typoscript) {
		$this->typoscriptFactory = GeneralUtility::makeInstance(\TYPO3\CMS\Form\Domain\Factory\TypoScriptFactory::class);
		$this->localizationHandler = GeneralUtility::makeInstance(\TYPO3\CMS\Form\Localization::class);
		$this->layoutHandler = GeneralUtility::makeInstance(\TYPO3\CMS\Form\Layout::class);
		$this->requestHandler = $this->typoscriptFactory->setRequestHandler($typoscript);
		$this->validate = $this->typoscriptFactory->setRules($typoscript);
		$this->typoscript = $typoscript;
	}

	/**
	 * Renders the application defined cObject FORM
	 * which overrides the TYPO3 default cObject FORM
	 *
	 * First we make a COA_INT out of it, because it does not need to be cached
	 * Then we send a FORM_INT to the COA_INT
	 * When this is read, it will call the FORM class again.
	 *
	 * It simply calls execute because this function name is not really descriptive
	 * but is needed by the core of TYPO3
	 *
	 * @param string $typoScriptObjectName Name of the object
	 * @param array $typoScript TS configuration for this cObject
	 * @param string $typoScriptKey A string label used for the internal debugging tracking.
	 * @param \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer $contentObject reference
	 * @return string HTML output
	 */
	public function cObjGetSingleExt($typoScriptObjectName, array $typoScript, $typoScriptKey, \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer $contentObject) {
		$content = '';
		if ($typoScriptObjectName === 'FORM' && !empty($typoScript['useDefaultContentObject'])) {
			$content = $contentObject->getContentObject($typoScriptObjectName)->render($typoScript);
		} elseif ($typoScriptObjectName === 'FORM') {
			if ($contentObject->data['CType'] === 'mailform') {
				$bodytext = $contentObject->data['bodytext'];
				/** @var $typoScriptParser \TYPO3\CMS\Core\TypoScript\Parser\TypoScriptParser */
				$typoScriptParser = GeneralUtility::makeInstance(\TYPO3\CMS\Core\TypoScript\Parser\TypoScriptParser::class);
				$typoScriptParser->parse($bodytext);
				$mergedTypoScript = (array)$typoScriptParser->setup;
				\TYPO3\CMS\Core\Utility\ArrayUtility::mergeRecursiveWithOverrule($mergedTypoScript, (array)$typoScript);
				// Disables content elements since TypoScript is handled that could contain insecure settings:
				$mergedTypoScript[\TYPO3\CMS\Form\Domain\Factory\TypoScriptFactory::PROPERTY_DisableContentElement] = TRUE;
			}
			$newTypoScript = array(
				'10' => 'FORM_INT',
				'10.' => is_array($mergedTypoScript) ? $mergedTypoScript : $typoScript,
			);
			$content = $contentObject->cObjGetSingle('COA_INT', $newTypoScript);
			// Only apply stdWrap to TypoScript that was NOT created by the wizard:
			if (isset($typoScript['stdWrap.'])) {
				$content = $contentObject->stdWrap($content, $typoScript['stdWrap.']);
			}
		} elseif ($typoScriptObjectName === 'FORM_INT') {
			$this->initialize($typoScript);
			$content = $this->execute();
		}
		return $content;
	}

	/**
	 * Build the models and views and renders the output from the views
	 *
	 * @return string HTML Output
	 */
	public function execute() {
		// Form
		if ($this->showForm()) {
			$content = $this->renderForm();
		} elseif ($this->showConfirmation()) {
			$content = $this->renderConfirmation();
		} else {
			$content = $this->doPostProcessing();
		}
		return $content;
	}

	/**
	 * Check if the form needs to be displayed
	 *
	 * This is TRUE when nothing has been submitted,
	 * when data has been submitted but the validation rules do not fit
	 * or when the user returns from the confirmation screen.
	 *
	 * @return bool TRUE when form needs to be shown
	 */
	protected function showForm() {
		$show = FALSE;
		$submittedByPrefix = $this->requestHandler->getByMethod();
		if (
			$submittedByPrefix === NULL ||
			!empty($submittedByPrefix) && !$this->validate->isValid() ||
			!empty($submittedByPrefix) && $this->validate->isValid() &&
			$this->requestHandler->getPost('confirmation-false', NULL) !== NULL
		) {
			$show = TRUE;
		}
		return $show;
	}

	/**
	 * Render the form
	 *
	 * @return string The form HTML
	 */
	protected function renderForm() {
		$layout = $this->typoscriptFactory->getLayoutFromTypoScript($this->typoscript['form.']);
		$this->layoutHandler->setLayout($layout);
		$this->requestHandler->destroySession();

		$form = $this->typoscriptFactory->buildModelFromTyposcript($this->typoscript);
		/** @var $view \TYPO3\CMS\Form\View\Form\FormView */
		$view = GeneralUtility::makeInstance(\TYPO3\CMS\Form\View\Form\FormView::class, $form);
		return $view->get();
	}

	/**
	 * Check if the confirmation message needs to be displayed
	 *
	 * This is TRUE when data has been submitted,
	 * the validation rules are valid,
	 * the confirmation screen has been configured in TypoScript
	 * and the confirmation screen has not been submitted
	 *
	 * @return bool TRUE when confirmation screen needs to be shown
	 */
	protected function showConfirmation() {
		$show = FALSE;
		if (isset($this->typoscript['confirmation']) && $this->typoscript['confirmation'] == 1 && $this->requestHandler->getPost('confirmation-true', NULL) === NULL) {
			$show = TRUE;
		}
		return $show;
	}

	/**
	 * Render the confirmation screen
	 *
	 * Stores the submitted data in a session
	 *
	 * @return string The confirmation screen HTML
	 */
	protected function renderConfirmation() {
		$confirmationTyposcript = array();
		if (isset($this->typoscript['confirmation.'])) {
			$confirmationTyposcript = $this->typoscript['confirmation.'];
		}

		$layout = $this->typoscriptFactory->getLayoutFromTypoScript($confirmationTyposcript);
		$form = $this->typoscriptFactory->buildModelFromTyposcript($this->typoscript);

		$this->layoutHandler->setLayout($layout);
		$this->requestHandler->storeSession();
		/** @var $view \TYPO3\CMS\Form\View\Confirmation\ConfirmationView */
		$view = GeneralUtility::makeInstance(\TYPO3\CMS\Form\View\Confirmation\ConfirmationView::class, $form, $confirmationTyposcript);
		return $view->get();
	}

	/**
	 * Do the post processing
	 *
	 * Destroys the session because it is not needed anymore
	 *
	 * @return string The post processing HTML
	 */
	protected function doPostProcessing() {
		$form = $this->typoscriptFactory->buildModelFromTyposcript($this->typoscript);
		$postProcessorTypoScript = array();
		if (isset($this->typoscript['postProcessor.'])) {
			$postProcessorTypoScript = $this->typoscript['postProcessor.'];
		}
		/** @var $postProcessor \TYPO3\CMS\Form\PostProcess\PostProcessor */
		$postProcessor = GeneralUtility::makeInstance(\TYPO3\CMS\Form\PostProcess\PostProcessor::class, $form, $postProcessorTypoScript);
		$content = $postProcessor->process();
		$this->requestHandler->destroySession();
		return $content;
	}

}
