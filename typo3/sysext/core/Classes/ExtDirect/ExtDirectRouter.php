<?php
namespace TYPO3\CMS\Core\ExtDirect;

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

/**
 * Ext Direct Router
 *
 * @author Sebastian Kurfürst <sebastian@typo3.org>
 * @author Stefan Galinski <stefan.galinski@gmail.com>
 */
class ExtDirectRouter {

	/**
	 * Dispatches the incoming calls to methods about the ExtDirect API.
	 *
	 * @param aray $ajaxParams Ajax parameters
	 * @param \TYPO3\CMS\Core\Http\AjaxRequestHandler $ajaxObj Ajax object
	 * @return void
	 */
	public function route($ajaxParams, \TYPO3\CMS\Core\Http\AjaxRequestHandler $ajaxObj) {
		$GLOBALS['error'] = GeneralUtility::makeInstance(\TYPO3\CMS\Core\ExtDirect\ExtDirectDebug::class);
		$isForm = FALSE;
		$isUpload = FALSE;
		$rawPostData = file_get_contents('php://input');
		$postParameters = GeneralUtility::_POST();
		$namespace = GeneralUtility::_GET('namespace');
		$response = array();
		$request = NULL;
		$isValidRequest = TRUE;
		if (!empty($postParameters['extAction'])) {
			$isForm = TRUE;
			$isUpload = $postParameters['extUpload'] === 'true';
			$request = new \stdClass();
			$request->action = $postParameters['extAction'];
			$request->method = $postParameters['extMethod'];
			$request->tid = $postParameters['extTID'];
			unset($_POST['securityToken']);
			$request->data = array($_POST + $_FILES);
			$request->data[] = $postParameters['securityToken'];
		} elseif (!empty($rawPostData)) {
			$request = json_decode($rawPostData);
		} else {
			$response[] = array(
				'type' => 'exception',
				'message' => 'Something went wrong with an ExtDirect call!',
				'code' => 'router'
			);
			$isValidRequest = FALSE;
		}
		if (!is_array($request)) {
			$request = array($request);
		}
		if ($isValidRequest) {
			$validToken = FALSE;
			$firstCall = TRUE;
			foreach ($request as $index => $singleRequest) {
				$response[$index] = array(
					'tid' => $singleRequest->tid,
					'action' => $singleRequest->action,
					'method' => $singleRequest->method
				);
				$token = array_pop($singleRequest->data);
				if ($firstCall) {
					$firstCall = FALSE;
					$formprotection = \TYPO3\CMS\Core\FormProtection\FormProtectionFactory::get();
					$validToken = $formprotection->validateToken($token, 'extDirect');
				}
				try {
					if (!$validToken) {
						throw new \TYPO3\CMS\Core\FormProtection\Exception('ExtDirect: Invalid Security Token!');
					}
					$response[$index]['type'] = 'rpc';
					$response[$index]['result'] = $this->processRpc($singleRequest, $namespace);
					$response[$index]['debug'] = $GLOBALS['error']->toString();
				} catch (\Exception $exception) {
					$response[$index]['type'] = 'exception';
					$response[$index]['message'] = $exception->getMessage();
					$response[$index]['code'] = 'router';
				}
			}
		}
		if ($isForm && $isUpload) {
			$ajaxObj->setContentFormat('plain');
			$response = json_encode($response);
			$response = preg_replace('/&quot;/', '\\&quot;', $response);
			$response = array(
				'<html><body><textarea>' . $response . '</textarea></body></html>'
			);
		} else {
			$ajaxObj->setContentFormat('jsonbody');
		}
		$ajaxObj->setContent($response);
	}

	/**
	 * Processes an incoming extDirect call by executing the defined method. The configuration
	 * array "$GLOBALS['TYPO3_CONF_VARS']['BE']['ExtDirect']" is taken to find the class/method
	 * information.
	 *
	 * @param \stdClass $singleRequest request object from extJS
	 * @param string $namespace namespace like TYPO3.Backend
	 * @return mixed return value of the called method
	 * @throws \UnexpectedValueException if the remote method couldn't be found
	 */
	protected function processRpc($singleRequest, $namespace) {
		$endpointName = $namespace . '.' . $singleRequest->action;
		if (!isset($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ExtDirect'][$endpointName])) {
			throw new \UnexpectedValueException('ExtDirect: Call to undefined endpoint: ' . $endpointName, 1294586450);
		}
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ExtDirect'][$endpointName])) {
			if (!isset($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ExtDirect'][$endpointName]['callbackClass'])) {
				throw new \UnexpectedValueException('ExtDirect: Call to undefined endpoint: ' . $endpointName, 1294586451);
			}
			$callbackClass = $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ExtDirect'][$endpointName]['callbackClass'];
			$configuration = $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ExtDirect'][$endpointName];
			if (!is_null($configuration['moduleName']) && !is_null($configuration['accessLevel'])) {
				$GLOBALS['BE_USER']->modAccess(array(
					'name' => $configuration['moduleName'],
					'access' => $configuration['accessLevel']
				), TRUE);
			}
		}
		$endpointObject = GeneralUtility::getUserObj($callbackClass, FALSE);
		return call_user_func_array(array($endpointObject, $singleRequest->method), is_array($singleRequest->data) ? $singleRequest->data : array());
	}

}
