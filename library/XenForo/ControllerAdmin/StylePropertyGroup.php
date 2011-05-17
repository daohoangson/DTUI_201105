<?php

/**
 * Controller for managing style property groups.
 *
 * @package XenForo_StyleProperty
 */
class XenForo_ControllerAdmin_StylePropertyGroup extends XenForo_ControllerAdmin_Abstract
{
	/**
	 * Gets the controller response to display the group add/edit form.
	 *
	 * @param array $group
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	protected function _getGroupAddEditResponse(array $group)
	{
		$style = $this->_getStylePropertyModel()->getStyle($group['group_style_id']);
		if (!$style)
		{
			return $this->responseError(new XenForo_Phrase('requested_style_not_found'), 404);
		}

		$addOnModel = $this->_getAddOnModel();

		$viewParams = array(
			'style' => $style,
			'group' => $group,
			'addOnOptions' => ($group['group_style_id'] <= 0 ? $addOnModel->getAddOnOptionsListIfAvailable() : array()),
			'addOnSelected' => (isset($group['addon_id'])
				? $group['addon_id']
				: ($group['group_style_id'] <= 0 ? $addOnModel->getDefaultAddOnId() : '')
			)
		);

		return $this->responseView('XenForo_ViewPublic_StylePropertyGroup_Edit',
			'style_property_group_edit', $viewParams
		);
	}

	/**
	 * Displays a form to add a style property group.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionAdd()
	{
		$styleId = $this->_input->filterSingle('style_id', XenForo_Input::INT); // can be -1
		$group = $this->_getStylePropertyModel()->getDefaultStylePropertyGroup($styleId);

		if (!$this->_getStylePropertyModel()->canEditStylePropertyDefinition($styleId))
		{
			return $this->responseError(new XenForo_Phrase('style_properties_in_style_can_not_be_modified'));
		}

		return $this->_getGroupAddEditResponse($group);
	}

	/**
	 * Displays a form to edit a style property group.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEdit()
	{
		$groupId = $this->_input->filterSingle('property_group_id', XenForo_Input::UINT);
		$group = $this->_getPropertyGroupOrError($groupId);

		if (!$this->_getStylePropertyModel()->canEditStylePropertyDefinition($group['group_style_id']))
		{
			return $this->responseError(new XenForo_Phrase('style_properties_in_style_can_not_be_modified'));
		}

		return $this->_getGroupAddEditResponse($group);
	}

	/**
	 * Creates a new style property group or updates an existing one.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSave()
	{
		$this->_assertPostOnly();

		$groupId = $this->_input->filterSingle('property_group_id', XenForo_Input::UINT);
		$dwInput = $this->_input->filter(array(
			'group_style_id' => XenForo_Input::INT,
			'group_name' => XenForo_Input::STRING,
			'title' => XenForo_Input::STRING,
			'description' => XenForo_Input::STRING,
			'display_order' => XenForo_Input::UINT,
			'addon_id' => XenForo_Input::STRING,
		));

		if (!$this->_getStylePropertyModel()->canEditStylePropertyDefinition($dwInput['group_style_id']))
		{
			return $this->responseError(new XenForo_Phrase('style_properties_in_style_can_not_be_modified'));
		}

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_StylePropertyGroup');
		if ($groupId)
		{
			$dw->setExistingData($groupId);
		}
		$dw->bulkSet($dwInput);
		$dw->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS, //$this->getStylePropertyReturnLink($dwInput['group_style_id'], $dwInput['group_name'])
			XenForo_Link::buildAdminLink('styles/style-properties', array('style_id' => $dwInput['group_style_id'])) // ugly, but uncommon use and saves a query.
				. $this->getLastHash($dwInput['group_name'])
		);
	}

	/**
	 * Delets a style property group.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDelete()
	{
		$groupId = $this->_input->filterSingle('property_group_id', XenForo_Input::UINT);
		$group = $this->_getPropertyGroupOrError($groupId);

		if (!$this->_getStylePropertyModel()->canEditStylePropertyDefinition($group['group_style_id']))
		{
			return $this->responseError(new XenForo_Phrase('style_properties_in_style_can_not_be_modified'));
		}

		if ($this->isConfirmedPost())
		{
			return $this->_deleteData(
				'XenForo_DataWriter_StylePropertyGroup', 'property_group_id',
				$this->getStylePropertyReturnLink($group['group_style_id'])
			);
		}
		else // show confirm dialog
		{
			$viewParams = array(
				'group' => $group,
				'style' => $this->_getStylePropertyModel()->getStyle($group['group_style_id'])
			);

			return $this->responseView('XenForo_ViewPublic_StylePropertyGroup_Delete', 'style_property_group_delete', $viewParams);
		}
	}

	/**
	 * Gets the named property group or throws an error.
	 *
	 * @param integer $id
	 *
	 * @return array
	 */
	protected function _getPropertyGroupOrError($id)
	{
		$propertyModel = $this->_getStylePropertyModel();

		return $propertyModel->prepareStylePropertyGroup($this->getRecordOrError(
			$id, $propertyModel, 'getStylePropertyGroupById', 'requested_style_property_group_not_found'
		));
	}

	public function getStylePropertyReturnLink($styleId, $groupName = false)
	{
		if ($styleId < 0)
		{
			return XenForo_Link::buildAdminLink('admin-style-properties', false, array('group' => $groupName));
		}
		else
		{
			$style = $this->_getStylePropertyModel()->getStyle($styleId);
			return XenForo_Link::buildAdminLink('styles/style-properties', $style, array('group' => $groupName));
		}
	}

	/**
	 * @return XenForo_Model_StyleProperty
	 */
	protected function _getStylePropertyModel()
	{
		return $this->getModelFromCache('XenForo_Model_StyleProperty');
	}

	/**
	 * @return XenForo_Model_AddOn
	 */
	protected function _getAddOnModel()
	{
		return $this->getModelFromCache('XenForo_Model_AddOn');
	}
}