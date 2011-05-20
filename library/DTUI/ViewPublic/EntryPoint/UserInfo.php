<?php
class DTUI_ViewPublic_EntryPoint_UserInfo extends XenForo_ViewPublic_Base {
	public function renderJson() {
		return array(
			'user' => $this->_params['user'],
		);
	}
}