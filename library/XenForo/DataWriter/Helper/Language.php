<?php

class XenForo_DataWriter_Helper_Language
{
	/**
	 * Verifies that the provided language is valid
	 *
	 * @param integer $languageId
	 *
	 * @return boolean
	 */
	public static function verifyLanguageId($languageId, XenForo_DataWriter $dw, $fieldName = false)
	{
		if ($languageId === 0)
		{
			// explicitly set to 0 - use system default
			return true;
		}

		if ($dw->getModelFromCache('XenForo_Model_Language')->getLanguageById($languageId))
		{
			return true;
		}

		$dw->error(new XenForo_Phrase('please_select_valid_language'), $fieldName);
		return false;
	}
}