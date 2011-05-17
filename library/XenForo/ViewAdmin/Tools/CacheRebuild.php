<?php

class XenForo_ViewAdmin_Tools_CacheRebuild extends XenForo_ViewAdmin_Base
{
	public function renderJson()
	{
		$output = $this->_renderer->getDefaultOutputArray(get_class($this), $this->_params, $this->_templateName);
		$output['elements'] = $this->_params['elements'];
		$output['rebuildMessage'] = $this->_params['rebuildMessage'];
		$output['detailedMessage'] = $this->_params['detailedMessage'];
		$output['showExitLink'] = $this->_params['showExitLink'];

		return $output;
	}
}