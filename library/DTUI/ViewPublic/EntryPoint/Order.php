<?php
class DTUI_ViewPublic_EntryPoint_Order extends XenForo_ViewPublic_Base {
	public function renderJson() {
		return array(
			'order' => $this->_params['order'],
			'orderItems' => $this->_params['orderItems'],
		);
	}
}