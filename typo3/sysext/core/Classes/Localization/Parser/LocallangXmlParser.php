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
 * Parser for XML locallang file.
 *
 * @author Dominique Feyer <dfeyer@reelpeek.net>
 */
class LocallangXmlParser extends AbstractXmlParser {

	/**
	 * Associative array of "filename => parsed data" pairs.
	 *
	 * @var array
	 */
	protected $parsedTargetFiles;

	/**
	 * Returns parsed representation of XML file.
	 *
	 * @param string $sourcePath Source file path
	 * @param string $languageKey Language key
	 * @param string $charset Charset
	 * @return array
	 */
	public function getParsedData($sourcePath, $languageKey, $charset = '') {
		$this->sourcePath = $sourcePath;
		$this->languageKey = $languageKey;
		$this->charset = $this->getCharset($languageKey, $charset);
		// Parse source
		$parsedSource = $this->parseXmlFile();
		// Parse target
		$localizedTargetPath = \TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName(\TYPO3\CMS\Core\Utility\GeneralUtility::llXmlAutoFileName($this->sourcePath, $this->languageKey));
		$targetPath = $this->languageKey !== 'default' && @is_file($localizedTargetPath) ? $localizedTargetPath : $this->sourcePath;
		try {
			$parsedTarget = $this->getParsedTargetData($targetPath);
		} catch (\TYPO3\CMS\Core\Localization\Exception\InvalidXmlFileException $e) {
			$parsedTarget = $this->getParsedTargetData($this->sourcePath);
		}
		$LOCAL_LANG = array();
		$LOCAL_LANG[$languageKey] = $parsedSource;
		\TYPO3\CMS\Core\Utility\ArrayUtility::mergeRecursiveWithOverrule($LOCAL_LANG[$languageKey], $parsedTarget);
		return $LOCAL_LANG;
	}

	/**
	 * Returns array representation of XLIFF data, starting from a root node.
	 *
	 * @param SimpleXMLElement $root XML root element
	 * @param string $element Target or Source
	 * @return array
	 */
	protected function doParsingFromRootForElement(\SimpleXMLElement $root, $element) {
		$bodyOfFileTag = $root->data->languageKey;
		// Check if the source llxml file contains localized records
		$localizedBodyOfFileTag = $root->data->xpath('languageKey[@index=\'' . $this->languageKey . '\']');
		if ($element === 'source' || $this->languageKey === 'default') {
			$parsedData = $this->getParsedDataForElement($bodyOfFileTag, $element);
		} else {
			$parsedData = array();
		}
		if ($element === 'target' && isset($localizedBodyOfFileTag[0]) && $localizedBodyOfFileTag[0] instanceof \SimpleXMLElement) {
			$parsedDataTarget = $this->getParsedDataForElement($localizedBodyOfFileTag[0], $element);
			$mergedData = $parsedDataTarget + $parsedData;
			if ($this->languageKey === 'default') {
				$parsedData = array_intersect_key($mergedData, $parsedData, $parsedDataTarget);
			} else {
				$parsedData = array_intersect_key($mergedData, $parsedDataTarget);
			}
		}
		return $parsedData;
	}

	/**
	 * Parse the given language key tag
	 *
	 * @param \SimpleXMLElement $bodyOfFileTag
	 * @param string $element
	 * @return array
	 */
	protected function getParsedDataForElement(\SimpleXMLElement $bodyOfFileTag, $element) {
		$parsedData = array();
		$children = $bodyOfFileTag->children();
		if ($children->count() == 0) {
			// Check for externally-referenced resource:
			// <languageKey index="fr">EXT:yourext/path/to/localized/locallang.xml</languageKey>
			$reference = sprintf('%s', $bodyOfFileTag);
			if (substr($reference, -4) === '.xml') {
				return $this->getParsedTargetData(\TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName($reference));
			}
		}
		/** @var \SimpleXMLElement $translationElement */
		foreach ($children as $translationElement) {
			if ($translationElement->getName() === 'label') {
				// If restype would be set, it could be metadata from Gettext to XLIFF conversion (and we don't need this data)
				$parsedData[(string)$translationElement['index']][0] = array(
					$element => (string)$translationElement
				);
			}
		}
		return $parsedData;
	}

	/**
	 * Returns array representation of XLIFF data, starting from a root node.
	 *
	 * @param \SimpleXMLElement $root A root node
	 * @return array An array representing parsed XLIFF
	 */
	protected function doParsingFromRoot(\SimpleXMLElement $root) {
		return $this->doParsingFromRootForElement($root, 'source');
	}

	/**
	 * Returns array representation of XLIFF data, starting from a root node.
	 *
	 * @param \SimpleXMLElement $root A root node
	 * @return array An array representing parsed XLIFF
	 */
	protected function doParsingTargetFromRoot(\SimpleXMLElement $root) {
		return $this->doParsingFromRootForElement($root, 'target');
	}

	/**
	 * Returns parsed representation of XML file.
	 *
	 * Parses XML if it wasn't done before. Caches parsed data.
	 *
	 * @param string $path An absolute path to XML file
	 * @return array Parsed XML file
	 */
	public function getParsedTargetData($path) {
		if (!isset($this->parsedTargetFiles[$path])) {
			$this->parsedTargetFiles[$path] = $this->parseXmlTargetFile($path);
		}
		return $this->parsedTargetFiles[$path];
	}

	/**
	 * Reads and parses XML file and returns internal representation of data.
	 *
	 * @param string $targetPath Path of the target file
	 * @return array
	 * @throws \TYPO3\CMS\Core\Localization\Exception\InvalidXmlFileException
	 */
	protected function parseXmlTargetFile($targetPath) {
		$rootXmlNode = FALSE;
		if (file_exists($targetPath)) {
			$rootXmlNode = simplexml_load_file($targetPath, 'SimpleXmlElement', \LIBXML_NOWARNING);
		}
		if (!isset($rootXmlNode) || $rootXmlNode === FALSE) {
			throw new \TYPO3\CMS\Core\Localization\Exception\InvalidXmlFileException('The path provided does not point to existing and accessible well-formed XML file (' . $targetPath . ').', 1278155987);
		}
		return $this->doParsingTargetFromRoot($rootXmlNode);
	}

}
