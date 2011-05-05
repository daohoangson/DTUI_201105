<?php
class DTUI_DataWriter_OrderItem extends XenForo_DataWriter {
	protected function _getFields() {
		return array(
			'xf_order_item' => array(
				'order_item_id' => array('type' => 'uint', 'autoIncrement' => true),
				'order_id' => array('type' => 'uint', 'required' => true),
				'trigger_user_id' => array('type' => 'uint', 'required' => true),
				'target_user_id' => array('type' => 'uint', 'required' => true),
				'item_id' => array('type' => 'uint', 'required' => true),
				'order_item_date' => array('type' => 'uint', 'required' => true),
				'status' => array(
					'type' => 'string',
					'allowedValues' => array('waiting', 'served', 'paid'),
					'required' => true
				)
			)
		);
	}

	protected function _getExistingData($data) {
		if (!$id = $this->_getExistingPrimaryKey($data, 'order_item_id')) {
			return false;
		}

		return array('xf_order_item' => $this->_getOrderItemModel()->getOrderItemById($id));
	}

	protected function _getUpdateCondition($tableName) {
		$conditions = array();
		
		foreach (array('order_item_id') as $field) {
			$conditions[] = $field . ' = ' . $this->_db->quote($this->getExisting($field));
		}
		
		return implode(' AND ', $conditions);
	}
	
	protected function _getOrderItemModel() {
		return $this->getModelFromCache('DTUI_Model_OrderItem');
	}
}