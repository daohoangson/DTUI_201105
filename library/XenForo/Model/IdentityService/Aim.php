<?php

class XenForo_Model_IdentityService_Aim extends XenForo_Model_IdentityService_Abstract
{
	protected function _getIdentityServiceId()
	{
		return 'aim';
	}

	static public function verifyAccountName(&$accountName, &$error)
	{
		if (!preg_match('/^[a-z0-9@\. ]+$/i', $accountName))
		{
			$error = new XenForo_Phrase('please_enter_valid_aim_screen_name_using_alphanumeric_at');
			return false;
		}

		return true;
	}
}