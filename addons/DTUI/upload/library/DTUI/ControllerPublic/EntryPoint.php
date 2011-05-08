<?php
class DTUI_ControllerPublic_EntryPoint extends XenForo_ControllerPublic_Abstract {
	public function actionIndex() {
		$viewParams = array();
		
		return $this->responseView('DTUI_ViewPublic_EntryPoint_Index', '', $viewParams);
	}
	
	public function actionTables() {
		$tableId = $this->_input->filterSingle('data', XenForo_Input::UINT);
		
		$table = $this->_getTableOrError($tableId);
		
		var_dump($tableId);exit;
		
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