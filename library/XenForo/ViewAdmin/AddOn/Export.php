<?php

/**
 * Helper to get the add-on export data (likely in XML format).
 *
 * @package XenForo_AddOns
 */
class XenForo_ViewAdmin_AddOn_Export extends XenForo_ViewAdmin_Base
{
	/**
	 * Render the exported date to XML.
	 *
	 * @return string
	 */
	public function renderXml()
	{
		$this->setDownloadFileName('addon-' . $this->_params['addOn']['addon_id'] . '.xml');
		return $this->_params['xml']->saveXml();
	}
}