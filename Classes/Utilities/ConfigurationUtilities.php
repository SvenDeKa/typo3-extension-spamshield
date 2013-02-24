<?php
namespace Tx\Spamshield\Utilities;

/*                                                                        *
 * This script belongs to the TYPO3 extension "spamshield".               *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License, either version 3 of the   *
 * License, or (at your option) any later version.                        *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

/**
 * Handles the extension configuration
 */
class ConfigurationUtilities implements \TYPO3\CMS\Core\SingletonInterface {

	/**
	 * Default values for all config options
	 *
	 * @var array
	 */
	protected $defaultConfiguration = array(
		'timestampMinAge' => 5,
		'timestampMaxAge' => 7200,
		'secondLine' => 'useragent,1;referer,1;javascript,1;honeypot,1;sessionTimestamp,1',
	);

	/**
	 * Deserialized extension configuration
	 *
	 * @var array
	 */
	protected $extensionConfiguration;

	/**
	 * Initializes the extension configuration
	 */
	public function __construct() {

		$this->extensionConfiguration = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['spamshield']);

		if (!is_array($this->extensionConfiguration)) {
			$this->extensionConfiguration = array();
		}
	}

	/**
	 * Returns the configuration for the given defence line
	 *
	 * @param int $lineNumer
	 * @return string
	 */
	public function getProtectionLine($lineNumer) {

		switch ($lineNumer) {
			case 1:
				$protectionLine = $this->getExtensionConfiguration('firstLine');
				break;
			case 2:
				$protectionLine = $this->getExtensionConfiguration('secondLine');
				break;
			default:
				throw new \Exception('Invalid protection line number: ' . $lineNumer);
		}

		return $protectionLine;
	}

	/**
	 * Returns the minimum required age of a timestamp in seconds
	 *
	 * @return int
	 */
	public function getTimestampMinAge() {
		return $this->getExtensionConfiguration('timestampMinAge', 'int');
	}

	/**
	 * Returns the minimum allowed age of a timestamp in seconds
	 *
	 * @return int
	 */
	public function getTimestampMaxAge() {
		return $this->getExtensionConfiguration('timestampMaxAge', 'int');
	}

	/**
	 * Cleans the config value and merges extension configuration and default
	 * configuration
	 *
	 * @param string $key The config key
	 * @param string $type The type (can be "string" or "int")
	 * @return mixed The config value
	 */
	protected function getExtensionConfiguration($key, $type = 'string') {

		if (!array_key_exists($key, $this->defaultConfiguration)) {
			throw new \Exception('Invalid configuration key: ' . $key);
		}

		$configValue = $this->defaultConfiguration[$key];

		if (array_key_exists($key, $this->extensionConfiguration)) {
			$configValue = $this->extensionConfiguration[$key];
		}

		switch ($type) {
			case 'string':
				break;
			case 'int':
				$configValue = intval($configValue);
				break;
			default:
				throw new \Exception('Unknown config variable type');
		}

		return $configValue;
	}
}
