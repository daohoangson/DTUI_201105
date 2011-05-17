<?php
class DTUI_ControllerAdmin_Table extends XenForo_ControllerAdmin_Abstract {
	public function actionIndex() {
		$model = $this->_getTableModel();
		$allTable = $model->getAllTable();
		
		$viewParams = array(
			'allTable' => $allTable
		);
		
		return $this->responseView('DTUI_ViewAdmin_Table_List', 'dtui_table_list', $viewParams);
	}
	
	public function actionAdd() {
		$viewParams = array(
			'table' => array(),
			
		);
		
		return $this->responseView('DTUI_ViewAdmin_Table_Edit', 'dtui_table_edit', $viewParams);
	}
	
	public function actionEdit() {
		$id = $this->_input->filterSingle('table_id', XenForo_Input::UINT);
		$table = $this->_getTableOrError($id);
		
		$viewParams = array(
			'table' => $table,
			
		);
		
		return $this->responseView('DTUI_ViewAdmin_Table_Edit', 'dtui_table_edit', $viewParams);
	}
	
	public function actionSave() {
		$this->_assertPostOnly();
		
		$id = $this->_input->filterSingle('table_id', XenForo_Input::UINT);

		$dwInput = $this->_input->filter(array('table_name' => 'string', 'table_description' => 'string', 'is_busy' => 'boolean', 'last_order_id' => 'uint', 'table_order_count' => 'uint'));
		
		$dw = $this->_getTableDataWriter();
		if ($id) {
			$dw->setExistingData($id);
		}
		$dw->bulkSet($dwInput);
		$dw->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('dtui-tables')
		);
	}
	
	public function actionDelete() {
		$id = $this->_input->filterSingle('table_id', XenForo_Input::UINT);
		$table = $this->_getTableOrError($id);
		
		if ($this->isConfirmedPost()) {
			$dw = $this->_getTableDataWriter();
			$dw->setExistingData($id);
			$dw->delete();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('dtui-tables')
			);
		} else {
			$viewParams = array(
				'table' => $table
			);

			return $this->responseView('DTUI_ViewAdmin_Table_Delete', 'dtui_table_delete', $viewParams);
		}
	}
	
	
	protected function _getTableOrError($id, array $fetchOptions = array()) {
		$info = $this->_getTableModel()->getTableById($id, $fetchOptions);
		
		if (empty($info)) {
			throw $this->responseException($this->responseError(new XenForo_Phrase('dtui_table_not_found'), 404));
		}
		
		return $info;
	}
	
	protected function _getTableModel() {
		return $this->getModelFromCache('DTUI_Model_Table');
	}
	
	protected function _getTableDataWriter() {
		return XenForo_DataWriter::create('DTUI_DataWriter_Table');
	}
}