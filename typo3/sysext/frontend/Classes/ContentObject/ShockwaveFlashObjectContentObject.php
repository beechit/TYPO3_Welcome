<?php
namespace TYPO3\CMS\Frontend\ContentObject;

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

use TYPO3\CMS\Core\Utility\ArrayUtility;

/**
 * Contains SWFOBJECT class object.
 *
 * @author Xavier Perseguers <typo3@perseguers.ch>
 * @author Steffen Kamper <steffen@typo3.org>
 */
class ShockwaveFlashObjectContentObject extends AbstractContentObject {

	/**
	 * Rendering the cObject, SWFOBJECT
	 *
	 * @param array $conf Array of TypoScript properties
	 * @return string Output
	 */
	public function render($conf = array()) {
		$prefix = '';
		if ($GLOBALS['TSFE']->baseUrl) {
			$prefix = $GLOBALS['TSFE']->baseUrl;
		}
		if ($GLOBALS['TSFE']->absRefPrefix) {
			$prefix = $GLOBALS['TSFE']->absRefPrefix;
		}
		$type = isset($conf['type.']) ? $this->cObj->stdWrap($conf['type'], $conf['type.']) : $conf['type'];
		$typeConf = $conf[$type . '.'];
		/** @var $pageRenderer \TYPO3\CMS\Core\Page\PageRenderer */
		$pageRenderer = $GLOBALS['TSFE']->getPageRenderer();

		// Add SWFobject js-file
		$pageRenderer->addJsFile(TYPO3_mainDir . 'contrib/flashmedia/swfobject/swfobject.js');
		$player = isset($typeConf['player.']) ? $this->cObj->stdWrap($typeConf['player'], $typeConf['player.']) : $typeConf['player'];
		$installUrl = isset($conf['installUrl.']) ? $this->cObj->stdWrap($conf['installUrl'], $conf['installUrl.']) : $conf['installUrl'];
		if (!$installUrl) {
			$installUrl = $prefix . TYPO3_mainDir . 'contrib/flashmedia/swfobject/expressInstall.swf';
		}
		// If file is audio and an explicit path has not been set,
		// take path from audio fallback property
		if ($type == 'audio' && empty($conf['file'])) {
			$conf['file'] = $conf['audioFallback'];
		}
		$filename = isset($conf['file.']) ? $this->cObj->stdWrap($conf['file'], $conf['file.']) : $conf['file'];
		$forcePlayer = isset($conf['forcePlayer.']) ? $this->cObj->stdWrap($conf['forcePlayer'], $conf['forcePlayer.']) : $conf['forcePlayer'];
		if ($filename && $forcePlayer) {
			if (strpos($filename, '://') !== FALSE) {
				$conf['flashvars.']['file'] = $filename;
			} else {
				if ($prefix) {
					$conf['flashvars.']['file'] = $prefix . $filename;
				} else {
					$conf['flashvars.']['file'] = str_repeat('../', substr_count($player, '/')) . $filename;
				}
			}
		} else {
			$player = $filename;
		}
		// Write calculated values in conf for the hook
		$conf['player'] = $player;
		$conf['installUrl'] = $installUrl;
		$conf['filename'] = $filename;
		$conf['prefix'] = $prefix;
		// Merge with default parameters
		$conf['flashvars.'] = array_merge((array)$typeConf['default.']['flashvars.'], (array)$conf['flashvars.']);
		$conf['params.'] = array_merge((array)$typeConf['default.']['params.'], (array)$conf['params.']);
		$conf['attributes.'] = array_merge((array)$typeConf['default.']['attributes.'], (array)$conf['attributes.']);
		$conf['embedParams'] = 'flashvars, params, attributes';
		// Hook for manipulating the conf array, it's needed for some players like flowplayer
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/hooks/class.tx_cms_mediaitems.php']['swfParamTransform'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/hooks/class.tx_cms_mediaitems.php']['swfParamTransform'] as $classRef) {
				\TYPO3\CMS\Core\Utility\GeneralUtility::callUserFunction($classRef, $conf, $this);
			}
		}
		if (is_array($conf['flashvars.'])) {
			ArrayUtility::remapArrayKeys($conf['flashvars.'], $typeConf['mapping.']['flashvars.']);
		}
		$flashvars = 'var flashvars = ' . (count($conf['flashvars.']) ? json_encode($conf['flashvars.']) : '{}') . ';';
		if (is_array($conf['params.'])) {
			ArrayUtility::remapArrayKeys($conf['params.'], $typeConf['mapping.']['params.']);
		}
		$params = 'var params = ' . (count($conf['params.']) ? json_encode($conf['params.']) : '{}') . ';';
		if (is_array($conf['attributes.'])) {
			ArrayUtility::remapArrayKeys($conf['attributes.'], $typeConf['attributes.']['params.']);
		}
		$attributes = 'var attributes = ' . (count($conf['attributes.']) ? json_encode($conf['attributes.']) : '{}') . ';';
		$flashVersion = isset($conf['flashVersion.']) ? $this->cObj->stdWrap($conf['flashVersion'], $conf['flashVersion.']) : $conf['flashVersion'];
		if (!$flashVersion) {
			$flashVersion = '9';
		}
		$replaceElementIdString = str_replace('.', '', uniqid('mmswf', TRUE));
		$GLOBALS['TSFE']->register['MMSWFID'] = $replaceElementIdString;
		$alternativeContent = isset($conf['alternativeContent.']) ? $this->cObj->stdWrap($conf['alternativeContent'], $conf['alternativeContent.']) : $conf['alternativeContent'];
		$layout = isset($conf['layout.']) ? $this->cObj->stdWrap($conf['layout'], $conf['layout.']) : $conf['layout'];
		$content = str_replace('###ID###', $replaceElementIdString, $layout);
		$content = str_replace('###SWFOBJECT###', '<div id="' . $replaceElementIdString . '">' . $alternativeContent . '</div>', $content);
		$width = isset($conf['width.']) ? $this->cObj->stdWrap($conf['width'], $conf['width.']) : $conf['width'];
		if (!$width) {
			$width = $conf[$type . '.']['defaultWidth'];
		}
		$height = isset($conf['height.']) ? $this->cObj->stdWrap($conf['height'], $conf['height.']) : $conf['height'];
		if (!$height) {
			$height = $conf[$type . '.']['defaultHeight'];
		}
		$embed = 'swfobject.embedSWF("' . $conf['player'] . '", "' . $replaceElementIdString . '", "' . $width . '", "' . $height . '",
				"' . $flashVersion . '", "' . $installUrl . '", ' . $conf['embedParams'] . ');';
		$script = $flashvars . $params . $attributes . $embed;
		$pageRenderer->addJsInlineCode($replaceElementIdString, $script);
		if (isset($conf['stdWrap.'])) {
			$content = $this->cObj->stdWrap($content, $conf['stdWrap.']);
		}
		return $content;
	}

}
