<?php

class XenForo_Install_Model_Upgrade extends XenForo_Model
{
	public function insertUpgradeLog($versionId = null, $type = 'upgrade', $userId = null)
	{
		if ($versionId === null)
		{
			$versionId = XenForo_Application::$versionId;
		}

		if ($userId === null)
		{
			$userId = XenForo_Visitor::getUserId();
		}

		$this->_getDb()->insert('xf_upgrade_log', array(
			'version_id' => $versionId,
			'completion_date' => XenForo_Application::$time,
			'user_id' => $userId,
			'log_type' => $type
		));
	}

	public function updateVersion()
	{
		$this->getModelFromCache('XenForo_Model_Option')->updateOptions(
			array('currentVersionId' => XenForo_Application::$versionId)
		);
	}

	public function getLatestUpgradeVersionId()
	{
		return $this->_getDb()->fetchOne('
			SELECT MAX(version_id)
			FROM xf_upgrade_log
		');
	}

	public function getRemainingUpgradeVersionIds($lastCompletedVersion)
	{
		$searchDir = XenForo_Application::getInstance()->getRootDir() . '/library/XenForo/Install/Upgrade';

		$upgrades = array();
		foreach (glob($searchDir . '/*.php') AS $file)
		{
			$file = basename($file);
			$versionId = intval($file);
			if (!$versionId)
			{
				continue;
			}

			$upgrades[] = $versionId;
		}

		sort($upgrades, SORT_NUMERIC);

		foreach ($upgrades AS $key => $upgrade)
		{
			if ($upgrade > $lastCompletedVersion)
			{
				return array_slice($upgrades, $key);
			}
		}

		return array();
	}

	public function getNextUpgradeVersionId($lastCompletedVersion)
	{
		$upgrades = $this->getRemainingUpgradeVersionIds($lastCompletedVersion);
		return reset($upgrades);
	}

	public function getUpgrade($versionId)
	{
		$versionId = intval($versionId);
		if (!$versionId)
		{
			throw new XenForo_Exception('No upgrade version ID specified.');
		}

		$searchDir = XenForo_Application::getInstance()->getRootDir() . '/library/XenForo/Install/Upgrade';

		$matches = glob($searchDir . '/' . $versionId . '*.php');
		foreach ($matches AS $file)
		{
			$file = basename($file);
			if (intval($file) == $versionId)
			{
				require($searchDir . '/' . $file);
				$class = 'XenForo_Install_Upgrade_' . intval($file);

				return new $class();
			}
		}

		throw new XenForo_Exception('Could not find the specified upgrade.');
	}
}