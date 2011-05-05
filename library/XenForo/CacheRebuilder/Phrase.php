<?php

/**
 * Cache rebuilder for phrases.
 *
 * @package XenForo_CacheRebuild
 */
class XenForo_CacheRebuilder_Phrase extends XenForo_CacheRebuilder_Abstract
{
	/**
	 * Gets rebuild message.
	 */
	public function getRebuildMessage()
	{
		return new XenForo_Phrase('phrases');
	}

	/**
	 * Rebuilds the data.
	 *
	 * @see XenForo_CacheRebuilder_Abstract::rebuild()
	 */
	public function rebuild($position = 0, array &$options = array(), &$detailedMessage = '')
	{
		$options = array_merge(array(
			'startLanguage' => 0,
			'startPhrase' => 0,
			'maxExecution' => 10,
			'mapped' => false
		), $options);

		/* @var $phraseModel XenForo_Model_Phrase */
		$phraseModel = XenForo_Model::create('XenForo_Model_Phrase');

		if ($options['startLanguage'] == 0 && $options['startPhrase'] == 0 && !$options['mapped'])
		{
			$phraseModel->insertPhraseMapForLanguages($phraseModel->buildPhraseMapForLanguageTree(0), true);
			$options['mapped'] = true;

			$detailedMessage = str_repeat(' . ', $position + 1);
			return $position + 1;
		}

		$result = $phraseModel->compileAllPhrases(
			$options['maxExecution'], $options['startLanguage'], $options['startPhrase']
		);
		if ($result === true)
		{
			return true;
		}
		else
		{
			$options['startLanguage'] = $result[0];
			$options['startPhrase'] = $result[1];

			$detailedMessage = str_repeat(' . ', $position + 1);

			return $position + 1; // continue again
		}
	}
}