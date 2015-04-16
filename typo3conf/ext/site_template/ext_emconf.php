<?php

/***************************************************************
 * Extension Manager/Repository config file for ext "site_template".
 ***************************************************************/

$EM_CONF[$_EXTKEY] = array (
	'title' => 'Site template',
	'description' => 'The site template for our welcome to TYPO3 website',
	'category' => 'misc',
	'version' => '0.0.1',
	'state' => 'stable',
	'uploadfolder' => false,
	'createDirs' => '',
	'clearcacheonload' => true,
	'author' => 'Ruud Silvrants',
	'author_email' => 'support@beech.it',
	'author_company' => 'Beech.it',
	'constraints' =>
	array (
		'depends' => array (
			'typo3' => '6.2.9-7.99.99',
			'bootstrap_package' => '6.2.9'
		),
		'conflicts' => array (
			'fluidpages' => '*',
			'dyncss' => '*',
		),
		'suggests' => array (
			'realurl' => '1.12.8-1.12.99',
		),
	),
);

