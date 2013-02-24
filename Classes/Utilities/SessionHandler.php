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
 * Utilities for storing and checking session values like form submit
 * timestamps
 */
class SessionHandler implements \TYPO3\CMS\Core\SingletonInterface {

	const SESSION_KEY_TIMESTAMPS = 'tx_spamshield_timestamps';

	/**
	 * @var \Tx\Spamshield\Utilities\ConfigurationUtilities
	 */
	protected $configUtils;

	/**
	 * @var \TYPO3\CMS\Frontend\Authentication\FrontendUserAuthtenication
	 */
	protected $feUser;

	/**
	 * @var array
	 */
	protected $sessionTimestamps;

	/**
	 * Initializes the feUserAuth object
	 */
	public function __construct() {
		$this->configUtils = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('Tx\\Spamshield\\Utilities\\ConfigurationUtilities');
		$this->feUser = $GLOBALS['TSFE']->fe_user;
		$this->sessionTimestamps = NULL;
	}

	/**
	 * Initializes the current timestamps and create a new one with a
	 * random id. The ramdom id is returned and should be used in the form.
	 *
	 * @return int the session timestamp ID
	 */
	public function createOrUpdateSessionTimestamp() {

		$formWasSubmitted = FALSE;
		$id = NULL;

		if ($id = $this->getSubmittedValidTimestampId()) {
			$formWasSubmitted = TRUE;
		} else {
			$id = $this->generateNewTimestampId();
		}

		$newTimestamp = $GLOBALS['EXEC_TIME'];

		// if the form was already submitted once, the user does not need to wait again
		if ($formWasSubmitted) {
			$newTimestamp = $newTimestamp - $this->configUtils->getTimestampMinAge();
		}

		$this->sessionTimestamps[$id] = $newTimestamp;

		$this->purgeOldTimestampsAndSave();

		return $id;
	}

	/**
	 * Returns the submitted timestamp ID if it is valid, otherwise
	 * FALSE is returned
	 *
	 * @return bool|int
	 */
	public function getSubmittedValidTimestampId() {

		$submittedId = $this->getSubmittedTimestampId();

		if ($submittedId === FALSE) {
			return FALSE;
		}

		if ($this->isValidTimestamp($submittedId)) {
			return $submittedId;
		} else {
			return FALSE;
		}
	}

	/**
	 * Checks if a timestamp was stored with the given id. If a timestamp
	 * was found it will be returned. Otherwise NULL is returned
	 *
	 * @param int $id The id for which the timestamp should be returned
	 * @return bool TRUE if the timestamp for the given ID is valid
	 */
	public function isValidTimestamp($id) {

		$id = intval($id);

		if ($id <= 0) {
			return FALSE;
		}

		$this->purgeOldTimestampsAndSave();

		if (!array_key_exists($id, $this->sessionTimestamps)) {
			return FALSE;
		}

		$timestamp = $this->sessionTimestamps[$id];
		$minTimestampAge = $this->configUtils->getTimestampMinAge();
		$minTimestampValue = $timestamp + $minTimestampAge;

		if ($GLOBALS['EXEC_TIME'] > $minTimestampValue) {
			return TRUE;
		} else {
			return FALSE;
		}
	}

	/**
	 * Generates a unique id based on a random string and the users IP
	 * address
	 *
	 * @return int
	 */
	protected function generateNewTimestampId() {
		$prefix = \TYPO3\CMS\Core\Utility\GeneralUtility::getRandomHexString(20);
		$prefix .= \TYPO3\CMS\Core\Utility\GeneralUtility::getIndpEnv('REMOTE_ADDR');
		$id = uniqid($prefix, TRUE);
		return \TYPO3\CMS\Core\Utility\GeneralUtility::md5int($id);
	}

	/**
	 * Initializes the sessionTimestamps array with an empty array or
	 * with the array stored in the user session if available.
	 */
	protected function initSessionTimestamps() {

		if (is_array($this->sessionTimestamps)) {
			return;
		}

		$timestamps = $this->feUser->getKey('ses', self::SESSION_KEY_TIMESTAMPS);

		if (is_array($timestamps)) {
			$this->sessionTimestamps = $timestamps;
		} else {
			$this->sessionTimestamps = array();
		}
	}

	/**
	 * Removes all expired timestamps form the array and stores the array
	 * in the session
	 */
	protected function purgeOldTimestampsAndSave() {

		$this->initSessionTimestamps();

		$sessionTimestamps = $this->sessionTimestamps;
		$maxTimestampAge = $this->configUtils->getTimestampMaxAge();
		$maxTimestampValue = $GLOBALS['EXEC_TIME'] + $maxTimestampAge;

		foreach ($sessionTimestamps as $id => $timestamp) {

			if ($timestamp <= $maxTimestampValue) {
				continue;
			}

			unset($this->sessionTimestamps[$id]);
		}

		$this->saveTimestampsInSession();
	}

	/**
	 * Stores the current timestamp array in the user session
	 */
	protected function saveTimestampsInSession() {
		$this->feUser->setAndSaveSessionData(self::SESSION_KEY_TIMESTAMPS, $this->sessionTimestamps);
	}

	/**
	 * Returns the timestamp ID that the user submitted in the POST data
	 * or 0 if none was set.
	 *
	 * @return bool|int
	 */
	protected function getSubmittedTimestampId() {

		$postData = \TYPO3\CMS\Core\Utility\GeneralUtility::_POST('spamshield');

		if (array_key_exists('session_timestamp_id', $postData)) {
			return $postData['session_timestamp_id'];
		} else {
			return FALSE;
		}
	}
}