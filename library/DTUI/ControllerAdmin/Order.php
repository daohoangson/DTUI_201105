<?php
class DTUI_ControllerAdmin_Order extends XenForo_ControllerAdmin_Abstract {
	public function actionIndex() {
		$model = $this->_getOrderModel();
		$allOrder = $model->getAllOrder();
		
		$viewParams = array(
			'allOrder' => $allOrder
		);
		
		return $this->responseView('DTUI_ViewAdmin_Order_List', 'dtui_order_list', $viewParams);
	}
	
	public function actionAdd() {
		$viewParams = array(
			'order' => array(),
			'allTable' => $this->getModelFromCache('DTUI_Model_Table')->getList(),
		);
		
		return $this->responseView('DTUI_ViewAdmin_Order_Edit', 'dtui_order_edit', $viewParams);
	}
	
	public function actionEdit() {
		$id = $this->_input->filterSingle('order_id', XenForo_Input::UINT);
		$order = $this->_getOrderOrError($id);
		
		$viewParams = array(
			'order' => $order,
			'allTable' => $this->getModelFromCache('DTUI_Model_Table')->getList(),
		);
		
		return $this->responseView('DTUI_ViewAdmin_Order_Edit', 'dtui_order_edit', $viewParams);
	}
	
	public function actionSave() {
		$this->_assertPostOnly();
		
		$id = $this->_input->filterSingle('order_id', XenForo_Input::UINT);

		$dwInput = $this->_input->filter(array('table_id' => 'uint', 'order_date' => 'uint', 'is_paid' => 'boolean', 'paid_amount' => 'float'));
		
		$dw = $this->_getOrderDataWriter();
		if ($id) {
			$dw->setExistingData($id);
		}
		$dw->bulkSet($dwInput);
		$dw->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('dtui-orders')
		);
	}
	
	public function actionDelete() {
		$id = $this->_input->filterSingle('order_id', XenForo_Input::UINT);
		$order = $this->_getOrderOrError($id);
		
		if ($this->isConfirmedPost()) {
			$dw = $this->_getOrderDataWriter();
			$dw->setExistingData($id);
			$dw->delete();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('dtui-orders')
			);
		} else {
			$viewParams = array(
				'order' => $order
			);

			return $this->responseView('DTUI_ViewAdmin_Order_Delete', 'dtui_order_delete', $viewParams);
		}
	}
	
	
	protected function _getOrderOrError($id, array $fetchOptions = array()) {
		$info = $this->_getOrderModel()->getOrderById($id, $fetchOptions);
		
		if (empty($info)) {
			throw $this->responseException($this->responseError(new XenForo_Phrase('dtui_order_not_found'), 404));
		}
		
		return $info;
	}
	
	protected function _getOrderModel() {
		return $this->getModelFromCache('DTUI_Model_Order');
	}
	
	protected function _getOrderDataWriter() {
		return XenForo_DataWriter::create('DTUI_DataWriter_Order');
	}
}