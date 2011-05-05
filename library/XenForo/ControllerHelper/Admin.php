<?php

class XenForo_ControllerHelper_Admin extends XenForo_ControllerHelper_Abstract
{
	public function checkSuperAdminEdit(array $user)
	{
		if ($user['is_admin'] && !XenForo_Visitor::getInstance()->isSuperAdmin())
		{
			$superAdmins = preg_split(
				'#\s*,\s*#', XenForo_Application::get('config')->superAdmins,
				-1, PREG_SPLIT_NO_EMPTY
			);
			if (in_array($user['user_id'], $superAdmins))
			{
				throw $this->_controller->responseException(
					$this->_controller->responseError(new XenForo_Phrase('you_must_be_super_administrator_to_edit_user'))
				);
			}
		}
	}
}