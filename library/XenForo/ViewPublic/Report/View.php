<?php

class XenForo_ViewPublic_Report_View extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		$report =& $this->_params['report'];

		$report['extraContentTemplate'] = call_user_func_array(
			$report['viewCallback'], array($this, &$report, &$report['extraContent'])
		);
	}
}