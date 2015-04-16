<?php
namespace TYPO3\CMS\Extbase\Tests\Unit\Persistence;

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
 * Test case
 */
class RepositoryTest extends \TYPO3\CMS\Core\Tests\UnitTestCase {

	/**
	 * @var \TYPO3\CMS\Extbase\Persistence\Repository|\PHPUnit_Framework_MockObject_MockObject|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface
	 */
	protected $repository;

	/**
	 * @var \TYPO3\CMS\Extbase\Object\ObjectManagerInterface
	 */
	protected $mockObjectManager;

	/**
	 * @var \TYPO3\CMS\Extbase\Persistence\Generic\IdentityMap
	 */
	protected $mockIdentityMap;

	/**
	 * @var \TYPO3\CMS\Extbase\Persistence\Generic\QueryFactory
	 */
	protected $mockQueryFactory;

	/**
	 * @var \TYPO3\CMS\Extbase\Persistence\Generic\BackendInterface
	 */
	protected $mockBackend;

	/**
	 * @var \TYPO3\CMS\Extbase\Persistence\Generic\Session
	 */
	protected $mockSession;

	/**
	 * @var \TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface
	 */
	protected $mockPersistenceManager;

	/**
	 * @var \TYPO3\CMS\Extbase\Persistence\QueryInterface
	 */
	protected $mockQuery;

	/**
	 * @var \TYPO3\CMS\Extbase\Persistence\Generic\QuerySettingsInterface
	 */
	protected $querySettings;

	/**
	 * @var \TYPO3\CMS\Extbase\Persistence\Generic\QuerySettingsInterface
	 */
	protected $mockQuerySettings;

	protected function setUp() {
		$this->mockIdentityMap = $this->getMock(\TYPO3\CMS\Extbase\Persistence\Generic\IdentityMap::class);
		$this->mockQueryFactory = $this->getMock(\TYPO3\CMS\Extbase\Persistence\Generic\QueryFactory::class);
		$this->mockQuery = $this->getMock(\TYPO3\CMS\Extbase\Persistence\QueryInterface::class);
		$this->mockQuerySettings = $this->getMock(\TYPO3\CMS\Extbase\Persistence\Generic\QuerySettingsInterface::class);
		$this->mockQuery->expects($this->any())->method('getQuerySettings')->will($this->returnValue($this->mockQuerySettings));
		$this->mockQueryFactory->expects($this->any())->method('create')->will($this->returnValue($this->mockQuery));
		$this->mockBackend = $this->getMock(\TYPO3\CMS\Extbase\Persistence\Generic\BackendInterface::class);
		$this->mockSession = $this->getMock(\TYPO3\CMS\Extbase\Persistence\Generic\Session::class);
		$this->mockPersistenceManager = $this->getMock(\TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface::class);
		$this->mockPersistenceManager->expects($this->any())->method('createQueryForType')->will($this->returnValue($this->mockQuery));
		$this->mockObjectManager = $this->getMock(\TYPO3\CMS\Extbase\Object\ObjectManagerInterface::class);
		$this->repository = $this->getAccessibleMock(\TYPO3\CMS\Extbase\Persistence\Repository::class, array('dummy'), array($this->mockObjectManager));
		$this->repository->_set('persistenceManager', $this->mockPersistenceManager);
	}

	/**
	 * @test
	 */
	public function abstractRepositoryImplementsRepositoryInterface() {
		$this->assertTrue($this->repository instanceof \TYPO3\CMS\Extbase\Persistence\RepositoryInterface);
	}

	/**
	 * @test
	 */
	public function createQueryCallsPersistenceManagerWithExpectedClassName() {
		$mockPersistenceManager = $this->getMock(\TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager::class);
		$mockPersistenceManager->expects($this->once())->method('createQueryForType')->with('ExpectedType');

		$this->repository->_set('objectType', 'ExpectedType');
		$this->inject($this->repository, 'persistenceManager', $mockPersistenceManager);

		$this->repository->createQuery();
	}

	/**
	 * @test
	 */
	public function createQuerySetsDefaultOrderingIfDefined() {
		$orderings = array('foo' => \TYPO3\CMS\Extbase\Persistence\QueryInterface::ORDER_ASCENDING);
		$mockQuery = $this->getMock(\TYPO3\CMS\Extbase\Persistence\QueryInterface::class);
		$mockQuery->expects($this->once())->method('setOrderings')->with($orderings);
		$mockPersistenceManager = $this->getMock(\TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager::class);
		$mockPersistenceManager->expects($this->exactly(2))->method('createQueryForType')->with('ExpectedType')->will($this->returnValue($mockQuery));

		$this->repository->_set('objectType', 'ExpectedType');
		$this->inject($this->repository, 'persistenceManager', $mockPersistenceManager);
		$this->repository->setDefaultOrderings($orderings);
		$this->repository->createQuery();

		$this->repository->setDefaultOrderings(array());
		$this->repository->createQuery();
	}

	/**
	 * @test
	 */
	public function findAllCreatesQueryAndReturnsResultOfExecuteCall() {
		$expectedResult = $this->getMock(\TYPO3\CMS\Extbase\Persistence\QueryResultInterface::class);

		$mockQuery = $this->getMock(\TYPO3\CMS\Extbase\Persistence\QueryInterface::class);
		$mockQuery->expects($this->once())->method('execute')->with()->will($this->returnValue($expectedResult));

		$repository = $this->getMock(\TYPO3\CMS\Extbase\Persistence\Repository::class, array('createQuery'), array($this->mockObjectManager));
		$repository->expects($this->once())->method('createQuery')->will($this->returnValue($mockQuery));

		$this->assertSame($expectedResult, $repository->findAll());
	}

	/**
	 * @test
	 */
	public function findByidentifierReturnsResultOfGetObjectByIdentifierCall() {
		$identifier = '42';
		$object = new \stdClass();

		$expectedResult = $this->getMock(\TYPO3\CMS\Extbase\Persistence\QueryResultInterface::class);
		$expectedResult->expects($this->once())->method('getFirst')->will($this->returnValue($object));

		$mockQuery = $this->getMock(\TYPO3\CMS\Extbase\Persistence\QueryInterface::class);
		$mockQuery->expects($this->any())->method('getQuerySettings')->will($this->returnValue($this->mockQuerySettings));
		$mockQuery->expects($this->once())->method('matching')->will($this->returnValue($mockQuery));
		$mockQuery->expects($this->once())->method('execute')->will($this->returnValue($expectedResult));

		$session = $this->getMock(\TYPO3\CMS\Extbase\Persistence\Generic\Session::class);
		$session->expects($this->once())->method('hasIdentifier')->will($this->returnValue(FALSE));

		$repository = $this->getAccessibleMock(\TYPO3\CMS\Extbase\Persistence\Repository::class, array('createQuery'), array($this->mockObjectManager));
		$repository->_set('session', $session);
		$repository->expects($this->once())->method('createQuery')->will($this->returnValue($mockQuery));
		$this->assertSame($object, $repository->findByIdentifier($identifier));
	}

	/**
	 * @test
	 */
	public function addDelegatesToPersistenceManager() {
		$object = new \stdClass();
		$mockPersistenceManager = $this->getMock(\TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface::class);
		$mockPersistenceManager->expects($this->once())->method('add')->with($object);
		$this->inject($this->repository, 'persistenceManager', $mockPersistenceManager);
		$this->repository->_set('objectType', get_class($object));
		$this->repository->add($object);
	}

	/**
	 * @test
	 */
	public function removeDelegatesToPersistenceManager() {
		$object = new \stdClass();
		$mockPersistenceManager = $this->getMock(\TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface::class);
		$mockPersistenceManager->expects($this->once())->method('remove')->with($object);
		$this->inject($this->repository, 'persistenceManager', $mockPersistenceManager);
		$this->repository->_set('objectType', get_class($object));
		$this->repository->remove($object);
	}

	/**
	 * @test
	 */
	public function updateDelegatesToPersistenceManager() {
		$object = new \stdClass();
		$mockPersistenceManager = $this->getMock(\TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface::class);
		$mockPersistenceManager->expects($this->once())->method('update')->with($object);
		$this->inject($this->repository, 'persistenceManager', $mockPersistenceManager);
		$this->repository->_set('objectType', get_class($object));
		$this->repository->update($object);
	}

	/**
	 * @test
	 */
	public function magicCallMethodAcceptsFindBySomethingCallsAndExecutesAQueryWithThatCriteria() {
		$mockQueryResult = $this->getMock(\TYPO3\CMS\Extbase\Persistence\QueryResultInterface::class);
		$mockQuery = $this->getMock(\TYPO3\CMS\Extbase\Persistence\QueryInterface::class);
		$mockQuery->expects($this->once())->method('equals')->with('foo', 'bar')->will($this->returnValue('matchCriteria'));
		$mockQuery->expects($this->once())->method('matching')->with('matchCriteria')->will($this->returnValue($mockQuery));
		$mockQuery->expects($this->once())->method('execute')->with()->will($this->returnValue($mockQueryResult));

		$repository = $this->getMock(\TYPO3\CMS\Extbase\Persistence\Repository::class, array('createQuery'), array($this->mockObjectManager));
		$repository->expects($this->once())->method('createQuery')->will($this->returnValue($mockQuery));

		$this->assertSame($mockQueryResult, $repository->findByFoo('bar'));
	}

	/**
	 * @test
	 */
	public function magicCallMethodAcceptsFindOneBySomethingCallsAndExecutesAQueryWithThatCriteria() {
		$object = new \stdClass();
		$mockQueryResult = $this->getMock(\TYPO3\CMS\Extbase\Persistence\QueryResultInterface::class);
		$mockQueryResult->expects($this->once())->method('getFirst')->will($this->returnValue($object));
		$mockQuery = $this->getMock(\TYPO3\CMS\Extbase\Persistence\QueryInterface::class);
		$mockQuery->expects($this->once())->method('equals')->with('foo', 'bar')->will($this->returnValue('matchCriteria'));
		$mockQuery->expects($this->once())->method('matching')->with('matchCriteria')->will($this->returnValue($mockQuery));
		$mockQuery->expects($this->once())->method('setLimit')->will($this->returnValue($mockQuery));
		$mockQuery->expects($this->once())->method('execute')->will($this->returnValue($mockQueryResult));

		$repository = $this->getMock(\TYPO3\CMS\Extbase\Persistence\Repository::class, array('createQuery'), array($this->mockObjectManager));
		$repository->expects($this->once())->method('createQuery')->will($this->returnValue($mockQuery));

		$this->assertSame($object, $repository->findOneByFoo('bar'));
	}

	/**
	 * @test
	 */
	public function magicCallMethodAcceptsCountBySomethingCallsAndExecutesAQueryWithThatCriteria() {
		$mockQuery = $this->getMock(\TYPO3\CMS\Extbase\Persistence\QueryInterface::class);
		$mockQueryResult = $this->getMock(\TYPO3\CMS\Extbase\Persistence\QueryResultInterface::class);
		$mockQuery->expects($this->once())->method('equals')->with('foo', 'bar')->will($this->returnValue('matchCriteria'));
		$mockQuery->expects($this->once())->method('matching')->with('matchCriteria')->will($this->returnValue($mockQuery));
		$mockQuery->expects($this->once())->method('execute')->will($this->returnValue($mockQueryResult));
		$mockQueryResult->expects($this->once())->method('count')->will($this->returnValue(2));

		$repository = $this->getMock(\TYPO3\CMS\Extbase\Persistence\Repository::class, array('createQuery'), array($this->mockObjectManager));
		$repository->expects($this->once())->method('createQuery')->will($this->returnValue($mockQuery));

		$this->assertSame(2, $repository->countByFoo('bar'));
	}

	/**
	 * @test
	 * @expectedException \TYPO3\CMS\Extbase\Persistence\Generic\Exception\UnsupportedMethodException
	 */
	public function magicCallMethodTriggersAnErrorIfUnknownMethodsAreCalled() {
		$repository = $this->getMock(\TYPO3\CMS\Extbase\Persistence\Repository::class, array('createQuery'), array($this->mockObjectManager));
		$repository->__call('foo', array());
	}

	/**
	 * @test
	 * @expectedException \TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException
	 */
	public function addChecksObjectType() {
		$this->repository->_set('objectType', 'ExpectedObjectType');
		$this->repository->add(new \stdClass());
	}

	/**
	 * @test
	 * @expectedException \TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException
	 */
	public function removeChecksObjectType() {
		$this->repository->_set('objectType', 'ExpectedObjectType');
		$this->repository->remove(new \stdClass());
	}
	/**
	 * @test
	 * @expectedException \TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException
	 */
	public function updateChecksObjectType() {
		$repository = $this->getAccessibleMock(\TYPO3\CMS\Extbase\Persistence\Repository::class, array('dummy'), array($this->mockObjectManager));
		$repository->_set('objectType', 'ExpectedObjectType');

		$repository->update(new \stdClass());
	}

	/**
	 * dataProvider for createQueryCallsQueryFactoryWithExpectedType
	 *
	 * @return array
	 */
	public function modelAndRepositoryClassNames() {
		return array(
			array('Tx_BlogExample_Domain_Repository_BlogRepository', 'Tx_BlogExample_Domain_Model_Blog'),
			array('﻿_Domain_Repository_Content_PageRepository', '﻿_Domain_Model_Content_Page'),
			array('Tx_RepositoryExample_Domain_Repository_SomeModelRepository', 'Tx_RepositoryExample_Domain_Model_SomeModel'),
			array('Tx_RepositoryExample_Domain_Repository_RepositoryRepository', 'Tx_RepositoryExample_Domain_Model_Repository'),
			array('Tx_Repository_Domain_Repository_RepositoryRepository', 'Tx_Repository_Domain_Model_Repository')
		);
	}

	/**
	 * @test
	 * @dataProvider modelAndRepositoryClassNames
	 * @param string $repositoryClassName
	 * @param string $modelClassName
	 */
	public function constructSetsObjectTypeFromClassName($repositoryClassName, $modelClassName) {
		$repositoryClassNameWithNS = __NAMESPACE__ . '\\' . $repositoryClassName;
		eval('namespace ' . __NAMESPACE__ . '; class ' . $repositoryClassName . ' extends \\TYPO3\\CMS\\Extbase\\Persistence\\Repository {
			protected function getRepositoryClassName() {
				return \'' . $repositoryClassName . '\';
			}
			public function _getObjectType() {
				return $this->objectType;
			}
		}');
		$this->repository = new $repositoryClassNameWithNS($this->mockObjectManager);
		$this->assertEquals($modelClassName, $this->repository->_getObjectType());
	}

	/**
	 * @test
	 */
	public function createQueryReturnsQueryWithUnmodifiedDefaultQuerySettings() {
		$this->mockQuery = new \TYPO3\CMS\Extbase\Persistence\Generic\Query('foo');
		$mockDefaultQuerySettings = $this->getMock(\TYPO3\CMS\Extbase\Persistence\Generic\QuerySettingsInterface::class);
		$this->repository->setDefaultQuerySettings($mockDefaultQuerySettings);
		$query = $this->repository->createQuery();
		$instanceQuerySettings = $query->getQuerySettings();
		$this->assertEquals($mockDefaultQuerySettings, $instanceQuerySettings);
		$this->assertNotSame($mockDefaultQuerySettings, $instanceQuerySettings);
	}

	/**
	 * @test
	 */
	public function findByUidReturnsResultOfGetObjectByIdentifierCall() {
		$fakeUid = '123';
		$object = new \stdClass();
		$repository = $this->getMock(\TYPO3\CMS\Extbase\Persistence\Repository::class, array('findByIdentifier'), array($this->mockObjectManager));
		$expectedResult = $object;
		$repository->expects($this->once())->method('findByIdentifier')->will($this->returnValue($object));
		$actualResult = $repository->findByUid($fakeUid);
		$this->assertSame($expectedResult, $actualResult);
	}

	/**
	 * @test
	 * @expectedException \TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException
	 */
	public function updateRejectsObjectsOfWrongType() {
		$this->repository->_set('objectType', 'Foo');
		$this->repository->update(new \stdClass());
	}

	/**
	 * @test
	 */
	public function magicCallMethodReturnsFirstArrayKeyInFindOneBySomethingIfQueryReturnsRawResult() {
		$queryResultArray = array(
			0 => array(
				'foo' => 'bar',
			),
		);
		$this->mockQuery->expects($this->once())->method('equals')->with('foo', 'bar')->will($this->returnValue('matchCriteria'));
		$this->mockQuery->expects($this->once())->method('matching')->with('matchCriteria')->will($this->returnValue($this->mockQuery));
		$this->mockQuery->expects($this->once())->method('setLimit')->with(1)->will($this->returnValue($this->mockQuery));
		$this->mockQuery->expects($this->once())->method('execute')->will($this->returnValue($queryResultArray));
		$this->assertSame(array('foo' => 'bar'), $this->repository->findOneByFoo('bar'));
	}

	/**
	 * @test
	 */
	public function magicCallMethodReturnsNullInFindOneBySomethingIfQueryReturnsEmptyRawResult() {
		$queryResultArray = array();
		$this->mockQuery->expects($this->once())->method('equals')->with('foo', 'bar')->will($this->returnValue('matchCriteria'));
		$this->mockQuery->expects($this->once())->method('matching')->with('matchCriteria')->will($this->returnValue($this->mockQuery));
		$this->mockQuery->expects($this->once())->method('setLimit')->with(1)->will($this->returnValue($this->mockQuery));
		$this->mockQuery->expects($this->once())->method('execute')->will($this->returnValue($queryResultArray));
		$this->assertNull($this->repository->findOneByFoo('bar'));
	}

}
