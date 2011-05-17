<?php

/**
 * Cache rebuilder for threads.
 *
 * @package XenForo_CacheRebuild
 */
class XenForo_CacheRebuilder_Thread extends XenForo_CacheRebuilder_Abstract
{
	/**
	 * Gets rebuild message.
	 */
	public function getRebuildMessage()
	{
		return new XenForo_Phrase('threads');
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
		$options = array_merge(array(
			'batch' => 100,
			'positionRebuild' => false
		), $options);

		/* @var $threadModel XenForo_Model_Thread */
		$threadModel = XenForo_Model::create('XenForo_Model_Thread');

		$threadIds = $threadModel->getThreadIdsInRange($position, $options['batch']);
		if (sizeof($threadIds) == 0)
		{
			return true;
		}

		XenForo_Db::beginTransaction();

		foreach ($threadIds AS $threadId)
		{
			$position = $threadId;

			$dw = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread', XenForo_DataWriter::ERROR_SILENT);
			if ($dw->setExistingData($threadId))
			{
				$dw->setOption(XenForo_DataWriter_Discussion::OPTION_UPDATE_CONTAINER, false);
				if ($options['positionRebuild'])
				{
					$dw->rebuildDiscussion();
				}
				else
				{
					$dw->rebuildDiscussionCounters();
				}
				$dw->save();
			}
		}

		XenForo_Db::commit();

		$detailedMessage = XenForo_Locale::numberFormat($position);

		return $position;
	}
}