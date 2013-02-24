<?php
if (!defined ("TYPO3_MODE")) 	die ("Access denied.");

#####################################################
## Hook for HTML-form and page modification #########
#####################################################
// hook is called after Caching! 
// => for form modification on pages with COA_/USER_INT objects. 
$TYPO3_CONF_VARS['SC_OPTIONS']['tslib/class.tslib_fe.php']['contentPostProc-output'][] = 'EXT:spamshield/class.tx_spamshield_formmodifier.php:&tx_spamshield_formmodifier->intPages';
// hook is called before Caching!
// => for form modification on pages on their way in the cache.
$TYPO3_CONF_VARS['SC_OPTIONS']['tslib/class.tslib_fe.php']['contentPostProc-all'][] = 'EXT:spamshield/class.tx_spamshield_formmodifier.php:&tx_spamshield_formmodifier->noIntPages';
#####################################################

#####################################################
## Hook for POST / GET variables checking     #######
#####################################################
// this hook is called at the very beginning of a request. 
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['determineId-PostProc'][] = 'EXT:'.$_EXTKEY.'/class.tx_spamshield_varanalyzer.php:&tx_spamshield_varanalyzer->main';
#####################################################

#####################################################
## FE-Plugin                                  #######
#####################################################
t3lib_extMgm::addPItoST43($_EXTKEY, 'pi1/class.tx_spamshield_pi1.php', '_pi1', 'list_type', 0);
#####################################################

#####################################################
## scheduler task                             #######
#####################################################
if (t3lib_div::compat_version('4.6')) {
	// Register tx_spamshield_log table in table garbage collection task
	if (!is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks']['tx_scheduler_TableGarbageCollection']['options']['tables']['tx_spamshield_log'])) {
		$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks']['tx_scheduler_TableGarbageCollection']['options']['tables']['tx_spamshield_log'] = array(
			'dateField' => 'tstamp',
			'expirePeriod' => 180,
		);
	}
} 
else {
	//@TODO: Remove this when TYPO3 4.5 LTS is not supported any more (in April 2014)
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks']['tx_spamshield_logcleaner'] = array(
		'extension'        => $_EXTKEY,
		'title'            => 'LLL:EXT:' . $_EXTKEY . '/locallang.xml:tx_spamshield_log.task.name',
		'description'      => 'LLL:EXT:' . $_EXTKEY . '/locallang.xml:tx_spamshield_log.task.description',
		'additionalFields' => 'tx_spamshield_logcleaner_additionalfields'
	);
}
#####################################################
?>
