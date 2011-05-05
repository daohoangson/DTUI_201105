<?php

class XenForo_ViewAdmin_Login_Form extends XenForo_ViewAdmin_Base
{
	public function renderJson()
	{
		return $this->_renderer->renderError(new XenForo_Phrase('action_not_completed_because_no_longer_logged_in'));
	}
}