<?php

class XenForo_ViewAdmin_User_SearchName extends XenForo_ViewAdmin_Base
{
	public function renderJson()
	{
		$results = array();
		foreach ($this->_params['users'] AS $user)
		{
			$results[$user['username']] = htmlspecialchars($user['username']);
		}

		// TODO: can expand this to return avatars, etc

		return array(
			'results' => $results
		);
	}
}