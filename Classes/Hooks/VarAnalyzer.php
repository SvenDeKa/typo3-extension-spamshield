<?php
namespace SpamshieldTeam\Spamshield\Hooks;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009-2016  Dr. Ronald Steiner <Ronald.Steiner@googlemail.com>, Christian Seifert <christian-f-seifert@gmx.de>
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

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\ArrayUtility;

/**
 * Hook for POST / GET variables checking
 */
class VarAnalyzer
{
    /**
     * @var bool
     */
    protected $params = false; // Complete TS-Config

    /**
     * @var bool
     */
    protected $pObj = false; // pObj at time of hook call

    /**
     * @var bool
     */
    protected $conf = false; // config from ext_conf_template.txt

    /**
     * @var
     */
    protected $getParams; // GET variables

    /**
     * @var
     */
    protected $postParams; // POST variables

    /**
     * @var
     */
    protected $gpParams; // POST and GET variables

    /**
     * @var array
     */
    protected $spamReason = array(); // description of the error

    /**
     * @var int
     */
    protected $spamWeight = 0; // weight of the spam

    /** @var tx_srfreecap_pi2 */
    public $freeCap = null;

    /**
     * Hook page id lookup before rendering the content.
     *
     * @param    $params object        $_params: parameter array
     * @param    $pObj object        $pObj: partent object
     * @return    void
     */
    public function main(&$params, &$pObj)
    {
        $this->params = & $params;
        $this->pObj = & $pObj;
        $this->conf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['spamshield']);

        // set global variables
        $this->getParams = GeneralUtility::_GET();
        $this->postParams = GeneralUtility::_POST();
        
        $this->gpParams = $this->getParams;
        ArrayUtility::mergeRecursiveWithOverrule($this->gpParams, $this->postParams);

        // check if data already is verified with the spamshield auth
        if ($this->gpParams['spamshield']['uid'] && $this->gpParams['spamshield']['auth']) {
            $res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
                '*',
                'tx_spamshield_log',
                'uid=' . intval($this->gpParams['spamshield']['uid']) . ' AND deleted=0'
            );
            if ($GLOBALS['TYPO3_DB']->sql_num_rows($res)) { # no UID
                $data = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
                if ($this->checkAuthCode($this->gpParams['spamshield']['auth'], $data) && $this->checkCaptcha(
                    $this->gpParams['spamshield']['captcha_response']
                )
                ) {
                    unset($data['auth']);
                    $data['tstamp'] = time();
                    $data['solved'] = 1;
                    $GLOBALS['TYPO3_DB']->exec_UPDATEquery('tx_spamshield_log', 'uid=' . intval($data['uid']), $data);
                    return; // bypass rest of spamshield. Input verified with captcha
                } else {
                    $this->stopOutputAndRedirect($data);
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
            if (!$this->conf['secondLine']) {
                $this->conf['secondLine'] = 'useragent,1;referer,1;javascript,1;honeypot,1;';
            }
            $this->check($this->conf['secondLine']);
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
                $data = $this->dbLog(); // DB-Logging
            }
            // option for second line of defence to verify with captcha page
            if (((int) $this->conf['redirecttopid']) > 0 && $this->checkFormSubmission()) {
                $this->stopOutputAndRedirect($data);
            } // block completely - only way for first line of defence up to now ....
            // verifying a user agent / user configuration with captcha could be doable
            // but first line of spamshield anyway has view false positives - and should have!!
            else {
                $this->stopOutput();
            }
        } else {
            return; // no spam detected
        }
    }

    /**
     * Walks one rule set of checks.
     * If a check is false, gives the corresponding weight
     *
     * @param string $ruleSet a rule set: rule1,weight;rule2,weight
     * @return void
     */
    protected function check($ruleSet)
    {
        $rules = explode(';', $ruleSet);
        
        foreach ($rules as $rule) {
            list($function, $weight) = GeneralUtility::trimExplode(',', $rule);
            $function = trim($function);
            $weight = (int) $weight;

            if ($weight > 0 && method_exists($this, $function)) {
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
     * @return boolean
     */
    protected function checkFormSubmission()
    {
        if ($this->postParams['spamshield']['mark']) {
            return true; // a form has been submitted
        }
        if (is_array($this->postParams) && sizeof($this->postParams) != 0) {
            return true; // a form has been submitted
        }
        return false; // regular page request with no form data
    }

    /**
     * Checks a given auth code
     *
     * @param $authCode int auth code
     * @param $row array DB-Row to check the auth code
     * @return boolean
     */
    protected function checkAuthCode($authCode, &$row)
    {
        $authCodeFields = ($this->conf['authcodeFields'] ? $this->conf['authcodeFields'] : 'uid');
        $ac = GeneralUtility::stdAuthCode($row, $authCodeFields);
        if ($ac == $authCode) {
            $row['auth'] = $authCode;
            return true;
        } else {
            return false;
        }
    }

    /**
     * Checks Captcha value
     *
     * @param string $captcharesponse Captcha response
     * @return boolean
     */
    protected function checkCaptcha($captcharesponse)
    {
        if (ExtensionManagementUtility::isLoaded('sr_freecap')) {
            $this->freeCap = t3lib_div::makeInstance('SJBR\\SrFreecap\\PiBaseApi');

            if (is_object($this->freeCap)) {
                return $this->freeCap->checkWord($captcharesponse);
            }
        } elseif (ExtensionManagementUtility::isLoaded('captcha')) {
            session_start();

            if ($captcharesponse && $captcharesponse === $_SESSION['tx_captcha_string']) {
                $_SESSION['tx_captcha_string'] = '';
                return true;
            }

            $_SESSION['tx_captcha_string'] = '';
        }

        return false;
    }

    /**
     * Stops TYPO3 output and redirects to another TYPO3 page.
     *
     * @param array $data the DB-Row of the spam log
     * @param string|int $authCodeFields uid of the fields used for auth code
     * @return void
     */
    protected function stopOutputAndRedirect($data, $authCodeFields = 'uid')
    {
        $param = '';
        
        if ($this->gpParams['L']) {
            $param .= '&L=' . $this->gpParams['L'] . ' ';
        }
        
        $param .= '&uid=' . $data['uid'] . ' ';
        $param .= '&auth=' . GeneralUtility::stdAuthCode($data, $authCodeFields) . ' ';
        // redirect to captcha check / result page
        $url = GeneralUtility::getIndpEnv('TYPO3_SITE_URL') . 'index.php?id=' . $this->conf['redirecttopid'] . $param;

        // sending a normal header tricks spam robots. They think everything is fine
        header("HTTP/1.0 301 Moved Permanently");
        header('Location: ' . $url);
        exit();
    }

    /**
     * Stops TYPO3 output and shows an error page.
     * - derived from mh_httpbl
     *
     * @return void
     */
    protected function stopOutput()
    {
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
		' . $this->conf['message'] . '
    </body>
</html>';

        // Prevent caching on the client side
        header('Expires: 0');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
        
        die($output);
    }

    /**
     * Put a log entry in the DB if spam is detected
     *
     * @return boolean
     */
    protected function dbLog()
    {
        $ref = $this->gpParams['refpid'] ? $this->gpParams['refpid'] : $GLOBALS['TSFE']->id;

        /*  mask recursive */
        $this->mask(new \RecursiveArrayIterator($this->postParams), 'pass');
        /** @var \TYPO3\CMS\Core\Database\DatabaseConnection $db */
        $db = $GLOBALS['TYPO3_DB'];
        
        $data = array(
            'pid' => (int)$this->conf['logpid'], // spam-log storage page
            'tstamp' => time(),
            'crdate' => time(),
            'spamWeight' => $this->spamWeight,
            'spamReason' => implode(',', $this->spamReason),
            'postvalues' => serialize($this->postParams),
            'getvalues' => serialize($this->getParams),
            'pageid' => $ref,
            'requesturl' => GeneralUtility::getIndpEnv('TYPO3_REQUEST_URL'),
            'ip' => GeneralUtility::getIndpEnv('REMOTE_ADDR'),
            'useragent' => GeneralUtility::getIndpEnv('HTTP_USER_AGENT'),
            'referer' => GeneralUtility::getIndpEnv('HTTP_REFERER'),
            'solved' => 0
        );

        $db->exec_INSERTquery('tx_spamshield_log', $data); // DB entry
        $data['uid'] = $db->sql_insert_id();

        return $data;
    }

    /**
     * Traverse / iterates POSTparams array recursive  for key $needle
     * and mask values , primary for password fields
     *
     * @param RecursiveArrayIterator $iterator
     * @param string $needle to search
     * @return void
     * @modify $this->postParams
     */
    protected function mask(\RecursiveArrayIterator $iterator, $needle = 'pass')
    {

        while ($iterator->valid()) {
            if ($iterator->hasChildren()) {
                $this->mask($iterator->getChildren(), $needle);
            } else {
                if (stripos($iterator->key(), $needle) !== false) {
                    $this->postParams[$iterator->key()] = 'xxxxx';
                }
            }
            $iterator->next();
        }
    }

#####################################################
## Functions for first Line Defence                ##
#####################################################   
    /**
     *    from mh_httpbl
     *    -5 => 'localhost'
     *    - 3 => 'misc = here possibly whitelist / blacklist'
     *    -2 => 'no REMOTE_ADDR = no request possible'
     *    -1 => 'no access key = no request possible'
     *    0 => 'Search Engine',
     *    1 => 'Suspicious',
     *    2 => 'Harvester',
     *    3 => 'Suspicious & Harvester',
     *    4 => 'Comment Spammer',
     *    5 => 'Suspicious & Comment Spammer',
     *    6 => 'Harvester & Comment Spammer',
     *    7 => 'Suspicious & Harvester & Comment Spammer'
     *
     *    httpbl recommends to block >= 2
     *
     * @return boolean
     */
    protected function httpbl()
    {
        if (empty($this->conf['accesskey'])) {
            $type = -1;
        } elseif (empty($_SERVER['REMOTE_ADDR'])) {
            $type = -2;
        } else {
            $type = -3;
            $codes = array( # codes used by httpbl.org
                0 => 'Search Engine',
                1 => 'Suspicious',
                2 => 'Harvester',
                3 => 'Suspicious &amp; Harvester',
                4 => 'Comment Spammer',
                5 => 'Suspicious &amp; Comment Spammer',
                6 => 'Harvester &amp; Comment Spammer',
                7 => 'Suspicious &amp; Harvester &amp; Comment Spammer'
            );
            $domain = 'dnsbl.httpbl.org';
            $request = $this->conf['accesskey'] . '.' . implode(
                '.',
                array_reverse(explode('.', $_SERVER['REMOTE_ADDR']))
            ) . '.' . $domain;
            $result = gethostbyname($request);
            $first = null;
            if ($result != $request) {
                list($first, $days, $score, $type) = explode(
                    '.',
                    $result
                ); // $type = one of the $codes; higher $score = more active bot
            }
            if ($first != 127 || !array_key_exists($this->conf['type'], $codes)) {
                $type = -5;
            }
        }
        if ($type >= $this->conf['type']) {
            return true; // = Spam
        } else {
            return false; // = no Spam
        }
    }

#####################################################
## functions for either first or Second Line       ##
#####################################################
    /**
     * useragant
     *
     * Every browser sends a HTTP_USER_AGENT value to a server.
     *    So a missing HTTP_USER_AGENT value almost always indicates a spammer bot.
     *
     * @return boolean
     */
    protected function useragent()
    {
        if (GeneralUtility::getIndpEnv('HTTP_USER_AGENT') == '') {
            return true; // = spam
        }
        return false; // no spam;
    }

    /**
     * referer
     *
     * The most of browsers (all modern browsers) send a HTTP_REFERER value,
     *    ... which would contain the submitted form URL.
     *   ... which therefore should be from same domain.
     *  Whereas clever bots send this value, a missing HTTP_REFERER value could mean a bot submitting.
     *  Note. There are several firewall and security products which block HTTP_REFERER by default.
     *  So, none of these people could send a message if you block posting without HTTP_REFERER.
     *
     * @return boolean
     */
    protected function referer()
    {
        if ($this->conf['whitelist']) {
            $whiteList = explode(',', $this->conf['whitelist']);
            foreach ($whiteList as $white) {
                $white = trim($white);
                if (strpos(GeneralUtility::getIndpEnv('HTTP_REFERER'), $white) !== false) {
                    return false; // no spam
                }
            }
        } // checking for empty referers or referers that are external of the website
        elseif ((!GeneralUtility::getIndpEnv('HTTP_REFERER') || GeneralUtility::getIndpEnv('HTTP_REFERER') == '')
            ||
            ($GLOBALS['TSFE']->tmpl->setup['config.']['baseUrl'] &&
                !strstr(GeneralUtility::getIndpEnv('HTTP_REFERER'), $GLOBALS['TSFE']->tmpl->setup['config.']['baseURL']))
            ||
            ($GLOBALS['TSFE']->tmpl->setup['config.']['absRefPrefix'] &&
                !strstr(
                    GeneralUtility::getIndpEnv('HTTP_REFERER'),
                    $GLOBALS['TSFE']->tmpl->setup['config.']['absRefPrefix']
                ))
        ) {
            return true; // spam
        }

        return false; // no spam
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
    protected function javascript()
    {
        // no form data send => new users are welcome and have no cookie
        return false; // no spam - this check does not work proper in some situations - the cookie gets lost on page changes
###
# to do: find a better javascript check
###
//      if (!$_COOKIE['spamshield']) {
//          return TRUE; // spam
//      }
//
//      return FALSE; // no spam
    }

    /**
     * checks if cookie is available
     * some bots don't accept cookies normally
     *
     * @return  boolean
     */
    protected function cookie()
    {
        $cookieName = 'fe_typo_user';
        if (trim($GLOBALS['TYPO3_CONF_VARS']['FE']['cookieName']) !== '') {
            $cookieName = $GLOBALS['TYPO3_CONF_VARS']['FE']['cookieName'];
        }

        if (!$_COOKIE[$cookieName]) {
            return true; // spam
        }

        return false; // no spam
    }

    /**
     * checks if honey pot fields are filled in
     * bots normally don't read CSS / Java and fill in everything.
     *
     * @return  boolean
     */
    protected function honeypot()
    {
        if (!$this->conf['honeypot']) {
            $this->conf['honeypot'] = 'email,e-mail,name,first-name';
        }

        foreach (explode(',', $this->conf['honeypot']) as $name) {
            if ($this->gpParams[$name]) {
                return true; // spam
            }
        }
        return false; // no spam
    }
}
