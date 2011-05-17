<?php
class DTUI_ViewPublic_EntryPoint_UpdateTask extends XenForo_ViewPublic_Base {
	public function renderJson() {
		return array(
			'orderItem' => $this->_params['orderItem'],
		);
	}
}