<?php

abstract class XenForo_Helper_Email
{
	/**
	 * Banned email cache for default banned emails option.
	 *
	 * @var array|null Null when not set up
	 */
	protected static $_bannedEmailCache = null;

	/**
	 * Determines if the specified email is banned. List of banned emails
	 * is simply an array of strings with * as wildcards.
	 *
	 * @param string $email
	 * @param array|null $bannedEmails List of banned emails; if null, uses the default value
	 *
	 * @return boolean
	 */
	public static function isEmailBanned($email, array $bannedEmails = null)
	{
		if ($bannedEmails === null)
		{
			if (self::$_bannedEmailCache === null)
			{
				$bannedEmails = XenForo_Model::create('XenForo_Model_DataRegistry')->get('bannedEmails');
				if (!is_array($bannedEmails))
				{
					$bannedEmails = XenForo_Model::create('XenForo_Model_Banning')->rebuildBannedEmailCache();
				}

				self::$_bannedEmailCache = $bannedEmails;
			}
			else
			{
				$bannedEmails = self::$_bannedEmailCache;
			}
		}

		foreach ($bannedEmails AS $bannedEmail)
		{
			$bannedEmail = str_replace('\\*', '(.*)', preg_quote($bannedEmail, '/'));
			if (preg_match('/^' . $bannedEmail . '$/', $email))
			{
				return true;
			}
		}

		return false;
	}
}