<?php

class XenForo_ViewAdmin_Login_CsrfTokenRefresh extends XenForo_ViewAdmin_Base
{
	public function renderJson()
	{
		return XenForo_ViewRenderer_Json::jsonEncodeForOutput(array(
			'csrfToken' => $this->_params['csrfToken']
		));
	}
}