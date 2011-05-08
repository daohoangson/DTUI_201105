<?php
class DTUI_ViewPublic_EntryPoint_Table extends XenForo_ViewPublic_Base {
	public function renderHtml() {
		die('html');
	}
	
	public function renderJson() {
		$table = $this->_params['table'];
		
		return array(
			'table' => $table,
		);
	}
}