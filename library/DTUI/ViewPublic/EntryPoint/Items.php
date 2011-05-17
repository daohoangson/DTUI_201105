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
		if (empty($this->_params['category'])) {
			// all items
			return array(
				'items' => $this->_params['items'],
			);
		} else {
			// items in a category
			return array(
				'category' => $this->_params['category'],
				'items' => $this->_params['items'],
			);
		}
	}
}
?>
