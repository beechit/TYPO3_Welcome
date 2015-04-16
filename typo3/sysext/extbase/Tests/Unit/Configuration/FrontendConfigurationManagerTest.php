<?php
namespace TYPO3\CMS\Extbase\Tests\Unit\Configuration;

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
class FrontendConfigurationManagerTest extends \TYPO3\CMS\Core\Tests\UnitTestCase {

	/**
	 * @var \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer|\PHPUnit_Framework_MockObject_MockObject
	 */
	protected $mockContentObject;

	/**
	 * @var \TYPO3\CMS\Extbase\Configuration\FrontendConfigurationManager|\PHPUnit_Framework_MockObject_MockObject|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface
	 */
	protected $frontendConfigurationManager;

	/**
	 * @var \TYPO3\CMS\Extbase\Service\TypoScriptService|\PHPUnit_Framework_MockObject_MockObject|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface
	 */
	protected $mockTypoScriptService;

	/**
	 * Sets up this testcase
	 */
	protected function setUp() {
		$GLOBALS['TSFE'] = new \stdClass();
		$GLOBALS['TSFE']->tmpl = new \stdClass();
		$this->mockContentObject = $this->getMock(\TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer::class, array('getTreeList'));
		$this->frontendConfigurationManager = $this->getAccessibleMock(\TYPO3\CMS\Extbase\Configuration\FrontendConfigurationManager::class, array('dummy'));
		$this->frontendConfigurationManager->_set('contentObject', $this->mockContentObject);
		$this->mockTypoScriptService = $this->getAccessibleMock(\TYPO3\CMS\Extbase\Service\TypoScriptService::class);
		$this->frontendConfigurationManager->_set('typoScriptService', $this->mockTypoScriptService);
	}

	/**
	 * @test
	 */
	public function getTypoScriptSetupReturnsSetupFromTsfe() {
		$GLOBALS['TSFE']->tmpl->setup = array('foo' => 'bar');
		$this->assertEquals(array('foo' => 'bar'), $this->frontendConfigurationManager->_call('getTypoScriptSetup'));
	}

	/**
	 * @test
	 */
	public function getPluginConfigurationReturnsEmptyArrayIfNoPluginConfigurationWasFound() {
		$GLOBALS['TSFE']->tmpl->setup = array('foo' => 'bar');
		$expectedResult = array();
		$actualResult = $this->frontendConfigurationManager->_call('getPluginConfiguration', 'SomeExtensionName', 'SomePluginName');
		$this->assertEquals($expectedResult, $actualResult);
	}

	/**
	 * @test
	 */
	public function getPluginConfigurationReturnsExtensionConfiguration() {
		$testSettings = array(
			'settings.' => array(
				'foo' => 'bar'
			)
		);
		$testSettingsConverted = array(
			'settings' => array(
				'foo' => 'bar'
			)
		);
		$testSetup = array(
			'plugin.' => array(
				'tx_someextensionname.' => $testSettings
			)
		);
		$this->mockTypoScriptService->expects($this->any())->method('convertTypoScriptArrayToPlainArray')->with($testSettings)->will($this->returnValue($testSettingsConverted));
		$GLOBALS['TSFE']->tmpl->setup = $testSetup;
		$expectedResult = array(
			'settings' => array(
				'foo' => 'bar'
			)
		);
		$actualResult = $this->frontendConfigurationManager->_call('getPluginConfiguration', 'SomeExtensionName');
		$this->assertEquals($expectedResult, $actualResult);
	}

	/**
	 * @test
	 */
	public function getPluginConfigurationReturnsPluginConfiguration() {
		$testSettings = array(
			'settings.' => array(
				'foo' => 'bar'
			)
		);
		$testSettingsConverted = array(
			'settings' => array(
				'foo' => 'bar'
			)
		);
		$testSetup = array(
			'plugin.' => array(
				'tx_someextensionname_somepluginname.' => $testSettings
			)
		);
		$this->mockTypoScriptService->expects($this->any())->method('convertTypoScriptArrayToPlainArray')->with($testSettings)->will($this->returnValue($testSettingsConverted));
		$GLOBALS['TSFE']->tmpl->setup = $testSetup;
		$expectedResult = array(
			'settings' => array(
				'foo' => 'bar'
			)
		);
		$actualResult = $this->frontendConfigurationManager->_call('getPluginConfiguration', 'SomeExtensionName', 'SomePluginName');
		$this->assertEquals($expectedResult, $actualResult);
	}

	/**
	 * @test
	 */
	public function getPluginConfigurationRecursivelyMergesExtensionAndPluginConfiguration() {
		$testExtensionSettings = array(
			'settings.' => array(
				'foo' => 'bar',
				'some.' => array(
					'nested' => 'value'
				)
			)
		);
		$testExtensionSettingsConverted = array(
			'settings' => array(
				'foo' => 'bar',
				'some' => array(
					'nested' => 'value'
				)
			)
		);
		$testPluginSettings = array(
			'settings.' => array(
				'some.' => array(
					'nested' => 'valueOverridde',
					'new' => 'value'
				)
			)
		);
		$testPluginSettingsConverted = array(
			'settings' => array(
				'some' => array(
					'nested' => 'valueOverridde',
					'new' => 'value'
				)
			)
		);
		$testSetup = array(
			'plugin.' => array(
				'tx_someextensionname.' => $testExtensionSettings,
				'tx_someextensionname_somepluginname.' => $testPluginSettings
			)
		);
		$this->mockTypoScriptService->expects($this->at(0))->method('convertTypoScriptArrayToPlainArray')->with($testExtensionSettings)->will($this->returnValue($testExtensionSettingsConverted));
		$this->mockTypoScriptService->expects($this->at(1))->method('convertTypoScriptArrayToPlainArray')->with($testPluginSettings)->will($this->returnValue($testPluginSettingsConverted));
		$GLOBALS['TSFE']->tmpl->setup = $testSetup;
		$expectedResult = array(
			'settings' => array(
				'foo' => 'bar',
				'some' => array(
					'nested' => 'valueOverridde',
					'new' => 'value'
				)
			)
		);
		$actualResult = $this->frontendConfigurationManager->_call('getPluginConfiguration', 'SomeExtensionName', 'SomePluginName');
		$this->assertEquals($expectedResult, $actualResult);
	}

	/**
	 * @test
	 */
	public function getSwitchableControllerActionsReturnsEmptyArrayByDefault() {
		$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extbase'] = NULL;
		$expectedResult = array();
		$actualResult = $this->frontendConfigurationManager->_call('getSwitchableControllerActions', 'SomeExtensionName', 'SomePluginName');
		$this->assertEquals($expectedResult, $actualResult);
	}

	/**
	 * @test
	 */
	public function getSwitchableControllerActionsReturnsConfigurationStoredInExtconf() {
		$testSwitchableControllerActions = array(
			'Controller1' => array(
				'actions' => array(
					'action1',
					'action2'
				),
				'nonCacheableActions' => array(
					'action1'
				)
			),
			'Controller2' => array(
				'actions' => array(
					'action3',
					'action4'
				)
			)
		);
		$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extbase']['extensions']['SomeExtensionName']['plugins']['SomePluginName']['controllers'] = $testSwitchableControllerActions;
		$expectedResult = $testSwitchableControllerActions;
		$actualResult = $this->frontendConfigurationManager->_call('getSwitchableControllerActions', 'SomeExtensionName', 'SomePluginName');
		$this->assertEquals($expectedResult, $actualResult);
	}

	/**
	 * @test
	 */
	public function overrideSwitchableControllerActionsFromFlexFormReturnsUnchangedFrameworkConfigurationIfNoFlexFormConfigurationIsFound() {
		$frameworkConfiguration = array(
			'pluginName' => 'Pi1',
			'extensionName' => 'SomeExtension',
			'controllerConfiguration' => array(
				'Controller1' => array(
					'controller' => 'Controller1',
					'actions' => 'action1 , action2'
				),
				'Controller2' => array(
					'controller' => 'Controller2',
					'actions' => 'action2 , action1,action3',
					'nonCacheableActions' => 'action2, action3'
				)
			)
		);
		$flexFormConfiguration = array();
		$actualResult = $this->frontendConfigurationManager->_call('overrideSwitchableControllerActionsFromFlexForm', $frameworkConfiguration, $flexFormConfiguration);
		$this->assertSame($frameworkConfiguration, $actualResult);
	}

	/**
	 * @test
	 */
	public function overrideSwitchableControllerActionsFromFlexFormMergesNonCacheableActions() {
		$frameworkConfiguration = array(
			'pluginName' => 'Pi1',
			'extensionName' => 'SomeExtension',
			'controllerConfiguration' => array(
				'Controller1' => array(
					'actions' => array('action1 , action2')
				),
				'Controller2' => array(
					'actions' => array('action2', 'action1', 'action3'),
					'nonCacheableActions' => array('action2', 'action3')
				)
			)
		);
		$flexFormConfiguration = array(
			'switchableControllerActions' => 'Controller1  -> action2;Controller2->action3;  Controller2->action1'
		);
		$expectedResult = array(
			'pluginName' => 'Pi1',
			'extensionName' => 'SomeExtension',
			'controllerConfiguration' => array(
				'Controller1' => array(
					'actions' => array('action2')
				),
				'Controller2' => array(
					'actions' => array('action3', 'action1'),
					'nonCacheableActions' => array(1 => 'action3')
				)
			)
		);
		$actualResult = $this->frontendConfigurationManager->_call('overrideSwitchableControllerActionsFromFlexForm', $frameworkConfiguration, $flexFormConfiguration);
		$this->assertEquals($expectedResult, $actualResult);
	}

	/**
	 * @test
	 * @expectedException \TYPO3\CMS\Extbase\Configuration\Exception\ParseErrorException
	 */
	public function overrideSwitchableControllerActionsThrowsExceptionIfFlexFormConfigurationIsInvalid() {
		$frameworkConfiguration = array(
			'pluginName' => 'Pi1',
			'extensionName' => 'SomeExtension',
			'controllerConfiguration' => array(
				'Controller1' => array(
					'actions' => array('action1 , action2')
				),
				'Controller2' => array(
					'actions' => array('action2', 'action1', 'action3'),
					'nonCacheableActions' => array('action2', 'action3')
				)
			)
		);
		$flexFormConfiguration = array(
			'switchableControllerActions' => 'Controller1->;Controller2->action3;Controller2->action1'
		);
		$this->frontendConfigurationManager->_call('overrideSwitchableControllerActionsFromFlexForm', $frameworkConfiguration, $flexFormConfiguration);
	}

	/**
	 * @test
	 */
	public function getContextSpecificFrameworkConfigurationCorrectlyCallsOverrideMethods() {
		$frameworkConfiguration = array(
			'some' => array(
				'framework' => 'configuration'
			)
		);
		/** @var \TYPO3\CMS\Extbase\Configuration\FrontendConfigurationManager|\PHPUnit_Framework_MockObject_MockObject|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface */
		$frontendConfigurationManager = $this->getAccessibleMock(\TYPO3\CMS\Extbase\Configuration\FrontendConfigurationManager::class, array('overrideStoragePidIfStartingPointIsSet', 'overrideConfigurationFromPlugin', 'overrideConfigurationFromFlexForm'));
		$frontendConfigurationManager->expects($this->at(0))->method('overrideStoragePidIfStartingPointIsSet')->with($frameworkConfiguration)->will($this->returnValue(array('overridden' => 'storagePid')));
		$frontendConfigurationManager->expects($this->at(1))->method('overrideConfigurationFromPlugin')->with(array('overridden' => 'storagePid'))->will($this->returnValue(array('overridden' => 'pluginConfiguration')));
		$frontendConfigurationManager->expects($this->at(2))->method('overrideConfigurationFromFlexForm')->with(array('overridden' => 'pluginConfiguration'))->will($this->returnValue(array('overridden' => 'flexFormConfiguration')));
		$expectedResult = array('overridden' => 'flexFormConfiguration');
		$actualResult = $frontendConfigurationManager->_call('getContextSpecificFrameworkConfiguration', $frameworkConfiguration);
		$this->assertEquals($expectedResult, $actualResult);
	}

	/**
	 * @test
	 */
	public function storagePidsAreExtendedIfRecursiveSearchIsConfigured() {
		$storagePid = '3,5,9';
		$recursive = 99;
		/** @var $abstractConfigurationManager \TYPO3\CMS\Extbase\Configuration\FrontendConfigurationManager */
		$abstractConfigurationManager = $this->getAccessibleMock(\TYPO3\CMS\Extbase\Configuration\FrontendConfigurationManager::class, array('overrideSwitchableControllerActions', 'getContextSpecificFrameworkConfiguration', 'getTypoScriptSetup', 'getPluginConfiguration', 'getSwitchableControllerActions'));
		/** @var $cObjectMock \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer */
		$cObjectMock = $this->getMock(\TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer::class);
		$cObjectMock->expects($this->any())
			->method('getTreeList')
			->will($this->onConsecutiveCalls('4', '', '898,12'));
		$abstractConfigurationManager->setContentObject($cObjectMock);

		$expectedResult = '4,898,12';
		$actualResult = $abstractConfigurationManager->_call('getRecursiveStoragePids', $storagePid, $recursive);
		$this->assertEquals($expectedResult, $actualResult);
	}

	/**
	 * @test
	 */
	public function storagePidsAreExtendedIfRecursiveSearchIsConfiguredAndWithPidIncludedForNegativePid() {
		$storagePid = '-3,5,9';
		$recursive = 99;
		/** @var $abstractConfigurationManager \TYPO3\CMS\Extbase\Configuration\FrontendConfigurationManager */
		$abstractConfigurationManager = $this->getAccessibleMock(\TYPO3\CMS\Extbase\Configuration\FrontendConfigurationManager::class, array('overrideSwitchableControllerActions', 'getContextSpecificFrameworkConfiguration', 'getTypoScriptSetup', 'getPluginConfiguration', 'getSwitchableControllerActions'));
		/** @var $cObjectMock \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer */
		$cObjectMock = $this->getMock(\TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer::class);
		$cObjectMock->expects($this->any())
			->method('getTreeList')
			->will($this->onConsecutiveCalls('3,4', '', '898,12'));
		$abstractConfigurationManager->setContentObject($cObjectMock);

		$expectedResult = '3,4,898,12';
		$actualResult = $abstractConfigurationManager->_call('getRecursiveStoragePids', $storagePid, $recursive);
		$this->assertEquals($expectedResult, $actualResult);
	}

	/**
	 * @test
	 */
	public function storagePidsAreNotExtendedIfRecursiveSearchIsNotConfigured() {
		$storagePid = '1,2,3';

		/** @var $abstractConfigurationManager \TYPO3\CMS\Extbase\Configuration\FrontendConfigurationManager */
		$abstractConfigurationManager = $this->getAccessibleMock(\TYPO3\CMS\Extbase\Configuration\FrontendConfigurationManager::class, array('overrideSwitchableControllerActions', 'getContextSpecificFrameworkConfiguration', 'getTypoScriptSetup', 'getPluginConfiguration', 'getSwitchableControllerActions'));
		/** @var $cObjectMock \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer */
		$cObjectMock = $this->getMock(\TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer::class);
		$cObjectMock->expects($this->never())->method('getTreeList');
		$abstractConfigurationManager->setContentObject($cObjectMock);

		$expectedResult = '1,2,3';
		$actualResult = $abstractConfigurationManager->_call('getRecursiveStoragePids', $storagePid);
		$this->assertEquals($expectedResult, $actualResult);
	}

	/**
	 * @test
	 */
	public function storagePidsAreNotExtendedIfRecursiveSearchIsConfiguredForZeroLevels() {
		$storagePid = '1,2,3';
		$recursive = 0;

		$abstractConfigurationManager = $this->getAccessibleMock(\TYPO3\CMS\Extbase\Configuration\FrontendConfigurationManager::class, array('overrideSwitchableControllerActions', 'getContextSpecificFrameworkConfiguration', 'getTypoScriptSetup', 'getPluginConfiguration', 'getSwitchableControllerActions'));

		/** @var $cObjectMock \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer */
		$cObjectMock = $this->getMock(\TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer::class);
		$cObjectMock->expects($this->never())->method('getTreeList');
		$abstractConfigurationManager->setContentObject($cObjectMock);

		$expectedResult = '1,2,3';
		$actualResult = $abstractConfigurationManager->_call('getRecursiveStoragePids', $storagePid, $recursive);
		$this->assertEquals($expectedResult, $actualResult);
	}

	/**
	 * @test
	 * @author Alexander Schnitzler <alex.schnitzler@typovision.de>
	 */
	public function mergeConfigurationIntoFrameworkConfigurationWorksAsExpected() {
		$configuration = array(
			'persistence' => array(
				'storagePid' => '0,1,2,3'
			)
		);

		$frameworkConfiguration = array('persistence' => array('storagePid' => '98'));
		$this->assertSame(
			array('persistence' => array('storagePid' => '0,1,2,3')),
			$this->frontendConfigurationManager->_call('mergeConfigurationIntoFrameworkConfiguration', $frameworkConfiguration, $configuration, 'persistence')
		);
	}

	/**
	 * @test
	 * @author Alexander Schnitzler <alex.schnitzler@typovision.de>
	 */
	public function overrideStoragePidIfStartingPointIsSetOverridesCorrectly() {
		$this->mockContentObject->expects($this->any())->method('getTreeList')->will($this->returnValue('1,2,3'));
		$this->mockContentObject->data = array('pages' => '0', 'recursive' => 1);

		$frameworkConfiguration = array('persistence' => array('storagePid' => '98'));
		$this->assertSame(
			array('persistence' => array('storagePid' => '0,1,2,3')),
			$this->frontendConfigurationManager->_call('overrideStoragePidIfStartingPointIsSet', $frameworkConfiguration)
		);
	}

	/**
	 * @test
	 */
	public function overrideConfigurationFromFlexFormChecksForDataIsString() {
		/** @var $flexFormService \TYPO3\CMS\Extbase\Service\FlexFormService|\PHPUnit_Framework_MockObject_MockObject */
		$flexFormService = $this->getMock(\TYPO3\CMS\Extbase\Service\FlexFormService::class, array('convertFlexFormContentToArray'));
		$flexFormService->expects($this->once())->method('convertFlexFormContentToArray')->will($this->returnValue(array(
			'persistence' => array(
				'storagePid' => '0,1,2,3'
			)
		)));

		$this->frontendConfigurationManager->_set('flexFormService', $flexFormService);
		$this->mockContentObject->data = array('pi_flexform' => '<XML_ARRAY>');

		$frameworkConfiguration = array('persistence' => array('storagePid' => '98'));
		$this->assertSame(
			array('persistence' => array('storagePid' => '0,1,2,3')),
			$this->frontendConfigurationManager->_call('overrideConfigurationFromFlexForm', $frameworkConfiguration)
		);
	}

	/**
	 * @test
	 */
	public function overrideConfigurationFromFlexFormChecksForDataIsStringAndEmpty() {
		/** @var $flexFormService \TYPO3\CMS\Extbase\Service\FlexFormService|\PHPUnit_Framework_MockObject_MockObject */
		$flexFormService = $this->getMock(\TYPO3\CMS\Extbase\Service\FlexFormService::class, array('convertFlexFormContentToArray'));
		$flexFormService->expects($this->never())->method('convertFlexFormContentToArray');

		$this->frontendConfigurationManager->_set('flexFormService', $flexFormService);
		$this->mockContentObject->data = array('pi_flexform' => '');

		$frameworkConfiguration = array('persistence' => array('storagePid' => '98'));
		$this->assertSame(
			array('persistence' => array('storagePid' => '98')),
			$this->frontendConfigurationManager->_call('overrideConfigurationFromFlexForm', $frameworkConfiguration)
		);
	}

	/**
	 * @test
	 */
	public function overrideConfigurationFromFlexFormChecksForDataIsArray() {
		/** @var $flexFormService \TYPO3\CMS\Extbase\Service\FlexFormService|\PHPUnit_Framework_MockObject_MockObject */
		$flexFormService = $this->getMock(\TYPO3\CMS\Extbase\Service\FlexFormService::class, array('convertFlexFormContentToArray'));
		$flexFormService->expects($this->never())->method('convertFlexFormContentToArray');

		$this->frontendConfigurationManager->_set('flexFormService', $flexFormService);
		$this->mockContentObject->data = array('pi_flexform' => array('persistence' => array('storagePid' => '0,1,2,3')));

		$frameworkConfiguration = array('persistence' => array('storagePid' => '98'));
		$this->assertSame(
			array('persistence' => array('storagePid' => '0,1,2,3')),
			$this->frontendConfigurationManager->_call('overrideConfigurationFromFlexForm', $frameworkConfiguration)
		);
	}

	/**
	 * @test
	 */
	public function overrideConfigurationFromFlexFormChecksForDataIsArrayAndEmpty() {
		/** @var $flexFormService \TYPO3\CMS\Extbase\Service\FlexFormService|\PHPUnit_Framework_MockObject_MockObject */
		$flexFormService = $this->getMock(\TYPO3\CMS\Extbase\Service\FlexFormService::class, array('convertFlexFormContentToArray'));
		$flexFormService->expects($this->never())->method('convertFlexFormContentToArray');

		$this->frontendConfigurationManager->_set('flexFormService', $flexFormService);
		$this->mockContentObject->data = array('pi_flexform' => array());

		$frameworkConfiguration = array('persistence' => array('storagePid' => '98'));
		$this->assertSame(
			array('persistence' => array('storagePid' => '98')),
			$this->frontendConfigurationManager->_call('overrideConfigurationFromFlexForm', $frameworkConfiguration)
		);
	}

	/**
	 * @test
	 * @author Alexander Schnitzler <alex.schnitzler@typovision.de>
	 */
	public function overrideConfigurationFromPluginOverridesCorrectly() {
		/** @var $frontendConfigurationManager \TYPO3\CMS\Extbase\Configuration\FrontendConfigurationManager */
		$frontendConfigurationManager = $this->getAccessibleMock(\TYPO3\CMS\Extbase\Configuration\FrontendConfigurationManager::class, array('getTypoScriptSetup'));
		$frontendConfigurationManager->_set('contentObject', $this->mockContentObject);
		$frontendConfigurationManager->_set('typoScriptService', $this->mockTypoScriptService);

		$this->mockTypoScriptService->expects($this->once())->method('convertTypoScriptArrayToPlainArray')->will($this->returnValue(array(
			'persistence' => array(
				'storagePid' => '0,1,2,3'
			),
			'settings' => array(
				'foo' => 'bar'
			),
			'view' => array(
				'foo' => 'bar'
			),
		)));
		$frontendConfigurationManager->expects($this->any())->method('getTypoScriptSetup')->will($this->returnValue(array(
			'plugin.' => array(
				'tx_ext_pi1.' => array(
					'persistence.' => array(
						'storagePid' => '0,1,2,3'
					),
					'settings.' => array(
						'foo' => 'bar'
					),
					'view.' => array(
						'foo' => 'bar'
					),
				)
			)
		)));

		$frameworkConfiguration = array(
			'extensionName' => 'ext',
			'pluginName' => 'pi1',
			'persistence' => array(
				'storagePid' => '1'
			),
			'settings' => array(
				'foo' => 'qux'
			),
			'view' => array(
				'foo' => 'qux'
			),
		);
		$this->assertSame(
			array(
				'extensionName' => 'ext',
				'pluginName' => 'pi1',
				'persistence' => array(
					'storagePid' => '0,1,2,3',
				),
				'settings' => array(
					'foo' => 'bar'
				),
				'view' => array(
					'foo' => 'bar'
				),
			),
			$frontendConfigurationManager->_call('overrideConfigurationFromPlugin', $frameworkConfiguration)
		);
	}

}
