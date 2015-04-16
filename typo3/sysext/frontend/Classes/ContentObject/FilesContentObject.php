<?php
namespace TYPO3\CMS\Frontend\ContentObject;

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
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * Contains FILES content object
 *
 * @author Ingmar Schlecht <ingmar@typo3.org>
 */
class FilesContentObject extends AbstractContentObject {

	/**
	 * @var \TYPO3\CMS\Core\Resource\FileCollectionRepository|NULL
	 */
	protected $collectionRepository = NULL;

	/**
	 * @var \TYPO3\CMS\Core\Resource\ResourceFactory|NULL
	 */
	protected $fileFactory = NULL;

	/**
	 * @var \TYPO3\CMS\Core\Resource\FileRepository|NULL
	 */
	protected $fileRepository = NULL;

	/**
	 * Rendering the cObject FILES
	 *
	 * @param array $conf Array of TypoScript properties
	 * @return string Output
	 */
	public function render($conf = array()) {
		if (!empty($conf['if.']) && !$this->cObj->checkIf($conf['if.'])) {
			return '';
		}

		$fileObjects = array();
		// Getting the files
		if ($conf['references'] || $conf['references.']) {
			/*
			The TypoScript could look like this:# all items related to the page.media field:
			references {
			table = pages
			uid.data = page:uid
			fieldName = media
			}# or: sys_file_references with uid 27:
			references = 27
			 */
			$referencesUid = $this->cObj->stdWrapValue('references', $conf);
			$referencesUidArray = GeneralUtility::intExplode(',', $referencesUid, TRUE);
			foreach ($referencesUidArray as $referenceUid) {
				try {
					$this->addToArray(
						$this->getFileFactory()->getFileReferenceObject($referenceUid),
						$fileObjects
					);
				} catch (\TYPO3\CMS\Core\Resource\Exception $e) {
					/** @var \TYPO3\CMS\Core\Log\Logger $logger */
					$logger = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Log\LogManager::class)->getLogger(__CLASS__);
					$logger->warning('The file-reference with uid  "' . $referenceUid . '" could not be found and won\'t be included in frontend output');
				}
			}

			$this->handleFileReferences($conf, (array)$this->cObj->data, $fileObjects);
		}
		if ($conf['files'] || $conf['files.']) {
			/*
			The TypoScript could look like this:
			# with sys_file UIDs:
			files = 12,14,15# using stdWrap:
			files.field = some_field
			 */
			$fileUids = GeneralUtility::intExplode(',', $this->cObj->stdWrapValue('files', $conf), TRUE);
			foreach ($fileUids as $fileUid) {
				try {
					$this->addToArray($this->getFileFactory()->getFileObject($fileUid), $fileObjects);
				} catch (\TYPO3\CMS\Core\Resource\Exception $e) {
					/** @var \TYPO3\CMS\Core\Log\Logger $logger */
					$logger = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Log\LogManager::class)->getLogger(__CLASS__);
					$logger->warning('The file with uid  "' . $fileUid . '" could not be found and won\'t be included in frontend output');
				}
			}
		}
		if ($conf['collections'] || $conf['collections.']) {
			$collectionUids = GeneralUtility::intExplode(',', $this->cObj->stdWrapValue('collections', $conf), TRUE);
			foreach ($collectionUids as $collectionUid) {
				try {
					$fileCollection = $this->getCollectionRepository()->findByUid($collectionUid);
					if ($fileCollection instanceof \TYPO3\CMS\Core\Resource\Collection\AbstractFileCollection) {
						$fileCollection->loadContents();
						$this->addToArray($fileCollection->getItems(), $fileObjects);
					}
				} catch (\TYPO3\CMS\Core\Resource\Exception $e) {
					/** @var \TYPO3\CMS\Core\Log\Logger $logger */
					$logger = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Log\LogManager::class)->getLogger(__CLASS__);
					$logger->warning('The file-collection with uid  "' . $collectionUid . '" could not be found or contents could not be loaded and won\'t be included in frontend output');
				}
			}
		}
		if ($conf['folders'] || $conf['folders.']) {
			$folderIdentifiers = GeneralUtility::trimExplode(',', $this->cObj->stdWrapValue('folders', $conf));
			foreach ($folderIdentifiers as $folderIdentifier) {
				if ($folderIdentifier) {
					try {
						$folder = $this->getFileFactory()->getFolderObjectFromCombinedIdentifier($folderIdentifier);
						if ($folder instanceof \TYPO3\CMS\Core\Resource\Folder) {
							$this->addToArray(array_values($folder->getFiles()), $fileObjects);
						}
					} catch (\TYPO3\CMS\Core\Resource\Exception $e) {
						/** @var \TYPO3\CMS\Core\Log\Logger $logger */
						$logger = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Log\LogManager::class)->getLogger(__CLASS__);
						$logger->warning('The folder with identifier  "' . $folderIdentifier . '" could not be found and won\'t be included in frontend output');
					}
				}
			}
		}
		// Rendering the files
		$content = '';
		// optionSplit applied to conf to allow differnt settings per file
		$splitConf = $GLOBALS['TSFE']->tmpl->splitConfArray($conf, count($fileObjects));

		// Enable sorting for multiple fileObjects
		$sortingProperty = '';
		if ($conf['sorting'] || $conf['sorting.']) {
			$sortingProperty = $this->cObj->stdWrapValue('sorting', $conf);
		}
		if ($sortingProperty !== '' && count($fileObjects) > 1) {
			@usort($fileObjects, function(\TYPO3\CMS\Core\Resource\FileInterface $a, \TYPO3\CMS\Core\Resource\FileInterface $b) use($sortingProperty) {
				if ($a->hasProperty($sortingProperty) && $b->hasProperty($sortingProperty)) {
					return strnatcasecmp($a->getProperty($sortingProperty), $b->getProperty($sortingProperty));
				} else {
					return 0;
				}
			});
			$sortingDirection = isset($conf['sorting.']['direction']) ? $conf['sorting.']['direction'] : '';
			if (isset($conf['sorting.']['direction.'])) {
				$sortingDirection = $this->cObj->stdWrap($sortingDirection, $conf['sorting.']['direction.']);
			}
			if (strtolower($sortingDirection) === 'desc') {
				$fileObjects = array_reverse($fileObjects);
			}
		}

		$availableFileObjectCount = count($fileObjects);

		$start = 0;
		if (!empty($conf['begin'])) {
			$start = (int)$conf['begin'];
		}
		if (!empty($conf['begin.'])) {
			$start = (int)$this->cObj->stdWrap($start, $conf['begin.']);
		}
		$start = MathUtility::forceIntegerInRange($start, 0, $availableFileObjectCount);

		$limit = $availableFileObjectCount;
		if (!empty($conf['maxItems'])) {
			$limit = (int)$conf['maxItems'];
		}
		if (!empty($conf['maxItems.'])) {
			$limit = (int)$this->cObj->stdWrap($limit, $conf['maxItems.']);
		}

		$end = MathUtility::forceIntegerInRange($start + $limit, $start, $availableFileObjectCount);

		$GLOBALS['TSFE']->register['FILES_COUNT'] = min($limit, $availableFileObjectCount);
		$fileObjectCounter = 0;
		$keys = array_keys($fileObjects);
		for ($i = $start; $i < $end; $i++) {
			$key = $keys[$i];
			$fileObject = $fileObjects[$key];

			$GLOBALS['TSFE']->register['FILE_NUM_CURRENT'] = $fileObjectCounter;
			$this->cObj->setCurrentFile($fileObject);
			$content .= $this->cObj->cObjGetSingle($splitConf[$key]['renderObj'], $splitConf[$key]['renderObj.']);
			$fileObjectCounter++;
		}
		$content = $this->cObj->stdWrap($content, $conf['stdWrap.']);
		return $content;
	}

	/**
	 * Sets the file factory.
	 *
	 * @param \TYPO3\CMS\Core\Resource\ResourceFactory $fileFactory
	 * @return void
	 */
	public function setFileFactory($fileFactory) {
		$this->fileFactory = $fileFactory;
	}

	/**
	 * Returns the file factory.
	 *
	 * @return \TYPO3\CMS\Core\Resource\ResourceFactory
	 */
	public function getFileFactory() {
		if ($this->fileFactory === NULL) {
			$this->fileFactory = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Resource\ResourceFactory::class);
		}

		return $this->fileFactory;
	}

	/**
	 * Sets the file repository.
	 *
	 * @param \TYPO3\CMS\Core\Resource\FileRepository $fileRepository
	 * @return void
	 */
	public function setFileRepository($fileRepository) {
		$this->fileRepository = $fileRepository;
	}

	/**
	 * Returns the file repository.
	 *
	 * @return \TYPO3\CMS\Core\Resource\FileRepository
	 */
	public function getFileRepository() {
		if ($this->fileRepository === NULL) {
			$this->fileRepository = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Resource\FileRepository::class);
		}

		return $this->fileRepository;
	}

	/**
	 * Sets the collection repository.
	 *
	 * @param \TYPO3\CMS\Core\Resource\FileCollectionRepository $collectionRepository
	 * @return void
	 */
	public function setCollectionRepository($collectionRepository) {
		$this->collectionRepository = $collectionRepository;
	}

	/**
	 * Returns the collection repository.
	 *
	 * @return \TYPO3\CMS\Core\Resource\FileCollectionRepository
	 */
	public function getCollectionRepository() {
		if ($this->collectionRepository === NULL) {
			$this->collectionRepository = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Resource\FileCollectionRepository::class);
		}

		return $this->collectionRepository;
	}

	/**
	 * Handles and resolves file references.
	 *
	 * @param array $configuration TypoScript configuration
	 * @param array $element The parent element referencing to files
	 * @param array $fileObjects Collection of file objects
	 * @return void
	 */
	protected function handleFileReferences(array $configuration, array $element, array &$fileObjects) {
		if (empty($configuration['references.'])) {
			return;
		}

		// It's important that this always stays "fieldName" and not be renamed to "field" as it would otherwise collide with the stdWrap key of that name
		$referencesFieldName = $this->cObj->stdWrapValue('fieldName', $configuration['references.']);

		// If no reference fieldName is set, there's nothing to do
		if (empty($referencesFieldName)) {
			return;
		}

		$currentId = !empty($element['uid']) ? $element['uid'] : 0;
		$tableName = $this->cObj->getCurrentTable();

		// Fetch the references of the default element
		$referencesForeignTable = $this->cObj->stdWrapValue('table', $configuration['references.'], $tableName);
		$referencesForeignUid = $this->cObj->stdWrapValue('uid', $configuration['references.'], $currentId);

		$pageRepository = $this->getPageRepository();
		// Fetch element if definition has been modified via TypoScript
		if ($referencesForeignTable !== $tableName || $referencesForeignUid !== $currentId) {
			$element = $pageRepository->getRawRecord(
				$referencesForeignTable,
				$referencesForeignUid,
				'*',
				FALSE
			);

			$pageRepository->versionOL($referencesForeignTable, $element, TRUE);
			if ($referencesForeignTable === 'pages') {
				$element = $pageRepository->getPageOverlay($element);
			} else {
				$element = $pageRepository->getRecordOverlay(
					$referencesForeignTable,
					$element,
					$GLOBALS['TSFE']->sys_language_content,
					$GLOBALS['TSFE']->sys_language_contentOL
				);
			}
		}

		$references = $pageRepository->getFileReferences(
			$referencesForeignTable,
			$referencesFieldName,
			$element
		);

		$this->addToArray($references, $fileObjects);
	}

	/**
	 * Adds $newItems to $theArray, which is passed by reference. Array must only consist of numerical keys.
	 *
	 * @param mixed $newItems Array with new items or single object that's added.
	 * @param array $theArray The array the new items should be added to. Must only contain numeric keys (for array_merge() to add items instead of replacing).
	 */
	protected function addToArray($newItems, array &$theArray) {
		if (is_array($newItems)) {
			$theArray = array_merge($theArray, $newItems);
		} elseif (is_object($newItems)) {
			$theArray[] = $newItems;
		}
	}

	/**
	 * @return \TYPO3\CMS\Frontend\Page\PageRepository
	 */
	protected function getPageRepository() {
		return $GLOBALS['TSFE']->sys_page;
	}

}
