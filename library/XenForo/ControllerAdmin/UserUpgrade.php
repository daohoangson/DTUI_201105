<?php

/**
 * Controller for managing user upgrades.
 *
 * @package XenForo_UserUpgrade
 */
class XenForo_ControllerAdmin_UserUpgrade extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		$this->assertAdminPermission('userUpgrade');
	}

	/**
	 * Displays a list of user upgrades, and the related option configuration.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIndex()
	{
		$upgradeModel = $this->_getUserUpgradeModel();
		$optionModel = XenForo_Model::create('XenForo_Model_Option');

		$viewParams = array(
			'upgrades' => $upgradeModel->prepareUserUpgrades($upgradeModel->getAllUserUpgrades()),

			'options' => $optionModel->prepareOptions($optionModel->getOptionsByIds(array('payPalPrimaryAccount'))),
			'canEditOptionDefinition' => $optionModel->canEditOptionAndGroupDefinitions()
		);

		return $this->responseView('XenForo_ViewAdmin_UserUpgrade_List', 'user_upgrade_list', $viewParams);
	}

	/**
	 * Gets the upgrade add/edit form response.
	 *
	 * @param array $upgrade
	 *
	 * @return XenForo_ControllerResponse_View
	 */
	protected function _getUpgradeAddEditResponse(array $upgrade)
	{
		$viewParams = array(
			'upgrade' => $upgrade,

			'userGroupOptions' => $this->getModelFromCache('XenForo_Model_UserGroup')->getUserGroupOptions(
				$upgrade['extra_group_ids']
			),

			'disabledUpgradeOptions' => $this->_getUserUpgradeModel()->getUserUpgradeOptions(
				$upgrade['disabled_upgrade_ids'], $upgrade['user_upgrade_id']
			)
		);

		return $this->responseView('XenForo_ViewAdmin_UserUpgrade_Edit', 'user_upgrade_edit', $viewParams);
	}

	/**
	 * Displays a form to add a user upgrade.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionAdd()
	{
		return $this->_getUpgradeAddEditResponse(array(
			'user_upgrade_id' => null,
			'title' => '',
			'description' => '',
			'display_order' => 1,
			'extra_group_ids' => '',
			'recurring' => 0,
			'cost_amount' => 5,
			'cost_currency' => 'usd',
			'length_amount' => 1,
			'length_unit' => 'month',
			'disabled_upgrade_ids' => '',
			'can_purchase' => 1
		));
	}

	/**
	 * Displays a form to edit a user upgrade.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEdit()
	{
		$userUpgradeId = $this->_input->filterSingle('user_upgrade_id', XenForo_Input::UINT);
		$upgrade = $this->_getUserUpgradeOrError($userUpgradeId);

		return $this->_getUpgradeAddEditResponse($upgrade);
	}

	/**
	 * Inserts a new upgrade or updates an existing one.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSave()
	{
		$this->_assertPostOnly();

		$userUpgradeId = $this->_input->filterSingle('user_upgrade_id', XenForo_Input::UINT);
		$input = $this->_input->filter(array(
			'title' => XenForo_Input::STRING,
			'description' => XenForo_Input::STRING,
			'display_order' => XenForo_Input::UINT,
			'extra_group_ids' => array(XenForo_Input::UINT, 'array' => true),
			'recurring' => XenForo_Input::UINT,
			'cost_amount' => XenForo_Input::UNUM,
			'cost_currency' => XenForo_Input::STRING,
			'length_amount' => XenForo_Input::UINT,
			'length_unit' => XenForo_Input::STRING,
			'disabled_upgrade_ids' => array(XenForo_Input::UINT, 'array' => true),
			'can_purchase' => XenForo_Input::UINT
		));
		if ($this->_input->filterSingle('length_type', XenForo_Input::STRING) == 'permanent')
		{
			$input['length_amount'] = 0;
			$input['length_unit'] = '';
		}

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_UserUpgrade');
		if ($userUpgradeId)
		{
			$dw->setExistingData($userUpgradeId);
		}
		$dw->bulkSet($input);
		$dw->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('user-upgrades') . $this->getLastHash($dw->get('user_upgrade_id'))
		);
	}

	/**
	 * Deletes a user upgrade.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDelete()
	{
		if ($this->isConfirmedPost())
		{
			return $this->_deleteData(
				'XenForo_DataWriter_UserUpgrade', 'user_upgrade_id',
				XenForo_Link::buildAdminLink('user-upgrades')
			);
		}
		else // show a confirmation dialog
		{
			$userUpgradeId = $this->_input->filterSingle('user_upgrade_id', XenForo_Input::UINT);
			$upgrade = $this->_getUserUpgradeOrError($userUpgradeId);

			$dw = XenForo_DataWriter::create('XenForo_DataWriter_UserUpgrade', XenForo_DataWriter::ERROR_EXCEPTION);
			$dw->setExistingData($upgrade, true);
			$dw->preDelete();

			$viewParams = array(
				'upgrade' => $upgrade
			);

			return $this->responseView('XenForo_ViewAdmin_UserUpgrade_Delete', 'user_upgrade_delete', $viewParams);
		}
	}

	/**
	 * Displays a list of active upgrades, either across all upgrades or a specific one.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionActive()
	{
		$userUpgradeId = $this->_input->filterSingle('user_upgrade_id', XenForo_Input::UINT);
		$userUpgradeModel = $this->_getUserUpgradeModel();

		$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
		$perPage = 20;

		$fetchOptions = array(
			'page' => $page,
			'perPage' => $perPage
		);

		if ($userUpgradeId)
		{
			$upgrade = $this->_getUserUpgradeOrError($userUpgradeId);

			$conditions = array(
				'user_upgrade_id' => $upgrade['user_upgrade_id'],
				'active' => true
			);

			$viewParams = array(
				'upgrade' => $upgrade,
				'upgradeRecords' => $userUpgradeModel->getUserUpgradeRecords($conditions, $fetchOptions),

				'totalRecords' => $userUpgradeModel->countUserUpgradeRecords($conditions),
				'perPage' => $perPage,
				'page' => $page
			);

			return $this->responseView('XenForo_ViewAdmin_UserUpgrade_ActiveSingle', 'user_upgrade_active_single', $viewParams);
		}
		else
		{
			$conditions = array(
				'active' => true
			);

			$fetchOptions['join'] = XenForo_Model_UserUpgrade::JOIN_UPGRADE;

			$viewParams = array(
				'upgradeRecords' => $userUpgradeModel->getUserUpgradeRecords($conditions, $fetchOptions),

				'totalRecords' => $userUpgradeModel->countUserUpgradeRecords($conditions),
				'perPage' => $perPage,
				'page' => $page
			);

			return $this->responseView('XenForo_ViewAdmin_UserUpgrade_Active', 'user_upgrade_active', $viewParams);
		}
	}

	/**
	 * Displays a form to manually upgrade a user with the specified upgrade,
	 * or actually upgrades the user.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionManual()
	{
		$userUpgradeId = $this->_input->filterSingle('user_upgrade_id', XenForo_Input::UINT);
		$upgrade = $this->_getUserUpgradeOrError($userUpgradeId);

		if ($this->_request->isPost())
		{
			$endDate = $this->_input->filterSingle('end_date', XenForo_Input::DATE_TIME);
			if (!$endDate)
			{
				$endDate = null; // if not specified, don't overwrite
			}

			$username = $this->_input->filterSingle('username', XenForo_Input::STRING);
			$user = $this->getModelFromCache('XenForo_Model_User')->getUserByName($username);
			if (!$user)
			{
				return $this->responseError(new XenForo_Phrase('requested_user_not_found'), 404);
			}

			$this->_getUserUpgradeModel()->upgradeUser($user['user_id'], $upgrade, true, $endDate);

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('user-upgrades')
			);
		}
		else
		{
			if ($upgrade['length_unit'])
			{
				$endDate = strtotime('+' . $upgrade['length_amount'] . ' ' . $upgrade['length_unit']);
			}
			else
			{
				$endDate = false;
			}

			$viewParams = array(
				'upgrade' => $upgrade,
				'endDate' => $endDate
			);

			return $this->responseView('XenForo_ViewAdmin_UserUpgrade_Manual', 'user_upgrade_manual', $viewParams);
		}
	}

	/**
	 * Displays a form to confirm downgrade of a user,
	 * or actually downgrades.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDowngrade()
	{
		$userUpgradeModel = $this->_getUserUpgradeModel();

		$userUpgradeRecordId = $this->_input->filterSingle('user_upgrade_record_id', XenForo_Input::UINT);
		$upgradeRecord = $userUpgradeModel->getActiveUserUpgradeRecordById($userUpgradeRecordId);
		if (!$upgradeRecord)
		{
			return $this->responseError(new XenForo_Phrase('requested_user_upgrade_not_found'), 404);
		}

		if ($this->_request->isPost())
		{
			$userUpgradeModel->downgradeUserUpgrade($upgradeRecord);

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('user-upgrades/active')
			);
		}
		else
		{
			$viewParams = array(
				'upgradeRecord' => $upgradeRecord,
				'upgrade' => $userUpgradeModel->getUserUpgradeById($upgradeRecord['user_upgrade_id'])
			);

			return $this->responseView('XenForo_ControllerAdmin_UserUpgrade_Downgrade', 'user_upgrade_downgrade', $viewParams);
		}
	}

	/**
	 * Gets the specified user upgrade or throws an exception.
	 *
	 * @param string $id
	 *
	 * @return array
	 */
	protected function _getUserUpgradeOrError($id)
	{
		$userUpgradeModel = $this->_getUserUpgradeModel();

		return $this->getRecordOrError(
			$id, $userUpgradeModel, 'getUserUpgradeById',
			'requested_user_upgrade_not_found'
		);
	}

	/**
	 * @return XenForo_Model_UserUpgrade
	 */
	protected function _getUserUpgradeModel()
	{
		return $this->getModelFromCache('XenForo_Model_UserUpgrade');
	}
}