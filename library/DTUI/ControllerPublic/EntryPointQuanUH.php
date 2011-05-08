<?php
abstract class DTUI_ControllerPublic_EntryPointQuanUH extends DTUI_ControllerPublic_EntryPointManhHX {
    public function actionCategories() {
		$categories = $this->_getCategoryModel()->getAllCategory();
		
		$viewParams = array(
		    'categories' => $categories,
		);
		
		return $this->responseView('DTUI_ViewPublic_EntryPoint_Categories', '', $viewParams);
    }

    public function actionItems() {
		$category_id = $this->_input->filterSingle('category_id', XenForo_Input::UINT);
		$conditions = array('category_id' => $category_id);
		$items = $this->_getItemModel()->getAllItem($conditions);
		
		$viewParams = array(
		    'items' => $items,
		);
		
		return $this->responseView('DTUI_ViewPublic_EntryPoint_Items', '', $viewParams);
    }

    public function actionCategory() {
		$category_id = $this->_input->filterSingle('data', XenForo_Input::UINT);
		$category = $this->_getCategoryModel()->getCategoryById($category_id);
		
		$viewParams = array(
		    'category' => $category,
		);
		
		return $this->responseView('DTUI_ViewPublic_EntryPoint_Category', '', $viewParams);
    }

    public function actionItem() {
		$item_id = $this->_input->filterSingle('data', XenForo_Input::UINT);
		
		$item = $this->_getItemModel()->getItemById($item_id);
		
		$viewParams = array(
		    'item' => $item,
		);
		
		return $this->responseView('DTUI_ViewPublic_EntryPoint_Item', '', $viewParams);
    }
}