<?php

class DTUI_ControllerPublic_EntryPoint extends DTUI_ControllerPublic_EntryPointQuanUH {

    public function actionIndex() {
		$viewParams = array();

		return $this->responseView('DTUI_ViewPublic_EntryPoint_Index', '', $viewParams);
    }

    public function actionUserInfo() {
    	$user = XenForo_Visitor::getInstance()->toArray();
    	$user['DTUI_canNewOrder'] = $this->_getOrderModel()->canNewOrder($user);
    	$user['DTUI_canUpdateTask'] = $this->_getOrderItemModel()->canUpdateTask($user);
    	
    	$viewParams = array(
    		'user' => $user
    	);
    	
    	return $this->responseView('DTUI_ViewPublic_EntryPoint_UserInfo', '', $viewParams);
    }
}