<?php

class XenForo_ViewPublic_Login_CsrfTokenRefresh extends XenForo_ViewPublic_Base
{
	public function renderJson()
	{
		return XenForo_ViewRenderer_Json::jsonEncodeForOutput(array(
			'csrfToken' => $this->_params['csrfToken'],
			'sessionId' => $this->_params['sessionId'],
		));
	}
}