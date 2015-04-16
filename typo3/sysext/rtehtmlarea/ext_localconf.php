<?php
defined('TYPO3_MODE') or die();

if (!$TYPO3_CONF_VARS['BE']['RTEenabled']) {
	$TYPO3_CONF_VARS['BE']['RTEenabled'] = 1;
}

// Registering the RTE object
$TYPO3_CONF_VARS['BE']['RTE_reg'][$_EXTKEY] = array('objRef' => '&TYPO3\\CMS\\Rtehtmlarea\\RteHtmlAreaBase');

// Make the extension version number available to the extension scripts
require_once \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($_EXTKEY) . 'ext_emconf.php';

$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['version'] = $EM_CONF[$_EXTKEY]['version'];
// Unserializing the configuration so we can use it here
$_EXTCONF = unserialize($_EXTCONF);

// Add default RTE transformation configuration
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig('<INCLUDE_TYPOSCRIPT: source="FILE:EXT:' . $_EXTKEY . '/Configuration/PageTS/Proc/pageTSConfig.txt">');

// Add default Page TS Config RTE configuration
if (strstr($_EXTCONF['defaultConfiguration'], 'Minimal')) {
	$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['defaultConfiguration'] = 'Advanced';
} elseif (strstr($_EXTCONF['defaultConfiguration'], 'Demo')) {
	$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['defaultConfiguration'] = 'Demo';
} else {
	$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['defaultConfiguration'] = 'Typical';
}

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig('<INCLUDE_TYPOSCRIPT: source="FILE:EXT:' . $_EXTKEY . '/Configuration/PageTS/' . $TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['defaultConfiguration'] . '/pageTSConfig.txt">');
// Add default User TS Config RTE configuration
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addUserTSConfig('<INCLUDE_TYPOSCRIPT: source="FILE:EXT:' . $_EXTKEY . '/Configuration/UserTS/' . $TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['defaultConfiguration'] . '/userTSConfig.txt">');

// Add processing of soft references on image tags in RTE content
require_once \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($_EXTKEY) . 'Configuration/Hook/SoftReferences/ext_localconf.php';
// Add Status Report about Conflicting Extensions
require_once \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($_EXTKEY) . 'Configuration/Hook/StatusReport/ext_localconf.php';

// Set warning in the Update Wizard of the Install Tool for deprecated Page TS Config properties
$TYPO3_CONF_VARS['SC_OPTIONS']['ext/install']['update']['checkForDeprecatedRtePageTSConfigProperties'] = \TYPO3\CMS\Rtehtmlarea\Hook\Install\DeprecatedRteProperties::class;
// Set warning in the Update Wizard of the Install Tool for replacement of "acronym" button by "abbreviation" button
$TYPO3_CONF_VARS['SC_OPTIONS']['ext/install']['update']['checkForRteAcronymButtonRenamedToAbbreviation'] = \TYPO3\CMS\Rtehtmlarea\Hook\Install\RteAcronymButtonRenamedToAbbreviation::class;

// Initialize plugin registration array
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins'] = array();

// Editor Mode configuration
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['EditorMode'] = array();
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['EditorMode']['objectReference'] = '&' . \TYPO3\CMS\Rtehtmlarea\Extension\EditorMode::class;

// General Element configuration
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['EditElement'] = array();
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['EditElement']['objectReference'] = '&' . \TYPO3\CMS\Rtehtmlarea\Extension\EditElement::class;
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['EditElement']['addIconsToSkin'] = 0;
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['EditElement']['disableInFE'] = 0;

$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['MicrodataSchema'] = array();
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['MicrodataSchema']['objectReference'] = '&' . \TYPO3\CMS\Rtehtmlarea\Extension\MicroDataSchema::class;
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['MicrodataSchema']['addIconsToSkin'] = 0;
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['MicrodataSchema']['disableInFE'] = 0;

// Inline Elements configuration
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['DefaultInline'] = array();
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['DefaultInline']['objectReference'] = '&' . \TYPO3\CMS\Rtehtmlarea\Extension\DefaultInline::class;
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['DefaultInline']['addIconsToSkin'] = 1;
if ($_EXTCONF['enableInlineElements']) {
	$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['InlineElements'] = array();
	$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['InlineElements']['objectReference'] = '&' . \TYPO3\CMS\Rtehtmlarea\Extension\InlineElements::class;
	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig('<INCLUDE_TYPOSCRIPT: source="FILE:EXT:' . $_EXTKEY . '/Configuration/PageTS/Extensions/InlineElements/pageTSConfig.txt">');
}

// Block Elements configuration
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['BlockElements'] = array();
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['BlockElements']['objectReference'] = '&' . \TYPO3\CMS\Rtehtmlarea\Extension\BlockElements::class;
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['BlockElements']['addIconsToSkin'] = 0;


$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['DefinitionList'] = array();
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['DefinitionList']['objectReference'] = '&' . \TYPO3\CMS\Rtehtmlarea\Extension\DefinitionList::class;
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['DefinitionList']['addIconsToSkin'] = 0;

$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['BlockStyle'] = array();
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['BlockStyle']['objectReference'] = '&' . \TYPO3\CMS\Rtehtmlarea\Extension\BlockStyle::class;

$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['CharacterMap'] = array();
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['CharacterMap']['objectReference'] = '&' . \TYPO3\CMS\Rtehtmlarea\Extension\CharacterMap::class;
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['CharacterMap']['addIconsToSkin'] = 0;

$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['Abbreviation'] = array();
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['Abbreviation']['objectReference'] = '&' . \TYPO3\CMS\Rtehtmlarea\Extension\Abbreviation::class;
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['Abbreviation']['addIconsToSkin'] = 0;
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['Abbreviation']['disableInFE'] = 1;

$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['UserElements'] = array();
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['UserElements']['objectReference'] = '&' . \TYPO3\CMS\Rtehtmlarea\Extension\UserElements::class;
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['UserElements']['addIconsToSkin'] = 0;
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['UserElements']['disableInFE'] = 1;

$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['TextStyle'] = array();
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['TextStyle']['objectReference'] = '&' . \TYPO3\CMS\Rtehtmlarea\Extension\TextStyle::class;

// Enable images and add default Page TS Config RTE configuration for enabling images with the Minimal and Typical default configuration
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['enableImages'] = $_EXTCONF['enableImages'] ?: 0;
if ($TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['defaultConfiguration'] == 'Demo') {
	$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['enableImages'] = 1;
}
if ($TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['enableImages']) {
	$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['DefaultImage'] = array();
	$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['DefaultImage']['objectReference'] = '&' . \TYPO3\CMS\Rtehtmlarea\Extension\DefaultImage::class;
	$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['DefaultImage']['addIconsToSkin'] = 0;

	$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['TYPO3Image'] = array();
	$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['TYPO3Image']['objectReference'] = '&' . \TYPO3\CMS\Rtehtmlarea\Extension\Typo3Image::class;
	$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['TYPO3Image']['addIconsToSkin'] = 0;
	$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['TYPO3Image']['disableInFE'] = 1;

	if ($TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['defaultConfiguration'] == 'Advanced' || $TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['defaultConfiguration'] == 'Typical') {
		\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig('<INCLUDE_TYPOSCRIPT: source="FILE:EXT:' . $_EXTKEY . '/Configuration/PageTS/Image/pageTSConfig.txt">');
	}
}
// Add frontend image rendering TypoScript anyways
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScript($_EXTKEY, 'setup', '<INCLUDE_TYPOSCRIPT: source="FILE:EXT:' . $_EXTKEY . '/Configuration/TypoScript/ImageRendering/setup.txt">', 43);

$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['DefaultLink'] = array();
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['DefaultLink']['objectReference'] = '&' . \TYPO3\CMS\Rtehtmlarea\Extension\DefaultLink::class;
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['DefaultLink']['addIconsToSkin'] = 0;

$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['TYPO3Link'] = array();
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['TYPO3Link']['objectReference'] = '&' . \TYPO3\CMS\Rtehtmlarea\Extension\Typo3Link::class;
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['TYPO3Link']['addIconsToSkin'] = 0;
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['TYPO3Link']['disableInFE'] = 1;
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['TYPO3Link']['additionalAttributes'] = 'rel';

// Add default Page TS Config RTE configuration for enabling links accessibility icons
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['enableAccessibilityIcons'] = $_EXTCONF['enableAccessibilityIcons'] ?: 0;
if ($TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['enableAccessibilityIcons']) {
	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig('<INCLUDE_TYPOSCRIPT: source="FILE:EXT:' . $_EXTKEY . '/Configuration/PageTS/AccessibilityIcons/pageTSConfig.txt">');
	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScript($_EXTKEY, 'setup', '<INCLUDE_TYPOSCRIPT: source="FILE:EXT:' . $_EXTKEY . '/res/accessibilityicons/setup.txt">', 43);
}

// Register features that use the style attribute
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['allowStyleAttribute'] = isset($_EXTCONF['allowStyleAttribute']) && !$_EXTCONF['allowStyleAttribute'] ? 0 : 1;
if ($TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['allowStyleAttribute']) {
	$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['TYPO3Color'] = array();
	$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['TYPO3Color']['objectReference'] = '&' . \TYPO3\CMS\Rtehtmlarea\Extension\Typo3Color::class;
	$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['TYPO3Color']['addIconsToSkin'] = 0;
	$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['TYPO3Color']['disableInFE'] = 0;

	$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['SelectFont'] = array();
	$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['SelectFont']['objectReference'] = '&' . \TYPO3\CMS\Rtehtmlarea\Extension\SelectFont::class;
	$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['SelectFont']['addIconsToSkin'] = 0;
	$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['SelectFont']['disableInFE'] = 0;
	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig('<INCLUDE_TYPOSCRIPT: source="FILE:EXT:' . $_EXTKEY . '/Configuration/PageTS/Style/pageTSConfig.txt">');
}

$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['TextIndicator'] = array();
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['TextIndicator']['objectReference'] = '&' . \TYPO3\CMS\Rtehtmlarea\Extension\TextIndicator::class;
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['TextIndicator']['addIconsToSkin'] = 0;
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['TextIndicator']['disableInFE'] = 0;

$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['InsertSmiley'] = array();
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['InsertSmiley']['objectReference'] = '&' . \TYPO3\CMS\Rtehtmlarea\Extension\InsertSmiley::class;
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['InsertSmiley']['addIconsToSkin'] = 0;
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['InsertSmiley']['disableInFE'] = 0;

$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['Language'] = array();
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['Language']['objectReference'] = '&' . \TYPO3\CMS\Rtehtmlarea\Extension\Language::class;
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['Language']['addIconsToSkin'] = 0;
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['Language']['disableInFE'] = 0;

// Spell checking configuration
$TYPO3_CONF_VARS['FE']['eID_include']['rtehtmlarea_spellchecker'] = 'EXT:' . $_EXTKEY . '/Resources/Private/PHP/SpellCheckerEid.php';
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::registerAjaxHandler('rtehtmlarea::spellchecker', \TYPO3\CMS\Rtehtmlarea\Controller\SpellCheckingController::class . '->main');

$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['SpellChecker'] = array();
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['SpellChecker']['objectReference'] = '&' . \TYPO3\CMS\Rtehtmlarea\Extension\Spellchecker::class;
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['SpellChecker']['addIconsToSkin'] = 0;
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['SpellChecker']['disableInFE'] = 0;
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['SpellChecker']['AspellDirectory'] = $_EXTCONF['AspellDirectory'] ? $_EXTCONF['AspellDirectory'] : '/usr/bin/aspell';
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['SpellChecker']['noSpellCheckLanguages'] = $_EXTCONF['noSpellCheckLanguages'] ? $_EXTCONF['noSpellCheckLanguages'] : 'ja,km,ko,lo,th,zh,b5,gb';
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['SpellChecker']['forceCommandMode'] = $_EXTCONF['forceCommandMode'] ? $_EXTCONF['forceCommandMode'] : 0;

$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['FindReplace'] = array();
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['FindReplace']['objectReference'] = '&' . \TYPO3\CMS\Rtehtmlarea\Extension\FindReplace::class;
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['FindReplace']['addIconsToSkin'] = 0;
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['FindReplace']['disableInFE'] = 0;

$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['RemoveFormat'] = array();
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['RemoveFormat']['objectReference'] = '&' . \TYPO3\CMS\Rtehtmlarea\Extension\RemoveFormat::class;
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['RemoveFormat']['addIconsToSkin'] = 0;
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['RemoveFormat']['disableInFE'] = 0;

$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['PlainText'] = array();
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['PlainText']['objectReference'] = '&' . \TYPO3\CMS\Rtehtmlarea\Extension\Plaintext::class;
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['PlainText']['addIconsToSkin'] = 0;
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['PlainText']['disableInFE'] = 0;

$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['DefaultClean'] = array();
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['DefaultClean']['objectReference'] = '&' . \TYPO3\CMS\Rtehtmlarea\Extension\DefaultClean::class;

$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['TYPO3HtmlParser'] = array();
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['TYPO3HtmlParser']['objectReference'] = '&' . \TYPO3\CMS\Rtehtmlarea\Extension\Typo3HtmlParser::class;
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['TYPO3HtmlParser']['disableInFE'] = 1;

$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['QuickTag'] = array();
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['QuickTag']['objectReference'] = '&' . \TYPO3\CMS\Rtehtmlarea\Extension\QuickTag::class;
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['QuickTag']['addIconsToSkin'] = 0;
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['QuickTag']['disableInFE'] = 0;

$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['TableOperations'] = array();
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['TableOperations']['objectReference'] = '&' . \TYPO3\CMS\Rtehtmlarea\Extension\TableOperations::class;
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['TableOperations']['addIconsToSkin'] = 0;
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['TableOperations']['disableInFE'] = 0;

$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['AboutEditor'] = array();
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['AboutEditor']['objectReference'] = '&' . \TYPO3\CMS\Rtehtmlarea\Extension\AboutEditor::class;
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['AboutEditor']['addIconsToSkin'] = 0;
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['AboutEditor']['disableInFE'] = 0;

$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['ContextMenu'] = array();
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['ContextMenu']['objectReference'] = '&' . \TYPO3\CMS\Rtehtmlarea\Extension\ContextMenu::class;
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['ContextMenu']['addIconsToSkin'] = 0;
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['ContextMenu']['disableInFE'] = 0;

$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['UndoRedo'] = array();
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['UndoRedo']['objectReference'] = '&' . \TYPO3\CMS\Rtehtmlarea\Extension\UndoRedo::class;
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['UndoRedo']['addIconsToSkin'] = 0;
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['UndoRedo']['disableInFE'] = 0;

// Copy & Paste configuration
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['CopyPaste'] = array();
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['CopyPaste']['objectReference'] = '&' . \TYPO3\CMS\Rtehtmlarea\Extension\CopyPaste::class;
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['CopyPaste']['addIconsToSkin'] = 0;
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['plugins']['CopyPaste']['disableInFE'] = 0;
