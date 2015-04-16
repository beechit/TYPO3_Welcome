<?php
defined('TYPO3_MODE') or die();

if (TYPO3_MODE === 'FE') {

	// Register legacy content objects
	$GLOBALS['TYPO3_CONF_VARS']['FE']['ContentObjects']['IMGTEXT']      = \TYPO3\CMS\Compatibility6\ContentObject\ImageTextContentObject::class;
	$GLOBALS['TYPO3_CONF_VARS']['FE']['ContentObjects']['CLEARGIF']     = \TYPO3\CMS\Compatibility6\ContentObject\ClearGifContentObject::class;
	$GLOBALS['TYPO3_CONF_VARS']['FE']['ContentObjects']['CTABLE']       = \TYPO3\CMS\Compatibility6\ContentObject\ContentTableContentObject::class;
	$GLOBALS['TYPO3_CONF_VARS']['FE']['ContentObjects']['OTABLE']       = \TYPO3\CMS\Compatibility6\ContentObject\OffsetTableContentObject::class;
	$GLOBALS['TYPO3_CONF_VARS']['FE']['ContentObjects']['COLUMNS']      = \TYPO3\CMS\Compatibility6\ContentObject\ColumnsContentObject::class;
	$GLOBALS['TYPO3_CONF_VARS']['FE']['ContentObjects']['HRULER']       = \TYPO3\CMS\Compatibility6\ContentObject\HorizontalRulerContentObject::class;
	$GLOBALS['TYPO3_CONF_VARS']['FE']['ContentObjects']['FORM']         = \TYPO3\CMS\Compatibility6\ContentObject\FormContentObject::class;
	$GLOBALS['TYPO3_CONF_VARS']['FE']['ContentObjects']['SEARCHRESULT'] = \TYPO3\CMS\Compatibility6\ContentObject\SearchResultContentObject::class;

	// Register a hook for data submission
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['checkDataSubmission']['mailform'] = \TYPO3\CMS\Compatibility6\Controller\FormDataSubmissionController::class;

	// Register hooks for xhtml_cleaning
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['contentPostProc-all'][] = \TYPO3\CMS\Compatibility6\Hooks\TypoScriptFrontendController\ContentPostProcHook::class . '->contentPostProcAll';
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['contentPostProc-cached'][] = \TYPO3\CMS\Compatibility6\Hooks\TypoScriptFrontendController\ContentPostProcHook::class . '->contentPostProcCached';
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['contentPostProc-output'][] = \TYPO3\CMS\Compatibility6\Hooks\TypoScriptFrontendController\ContentPostProcHook::class . '->contentPostProcOutput';
}

/**
 * CType "mailform"
 */
// Add Default TypoScript for CType "mailform" after default content rendering
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScript('compatibility6', 'constants', '<INCLUDE_TYPOSCRIPT: source="FILE:EXT:compatibility6/Configuration/TypoScript/Form/constants.txt">', 'defaultContentRendering');
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScript('compatibility6', 'setup', '<INCLUDE_TYPOSCRIPT: source="FILE:EXT:compatibility6/Configuration/TypoScript/Form/setup.txt">', 'defaultContentRendering');

// Add the search CType to the "New Content Element" wizard
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig('
mod.wizards.newContentElement.wizardItems.forms {
	elements.mailform {
		icon = gfx/c_wiz/mailform.gif
		title = LLL:EXT:cms/layout/locallang_db_new_content_el.xlf:forms_mail_title
		description = LLL:EXT:cms/layout/locallang_db_new_content_el.xlf:forms_mail_description
		tt_content_defValues {
			CType = mailform
			bodytext (
		# Example content:
		Name: | *name = input,40 | Enter your name here
		Email: | *email=input,40 |
		Address: | address=textarea,40,5 |
		Contact me: | tv=check | 1

		|formtype_mail = submit | Send form!
		|html_enabled=hidden | 1
		|subject=hidden| This is the subject
			)
		}
	}
	show :=addToList(mailform)
}
');

// Add a for previewing tt_content elements of CType="mailform"
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['cms/layout/class.tx_cms_layout.php']['tt_content_drawItem']['mailform'] = \TYPO3\CMS\Compatibility6\Hooks\PageLayoutView\MailformPreviewRenderer::class;


/**
 * CType "search"
 */

// Add Default TypoScript for CType "search" after default content rendering
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScript('compatibility6', 'constants', '<INCLUDE_TYPOSCRIPT: source="FILE:EXT:compatibility6/Configuration/TypoScript/Search/constants.txt">', 'defaultContentRendering');
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScript('compatibility6', 'setup', '<INCLUDE_TYPOSCRIPT: source="FILE:EXT:compatibility6/Configuration/TypoScript/Search/setup.txt">', 'defaultContentRendering');

// Add the search CType to the "New Content Element" wizard
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig('
mod.wizards.newContentElement.wizardItems.forms {
	elements.search {
		icon = gfx/c_wiz/searchform.gif
		title = LLL:EXT:cms/layout/locallang_db_new_content_el.xlf:forms_search_title
		description = LLL:EXT:cms/layout/locallang_db_new_content_el.xlf:forms_search_description
		tt_content_defValues.CType = search
	}
	show :=addToList(search)
}
');
