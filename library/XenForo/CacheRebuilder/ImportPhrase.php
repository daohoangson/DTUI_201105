<?php

/**
 * Cache rebuilder for phrase imports. Note that this does not recompile the phrase cache or templates.
 *
 * @package XenForo_CacheRebuild
 */
class XenForo_CacheRebuilder_ImportPhrase extends XenForo_CacheRebuilder_Abstract
{
	/**
	 * Gets rebuild message.
	 */
	public function getRebuildMessage()
	{
		return new XenForo_Phrase('phrases_importing');
	}

	/**
	 * Rebuilds the data.
	 *
	 * @see XenForo_CacheRebuilder_Abstract::rebuild()
	 */
	public function rebuild($position = 0, array &$options = array(), &$detailedMessage = '')
	{
		$options = array_merge(array(
			'file' => XenForo_Application::getInstance()->getRootDir() . '/install/data/phrases.xml',
			'offset' => 0,
			'maxExecution' => 10
		), $options);

		/* @var $phraseModel XenForo_Model_Phrase */
		$phraseModel = XenForo_Model::create('XenForo_Model_Phrase');

		$document = new SimpleXMLElement($options['file'], 0, true);
		$result = $phraseModel->importPhrasesAddOnXml($document, 'XenForo', $options['maxExecution'], $options['offset']);

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