<?php

abstract class XenForo_Install_Upgrade_Abstract
{
	abstract public function getVersionName();

	/**
	 * @return Zend_Db_Adapter_Abstract
	 */
	protected function _getDb()
	{
		return XenForo_Application::get('db');
	}
}