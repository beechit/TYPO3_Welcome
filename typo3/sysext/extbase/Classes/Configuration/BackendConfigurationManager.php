<?php
namespace TYPO3\CMS\Extbase\Configuration;

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
 * A general purpose configuration manager used in backend mode.
 */
class BackendConfigurationManager extends \TYPO3\CMS\Extbase\Configuration\AbstractConfigurationManager {

	/**
	 * Needed to recursively fetch a page tree
	 *
	 * @var \TYPO3\CMS\Core\Database\QueryGenerator
	 * @inject
	 */
	protected $queryGenerator;

	/**
	 * @var array
	 */
	protected $typoScriptSetupCache = array();

	/**
	 * stores the current page ID
	 * @var int
	 */
	protected $currentPageId;

	/**
	 * Returns TypoScript Setup array from current Environment.
	 *
	 * @return array the raw TypoScript setup
	 */
	public function getTypoScriptSetup() {
		$pageId = $this->getCurrentPageId();

		if (!array_key_exists($pageId, $this->typoScriptSetupCache)) {
			/** @var $template \TYPO3\CMS\Core\TypoScript\TemplateService */
			$template = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\TypoScript\TemplateService::class);
			// do not log time-performance information
			$template->tt_track = 0;
			// Explicitly trigger processing of extension static files
			$template->setProcessExtensionStatics(TRUE);
			$template->init();
			// Get the root line
			$rootline = array();
			if ($pageId > 0) {
				/** @var $sysPage \TYPO3\CMS\Frontend\Page\PageRepository */
				$sysPage = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Frontend\Page\PageRepository::class);
				// Get the rootline for the current page
				$rootline = $sysPage->getRootLine($pageId, '', TRUE);
			}
			// This generates the constants/config + hierarchy info for the template.
			$template->runThroughTemplates($rootline, 0);
			$template->generateConfig();
			$this->typoScriptSetupCache[$pageId] = $template->setup;
		}
		return $this->typoScriptSetupCache[$pageId];
	}

	/**
	 * Returns the TypoScript configuration found in module.tx_yourextension_yourmodule
	 * merged with the global configuration of your extension from module.tx_yourextension
	 *
	 * @param string $extensionName
	 * @param string $pluginName in BE mode this is actually the module signature. But we're using it just like the plugin name in FE
	 * @return array
	 */
	protected function getPluginConfiguration($extensionName, $pluginName = NULL) {
		$setup = $this->getTypoScriptSetup();
		$pluginConfiguration = array();
		if (is_array($setup['module.']['tx_' . strtolower($extensionName) . '.'])) {
			$pluginConfiguration = $this->typoScriptService->convertTypoScriptArrayToPlainArray($setup['module.']['tx_' . strtolower($extensionName) . '.']);
		}
		if ($pluginName !== NULL) {
			$pluginSignature = strtolower($extensionName . '_' . $pluginName);
			if (is_array($setup['module.']['tx_' . $pluginSignature . '.'])) {
				$overruleConfiguration = $this->typoScriptService->convertTypoScriptArrayToPlainArray($setup['module.']['tx_' . $pluginSignature . '.']);
				\TYPO3\CMS\Core\Utility\ArrayUtility::mergeRecursiveWithOverrule($pluginConfiguration, $overruleConfiguration);
			}
		}
		return $pluginConfiguration;
	}

	/**
	 * Returns the configured controller/action pairs of the specified module in the format
	 * array(
	 * 'Controller1' => array('action1', 'action2'),
	 * 'Controller2' => array('action3', 'action4')
	 * )
	 *
	 * @param string $extensionName
	 * @param string $pluginName in BE mode this is actually the module signature. But we're using it just like the plugin name in FE
	 * @return array
	 */
	protected function getSwitchableControllerActions($extensionName, $pluginName) {
		$switchableControllerActions = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extbase']['extensions'][$extensionName]['modules'][$pluginName]['controllers'];
		if (!is_array($switchableControllerActions)) {
			$switchableControllerActions = array();
		}
		return $switchableControllerActions;
	}

	/**
	 * Returns the page uid of the current page.
	 * If no page is selected, we'll return the uid of the first root page.
	 *
	 * @return int current page id. If no page is selected current root page id is returned
	 */
	protected function getCurrentPageId() {
		if ($this->currentPageId !== NULL) {
			return $this->currentPageId;
		}

		$this->currentPageId = $this->getCurrentPageIdFromGetPostData() ?: $this->getCurrentPageIdFromCurrentSiteRoot();
		$this->currentPageId = $this->currentPageId ?: $this->getCurrentPageIdFromRootTemplate();
		$this->currentPageId = $this->currentPageId ?: self::DEFAULT_BACKEND_STORAGE_PID;

		return $this->currentPageId;
	}

	/**
	 * Gets the current page ID from the GET/POST data.
	 *
	 * @return int the page UID, will be 0 if none has been set
	 */
	protected function getCurrentPageIdFromGetPostData() {
		return (int)\TYPO3\CMS\Core\Utility\GeneralUtility::_GP('id');
	}

	/**
	 * Gets the current page ID from the first site root in tree.
	 *
	 * @return int the page UID, will be 0 if none has been set
	 */
	protected function getCurrentPageIdFromCurrentSiteRoot() {
		$rootPage = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow(
			'uid', 'pages', 'deleted=0 AND hidden=0 AND is_siteroot=1', '', 'sorting'
		);
		if (empty($rootPage)) {
			return 0;
		}

		return (int)$rootPage['uid'];
	}

	/**
	 * Gets the current page ID from the first created root template.
	 *
	 * @return int the page UID, will be 0 if none has been set
	 */
	protected function getCurrentPageIdFromRootTemplate() {
		$rootTemplate = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow(
			'pid', 'sys_template', 'deleted=0 AND hidden=0 AND root=1', '', 'crdate'
		);
		if (empty($rootTemplate)) {
			return 0;
		}

		return (int)$rootTemplate['pid'];
	}

	/**
	 * Returns the default backend storage pid
	 *
	 * @return string
	 */
	public function getDefaultBackendStoragePid() {
		return $this->getCurrentPageId();
	}

	/**
	 * We need to set some default request handler if the framework configuration
	 * could not be loaded; to make sure Extbase also works in Backend modules
	 * in all contexts.
	 *
	 * @param array $frameworkConfiguration
	 * @return array
	 */
	protected function getContextSpecificFrameworkConfiguration(array $frameworkConfiguration) {
		if (!isset($frameworkConfiguration['mvc']['requestHandlers'])) {
			$frameworkConfiguration['mvc']['requestHandlers'] = array(
				\TYPO3\CMS\Extbase\Mvc\Web\FrontendRequestHandler::class => \TYPO3\CMS\Extbase\Mvc\Web\FrontendRequestHandler::class,
				\TYPO3\CMS\Extbase\Mvc\Web\BackendRequestHandler::class => \TYPO3\CMS\Extbase\Mvc\Web\BackendRequestHandler::class
			);
		}
		return $frameworkConfiguration;
	}


	/**
	 * Returns a comma separated list of storagePid that are below a certain storage pid.
	 *
	 * @param string $storagePid Storage PID to start at; multiple PIDs possible as comma-separated list
	 * @param int $recursionDepth Maximum number of levels to search, 0 to disable recursive lookup
	 * @return string storage PIDs
	 */
	protected function getRecursiveStoragePids($storagePid, $recursionDepth = 0) {
		if ($recursionDepth <= 0) {
			return $storagePid;
		}

		$recursiveStoragePids = '';
		$storagePids = \TYPO3\CMS\Core\Utility\GeneralUtility::intExplode(',', $storagePid);
		foreach ($storagePids as $startPid) {
			$pids = $this->queryGenerator->getTreeList($startPid, $recursionDepth, 0, 1);
			if ((string)$pids !== '') {
				$recursiveStoragePids .= $pids . ',';
			}
		}

		return rtrim($recursiveStoragePids, ',');
	}

}
