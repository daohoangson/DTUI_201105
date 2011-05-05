<?php
class DTUI_Model_Order extends XenForo_Model {
	public function getList(array $conditions = array(), array $fetchOptions = array()) {
		$data = $this->getAllOrder($conditions, $fetchOptions);
		$list = array();
		
		foreach ($data as $id => $row) {
			$list[$id] = $row[''];
		}
		
		return $list;
	}

	public function getOrderById($id, array $fetchOptions = array()) {
		$data = $this->getAllOrder(array ('order_id' => $id), $fetchOptions);
		
		return reset($data);
	}
	
	public function getAllOrder(array $conditions = array(), array $fetchOptions = array()) {
		$whereConditions = $this->prepareOrderConditions($conditions, $fetchOptions);

		$orderClause = $this->prepareOrderOrderOptions($fetchOptions);
		$joinOptions = $this->prepareOrderFetchOptions($fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->fetchAllKeyed($this->limitQueryResults("
				SELECT order.*
					$joinOptions[selectFields]
				FROM `xf_dtui_order` AS order
					$joinOptions[joinTables]
				WHERE $whereConditions
					$orderClause
			", $limitOptions['limit'], $limitOptions['offset']
		), 'order_id');
	}
		
	public function countAllOrder(array $conditions = array(), array $fetchOptions = array()) {
		$whereConditions = $this->prepareOrderConditions($conditions, $fetchOptions);

		$orderClause = $this->prepareOrderOrderOptions($fetchOptions);
		$joinOptions = $this->prepareOrderFetchOptions($fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->_getDb()->fetchOne("
			SELECT COUNT(*)
			FROM `xf_dtui_order` AS order
				$joinOptions[joinTables]
			WHERE $whereConditions
		");
	}
	
	public function prepareOrderConditions(array $conditions, array &$fetchOptions) {
		$sqlConditions = array();
		$db = $this->_getDb();
		
		foreach (array('order_id', 'table_id', 'order_date') as $intField) {
			if (!isset($conditions[$intField])) continue;
			
			if (is_array($conditions[$intField])) {
				$sqlConditions[] = "order.$intField IN (" . $db->quote($conditions[$intField]) . ")";
			} else {
				$sqlConditions[] = "order.$intField = " . $db->quote($conditions[$intField]);
			}
		}
		
		return $this->getConditionsForClause($sqlConditions);
	}
	
	public function prepareOrderFetchOptions(array $fetchOptions) {
		$selectFields = '';
		$joinTables = '';

		return array(
			'selectFields' => $selectFields,
			'joinTables'   => $joinTables
		);
	}
	
	public function prepareOrderOrderOptions(array &$fetchOptions, $defaultOrderSql = '') {
		$choices = array(
			
		);
		return $this->getOrderByClause($choices, $fetchOptions, $defaultOrderSql);
	}
}