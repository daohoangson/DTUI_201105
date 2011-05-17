<?php

class XenForo_ViewPublic_Account_Index extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		$this->_params['alerts'] = XenForo_ViewPublic_Helper_Alert::dateSplit(
			XenForo_ViewPublic_Helper_Alert::getTemplates(
				$this,
				$this->_params['alerts'],
				$this->_params['alertHandlers']
			), 'event_date'
		);
	}
}