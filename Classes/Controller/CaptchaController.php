<?php
namespace SpamshieldTeam\Spamshield\Controller;

/**
 * *************************************************************
 *
 * Copyright notice
 *
 * (c) 2011-2016 Ronald Steiner <Ronald.Steiner@AshtangaYoga.info>, Christian Seifert <christian-f-seifert@gmx.de>
 *
 * All rights reserved
 *
 * This script is part of the TYPO3 project. The TYPO3 project is
 * free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * The GNU General Public License can be found at
 * http://www.gnu.org/copyleft/gpl.html.
 *
 * This script is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * This copyright notice MUST APPEAR in all copies of the script!
 * *************************************************************
 */

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * Plugin 'spamshield: Auth-Code-Handler' for the 'spamshield' extension.
 */
class CaptchaController extends ActionController
{
    /**
     * @var ConfigurationManagerInterface
     */
    protected $configurationManager;

    /**
     * Contains the settings of the current extension
     *
     * @var array
     * @api
     */
    protected $settings;


    /**
     * @param ConfigurationManagerInterface $configurationManager
     * @return void
     */
    public function injectConfigurationManager(ConfigurationManagerInterface $configurationManager)
    {
        $this->configurationManager = $configurationManager;
        $this->settings = $this->configurationManager
            ->getConfiguration(ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS);
    }

    /**
     * The main action of the plugin
     *
     * @return string
     */
    public function showAction()
    {
        $getParameters = GeneralUtility::_GET();

        // todo: move HTML into fluid templates
        if (!ExtensionManagementUtility::isLoaded('sr_freecap') && !ExtensionManagementUtility::isLoaded('captcha')) {
            $message = LocalizationUtility::translate('message.nocaptcha', 'spamshield');
            $content = '<div class="message red">' . htmlspecialchars($message) . '</div>';
        } elseif (!$getParameters['uid'] || !$getParameters['auth']) {
            $message = LocalizationUtility::translate('message.wronglink', 'spamshield');
            $content = '<div class="message red">' . htmlspecialchars($message) . '</div>';
        } else {
            $res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
                '*',
                'tx_spamshield_log',
                'uid=' . intval($getParameters['uid']) . ' AND deleted=0 AND solved=0'
            );
            if (!$GLOBALS['TYPO3_DB']->sql_num_rows($res)) { # no UID
                $message = LocalizationUtility::translate('message.wronguid', 'spamshield');
                $content = '<div class="message red">' . htmlspecialchars($message) . '</div>';
            } else {
                $data = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
                if (!$this->checkAuthCode($getParameters['auth'], $data)) {
                    $message = LocalizationUtility::translate('message.wrongauth', 'spamshield');
                    $content = '<div class="message red">' . htmlspecialchars($message) . '</div>';
                } else {
                    $content = $this->renderForm($data);
                }
            }
        }

        return $content;
    }

    /**
     * Checks a given auth code
     *
     * @param int $authCode auth code
     * @param array $row DB-Row to check the auth code
     * @return boolean
     */
    protected function checkAuthCode($authCode, &$row)
    {
        $authCodeFields = ($this->settings['authcodeFields'] ? $this->settings['authcodeFields'] : 'uid');
        $ac = GeneralUtility::stdAuthCode($row, $authCodeFields);
        if ($ac == $authCode) {
            $row['auth'] = $authCode;
            return true;
        } else {
            return false;
        }
    }

    /**
     * renders a form
     *
     * @param    array $data the DB-Row of the spam log
     * @return    string    the form
     */
    protected function renderForm($data)
    {
        if ($data['postvalues'] !== '') {
            // stripslashes needed because data is stored in DB with: mysql_escape_string
            $post = unserialize(stripslashes($data['postvalues']));
            unset($post['spamshield']['uid']);
            unset($post['spamshield']['auth']);
            foreach ($post as $key => $val) {
                if (!is_array($val)) {
                    $input[] = '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars(
                            $val
                        ) . '" />';
                } else {
                    foreach ($val as $a => $b) {
                        if (!is_array($b)) {
                            $input[] = '<input type="hidden" name="' . htmlspecialchars($key) . '[' . htmlspecialchars(
                                    $a
                                ) . ']" value="' . htmlspecialchars($b) . '" />';
                        } else {
                            foreach ($b as $x => $y) {
                                if (!is_array($y)) {
                                    $input[] = '<input type="hidden" name="' . htmlspecialchars(
                                            $key
                                        ) . '[' . htmlspecialchars($a) . '][' . htmlspecialchars(
                                            $x
                                        ) . ']" value="' . htmlspecialchars($y) . '" />';
                                } else {
                                    foreach ($y as $c => $d) {
                                        $input[] = '<input type="hidden" name="' . htmlspecialchars(
                                                $key
                                            ) . '[' . htmlspecialchars($a) . '][' . htmlspecialchars(
                                                $x
                                            ) . '][' . htmlspecialchars($c) . ']" value="' . htmlspecialchars(
                                                $d
                                            ) . '" />';
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // Captcha
        if (ExtensionManagementUtility::isLoaded('sr_freecap')) {
            /** @var \SJBR\SrFreecap\PiBaseApi freeCap */
            $freeCap = GeneralUtility::makeInstance('SJBR\\SrFreecap\\PiBaseApi');

            if (is_object($freeCap)) {
                /** @var ContentObjectRenderer $contentObject */
                $contentObject = $this->configurationManager->getContentObject();
                $template = $contentObject->getSubpart(
                    $contentObject->fileResource('EXT:spamshield/Resources/Private/Templates/sr_freecap.html'),
                    '###CAPTCHA_INSERT###'
                ); // TODO: overwrite template path with TypoScript
                $input[] = $contentObject->substituteMarkerArray($template, $freeCap->makeCaptcha());
            }
        } elseif (ExtensionManagementUtility::isLoaded('captcha')) {
            $input[] = '<img src="' . ExtensionManagementUtility::siteRelPath(
                    'captcha'
                ) . 'captcha/captcha.php" alt="" /><br /><input type="text" size="15" name="spamshield[captcha_response]" value="">'; // TODO: Should be a template like sr_freecap
        }

        $submitLabel = LocalizationUtility::translate('form.submit', 'spamshield');

        $input[] = '<input type="hidden" name="spamshield[uid]" value="' . $data['uid'] . '" />';
        $input[] = '<input type="hidden" name="spamshield[auth]" value="' . $data['auth'] . '" />';
        $input[] = '<input type="submit" value="' . htmlspecialchars($submitLabel) . '" />';
        $form = "<form action='" . $data['requesturl'] . "' method='post' name='frmnoadd2form'>" . implode(
                '',
                $input
            ) . "</form>";

        return $form;
    }
}
