<?php
abstract class DTUI_ControllerPublic_EntryPointManhHX extends DTUI_ControllerPublic_EntryPointBase {
	public function actionNewOrder() {
		if ($this->_request->isPost()) {
			// this is a POST request
			// start creating new order
			$input = $this->_input->filter(array(
				'table_id' => XenForo_Input::UINT,
				'item_ids' => array(XenForo_Input::UINT, 'array' => true)
			));
			// 
			
			$table = $this->_getTableOrError($input['table_id']);
			$items = array();
			foreach ($input['item_ids'] as $itemId) {
				$items[$itemId] = $this->_getItemOrError($itemId);
			}
			
			$orderDw = XenForo_DataWriter::create('DTUI_DataWriter_Order');
			$orderDw->set('table_id', $table['table_id']);
			$orderDw->save();
			$order = $orderDw->getMergedData();
 
			foreach ($input['item_ids'] as $itemId) {
				$item =& $items[$itemId];
				
				$orderItemDw = XenForo_DataWriter::create('DTUI_DataWriter_OrderItem');
				$orderItemDw->set('order_id', $order['order_id']);
				$orderItemDw->set('trigger_user_id', XenForo_Visitor::getUserId());
				$orderItemDw->set('target_user_id', $this->findTargetUserId());
				$orderItemDw->set('item_id', $item['item_id']);
				$orderItemDw->save();
			}
			
			$this->_request->setParam('data', $order['order_id']);
			return $this->responseReroute('DTUI_ControllerPublic_EntryPoint', 'order');
		} else {
			// this is a GET request
			// display a form
			$tables = $this ->_getTableModel()->getAllTable();
			$items = $this->_getItemModel()->getAllItem();
			
			$viewParams = array(
				'table' => $tables,
				'items' => $items
			);
			
			return $this -> responseView('DTUI_ViewPublic_EntryPoint_NewOrder','dtui_entrypoint_new_order',$viewParams);
		}
	}
	
	public function actionTasks(){ 
		$conditions = array('userId' => XenForo_Visitor::getUserId());
		$fetchOptions = array(
			'join' => DTUI_Model_OrderItem::FETCH_ITEM + DTUI_Model_OrderItem::FETCH_ORDER,
		);
		
		$order_items = $this->_getOrderItemModel()->getAllOrderItem($conditions, $fetchOptions);
		
		$viewParams = array(
			'tasks' => $order_items,
			'direction' => array(
				'from' => DTUI_DataWriter_OrderItem::STATUS_WAITING,
				'to' => DTUI_DataWriter_OrderItem::STATUS_PREPARED,
			),
		);
		
		return $this -> responseView('DTUI_ViewPublic_EntryPoint_Tasks','dtui_task_list',$viewParams);
	}
	
	public function actionUpdateTask() {
		$this->_assertPostOnly();
		
		$input = $this->_input->filter(array(
			'order_item_id' => XenForo_Input::UINT,
			'status' => XenForo_Input::STRING,
		));
		
		$orderItem = $this->_getOrderItemOrError($input['order_item_id']);
		
		$dw = XenForo_DataWriter::create('DTUI_DataWriter_OrderItem');
		$dw->setExistingData($orderItem, true);
		$dw->set('status', $input['status']);
		$dw->save();
		
		$viewParams = array(
			'orderItem' => $dw->getMergedData(),
		);
		
		return $this->responseView('DTUI_ViewPublic_EntryPoint_UpdateTask', '', $viewParams);
	}
	
	public function actionOrders(){
		$orders = $this->_getOrderModel()->getAllOrder();
		
		$viewParams = array(
			'orders' => $orders
		);
		
		return $this -> responseView('DTUI_ViewPublic_EntryPoint_Orders','dtui_order_list',$viewParams);
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
		
		$tables = $tableModel->getAllTable();
		$tableModel->prepareTables($tables);
	
		$viewParams = array(
			'tables' => $tables
		);
	
		return $this->responseView('DTUI_ViewPublic_EntryPoint_Tables','dtui_table_list',$viewParams);
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