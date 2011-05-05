<?php

/**
 * Cache rebuilder for email template imports. Note that this does not fully compile the templates.
 *
 * @package XenForo_CacheRebuild
 */
class XenForo_CacheRebuilder_ImportEmailTemplate extends XenForo_CacheRebuilder_Abstract
{
	/**
	 * Gets rebuild message.
	 */
	public function getRebuildMessage()
	{
		return new XenForo_Phrase('email_templates_importing');
	}

	/**
	 * Rebuilds the data.
	 *
	 * @see XenForo_CacheRebuilder_Abstract::rebuild()
	 */
	public function rebuild($position = 0, array &$options = array(), &$detailedMessage = '')
	{
		$options = array_merge(array(
			'file' => XenForo_Application::getInstance()->getRootDir() . '/install/data/email_templates.xml'
		), $options);

		/* @var $templateModel XenForo_Model_EmailTemplate */
		$templateModel = XenForo_Model::create('XenForo_Model_EmailTemplate');

		$document = new SimpleXMLElement($options['file'], 0, true);
		$templateModel->importEmailTemplatesAddOnXml($document, 'XenForo', false);

		return true;
	}
}