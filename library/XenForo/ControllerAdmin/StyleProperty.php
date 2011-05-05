<?php

class XenForo_ControllerAdmin_StyleProperty extends XenForo_ControllerAdmin_StyleAbstract
{
	public function actionIndex()
	{
		$style = $this->_getStyleFromCookie();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
			XenForo_Link::buildAdminLink('styles/style-properties', $style)
		);
	}

	public function actionColor()
	{
		$style = $this->_getStyleFromCookie();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
			XenForo_Link::buildAdminLink('styles/style-properties', $style, array('group' => 'color'))
		);
	}

	public function actionColorReference()
	{
		$styleId = $this->_input->filterSingle('style_id', XenForo_Input::UINT);

		$propertyModel = $this->_getStylePropertyModel();

		$colors = $propertyModel->getColorPalettePropertiesInStyle($styleId);

		$viewParams = array(
			'colors' => $propertyModel->prepareStyleProperties($colors)
		);

		return $this->responseView(
			'XenForo_ViewAdmin_StyleProperty_ColorReference',
			'style_property_color_reference',
			$viewParams);
	}

	/**
	 * Gets a style ID from the edit_style_id cookie if available.
	 *
	 * @return integer
	 */
	protected function _getStyleFromCookie()
	{
		$styleId = $this->_getStyleIdFromCookie();

		$style = $this->_getStyleModel()->getStyleById($styleId, true);
		if (!$style || !$this->_getStylePropertyModel()->canEditStyleProperty($styleId))
		{
			$style = $this->_getStyleModel()->getStyleById(XenForo_Application::get('options')->defaultStyleId);
		}

		return $style;
	}
}