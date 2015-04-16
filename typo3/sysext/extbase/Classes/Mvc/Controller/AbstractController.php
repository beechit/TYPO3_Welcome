<?php
namespace TYPO3\CMS\Extbase\Mvc\Controller;

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

use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Mvc\Exception\StopActionException;
use TYPO3\CMS\Extbase\Mvc\Exception\UnsupportedRequestTypeException;
use TYPO3\CMS\Extbase\Mvc\Web\Request as WebRequest;

/**
 * An abstract base class for Controllers
 *
 * @api
 */
abstract class AbstractController implements ControllerInterface {

	/**
	 * @var \TYPO3\CMS\Extbase\SignalSlot\Dispatcher
	 * @inject
	 */
	protected $signalSlotDispatcher;

	/**
	 * @var \TYPO3\CMS\Extbase\Object\ObjectManagerInterface
	 */
	protected $objectManager;

	/**
	 * @var \TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder
	 */
	protected $uriBuilder;

	/**
	 * @var string Key of the extension this controller belongs to
	 */
	protected $extensionName;

	/**
	 * Contains the settings of the current extension
	 *
	 * @var array
	 * @api
	 */
	protected $settings;

	/**
	 * The current request.
	 *
	 * @var \TYPO3\CMS\Extbase\Mvc\RequestInterface
	 * @api
	 */
	protected $request;

	/**
	 * The response which will be returned by this action controller
	 *
	 * @var \TYPO3\CMS\Extbase\Mvc\ResponseInterface
	 * @api
	 */
	protected $response;

	/**
	 * @var \TYPO3\CMS\Extbase\Validation\ValidatorResolver
	 * @inject
	 */
	protected $validatorResolver;

	/**
	 * @var \TYPO3\CMS\Extbase\Mvc\Controller\Arguments Arguments passed to the controller
	 */
	protected $arguments;

	/**
	 * An array of supported request types. By default only web requests are supported.
	 * Modify or replace this array if your specific controller supports certain
	 * (additional) request types.
	 *
	 * @var array
	 */
	protected $supportedRequestTypes = array(\TYPO3\CMS\Extbase\Mvc\Request::class);

	/**
	 * @var \TYPO3\CMS\Extbase\Mvc\Controller\ControllerContext
	 * @api
	 */
	protected $controllerContext;

	/**
	 * @return ControllerContext
	 * @api
	 */
	public function getControllerContext() {
		return $this->controllerContext;
	}

	/**
	 * @var ConfigurationManagerInterface
	 */
	protected $configurationManager;

	/**
	 * Constructs the controller.
	 */
	public function __construct() {
		$className = get_class($this);
		if (strpos($className, '\\') !== FALSE) {
			$classNameParts = explode('\\', $className, 4);
			// Skip vendor and product name for core classes
			if (strpos($className, 'TYPO3\\CMS\\') === 0) {
				$this->extensionName = $classNameParts[2];
			} else {
				$this->extensionName = $classNameParts[1];
			}
		} else {
			list(, $this->extensionName) = explode('_', $className);
		}
	}

	/**
	 * @param ConfigurationManagerInterface $configurationManager
	 * @return void
	 */
	public function injectConfigurationManager(ConfigurationManagerInterface $configurationManager) {
		$this->configurationManager = $configurationManager;
		$this->settings = $this->configurationManager->getConfiguration(ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS);
	}

	/**
	 * Injects the object manager
	 *
	 * @param \TYPO3\CMS\Extbase\Object\ObjectManagerInterface $objectManager
	 * @return void
	 */
	public function injectObjectManager(\TYPO3\CMS\Extbase\Object\ObjectManagerInterface $objectManager) {
		$this->objectManager = $objectManager;
		$this->arguments = $this->objectManager->get(\TYPO3\CMS\Extbase\Mvc\Controller\Arguments::class);
	}

	/**
	 * Creates a Message object and adds it to the FlashMessageQueue.
	 *
	 * @param string $messageBody The message
	 * @param string $messageTitle Optional message title
	 * @param int $severity Optional severity, must be one of \TYPO3\CMS\Core\Messaging\FlashMessage constants
	 * @param bool $storeInSession Optional, defines whether the message should be stored in the session (default) or not
	 * @return void
	 * @throws \InvalidArgumentException if the message body is no string
	 * @see \TYPO3\CMS\Core\Messaging\FlashMessage
	 * @api
	 */
	public function addFlashMessage($messageBody, $messageTitle = '', $severity = \TYPO3\CMS\Core\Messaging\AbstractMessage::OK, $storeInSession = TRUE) {
		if (!is_string($messageBody)) {
			throw new \InvalidArgumentException('The message body must be of type string, "' . gettype($messageBody) . '" given.', 1243258395);
		}
		/* @var \TYPO3\CMS\Core\Messaging\FlashMessage $flashMessage */
		$flashMessage = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
			\TYPO3\CMS\Core\Messaging\FlashMessage::class, $messageBody, $messageTitle, $severity, $storeInSession
		);
		$this->controllerContext->getFlashMessageQueue()->enqueue($flashMessage);
	}

	/**
	 * Checks if the current request type is supported by the controller.
	 *
	 * If your controller only supports certain request types, either
	 * replace / modify the supportedRequestTypes property or override this
	 * method.
	 *
	 * @param \TYPO3\CMS\Extbase\Mvc\RequestInterface $request The current request
	 * @return bool TRUE if this request type is supported, otherwise FALSE
	 * @api
	 */
	public function canProcessRequest(\TYPO3\CMS\Extbase\Mvc\RequestInterface $request) {
		foreach ($this->supportedRequestTypes as $supportedRequestType) {
			if ($request instanceof $supportedRequestType) {
				return TRUE;
			}
		}
		return FALSE;
	}

	/**
	 * Processes a general request. The result can be returned by altering the given response.
	 *
	 * @param \TYPO3\CMS\Extbase\Mvc\RequestInterface $request The request object
	 * @param \TYPO3\CMS\Extbase\Mvc\ResponseInterface $response The response, modified by this handler
	 * @return void
	 * @throws UnsupportedRequestTypeException if the controller doesn't support the current request type
	 * @api
	 */
	public function processRequest(\TYPO3\CMS\Extbase\Mvc\RequestInterface $request, \TYPO3\CMS\Extbase\Mvc\ResponseInterface $response) {
		if (!$this->canProcessRequest($request)) {
			throw new UnsupportedRequestTypeException(get_class($this) . ' does not support requests of type "' . get_class($request) . '". Supported types are: ' . implode(' ', $this->supportedRequestTypes), 1187701132);
		}
		if ($response instanceof \TYPO3\CMS\Extbase\Mvc\Web\Response && $request instanceof WebRequest) {
			$response->setRequest($request);
		}
		$this->request = $request;
		$this->request->setDispatched(TRUE);
		$this->response = $response;
		$this->uriBuilder = $this->objectManager->get(\TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder::class);
		$this->uriBuilder->setRequest($request);
		$this->initializeControllerArgumentsBaseValidators();
		$this->mapRequestArgumentsToControllerArguments();
		$this->controllerContext = $this->buildControllerContext();
	}

	/**
	 * Initialize the controller context
	 *
	 * @return \TYPO3\CMS\Extbase\Mvc\Controller\ControllerContext ControllerContext to be passed to the view
	 * @api
	 */
	protected function buildControllerContext() {
		/** @var $controllerContext \TYPO3\CMS\Extbase\Mvc\Controller\ControllerContext */
		$controllerContext = $this->objectManager->get(\TYPO3\CMS\Extbase\Mvc\Controller\ControllerContext::class);
		$controllerContext->setRequest($this->request);
		$controllerContext->setResponse($this->response);
		if ($this->arguments !== NULL) {
			$controllerContext->setArguments($this->arguments);
		}
		$controllerContext->setUriBuilder($this->uriBuilder);

		return $controllerContext;
	}

	/**
	 * Forwards the request to another action and / or controller.
	 *
	 * Request is directly transferred to the other action / controller
	 * without the need for a new request.
	 *
	 * @param string $actionName Name of the action to forward to
	 * @param string $controllerName Unqualified object name of the controller to forward to. If not specified, the current controller is used.
	 * @param string $extensionName Name of the extension containing the controller to forward to. If not specified, the current extension is assumed.
	 * @param array $arguments Arguments to pass to the target action
	 * @return void
	 * @throws StopActionException
	 * @see redirect()
	 * @api
	 */
	public function forward($actionName, $controllerName = NULL, $extensionName = NULL, array $arguments = NULL) {
		$this->request->setDispatched(FALSE);
		if ($this->request instanceof WebRequest) {
			$this->request->setControllerActionName($actionName);
			if ($controllerName !== NULL) {
				$this->request->setControllerName($controllerName);
			}
			if ($extensionName !== NULL) {
				$this->request->setControllerExtensionName($extensionName);
			}
		}
		if ($arguments !== NULL) {
			$this->request->setArguments($arguments);
		}
		throw new StopActionException();
	}

	/**
	 * Redirects the request to another action and / or controller.
	 *
	 * Redirect will be sent to the client which then performs another request to the new URI.
	 *
	 * NOTE: This method only supports web requests and will thrown an exception
	 * if used with other request types.
	 *
	 * @param string $actionName Name of the action to forward to
	 * @param string $controllerName Unqualified object name of the controller to forward to. If not specified, the current controller is used.
	 * @param string $extensionName Name of the extension containing the controller to forward to. If not specified, the current extension is assumed.
	 * @param array $arguments Arguments to pass to the target action
	 * @param int $pageUid Target page uid. If NULL, the current page uid is used
	 * @param int $delay (optional) The delay in seconds. Default is no delay.
	 * @param int $statusCode (optional) The HTTP status code for the redirect. Default is "303 See Other
	 * @return void
	 * @throws UnsupportedRequestTypeException If the request is not a web request
	 * @throws StopActionException
	 * @see forward()
	 * @api
	 */
	protected function redirect($actionName, $controllerName = NULL, $extensionName = NULL, array $arguments = NULL, $pageUid = NULL, $delay = 0, $statusCode = 303) {
		if (!$this->request instanceof WebRequest) {
			throw new UnsupportedRequestTypeException('redirect() only supports web requests.', 1220539734);
		}
		if ($controllerName === NULL) {
			$controllerName = $this->request->getControllerName();
		}
		$this->uriBuilder->reset()->setTargetPageUid($pageUid)->setCreateAbsoluteUri(TRUE);
		if (\TYPO3\CMS\Core\Utility\GeneralUtility::getIndpEnv('TYPO3_SSL')) {
			$this->uriBuilder->setAbsoluteUriScheme('https');
		}
		$uri = $this->uriBuilder->uriFor($actionName, $arguments, $controllerName, $extensionName);
		$this->redirectToUri($uri, $delay, $statusCode);
	}

	/**
	 * Redirects the web request to another uri.
	 *
	 * NOTE: This method only supports web requests and will thrown an exception if used with other request types.
	 *
	 * @param mixed $uri A string representation of a URI
	 * @param int $delay (optional) The delay in seconds. Default is no delay.
	 * @param int $statusCode (optional) The HTTP status code for the redirect. Default is "303 See Other
	 * @throws UnsupportedRequestTypeException If the request is not a web request
	 * @throws StopActionException
	 * @api
	 */
	protected function redirectToUri($uri, $delay = 0, $statusCode = 303) {
		if (!$this->request instanceof WebRequest) {
			throw new UnsupportedRequestTypeException('redirect() only supports web requests.', 1220539735);
		}

		$this->objectManager->get(\TYPO3\CMS\Extbase\Service\CacheService::class)->clearCachesOfRegisteredPageIds();

		$uri = $this->addBaseUriIfNecessary($uri);
		$escapedUri = htmlentities($uri, ENT_QUOTES, 'utf-8');
		$this->response->setContent('<html><head><meta http-equiv="refresh" content="' . (int)$delay . ';url=' . $escapedUri . '"/></head></html>');
		if ($this->response instanceof \TYPO3\CMS\Extbase\Mvc\Web\Response) {
			$this->response->setStatus($statusCode);
			$this->response->setHeader('Location', (string)$uri);
		}
		throw new StopActionException();
	}

	/**
	 * Adds the base uri if not already in place.
	 *
	 * @param string $uri The URI
	 * @return string
	 */
	protected function addBaseUriIfNecessary($uri) {
		return \TYPO3\CMS\Core\Utility\GeneralUtility::locationHeaderUrl((string)$uri);
	}

	/**
	 * Sends the specified HTTP status immediately.
	 *
	 * NOTE: This method only supports web requests and will thrown an exception if used with other request types.
	 *
	 * @param int $statusCode The HTTP status code
	 * @param string $statusMessage A custom HTTP status message
	 * @param string $content Body content which further explains the status
	 * @throws UnsupportedRequestTypeException If the request is not a web request
	 * @throws StopActionException
	 * @api
	 */
	public function throwStatus($statusCode, $statusMessage = NULL, $content = NULL) {
		if (!$this->request instanceof WebRequest) {
			throw new UnsupportedRequestTypeException('throwStatus() only supports web requests.', 1220539739);
		}
		if ($this->response instanceof \TYPO3\CMS\Extbase\Mvc\Web\Response) {
			$this->response->setStatus($statusCode, $statusMessage);
			if ($content === NULL) {
				$content = $this->response->getStatus();
			}
		}
		$this->response->setContent($content);
		throw new StopActionException();
	}

	/**
	 * Collects the base validators which were defined for the data type of each
	 * controller argument and adds them to the argument's validator chain.
	 *
	 * @return void
	 */
	public function initializeControllerArgumentsBaseValidators() {
		/** @var \TYPO3\CMS\Extbase\Mvc\Controller\Argument $argument */
		foreach ($this->arguments as $argument) {
			$validator = $this->validatorResolver->getBaseValidatorConjunction($argument->getDataType());
			if ($validator !== NULL) {
				$argument->setValidator($validator);
			}
		}
	}

	/**
	 * Maps arguments delivered by the request object to the local controller arguments.
	 *
	 * @throws Exception\RequiredArgumentMissingException
	 * @return void
	 */
	protected function mapRequestArgumentsToControllerArguments() {
		/** @var \TYPO3\CMS\Extbase\Mvc\Controller\Argument $argument */
		foreach ($this->arguments as $argument) {
			$argumentName = $argument->getName();
			if ($this->request->hasArgument($argumentName)) {
				$argument->setValue($this->request->getArgument($argumentName));
			} elseif ($argument->isRequired()) {
				throw new \TYPO3\CMS\Extbase\Mvc\Controller\Exception\RequiredArgumentMissingException('Required argument "' . $argumentName . '" is not set.', 1298012500);
			}
		}
	}

}
