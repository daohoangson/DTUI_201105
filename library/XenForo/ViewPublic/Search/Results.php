<?php

class XenForo_ViewPublic_Search_Results extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		$this->_params['results'] = XenForo_ViewPublic_Helper_Search::renderSearchResults(
			$this, $this->_params['results'], $this->_params['search']
		);
	}
}