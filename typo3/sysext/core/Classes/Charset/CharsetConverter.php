<?php
namespace TYPO3\CMS\Core\Charset;

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

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

/**
 * Notes on UTF-8
 *
 * Functions working on UTF-8 strings:
 *
 * - strchr/strstr
 * - strrchr
 * - substr_count
 * - implode/explode/join
 *
 * Functions nearly working on UTF-8 strings:
 *
 * - strlen: returns the length in BYTES, if you need the length in CHARACTERS use utf8_strlen
 * - trim/ltrim/rtrim: the second parameter 'charlist' won't work for characters not contained in 7-bit ASCII
 * - strpos/strrpos: they return the BYTE position, if you need the CHARACTER position use utf8_strpos/utf8_strrpos
 * - htmlentities: charset support for UTF-8 only since PHP 4.3.0
 * - preg_*: Support compiled into PHP by default nowadays, but could be unavailable, need to use modifier
 *
 * Functions NOT working on UTF-8 strings:
 *
 * - str*cmp
 * - stristr
 * - stripos
 * - substr
 * - strrev
 * - split/spliti
 * - ...
 */

/**
 * Class for conversion between charsets
 *
 * @author Kasper Skårhøj <kasperYYYY@typo3.com>
 * @author Martin Kutschker <martin.t.kutschker@blackbox.net>
 */
class CharsetConverter {

	/**
	 * @var \TYPO3\CMS\Core\Localization\Locales
	 */
	protected $locales;

	/**
	 * ASCII Value for chars with no equivalent.
	 *
	 * @var int
	 */
	public $noCharByteVal = 63;

	/**
	 * This is the array where parsed conversion tables are stored (cached)
	 *
	 * @var array
	 */
	public $parsedCharsets = array();

	/**
	 * An array where case folding data will be stored (cached)
	 *
	 * @var array
	 */
	public $caseFolding = array();

	/**
	 * An array where charset-to-ASCII mappings are stored (cached)
	 *
	 * @var array
	 */
	public $toASCII = array();

	/**
	 * This tells the converter which charsets has two bytes per char:
	 *
	 * @var array
	 */
	public $twoByteSets = array(
		'ucs-2' => 1
	);

	/**
	 * This tells the converter which charsets has four bytes per char:
	 *
	 * @var array
	 */
	public $fourByteSets = array(
		'ucs-4' => 1,
		// 4-byte Unicode
		'utf-32' => 1
	);

	/**
	 * This tells the converter which charsets use a scheme like the Extended Unix Code:
	 *
	 * @var array
	 */
	public $eucBasedSets = array(
		'gb2312' => 1,
		// Chinese, simplified.
		'big5' => 1,
		// Chinese, traditional.
		'euc-kr' => 1,
		// Korean
		'shift_jis' => 1
	);

	/**
	 * @link http://developer.apple.com/documentation/macos8/TextIntlSvcs/TextEncodingConversionManager/TEC1.5/TEC.b0.html
	 * @link http://czyborra.com/charsets/iso8859.html
	 *
	 * @var array
	 */
	public $synonyms = array(
		'us' => 'ascii',
		'us-ascii' => 'ascii',
		'cp819' => 'iso-8859-1',
		'ibm819' => 'iso-8859-1',
		'iso-ir-100' => 'iso-8859-1',
		'iso-ir-101' => 'iso-8859-2',
		'iso-ir-109' => 'iso-8859-3',
		'iso-ir-110' => 'iso-8859-4',
		'iso-ir-144' => 'iso-8859-5',
		'iso-ir-127' => 'iso-8859-6',
		'iso-ir-126' => 'iso-8859-7',
		'iso-ir-138' => 'iso-8859-8',
		'iso-ir-148' => 'iso-8859-9',
		'iso-ir-157' => 'iso-8859-10',
		'iso-ir-179' => 'iso-8859-13',
		'iso-ir-199' => 'iso-8859-14',
		'iso-ir-203' => 'iso-8859-15',
		'csisolatin1' => 'iso-8859-1',
		'csisolatin2' => 'iso-8859-2',
		'csisolatin3' => 'iso-8859-3',
		'csisolatin5' => 'iso-8859-9',
		'csisolatin8' => 'iso-8859-14',
		'csisolatin9' => 'iso-8859-15',
		'csisolatingreek' => 'iso-8859-7',
		'iso-celtic' => 'iso-8859-14',
		'latin1' => 'iso-8859-1',
		'latin2' => 'iso-8859-2',
		'latin3' => 'iso-8859-3',
		'latin5' => 'iso-8859-9',
		'latin6' => 'iso-8859-10',
		'latin8' => 'iso-8859-14',
		'latin9' => 'iso-8859-15',
		'l1' => 'iso-8859-1',
		'l2' => 'iso-8859-2',
		'l3' => 'iso-8859-3',
		'l5' => 'iso-8859-9',
		'l6' => 'iso-8859-10',
		'l8' => 'iso-8859-14',
		'l9' => 'iso-8859-15',
		'cyrillic' => 'iso-8859-5',
		'arabic' => 'iso-8859-6',
		'tis-620' => 'iso-8859-11',
		'win874' => 'windows-874',
		'win1250' => 'windows-1250',
		'win1251' => 'windows-1251',
		'win1252' => 'windows-1252',
		'win1253' => 'windows-1253',
		'win1254' => 'windows-1254',
		'win1255' => 'windows-1255',
		'win1256' => 'windows-1256',
		'win1257' => 'windows-1257',
		'win1258' => 'windows-1258',
		'cp1250' => 'windows-1250',
		'cp1251' => 'windows-1251',
		'cp1252' => 'windows-1252',
		'ms-ee' => 'windows-1250',
		'ms-ansi' => 'windows-1252',
		'ms-greek' => 'windows-1253',
		'ms-turk' => 'windows-1254',
		'winbaltrim' => 'windows-1257',
		'koi-8ru' => 'koi-8r',
		'koi8r' => 'koi-8r',
		'cp878' => 'koi-8r',
		'mac' => 'macroman',
		'macintosh' => 'macroman',
		'euc-cn' => 'gb2312',
		'x-euc-cn' => 'gb2312',
		'euccn' => 'gb2312',
		'cp936' => 'gb2312',
		'big-5' => 'big5',
		'cp950' => 'big5',
		'eucjp' => 'euc-jp',
		'sjis' => 'shift_jis',
		'shift-jis' => 'shift_jis',
		'cp932' => 'shift_jis',
		'cp949' => 'euc-kr',
		'utf7' => 'utf-7',
		'utf8' => 'utf-8',
		'utf16' => 'utf-16',
		'utf32' => 'utf-32',
		'utf8' => 'utf-8',
		'ucs2' => 'ucs-2',
		'ucs4' => 'ucs-4'
	);

	/**
	 * Mapping of iso-639-1 language codes to script names
	 *
	 * @var array
	 */
	public $lang_to_script = array(
		// iso-639-1 language codes, see http://www.loc.gov/standards/iso639-2/php/code_list.php
		'af' => 'west_european',
		//Afrikaans
		'ar' => 'arabic',
		'bg' => 'cyrillic',
		// Bulgarian
		'bs' => 'east_european',
		// Bosnian
		'cs' => 'east_european',
		// Czech
		'da' => 'west_european',
		// Danish
		'de' => 'west_european',
		// German
		'es' => 'west_european',
		// Spanish
		'et' => 'estonian',
		'eo' => 'unicode',
		// Esperanto
		'eu' => 'west_european',
		// Basque
		'fa' => 'arabic',
		// Persian
		'fi' => 'west_european',
		// Finish
		'fo' => 'west_european',
		// Faroese
		'fr' => 'west_european',
		// French
		'ga' => 'west_european',
		// Irish
		'gl' => 'west_european',
		// Galician
		'gr' => 'greek',
		'he' => 'hebrew',
		// Hebrew (since 1998)
		'hi' => 'unicode',
		// Hindi
		'hr' => 'east_european',
		// Croatian
		'hu' => 'east_european',
		// Hungarian
		'iw' => 'hebrew',
		// Hebrew (til 1998)
		'is' => 'west_european',
		// Icelandic
		'it' => 'west_european',
		// Italian
		'ja' => 'japanese',
		'ka' => 'unicode',
		// Georgian
		'kl' => 'west_european',
		// Greenlandic
		'km' => 'unicode',
		// Khmer
		'ko' => 'korean',
		'lt' => 'lithuanian',
		'lv' => 'west_european',
		// Latvian/Lettish
		'nl' => 'west_european',
		// Dutch
		'no' => 'west_european',
		// Norwegian
		'nb' => 'west_european',
		// Norwegian Bokmal
		'nn' => 'west_european',
		// Norwegian Nynorsk
		'pl' => 'east_european',
		// Polish
		'pt' => 'west_european',
		// Portuguese
		'ro' => 'east_european',
		// Romanian
		'ru' => 'cyrillic',
		// Russian
		'sk' => 'east_european',
		// Slovak
		'sl' => 'east_european',
		// Slovenian
		'sr' => 'cyrillic',
		// Serbian
		'sv' => 'west_european',
		// Swedish
		'sq' => 'albanian',
		// Albanian
		'th' => 'thai',
		'uk' => 'cyrillic',
		// Ukranian
		'vi' => 'vietnamese',
		'zh' => 'chinese',
		// MS language codes, see http://msdn.microsoft.com/library/default.asp?url=/library/en-us/vclib/html/_crt_language_strings.asp
		// http://msdn.microsoft.com/library/default.asp?url=/library/en-us/wceinternational5/html/wce50conLanguageIdentifiersandLocales.asp
		'afk' => 'west_european',
		// Afrikaans
		'ara' => 'arabic',
		'bgr' => 'cyrillic',
		// Bulgarian
		'cat' => 'west_european',
		// Catalan
		'chs' => 'simpl_chinese',
		'cht' => 'trad_chinese',
		'csy' => 'east_european',
		// Czech
		'dan' => 'west_european',
		// Danisch
		'deu' => 'west_european',
		// German
		'dea' => 'west_european',
		// German (Austrian)
		'des' => 'west_european',
		// German (Swiss)
		'ena' => 'west_european',
		// English (Australian)
		'enc' => 'west_european',
		// English (Canadian)
		'eng' => 'west_european',
		// English
		'enz' => 'west_european',
		// English (New Zealand)
		'enu' => 'west_european',
		// English (United States)
		'euq' => 'west_european',
		// Basque
		'fos' => 'west_european',
		// Faroese
		'far' => 'arabic',
		// Persian
		'fin' => 'west_european',
		// Finish
		'fra' => 'west_european',
		// French
		'frb' => 'west_european',
		// French (Belgian)
		'frc' => 'west_european',
		// French (Canadian)
		'frs' => 'west_european',
		// French (Swiss)
		'geo' => 'unicode',
		// Georgian
		'glg' => 'west_european',
		// Galician
		'ell' => 'greek',
		'heb' => 'hebrew',
		'hin' => 'unicode',
		// Hindi
		'hun' => 'east_european',
		// Hungarian
		'isl' => 'west_european',
		// Icelandic
		'ita' => 'west_european',
		// Italian
		'its' => 'west_european',
		// Italian (Swiss)
		'jpn' => 'japanese',
		'khm' => 'unicode',
		// Khmer
		'kor' => 'korean',
		'lth' => 'lithuanian',
		'lvi' => 'west_european',
		// Latvian/Lettish
		'msl' => 'west_european',
		// Malay
		'nlb' => 'west_european',
		// Dutch (Belgian)
		'nld' => 'west_european',
		// Dutch
		'nor' => 'west_european',
		// Norwegian (bokmal)
		'non' => 'west_european',
		// Norwegian (nynorsk)
		'plk' => 'east_european',
		// Polish
		'ptg' => 'west_european',
		// Portuguese
		'ptb' => 'west_european',
		// Portuguese (Brazil)
		'rom' => 'east_european',
		// Romanian
		'rus' => 'cyrillic',
		// Russian
		'slv' => 'east_european',
		// Slovenian
		'sky' => 'east_european',
		// Slovak
		'srl' => 'east_european',
		// Serbian (Latin)
		'srb' => 'cyrillic',
		// Serbian (Cyrillic)
		'esp' => 'west_european',
		// Spanish (trad. sort)
		'esm' => 'west_european',
		// Spanish (Mexican)
		'esn' => 'west_european',
		// Spanish (internat. sort)
		'sve' => 'west_european',
		// Swedish
		'sqi' => 'albanian',
		// Albanian
		'tha' => 'thai',
		'trk' => 'turkish',
		'ukr' => 'cyrillic',
		// Ukrainian
		// English language names
		'afrikaans' => 'west_european',
		'albanian' => 'albanian',
		'arabic' => 'arabic',
		'basque' => 'west_european',
		'bosnian' => 'east_european',
		'bulgarian' => 'east_european',
		'catalan' => 'west_european',
		'croatian' => 'east_european',
		'czech' => 'east_european',
		'danish' => 'west_european',
		'dutch' => 'west_european',
		'english' => 'west_european',
		'esperanto' => 'unicode',
		'estonian' => 'estonian',
		'faroese' => 'west_european',
		'farsi' => 'arabic',
		'finnish' => 'west_european',
		'french' => 'west_european',
		'galician' => 'west_european',
		'georgian' => 'unicode',
		'german' => 'west_european',
		'greek' => 'greek',
		'greenlandic' => 'west_european',
		'hebrew' => 'hebrew',
		'hindi' => 'unicode',
		'hungarian' => 'east_european',
		'icelandic' => 'west_european',
		'italian' => 'west_european',
		'khmer' => 'unicode',
		'latvian' => 'west_european',
		'lettish' => 'west_european',
		'lithuanian' => 'lithuanian',
		'malay' => 'west_european',
		'norwegian' => 'west_european',
		'persian' => 'arabic',
		'polish' => 'east_european',
		'portuguese' => 'west_european',
		'russian' => 'cyrillic',
		'romanian' => 'east_european',
		'serbian' => 'cyrillic',
		'slovak' => 'east_european',
		'slovenian' => 'east_european',
		'spanish' => 'west_european',
		'svedish' => 'west_european',
		'that' => 'thai',
		'turkish' => 'turkish',
		'ukrainian' => 'cyrillic'
	);

	/**
	 * Mapping of language (family) names to charsets on Unix
	 *
	 * @var array
	 */
	public $script_to_charset_unix = array(
		'west_european' => 'iso-8859-1',
		'estonian' => 'iso-8859-1',
		'east_european' => 'iso-8859-2',
		'baltic' => 'iso-8859-4',
		'cyrillic' => 'iso-8859-5',
		'arabic' => 'iso-8859-6',
		'greek' => 'iso-8859-7',
		'hebrew' => 'iso-8859-8',
		'turkish' => 'iso-8859-9',
		'thai' => 'iso-8859-11',
		// = TIS-620
		'lithuanian' => 'iso-8859-13',
		'chinese' => 'gb2312',
		// = euc-cn
		'japanese' => 'euc-jp',
		'korean' => 'euc-kr',
		'simpl_chinese' => 'gb2312',
		'trad_chinese' => 'big5',
		'vietnamese' => '',
		'unicode' => 'utf-8',
		'albanian' => 'utf-8'
	);

	/**
	 * Mapping of language (family) names to charsets on Windows
	 *
	 * @var array
	 */
	public $script_to_charset_windows = array(
		'east_european' => 'windows-1250',
		'cyrillic' => 'windows-1251',
		'west_european' => 'windows-1252',
		'greek' => 'windows-1253',
		'turkish' => 'windows-1254',
		'hebrew' => 'windows-1255',
		'arabic' => 'windows-1256',
		'baltic' => 'windows-1257',
		'estonian' => 'windows-1257',
		'lithuanian' => 'windows-1257',
		'vietnamese' => 'windows-1258',
		'thai' => 'cp874',
		'korean' => 'cp949',
		'chinese' => 'gb2312',
		'japanese' => 'shift_jis',
		'simpl_chinese' => 'gb2312',
		'trad_chinese' => 'big5',
		'albanian' => 'windows-1250',
		'unicode' => 'utf-8'
	);

	/**
	 * Mapping of locale names to charsets
	 *
	 * @var array
	 */
	public $locale_to_charset = array(
		'japanese.euc' => 'euc-jp',
		'ja_jp.ujis' => 'euc-jp',
		'korean.euc' => 'euc-kr',
		'sr@Latn' => 'iso-8859-2',
		'zh_cn' => 'gb2312',
		'zh_hk' => 'big5',
		'zh_tw' => 'big5'
	);

	/**
	 * TYPO3 specific: Array with the system charsets used for each system language in TYPO3:
	 * Empty values means "iso-8859-1"
	 *
	 * @var array
	 */
	public $charSetArray = array(
		'af' => '',
		'ar' => 'iso-8859-6',
		'ba' => 'iso-8859-2',
		'bg' => 'windows-1251',
		'br' => '',
		'ca' => 'iso-8859-15',
		'ch' => 'gb2312',
		'cs' => 'windows-1250',
		'cz' => 'windows-1250',
		'da' => '',
		'de' => '',
		'dk' => '',
		'el' => 'iso-8859-7',
		'eo' => 'utf-8',
		'es' => '',
		'et' => 'iso-8859-4',
		'eu' => '',
		'fa' => 'utf-8',
		'fi' => '',
		'fo' => 'utf-8',
		'fr' => '',
		'fr_CA' => '',
		'ga' => '',
		'ge' => 'utf-8',
		'gl' => '',
		'gr' => 'iso-8859-7',
		'he' => 'utf-8',
		'hi' => 'utf-8',
		'hk' => 'big5',
		'hr' => 'windows-1250',
		'hu' => 'iso-8859-2',
		'is' => 'utf-8',
		'it' => '',
		'ja' => 'shift_jis',
		'jp' => 'shift_jis',
		'ka' => 'utf-8',
		'kl' => 'utf-8',
		'km' => 'utf-8',
		'ko' => 'euc-kr',
		'kr' => 'euc-kr',
		'lt' => 'windows-1257',
		'lv' => 'utf-8',
		'ms' => '',
		'my' => '',
		'nl' => '',
		'no' => '',
		'pl' => 'iso-8859-2',
		'pt' => '',
		'pt_BR' => '',
		'qc' => '',
		'ro' => 'iso-8859-2',
		'ru' => 'windows-1251',
		'se' => '',
		'si' => 'windows-1250',
		'sk' => 'windows-1250',
		'sl' => 'windows-1250',
		'sq' => 'utf-8',
		'sr' => 'utf-8',
		'sv' => '',
		'th' => 'iso-8859-11',
		'tr' => 'iso-8859-9',
		'ua' => 'windows-1251',
		'uk' => 'windows-1251',
		'vi' => 'utf-8',
		'vn' => 'utf-8',
		'zh' => 'big5'
	);

	/**
	 * Default constructor.
	 */
	public function __construct() {
		$this->locales = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Localization\Locales::class);
	}

	/**
	 * Normalize - changes input character set to lowercase letters.
	 *
	 * @param string $charset Input charset
	 * @return string Normalized charset
	 */
	public function parse_charset($charset) {
		$charset = trim(strtolower($charset));
		if (isset($this->synonyms[$charset])) {
			$charset = $this->synonyms[$charset];
		}
		return $charset;
	}

	/**
	 * Get the charset of a locale.
	 *
	 * ln			language
	 * ln_CN		 language / country
	 * ln_CN.cs	  language / country / charset
	 * ln_CN.cs@mod  language / country / charset / modifier
	 *
	 * @param string $locale Locale string
	 * @return string Charset resolved for locale string
	 */
	public function get_locale_charset($locale) {
		$locale = strtolower($locale);
		// Exact locale specific charset?
		if (isset($this->locale_to_charset[$locale])) {
			return $this->locale_to_charset[$locale];
		}
		// Get modifier
		list($locale, $modifier) = explode('@', $locale);
		// Locale contains charset: use it
		list($locale, $charset) = explode('.', $locale);
		if ($charset) {
			return $this->parse_charset($charset);
		}
		// Modifier is 'euro' (after charset check, because of xx.utf-8@euro)
		if ($modifier === 'euro') {
			return 'iso-8859-15';
		}
		// Get language
		list($language, $country) = explode('_', $locale);
		if (isset($this->lang_to_script[$language])) {
			$script = $this->lang_to_script[$language];
		}
		if (TYPO3_OS === 'WIN') {
			$cs = $this->script_to_charset_windows[$script] ?: 'windows-1252';
		} else {
			$cs = $this->script_to_charset_unix[$script] ?: 'utf-8';
		}
		return $cs;
	}

	/********************************************
	 *
	 * Charset Conversion functions
	 *
	 ********************************************/
	/**
	 * Convert from one charset to another charset.
	 *
	 * @param string $str Input string
	 * @param string $fromCS From charset (the current charset of the string)
	 * @param string $toCS To charset (the output charset wanted)
	 * @param bool $useEntityForNoChar If set, then characters that are not available in the destination character set will be encoded as numeric entities
	 * @return string Converted string
	 * @see convArray()
	 */
	public function conv($str, $fromCS, $toCS, $useEntityForNoChar = 0) {
		if ($fromCS == $toCS) {
			return $str;
		}
		// PHP-libs don't support fallback to SGML entities, but UTF-8 handles everything
		if ($toCS === 'utf-8' || !$useEntityForNoChar) {
			switch ($GLOBALS['TYPO3_CONF_VARS']['SYS']['t3lib_cs_convMethod']) {
				case 'mbstring':
					$conv_str = mb_convert_encoding($str, $toCS, $fromCS);
					if (FALSE !== $conv_str) {
						return $conv_str;
					}
					// Returns FALSE for unsupported charsets
					break;
				case 'iconv':
					$conv_str = iconv($fromCS, $toCS . '//TRANSLIT', $str);
					if (FALSE !== $conv_str) {
						return $conv_str;
					}
					break;
				case 'recode':
					$conv_str = recode_string($fromCS . '..' . $toCS, $str);
					if (FALSE !== $conv_str) {
						return $conv_str;
					}
					break;
			}
		}
		if ($fromCS !== 'utf-8') {
			$str = $this->utf8_encode($str, $fromCS);
		}
		if ($toCS !== 'utf-8') {
			$str = $this->utf8_decode($str, $toCS, $useEntityForNoChar);
		}
		return $str;
	}

	/**
	 * Convert all elements in ARRAY with type string from one charset to another charset.
	 * NOTICE: Array is passed by reference!
	 *
	 * @param string $array Input array, possibly multidimensional
	 * @param string $fromCS From charset (the current charset of the string)
	 * @param string $toCS To charset (the output charset wanted)
	 * @param bool $useEntityForNoChar If set, then characters that are not available in the destination character set will be encoded as numeric entities
	 * @return void
	 * @see conv()
	 */
	public function convArray(&$array, $fromCS, $toCS, $useEntityForNoChar = 0) {
		foreach ($array as $key => $value) {
			if (is_array($array[$key])) {
				$this->convArray($array[$key], $fromCS, $toCS, $useEntityForNoChar);
			} elseif (is_string($array[$key])) {
				$array[$key] = $this->conv($array[$key], $fromCS, $toCS, $useEntityForNoChar);
			}
		}
	}

	/**
	 * Converts $str from $charset to UTF-8
	 *
	 * @param string $str String in local charset to convert to UTF-8
	 * @param string $charset Charset, lowercase. Must be found in csconvtbl/ folder.
	 * @return string Output string, converted to UTF-8
	 */
	public function utf8_encode($str, $charset) {
		if ($charset === 'utf-8') {
			return $str;
		}
		// Charset is case-insensitive
		// Parse conv. table if not already
		if ($this->initCharset($charset)) {
			$strLen = strlen($str);
			$outStr = '';
			// Traverse each char in string
			for ($a = 0; $a < $strLen; $a++) {
				$chr = substr($str, $a, 1);
				$ord = ord($chr);
				// If the charset has two bytes per char
				if (isset($this->twoByteSets[$charset])) {
					$ord2 = ord($str[$a + 1]);
					// Assume big endian
					$ord = $ord << 8 | $ord2;
					// If the local char-number was found in parsed conv. table then we use that, otherwise 127 (no char?)
					if (isset($this->parsedCharsets[$charset]['local'][$ord])) {
						$outStr .= $this->parsedCharsets[$charset]['local'][$ord];
					} else {
						$outStr .= chr($this->noCharByteVal);
					}
					// No char exists
					$a++;
				} elseif ($ord > 127) {
					// If char has value over 127 it's a multibyte char in UTF-8
					// EUC uses two-bytes above 127; we get both and advance pointer and make $ord a 16bit int.
					if (isset($this->eucBasedSets[$charset])) {
						// Shift-JIS: chars between 160 and 223 are single byte
						if ($charset !== 'shift_jis' || ($ord < 160 || $ord > 223)) {
							$a++;
							$ord2 = ord(substr($str, $a, 1));
							$ord = $ord * 256 + $ord2;
						}
					}
					if (isset($this->parsedCharsets[$charset]['local'][$ord])) {
						// If the local char-number was found in parsed conv. table then we use that, otherwise 127 (no char?)
						$outStr .= $this->parsedCharsets[$charset]['local'][$ord];
					} else {
						$outStr .= chr($this->noCharByteVal);
					}
				} else {
					$outStr .= $chr;
				}
			}
			return $outStr;
		}
	}

	/**
	 * Converts $str from UTF-8 to $charset
	 *
	 * @param string $str String in UTF-8 to convert to local charset
	 * @param string $charset Charset, lowercase. Must be found in csconvtbl/ folder.
	 * @param bool $useEntityForNoChar If set, then characters that are not available in the destination character set will be encoded as numeric entities
	 * @return string Output string, converted to local charset
	 */
	public function utf8_decode($str, $charset, $useEntityForNoChar = 0) {
		if ($charset === 'utf-8') {
			return $str;
		}
		// Charset is case-insensitive.
		// Parse conv. table if not already
		if ($this->initCharset($charset)) {
			$strLen = strlen($str);
			$outStr = '';
			$buf = '';
			// Traverse each char in UTF-8 string
			for ($a = 0, $i = 0; $a < $strLen; $a++, $i++) {
				$chr = substr($str, $a, 1);
				$ord = ord($chr);
				// This means multibyte! (first byte!)
				if ($ord > 127) {
					// Since the first byte must have the 7th bit set we check that. Otherwise we might be in the middle of a byte sequence.
					if ($ord & 64) {
						// Add first byte
						$buf = $chr;
						// For each byte in multibyte string
						for ($b = 0; $b < 8; $b++) {
							// Shift it left and
							$ord = $ord << 1;
							// ... and with 8th bit - if that is set, then there are still bytes in sequence.
							if ($ord & 128) {
								$a++;
								// ... and add the next char.
								$buf .= substr($str, $a, 1);
							} else {
								break;
							}
						}
						// If the UTF-8 char-sequence is found then...
						if (isset($this->parsedCharsets[$charset]['utf8'][$buf])) {
							// The local number
							$mByte = $this->parsedCharsets[$charset]['utf8'][$buf];
							// If the local number is greater than 255 we will need to split the byte (16bit word assumed) in two chars.
							if ($mByte > 255) {
								$outStr .= chr(($mByte >> 8 & 255)) . chr(($mByte & 255));
							} else {
								$outStr .= chr($mByte);
							}
						} elseif ($useEntityForNoChar) {
							// Create num entity:
							$outStr .= '&#' . $this->utf8CharToUnumber($buf, 1) . ';';
						} else {
							$outStr .= chr($this->noCharByteVal);
						}
					} else {
						$outStr .= chr($this->noCharByteVal);
					}
				} else {
					$outStr .= $chr;
				}
			}
			return $outStr;
		}
	}

	/**
	 * Converts all chars > 127 to numeric entities.
	 *
	 * @param string $str Input string
	 * @return string Output string
	 */
	public function utf8_to_entities($str) {
		$strLen = strlen($str);
		$outStr = '';
		$buf = '';
		// Traverse each char in UTF-8 string.
		for ($a = 0; $a < $strLen; $a++) {
			$chr = substr($str, $a, 1);
			$ord = ord($chr);
			// This means multibyte! (first byte!)
			if ($ord > 127) {
				// Since the first byte must have the 7th bit set we check that. Otherwise we might be in the middle of a byte sequence.
				if ($ord & 64) {
					// Add first byte
					$buf = $chr;
					// For each byte in multibyte string...
					for ($b = 0; $b < 8; $b++) {
						// Shift it left and ...
						$ord = $ord << 1;
						// ... and with 8th bit - if that is set, then there are still bytes in sequence.
						if ($ord & 128) {
							$a++;
							// ... and add the next char.
							$buf .= substr($str, $a, 1);
						} else {
							break;
						}
					}
					$outStr .= '&#' . $this->utf8CharToUnumber($buf, 1) . ';';
				} else {
					$outStr .= chr($this->noCharByteVal);
				}
			} else {
				$outStr .= $chr;
			}
		}
		return $outStr;
	}

	/**
	 * Converts numeric entities (UNICODE, eg. decimal (&#1234;) or hexadecimal (&#x1b;)) to UTF-8 multibyte chars
	 *
	 * @param string $str Input string, UTF-8
	 * @param bool $alsoStdHtmlEnt If set, then all string-HTML entities (like &amp; or &pound; will be converted as well)
	 * @return string Output string
	 */
	public function entities_to_utf8($str, $alsoStdHtmlEnt = FALSE) {
		if ($alsoStdHtmlEnt) {
			$trans_tbl = array_flip(get_html_translation_table(HTML_ENTITIES, ENT_COMPAT, 'UTF-8'));
		}
		$token = md5(microtime());
		$parts = explode($token, preg_replace('/(&([#[:alnum:]]*);)/', $token . '${2}' . $token, $str));
		foreach ($parts as $k => $v) {
			// Only take every second element
			if ($k % 2 === 0) {
				continue;
			}
			$position = 0;
			// Dec or hex entities
			if (substr($v, $position, 1) === '#') {
				$position++;
				if (substr($v, $position, 1) === 'x') {
					$v = hexdec(substr($v, ++$position));
				} else {
					$v = substr($v, $position);
				}
				$parts[$k] = $this->UnumberToChar($v);
			} elseif ($alsoStdHtmlEnt && isset($trans_tbl['&' . $v . ';'])) {
				// Other entities:
				$v = $trans_tbl['&' . $v . ';'];
				$parts[$k] = $v;
			} else {
				// No conversion:
				$parts[$k] = '&' . $v . ';';
			}
		}
		return implode('', $parts);
	}

	/**
	 * Converts all chars in the input UTF-8 string into integer numbers returned in an array
	 *
	 * @param string $str Input string, UTF-8
	 * @param bool $convEntities If set, then all HTML entities (like &amp; or &pound; or &#123; or &#x3f5d;) will be detected as characters.
	 * @param bool $retChar If set, then instead of integer numbers the real UTF-8 char is returned.
	 * @return array Output array with the char numbers
	 */
	public function utf8_to_numberarray($str, $convEntities = 0, $retChar = 0) {
		// If entities must be registered as well...:
		if ($convEntities) {
			$str = $this->entities_to_utf8($str, 1);
		}
		// Do conversion:
		$strLen = strlen($str);
		$outArr = array();
		$buf = '';
		// Traverse each char in UTF-8 string.
		for ($a = 0; $a < $strLen; $a++) {
			$chr = substr($str, $a, 1);
			$ord = ord($chr);
			// This means multibyte! (first byte!)
			if ($ord > 127) {
				// Since the first byte must have the 7th bit set we check that. Otherwise we might be in the middle of a byte sequence.
				if ($ord & 64) {
					// Add first byte
					$buf = $chr;
					// For each byte in multibyte string...
					for ($b = 0; $b < 8; $b++) {
						// Shift it left and ...
						$ord = $ord << 1;
						// ... and with 8th bit - if that is set, then there are still bytes in sequence.
						if ($ord & 128) {
							$a++;
							// ... and add the next char.
							$buf .= substr($str, $a, 1);
						} else {
							break;
						}
					}
					$outArr[] = $retChar ? $buf : $this->utf8CharToUnumber($buf);
				} else {
					$outArr[] = $retChar ? chr($this->noCharByteVal) : $this->noCharByteVal;
				}
			} else {
				$outArr[] = $retChar ? chr($ord) : $ord;
			}
		}
		return $outArr;
	}

	/**
	 * Converts a UNICODE number to a UTF-8 multibyte character
	 * Algorithm based on script found at From: http://czyborra.com/utf/
	 * Unit-tested by Kasper
	 *
	 * The binary representation of the character's integer value is thus simply spread across the bytes
	 * and the number of high bits set in the lead byte announces the number of bytes in the multibyte sequence:
	 *
	 * bytes | bits | representation
	 * 1 |	7 | 0vvvvvvv
	 * 2 |   11 | 110vvvvv 10vvvvvv
	 * 3 |   16 | 1110vvvv 10vvvvvv 10vvvvvv
	 * 4 |   21 | 11110vvv 10vvvvvv 10vvvvvv 10vvvvvv
	 * 5 |   26 | 111110vv 10vvvvvv 10vvvvvv 10vvvvvv 10vvvvvv
	 * 6 |   31 | 1111110v 10vvvvvv 10vvvvvv 10vvvvvv 10vvvvvv 10vvvvvv
	 *
	 * @param int $cbyte UNICODE integer
	 * @return string UTF-8 multibyte character string
	 * @see utf8CharToUnumber()
	 */
	public function UnumberToChar($cbyte) {
		$str = '';
		if ($cbyte < 128) {
			$str .= chr($cbyte);
		} else {
			if ($cbyte < 2048) {
				$str .= chr(192 | $cbyte >> 6);
				$str .= chr(128 | $cbyte & 63);
			} else {
				if ($cbyte < 65536) {
					$str .= chr(224 | $cbyte >> 12);
					$str .= chr(128 | $cbyte >> 6 & 63);
					$str .= chr(128 | $cbyte & 63);
				} else {
					if ($cbyte < 2097152) {
						$str .= chr(240 | $cbyte >> 18);
						$str .= chr(128 | $cbyte >> 12 & 63);
						$str .= chr(128 | $cbyte >> 6 & 63);
						$str .= chr(128 | $cbyte & 63);
					} else {
						if ($cbyte < 67108864) {
							$str .= chr(248 | $cbyte >> 24);
							$str .= chr(128 | $cbyte >> 18 & 63);
							$str .= chr(128 | $cbyte >> 12 & 63);
							$str .= chr(128 | $cbyte >> 6 & 63);
							$str .= chr(128 | $cbyte & 63);
						} else {
							if ($cbyte < 2147483648) {
								$str .= chr(252 | $cbyte >> 30);
								$str .= chr(128 | $cbyte >> 24 & 63);
								$str .= chr(128 | $cbyte >> 18 & 63);
								$str .= chr(128 | $cbyte >> 12 & 63);
								$str .= chr(128 | $cbyte >> 6 & 63);
								$str .= chr(128 | $cbyte & 63);
							} else {
								// Cannot express a 32-bit character in UTF-8
								$str .= chr($this->noCharByteVal);
							}
						}
					}
				}
			}
		}
		return $str;
	}

	/**
	 * Converts a UTF-8 Multibyte character to a UNICODE number
	 * Unit-tested by Kasper
	 *
	 * @param string $str UTF-8 multibyte character string
	 * @param bool $hex If set, then a hex. number is returned.
	 * @return int UNICODE integer
	 * @see UnumberToChar()
	 */
	public function utf8CharToUnumber($str, $hex = 0) {
		// First char
		$ord = ord($str[0]);
		// This verifyes that it IS a multi byte string
		if (($ord & 192) == 192) {
			$binBuf = '';
			// For each byte in multibyte string...
			for ($b = 0; $b < 8; $b++) {
				// Shift it left and ...
				$ord = $ord << 1;
				// ... and with 8th bit - if that is set, then there are still bytes in sequence.
				if ($ord & 128) {
					$binBuf .= substr('00000000' . decbin(ord(substr($str, ($b + 1), 1))), -6);
				} else {
					break;
				}
			}
			$binBuf = substr(('00000000' . decbin(ord($str[0]))), -(6 - $b)) . $binBuf;
			$int = bindec($binBuf);
		} else {
			$int = $ord;
		}
		return $hex ? 'x' . dechex($int) : $int;
	}

	/********************************************
	 *
	 * Init functions
	 *
	 ********************************************/
	/**
	 * This will initialize a charset for use if it's defined in the 'typo3/sysext/core/Resources/Private/Charsets/csconvtbl/' folder
	 * This function is automatically called by the conversion functions
	 *
	 * PLEASE SEE: http://www.unicode.org/Public/MAPPINGS/
	 *
	 * @param string The charset to be initialized. Use lowercase charset always (the charset must match exactly with a filename in csconvtbl/ folder ([charset].tbl)
	 * @return int Returns '1' if already loaded. Returns FALSE if charset conversion table was not found. Returns '2' if the charset conversion table was found and parsed.
	 * @acces private
	 */
	public function initCharset($charset) {
		// Only process if the charset is not yet loaded:
		if (!is_array($this->parsedCharsets[$charset])) {
			// Conversion table filename:
			$charsetConvTableFile = ExtensionManagementUtility::extPath('core') . 'Resources/Private/Charsets/csconvtbl/' . $charset . '.tbl';
			// If the conversion table is found:
			if ($charset && GeneralUtility::validPathStr($charsetConvTableFile) && @is_file($charsetConvTableFile)) {
				// Cache file for charsets:
				// Caching brought parsing time for gb2312 down from 2400 ms to 150 ms. For other charsets we are talking 11 ms down to zero.
				$cacheFile = GeneralUtility::getFileAbsFileName('typo3temp/cs/charset_' . $charset . '.tbl');
				if ($cacheFile && @is_file($cacheFile)) {
					$this->parsedCharsets[$charset] = unserialize(GeneralUtility::getUrl($cacheFile));
				} else {
					// Parse conversion table into lines:
					$lines = GeneralUtility::trimExplode(LF, GeneralUtility::getUrl($charsetConvTableFile), TRUE);
					// Initialize the internal variable holding the conv. table:
					$this->parsedCharsets[$charset] = array('local' => array(), 'utf8' => array());
					// traverse the lines:
					$detectedType = '';
					foreach ($lines as $value) {
						// Comment line or blanks are ignored.
						if (trim($value) && $value[0] !== '#') {
							// Detect type if not done yet: (Done on first real line)
							// The "whitespaced" type is on the syntax 	"0x0A	0x000A	#LINE FEED" 	while 	"ms-token" is like 		"B9 = U+00B9 : SUPERSCRIPT ONE"
							if (!$detectedType) {
								$detectedType = preg_match('/[[:space:]]*0x([[:alnum:]]*)[[:space:]]+0x([[:alnum:]]*)[[:space:]]+/', $value) ? 'whitespaced' : 'ms-token';
							}
							if ($detectedType === 'ms-token') {
								list($hexbyte, $utf8) = preg_split('/[=:]/', $value, 3);
							} elseif ($detectedType === 'whitespaced') {
								$regA = array();
								preg_match('/[[:space:]]*0x([[:alnum:]]*)[[:space:]]+0x([[:alnum:]]*)[[:space:]]+/', $value, $regA);
								$hexbyte = $regA[1];
								$utf8 = 'U+' . $regA[2];
							}
							$decval = hexdec(trim($hexbyte));
							if ($decval > 127) {
								$utf8decval = hexdec(substr(trim($utf8), 2));
								$this->parsedCharsets[$charset]['local'][$decval] = $this->UnumberToChar($utf8decval);
								$this->parsedCharsets[$charset]['utf8'][$this->parsedCharsets[$charset]['local'][$decval]] = $decval;
							}
						}
					}
					if ($cacheFile) {
						GeneralUtility::writeFileToTypo3tempDir($cacheFile, serialize($this->parsedCharsets[$charset]));
					}
				}
				return 2;
			} else {
				return FALSE;
			}
		} else {
			return 1;
		}
	}

	/**
	 * This function initializes all UTF-8 character data tables.
	 *
	 * PLEASE SEE: http://www.unicode.org/Public/UNIDATA/
	 *
	 * @param string $mode Mode ("case", "ascii", ...)
	 * @return int Returns FALSE on error, a TRUE value on success: 1 table already loaded, 2, cached version, 3 table parsed (and cached).
	 * @access private
	 */
	public function initUnicodeData($mode = NULL) {
		// Cache files
		$cacheFileCase = GeneralUtility::getFileAbsFileName('typo3temp/cs/cscase_utf-8.tbl');
		$cacheFileASCII = GeneralUtility::getFileAbsFileName('typo3temp/cs/csascii_utf-8.tbl');
		// Only process if the tables are not yet loaded
		switch ($mode) {
			case 'case':
				if (is_array($this->caseFolding['utf-8'])) {
					return 1;
				}
				// Use cached version if possible
				if ($cacheFileCase && @is_file($cacheFileCase)) {
					$this->caseFolding['utf-8'] = unserialize(GeneralUtility::getUrl($cacheFileCase));
					return 2;
				}
				break;
			case 'ascii':
				if (is_array($this->toASCII['utf-8'])) {
					return 1;
				}
				// Use cached version if possible
				if ($cacheFileASCII && @is_file($cacheFileASCII)) {
					$this->toASCII['utf-8'] = unserialize(GeneralUtility::getUrl($cacheFileASCII));
					return 2;
				}
				break;
		}
		// Process main Unicode data file
		$unicodeDataFile = ExtensionManagementUtility::extPath('core') . 'Resources/Private/Charsets/unidata/UnicodeData.txt';
		if (!(GeneralUtility::validPathStr($unicodeDataFile) && @is_file($unicodeDataFile))) {
			return FALSE;
		}
		$fh = fopen($unicodeDataFile, 'rb');
		if (!$fh) {
			return FALSE;
		}
		// key = utf8 char (single codepoint), value = utf8 string (codepoint sequence)
		// Note: we use the UTF-8 characters here and not the Unicode numbers to avoid conversion roundtrip in utf8_strtolower/-upper)
		$this->caseFolding['utf-8'] = array();
		$utf8CaseFolding = &$this->caseFolding['utf-8'];
		// a shorthand
		$utf8CaseFolding['toUpper'] = array();
		$utf8CaseFolding['toLower'] = array();
		$utf8CaseFolding['toTitle'] = array();
		// Array of temp. decompositions
		$decomposition = array();
		// Array of chars that are marks (eg. composing accents)
		$mark = array();
		// Array of chars that are numbers (eg. digits)
		$number = array();
		// Array of chars to be omitted (eg. Russian hard sign)
		$omit = array();
		while (!feof($fh)) {
			$line = fgets($fh, 4096);
			// Has a lot of info
			list($char, $name, $cat, , , $decomp, , , $num, , , , $upper, $lower, $title, ) = explode(';', rtrim($line));
			$ord = hexdec($char);
			if ($ord > 65535) {
				// Only process the BMP
				break;
			}
			$utf8_char = $this->UnumberToChar($ord);
			if ($upper) {
				$utf8CaseFolding['toUpper'][$utf8_char] = $this->UnumberToChar(hexdec($upper));
			}
			if ($lower) {
				$utf8CaseFolding['toLower'][$utf8_char] = $this->UnumberToChar(hexdec($lower));
			}
			// Store "title" only when different from "upper" (only a few)
			if ($title && $title != $upper) {
				$utf8CaseFolding['toTitle'][$utf8_char] = $this->UnumberToChar(hexdec($title));
			}
			switch ($cat[0]) {
				case 'M':
					// mark (accent, umlaut, ...)
					$mark['U+' . $char] = 1;
					break;
				case 'N':
					// numeric value
					if ($ord > 128 && $num != '') {
						$number['U+' . $char] = $num;
					}
			}
			// Accented Latin letters without "official" decomposition
			$match = array();
			if (preg_match('/^LATIN (SMALL|CAPITAL) LETTER ([A-Z]) WITH/', $name, $match) && !$decomp) {
				$c = ord($match[2]);
				if ($match[1] === 'SMALL') {
					$c += 32;
				}
				$decomposition['U+' . $char] = array(dechex($c));
				continue;
			}
			$match = array();
			if (preg_match('/(<.*>)? *(.+)/', $decomp, $match)) {
				switch ($match[1]) {
					case '<circle>':
						// add parenthesis as circle replacement, eg (1)
						$match[2] = '0028 ' . $match[2] . ' 0029';
						break;
					case '<square>':
						// add square brackets as square replacement, eg [1]
						$match[2] = '005B ' . $match[2] . ' 005D';
						break;
					case '<compat>':
						// ignore multi char decompositions that start with a space
						if (preg_match('/^0020 /', $match[2])) {
							continue 2;
						}
						break;
					case '<initial>':
					case '<medial>':
					case '<final>':
					case '<isolated>':
					case '<vertical>':
						continue 2;
				}
				$decomposition['U+' . $char] = explode(' ', $match[2]);
			}
		}
		fclose($fh);
		// Process additional Unicode data for casing (allow folded characters to expand into a sequence)
		$specialCasingFile = ExtensionManagementUtility::extPath('core') . 'Resources/Private/Charsets/unidata/SpecialCasing.txt';
		if (GeneralUtility::validPathStr($specialCasingFile) && @is_file($specialCasingFile)) {
			$fh = fopen($specialCasingFile, 'rb');
			if ($fh) {
				while (!feof($fh)) {
					$line = fgets($fh, 4096);
					if ($line[0] !== '#' && trim($line) !== '') {
						list($char, $lower, $title, $upper, $cond) = GeneralUtility::trimExplode(';', $line);
						if ($cond === '' || $cond[0] === '#') {
							$utf8_char = $this->UnumberToChar(hexdec($char));
							if ($char !== $lower) {
								$arr = explode(' ', $lower);
								for ($i = 0; isset($arr[$i]); $i++) {
									$arr[$i] = $this->UnumberToChar(hexdec($arr[$i]));
								}
								$utf8CaseFolding['toLower'][$utf8_char] = implode('', $arr);
							}
							if ($char !== $title && $title !== $upper) {
								$arr = explode(' ', $title);
								for ($i = 0; isset($arr[$i]); $i++) {
									$arr[$i] = $this->UnumberToChar(hexdec($arr[$i]));
								}
								$utf8CaseFolding['toTitle'][$utf8_char] = implode('', $arr);
							}
							if ($char !== $upper) {
								$arr = explode(' ', $upper);
								for ($i = 0; isset($arr[$i]); $i++) {
									$arr[$i] = $this->UnumberToChar(hexdec($arr[$i]));
								}
								$utf8CaseFolding['toUpper'][$utf8_char] = implode('', $arr);
							}
						}
					}
				}
				fclose($fh);
			}
		}
		// Process custom decompositions
		$customTranslitFile = ExtensionManagementUtility::extPath('core') . 'Resources/Private/Charsets/unidata/Translit.txt';
		if (GeneralUtility::validPathStr($customTranslitFile) && @is_file($customTranslitFile)) {
			$fh = fopen($customTranslitFile, 'rb');
			if ($fh) {
				while (!feof($fh)) {
					$line = fgets($fh, 4096);
					if ($line[0] !== '#' && trim($line) !== '') {
						list($char, $translit) = GeneralUtility::trimExplode(';', $line);
						if (!$translit) {
							$omit['U+' . $char] = 1;
						}
						$decomposition['U+' . $char] = explode(' ', $translit);
					}
				}
				fclose($fh);
			}
		}
		// Decompose and remove marks; inspired by unac (Loic Dachary <loic@senga.org>)
		foreach ($decomposition as $from => $to) {
			$code_decomp = array();
			while ($code_value = array_shift($to)) {
				// Do recursive decomposition
				if (isset($decomposition['U+' . $code_value])) {
					foreach (array_reverse($decomposition['U+' . $code_value]) as $cv) {
						array_unshift($to, $cv);
					}
				} elseif (!isset($mark[('U+' . $code_value)])) {
					// remove mark
					array_push($code_decomp, $code_value);
				}
			}
			if (count($code_decomp) || isset($omit[$from])) {
				$decomposition[$from] = $code_decomp;
			} else {
				unset($decomposition[$from]);
			}
		}
		// Create ascii only mapping
		$this->toASCII['utf-8'] = array();
		$ascii = &$this->toASCII['utf-8'];
		foreach ($decomposition as $from => $to) {
			$code_decomp = array();
			while ($code_value = array_shift($to)) {
				$ord = hexdec($code_value);
				if ($ord > 127) {
					continue 2;
				} else {
					// Skip decompositions containing non-ASCII chars
					array_push($code_decomp, chr($ord));
				}
			}
			$ascii[$this->UnumberToChar(hexdec($from))] = join('', $code_decomp);
		}
		// Add numeric decompositions
		foreach ($number as $from => $to) {
			$utf8_char = $this->UnumberToChar(hexdec($from));
			if (!isset($ascii[$utf8_char])) {
				$ascii[$utf8_char] = $to;
			}
		}
		if ($cacheFileCase) {
			GeneralUtility::writeFileToTypo3tempDir($cacheFileCase, serialize($utf8CaseFolding));
		}
		if ($cacheFileASCII) {
			GeneralUtility::writeFileToTypo3tempDir($cacheFileASCII, serialize($ascii));
		}
		return 3;
	}

	/**
	 * This function initializes the folding table for a charset other than UTF-8.
	 * This function is automatically called by the case folding functions.
	 *
	 * @param string $charset Charset for which to initialize case folding.
	 * @return int Returns FALSE on error, a TRUE value on success: 1 table already loaded, 2, cached version, 3 table parsed (and cached).
	 * @access private
	 */
	public function initCaseFolding($charset) {
		// Only process if the case table is not yet loaded:
		if (is_array($this->caseFolding[$charset])) {
			return 1;
		}
		// Use cached version if possible
		$cacheFile = GeneralUtility::getFileAbsFileName('typo3temp/cs/cscase_' . $charset . '.tbl');
		if ($cacheFile && @is_file($cacheFile)) {
			$this->caseFolding[$charset] = unserialize(GeneralUtility::getUrl($cacheFile));
			return 2;
		}
		// init UTF-8 conversion for this charset
		if (!$this->initCharset($charset)) {
			return FALSE;
		}
		// UTF-8 case folding is used as the base conversion table
		if (!$this->initUnicodeData('case')) {
			return FALSE;
		}
		$nochar = chr($this->noCharByteVal);
		foreach ($this->parsedCharsets[$charset]['local'] as $ci => $utf8) {
			// Reconvert to charset (don't use chr() of numeric value, might be muli-byte)
			$c = $this->utf8_decode($utf8, $charset);
			$cc = $this->utf8_decode($this->caseFolding['utf-8']['toUpper'][$utf8], $charset);
			if ($cc !== '' && $cc !== $nochar) {
				$this->caseFolding[$charset]['toUpper'][$c] = $cc;
			}
			$cc = $this->utf8_decode($this->caseFolding['utf-8']['toLower'][$utf8], $charset);
			if ($cc !== '' && $cc !== $nochar) {
				$this->caseFolding[$charset]['toLower'][$c] = $cc;
			}
			$cc = $this->utf8_decode($this->caseFolding['utf-8']['toTitle'][$utf8], $charset);
			if ($cc !== '' && $cc !== $nochar) {
				$this->caseFolding[$charset]['toTitle'][$c] = $cc;
			}
		}
		// Add the ASCII case table
		$start = ord('a');
		$end = ord('z');
		for ($i = $start; $i <= $end; $i++) {
			$this->caseFolding[$charset]['toUpper'][chr($i)] = chr($i - 32);
		}
		$start = ord('A');
		$end = ord('Z');
		for ($i = $start; $i <= $end; $i++) {
			$this->caseFolding[$charset]['toLower'][chr($i)] = chr($i + 32);
		}
		if ($cacheFile) {
			GeneralUtility::writeFileToTypo3tempDir($cacheFile, serialize($this->caseFolding[$charset]));
		}
		return 3;
	}

	/**
	 * This function initializes the to-ASCII conversion table for a charset other than UTF-8.
	 * This function is automatically called by the ASCII transliteration functions.
	 *
	 * @param string $charset Charset for which to initialize conversion.
	 * @return int Returns FALSE on error, a TRUE value on success: 1 table already loaded, 2, cached version, 3 table parsed (and cached).
	 * @access private
	 */
	public function initToASCII($charset) {
		// Only process if the case table is not yet loaded:
		if (is_array($this->toASCII[$charset])) {
			return 1;
		}
		// Use cached version if possible
		$cacheFile = GeneralUtility::getFileAbsFileName('typo3temp/cs/csascii_' . $charset . '.tbl');
		if ($cacheFile && @is_file($cacheFile)) {
			$this->toASCII[$charset] = unserialize(GeneralUtility::getUrl($cacheFile));
			return 2;
		}
		// Init UTF-8 conversion for this charset
		if (!$this->initCharset($charset)) {
			return FALSE;
		}
		// UTF-8/ASCII transliteration is used as the base conversion table
		if (!$this->initUnicodeData('ascii')) {
			return FALSE;
		}
		$nochar = chr($this->noCharByteVal);
		foreach ($this->parsedCharsets[$charset]['local'] as $ci => $utf8) {
			// Reconvert to charset (don't use chr() of numeric value, might be muli-byte)
			$c = $this->utf8_decode($utf8, $charset);
			if (isset($this->toASCII['utf-8'][$utf8])) {
				$this->toASCII[$charset][$c] = $this->toASCII['utf-8'][$utf8];
			}
		}
		if ($cacheFile) {
			GeneralUtility::writeFileToTypo3tempDir($cacheFile, serialize($this->toASCII[$charset]));
		}
		return 3;
	}

	/********************************************
	 *
	 * String operation functions
	 *
	 ********************************************/
	/**
	 * Returns a part of a string.
	 * Unit-tested by Kasper (single byte charsets only)
	 *
	 * @param string $charset The character set
	 * @param string $string Character string
	 * @param int $start Start position (character position)
	 * @param int $len Length (in characters)
	 * @return string The substring
	 * @see substr(), mb_substr()
	 */
	public function substr($charset, $string, $start, $len = NULL) {
		if ($len === 0 || $string === '') {
			return '';
		}
		if ($GLOBALS['TYPO3_CONF_VARS']['SYS']['t3lib_cs_utils'] === 'mbstring') {
			// Cannot omit $len, when specifying charset
			if ($len === NULL) {
				// Save internal encoding
				$enc = mb_internal_encoding();
				mb_internal_encoding($charset);
				$str = mb_substr($string, $start);
				// Restore internal encoding
				mb_internal_encoding($enc);
				return $str;
			} else {
				return mb_substr($string, $start, $len, $charset);
			}
		} elseif ($GLOBALS['TYPO3_CONF_VARS']['SYS']['t3lib_cs_utils'] === 'iconv') {
			// Cannot omit $len, when specifying charset
			if ($len === NULL) {
				// Save internal encoding
				$enc = iconv_get_encoding('internal_encoding');
				iconv_set_encoding('internal_encoding', $charset);
				$str = iconv_substr($string, $start);
				// Restore internal encoding
				iconv_set_encoding('internal_encoding', $enc);
				return $str;
			} else {
				return iconv_substr($string, $start, $len, $charset);
			}
		} elseif ($charset === 'utf-8') {
			return $this->utf8_substr($string, $start, $len);
		} elseif ($this->eucBasedSets[$charset]) {
			return $this->euc_substr($string, $start, $charset, $len);
		} elseif ($this->twoByteSets[$charset]) {
			return substr($string, $start * 2, $len * 2);
		} elseif ($this->fourByteSets[$charset]) {
			return substr($string, $start * 4, $len * 4);
		}
		// Treat everything else as single-byte encoding
		return $len === NULL ? substr($string, $start) : substr($string, $start, $len);
	}

	/**
	 * Counts the number of characters.
	 * Unit-tested by Kasper (single byte charsets only)
	 *
	 * @param string $charset The character set
	 * @param string $string Character string
	 * @return int The number of characters
	 * @see strlen()
	 */
	public function strlen($charset, $string) {
		if ($GLOBALS['TYPO3_CONF_VARS']['SYS']['t3lib_cs_utils'] === 'mbstring') {
			return mb_strlen($string, $charset);
		} elseif ($GLOBALS['TYPO3_CONF_VARS']['SYS']['t3lib_cs_utils'] === 'iconv') {
			return iconv_strlen($string, $charset);
		} elseif ($charset == 'utf-8') {
			return $this->utf8_strlen($string);
		} elseif ($this->eucBasedSets[$charset]) {
			return $this->euc_strlen($string, $charset);
		} elseif ($this->twoByteSets[$charset]) {
			return strlen($string) / 2;
		} elseif ($this->fourByteSets[$charset]) {
			return strlen($string) / 4;
		}
		// Treat everything else as single-byte encoding
		return strlen($string);
	}

	/**
	 * Method to crop strings using the mb_substr function.
	 *
	 * @param string $charset The character set
	 * @param string $string String to be cropped
	 * @param int $len Crop length (in characters)
	 * @param string $crop Crop signifier
	 * @return string The shortened string
	 * @see mb_strlen(), mb_substr()
	 */
	protected function cropMbstring($charset, $string, $len, $crop = '') {
		if ((int)$len === 0 || mb_strlen($string, $charset) <= abs($len)) {
			return $string;
		}
		if ($len > 0) {
			$string = mb_substr($string, 0, $len, $charset) . $crop;
		} else {
			$string = $crop . mb_substr($string, $len, mb_strlen($string, $charset), $charset);
		}
		return $string;
	}

	/**
	 * Truncates a string and pre-/appends a string.
	 * Unit tested by Kasper
	 *
	 * @param string $charset The character set
	 * @param string $string Character string
	 * @param int $len Length (in characters)
	 * @param string $crop Crop signifier
	 * @return string The shortened string
	 * @see substr(), mb_strimwidth()
	 */
	public function crop($charset, $string, $len, $crop = '') {
		if ($GLOBALS['TYPO3_CONF_VARS']['SYS']['t3lib_cs_utils'] === 'mbstring') {
			return $this->cropMbstring($charset, $string, $len, $crop);
		}
		if ((int)$len === 0) {
			return $string;
		}
		if ($charset == 'utf-8') {
			$i = $this->utf8_char2byte_pos($string, $len);
		} elseif ($this->eucBasedSets[$charset]) {
			$i = $this->euc_char2byte_pos($string, $len, $charset);
		} else {
			if ($len > 0) {
				$i = $len;
			} else {
				$i = strlen($string) + $len;
				if ($i <= 0) {
					$i = FALSE;
				}
			}
		}
		// $len outside actual string length
		if ($i === FALSE) {
			return $string;
		} else {
			if ($len > 0) {
				if (isset($string[$i])) {
					return substr($string, 0, $i) . $crop;
				}
			} else {
				if (isset($string[$i - 1])) {
					return $crop . substr($string, $i);
				}
			}
		}
		return $string;
	}

	/**
	 * Cuts a string short at a given byte length.
	 *
	 * @param string $charset The character set
	 * @param string $string Character string
	 * @param int $len The byte length
	 * @return string The shortened string
	 * @see mb_strcut()
	 */
	public function strtrunc($charset, $string, $len) {
		if ($len <= 0) {
			return '';
		}
		if ($GLOBALS['TYPO3_CONF_VARS']['SYS']['t3lib_cs_utils'] === 'mbstring') {
			return mb_strcut($string, 0, $len, $charset);
		} elseif ($charset == 'utf-8') {
			return $this->utf8_strtrunc($string, $len);
		} elseif ($this->eucBasedSets[$charset]) {
			return $this->euc_strtrunc($string, $len, $charset);
		} elseif ($this->twoByteSets[$charset]) {
			if ($len % 2) {
				$len--;
			}
		} elseif ($this->fourByteSets[$charset]) {
			$x = $len % 4;
			// Realign to position dividable by four
			$len -= $x;
		}
		// Treat everything else as single-byte encoding
		return substr($string, 0, $len);
	}

	/**
	 * Translates all characters of a string into their respective case values.
	 * Unlike strtolower() and strtoupper() this method is locale independent.
	 * Note that the string length may change!
	 * eg. lower case German "ß" (sharp S) becomes upper case "SS"
	 * Unit-tested by Kasper
	 * Real case folding is language dependent, this method ignores this fact.
	 *
	 * @param string $charset Character set of string
	 * @param string $string Input string to convert case for
	 * @param string $case Case keyword: "toLower" means lowercase conversion, anything else is uppercase (use "toUpper" )
	 * @return string The converted string
	 * @see strtolower(), strtoupper()
	 */
	public function conv_case($charset, $string, $case) {
		if ($GLOBALS['TYPO3_CONF_VARS']['SYS']['t3lib_cs_utils'] === 'mbstring') {
			if ($case === 'toLower') {
				$string = mb_strtolower($string, $charset);
			} else {
				$string = mb_strtoupper($string, $charset);
			}
		} elseif ($charset === 'utf-8') {
			$string = $this->utf8_char_mapping($string, 'case', $case);
		} elseif (isset($this->eucBasedSets[$charset])) {
			$string = $this->euc_char_mapping($string, $charset, 'case', $case);
		} else {
			// Treat everything else as single-byte encoding
			$string = $this->sb_char_mapping($string, $charset, 'case', $case);
		}
		return $string;
	}

	/**
	 * Equivalent of lcfirst/ucfirst but using character set.
	 *
	 * @param string $charset
	 * @param string $string
	 * @param string $case
	 * @return string
	 * @see \TYPO3\CMS\Core\Charset\CharsetConverter::conv_case()
	 */
	public function convCaseFirst($charset, $string, $case) {
		$firstChar = $this->substr($charset, $string, 0, 1);
		$firstChar = $this->conv_case($charset, $firstChar, $case);
		$remainder = $this->substr($charset, $string, 1);
		return $firstChar . $remainder;
	}

	/**
	 * Converts special chars (like æøåÆØÅ, umlauts etc) to ascii equivalents (usually double-bytes, like æ => ae etc.)
	 *
	 * @param string $charset Character set of string
	 * @param string $string Input string to convert
	 * @return string The converted string
	 */
	public function specCharsToASCII($charset, $string) {
		if ($charset === 'utf-8') {
			$string = $this->utf8_char_mapping($string, 'ascii');
		} elseif (isset($this->eucBasedSets[$charset])) {
			$string = $this->euc_char_mapping($string, $charset, 'ascii');
		} else {
			// Treat everything else as single-byte encoding
			$string = $this->sb_char_mapping($string, $charset, 'ascii');
		}
		return $string;
	}

	/**
	 * Converts the language codes that we get from the client (usually HTTP_ACCEPT_LANGUAGE)
	 * into a TYPO3-readable language code
	 *
	 * @param string $languageCodesList List of language codes. something like 'de,en-us;q=0.9,de-de;q=0.7,es-cl;q=0.6,en;q=0.4,es;q=0.3,zh;q=0.1'
	 * @return string A preferred language that TYPO3 supports, or "default" if none found
	 */
	public function getPreferredClientLanguage($languageCodesList) {
		$allLanguageCodes = array();
		$selectedLanguage = 'default';
		// Get all languages where TYPO3 code is the same as the ISO code
		foreach ($this->charSetArray as $typo3Lang => $charSet) {
			$allLanguageCodes[$typo3Lang] = $typo3Lang;
		}
		// Get all languages where TYPO3 code differs from ISO code
		// or needs the country part
		// the iso codes will here overwrite the default typo3 language in the key
		foreach ($this->locales->getIsoMapping() as $typo3Lang => $isoLang) {
			$isoLang = join('-', explode('_', $isoLang));
			$allLanguageCodes[$typo3Lang] = $isoLang;
		}
		// Move the iso codes to the (because we're comparing the keys with "isset" later on)
		$allLanguageCodes = array_flip($allLanguageCodes);
		$preferredLanguages = GeneralUtility::trimExplode(',', $languageCodesList);
		// Order the preferred languages after they key
		$sortedPreferredLanguages = array();
		foreach ($preferredLanguages as $preferredLanguage) {
			$quality = 1.0;
			if (strpos($preferredLanguage, ';q=') !== FALSE) {
				list($preferredLanguage, $quality) = explode(';q=', $preferredLanguage);
			}
			$sortedPreferredLanguages[$preferredLanguage] = $quality;
		}
		// Loop through the languages, with the highest priority first
		arsort($sortedPreferredLanguages, SORT_NUMERIC);
		foreach ($sortedPreferredLanguages as $preferredLanguage => $quality) {
			if (isset($allLanguageCodes[$preferredLanguage])) {
				$selectedLanguage = $allLanguageCodes[$preferredLanguage];
				break;
			}
			// Strip the country code from the end
			list($preferredLanguage, $preferredCountry) = explode('-', $preferredLanguage);
			if (isset($allLanguageCodes[$preferredLanguage])) {
				$selectedLanguage = $allLanguageCodes[$preferredLanguage];
				break;
			}
		}
		if (!$selectedLanguage || $selectedLanguage === 'en') {
			$selectedLanguage = 'default';
		}
		return $selectedLanguage;
	}

	/********************************************
	 *
	 * Internal string operation functions
	 *
	 ********************************************/
	/**
	 * Maps all characters of a string in a single byte charset.
	 *
	 * @param string $str The string
	 * @param string $charset The charset
	 * @param string $mode Mode: 'case' (case folding) or 'ascii' (ASCII transliteration)
	 * @param string $opt 'case': conversion 'toLower' or 'toUpper'
	 * @return string The converted string
	 */
	public function sb_char_mapping($str, $charset, $mode, $opt = '') {
		switch ($mode) {
			case 'case':
				if (!$this->initCaseFolding($charset)) {
					return $str;
				}
				// Do nothing
				$map = &$this->caseFolding[$charset][$opt];
				break;
			case 'ascii':
				if (!$this->initToASCII($charset)) {
					return $str;
				}
				// Do nothing
				$map = &$this->toASCII[$charset];
				break;
			default:
				return $str;
		}
		$out = '';
		for ($i = 0; isset($str[$i]); $i++) {
			$c = $str[$i];
			if (isset($map[$c])) {
				$out .= $map[$c];
			} else {
				$out .= $c;
			}
		}
		return $out;
	}

	/********************************************
	 *
	 * Internal UTF-8 string operation functions
	 *
	 ********************************************/
	/**
	 * Returns a part of a UTF-8 string.
	 * Unit-tested by Kasper and works 100% like substr() / mb_substr() for full range of $start/$len
	 *
	 * @param string $str UTF-8 string
	 * @param int $start Start position (character position)
	 * @param int $len Length (in characters)
	 * @return string The substring
	 * @see substr()
	 */
	public function utf8_substr($str, $start, $len = NULL) {
		if ((string)$len === '0') {
			return '';
		}
		$byte_start = $this->utf8_char2byte_pos($str, $start);
		if ($byte_start === FALSE) {
			if ($start > 0) {
				// $start outside string length
				return FALSE;
			} else {
				$start = 0;
			}
		}
		$str = substr($str, $byte_start);
		if ($len != NULL) {
			$byte_end = $this->utf8_char2byte_pos($str, $len);
			// $len outside actual string length
			if ($byte_end === FALSE) {
				return $len < 0 ? '' : $str;
			} else {
				// When length is less than zero and exceeds, then we return blank string.
				return substr($str, 0, $byte_end);
			}
		} else {
			return $str;
		}
	}

	/**
	 * Counts the number of characters of a string in UTF-8.
	 * Unit-tested by Kasper and works 100% like strlen() / mb_strlen()
	 *
	 * @param string $str UTF-8 multibyte character string
	 * @return int The number of characters
	 * @see strlen()
	 */
	public function utf8_strlen($str) {
		$n = 0;
		for ($i = 0; isset($str[$i]); $i++) {
			$c = ord($str[$i]);
			// Single-byte (0xxxxxx)
			if (!($c & 128)) {
				$n++;
			} elseif (($c & 192) == 192) {
				// Multi-byte starting byte (11xxxxxx)
				$n++;
			}
		}
		return $n;
	}

	/**
	 * Truncates a string in UTF-8 short at a given byte length.
	 *
	 * @param string $str UTF-8 multibyte character string
	 * @param int $len The byte length
	 * @return string The shortened string
	 * @see mb_strcut()
	 */
	public function utf8_strtrunc($str, $len) {
		$i = $len - 1;
		// Part of a multibyte sequence
		if (ord($str[$i]) & 128) {
			for (; $i > 0 && !(ord($str[$i]) & 64); $i--) {

			}
			if ($i <= 0) {
				return '';
			}
			// Sanity check
			for ($bc = 0, $mbs = ord($str[$i]); $mbs & 128; $mbs = $mbs << 1) {
				// Calculate number of bytes
				$bc++;
			}
			if ($bc + $i > $len) {
				return substr($str, 0, $i);
			}
		}
		return substr($str, 0, $len);
	}

	/**
	 * Find position of first occurrence of a string, both arguments are in UTF-8.
	 *
	 * @param string $haystack UTF-8 string to search in
	 * @param string $needle UTF-8 string to search for
	 * @param int $offset Positition to start the search
	 * @return int The character position
	 * @see strpos()
	 */
	public function utf8_strpos($haystack, $needle, $offset = 0) {
		if ($GLOBALS['TYPO3_CONF_VARS']['SYS']['t3lib_cs_utils'] === 'mbstring') {
			return mb_strpos($haystack, $needle, $offset, 'utf-8');
		} elseif ($GLOBALS['TYPO3_CONF_VARS']['SYS']['t3lib_cs_utils'] === 'iconv') {
			return iconv_strpos($haystack, $needle, $offset, 'utf-8');
		}
		$byte_offset = $this->utf8_char2byte_pos($haystack, $offset);
		if ($byte_offset === FALSE) {
			// Offset beyond string length
			return FALSE;
		}
		$byte_pos = strpos($haystack, $needle, $byte_offset);
		if ($byte_pos === FALSE) {
			// Needle not found
			return FALSE;
		}
		return $this->utf8_byte2char_pos($haystack, $byte_pos);
	}

	/**
	 * Find position of last occurrence of a char in a string, both arguments are in UTF-8.
	 *
	 * @param string $haystack UTF-8 string to search in
	 * @param string $needle UTF-8 character to search for (single character)
	 * @return int The character position
	 * @see strrpos()
	 */
	public function utf8_strrpos($haystack, $needle) {
		if ($GLOBALS['TYPO3_CONF_VARS']['SYS']['t3lib_cs_utils'] === 'mbstring') {
			return mb_strrpos($haystack, $needle, 'utf-8');
		} elseif ($GLOBALS['TYPO3_CONF_VARS']['SYS']['t3lib_cs_utils'] === 'iconv') {
			return iconv_strrpos($haystack, $needle, 'utf-8');
		}
		$byte_pos = strrpos($haystack, $needle);
		if ($byte_pos === FALSE) {
			// Needle not found
			return FALSE;
		}
		return $this->utf8_byte2char_pos($haystack, $byte_pos);
	}

	/**
	 * Translates a character position into an 'absolute' byte position.
	 * Unit tested by Kasper.
	 *
	 * @param string $str UTF-8 string
	 * @param int $pos Character position (negative values start from the end)
	 * @return int Byte position
	 */
	public function utf8_char2byte_pos($str, $pos) {
		// Number of characters found
		$n = 0;
		// Number of characters wanted
		$p = abs($pos);
		if ($pos >= 0) {
			$i = 0;
			$d = 1;
		} else {
			$i = strlen($str) - 1;
			$d = -1;
		}
		for (; isset($str[$i]) && $n < $p; $i += $d) {
			$c = (int)ord($str[$i]);
			// single-byte (0xxxxxx)
			if (!($c & 128)) {
				$n++;
			} elseif (($c & 192) == 192) {
				// Multi-byte starting byte (11xxxxxx)
				$n++;
			}
		}
		if (!isset($str[$i])) {
			// Offset beyond string length
			return FALSE;
		}
		if ($pos >= 0) {
			// Skip trailing multi-byte data bytes
			while (ord($str[$i]) & 128 && !(ord($str[$i]) & 64)) {
				$i++;
			}
		} else {
			// Correct offset
			$i++;
		}
		return $i;
	}

	/**
	 * Translates an 'absolute' byte position into a character position.
	 * Unit tested by Kasper.
	 *
	 * @param string $str UTF-8 string
	 * @param int $pos Byte position
	 * @return int Character position
	 */
	public function utf8_byte2char_pos($str, $pos) {
		// Number of characters
		$n = 0;
		for ($i = $pos; $i > 0; $i--) {
			$c = (int)ord($str[$i]);
			// single-byte (0xxxxxx)
			if (!($c & 128)) {
				$n++;
			} elseif (($c & 192) == 192) {
				// Multi-byte starting byte (11xxxxxx)
				$n++;
			}
		}
		if (!isset($str[$i])) {
			// Offset beyond string length
			return FALSE;
		}
		return $n;
	}

	/**
	 * Maps all characters of an UTF-8 string.
	 *
	 * @param string $str UTF-8 string
	 * @param string $mode Mode: 'case' (case folding) or 'ascii' (ASCII transliteration)
	 * @param string $opt 'case': conversion 'toLower' or 'toUpper'
	 * @return string The converted string
	 */
	public function utf8_char_mapping($str, $mode, $opt = '') {
		if (!$this->initUnicodeData($mode)) {
			// Do nothing
			return $str;
		}
		$out = '';
		switch ($mode) {
			case 'case':
				$map = &$this->caseFolding['utf-8'][$opt];
				break;
			case 'ascii':
				$map = &$this->toASCII['utf-8'];
				break;
			default:
				return $str;
		}
		for ($i = 0; isset($str[$i]); $i++) {
			$c = ord($str[$i]);
			// single-byte (0xxxxxx)
			if (!($c & 128)) {
				$mbc = $str[$i];
			} elseif (($c & 192) == 192) {
				// multi-byte starting byte (11xxxxxx)
				for ($bc = 0; $c & 128; $c = $c << 1) {
					$bc++;
				}
				// calculate number of bytes
				$mbc = substr($str, $i, $bc);
				$i += $bc - 1;
			}
			if (isset($map[$mbc])) {
				$out .= $map[$mbc];
			} else {
				$out .= $mbc;
			}
		}
		return $out;
	}

	/********************************************
	 *
	 * Internal EUC string operation functions
	 *
	 * Extended Unix Code:
	 *  ASCII compatible 7bit single bytes chars
	 *  8bit two byte chars
	 *
	 * Shift-JIS is treated as a special case.
	 *
	 ********************************************/
	/**
	 * Cuts a string in the EUC charset family short at a given byte length.
	 *
	 * @param string $str EUC multibyte character string
	 * @param int $len The byte length
	 * @param string $charset The charset
	 * @return string The shortened string
	 * @see mb_strcut()
	 */
	public function euc_strtrunc($str, $len, $charset) {
		$sjis = $charset === 'shift_jis';
		for ($i = 0; isset($str[$i]) && $i < $len; $i++) {
			$c = ord($str[$i]);
			if ($sjis) {
				if ($c >= 128 && $c < 160 || $c >= 224) {
					$i++;
				}
			} else {
				if ($c >= 128) {
					$i++;
				}
			}
		}
		if (!isset($str[$i])) {
			return $str;
		}
		// string shorter than supplied length
		if ($i > $len) {
			// We ended on a first byte
			return substr($str, 0, $len - 1);
		} else {
			return substr($str, 0, $len);
		}
	}

	/**
	 * Returns a part of a string in the EUC charset family.
	 *
	 * @param string $str EUC multibyte character string
	 * @param int $start Start position (character position)
	 * @param string $charset The charset
	 * @param int $len Length (in characters)
	 * @return string the substring
	 */
	public function euc_substr($str, $start, $charset, $len = NULL) {
		$byte_start = $this->euc_char2byte_pos($str, $start, $charset);
		if ($byte_start === FALSE) {
			// $start outside string length
			return FALSE;
		}
		$str = substr($str, $byte_start);
		if ($len != NULL) {
			$byte_end = $this->euc_char2byte_pos($str, $len, $charset);
			// $len outside actual string length
			if ($byte_end === FALSE) {
				return $str;
			} else {
				return substr($str, 0, $byte_end);
			}
		} else {
			return $str;
		}
	}

	/**
	 * Counts the number of characters of a string in the EUC charset family.
	 *
	 * @param string $str EUC multibyte character string
	 * @param string $charset The charset
	 * @return int The number of characters
	 * @see strlen()
	 */
	public function euc_strlen($str, $charset) {
		$sjis = $charset === 'shift_jis';
		$n = 0;
		for ($i = 0; isset($str[$i]); $i++) {
			$c = ord($str[$i]);
			if ($sjis) {
				if ($c >= 128 && $c < 160 || $c >= 224) {
					$i++;
				}
			} else {
				if ($c >= 128) {
					$i++;
				}
			}
			$n++;
		}
		return $n;
	}

	/**
	 * Translates a character position into an 'absolute' byte position.
	 *
	 * @param string $str EUC multibyte character string
	 * @param int $pos Character position (negative values start from the end)
	 * @param string $charset The charset
	 * @return int Byte position
	 */
	public function euc_char2byte_pos($str, $pos, $charset) {
		$sjis = $charset === 'shift_jis';
		// Number of characters seen
		$n = 0;
		// Number of characters wanted
		$p = abs($pos);
		if ($pos >= 0) {
			$i = 0;
			$d = 1;
		} else {
			$i = strlen($str) - 1;
			$d = -1;
		}
		for (; isset($str[$i]) && $n < $p; $i += $d) {
			$c = ord($str[$i]);
			if ($sjis) {
				if ($c >= 128 && $c < 160 || $c >= 224) {
					$i += $d;
				}
			} else {
				if ($c >= 128) {
					$i += $d;
				}
			}
			$n++;
		}
		if (!isset($str[$i])) {
			return FALSE;
		}
		// offset beyond string length
		if ($pos < 0) {
			$i++;
		}
		// correct offset
		return $i;
	}

	/**
	 * Maps all characters of a string in the EUC charset family.
	 *
	 * @param string $str EUC multibyte character string
	 * @param string $charset The charset
	 * @param string $mode Mode: 'case' (case folding) or 'ascii' (ASCII transliteration)
	 * @param string $opt 'case': conversion 'toLower' or 'toUpper'
	 * @return string The converted string
	 */
	public function euc_char_mapping($str, $charset, $mode, $opt = '') {
		switch ($mode) {
			case 'case':
				if (!$this->initCaseFolding($charset)) {
					return $str;
				}
				// do nothing
				$map = &$this->caseFolding[$charset][$opt];
				break;
			case 'ascii':
				if (!$this->initToASCII($charset)) {
					return $str;
				}
				// do nothing
				$map = &$this->toASCII[$charset];
				break;
			default:
				return $str;
		}
		$sjis = $charset === 'shift_jis';
		$out = '';
		for ($i = 0; isset($str[$i]); $i++) {
			$mbc = $str[$i];
			$c = ord($mbc);
			if ($sjis) {
				// A double-byte char
				if ($c >= 128 && $c < 160 || $c >= 224) {
					$mbc = substr($str, $i, 2);
					$i++;
				}
			} else {
				// A double-byte char
				if ($c >= 128) {
					$mbc = substr($str, $i, 2);
					$i++;
				}
			}
			if (isset($map[$mbc])) {
				$out .= $map[$mbc];
			} else {
				$out .= $mbc;
			}
		}
		return $out;
	}

}
