<?php
class DTUI_Model_OrderItem extends XenForo_Model {
	const FETCH_ITEM = 1;
	const FETCH_ORDER = 2;
	const FETCH_TABLE = 4;
	const FETCH_TRIGGER_USER = 8;
	const FETCH_TARGET_USER = 16;
	
	public function canUpdateTask(array $user = null) {
		$this->standardizeViewingUserReference($user);
		
		if (!XenForo_Permission::hasPermission($user['permissions'], 'general', 'dtui_canUpdateTask')) {
			return false;
		}

		return true;
	}
	
	public function canMarkCompleted(array $orderItem, array $user = null) {
		$this->standardizeViewingUserReference($user);
		
		if ($this->canUpdateTask($user)) {
			if ($orderItem['target_user_id'] == $user['user_id']) {
				return true;
			}
		}
		
		return false;
	}
	
	public function canRevertCompleted(array $orderItem, array $user = null) {
		$this->standardizeViewingUserReference($user);
		
		if ($this->canUpdateTask($user)) {
			// the data writer will do further check
			return true;
		}
		
		return false;
	}
	
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
				FROM `xf_dtui_order_item` AS order_item
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
			FROM `xf_dtui_order_item` AS order_item
				$joinOptions[joinTables]
			WHERE $whereConditions
		");
	}
	
	public function prepareOrderItemConditions(array $conditions, array &$fetchOptions) {
		$sqlConditions = array();
		$db = $this->_getDb();
		
		foreach (array('order_item_id', 'order_id', 'trigger_user_id', 'target_user_id', 'item_id', 'order_item_date', 'status') as $intField) {
			if (!isset($conditions[$intField])) continue;
			
			if (is_array($conditions[$intField])) {
				$sqlConditions[] = "order_item.$intField IN (" . $db->quote($conditions[$intField]) . ")";
			} else {
				$sqlConditions[] = "order_item.$intField = " . $db->quote($conditions[$intField]);
			}
		}
		
		if (!empty($conditions['last_updated'])) {
			list($operator, $cutOff) = $conditions['last_updated'];

			$this->assertValidCutOffOperator($operator);
			$sqlConditions[] = "order_item.last_updated $operator " . $db->quote($cutOff);
		}
		
		return $this->getConditionsForClause($sqlConditions);
	}
	
	public function prepareOrderItemFetchOptions(array $fetchOptions) {
		$selectFields = '';
		$joinTables = '';
		
		if (!empty($fetchOptions['join'])) {
			if ($fetchOptions['join'] & self::FETCH_ITEM) {
				$selectFields .= ' ,item.* ';
				$joinTables .= ' INNER JOIN `xf_dtui_item` AS item ON (item.item_id = order_item.item_id) ';
			}
			
			if ($fetchOptions['join'] & self::FETCH_ORDER) {
				$selectFields .= ' ,`order`.* ';
				$joinTables .= ' INNER JOIN `xf_dtui_order` AS `order` ON (`order`.order_id = order_item.order_id) ';
				
				if ($fetchOptions['join'] & self::FETCH_TABLE) {
					$selectFields .= ' ,`table`.* ';
					$joinTables .= ' INNER JOIN `xf_dtui_table` AS `table` ON (`table`.table_id = `order`.table_id) ';
				}
			}
			
			if ($fetchOptions['join'] & self::FETCH_TRIGGER_USER) {
				$selectFields .= ' ,`user`.* ';
				$joinTables .= ' INNER JOIN `xf_user` AS `user` ON (`user`.user_id = order_item.trigger_user_id) ';
			} elseif ($fetchOptions['join'] & self::FETCH_TARGET_USER) {
				$selectFields .= ' ,`user`.* ';
				$joinTables .= ' INNER JOIN `xf_user` AS `user` ON (`user`.user_id = order_item.target_user_id) ';
			}
		}

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