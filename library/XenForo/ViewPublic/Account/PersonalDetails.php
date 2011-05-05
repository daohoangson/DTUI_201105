<?php

class XenForo_ViewPublic_Account_PersonalDetails extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		$this->_params['aboutEditor'] = XenForo_ViewPublic_Helper_Editor::getEditorTemplate(
			$this, 'about', XenForo_Visitor::getInstance()->get('about')
		);
	}
}