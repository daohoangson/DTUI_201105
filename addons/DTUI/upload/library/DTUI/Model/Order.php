<?php
class DTUI_Model_Order extends XenForo_Model {
	public function newOrder(array $table, array $items, array $itemIds, array $user = null) {
		$this->standardizeViewingUserReference($user);
		
		XenForo_Db::beginTransaction();

		try {
			$orderDw = XenForo_DataWriter::create('DTUI_DataWriter_Order');
			$orderDw->set('table_id', $table['table_id']);
			$orderDw->save();
			$order = $orderDw->getMergedData();
	 
			foreach ($itemIds as $itemId) {
				$item =& $items[$itemId];
				
				$orderItemDw = XenForo_DataWriter::create('DTUI_DataWriter_OrderItem');
				$orderItemDw->set('order_id', $order['order_id']);
				$orderItemDw->set('item_id', $item['item_id']);
				$orderItemDw->updateStatus($user);
				$orderItemDw->save();
			}
		} catch (Exception $e) {
			XenForo_Db::rollback();
			throw $e;
		}
		
		XenForo_Db::commit();
		
		return $order;
	}
	
	public function canNewOrder(array $user = null) {
		$this->standardizeViewingUserReference($user);
		
		if (XenForo_Permission::hasPermission($user['permissions'], 'general', 'dtui_canNewOrder')) {
			return true;
		}

		return false;
	}
	
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
				SELECT `order`.*
					$joinOptions[selectFields]
				FROM `xf_dtui_order` AS `order`
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
			FROM `xf_dtui_order` AS `order`
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
				$sqlConditions[] = "`order`.$intField IN (" . $db->quote($conditions[$intField]) . ")";
			} else {
				$sqlConditions[] = "`order`.$intField = " . $db->quote($conditions[$intField]);
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