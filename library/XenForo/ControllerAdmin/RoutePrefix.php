<?php

class XenForo_ControllerAdmin_RoutePrefix extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		$this->assertDebugMode();
	}

	/**
	 * Lists the current route prefixes.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIndex()
	{
		$prefixModel = $this->_getRoutePrefixModel();

		$publicPrefixes = $prefixModel->getPrefixesByRouteType('public');
		$adminPrefixes = $prefixModel->getPrefixesByRouteType('admin');

		$viewParams = array(
			'publicPrefixes' => $publicPrefixes,
			'adminPrefixes' => $adminPrefixes,
			'totalPrefixes' => count($publicPrefixes) + count($adminPrefixes)
		);

		return $this->responseView('XenForo_ViewAdmin_RoutePrefix_List', 'route_prefix_list', $viewParams);
	}

	/**
	 * Helper function to get the prefix add/edit form controller response.
	 *
	 * @param array $prefix
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	protected function _getRoutePrefixAddEditResponse(array $prefix)
	{
		$addOnModel = $this->_getAddOnModel();

		$viewParams = array(
			'prefix' => $prefix,
			'addOnOptions' => $addOnModel->getAddOnOptionsListIfAvailable(),
			'addOnSelected' => (isset($prefix['addon_id']) ? $prefix['addon_id'] : $addOnModel->getDefaultAddOnId())
		);

		return $this->responseView('XenForo_ViewAdmin_RoutePrefix_Edit', 'route_prefix_edit', $viewParams);
	}

	/**
	 * Displays a form to create a new prefix.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionAdd()
	{
		return $this->_getRoutePrefixAddEditResponse($this->_getRoutePrefixModel()->getDefaultRoutePrefix());
	}

	/**
	 * Displays a form to edit an existing prefix.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEdit()
	{
		$input = $this->_input->filter(array(
			'original_prefix' => XenForo_Input::STRING,
			'route_type' => XenForo_Input::STRING
		));

		$prefix = $this->_getRoutePrefixOrError($input['original_prefix'], $input['route_type']);

		return $this->_getRoutePrefixAddEditResponse($prefix);
	}

	/**
	 * Inserts a new route prefix or updates an existing one.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSave()
	{
		$this->_assertPostOnly();

		$originalPrefix = $this->_input->filterSingle('original_original_prefix', XenForo_Input::STRING);
		$originalRouteType = $this->_input->filterSingle('original_route_type', XenForo_Input::STRING);

		$dwInput = $this->_input->filter(array(
			'original_prefix' => XenForo_Input::STRING,
			'route_type' => XenForo_Input::STRING,
			'route_class' => XenForo_Input::STRING,
			'build_link' => XenForo_Input::STRING,
			'addon_id' => XenForo_Input::STRING
		));

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_RoutePrefix');
		if ($originalPrefix)
		{
			$dw->setExistingData(array($originalPrefix, $originalRouteType));
		}
		$dw->bulkSet($dwInput);
		$dw->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('route-prefixes') . $this->getLastHash("{$dwInput['route_type']}_{$dwInput['original_prefix']}")
		);
	}

	/**
	 * Deletes an existing route prefix.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDelete()
	{
		if ($this->isConfirmedPost())
		{
			return $this->_deleteData(
				'XenForo_DataWriter_RoutePrefix',
				$this->_input->filter(array(
					'original_prefix' => XenForo_Input::STRING,
					'route_type' => XenForo_Input::STRING
				)),
				XenForo_Link::buildAdminLink('route-prefixes')
			);
		}
		else // show confirmation dialog
		{
			$input = $this->_input->filter(array(
				'original_prefix' => XenForo_Input::STRING,
				'route_type' => XenForo_Input::STRING
			));

			$prefix = $this->_getRoutePrefixOrError($input['original_prefix'], $input['route_type']);

			$viewParams = array(
				'prefix' => $prefix
			);

			return $this->responseView('XenForo_ViewAdmin_RoutePrefix_Delete', 'route_prefix_delete', $viewParams);
		}
	}

	/**
	 * Gets a valid prefix based on type and original prefix or throws an exception.
	 *
	 * @param string $originalPrefix
	 * @param string $routeType
	 *
	 * @return array
	 */
	protected function _getRoutePrefixOrError($originalPrefix, $routeType)
	{
		$info = $this->_getRoutePrefixModel()->getPrefixByOriginal($originalPrefix, $routeType);
		if (!$info)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_route_prefix_not_found'), 404));
		}

		return $info;
	}

	/**
	 * Gets the route prefix model.
	 *
	 * @return XenForo_Model_RoutePrefix
	 */
	protected function _getRoutePrefixModel()
	{
		return $this->getModelFromCache('XenForo_Model_RoutePrefix');
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