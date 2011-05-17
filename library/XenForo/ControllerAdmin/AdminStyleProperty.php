<?php

class XenForo_ControllerAdmin_AdminStyleProperty extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		$this->assertDebugMode();
	}

	/**
	 * Displays a list of admin style properties.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIndex()
	{
		$propertyModel = $this->_getStylePropertyModel();
		$styleId = -1;

		if (!$propertyModel->canEditStyleProperty($styleId))
		{
			return $this->responseError(new XenForo_Phrase('style_properties_in_style_can_not_be_modified'));
		}

		$groupId = $this->_input->filterSingle('group', XenForo_Input::STRING);
		if ($groupId)
		{
			$groups = $propertyModel->getEffectiveStylePropertiesByGroup($styleId);
			if (!isset($groups[$groupId]))
			{
				return $this->responseError(new XenForo_Phrase('requested_style_property_group_not_found'), 404);
			}

			$group = $groups[$groupId];

			list($scalars, $properties) = $propertyModel->filterPropertiesByType($group['properties']);
			unset($group['properties']);

			$viewParams = array(
				'group' => $propertyModel->prepareStylePropertyGroup($group, $styleId),
				'colorPalette' => $propertyModel->prepareStyleProperties($groups['color']['properties'], $styleId),
				'scalars' => $propertyModel->prepareStyleProperties($scalars, $styleId),
				'properties' => $propertyModel->prepareStyleProperties($properties, $styleId),
				'canEditDefinition' => $propertyModel->canEditStylePropertyDefinition($styleId)
			);

			return $this->responseView('XenForo_ViewAdmin_AdminStyleProperty_List', 'admin_style_property_list', $viewParams);
		}
		else
		{
			$groups = $propertyModel->getEffectiveStylePropertyGroupsInStyle($styleId);

			$viewParams = array(
				'groups' => $propertyModel->prepareStylePropertyGroups($groups, $styleId),
				'canEditDefinition' => $propertyModel->canEditStylePropertyDefinition($styleId)
			);

			return $this->responseView('XenForo_ViewAdmin_AdminStyleProperty_GroupList', 'admin_style_property_group_list', $viewParams);
		}
	}

	/**
	 * Saves the admin style properties.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSave()
	{
		$this->_assertPostOnly();

		$styleId = -1;

		if ($_input = $this->_getInputFromSerialized('_xfStylePropertiesData', false))
		{
			$this->_input = $_input;
		}

		$properties = $this->_input->filterSingle('properties', XenForo_Input::ARRAY_SIMPLE);
		$reset = $this->_input->filterSingle('reset', array(XenForo_Input::UINT, 'array' => true));

		// deal with checkboxes
		foreach ($this->_input->filterSingle('checkboxes', XenForo_Input::ARRAY_SIMPLE) AS $propertyDefinitionId)
		{
			if (!isset($properties[$propertyDefinitionId]))
			{
				$properties[$propertyDefinitionId] = 0;
			}
		}

		$this->_getStylePropertyModel()->saveStylePropertiesInStyleFromInput($styleId, $properties, $reset);

		$group = $this->_input->filterSingle('group', XenForo_Input::STRING);
		$tabId = $this->_input->filterSingle('tab_id', XenForo_Input::UINT);

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('admin-style-properties', false, array('group' => $group)) . '#tab-' . $tabId
		);
	}

	/**
	 * @return XenForo_Model_StyleProperty
	 */
	protected function _getStylePropertyModel()
	{
		return $this->getModelFromCache('XenForo_Model_StyleProperty');
	}
}