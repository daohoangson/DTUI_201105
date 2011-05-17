<?php

class XenForo_Model_IdentityService_Twitter extends XenForo_Model_IdentityService_Abstract
{
	protected function _getIdentityServiceId()
	{
		return 'twitter';
	}

	static public function verifyAccountName(&$accountName, &$error)
	{
		if ($accountName === '')
		{
			return true;
		}

		if ($accountName[0] == '@')
		{
			$accountName = substr($accountName, 1);
		}

		if (!preg_match('/^[a-z0-9_]+$/i', $accountName))
		{
			$error = new XenForo_Phrase('please_enter_valid_twitter_name_using_alphanumeric');
			return false;
		}

		return true;
	}
}