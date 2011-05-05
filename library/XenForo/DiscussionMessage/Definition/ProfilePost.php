<?php

/**
 * User profile discussion message definition.
 *
 * @package XenForo_ProfilePost
 */
class XenForo_DiscussionMessage_Definition_ProfilePost extends XenForo_DiscussionMessage_Definition_Abstract
{
	/**
	 * Gets the structure of the message record.
	 *
	 * @return array
	 */
	protected function _getMessageStructure()
	{
		return array(
			'table' => 'xf_profile_post',
			'key' => 'profile_post_id',
			'container' => 'profile_user_id',
			'contentType' => 'profile_post'
		);
	}

	/**
	 * Gets the parts of the message configuration options that are to override the defaults.
	 *
	 * @return array
	 */
	protected function _getMessageConfiguration()
	{
		return array(
			'hasParentDiscussion' => false
		);
	}
}