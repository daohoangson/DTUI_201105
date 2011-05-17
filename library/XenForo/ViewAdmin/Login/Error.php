<?php

class XenForo_ViewAdmin_Login_Error extends XenForo_ViewAdmin_Base
{
	public function renderJson()
	{
		return $this->_renderer->renderError($this->_params['text']);
	}
}