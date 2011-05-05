<?php

class XenForo_ViewPublic_Thread_Save_ThreadListItem extends XenForo_ViewPublic_Base
{
	public function renderJson()
	{
		return XenForo_ViewRenderer_Json::jsonEncodeForOutput(array(
			'templateHtml' => $this->createTemplateObject('thread_list_item', $this->_params),
			'threadId' => $this->_params['thread']['thread_id']
		));
	}
}