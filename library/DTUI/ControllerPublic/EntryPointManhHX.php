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
			$orderDw = XenForo_DataWriter::create('DTUI_DataWriter_Order');// new order
			$orderDw->set('table_id', $input['table_id']);
			$orderDw->set('order_date',XenForo_Application::$time);
			$orderDw->save();// storage new order into Order table in database;
			
			$order = $orderDw->getMergedData();// get data is saved ;
			// create items for a order 
			foreach ($input['item_ids'] as $itemId) {
				$orderItemDw = XenForo_DataWriter::create('DTUI_DataWriter_OrderItem');// a item in a order
				$orderItemDw->set('order_id', $order['order_id']);
				$orderItemDw->set('trigger_user_id',XenForo_Visitor::getUserId());
				$orderItemDw->set('target_user_id',$this->findTargetUserId());
				//$orderItemDw->set('target_user_id',2);
				$orderItemDw->set('item_id',$itemId);//
				$orderItemDw->set('order_item_date', XenForo_Application::$time);
				$orderItemDw->set('status','waiting');
				
				$tmp = $orderItemDw->save();// storage new order_item into OrderItem table in Database
			}
			
			die('ok');
		} else {
			// this is a GET request
			// display a form
			$tables = $this ->_getTableModel()->getAllTable();// get array tables with key= tableId and values = tableName
			$items = $this->_getItemModel()->getAllItem();// get all items from database
			
			$viewParams = array(
			'table' => $tables,
			'items' => $items
			);
			
			return $this -> responseView('DTUI_ViewPublic_EntryPoint_NewOrder','dtui_entrypoint_new_order',$viewParams);
		}
	}
	
	public function actionTasks(){// return list order_items of a user 
		$userIdtmp = XenForo_Visitor::getUserId();
		$conditions = array('userId' => $userIdtmp);
		$order_items = $this->_getOrderItemModel()->getAllOrderItem($conditions);
		
		$viewParams = array(
			'tasks' => $order_items
		);
		
		return $this -> responseView('DTUI_ViewPublic_EntryPoint_Tasks','dtui_task_list',$viewParams);
	}
	
	public function actionOrders(){// get all Order in database
		$orders = $this->_getOrderModel()->getAllOrder();
		
		$viewParams = array(
			'orders' => $orders
		);
		
		return $this -> responseView('DTUI_ViewPublic_EntryPoint_Orders','dtui_order_list',$viewParams);
	}
	
	public function actionTables() {
		$tables = $this->_getTableModel()->getAllTable();
	
		$viewParams = array(
			'tables' => $tables
		);
	
		return $this->responseView('DTUI_ViewPublic_EntryPoint_Tables','dtui_table_list',$viewParams);
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