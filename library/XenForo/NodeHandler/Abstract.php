<?php

/**
 * Class to handle preparing nodes of a specified type for display.
 *
 * @package XenForo_Nodes
 */
abstract class XenForo_NodeHandler_Abstract
{
	/**
	 * Determines if the specified node is viewable with the given permissions.
	 *
	 * @param array $node Node info
	 * @param array $nodePermissions Permissions for this node
	 *
	 * @return boolean
	 */
	abstract public function isNodeViewable(array $node, array $nodePermissions);
	// TODO: be able to pass in $viewingUser

	/**
	 * Renders the specified node for display in a node tree.
	 * Note that if using a template, it is preferable to not explicitly
	 * render the template here, but to return the object instead.
	 *
	 * @param XenForo_View $view View object doing the rendering
	 * @param array $node Information about this node
	 * @param array $permissions Pemissions for this node
	 * @param array $renderedChildren List of rendered children, [node id] => rendered output
	 * @param integer $level The level this node should be rendered at, relative to how it's to be displayed.
	 *
	 * @return string|XenForo_Template_Abstract
	 */
	abstract public function renderNodeForTree(XenForo_View $view, array $node, array $permissions,
		array $renderedChildren, $level
	);

	/**
	 * Gets the extra, node-type-specified data for the list of nodes.
	 *
	 * @param array $nodeIds
	 *
	 * @return array Format: [node id] => info
	 */
	public function getExtraDataForNodes(array $nodeIds)
	{
		return array();
	}

	/**
	 * Do type-specific node preparations.
	 *
	 * @param array $node Unprepared data
	 *
	 * @return array Prepared data
	 */
	public function prepareNode(array $node)
	{
		return $node;
	}

	/**
	 * Gets a count of total items in this node, broken down to discussions and messages.
	 *
	 * @param array $node
	 *
	 * @return array Keys: discussions, messages
	 */
	public function getNodeItemCounts(array $node)
	{
		$output = array(
			'discussions' => 0,
			'messages' => 0
		);

		if (isset($node['discussion_count']))
		{
			$output['discussions'] = $node['discussion_count'];
		}
		if (isset($node['message_count']))
		{
			$output['messages'] = $node['message_count'];
		}

		return $output;
	}

	/**
	 * Gets the effective data that can be pushed up to a parent node.
	 *
	 * @param array $node Current node info
	 * @param array $childPushable List of pushable data from all child nodes: [node id] => pushable data
	 * @param array $permissions Permissions for this node
	 *
	 * @return array List of pushable data (key-value pairs)
	 */
	public function getPushableDataForNode(array $node, array $childPushable, array $permissions)
	{
		return $this->_getForumLikePushableData($node, $childPushable);
	}

	/**
	 * Gets the pushable data for a forum-like node. Most nodes, including
	 * categories and forums, will fall into this category, provided the
	 * key names match.
	 *
	 * This function does not check any permissions.
	 *
	 * @param array $node Info about the current node
	 * @param array $childPushable List of pushable data for child nodes ([node id] => info)
	 *
	 * @return array Key-value pairs of pushable data
	 */
	protected function _getForumLikePushableData(array $node, array $childPushable)
	{
		$newPushable = array(
			'discussion_count' => (isset($node['discussion_count']) ? $node['discussion_count'] : 0),
			'message_count' => (isset($node['message_count']) ? $node['message_count'] : 0),
			'hasNew' => (isset($node['hasNew']) ? $node['hasNew'] : false),
			'childCount' => 0
		);

		if (!empty($node['last_post_date']))
		{
			$newPushable['last_post_id'] = $node['last_post_id'];
			$newPushable['last_post_date'] = $node['last_post_date'];
			$newPushable['last_post_user_id'] = $node['last_post_user_id'];
			$newPushable['last_post_username'] = $node['last_post_username'];
			$newPushable['last_thread_title'] = XenForo_Helper_String::censorString($node['last_thread_title']);
		}
		else
		{
			$newPushable['last_post_id'] = 0;
			$newPushable['last_post_date'] = 0;
			$newPushable['last_post_user_id'] = 0;
			$newPushable['last_post_username'] = '';
			$newPushable['last_thread_title'] = '';
		}

		foreach ($childPushable AS $childData)
		{
			if (!empty($childData['discussion_count']))
			{
				$newPushable['discussion_count'] += $childData['discussion_count'];
			}

			if (!empty($childData['message_count']))
			{
				$newPushable['message_count'] += $childData['message_count'];
			}

			if (!empty($childData['last_post_date']) && $childData['last_post_date'] > $newPushable['last_post_date'])
			{
				$newPushable['last_post_id'] = $childData['last_post_id'];
				$newPushable['last_post_date'] = $childData['last_post_date'];
				$newPushable['last_post_user_id'] = $childData['last_post_user_id'];
				$newPushable['last_post_username'] = $childData['last_post_username'];
				$newPushable['last_thread_title'] = $childData['last_thread_title'];
			}

			if (!empty($childData['hasNew']))
			{
				// one child has new stuff
				$newPushable['hasNew'] = true;
			}

			$newPushable['childCount'] += 1 + (!empty($childData['childCount']) ? $childData['childCount'] : 0);
		}

		$newPushable['lastPost'] = array(
			'post_id'   => $newPushable['last_post_id'],
			'date'      => $newPushable['last_post_date'],
			'user_id'   => $newPushable['last_post_user_id'],
			'username'  => $newPushable['last_post_username'],
			'title'     => $newPushable['last_thread_title']
		);

		return $newPushable;
	}
}