<?php
namespace TYPO3\CMS\Extbase\Mvc\Web\Routing;

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

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * An URI Builder
 *
 * @api
 */
class UriBuilder {

	/**
	 * @var \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface
	 * @inject
	 */
	protected $configurationManager;

	/**
	 * @var \TYPO3\CMS\Extbase\Service\ExtensionService
	 * @inject
	 */
	protected $extensionService;

	/**
	 * An instance of \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer
	 *
	 * @var \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer
	 */
	protected $contentObject;

	/**
	 * @var \TYPO3\CMS\Extbase\Mvc\Web\Request
	 */
	protected $request;

	/**
	 * @var array
	 */
	protected $arguments = array();

	/**
	 * Arguments which have been used for building the last URI
	 *
	 * @var array
	 */
	protected $lastArguments = array();

	/**
	 * @var string
	 */
	protected $section = '';

	/**
	 * @var bool
	 */
	protected $createAbsoluteUri = FALSE;

	/**
	 * @var string
	 */
	protected $absoluteUriScheme = NULL;

	/**
	 * @var bool
	 */
	protected $addQueryString = FALSE;

	/**
	 * @var string
	 */
	protected $addQueryStringMethod = NULL;

	/**
	 * @var array
	 */
	protected $argumentsToBeExcludedFromQueryString = array();

	/**
	 * @var bool
	 */
	protected $linkAccessRestrictedPages = FALSE;

	/**
	 * @var int
	 */
	protected $targetPageUid = NULL;

	/**
	 * @var int
	 */
	protected $targetPageType = 0;

	/**
	 * @var bool
	 */
	protected $noCache = FALSE;

	/**
	 * @var bool
	 */
	protected $useCacheHash = TRUE;

	/**
	 * @var string
	 */
	protected $format = '';

	/**
	 * @var string
	 */
	protected $argumentPrefix = NULL;

	/**
	 * @var \TYPO3\CMS\Extbase\Service\EnvironmentService
	 * @inject
	 */
	protected $environmentService;

	/**
	 * Life-cycle method that is called by the DI container as soon as this object is completely built
	 *
	 * @return void
	 */
	public function initializeObject() {
		$this->contentObject = $this->configurationManager->getContentObject();
	}

	/**
	 * Sets the current request
	 *
	 * @param \TYPO3\CMS\Extbase\Mvc\Request $request
	 * @return \TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder the current UriBuilder to allow method chaining
	 */
	public function setRequest(\TYPO3\CMS\Extbase\Mvc\Request $request) {
		$this->request = $request;
		return $this;
	}

	/**
	 * @return \TYPO3\CMS\Extbase\Mvc\Web\Request
	 */
	public function getRequest() {
		return $this->request;
	}

	/**
	 * Additional query parameters.
	 * If you want to "prefix" arguments, you can pass in multidimensional arrays:
	 * array('prefix1' => array('foo' => 'bar')) gets "&prefix1[foo]=bar"
	 *
	 * @param array $arguments
	 * @return \TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder the current UriBuilder to allow method chaining
	 * @api
	 */
	public function setArguments(array $arguments) {
		$this->arguments = $arguments;
		return $this;
	}

	/**
	 * @return array
	 * @api
	 */
	public function getArguments() {
		return $this->arguments;
	}

	/**
	 * If specified, adds a given HTML anchor to the URI (#...)
	 *
	 * @param string $section
	 * @return \TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder the current UriBuilder to allow method chaining
	 * @api
	 */
	public function setSection($section) {
		$this->section = $section;
		return $this;
	}

	/**
	 * @return string
	 * @api
	 */
	public function getSection() {
		return $this->section;
	}

	/**
	 * Specifies the format of the target (e.g. "html" or "xml")
	 *
	 * @param string $format
	 * @return \TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder the current UriBuilder to allow method chaining
	 * @api
	 */
	public function setFormat($format) {
		$this->format = $format;
		return $this;
	}

	/**
	 * @return string
	 * @api
	 */
	public function getFormat() {
		return $this->format;
	}

	/**
	 * If set, the URI is prepended with the current base URI. Defaults to FALSE.
	 *
	 * @param bool $createAbsoluteUri
	 * @return \TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder the current UriBuilder to allow method chaining
	 * @api
	 */
	public function setCreateAbsoluteUri($createAbsoluteUri) {
		$this->createAbsoluteUri = $createAbsoluteUri;
		return $this;
	}

	/**
	 * @return bool
	 * @api
	 */
	public function getCreateAbsoluteUri() {
		return $this->createAbsoluteUri;
	}

	/**
	 * @return string
	 */
	public function getAbsoluteUriScheme() {
		return $this->absoluteUriScheme;
	}

	/**
	 * Sets the scheme that should be used for absolute URIs in FE mode
	 *
	 * @param string $absoluteUriScheme the scheme to be used for absolute URIs
	 * @return \TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder the current UriBuilder to allow method chaining
	 */
	public function setAbsoluteUriScheme($absoluteUriScheme) {
		$this->absoluteUriScheme = $absoluteUriScheme;
		return $this;
	}

	/**
	 * If set, the current query parameters will be merged with $this->arguments. Defaults to FALSE.
	 *
	 * @param bool $addQueryString
	 * @return \TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder the current UriBuilder to allow method chaining
	 * @api
	 * @see TSref/typolink.addQueryString
	 */
	public function setAddQueryString($addQueryString) {
		$this->addQueryString = (bool)$addQueryString;
		return $this;
	}

	/**
	 * @return bool
	 * @api
	 */
	public function getAddQueryString() {
		return $this->addQueryString;
	}

	/**
	 * Sets the method to get the addQueryString parameters. Defaults undefined
	 * which results in using QUERY_STRING.
	 *
	 * @param string $addQueryStringMethod
	 * @return \TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder the current UriBuilder to allow method chaining
	 * @api
	 * @see TSref/typolink.addQueryString.method
	 */
	public function setAddQueryStringMethod($addQueryStringMethod) {
		$this->addQueryStringMethod = $addQueryStringMethod;
		return $this;
	}

	/**
	 * @return string
	 * @api
	 */
	public function getAddQueryStringMethod() {
		return (string)$this->addQueryStringMethod;
	}

	/**
	 * A list of arguments to be excluded from the query parameters
	 * Only active if addQueryString is set
	 *
	 * @param array $argumentsToBeExcludedFromQueryString
	 * @return \TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder the current UriBuilder to allow method chaining
	 * @api
	 * @see TSref/typolink.addQueryString.exclude
	 * @see setAddQueryString()
	 */
	public function setArgumentsToBeExcludedFromQueryString(array $argumentsToBeExcludedFromQueryString) {
		$this->argumentsToBeExcludedFromQueryString = $argumentsToBeExcludedFromQueryString;
		return $this;
	}

	/**
	 * @return array
	 * @api
	 */
	public function getArgumentsToBeExcludedFromQueryString() {
		return $this->argumentsToBeExcludedFromQueryString;
	}

	/**
	 * Specifies the prefix to be used for all arguments.
	 *
	 * @param string $argumentPrefix
	 * @return \TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder the current UriBuilder to allow method chaining
	 */
	public function setArgumentPrefix($argumentPrefix) {
		$this->argumentPrefix = (string)$argumentPrefix;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getArgumentPrefix() {
		return $this->argumentPrefix;
	}

	/**
	 * If set, URIs for pages without access permissions will be created
	 *
	 * @param bool $linkAccessRestrictedPages
	 * @return \TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder the current UriBuilder to allow method chaining
	 * @api
	 */
	public function setLinkAccessRestrictedPages($linkAccessRestrictedPages) {
		$this->linkAccessRestrictedPages = (bool)$linkAccessRestrictedPages;
		return $this;
	}

	/**
	 * @return bool
	 * @api
	 */
	public function getLinkAccessRestrictedPages() {
		return $this->linkAccessRestrictedPages;
	}

	/**
	 * Uid of the target page
	 *
	 * @param int $targetPageUid
	 * @return \TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder the current UriBuilder to allow method chaining
	 * @api
	 */
	public function setTargetPageUid($targetPageUid) {
		$this->targetPageUid = $targetPageUid;
		return $this;
	}

	/**
	 * returns $this->targetPageUid.
	 *
	 * @return int
	 * @api
	 */
	public function getTargetPageUid() {
		return $this->targetPageUid;
	}

	/**
	 * Sets the page type of the target URI. Defaults to 0
	 *
	 * @param int $targetPageType
	 * @return \TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder the current UriBuilder to allow method chaining
	 * @api
	 */
	public function setTargetPageType($targetPageType) {
		$this->targetPageType = (int)$targetPageType;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getTargetPageType() {
		return $this->targetPageType;
	}

	/**
	 * by default FALSE; if TRUE, &no_cache=1 will be appended to the URI
	 * This overrules the useCacheHash setting
	 *
	 * @param bool $noCache
	 * @return \TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder the current UriBuilder to allow method chaining
	 * @api
	 */
	public function setNoCache($noCache) {
		$this->noCache = (bool)$noCache;
		return $this;
	}

	/**
	 * @return bool
	 * @api
	 */
	public function getNoCache() {
		return $this->noCache;
	}

	/**
	 * by default TRUE; if FALSE, no cHash parameter will be appended to the URI
	 * If noCache is set, this setting will be ignored.
	 *
	 * @param bool $useCacheHash
	 * @return \TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder the current UriBuilder to allow method chaining
	 * @api
	 */
	public function setUseCacheHash($useCacheHash) {
		$this->useCacheHash = (bool)$useCacheHash;
		return $this;
	}

	/**
	 * @return bool
	 * @api
	 */
	public function getUseCacheHash() {
		return $this->useCacheHash;
	}

	/**
	 * Returns the arguments being used for the last URI being built.
	 * This is only set after build() / uriFor() has been called.
	 *
	 * @return array The last arguments
	 */
	public function getLastArguments() {
		return $this->lastArguments;
	}

	/**
	 * Resets all UriBuilder options to their default value
	 *
	 * @return \TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder the current UriBuilder to allow method chaining
	 * @api
	 */
	public function reset() {
		$this->arguments = array();
		$this->section = '';
		$this->format = '';
		$this->createAbsoluteUri = FALSE;
		$this->addQueryString = FALSE;
		$this->addQueryStringMethod = NULL;
		$this->argumentsToBeExcludedFromQueryString = array();
		$this->linkAccessRestrictedPages = FALSE;
		$this->targetPageUid = NULL;
		$this->targetPageType = 0;
		$this->noCache = FALSE;
		$this->useCacheHash = TRUE;
		$this->argumentPrefix = NULL;
		return $this;
	}

	/**
	 * Creates an URI used for linking to an Extbase action.
	 * Works in Frontend and Backend mode of TYPO3.
	 *
	 * @param string $actionName Name of the action to be called
	 * @param array $controllerArguments Additional query parameters. Will be "namespaced" and merged with $this->arguments.
	 * @param string $controllerName Name of the target controller. If not set, current ControllerName is used.
	 * @param string $extensionName Name of the target extension, without underscores. If not set, current ExtensionName is used.
	 * @param string $pluginName Name of the target plugin. If not set, current PluginName is used.
	 * @return string the rendered URI
	 * @api
	 * @see build()
	 */
	public function uriFor($actionName = NULL, $controllerArguments = array(), $controllerName = NULL, $extensionName = NULL, $pluginName = NULL) {
		if ($actionName !== NULL) {
			$controllerArguments['action'] = $actionName;
		}
		if ($controllerName !== NULL) {
			$controllerArguments['controller'] = $controllerName;
		} else {
			$controllerArguments['controller'] = $this->request->getControllerName();
		}
		if ($extensionName === NULL) {
			$extensionName = $this->request->getControllerExtensionName();
		}
		if ($pluginName === NULL && $this->environmentService->isEnvironmentInFrontendMode()) {
			$pluginName = $this->extensionService->getPluginNameByAction($extensionName, $controllerArguments['controller'], $controllerArguments['action']);
		}
		if ($pluginName === NULL) {
			$pluginName = $this->request->getPluginName();
		}
		if ($this->environmentService->isEnvironmentInFrontendMode() && $this->configurationManager->isFeatureEnabled('skipDefaultArguments')) {
			$controllerArguments = $this->removeDefaultControllerAndAction($controllerArguments, $extensionName, $pluginName);
		}
		if ($this->targetPageUid === NULL && $this->environmentService->isEnvironmentInFrontendMode()) {
			$this->targetPageUid = $this->extensionService->getTargetPidByPlugin($extensionName, $pluginName);
		}
		if ($this->format !== '') {
			$controllerArguments['format'] = $this->format;
		}
		if ($this->argumentPrefix !== NULL) {
			$prefixedControllerArguments = array($this->argumentPrefix => $controllerArguments);
		} else {
			$pluginNamespace = $this->extensionService->getPluginNamespace($extensionName, $pluginName);
			$prefixedControllerArguments = array($pluginNamespace => $controllerArguments);
		}
		ArrayUtility::mergeRecursiveWithOverrule($this->arguments, $prefixedControllerArguments);
		return $this->build();
	}

	/**
	 * This removes controller and/or action arguments from given controllerArguments
	 * if they are equal to the default controller/action of the target plugin.
	 * Note: This is only active in FE mode and if feature "skipDefaultArguments" is enabled
	 *
	 * @see \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::isFeatureEnabled()
	 * @param array $controllerArguments the current controller arguments to be modified
	 * @param string $extensionName target extension name
	 * @param string $pluginName target plugin name
	 * @return array
	 */
	protected function removeDefaultControllerAndAction(array $controllerArguments, $extensionName, $pluginName) {
		$defaultControllerName = $this->extensionService->getDefaultControllerNameByPlugin($extensionName, $pluginName);
		if (isset($controllerArguments['action'])) {
			$defaultActionName = $this->extensionService->getDefaultActionNameByPluginAndController($extensionName, $pluginName, $controllerArguments['controller']);
			if ($controllerArguments['action'] === $defaultActionName) {
				unset($controllerArguments['action']);
			}
		}
		if ($controllerArguments['controller'] === $defaultControllerName) {
			unset($controllerArguments['controller']);
		}
		return $controllerArguments;
	}

	/**
	 * Builds the URI
	 * Depending on the current context this calls buildBackendUri() or buildFrontendUri()
	 *
	 * @return string The URI
	 * @api
	 * @see buildBackendUri()
	 * @see buildFrontendUri()
	 */
	public function build() {
		if ($this->environmentService->isEnvironmentInBackendMode()) {
			return $this->buildBackendUri();
		} else {
			return $this->buildFrontendUri();
		}
	}

	/**
	 * Builds the URI, backend flavour
	 * The resulting URI is relative and starts with "mod.php".
	 * The settings pageUid, pageType, noCache, useCacheHash & linkAccessRestrictedPages
	 * will be ignored in the backend.
	 *
	 * @return string The URI
	 */
	public function buildBackendUri() {
		if ($this->addQueryString === TRUE) {
			if ($this->addQueryStringMethod) {
				switch ($this->addQueryStringMethod) {
					case 'GET':
						$arguments = GeneralUtility::_GET();
						break;
					case 'POST':
						$arguments = GeneralUtility::_POST();
						break;
					case 'GET,POST':
						$arguments = array_replace_recursive(GeneralUtility::_GET(), GeneralUtility::_POST());
						break;
					case 'POST,GET':
						$arguments = array_replace_recursive(GeneralUtility::_POST(), GeneralUtility::_GET());
						break;
					default:
						$arguments = GeneralUtility::explodeUrl2Array(GeneralUtility::getIndpEnv('QUERY_STRING'), TRUE);
				}
			} else {
				$arguments = GeneralUtility::_GET();
			}
			foreach ($this->argumentsToBeExcludedFromQueryString as $argumentToBeExcluded) {
				$argumentToBeExcluded = GeneralUtility::explodeUrl2Array($argumentToBeExcluded, TRUE);
				$arguments = ArrayUtility::arrayDiffAssocRecursive($arguments, $argumentToBeExcluded);
			}
		} else {
			$arguments = array(
				'M' => GeneralUtility::_GP('M'),
				'id' => GeneralUtility::_GP('id')
			);
		}
		ArrayUtility::mergeRecursiveWithOverrule($arguments, $this->arguments);
		$arguments = $this->convertDomainObjectsToIdentityArrays($arguments);
		$this->lastArguments = $arguments;
		$moduleName = $arguments['M'];
		unset($arguments['M'], $arguments['moduleToken']);
		$uri = BackendUtility::getModuleUrl($moduleName, $arguments, '');
		if ($this->section !== '') {
			$uri .= '#' . $this->section;
		}
		if ($this->createAbsoluteUri === TRUE) {
			$uri = $this->request->getBaseUri() . $uri;
		}
		return $uri;
	}

	/**
	 * Builds the URI, frontend flavour
	 *
	 * @return string The URI
	 * @see buildTypolinkConfiguration()
	 */
	public function buildFrontendUri() {
		$typolinkConfiguration = $this->buildTypolinkConfiguration();
		if ($this->createAbsoluteUri === TRUE) {
			$typolinkConfiguration['forceAbsoluteUrl'] = TRUE;
			if ($this->absoluteUriScheme !== NULL) {
				$typolinkConfiguration['forceAbsoluteUrl.']['scheme'] = $this->absoluteUriScheme;
			}
		}
		$uri = $this->contentObject->typoLink_URL($typolinkConfiguration);
		return $uri;
	}

	/**
	 * Builds a TypoLink configuration array from the current settings
	 *
	 * @return array typolink configuration array
	 * @see TSref/typolink
	 */
	protected function buildTypolinkConfiguration() {
		$typolinkConfiguration = array();
		$typolinkConfiguration['parameter'] = $this->targetPageUid !== NULL ? $this->targetPageUid : $GLOBALS['TSFE']->id;
		if ($this->targetPageType !== 0) {
			$typolinkConfiguration['parameter'] .= ',' . $this->targetPageType;
		} elseif ($this->format !== '') {
			$targetPageType = $this->extensionService->getTargetPageTypeByFormat($this->request->getControllerExtensionKey(), $this->format);
			$typolinkConfiguration['parameter'] .= ',' . $targetPageType;
		}
		if (count($this->arguments) > 0) {
			$arguments = $this->convertDomainObjectsToIdentityArrays($this->arguments);
			$this->lastArguments = $arguments;
			$typolinkConfiguration['additionalParams'] = GeneralUtility::implodeArrayForUrl(NULL, $arguments);
		}
		if ($this->addQueryString === TRUE) {
			$typolinkConfiguration['addQueryString'] = 1;
			if (count($this->argumentsToBeExcludedFromQueryString) > 0) {
				$typolinkConfiguration['addQueryString.'] = array(
					'exclude' => implode(',', $this->argumentsToBeExcludedFromQueryString)
				);
			}
			if ($this->addQueryStringMethod) {
				$typolinkConfiguration['addQueryString.']['method'] = $this->addQueryStringMethod;
			}
		}
		if ($this->noCache === TRUE) {
			$typolinkConfiguration['no_cache'] = 1;
		} elseif ($this->useCacheHash) {
			$typolinkConfiguration['useCacheHash'] = 1;
		}
		if ($this->section !== '') {
			$typolinkConfiguration['section'] = $this->section;
		}
		if ($this->linkAccessRestrictedPages === TRUE) {
			$typolinkConfiguration['linkAccessRestrictedPages'] = 1;
		}
		return $typolinkConfiguration;
	}

	/**
	 * Recursively iterates through the specified arguments and turns instances of type \TYPO3\CMS\Extbase\DomainObject\AbstractEntity
	 * into an arrays containing the uid of the domain object.
	 *
	 * @param array $arguments The arguments to be iterated
	 * @throws \TYPO3\CMS\Extbase\Mvc\Exception\InvalidArgumentValueException
	 * @return array The modified arguments array
	 */
	protected function convertDomainObjectsToIdentityArrays(array $arguments) {
		foreach ($arguments as $argumentKey => $argumentValue) {
			// if we have a LazyLoadingProxy here, make sure to get the real instance for further processing
			if ($argumentValue instanceof \TYPO3\CMS\Extbase\Persistence\Generic\LazyLoadingProxy) {
				$argumentValue = $argumentValue->_loadRealInstance();
				// also update the value in the arguments array, because the lazyLoaded object could be
				// hidden and thus the $argumentValue would be NULL.
				$arguments[$argumentKey] = $argumentValue;
			}
			if ($argumentValue instanceof \TYPO3\CMS\Extbase\DomainObject\AbstractDomainObject) {
				if ($argumentValue->getUid() !== NULL) {
					$arguments[$argumentKey] = $argumentValue->getUid();
				} elseif ($argumentValue instanceof \TYPO3\CMS\Extbase\DomainObject\AbstractValueObject) {
					$arguments[$argumentKey] = $this->convertTransientObjectToArray($argumentValue);
				} else {
					throw new \TYPO3\CMS\Extbase\Mvc\Exception\InvalidArgumentValueException('Could not serialize Domain Object ' . get_class($argumentValue) . '. It is neither an Entity with identity properties set, nor a Value Object.', 1260881688);
				}
			} elseif (is_array($argumentValue)) {
				$arguments[$argumentKey] = $this->convertDomainObjectsToIdentityArrays($argumentValue);
			}
		}
		return $arguments;
	}

	/**
	 * Converts a given object recursively into an array.
	 *
	 * @param \TYPO3\CMS\Extbase\DomainObject\AbstractDomainObject $object
	 * @return array
	 * @todo Refactore this into convertDomainObjectsToIdentityArrays()
	 */
	public function convertTransientObjectToArray(\TYPO3\CMS\Extbase\DomainObject\AbstractDomainObject $object) {
		$result = array();
		foreach ($object->_getProperties() as $propertyName => $propertyValue) {
			if ($propertyValue instanceof \TYPO3\CMS\Extbase\DomainObject\AbstractDomainObject) {
				if ($propertyValue->getUid() !== NULL) {
					$result[$propertyName] = $propertyValue->getUid();
				} else {
					$result[$propertyName] = $this->convertTransientObjectToArray($propertyValue);
				}
			} elseif (is_array($propertyValue)) {
				$result[$propertyName] = $this->convertDomainObjectsToIdentityArrays($propertyValue);
			} else {
				$result[$propertyName] = $propertyValue;
			}
		}
		return $result;
	}

}
