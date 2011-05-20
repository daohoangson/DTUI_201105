<?php
class DTUI_ViewPublic_EntryPoint_OrderItems extends XenForo_ViewPublic_Base {
	public function renderJson() {
		return array(
			'orderItems' => $this->_params['orderItems'],
		);
	}
}