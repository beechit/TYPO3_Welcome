<?php
namespace TYPO3\CMS\Core\Utility;

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
 * Class to handle mail specific functionality
 *
 * @author Tolleiv Nietsch <nietsch@aoemedia.de>
 */
class MailUtility {

	/**
	 * Gets a valid "from" for mail messages (email and name).
	 *
	 * Ready to be passed to $mail->setFrom()
	 *
	 * @return array key=Valid email address which can be used as sender, value=Valid name which can be used as a sender. NULL if no address is configured
	 */
	static public function getSystemFrom() {
		$address = self::getSystemFromAddress();
		$name = self::getSystemFromName();
		if (!$address) {
			return NULL;
		} elseif ($name) {
			return array($address => $name);
		} else {
			return array($address);
		}
	}

	/**
	 * Creates a valid "from" name for mail messages.
	 *
	 * As configured in Install Tool.
	 *
	 * @return string The name (unquoted, unformatted). NULL if none is set
	 */
	static public function getSystemFromName() {
		if ($GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromName']) {
			return $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromName'];
		} else {
			return NULL;
		}
	}

	/**
	 * Creates a valid email address for the sender of mail messages.
	 *
	 * Uses a fallback chain:
	 * $TYPO3_CONF_VARS['MAIL']['defaultMailFromAddress'] ->
	 * no-reply@FirstDomainRecordFound ->
	 * no-reply@php_uname('n') ->
	 * no-reply@example.com
	 *
	 * Ready to be passed to $mail->setFrom()
	 *
	 * @return string An email address
	 */
	static public function getSystemFromAddress() {
		// default, first check the localconf setting
		$address = $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'];
		if (!GeneralUtility::validEmail($address)) {
			// just get us a domain record we can use as the host
			$host = '';
			$domainRecord = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow('domainName', 'sys_domain', 'hidden = 0', '', 'pid ASC, sorting ASC');
			if (!empty($domainRecord['domainName'])) {
				$tempUrl = $domainRecord['domainName'];
				if (!GeneralUtility::isFirstPartOfStr($tempUrl, 'http')) {
					// shouldn't be the case anyways, but you never know
					// ... there're crazy people out there
					$tempUrl = 'http://' . $tempUrl;
				}
				$host = parse_url($tempUrl, PHP_URL_HOST);
			}
			$address = 'no-reply@' . $host;
			if (!GeneralUtility::validEmail($address)) {
				// still nothing, get host name from server
				$address = 'no-reply@' . php_uname('n');
				if (!GeneralUtility::validEmail($address)) {
					// if everything fails use a dummy address
					$address = 'no-reply@example.com';
				}
			}
		}
		return $address;
	}

	/**
	 * Breaks up a single line of text for emails
	 * Words - longer than $lineWidth - will not be split into parts
	 *
	 * @param string $str The string to break up
	 * @param string $newlineChar The string to implode the broken lines with (default/typically \n)
	 * @param int $lineWidth The line width
	 * @return string Reformated text
	 */
	static public function breakLinesForEmail($str, $newlineChar = LF, $lineWidth = 76) {
		$lines = array();
		$substrStart = 0;
		while (strlen($str) > $substrStart) {
			$substr = substr($str, $substrStart, $lineWidth);
			// has line exceeded (reached) the maximum width?
			if (strlen($substr) == $lineWidth) {
				// find last space-char
				$spacePos = strrpos(rtrim($substr), ' ');
				// space-char found?
				if ($spacePos !== FALSE) {
					// take everything up to last space-char
					$theLine = substr($substr, 0, $spacePos);
					$substrStart++;
				} else {
					// search for space-char in remaining text
					// makes this line longer than $lineWidth!
					$afterParts = explode(' ', substr($str, $lineWidth + $substrStart), 2);
					$theLine = $substr . $afterParts[0];
				}
				if ($theLine === '') {
					// prevent endless loop because of empty line
					break;
				}
			} else {
				$theLine = $substr;
			}
			$lines[] = trim($theLine);
			$substrStart += strlen($theLine);
			if (trim(substr($str, $substrStart, $lineWidth)) === '') {
				// no more text
				break;
			}
		}
		return implode($newlineChar, $lines);
	}

	/**
	 * Parses mailbox headers and turns them into an array.
	 *
	 * Mailbox headers are a comma separated list of 'name <email@example.org>' combinations
	 * or plain email addresses (or a mix of these).
	 * The resulting array has key-value pairs where the key is either a number
	 * (no display name in the mailbox header) and the value is the email address,
	 * or the key is the email address and the value is the display name.
	 *
	 * @param string $rawAddresses Comma separated list of email addresses (optionally with display name)
	 * @return array Parsed list of addresses.
	 */
	static public function parseAddresses($rawAddresses) {
		/** @var $addressParser \TYPO3\CMS\Core\Mail\Rfc822AddressesParser */
		$addressParser = GeneralUtility::makeInstance(
			\TYPO3\CMS\Core\Mail\Rfc822AddressesParser::class,
			$rawAddresses
		);
		$addresses = $addressParser->parseAddressList();
		$addressList = array();
		foreach ($addresses as $address) {
			if ($address->mailbox === '') {
				continue;
			}
			if ($address->personal) {
				// item with name found ( name <email@example.org> )
				$addressList[$address->mailbox . '@' . $address->host] = $address->personal;
			} else {
				// item without name found ( email@example.org )
				$addressList[] = $address->mailbox . '@' . $address->host;
			}
		}
		return $addressList;
	}

}
