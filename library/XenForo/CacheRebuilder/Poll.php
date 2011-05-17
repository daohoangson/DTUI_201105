<?php

/**
 * Cache rebuilder for pol.
 *
 * @package XenForo_CacheRebuild
 */
class XenForo_CacheRebuilder_Poll extends XenForo_CacheRebuilder_Abstract
{
	/**
	 * Gets rebuild message.
	 */
	public function getRebuildMessage()
	{
		return new XenForo_Phrase('polls');
	}

	/**
	 * Shows the exit link.
	 */
	public function showExitLink()
	{
		return true;
	}

	/**
	 * Rebuilds the data.
	 *
	 * @see XenForo_CacheRebuilder_Abstract::rebuild()
	 */
	public function rebuild($position = 0, array &$options = array(), &$detailedMessage = '')
	{
		$options['batch'] = isset($options['batch']) ? $options['batch'] : 100;
		$options['batch'] = max(1, $options['batch']);

		/* @var $pollModel XenForo_Model_Poll */
		$pollModel = XenForo_Model::create('XenForo_Model_Poll');

		$pollIds = $pollModel->getPollIdsInRange($position, $options['batch']);
		if (count($pollIds) == 0)
		{
			return true;
		}

		XenForo_Db::beginTransaction();

		foreach ($pollIds AS $pollId)
		{
			$position = $pollId;
			$pollModel->rebuildPollData($pollId);
		}

		XenForo_Db::commit();

		$detailedMessage = XenForo_Locale::numberFormat($position);

		return $position;
	}
}