<?php

class XenForo_ViewPublic_Error_RegistrationRequired extends XenForo_ViewPublic_Base
{
	public function renderJson()
	{
		if (!empty($this->_params['text']))
		{
			return $this->_renderer->renderError($this->_params['text']);
		}

		return null;
	}
}