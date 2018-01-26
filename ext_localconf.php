<?php

if (!defined("TYPO3_MODE")) {
    die("Access denied.");
}

#####################################################
## Hook for HTML-form and page modification #########
#####################################################

// hook is called after Caching!
// => for form modification on pages with COA_/USER_INT objects. 
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['contentPostProc-output'][] =
    'SpamshieldTeam\\Spamshield\\Hooks\\FormModifier->intPages';

// hook is called before Caching!
// => for form modification on pages on their way in the cache.
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['contentPostProc-all'][] =
    'SpamshieldTeam\\Spamshield\\Hooks\\FormModifier->noIntPages';

#####################################################
## Hook for POST / GET variables checking     #######
#####################################################

// this hook is called at the very beginning of a request.
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['determineId-PostProc'][] =
    'SpamshieldTeam\\Spamshield\\Hooks\\VarAnalyzer->main';

#####################################################
## FE-Plugin                                  #######
#####################################################

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
    'SpamshieldTeam.spamshield',
    'Spamshield',
    ['Captcha' => 'show'],
    ['Captcha' => 'show']
);

#####################################################
## scheduler task                             #######
#####################################################

// Register tx_spamshield_log table in table garbage collection task
$taskClass = 'TYPO3\CMS\Scheduler\Task\TableGarbageCollectionTask';
$tableName = 'tx_spamshield_log';
if (!is_array(
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][$taskClass]['options']['tables'][$tableName]
)
) {
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][$taskClass]['options']['tables'][$tableName] = [
        'dateField' => 'tstamp',
        'expirePeriod' => 180,
    ];
}
