<?php
if (!defined('TYPO3_MODE')) {
	die ('Access denied.');
}

$pluginSignature = 'spamshield_protectedplugin';

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPlugin(array(
	'LLL:EXT:spamshield/locallang_db.xml:tt_content.list_type_pi1',
	$pluginSignature,
	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extRelPath($_EXTKEY) . 'ext_icon.gif'
), 'CType');

$GLOBALS['TCA']['tt_content']['types'][$pluginSignature] = &$GLOBALS['TCA']['tt_content']['types']['list'];

if (is_array($GLOBALS['TCA']['tt_content']['columns']['pi_flexform']['config']['ds'])) {
	$currentFlexformConfig = $GLOBALS['TCA']['tt_content']['columns']['pi_flexform']['config']['ds'];
	foreach ($currentFlexformConfig as $key => $value) {
		list($piKeyToMatch, $CTypeToMatch) = explode(',', $key);
		if ($CTypeToMatch === 'list') {
			$GLOBALS['TCA']['tt_content']['columns']['pi_flexform']['config']['ds'][$piKeyToMatch . ',' . $pluginSignature] = &$GLOBALS['TCA']['tt_content']['columns']['pi_flexform']['config']['ds'][$piKeyToMatch . ',list'];
		}
	}
}

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile($_EXTKEY, 'Configuration/TypoScript/', 'spamshield spam protection'); // for TS template

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('spamshield', 'EXT:spamshield/locallang_csh.xml');