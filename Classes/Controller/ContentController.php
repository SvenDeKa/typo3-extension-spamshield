<?php
namespace Tx\Spamshield\Controller;

/**
 * Controller containing functions used in content elements.
 */
class ContentController extends \TYPO3\CMS\Frontend\Plugin\AbstractPlugin{

	public function renderSpamProtectedContent() {
		return 'test';
	}

	public function spamProtectForms($content, $config) {
		return $content;
	}

	public function isSpamOrSpamPluginActive($content, $config) {
		return FALSE;
	}

	public function isSpam($content, $config) {
		return FALSE;
	}

	public function enableSpamCheck() {
		return TRUE;

		$postVariables = \TYPO3\CMS\Core\Utility\GeneralUtility::_POST();
		$enableSpamCheck = FALSE;
		if (count($postVariables) > 0 ) {
			$enableSpamCheck = TRUE;
		}
		return $enableSpamCheck;
	}
}