<?php

class XenForo_BbCode_Formatter_HtmlEmail extends XenForo_BbCode_Formatter_Base
{
	/**
	 * Stores the root server url, eg: http://localhost
	 *
	 * @var string
	 */
	protected static $_boardRoot = null;

	/**
	 * @see XenForo_BbCode_Formatter_Base::_prepareSmilieUrl()
	 */
	protected function _prepareSmilieUrl($smilieUrl)
	{
		if ($smilieUrl[0] == '/')
		{
			if (self::$_boardRoot === null)
			{
				$boardUrl = XenForo_Application::get('options')->boardUrl;
				self::$_boardRoot = substr($boardUrl, 0, strpos($boardUrl, '/', 8));
			}

			// absolute path to this server
			return $boardUrl . parent::_prepareSmilieUrl($smilieUrl);

		}
		else if (!preg_match('#^https?://#i', $smilieUrl))
		{
			// relative path to this server
			return XenForo_Application::get('options')->boardUrl . '/' . parent::_prepareSmilieUrl($smilieUrl);
		}
		else
		{
			// no change required
			return parent::_prepareSmilieUrl($smilieUrl);
		}
	}
}