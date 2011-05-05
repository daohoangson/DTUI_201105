<?php

class XenForo_Model_IdentityService_Gtalk extends XenForo_Model_IdentityService_Abstract
{
	protected function _getIdentityServiceId()
	{
		return 'gtalk';
	}

	static public function verifyAccountName(&$accountName, &$error)
	{
		// this can be an email or just a username (which is implicitly @gmail.com)
		return true;
	}
}