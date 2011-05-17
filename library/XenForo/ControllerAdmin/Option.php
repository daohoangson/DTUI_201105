<?php

/**
 * Admin controller for handling actions on options and option groups.
 *
 * @package XenForo_Options
 */
class XenForo_ControllerAdmin_Option extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		$this->assertAdminPermission('option');
	}

	/**
	 * Assert that the option or group definition is editable and error
	 * if it is not.
	 */
	protected function _assertOptionOrGroupDefinitionEditable()
	{
		if (!$this->_getOptionModel()->canEditOptionAndGroupDefinitions())
		{
			throw new XenForo_Exception(new XenForo_Phrase('you_cannot_edit_option_or_group_definitions'), true);
		}
	}

	/**
	 * Index. This shows a list of option groups.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIndex()
	{
		$optionModel = $this->_getOptionModel();

		$groups = $optionModel->getOptionGroupList(array('join' => XenForo_Model_Option::FETCH_ADDON));

		$viewParams = array(
			'groups' => $optionModel->prepareOptionGroups($groups, false),
			'canEditOptionDefinitions' => $optionModel->canEditOptionAndGroupDefinitions()
		);

		return $this->responseView('XenForo_ViewAdmin_Option_ListGroups', 'option_group_list', $viewParams);
	}

	/**
	 * Lists all the options that belong to a particular group.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionList()
	{
		$input = $this->_input->filter(array(
			'group_id' => XenForo_Input::STRING
		));

		if ($input['group_id'] === '')
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
				XenForo_Link::buildAdminLink('options')
			);
		}

		$optionModel = $this->_getOptionModel();

		$fetchOptions = array('join' => XenForo_Model_Option::FETCH_ADDON);

		$group = $this->_getOptionGroupOrError($input['group_id'], $fetchOptions);
		$groups = $optionModel->getOptionGroupList($fetchOptions);
		$options = $optionModel->getOptionsInGroup($group['group_id'], $fetchOptions);

		$canEdit = $optionModel->canEditOptionAndGroupDefinitions();

		$viewParams = array(
			'group' => $group,
			'groups' => $optionModel->prepareOptionGroups($groups, false),
			'preparedOptions' => $optionModel->prepareOptions($options, false),
			'canEditGroup' => $canEdit,
			'canEditOptionDefinition' => $canEdit
		);

		return $this->responseView('XenForo_ViewAdmin_Option_ListOptions', 'option_list', $viewParams);
	}

	public function actionGroupDisplayOrder()
	{
		$this->_assertOptionOrGroupDefinitionEditable();

		$optionModel = $this->_getOptionModel();
		$groups = $optionModel->getOptionGroupList();

		if ($this->isConfirmedPost())
		{
			// save adjusted display orders
			$displayOrders = $this->_input->filterSingle('group', array(XenForo_Input::UINT, 'array' => true));

			XenForo_Db::beginTransaction();

			foreach ($displayOrders AS $groupId => $displayOrder)
			{
				if (isset($groups[$groupId]) && $groups[$groupId]['display_order'] != $displayOrder)
				{
					$dw = XenForo_DataWriter::create('XenForo_DataWriter_OptionGroup', XenForo_DataWriter::ERROR_EXCEPTION);
					$dw->setExistingData($groupId);
					$dw->set('display_order', $displayOrder);
					$dw->save();
				}
			}

			XenForo_Db::commit();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('options/group-display-order')
			);
		}
		else
		{
			$viewParams = array(
				'groups' => $optionModel->prepareOptionGroups($groups)
			);

			return $this->responseView('XenForo_ViewAdmin_Option_GroupDisplayOrder', 'option_group_display_order', $viewParams);
		}
	}

	/**
	 * Saves a list of options. Lists come in via 2 arrays:
	 *  * options - [name] => value,
	 *  * options_listed - [] => name
	 * This is needed for checkbox options.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSave()
	{
		$this->_assertPostOnly();

		$input = $this->_input->filter(array(
			'group_id' => XenForo_Input::STRING,
			'options' => XenForo_Input::ARRAY_SIMPLE,
			'options_listed' => array(XenForo_Input::STRING, array('array' => true))
		));

		foreach ($input['options_listed'] AS $optionName)
		{
			if (!isset($input['options'][$optionName]))
			{
				$input['options'][$optionName] = '';
			}
		}

		$optionModel = $this->_getOptionModel();
		$optionModel->updateOptions($input['options']);

		$group = $optionModel->getOptionGroupById($input['group_id']);

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			$this->getDynamicRedirect(XenForo_Link::buildAdminLink('options/list', $group))
		);
	}

	/**
	 * Helper to get the option group add/edit form controller response.
	 *
	 * @param array $group
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	protected function _getGroupAddEditResponse(array $group)
	{
		$optionModel = $this->_getOptionModel();
		$addOnModel = $this->_getAddOnModel();

		$viewParams = array(
			'group' => $group,
			'masterTitle' => $optionModel->getOptionGroupMasterTitlePhraseValue($group['group_id']),
			'masterDescription' => $optionModel->getOptionGroupMasterDescriptionPhraseValue($group['group_id']),
			'addOnOptions' => $addOnModel->getAddOnOptionsListIfAvailable(),
			'addOnSelected' => (isset($group['addon_id']) ? $group['addon_id'] : $addOnModel->getDefaultAddOnId())
		);

		return $this->responseView('XenForo_ViewAdmin_Option_EditGroup', 'option_group_edit', $viewParams);
	}

	/**
	 * Displays a form to add a group.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionAddGroup()
	{
		$this->_assertOptionOrGroupDefinitionEditable();

		$group = $this->_getOptionModel()->getDefaultOptionGroup();
		return $this->_getGroupAddEditResponse($group);
	}

	/**
	 * Displays a form to edit an existing group.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEditGroup()
	{
		$this->_assertOptionOrGroupDefinitionEditable();

		$groupId = $this->_input->filterSingle('group_id', XenForo_Input::STRING);
		$group = $this->_getOptionGroupOrError($groupId);

		return $this->_getGroupAddEditResponse($group);
	}

	/**
	 * Inserts a new group or updates an existing one.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSaveGroup()
	{
		$this->_assertPostOnly();
		$this->_assertOptionOrGroupDefinitionEditable();

		$input = $this->_input->filter(array(
			'original_group_id' => XenForo_Input::STRING,
			'group_id' => XenForo_Input::STRING,
			'display_order' => XenForo_Input::UINT,
			'debug_only' => XenForo_Input::UINT,
			'addon_id' => XenForo_Input::STRING
		));
		$titlePhrase = $this->_input->filterSingle('title', XenForo_Input::STRING);
		$descriptionPhrase = $this->_input->filterSingle('description', XenForo_Input::STRING);

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_OptionGroup');
		if ($input['original_group_id'])
		{
			$dw->setExistingData($input['original_group_id']);
		}

		$dw->set('group_id', $input['group_id']);
		$dw->set('display_order', $input['display_order']);
		$dw->set('debug_only', $input['debug_only']);
		$dw->set('addon_id', $input['addon_id']);
		$dw->setExtraData(XenForo_DataWriter_OptionGroup::DATA_TITLE, $titlePhrase);
		$dw->setExtraData(XenForo_DataWriter_OptionGroup::DATA_DESCRIPTION, $descriptionPhrase);
		$dw->save();

		$redirectType = ($input['original_group_id']
			? XenForo_ControllerResponse_Redirect::RESOURCE_CREATED
			: XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED);

		return $this->responseRedirect(
			$redirectType,
			XenForo_Link::buildAdminLink('options/list', $dw->getMergedData())
		);
	}

	/**
	 * Displays a form to delete an existing option group.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDeleteGroup()
	{
		$this->_assertOptionOrGroupDefinitionEditable();

		$groupId = $this->_input->filterSingle('group_id', XenForo_Input::STRING);

		if ($this->isConfirmedPost())
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_OptionGroup');
			$dw->setExistingData($groupId);
			$dw->delete();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('options')
			);
		}
		else // show delete confirmation dialog
		{
			$viewParams = array('group' => $this->_getOptionGroupOrError($groupId));

			return $this->responseView('XenForo_ViewAdmin_Option_DeleteGroup', 'option_group_delete', $viewParams);
		}
	}

	/**
	 * Allows easy editing of the display order of options in the specified group
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDisplayOrder()
	{
		$this->_assertOptionOrGroupDefinitionEditable();

		$groupId = $this->_input->filterSingle('group_id', XenForo_Input::STRING);
		$group = $this->_getOptionGroupOrError($groupId);

		$optionModel = $this->_getOptionModel();
		$options = $optionModel->getOptionsInGroup($groupId);

		if ($this->isConfirmedPost())
		{
			// save adjusted display orders
			$displayOrders = $this->_input->filterSingle('option', array(XenForo_Input::UINT, 'array' => true));

			$relations = $optionModel->getOptionRelationsGroupedByOption(array_keys($options));

			XenForo_Db::beginTransaction();

			foreach ($displayOrders AS $optionId => $displayOrder)
			{
				if (isset($relations[$optionId][$groupId]))
				{
					if ($relations[$optionId][$groupId]['display_order'] != $displayOrder)
					{
						$relations[$optionId][$groupId]['display_order'] = $displayOrder;

						$newRelations = array();
						foreach ($relations[$optionId] AS $relation)
						{
							$newRelations[$relation['group_id']] = $relation['display_order'];
						}

						$dw = XenForo_DataWriter::create('XenForo_DataWriter_Option', XenForo_DataWriter::ERROR_EXCEPTION);
						$dw->setExistingData($optionId);
						$dw->setRelations($newRelations);
						$dw->save();
					}
				}
			}

			XenForo_Db::commit();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('options/list', $group)
			);
		}
		else
		{
			$viewParams = array(
				'group' => $group,
				'options' => $options,
				'preparedOptions' => $optionModel->prepareOptions($options)
			);

			return $this->responseView('XenForo_ViewAdmin_Option_DisplayOrder', 'option_display_order', $viewParams);
		}
	}

	/**
	 * Gets the controller response for adding or editing an option.
	 *
	 * @param array $option Information about the option
	 * @param array $relations Option group relations ([group id] => display order)
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	protected function _getOptionAddEditResponse(array $option, array $relations = array())
	{
		$optionModel = $this->_getOptionModel();
		$addOnModel = $this->_getAddOnModel();

		$viewParams = array(
			'option' => $option,
			'groups' => $optionModel->prepareOptionGroups($optionModel->getOptionGroupList()),
			'masterTitle' => $optionModel->getOptionMasterTitlePhraseValue($option['option_id']),
			'masterExplain' => $optionModel->getOptionMasterExplainPhraseValue($option['option_id']),
			'relations' => $relations,
			'addOnOptions' => $addOnModel->getAddOnOptionsListIfAvailable(),
			'addOnSelected' => (isset($option['addon_id']) ? $option['addon_id'] : $addOnModel->getDefaultAddOnId())
		);

		return $this->responseView('XenForo_ViewAdmin_Option_EditOption', 'option_edit', $viewParams);
	}

	/**
	 * Displays a form to add an option.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionAddOption()
	{
		$this->_assertOptionOrGroupDefinitionEditable();

		$option = $this->_getOptionModel()->getDefaultOption();

		$groupId = $this->_input->filterSingle('group_id', XenForo_Input::STRING);
		$relations = array($groupId => 1);

		return $this->_getOptionAddEditResponse($option, $relations);
	}

	/**
	 * Displays a form to edit an existing option.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEditOption()
	{
		$this->_assertOptionOrGroupDefinitionEditable();

		$optionId = $this->_input->filterSingle('option_id', XenForo_Input::STRING);

		$option = $this->_getOptionOrError($optionId);
		$relations = $this->_getOptionModel()->getOptionRelationsByOptionId($optionId);

		return $this->_getOptionAddEditResponse($option, $relations);
	}

	/**
	 * Inserts a new option or updates an existing one.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSaveOption()
	{
		$this->_assertPostOnly();
		$this->_assertOptionOrGroupDefinitionEditable();

		$input = $this->_input->filter(array(
			'original_option_id' => XenForo_Input::STRING,
			'relations' => XenForo_Input::ARRAY_SIMPLE
		));

		$dwInput = $this->_input->filter(array(
			'option_id' => XenForo_Input::STRING,
			'default_value' => XenForo_Input::STRING,
			'edit_format' => XenForo_Input::STRING,
			'edit_format_params' => XenForo_Input::STRING,
			'data_type' => XenForo_Input::STRING,
			'sub_options' => XenForo_Input::STRING,
			'can_backup' => XenForo_Input::UINT,
			'validation_class' => XenForo_Input::STRING,
			'validation_method' => XenForo_Input::STRING,
			'addon_id' => XenForo_Input::STRING
		));
		$phrase = $this->_input->filter(array(
			'title' => XenForo_Input::STRING,
			'explain' => XenForo_Input::STRING
		));

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_Option');
		if ($input['original_option_id'])
		{
			$dw->setExistingData($input['original_option_id']);
		}

		$dw->bulkSet($dwInput);
		$dw->setExtraData(XenForo_DataWriter_Option::DATA_TITLE, $phrase['title']);
		$dw->setExtraData(XenForo_DataWriter_Option::DATA_EXPLAIN, $phrase['explain']);

		$relations = array();
		$firstRelationGroupId = null;
		foreach ($input['relations'] AS $groupId => $data)
		{
			if (!empty($data['selected']) && isset($data['display_order']))
			{
				$relations[$groupId] = intval($data['display_order']);

				if ($firstRelationGroupId === null)
				{
					$firstRelationGroupId = $groupId;
				}
			}
		}
		$dw->setRelations($relations);

		$dw->save();

		$group = $this->_getOptionModel()->getOptionGroupById($firstRelationGroupId);

		$redirectType = ($input['original_option_id']
			? XenForo_ControllerResponse_Redirect::RESOURCE_CREATED
			: XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED);

		return $this->responseRedirect(
			$redirectType,
			XenForo_Link::buildAdminLink('options/list', $group)
		);
	}

	/**
	 * Deletes an existing option.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDeleteOption()
	{
		$this->_assertOptionOrGroupDefinitionEditable();

		$optionId = $this->_input->filterSingle('option_id', XenForo_Input::STRING);

		if ($this->isConfirmedPost())
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_Option');
			$dw->setExistingData($optionId);
			$dw->delete();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('options')
			);
		}
		else // show confirmation dialog
		{
			$option = $this->_getOptionOrError($optionId);

			$viewParams = array(
				'option' => $option
			);

			return $this->responseView('XenForo_ViewAdmin_Option_DeleteOption', 'option_delete', $viewParams);
		}
	}

	/**
	 * Gets the specified option group or throws an exception.
	 *
	 * @param integer $groupId
	 * @param array $fetchOptions
	 *
	 * @return array
	 */
	protected function _getOptionGroupOrError($groupId, array $fetchOptions = array())
	{
		$info = $this->_getOptionModel()->getOptionGroupById($groupId, $fetchOptions);
		if (!$info)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_option_group_not_found'), 404));
		}

		if (!empty($fetchOptions['join']) && $fetchOptions['join'] & XenForo_Model_Option::FETCH_ADDON)
		{
			if ($this->getModelFromCache('XenForo_Model_AddOn')->isAddOnDisabled($info))
			{
				throw $this->responseException($this->responseError(
					new XenForo_Phrase('option_group_belongs_to_disabled_addon', array(
						'addon' => $info['addon_title'],
						'link' => XenForo_Link::buildAdminLink('add-ons', $info)
					))
				));
			}
		}

		return $this->_getOptionModel()->prepareOptionGroup($info);
	}

	/**
	 * Gets the specified option or throws an exception.
	 *
	 * @param integer $optionId
	 *
	 * @return array
	 */
	protected function _getOptionOrError($optionId)
	{
		$info = $this->_getOptionModel()->getOptionById($optionId);
		if (!$info)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_option_not_found'), 404));
		}

		return $this->_getOptionModel()->prepareOption($info);
	}

	/**
	 * Lazy load the option model.
	 *
	 * @return XenForo_Model_Option
	 */
	protected function _getOptionModel()
	{
		return $this->getModelFromCache('XenForo_Model_Option');
	}

	/**
	 * Get the add-on model.
	 *
	 * @return XenForo_Model_AddOn
	 */
	protected function _getAddOnModel()
	{
		return $this->getModelFromCache('XenForo_Model_AddOn');
	}

}