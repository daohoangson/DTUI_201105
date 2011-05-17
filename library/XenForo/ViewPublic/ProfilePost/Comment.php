<?php

class XenForo_ViewPublic_ProfilePost_Comment extends XenForo_ViewPublic_Base
{
	public function renderJson()
	{
		return array(
			'comment' => $this->createTemplateObject('profile_post_comment', $this->_params)
		);
	}
}