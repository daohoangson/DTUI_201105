<?php

/**
 * Cache rebuilder for template imports. Note that this does not fully compile the templates.
 *
 * @package XenForo_CacheRebuild
 */
class XenForo_CacheRebuilder_ImportTemplate extends XenForo_CacheRebuilder_Abstract
{
	/**
	 * Gets rebuild message.
	 */
	public function getRebuildMessage()
	{
		return new XenForo_Phrase('templates_importing');
	}

	/**
	 * Rebuilds the data.
	 *
	 * @see XenForo_CacheRebuilder_Abstract::rebuild()
	 */
	public function rebuild($position = 0, array &$options = array(), &$detailedMessage = '')
	{
		$options = array_merge(array(
			'file' => XenForo_Application::getInstance()->getRootDir() . '/install/data/templates.xml',
			'offset' => 0,
			'maxExecution' => 10
		), $options);

		/* @var $templateModel XenForo_Model_Template */
		$templateModel = XenForo_Model::create('XenForo_Model_Template');

		$document = new SimpleXMLElement($options['file'], 0, true);
		$result = $templateModel->importTemplatesAddOnXml($document, 'XenForo', $options['maxExecution'], $options['offset']);

		if (is_int($result))
		{
			$options['offset'] = $result;
			$detailedMessage = str_repeat(' . ', $position + 1);

			return $position + 1; // continue again
		}
		else
		{
			return true;
		}
	}
}