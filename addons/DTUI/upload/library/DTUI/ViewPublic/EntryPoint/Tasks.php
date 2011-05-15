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
		$tasks = $this->_params['tasks'];// $items de hien thi ket qua ra html
		
		return array(
			'tasks' => $tasks,
		);
	}
 }
?>
