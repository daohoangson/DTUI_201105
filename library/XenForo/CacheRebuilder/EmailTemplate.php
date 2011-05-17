<?php

/**
 * Cache rebuilder for email templates.
 *
 * @package XenForo_CacheRebuild
 */
class XenForo_CacheRebuilder_EmailTemplate extends XenForo_CacheRebuilder_Abstract
{
	/**
	 * Gets rebuild message.
	 */
	public function getRebuildMessage()
	{
		return new XenForo_Phrase('email_templates');
	}

	/**
	 * Rebuilds the data.
	 *
	 * @see XenForo_CacheRebuilder_Abstract::rebuild()
	 */
	public function rebuild($position = 0, array &$options = array(), &$detailedMessage = '')
	{
		/* @var $templateModel XenForo_Model_EmailTemplate */
		$templateModel = XenForo_Model::create('XenForo_Model_EmailTemplate');

		$templateModel->compileAllEmailTemplates();

		return true;
	}
}