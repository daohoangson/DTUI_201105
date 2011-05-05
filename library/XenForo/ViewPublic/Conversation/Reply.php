<?php

class XenForo_ViewPublic_Conversation_Reply extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		$this->_params['editorTemplate'] = XenForo_ViewPublic_Helper_Editor::getEditorTemplate(
			$this, 'message', $this->_params['defaultMessage']
		);
	}

	public function renderJson()
	{
		$bbCodeParser = new XenForo_BbCode_Parser(XenForo_BbCode_Formatter_Base::create('Wysiwyg', array('view' => $this)));

		return XenForo_ViewRenderer_Json::jsonEncodeForOutput(array(
			'quote' => $this->_params['defaultMessage'],
			'quoteHtml' => $bbCodeParser->render($this->_params['defaultMessage'])
		));
	}
}