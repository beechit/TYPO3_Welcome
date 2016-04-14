<?php

/***************************************************************
 * Extension Manager/Repository config file for ext "site_template".
 ***************************************************************/

$EM_CONF[$_EXTKEY] = [
    'title' => 'Website template',
    'description' => 'Website basic/template',
    'category' => 'misc',
    'author' => 'Ruud Silvrants',
    'author_email' => 't3ext@beech.it',
    'author_company' => 'Beech.it',
    'version' => '0.0.1',
    'state' => 'stable',
    'uploadfolder' => '0',
    'createDirs' => '',
    'clearCacheOnLoad' => 1,
    'constraints' => [
        'depends' => [

            'typo3' => '6.2.9-7.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];