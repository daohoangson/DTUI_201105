<?php
class DTUI_DataWriter_Table extends XenForo_DataWriter {
	protected function _getFields() {
		return array(
			'xf_dtui_table' => array(
				'table_id' => array('type' => 'uint', 'autoIncrement' => true),
				'table_name' => array('type' => 'string', 'required' => true, 'maxLength' => 255),
				'table_description' => array('type' => 'string'),
				'is_busy' => array('type' => 'boolean', 'required' => true, 'default' => 0),
				'last_order_id' => array('type' => 'uint', 'required' => true, 'default' => 0),
				'table_order_count' => array('type' => 'uint', 'required' => true, 'default' => 0)
			)
		);
	}

	protected function _getExistingData($data) {
		if (!$id = $this->_getExistingPrimaryKey($data, 'table_id')) {
			return false;
		}

		return array('xf_dtui_table' => $this->_getTableModel()->getTableById($id));
	}

	protected function _getUpdateCondition($tableName) {
		$conditions = array();
		
		foreach (array('table_id') as $field) {
			$conditions[] = $field . ' = ' . $this->_db->quote($this->getExisting($field));
		}
		
		return implode(' AND ', $conditions);
	}
	
	protected function _getTableModel() {
		return $this->getModelFromCache('DTUI_Model_Table');
	}
}