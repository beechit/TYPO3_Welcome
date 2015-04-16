<?php
namespace TYPO3\CMS\Backend\Tree\View;

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
 * Base extension class which generates the folder tree.
 * Used directly by the RTE.
 * also used for the linkpicker on files
 *
 * Browsable folder tree, used in Element Browser and RTE (for which it will be extended)
 * previously located inside typo3/class.browse_links.php
 */
class ElementBrowserFolderTreeView extends FolderTreeView {

	/**
	 * @var int
	 */
	public $ext_IconMode = 1;

	/**
	 * Initializes the script path
	 */
	public function __construct() {
		$this->determineScriptUrl();
		parent::__construct();
	}

	/**
	 * Wrapping the title in a link, if applicable.
	 *
	 * @param string $title Title, ready for output.
	 * @param \TYPO3\CMS\Core\Resource\Folder $folderObject The "record
	 * @return string Wrapping title string.
	 */
	public function wrapTitle($title, \TYPO3\CMS\Core\Resource\Folder $folderObject) {
		if ($this->ext_isLinkable($folderObject)) {
			$aOnClick = 'return jumpToUrl(' . \TYPO3\CMS\Core\Utility\GeneralUtility::quoteJSvalue($this->getThisScript() . 'act=' . $GLOBALS['SOBE']->browser->act . '&mode=' . $GLOBALS['SOBE']->browser->mode . '&expandFolder=' . rawurlencode($folderObject->getCombinedIdentifier())) . ');';
			return '<a href="#" onclick="' . htmlspecialchars($aOnClick) . '">' . $title . '</a>';
		} else {
			return '<span class="typo3-dimmed">' . $title . '</span>';
		}
	}

	/**
	 * Returns TRUE if the input "record" contains a folder which can be linked.
	 *
	 * @param \TYPO3\CMS\Core\Resource\Folder $folderObject Object with information about the folder element. Contains keys like title, uid, path, _title
	 * @return bool TRUE is returned if the path is found in the web-part of the server and is NOT a recycler or temp folder
	 */
	public function ext_isLinkable(\TYPO3\CMS\Core\Resource\Folder $folderObject) {
		if (strstr($folderObject->getIdentifier(), '_recycler_') || strstr($folderObject->getIdentifier(), '_temp_')) {
			return FALSE;
		} else {
			return TRUE;
		}
	}

	/**
	 * Wrap the plus/minus icon in a link
	 *
	 * @param string $icon HTML string to wrap, probably an image tag.
	 * @param string $cmd Command for 'PM' get var
	 * @param bool $bMark If set, the link will have a anchor point (=$bMark) and a name attribute (=$bMark)
	 * @return string Link-wrapped input string
	 * @access private
	 */
	public function PM_ATagWrap($icon, $cmd, $bMark = '') {
		$name = $anchor = '';
		if ($bMark) {
			$anchor = '#' . $bMark;
			$name = ' name="' . $bMark . '"';
		}
		$aOnClick = 'return jumpToUrl(' . \TYPO3\CMS\Core\Utility\GeneralUtility::quoteJSvalue($this->getThisScript() . 'PM=' . $cmd) . ',' . \TYPO3\CMS\Core\Utility\GeneralUtility::quoteJSvalue($anchor) . ');';
		return '<a href="#"' . htmlspecialchars($name) . ' onclick="' . htmlspecialchars($aOnClick) . '">' . $icon . '</a>';
	}

}
