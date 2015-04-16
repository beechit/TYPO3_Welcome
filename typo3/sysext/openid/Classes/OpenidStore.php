<?php
namespace TYPO3\CMS\Openid;

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

require_once \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('openid') . 'lib/php-openid/Auth/OpenID/Interface.php';

/**
 * This class is a TYPO3-specific OpenID store.
 *
 * @author Dmitry Dulepov <dmitry.dulepov@gmail.com>
 */
class OpenidStore extends \Auth_OpenID_OpenIDStore {

	const ASSOCIATION_TABLE_NAME = 'tx_openid_assoc_store';
	const NONCE_TABLE_NAME = 'tx_openid_nonce_store';
	/* 2 minutes */
	const ASSOCIATION_EXPIRATION_SAFETY_INTERVAL = 120;
	/* 10 days */
	const NONCE_STORAGE_TIME = 864000;

	/**
	 * @var \TYPO3\CMS\Core\Database\DatabaseConnection
	 */
	protected $databaseConnection;

	/**
	 * @param null|\TYPO3\CMS\Core\Database\DatabaseConnection $databaseConnection
	 */
	public function __construct($databaseConnection = NULL) {
		$this->databaseConnection = $databaseConnection ?: $GLOBALS['TYPO3_DB'];
	}

	/**
	 * Sores the association for future use
	 *
	 * @param string $serverUrl Server URL
	 * @param \Auth_OpenID_Association $association OpenID association
	 * @return void
	 */
	public function storeAssociation($serverUrl, $association) {
		/* @var $association \Auth_OpenID_Association */
		$this->databaseConnection->sql_query('START TRANSACTION');
		if ($this->doesAssociationExist($serverUrl, $association->handle)) {
			$this->updateExistingAssociation($serverUrl, $association);
		} else {
			$this->storeNewAssociation($serverUrl, $association);
		}
		$this->databaseConnection->sql_query('COMMIT');
	}

	/**
	 * Removes all expired associations.
	 *
	 * @return int A number of removed associations
	 */
	public function cleanupAssociations() {
		$where = sprintf('expires<=%d', time());
		$this->databaseConnection->exec_DELETEquery(self::ASSOCIATION_TABLE_NAME, $where);
		return $this->databaseConnection->sql_affected_rows();
	}

	/**
	 * Obtains the association to the server
	 *
	 * @param string $serverUrl Server URL
	 * @param string $handle Association handle (optional)
	 * @return \Auth_OpenID_Association
	 */
	public function getAssociation($serverUrl, $handle = NULL) {
		$this->cleanupAssociations();
		$where = sprintf('server_url=%s AND expires>%d', $this->databaseConnection->fullQuoteStr($serverUrl, self::ASSOCIATION_TABLE_NAME), time());
		if ($handle != NULL) {
			$where .= sprintf(' AND assoc_handle=%s', $this->databaseConnection->fullQuoteStr($handle, self::ASSOCIATION_TABLE_NAME));
			$sort = '';
		} else {
			$sort = 'tstamp DESC';
		}
		$row = $this->databaseConnection->exec_SELECTgetSingleRow('uid, content', self::ASSOCIATION_TABLE_NAME, $where, '', $sort);
		$result = NULL;
		if (is_array($row)) {
			$result = @unserialize(base64_decode($row['content']));
			if ($result === FALSE) {
				$result = NULL;
			} else {
				$this->updateAssociationTimeStamp($row['tstamp']);
			}
		}
		return $result;
	}

	/**
	 * Removes the association
	 *
	 * @param string $serverUrl Server URL
	 * @param string $handle Association handle (optional)
	 * @return bool TRUE if the association existed
	 */
	public function removeAssociation($serverUrl, $handle) {
		$where = sprintf('server_url=%s AND assoc_handle=%s', $this->databaseConnection->fullQuoteStr($serverUrl, self::ASSOCIATION_TABLE_NAME), $this->databaseConnection->fullQuoteStr($handle, self::ASSOCIATION_TABLE_NAME));
		$this->databaseConnection->exec_DELETEquery(self::ASSOCIATION_TABLE_NAME, $where);
		$deletedCount = $this->databaseConnection->sql_affected_rows();
		return $deletedCount > 0;
	}

	/**
	 * Removes old nonces
	 *
	 * @return void
	 */
	public function cleanupNonces() {
		$where = sprintf('crdate<%d', time() - self::NONCE_STORAGE_TIME);
		$this->databaseConnection->exec_DELETEquery(self::NONCE_TABLE_NAME, $where);
	}

	/**
	 * Checks if this nonce was already used
	 *
	 * @param string $serverUrl Server URL
	 * @param int $timestamp Time stamp
	 * @param string $salt Nonce value
	 * @return bool TRUE if nonce was not used before anc can be used now
	 */
	public function useNonce($serverUrl, $timestamp, $salt) {
		$result = FALSE;
		if (abs($timestamp - time()) < $GLOBALS['Auth_OpenID_SKEW']) {
			$values = array(
				'crdate' => time(),
				'salt' => $salt,
				'server_url' => $serverUrl,
				'tstamp' => $timestamp
			);
			$this->databaseConnection->exec_INSERTquery(self::NONCE_TABLE_NAME, $values);
			$affectedRows = $this->databaseConnection->sql_affected_rows();
			$result = $affectedRows > 0;
		}
		return $result;
	}

	/**
	 * Resets the store by removing all data in it
	 *
	 * @return void
	 */
	public function reset() {
		$this->databaseConnection->exec_TRUNCATEquery(self::ASSOCIATION_TABLE_NAME);
		$this->databaseConnection->exec_TRUNCATEquery(self::NONCE_TABLE_NAME);
	}

	/**
	 * Checks if such association exists.
	 *
	 * @param string $serverUrl Server URL
	 * @param \Auth_OpenID_Association $association OpenID association
	 * @return bool
	 */
	protected function doesAssociationExist($serverUrl, $association) {
		$where = sprintf('server_url=%s AND assoc_handle=%s AND expires>%d', $this->databaseConnection->fullQuoteStr($serverUrl, self::ASSOCIATION_TABLE_NAME), $this->databaseConnection->fullQuoteStr($association->handle, self::ASSOCIATION_TABLE_NAME), time());
		$row = $this->databaseConnection->exec_SELECTgetSingleRow('COUNT(*) as assocCount', self::ASSOCIATION_TABLE_NAME, $where);
		return $row['assocCount'] > 0;
	}

	/**
	 * Updates existing association.
	 *
	 * @param string $serverUrl Server URL
	 * @param \Auth_OpenID_Association $association OpenID association
	 * @return void
	 */
	protected function updateExistingAssociation($serverUrl, \Auth_OpenID_Association $association) {
		$where = sprintf('server_url=%s AND assoc_handle=%s AND expires>%d', $this->databaseConnection->fullQuoteStr($serverUrl, self::ASSOCIATION_TABLE_NAME), $this->databaseConnection->fullQuoteStr($association->handle, self::ASSOCIATION_TABLE_NAME), time());
		$serializedAssociation = serialize($association);
		$values = array(
			'content' => base64_encode($serializedAssociation),
			'tstamp' => time()
		);
		$this->databaseConnection->exec_UPDATEquery(self::ASSOCIATION_TABLE_NAME, $where, $values);
	}

	/**
	 * Stores new association to the database.
	 *
	 * @param string $serverUrl Server URL
	 * @param \Auth_OpenID_Association $association OpenID association
	 * @return void
	 */
	protected function storeNewAssociation($serverUrl, $association) {
		$serializedAssociation = serialize($association);
		$values = array(
			'assoc_handle' => $association->handle,
			'content' => base64_encode($serializedAssociation),
			'crdate' => $association->issued,
			'tstamp' => time(),
			'expires' => $association->issued + $association->lifetime - self::ASSOCIATION_EXPIRATION_SAFETY_INTERVAL,
			'server_url' => $serverUrl
		);
		// In the next query we can get race conditions. sha1_hash prevents many
		// asociations from being stored for one server
		$this->databaseConnection->exec_INSERTquery(self::ASSOCIATION_TABLE_NAME, $values);
	}

	/**
	 * Updates association time stamp.
	 *
	 * @param int $recordId Association record id in the database
	 * @return void
	 */
	protected function updateAssociationTimeStamp($recordId) {
		$where = sprintf('uid=%d', $recordId);
		$values = array(
			'tstamp' => time()
		);
		$this->databaseConnection->exec_UPDATEquery(self::ASSOCIATION_TABLE_NAME, $where, $values);
	}

}
