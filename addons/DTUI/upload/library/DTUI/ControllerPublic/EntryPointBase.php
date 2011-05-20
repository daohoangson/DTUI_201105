<?php
abstract class DTUI_ControllerPublic_EntryPointBase extends XenForo_ControllerPublic_Abstract {
	protected function _getTableOrError($tableId, array $fetchOptions = array()) {
		$info = $this->_getTableModel()->getTableById($tableId, $fetchOptions);
		
		if (empty($info)) {
			throw new XenForo_Exception(new XenForo_Phrase('dtui_table_not_found'), true);
		}
		
		return $info;
	}
	
	protected function _getOrderOrError($orderId, array $fetchOptions = array()) {
		$info = $this->_getOrderModel()->getOrderById($orderId, $fetchOptions);
		
		if (empty($info)) {
			throw new XenForo_Exception(new XenForo_Phrase('dtui_order_not_found'), true);
		}
		
		return $info;
	}
	
	protected function _getOrderItemOrError($orderItemId, array $fetchOptions = array()) {
		$info = $this->_getOrderItemModel()->getOrderItemByid($orderItemId, $fetchOptions);
		
		if (empty($info)) {
			throw new XenForo_Exception(new XenForo_Phrase('dtui_order_item_not_found'), true);
		}
		
		return $info;
	}
	
	protected function _getCategoryOrError($categoryId, array $fetchOptions = array()) {
		$info = $this->_getCategoryModel()->getCategoryById($categoryId, $fetchOptions);
		
		if (empty($info)) {
			throw new XenForo_Exception(new XenForo_Phrase('dtui_category_not_found'), true);
		}
		
		return $info;
	}
	
	protected function _getItemOrError($itemId, array $fetchOptions = array()) {
		$info = $this->_getItemModel()->getItemById($itemId, $fetchOptions);
		
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
	
	protected function _getTaskModel() {
		return $this->getModelFromCache('DTUI_Model_Task');
	}
	
	protected function _preDispatchFirst($action) {
		if (!$this->_request->isPost()) {
			self::$_executed['csrf'] = true;
		}
		
		return parent::_preDispatchFirst($action);
	}
	
	protected function _preDispatch($action) {
		if (!$this->_getItemModel()->canAccess()) {
			throw new XenForo_Exception(new XenForo_Phrase('dtui_you_have_no_access_to_this_system'), true);
		}
		
		return parent::_preDispatch($action);
	}
}