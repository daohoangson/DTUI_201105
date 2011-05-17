<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
class DTUI_ViewPublic_EntryPoint_Item extends XenForo_ViewPublic_Base {
	public function renderHtml() {
		die('html');
	}
	
	public function renderJson() {
		$item = $this->_params['item'];
		
		return array(
			'item' => $item,
		);
	}
}
?>
