<?php

class XenForo_ViewPublic_Account_Preferences extends XenForo_ViewPublic_Base
{
	public function prepareParams()
	{
		parent::prepareParams();

		$visitor = XenForo_Visitor::getInstance();

		if ($visitor['language_id'] == 0)
		{
			$visitor['effectiveLanguageId'] = XenForo_Application::get('options')->defaultLanguageId;
		}
		else
		{
			$visitor['effectiveLanguageId'] = $visitor['language_id'];
		}

		$this->_params['visitor'] = $visitor->toArray();
	}
}