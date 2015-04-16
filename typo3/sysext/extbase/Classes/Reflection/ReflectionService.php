<?php
namespace TYPO3\CMS\Extbase\Reflection;

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

use TYPO3\CMS\Core\Utility\ClassNamingUtility;
use TYPO3\CMS\Extbase\Utility\TypeHandlingUtility;

/**
 * A backport of the TYPO3.Flow reflection service for aquiring reflection based information.
 * Most of the code is based on the TYPO3.Flow reflection service.
 *
 * @api
 */
class ReflectionService implements \TYPO3\CMS\Core\SingletonInterface {

	/**
	 * @var \TYPO3\CMS\Extbase\Object\ObjectManagerInterface
	 * @inject
	 */
	protected $objectManager;

	/**
	 * Whether this service has been initialized.
	 *
	 * @var bool
	 */
	protected $initialized = FALSE;

	/**
	 * @var \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend
	 */
	protected $dataCache;

	/**
	 * Whether class alterations should be detected on each initialization.
	 *
	 * @var bool
	 */
	protected $detectClassChanges = FALSE;

	/**
	 * All available class names to consider. Class name = key, value is the
	 * UNIX timestamp the class was reflected.
	 *
	 * @var array
	 */
	protected $reflectedClassNames = array();

	/**
	 * Array of tags and the names of classes which are tagged with them.
	 *
	 * @var array
	 */
	protected $taggedClasses = array();

	/**
	 * Array of class names and their tags and values.
	 *
	 * @var array
	 */
	protected $classTagsValues = array();

	/**
	 * Array of class names, method names and their tags and values.
	 *
	 * @var array
	 */
	protected $methodTagsValues = array();

	/**
	 * Array of class names, method names, their parameters and additional
	 * information about the parameters.
	 *
	 * @var array
	 */
	protected $methodParameters = array();

	/**
	 * Array of class names and names of their properties.
	 *
	 * @var array
	 */
	protected $classPropertyNames = array();

	/**
	 * Array of class names, property names and their tags and values.
	 *
	 * @var array
	 */
	protected $propertyTagsValues = array();

	/**
	 * List of tags which are ignored while reflecting class and method annotations.
	 *
	 * @var array
	 */
	protected $ignoredTags = array('package', 'subpackage', 'license', 'copyright', 'author', 'version', 'const');

	/**
	 * Indicates whether the Reflection cache needs to be updated.
	 *
	 * This flag needs to be set as soon as new Reflection information was
	 * created.
	 *
	 * @see reflectClass()
	 * @see getMethodReflection()
	 * @var bool
	 */
	protected $dataCacheNeedsUpdate = FALSE;

	/**
	 * Local cache for Class schemata
	 *
	 * @var array
	 */
	protected $classSchemata = array();

	/**
	 * @var \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface
	 * @inject
	 */
	protected $configurationManager;

	/**
	 * @var string
	 */
	protected $cacheIdentifier;

	/**
	 * @var array
	 */
	protected $methodReflections = array();

	/**
	 * Sets the data cache.
	 *
	 * The cache must be set before initializing the Reflection Service.
	 *
	 * @param \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend $dataCache Cache for the Reflection service
	 * @return void
	 */
	public function setDataCache(\TYPO3\CMS\Core\Cache\Frontend\VariableFrontend $dataCache) {
		$this->dataCache = $dataCache;
	}

	/**
	 * Initializes this service
	 *
	 * @throws Exception
	 * @return void
	 */
	public function initialize() {
		if ($this->initialized) {
			throw new Exception('The Reflection Service can only be initialized once.', 1232044696);
		}
		$frameworkConfiguration = $this->configurationManager->getConfiguration(\TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK);
		$this->cacheIdentifier = 'ReflectionData_' . $frameworkConfiguration['extensionName'];
		$this->loadFromCache();
		$this->initialized = TRUE;
	}

	/**
	 * Returns whether the Reflection Service is initialized.
	 *
	 * @return bool true if the Reflection Service is initialized, otherwise false
	 */
	public function isInitialized() {
		return $this->initialized;
	}

	/**
	 * Shuts the Reflection Service down.
	 *
	 * @return void
	 */
	public function shutdown() {
		if ($this->dataCacheNeedsUpdate) {
			$this->saveToCache();
		}
		$this->initialized = FALSE;
	}

	/**
	 * Returns all tags and their values the specified class is tagged with
	 *
	 * @param string $className Name of the class
	 * @return array An array of tags and their values or an empty array if no tags were found
	 */
	public function getClassTagsValues($className) {
		if (!isset($this->reflectedClassNames[$className])) {
			$this->reflectClass($className);
		}
		if (!isset($this->classTagsValues[$className])) {
			return array();
		}
		return isset($this->classTagsValues[$className]) ? $this->classTagsValues[$className] : array();
	}

	/**
	 * Returns the values of the specified class tag
	 *
	 * @param string $className Name of the class containing the property
	 * @param string $tag Tag to return the values of
	 * @return array An array of values or an empty array if the tag was not found
	 */
	public function getClassTagValues($className, $tag) {
		if (!isset($this->reflectedClassNames[$className])) {
			$this->reflectClass($className);
		}
		if (!isset($this->classTagsValues[$className])) {
			return array();
		}
		return isset($this->classTagsValues[$className][$tag]) ? $this->classTagsValues[$className][$tag] : array();
	}

	/**
	 * Returns the names of all properties of the specified class
	 *
	 * @param string $className Name of the class to return the property names of
	 * @return array An array of property names or an empty array if none exist
	 */
	public function getClassPropertyNames($className) {
		if (!isset($this->reflectedClassNames[$className])) {
			$this->reflectClass($className);
		}
		return isset($this->classPropertyNames[$className]) ? $this->classPropertyNames[$className] : array();
	}

	/**
	 * Returns the class schema for the given class
	 *
	 * @param mixed $classNameOrObject The class name or an object
	 * @return ClassSchema
	 */
	public function getClassSchema($classNameOrObject) {
		$className = is_object($classNameOrObject) ? get_class($classNameOrObject) : $classNameOrObject;
		if (isset($this->classSchemata[$className])) {
			return $this->classSchemata[$className];
		} else {
			return $this->buildClassSchema($className);
		}
	}

	/**
	 * Wrapper for method_exists() which tells if the given method exists.
	 *
	 * @param string $className Name of the class containing the method
	 * @param string $methodName Name of the method
	 * @return bool
	 */
	public function hasMethod($className, $methodName) {
		try {
			if (!array_key_exists($className, $this->methodReflections) || !array_key_exists($methodName, $this->methodReflections[$className])) {
				$this->methodReflections[$className][$methodName] = new MethodReflection($className, $methodName);
				$this->dataCacheNeedsUpdate = TRUE;
			}
		} catch (\ReflectionException $e) {
			// Method does not exist. Store this information in cache.
			$this->methodReflections[$className][$methodName] = NULL;
		}
		return isset($this->methodReflections[$className][$methodName]);
	}

	/**
	 * Returns all tags and their values the specified method is tagged with
	 *
	 * @param string $className Name of the class containing the method
	 * @param string $methodName Name of the method to return the tags and values of
	 * @return array An array of tags and their values or an empty array of no tags were found
	 */
	public function getMethodTagsValues($className, $methodName) {
		if (!isset($this->methodTagsValues[$className][$methodName])) {
			$this->methodTagsValues[$className][$methodName] = array();
			$method = $this->getMethodReflection($className, $methodName);
			foreach ($method->getTagsValues() as $tag => $values) {
				if (array_search($tag, $this->ignoredTags) === FALSE) {
					$this->methodTagsValues[$className][$methodName][$tag] = $values;
				}
			}
		}
		return $this->methodTagsValues[$className][$methodName];
	}

	/**
	 * Returns an array of parameters of the given method. Each entry contains
	 * additional information about the parameter position, type hint etc.
	 *
	 * @param string $className Name of the class containing the method
	 * @param string $methodName Name of the method to return parameter information of
	 * @return array An array of parameter names and additional information or an empty array of no parameters were found
	 */
	public function getMethodParameters($className, $methodName) {
		if (!isset($this->methodParameters[$className][$methodName])) {
			$method = $this->getMethodReflection($className, $methodName);
			$this->methodParameters[$className][$methodName] = array();
			foreach ($method->getParameters() as $parameterPosition => $parameter) {
				$this->methodParameters[$className][$methodName][$parameter->getName()] = $this->convertParameterReflectionToArray($parameter, $parameterPosition, $method);
			}
		}
		return $this->methodParameters[$className][$methodName];
	}

	/**
	 * Returns all tags and their values the specified class property is tagged with
	 *
	 * @param string $className Name of the class containing the property
	 * @param string $propertyName Name of the property to return the tags and values of
	 * @return array An array of tags and their values or an empty array of no tags were found
	 */
	public function getPropertyTagsValues($className, $propertyName) {
		if (!isset($this->reflectedClassNames[$className])) {
			$this->reflectClass($className);
		}
		if (!isset($this->propertyTagsValues[$className])) {
			return array();
		}
		return isset($this->propertyTagsValues[$className][$propertyName]) ? $this->propertyTagsValues[$className][$propertyName] : array();
	}

	/**
	 * Returns the values of the specified class property tag
	 *
	 * @param string $className Name of the class containing the property
	 * @param string $propertyName Name of the tagged property
	 * @param string $tag Tag to return the values of
	 * @return array An array of values or an empty array if the tag was not found
	 */
	public function getPropertyTagValues($className, $propertyName, $tag) {
		if (!isset($this->reflectedClassNames[$className])) {
			$this->reflectClass($className);
		}
		if (!isset($this->propertyTagsValues[$className][$propertyName])) {
			return array();
		}
		return isset($this->propertyTagsValues[$className][$propertyName][$tag]) ? $this->propertyTagsValues[$className][$propertyName][$tag] : array();
	}

	/**
	 * Tells if the specified class is known to this reflection service and
	 * reflection information is available.
	 *
	 * @param string $className Name of the class
	 * @return bool If the class is reflected by this service
	 */
	public function isClassReflected($className) {
		return isset($this->reflectedClassNames[$className]);
	}

	/**
	 * Tells if the specified class is tagged with the given tag
	 *
	 * @param string $className Name of the class
	 * @param string $tag Tag to check for
	 * @return bool TRUE if the class is tagged with $tag, otherwise FALSE
	 */
	public function isClassTaggedWith($className, $tag) {
		if ($this->initialized === FALSE) {
			return FALSE;
		}
		if (!isset($this->reflectedClassNames[$className])) {
			$this->reflectClass($className);
		}
		if (!isset($this->classTagsValues[$className])) {
			return FALSE;
		}
		return isset($this->classTagsValues[$className][$tag]);
	}

	/**
	 * Tells if the specified class property is tagged with the given tag
	 *
	 * @param string $className Name of the class
	 * @param string $propertyName Name of the property
	 * @param string $tag Tag to check for
	 * @return bool TRUE if the class property is tagged with $tag, otherwise FALSE
	 */
	public function isPropertyTaggedWith($className, $propertyName, $tag) {
		if (!isset($this->reflectedClassNames[$className])) {
			$this->reflectClass($className);
		}
		if (!isset($this->propertyTagsValues[$className])) {
			return FALSE;
		}
		if (!isset($this->propertyTagsValues[$className][$propertyName])) {
			return FALSE;
		}
		return isset($this->propertyTagsValues[$className][$propertyName][$tag]);
	}

	/**
	 * Reflects the given class and stores the results in this service's properties.
	 *
	 * @param string $className Full qualified name of the class to reflect
	 * @return void
	 */
	protected function reflectClass($className) {
		$class = new ClassReflection($className);
		$this->reflectedClassNames[$className] = time();
		foreach ($class->getTagsValues() as $tag => $values) {
			if (array_search($tag, $this->ignoredTags) === FALSE) {
				$this->taggedClasses[$tag][] = $className;
				$this->classTagsValues[$className][$tag] = $values;
			}
		}
		foreach ($class->getProperties() as $property) {
			$propertyName = $property->getName();
			$this->classPropertyNames[$className][] = $propertyName;
			foreach ($property->getTagsValues() as $tag => $values) {
				if (array_search($tag, $this->ignoredTags) === FALSE) {
					$this->propertyTagsValues[$className][$propertyName][$tag] = $values;
				}
			}
		}
		foreach ($class->getMethods() as $method) {
			$methodName = $method->getName();
			foreach ($method->getTagsValues() as $tag => $values) {
				if (array_search($tag, $this->ignoredTags) === FALSE) {
					$this->methodTagsValues[$className][$methodName][$tag] = $values;
				}
			}
			foreach ($method->getParameters() as $parameterPosition => $parameter) {
				$this->methodParameters[$className][$methodName][$parameter->getName()] = $this->convertParameterReflectionToArray($parameter, $parameterPosition, $method);
			}
		}
		ksort($this->reflectedClassNames);
		$this->dataCacheNeedsUpdate = TRUE;
	}

	/**
	 * Builds class schemata from classes annotated as entities or value objects
	 *
	 * @param string $className
	 * @throws Exception\UnknownClassException
	 * @return ClassSchema The class schema
	 */
	protected function buildClassSchema($className) {
		if (!class_exists($className)) {
			throw new Exception\UnknownClassException('The classname "' . $className . '" was not found and thus can not be reflected.', 1278450972);
		}
		$classSchema = $this->objectManager->get(\TYPO3\CMS\Extbase\Reflection\ClassSchema::class, $className);
		if (is_subclass_of($className, \TYPO3\CMS\Extbase\DomainObject\AbstractEntity::class)) {
			$classSchema->setModelType(ClassSchema::MODELTYPE_ENTITY);
			$possibleRepositoryClassName = ClassNamingUtility::translateModelNameToRepositoryName($className);
			if (class_exists($possibleRepositoryClassName)) {
				$classSchema->setAggregateRoot(TRUE);
			}
		} elseif (is_subclass_of($className, \TYPO3\CMS\Extbase\DomainObject\AbstractValueObject::class)) {
			$classSchema->setModelType(ClassSchema::MODELTYPE_VALUEOBJECT);
		}
		foreach ($this->getClassPropertyNames($className) as $propertyName) {
			if (!$this->isPropertyTaggedWith($className, $propertyName, 'transient') && $this->isPropertyTaggedWith($className, $propertyName, 'var')) {
				$cascadeTagValues = $this->getPropertyTagValues($className, $propertyName, 'cascade');
				$classSchema->addProperty($propertyName, implode(' ', $this->getPropertyTagValues($className, $propertyName, 'var')), $this->isPropertyTaggedWith($className, $propertyName, 'lazy'), $cascadeTagValues[0]);
			}
			if ($this->isPropertyTaggedWith($className, $propertyName, 'uuid')) {
				$classSchema->setUuidPropertyName($propertyName);
			}
			if ($this->isPropertyTaggedWith($className, $propertyName, 'identity')) {
				$classSchema->markAsIdentityProperty($propertyName);
			}
		}
		$this->classSchemata[$className] = $classSchema;
		$this->dataCacheNeedsUpdate = TRUE;
		return $classSchema;
	}

	/**
	 * Converts the given parameter reflection into an information array
	 *
	 * @param ParameterReflection $parameter The parameter to reflect
	 * @param int $parameterPosition
	 * @param MethodReflection|NULL $method
	 * @return array Parameter information array
	 */
	protected function convertParameterReflectionToArray(ParameterReflection $parameter, $parameterPosition, MethodReflection $method = NULL) {
		$parameterInformation = array(
			'position' => $parameterPosition,
			'byReference' => $parameter->isPassedByReference(),
			'array' => $parameter->isArray(),
			'optional' => $parameter->isOptional(),
			'allowsNull' => $parameter->allowsNull()
		);
		$parameterClass = $parameter->getClass();
		$parameterInformation['class'] = $parameterClass !== NULL ? $parameterClass->getName() : NULL;
		if ($parameter->isDefaultValueAvailable()) {
			$parameterInformation['defaultValue'] = $parameter->getDefaultValue();
		}
		if ($parameterClass !== NULL) {
			$parameterInformation['type'] = $parameterClass->getName();
		} elseif ($method !== NULL) {
			$methodTagsAndValues = $this->getMethodTagsValues($method->getDeclaringClass()->getName(), $method->getName());
			if (isset($methodTagsAndValues['param']) && isset($methodTagsAndValues['param'][$parameterPosition])) {
				$explodedParameters = explode(' ', $methodTagsAndValues['param'][$parameterPosition]);
				if (count($explodedParameters) >= 2) {
					if (TypeHandlingUtility::isSimpleType($explodedParameters[0])) {
						// ensure that short names of simple types are resolved correctly to the long form
						// this is important for all kinds of type checks later on
						$typeInfo = TypeHandlingUtility::parseType($explodedParameters[0]);
						$parameterInformation['type'] = $typeInfo['type'];
					} else {
						$parameterInformation['type'] = $explodedParameters[0];
					}
				}
			}
		}
		if (isset($parameterInformation['type']) && $parameterInformation['type'][0] === '\\') {
			$parameterInformation['type'] = substr($parameterInformation['type'], 1);
		}
		return $parameterInformation;
	}

	/**
	 * Returns the Reflection of a method.
	 *
	 * @param string $className Name of the class containing the method
	 * @param string $methodName Name of the method to return the Reflection for
	 * @return MethodReflection the method Reflection object
	 */
	protected function getMethodReflection($className, $methodName) {
		if (!isset($this->methodReflections[$className][$methodName])) {
			$this->methodReflections[$className][$methodName] = new MethodReflection($className, $methodName);
			$this->dataCacheNeedsUpdate = TRUE;
		}
		return $this->methodReflections[$className][$methodName];
	}

	/**
	 * Tries to load the reflection data from this service's cache.
	 *
	 * @return void
	 */
	protected function loadFromCache() {
		$data = $this->dataCache->get($this->cacheIdentifier);
		if ($data !== FALSE) {
			foreach ($data as $propertyName => $propertyValue) {
				$this->{$propertyName} = $propertyValue;
			}
		}
	}

	/**
	 * Exports the internal reflection data into the ReflectionData cache.
	 *
	 * @throws Exception
	 * @return void
	 */
	protected function saveToCache() {
		if (!is_object($this->dataCache)) {
			throw new Exception('A cache must be injected before initializing the Reflection Service.', 1232044697);
		}
		$data = array();
		$propertyNames = array(
			'reflectedClassNames',
			'classPropertyNames',
			'classTagsValues',
			'methodTagsValues',
			'methodParameters',
			'propertyTagsValues',
			'taggedClasses',
			'classSchemata'
		);
		foreach ($propertyNames as $propertyName) {
			$data[$propertyName] = $this->{$propertyName};
		}
		$this->dataCache->set($this->cacheIdentifier, $data);
	}

}
