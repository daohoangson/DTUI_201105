<?php
abstract class DTUI_ControllerPublic_EntryPointBase extends XenForo_ControllerPublic_Abstract {
	protected function _getTableOrError($tableId) {
		$info = $this->_getTableModel()->getTableById($tableId);
		
		if (empty($info)) {
			throw new XenForo_Exception(new XenForo_Phrase('dtui_table_not_found'), true);
		}
		
		return $info;
	}
	
	protected function _getOrderOrError($orderId) {
		$info = $this->_getOrderModel()->getOrderById($orderId);
		
		if (empty($info)) {
			throw new XenForo_Exception(new XenForo_Phrase('dtui_order_not_found'), true);
		}
		
		return $info;
	}
	
	protected function _getOrderItemOrError($orderItemId) {
		$info = $this->_getOrderItemModel()->getOrderItemByid($orderItemId);
		
		if (empty($info)) {
			throw new XenForo_Exception(new XenForo_Phrase('dtui_order_item_not_found'), true);
		}
		
		return $info;
	}
	
	protected function _getCategoryOrError($categoryId) {
		$info = $this->_getCategoryModel()->getCategoryById($categoryId);
		
		if (empty($info)) {
			throw new XenForo_Exception(new XenForo_Phrase('dtui_category_not_found'), true);
		}
		
		return $info;
	}
	
	protected function _getItemOrError($itemId) {
		$info = $this->_getItemModel()->getItemById($itemId);
		
		if (empty($info)) {
			throw new XenForo_Exception(new XenForo_Phrase('dtui_item_not_found'), true);
		}
		
		return $info;
	}
	
	protected function _getTableModel() {
		return $this->getModelFromCache('DTUI_Model_Table');
	}
	    
	protected function _getOrderModel() {
		return $this->getModelFromCache('DTUI_Model_Order');
	}
	
	protected function _getOrderItemModel() {
		return $this->getModelFromCache('DTUI_Model_OrderItem');
	}
	  
	protected function _getCategoryModel() {
		return $this->getModelFromCache('DTUI_Model_Category');
	}
	  
	protected function _getItemModel() {
		return $this->getModelFromCache('DTUI_Model_Item');
	}
	
	protected function _preDispatchFirst($action) {
		if (!$this->_request->isPost()) {
			self::$_executed['csrf'] = true;
		}
	}
}