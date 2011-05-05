<?php

class XenForo_DataWriter_Helper_User
{
	public static function verifyUserId($user_id, XenForo_DataWriter $dw, $fieldName = false)
	{
		$db = XenForo_Application::get('db');
		$existing_user_id = $db->fetchOne('
				SELECT user_id
				FROM xf_user
				WHERE user_id = ?
			', $user_id);

		if ($existing_user_id == $user_id)
		{
			return true;
		}

		$dw->error(new XenForo_Phrase('requested_user_not_found'), $fieldName);
		return false;
	}

	public static function updateSecondaryUserGroupIds($userId, $newGroupIds, $oldGroupIds)
	{
		$userDw = XenForo_DataWriter::create('XenForo_DataWriter_User');
		$userDw->setExistingData($userId);
		if ($userDw->get('secondary_group_ids'))
		{
			$existingGroups = array_fill_keys(explode(',', $userDw->get('secondary_group_ids')), true);
		}
		else
		{
			$existingGroups = array();
		}

		if ($oldGroupIds)
		{
			foreach(explode(',', $oldGroupIds) AS $groupId)
			{
				unset($existingGroups[$groupId]);
			}
		}

		if ($newGroupIds)
		{
			foreach(explode(',', $newGroupIds) AS $groupId)
			{
				$existingGroups[$groupId] = true;
			}
		}

		$userDw->setSecondaryGroups(array_keys($existingGroups));
		$userDw->save();

		return $userDw;
	}
}
