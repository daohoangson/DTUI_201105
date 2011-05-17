<?php
class DTUI_Model_Item extends XenForo_Model {
	public function getList(array $conditions = array(), array $fetchOptions = array()) {
		$data = $this->getAllItem($conditions, $fetchOptions);
		$list = array();
		
		foreach ($data as $id => $row) {
			$list[$id] = $row['item_name'];
		}
		
		return $list;
	}

	public function getItemById($id, array $fetchOptions = array()) {
		$data = $this->getAllItem(array ('item_id' => $id), $fetchOptions);
		
		return reset($data);
	}
	
	public function getAllItem(array $conditions = array(), array $fetchOptions = array()) {
		$whereConditions = $this->prepareItemConditions($conditions, $fetchOptions);

		$orderClause = $this->prepareItemOrderOptions($fetchOptions);
		$joinOptions = $this->prepareItemFetchOptions($fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->fetchAllKeyed($this->limitQueryResults("
				SELECT item.*
					$joinOptions[selectFields]
				FROM `xf_dtui_item` AS item
					$joinOptions[joinTables]
				WHERE $whereConditions
					$orderClause
			", $limitOptions['limit'], $limitOptions['offset']
		), 'item_id');
	}
		
	public function countAllItem(array $conditions = array(), array $fetchOptions = array()) {
		$whereConditions = $this->prepareItemConditions($conditions, $fetchOptions);

		$orderClause = $this->prepareItemOrderOptions($fetchOptions);
		$joinOptions = $this->prepareItemFetchOptions($fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->_getDb()->fetchOne("
			SELECT COUNT(*)
			FROM `xf_dtui_item` AS item
				$joinOptions[joinTables]
			WHERE $whereConditions
		");
	}
	
	public function prepareItemConditions(array $conditions, array &$fetchOptions) {
		$sqlConditions = array();
		$db = $this->_getDb();
		
		foreach (array('item_id', 'category_id', 'item_order_count') as $intField) {
			if (!isset($conditions[$intField])) continue;
			
			if (is_array($conditions[$intField])) {
				$sqlConditions[] = "item.$intField IN (" . $db->quote($conditions[$intField]) . ")";
			} else {
				$sqlConditions[] = "item.$intField = " . $db->quote($conditions[$intField]);
			}
		}
		
		return $this->getConditionsForClause($sqlConditions);
	}
	
	public function prepareItemFetchOptions(array $fetchOptions) {
		$selectFields = '';
		$joinTables = '';

		return array(
			'selectFields' => $selectFields,
			'joinTables'   => $joinTables
		);
	}
	
	public function prepareItemOrderOptions(array &$fetchOptions, $defaultOrderSql = '') {
		$choices = array(
			
		);
		return $this->getOrderByClause($choices, $fetchOptions, $defaultOrderSql);
	}
}