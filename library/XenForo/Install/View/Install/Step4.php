<?php

class XenForo_Install_View_Install_Step4 extends XenForo_Install_View_Base
{
	public function renderHtml()
	{
		$dep = $this->_renderer->getDependencyHandler();
		$oldClass = $dep->templateClass;
		$dep->templateClass = 'XenForo_Template_Admin';

		XenForo_Template_Abstract::setLanguageId(1); // this is the language that was created

		$this->_params['renderedOptions'] = XenForo_ViewAdmin_Helper_Option::renderPreparedOptionsHtml(
			$this, $this->_params['options'], $this->_params['canEditOptionDefinition']
		);

		$dep->templateClass = $oldClass;
	}
}