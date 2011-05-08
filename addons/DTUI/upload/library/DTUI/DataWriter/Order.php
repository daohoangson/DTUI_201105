<?php
class DTUI_DataWriter_Order extends XenForo_DataWriter {
	protected function _getFields() {
		return array(
			'xf_dtui_order' => array(
				'order_id' => array('type' => 'uint', 'autoIncrement' => true),
				'table_id' => array('type' => 'uint', 'required' => true),
				'order_date' => array('type' => 'uint', 'required' => true),
				'is_paid' => array('type' => 'boolean', 'required' => true, 'default' => 0),
				'paid_amount' => array('type' => 'float', 'required' => true, 'default' => 0)
			)
		);
	}

	protected function _getExistingData($data) {
		if (!$id = $this->_getExistingPrimaryKey($data, 'order_id')) {
			return false;
		}

		return array('xf_dtui_order' => $this->_getOrderModel()->getOrderById($id));
	}

	protected function _getUpdateCondition($tableName) {
		$conditions = array();
		
		foreach (array('order_id') as $field) {
			$conditions[] = $field . ' = ' . $this->_db->quote($this->getExisting($field));
		}
		
		return implode(' AND ', $conditions);
	}
	
	protected function _getOrderModel() {
		return $this->getModelFromCache('DTUI_Model_Order');
	}
}