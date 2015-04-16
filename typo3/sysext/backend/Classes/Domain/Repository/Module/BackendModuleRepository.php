<?php
namespace TYPO3\CMS\Backend\Domain\Repository\Module;

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
use TYPO3\CMS\Backend\Utility\BackendUtility;

/**
 * Repository for backend module menu
 * compiles all data from $GLOBALS[TBE_MODULES]
 *
 * @author Susanne Moog <typo3@susannemoog.de>
 */
class BackendModuleRepository implements \TYPO3\CMS\Core\SingletonInterface {

	/**
	 * @var \TYPO3\CMS\Backend\Module\ModuleStorage
	 */
	protected $moduleStorage;

	/**
	 * Constructs the module menu and gets the Singleton instance of the menu
	 */
	public function __construct() {
		$this->moduleStorage = GeneralUtility::makeInstance(\TYPO3\CMS\Backend\Module\ModuleStorage::class);

		$rawData = $this->getRawModuleMenuData();

		$this->convertRawModuleDataToModuleMenuObject($rawData);
		$this->createMenuEntriesForTbeModulesExt();
	}

	/**
	 * loads all module information in the module storage
	 *
	 * @param array $excludeGroupNames
	 * @return \SplObjectStorage
	 */
	public function loadAllowedModules(array $excludeGroupNames = array()) {
		if (empty($excludeGroupNames)) {
			return $this->moduleStorage->getEntries();
		}

		$modules = new \SplObjectStorage();
		foreach ($this->moduleStorage->getEntries() as $moduleGroup) {
			if (!in_array($moduleGroup->getName(), $excludeGroupNames, TRUE)) {
				$modules->attach($moduleGroup);
			}
		}

		return $modules;
	}

	/**
	 * @param string $groupName
	 * @return \SplObjectStorage|FALSE
	 **/
	public function findByGroupName($groupName = '') {
		foreach ($this->moduleStorage->getEntries() as $moduleGroup) {
			if ($moduleGroup->getName() === $groupName) {
				return $moduleGroup;
			}
		}

		return FALSE;
	}

	/**
	 * Finds a module menu entry by name
	 *
	 * @param string $name
	 * @return \TYPO3\CMS\Backend\Domain\Model\Module\BackendModule|boolean
	 */
	public function findByModuleName($name) {
		$entries = $this->moduleStorage->getEntries();
		$entry = $this->findByModuleNameInGivenEntries($name, $entries);
		return $entry;
	}

	/**
	 * Finds a module menu entry by name in a given storage
	 *
	 * @param string $name
	 * @param \SplObjectStorage $entries
	 * @return \TYPO3\CMS\Backend\Domain\Model\Module\BackendModule|bool
	 */
	public function findByModuleNameInGivenEntries($name, \SplObjectStorage $entries) {
		foreach ($entries as $entry) {
			if ($entry->getName() === $name) {
				return $entry;
			}
			$children = $entry->getChildren();
			if (count($children) > 0) {
				$childRecord = $this->findByModuleNameInGivenEntries($name, $children);
				if ($childRecord !== FALSE) {
					return $childRecord;
				}
			}
		}
		return FALSE;
	}

	/**
	 * Creates the module menu object structure from the raw data array
	 *
	 * @param array $rawModuleData
	 * @return void
	 */
	protected function convertRawModuleDataToModuleMenuObject(array $rawModuleData) {
		foreach ($rawModuleData as $module) {
			$entry = $this->createEntryFromRawData($module);
			if (isset($module['subitems']) && !empty($module['subitems'])) {
				foreach ($module['subitems'] as $subitem) {
					$subEntry = $this->createEntryFromRawData($subitem);
					$entry->addChild($subEntry);
				}
			}
			$this->moduleStorage->attachEntry($entry);
		}
	}

	/**
	 * Creates a menu entry object from an array
	 *
	 * @param array $module
	 * @return \TYPO3\CMS\Backend\Domain\Model\Module\BackendModule
	 */
	protected function createEntryFromRawData(array $module) {
		/** @var $entry \TYPO3\CMS\Backend\Domain\Model\Module\BackendModule */
		$entry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Backend\Domain\Model\Module\BackendModule::class);
		if (!empty($module['name']) && is_string($module['name'])) {
			$entry->setName($module['name']);
		}
		if (!empty($module['title']) && is_string($module['title'])) {
			$entry->setTitle($this->getLanguageService()->sL($module['title']));
		}
		if (!empty($module['onclick']) && is_string($module['onclick'])) {
			$entry->setOnClick($module['onclick']);
		}
		if (!empty($module['link']) && is_string($module['link'])) {
			$entry->setLink($module['link']);
		} elseif (empty($module['link']) && !empty($module['path']) && is_string($module['path'])) {
			$entry->setLink($module['path']);
		}
		if (!empty($module['description']) && is_string($module['description'])) {
			$entry->setDescription($module['description']);
		}
		if (!empty($module['icon']) && is_array($module['icon'])) {
			$entry->setIcon($module['icon']);
		}
		if (!empty($module['navigationComponentId']) && is_string($module['navigationComponentId'])) {
			$entry->setNavigationComponentId($module['navigationComponentId']);
		}
		if (!empty($module['navigationFrameScript']) && is_string($module['navigationFrameScript'])) {
			$entry->setNavigationFrameScript($module['navigationFrameScript']);
		} elseif (!empty($module['parentNavigationFrameScript']) && is_string($module['parentNavigationFrameScript'])) {
			$entry->setNavigationFrameScript($module['parentNavigationFrameScript']);
		}
		if (!empty($module['navigationFrameScriptParam']) && is_string($module['navigationFrameScriptParam'])) {
			$entry->setNavigationFrameScriptParameters($module['navigationFrameScriptParam']);
		}
		return $entry;
	}

	/**
	 * Creates the "third level" menu entries (submodules for the info module for
	 * example) from the TBE_MODULES_EXT array
	 *
	 * @return void
	 */
	protected function createMenuEntriesForTbeModulesExt() {
		foreach ($GLOBALS['TBE_MODULES_EXT'] as $mainModule => $tbeModuleExt) {
			list($main) = explode('_', $mainModule);
			$mainEntry = $this->findByModuleName($main);
			if ($mainEntry === FALSE) {
				continue;
			}

			$subEntries = $mainEntry->getChildren();
			if (empty($subEntries)) {
				continue;
			}
			$matchingSubEntry = $this->findByModuleName($mainModule);
			if ($matchingSubEntry !== FALSE) {
				if (isset($tbeModuleExt['MOD_MENU']) && isset($tbeModuleExt['MOD_MENU']['function'])) {
					foreach ($tbeModuleExt['MOD_MENU']['function'] as $subModule) {
						$entry = $this->createEntryFromRawData($subModule);
						$matchingSubEntry->addChild($entry);
					}
				}
			}
		}
	}

	/**
	 * Return language service instance
	 *
	 * @return \TYPO3\CMS\Lang\LanguageService
	 */
	protected function getLanguageService() {
		return $GLOBALS['LANG'];
	}

	/**
	 * loads the module menu from the moduleloader based on $GLOBALS['TBE_MODULES']
	 * and compiles an array with all the data needed for menu etc.
	 *
	 * @return array
	 */
	public function getRawModuleMenuData() {
		// Loads the backend modules available for the logged in user.
		$moduleLoader = GeneralUtility::makeInstance(\TYPO3\CMS\Backend\Module\ModuleLoader::class);
		$moduleLoader->observeWorkspaces = TRUE;
		$moduleLoader->load($GLOBALS['TBE_MODULES']);
		$loadedModules = $moduleLoader->modules;

		$modules = array();

		// Unset modules that are meant to be hidden from the menu.
		$loadedModules = $this->removeHiddenModules($loadedModules);

		foreach ($loadedModules as $moduleName => $moduleData) {
			$moduleLink = '';
			if (!is_array($moduleData['sub'])) {
				$moduleLink = $moduleData['script'];
			}
			$moduleLink = GeneralUtility::resolveBackPath($moduleLink);
			$moduleKey = 'modmenu_' . $moduleName;
			$moduleIcon = $this->getModuleIcon($moduleKey);
			$modules[$moduleKey] = array(
				'name' => $moduleName,
				'title' => $GLOBALS['LANG']->moduleLabels['tabs'][$moduleName . '_tab'],
				'onclick' => 'top.goToModule(\'' . $moduleName . '\');',
				'icon' => $moduleIcon,
				'link' => $moduleLink,
				'description' => $GLOBALS['LANG']->moduleLabels['labels'][$moduleKey . 'label']
			);
			if (!is_array($moduleData['sub']) && $moduleData['script'] !== 'dummy.php') {
				// Work around for modules with own main entry, but being self the only submodule
				$modules[$moduleKey]['subitems'][$moduleKey] = array(
					'name' => $moduleName,
					'title' => $GLOBALS['LANG']->moduleLabels['tabs'][$moduleName . '_tab'],
					'onclick' => 'top.goToModule(\'' . $moduleName . '\');',
					'icon' => $this->getModuleIcon($moduleName . '_tab'),
					'link' => $moduleLink,
					'originalLink' => $moduleLink,
					'description' => $GLOBALS['LANG']->moduleLabels['labels'][$moduleKey . 'label'],
					'navigationFrameScript' => NULL,
					'navigationFrameScriptParam' => NULL,
					'navigationComponentId' => NULL
				);
			} elseif (is_array($moduleData['sub'])) {
				foreach ($moduleData['sub'] as $submoduleName => $submoduleData) {
					if (isset($submoduleData['script'])) {
						$submoduleLink = GeneralUtility::resolveBackPath($submoduleData['script']);
					} else {
						$submoduleLink = BackendUtility::getModuleUrl($submoduleData['name']);
					}
					$submoduleKey = $moduleName . '_' . $submoduleName . '_tab';
					$submoduleIcon = $this->getModuleIcon($submoduleKey);
					$submoduleDescription = $GLOBALS['LANG']->moduleLabels['labels'][$submoduleKey . 'label'];
					$originalLink = $submoduleLink;
					if (isset($submoduleData['navigationFrameModule'])) {
						$navigationFrameScript = BackendUtility::getModuleUrl(
							$submoduleData['navigationFrameModule'],
							isset($submoduleData['navigationFrameModuleParameters'])
								? $submoduleData['navigationFrameModuleParameters']
								: array()
						);
					} else {
						$navigationFrameScript = $submoduleData['navFrameScript'];
					}
					$modules[$moduleKey]['subitems'][$submoduleKey] = array(
						'name' => $moduleName . '_' . $submoduleName,
						'title' => $GLOBALS['LANG']->moduleLabels['tabs'][$submoduleKey],
						'onclick' => 'top.goToModule(\'' . $moduleName . '_' . $submoduleName . '\');',
						'icon' => $submoduleIcon,
						'link' => $submoduleLink,
						'originalLink' => $originalLink,
						'description' => $submoduleDescription,
						'navigationFrameScript' => $navigationFrameScript,
						'navigationFrameScriptParam' => $submoduleData['navFrameScriptParam'],
						'navigationComponentId' => $submoduleData['navigationComponentId']
					);
					// if the main module has a navframe script, inherit to the submodule,
					// but only if it is not disabled explicitly (option is set to FALSE)
					if ($moduleData['navFrameScript'] && $submoduleData['inheritNavigationComponentFromMainModule'] !== FALSE) {
						$modules[$moduleKey]['subitems'][$submoduleKey]['parentNavigationFrameScript'] = $moduleData['navFrameScript'];
					}
				}
			}
		}
		return $modules;
	}

	/**
	 * Reads User configuration from options.hideModules and removes
	 * modules accordingly.
	 *
	 * @param array $loadedModules
	 * @return array
	 */
	protected function removeHiddenModules($loadedModules) {
		$hiddenModules = $GLOBALS['BE_USER']->getTSConfig('options.hideModules');

		// Hide modules if set in userTS.
		if (!empty($hiddenModules['value'])) {
			$hiddenMainModules = explode(',', $hiddenModules['value']);
			foreach ($hiddenMainModules as $hiddenMainModule) {
				unset($loadedModules[trim($hiddenMainModule)]);
			}
		}

		// Hide sub-modules if set in userTS.
		if (!empty($hiddenModules['properties']) && is_array($hiddenModules['properties'])) {
			foreach ($hiddenModules['properties'] as $mainModuleName => $subModules) {
				$hiddenSubModules = explode(',', $subModules);
				foreach ($hiddenSubModules as $hiddenSubModule) {
					unset($loadedModules[$mainModuleName]['sub'][trim($hiddenSubModule)]);
				}
			}
		}

		return $loadedModules;
	}

	/**
	 * gets the module icon and its size
	 *
	 * @param string $moduleKey Module key
	 * @return array Icon data array with 'filename', 'size', and 'html'
	 */
	protected function getModuleIcon($moduleKey) {
		$icon = array(
			'filename' => '',
			'size' => '',
			'title' => '',
			'html' => ''
		);

		if (!empty($GLOBALS['LANG']->moduleLabels['tabs_images'][$moduleKey])) {
			$imageReference = $GLOBALS['LANG']->moduleLabels['tabs_images'][$moduleKey];
			$iconFileRelative = $this->getModuleIconRelative($imageReference);
			if (!empty($iconFileRelative)) {
				$iconTitle = $GLOBALS['LANG']->moduleLabels['tabs'][$moduleKey];
				$iconFileAbsolute = $this->getModuleIconAbsolute($imageReference);
				$iconSizes = @getimagesize($iconFileAbsolute);
				$icon['filename'] = $iconFileRelative;
				$icon['size'] = $iconSizes[3];
				$icon['title'] = htmlspecialchars($iconTitle);
				$icon['html'] = '<img src="' . $iconFileRelative . '" ' . $iconSizes[3] . ' title="' . htmlspecialchars($iconTitle) . '" alt="' . htmlspecialchars($iconTitle) . '" />';
			}
		}
		return $icon;
	}

	/**
	 * Returns the filename readable for the script from PATH_typo3.
	 * That means absolute names are just returned while relative names are
	 * prepended with the path pointing back to typo3/ dir
	 *
	 * @param string $iconFilename Icon filename
	 * @return string Icon filename with absolute path
	 * @see getModuleIconRelative()
	 */
	protected function getModuleIconAbsolute($iconFilename) {
		if (!GeneralUtility::isAbsPath($iconFilename)) {
			$iconFilename = $GLOBALS['BACK_PATH'] . $iconFilename;
		}
		return $iconFilename;
	}

	/**
	 * Returns relative path to the icon filename for use in img-tags
	 *
	 * @param string $iconFilename Icon filename
	 * @return string Icon filename with relative path
	 * @see getModuleIconAbsolute()
	 */
	protected function getModuleIconRelative($iconFilename) {
		if (GeneralUtility::isAbsPath($iconFilename)) {
			$iconFilename = '../' . \TYPO3\CMS\Core\Utility\PathUtility::stripPathSitePrefix($iconFilename);
		}
		return $iconFilename;
	}

}
