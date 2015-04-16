<?php
namespace TYPO3\CMS\Core\Tests\Unit\TypoScript\Parser;

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
 * Test case for \TYPO3\CMS\Core\TypoScript\Parser\TypoScriptParser
 */
class TypoScriptParserTest extends \TYPO3\CMS\Core\Tests\UnitTestCase {

	/**
	 * @var \TYPO3\CMS\Core\TypoScript\Parser\TypoScriptParser|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface
	 */
	protected $typoScriptParser = NULL;

	/**
	 * Set up
	 *
	 * @return void
	 */
	protected function setUp() {
		$accessibleClassName = $this->buildAccessibleProxy(\TYPO3\CMS\Core\TypoScript\Parser\TypoScriptParser::class);
		$this->typoScriptParser = new $accessibleClassName();
	}

	/**
	 * Data provider for executeValueModifierReturnsModifiedResult
	 *
	 * @return array modifier name, modifier arguments, current value, expected result
	 */
	public function executeValueModifierDataProvider() {
		return array(
			'prependString with string' => array(
				'prependString',
				'abc',
				'!',
				'!abc'
			),
			'prependString with empty string' => array(
				'prependString',
				'foo',
				'',
				'foo',
			),
			'appendString with string' => array(
				'appendString',
				'abc',
				'!',
				'abc!',
			),
			'appendString with empty string' => array(
				'appendString',
				'abc',
				'',
				'abc',
			),
			'removeString removes simple string' => array(
				'removeString',
				'abcdef',
				'bc',
				'adef',
			),
			'removeString removes nothing if no match' => array(
				'removeString',
				'abcdef',
				'foo',
				'abcdef',
			),
			'removeString removes multiple matches' => array(
				'removeString',
				'FooBarFoo',
				'Foo',
				'Bar',
			),
			'replaceString replaces simple match' => array(
				'replaceString',
				'abcdef',
				'bc|123',
				'a123def',
			),
			'replaceString replaces simple match with nothing' => array(
				'replaceString',
				'abcdef',
				'bc',
				'adef',
			),
			'replaceString replaces multiple matches' => array(
				'replaceString',
				'FooBarFoo',
				'Foo|Bar',
				'BarBarBar',
			),
			'addToList adds at end of existing list' => array(
				'addToList',
				'123,456',
				'789',
				'123,456,789',
			),
			'addToList adds at end of existing list including white-spaces' => array(
				'addToList',
				'123,456',
				' 789 , 32 , 12 ',
				'123,456, 789 , 32 , 12 ',
			),
			'addToList adds nothing' => array(
				'addToList',
				'123,456',
				'',
				'123,456,', // This result is probably not what we want (appended comma) ... fix it?
			),
			'addToList adds to empty list' => array(
				'addToList',
				'',
				'foo',
				'foo',
			),
			'removeFromList removes value from list' => array(
				'removeFromList',
				'123,456,789,abc',
				'456',
				'123,789,abc',
			),
			'removeFromList removes value at beginning of list' => array(
				'removeFromList',
				'123,456,abc',
				'123',
				'456,abc',
			),
			'removeFromList removes value at end of list' => array(
				'removeFromList',
				'123,456,abc',
				'abc',
				'123,456',
			),
			'removeFromList removes multiple values from list' => array(
				'removeFromList',
				'foo,123,bar,123',
				'123',
				'foo,bar',
			),
			'removeFromList removes empty values' => array(
				'removeFromList',
				'foo,,bar',
				'',
				'foo,bar',
			),
			'uniqueList removes duplicates' => array(
				'uniqueList',
				'123,456,abc,456,456',
				'',
				'123,456,abc',
			),
			'uniqueList removes duplicate empty list values' => array(
				'uniqueList',
				'123,,456,,abc',
				'',
				'123,,456,abc',
			),
			'reverseList returns list reversed' => array(
				'reverseList',
				'123,456,abc,456',
				'',
				'456,abc,456,123',
			),
			'reverseList keeps empty values' => array(
				'reverseList',
				',123,,456,abc,,456',
				'',
				'456,,abc,456,,123,',
			),
			'reverseList does not change single element' => array(
				'reverseList',
				'123',
				'',
				'123',
			),
			'sortList sorts a list' => array(
				'sortList',
				'10,100,0,20,abc',
				'',
				'0,10,20,100,abc',
			),
			'sortList sorts a list numeric' => array(
				'sortList',
				'10,0,100,-20,abc',
				'numeric',
				'-20,0,abc,10,100',
			),
			'sortList sorts a list descending' => array(
				'sortList',
				'10,100,0,20,abc,-20',
				'descending',
				'abc,100,20,10,0,-20',
			),
			'sortList sorts a list numeric descending' => array(
				'sortList',
				'10,100,0,20,abc,-20',
				'descending,numeric',
				'100,20,10,0,abc,-20',
			),
			'sortList ignores invalid modifier arguments' => array(
				'sortList',
				'10,100,20',
				'foo,descending,bar',
				'100,20,10',
			),
		);
	}

	/**
	 * @test
	 * @dataProvider executeValueModifierDataProvider
	 */
	public function executeValueModifierReturnsModifiedResult($modifierName, $currentValue, $modifierArgument, $expected) {
		$actualValue = $this->typoScriptParser->_call('executeValueModifier', $modifierName, $modifierArgument, $currentValue);
		$this->assertEquals($expected, $actualValue);
	}

	/**
	 * @param string $typoScript
	 * @param array $expected
	 * @dataProvider typoScriptIsParsedToArrayDataProvider
	 * @test
	 */
	public function typoScriptIsParsedToArray($typoScript, array $expected) {
		$this->typoScriptParser->parse($typoScript);
		$this->assertEquals($expected, $this->typoScriptParser->setup);
	}

	/**
	 * @return array
	 */
	public function typoScriptIsParsedToArrayDataProvider() {
		return array(
			'simple assignment' => array(
				'key = value',
				array(
					'key' => 'value',
				)
			),
			'simple assignment with escaped dot at the beginning' => array(
				'\\.key = value',
				array(
					'.key' => 'value',
				)
			),
			'simple assignment with protected escaped dot at the beginning' => array(
				'\\\\.key = value',
				array(
					'\\.' => array(
						'key' => 'value',
					),
				)
			),
			'nested assignment' => array(
				'lib.key = value',
				array(
					'lib.' => array(
						'key' => 'value',
					),
				),
			),
			'nested assignment with escaped key' => array(
				'lib\\.key = value',
				array(
					'lib.key' => 'value',
				),
			),
			'nested assignment with escaped key and escaped dot at the beginning' => array(
				'\\.lib\\.key = value',
				array(
					'.lib.key' => 'value',
				),
			),
			'nested assignment with protected escaped key' => array(
				'lib\\\\.key = value',
				array(
					'lib\\.' => array('key' => 'value'),
				),
			),
			'nested assignment with protected escaped key and protected escaped dot at the beginning' => array(
				'\\\\.lib\\\\.key = value',
				array(
					'\\.' => array(
						'lib\\.' => array('key' => 'value'),
					),
				),
			),
			'assignment with escaped an non escaped keys' => array(
				'firstkey.secondkey\\.thirdkey.setting = value',
				array(
					'firstkey.' => array(
						'secondkey.thirdkey.' => array(
							'setting' => 'value'
						)
					)
				)
			),
			'nested structured assignment' => array(
				'lib {' . LF .
					'key = value' . LF .
				'}',
				array(
					'lib.' => array(
						'key' => 'value',
					),
				),
			),
			'nested structured assignment with escaped key inside' => array(
				'lib {' . LF .
					'key\\.nextkey = value' . LF .
				'}',
				array(
					'lib.' => array(
						'key.nextkey' => 'value',
					),
				),
			),
			'nested structured assignment with escaped key inside and escaped dots at the beginning' => array(
				'\\.lib {' . LF .
					'\\.key\\.nextkey = value' . LF .
				'}',
				array(
					'.lib.' => array(
						'.key.nextkey' => 'value',
					),
				),
			),
			'nested structured assignment with protected escaped key inside' => array(
				'lib {' . LF .
				'key\\\\.nextkey = value' . LF .
				'}',
				array(
					'lib.' => array(
						'key\\.' => array('nextkey' => 'value'),
					),
				),
			),
			'nested structured assignment with protected escaped key inside and protected escaped dots at the beginning' => array(
				'\\\\.lib {' . LF .
					'\\\\.key\\\\.nextkey = value' . LF .
				'}',
				array(
					'\\.' => array(
						'lib.' => array(
							'\\.' => array(
								'key\\.' => array('nextkey' => 'value'),
							),
						),
					),
				),
			),
			'nested structured assignment with escaped key' => array(
				'lib\\.anotherkey {' . LF .
					'key = value' . LF .
				'}',
				array(
					'lib.anotherkey.' => array(
						'key' => 'value',
					),
				),
			),
			'nested structured assignment with protected escaped key' => array(
				'lib\\\\.anotherkey {' . LF .
				'key = value' . LF .
				'}',
				array(
					'lib\\.' => array(
						'anotherkey.' => array(
							'key' => 'value',
						),
					),
				),
			),
			'multiline assignment' => array(
				'key (' . LF .
					'first' . LF .
					'second' . LF .
				')',
				array(
					'key' => 'first' . LF . 'second',
				),
			),
			'multiline assignment with escaped key' => array(
				'key\\.nextkey (' . LF .
					'first' . LF .
					'second' . LF .
				')',
				array(
					'key.nextkey' => 'first' . LF . 'second',
				),
			),
			'multiline assignment with protected escaped key' => array(
				'key\\\\.nextkey (' . LF .
				'first' . LF .
				'second' . LF .
				')',
				array(
					'key\\.' => array('nextkey' => 'first' . LF . 'second'),
				),
			),
			'copying values' => array(
				'lib.default = value' . LF .
				'lib.copy < lib.default',
				array(
					'lib.' => array(
						'default' => 'value',
						'copy' => 'value',
					),
				),
			),
			'copying values with escaped key' => array(
				'lib\\.default = value' . LF .
				'lib.copy < lib\\.default',
				array(
					'lib.default' => 'value',
					'lib.' => array(
						'copy' => 'value',
					),
				),
			),
			'copying values with protected escaped key' => array(
				'lib\\\\.default = value' . LF .
				'lib.copy < lib\\\\.default',
				array(
					'lib\\.' => array('default' => 'value'),
					'lib.' => array(
						'copy' => 'value',
					),
				),
			),
			'one-line hash comment' => array(
				'first = 1' . LF .
				'# ignore = me' . LF .
				'second = 2',
				array(
					'first' => '1',
					'second' => '2',
				),
			),
			'one-line slash comment' => array(
				'first = 1' . LF .
				'// ignore = me' . LF .
				'second = 2',
				array(
					'first' => '1',
					'second' => '2',
				),
			),
			'multi-line slash comment' => array(
				'first = 1' . LF .
				'/*' . LF .
					'ignore = me' . LF .
				'*/' . LF .
				'second = 2',
				array(
					'first' => '1',
					'second' => '2',
				),
			),
			'nested assignment repeated segment names' => array(
				'test.test.test = 1',
				array(
					'test.' => array(
						'test.' => array(
							'test' => '1',
						),
					)
				),
			),
			'simple assignment operator with tab character before "="' => array(
				'test	 = someValue',
				array(
					'test' => 'someValue',
				),
			),
			'simple assignment operator character as value "="' => array(
				'test ==TEST=',
				array(
					'test' => '=TEST=',
				),
			),
			'nested assignment operator character as value "="' => array(
				'test.test ==TEST=',
				array(
					'test.' => array(
						'test' => '=TEST=',
					),
				),
			),
			'simple assignment character as value "<"' => array(
				'test =<TEST>',
				array(
					'test' => '<TEST>',
				),
			),
			'nested assignment character as value "<"' => array(
				'test.test =<TEST>',
				array(
					'test.' => array(
						'test' => '<TEST>',
					),
				),
			),
			'simple assignment character as value ">"' => array(
				'test =>TEST<',
				array(
					'test' => '>TEST<',
				),
			),
			'nested assignment character as value ">"' => array(
				'test.test =>TEST<',
				array(
					'test.' => array(
						'test' => '>TEST<',
					),
				),
			),
			'nested assignment repeated segment names with whitespaces' => array(
				'test.test.test = 1' . " \t",
				array(
					'test.' => array(
						'test.' => array(
							'test' => '1',
						),
					)
				),
			),
			'simple assignment operator character as value "=" with whitespaces' => array(
				'test = =TEST=' . " \t",
				array(
					'test' => '=TEST=',
				),
			),
			'nested assignment operator character as value "=" with whitespaces' => array(
				'test.test = =TEST=' . " \t",
				array(
					'test.' => array(
						'test' => '=TEST=',
					),
				),
			),
			'simple assignment character as value "<" with whitespaces' => array(
				'test = <TEST>' . " \t",
				array(
					'test' => '<TEST>',
				),
			),
			'nested assignment character as value "<" with whitespaces' => array(
				'test.test = <TEST>' . " \t",
				array(
					'test.' => array(
						'test' => '<TEST>',
					),
				),
			),
			'simple assignment character as value ">" with whitespaces' => array(
				'test = >TEST<' . " \t",
				array(
					'test' => '>TEST<',
				),
			),
			'nested assignment character as value ">" with whitespaces' => array(
				'test.test = >TEST<',
				array(
					'test.' => array(
						'test' => '>TEST<',
					),
				),
			),
			'CSC example #1' => array(
				'linkParams.ATagParams.dataWrap =  class="{$styles.content.imgtext.linkWrap.lightboxCssClass}" rel="{$styles.content.imgtext.linkWrap.lightboxRelAttribute}"',
				array(
					'linkParams.' => array(
						'ATagParams.' => array(
							'dataWrap' => 'class="{$styles.content.imgtext.linkWrap.lightboxCssClass}" rel="{$styles.content.imgtext.linkWrap.lightboxRelAttribute}"',
						),
					),
				),
			),
			'CSC example #2' => array(
				'linkParams.ATagParams {' . LF .
					'dataWrap = class="{$styles.content.imgtext.linkWrap.lightboxCssClass}" rel="{$styles.content.imgtext.linkWrap.lightboxRelAttribute}"' . LF .
				'}',
				array(
					'linkParams.' => array(
						'ATagParams.' => array(
							'dataWrap' => 'class="{$styles.content.imgtext.linkWrap.lightboxCssClass}" rel="{$styles.content.imgtext.linkWrap.lightboxRelAttribute}"',
						),
					),
				),
			),
			'CSC example #3' => array(
				'linkParams.ATagParams.dataWrap (' . LF .
					'class="{$styles.content.imgtext.linkWrap.lightboxCssClass}" rel="{$styles.content.imgtext.linkWrap.lightboxRelAttribute}"' . LF .
				')',
				array(
					'linkParams.' => array(
						'ATagParams.' => array(
							'dataWrap' => 'class="{$styles.content.imgtext.linkWrap.lightboxCssClass}" rel="{$styles.content.imgtext.linkWrap.lightboxRelAttribute}"',
						),
					),
				),
			),
			'key with colon' => array(
				'some:key = is valid',
				array(
					'some:key' => 'is valid'
				)
			),
			'special operator' => array(
				'some := addToList(a)',
				array(
					'some' => 'a'
				)
			),
			'special operator with white-spaces' => array(
				'some := addToList (a)',
				array(
					'some' => 'a'
				)
			),
			'special operator with tabs' => array(
				'some :=	addToList	(a)',
				array(
					'some' => 'a'
				)
			),
			'special operator with white-spaces and tabs in value' => array(
				'some := addToList( a, b,	c )',
				array(
					'some' => 'a, b,	c'
				)
			),
			'special operator and colon, no spaces' => array(
				'some:key:=addToList(a)',
				array(
					'some:key' => 'a'
				)
			),
			'key with all special symbols' => array(
				'someSpecial\\_:-\\.Chars = is valid',
				array(
					'someSpecial\\_:-.Chars' => 'is valid'
				)
			),
		);
	}

	/**
	 * @test
	 */
	public function setValCanBeCalledWithArrayValueParameter() {
		$string = '';
		$setup = array();
		$value = array();
		$this->typoScriptParser->setVal($string, $setup, $value);
	}

	/**
	 * @test
	 */
	public function setValCanBeCalledWithStringValueParameter() {
		$string = '';
		$setup = array();
		$value = '';
		$this->typoScriptParser->setVal($string, $setup, $value);
	}

	/**
	 * @test
	 * @dataProvider parseNextKeySegmentReturnsCorrectNextKeySegmentDataProvider
	 */
	public function parseNextKeySegmentReturnsCorrectNextKeySegment($key, $expectedKeySegment, $expectedRemainingKey) {
		list($keySegment, $remainingKey) = $this->typoScriptParser->_call('parseNextKeySegment', $key);
		$this->assertSame($expectedKeySegment, $keySegment);
		$this->assertSame($expectedRemainingKey, $remainingKey);
	}

	/**
	 * @return array
	 */
	public function parseNextKeySegmentReturnsCorrectNextKeySegmentDataProvider() {
		return array(
			'key without separator' => array(
				'testkey',
				'testkey',
				''
			),
			'key with normal separator' => array(
				'test.key',
				'test',
				'key'
			),
			'key with multiple normal separators' => array(
				'test.key.subkey',
				'test',
				'key.subkey'
			),
			'key with separator and escape character' => array(
				'te\\st.test',
				'te\\st',
				'test'
			),
			'key with escaped separators' => array(
				'test\\.key\\.subkey',
				'test.key.subkey',
				''
			),
			'key with escaped and unescaped separator 1' => array(
				'test.test\\.key',
				'test',
				'test\\.key'
			),
			'key with escaped and unescaped separator 2' => array(
				'test\\.test.key\\.key2',
				'test.test',
				'key\\.key2'
			),
			'key with escaped escape character' => array(
				'test\\\\.key',
				'test\\',
				'key'
			),
			'key with escaped separator and additional escape character' => array(
				'test\\\\\\.key',
				'test\\\\',
				'key'
			),

		    'multiple escape characters within the key are preserved' => array(
				'te\\\\st\\\\.key',
				'te\\\\st\\',
				'key'
		    )
		);
	}

}
