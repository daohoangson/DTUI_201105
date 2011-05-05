<?php

class XenForo_Install_Upgrade_1000035 extends XenForo_Install_Upgrade_Abstract
{
	public function getVersionName()
	{
		return '1.0.0 Beta 5';
	}

	public function step1()
	{
		$db = $this->_getDb();

		$db->query("
			INSERT IGNORE INTO xf_permission_entry
				(user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
			SELECT user_group_id, user_id, 'forum', 'editOwnPostTimeLimit', 'use_int', -1
			FROM xf_permission_entry
			WHERE permission_group_id = 'forum'
				AND permission_id = 'editOwnPost'
				AND permission_value = 'allow'
		");
		// the permission cache will be rebuilt at the end

		try
		{
			$db->query("
				ALTER TABLE xf_forum_read
					DROP PRIMARY KEY,
					ADD forum_read_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST,
					ADD UNIQUE KEY user_id_node_id (user_id, node_id)
			");
		}
		catch (Zend_Db_Exception $e) {}

		try
		{
			$db->query("
				ALTER TABLE xf_thread_read
					DROP PRIMARY KEY,
					ADD thread_read_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST,
					ADD UNIQUE KEY user_id_thread_id (user_id, thread_id)
			");
		}
		catch (Zend_Db_Exception $e) {}

		return true;
	}
}