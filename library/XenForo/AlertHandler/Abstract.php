<?php

/**
 * Class to handle turning raw user alerts into renderable items
 *
 * @author kier
 *
 */
abstract class XenForo_AlertHandler_Abstract
{
	/**
	* Factory method to get the named alert handler. The class must exist and be autoloadable
	* or an exception will be thrown.
	*
	* @param string Class to load
	*
	* @return XenForo_AlertHandler_Abstract
	*/
	public static function create($class)
	{
		if (XenForo_Application::autoload($class))
		{
			$obj = new $class();
			if ($obj instanceof XenForo_AlertHandler_Abstract)
			{
				return $obj;
			}
		}

		throw new XenForo_Exception("Invalid user alert handler '$class' specified");
	}

	/**
	 * Fetches the content required by alerts.
	 * Designed to be overridden by child classes using $model->getContentByIds($contentIds) or similar
	 *
	 * @param array $contentIds
	 * @param XenForo_Model_Alert $model Alert model invoking this
	 * @param integer $userId User ID the alerts are for
	 * @param array $viewingUser Information about the viewing user (keys: user_id, permission_combination_id, permissions)
	 *
	 * @return array
	 */
	abstract public function getContentByIds(array $contentIds, $model, $userId, array $viewingUser);

	/**
	 * Determines if the given alert is viewable.
	 *
	 * @param array $alert
	 * @param mixed $content
	 * @param array $viewingUser Information about the viewing user (keys: user_id, permission_combination_id, permissions)
	 *
	 * @return boolean
	 */
	public function canViewAlert(array $alert, $content, array $viewingUser)
	{
		return true;
	}

	/**
	 * Prepares a news feed item for rendering.
	 * Designed to be overriden by extended classes, while retaining the call to _prepareAlert.
	 *
	 * @param array $alert
	 * @param array $viewingUser Information about the viewing user (keys: user_id, permission_combination_id, permissions)
	 *
	 * @return array
	 */
	public function prepareAlert(array $item, array $viewingUser)
	{
		$methodName = '_prepare' . ucfirst($item['action']);

		$item = $this->_prepareAlertBeforeAction($item, $item['content'], $viewingUser);

		if (isset($item['content']['title']))
		{
			$item['content']['title'] = XenForo_Helper_String::censorString($item['content']['title']);
		}

		if (method_exists($this, $methodName))
		{
			$item = call_user_func(array($this, $methodName), $item, $viewingUser);
		}

		return $this->_prepareAlertAfterAction($item, $item['content'], $viewingUser);
	}

	/**
	 * Performs basic and generic preparation for alerts, BEFORE content-type/action specific manipulation
	 * Designed to be overridden by child classes
	 *
	 * @param array $alert
	 * @param mixed $alert['content']
	 * @param array $viewingUser Information about the viewing user (keys: user_id, permission_combination_id, permissions)
	 *
	 * @return array
	 */
	protected function _prepareAlertBeforeAction(array $item, $content, array $viewingUser)
	{
		return $item;
	}

	/**
	 * Performs basic and generic preparation for alerts, AFTER content-type/action specific manipulation
	 * Designed to be overridden by child classes
	 *
	 * @param array $alert
	 * @param mixed $alert['content']
	 * @param array $viewingUser Information about the viewing user (keys: user_id, permission_combination_id, permissions)
	 *
	 * @return array
	 */
	protected function _prepareAlertAfterAction(array $item, $content, array $viewingUser)
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
	 * Returns a template title in the form 'alert_{contentType}_{action}'
	 *
	 * @param string $contentType
	 * @param string $action
	 *
	 * @return string
	 */
	protected function _getDefaultTemplateTitle($contentType, $action)
	{
		return 'alert_' . $contentType . '_' . $action;
	}
}