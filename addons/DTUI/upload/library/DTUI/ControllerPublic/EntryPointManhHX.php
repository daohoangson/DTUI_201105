<?php
abstract class DTUI_ControllerPublic_EntryPointManhHX extends DTUI_ControllerPublic_EntryPointBase {
	public function actionNewOrder() {
		$orderModel = $this->_getOrderModel();
		
		if (!$orderModel->canNewOrder()) {
			return $this->responseNoPermission();
		}
		
		$input = $this->_input->filter(array(
			'table_id' => XenForo_Input::UINT,
			'item_ids' => array(XenForo_Input::UINT, 'array' => true)
		));
		
		if ($this->_request->isPost()) {
			// this is a POST request
			// start creating new order
			
			foreach ($input['item_ids'] as $key => $itemId) {
				if (empty($itemId)) {
					unset($input['item_ids'][$key]);
				}
			}
			
			$table = $this->_getTableOrError($input['table_id']);
			$items = array();
			foreach ($input['item_ids'] as $itemId) {
				$items[$itemId] = $this->_getItemOrError($itemId);
			}
			
			$order = $orderModel->newOrder($table, $items, $input['item_ids']);
			
			$this->_request->setParam('data', $order['order_id']);
			return $this->responseReroute('DTUI_ControllerPublic_EntryPoint', 'order');
		} else {
			// this is a GET request
			// display a form
			$tables = $this ->_getTableModel()->getAllTable(array('is_busy' => 0));
			$items = $this->_getItemModel()->getAllItem(array(), array(
				'join' => DTUI_Model_Item::FETCH_CATEGORY,
				'order' => 'category_id',
				'direction' => 'asc',
			));
			
			$viewParams = array(
				'tables' => $tables,
				'items' => $items,
			
				'input' => $input,
			);
			
			return $this -> responseView('DTUI_ViewPublic_EntryPoint_OrderNew','dtui_entry_point_new_order',$viewParams);
		}
	}
	
	public function actionTasks(){ 
		$conditions = array('target_user_id' => XenForo_Visitor::getUserId());
		$fetchOptions = array(
			'join' => DTUI_Model_OrderItem::FETCH_ITEM + DTUI_Model_OrderItem::FETCH_ORDER 
				+ DTUI_Model_OrderItem::FETCH_TABLE
				+ DTUI_Model_OrderItem::FETCH_TARGET_USER,
		);
		
		if (XenForo_Visitor::getInstance()->isSuperAdmin()) {
			if ($this->_input->filterSingle('all', XenForo_Input::UINT)) {
				unset($conditions['target_user_id']);
			}
		}
		
		
		$lastUpdated = $this->_input->filterSingle('last_updated', XenForo_Input::UINT);
		if (!empty($lastUpdated)) {
			$conditions['last_updated'] = array('>', $lastUpdated);
		}
		
		$orderItems = $this->_getOrderItemModel()->getAllOrderItem($conditions, $fetchOptions);
		$this->_getItemModel()->prepareImagesMultiple($orderItems);
		
		$viewParams = array(
			'tasks' => $orderItems,
		);
		
		return $this -> responseView('DTUI_ViewPublic_EntryPoint_Tasks','dtui_entry_point_tasks',$viewParams);
	}
	
	protected function _actionOrderItems(array $orderItems) {
		$viewParams = array(
			'orderItems' => $orderItems,
		);
		
		return $this->responseView('DTUI_ViewPublic_EntryPoint_OrderItems', '', $viewParams);
	}
	
	public function actionTaskMarkCompleted() {
		$this->_assertPostOnly();
		
		$input = $this->_input->filter(array(
			'order_item_ids' => array(XenForo_Input::UINT, 'array' => true)
		));
		$orderItems = array();
		
		XenForo_Db::beginTransaction();
		
		try {
			foreach ($input['order_item_ids'] as $orderItemId) {
				$orderItem = $this->_getOrderItemOrError($orderItemId);
				
				if (!$this->_getOrderItemModel()->canMarkCompleted($orderItem)) {
					return $this->responseNoPermission();
				}
				
				$dw = XenForo_DataWriter::create('DTUI_DataWriter_OrderItem');
				$dw->setExistingData($orderItem, true);
				$dw->updateStatus(XenForo_Visitor::getInstance()->toArray());
				$dw->save();
				
				$orderItem = $dw->getMergedData();
				$orderItems[$orderItem['order_item_id']] = $orderItem;
			}
		} catch (Exception $e) {
			XenForo_Db::rollback();
			throw $e;
		}
		
		XenForo_Db::commit();
		
		return $this->_actionOrderItems($orderItems);
	}
	
	public function actionTaskRevertCompleted() {
		$this->_assertPostOnly();
		
		$input = $this->_input->filter(array(
			'order_item_ids' => array(XenForo_Input::UINT, 'array' => true)
		));
		$orderItems = array();
		
		XenForo_Db::beginTransaction();
		
		try {
			foreach ($input['order_item_ids'] as $orderItemId) {
				$orderItem = $this->_getOrderItemOrError($orderItemId);
				
				if (!$this->_getOrderItemModel()->canRevertCompleted($orderItem)) {
					return $this->responseNoPermission();
				}
				
				$dw = XenForo_DataWriter::create('DTUI_DataWriter_OrderItem');
				$dw->setExistingData($orderItem, true);
				$dw->revertStatus(XenForo_Visitor::getInstance()->toArray());
				$dw->save();
				
				$orderItem = $dw->getMergedData();
				$orderItems[$orderItem['order_item_id']] = $orderItem;
			}
		} catch (Exception $e) {
			XenForo_Db::rollback();
			throw $e;
		}
		
		XenForo_Db::commit();
		
		return $this->_actionOrderItems($orderItems);
	}
	
	public function actionOrders(){
		$orders = $this->_getOrderModel()->getAllOrder();
		
		$viewParams = array(
			'orders' => $orders
		);
		
		return $this -> responseView('DTUI_ViewPublic_EntryPoint_Orders','dtui_entry_point_orders',$viewParams);
	}
	
	public function actionOrder() {
		$orderId = $this->_input->filterSingle('data', XenForo_Input::UINT);
		
		$order = $this->_getOrderOrError($orderId);
		
		$orderItems = $this->_getOrderItemModel()->getAllOrderItem(array('order_id' => $order['order_id']));
		
		$viewParams = array(
			'order' => $order,
			'orderItems' => $orderItems,
		);
		
		return $this->responseView('DTUI_ViewPublic_EntryPoint_Order', '', $viewParams);
	}
	
	public function actionTables() {
		$tableModel = $this->_getTableModel();
		$conditions = array(
			'is_busy' => 0,
		);
		
		if (XenForo_Visitor::getInstance()->isSuperAdmin()) {
			if ($this->_input->filterSingle('all', XenForo_Input::UINT)) {
				unset($conditions['is_busy']);
			}
		}
		
		$tables = $tableModel->getAllTable($conditions);
		$tableModel->prepareTables($tables);
	
		$viewParams = array(
			'tables' => $tables
		);
	
		return $this->responseView('DTUI_ViewPublic_EntryPoint_Tables','dtui_entry_point_tables',$viewParams);
    }
    
    public function actionTable() {
    	$tableId = $this->_input->filterSingle('data', XenForo_Input::UINT);
		
    	$tableModel = $this->_getTableModel();
    	
		$table = $this->_getTableOrError($tableId);
		$tableModel->prepareTable($table);
		
		$viewParams = array(
			'table' => $table,
		);
		
		return $this->responseView('DTUI_ViewPublic_EntryPoint_Table', '', $viewParams);
    }
    
	public function actionTableQrcode() {
    	$response = $this->actionTable();
    	
    	if ($response instanceof XenForo_ControllerResponse_View) {
    		$table =& $response->params['table'];
    		
    		$viewParams = array(
    			'title' => $table['table_name'],
    			'qrcode' => $table['qrcode'],
    			'breadCrumbs' => array(
    				array('href' => XenForo_Link::buildPublicLink('dtui-entry-point/tables'), 'value' => new XenForo_Phrase('dtui_tables')),
    				array('href' => XenForo_Link::buildPublicLink('dtui-entry-point/table', $table['table_id']), 'value' => $table['table_name']),
    			),
    		);
    		
    		return $this->responseView('DTUI_ViewPublic_EntryPoint_QrCode', 'dtui_entry_point_qrcode', $viewParams);
    	}
    	
    	return $response;
    }
    
    public function findTargetUserId()// get target id for a user 
   {
    	/*
		 * get all user online 
		 */
		$sessionModel = $this->getModelFromCache('XenForo_Model_Session');

		$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
		$userPerPage = XenForo_Application::get('options')->membersPerPage;

		$bypassUserPrivacy = $this->getModelFromCache('XenForo_Model_User')->canBypassUserPrivacy();

		$conditions = array(
			'cutOff' => array('>', $sessionModel->getOnlineStatusTimeout()),
			'getInvisible' => $bypassUserPrivacy,
			'getUnconfirmed' => $bypassUserPrivacy,

			// allow force including of self, even if invisible
			'forceInclude' => ($bypassUserPrivacy ? false : XenForo_Visitor::getUserId())
		);

		$onlineUsers = $sessionModel->getSessionActivityRecords($conditions, array(
			'perPage' => $userPerPage,
			'page' => $page,
			'join' => XenForo_Model_Session::FETCH_USER,
			'order' => 'view_date'
		));
		//var_dump($onlineUsers);exit;
		
		// Lay cac target user id ma co trong cac item va co trang thai la waiting 
		$items = $this->_getOrderItemModel()->getAllOrderItem();// get all items from database
		$targetIds = array();// storage all targetId in items in database
		foreach($items as $item)	
		{
			if($item['status']=='waiting')// items is preparing 
			{
				array_push($targetIds,$item['target_user_id']);
			}
		}
		// Lay cac user online ma khong co trong target user id
		$OnlineUsersNotTarget = array();
		foreach($onlineUsers as $onlineUser)
		{
			if(in_array($onlineUser['user_id'],$targetIds) == false)// neu khong co user id online nao trong target user id status waiting
			                                               // minh tra lai user id online nay luon
			   //array_push($OnlineUsersNotTarget,$onlineUser['user_id']);
			   return $onlineUser['user_id'];
		}
		// Lay cac target user id co trang thai la waiting va dang online
		$targetIdOnlines = array();
		foreach($targetIds as $targetId)
		{
			foreach($onlineUsers as $onlineUser)
			{
				if($targetId == $onlineUser['user_id'])
				   array_push($targetIdOnlines,$targetId);
			}
		}
		if(count($targetIdOnlines) == 0)
		{
			return $onlineUsers[0]['user_id'] ;
		}
		else
		{
			$ItemATargetId = array();// so item cho 1 target id
			$tmp = 0; 
			foreach($targetIdOnlines as $targetId)
			{
			    foreach($items as $item)
			    {
			    	if($item['target_user_id'] == $targetId)
			    	{
			 			$tmp ++;   		
			    	}
			    }
			    $ItemATargetId[$targetId] = $tmp;	
			    $tmp = 0;
			}
			$MinItemATarget = min($ItemATargetId);// so item nho nhat cua 1 target
			foreach($ItemATargetId as $key => $item)
			{
				if($MinItemATarget == $item)
				   return $key;
			}
		}
    }
    
}