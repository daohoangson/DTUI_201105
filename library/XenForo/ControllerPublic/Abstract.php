<?php

/**
 * Abstract controller for public actions.
 *
 * @package XenForo_Mvc
 */
abstract class XenForo_ControllerPublic_Abstract extends XenForo_Controller
{
	/**
	 * Pre-dispatch behaviors for the whole set of public controllers.
	 */
	final protected function _preDispatchType($action)
	{
		$this->_assertCorrectVersion($action);
		$this->_assertIpNotBanned();
		$this->_assertViewingPermissions($action);
		$this->_assertNotBanned();
		$this->_assertBoardActive($action);

		if ($this->_isDiscouraged())
		{
			$this->_discourage($action);
		}

		$this->_updateModeratorSessionCaches();
	}

	/**
	 * Asserts that the installed version of the board matches the files.
	 *
	 * @param string $action
	 */
	protected function _assertCorrectVersion($action)
	{
		if (XenForo_Application::debugMode())
		{
			return;
		}

		if (!XenForo_Application::get('config')->checkVersion)
		{
			return;
		}

		if (XenForo_Application::$versionId != XenForo_Application::get('options')->currentVersionId)
		{
			$response = $this->responseMessage(new XenForo_Phrase('board_currently_being_upgraded'));
			throw $this->responseException($response);
		}
	}

	/**
	 * Asserts that the user's IP address is not banned.
	 */
	protected function _assertIpNotBanned()
	{
		if (XenForo_Application::isRegistered('bannedIps'))
		{
			$bannedIps = XenForo_Application::get('bannedIps');
		}
		else
		{
			$bannedIps = XenForo_Model::create('XenForo_Model_Banning')->rebuildBannedIpCache();
		}

		if ($bannedIps && $this->ipMatch($this->_getClientIps(), $bannedIps))
		{
			throw $this->responseException($this->responseReroute('XenForo_ControllerPublic_Error', 'bannedIp'));
		}
	}

	/**
	 * Assert that the visitor has the necessary viewing permissions.
	 *
	 * @param string $action
	 */
	protected function _assertViewingPermissions($action)
	{
		if (!XenForo_Visitor::getInstance()->hasPermission('general', 'view'))
		{
			throw $this->getNoPermissionResponseException();
		}
	}

	/**
	 * Assert that the visitor is not banned.
	 */
	protected function _assertNotBanned()
	{
		if (XenForo_Visitor::getInstance()->get('is_banned'))
		{
			throw $this->responseException($this->responseReroute('XenForo_ControllerPublic_Error', 'banned'), 403);
		}
	}

	/**
	 * Checks that the board is currently active (and can be viewed by the visitor)
	 * or throws an exception.
	 *
	 * @param string $action
	 */
	protected function _assertBoardActive($action)
	{
		$options = XenForo_Application::get('options');
		if (!$options->boardActive && !XenForo_Visitor::getInstance()->get('is_admin'))
		{
			throw $this->responseException($this->responseMessage($options->boardInactiveMessage), 503);
		}
	}

	/**
	* Discourage the current visitor from remaining on the board by making theirs a bad experience.
	*
	* @param string $action
	*/
	protected function _discourage($action)
	{
		$options = XenForo_Application::get('options');

		// random loading delay
		if ($options->discourageDelay['max'])
		{
			usleep(mt_rand($options->discourageDelay['min'], $options->discourageDelay['max']) * 1000000);
		}

		// TODO: server busy message?

		// random page redirect
		if ($options->discourageRedirectChance && mt_rand(0, 100) < $options->discourageRedirectChance)
		{
			header('Location: ' . ($options->discourageRedirectUrl ? $options->discourageRedirectUrl : $options->boardUrl));
			die();
		}

		// random blank page
		if ($options->discourageBlankChance && mt_rand(0, 100) < $options->discourageBlankChance)
		{
			die();
		}

		// randomly disable search
		if ($options->discourageSearchChance && mt_rand(0, 100) < $options->discourageSearchChance)
		{
			$options->set('enableSearch', false);
		}

		// randomly disable news feed
		if ($options->discourageNewsFeedChance && mt_rand(0, 100) < $options->discourageNewsFeedChance)
		{
			$options->set('enableNewsFeed', false);
		}

		// increase flood check time
		if ($options->discourageFloodMultiplier > 1)
		{
			$options->set('floodCheckTime', $options->floodCheckTime * $options->discourageFloodMultiplier);
		}
	}

	/**
	 * Checks whether a user is being discouraged, either by user preference or IP match
	 *
	 * @return boolean
	 */
	protected function _isDiscouraged()
	{
		if (XenForo_Visitor::getInstance()->get('is_discouraged'))
		{
			return true;
		}
		else
		{
			if (XenForo_Application::isRegistered('discouragedIps'))
			{
				$discouragedIps = XenForo_Application::get('discouragedIps');
			}
			else
			{
				$discouragedIps = XenForo_Model::create('XenForo_Model_Banning')->rebuildBannedIpCache();
			}

			if ($discouragedIps && $this->ipMatch($this->_getClientIps(), $discouragedIps))
			{
				return true;
			}

			return false;
		}
	}

	/**
	 * Updates the moderator session caches if necessary.
	 */
	protected function _updateModeratorSessionCaches()
	{
		if (!XenForo_Application::isRegistered('session'))
		{
			return;
		}

		$visitor = XenForo_Visitor::getInstance();
		if (!$visitor['is_moderator'])
		{
			return;
		}

		$this->_updateModeratorSessionReportCounts();
		$this->_updateModeratorSessionModerationCounts();
	}

	/**
	 * Updates the counts in the session for reported content.
	 */
	protected function _updateModeratorSessionReportCounts()
	{
		if (XenForo_Application::isRegistered('reportCounts'))
		{
			$reportCounts = XenForo_Application::get('reportCounts');
		}
		else
		{
			$reportCounts = $this->getModelFromCache('XenForo_Model_Report')->rebuildReportCountCache();
		}

		$session = XenForo_Application::get('session');
		$sessionReportCounts = $session->get('reportCounts');

		if (!is_array($sessionReportCounts) || $sessionReportCounts['lastBuildDate'] < $reportCounts['lastModifiedDate'])
		{
			if (!$reportCounts['activeCount'])
			{
				$sessionReportCounts = array(
					'total' => 0,
					'assigned' => 0
				);
			}
			else
			{
				$sessionReportCounts = $this->getModelFromCache('XenForo_Model_Report')->getActiveReportsCountsForUser();
			}

			$sessionReportCounts['lastBuildDate'] = XenForo_Application::$time;
			$session->set('reportCounts', $sessionReportCounts);
		}
	}

	/**
	 * Updates the counts in the session for the moderation queue.
	 */
	protected function _updateModeratorSessionModerationCounts()
	{
		if (XenForo_Application::isRegistered('moderationCounts'))
		{
			$counts = XenForo_Application::get('moderationCounts');
		}
		else
		{
			$counts = $this->getModelFromCache('XenForo_Model_ModerationQueue')->rebuildModerationQueueCountCache();
		}

		$session = XenForo_Application::get('session');
		$sessionCounts = $session->get('moderationCounts');

		if (!is_array($sessionCounts) || $sessionCounts['lastBuildDate'] < $counts['lastModifiedDate'])
		{
			if (!$counts['total'])
			{
				$sessionCounts = array('total' => 0);
			}
			else
			{
				$sessionCounts = array(
					'total' => $this->getModelFromCache('XenForo_Model_ModerationQueue')->getModerationQueueCountForUser()
				);
			}

			$sessionCounts['lastBuildDate'] = XenForo_Application::$time;

			$session->set('moderationCounts', $sessionCounts);
		}
	}

	/**
	 * Gets the response for a generic no permission page.
	 *
	 * @return XenForo_ControllerResponse_Error
	 */
	public function responseNoPermission()
	{
		return $this->responseReroute('XenForo_ControllerPublic_Error', 'noPermission');
	}

	/**
	 * Asserts that the visitor is not flooding with the specified action.
	 * Throws a response exception if flooding occurs.
	 *
	 * @param string $action
	 * @param integer|null $floodingLimit
	 */
	public function assertNotFlooding($action, $floodingLimit = null)
	{
		if (!XenForo_Visitor::getInstance()->hasPermission('general', 'bypassFloodCheck'))
		{
			$floodTimeRemaining = XenForo_Model_FloodCheck::checkFlooding($action, $floodingLimit);
			if ($floodTimeRemaining)
			{
				throw $this->responseException(
					$this->responseFlooding($floodTimeRemaining)
				);
			}
		}
	}

	protected function _buildLink($type, $data = null, array $params = array())
	{
		return XenForo_Link::buildPublicLink($type, $data, $params);
	}

	/**
	 * Helper to assert that access to this action requires registration and logged-in-edness.
	 * Throws an exception for visitors that do not meet these criteria.
	 */
	protected function _assertRegistrationRequired()
	{
		if (!XenForo_Visitor::getUserId())
		{
			throw $this->responseException(
				$this->responseReroute('XenForo_ControllerPublic_Error', 'registrationRequired')
			);
		}
	}

	/**
	 * Gets an array containing the isRegistrationOrLogin parameter
	 *
	 * @return array
	 */
	protected function _getRegistrationContainerParams()
	{
		return array('hideLoginBar' => true);
	}
}