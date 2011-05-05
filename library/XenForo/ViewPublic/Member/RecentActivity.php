<?php

class XenForo_ViewPublic_Member_RecentActivity extends XenForo_ViewPublic_Base
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
		if (!empty($this->_params['startNewsFeedId']))
		{
			// this is for loading more entries
			$this->renderHtml();

			return XenForo_ViewRenderer_Json::jsonEncodeForOutput(array(
				'templateHtml' => $this->createTemplateObject('news_feed', $this->_params),
				'oldestItemId' => $this->_params['oldestItemId'],
				'feedEnds' => $this->_params['feedEnds']
			));
		}
	}
}