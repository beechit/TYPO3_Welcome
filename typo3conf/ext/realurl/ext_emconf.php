<?php

/***************************************************************
 * Extension Manager/Repository config file for ext "realurl".
 *
 * Auto generated 16-04-2015 10:35
 *
 * Manual updates:
 * Only the data in the array - everything else is removed by next
 * writing. "version" and "dependencies" must not be touched!
 ***************************************************************/

$EM_CONF[$_EXTKEY] = array (
	'title' => 'RealURL: speaking paths for TYPO3',
	'description' => 'Creates nice looking URLs for TYPO3 pages: converts http://example.com/index.phpid=12345&L=2 to http://example.com/path/to/your/page/. Please, ask for free support in TYPO3 mailing lists or contact the maintainer for paid support.',
	'category' => 'fe',
	'version' => '1.13.3',
	'state' => 'stable',
	'uploadfolder' => true,
	'createDirs' => '',
	'clearcacheonload' => false,
	'author' => 'Dmitry Dulepov',
	'author_email' => 'dmitry.dulepov@gmail.com',
	'author_company' => '',
	'constraints' => 
	array (
		'depends' => 
		array (
			'php' => '5.3.2-5.999.999',
			'typo3' => '4.5.40-7.1.999',
			'lowlevel' => '1.0.0-100.0.0',
		),
		'conflicts' => 
		array (
			'cooluri' => '',
			'simulatestatic' => '',
		),
		'suggests' => 
		array (
		),
	),
);

