<?php

class XenForo_ViewPublic_Page_View extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		$this->_params['templateHtml'] = $this->createTemplateObject(
			$this->_params['templateTitle'],
			$this->_params
		);
	}
}