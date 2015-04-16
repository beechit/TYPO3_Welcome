<?php
namespace TYPO3\CMS\Extbase\DomainObject;

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
 * A generic Domain Object.
 *
 * All Model domain objects need to inherit from either AbstractEntity or AbstractValueObject, as this provides important framework information.
 */
abstract class AbstractDomainObject implements DomainObjectInterface, \TYPO3\CMS\Extbase\Persistence\ObjectMonitoringInterface {

	/**
	 * @var int The uid of the record. The uid is only unique in the context of the database table.
	 */
	protected $uid;

	/**
	 * @var int The uid of the localized record. In TYPO3 v4.x the property "uid" holds the uid of the record in default language (the translationOrigin).
	 */
	protected $_localizedUid;

	/**
	 * @var int The uid of the language of the object. In TYPO3 v4.x this is the uid of the language record in the table sys_language.
	 */
	protected $_languageUid;

	/**
	 * @var int The uid of the versioned record.
	 */
	protected $_versionedUid;

	/**
	 * @var int The id of the page the record is "stored".
	 */
	protected $pid;

	/**
	 * TRUE if the object is a clone
	 *
	 * @var bool
	 */
	private $_isClone = FALSE;

	/**
	 * @var array An array holding the clean property values. Set right after reconstitution of the object
	 */
	private $_cleanProperties = array();

	/**
	 * This is the magic __wakeup() method. It's invoked by the unserialize statement in the reconstitution process
	 * of the object. If you want to implement your own __wakeup() method in your Domain Object you have to call
	 * parent::__wakeup() first!
	 *
	 * @return void
	 */
	public function __wakeup() {
		$this->initializeObject();
	}

	public function initializeObject() {
	}

	/**
	 * Getter for uid.
	 *
	 * @return int the uid or NULL if none set yet.
	 */
	public function getUid() {
		if ($this->uid !== NULL) {
			return (int)$this->uid;
		} else {
			return NULL;
		}
	}

	/**
	 * Setter for the pid.
	 *
	 * @param int|NULL $pid
	 * @return void
	 */
	public function setPid($pid) {
		if ($pid === NULL) {
			$this->pid = NULL;
		} else {
			$this->pid = (int)$pid;
		}
	}

	/**
	 * Getter for the pid.
	 *
	 * @return int The pid or NULL if none set yet.
	 */
	public function getPid() {
		if ($this->pid === NULL) {
			return NULL;
		} else {
			return (int)$this->pid;
		}
	}

	/**
	 * Reconstitutes a property. Only for internal use.
	 *
	 * @param string $propertyName
	 * @param mixed $propertyValue
	 * @return bool
	 */
	public function _setProperty($propertyName, $propertyValue) {
		if ($this->_hasProperty($propertyName)) {
			$this->{$propertyName} = $propertyValue;
			return TRUE;
		}
		return FALSE;
	}

	/**
	 * Returns the property value of the given property name. Only for internal use.
	 *
	 * @param string $propertyName
	 * @return mixed The propertyValue
	 */
	public function _getProperty($propertyName) {
		return $this->{$propertyName};
	}

	/**
	 * Returns a hash map of property names and property values. Only for internal use.
	 *
	 * @return array The properties
	 */
	public function _getProperties() {
		$properties = get_object_vars($this);
		foreach ($properties as $propertyName => $propertyValue) {
			if ($propertyName[0] === '_') {
				unset($properties[$propertyName]);
			}
		}
		return $properties;
	}

	/**
	 * Returns the property value of the given property name. Only for internal use.
	 *
	 * @param string $propertyName
	 * @return bool TRUE bool true if the property exists, FALSE if it doesn't exist or NULL in case of an error.
	 */
	public function _hasProperty($propertyName) {
		return property_exists($this, $propertyName);
	}

	/**
	 * Returns TRUE if the object is new (the uid was not set, yet). Only for internal use
	 *
	 * @return bool
	 */
	public function _isNew() {
		return $this->uid === NULL;
	}

	/**
	 * Register an object's clean state, e.g. after it has been reconstituted
	 * from the database.
	 *
	 * @param string $propertyName The name of the property to be memorized. If omitted all persistable properties are memorized.
	 * @return void
	 */
	public function _memorizeCleanState($propertyName = NULL) {
		if ($propertyName !== NULL) {
			$this->_memorizePropertyCleanState($propertyName);
		} else {
			$this->_cleanProperties = array();
			$properties = get_object_vars($this);
			foreach ($properties as $propertyName => $propertyValue) {
				if ($propertyName[0] === '_') {
					continue;
				}
				// Do not memorize "internal" properties
				$this->_memorizePropertyCleanState($propertyName);
			}
		}
	}

	/**
	 * Register an properties's clean state, e.g. after it has been reconstituted
	 * from the database.
	 *
	 * @param string $propertyName The name of the property to be memorized. If omittet all persistable properties are memorized.
	 * @return void
	 */
	public function _memorizePropertyCleanState($propertyName) {
		$propertyValue = $this->{$propertyName};
		if (is_object($propertyValue)) {
			$this->_cleanProperties[$propertyName] = clone $propertyValue;
			// We need to make sure the clone and the original object
			// are identical when compared with == (see _isDirty()).
			// After the cloning, the Domain Object will have the property
			// "isClone" set to TRUE, so we manually have to set it to FALSE
			// again. Possible fix: Somehow get rid of the "isClone" property,
			// which is currently needed in Fluid.
			if ($propertyValue instanceof \TYPO3\CMS\Extbase\DomainObject\AbstractDomainObject) {
				$this->_cleanProperties[$propertyName]->_setClone(FALSE);
			}
		} else {
			$this->_cleanProperties[$propertyName] = $propertyValue;
		}
	}

	/**
	 * Returns a hash map of clean properties and $values.
	 *
	 * @return array
	 */
	public function _getCleanProperties() {
		return $this->_cleanProperties;
	}

	/**
	 * Returns the clean value of the given property. The returned value will be NULL if the clean state was not memorized before, or
	 * if the clean value is NULL.
	 *
	 * @param string $propertyName The name of the property to be memorized.
	 * @return mixed The clean property value or NULL
	 */
	public function _getCleanProperty($propertyName) {
		return isset($this->_cleanProperties[$propertyName]) ? $this->_cleanProperties[$propertyName] : NULL;
	}

	/**
	 * Returns TRUE if the properties were modified after reconstitution
	 *
	 * @param string $propertyName An optional name of a property to be checked if its value is dirty
	 * @throws \TYPO3\CMS\Extbase\Persistence\Generic\Exception\TooDirtyException
	 * @return bool
	 */
	public function _isDirty($propertyName = NULL) {
		if ($this->uid !== NULL && $this->_getCleanProperty('uid') !== NULL && $this->uid != $this->_getCleanProperty('uid')) {
			throw new \TYPO3\CMS\Extbase\Persistence\Generic\Exception\TooDirtyException('The uid "' . $this->uid . '" has been modified, that is simply too much.', 1222871239);
		}

		if ($propertyName === NULL) {
			foreach ($this->_getCleanProperties() as $propertyName => $cleanPropertyValue) {
				if ($this->isPropertyDirty($cleanPropertyValue, $this->{$propertyName}) === TRUE) {
					return TRUE;
				}
			}
		} else {
			if ($this->isPropertyDirty($this->_getCleanProperty($propertyName), $this->{$propertyName}) === TRUE) {
				return TRUE;
			}
		}
		return FALSE;
	}

	/**
	 * Checks the $value against the $cleanState.
	 *
	 * @param mixed $previousValue
	 * @param mixed $currentValue
	 * @return bool
	 */
	protected function isPropertyDirty($previousValue, $currentValue) {
		// In case it is an object and it implements the ObjectMonitoringInterface, we call _isDirty() instead of a simple comparison of objects.
		// We do this, because if the object itself contains a lazy loaded property, the comparison of the objects might fail even if the object didn't change
		if (is_object($currentValue)) {
			if ($currentValue instanceof DomainObjectInterface) {
				$result = !is_object($previousValue) || get_class($previousValue) !== get_class($currentValue) || $currentValue->getUid() !== $previousValue->getUid();
			} elseif ($currentValue instanceof \TYPO3\CMS\Extbase\Persistence\ObjectMonitoringInterface) {
				$result = !is_object($previousValue) || $currentValue->_isDirty() || get_class($previousValue) !== get_class($currentValue);
			} else {
				// For all other objects we do only a simple comparison (!=) as we want cloned objects to return the same values.
				$result = $previousValue != $currentValue;
			}
		} else {
			$result = $previousValue !== $currentValue;
		}
		return $result;
	}

	/**
	 * Returns TRUE if the object has been clonesd, cloned, FALSE otherwise.
	 *
	 * @return bool TRUE if the object has been cloned
	 */
	public function _isClone() {
		return $this->_isClone;
	}

	/**
	 * Setter whether this Domain Object is a clone of another one.
	 * NEVER SET THIS PROPERTY DIRECTLY. We currently need it to make the
	 * _isDirty check inside AbstractEntity work, but it is just a work-
	 * around right now.
	 *
	 * @param bool $clone
	 */
	public function _setClone($clone) {
		$this->_isClone = (bool)$clone;
	}

	/**
	 * Clone method. Sets the _isClone property.
	 *
	 * @return void
	 */
	public function __clone() {
		$this->_isClone = TRUE;
	}

	/**
	 * Returns the class name and the uid of the object as string
	 *
	 * @return string
	 */
	public function __toString() {
		return get_class($this) . ':' . (string)$this->uid;
	}

}
