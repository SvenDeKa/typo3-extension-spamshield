<?php
return array(
    'ctrl' => array(
        'title' => 'Spamshield',
        'label' => 'spamreason',
        'label_alt' => 'pageid,solved',
        'label_alt_force' => 1,
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'default_sortby' => "ORDER BY crdate DESC",
        'delete' => 'deleted',
        'iconfile' => TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extRelPath('spamshield') . 'Resources/Public/Icons/icon_tx_spamshield_log.gif',
    ),
    'interface' => array(
        'showRecordFieldList' => 'spamreason, spamweight, postvalues, getvalues, requesturl, pageid, referer, ip, useragent, solved'
    ),
    'columns' => array(
        'spamreason' => array(
            'exclude' => 1,
            'label' => 'LLL:EXT:spamshield/Resources/Private/Language/locallang_db.xlf:tx_spamshield_log.spamreason',
            'config' => array(
                'type' => 'input',
                'size' => '30',
            )
        ),
        'spamweight' => array(
            'exclude' => 1,
            'label' => 'LLL:EXT:spamshield/Resources/Private/Language/locallang_db.xlf:tx_spamshield_log.spamweight',
            'config' => array(
                'type' => 'input',
                'size' => '5',
            )
        ),
        'postvalues' => array(
            'exclude' => 1,
            'label' => 'LLL:EXT:spamshield/Resources/Private/Language/locallang_db.xlf:tx_spamshield_log.postvalues',
            'config' => array(
                'type' => 'text',
                'cols' => '30',
                'rows' => '5',
            )
        ),
        'getvalues' => array(
            'exclude' => 1,
            'label' => 'LLL:EXT:spamshield/Resources/Private/Language/locallang_db.xlf:tx_spamshield_log.getvalues',
            'config' => array(
                'type' => 'text',
                'cols' => '30',
                'rows' => '5',
            )
        ),
        'requesturl' => array(
            'exclude' => 1,
            'label' => 'LLL:EXT:spamshield/Resources/Private/Language/locallang_db.xlf:tx_spamshield_log.requesturl',
            'config' => array(
                'type' => 'input',
                'size' => '80',
            )
        ),
        'pageid' => array(
            'exclude' => 1,
            'label' => 'LLL:EXT:spamshield/Resources/Private/Language/locallang_db.xlf:tx_spamshield_log.pageid',
            'config' => array(
                'type' => 'input',
                'size' => '5',
            )
        ),
        'referer' => array(
            'exclude' => 1,
            'label' => 'LLL:EXT:spamshield/Resources/Private/Language/locallang_db.xlf:tx_spamshield_log.referer',
            'config' => array(
                'type' => 'input',
                'size' => '30',
            )
        ),
        'ip' => array(
            'exclude' => 1,
            'label' => 'LLL:EXT:spamshield/Resources/Private/Language/locallang_db.xlf:tx_spamshield_log.ip',
            'config' => array(
                'type' => 'input',
                'size' => '30',
            )
        ),
        'useragent' => array(
            'exclude' => 1,
            'label' => 'LLL:EXT:spamshield/Resources/Private/Language/locallang_db.xlf:tx_spamshield_log.useragent',
            'config' => array(
                'type' => 'input',
                'size' => '30',
            )
        ),
        'solved' => array(
            'exclude' => 1,
            'label' => 'LLL:EXT:spamshield/Resources/Private/Language/locallang_db.xlf:tx_spamshield_log.solved',
            'config' => array(
                'type' => 'check',
                'default' => '0',
            )
        ),
    ),
    'types' => array(
        '0' => array('showitem' => 'spamreason, spamweight, postvalues, getvalues, requesturl, pageid, referer, ip, useragent, solved')
    ),
    'palettes' => array(
        '1' => array('showitem' => '')
    )
);
