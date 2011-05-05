<?php
class DTUI_ControllerAdmin_Category extends XenForo_ControllerAdmin_Abstract {
	public function actionIndex() {
		$model = $this->_getCategoryModel();
		$allCategory = $model->getAllCategory();
		
		$viewParams = array(
			'allCategory' => $allCategory
		);
		
		return $this->responseView('DTUI_ViewAdmin_Category_List', 'dtui_category_list', $viewParams);
	}
	
	public function actionAdd() {
		$viewParams = array(
			'category' => array(),
			
		);
		
		return $this->responseView('DTUI_ViewAdmin_Category_Edit', 'dtui_category_edit', $viewParams);
	}
	
	public function actionEdit() {
		$id = $this->_input->filterSingle('category_id', XenForo_Input::UINT);
		$category = $this->_getCategoryOrError($id);
		
		$viewParams = array(
			'category' => $category,
			
		);
		
		return $this->responseView('DTUI_ViewAdmin_Category_Edit', 'dtui_category_edit', $viewParams);
	}
	
	public function actionSave() {
		$this->_assertPostOnly();
		
		$id = $this->_input->filterSingle('category_id', XenForo_Input::UINT);

		$dwInput = $this->_input->filter(array(
			'category_name' => 'string',
			'category_description' => 'string'
		));
		
		$dw = $this->_getCategoryDataWriter();
		if ($id) {
			$dw->setExistingData($id);
		}
		$dw->bulkSet($dwInput);
		$dw->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('dtui-categories')
		);
	}
	
	public function actionDelete() {
		$id = $this->_input->filterSingle('category_id', XenForo_Input::UINT);
		$category = $this->_getCategoryOrError($id);
		
		if ($this->isConfirmedPost()) {
			$dw = $this->_getCategoryDataWriter();
			$dw->setExistingData($id);
			$dw->delete();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('dtui-categories')
			);
		} else {
			$viewParams = array(
				'category' => $category
			);

			return $this->responseView('DTUI_ViewAdmin_Category_Delete', 'dtui_category_delete', $viewParams);
		}
	}
	
	
	protected function _getCategoryOrError($id, array $fetchOptions = array()) {
		$info = $this->_getCategoryModel()->getCategoryById($id, $fetchOptions);
		
		if (empty($info)) {
			throw $this->responseException($this->responseError(new XenForo_Phrase('dtui_category_not_found'), 404));
		}
		
		return $info;
	}
	
	protected function _getCategoryModel() {
		return $this->getModelFromCache('DTUI_Model_Category');
	}
	
	protected function _getCategoryDataWriter() {
		return XenForo_DataWriter::create('DTUI_DataWriter_Category');
	}
}