<?php
class DTUI_Model_Table extends XenForo_Model {
	
	public function prepareTable(array &$table) {
		$table['links'] = array();
		
		$table['links']['self'] = XenForo_Link::buildPublicLink('full:dtui-entry-point/table.json', $table['table_id']);
		
		$tableSimple = array();
		foreach ($table as $key => $value) {
			if (in_array($key, array(
				'table_id',
				'table_name',
			))) {
				$tableSimple[$key] = $value;
			}
		}
		$table['qrcode'] = DTUI_Helper_QrCode::getUrl(array('table' => $tableSimple));
	}
	
	public function prepareTables(array &$tables) {
		foreach ($tables as &$table) {
			$this->prepareTable($table);
		}
	}
	
	public function getList(array $conditions = array(), array $fetchOptions = array()) {
		$data = $this->getAllTable($conditions, $fetchOptions);
		$list = array();
		
		foreach ($data as $id => $row) {
			$list[$id] = $row['table_name'];
		}
		
		return $list;
	}

	public function getTableById($id, array $fetchOptions = array()) {
		$data = $this->getAllTable(array ('table_id' => $id), $fetchOptions);
		
		return reset($data);
	}
	
	public function getAllTable(array $conditions = array(), array $fetchOptions = array()) {
		$whereConditions = $this->prepareTableConditions($conditions, $fetchOptions);

		$orderClause = $this->prepareTableOrderOptions($fetchOptions);
		$joinOptions = $this->prepareTableFetchOptions($fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->fetchAllKeyed($this->limitQueryResults("
				SELECT `table`.*
					$joinOptions[selectFields]
				FROM `xf_dtui_table` AS `table`
					$joinOptions[joinTables]
				WHERE $whereConditions
					$orderClause
			", $limitOptions['limit'], $limitOptions['offset']
		), 'table_id');
	}
		
	public function countAllTable(array $conditions = array(), array $fetchOptions = array()) {
		$whereConditions = $this->prepareTableConditions($conditions, $fetchOptions);

		$orderClause = $this->prepareTableOrderOptions($fetchOptions);
		$joinOptions = $this->prepareTableFetchOptions($fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->_getDb()->fetchOne("
			SELECT COUNT(*)
			FROM `xf_dtui_table` AS `table`
				$joinOptions[joinTables]
			WHERE $whereConditions
		");
	}
	
	public function prepareTableConditions(array $conditions, array &$fetchOptions) {
		$sqlConditions = array();
		$db = $this->_getDb();
		
		foreach (array('table_id', 'last_order_id', 'table_order_count') as $intField) {
			if (!isset($conditions[$intField])) continue;
			
			if (is_array($conditions[$intField])) {
				$sqlConditions[] = "`table`.$intField IN (" . $db->quote($conditions[$intField]) . ")";
			} else {
				$sqlConditions[] = "`table`.$intField = " . $db->quote($conditions[$intField]);
			}
		}
		
		return $this->getConditionsForClause($sqlConditions);
	}
	
	public function prepareTableFetchOptions(array $fetchOptions) {
		$selectFields = '';
		$joinTables = '';

		return array(
			'selectFields' => $selectFields,
			'joinTables'   => $joinTables
		);
	}
	
	public function prepareTableOrderOptions(array &$fetchOptions, $defaultOrderSql = '') {
		$choices = array(
			
		);
		return $this->getOrderByClause($choices, $fetchOptions, $defaultOrderSql);
	}
}