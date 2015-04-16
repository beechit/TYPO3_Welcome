<?php
namespace TYPO3\CMS\Backend\Sprite;

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

use TYPO3\CMS\Core\Html\HtmlParser;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Sprite generator
 *
 * @author Steffen Ritter <info@steffen-ritter.net>
 */
class SpriteGenerator {

	/**
	 * Template creating CSS for the spritefile
	 *
	 * @var string
	 */
	protected $templateSprite = '
.###NAMESPACE###-###SPRITENAME### {
	background-image: url(\'###SPRITEURL###\') !important;
	height: ###DEFAULTHEIGHT###px;
	width: ###DEFAULTWIDTH###px;
}
';

	/**
	 * Template creating CSS for the high density spritefile
	 *
	 * @var string
	 */
	protected $templateSpriteHighDensity =  '
.backgroundsize .###NAMESPACE###-###SPRITENAME### {
	background-image: url(\'###SPRITEURL###\') !important;
	background-size:###BGWIDTH### ###BGHEIGHT###;
}
';
	/**
	 * Template creating CSS for position and size of a single icon
	 *
	 * @var string
	 */
	protected $templateIcon = '.###NAMESPACE###-###ICONNAME### {
	background-position: -###LEFT###px -###TOP###px !important;
###SIZE_INFO###
}
';

	/**
	 * @var bool
	 */
	protected $enableHighDensitySprite = TRUE;

	/**
	 * Most common icon-width in the sprite
	 *
	 * @var int
	 */
	protected $defaultWidth = 0;

	/**
	 * Most common icon-height in the sprite
	 *
	 * @var int
	 */
	protected $defaultHeight = 0;

	/**
	 * Calculated width of the sprite
	 *
	 * @var int
	 */
	protected $spriteWidth = 0;

	/**
	 * Calculated height of the sprite
	 *
	 * @var int
	 */
	protected $spriteHeight = 0;

	/**
	 * Sprite name, will be the filename, too
	 *
	 * @var string
	 */
	protected $spriteName = '';

	/**
	 * The folder the sprite-images will be saved (relative to PATH_site)
	 *
	 * @var string
	 */
	protected $spriteFolder = 'typo3temp/sprites/';

	/**
	 * The folder the sprite-cs will be saved (relative to PATH_site)
	 *
	 * @var string
	 */
	protected $cssFolder = 'typo3temp/sprites/';

	/**
	 * The spriteName will not be included in icon names
	 *
	 * @var bool
	 */
	protected $omitSpriteNameInIconName = FALSE;

	/**
	 * Namespace of css classes
	 *
	 * @var string
	 */
	protected $nameSpace = 't3-icon';

	/**
	 * Setting this to TRUE, the timestamp of the creation will be included to the background import
	 * helps to easily rebuild sprites without cache problems
	 *
	 * @var bool
	 */
	protected $includeTimestampInCSS = TRUE;

	/**
	 * All bases/root-names included in the sprite which has to be in css
	 * as sprite to include the background-image
	 *
	 * @var array
	 */
	protected $spriteBases = array();

	/**
	 * Collects data about all icons to process
	 *
	 * @var array
	 */
	protected $iconsData = array();

	/**
	 * Collects all sizes of icons within this sprite and there count
	 *
	 * @var array
	 */
	protected $iconSizes = array();

	/**
	 * Maps icon-sizes to iconnames
	 *
	 * @var array
	 */
	protected $iconNamesPerSize = array();

	/**
	 * space in px between to icons in the sprite (gap)
	 *
	 * @var int
	 */
	protected $space = 2;

	/**
	 * Initializes the configuration of the spritegenerator
	 *
	 * @param string $spriteName The name of the sprite to be generated
	 */
	public function __construct($spriteName) {
		$this->spriteName = $spriteName;
	}

	/**
	 * Sets namespace of css code
	 *
	 * @param string $nameSpace
	 * @return SpriteGenerator An instance of $this, to enable "chaining".
	 */
	public function setNamespace($nameSpace) {
		$this->nameSpace = $nameSpace;
		return $this;
	}

	/**
	 * Sets the spritename
	 *
	 * @param string $spriteName The name of the sprite to be generated
	 * @return SpriteGenerator An instance of $this, to enable "chaining".
	 */
	public function setSpriteName($spriteName) {
		$this->spriteName = $spriteName;
		return $this;
	}

	/**
	 * Sets the sprite-graphics target-folder
	 *
	 * @param string $folder The target folder where the generated sprite is stored
	 * @return SpriteGenerator An instance of $this, to enable "chaining".
	 */
	public function setSpriteFolder($folder) {
		$this->spriteFolder = $folder;
		return $this;
	}

	/**
	 * Sets the sprite-css target-folder
	 *
	 * @param string $folder the target folder where the generated CSS files are stored
	 * @return SpriteGenerator An instance of $this, to enable "chaining".
	 */
	public function setCSSFolder($folder) {
		$this->cssFolder = $folder;
		return $this;
	}

	/**
	 * Enables/Disables HighDensitySprite Generation
	 *
	 * @param bool $enable
	 * @return SpriteGenerator An instance of $this, to enable "chaining".
	 */
	public function setEnableHighDensitySprite($enable = TRUE) {
		$this->enableHighDensitySprite = $enable;
		return $this;
	}

	/**
	 * Setter do enable the exclusion of the sprites-name from iconnames
	 *
	 * @param bool $value
	 * @return SpriteGenerator An instance of $this, to enable "chaining".
	 */
	public function setOmitSpriteNameInIconName($value) {
		$this->omitSpriteNameInIconName = is_bool($value) ? $value : FALSE;
		return $this;
	}

	/**
	 * Setter to adjust how much space is between to icons in the sprite
	 *
	 * @param int $value
	 * @return SpriteGenerator An instance of $this, to enable "chaining".
	 */
	public function setIconSpace($value) {
		$this->space = (int)$value;
		return $this;
	}

	/**
	 * Setter for timestamp inclusion: imageFiles will be included with ?timestamp
	 *
	 * @param bool $value
	 * @return SpriteGenerator An instance of $this, to enable "chaining".
	 */
	public function setIncludeTimestampInCSS($value) {
		$this->includeTimestampInCSS = is_bool($value) ? $value : TRUE;
		return $this;
	}

	/**
	 * Reads all png,gif,jpg files from the passed folder name (including 1 subfolder level)
	 * extracts size information and stores data in internal array,
	 * afterwards triggers sprite generation.
	 *
	 * @param array $inputFolder Folder from which files are read
	 * @return array
	 */
	public function generateSpriteFromFolder(array $inputFolder) {
		$iconArray = array();
		foreach ($inputFolder as $folder) {
			// Detect all files to be included in sprites
			$iconArray = array_merge($iconArray, $this->getFolder($folder));
		}
		return $this->generateSpriteFromArray($iconArray);
	}

	/**
	 * Method processes an array of files into an sprite,
	 * the array can be build from files within an folder or
	 * by hand (as the SpriteManager does)
	 *
	 * @param array $files array(icon name => icon file)
	 * @return array
	 */
	public function generateSpriteFromArray(array $files) {
		if (!$this->omitSpriteNameInIconName) {
			$this->spriteBases[] = $this->spriteName;
		}
		$this->buildFileInformationCache($files);
		// Calculate Icon Position in sprite
		$this->calculateSpritePositions();
		$this->generateGraphic();
		if ($this->enableHighDensitySprite) {
			$this->generateHighDensityGraphic();
		}
		$this->generateCSS();
		$iconNames = array_keys($this->iconsData);
		natsort($iconNames);
		return array(
			'spriteImage' => PATH_site . $this->spriteFolder . $this->spriteName . '.png',
			'cssFile' => PATH_site . $this->cssFolder . $this->spriteName . '.css',
			'iconNames' => $iconNames
		);
	}

	/**
	 * Generates the css files
	 *
	 * @return void
	 */
	protected function generateCSS() {
		$cssData = '';
		if ($this->includeTimestampInCSS) {
			$timestamp = '?' . time();
		} else {
			$timestamp = '';
		}
		$spritePathForCSS = $this->resolveSpritePath();
		$markerArray = array(
			'###NAMESPACE###' => $this->nameSpace,
			'###DEFAULTWIDTH###' => $this->defaultWidth,
			'###DEFAULTHEIGHT###' => $this->defaultHeight,
			'###SPRITENAME###' => '',
			'###SPRITEURL###' => $spritePathForCSS ? $spritePathForCSS . '/' : ''
		);
		$markerArray['###SPRITEURL###'] .= $this->spriteName . '.png' . $timestamp;
		foreach ($this->spriteBases as $base) {
			$markerArray['###SPRITENAME###'] = $base;
			$cssData .= HtmlParser::substituteMarkerArray($this->templateSprite, $markerArray);

			if ($this->enableHighDensitySprite) {
				$highDensityMarkerArray = array_merge($markerArray, array(
					'###BGWIDTH###' => $this->spriteWidth . 'px',
					'###BGHEIGHT###' => $this->spriteHeight . 'px',
					'###SPRITEURL###' => str_replace(
						$this->spriteName . '.png',
						$this->spriteName . '@x2.png',
						$markerArray['###SPRITEURL###']
					)
				));
				$cssData .= HtmlParser::substituteMarkerArray($this->templateSpriteHighDensity, $highDensityMarkerArray);
			}
		}

		foreach ($this->iconsData as $data) {
			$temp = $data['iconNameParts'];
			array_shift($temp);
			$cssName = implode('-', $temp);
			$markerArrayIcons = array(
				'###NAMESPACE###' => $this->nameSpace,
				'###ICONNAME###' => $cssName,
				'###LEFT###' => $data['left'],
				'###TOP###' => $data['top'],
				'###SIZE_INFO###' => ''
			);
			if ($data['height'] != $this->defaultHeight) {
				$markerArrayIcons['###SIZE_INFO###'] .= TAB . 'height: ' . $data['height'] . 'px;' . LF;
			}
			if ($data['width'] != $this->defaultWidth) {
				$markerArrayIcons['###SIZE_INFO###'] .= TAB . 'width: ' . $data['width'] . 'px;' . LF;
			}
			$cssData .= HtmlParser::substituteMarkerArray($this->templateIcon, $markerArrayIcons);
		}
		GeneralUtility::writeFile(PATH_site . $this->cssFolder . $this->spriteName . '.css', $cssData);
	}

	/**
	 * Compares image path to CSS path and creates the relative backpath from css to the sprites
	 *
	 * @return string
	 */
	protected function resolveSpritePath() {
		// Fix window paths
		$this->cssFolder = str_replace('\\', '/', $this->cssFolder);
		$this->spriteFolder = str_replace('\\', '/', $this->spriteFolder);
		$cssPathSegments = GeneralUtility::trimExplode('/', trim($this->cssFolder, '/'));
		$graphicPathSegments = GeneralUtility::trimExplode('/', trim($this->spriteFolder, '/'));
		$i = 0;
		while (isset($cssPathSegments[$i]) && isset($graphicPathSegments[$i]) && $cssPathSegments[$i] == $graphicPathSegments[$i]) {
			unset($cssPathSegments[$i]);
			unset($graphicPathSegments[$i]);
			++$i;
		}
		foreach ($cssPathSegments as $key => $value) {
			$cssPathSegments[$key] = '..';
		}
		$completePath = array_merge($cssPathSegments, $graphicPathSegments);
		$path = implode('/', $completePath);
		return GeneralUtility::resolveBackPath($path);
	}

	/**
	 * The actual sprite generator, renders the command for Im/GM and executes
	 *
	 * @return void
	 */
	protected function generateGraphic() {
		$tempSprite = GeneralUtility::tempnam($this->spriteName, '.png');
		$filePath = PATH_site . $this->spriteFolder . $this->spriteName . '.png';

		// Create black true color image with given size
		$newSprite = imagecreatetruecolor($this->spriteWidth, $this->spriteHeight);
		imagesavealpha($newSprite, TRUE);
		// Make it transparent
		imagefill($newSprite, 0, 0, imagecolorallocatealpha($newSprite, 0, 255, 255, 127));
		foreach ($this->iconsData as $icon) {
			$function = 'imagecreatefrom' . strtolower($icon['fileExtension']);
			if (function_exists($function)) {
				$currentIcon = $function($icon['fileName']);
				imagecopy($newSprite, $currentIcon, $icon['left'], $icon['top'], 0, 0, $icon['width'], $icon['height']);
			}
		}
		imagepng($newSprite, $tempSprite);
		GeneralUtility::upload_copy_move($tempSprite, $filePath);
		GeneralUtility::unlink_tempfile($tempSprite);
	}

	/**
	 * The actual sprite generator, renders the command for IM/GM and executes
	 *
	 * @return void
	 */
	protected function generateHighDensityGraphic() {
		$tempSprite = GeneralUtility::tempnam($this->spriteName . '@x2', '.png');
		$filePath = PATH_site . $this->spriteFolder . $this->spriteName . '@x2.png';

		// Create black true color image with given size
		$newSprite = imagecreatetruecolor($this->spriteWidth * 2, $this->spriteHeight * 2);
		imagesavealpha($newSprite, TRUE);
		// Make it transparent
		imagefill($newSprite, 0, 0, imagecolorallocatealpha($newSprite, 0, 255, 255, 127));
		foreach ($this->iconsData as $icon) {
			$function = 'imagecreatefrom' . strtolower($icon['fileExtension']);
			if (function_exists($function)) {
				if ($icon['fileNameHighDensity'] !== FALSE) {
					// copy HighDensity file
					$currentIcon = $function($icon['fileNameHighDensity']);
					imagecopy($newSprite, $currentIcon, $icon['left'] * 2, $icon['top'] * 2, 0, 0, $icon['width'] * 2, $icon['height'] * 2);
				} else {
					// scale up normal file
					$currentIcon = $function($icon['fileName']);
					imagecopyresized($newSprite, $currentIcon, $icon['left'] * 2, $icon['top'] * 2, 0, 0, $icon['width'] * 2, $icon['height'] * 2, $icon['width'], $icon['height']);
				}
			}
		}
		imagepng($newSprite, $tempSprite);
		GeneralUtility::upload_copy_move($tempSprite, $filePath);
		GeneralUtility::unlink_tempfile($tempSprite);
	}
	/**
	 * Arranges icons in sprites,
	 * afterwards all icons have information about the position in sprite
	 */
	protected function calculateSpritePositions() {
		// Calculate width of every icon-size-group
		$sizes = array();
		foreach ($this->iconSizes as $sizeTag => $count) {
			$size = $this->explodeSizeTag($sizeTag);
			$rowWidth = (int)ceil(sqrt($count)) * $size['width'];
			while (isset($sizes[$rowWidth])) {
				$rowWidth++;
			}
			$sizes[$rowWidth] = $sizeTag;
		}
		// Reverse sorting: widest group to top
		krsort($sizes);
		$currentTop = 0;
		// Integrate all icons grouped by icons size into the sprite
		foreach ($sizes as $sizeTag) {
			$size = $this->explodeSizeTag($sizeTag);
			$currentLeft = 0;
			$rowCounter = 0;
			$rowSize = ceil(sqrt($this->iconSizes[$sizeTag]));
			$rowWidth = $rowSize * $size['width'] + ($rowSize - 1) * $this->space;
			$this->spriteWidth = $rowWidth > $this->spriteWidth ? $rowWidth : $this->spriteWidth;
			$firstLine = TRUE;
			natsort($this->iconNamesPerSize[$sizeTag]);
			foreach ($this->iconNamesPerSize[$sizeTag] as $iconName) {
				if ($rowCounter == $rowSize - 1) {
					$rowCounter = -1;
				} elseif ($rowCounter == 0) {
					if (!$firstLine) {
						$currentTop += $size['height'];
						$currentTop += $this->space;
					}
					$firstLine = FALSE;
					$currentLeft = 0;
				}
				$this->iconsData[$iconName]['left'] = $currentLeft;
				$this->iconsData[$iconName]['top'] = $currentTop;
				$currentLeft += $size['width'];
				$currentLeft += $this->space;
				$rowCounter++;
			}
			$currentTop += $size['height'];
			$currentTop += $this->space;
		}
		$this->spriteHeight = $currentTop;
	}

	/**
	 * Function getFolder traverses the target directory,
	 * locates all iconFiles and collects them into an array
	 *
	 * @param string $directoryPath Path to an folder which contains images
	 * @return array Returns an array with all files key: iconname, value: fileName
	 */
	protected function getFolder($directoryPath) {
		$subFolders = GeneralUtility::get_dirs(PATH_site . $directoryPath);
		if (!$this->omitSpriteNameInIconName) {
			$subFolders[] = '';
		}
		$resultArray = array();
		foreach ($subFolders as $folder) {
			if ($folder !== '.svn') {
				$icons = GeneralUtility::getFilesInDir(PATH_site . $directoryPath . $folder . '/', 'gif,png,jpg');
				if (!in_array($folder, $this->spriteBases) && count($icons) && $folder !== '') {
					$this->spriteBases[] = $folder;
				}
				foreach ($icons as $icon) {
					$fileInfo = pathinfo($icon);
					$iconName = ($folder ? $folder . '-' : '') . $fileInfo['filename'];
					if (!$this->omitSpriteNameInIconName) {
						$iconName = $this->spriteName . '-' . $iconName;
					}
					$resultArray[$iconName] = $directoryPath . $folder . '/' . $icon;
				}
			}
		}
		return $resultArray;
	}

	/**
	 * Generates file information cache from file array
	 *
	 * @param array $files List of all files with their icon name
	 * @return void
	 */
	protected function buildFileInformationCache(array $files) {
		foreach ($files as $iconName => $iconFile) {
			$iconNameParts = GeneralUtility::trimExplode('-', $iconName);
			if (!in_array($iconNameParts[0], $this->spriteBases)) {
				$this->spriteBases[] = $iconNameParts[0];
			}
			$fileInfo = @pathinfo((PATH_site . $iconFile));
			$imageInfo = @getimagesize((PATH_site . $iconFile));
			$this->iconsData[$iconName] = array(
				'iconName' => $iconName,
				'iconNameParts' => $iconNameParts,
				'singleName' => $fileInfo['filename'],
				'fileExtension' => $fileInfo['extension'],
				'fileName' => PATH_site . $iconFile,
				'width' => $imageInfo[0],
				'height' => $imageInfo[1],
				'left' => 0,
				'top' => 0,
				'fileNameHighDensity' => FALSE
			);
			if ($this->enableHighDensitySprite) {
				$highDensityFile = str_replace('.' . $fileInfo['extension'], '@x2.' . $fileInfo['extension'], $iconFile);
				if (@file_exists(PATH_site . $highDensityFile)) {
					$this->iconsData[$iconName]['fileNameHighDensity'] = $highDensityFile;
				}
			}
			$sizeTag = $imageInfo[0] . 'x' . $imageInfo[1];
			if (isset($this->iconSizes[$sizeTag])) {
				$this->iconSizes[$sizeTag] += 1;
			} else {
				$this->iconSizes[$sizeTag] = 1;
				$this->iconNamesPerSize[$sizeTag] = array();
			}
			$this->iconNamesPerSize[$sizeTag][] = $iconName;
		}
		// Find most common image size, save it as default
		asort($this->iconSizes);
		$defaultSize = $this->explodeSizeTag(array_pop(array_keys($this->iconSizes)));
		$this->defaultWidth = $defaultSize['width'];
		$this->defaultHeight = $defaultSize['height'];
	}

	/**
	 * Transforms size tag into size array
	 *
	 * @param string $tag A size tag at the cache arrays
	 * @return array
	 */
	protected function explodeSizeTag($tag = '') {
		$size = GeneralUtility::trimExplode('x', $tag);
		return array(
			'width' => $size[0],
			'height' => $size[1]
		);
	}

}
