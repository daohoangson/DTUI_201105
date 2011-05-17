<?php

class XenForo_DataWriter_Helper_Uri
{
	/**
	 * Verifies that the provided string is a valid URL
	 *
	 * @param string $url
	 *
	 * @return boolean
	 */
	public static function verifyUri($uri, XenForo_DataWriter $dw, $fieldName = false)
	{
		if (Zend_Uri::check($uri))
		{
			return true;
		}

		$dw->error(new XenForo_Phrase('please_enter_valid_url'), $fieldName);
		return false;
	}
}
