<?php

class XenForo_Model_IdentityService_Yahoo extends XenForo_Model_IdentityService_Abstract
{
	protected function _getIdentityServiceId()
	{
		return 'yahoo';
	}

	static public function verifyAccountName(&$accountName, &$error)
	{
		return true;
	}
}