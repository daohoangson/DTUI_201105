<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
class DTUI_ViewPublic_EntryPoint_Categories extends XenForo_ViewPublic_Base {
	public function renderHtml() {
		die('html');
	}
	
	public function renderJson() {
		$categories = $this->_params['categories'];
		
		return array(
			'categories' => $categories,
		);
	}
}
?>
