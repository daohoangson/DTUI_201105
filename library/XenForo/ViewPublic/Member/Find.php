<?php

class XenForo_ViewPublic_Member_Find extends XenForo_ViewPublic_Base
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