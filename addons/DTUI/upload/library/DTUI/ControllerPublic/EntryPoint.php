<?php

class DTUI_ControllerPublic_EntryPoint extends DTUI_ControllerPublic_EntryPointQuanUH {

    public function actionIndex() {
		$viewParams = array();

		return $this->responseView('DTUI_ViewPublic_EntryPoint_Index', '', $viewParams);
    }

    public function actionUserInfo() {
    	return $this->responseView('DTUI_ViewPublic_EntryPoint_UserInfo');
    }
}