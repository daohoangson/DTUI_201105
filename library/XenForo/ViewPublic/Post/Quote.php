<?php

class XenForo_ViewPublic_Post_Quote extends XenForo_ViewPublic_Base
{
	public function renderJson()
	{
		$bbCodeParser = new XenForo_BbCode_Parser(XenForo_BbCode_Formatter_Base::create('Wysiwyg', array('view' => $this)));

		return XenForo_ViewRenderer_Json::jsonEncodeForOutput(array(
			'quote' => $this->_params['quote'],
			'quoteHtml' => $bbCodeParser->render($this->_params['quote'])
		));
	}
}