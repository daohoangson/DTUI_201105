<?php

class XenForo_ViewPublic_Thread_ViewNewPosts extends XenForo_ViewPublic_Thread_View
{
	public function renderHtml()
	{
		$bbCodeParser = new XenForo_BbCode_Parser(XenForo_BbCode_Formatter_Base::create('Base', array('view' => $this)));
		$bbCodeOptions = array(
			'states' => array(
				'viewAttachments' => $this->_params['canViewAttachments']
			)
		);
		XenForo_ViewPublic_Helper_Message::bbCodeWrapMessages($this->_params['posts'], $bbCodeParser, $bbCodeOptions);
	}

	public function renderJson()
	{
		$output = $this->_renderer->getDefaultOutputArray(get_class($this), $this->_params, $this->_templateName);

		$output['lastDate'] = $this->_params['lastPost']['post_date'];

		return XenForo_ViewRenderer_Json::jsonEncodeForOutput($output);
	}
}