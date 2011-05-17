<?php

class XenForo_ViewPublic_NewsFeed_View extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		$this->_params['newsFeed'] = XenForo_ViewPublic_Helper_NewsFeed::getTemplates(
			$this,
			$this->_params['newsFeed'],
			$this->_params['newsFeedHandlers']
		);
	}

	public function renderJson()
	{
		// prepare templates for output in JSON
		$this->renderHtml();

		return XenForo_ViewRenderer_Json::jsonEncodeForOutput(array(
			'templateHtml' => $this->createTemplateObject('news_feed', $this->_params),
			'oldestItemId' => $this->_params['oldestItemId'],
			'feedEnds' => $this->_params['feedEnds']
		));
	}
}