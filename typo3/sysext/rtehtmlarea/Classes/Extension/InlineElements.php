<?php
namespace TYPO3\CMS\Rtehtmlarea\Extension;

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
 * InlineElements plugin for htmlArea RTE
 *
 * @author Stanislas Rolland <typo3(arobas)sjbr.ca>
 */
class InlineElements extends \TYPO3\CMS\Rtehtmlarea\RteHtmlAreaApi {

	/**
	 * The key of the extension that is extending htmlArea RTE
	 *
	 * @var string
	 */
	protected $extensionKey = 'rtehtmlarea';

	/**
	 * The name of the plugin registered by the extension
	 *
	 * @var string
	 */
	protected $pluginName = 'InlineElements';

	/**
	 * Path to this main locallang file of the extension relative to the extension directory
	 *
	 * @var string
	 */
	protected $relativePathToLocallangFile = 'extensions/InlineElements/locallang.xlf';

	/**
	 * Path to the skin file relative to the extension directory
	 *
	 * @var string
	 */
	protected $relativePathToSkin = 'Resources/Public/Css/Skin/Plugins/inline-elements.css';

	/**
	 * Reference to the invoking object
	 *
	 * @var \TYPO3\CMS\Rtehtmlarea\RteHtmlAreaBase
	 */
	protected $htmlAreaRTE;

	protected $thisConfig;

	// Reference to RTE PageTSConfig
	protected $toolbar;

	// Reference to RTE toolbar array
	protected $LOCAL_LANG;

	// Frontend language array
	protected $pluginButtons = 'formattext, bidioverride, big, bold, citation, code, definition, deletedtext, emphasis, insertedtext, italic, keyboard, quotation, sample, small, span, strikethrough, strong, subscript, superscript, underline, variable';

	protected $convertToolbarForHtmlAreaArray = array(
		'formattext' => 'FormatText',
		'bidioverride' => 'BiDiOverride',
		'big' => 'Big',
		'bold' => 'Bold',
		'citation' => 'Citation',
		'code' => 'Code',
		'definition' => 'Definition',
		'deletedtext' => 'DeletedText',
		'emphasis' => 'Emphasis',
		'insertedtext' => 'InsertedText',
		'italic' => 'Italic',
		'keyboard' => 'Keyboard',
		'monospaced' => 'MonoSpaced',
		'quotation' => 'Quotation',
		'sample' => 'Sample',
		'small' => 'Small',
		'span' => 'Span',
		'strikethrough' => 'StrikeThrough',
		'strong' => 'Strong',
		'subscript' => 'Subscript',
		'superscript' => 'Superscript',
		'underline' => 'Underline',
		'variable' => 'Variable'
	);

	protected $defaultInlineElements = array(
		'none' => 'No markup',
		'b' => 'Bold',
		'bdo' => 'BiDi override',
		'big' => 'Large text',
		'cite' => 'Citation',
		'code' => 'Code',
		'del' => 'Deleted text',
		'dfn' => 'Definition',
		'em' => 'Emphasis',
		'i' => 'Italic',
		'ins' => 'Inserted text',
		'kbd' => 'Keyboard',
		'q' => 'Quotation',
		'samp' => 'Sample',
		'small' => 'Small text',
		'span' => 'Style container',
		'strike' => 'Strike-through',
		'strong' => 'Strong emphasis',
		'sub' => 'Subscript',
		'sup' => 'Superscript',
		'tt' => 'Monospaced text',
		'u' => 'Underline',
		'var' => 'Variable'
	);

	protected $defaultInlineElementsOrder = 'none, bidioverride, big, bold, citation, code, definition, deletedtext, emphasis, insertedtext, italic, keyboard,
						monospaced, quotation, sample, small, span, strikethrough, strong, subscript, superscript, underline, variable';

	protected $buttonToInlineElement = array(
		'none' => 'none',
		'bidioverride' => 'bdo',
		'big' => 'big',
		'bold' => 'b',
		'citation' => 'cite',
		'code' => 'code',
		'definition' => 'dfn',
		'deletedtext' => 'del',
		'emphasis' => 'em',
		'insertedtext' => 'ins',
		'italic' => 'i',
		'keyboard' => 'kbd',
		'monospaced' => 'tt',
		'quotation' => 'q',
		'sample' => 'samp',
		'small' => 'small',
		'span' => 'span',
		'strikethrough' => 'strike',
		'strong' => 'strong',
		'subscript' => 'sub',
		'superscript' => 'sup',
		'underline' => 'u',
		'variable' => 'var'
	);

	/**
	 * Return JS configuration of the htmlArea plugins registered by the extension
	 *
	 * @param int Relative id of the RTE editing area in the form
	 * @return string JS configuration for registered plugins
	 */
	public function buildJavascriptConfiguration($RTEcounter) {
		$registerRTEinJavascriptString = '';
		if (in_array('formattext', $this->toolbar)) {
			if (!is_array($this->thisConfig['buttons.']) || !is_array($this->thisConfig['buttons.']['formattext.'])) {
				$registerRTEinJavascriptString .= '
			RTEarea[' . $RTEcounter . '].buttons.formattext = new Object();';
			}
			// Default inline elements
			$hideItems = array();
			$restrictTo = array('*');
			$inlineElementsOrder = $this->defaultInlineElementsOrder;
			$prefixLabelWithTag = FALSE;
			$postfixLabelWithTag = FALSE;
			// Processing PageTSConfig
			if (is_array($this->thisConfig['buttons.']) && is_array($this->thisConfig['buttons.']['formattext.'])) {
				// Removing elements
				if ($this->thisConfig['buttons.']['formattext.']['removeItems']) {
					$hideItems = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', $this->htmlAreaRTE->cleanList($this->thisConfig['buttons.']['formattext.']['removeItems']), TRUE);
				}
				// Restriction clause
				if ($this->thisConfig['buttons.']['formattext.']['restrictTo']) {
					$restrictTo = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', $this->htmlAreaRTE->cleanList('none,' . $this->thisConfig['buttons.']['formattext.']['restrictTo']), TRUE);
				} elseif ($this->thisConfig['buttons.']['formattext.']['restrictToItems']) {
					$restrictTo = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', $this->htmlAreaRTE->cleanList('none,' . $this->thisConfig['buttons.']['formattext.']['restrictToItems']), TRUE);
				}
				// Elements order
				if ($this->thisConfig['buttons.']['formattext.']['orderItems']) {
					$inlineElementsOrder = 'none,' . $this->thisConfig['buttons.']['formattext.']['orderItems'];
				}
				$prefixLabelWithTag = $this->thisConfig['buttons.']['formattext.']['prefixLabelWithTag'] ? TRUE : $prefixLabelWithTag;
				$postfixLabelWithTag = $this->thisConfig['buttons.']['formattext.']['postfixLabelWithTag'] ? TRUE : $postfixLabelWithTag;
			}
			$inlineElementsOrder = array_diff(\TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', $this->htmlAreaRTE->cleanList($inlineElementsOrder), TRUE), $hideItems);
			if (!in_array('*', $restrictTo)) {
				$inlineElementsOrder = array_intersect($inlineElementsOrder, $restrictTo);
			}
			// Localizing the options
			$inlineElementsOptions = array();
			foreach ($inlineElementsOrder as $item) {
				if ($this->htmlAreaRTE->is_FE()) {
					$inlineElementsOptions[$this->buttonToInlineElement[$item]] = $GLOBALS['TSFE']->getLLL($this->defaultInlineElements[$this->buttonToInlineElement[$item]], $this->LOCAL_LANG);
				} else {
					$inlineElementsOptions[$this->buttonToInlineElement[$item]] = $GLOBALS['LANG']->getLL($this->defaultInlineElements[$this->buttonToInlineElement[$item]]);
				}
				$inlineElementsOptions[$this->buttonToInlineElement[$item]] = ($prefixLabelWithTag && $item != 'none' ? $this->buttonToInlineElement[$item] . ' - ' : '') . $inlineElementsOptions[$this->buttonToInlineElement[$item]] . ($postfixLabelWithTag && $item != 'none' ? ' - ' . $this->buttonToInlineElement[$item] : '');
			}
			$first = array_shift($inlineElementsOptions);
			// Sorting the options
			if (!is_array($this->thisConfig['buttons.']) || !is_array($this->thisConfig['buttons.']['formattext.']) || !$this->thisConfig['buttons.']['formattext.']['orderItems']) {
				asort($inlineElementsOptions);
			}
			// Generating the javascript options
			$JSInlineElements = array();
			$JSInlineElements[] = array($first, 'none');
			foreach ($inlineElementsOptions as $item => $label) {
				$JSInlineElements[] = array($label, $item);
			}
			if ($this->htmlAreaRTE->is_FE()) {
				$GLOBALS['TSFE']->csConvObj->convArray($JSInlineElements, $this->htmlAreaRTE->OutputCharset, 'utf-8');
			}
			$registerRTEinJavascriptString .= '
			RTEarea[' . $RTEcounter . '].buttons.formattext.options = ' . json_encode($JSInlineElements) . ';';
		}
		return $registerRTEinJavascriptString;
	}

}
