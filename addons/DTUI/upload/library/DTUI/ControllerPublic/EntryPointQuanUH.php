<?php
abstract class DTUI_ControllerPublic_EntryPointQuanUH extends DTUI_ControllerPublic_EntryPointManhHX {
    public function actionCategories() {
    	$categoryModel = $this->_getCategoryModel();
    	$conditions = array();
    	$fetchOptions = array();
    	
    	$defaultOrder = 'category_id';
		$defaultOrderDirection = 'asc';
		$order = $this->_input->filterSingle('order', XenForo_Input::STRING, array('default' => $defaultOrder));
		$orderDirection = $this->_input->filterSingle('direction', XenForo_Input::STRING, array('default' => $defaultOrderDirection));
		$fetchOptions['order'] = $order;
		$fetchOptions['direction'] = $orderDirection;
    	
		$categories = $categoryModel->getAllCategory($conditions, $fetchOptions);
		$categoryModel->prepareImagesMultiple($categories);
		$categoryModel->prepareCategories($categories);
		
		$orderParams = array();
		foreach (array('category_id', 'category_name') AS $field) {
			$orderParams[$field]['order'] = ($field != $defaultOrder ? $field : false);
			if ($order == $field) {
				$orderParams[$field]['direction'] = ($orderDirection == 'desc' ? 'asc' : 'desc');
			}
		}
		
		$viewParams = array(
		    'categories' => $categories,
		
			'order' => $order,
			'orderDirection' => $orderDirection,
			'orderParams' => $orderParams,
		);
		
		return $this->responseView('DTUI_ViewPublic_EntryPoint_Categories', 'dtui_entry_point_categories', $viewParams);
    }

    public function actionCategory() {
		$categoryId = $this->_input->filterSingle('data', XenForo_Input::UINT);
		
		$categoryModel = $this->_getCategoryModel();
		
		$category = $this->_getCategoryOrError($categoryId);
		$categoryModel->prepareImages($category);
		$categoryModel->prepareCategory($category);
		
		$viewParams = array(
		    'category' => $category,
		);

		return $this->responseView('DTUI_ViewPublic_EntryPoint_Category', '', $viewParams);
    }
    
    public function actionCategoryQrcode() {
    	$response = $this->actionCategory();
    	
    	if ($response instanceof XenForo_ControllerResponse_View) {
    		$category =& $response->params['category'];
    		
    		$viewParams = array(
    			'title' => $category['category_name'],
    			'qrcode' => $category['qrcode'],
    			'breadCrumbs' => array(
    				array('href' => XenForo_Link::buildPublicLink('dtui-entry-point/categories'), 'value' => new XenForo_Phrase('dtui_categories')),
    				array('href' => XenForo_Link::buildPublicLink('dtui-entry-point/category', $category['category_id']), 'value' => $category['category_name']),
    			),
    		);
    		
    		return $this->responseView('DTUI_ViewPublic_EntryPoint_QrCode', 'dtui_entry_point_qrcode', $viewParams);
    	}
    	
    	return $response;
    }
    
    public function actionItems() {
		$categoryId = $this->_input->filterSingle('category_id', XenForo_Input::UINT);
		
		$itemModel = $this->_getItemModel();
		$conditions = array();
    	$fetchOptions = array(
    		'join' => DTUI_Model_Item::FETCH_CATEGORY,
    	);
    	$category = false;
    	
    	$defaultOrder = 'item_name';
		$defaultOrderDirection = 'asc';
		$order = $this->_input->filterSingle('order', XenForo_Input::STRING, array('default' => $defaultOrder));
		$orderDirection = $this->_input->filterSingle('direction', XenForo_Input::STRING, array('default' => $defaultOrderDirection));
		$fetchOptions['order'] = $order;
		$fetchOptions['direction'] = $orderDirection;
		
		if (!empty($categoryId)) {
			$category = $this->_getCategoryOrError($categoryId);
			$conditions['category_id'] = $category['category_id'];
		}
		
		$items = $itemModel->getAllItem($conditions, $fetchOptions);
		$itemModel->prepareImagesMultiple($items);
		$itemModel->prepareItems($items);
		
    	$orderParams = array();
		foreach (array('item_name', 'category_name', 'item_order_count') AS $field) {
			$orderParams[$field]['order'] = ($field != $defaultOrder ? $field : false);
			if ($order == $field) {
				$orderParams[$field]['direction'] = ($orderDirection == 'desc' ? 'asc' : 'desc');
			}
			if (!empty($category)) $orderParams[$field]['category_id'] = $category['category_id'];
		}
		
		$viewParams = array(
			'category' => $category,
		    'items' => $items,
		
			'order' => $order,
			'orderDirection' => $orderDirection,
			'orderParams' => $orderParams,
		);
		
		return $this->responseView('DTUI_ViewPublic_EntryPoint_Items', 'dtui_entry_point_items', $viewParams);
    }

    public function actionItem() {
		$itemId = $this->_input->filterSingle('data', XenForo_Input::UINT);
		
		$itemModel = $this->_getItemModel();
    	$fetchOptions = array(
    		'join' => DTUI_Model_Item::FETCH_CATEGORY,
    	);
		
		$item = $this->_getItemOrError($itemId, $fetchOptions);
		$itemModel->prepareImages($item);
		$itemModel->prepareItem($item);
		
		$viewParams = array(
		    'item' => $item,
		);
		
		return $this->responseView('DTUI_ViewPublic_EntryPoint_Item', '', $viewParams);
    }
    
	public function actionItemQrcode() {
    	$response = $this->actionItem();
    	
    	if ($response instanceof XenForo_ControllerResponse_View) {
    		$item =& $response->params['item'];
    		
    		$viewParams = array(
    			'title' => $item['item_name'],
    			'qrcode' => $item['qrcode'],
    			'breadCrumbs' => array(
    				array('href' => XenForo_Link::buildPublicLink('dtui-entry-point/categories'), 'value' => new XenForo_Phrase('dtui_categories')),
    				array('href' => XenForo_Link::buildPublicLink('dtui-entry-point/items', '', array('category_id' => $item['category_id'])), 'value' => $item['category_name']),
    				array('href' => XenForo_Link::buildPublicLink('dtui-entry-point/item', $item['item_id']), 'value' => $item['item_name']),
    			),
    		);
    		
    		return $this->responseView('DTUI_ViewPublic_EntryPoint_QrCode', 'dtui_entry_point_qrcode', $viewParams);
    	}
    	
    	return $response;
    }
}