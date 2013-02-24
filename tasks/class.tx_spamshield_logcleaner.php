<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2011 Hauke Hain <hhpreuss@googlemail.com>
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
 * Class "tx_spamshield_logcleaner" provides db procedures to delete log entries
 *
 * @author		Hauke Hain <hhpreuss@googlemail.com>
 * @package		TYPO3
 * @subpackage	tx_spamshield
 *
 * $Id$
 */
class tx_spamshield_logcleaner extends tx_scheduler_Task {
	var $table = 'tx_spamshield_log';
	var $age;
	var $sqlWhereClause;

	public function execute() {
		$success = $this->delete();
		t3lib_div::sysLog('[tx_spamshield_logcleaner]: "' . $GLOBALS['TYPO3_DB']->sql_affected_rows . '" spamlogs older than "' . $this->getAgeInDaysAsString() . ' were deleted.', 'spamshield', 0);

		return $success;
	}

	protected function delete(){
		if (((bool) $GLOBALS['TYPO3_DB']->exec_DELETEquery($this->table, $this->getSqlWhereClause()))) {
			return TRUE;
		} else {
			t3lib_div::sysLog('[tx_spamshield_logcleaner]: Deleting old spamlogs failed using "' . $this->getSqlWhereClause() . '".', 'spamshield', 3);
			return FALSE;
		}
	}

	public function getAgeInSeconds() {
		return 86400*$this->age;
	}

	public function getAgeInDaysAsString() {
		return($this->age > 1) ? $this->age . ' ' . $GLOBALS['LANG']->sL('LLL:EXT:spamshield/locallang.xml:tx_spamshield_log.task.days') : $this->age . ' ' . $GLOBALS['LANG']->sL('LLL:EXT:spamshield/locallang.xml:tx_spamshield_log.task.day');
	}

	public function getSqlWhereClause() {
		if (empty($this->sqlWhereClause)) {
			$this->sqlWhereClause = $GLOBALS['TCA'][$this->table]['ctrl']['crdate'] . ' + ' . $this->getAgeInSeconds() . ' < ' . time();;
		}

		return $this->sqlWhereClause;
	}
}

if (defined('TYPO3_MODE') && isset($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/spamshield/taks/class.tx_spamshield_logcleaner.php'])) {
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/spamshield/tasks/class.tx_spamshield_logcleaner.php']);
}

?>