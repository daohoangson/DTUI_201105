<?php
abstract class DTUI_ControllerPublic_EntryPointManhHX extends DTUI_ControllerPublic_EntryPointBase {
	public function actionNewOrder() {
		$input = $this->_input->filter(array(
		'table_id' => XenForo_Input::UINT,
		'item_ids' => array(XenForo_Input::UINT, 'array' => true)
		));
		
		
		$orderDw = XenForo_DataWriter::create('DTUI_DataWriter_Order');// new order
		$orderDw->set('table_id', $input['table_id']);
		$orderDw->set('order_date',XenForo_Application::$time);
		$orderDw->save();// storage new order into Oder table in database;
		
		$order = $orderDw->getMergedData();// get data is saved ;
		// create items for a order 
		foreach ($input['item_ids'] as $itemId) {
			$orderItemDw = XenForo_DataWriter::create('DTUI_DataWriter_OrderItem');// a item in a order
			$orderItemDw->set('order_id', $order['order_id']);
			$orderItemDw->set('trigger_user_id',XenForo_Visitor::getUserId());
			$orderItemDw->set('target_user_id',0);
			$orderItemDw->set('item_id',$itemId);
			$orderItemDw->set('order_item_date', XenForo_Application::$time);
			$orderItemDw->set('status','waiting');
			
			$tmp = $orderItemDw->save();// storage new order_item into OrderItem table in Database
			
		}
		die('Ok');
	}
	
	public function actionTasks(){// return list order_items of a user 
		$userIdtmp = XenForo_Visitor::getUserId();
		$conditions = array('userId' => $userIdtmp);
		$order_items = $this->_getOrderItemModel()->getAllOrderItem($conditions);
		
		$viewParams = array(
			'items' => $order_items
		);
		
		return $this -> responseView('DTUI_ViewPublic_EntryPoint_Tasks','',$viewParams);
	}
	
	public function actionOrders(){
		$OrdersTmp = $this->_getOrderModel()->getAllOrder();
		
		$viewParams = array(
			'Orders' => $OrdersTmp
		);
		
		return $this -> responseView('DTUI_ViewPublic_EntryPoint_Orders','testTemp1',$viewParams);
	}
}