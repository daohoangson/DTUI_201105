<?php
class DTUI_Model_Item extends DTUI_Model_WithImage {
	const FETCH_CATEGORY = 0x01;
	
	protected function _getImageInternal(array $data, $sizeCode) {
		return 'dtui/item/' . $data['item_id'] . $this->_getImageFileNameFromName($data['item_name']) . $sizeCode . '.jpg';		
	}
	
	public function prepareItem(array &$item) {
		$item['links'] = array();
		
		$item['links']['self'] = XenForo_Link::buildPublicLink('full:dtui-entry-point/item.json', $item['item_id']);
		
		$itemSimple = array();
		foreach ($item as $key => $value) {
			if (in_array($key, array(
				'item_id',
				'item_name',
				'item_description',
				'price',
				'category_id',
			))) {
				$itemSimple[$key] = $value;
			}
		}
		$item['qrcode'] = DTUI_Helper_QrCode::getUrl(array('item' => $itemSimple));
	}
	
	public function prepareItems(array &$items) {
		foreach ($items as &$item) {
			$this->prepareItem($item);
		}
	}
	
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
		
		if (!empty($fetchOptions['join'])) {
			if ($fetchOptions['join'] & self::FETCH_CATEGORY) {
				$selectFields .= ' ,category.* ';
				$joinTables .= ' LEFT JOIN `xf_dtui_category` AS category ON (category.category_id = item.category_id) ';
			}
		}

		return array(
			'selectFields' => $selectFields,
			'joinTables'   => $joinTables
		);
	}
	
	public function prepareItemOrderOptions(array &$fetchOptions, $defaultOrderSql = '') {
		$choices = array(
			'item_name' => 'item.item_name',
			'item_order_count' => 'item.item_order_count',
			'category_name' => 'category.category_name',
		);
		
		if (!empty($fetchOptions['order']) AND $fetchOptions['order'] == 'category_name') {
			if (empty($fetchOptions['join'])) $fetchOptions['join'] = 0;
			$fetchOptions['join'] |= self::FETCH_CATEGORY;
		}
		
		return $this->getOrderByClause($choices, $fetchOptions, $defaultOrderSql);
	}
}