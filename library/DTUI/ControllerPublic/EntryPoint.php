<?php

class DTUI_ControllerPublic_EntryPoint extends DTUI_ControllerPublic_EntryPointQuanUH {

    public function actionIndex() {
		$viewParams = array();
	
		return $this->responseView('DTUI_ViewPublic_EntryPoint_Index', '', $viewParams);
    }
    
    public function actionUserInfo() {
    	return $this->responseView('DTUI_ViewPublic_EntryPoint_UserInfo', '', $viewParams);
    }

	protected function _getTableOrError($tableId) {
		$tableModel = $this->_getTableModel();
		$info = $tableModel->getTableById($tableId);
		if (empty($info)) {
		    throw new XenForo_Exception(new XenForo_Phrase('dtui_table_not_found'), true);
		}
	
		return $info;
    }
}