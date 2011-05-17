<?php

class XenForo_ViewPublic_Misc_Captcha extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		if (!empty($this->_params['captcha']))
		{
			$this->_params['captcha'] = $this->_params['captcha']->render($this);
		}
	}
}