<?php

// TODO: need to be able to limit news feed items to specific items for specific users or maybe usergroups (don't show posts from 'James', for example)

/**
 * Class to handle turning raw news feed events into renderable items
 *
 * @author kier
 *
 */
abstract class XenForo_NewsFeedHandler_Abstract
{
	/**
	* Factory method to get the named news feed handler. The class must exist and be autoloadable
	* or an exception will be thrown.
	*
	* @param string Class to load
	*
	* @return XenForo_NewsFeedHandler_Abstract
	*/
	public static function create($class)
	{
		if (XenForo_Application::autoload($class))
		{
			$obj = new $class();
			if ($obj instanceof XenForo_NewsFeedHandler_Abstract)
			{
				return $obj;
			}
		}

		throw new XenForo_Exception("Invalid news feed handler '$class' specified");
	}

	/**
	 * Fetches the content required by news feed items.
	 * Designed to be overridden by child classes using $model->getContentByIds($contentIds) or similar
	 *
	 * @param array $contentIds
	 * @param XenForo_Model_NewsFeed $model
	 * @param array $viewingUser Information about the viewing user (keys: user_id, permission_combination_id, permissions)
	 *
	 * @return array
	 */
	abstract public function getContentByIds(array $contentIds, $model, array $viewingUser);

	/**
	 * Determines if the given news feed item is viewable.
	 *
	 * @param array $item
	 * @param mixed $content
	 * @param array $viewingUser
	 *
	 * @return boolean
	 */
	public function canViewNewsFeedItem(array $item, $content, array $viewingUser)
	{
		return true;
	}

	/**
	 * Prepares a news feed item for rendering.
	 * Designed to be overriden by extended classes, while retaining the call to _prepareNewsFeedItem.
	 *
	 * @param array $newsFeedItem
	 * @param array $viewingUser Information about the viewing user (keys: user_id, permission_combination_id, permissions)
	 *
	 * @return array
	 */
	public function prepareNewsFeedItem(array $item, array $viewingUser)
	{
		$methodName = '_prepare' . ucfirst($item['action']);

		$item = $this->_prepareNewsFeedItemBeforeAction($item, $item['content'], $viewingUser);

		if (method_exists($this, $methodName))
		{
			$item = call_user_func(array($this, $methodName), $item, $viewingUser);
		}

		return $this->_prepareNewsFeedItemAfterAction($item, $item['content'], $viewingUser);
	}

	/**
	 * Performs basic and generic preparation for news feed items, BEFORE content-type/action specific manipulation
	 * Designed to be overridden by child classes
	 *
	 * @param array $newsFeedItem
	 * @param mixed $newsFeedItem['content']
	 * @param array $viewingUser Information about the viewing user (keys: user_id, permission_combination_id, permissions)
	 *
	 * @return array
	 */
	protected function _prepareNewsFeedItemBeforeAction(array $item, $content, array $viewingUser)
	{
		return $item;
	}

	/**
	 * Performs basic and generic preparation for news feed items, AFTER content-type/action specific manipulation
	 * Designed to be overridden by child classes
	 *
	 * @param array $newsFeedItem
	 * @param mixed $newsFeedItem['content']
	 * @param array $viewingUser Information about the viewing user (keys: user_id, permission_combination_id, permissions)
	 *
	 * @return array
	 */
	protected function _prepareNewsFeedItemAfterAction(array $item, $content, array $viewingUser)
	{
		return $item;
	}


	/**
	 * Renders an item content template
	 *
	 * @param array $item
	 * @param XenForo_View $view
	 *
	 * @return XenForo_Template_Public
	 */
	public function renderHtml(array $item, XenForo_View $view)
	{
		$item['templateTitle'] = $this->_getDefaultTemplateTitle($item['content_type'], $item['action']);

		$methodName = '_renderHtml' . ucfirst($item['action']);

		if (method_exists($this, $methodName))
		{
			return call_user_func(array($this, $methodName), $item, $view);
		}

		return $view->createTemplateObject($item['templateTitle'], $item);
	}

	/**
	 * Returns a template title in the form 'news_feed_item_{contentType}_{action}'
	 *
	 * @param string $contentType
	 * @param string $action
	 *
	 * @return string
	 */
	protected function _getDefaultTemplateTitle($contentType, $action)
	{
		return 'news_feed_item_' . $contentType . '_' . $action;
	}
}