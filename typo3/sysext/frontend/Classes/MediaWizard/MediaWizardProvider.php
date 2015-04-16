<?php
namespace TYPO3\CMS\Frontend\MediaWizard;

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
 * Contains an implementation of the mediaWizardProvider supporting some
 * well known providers.
 *
 * @author Aishwara M.B.<aishu.moorthy@gmail.com>
 * @author Steffen Kamper <info@sk-typo3.de>
 * @author Ernesto Baschny <ernst@cron-it.de>
 */
class MediaWizardProvider implements MediaWizardProviderInterface {

	/**
	 * @var array List of providers we can handle in this class
	 */
	protected $providers = array(
		'youtube',
		'youtu',
		'dailymotion',
		'sevenload',
		'vimeo',
		'clipfish',
		'google',
		'metacafe',
		'myvideo',
		'liveleak',
		'veoh'
	);

	/**
	 * Checks if we have a valid method for processing a given URL.
	 *
	 * This is done by analysing the hostname of the URL and checking if it contains
	 * any of our provider tags defined in $this->providers.
	 *
	 * @param string $url
	 * @return string
	 */
	protected function getMethod($url) {
		$urlInfo = @parse_url($url);
		if ($urlInfo === FALSE) {
			return NULL;
		}
		// The URL passed might not contain http:// prefix
		if (!isset($urlInfo['host'])) {
			$urlInfo = @parse_url('http://' . $url);
		}
		if (empty($urlInfo['host'])) {
			return NULL;
		}
		$hostName = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode('.', $urlInfo['host'], TRUE);
		foreach ($this->providers as $provider) {
			$functionName = 'process_' . $provider;
			if (in_array($provider, $hostName) && is_callable(array($this, $functionName))) {
				return $functionName;
			}
		}
		return NULL;
	}

	/***********************************************
	 *
	 * Implementation of MediaWizardProviderInterface
	 *
	 ***********************************************/
	/**
	 * @param string $url
	 * @return bool
	 * @see MediaWizardProviderInterface::canHandle
	 */
	public function canHandle($url) {
		return $this->getMethod($url) !== NULL;
	}

	/**
	 * @param string $url URL to rewrite
	 * @return string The rewritten URL
	 * @see MediaWizardProviderInterface::rewriteUrl
	 */
	public function rewriteUrl($url) {
		$method = $this->getMethod($url);
		return $this->{$method}($url);
	}

	/***********************************************
	 *
	 * Providers URL rewriting:
	 *
	 ***********************************************/
	/**
	 * Parse youtube url
	 *
	 * @param string $url
	 * @return string processed url
	 */
	protected function process_youtube($url) {
		$videoId = '';

		$pattern = '%
		^(?:https?://)?									# Optional URL scheme Either http or https
		(?:www\.)?										# Optional www subdomain
		(?:												# Group host alternatives:
			youtu\.be/									#  Either youtu.be/,
			|youtube(?:									#  or youtube.com/
				-nocookie								#   optional nocookie domain
			)?\.com/(?:
				[^/]+/.+/								#   Either /something/other_params/ for channels,
				|(?:v|e(?:								#   or v/ or e/,
					mbed								#    optional mbed for embed/
				)?)/
				|.*[?&]v=								#   or ?v= or ?other_param&v=
			)
		)												# End host alternatives.
		([^"&?/ ]{11})									# 11 characters (Length of Youtube video ids).
		(?:.+)?$										# Optional other ending URL parameters.
		%xs';
		if (preg_match($pattern, $url, $matches)) {
			$videoId = $matches[1];
		}

		if ($videoId) {
			$url = $this->getUrlSchema() . 'www.youtube.com/v/' . $videoId . '?fs=1';
		}
		return $url;
	}

	/**
	 * Parse youtube short url
	 *
	 * @param string $url
	 * @return string processed url
	 */
	protected function process_youtu($url) {
		return $this->process_youtube($url);
	}

	/**
	 * Parse dailymotion url
	 *
	 * @param string $url
	 * @return string processed url
	 */
	protected function process_dailymotion($url) {
		$parts = explode('video/', $url);
		$videoId = $parts[1];
		if (strpos($videoId, '/') !== FALSE) {
			$videoId = substr($videoId, 0, strpos($videoId, '/'));
		}
		return $this->getUrlSchema() . 'www.dailymotion.com/swf/' . $videoId;
	}

	/**
	 * Parse sevenload url
	 *
	 * @param string $url
	 * @return string processed url and preview image
	 */
	protected function process_sevenload($url) {
		$parts = explode('/', $url);
		$videoId = $parts[count($parts) - 1];
		if (strpos($videoId, '-') !== FALSE) {
			$videoId = substr($videoId, 0, strpos($videoId, '-'));
		}
		return 'http://de.sevenload.com/pl/' . $videoId . '/400x500/swf';
	}

	/**
	 * Parse vimeo url
	 *
	 * Supports:
	 * - http://vimeo.com/hd#<id>
	 * - http://vimeo.com/<id>
	 * - http://player.vimeo.com/video/<id>
	 *
	 * @param string $url
	 * @return string processed url
	 */
	protected function process_vimeo($url) {
		if (preg_match('/[\\/#](\\d+)$/', $url, $matches)) {
			$videoId = $matches[1];
			$url = $this->getUrlSchema() . 'vimeo.com/moogaloop.swf?clip_id=' . $videoId . '&server=vimeo.com&show_title=1&show_byline=1&show_portrait=0&fullscreen=1';
		}
		return $url;
	}

	/**
	 * Parse clipfish url
	 *
	 * @param string $url
	 * @return string processed url
	 */
	protected function process_clipfish($url) {
		if (preg_match('/video([^(\\&|$)]*)/', $url, $matches)) {
			$parts = explode('/', $matches[1]);
			$videoId = $parts[1];
			$url = 'http://www.clipfish.de/cfng/flash/clipfish_player_3.swf?as=0&r=1&noad=1&fs=1&vid=' . $videoId;
		}
		return $url;
	}

	/**
	 * Parse google url
	 *
	 * @param string $url
	 * @return string processed url
	 */
	protected function process_google($url) {
		if (preg_match('/docid=([^(\\&|$)]*)/', $url, $matches)) {
			$videoId = $matches[1];
			$url = $this->getUrlSchema() . 'video.google.com/googleplayer.swf?docid=' . $videoId;
		}
		return $url;
	}

	/**
	 * Parse metacafe url
	 *
	 * @param string $url
	 * @return string processed url
	 */
	protected function process_metacafe($url) {
		if (preg_match('/watch([^(\\&|$)]*)/', $url, $matches)) {
			$parts = explode('/', $matches[1]);
			$videoId = $parts[1];
			$url = 'http://www.metacafe.com/fplayer/' . $videoId . '/.swf';
		}
		return $url;
	}

	/**
	 * Parse myvideo url
	 *
	 * @param string $url
	 * @return string processed url
	 */
	protected function process_myvideo($url) {
		preg_match('/watch([^(\\&|$)]*)/', $url, $matches);
		$parts = explode('/', $matches[1]);
		$videoId = $parts[1];
		return $this->getUrlSchema() . 'www.myvideo.de/movie/' . $videoId . '/';
	}

	/**
	 * Parse liveleak url
	 *
	 * @param string $url
	 * @return string processed url
	 */
	protected function process_liveleak($url) {
		preg_match('/i=([^(\\&|$)]*)/', $url, $matches);
		$videoId = $matches[1];
		return 'http://www.liveleak.com/e/' . $videoId;
	}

	/**
	 * Parse veoh url
	 *
	 * @param string $url
	 * @return string processed url
	 */
	protected function process_veoh($url) {
		preg_match('/watch\\/([^(\\&|$)]*)/', $url, $matches);
		$videoId = $matches[1];
		return 'http://www.veoh.com/static/swf/webplayer/WebPlayer.swf?version=AFrontend.5.5.2.1001&permalinkId=' . $videoId;
	}

	/**
	 * Get the correct url schema
	 *
	 * @return string
	 */
	protected function getUrlSchema() {
		return \TYPO3\CMS\Core\Utility\GeneralUtility::getIndpEnv('TYPO3_SSL') ? 'https://' : 'http://';
	}

}
