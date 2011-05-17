<?php

/**
 * Model for external user authentication (eg, Facebook).
 *
 * @package XenForo_User
 */
class XenForo_Model_UserExternal extends XenForo_Model
{
	/**
	 * Updates the specified provider association for the given user.
	 *
	 * @param string $provider
	 * @param string $providerKey
	 * @param string $userId
	 * @param string|null|false $userProfileField Name of the profile field that the provider key will be stored in; if null, <provider>_auth_id
	 * @param array $extra Array of extra data to store
	 */
	public function updateExternalAuthAssociation($provider, $providerKey, $userId, $userProfileField = null, array $extra = null)
	{
		$db = $this->_getDb();

		$existing = $this->getExternalAuthAssociation($provider, $providerKey);
		if ($existing && $existing['user_id'] != $userId)
		{
			$this->deleteExternalAuthAssociation($provider, $providerKey, $existing['user_id'], $userProfileField);
		}

		$db->query('
			INSERT INTO xf_user_external_auth
				(provider, provider_key, user_id, extra_data)
			VALUES
				(?, ?, ?, ?)
			ON DUPLICATE KEY UPDATE
				provider = VALUES(provider),
				provider_key = VALUES(provider_key),
				user_id = VALUES(user_id),
				extra_data = VALUES(extra_data)
		', array($provider, $providerKey, $userId, serialize($extra)));

		if ($userProfileField === null)
		{
			$userProfileField = $provider . '_auth_id';
		}
		if ($userProfileField)
		{
			$db->update('xf_user_profile', array($userProfileField => $providerKey), 'user_id = ' . $db->quote($userId));
		}
	}

	/**
	 * Deletes the external auth association for the given key.
	 *
	 * @param string $provider
	 * @param string $providerKey
	 * @param integer|null $userId If null, finds the user that has this key.
	 * @param string|null $userProfileField Name of the profile field that the provider key is stored in; if null, <provider>_auth_id
	 */
	public function deleteExternalAuthAssociation($provider, $providerKey, $userId = null, $userProfileField = null)
	{
		if ($userId === null)
		{
			$existing = $this->getExternalAuthAssociation($provider, $providerKey);
			if (!$existing)
			{
				return false;
			}
			$userId = $existing['user_id'];
		}

		$db = $this->_getDb();

		$db->query('
			DELETE FROM xf_user_external_auth
			WHERE provider = ?
				AND provider_key = ?
				AND user_id = ?
		', array($provider, $providerKey, $userId));

		if ($userProfileField === null)
		{
			$userProfileField = $provider . '_auth_id';
		}
		if ($userProfileField)
		{
			$db->update('xf_user_profile', array($userProfileField => 0), 'user_id = ' . $db->quote($userId));
		}

		return true;
	}

	/**
	 * Gets the external auth association record for the specified provider and key.
	 *
	 * @param string $provider
	 * @param string $providerKey
	 *
	 * @return array|false
	 */
	public function getExternalAuthAssociation($provider, $providerKey)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_user_external_auth
			WHERE provider = ?
				AND provider_key = ?
		', array($provider, $providerKey));
	}
}