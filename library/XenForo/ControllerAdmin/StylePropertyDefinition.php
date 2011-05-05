<?php

/**
 * Controller for managing style property definitions.
 *
 * @package XenForo_StyleProperty
 */
class XenForo_ControllerAdmin_StylePropertyDefinition extends XenForo_ControllerAdmin_Abstract
{
	/**
	 * Gets the controller response to display the definition
	 * add/edit form.
	 *
	 * @param array $definition
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	protected function _getDefinitionAddEditResponse(array $definition)
	{
		$style = $this->getDefinitionStyle($definition['definition_style_id']);
		if (!$style)
		{
			return $this->responseError(new XenForo_Phrase('requested_style_not_found'), 404);
		}

		$addOnModel = $this->_getAddOnModel();

		$viewParams = array(
			'style' => $style,
			'definition' => $definition,
			'addOnOptions' => ($definition['definition_style_id'] <= 0 ? $addOnModel->getAddOnOptionsListIfAvailable() : array()),
			'addOnSelected' => (isset($definition['addon_id'])
				? $definition['addon_id']
				: ($definition['definition_style_id'] <= 0 ? $addOnModel->getDefaultAddOnId() : '')
			),
			'groupOptions' => $this->_getStylePropertyModel()->getStylePropertyGroupOptions($definition['definition_style_id'])
		);

		return $this->responseView('XenForo_ViewAdmin_StylePropertyDefinition_Edit',
			'style_property_definition_edit', $viewParams
		);
	}

	/**
	 * Displays a form to add a style property definition.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionAdd()
	{
		$styleId = $this->_input->filterSingle('style_id', XenForo_Input::INT); // can be -1);
		$groupName = $this->_input->filterSingle('group_name', XenForo_Input::STRING);

		$definition = $this->_getStylePropertyModel()->getDefaultStylePropertyDefinition($styleId, $groupName);

		if (!$this->_getStylePropertyModel()->canEditStylePropertyDefinition($styleId))
		{
			return $this->responseError(new XenForo_Phrase('style_properties_in_style_can_not_be_modified'));
		}

		return $this->_getDefinitionAddEditResponse($definition);
	}

	/**
	 * Displays a form to edit a style property definition.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEdit()
	{
		$definitionId = $this->_input->filterSingle('property_definition_id', XenForo_Input::UINT);
		$definition = $this->_getPropertyDefinitionOrError($definitionId);

		if (!$this->_getStylePropertyModel()->canEditStylePropertyDefinition($definition['definition_style_id']))
		{
			return $this->responseError(new XenForo_Phrase('style_properties_in_style_can_not_be_modified'));
		}

		return $this->_getDefinitionAddEditResponse($definition);
	}

	/**
	 * Creates a new style property definition or updates an existing one.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSave()
	{
		$this->_assertPostOnly();

		$input = $this->_input->filter(array(
			'property_definition_id' => XenForo_Input::UINT,
			'definition_style_id' => XenForo_Input::INT,
			'group_name' => XenForo_Input::STRING,
			'title' => XenForo_Input::STRING,
			'description' => XenForo_Input::STRING,
			'property_name' => XenForo_Input::STRING,
			'property_type' => XenForo_Input::STRING,
			'css_components' => array(XenForo_Input::STRING, 'array' => true),
			'scalar_type' => array(XenForo_Input::STRING),
			'scalar_parameters' => array(XenForo_Input::STRING),
			'display_order' => XenForo_Input::UINT,
			'sub_group' => XenForo_Input::STRING,
			'addon_id' => XenForo_Input::STRING,
			'property_value_scalar' => XenForo_Input::STRING,
			'property_value_css' => array(XenForo_Input::STRING, 'array' => true)
		));

		if (!$this->_getStylePropertyModel()->canEditStylePropertyDefinition($input['definition_style_id']))
		{
			return $this->responseError(new XenForo_Phrase('style_properties_in_style_can_not_be_modified'));
		}

		$this->_getStylePropertyModel()->createOrUpdateStylePropertyDefinition(
			$input['property_definition_id'], $input
		);

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			$this->getStylePropertyReturnLink($input['definition_style_id'], $input['group_name'])
		);
	}

	/**
	 * Deletes a style property definition
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDelete()
	{
		$definitionId = $this->_input->filterSingle('property_definition_id', XenForo_Input::UINT);
		$definition = $this->_getPropertyDefinitionOrError($definitionId);

		if (!$this->_getStylePropertyModel()->canEditStylePropertyDefinition($definition['definition_style_id']))
		{
			return $this->responseError(new XenForo_Phrase('style_properties_in_style_can_not_be_modified'));
		}

		if ($this->isConfirmedPost())
		{
			return $this->_deleteData(
				'XenForo_DataWriter_StylePropertyDefinition', 'property_definition_id',
				$this->getStylePropertyReturnLink($definition['definition_style_id'], $definition['group_name'])
			);
		}
		else // show confirmation dialog
		{
			$viewParams = array(
				'definition' => $definition,
				'style' => $this->getDefinitionStyle($definition['definition_style_id'])
			);

			return $this->responseView('XenForo_ViewPublic_StylePropertyDefinition_Delete', 'style_property_definition_delete', $viewParams);
		}
	}

	/**
	 * Debug only action for quickly adjusting the display order of the master property definitions
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDisplayOrder()
	{
		$propertyModel = $this->_getStylePropertyModel();

		if (!$propertyModel->canEditStylePropertyDefinition(0)) // master style
		{
			return $this->responseError(new XenForo_Phrase('style_properties_in_style_can_not_be_modified'));
		}

		$group = $this->_input->filterSingle('group', XenForo_Input::STRING);
		if ($group && $groups = $propertyModel->getStylePropertyDefinitionsByGroup($group, 0))
		{
			if ($this->isConfirmedPost())
			{
				// save adjusted display orders
				$displayOrders = $this->_input->filterSingle('property', array(XenForo_Input::UINT, 'array' => true));

				XenForo_Db::beginTransaction();

				foreach ($displayOrders AS $propertyName => $displayOrder)
				{
					if (isset($groups[$propertyName]) && $groups[$propertyName]['display_order'] != $displayOrder)
					{
						$dw = XenForo_DataWriter::create('XenForo_DataWriter_StylePropertyDefinition', XenForo_DataWriter::ERROR_EXCEPTION);
						$dw->setExistingData($groups[$propertyName]['property_definition_id']);
						$dw->set('display_order', $displayOrder);
						$dw->save();
					}
				}

				XenForo_Db::commit();

				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					XenForo_Link::buildAdminLink('style-property-defs/display-order', null, array('group' => $group))
				);
			}
			else
			{
				// show the display order adjustment form
				list($scalars, $properties) = $propertyModel->filterPropertiesByType($groups);

				$viewParams = array(
					'group' => $group,
					'groups' => $groups,
					'scalars' => $scalars,
					'properties' => $properties,
				);

				return $this->responseView(
					'XenForo_ViewAdmin_StyleProperty_DisplayOrder',
					'style_property_display_order',
					$viewParams
				);
			}
		}
		else
		{
			return $this->responseError(new XenForo_Phrase('requested_style_property_group_not_found'), 404);
		}
	}

	/**
	 * Gets the named property definition or throws an error.
	 *
	 * @param integer $id
	 *
	 * @return array
	 */
	protected function _getPropertyDefinitionOrError($id)
	{
		$propertyModel = $this->_getStylePropertyModel();

		return $propertyModel->prepareStyleProperty($this->getRecordOrError(
			$id, $propertyModel, 'getStylePropertyDefinitionById', 'requested_style_property_definition_not_found'
		));
	}

	public function getDefinitionStyle($styleId)
	{
		if ($styleId >= 0)
		{
			return $this->getModelFromCache('XenForo_Model_Style')->getStyleById($styleId, true);
		}
		else
		{
			return array(
				'style_id' => -1,
				'title' => new XenForo_Phrase('admin_control_panel')
			);
		}
	}

	public function getStylePropertyReturnLink($styleId, $groupName = false)
	{
		if ($styleId < 0)
		{
			return XenForo_Link::buildAdminLink('admin-style-properties', false, array('group' => $groupName));
		}
		else
		{
			$style = $this->getDefinitionStyle($styleId);
			return XenForo_Link::buildAdminLink('styles/style-properties', $style, array('group' => $groupName));
		}
	}

	/**
	 * @return XenForo_Model_Style
	 */
	protected function _getStyleModel()
	{
		return $this->getModelFromCache('XenForo_Model_Style');
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