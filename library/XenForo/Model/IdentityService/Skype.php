<?php

class XenForo_Model_IdentityService_Skype extends XenForo_Model_IdentityService_Abstract
{
	protected function _getIdentityServiceId()
	{
		return 'skype';
	}

	static public function verifyAccountName(&$accountName, &$error)
	{
		if (!preg_match('/^[a-z0-9-_\.,]{3,30}$/siU', $accountName))
		{
			$error = new XenForo_Phrase('please_enter_valid_skype_name');
			return false;
		}

		return true;
	}
}