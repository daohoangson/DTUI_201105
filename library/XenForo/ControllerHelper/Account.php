<?php

class XenForo_ControllerHelper_Account extends XenForo_ControllerHelper_Abstract
{
	public function getWrapper($selectedGroup, $selectedLink, XenForo_ControllerResponse_View $subView)
	{
		$viewParams = array(
			'selectedGroup' => $selectedGroup,
			'selectedLink' => $selectedLink,
			'selectedKey' => "$selectedGroup/$selectedLink",

			'canStartConversation' => $this->_controller->getModelFromCache('XenForo_Model_Conversation')->canStartConversations(),
			'canEditSignature' => XenForo_Visitor::getInstance()->canEditSignature()
		);

		$wrapper = $this->_controller->responseView('XenForo_ViewPublic_Account_Wrapper', 'account_wrapper', $viewParams);
		$wrapper->subView = $subView;

		return $wrapper;
	}

	public static function wrap(XenForo_Controller $controller, $selectedGroup, $selectedLink, XenForo_ControllerResponse_View $subView)
	{
		$helper = new self($controller);
		return $helper->getWrapper($selectedGroup, $selectedLink, $subView);
	}
}