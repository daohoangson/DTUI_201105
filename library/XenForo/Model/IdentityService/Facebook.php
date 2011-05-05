<?php

class XenForo_Model_IdentityService_Facebook extends XenForo_Model_IdentityService_Abstract
{
	protected function _getIdentityServiceId()
	{
		return 'facebook';
	}

	static public function verifyAccountName(&$accountName, &$error)
	{
		if ($accountName === '')
		{
			return true;
		}

		if (preg_match('#^https?://www\.facebook\.com/(\#!/)?profile\.php\?id=(?P<id>\d+)#i', $accountName, $match))
		{
			$accountName = $match['id'];
		}
		else if (preg_match('#^https?://www\.facebook\.com/(\#!/)?(?P<id>[a-z0-9\.]+)#i', $accountName, $match))
		{
			if (substr($match['id'], -4) != '.php')
			{
				$accountName = $match['id'];
			}
		}

		if (!preg_match('/^[a-z0-9\.]+$/i', $accountName))
		{
			$error = new XenForo_Phrase('please_enter_valid_facebook_username_using_alphanumeric_dot_numbers');
			return false;
		}

		return true;
	}
}