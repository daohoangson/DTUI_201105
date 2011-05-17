<?php

class XenForo_ViewPublic_Account_Avatar extends XenForo_ViewPublic_Base
{
	public function renderJson()
	{
		$this->_templateName = 'account_avatar_overlay';
	}
}