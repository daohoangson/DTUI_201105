<?php

class XenForo_Route_Prefix_Threads implements XenForo_Route_Interface
{
	/**
	 * Match a specific route for an already matched prefix.
	 *
	 * @see XenForo_Route_Interface::match()
	 */
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		$action = $router->resolveActionWithIntegerParam($routePath, $request, 'thread_id');
		$action = $router->resolveActionAsPageNumber($action, $request);
		return $router->getRouteMatch('XenForo_ControllerPublic_Thread', $action, 'forums');
	}

	/**
	 * Method to build a link to the specified page/action with the provided
	 * data and params.
	 *
	 * @see XenForo_Route_BuilderInterface
	 */
	public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams)
	{
		$postHash = '';
		if ($action == 'post-permalink' && !empty($extraParams['post']))
		{
			$post = $extraParams['post'];
			unset($extraParams['post']);

			if (!empty($post['post_id']) && isset($post['position']))
			{
				if ($post['position'] > 0)
				{
					$postHash = '#post-' . intval($post['post_id']);
					$extraParams['page'] = floor($post['position'] / XenForo_Application::get('options')->messagesPerPage) + 1;
				}
			}

			$action = '';
		}

		$action = XenForo_Link::getPageNumberAsAction($action, $extraParams);

		return XenForo_Link::buildBasicLinkWithIntegerParam($outputPrefix, $action, $extension, $data, 'thread_id', 'title') . $postHash;
	}
}