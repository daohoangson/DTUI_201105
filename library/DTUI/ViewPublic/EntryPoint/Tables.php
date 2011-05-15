<?php
class DTUI_ViewPublic_EntryPoint_Tables extends XenForo_ViewPublic_Base {
	public function renderHtml() {
		die('html');
	}
	
	public function renderJson() {
		$tables = $this->_params['tables'];
		
		return array(
			'tables' => $tables,
		);
	}
}