<?php

/**
 * General model to help get/rebuild counters that don't necessarily fit anywhere else
 * because they span multiple types of data.
 */
class XenForo_Model_Counters extends XenForo_Model
{
	/**
	 * Gets the board totals counter. Includes discussion/message/user totals and the latest user's info.
	 *
	 * @return array Keys: discussions, messages, latestUser, users
	 */
	public function getBoardTotalsCounter()
	{
		$output = $this->getModelFromCache('XenForo_Model_Node')->getNodeTotalItemCounts();

		$userModel = $this->getModelFromCache('XenForo_Model_User');

		$output['latestUser'] = $userModel->getLatestUser();
		$output['users'] = $userModel->countTotalUsers();

		return $output;
	}

	/**
	 * Rebuilds the board totals counter and stores it in the data registry.
	 *
	 * @return array Keys: discussions, messages, latestUser, users
	 */
	public function rebuildBoardTotalsCounter()
	{
		$counter = $this->getBoardTotalsCounter();

		$this->_getDataRegistryModel()->set('boardTotals', $counter);
		return $counter;
	}
}