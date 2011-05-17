<?php

abstract class XenForo_Model_IdentityService_Abstract extends XenForo_Model
{
	/**
	 * Returns the identity_service_id for this identity service, as defined in xf_identity_service in the database
	 *
	 * @return string
	 */
	abstract protected function _getIdentityServiceId();

	/**
	 * Verifies that the provided value is a valid account name for this service.
	 * If this function returns false, the $error variable shall contain an error message.
	 *
	 * @param string $accountName
	 * @param string $error
	 *
	 * @return boolean
	 */
	static public function verifyAccountName(&$accountName, &$error)
	{
		return true;
	}
}