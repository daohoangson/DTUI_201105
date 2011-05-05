<?php

class XenForo_Model_IdentityService_Icq extends XenForo_Model_IdentityService_Abstract
{
	protected function _getIdentityServiceId()
	{
		return 'icq';
	}

	static public function verifyAccountName(&$accountName, &$error)
	{
		if (!preg_match('/^\d+$/', $accountName))
		{
			$error = new XenForo_Phrase('please_enter_valid_icq_uin_using_numeric_characters_only');
			return false;
		}

		return true;
	}
}