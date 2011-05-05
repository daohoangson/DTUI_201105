<?php

class XenForo_ViewPublic_Editor_Media extends XenForo_ViewPublic_Base
{
	public function renderJson()
	{
		return XenForo_ViewRenderer_Json::jsonEncodeForOutput($this->_params);
	}
}