<?php

/**
 * Cache rebuilder for forums.
 *
 * @package XenForo_CacheRebuild
 */
class XenForo_CacheRebuilder_Forum extends XenForo_CacheRebuilder_Abstract
{
	/**
	 * Gets rebuild message.
	 */
	public function getRebuildMessage()
	{
		return new XenForo_Phrase('forums');
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
		$options['batch'] = max(1, isset($options['batch']) ? $options['batch'] : 10);

		if ($position == 0)
		{
			XenForo_Model::create('XenForo_Model_Node')->updateNestedSetInfo();
		}

		/* @var $forumModel XenForo_Model_Forum */
		$forumModel = XenForo_Model::create('XenForo_Model_Forum');

		$forums = $forumModel->getForums(array(), array('limit' => $options['batch'], 'offset' => $position));

		XenForo_Db::beginTransaction();

		foreach ($forums AS $forum)
		{
			$position++;

			$forumDw = XenForo_DataWriter::create('XenForo_DataWriter_Forum', XenForo_DataWriter::ERROR_SILENT);
			if ($forumDw->setExistingData($forum, true))
			{
				$forumDw->rebuildCounters();
				$forumDw->save();
			}
		}

		XenForo_Db::commit();

		$detailedMessage = XenForo_Locale::numberFormat($position);

		if (!$forums)
		{
			return true;
		}
		else
		{
			return $position;
		}
	}
}