<?php
class DTUI_ViewPublic_EntryPoint_Table extends XenForo_ViewPublic_Base {
	public function renderJson() {
		return array(
			'table' => $this->_params['table'],
		);
	}
}