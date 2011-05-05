<?php

/**
 * Class to handle turning raw discussion news feed events into renderable output
 *
 * @author kier
 *
 */
abstract class XenForo_NewsFeedHandler_Discussion extends XenForo_NewsFeedHandler_Abstract
{
	/**
	 * Fetches the name(s) of the primary key(s) for the table being dealt with
	 *
	 * @return array
	 */
	abstract protected function _getContentPrimaryKeynames();

	/**
	 * Prepares the news feed item for display
	 *
	 * @param array $item News feed item
	 * @param array $content News feed item content
	 * @param array $viewingUser Information about the viewing user (keys: user_id, permission_combination_id, permissions)
	 *
	 * @return array
	 */
	protected function _prepareNewsFeedItemAfterAction(array $item, $content, array $viewingUser)
	{
		$item['content'] = array(
			'title' => XenForo_Helper_String::censorString($content['title'])
		);

		foreach ($this->_getContentPrimaryKeynames() AS $key)
		{
			if (isset($content[$key]))
			{
				$item['content'][$key] = $content[$key];
			}
		}

		return $item;
	}
}