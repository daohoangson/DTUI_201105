<?php

class XenForo_ViewPublic_Thread_ViewPosts extends XenForo_ViewPublic_Base
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
		$bbCodeParser = new XenForo_BbCode_Parser(XenForo_BbCode_Formatter_Base::create('Base', array('view' => $this)));
		$bbCodeOptions = array(
			'states' => array(
				'viewAttachments' => $this->_params['canViewAttachments']
			)
		);
		XenForo_ViewPublic_Helper_Message::bbCodeWrapMessages($this->_params['posts'], $bbCodeParser, $bbCodeOptions);

		$output = array('messagesTemplateHtml' => array());

		foreach ($this->_params['posts'] AS $postId => $post)
		{
			$output['messagesTemplateHtml']["#post-$postId"] =
				$this->createTemplateObject('post', array_merge($this->_params, array('post' => $post)))->render();
		}

		$template = $this->createTemplateObject('', array());

		$output['css'] = $template->getRequiredExternals('css');
		$output['js'] = $template->getRequiredExternals('js');

		return XenForo_ViewRenderer_Json::jsonEncodeForOutput($output);
	}
}