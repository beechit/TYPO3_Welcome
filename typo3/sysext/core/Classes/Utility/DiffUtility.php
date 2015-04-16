<?php
namespace TYPO3\CMS\Core\Utility;

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
 * This class has functions which generates a difference output of a content string
 *
 * @author Kasper Skårhøj <kasperYYYY@typo3.com>
 */
class DiffUtility {

	/**
	 * If set, the HTML tags are stripped from the input strings first.
	 *
	 * @var bool
	 */
	public $stripTags = 0;

	/**
	 * Diff options. eg "--unified=3"
	 *
	 * @var string
	 */
	public $diffOptions = '';

	/**
	 * This indicates the number of times the function addClearBuffer has been called - and used to detect the very first call...
	 *
	 * @var int
	 */
	public $clearBufferIdx = 0;

	/**
	 * @var int
	 */
	public $differenceLgd = 0;

	/**
	 * This will produce a color-marked-up diff output in HTML from the input strings.
	 *
	 * @param string $str1 String 1
	 * @param string $str2 String 2
	 * @param string $wrapTag Setting the wrapping tag name
	 * @return string Formatted output.
	 */
	public function makeDiffDisplay($str1, $str2, $wrapTag = 'span') {
		if ($this->stripTags) {
			$str1 = strip_tags($str1);
			$str2 = strip_tags($str2);
		} else {
			$str1 = $this->tagSpace($str1);
			$str2 = $this->tagSpace($str2);
		}
		$str1Lines = $this->explodeStringIntoWords($str1);
		$str2Lines = $this->explodeStringIntoWords($str2);
		$diffRes = $this->getDiff(implode(LF, $str1Lines) . LF, implode(LF, $str2Lines) . LF);
		if (is_array($diffRes)) {
			$c = 0;
			$diffResArray = array();
			$differenceStr = '';
			foreach ($diffRes as $lValue) {
				if ((int)$lValue) {
					$c = (int)$lValue;
					$diffResArray[$c]['changeInfo'] = $lValue;
				}
				if ($lValue[0] === '<') {
					$differenceStr .= ($diffResArray[$c]['old'][] = substr($lValue, 2));
				}
				if ($lValue[0] === '>') {
					$differenceStr .= ($diffResArray[$c]['new'][] = substr($lValue, 2));
				}
			}
			$this->differenceLgd = strlen($differenceStr);
			$outString = '';
			$clearBuffer = '';
			$str1LinesCount = count($str1Lines);
			for ($a = -1; $a < $str1LinesCount; $a++) {
				if (is_array($diffResArray[$a + 1])) {
					// a=Add, c=change, d=delete: If a, then the content is Added after the entry and we must insert the line content as well.
					if (strstr($diffResArray[$a + 1]['changeInfo'], 'a')) {
						$clearBuffer .= htmlspecialchars($str1Lines[$a]) . ' ';
					}
					$outString .= $this->addClearBuffer($clearBuffer);
					$clearBuffer = '';
					if (is_array($diffResArray[$a + 1]['old'])) {
						$outString .= '<' . $wrapTag . ' class="text-danger">' . htmlspecialchars(implode(' ', $diffResArray[($a + 1)]['old'])) . '</' . $wrapTag . '> ';
					}
					if (is_array($diffResArray[$a + 1]['new'])) {
						$outString .= '<' . $wrapTag . ' class="text-success">' . htmlspecialchars(implode(' ', $diffResArray[($a + 1)]['new'])) . '</' . $wrapTag . '> ';
					}
					$chInfParts = explode(',', $diffResArray[$a + 1]['changeInfo']);
					if ((string)$chInfParts[0] === (string)($a + 1)) {
						$newLine = (int)$chInfParts[1] - 1;
						if ($newLine > $a) {
							$a = $newLine;
						}
					}
				} else {
					$clearBuffer .= htmlspecialchars($str1Lines[$a]) . ' ';
				}
			}
			$outString .= $this->addClearBuffer($clearBuffer, 1);
			$outString = str_replace('  ', LF, $outString);
			if (!$this->stripTags) {
				$outString = $this->tagSpace($outString, 1);
			}
			return $outString;
		}
	}

	/**
	 * Produce a diff (using the "diff" application) between two strings
	 * The function will write the two input strings to temporary files, then execute the diff program, delete the temp files and return the result.
	 *
	 * @param string $str1 String 1
	 * @param string $str2 String 2
	 * @return array The result from the exec() function call.
	 * @access private
	 */
	public function getDiff($str1, $str2) {
		// Create file 1 and write string
		$file1 = GeneralUtility::tempnam('diff1_');
		GeneralUtility::writeFile($file1, $str1);
		// Create file 2 and write string
		$file2 = GeneralUtility::tempnam('diff2_');
		GeneralUtility::writeFile($file2, $str2);
		// Perform diff.
		$cmd = $GLOBALS['TYPO3_CONF_VARS']['BE']['diff_path'] . ' ' . $this->diffOptions . ' ' . $file1 . ' ' . $file2;
		$res = array();
		CommandUtility::exec($cmd, $res);
		unlink($file1);
		unlink($file2);
		return $res;
	}

	/**
	 * Will bring down the length of strings to < 150 chars if they were longer than 200 chars. This done by preserving the 70 first and last chars and concatenate those strings with "..." and a number indicating the string length
	 *
	 * @param string $clearBuffer The input string.
	 * @param bool $last If set, it indicates that the string should just end with ... (thus no "complete" ending)
	 * @return string Processed string.
	 * @access private
	 */
	public function addClearBuffer($clearBuffer, $last = 0) {
		if (strlen($clearBuffer) > 200) {
			$clearBuffer = ($this->clearBufferIdx ? GeneralUtility::fixed_lgd_cs($clearBuffer, 70) : '') . '[' . strlen($clearBuffer) . ']' . (!$last ? GeneralUtility::fixed_lgd_cs($clearBuffer, -70) : '');
		}
		$this->clearBufferIdx++;
		return $clearBuffer;
	}

	/**
	 * Explodes the input string into words.
	 * This is done by splitting first by lines, then by space char. Each word will be in stored as a value in an array. Lines will be indicated by two subsequent empty values.
	 *
	 * @param string $str The string input
	 * @return array Array with words.
	 * @access private
	 */
	public function explodeStringIntoWords($str) {
		$strArr = GeneralUtility::trimExplode(LF, $str);
		$outArray = array();
		foreach ($strArr as $lineOfWords) {
			$allWords = GeneralUtility::trimExplode(' ', $lineOfWords, TRUE);
			$outArray[] = $allWords;
			$outArray[] = array('');
			$outArray[] = array('');
		}
		return call_user_func_array('array_merge', $outArray);
	}

	/**
	 * Adds a space character before and after HTML tags (more precisely any found < or >)
	 *
	 * @param string $str String to process
	 * @param bool $rev If set, the < > searched for will be &lt; and &gt;
	 * @return string Processed string
	 * @access private
	 */
	public function tagSpace($str, $rev = 0) {
		if ($rev) {
			return str_replace(' &lt;', '&lt;', str_replace('&gt; ', '&gt;', $str));
		} else {
			return str_replace('<', ' <', str_replace('>', '> ', $str));
		}
	}

}
