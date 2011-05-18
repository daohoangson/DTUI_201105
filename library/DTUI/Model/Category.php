<?php
class DTUI_Model_Category extends DTUI_Model_WithImage {
	protected function _getImageInternal(array $data, $sizeCode) {
		return 'dtui/category/' . $data['category_id'] . $this->_getImageFileNameFromName($data['category_name']) . $sizeCode . '.jpg';		
	}
	
	public function prepareCategory(array &$category) {
		$category['links'] = array();
		
		$category['links']['self'] = XenForo_Link::buildPublicLink('full:dtui-entry-point/category.json', $category['category_id']);
		$category['links']['items'] = XenForo_Link::buildPublicLink('full:dtui-entry-point/items.json', '', array('category_id' => $category['category_id']));
		
		$categorySimple = array();
		foreach ($category as $key => $value) {
			if (in_array($key, array(
				'category_id',
				'category_name',
				'category_description',
			))) {
				$categorySimple[$key] = $value;
			}
		}
		$category['qrcode'] = DTUI_Helper_QrCode::getUrl(array('category' => $categorySimple));
	}
	
	public function prepareCategories(array &$categories) {
		foreach ($categories as &$category) {
			$this->prepareCategory($category);
		}
	}
	
	public function getList(array $conditions = array(), array $fetchOptions = array()) {
		$data = $this->getAllCategory($conditions, $fetchOptions);
		$list = array();
		
		foreach ($data as $id => $row) {
			$list[$id] = $row['category_name'];
		}
		
		return $list;
	}

	public function getCategoryById($id, array $fetchOptions = array()) {
		$data = $this->getAllCategory(array ('category_id' => $id), $fetchOptions);
		
		return reset($data);
	}
	
	public function getAllCategory(array $conditions = array(), array $fetchOptions = array()) {
		$whereConditions = $this->prepareCategoryConditions($conditions, $fetchOptions);

		$orderClause = $this->prepareCategoryOrderOptions($fetchOptions);
		$joinOptions = $this->prepareCategoryFetchOptions($fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->fetchAllKeyed($this->limitQueryResults("
				SELECT category.*
					$joinOptions[selectFields]
				FROM `xf_dtui_category` AS category
					$joinOptions[joinTables]
				WHERE $whereConditions
					$orderClause
			", $limitOptions['limit'], $limitOptions['offset']
		), 'category_id');
	}
		
	public function countAllCategory(array $conditions = array(), array $fetchOptions = array()) {
		$whereConditions = $this->prepareCategoryConditions($conditions, $fetchOptions);

		$orderClause = $this->prepareCategoryOrderOptions($fetchOptions);
		$joinOptions = $this->prepareCategoryFetchOptions($fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->_getDb()->fetchOne("
			SELECT COUNT(*)
			FROM `xf_dtui_category` AS category
				$joinOptions[joinTables]
			WHERE $whereConditions
		");
	}
	
	public function prepareCategoryConditions(array $conditions, array &$fetchOptions) {
		$sqlConditions = array();
		$db = $this->_getDb();
		
		foreach (array('category_id') as $intField) {
			if (!isset($conditions[$intField])) continue;
			
			if (is_array($conditions[$intField])) {
				$sqlConditions[] = "category.$intField IN (" . $db->quote($conditions[$intField]) . ")";
			} else {
				$sqlConditions[] = "category.$intField = " . $db->quote($conditions[$intField]);
			}
		}
		
		return $this->getConditionsForClause($sqlConditions);
	}
	
	public function prepareCategoryFetchOptions(array $fetchOptions) {
		$selectFields = '';
		$joinTables = '';

		return array(
			'selectFields' => $selectFields,
			'joinTables'   => $joinTables
		);
	}
	
	public function prepareCategoryOrderOptions(array &$fetchOptions, $defaultOrderSql = '') {
		$choices = array(
			'category_id' => 'category.category_id',
			'category_name' => 'category.category_name',
		);
		return $this->getOrderByClause($choices, $fetchOptions, $defaultOrderSql);
	}
}