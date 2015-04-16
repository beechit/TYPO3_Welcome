<?php
namespace TYPO3\CMS\Extbase\Property\TypeConverter;

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
 * Converter which transforms simple types to \TYPO3\CMS\Extbase\Domain\Model\File.
 *
 * @api experimental! This class is experimental and subject to change!
 */
abstract class AbstractFileCollectionConverter extends \TYPO3\CMS\Extbase\Property\TypeConverter\AbstractTypeConverter {

	/**
	 * @var int
	 */
	protected $priority = 1;

	/**
	 * @var string
	 */
	protected $expectedObjectType;

	/**
	 * @var \TYPO3\CMS\Core\Resource\ResourceFactory
	 * @inject
	 */
	protected $fileFactory;

	/**
	 * Actually convert from $source to $targetType, taking into account the fully
	 * built $convertedChildProperties and $configuration.
	 *
	 * @param int $source
	 * @param string $targetType
	 * @param array $convertedChildProperties
	 * @param \TYPO3\CMS\Extbase\Property\PropertyMappingConfigurationInterface $configuration
	 * @throws \TYPO3\CMS\Extbase\Property\Exception
	 * @return \TYPO3\CMS\Extbase\Domain\Model\AbstractFileCollection
	 * @api
	 */
	public function convertFrom($source, $targetType, array $convertedChildProperties = array(), \TYPO3\CMS\Extbase\Property\PropertyMappingConfigurationInterface $configuration = NULL) {
		$object = $this->getObject($source);
		if (empty($this->expectedObjectType) || !$object instanceof $this->expectedObjectType) {
			throw new \TYPO3\CMS\Extbase\Property\Exception('Expected object of type "' . $this->expectedObjectType . '" but got ' . get_class($object), 1342895976);
		}
		/** @var $subject \TYPO3\CMS\Extbase\Domain\Model\AbstractFileCollection */
		$subject = $this->objectManager->get($targetType);
		$subject->setObject($object);
		return $subject;
	}

	/**
	 * @param int $source
	 * @return \TYPO3\CMS\Core\Resource\Collection\AbstractFileCollection
	 */
	abstract protected function getObject($source);
}
