<?php
abstract class DTUI_ControllerPublic_EntryPointQuanUH extends DTUI_ControllerPublic_EntryPointManhHX {
    public function actionCategories() {
    	$categoryModel = $this->_getCategoryModel();
    	
		$categories = $categoryModel->getAllCategory();
		$categoryModel->prepareImagesMultiple($categories);
		$categoryModel->prepareCategories($categories);
		
		$viewParams = array(
		    'categories' => $categories,
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
    
    public function actionItems() {
		$categoryId = $this->_input->filterSingle('category_id', XenForo_Input::UINT);
		
		$itemModel = $this->_getItemModel();
		
		if (!empty($categoryId)) {
			$category = $this->_getCategoryOrError($categoryId);
			$items = $itemModel->getAllItem(array('category_id' => $category['category_id']));
		} else {
			$items = $itemModel->getAllItem();
		}
		$itemModel->prepareImagesMultiple($items);
		$itemModel->prepareItems($items);
		
		$viewParams = array(
		    'items' => $items,
		);
		if (!empty($category)) $viewParams['category'] = $category; // add the found category to viewParams (optional)
		
		return $this->responseView('DTUI_ViewPublic_EntryPoint_Items', 'dtui_entry_point_items', $viewParams);
    }

    public function actionItem() {
		$itemId = $this->_input->filterSingle('data', XenForo_Input::UINT);
		
		$itemModel = $this->_getItemModel();
		
		$item = $this->_getItemOrError($itemId);
		$itemModel->prepareImages($item);
		$itemModel->prepareItem($item);
		
		$viewParams = array(
		    'item' => $item,
		);
		
		return $this->responseView('DTUI_ViewPublic_EntryPoint_Item', '', $viewParams);
    }
}