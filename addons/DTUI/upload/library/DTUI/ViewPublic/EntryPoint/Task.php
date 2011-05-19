<?php
class DTUI_ViewPublic_EntryPoint_Task extends XenForo_ViewPublic_Base {
	public function renderJson() {
		return array(
			'orderItem' => $this->_params['orderItem'],
		);
	}
}