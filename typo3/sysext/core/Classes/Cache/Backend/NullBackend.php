<?php
namespace TYPO3\CMS\Core\Cache\Backend;

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
 * A caching backend which forgets everything immediately
 *
 * This file is a backport from FLOW3
 *
 * @author Robert Lemke <robert@typo3.org>
 * @author Karsten Dambekalns <karsten@typo3.org>
 * @api
 */
class NullBackend extends \TYPO3\CMS\Core\Cache\Backend\AbstractBackend implements \TYPO3\CMS\Core\Cache\Backend\PhpCapableBackendInterface, \TYPO3\CMS\Core\Cache\Backend\TaggableBackendInterface {

	/**
	 * Acts as if it would save data
	 *
	 * @param string $entryIdentifier ignored
	 * @param string $data ignored
	 * @param array $tags ignored
	 * @param int $lifetime ignored
	 * @return void
	 * @api
	 */
	public function set($entryIdentifier, $data, array $tags = array(), $lifetime = NULL) {

	}

	/**
	 * Acts as if it would enable data compression
	 *
	 * @param bool $compression ignored
	 * @return void
	 */
	public function setCompression($compression) {

	}

	/**
	 * Returns False
	 *
	 * @param string $entryIdentifier ignored
	 * @return bool FALSE
	 * @api
	 */
	public function get($entryIdentifier) {
		return FALSE;
	}

	/**
	 * Returns False
	 *
	 * @param string $entryIdentifier ignored
	 * @return bool FALSE
	 * @api
	 */
	public function has($entryIdentifier) {
		return FALSE;
	}

	/**
	 * Does nothing
	 *
	 * @param string $entryIdentifier ignored
	 * @return bool FALSE
	 * @api
	 */
	public function remove($entryIdentifier) {
		return FALSE;
	}

	/**
	 * Returns an empty array
	 *
	 * @param string $tag ignored
	 * @return array An empty array
	 * @api
	 */
	public function findIdentifiersByTag($tag) {
		return array();
	}

	/**
	 * Does nothing
	 *
	 * @return void
	 * @api
	 */
	public function flush() {

	}

	/**
	 * Does nothing
	 *
	 * @param string $tag ignored
	 * @return void
	 * @api
	 */
	public function flushByTag($tag) {

	}

	/**
	 * Does nothing
	 *
	 * @return void
	 * @api
	 */
	public function collectGarbage() {

	}

	/**
	 * Does nothing
	 *
	 * @param string $identifier An identifier which describes the cache entry to load
	 * @return void
	 * @api
	 */
	public function requireOnce($identifier) {

	}

}
