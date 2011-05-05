<?php

class XenForo_Install_Upgrade_1000033 extends XenForo_Install_Upgrade_Abstract
{
	public function getVersionName()
	{
		return '1.0.0 Beta 3';
	}

	public function step1()
	{
		$db = $this->_getDb();

		$db->query("
			ALTER TABLE xf_user
				CHANGE email email VARCHAR(120) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
				CHANGE gravatar gravatar VARCHAR(120) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' COMMENT 'If specified, this is an email address corresponding to the user''s ''Gravatar'''
		");

		try
		{
			$db->query("
				ALTER TABLE xf_session_activity
					ADD ip INT UNSIGNED NOT NULL DEFAULT 0
			");
		}
		catch (Zend_Db_Exception $e) {}

		return true;
	}
}