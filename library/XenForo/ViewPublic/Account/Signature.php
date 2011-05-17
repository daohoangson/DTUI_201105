<?php

class XenForo_ViewPublic_Account_Signature extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		$this->_params['signatureEditor'] = XenForo_ViewPublic_Helper_Editor::getEditorTemplate(
			$this, 'signature', XenForo_Visitor::getInstance()->get('signature')
		);
	}
}