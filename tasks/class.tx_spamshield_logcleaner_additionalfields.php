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
 * Aditional fields provider class for usage with the Spamshield logcleaner task
 *
 * @author		Hauke Hain <hhpreuss@googlemail.com>
 * @package		TYPO3
 * @subpackage	tx_spamshield
 *
 * $Id$
 */
class tx_spamshield_logcleaner_additionalfields implements tx_scheduler_AdditionalFieldProvider  {

	/**
	 * @param array $taskInfo
	 * @param tx_scheduler_Task $task
	 * @param tx_scheduler_Module $parentObject
	 * @return array
	 */
	public function getAdditionalFields(array &$taskInfo, $task, tx_scheduler_Module $parentObject) {
			$additionalFields = array();

			// Initialize extra field value
		if (empty($taskInfo['age'])) {
			if ($parentObject->CMD == 'add') {
					// In case of new task and if field is empty, set default age
				$taskInfo['age'] = '365';

			} elseif ($parentObject->CMD == 'edit') {
					// In case of edit, and editing a task, set to internal value if not data was submitted already
				$taskInfo['age'] = $task->age;
			} else {
					// Otherwise set an empty value, as it will not be used anyway
				$taskInfo['age'] = '';
			}
		}
			// Write the code for the field age
		$fieldID = 'task_age';
		$values = array(1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,30,60,90,180,270,365);
		$selectValues = '<option></option>';
		foreach( $values as $value ) {
			if( htmlspecialchars($task->age) == $value ) {
				$selected = ' selected="selected"';
			} else {
				$selected = '';
			}
			$selectValues .= sprintf('<option value="%1$d"%3$s>%1$d %2$s</option>', $value, ($value > 1) ? $GLOBALS['LANG']->sL('LLL:EXT:spamshield/locallang.xml:tx_spamshield_log.task.days') : $GLOBALS['LANG']->sL('LLL:EXT:spamshield/locallang.xml:tx_spamshield_log.task.day'), $selected);
		}
		$fieldCode = sprintf('<select name="tx_scheduler[age]" id="%s" size="1">%s</select>', $fieldID, $selectValues);
		$additionalFields[$fieldID] = array(
			'code'     => $fieldCode,
			'label'    => 'LLL:EXT:spamshield/locallang.xml:tx_spamshield_log.task.age',
			'cshKey'   => 'spamshield',
			'cshLabel' => 'age'
		);

		return $additionalFields;
	}

	/**
	 * @param array $submittedData
	 * @param tx_scheduler_Module $parentObject
	 * @return bool
	 */
	public function validateAdditionalFields(array &$submittedData, tx_scheduler_Module $parentObject) {
		$result = TRUE;
		$submittedData['age'] = intval($submittedData['age']);

		if (empty($submittedData['age'])) {
			$parentObject->addMessage($GLOBALS['LANG']->sL('LLL:EXT:spamshield/locallang.xml:tx_spamshield_log.task.noage'), t3lib_FlashMessage::ERROR);
			$result = FALSE;
		}

		return $result;
	}

	/**
	 * @param array $submittedData
	 * @param tx_scheduler_Task $task
	 */
	public function saveAdditionalFields(array $submittedData, tx_scheduler_Task $task) {
		$task->age = $submittedData['age'];
	}
}

if (defined('TYPO3_MODE') && isset($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/spamshield/tasks/class.tx_spamshield_logcleaner_additionalfields.php'])) {
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/spamshield/tasks/class.tx_spamshield_logcleaner_additionalfields.php']);
}

?>