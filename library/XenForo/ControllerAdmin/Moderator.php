<?php

/**
 * Controller for managing moderators.
 *
 * @package XenForo_Moderator
 */
class XenForo_ControllerAdmin_Moderator extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		$this->assertAdminPermission('user');
	}

	/**
	 * A list of all moderators: super moderators and content-specific mods.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIndex()
	{
		$moderatorModel = $this->_getModeratorModel();

		$contentModeratorInfo = array();
		$contentModData = $moderatorModel->addContentTitlesToModerators(
			$moderatorModel->getContentModerators()
		);

		foreach ($contentModData AS $moderator)
		{
			$contentModeratorInfo[$moderator['user_id']][] = $moderator;
		}

		$superModerators = array();
		$contentModerators = array();
		foreach ($moderatorModel->getAllGeneralModerators() AS $moderator)
		{
			if ($moderator['is_super_moderator'])
			{
				$superModerators[$moderator['user_id']] = $moderator;
			}

			if (isset($contentModeratorInfo[$moderator['user_id']]))
			{
				$moderator['content'] = $contentModeratorInfo[$moderator['user_id']];
				$contentModerators[$moderator['user_id']] = $moderator;
			}
		}

		// get total content moderators
		$totalContentModerators = 0;
		foreach ($contentModerators AS $user)
		{
			$totalContentModerators += count($user['content']);
		}

		$viewParams = array(
			'superModerators' => $superModerators,
			'contentModerators' => $contentModerators,
			'totalContentModerators' => $totalContentModerators,
		);

		return $this->responseView('XenForo_ViewAdmin_Moderator_List', 'moderator_list', $viewParams);
	}

	/**
	 * Gets the moderator add/edit controller response. This handles both super
	 * and content moderators.
	 *
	 * @param array $moderator Info about the moderator; a content or super mod record
	 * @param boolean $allowDelete If true, shows the necessary delete button
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	protected function _moderatorAddEditResponse(array $moderator, $allowDelete = true)
	{
		$moderatorModel = $this->_getModeratorModel();

		$isContentModerator = (!empty($moderator['content_type']));
		$isSuperModerator = (!empty($moderator['is_super_moderator']));

		$existingPermissions = unserialize($moderator['moderator_permissions']);

		if ($isContentModerator)
		{
			$generalModerator = $moderatorModel->getGeneralModeratorByUserId($moderator['user_id']);
			if ($generalModerator)
			{
				$moderator['extra_user_group_ids'] = $generalModerator['extra_user_group_ids'];
				$moderator['is_super_moderator'] = $generalModerator['is_super_moderator'];

				$existingPermissions = $moderatorModel->mergeGeneralModeratorPermissions(
					$existingPermissions, unserialize($generalModerator['moderator_permissions'])
				);
			}
			else
			{
				$moderator['extra_user_group_ids'] = '';
				$moderator['is_super_moderator'] = 0;
			}
		}

		$generalInterfaceGroupIds = $moderatorModel->getGeneralModeratorInterfaceGroupIds();
		$moderatorInterfaceGroupIds = $moderatorModel->getModeratorInterfaceGroupIds($moderator);

		$interfaceGroups = $moderatorModel->getModeratorPermissionsForInterface($moderatorInterfaceGroupIds, $existingPermissions);

		$generalInterfaceGroups = array();
		foreach ($generalInterfaceGroupIds AS $generalInterfaceGroupId)
		{
			$generalInterfaceGroups[$generalInterfaceGroupId] = $interfaceGroups[$generalInterfaceGroupId];
			unset($interfaceGroups[$generalInterfaceGroupId]);
		}

		$userGroups = $moderatorModel->getExtraUserGroupOptions($moderator['extra_user_group_ids']);

		if ($isContentModerator)
		{
			$handler = $moderatorModel->getContentModeratorHandlers($moderator['content_type']);
			$contentTitle = $handler->getContentTitle($moderator['content_id']);
		}
		else
		{
			$contentTitle = '';
		}

		$viewParams = array(
			'user' => $this->_getUserModel()->getUserById($moderator['user_id']),
			'moderator' => $moderator,
			'contentTitle' => $contentTitle,
			'allowDelete' => $allowDelete,

			'interfaceGroups' => $interfaceGroups,
			'generalInterfaceGroups' => $generalInterfaceGroups,

			'userGroups' => $userGroups,
		);

		return $this->responseView('XenForo_ViewAdmin_Moderator_Edit', 'moderator_edit', $viewParams);
	}

	/**
	 * Either displays a form to allow choice of moderator and type, or displays a
	 * form to explicitly add a moderator.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionAdd()
	{
		$input = $this->_input->filter(array(
			'username' => XenForo_Input::STRING,
			'type' => XenForo_Input::STRING,
			'type_id' => array(XenForo_Input::UINT, 'array' => true)
		));

		if ($input['username'] === '' || $input['type'] === '')
		{
			$viewParams = array(
				'username' => $input['username'],
				'type' => $input['type'],
				'typeId' => $input['type_id'],
				'typeHandlers' => $this->_getModeratorModel()->getContentModeratorHandlers()
			);

			return $this->responseView('XenForo_ViewAdmin_Moderator_AddChoice', 'moderator_add_choice', $viewParams);
		}

		$user = $this->_getUserModel()->getUserByName($input['username']);
		if (!$user)
		{
			return $this->responseError(new XenForo_Phrase('requested_user_not_found'), 404);
		}

		$moderatorModel = $this->_getModeratorModel();

		if ($input['type'] == '_super')
		{
			$moderator = $moderatorModel->getGeneralModeratorByUserId($user['user_id']);
			if (!$moderator)
			{
				$moderator = array(
					'user_id' => $user['user_id'],
					'content_type' => '',
					'content_id' => 0,
					'is_super_moderator' => 1,
					'extra_user_group_ids' => '',
					'moderator_permissions' => serialize(array()),
				);
			}
			else
			{
				$moderator['is_super_moderator'] = 1;
			}
		}
		else
		{
			$handler = $moderatorModel->getContentModeratorHandlers($input['type']);
			$contentId = isset($input['type_id'][$input['type']]) ? $input['type_id'][$input['type']] : 0;

			if (!$handler->getContentTitle($contentId))
			{
				return $this->responseError(new XenForo_Phrase('please_select_a_valid_type_of_moderator'), 404);
			}

			$moderator = $moderatorModel->getContentModeratorByContentAndUserId($input['type'], $contentId, $user['user_id']);
			if (!$moderator)
			{
				$moderator = array(
					'user_id' => $user['user_id'],
					'content_type' => $input['type'],
					'content_id' => $contentId,
					'is_super_moderator' => 0,
					'extra_user_group_ids' => '',
					'moderator_permissions' => serialize(array()),
				);
			}
		}

		return $this->_moderatorAddEditResponse($moderator, false);
	}

	/**
	 * Saves a moderator (super or content). This handles both inserts and updates.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSave()
	{
		$this->_assertPostOnly();

		$input = $this->_input->filter(array(
			'moderator_id' => XenForo_Input::UINT,
			'user_id' => XenForo_Input::UINT,
			'content_type' => XenForo_Input::STRING,
			'content_id' => XenForo_Input::UINT,
			'is_super_moderator' => XenForo_Input::UINT,
			'extra_user_group_ids' => array(XenForo_Input::UINT, 'array' => true),
			'general_moderator_permissions' => XenForo_Input::ARRAY_SIMPLE,
			'moderator_permissions' => XenForo_Input::ARRAY_SIMPLE
		));

		if ($input['content_type'])
		{
			$moderatorId = $this->_getModeratorModel()->insertOrUpdateContentModerator(
				$input['user_id'], $input['content_type'], $input['content_id'], $input['moderator_permissions'],
				array(
					'general_moderator_permissions' => $input['general_moderator_permissions'],
					'extra_user_group_ids' => $input['extra_user_group_ids']
				)
			);
		}
		else
		{
			$userId = $this->_getModeratorModel()->insertOrUpdateGeneralModerator(
				$input['user_id'], $input['general_moderator_permissions'], $input['is_super_moderator'],
				array(
					'super_moderator_permissions' => $input['moderator_permissions'],
					'extra_user_group_ids' => $input['extra_user_group_ids']
				)
			);

			$moderatorId = "supermod_{$userId}";
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('moderators') . $this->getLastHash($moderatorId)
		);
	}

	/**
	 * Displays a form to edit a super moderator.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSuperEdit()
	{
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$moderator = $this->_getModeratorModel()->getGeneralModeratorByUserId($userId);
		if (!$moderator || !$moderator['is_super_moderator'])
		{
			return $this->responseError(new XenForo_Phrase('requested_moderator_not_found'), 404);
		}

		return $this->_moderatorAddEditResponse($moderator);
	}

	/**
	 * Deletes a super moderator.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSuperDelete()
	{
		if ($this->isConfirmedPost())
		{
			return $this->_deleteData(
				'XenForo_DataWriter_Moderator', 'user_id',
				XenForo_Link::buildAdminLink('moderators')
			);
		}
		else // show confirmation dialog
		{
			$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
			$moderator = $this->_getModeratorModel()->getGeneralModeratorByUserId($userId);
			if (!$moderator || !$moderator['is_super_moderator'])
			{
				return $this->responseError(new XenForo_Phrase('requested_moderator_not_found'), 404);
			}

			$viewParams = array(
				'moderator' => $moderator
			);

			return $this->responseView('XenForo_ViewAdmin_Moderator_SuperDelete', 'moderator_super_delete', $viewParams);
		}
	}

	/**
	 * Displays a form to edit a content moderator.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionContentEdit()
	{
		$moderatorId = $this->_input->filterSingle('moderator_id', XenForo_Input::UINT);
		$moderator = $this->_getModeratorModel()->getContentModeratorById($moderatorId);
		if (!$moderator)
		{
			return $this->responseError(new XenForo_Phrase('requested_moderator_not_found'), 404);
		}

		return $this->_moderatorAddEditResponse($moderator);
	}

	/**
	 * Deletes a content moderator.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionContentDelete()
	{
		if ($this->isConfirmedPost())
		{
			return $this->_deleteData(
				'XenForo_DataWriter_ModeratorContent', 'moderator_id',
				XenForo_Link::buildAdminLink('moderators')
			);
		}
		else // show confirmation dialog
		{
			$moderatorModel = $this->_getModeratorModel();

			$moderatorId = $this->_input->filterSingle('moderator_id', XenForo_Input::UINT);
			$moderator = $moderatorModel->getContentModeratorById($moderatorId);
			if (!$moderator)
			{
				return $this->responseError(new XenForo_Phrase('requested_moderator_not_found'), 404);
			}

			$handler = $moderatorModel->getContentModeratorHandlers($moderator['content_type']);
			$contentTitle = $handler->getContentTitle($moderator['content_id']);

			$viewParams = array(
				'moderator' => $moderator,
				'contentTitle' => $contentTitle
			);

			return $this->responseView('XenForo_ViewAdmin_Moderator_ContentDelete', 'moderator_content_delete', $viewParams);
		}
	}

	/**
	 * @return XenForo_Model_Moderator
	 */
	protected function _getModeratorModel()
	{
		return $this->getModelFromCache('XenForo_Model_Moderator');
	}

	/**
	 * @return XenForo_Model_User
	 */
	protected function _getUserModel()
	{
		return $this->getModelFromCache('XenForo_Model_User');
	}
}