<?php
/* Created on May 8, 2011
 *
 * To change the template for this generated file go to
 * Window - Preferences - PHPeclipse - PHP - Code Templates
 */
 class DTUI_ViewPublic_EntryPoint_Orders extends XenForo_ViewPublic_Base {
 	
	public function renderHtml() {
		//die('html');
		//die('html');
		$Orders = "Vit Lon";
	}
	public function renderJson() {
		$orders = $this->_params['orders'];// Orders hien thi ket qua thanh html
		
		return array(
			'orders' => $orders,
		);
	}
 }
?>
