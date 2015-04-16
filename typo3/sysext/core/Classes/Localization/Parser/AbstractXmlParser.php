<?php
namespace TYPO3\CMS\Core\Localization\Parser;

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
 * Abstract class for XML based parser.
 *
 * @author Dominique Feyer <dfeyer@reelpeek.net>
 */
abstract class AbstractXmlParser implements \TYPO3\CMS\Core\Localization\Parser\LocalizationParserInterface {

	/**
	 * @var string
	 */
	protected $sourcePath;

	/**
	 * @var string
	 */
	protected $languageKey;

	/**
	 * @var string
	 */
	protected $charset;

	/**
	 * Returns parsed representation of XML file.
	 *
	 * @param string $sourcePath Source file path
	 * @param string $languageKey Language key
	 * @param string $charset File charset
	 * @return array
	 * @throws \TYPO3\CMS\Core\Localization\Exception\FileNotFoundException
	 */
	public function getParsedData($sourcePath, $languageKey, $charset = '') {
		$this->sourcePath = $sourcePath;
		$this->languageKey = $languageKey;
		$this->charset = $this->getCharset($languageKey, $charset);
		if ($this->languageKey !== 'default') {
			$this->sourcePath = \TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName(\TYPO3\CMS\Core\Utility\GeneralUtility::llXmlAutoFileName($this->sourcePath, $this->languageKey));
			if (!@is_file($this->sourcePath)) {
				// Global localization is not available, try split localization file
				$this->sourcePath = \TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName(\TYPO3\CMS\Core\Utility\GeneralUtility::llXmlAutoFileName($sourcePath, $languageKey, TRUE));
			}
			if (!@is_file($this->sourcePath)) {
				throw new \TYPO3\CMS\Core\Localization\Exception\FileNotFoundException('Localization file does not exist', 1306332397);
			}
		}
		$LOCAL_LANG = array();
		$LOCAL_LANG[$languageKey] = $this->parseXmlFile();
		return $LOCAL_LANG;
	}

	/**
	 * Gets the character set to use.
	 *
	 * @param string $languageKey
	 * @param string $charset
	 * @return string
	 */
	protected function getCharset($languageKey, $charset = '') {
		/** @var $csConvObj \TYPO3\CMS\Core\Charset\CharsetConverter */
		if (is_object($GLOBALS['LANG'])) {
			$csConvObj = $GLOBALS['LANG']->csConvObj;
		} elseif (is_object($GLOBALS['TSFE'])) {
			$csConvObj = $GLOBALS['TSFE']->csConvObj;
		} else {
			$csConvObj = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Charset\CharsetConverter::class);
		}
		if ($charset !== '') {
			$targetCharset = $csConvObj->parse_charset($charset);
		} else {
			$targetCharset = 'utf-8';
		}
		return $targetCharset;
	}

	/**
	 * Loads the current XML file before processing.
	 *
	 * @return array An array representing parsed XML file (structure depends on concrete parser)
	 * @throws \TYPO3\CMS\Core\Localization\Exception\InvalidXmlFileException
	 */
	protected function parseXmlFile() {
		$rootXmlNode = simplexml_load_file($this->sourcePath, 'SimpleXmlElement', \LIBXML_NOWARNING);
		if (!isset($rootXmlNode) || $rootXmlNode === FALSE) {
			throw new \TYPO3\CMS\Core\Localization\Exception\InvalidXmlFileException('The path provided does not point to existing and accessible well-formed XML file.', 1278155988);
		}
		return $this->doParsingFromRoot($rootXmlNode);
	}

	/**
	 * Returns array representation of XML data, starting from a root node.
	 *
	 * @param SimpleXMLElement $root A root node
	 * @return array An array representing the parsed XML file
	 */
	abstract protected function doParsingFromRoot(\SimpleXMLElement $root);

}
