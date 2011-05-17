<?php

/**
 * Helper class for manipulating cookies with XenForo-specific options.
 *
 * @package XenForo_Helper
 */
class XenForo_Helper_Cookie
{
	/**
	 * Private constructor. Use statically.
	 */
	private function __construct()
	{
	}

	/**
	 * Internal helper to set or delete a cookie with XenForo-specific options.
	 *
	 * @param string $name Name of the cookie
	 * @param string|false $value Value of the cookie, false to delete
	 * @param integer $expiration Time stamp the cookie expires
	 * @param boolean $httpOnly Whether the cookie should be available via HTTP only
	 * @param boolean|null $secure Whether the cookie should be available via HTTPS only; if null, value is true if currently on HTTPS
	 *
	 * @return boolean True if set
	 */
	protected static function _setCookieInternal($name, $value, $expiration = 0, $httpOnly = false, $secure = null)
	{
		if ($secure === null)
		{
			$secure = XenForo_Application::$secure;
		}

		$cookieConfig = XenForo_Application::get('config')->cookie;
		$path = $cookieConfig->path;
		$domain = $cookieConfig->domain;

		if ($value === false)
		{
			$expiration = XenForo_Application::$time - 86400 * 365;
		}

		$name = $cookieConfig->prefix . $name;

		return setcookie($name, $value, $expiration, $path, $domain, $secure, $httpOnly);
	}

	/**
	 * Sets a cookie with XenForo-specific options.
	 *
	 * @param string $name Name of the cookie
	 * @param string $value Value of the cookie
	 * @param integer $lifetime The number of seconds the cookie should live from now. If 0, sets a session cookie.
	 * @param boolean $httpOnly Whether the cookie should be available via HTTP only
	 * @param boolean|null $secure Whether the cookie should be available via HTTPS only; if null, value is true if currently on HTTPS
	 *
	 * @return boolean True if set
	 */
	public static function setCookie($name, $value, $lifetime = 0, $httpOnly = false, $secure = null)
	{
		$expiration = ($lifetime ? (XenForo_Application::$time + $lifetime) : 0);
		return self::_setCookieInternal($name, $value, $expiration, $httpOnly, $secure);
	}

	/**
	 * Deletes the named cookie. The settings must match the settings when it was created.
	 *
	 * @param string $name Name of cookie
	 * @param boolean $httpOnly Whether the cookie should be available via HTTP only
	 * @param boolean|null $secure Whether the cookie should be available via HTTPS only; if null, value is true if currently on HTTPS
	 *
	 * @return boolean True if deleted
	 */
	public static function deleteCookie($name, $httpOnly = false, $secure = null)
	{
		return self::_setCookieInternal($name, false, 0, $httpOnly, $secure);
	}

	/**
	 * Deletes all cookies set by XenForo.
	 *
	 * @param array $skip List of cookies to skip
	 * @param array $flags List of flags to apply to individual cookies. [cookie name] => {httpOnly: true/false, secure: true/false/null}
	 */
	public static function deleteAllCookies(array $skip = array(), array $flags = array())
	{
		if (empty($_COOKIE))
		{
			return;
		}

		$prefix = XenForo_Application::get('config')->cookie->prefix;
		foreach ($_COOKIE AS $cookie => $null)
		{
			if (strpos($cookie, $prefix) === 0)
			{
				$cookieStripped = substr($cookie, strlen($prefix));
				if (in_array($cookieStripped, $skip))
				{
					continue;
				}

				$cookieSettings = array('httpOnly' => false, 'secure' => null);
				if (!empty($flags[$cookieStripped]))
				{
					$cookieSettings = array_merge($cookieSettings, $flags[$cookieStripped]);
				}

				self::_setCookieInternal($cookieStripped, false, 0, $cookieSettings['httpOnly'], $cookieSettings['secure']);
			}
		}
	}

	/**
	 * Gets the specified cookie. This automatically adds the necessary prefix.
	 *
	 * @param string $name Cookie name without prefix
	 * @param Zend_Controller_Request_Http $request
	 *
	 * @return string|array|false False if cookie isn't found
	 */
	public static function getCookie($name, Zend_Controller_Request_Http $request = null)
	{
		$name = XenForo_Application::get('config')->cookie->prefix . $name;

		if ($request)
		{
			return $request->getCookie($name);
		}
		else if (isset($_COOKIE[$name]))
		{
			return $_COOKIE[$name];
		}
		else
		{
			return false;
		}
	}

	/**
	 * Clears the specified ID from the specified cookie. The cookie must be a comma-separated ID list.
	 *
	 * @param integer|string $id
	 * @param string $name
	 * @param Zend_Controller_Request_Http $request
	 *
	 * @return array Exploded cookie array
	 */
	public static function clearIdFromCookie($id, $cookieName, Zend_Controller_Request_Http $request = null)
	{
		$cookie = self::getCookie($cookieName, $request);
		if (!is_string($cookie) || $cookie === '')
		{
			return array();
		}

		$cookie = explode(',', $cookie);
		$position = array_search($id, $cookie);

		if ($position !== false)
		{
			unset($cookie[$position]);
			self::setCookie($cookieName, implode(',', $cookie));
		}

		return $cookie;
	}
}