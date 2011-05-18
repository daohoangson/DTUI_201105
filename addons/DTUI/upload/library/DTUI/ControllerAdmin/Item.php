<?php
class DTUI_ControllerAdmin_Item extends XenForo_ControllerAdmin_Abstract {
	public function actionIndex() {
		$model = $this->_getItemModel();
		$allItem = $model->getAllItem();
		$model->prepareImagesMultiple($allItem);
		
		$viewParams = array(
			'allItem' => $allItem
		);
		
		return $this->responseView('DTUI_ViewAdmin_Item_List', 'dtui_item_list', $viewParams);
	}
	
	public function actionAdd() {
		$viewParams = array(
			'item' => array(),
			'allCategory' => $this->getModelFromCache('DTUI_Model_Category')->getList(),
		);
		
		return $this->responseView('DTUI_ViewAdmin_Item_Edit', 'dtui_item_edit', $viewParams);
	}
	
	public function actionEdit() {
		$id = $this->_input->filterSingle('item_id', XenForo_Input::UINT);
		$item = $this->_getItemOrError($id);
		$this->_getItemModel()->prepareImages($item);
		
		$viewParams = array(
			'item' => $item,
			'allCategory' => $this->getModelFromCache('DTUI_Model_Category')->getList(),
		);
		
		return $this->responseView('DTUI_ViewAdmin_Item_Edit', 'dtui_item_edit', $viewParams);
	}
	
	public function actionSave() {
		$this->_assertPostOnly();
		
		$id = $this->_input->filterSingle('item_id', XenForo_Input::UINT);

		$dwInput = $this->_input->filter(array(
			'item_name' => 'string',
			'item_description' => 'string',
			'category_id' => 'uint',
			'price' => 'float',
		));
		
		$dw = $this->_getItemDataWriter();
		if ($id) {
			$dw->setExistingData($id);
		}
		$dw->bulkSet($dwInput);
		
		$image = XenForo_Upload::getUploadedFile('image');
		if (!empty($image)) {
			$dw->setImage($image);
		}
		
		$dw->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('dtui-items') . $this->getLastHash($id)
		);
	}
	
	public function actionDelete() {
		$id = $this->_input->filterSingle('item_id', XenForo_Input::UINT);
		$item = $this->_getItemOrError($id);
		
		if ($this->isConfirmedPost()) {
			$dw = $this->_getItemDataWriter();
			$dw->setExistingData($id);
			$dw->delete();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('dtui-items')
			);
		} else {
			$viewParams = array(
				'item' => $item
			);

			return $this->responseView('DTUI_ViewAdmin_Item_Delete', 'dtui_item_delete', $viewParams);
		}
	}
	
	
	protected function _getItemOrError($id, array $fetchOptions = array()) {
		$info = $this->_getItemModel()->getItemById($id, $fetchOptions);
		
		if (empty($info)) {
			throw $this->responseException($this->responseError(new XenForo_Phrase('dtui_item_not_found'), 404));
		}
		
		return $info;
	}
	
	protected function _getItemModel() {
		return $this->getModelFromCache('DTUI_Model_Item');
	}
	
	protected function _getItemDataWriter() {
		return XenForo_DataWriter::create('DTUI_DataWriter_Item');
	}
}