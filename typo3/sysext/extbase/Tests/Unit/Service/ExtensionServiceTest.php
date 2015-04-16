<?php
namespace TYPO3\CMS\Extbase\Tests\Unit\Service;

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
class ExtensionServiceTest extends \TYPO3\CMS\Core\Tests\UnitTestCase {

	/**
	 * @var \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface
	 */
	protected $mockConfigurationManager;

	/**
	 * @var \TYPO3\CMS\Extbase\Service\ExtensionService
	 */
	protected $extensionService;

	protected function setUp() {
		$GLOBALS['TYPO3_DB'] = $this->getMock(\TYPO3\CMS\Core\Database\DatabaseConnection::class, array('fullQuoteStr', 'exec_SELECTgetRows'));
		$GLOBALS['TSFE'] = new \stdClass();
		$this->extensionService = $this->getAccessibleMock(\TYPO3\CMS\Extbase\Service\ExtensionService::class, array('dummy'));
		$this->mockConfigurationManager = $this->getMock(\TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::class);
		$this->extensionService->_set('configurationManager', $this->mockConfigurationManager);
		$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extbase']['extensions'] = array(
			'ExtensionName' => array(
				'plugins' => array(
					'SomePlugin' => array(
						'controllers' => array(
							'ControllerName' => array(
								'actions' => array('index', 'otherAction')
							)
						)
					),
					'ThirdPlugin' => array(
						'controllers' => array(
							'ControllerName' => array(
								'actions' => array('otherAction', 'thirdAction')
							)
						)
					)
				)
			),
			'SomeOtherExtensionName' => array(
				'plugins' => array(
					'SecondPlugin' => array(
						'controllers' => array(
							'ControllerName' => array(
								'actions' => array('index', 'otherAction')
							),
							'SecondControllerName' => array(
								'actions' => array('someAction', 'someOtherAction'),
								'nonCacheableActions' => array('someOtherAction')
							)
						)
					)
				)
			)
		);
	}

	/**
	 * DataProvider for getPluginNamespaceByPluginSignatureTests()
	 *
	 * @return array
	 */
	public function getPluginNamespaceDataProvider() {
		return array(
			array('SomeExtension', 'SomePlugin', 'tx_someextension_someplugin'),
			array('NonExistingExtension', 'SomePlugin', 'tx_nonexistingextension_someplugin'),
			array('Invalid', '', 'tx_invalid_')
		);
	}

	/**
	 * @test
	 * @dataProvider getPluginNamespaceDataProvider
	 * @param string $extensionName
	 * @param string $pluginName
	 * @param mixed $expectedResult
	 */
	public function getPluginNamespaceTests($extensionName, $pluginName, $expectedResult) {
		$this->mockConfigurationManager->expects($this->once())->method('getConfiguration')->will($this->returnValue(array()));
		$actualResult = $this->extensionService->getPluginNamespace($extensionName, $pluginName);
		$this->assertEquals($expectedResult, $actualResult, 'Failing for extension: "' . $extensionName . '", plugin: "' . $pluginName . '"');
	}

	/**
	 * @test
	 */
	public function pluginNamespaceCanBeOverridden() {
		$this->mockConfigurationManager->expects($this->once())->method('getConfiguration')->with(\TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK, 'SomeExtension', 'SomePlugin')->will($this->returnValue(array('view' => array('pluginNamespace' => 'overridden_plugin_namespace'))));
		$expectedResult = 'overridden_plugin_namespace';
		$actualResult = $this->extensionService->getPluginNamespace('SomeExtension', 'SomePlugin');
		$this->assertEquals($expectedResult, $actualResult);
	}

	/**
	 * DataProvider for getPluginNameByActionTests()
	 *
	 * @return array
	 */
	public function getPluginNameByActionDataProvider() {
		return array(
			array('ExtensionName', 'ControllerName', 'someNonExistingAction', NULL),
			array('ExtensionName', 'ControllerName', 'index', 'SomePlugin'),
			array('ExtensionName', 'ControllerName', 'thirdAction', 'ThirdPlugin'),
			array('eXtEnSiOnNaMe', 'cOnTrOlLeRnAmE', 'thirdAction', NULL),
			array('eXtEnSiOnNaMe', 'cOnTrOlLeRnAmE', 'ThIrDaCtIoN', NULL),
			array('SomeOtherExtensionName', 'ControllerName', 'otherAction', 'SecondPlugin')
		);
	}

	/**
	 * @test
	 * @dataProvider getPluginNameByActionDataProvider
	 * @param string $extensionName
	 * @param string $controllerName
	 * @param string $actionName
	 * @param mixed $expectedResult
	 */
	public function getPluginNameByActionTests($extensionName, $controllerName, $actionName, $expectedResult) {
		$this->mockConfigurationManager->expects($this->once())->method('getConfiguration')->with(\TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK)->will($this->returnValue(array('view' => array('pluginNamespace' => 'overridden_plugin_namespace'))));
		$actualResult = $this->extensionService->getPluginNameByAction($extensionName, $controllerName, $actionName);
		$this->assertEquals($expectedResult, $actualResult, 'Failing for $extensionName: "' . $extensionName . '", $controllerName: "' . $controllerName . '", $actionName: "' . $actionName . '" - ');
	}

	/**
	 * @test
	 * @expectedException \TYPO3\CMS\Extbase\Exception
	 */
	public function getPluginNameByActionThrowsExceptionIfMoreThanOnePluginMatches() {
		$this->mockConfigurationManager->expects($this->once())->method('getConfiguration')->with(\TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK)->will($this->returnValue(array('view' => array('pluginNamespace' => 'overridden_plugin_namespace'))));
		$this->extensionService->getPluginNameByAction('ExtensionName', 'ControllerName', 'otherAction');
	}

	/**
	 * @test
	 */
	public function getPluginNameByActionReturnsCurrentIfItCanHandleTheActionEvenIfMoreThanOnePluginMatches() {
		$this->mockConfigurationManager->expects($this->once())->method('getConfiguration')->with(\TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK)->will($this->returnValue(array('extensionName' => 'CurrentExtension', 'pluginName' => 'CurrentPlugin', 'controllerConfiguration' => array('ControllerName' => array('actions' => array('otherAction'))))));
		$actualResult = $this->extensionService->getPluginNameByAction('CurrentExtension', 'ControllerName', 'otherAction');
		$expectedResult = 'CurrentPlugin';
		$this->assertEquals($expectedResult, $actualResult);
	}

	/**
	 * @test
	 */
	public function isActionCacheableReturnsTrueByDefault() {
		$mockConfiguration = array();
		$this->mockConfigurationManager->expects($this->any())->method('getConfiguration')->will($this->returnValue($mockConfiguration));
		$actualResult = $this->extensionService->isActionCacheable('SomeExtension', 'SomePlugin', 'SomeController', 'someAction');
		$this->assertTrue($actualResult);
	}

	/**
	 * @test
	 */
	public function isActionCacheableReturnsFalseIfActionIsNotCacheable() {
		$mockConfiguration = array(
			'controllerConfiguration' => array(
				'SomeController' => array(
					'nonCacheableActions' => array('someAction')
				)
			)
		);
		$this->mockConfigurationManager->expects($this->any())->method('getConfiguration')->will($this->returnValue($mockConfiguration));
		$actualResult = $this->extensionService->isActionCacheable('SomeExtension', 'SomePlugin', 'SomeController', 'someAction');
		$this->assertFalse($actualResult);
	}

	/**
	 * @test
	 */
	public function getTargetPidByPluginSignatureReturnsNullIfConfigurationManagerIsNotInitialized() {
		$this->mockConfigurationManager->expects($this->once())->method('getConfiguration')->will($this->returnValue(NULL));
		$this->assertNull($this->extensionService->getTargetPidByPlugin('ExtensionName', 'PluginName'));
	}

	/**
	 * @test
	 */
	public function getTargetPidByPluginSignatureReturnsNullIfDefaultPidIsZero() {
		$this->mockConfigurationManager->expects($this->once())->method('getConfiguration')->will($this->returnValue(array('view' => array('defaultPid' => 0))));
		$this->assertNull($this->extensionService->getTargetPidByPlugin('ExtensionName', 'PluginName'));
	}

	/**
	 * @test
	 */
	public function getTargetPidByPluginSignatureReturnsTheConfiguredDefaultPid() {
		$this->mockConfigurationManager->expects($this->once())->method('getConfiguration')->will($this->returnValue(array('view' => array('defaultPid' => 123))));
		$expectedResult = 123;
		$actualResult = $this->extensionService->getTargetPidByPlugin('ExtensionName', 'SomePlugin');
		$this->assertEquals($expectedResult, $actualResult);
	}

	/**
	 * @test
	 */
	public function getTargetPidByPluginSignatureDeterminesTheTargetPidIfDefaultPidIsAuto() {
		$this->mockConfigurationManager->expects($this->once())->method('getConfiguration')->will($this->returnValue(array('view' => array('defaultPid' => 'auto'))));
		$pluginSignature = 'extensionname_someplugin';
		$GLOBALS['TSFE']->sys_page = $this->getMock(\TYPO3\CMS\Frontend\Page\PageRepository::class, array('enableFields'));
		$GLOBALS['TSFE']->sys_page->expects($this->once())->method('enableFields')->with('tt_content')->will($this->returnValue(' AND enable_fields'));
		$GLOBALS['TYPO3_DB']->expects($this->once())->method('fullQuoteStr')->with($pluginSignature, 'tt_content')->will($this->returnValue('"pluginSignature"'));
		$GLOBALS['TYPO3_DB']->expects($this->once())->method('exec_SELECTgetRows')->with('pid', 'tt_content', 'list_type="pluginSignature" AND CType="list" AND enable_fields AND sys_language_uid=', '', '')->will($this->returnValue(array(array('pid' => '321'))));
		$expectedResult = 321;
		$actualResult = $this->extensionService->getTargetPidByPlugin('ExtensionName', 'SomePlugin');
		$this->assertEquals($expectedResult, $actualResult);
	}

	/**
	 * @test
	 */
	public function getTargetPidByPluginSignatureReturnsNullIfTargetPidCouldNotBeDetermined() {
		$this->mockConfigurationManager->expects($this->once())->method('getConfiguration')->will($this->returnValue(array('view' => array('defaultPid' => 'auto'))));
		$GLOBALS['TSFE']->sys_page = $this->getMock(\TYPO3\CMS\Frontend\Page\PageRepository::class, array('enableFields'));
		$GLOBALS['TSFE']->sys_page->expects($this->once())->method('enableFields')->will($this->returnValue(' AND enable_fields'));
		$GLOBALS['TYPO3_DB']->expects($this->once())->method('fullQuoteStr')->will($this->returnValue('"pluginSignature"'));
		$GLOBALS['TYPO3_DB']->expects($this->once())->method('exec_SELECTgetRows')->will($this->returnValue(array()));
		$this->assertNull($this->extensionService->getTargetPidByPlugin('ExtensionName', 'SomePlugin'));
	}

	/**
	 * @test
	 * @expectedException \TYPO3\CMS\Extbase\Exception
	 */
	public function getTargetPidByPluginSignatureThrowsExceptionIfMoreThanOneTargetPidsWereFound() {
		$this->mockConfigurationManager->expects($this->once())->method('getConfiguration')->will($this->returnValue(array('view' => array('defaultPid' => 'auto'))));
		$GLOBALS['TSFE']->sys_page = $this->getMock(\TYPO3\CMS\Frontend\Page\PageRepository::class, array('enableFields'));
		$GLOBALS['TSFE']->sys_page->expects($this->once())->method('enableFields')->will($this->returnValue(' AND enable_fields'));
		$GLOBALS['TYPO3_DB']->expects($this->once())->method('fullQuoteStr')->will($this->returnValue('"pluginSignature"'));
		$GLOBALS['TYPO3_DB']->expects($this->once())->method('exec_SELECTgetRows')->will($this->returnValue(array(array('pid' => 123), array('pid' => 124))));
		$this->extensionService->getTargetPidByPlugin('ExtensionName', 'SomePlugin');
	}

	/**
	 * @test
	 */
	public function getDefaultControllerNameByPluginReturnsNullIfGivenExtensionCantBeFound() {
		$this->assertNull($this->extensionService->getDefaultControllerNameByPlugin('NonExistingExtensionName', 'SomePlugin'));
	}

	/**
	 * @test
	 */
	public function getDefaultControllerNameByPluginReturnsNullIfGivenPluginCantBeFound() {
		$this->assertNull($this->extensionService->getDefaultControllerNameByPlugin('ExtensionName', 'NonExistingPlugin'));
	}

	/**
	 * @test
	 */
	public function getDefaultControllerNameByPluginReturnsFirstControllerNameOfGivenPlugin() {
		$expectedResult = 'ControllerName';
		$actualResult = $this->extensionService->getDefaultControllerNameByPlugin('ExtensionName', 'SomePlugin');
		$this->assertEquals($expectedResult, $actualResult);
	}

	/**
	 * @test
	 */
	public function getDefaultActionNameByPluginAndControllerReturnsNullIfGivenExtensionCantBeFound() {
		$this->assertNull($this->extensionService->getDefaultActionNameByPluginAndController('NonExistingExtensionName', 'SomePlugin', 'ControllerName'));
	}

	/**
	 * @test
	 */
	public function getDefaultActionNameByPluginAndControllerReturnsNullIfGivenPluginCantBeFound() {
		$this->assertNull($this->extensionService->getDefaultActionNameByPluginAndController('ExtensionName', 'NonExistingPlugin', 'ControllerName'));
	}

	/**
	 * @test
	 */
	public function getDefaultActionNameByPluginAndControllerReturnsNullIfGivenControllerCantBeFound() {
		$this->assertNull($this->extensionService->getDefaultActionNameByPluginAndController('ExtensionName', 'SomePlugin', 'NonExistingControllerName'));
	}

	/**
	 * @test
	 */
	public function getDefaultActionNameByPluginAndControllerReturnsFirstActionNameOfGivenController() {
		$expectedResult = 'someAction';
		$actualResult = $this->extensionService->getDefaultActionNameByPluginAndController('SomeOtherExtensionName', 'SecondPlugin', 'SecondControllerName');
		$this->assertEquals($expectedResult, $actualResult);
	}

}
