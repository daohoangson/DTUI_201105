<?php

class XenForo_ViewPublic_Account_GravatarTest extends XenForo_ViewPublic_Base
{
	public function renderJson()
	{
		return XenForo_ViewRenderer_Json::jsonEncodeForOutput(array(
			'gravatarUrl' => $this->_params['gravatarUrl']
		));
	}
}