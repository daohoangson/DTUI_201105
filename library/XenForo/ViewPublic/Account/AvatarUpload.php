<?php

class XenForo_ViewPublic_Account_AvatarUpload extends XenForo_ViewPublic_Base
{
	public function prepareParams()
	{
		$this->_params['cropCss'] = XenForo_ViewPublic_Helper_User::getAvatarCropCss($this->_params['user']);
	}

	public function renderJson()
	{
		$this->_params['urls'] = XenForo_Template_Helper_Core::getAvatarUrls($this->_params['user']);

		$output = XenForo_Application::arrayFilterKeys($this->_params, array(
			'sizeCode', 'maxWidth', 'maxDimension',
			'width', 'height', 'cropX', 'cropY',
			'urls', 'user_id', 'avatar_date', 'cropCss',
			'message'
		));

		return XenForo_ViewRenderer_Json::jsonEncodeForOutput($output);
	}
}