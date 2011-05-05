<?php

class XenForo_Install_Upgrade_1000052 extends XenForo_Install_Upgrade_Abstract
{
	public function getVersionName()
	{
		return '1.0.0 Release Candidate 2';
	}

	public function step1()
	{
		$db = $this->_getDb();

		// change alert table index
		try
		{
			$db->query("
				ALTER TABLE xf_user_alert
					DROP INDEX viewDate,
					ADD INDEX viewDate_eventDate (view_date, event_date)
			");
		}
		catch (Zend_Db_Exception $e) {}

		return true;
	}
}