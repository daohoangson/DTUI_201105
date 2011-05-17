<?php

class XenForo_ViewPublic_Editor_ToHtml extends XenForo_ViewPublic_Base
{
	public function renderJson()
	{
		$bbCodeParser = new XenForo_BbCode_Parser(XenForo_BbCode_Formatter_Base::create('Wysiwyg', array('view' => $this)));
		return array(
			'html' => $bbCodeParser->render($this->_params['bbCode'])
		);
	}
}