<?php

/***************************************************************
*  Copyright notice
*
*  (c) 2009  Dr. Ronald Steiner <Ronald.Steiner@googlemail.com>
*  All rights reserved
*
*  This script is part of the Typo3 project. The Typo3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

class tx_spamshield_formmodifier extends \TYPO3\CMS\Frontend\Plugin\AbstractPlugin {

	var $prefixId = "tx_spamshield_formmodifier";				// Same as class name
	var $scriptRelPath = "class.tx_spamshield_formmodifier.php";		// Path to this script relative to the extension dir.
	var $extKey = "spamshield";		// The extension key.

	/**
	 * @var \Tx\Spamshield\Utilities\SessionHandler
	 */
	protected $sessionHandler;

	/**
	 * @var \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController
	 */
	protected $tsfe;

	public function __construct() {
		parent::__construct();
		$this->tsfe = $GLOBALS['TSFE'];

	}

	/**
	 * Hook output after rendering the content.
	 * - no cached pages
	 *
	 * @param	$params object		parameter array
	 * @param	$that object		parent object
	 * @return	void
	 */
	function intPages (&$params,&$that) {
		if (!$this->tsfe->isINTincScript()) {
			return;
		}
		$this->main ($params['pObj']->content, $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_spamshield.']);
	}

	/**
	 * Hook output after rendering the content.
	 * - cached pages
	 *
	 * @param	$params object		$_params: parameter array
	 * @param	$that object		$pObj: parent object
	 * @return	void
	 */
	function noIntPages (&$params,&$that) {
		if ($this->tsfe->isINTincScript()) {
			return;
		}
		$this->main ($params['pObj']->content, $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_spamshield.']);
	}

	/**
	 * Main Function:
	 * - search for forms, mark them with a honey pot
	 *
	 * @param	$html string		the output html code
	 * @param	$conf array		Configuration array
	 * @return	void
	 */
	function main (&$html, &$conf) {
		$this->sessionHandler = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('Tx\\Spamshield\\Utilities\\SessionHandler');
		$this->cObj = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Frontend\\ContentObject\\ContentObjectRenderer');
		$this->cObj->start();
		if ($conf['add2forms'] && strstr($html,'<form')) {
			$newForms = $orgForms = $this->getForms($html);
			for ($i = 0; $i < sizeof($newForms); $i++) {
				if (!$this->enableOff($newForms[$i],$conf['add2forms.']['off.'])) {
					$this->add2forms($newForms[$i],$conf['add2forms.']);
				}
			}
			$html = str_replace($orgForms,$newForms,$html);
		}
	}

	/**
	 * get all forms out of $this->html
	 *
	 * @param	nothing
	 * @return  array	   all forms in $this->body
	 */
	function getForms (&$html) {
		preg_match_all ("/(?s)(<[ \n\r]*form[^>]*>.*?<[ \n\r]*\/form[^>]*>)/is", $html, $matches);
		return $matches[0];
	}

	/**
	 * include a fields to a form
	 * e.g. inclusion of markers (required) and honeypots (have to be empty)
	 *
	 * @param	$form string		html code of the forms.
	 * @param $conf array
	 * @return	void
	 */
	function add2forms (&$form,$conf) {
		$newInputs = $inputs = $this->getInputs($form);
		$honeypotArray = $this->renderHoneypots($conf);
		if ($conf['position'] == "rnd") {
			foreach ($honeypotArray as $honeyPot) {
				$changePos = mt_rand(0,sizeof($newInputs)-1);
				if (mt_rand(0,1) == 1) {
					$newInputs[$changePos] = $honeyPot.$newInputs[$changePos];
				}
				else {
					$newInputs[$changePos] = $newInputs[$changePos].$honeyPot;
				}
			}
		}
		elseif ($conf['position'] == 'end') {
			$newInputs[sizeof($newInputs)-1] = $newInputs[sizeof($newInputs)-1].implode('',$honeypotArray);
		}
		elseif ($conf['position'] == 'start') {
			$newInputs[0] = implode('',$honeypotArray).$newInputs[0];
		}
		elseif ($conf['position'] == 'start-end') {
			$i = 0;
			foreach ($honeypotArray as $honeypot) {
				if ($i%2) {
					$newInputs[0] = $honeypot.$newInputs[0];
				}
				else {
					$newInputs[sizeof($newInputs)-1] = $newInputs[sizeof($newInputs)-1].$honeypot;
				}
				$i++;
			}
		}
		$form = str_replace($inputs,$newInputs,$form);
	}

	/**
	 * regex rules for disabling for single forms
	 *
	 * @param	$form string	 the html form
	 * @param   $conf array	   config array with regex rules
	 * @return  boolean
	 */
	function enableOff($form,$conf) {
		if (is_array($conf)) {
			foreach($conf as $pattern) {
				if(preg_match('/' . preg_quote($pattern, '/') . '/is', $form)) {
					return TRUE;
				}
			}
		}

		return FALSE;
	}

	/**
	 * get all input fields out of a form
	 * help function for honeypots
	 *
	 * @param	$form string		html code of the form.
	 * @return	array	   all input fields in the form
	 */
	function getInputs($form) {
		preg_match_all ("/(?s)(<[ \n\r]*input.*?[ \n\r]*[^>]*>)/is", $form, $matches);
		return $matches[0];
	}

	/**
	 * Renders the configured honeypot fields in an array. If a configuration has a subconfiguration
	 * (like 10 = TEXT, 10.value = Hallo world), the entry will be rendered as a content object.
	 *
	 * Otherwise a simple string will be added.
	 *
	 * If the rendered honeypot contains a ###SESSION_TIMESTAMP_ID### marker it will be repaced with
	 * the current session timestamp ID.
	 *
	 * @param array $conf
	 * @return array
	 */
	protected function renderHoneypots($conf) {

		$sessionTimestampId = $this->sessionHandler->createOrUpdateSessionTimestamp();
		$fieldConfigArray = $conf['fields.'];
		$honeypots = array();

		foreach ($fieldConfigArray as $key => $fieldConfig) {

			if (array_key_exists($key . '.', $fieldConfigArray)) {
				$honeypotContent = $this->cObj->cObjGetSingle($fieldConfig, $fieldConfigArray[$key . '.']);
			} else {
				$honeypotContent = $fieldConfig;
			}

			$honeypotContent = $this->cObj->substituteMarker($honeypotContent, '###SESSION_TIMESTAMP_ID###', $sessionTimestampId);

			$honeypots[] = $honeypotContent;
		}

		return $honeypots;
	}
}

?>