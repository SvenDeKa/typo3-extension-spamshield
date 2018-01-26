<?php

if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}


TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile(
    'spamshield', 'Configuration/TypoScript/', 'spamshield spam protection'
);

TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin(
    'SpamshieldTeam.' . $_EXTKEY, 'Spamshield',
    'LLL:EXT:spamshield/Resources/Private/Language/locallang_db.xlf:plugin.captcha'
);

TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr(
    'spamshield', 'EXT:spamshield/Resources/Private/Language/locallang_csh.xlf'
);
