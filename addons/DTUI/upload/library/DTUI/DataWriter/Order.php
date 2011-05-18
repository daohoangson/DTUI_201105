<?php
class DTUI_DataWriter_Order extends XenForo_DataWriter {
	protected function _preSave() {
		if ($this->get('is_paid') AND $this->get('paid_amount') == 0) {
			// this order is paid but paid amount hasn't been updated yet
			// we will have to update it here 
			$orderItems = $this->getModelFromCache('DTUI_Model_OrderItem')->getAllOrderItem(array(
				'order_id' => $this->get('order_id')
			), array(
				'join' => DTUI_Model_OrderItem::FETCH_ITEM,
			));
			$paidAmount = 0;
			
			foreach ($orderItems as $orderItem) {
				if ($orderItem['status'] != DTUI_DataWriter_OrderItem::STATUS_PAID) {
					// check to make sure, just in case
					throw new XenForo_Exception(new XenForo_Phrase('dtui_can_not_update_order_is_paid_outstanding_items'), true);
				}
				
				$paidAmount += $orderItem['price'];
			}
			
			$this->set('paid_amount', $paidAmount);
		}
		
		return parent::_preSave();
	}
	
	protected function _postSave() {
		$tableDw = XenForo_DataWriter::create('DTUI_DataWriter_Table');
		$tableDw->setExistingData($this->get('table_id'));
		
		if ($this->isInsert()) {
			if ($tableDw->get('is_busy')) {
				throw new XenForo_Exception(new XenForo_Phrase('dtui_can_not_create_new_order_for_busy_table'), true);
			}
			$tableDw->set('is_busy', true);
			$tableDw->set('last_order_id', $this->get('order_id'));
			$tableDw->set('table_order_count', $tableDw->get('table_order_count') + 1);
		} else {
			if ($this->get('is_paid')) {
				// the order is now paid
				// un-busy the table
				$tableDw->set('is_busy', false);
			}
		}
		
		$tableDw->save();
		
		return parent::_postSave();
	}
	
	protected function _getFields() {
		return array(
			'xf_dtui_order' => array(
				'order_id' => array('type' => 'uint', 'autoIncrement' => true),
				'table_id' => array('type' => 'uint', 'required' => true),
				'order_date' => array('type' => 'uint', 'default' => XenForo_Application::$time),
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