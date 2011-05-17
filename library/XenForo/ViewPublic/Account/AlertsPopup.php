<?php

class XenForo_ViewPublic_Account_AlertsPopup extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		$this->_params['alertsUnread'] = XenForo_ViewPublic_Helper_Alert::getTemplates(
			$this,
			$this->_params['alertsUnread'],
			$this->_params['alertHandlers']
		);

		$this->_params['alertsRead'] = XenForo_ViewPublic_Helper_Alert::getTemplates(
			$this,
			$this->_params['alertsRead'],
			$this->_params['alertHandlers']
		);
	}
}