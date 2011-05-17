<?php

class XenForo_Helper_Url
{
	public static function getTitle($url, $text)
	{
		if (preg_match('#^https?://#i', $url))
		{
			try
			{
				$client = XenForo_Helper_Http::getClient($url, array(
					'timeout' => 10
				));

				$request = $client->request();

				if ($request->isSuccessful())
				{
					$html = $request->getBody();

					if (preg_match('#<title[^>]*>(.*)</title>#siU', $html, $match))
					{
						return $match[1];
					}
				}
			}
			catch (Zend_Http_Client_Exception $e)
			{
				return $text;
			}
		}

		return $text;
	}
}