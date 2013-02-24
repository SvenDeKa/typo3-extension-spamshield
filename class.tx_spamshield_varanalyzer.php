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

class tx_spamshield_varanalyzer extends \TYPO3\CMS\Frontend\Plugin\AbstractPlugin  {

	var $prefixId = 'tx_spamshield_varanalyzer';									// Same as class name
	var $scriptRelPath = 'class.tx_spamshield_varanalyzer.php';		// Path to this script relative to the extension dir.
	var $extKey = 'spamshield';		// The extension key.

	var $params = FALSE;	// Complete TS-Config
	var $pObj = FALSE;		// pObj at time of hook call

	var $conf = FALSE;		// config from ext_conf_template.txt

	var $GETparams;				// GET variables
	var $POSTparams;			// POST variables
	var $GPparams;				// POST and GET variables

	var $spamReason = array(); 	// description of the error
	var $spamWeight = 0;		// weight of the spam

	/** @var tx_srfreecap_pi2 */
	public $freeCap = NULL;

	/**
	 * @var \Tx\Spamshield\Utilities\ConfigurationUtilities
	 */
	protected $configUtils;

	/**
	 * @var \TYPO3\CMS\Core\Database\DatabaseConnection
	 */
	protected $db;

	/**
	 * @var \Tx\Spamshield\Utilities\SessionHandler
	 */
	protected $sessionHandler;

	/**
	 * Injects the required classes
	 */
	public function __construct() {
		parent::__construct();
		$this->db = $GLOBALS['TYPO3_DB'];
		$this->sessionHandler = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('Tx\\Spamshield\\Utilities\\SessionHandler');
		$this->configUtils = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('Tx\\Spamshield\\Utilities\\ConfigurationUtilities');
	}

	/**
	 * Hook page id lookup before rendering the content.
	 *
	 * @param	$params object		$_params: parameter array
	 * @param	$pObj object		$pObj: partent object
	 * @return	void
	 */
	function main (&$params, &$pObj) {
		$this->params = &$params;
		$this->pObj = &$pObj;
		$this->conf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$this->extKey]);

		# set global variables
		$this->GETparams = \TYPO3\CMS\Core\Utility\GeneralUtility::_GET();
		$this->POSTparams = \TYPO3\CMS\Core\Utility\GeneralUtility::_POST();
		$this->GPparams =  \TYPO3\CMS\Core\Utility\GeneralUtility::array_merge_recursive_overrule($this->GETparams,$this->POSTparams,0,0);

		// check if data already is verified with the spamshield auth
		if ($this->GPparams['spamshield']['uid'] && $this->GPparams['spamshield']['auth']) {
			$res = $this->db->exec_SELECTquery('*', 'tx_spamshield_log', 'uid='.$this->GPparams['spamshield']['uid'].' AND deleted=0');
			if ($this->db->sql_num_rows($res)) { # no UID
				$data = $this->db->sql_fetch_assoc ($res);
				if ($this->checkAuthCode($this->GPparams['spamshield']['auth'],$data)&&$this->checkCaptcha($this->GPparams['spamshield']['captcha_response'])) {
					unset($data['auth']);
					$data['tstamp']= time();
					$data['solved'] = 1;
					$this->db->exec_UPDATEquery('tx_spamshield_log','uid=\''.$data['uid'].'\'', $data);
					return;	// bypass rest of spamshield. Input verified with captcha
				}
			}
		}

		// first line of defense:
		// Block always no matter if a form has been submitted or not
		if (!$this->conf['firstLine']) {
        		$this->conf['firstLine'] = 'httpbl,1';
		}
		$this->check($this->conf['firstLine']);
		// second line of defence:
		// Block only when a form has been submitted
		if ($this->checkFormSubmission()) {
			$this->check($this->configUtils->getProtectionLine(2));
		}

		// if spam => dbLog and stopOutput and Redirect
		if (!$this->conf['weight']) {
			$this->conf['weight'] = 1;
		}
		if ($this->spamWeight >= $this->conf['weight']) {
			if (((int) $this->conf['redirecttopid']) > 0 && ((int) $this->conf['logpid']) === 0) {
				$this->conf['logpid'] = $this->conf['redirecttopid']; // DB-Logging is necessary for redirection!
			}
			$data = array();
			if (((int) $this->conf['logpid']) !== 0) {
				$data = $this->dbLog();  // DB-Logging
			}
			// option for second line of defence to verify with captcha page
			if (((int) $this->conf['redirecttopid']) > 0 && $this->checkFormSubmission()) {
				$this->stopOutputAndRedirect($data);
			}
			// block completely - only way for first line of defence up to now ....
			// verifying a user agent / user configuration with captcha could be doable
			// but first line of spamshield anyway has view false positives - and should have!!
			else {
				$this->stopOutput();
			}
		}
		else {
			return;	// no spam detected
		}
	}

#####################################################
## General functions                               ##
#####################################################

	/**
	* Walks one rule set of checks.
	* If a check is false, gives the corresponding weight
	*
	* @param    $ruleSet string	a rule set: rule1,weight;rule2,weight
	* @return	void
	*/
	function check($ruleSet) {
		$rules = explode(';',$ruleSet);
		foreach ($rules as $rule) {
			list($function,$weight) = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',',$rule);
			$function = trim($function);
			$weight = (int) $weight;
			if ($weight > 0 && method_exists($this,$function)) {
				if ($this->$function()) {
					$this->spamReason[] = $function;
					$this->spamWeight += $weight;
				}
			}
		}
	}

	/**
	* Checks if a form has been submitted
	*
	* @param    nothing
	* @return	boolean
	*/
	function checkFormSubmission () {
		if ($this->POSTparams['spamshield']['mark']) {
			return TRUE; // a form has been submitted
		}
		if (is_array($this->POSTparams) && sizeof($this->POSTparams) != 0) {
			return TRUE; // a form has been submitted
		}
		return FALSE; // regular page request with no form data
	}

	/**
	* Checks a given auth code
	*
	* @param    $authCode int		auth code
	* @param	$row array	DB-Row to check the auth code
	* @return	boolean
	*/
	function checkAuthCode($authCode,&$row) {
		$authCodeFields = ($this->conf['authcodeFields'] ? $this->conf['authcodeFields'] : 'uid');
		$ac = \TYPO3\CMS\Core\Utility\GeneralUtility::stdAuthCode ($row,$authCodeFields);
		if ($ac==$authCode) {
			$row['auth'] = $authCode;
			return TRUE;
		}
		else {
			return FALSE;
		}
	}

	/**
	* Checks Captcha value
	*
	* @param    $captcharesponse string		Captcha response
	* @return	boolean
	*/
	function checkCaptcha($captcharesponse) {
		if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('sr_freecap') ) {
			require_once(\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('sr_freecap').'pi2/class.tx_srfreecap_pi2.php');
			$this->freeCap = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('tx_srfreecap_pi2');
			if (is_object($this->freeCap)) {
				return $this->freeCap->checkWord($captcharesponse);
			}
		} elseif (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('captcha')) {
			session_start();
			if($captcharesponse && $captcharesponse === $_SESSION['tx_captcha_string']) {
				$_SESSION['tx_captcha_string'] = '';
				return TRUE;
			}
			$_SESSION['tx_captcha_string'] = '';
		}

		return FALSE;
	}

	/**
	* Stops TYPO3 output and redirects to another TYPO3 page.
	*
	* @param    $data array	the DB-Row of the spam log
	* @param	$authCodeFields string|int		uid of the fields used for auth code
	* @return	void
	*/
	function stopOutputAndRedirect($data,$authCodeFields = "uid") {
		$param = '';
		if ($this->GPparams['L']) {
			$param .= '&L='.$this->GPparams['L'].' ';
		}
		$param .= '&uid='.$data['uid'].' ';
		$param .= '&auth='.\TYPO3\CMS\Core\Utility\GeneralUtility::stdAuthCode($data,$authCodeFields).' ';
		// redirect to captcha check / result page
		/*if (t3lib_extMgm::isLoaded('pagepath')) {
			require_once(t3lib_extMgm::extPath('pagepath', 'class.tx_pagepath_api.php'));
			$url = tx_pagepath_api::getPagePath($this->conf['redirecttopid'], $param);
		} else {*/
			$url = \TYPO3\CMS\Core\Utility\GeneralUtility::getIndpEnv('TYPO3_SITE_URL').'index.php?id='.$this->conf['redirecttopid'].$param;
		//}
		header("HTTP/1.0 301 Moved Permanently");	// sending a normal header does trick spam robots. They think everything is fine
		header('Location: '.$url);
		die();
	}

	/**
	* Stops TYPO3 output and shows an error page.
	* - derived from mh_httpbl
	*
	* @return	void
	*/
	function stopOutput() {
		if (!$this->conf['message']) {
			$this->conf['message'] = '<strong>you have been blocked.</strong>';
		}

		$output = '
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<title>TYPO3 - http:BL</title>
	</head>
	<body style="background: #fff; color: #ccc; font-family: \'Verdana\', \'Arial\', sans-serif; text-align: center;">
		'.$this->conf['message'].'
	</body>
</html>
		';

		// Prevent caching on the client side
		header('Expires: 0');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
		header('Cache-Control: no-store, no-cache, must-revalidate');
		header('Cache-Control: post-check=0, pre-check=0', FALSE);
		header('Pragma: no-cache');
		die($output);
	}

	/**
	* Put a log entry in the DB if spam is detected
	*
	* @return  boolean
	*/
	function dbLog() {
		if ($this->piVars['refpid']) {
			$ref = $this->piVars['refpid'];
		}
		else {
			$ref = $GLOBALS['TSFE']->id;
		}
		/*  mask recursive */
        $this->mask( new RecursiveArrayIterator($this->POSTparams),'pass');
		$data = array (
			'pid' => mysql_real_escape_string($this->conf['logpid']),  // spam-log storage page
			'tstamp' => time(),
			'crdate' => time(),
			'spamWeight' => mysql_real_escape_string($this->spamWeight),
			'spamReason' => mysql_real_escape_string(implode(',',$this->spamReason)),
			'postvalues' => mysql_real_escape_string(serialize($this->POSTparams)),
			'getvalues' => mysql_real_escape_string(serialize($this->GETparams)),
			'pageid' => mysql_real_escape_string($ref),
			'requesturl' => \TYPO3\CMS\Core\Utility\GeneralUtility::getIndpEnv('TYPO3_REQUEST_URL'),
			'ip' => mysql_real_escape_string(\TYPO3\CMS\Core\Utility\GeneralUtility::getIndpEnv('REMOTE_ADDR')),
			'useragent' => mysql_real_escape_string(\TYPO3\CMS\Core\Utility\GeneralUtility::getIndpEnv('HTTP_USER_AGENT')),
			'referer' => mysql_real_escape_string(\TYPO3\CMS\Core\Utility\GeneralUtility::getIndpEnv('HTTP_REFERER')),
			'solved' => 0
		);
		$this->db->exec_INSERTquery('tx_spamshield_log', $data); // DB entry
		$data['uid'] = $this->db->sql_insert_id();
		return $data;
	}

      /**
      * Traverse / iterates POSTparams array recursive  for key $needle
      * and mask values , primary for password fields
      *
       * @param   $iterator RecursiveArrayIterator
       * @param   $needle string to search
      * @return  void
      * @modify $this->POSTparams
      */
      function mask($iterator, $needle = 'pass' ) {

          while ( $iterator -> valid() ) {
              if ( $iterator -> hasChildren() ) {
                  $this->mask($iterator -> getChildren(), $needle);
              }
              else {
                  if (stripos($iterator -> key(), $needle) !== FALSE ) {
                        $this->POSTparams[$iterator->key()] = 'xxxxx';
                  }
              }
              $iterator -> next();
          }
      }

#####################################################
## Functions for first Line Defence                ##
#####################################################
	/*
	*	from mh_httpbl
	*	-5 => 'localhost'
	*	- 3 => 'misc = here possibly whitelist / blacklist'
	*	-2 => 'no REMOTE_ADDR = no request possible'
	*	-1 => 'no access key = no request possible'
	*	0 => 'Search Engine',
	*	1 => 'Suspicious',
	*	2 => 'Harvester',
	*	3 => 'Suspicious & Harvester',
	*	4 => 'Comment Spammer',
	*	5 => 'Suspicious & Comment Spammer',
	*	6 => 'Harvester & Comment Spammer',
	*	7 => 'Suspicious & Harvester & Comment Spammer'
	*
	*	httpbl recommends to block >= 2
	*
	* @return  boolean
	*/
	function httpbl() {
		if (empty($this->conf['accesskey'])) {
			$type = -1;
		}
		elseif (empty($_SERVER['REMOTE_ADDR'])) {
			$type = -2;
		}
		else {
			$type = -3;
			$codes = array(  # codes used by httpbl.org
					0 => 'Search Engine',
					1 => 'Suspicious',
					2 => 'Harvester',
					3 => 'Suspicious &amp; Harvester',
					4 => 'Comment Spammer',
					5 => 'Suspicious &amp; Comment Spammer',
					6 => 'Harvester &amp; Comment Spammer',
					7 => 'Suspicious &amp; Harvester &amp; Comment Spammer'
			);
			$domain	= 'dnsbl.httpbl.org';
			$request = $this->conf['accesskey'].'.'.implode('.', array_reverse(explode('.', $_SERVER['REMOTE_ADDR']))).'.'.$domain;
			$result = gethostbyname($request);
			$first = NULL;
			if ($result != $request) {
				list($first, $days, $score, $type) = explode('.', $result);  // $type = one of the $codes; higher $score = more active bot
			}
			if ($first != 127 || !array_key_exists($this->conf['type'], $codes)) {
   			     $type = -5;
			}
		}
		if ($type >= $this->conf['type']) {
			return TRUE;  // = Spam
		}
		else {
			return FALSE; // = no Spam
		}
	}

#####################################################
## functions for either first or Second Line       ##
#####################################################
	/**
	* useragant
	*
	* Every browser sends a HTTP_USER_AGENT value to a server.
	* 	So a missing HTTP_USER_AGENT value almost always indicates a spammer bot.
	*
	* @return  boolean
	*/
	function useragent() {
		if (\TYPO3\CMS\Core\Utility\GeneralUtility::getIndpEnv('HTTP_USER_AGENT') == '') {
		   	return TRUE;  		// = spam
		}
		return FALSE; // no spam;
	}

	/**
	* referer
	*
	* The most of browsers (all modern browsers) send a HTTP_REFERER value,
	* 	... which would contain the submitted form URL.
	*   ... which therefore should be from same domain.
	*  Whereas clever bots send this value, a missing HTTP_REFERER value could mean a bot submitting.
	*  Note. There are several firewall and security products which block HTTP_REFERER by default.
	*  So, none of these people could send a message if you block posting without HTTP_REFERER.
	*
	* @return  boolean
	*/
	function referer() {
		if ($this->conf['whitelist']) {
			$whiteList = explode(',',$this->conf['whitelist']);
			foreach ($whiteList as $white) {
				$white = trim($white);
				if (strpos(\TYPO3\CMS\Core\Utility\GeneralUtility::getIndpEnv('HTTP_REFERER'),$white)!==FALSE) {
					return FALSE; // no spam
				}
			}
		}
		// checking for empty referers or referers that are external of the website
		elseif (
			(!\TYPO3\CMS\Core\Utility\GeneralUtility::getIndpEnv('HTTP_REFERER') || \TYPO3\CMS\Core\Utility\GeneralUtility::getIndpEnv('HTTP_REFERER') == '')
			||
			($GLOBALS['TSFE']->tmpl->setup['config.']['baseUrl'] &&
			!strstr(\TYPO3\CMS\Core\Utility\GeneralUtility::getIndpEnv('HTTP_REFERER'),$GLOBALS['TSFE']->tmpl->setup['config.']['baseURL']))
			||
			($GLOBALS['TSFE']->tmpl->setup['config.']['absRefPrefix'] &&
			!strstr(\TYPO3\CMS\Core\Utility\GeneralUtility::getIndpEnv('HTTP_REFERER'),$GLOBALS['TSFE']->tmpl->setup['config.']['absRefPrefix']))
			) {
			return TRUE; // spam
		}

		return FALSE;  // no spam
	}

#####################################################
## Functions for Second Line Defence               ##
#####################################################
	/**
	* checks if the java-skript cookie is availabel
	* some bots don't accept cookies normally
	*
	* @return  boolean
	*/
	function javascript() {
		// no form data send => new users are welcome and have no cookie
		return FALSE; // no spam - this check does not work proper in some situations - the cookie gets lost on page changes
###
# to do: find a better javascript check
###
//		if (!$_COOKIE['spamshield']) {
//		   	return TRUE; // spam
//		}
//
//		return FALSE; // no spam
	}

	/**
	* checks if cookie is available
	* some bots don't accept cookies normally
	*
	* @return  boolean
	*/
	function cookie() {
		$cookieName = 'fe_typo_user';
		if (trim($GLOBALS['TYPO3_CONF_VARS']['FE']['cookieName']) !== '') {
			$cookieName = $GLOBALS['TYPO3_CONF_VARS']['FE']['cookieName'];
		}

		if (!$_COOKIE[$cookieName]) {
		   	return TRUE; // spam
		}

		return FALSE; // no spam
	}

	/**
	* checks if honey pot fields are filled in
	* bots normally don't read CSS / Java and fill in everything.
	*
	* @return  boolean
	*/
	function honeypot() {
		if (!$this->conf['honeypot']) {
			$this->conf['honeypot'] = 'email,e-mail,name,first-name';
		}
		foreach (explode(',',$this->conf['honeypot']) as $name) {
			if ($this->GPparams[$name]) {
				return TRUE; // spam
			}
		}
		return FALSE; // no spam
	}

	/**
	 * Checks, if the user has submitted the form in a reasonable amount of
	 * time
	 *
	 * @return bool
	 */
	protected function sessionTimestamp() {

		$validTimestamp = $this->sessionHandler->getSubmittedValidTimestampId();

		if ($validTimestamp !== FALSE) {
			return FALSE; // no spam
		} else {
			return TRUE; // spam
		}
	}
}
