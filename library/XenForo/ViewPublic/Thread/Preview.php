<?php

class XenForo_ViewPublic_Thread_Preview extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		$previewLength = XenForo_Application::get('options')->discussionPreviewLength;

		if ($previewLength && !empty($this->_params['post']))
		{
			$formatter = XenForo_BbCode_Formatter_Base::create('XenForo_BbCode_Formatter_Text');
			$parser = new XenForo_BbCode_Parser($formatter);

			$this->_params['post']['messageParsed'] = $parser->render($this->_params['post']['message']);
		}
	}
}