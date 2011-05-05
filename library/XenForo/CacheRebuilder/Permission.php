<?php

/**
 * Cache rebuilder for permissions.
 *
 * @package XenForo_CacheRebuild
 */
class XenForo_CacheRebuilder_Permission extends XenForo_CacheRebuilder_Abstract
{
	/**
	 * Gets rebuild message.
	 */
	public function getRebuildMessage()
	{
		return new XenForo_Phrase('permissions');
	}

	/**
	 * Rebuilds the data.
	 *
	 * @see XenForo_CacheRebuilder_Abstract::rebuild()
	 */
	public function rebuild($position = 0, array &$options = array(), &$detailedMessage = '')
	{
		$options = array_merge(array(
			'startCombinationId' => 0,
			'maxExecution' => 10
		), $options);

		/* @var $permissionModel XenForo_Model_Permission */
		$permissionModel = XenForo_Model::create('XenForo_Model_Permission');

		$result = $permissionModel->rebuildPermissionCache($options['maxExecution'], $options['startCombinationId']);
		if ($result === true)
		{
			return true;
		}
		else
		{
			$options['startCombinationId'] = $result;

			$detailedMessage = str_repeat(' . ', $position + 1);

			return $position + 1; // continue again
		}
	}
}