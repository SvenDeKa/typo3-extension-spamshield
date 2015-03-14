<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2011 Ronald Steiner <Ronald.Steiner@AshtangaYoga.info>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
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
/**
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 * Hint: use extdeveval to insert/update function index above.
 */

/**
 * Plugin 'spamshield: Auth-Code-Handler' for the 'spamshield' extension.
 *
 * @author	Ronald Steiner <Ronald.Steiner@AshtangaYoga.info>
 * @package	TYPO3
 * @subpackage	tx_spamshield
 */
class tx_spamshield_pi1 extends tslib_pibase {
	var $prefixId      = 'tx_spamshield_pi1';		// Same as class name
	var $scriptRelPath = 'pi1/class.tx_spamshield_pi1.php';	// Path to this script relative to the extension dir.
	var $extKey        = 'spamshield';	// The extension key.

	var $GETparams     = array();   // GET-Params

	/**
	 * The main method of the PlugIn
	 *
	 * @param	$content string		$content: The PlugIn content
	 * @param	$conf array		$conf: The PlugIn configuration
	 * @return	string The content that is displayed on the website
	 */
	function main($content, $conf) {
		$this->conf = $conf;
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();
		$this->pi_USER_INT_obj = 1;	// Configuring so caching is not expected. This value means that no cHash params are ever set. We do this, because it's a USER_INT object!

		$this->GETparams = t3lib_div::_GET();

		if (!t3lib_extMgm::isLoaded('sr_freecap') && !t3lib_extMgm::isLoaded('captcha')) {
			$content = '<div class="message red">'.htmlspecialchars($this->pi_getLL('message.nocaptcha')).'</div>';
		} elseif (!$this->GETparams['uid'] || !$this->GETparams['auth']) {
			$content = '<div class="message red">'.htmlspecialchars($this->pi_getLL('message.wronglink')).'</div>';
		}
		else {
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_spamshield_log', 'uid='.intval($this->GETparams['uid']).' AND deleted=0 AND solved=0');
			if (!$GLOBALS['TYPO3_DB']->sql_num_rows($res)) { # no UID
				$content = '<div class="message red">'.htmlspecialchars($this->pi_getLL('message.wronguid')).'</div>';
			}
			else {
				$data = $GLOBALS['TYPO3_DB']->sql_fetch_assoc ($res);
				if (!$this->checkAuthCode($this->GETparams['auth'],$data)) {
					$content = '<div class="message red">'.htmlspecialchars($this->pi_getLL('message.wrongauth')).'</div>';
				}
				else {
					$content = $this->renderForm($data);
				}
			}
		}
		return $this->pi_wrapInBaseClass($content);
	}

	/**
	* Checks a given auth code
	*
	* @param    int		auth code
	* @param	array	DB-Row to check the auth code
	* @return	boolean
	*/
	function checkAuthCode($authCode,&$row) {
		$authCodeFields = ($this->conf['authcodeFields'] ? $this->conf['authcodeFields'] : 'uid');
		$ac = t3lib_div::stdAuthCode ($row,$authCodeFields);
		if ($ac==$authCode) {
			$row['auth'] = $authCode;
			return true;
		}
		else {
			return false;
		}
	}

	/**
	* renders a form
	*
	* @param    array	the DB-Row of the spam log
	* @return	string	the form
	*/
	function renderForm($data) {
		$post = unserialize(stripslashes($data['postvalues'])); // stripslashes needed because data is stored in DB with: mysql_escape_string
		unset ($post['spamshield']['uid']);
		unset ($post['spamshield']['auth']);
		foreach ($post as $key => $val) {
			if (!is_array($val)) {
				$input[] = '<input type="hidden" name="'.$key.'" value="'.$val.'" />';
			}
			else {
				foreach ($val as $a => $b) {
					if (!is_array($b)) {
						$input[] = '<input type="hidden" name="'.$key.'['.$a.']" value="'.$b.'" />';
					}
					else {
						foreach ($b as $x => $y) {
							$input[] = '<input type="hidden" name="'.$key.'['.$a.']['.$x.']" value="'.$y.'" />';
						}
					}
				}
			}
		}

		// Captcha
		if (t3lib_extMgm::isLoaded('sr_freecap')) {
			require_once(t3lib_extMgm::extPath('sr_freecap').'pi2/class.tx_srfreecap_pi2.php');
			$this->freeCap = t3lib_div::makeInstance('tx_srfreecap_pi2');
			if (is_object($this->freeCap)) {
				$template = $this->cObj->getSubpart($this->cObj->fileResource('EXT:spamshield/res/sr_freecap.html'), '###CAPTCHA_INSERT###'); // TODO: overwrite template path with TypoScript
				$input[] = $this->cObj->substituteMarkerArray($template,$this->freeCap->makeCaptcha());
			}
		} elseif (t3lib_extMgm::isLoaded('captcha')) {
			$input[] = '<img src="'.t3lib_extMgm::siteRelPath('captcha').'captcha/captcha.php" alt="" /><br /><input type="text" size="15" name="spamshield[captcha_response]" value="">'; // TODO: Should be a template like sr_freecap
		}

		$input[] = '<input type="hidden" name="spamshield[uid]" value="'.$data['uid'].'" />';
		$input[] = '<input type="hidden" name="spamshield[auth]" value="'.$data['auth'].'" />';
		$input[] = '<input type="submit" value="'.htmlspecialchars($this->pi_getLL('form.submit')).'" />';
		$form = "<form action='".$data['requesturl']."' method='post' name='frmnoadd2form'>".implode('',$input)."</form>";
		return $form;
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/spamshield/pi1/class.tx_spamshield_pi1.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/spamshield/pi1/class.tx_spamshield_pi1.php']);
}

?>
