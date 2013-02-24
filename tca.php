<?php
if(!defined('TYPO3_MODE'))
	die ('Access denied.');

$TCA['tx_spamshield_log'] = array (
    'ctrl' => $TCA['tx_spamshield_log']['ctrl'],
    'interface' => array (
        'showRecordFieldList' => 'spamreason, spamweight, postvalues, getvalues, requesturl, pageid, referer, ip, useragent, solved'
    ),
    'feInterface' => $TCA['tx_spamshield_log']['feInterface'],
    'columns' => array (
        'spamreason' => Array (
            'exclude' => 1,
            'label' => 'LLL:EXT:spamshield/locallang.xml:tx_spamshield_log.spamreason',
            'config' => Array (
                'type' => 'input',
                'size' => '30',
            )
        ),
        'spamweight' => Array (
            'exclude' => 1,
            'label' => 'LLL:EXT:spamshield/locallang.xml:tx_spamshield_log.spamweight',
            'config' => Array (
                'type' => 'input',
                'size' => '5',
            )
        ),
		'postvalues' => array (
            'exclude' => 1,
            'label' => 'LLL:EXT:spamshield/locallang.xml:tx_spamshield_log.postvalues',
            'config' => array (
                'type' => 'text',
                'cols' => '30',
                'rows' => '5',
            )
        ),
        'getvalues' => array (
            'exclude' => 1,
            'label' => 'LLL:EXT:spamshield/locallang.xml:tx_spamshield_log.getvalues',
            'config' => array (
                'type' => 'text',
                'cols' => '30',
                'rows' => '5',
            )
        ),
        'requesturl' => Array (
            'exclude' => 1,
            'label' => 'LLL:EXT:spamshield/locallang.xml:tx_spamshield_log.requesturl',
            'config' => Array (
                'type' => 'input',
                'size' => '80',
            )
        ),
        'pageid' => Array (
            'exclude' => 1,
            'label' => 'LLL:EXT:spamshield/locallang.xml:tx_spamshield_log.pageid',
            'config' => Array (
                'type' => 'input',
                'size' => '5',
            )
        ),
        'referer' => Array (
            'exclude' => 1,
            'label' => 'LLL:EXT:spamshield/locallang.xml:tx_spamshield_log.referer',
            'config' => Array (
                'type' => 'input',
                'size' => '30',
            )
        ),
        'ip' => Array (
            'exclude' => 1,
            'label' => 'LLL:EXT:spamshield/locallang.xml:tx_spamshield_log.ip',
            'config' => Array (
                'type' => 'input',
                'size' => '30',
            )
        ),
        'useragent' => Array (
            'exclude' => 1,
            'label' => 'LLL:EXT:spamshield/locallang.xml:tx_spamshield_log.useragent',
            'config' => Array (
                'type' => 'input',
                'size' => '30',
            )
        ),
        'solved' => Array (
            'exclude' => 1,
            'label' => 'LLL:EXT:spamshield/locallang.xml:tx_spamshield_log.solved',
            'config' => Array (
                'type' => 'check',
                'default' => '0',
            )
        ),
    ),
    'types' => array (
        '0' => array('showitem' => 'spamreason, spamweight, postvalues, getvalues, requesturl, pageid, referer, ip, useragent, solved')
    ),
    'palettes' => array (
        '1' => array('showitem' => '')
    )
);
?>