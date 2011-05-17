<?php
class DTUI_DataWriter_Category extends DTUI_DataWriter_WithImage {
	protected function _getImageModel() {
		return $this->_getCategoryModel();		
	}
	
	protected function _getFields() {
		return array(
			'xf_dtui_category' => array(
				'category_id' => array('type' => 'uint', 'autoIncrement' => true),
				'category_name' => array('type' => 'string', 'required' => true, 'maxLength' => 255),
				'category_description' => array('type' => 'string'),
				'category_options' => array('type' => 'serialized')
			)
		);
	}

	protected function _getExistingData($data) {
		if (!$id = $this->_getExistingPrimaryKey($data, 'category_id')) {
			return false;
		}

		return array('xf_dtui_category' => $this->_getCategoryModel()->getCategoryById($id));
	}

	protected function _getUpdateCondition($tableName) {
		$conditions = array();
		
		foreach (array('category_id') as $field) {
			$conditions[] = $field . ' = ' . $this->_db->quote($this->getExisting($field));
		}
		
		return implode(' AND ', $conditions);
	}
	
	protected function _getCategoryModel() {
		return $this->getModelFromCache('DTUI_Model_Category');
	}
}