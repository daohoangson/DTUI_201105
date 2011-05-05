<?php

class XenForo_Model_IdentityService_Msn extends XenForo_Model_IdentityService_Abstract
{
	protected function _getIdentityServiceId()
	{
		return 'msn';
	}

	static public function verifyAccountName(&$accountName, &$error)
	{
		if (!Zend_Validate::is($accountName, 'EmailAddress'))
		{
			$error = new XenForo_Phrase('please_enter_valid_email_address_for_your_windows_live_id');
			return false;
		}

		return true;
	}
}