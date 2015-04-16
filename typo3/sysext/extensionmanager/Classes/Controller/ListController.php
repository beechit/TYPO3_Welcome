<?php
namespace TYPO3\CMS\Extensionmanager\Controller;

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
 * Controller for extension listings (TER or local extensions)
 *
 * @author Susanne Moog <typo3@susannemoog.de>
 */
class ListController extends AbstractController {

	/**
	 * @var \TYPO3\CMS\Extensionmanager\Domain\Repository\ExtensionRepository
	 * @inject
	 */
	protected $extensionRepository;

	/**
	 * @var \TYPO3\CMS\Extensionmanager\Utility\ListUtility
	 * @inject
	 */
	protected $listUtility;

	/**
	 * @var \TYPO3\CMS\Core\Page\PageRenderer
	 * @inject
	 */
	protected $pageRenderer;

	/**
	 * @var \TYPO3\CMS\Extensionmanager\Utility\DependencyUtility
	 * @inject
	 */
	protected $dependencyUtility;

	/**
	 * Add the needed JavaScript files for all actions
	 */
	public function initializeAction() {
		$this->pageRenderer->addJsFile('sysext/backend/Resources/Public/JavaScript/notifications.js');
		$this->pageRenderer->addInlineLanguageLabelFile('EXT:extensionmanager/Resources/Private/Language/locallang.xlf');
	}

	/**
	 * Shows list of extensions present in the system
	 *
	 * @return void
	 */
	public function indexAction() {
		$availableAndInstalledExtensions = $this->listUtility->getAvailableAndInstalledExtensionsWithAdditionalInformation();
		$this->view->assign('extensions', $availableAndInstalledExtensions);
		$this->handleTriggerArguments();
	}

	/**
	 * Shows a list of unresolved dependency errors with the possibility to bypass the dependency check
	 *
	 * @param string $extensionKey
	 * @throws \TYPO3\CMS\Extensionmanager\Exception\ExtensionManagerException
	 * @return void
	 */
	public function unresolvedDependenciesAction($extensionKey) {
		$availableExtensions = $this->listUtility->getAvailableExtensions();
		if (isset($availableExtensions[$extensionKey])) {
			$extensionArray = $this->listUtility->enrichExtensionsWithEmConfAndTerInformation(
				array(
					$extensionKey => $availableExtensions[$extensionKey]
				)
			);
			/** @var \TYPO3\CMS\Extensionmanager\Utility\ExtensionModelUtility $extensionModelUtility */
			$extensionModelUtility = $this->objectManager->get(\TYPO3\CMS\Extensionmanager\Utility\ExtensionModelUtility::class);
			$extension = $extensionModelUtility->mapExtensionArrayToModel($extensionArray[$extensionKey]);
		} else {
			throw new \TYPO3\CMS\Extensionmanager\Exception\ExtensionManagerException('Extension ' . $extensionKey . ' is not available', 1402421007);
		}
		$this->dependencyUtility->checkDependencies($extension);
		$this->view->assign('extension', $extension);
		$this->view->assign('unresolvedDependencies', $this->dependencyUtility->getDependencyErrors());
	}

	/**
	 * Shows extensions from TER
	 * Either all extensions or depending on a search param
	 *
	 * @param string $search
	 * @return void
	 */
	public function terAction($search = '') {
		if (!empty($search)) {
			$extensions = $this->extensionRepository->findByTitleOrAuthorNameOrExtensionKey($search);
		} else {
			$extensions = $this->extensionRepository->findAll();
		}
		$availableAndInstalledExtensions = $this->listUtility->getAvailableAndInstalledExtensionsWithAdditionalInformation();
		$this->view->assign('extensions', $extensions)
				->assign('search', $search)
				->assign('availableAndInstalled', $availableAndInstalledExtensions);
	}

	/**
	 * Action for listing all possible distributions
	 *
	 * @return void
	 */
	public function distributionsAction() {
		$importExportInstalled = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('impexp');
		if ($importExportInstalled) {
			// check if a TER update has been done at all, if not, fetch it directly
			/** @var $repositoryHelper \TYPO3\CMS\Extensionmanager\Utility\Repository\Helper */
			$repositoryHelper = $this->objectManager->get(\TYPO3\CMS\Extensionmanager\Utility\Repository\Helper::class);
			// repository needs an update, but not because of the extension hash has changed
			if ($repositoryHelper->isExtListUpdateNecessary() > 0 && ($repositoryHelper->isExtListUpdateNecessary() & $repositoryHelper::PROBLEM_EXTENSION_HASH_CHANGED) === 0) {
				$repositoryHelper->fetchExtListFile();
				$repositoryHelper->updateExtList();
			}

			$officialDistributions = $this->extensionRepository->findAllOfficialDistributions();
			$this->view->assign('officialDistributions', $officialDistributions);

			$communityDistributions = $this->extensionRepository->findAllCommunityDistributions();
			$this->view->assign('communityDistributions', $communityDistributions);
		}
		$this->view->assign('enableDistributionsView', $importExportInstalled);
	}

	/**
	 * Shows all versions of a specific extension
	 *
	 * @param string $extensionKey
	 * @return void
	 */
	public function showAllVersionsAction($extensionKey) {
		$currentVersion = $this->extensionRepository->findOneByCurrentVersionByExtensionKey($extensionKey);
		$extensions = $this->extensionRepository->findByExtensionKeyOrderedByVersion($extensionKey);

		$this->view->assignMultiple(
			array(
				'extensionKey' => $extensionKey,
				'currentVersion' => $currentVersion,
				'extensions' => $extensions
			)
		);
	}

}
