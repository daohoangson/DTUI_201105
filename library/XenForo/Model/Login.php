<?php

class XenForo_Model_Login extends XenForo_Model
{
	public function countLoginAttempts($usernameOrEmail, $ipAddress = null)
	{
		$ipAddress = $this->convertIpToLong($ipAddress);

		$cutOff = XenForo_Application::$time - 60 * 15;

		return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM xf_login_attempt
			WHERE login = ?
				AND ip_address = ?
				AND attempt_date > ?
		', array($usernameOrEmail, $ipAddress, $cutOff));
	}

	public function requireLoginCaptcha($usernameOrEmail, $maxNoCaptcha = null, $ipAddress = null)
	{
		if ($maxNoCaptcha === null)
		{
			$maxNoCaptcha = 4;
		}

		return ($this->countLoginAttempts($usernameOrEmail, $ipAddress) > $maxNoCaptcha);
	}

	public function logLoginAttempt($usernameOrEmail, $ipAddress = null)
	{
		$this->_getDb()->insert('xf_login_attempt', array(
			'login' => utf8_substr($usernameOrEmail, 0, 60),
			'ip_address' => $this->convertIpToLong($ipAddress),
			'attempt_date' => XenForo_Application::$time
		));
	}

	public function clearLoginAttempts($usernameOrEmail, $ipAddress = null)
	{
		$ipAddress = $this->convertIpToLong($ipAddress);

		$db = $this->_getDb();
		$db->delete('xf_login_attempt',
			'login = ' . $db->quote($usernameOrEmail) . ' AND ip_address = ' . $db->quote($ipAddress)
		);
	}

	public function cleanUpLoginAttempts()
	{
		$cutOff = XenForo_Application::$time - 60 * 15;

		$db = $this->_getDb();
		$db->delete('xf_login_attempt', 'attempt_date < ' . $db->quote($cutOff));
	}

	public function convertIpToLong($ipAddress = null)
	{
		if ($ipAddress === null)
		{
			$ipAddress = (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 0);
		}

		if (is_string($ipAddress) && strpos($ipAddress, '.'))
		{
			$ipAddress = ip2long($ipAddress);
		}

		return sprintf('%u', $ipAddress);
	}
}