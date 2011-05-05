<?php

class XenForo_ControllerPublic_Index extends XenForo_ControllerPublic_Abstract
{
	public function actionIndex()
	{
		$this->canonicalizeRequestUrl(
			XenForo_Link::buildPublicLink('index')
		);

		$boardTotals = $this->getModelFromCache('XenForo_Model_DataRegistry')->get('boardTotals');
		if (!$boardTotals)
		{
			$boardTotals = $this->getModelFromCache('XenForo_Model_Counters')->rebuildBoardTotalsCounter();
		}

		$visitor = XenForo_Visitor::getInstance();

		$sessionModel = $this->getModelFromCache('XenForo_Model_Session');

		$onlineUsers = $sessionModel->getSessionActivityQuickList(
			$visitor->toArray(),
			array('cutOff' => array('>', $sessionModel->getOnlineStatusTimeout())),
			($visitor['user_id'] ? $visitor->toArray() : null)
		);

		$viewParams = array(
			'nodeList' => $this->_getNodeModel()->getNodeDataForListDisplay(false, 0),
			'onlineUsers' => $onlineUsers,
			'boardTotals' => $boardTotals
		);

		return $this->responseView('XenForo_ViewPublic_Forum_List', 'forum_list', $viewParams);
	}

	/**
	 * Session activity details.
	 * @see XenForo_Controller::getSessionActivityDetailsForList()
	 */
	public static function getSessionActivityDetailsForList(array $activities)
	{
		return new XenForo_Phrase('viewing_forum_list');
	}

	/**
	 * @return XenForo_Model_Node
	 */
	protected function _getNodeModel()
	{
		return $this->getModelFromCache('XenForo_Model_Node');
	}
}