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
	}
	
	protected function _getTableOrError($tableId) {
		$tableModel = $this->_getTableModel();
		$info = $tableModel->getTableById($tableid);
		// TODO: from here
	}
	
	protected function _preDispatchFirst($action) {
		if (!$this->_request->isPost()) {
			self::$_executed['csrf'] = true;
		}
	}
}