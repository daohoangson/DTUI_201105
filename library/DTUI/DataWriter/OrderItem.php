<?php
class DTUI_DataWriter_OrderItem extends XenForo_DataWriter {
	const STATUS_WAITING = 'waiting';
	const STATUS_PREPARED = 'prepared';
	const STATUS_SERVED = 'served';
	const STATUS_PAID = 'paid';
	
	protected function _getFields() {
		return array(
			'xf_dtui_order_item' => array(
				'order_item_id' => array('type' => 'uint', 'autoIncrement' => true),
				'order_id' => array('type' => 'uint', 'required' => true),
				'trigger_user_id' => array('type' => 'uint', 'required' => true),
				'target_user_id' => array('type' => 'uint', 'required' => true),
				'item_id' => array('type' => 'uint', 'required' => true),
				'order_item_date' => array('type' => 'uint', 'default' => XenForo_Application::$time),
				'status' => array(
					'type' => 'string',
					'allowedValues' => array(
						self::STATUS_WAITING,
						self::STATUS_PREPARED,
						self::STATUS_SERVED,
						self::STATUS_PAID,
					),
					'default' => self::STATUS_WAITING
				)
			)
		);
	}

	protected function _getExistingData($data) {
		if (!$id = $this->_getExistingPrimaryKey($data, 'order_item_id')) {
			return false;
		}

		return array('xf_dtui_order_item' => $this->_getOrderItemModel()->getOrderItemById($id));
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