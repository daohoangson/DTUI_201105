<?php

class DTUI_ControllerPublic_EntryPoint extends XenForo_ControllerPublic_Abstract {

    public function actionIndex() {
	$viewParams = array();

	return $this->responseView('DTUI_ViewPublic_EntryPoint_Index', '', $viewParams);
    }

    //quan sua
    public function actionCategories() {
	$categories = $this->getModelFromCache('DTUI_Model_Category')->getAllCategory();
	$viewParams = array(
	    'categories' => $categories,
	);
	return $this->responseView('DTUI_ViewPublic_EntryPoint_Categories', '', $viewParams);
    }

    public function actionItems() {
	$category_id = $this->_input->filterSingle('category_id', XenForo_Input::UINT);
	$conditions = array('category_id' => $category_id);
	$items = $this->getModelFromCache('DTUI_Model_Item')->getAllItem($conditions);
	$viewParams = array(
	    'items' => $items,
	);
	return $this->responseView('DTUI_ViewPublic_EntryPoint_Items', '', $viewParams);
    }

    public function actionCategory() {
	$category_id = $this->_input->filterSingle('data', XenForo_Input::UINT);
	$category = $this->getModelFromCache('DTUI_Model_Category')->getCategoryById($category_id);
	$viewParams = array(
	    'category' => $category,
	);
	return $this->responseView('DTUI_ViewPublic_EntryPoint_Category', '', $viewParams);
    }

    public function actionItem() {
	$item_id = $this->_input->filterSingle('data', XenForo_Input::UINT);
	$item = $this->getModelFromCache('DTUI_Model_Item')->getItemById($item_id);
	$viewParams = array(
	    'item' => $item,
	);
	return $this->responseView('DTUI_ViewPublic_EntryPoint_Item', '', $viewParams);
    }

    //
    public function actionTables() {
	$tableId = $this->_input->filterSingle('data', XenForo_Input::UINT);

	$table = $this->_getTableOrError($tableId);

	var_dump($tableId);
	exit;

	return $this->responseView('DTUI_ViewPublic_EntryPoint_Tables');
    }

    public function actionTable() {
	$tableId = $this->_input->filterSingle('data', XenForo_Input::UINT);
	$table = $this->_getTableOrError($tableId);

	$viewParams = array(
	    'table' => $table,
	);

	return $this->responseView('DTUI_ViewPublic_EntryPoint_Table', '', $viewParams);
    }

    public function actionNewOrder() {
	
    }

    protected function _getTableOrError($tableId) {
	$tableModel = $this->_getTableModel();
	$info = $tableModel->getTableById($tableId);
	if (empty($info)) {
	    throw new XenForo_Exception(new XenForo_Phrase('dtui_table_not_found'), true);
	}

	return $info;
    }

    protected function _getTableModel() {
	return $this->getModelFromCache('DTUI_Model_Table');
    }

    protected function _preDispatchFirst($action) {
	if (!$this->_request->isPost()) {
	    self::$_executed['csrf'] = true;
	}
    }

}