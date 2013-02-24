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
 * Class that adds the wizard icon.
 *
 * @author	Ronald Steiner <Ronald.Steiner@AshtangaYoga.info>
 * @package	TYPO3
 * @subpackage	tx_spamshield
 */
class tx_spamshield_pi1_wizicon {
	/**
	 * Processing the wizard items array
	 *
	 * @param	$wizardItems array		$wizardItems: The wizard items
	 * @return	array Modified array with wizard items
	 */
	function proc($wizardItems)	{
		/** @var $LANG language */
		global $LANG;

		$LL = $this->includeLocalLang();

		$wizardItems['plugins_tx_spamshield_pi1'] = array(
			'icon'=>t3lib_extMgm::extRelPath('spamshield').'pi1/ce_wiz.gif',
			'title'=>$LANG->getLLL('pi1_title',$LL),
			'description'=>$LANG->getLLL('pi1_plus_wiz_description',$LL),
			'params'=>'&defVals[tt_content][CType]=list&defVals[tt_content][list_type]=spamshield_pi1'
		);

		return $wizardItems;
	}

	/**
	 * Reads the [extDir]/locallang.xml and returns the $LOCAL_LANG array found in that file.
	 *
	 * @return	array The array with language labels
	 */
	function includeLocalLang()	{
		/** @var $localizationParser t3lib_l10n_parser_Llxml */
		$localizationParser = t3lib_div::makeInstance('t3lib_l10n_parser_Llxml');
		$llFile = t3lib_extMgm::extPath('spamshield') . 'locallang.xml';
		return $localizationParser->getParsedData($llFile, $GLOBALS['LANG']->lang);
	}
}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/spamshield/pi1/class.tx_spamshield_pi1_wizicon.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/spamshield/pi1/class.tx_spamshield_pi1_wizicon.php']);
}

?>