<?php

class XenForo_ViewPublic_ProfilePost_Comments extends XenForo_ViewPublic_Base
{
	public function renderJson()
	{
		$comments = array();

		if ($this->_params['profilePost']['first_comment_date'] < $this->_params['firstCommentShown']['comment_date'])
		{
			$comments[] = $this->createTemplateObject(
				'profile_post_comments_before', $this->_params
			);
		}

		foreach ($this->_params['comments'] AS $comment)
		{
			$comments[] = $this->createTemplateObject(
				'profile_post_comment', array('comment' => $comment) + $this->_params
			);
		}

		return array(
			'comments' => $comments
		);
	}
}