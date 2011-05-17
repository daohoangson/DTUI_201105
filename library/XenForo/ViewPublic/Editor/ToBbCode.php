<?php

class XenForo_ViewPublic_Editor_ToBbCode extends XenForo_ViewPublic_Base
{
	public function renderJson()
	{
		return array(
			'bbCode' => $this->_params['bbCode']
		);
	}
}