<?php

/**
 * Helper for Facebook integration.
 *
 * @package XenForo_Facebook
 */
class XenForo_Helper_Facebook
{
	/**
	 * Takes a code (with a redirect URL) and gets an access token.
	 *
	 * @param string $url
	 * @param string $code
	 *
	 * @return array|false Array of info (may be error); false if Facebook integration not active
	 */
	public static function getAccessToken($url, $code)
	{
		$options = XenForo_Application::get('options');

		if (!$options->facebookAppId)
		{
			return false;
		}

		try
		{
			$client = XenForo_Helper_Http::getClient('https://graph.facebook.com/oauth/access_token');
			$client->setParameterGet(array(
				'client_id' => $options->facebookAppId,
				'redirect_uri' => $url,
				'client_secret' => $options->facebookAppSecret,
				'code' => $code
			));

			$response = $client->request('GET');

			$body = $response->getBody();
			if (preg_match('#^[{\[]#', $body))
			{
				$parts = json_decode($body, true);
			}
			else
			{
				$parts = XenForo_Application::parseQueryString($body);
			}

			return $parts;
		}
		catch (Zend_Http_Client_Exception $e)
		{
			XenForo_Error::logException($e, false);
			return false;
		}
	}

	public static function getAccessTokenFromCode($code, $redirectUri = false)
	{
		if (!$redirectUri)
		{
			$requestPaths = XenForo_Application::get('requestPaths');
			$redirectUri = preg_replace('#(&|\?)code=[^&]*#', '', $requestPaths['fullUri']);
		}
		else
		{
			// FB does this strange thing with slashes after a ? for some reason
			$parts = explode('?', $redirectUri, 2);
			if (isset($parts[1]))
			{
				$redirectUri = $parts[0] . '?' . str_replace('/', '%2F', $parts[1]);
			}
		}

		return XenForo_Helper_Facebook::getAccessToken($redirectUri, $code);
	}

	/**
	 * Gets Facebook user info from the specified place.
	 *
	 * @param string $accessToken FB access token (from code swap, or given by user); may be empty
	 * @param string $path Path to access (defaults to "me")
	 *
	 * @return array Info; may be error
	 */
	public static function getUserInfo($accessToken, $path = 'me')
	{
		try
		{
			$client = XenForo_Helper_Http::getClient('https://graph.facebook.com/' . $path);
			if ($accessToken)
			{
				$client->setParameterGet('access_token', $accessToken);
			}

			$response = $client->request('GET');
			return json_decode($response->getBody(), true);
		}
		catch (Zend_Http_Client_Exception $e)
		{
			XenForo_Error::logException($e, false);
			return false;
		}
	}

	/**
	 * Gets the user picture for the current user.
	 *
	 * @param string $accessToken FB access token (from code swap, or given by user)
	 * @param string $size Size/type of picture. Defaults to large
	 *
	 * @return string Binary data
	 */
	public static function getUserPicture($accessToken, $size = 'large')
	{
		try
		{
			$client = XenForo_Helper_Http::getClient('https://graph.facebook.com/me/picture?type=' . $size);
			$client->setParameterGet('access_token', $accessToken);

			$response = $client->request('GET');

			return $response->getBody();
		}
		catch (Zend_Http_Client_Exception $e)
		{
			return false;
		}
	}

	/**
	 * Sets the fbUid cookie that is used by the JavaScript.
	 *
	 * @param integer $fbUid 64-bit int of FB user ID
	 */
	public static function setUidCookie($fbUid)
	{
		XenForo_Helper_Cookie::setCookie('fbUid', $fbUid, 14 * 86400);
	}

	/**
	 * Gets the URL to request Facebook permissions.
	 *
	 * @param string $redirectUri URL to return to
	 * @param string|null $appId Facebook app ID
	 *
	 * @return string
	 */
	public static function getFacebookRequestUrl($redirectUri, $appId = null)
	{
		$perms = 'email,publish_stream,user_birthday,user_status,user_website,user_location';

		if (!$appId)
		{
			$appId = XenForo_Application::get('options')->facebookAppId;
		}

		return 'https://graph.facebook.com/oauth/authorize?client_id=' . $appId
			. '&scope=' . $perms
			. '&redirect_uri=' . urlencode($redirectUri);
	}

	public static function getFacebookRequestErrorInfo($result, $expectedKey = false)
	{
		if (!$result)
		{
			return new XenForo_Phrase('your_server_could_not_connect_to_facebook');
		}
		if (!is_array($result))
		{
			return new XenForo_Phrase('facebook_returned_unknown_error');
		}
		if (!empty($result['error']['message']))
		{
			return new XenForo_Phrase('facebook_returned_following_error_x', array('error' => $result['error']['message']));
		}
		if ($expectedKey && !isset($result[$expectedKey]))
		{
			return new XenForo_Phrase('facebook_returned_unknown_error');
		}

		return false;
	}
}