<?php

$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_excludelist']['spamshield_pi1'] = 'layout,select_key,pages';

TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPlugin(
    array(
        'LLL:EXT:spamshield/Resources/Private/Language/locallang_db.xlf:tt_content.list_type_pi1',
        'spamshield_pi1',
        TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extRelPath('spamshield') . 'ext_icon.gif'
    ),
    'list_type'
);
