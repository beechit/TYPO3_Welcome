<?php
namespace TYPO3\CMS\Extbase\Mvc\Web;

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
 * Represents a web request.
 *
 * @api
 */
class Request extends \TYPO3\CMS\Extbase\Mvc\Request {

	/**
	 * @var \TYPO3\CMS\Extbase\Security\Cryptography\HashService
	 * @inject
	 */
	protected $hashService;

	/**
	 * @var string The requested representation format
	 */
	protected $format = 'html';

	/**
	 * @var string Contains the request method
	 */
	protected $method = 'GET';

	/**
	 * @var string
	 */
	protected $requestUri;

	/**
	 * @var string The base URI for this request - ie. the host and path leading to the index.php
	 */
	protected $baseUri;

	/**
	 * @var bool TRUE if the current request is cached, false otherwise.
	 */
	protected $isCached = FALSE;

	/**
	 * @var \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface
	 * @inject
	 */
	protected $configurationManager;

	/**
	 * @var \TYPO3\CMS\Extbase\Service\EnvironmentService
	 * @inject
	 */
	protected $environmentService;

	/**
	 * Sets the request method
	 *
	 * @param string $method Name of the request method
	 * @return void
	 * @throws \TYPO3\CMS\Extbase\Mvc\Exception\InvalidRequestMethodException if the request method is not supported
	 */
	public function setMethod($method) {
		if ($method === '' || strtoupper($method) !== $method) {
			throw new \TYPO3\CMS\Extbase\Mvc\Exception\InvalidRequestMethodException('The request method "' . $method . '" is not supported.', 1217778382);
		}
		$this->method = $method;
	}

	/**
	 * Returns the name of the request method
	 *
	 * @return string Name of the request method
	 * @api
	 */
	public function getMethod() {
		return $this->method;
	}

	/**
	 * Sets the request URI
	 *
	 * @param string $requestUri URI of this web request
	 * @return void
	 */
	public function setRequestUri($requestUri) {
		$this->requestUri = $requestUri;
	}

	/**
	 * Returns the request URI
	 *
	 * @return string URI of this web request
	 * @api
	 */
	public function getRequestUri() {
		return $this->requestUri;
	}

	/**
	 * Sets the base URI for this request.
	 *
	 * @param string $baseUri New base URI
	 * @return void
	 */
	public function setBaseUri($baseUri) {
		$this->baseUri = $baseUri;
	}

	/**
	 * Returns the base URI
	 *
	 * @return string Base URI of this web request
	 * @api
	 */
	public function getBaseUri() {
		if ($this->environmentService->isEnvironmentInBackendMode()) {
			return $this->baseUri . TYPO3_mainDir;
		} else {
			return $this->baseUri;
		}
	}

	/**
	 * Set if the current request is cached.
	 *
	 * @param bool $isCached
	 */
	public function setIsCached($isCached) {
		$this->isCached = (bool)$isCached;
	}

	/**
	 * Return whether the current request is a cached request or not.
	 *
	 * @api (v4 only)
	 * @return bool the caching status.
	 */
	public function isCached() {
		return $this->isCached;
	}

	/**
	 * Get a freshly built request object pointing to the Referrer.
	 *
	 * @return Request the referring request, or NULL if no referrer found
	 */
	public function getReferringRequest() {
		if (isset($this->internalArguments['__referrer']) && is_array($this->internalArguments['__referrer'])) {
			$referrerArray = $this->internalArguments['__referrer'];
			$referringRequest = new \TYPO3\CMS\Extbase\Mvc\Web\Request();
			$arguments = array();
			if (isset($referrerArray['arguments'])) {
				$serializedArgumentsWithHmac = $referrerArray['arguments'];
				$serializedArguments = $this->hashService->validateAndStripHmac($serializedArgumentsWithHmac);
				$arguments = unserialize(base64_decode($serializedArguments));
				unset($referrerArray['arguments']);
			}
			$referringRequest->setArguments(\TYPO3\CMS\Extbase\Utility\ArrayUtility::arrayMergeRecursiveOverrule($arguments, $referrerArray));
			return $referringRequest;
		}
		return NULL;
	}

}
