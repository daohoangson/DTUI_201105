<?php

class XenForo_ViewPublic_Editor_Dialog extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		$this->_renderer->setNeedsContainer(false);

		$template = $this->createTemplateObject($this->_templateName, $this->_params);
		$output = $template->render();

		return $this->_renderer->replaceRequiredExternalPlaceholders($template, $output);
	}
}