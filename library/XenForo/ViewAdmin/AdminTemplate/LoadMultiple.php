<?php

class XenForo_ViewAdmin_AdminTemplate_LoadMultiple extends XenForo_ViewAdmin_Base
{
	public function prepareParams()
	{
		parent::prepareParams();

		$this->_params['templates'] = array();

		$keys = array('template_id', 'title', 'template', 'link');

		foreach ($this->_params['templateData'] AS $template)
		{
			$this->_params['templates'][$template['title']] = XenForo_Application::arrayFilterKeys($template, $keys);
		}
	}

	public function renderJson()
	{
		return XenForo_ViewRenderer_Json::jsonEncodeForOutput(array(
			'templates' => $this->_params['templates'],
			'saveMessage' => new XenForo_Phrase('all_templates_saved_successfully')
		));
	}
}