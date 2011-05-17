<?php

class XenForo_SabreDav_Directory_Templates extends Sabre_DAV_Directory
{
	protected $_style;

	public function __construct(array $style)
	{
		$this->_style = $style;
	}

	public function getChildren()
	{
		/* @var $templateModel XenForo_Model_Template */
		$templateModel = XenForo_Model::create('XenForo_Model_Template');

		$output = array();
		foreach ($templateModel->getAllEffectiveTemplatesInStyle($this->_style['style_id']) AS $template)
		{
			$output[] = new XenForo_SabreDav_File_Template($template, $this->_style);
		}

		return $output;
	}

	public function getChild($title)
	{
		if (substr($title, -5) == '.html')
		{
			$title = substr($title, 0, -5);
		}

		/* @var $templateModel XenForo_Model_Template */
		$templateModel = XenForo_Model::create('XenForo_Model_Template');

		$template = $templateModel->getEffectiveTemplateByTitle($title, $this->_style['style_id']);
		if ($template)
		{
			return new XenForo_SabreDav_File_Template($template, $this->_style);
		}
		else
		{
			return new XenForo_SabreDav_File_Template(null, $this->_style, $title);
		}
	}

	public function getName()
	{
		return preg_replace('/[^a-z0-9.\-_ ]/i', '_', $this->_style['title']) . ' (' . $this->_style['style_id'] . ')';
	}
}