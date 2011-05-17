<?php

class XenForo_ViewPublic_Base extends XenForo_View
{
	public function prepareParams()
	{
		parent::prepareParams();

		if (isset($this->_params['captcha']) && $this->_params['captcha'] instanceof XenForo_Captcha_Abstract)
		{
			$this->_params['captcha']->render($this);
		}
	}
}