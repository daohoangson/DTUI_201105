<?php

class XenForo_SabreDav_Directory_AdminTemplates extends Sabre_DAV_Directory
{
	public function getChildren()
	{
		/* @var $adminTemplateModel XenForo_Model_AdminTemplate */
		$adminTemplateModel = XenForo_Model::create('XenForo_Model_AdminTemplate');

		$output = array();
		foreach ($adminTemplateModel->getAllAdminTemplates() AS $template)
		{
			$output[] = new XenForo_SabreDav_File_AdminTemplate($template, $template['title']);
		}

		return $output;
	}

	public function getChild($title)
	{
		if (substr($title, -5) == '.html')
		{
			$title = substr($title, 0, -5);
		}

		/* @var $adminTemplateModel XenForo_Model_AdminTemplate */
		$adminTemplateModel = XenForo_Model::create('XenForo_Model_AdminTemplate');

		$template = $adminTemplateModel->getAdminTemplateByTitle($title);
		return new XenForo_SabreDav_File_AdminTemplate($template, $title);
	}

	public function getName()
	{
		return XenForo_SabreDav_RootDirectory::ADMIN_TEMPLATES;
	}
}