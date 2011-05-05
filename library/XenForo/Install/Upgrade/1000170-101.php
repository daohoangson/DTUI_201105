<?php

class XenForo_Install_Upgrade_1000170 extends XenForo_Install_Upgrade_Abstract
{
	public function getVersionName()
	{
		return '1.0.1';
	}

	public function step1()
	{
		$db = $this->_getDb();

		// better thread index
		try
		{
			$db->query("
				ALTER TABLE xf_thread
					DROP INDEX node_id_sticky,
					ADD INDEX node_id_sticky_last_post_date (node_id, sticky, last_post_date)
			");
		}
		catch (Zend_Db_Exception $e) {}

		return true;
	}
}