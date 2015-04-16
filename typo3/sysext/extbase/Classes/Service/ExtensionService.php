<?php
namespace TYPO3\CMS\Extbase\Service;

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
 * Service for determining basic extension params
 */
class ExtensionService implements \TYPO3\CMS\Core\SingletonInterface {

	const PLUGIN_TYPE_PLUGIN = 'list_type';
	const PLUGIN_TYPE_CONTENT_ELEMENT = 'CType';

	/**
	 * @var \TYPO3\CMS\Extbase\Object\ObjectManagerInterface
	 * @inject
	 */
	protected $objectManager;

	/**
	 * @var \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface
	 * @inject
	 */
	protected $configurationManager;

	/**
	 * Cache of result for getTargetPidByPlugin()
	 * @var array
	 */
	protected $targetPidPluginCache = array();

	/**
	 * Determines the plugin namespace of the specified plugin (defaults to "tx_[extensionname]_[pluginname]")
	 * If plugin.tx_$pluginSignature.view.pluginNamespace is set, this value is returned
	 * If pluginNamespace is not specified "tx_[extensionname]_[pluginname]" is returned.
	 *
	 * @param string $extensionName name of the extension to retrieve the namespace for
	 * @param string $pluginName name of the plugin to retrieve the namespace for
	 * @return string plugin namespace
	 */
	public function getPluginNamespace($extensionName, $pluginName) {
		$pluginSignature = strtolower($extensionName . '_' . $pluginName);
		$defaultPluginNamespace = 'tx_' . $pluginSignature;
		$frameworkConfiguration = $this->configurationManager->getConfiguration(\TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK, $extensionName, $pluginName);
		if (!isset($frameworkConfiguration['view']['pluginNamespace']) || empty($frameworkConfiguration['view']['pluginNamespace'])) {
			return $defaultPluginNamespace;
		}
		return $frameworkConfiguration['view']['pluginNamespace'];
	}

	/**
	 * Iterates through the global TypoScript configuration and returns the name of the plugin
	 * that matches specified extensionName, controllerName and actionName.
	 * If no matching plugin was found, NULL is returned.
	 * If more than one plugin matches and the current plugin is not configured to handle the action,
	 * an Exception will be thrown
	 *
	 * @param string $extensionName name of the target extension (UpperCamelCase)
	 * @param string $controllerName name of the target controller (UpperCamelCase)
	 * @param string $actionName name of the target action (lowerCamelCase)
	 * @throws \TYPO3\CMS\Extbase\Exception
	 * @return string name of the target plugin (UpperCamelCase) or NULL if no matching plugin configuration was found
	 */
	public function getPluginNameByAction($extensionName, $controllerName, $actionName) {
		$frameworkConfiguration = $this->configurationManager->getConfiguration(\TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK);
		// check, whether the current plugin is configured to handle the action
		if ($extensionName === $frameworkConfiguration['extensionName']) {
			if (isset($frameworkConfiguration['controllerConfiguration'][$controllerName]) && in_array($actionName, $frameworkConfiguration['controllerConfiguration'][$controllerName]['actions'])) {
				return $frameworkConfiguration['pluginName'];
			}
		}
		if (!is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extbase']['extensions'][$extensionName]['plugins'])) {
			return NULL;
		}
		$pluginNames = array();
		foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extbase']['extensions'][$extensionName]['plugins'] as $pluginName => $pluginConfiguration) {
			if (!is_array($pluginConfiguration['controllers'])) {
				continue;
			}
			foreach ($pluginConfiguration['controllers'] as $pluginControllerName => $pluginControllerActions) {
				if (strtolower($pluginControllerName) !== strtolower($controllerName)) {
					continue;
				}
				if (in_array($actionName, $pluginControllerActions['actions'])) {
					$pluginNames[] = $pluginName;
				}
			}
		}
		if (count($pluginNames) > 1) {
			throw new \TYPO3\CMS\Extbase\Exception('There is more than one plugin that can handle this request (Extension: "' . $extensionName . '", Controller: "' . $controllerName . '", action: "' . $actionName . '"). Please specify "pluginName" argument', 1280825466);
		}
		return count($pluginNames) > 0 ? $pluginNames[0] : NULL;
	}

	/**
	 * Checks if the given action is cacheable or not.
	 *
	 * @param string $extensionName Name of the target extension, without underscores
	 * @param string $pluginName Name of the target plugin
	 * @param string $controllerName Name of the target controller
	 * @param string $actionName Name of the action to be called
	 * @return bool TRUE if the specified plugin action is cacheable, otherwise FALSE
	 */
	public function isActionCacheable($extensionName, $pluginName, $controllerName, $actionName) {
		$frameworkConfiguration = $this->configurationManager->getConfiguration(\TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK, $extensionName, $pluginName);
		if (isset($frameworkConfiguration['controllerConfiguration'][$controllerName]) && is_array($frameworkConfiguration['controllerConfiguration'][$controllerName]) && is_array($frameworkConfiguration['controllerConfiguration'][$controllerName]['nonCacheableActions']) && in_array($actionName, $frameworkConfiguration['controllerConfiguration'][$controllerName]['nonCacheableActions'])) {
			return FALSE;
		}
		return TRUE;
	}

	/**
	 * Determines the target page of the specified plugin.
	 * If plugin.tx_$pluginSignature.view.defaultPid is set, this value is used as target page id
	 * If defaultPid is set to "auto", a the target pid is determined by loading the tt_content record that contains this plugin
	 * If the page could not be determined, NULL is returned
	 * If defaultPid is "auto" and more than one page contains the specified plugin, an Exception is thrown
	 *
	 * @param string $extensionName name of the extension to retrieve the target PID for
	 * @param string $pluginName name of the plugin to retrieve the target PID for
	 * @throws \TYPO3\CMS\Extbase\Exception
	 * @return int uid of the target page or NULL if target page could not be determined
	 */
	public function getTargetPidByPlugin($extensionName, $pluginName) {
		$frameworkConfiguration = $this->configurationManager->getConfiguration(\TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK, $extensionName, $pluginName);
		if (!isset($frameworkConfiguration['view']['defaultPid']) || empty($frameworkConfiguration['view']['defaultPid'])) {
			return NULL;
		}
		$pluginSignature = strtolower($extensionName . '_' . $pluginName);
		if ($frameworkConfiguration['view']['defaultPid'] === 'auto') {
			if (!array_key_exists($pluginSignature, $this->targetPidPluginCache)) {
				$pages = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('pid', 'tt_content', 'list_type=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($pluginSignature, 'tt_content') . ' AND CType="list"' . $GLOBALS['TSFE']->sys_page->enableFields('tt_content') . ' AND sys_language_uid=' . $GLOBALS['TSFE']->sys_language_uid, '', '', 2);
				if (count($pages) > 1) {
					throw new \TYPO3\CMS\Extbase\Exception('There is more than one "' . $pluginSignature . '" plugin in the current page tree. Please remove one plugin or set the TypoScript configuration "plugin.tx_' . $pluginSignature . '.view.defaultPid" to a fixed page id', 1280773643);
				}
				$this->targetPidPluginCache[$pluginSignature] = count($pages) > 0 ? $pages[0]['pid'] : NULL;
			}
			return $this->targetPidPluginCache[$pluginSignature];

		}
		return (int)$frameworkConfiguration['view']['defaultPid'];
	}

	/**
	 * This returns the name of the first controller of the given plugin.
	 *
	 * @param string $extensionName name of the extension to retrieve the target PID for
	 * @param string $pluginName name of the plugin to retrieve the target PID for
	 * @return string|NULL
	 */
	public function getDefaultControllerNameByPlugin($extensionName, $pluginName) {
		if (!is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extbase']['extensions'][$extensionName]['plugins'][$pluginName]['controllers'])) {
			return NULL;
		}
		$controllers = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extbase']['extensions'][$extensionName]['plugins'][$pluginName]['controllers'];
		return key($controllers);
	}

	/**
	 * This returns the name of the first action of the given plugin controller.
	 *
	 * @param string $extensionName name of the extension to retrieve the target PID for
	 * @param string $pluginName name of the plugin to retrieve the target PID for
	 * @param string $controllerName name of the controller to retrieve default action for
	 * @return string|NULL
	 */
	public function getDefaultActionNameByPluginAndController($extensionName, $pluginName, $controllerName) {
		if (!is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extbase']['extensions'][$extensionName]['plugins'][$pluginName]['controllers'][$controllerName]['actions'])) {
			return NULL;
		}
		$actions = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extbase']['extensions'][$extensionName]['plugins'][$pluginName]['controllers'][$controllerName]['actions'];
		return current($actions);
	}

	/**
	 * Resolve the page type number to use for building a link for a specific format
	 *
	 * @param string $extensionName name of the extension that has defined the target page type
	 * @param string $format The format for which to look up the page type
	 * @return int Page type number for target page
	 */
	public function getTargetPageTypeByFormat($extensionName, $format) {
		$targetPageType = 0;
		$settings = $this->configurationManager->getConfiguration(\TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS, $extensionName);
		$formatToPageTypeMapping = isset($settings['view']['formatToPageTypeMapping']) ? $settings['view']['formatToPageTypeMapping'] : array();
		if (is_array($formatToPageTypeMapping) && array_key_exists($format, $formatToPageTypeMapping)) {
			$targetPageType = (int)$formatToPageTypeMapping[$format];
		}
		return $targetPageType;
	}

}
