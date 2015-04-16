<?php
namespace TYPO3\CMS\Form\PostProcess;

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
 * The mail post processor
 *
 * @author Patrick Broens <patrick@patrickbroens.nl>
 */
class MailPostProcessor implements \TYPO3\CMS\Form\PostProcess\PostProcessorInterface {

	/**
	 * @var Form
	 */
	protected $form;

	/**
	 * @var array
	 */
	protected $typoScript;

	/**
	 * @var \TYPO3\CMS\Core\Mail\MailMessage
	 */
	protected $mailMessage;

	/**
	 * @var \TYPO3\CMS\Form\Request
	 */
	protected $requestHandler;

	/**
	 * @var array
	 */
	protected $dirtyHeaders = array();

	/**
	 * Constructor
	 *
	 * @param Form $form Form domain model
	 * @param array $typoScript Post processor TypoScript settings
	 */
	public function __construct(Form $form, array $typoScript) {
		$this->form = $form;
		$this->typoScript = $typoScript;
		$this->mailMessage = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Mail\MailMessage::class);
		$this->requestHandler = GeneralUtility::makeInstance(\TYPO3\CMS\Form\Request::class);
	}

	/**
	 * The main method called by the post processor
	 *
	 * Configures the mail message
	 *
	 * @return string HTML message from this processor
	 */
	public function process() {
		$this->setSubject();
		$this->setFrom();
		$this->setTo();
		$this->setCc();
		$this->setPriority();
		$this->setOrganization();
		// @todo The whole content rendering seems to be missing here!
		$this->setHtmlContent();
		$this->setPlainContent();
		$this->addAttachmentsFromForm();
		$this->send();
		return $this->render();
	}

	/**
	 * Sets the subject of the mail message
	 *
	 * If not configured, it will use a default setting
	 *
	 * @return void
	 */
	protected function setSubject() {
		if (isset($this->typoScript['subject'])) {
			$subject = $this->typoScript['subject'];
		} elseif ($this->requestHandler->has($this->typoScript['subjectField'])) {
			$subject = $this->requestHandler->get($this->typoScript['subjectField']);
		} else {
			$subject = 'Formmail on ' . GeneralUtility::getIndpEnv('HTTP_HOST');
		}
		$subject = $this->sanitizeHeaderString($subject);
		$this->mailMessage->setSubject($subject);
	}

	/**
	 * Sets the sender of the mail message
	 *
	 * Mostly the sender is a combination of the name and the email address
	 *
	 * @return void
	 */
	protected function setFrom() {
		if ($this->typoScript['senderEmail']) {
			$fromEmail = $this->typoScript['senderEmail'];
		} elseif ($this->requestHandler->has($this->typoScript['senderEmailField'])) {
			$fromEmail = $this->requestHandler->get($this->typoScript['senderEmailField']);
		} else {
			$fromEmail = $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'];
		}
		if (!GeneralUtility::validEmail($fromEmail)) {
			$fromEmail = \TYPO3\CMS\Core\Utility\MailUtility::getSystemFromAddress();
		}
		if ($this->typoScript['senderName']) {
			$fromName = $this->typoScript['senderName'];
		} elseif ($this->requestHandler->has($this->typoScript['senderNameField'])) {
			$fromName = $this->requestHandler->get($this->typoScript['senderNameField']);
		} else {
			$fromName = $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromName'];
		}
		$fromName = $this->sanitizeHeaderString($fromName);
		if (!empty($fromName)) {
			$from = array($fromEmail => $fromName);
		} else {
			$from = $fromEmail;
		}
		$this->mailMessage->setFrom($from);
	}

	/**
	 * Filter input-string for valid email addresses
	 *
	 * @param string $emails If this is a string, it will be checked for one or more valid email addresses.
	 * @return array List of valid email addresses
	 */
	protected function filterValidEmails($emails) {
		if (!is_string($emails)) {
			// No valid addresses - empty list
			return array();
		}

		/** @var $addressParser \TYPO3\CMS\Core\Mail\Rfc822AddressesParser */
		$addressParser = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Mail\Rfc822AddressesParser::class, $emails);
		$addresses = $addressParser->parseAddressList();

		$validEmails = array();
		foreach ($addresses as $address) {
			$fullAddress = $address->mailbox . '@' . $address->host;
			if (GeneralUtility::validEmail($fullAddress)) {
				if ($address->personal) {
					$validEmails[$fullAddress] = $address->personal;
				} else {
					$validEmails[] = $fullAddress;
				}
			}
		}
		return $validEmails;
	}

	/**
	 * Adds the receiver of the mail message when configured
	 *
	 * Checks the address if it is a valid email address
	 *
	 * @return void
	 */
	protected function setTo() {
		$validEmails = $this->filterValidEmails($this->typoScript['recipientEmail']);
		if (count($validEmails)) {
			$this->mailMessage->setTo($validEmails);
		}
	}

	/**
	 * Adds the carbon copy receiver of the mail message when configured
	 *
	 * Checks the address if it is a valid email address
	 *
	 * @return void
	 */
	protected function setCc() {
		$validEmails = $this->filterValidEmails($this->typoScript['ccEmail']);
		if (count($validEmails)) {
			$this->mailMessage->setCc($validEmails);
		}
	}

	/**
	 * Set the priority of the mail message
	 *
	 * When not in settings, the value will be 3. If the priority is configured,
	 * but too big, it will be set to 5, which means very low.
	 *
	 * @return void
	 */
	protected function setPriority() {
		$priority = 3;
		if ($this->typoScript['priority']) {
			$priority = \TYPO3\CMS\Core\Utility\MathUtility::forceIntegerInRange($this->typoScript['priority'], 1, 5);
		}
		$this->mailMessage->setPriority($priority);
	}

	/**
	 * Add a text header to the mail header of the type Organization
	 *
	 * Sanitizes the header string when necessary
	 *
	 * @return void
	 */
	protected function setOrganization() {
		if ($this->typoScript['organization']) {
			$organization = $this->typoScript['organization'];
			$organization = $this->sanitizeHeaderString($organization);
			$this->mailMessage->getHeaders()->addTextHeader('Organization', $organization);
		}
	}

	/**
	 * Set the default character set used
	 *
	 * Respect formMailCharset if it was set, otherwise use metaCharset for mail
	 * if different from renderCharset
	 *
	 * @return void
	 */
	protected function setCharacterSet() {
		$characterSet = NULL;
		if ($GLOBALS['TSFE']->config['config']['formMailCharset']) {
			$characterSet = $GLOBALS['TSFE']->csConvObj->parse_charset($GLOBALS['TSFE']->config['config']['formMailCharset']);
		} elseif ($GLOBALS['TSFE']->metaCharset != $GLOBALS['TSFE']->renderCharset) {
			$characterSet = $GLOBALS['TSFE']->metaCharset;
		}
		if ($characterSet) {
			$this->mailMessage->setCharset($characterSet);
		}
	}

	/**
	 * Add the HTML content
	 *
	 * Add a MimePart of the type text/html to the message.
	 *
	 * @return void
	 */
	protected function setHtmlContent() {
		/** @var $view \TYPO3\CMS\Form\View\Mail\Html\HtmlView */
		$view = GeneralUtility::makeInstance(\TYPO3\CMS\Form\View\Mail\Html\HtmlView::class, $this->form, $this->typoScript);
		$htmlContent = $view->get();
		$this->mailMessage->setBody($htmlContent, 'text/html');
	}

	/**
	 * Add the plain content
	 *
	 * Add a MimePart of the type text/plain to the message.
	 *
	 * @return void
	 */
	protected function setPlainContent() {
		/** @var $view \TYPO3\CMS\Form\View\Mail\Plain\PlainView */
		$view = GeneralUtility::makeInstance(\TYPO3\CMS\Form\View\Mail\Plain\PlainView::class, $this->form);
		$plainContent = $view->render();
		$this->mailMessage->addPart($plainContent, 'text/plain');
	}

	/**
	 * Sends the mail.
	 * Sending the mail requires the recipient and message to be set.
	 *
	 * @return void
	 */
	protected function send() {
		if ($this->mailMessage->getTo() && $this->mailMessage->getBody()) {
			$this->mailMessage->send();
		}
	}

	/**
	 * Render the message after trying to send the mail
	 *
	 * @return string HTML message from the mail view
	 */
	protected function render() {
		/** @var $view \TYPO3\CMS\Form\View\Mail\MailView */
		$view = GeneralUtility::makeInstance(\TYPO3\CMS\Form\View\Mail\MailView::class, $this->mailMessage, $this->typoScript);
		return $view->render();
	}

	/**
	 * Checks string for suspicious characters
	 *
	 * @param string $string String to check
	 * @return string Valid or empty string
	 */
	protected function sanitizeHeaderString($string) {
		$pattern = '/[\\r\\n\\f\\e]/';
		if (preg_match($pattern, $string) > 0) {
			$this->dirtyHeaders[] = $string;
			$string = '';
		}
		return $string;
	}

	/**
	 * Add attachments when uploaded
	 *
	 * @return void
	 */
	protected function addAttachmentsFromForm() {
		$formElements = $this->form->getElements();
		$values = $this->requestHandler->getByMethod();
		$this->addAttachmentsFromElements($formElements, $values);
	}

	/**
	 * Loop through all elements and attach the file when the element
	 * is a fileupload
	 *
	 * @param array $elements
	 * @param array $submittedValues
	 * @return void
	 */
	protected function addAttachmentsFromElements($elements, $submittedValues) {
		/** @var $element \TYPO3\CMS\Form\Domain\Model\Element\AbstractElement */
		foreach ($elements as $element) {
			if (is_a($element, \TYPO3\CMS\Form\Domain\Model\Element\ContainerElement::class)) {
				$this->addAttachmentsFromElements($element->getElements(), $submittedValues);
				continue;
			}
			if (is_a($element, \TYPO3\CMS\Form\Domain\Model\Element\FileuploadElement::class)) {
				$elementName = $element->getName();
				if (is_array($submittedValues[$elementName]) && isset($submittedValues[$elementName]['tempFilename'])) {
					$filename = $submittedValues[$elementName]['tempFilename'];
					if (is_file($filename) && GeneralUtility::isAllowedAbsPath($filename)) {
						$this->mailMessage->attach(\Swift_Attachment::fromPath($filename)->setFilename($submittedValues[$elementName]['originalFilename']));
					}
				}
			}
		}
	}

}
