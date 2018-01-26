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

/**
 * Hook for HTML-form and page modification
 */
class FormModifier
{
    /**
     * Hook output after rendering the content.
     * - no cached pages
     *
     * @param object $params parameter array
     * @param object $that parent object
     * @return void
     */
    public function intPages(&$params, &$that)
    {
        if (!$GLOBALS['TSFE']->isINTincScript()) {
            return;
        }

        $this->main($params['pObj']->content, $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_spamshield.']);
    }

    /**
     * Hook output after rendering the content.
     * - cached pages
     *
     * @param object $params $_params: parameter array
     * @param object $that $pObj: parent object
     * @return void
     */
    public function noIntPages(&$params, &$that)
    {
        if ($GLOBALS['TSFE']->isINTincScript()) {
            return;
        }

        $this->main($params['pObj']->content, $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_spamshield.']);
    }

    /**
     * Main Function:
     * - search for forms, mark them with a honey pot
     *
     * @param string $html the output html code
     * @param array $conf Configuration array
     * @return void
     */
    public function main(&$html, &$conf)
    {
        if ($conf['add2forms'] && strstr($html, '<form')) {
            $newForms = $orgForms = $this->getForms($html);

            for ($i = 0; $i < sizeof($newForms); $i++) {
                if (!$this->enableOff($newForms[$i], $conf['add2forms.']['off.'])) {
                    $this->add2forms($newForms[$i], $conf['add2forms.']);
                }
            }

            $html = str_replace($orgForms, $newForms, $html);
        }
    }

    /**
     * Get all forms out of $this->html
     *
     * @param string $html
     * @return array all forms in $this->body
     */
    protected function getForms(&$html)
    {
        preg_match_all("/(?s)(<[ \n\r]*form[^>]*>.*?<[ \n\r]*\/form[^>]*>)/is", $html, $matches);
        return $matches[0];
    }

    /**
     * include a fields to a form
     * e.g. inclusion of markers (required) and honeypots (have to be empty)
     *
     * @param string $form html code of the forms.
     * @param array $conf
     * @return void
     */
    protected function add2forms(&$form, $conf)
    {
        $newInputs = $inputs = $this->getInputs($form);
        if ($conf['position'] == "rnd") {
            foreach ($conf['fields.'] as $honeyPot) {
                $changePos = mt_rand(0, sizeof($newInputs) - 1);
                if (mt_rand(0, 1) == 1) {
                    $newInputs[$changePos] = $honeyPot . $newInputs[$changePos];
                } else {
                    $newInputs[$changePos] = $newInputs[$changePos] . $honeyPot;
                }
            }
        } elseif ($conf['position'] == 'end') {
            $newInputs[sizeof($newInputs) - 1] = $newInputs[sizeof($newInputs) - 1] . implode('', $conf['fields.']);
        } elseif ($conf['position'] == 'start') {
            $newInputs[0] = implode('', $conf['fields.']) . $newInputs[0];
        } elseif ($conf['position'] == 'start-end') {
            $i = 0;
            foreach ($conf['fields.'] as $honeypot) {
                if ($i % 2) {
                    $newInputs[0] = $honeypot . $newInputs[0];
                } else {
                    $newInputs[sizeof($newInputs) - 1] = $newInputs[sizeof($newInputs) - 1] . $honeypot;
                }
                $i++;
            }
        }
        
        $form = str_replace($inputs, $newInputs, $form);
    }

    /**
     * regex rules for disabling for single forms
     *
     * @param string $form the html form
     * @param array $conf config array with regex rules
     * @return boolean
     */
    protected function enableOff($form, $conf)
    {
        if (!is_array($conf)) {
            return false;
        }
        
        foreach ($conf as $pattern) {
            if (preg_match('/' . preg_quote($pattern, '/') . '/is', $form)) {
                return true;
            }
        }

        return false;
    }

    /**
     * get all input fields out of a form
     * help function for honeypots
     *
     * @param string $form html code of the form.
     * @return array all input fields in the form
     */
    protected function getInputs($form)
    {
        preg_match_all("/(?s)(<[ \n\r]*input.*?[ \n\r]*[^>]*>)/is", $form, $matches);
        return $matches[0];
    }
}
