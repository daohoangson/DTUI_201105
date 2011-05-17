<?php

class XenForo_DataWriter_Helper_Option
{
	/**
	 * Verifies that the Google Analytics Web Property ID is valid, if specified.
	 * See https://www.google.com/support/googleanalytics/bin/answer.py?answer=113500
	 *
	 * @param string $wpId
	 * @param XenForo_DataWriter $dw
	 * @param string $fieldName
	 *
	 * @return boolean
	 */
	public static function verifyGoogleAnalyticsWebPropertyId(&$wpId, XenForo_DataWriter $dw, $fieldName)
	{
		if ($wpId !== '' && !preg_match('/^UA-\d+-\d+$/', $wpId))
		{
			$dw->error(new XenForo_Phrase('please_enter_your_google_analytics_web_property_id_in_format'), $fieldName);
			return false;
		}

		return true;
	}
}