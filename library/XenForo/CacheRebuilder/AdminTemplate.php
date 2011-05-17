<?php

/**
 * Cache rebuilder for admin templates.
 *
 * @package XenForo_CacheRebuild
 */
class XenForo_CacheRebuilder_AdminTemplate extends XenForo_CacheRebuilder_Abstract
{
	/**
	 * Gets rebuild message.
	 */
	public function getRebuildMessage()
	{
		return new XenForo_Phrase('admin_templates');
	}

	/**
	 * Rebuilds the data.
	 *
	 * @see XenForo_CacheRebuilder_Abstract::rebuild()
	 */
	public function rebuild($position = 0, array &$options = array(), &$detailedMessage = '')
	{
		/* @var $templateModel XenForo_Model_AdminTemplate */
		$templateModel = XenForo_Model::create('XenForo_Model_AdminTemplate');

		$templateModel->compileAllParsedAdminTemplates();

		return true;
	}
}