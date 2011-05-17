<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
class DTUI_ViewPublic_EntryPoint_Items extends XenForo_ViewPublic_Base {
	public function renderHtml() {
		die('html');
	}
	
	public function renderJson() {
		$items = $this->_params['items'];
		
		return array(
			'items' => $items,
		);
	}
}
?>
