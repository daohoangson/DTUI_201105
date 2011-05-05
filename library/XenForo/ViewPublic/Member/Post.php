<?php

class XenForo_ViewPublic_Member_Post extends XenForo_ViewPublic_Base
{
	public function renderJson()
	{
		$output = $this->_renderer->getDefaultOutputArray(get_class($this), $this->_params, $this->_templateName);

		if ($this->_params['isStatus'])
		{
			$output['statusHtml'] =
				XenForo_Template_Helper_Core::helperBodyText($this->_params['profilePost']['message']) . ' '
				. XenForo_Template_Helper_Core::dateTimeHtml($this->_params['profilePost']['post_date']);
		}

		return XenForo_ViewRenderer_Json::jsonEncodeForOutput($output);
	}
}