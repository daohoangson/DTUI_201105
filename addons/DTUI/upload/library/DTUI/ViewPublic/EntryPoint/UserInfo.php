<?php
class DTUI_ViewPublic_EntryPoint_UserInfo extends XenForo_ViewPublic_Base {
	public function renderJson() {
		$visitor = XenForo_Visitor::getInstance()->toArray();
		
		/*
		$user = array();
		$user['user_id'] = $visitor['user_id'];
		$user['username'] = $visitor['username'];
		*/
		$user = $visitor;
		
		return array(
			'user' => $user,
		);
	}
}