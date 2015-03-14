<?php
if (!defined("TYPO3_MODE")) {
	die ("Access denied.");
}

// Register tx_spamshield_log table in table garbage collection task
if (!is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks']['TYPO3\\CMS\\Scheduler\\Task\\TableGarbageCollectionTask']['options']['tables']['tx_spamshield_log'])) {
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks']['TYPO3\\CMS\\Scheduler\\Task\\TableGarbageCollectionTask']['options']['tables']['tx_spamshield_log'] = array(
		'dateField' => 'tstamp',
		'expirePeriod' => 180,
	);
}

// We hook into the page module to make the spam protected plugin behave
// like a normal plugin with CType = list.
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['cms/layout/class.tx_cms_layout.php']['tt_content_drawItem'][] = 'Tx\\Spamshield\\Hook\\PageLayoutViewDrawItemHook';

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin('Tx.Spamshield', 'spamhandler', array('SpamHandler' => 'displaySpamInformation'), array('SpamHandler' => 'displaySpamInformation'));