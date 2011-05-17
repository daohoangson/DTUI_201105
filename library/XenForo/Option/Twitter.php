<?php

/**
 * Helper for the Twitter option.
 *
 * @package XenForo_Options
 */
abstract class XenForo_Option_Twitter
{
	public static function verifyTweetOption(array &$option, XenForo_DataWriter $dw, $fieldName)
	{
		if (!empty($option['enabled']))
		{
			if (!XenForo_Model_IdentityService_Twitter::verifyAccountName($option['via'], $error))
			{
				$dw->error($error);
				return false;
			}

			if (!XenForo_Model_IdentityService_Twitter::verifyAccountName($option['related'], $error))
			{
				$dw->error($error);
				return false;
			}
		}

		return true;
	}
}