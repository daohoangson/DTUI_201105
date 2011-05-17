<?php
abstract class DTUI_ControllerPublic_EntryPointBase extends XenForo_ControllerPublic_Abstract {
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