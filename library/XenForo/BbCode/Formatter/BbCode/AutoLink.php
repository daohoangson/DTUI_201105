<?php

/**
 * BB code to BB code formatter that automatically links URLs and emails using
 * [url] and [email] tags.
 *
 * @package XenForo_BbCode
 */
class XenForo_BbCode_Formatter_BbCode_AutoLink extends XenForo_BbCode_Formatter_BbCode_Abstract
{
	/**
	 * Callback for all tags.
	 *
	 * @var callback
	 */
	protected $_generalTagCallback = array('$this', 'autoLinkTag');

	/**
	 * The tags that disable autolinking.
	 *
	 * @var array
	 */
	protected $_disableAutoLink = array('url', 'email', 'img', 'code', 'php', 'html', 'plain');

	/**
	 * Callback that all tags with go through. Changes the rendering state to disable
	 * URL parsing if necessary.
	 *
	 * @param array $tag
	 * @param array $rendererStates

	 * @return string
	 */
	public function autoLinkTag(array $tag, array $rendererStates)
	{
		if (in_array($tag['tag'], $this->_disableAutoLink))
		{
			$rendererStates['stopAutoLink'] = true;
		}

		$text = $this->renderSubTree($tag['children'], $rendererStates);

		if (!empty($tag['original']) && is_array($tag['original']))
		{
			list($prepend, $append) = $tag['original'];
		}
		else
		{
			$prepend = '';
			$append = '';
		}

		// note: necessary to return prepend/append unfiltered to keep them unchanged
		return $prepend . $text . $append;
	}

	/**
	 * String filter that does link parsing if not disabled.
	 *
	 * @param string $string
	 * @param array $rendererStates List of states the renderer may be in
	 *
	 * @return string Filtered/escaped string
	 */
	public function filterString($string, array $rendererStates)
	{
		if (empty($rendererStates['stopAutoLink']))
		{
			$string = preg_replace_callback(
				'#(?<=[^a-z0-9@-]|^)(https?://|ftp://|www\.)[^\s"]+#i',
				array($this, '_autoLinkUrlCallback'),
				$string
			);

			if (strpos($string, '@') !== false)
			{
				// assertion to prevent matching email in url matched above (user:pass@example.com)
				$string = preg_replace(
					'#[a-z0-9.+_-]+@[a-z0-9-]+(\.[a-z]+)+(?![^\s"]*\[/url\])#i',
					'[email]$0[/email]',
					$string
				);
			}
		}

		return $string;
	}

	/**
	 * Callback for the auto-linker regex.
	 *
	 * @param array $match
	 *
	 * @return string
	 */
	protected function _autoLinkUrlCallback(array $match)
	{
		$link = XenForo_Helper_String::prepareAutoLinkedUrl($match[0]);

		if ($link['url'] === $link['linkText'])
		{
			$tag = '[url]' . $link['url'] . '[/url]';
		}
		else
		{
			$tag = '[url="' . $link['url'] . '"]' . $link['linkText'] . '[/url]';
		}

		return $tag . $link['suffixText'];
	}
}