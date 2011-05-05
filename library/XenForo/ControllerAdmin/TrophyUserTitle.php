<?php

/**
 * Controller for trophy user titles.
 *
 * @package XenForo_Trophy
 */
class XenForo_ControllerAdmin_TrophyUserTitle extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		$this->assertAdminPermission('trophy');
	}

	/**
	 * Displays a list of all trophies, with an option to delete.
	 * Also shows form to create new.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIndex()
	{
		$viewParams = array(
			'titles' => $this->_getTrophyModel()->getAllTrophyUserTitles()
		);

		return $this->responseView('XenForo_ViewAdmin_TrophyUserTitle_List', 'trophy_user_title_list', $viewParams);
	}

	/**
	 * Updates existing titles, deletes specified ones, and optionally creates
	 * a new one.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionUpdate()
	{
		$this->_assertPostOnly();

		$update = $this->_input->filterSingle('update', XenForo_Input::ARRAY_SIMPLE);
		$delete = $this->_input->filterSingle('delete', array(XenForo_Input::UINT, 'array' => true));
		foreach ($delete AS $deletePoint)
		{
			unset($update[$deletePoint]);
		}

		$input = $this->_input->filter(array(
			'minimum_points' => XenForo_Input::UINT,
			'title' => XenForo_Input::STRING
		));

		$trophyModel = $this->_getTrophyModel();

		XenForo_Db::beginTransaction();

		$trophyModel->deleteTrophyUserTitles($delete, false);
		$trophyModel->updateTrophyUserTitles($update, false);

		if ($input['title'])
		{
			$trophyModel->insertTrophyUserTitle($input['title'], $input['minimum_points'], false);
		}

		$trophyModel->rebuildTrophyUserTitleCache();

		XenForo_Db::commit();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('trophy-user-titles')
		);
	}

	/**
	 * @return XenForo_Model_Trophy
	 */
	protected function _getTrophyModel()
	{
		return $this->getModelFromCache('XenForo_Model_Trophy');
	}
}