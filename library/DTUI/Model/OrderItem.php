<?php
class DTUI_Model_OrderItem extends XenForo_Model {
	public function getList(array $conditions = array(), array $fetchOptions = array()) {
		$data = $this->getAllOrderItem($conditions, $fetchOptions);
		$list = array();
		
		foreach ($data as $id => $row) {
			$list[$id] = $row['status'];
		}
		
		return $list;
	}

	public function getOrderItemById($id, array $fetchOptions = array()) {
		$data = $this->getAllOrderItem(array ('order_item_id' => $id), $fetchOptions);
		
		return reset($data);
	}
	
	public function getAllOrderItem(array $conditions = array(), array $fetchOptions = array()) {
		$whereConditions = $this->prepareOrderItemConditions($conditions, $fetchOptions);

		$orderClause = $this->prepareOrderItemOrderOptions($fetchOptions);
		$joinOptions = $this->prepareOrderItemFetchOptions($fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->fetchAllKeyed($this->limitQueryResults("
				SELECT order_item.*
					$joinOptions[selectFields]
				FROM `xf_order_item` AS order_item
					$joinOptions[joinTables]
				WHERE $whereConditions
					$orderClause
			", $limitOptions['limit'], $limitOptions['offset']
		), 'order_item_id');
	}
		
	public function countAllOrderItem(array $conditions = array(), array $fetchOptions = array()) {
		$whereConditions = $this->prepareOrderItemConditions($conditions, $fetchOptions);

		$orderClause = $this->prepareOrderItemOrderOptions($fetchOptions);
		$joinOptions = $this->prepareOrderItemFetchOptions($fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->_getDb()->fetchOne("
			SELECT COUNT(*)
			FROM `xf_order_item` AS order_item
				$joinOptions[joinTables]
			WHERE $whereConditions
		");
	}
	
	public function prepareOrderItemConditions(array $conditions, array &$fetchOptions) {
		$sqlConditions = array();
		$db = $this->_getDb();
		
		foreach (array('order_item_id', 'order_id', 'trigger_user_id', 'target_user_id', 'item_id', 'order_item_date') as $intField) {
			if (!isset($conditions[$intField])) continue;
			
			if (is_array($conditions[$intField])) {
				$sqlConditions[] = "order_item.$intField IN (" . $db->quote($conditions[$intField]) . ")";
			} else {
				$sqlConditions[] = "order_item.$intField = " . $db->quote($conditions[$intField]);
			}
		}
		
		return $this->getConditionsForClause($sqlConditions);
	}
	
	public function prepareOrderItemFetchOptions(array $fetchOptions) {
		$selectFields = '';
		$joinTables = '';

		return array(
			'selectFields' => $selectFields,
			'joinTables'   => $joinTables
		);
	}
	
	public function prepareOrderItemOrderOptions(array &$fetchOptions, $defaultOrderSql = '') {
		$choices = array(
			
		);
		return $this->getOrderByClause($choices, $fetchOptions, $defaultOrderSql);
	}
}