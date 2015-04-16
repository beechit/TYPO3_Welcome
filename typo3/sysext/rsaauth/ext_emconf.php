<?php
$EM_CONF[$_EXTKEY] = array(
	'title' => 'RSA authentication for TYPO3',
	'description' => 'Contains a service to authenticate TYPO3 BE and FE users using private/public key encryption of passwords',
	'category' => 'services',
	'author' => 'Dmitry Dulepov',
	'author_email' => 'dmitry@typo3.org',
	'author_company' => '',
	'state' => 'stable',
	'uploadfolder' => 0,
	'createDirs' => '',
	'clearCacheOnLoad' => 0,
	'version' => '7.1.0',
	'constraints' => array(
		'depends' => array(
			'typo3' => '7.1.0-7.1.99',
		),
		'conflicts' => array(),
		'suggests' => array(),
	),
	'_md5_values_when_last_written' => '',
);
