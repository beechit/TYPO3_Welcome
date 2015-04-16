<?php

/***************************************************************
 * Extension Manager/Repository config file for ext "bootstrap_package".
 *
 * Auto generated 16-04-2015 10:35
 *
 * Manual updates:
 * Only the data in the array - everything else is removed by next
 * writing. "version" and "dependencies" must not be touched!
 ***************************************************************/

$EM_CONF[$_EXTKEY] = array (
	'title' => 'Bootstrap Package',
	'description' => 'Bootstrap Package delivers a full configured frontend for TYPO3 CMS 6.2, based on the Bootstrap CSS Framework.',
	'category' => 'templates',
	'version' => '6.2.9',
	'state' => 'stable',
	'uploadfolder' => false,
	'createDirs' => '',
	'clearcacheonload' => true,
	'author' => 'Benjamin Kott',
	'author_email' => 'info@bk2k.info',
	'author_company' => 'private',
	'constraints' => 
	array (
		'depends' => 
		array (
			'typo3' => '6.2.9-7.99.99',
			'css_styled_content' => '6.2.0-7.99.99',
		),
		'conflicts' => 
		array (
			'fluidpages' => '*',
			'dyncss' => '*',
		),
		'suggests' => 
		array (
			'realurl' => '1.12.8-1.12.99',
		),
	),
);

