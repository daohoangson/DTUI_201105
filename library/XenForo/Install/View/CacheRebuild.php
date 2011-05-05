<?php

class XenForo_Install_View_CacheRebuild extends XenForo_Install_View_Base
{
	public function renderJson()
	{
		$output = $this->_renderer->getDefaultOutputArray(get_class($this), $this->_params, $this->_templateName);
		$output['elements'] = $this->_params['elements'];
		$output['rebuildMessage'] = $this->_params['rebuildMessage'];
		$output['detailedMessage'] = $this->_params['detailedMessage'];
		$output['showExitLink'] = false;

		return $output;
	}
}