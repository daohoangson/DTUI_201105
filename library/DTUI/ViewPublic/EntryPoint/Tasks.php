<?php
/*
 * Created on May 8, 2011
 *
 * To change the template for this generated file go to
 * Window - Preferences - PHPeclipse - PHP - Code Templates
 */
 class DTUI_ViewPublic_EntryPoint_Tasks extends XenForo_ViewPublic_Base {
	public function renderHtml() {
		//die('html');
	}
	
	public function renderJson() {
		return array(
			'tasks' => $this->_params['tasks'],
			'direction' => $this->_params['direction'],
		);
	}
 }
?>
