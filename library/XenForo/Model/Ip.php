<?php

/**
 * Model for logging IPs and querying them.
 *
 * @package XenForo_Ip
 */
class XenForo_Model_Ip extends XenForo_Model
{
	/**
	 * Stores resolved host names from IPs
	 *
	 * @var array
	 */
	protected $_hostCache = array();

	/**
	 * Logs an IP for an action.
	 *
	 * @param integer $userId User causing action
	 * @param string $contentType Type of content (user, post)
	 * @param integer $contentId ID of content
	 * @param string $action Action (insert, login)
	 * @param string|null $ipAddress IPv4 address or null to pull from request
	 * @param integer|null $date Timestamp to tag IP with
	 *
	 * @return integer ID of inserted IP; 0 if no insert
	 */
	public function logIp($userId, $contentType, $contentId, $action, $ipAddress = null, $date = null)
	{
		if ($ipAddress == null)
		{
			$ipAddress = (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : false);
		}

		if (is_string($ipAddress) && strpos($ipAddress, '.'))
		{
			$ipAddress = ip2long($ipAddress);
		}
		else
		{
			$ipAddress = false;
		}

		if (!$ipAddress)
		{
			return 0;
		}

		if ($date === null)
		{
			$date = XenForo_Application::$time;
		}

		$this->_getDb()->insert('xf_ip', array(
			'user_id' => $userId,
			'content_type' => $contentType,
			'content_id' => $contentId,
			'action' => $action,
			'ip' => sprintf('%u', $ipAddress),
			'log_date' => max(0, $date)
		));

		return $this->_getDb()->lastInsertId();
	}

	/**
	 * Static helper to log IPs without creating the model first.
	 *
	 * @see XenForo_Model_Ip::logIp()
	 */
	public static function log($userId, $contentType, $contentId, $action, $ipAddress = null, $date = null)
	{
		return XenForo_Model::create(__CLASS__)->logIp(
			$userId, $contentType, $contentId, $action, $ipAddress, $date
		);
	}

	/**
	 * Fetches an IP record by its id
	 *
	 * @param integer $ipId
	 *
	 * @return array
	 */
	public function getIpById($ipId)
	{
		$ip = $this->_getDb()->fetchRow('
			SELECT * FROM xf_ip
			WHERE ip_id = ?
		', $ipId);

		$ip['ip_address'] = long2ip($ip['ip']);

		return $ip;
	}

	/**
	 * Static version of getIpById
	 *
	 * @see XenForo_Model_Ip::getIpById()
	 */
	public static function getById($ipId)
	{
		return XenForo_Model::create(__CLASS__)->getIpById($ipId);
	}

	/**
	 * Returns the first IP logged for the given parameters
	 *
	 * @param integer $userId
	 * @param string $contentType
	 * @param integer $contentId
	 *
	 * @return string IPv4
	 */
	public function getIp($userId, $contentType, $contentId)
	{
		$ip = $this->_getDb()->fetchOne('
			SELECT ip
			FROM xf_ip
			WHERE user_id = ?
			AND content_type = ?
			AND content_id = ?
		', array($userId, $contentType, $contentId));

		return ($ip ? long2ip($ip) : '');
	}

	/**
	 * Static helper to get a logged ip wihtout creating the model first
	 *
	 * @see XenForo_Model_Ip::getIp()
	 */
	public static function get($userId, $contentType, $contentId)
	{
		return XenForo_Model::create(__CLASS__)->getIp(
			$userId, $contentType, $contentId
		);
	}

	/**
	 * Deletes all IPs that belong to the specified content.
	 *
	 * @param string $contentType
	 * @param int|array $contentIds One or more content Ids to delete from
	 */
	public function deleteByContent($contentType, $contentIds)
	{
		if (!is_array($contentIds))
		{
			$contentIds = array($contentIds);
		}
		if (!$contentIds)
		{
			return;
		}

		$db = $this->_getDb();

		$db->delete('xf_ip',
			'content_type = ' . $db->quote($contentType) . ' AND content_id IN (' . $db->quote($contentIds) . ')'
		);
	}

	protected static $_lookupCache = array();

	/**
	 * Resolves the host name of an IP address
	 *
	 * @param string $ip
	 *
	 * @return string
	 */
	protected function _getHost($ip)
	{
		$parts = explode('.', $ip);
		if (count($parts) != 4)
		{
			return '';
		}

		if (isset(self::$_lookupCache[$ip]))
		{
			return self::$_lookupCache[$ip];
		}

		$lookup = false;

		try
		{
			if (function_exists('dns_get_record'))
			{
				$host = dns_get_record(implode('.', array_reverse($parts)) . '.in-addr.arpa', DNS_PTR);
				if (isset($host[0]['target']))
				{
					$lookup = $host[0]['target'];
				}
			}
			else
			{
				$lookup = gethostbyaddr($ip);
			}
		}
		catch (Exception $e) {} // bad lookup

		if (!$lookup)
		{
			$lookup = $ip;
		}

		self::$_lookupCache[$ip] = $lookup;
		return $lookup;
	}

	/**
	 * Resolves the host name of an IP address
	 *
	 * @param string $ip
	 *
	 * @return string
	 */
	public static function getHost($ip)
	{
		return XenForo_Model::create(__CLASS__)->_getHost($ip);
	}

	/**
	 * Gets IP info for a content item and the member who created it
	 *
	 * @param array $content
	 * @param boolean $resolveHosts
	 *
	 * @return array (contentIp, contentHost, registrationIp, registrationHost, confirmationIp, confirmationHost)
	 */
	public function getContentIpInfo(array $content)
	{
		if ($content['ip_id'])
		{
			$ip = $this->getIpById($content['ip_id']);
			$contentIp = $ip['ip_address'];
			$contentHost = $this->_getHost($contentIp);
		}

		return $this->getRegistrationIps($content['user_id']) + array(
			'contentIp'    => (empty($contentIp) ? false : $contentIp),
			'contentHost'  => (empty($contentIp) ? false : $this->_getHost($contentIp)),
		);
	}

	/**
	 * Gets IP info for an online user
	 *
	 * @param array $onlineUser
	 *
	 * @return array (contentIp, contentHost, registrationIp, registrationHost, confirmationIp, confirmationHost)
	 */
	public function getOnlineUserIp($onlineUser)
	{
		if ($onlineUser['ip'])
		{
			$contentIp = long2ip($onlineUser['ip']);
			$contentHost = $this->_getHost($contentIp);
		}

		return $this->getRegistrationIps($onlineUser['user_id']) + array(
			'contentIp'   => (empty($contentIp) ? false : $contentIp),
			'contentHost' => (empty($contentIp) ? false : $this->_getHost($contentIp)),
		);
	}

	/**
	 * Fetches an array containing IP info for the registration and confirmation IPs of the given user
	 *
	 * @param integer $userId
	 *
	 * @return array (registrationIp, registrationHost, confirmationIp, confirmationHost)
	 */
	protected function getRegistrationIps($userId)
	{
		$userIps = $this->getModelFromCache('XenForo_Model_User')->getRegistrationIps($userId);

		return array(
			'registrationIp'   => (empty($userIps['register']) ? false : $userIps['register']),
			'registrationHost' => (empty($userIps['register']) ? false : $this->_getHost($userIps['register'])),

			'confirmationIp'   => (empty($userIps['account-confirmation']) ? false : $userIps['account-confirmation']),
			'confirmationHost' => (empty($userIps['account-confirmation']) ? false : $this->_getHost($userIps['account-confirmation'])),
		);
	}
}