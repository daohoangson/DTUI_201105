<?php

class XenForo_ViewPublic_Thread_ListItemEdit extends XenForo_ViewPublic_Base
{
	public function renderJson()
	{
		$output = $this->_renderer->getDefaultOutputArray(get_class($this), $this->_params, $this->_templateName);

		$output['threadId'] = $this->_params['thread']['thread_id'];

		return XenForo_ViewRenderer_Json::jsonEncodeForOutput($output);
	}
}