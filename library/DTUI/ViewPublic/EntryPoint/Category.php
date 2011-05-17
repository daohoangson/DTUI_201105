<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
class DTUI_ViewPublic_EntryPoint_Category extends XenForo_ViewPublic_Base {
	public function renderHtml() {
		die('html');
	}
	
	public function renderJson() {
		$category = $this->_params['category'];
		
		return array(
			'category' => $category,
		);
	}
}
?>
