<?php

class XenForo_SabreDav_Directory_TemplateStyles extends Sabre_DAV_Directory
{
	public function getChildren()
	{
		/* @var $styleModel XenForo_Model_Style */
		$styleModel = XenForo_Model::create('XenForo_Model_Style');

		$output = array(
			new XenForo_SabreDav_Directory_Templates($styleModel->getStyleById(0, true))
		);

		foreach ($styleModel->getAllStyles() AS $style)
		{
			$output[] = new XenForo_SabreDav_Directory_Templates($style);
		}

		return $output;
	}

	/**
	 * @see XenForo_SabreDav_Directory_Templates::getName()
	 * @param string 'Safe Style Title ($styleId)'
	 */
	public function getChild($directoryName)
	{
		/* @var $styleModel XenForo_Model_Style */
		$styleModel = XenForo_Model::create('XenForo_Model_Style');

		$styleId = preg_replace('/.*\((\d+)\)$/', '\1', $directoryName);

		$style = $styleModel->getStyleById($styleId, true);
		return new XenForo_SabreDav_Directory_Templates($style);
	}

	public function getName()
	{
		return XenForo_SabreDav_RootDirectory::PUBLIC_TEMPLATES;
	}
}