<?php
/*
 * Register necessary class names for scheduler with autoloader
 */
$extensionPath = t3lib_extMgm::extPath('spamshield');
return array(
	'tx_spamshield_logcleaner' => $extensionPath . 'tasks/class.tx_spamshield_logcleaner.php',
	'tx_spamshield_logcleaner_additionalfields' => $extensionPath . 'tasks/class.tx_spamshield_logcleaner_additionalfields.php',
);
?>

