<?php
class DTUI_DataWriter_Item extends XenForo_DataWriter {
	protected function _getFields() {
		return array(
			'xf_dtui_item' => array(
				'item_id' => array('type' => 'uint', 'autoIncrement' => true),
				'item_name' => array('type' => 'string', 'required' => true, 'maxLength' => 255),
				'item_description' => array('type' => 'string'),
				'category_id' => array('type' => 'uint', 'required' => true),
				'price' => array('type' => 'float', 'required' => true),
				'item_options' => array('type' => 'serialized'),
				'item_order_count' => array('type' => 'uint', 'required' => true, 'default' => 0),
				'last_update_date' => array('type' => 'uint', 'required' => true)
			)
		);
	}

	protected function _getExistingData($data) {
		if (!$id = $this->_getExistingPrimaryKey($data, 'item_id')) {
			return false;
		}

		return array('xf_dtui_item' => $this->_getItemModel()->getItemById($id));
	}

	protected function _getUpdateCondition($tableName) {
		$conditions = array();
		
		foreach (array('item_id') as $field) {
			$conditions[] = $field . ' = ' . $this->_db->quote($this->getExisting($field));
		}
		
		return implode(' AND ', $conditions);
	}
	
	protected function _getItemModel() {
		return $this->getModelFromCache('DTUI_Model_Item');
	}
}