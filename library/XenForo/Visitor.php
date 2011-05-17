<?php

/**
* Visitor Class
*
* @package XenForo_Core
*/
class XenForo_Visitor implements ArrayAccess
{
	/**
	* Instance manager.
	*
	* @var XenForo_Visitor
	*/
	private static $_instance;

	/**
	 * List of browser strings to check for in the {@link isBrowsingWith()} function.
	 *
	 * @var array
	 */
	protected static $_browsers = array(
		'firefox' => 'Firefox',
		'ie' => 'MSIE',
		'webkit' => 'WebKit',
		'opera' => 'Opera'
	);

	/**
	* Array of user info.
	*
	* @var array
	*/
	protected $_user = array();

	/**
	 * Language the user is using.
	 *
	 * @var array
	 */
	protected $_language = array();

	/**
	 * Cache of node-specific permissions for this user.
	 *
	 * @var array
	 */
	protected $_nodePermissions = array();

	/**
	 * List of admin permissions the user has. Note that this may not be populated
	 * until necessary.
	 *
	 * @var array|null
	 */
	protected $_adminPermissions = null;

	/**
	 * Stores if user is a super admin.
	 *
	 * @var boolean|null
	 */
	protected $_isSuperAdmin = null;

	/**
	* Protected constructor. Use {@link getInstance()} instead.
	*/
	protected function __construct()
	{
	}

	/**
	* Gets the browsing user's info.
	*
	* @return XenForo_Visitor
	*/
	public static final function getInstance()
	{
		if (!self::$_instance)
		{
			self::setup(0); // setup sets the instance
		}

		return self::$_instance;
	}

	/**
	 * Determines if we have a visitor instance setup.
	 *
	 * @return boolean
	 */
	public static function hasInstance()
	{
		return (self::$_instance ? true : false);
	}

	/**
	 * Returns the user ID of the current visiting user
	 *
	 * @return integer User ID
	 */
	public static function getUserId()
	{
		$object = self::getInstance();

		return $object['user_id'];
	}

	/**
	 * Gets the user info in array format (for areas that require actual arrays).
	 *
	 * @return array
	 */
	public function toArray()
	{
		return $this->_user;
	}

	/**
	 * Gets the user's language.
	 *
	 * @return array
	 */
	public function getLanguage()
	{
		return $this->_language;
	}

	/**
	 * Determines if the visitor has a specific permission.
	 *
	 * @param string $group Permission group
	 * @param string $permission
	 *
	 * @return boolean
	 */
	public function hasPermission($group, $permission)
	{
		return XenForo_Permission::hasPermission($this->_user['permissions'], $group, $permission);
	}

	/**
	 * Gets all global permissions for the visitor.
	 *
	 * @return array Format: [group][permission] => value
	 */
	public function getPermissions()
	{
		return $this->_user['permissions'];
	}

	/**
	 * Set the visitor's permissions for a particular node. Useful caching.
	 *
	 * @param integer $nodeId
	 * @param array|string $permissions Permissions (may be serialized)
	 */
	public function setNodePermissions($nodeId, $permissions)
	{
		if (is_string($permissions))
		{
			$permissions = XenForo_Permission::unserializePermissions($permissions);
		}

		if (is_array($permissions))
		{
			$this->_nodePermissions[$nodeId] = $permissions;
		}
	}

	/**
	 * Returns true if there are node permissions cached for the specified node.
	 *
	 * @param integer $nodeId
	 *
	 * @return boolean
	 */
	public function hasNodePermissionsCached($nodeId)
	{
		return isset($this->_nodePermissions[$nodeId]);
	}

	/**
	 * Determines if the visitor has the specified permission on a specific node.
	 *
	 * @param integer $nodeId
	 * @param string $permission
	 *
	 * @return boolean
	 */
	public function hasNodePermission($nodeId, $permission)
	{
		return XenForo_Permission::hasContentPermission($this->getNodePermissions($nodeId), $permission);
	}

	/**
	 * Gets the visitor's permissions for a specific node. Permissions will be
	 * fetched if necessary.
	 *
	 * @param integer $nodeId
	 *
	 * @return array
	 */
	public function getNodePermissions($nodeId)
	{
		if (!isset($this->_nodePermissions[$nodeId]))
		{
			/* @var $permissionCacheModel XenForo_Model_PermissionCache */
			$permissionCacheModel = XenForo_Model::create('XenForo_Model_PermissionCache');

			$this->_nodePermissions[$nodeId] = $permissionCacheModel->getContentPermissionsForItem(
				$this->_user['permission_combination_id'], 'node', $nodeId
			);
		}

		return $this->_nodePermissions[$nodeId];
	}

	/**
	 * Get all cached node permissions for the visitor. Not all nodes may be present.
	 *
	 * @return array Format: [node id] => permissions
	 */
	public function getAllNodePermissions()
	{
		return $this->_nodePermissions;
	}

	/**
	 * Determines if the current user has the specified admin permission.
	 *
	 * @param string $permissionId
	 *
	 * @return boolean
	 */
	public function hasAdminPermission($permissionId)
	{
		if (empty($this->_user['user_id']) || empty($this->_user['is_admin']))
		{
			return false;
		}

		if ($this->isSuperAdmin())
		{
			return true;
		}

		if (!is_array($this->_adminPermissions))
		{
			$this->_adminPermissions = XenForo_Model::create('XenForo_Model_Admin')->getAdminPermissionCacheForUser(
				$this->_user['user_id']
			);
		}
		return !empty($this->_adminPermissions[$permissionId]);
	}

	/**
	 * Determines if current user is a super admin.
	 *
	 * @return boolean
	 */
	public function isSuperAdmin()
	{
		if ($this->_isSuperAdmin === null)
		{
			$superAdmins = preg_split(
				'#\s*,\s*#', XenForo_Application::get('config')->superAdmins,
				-1, PREG_SPLIT_NO_EMPTY
			);
			$this->_isSuperAdmin = in_array($this->_user['user_id'], $superAdmins);
		}

		return $this->_isSuperAdmin;
	}

	/**
	 * Returns true if the visitor should be shown a CAPTCHA.
	 *
	 * @return boolean
	 */
	public function showCaptcha()
	{
		return ($this->_user['user_id'] == 0); // TODO: permission
	}

	/**
	 * Returns true if visitor can run searches. Does not cover find new or user content searches.
	 *
	 * @return boolean
	 */
	public function canSearch()
	{
		// TODO: we should probably distinguish between search disabled and no permission to search
		return ($this->hasPermission('general', 'search') && XenForo_Application::get('options')->enableSearch);
	}

	/**
	 * Returns true if visitor can upload/change their avatar.
	 *
	 * @return boolean
	 */
	public function canUploadAvatar()
	{
		return (
			$this->_user['user_id']
			&& $this->hasPermission('avatar', 'allowed')
			&& $this->hasPermission('avatar', 'maxFileSize') != 0
		);
	}

	/**
	 * Returns true if the visitor can edit his/her signature.
	 *
	 * @return boolean
	 */
	public function canEditSignature()
	{
		return ($this->_user['user_id'] && $this->hasPermission('general', 'editSignature'));
	}

	/**
	 * Determines if the visitor can update his/hew status.
	 *
	 * @return boolean
	 */
	public function canUpdateStatus()
	{
		if (!$this->_user['user_id'])
		{
			return false;
		}

		return (
			$this->_user['user_id']
			&& $this->hasPermission('profilePost', 'view')
			&& $this->hasPermission('profilePost', 'post')
		);
	}

	/**
	 * Setup the visitor singleton.
	 *
	 * @param integer $userId User ID to setup as
	 * @param array $options
	 *
	 * @return XenForo_Visitor
	 */
	public static function setup($userId, array $options = array())
	{
		$userId = intval($userId);

		$options = array_merge(array(
			'languageId' => 0,
			'permissionUserId' => 0
		), $options);

		/* @var $userModel XenForo_Model_User */
		$userModel = XenForo_Model::create('XenForo_Model_User');

		$object = new self();
		if ($userId && $user = $userModel->getVisitingUserById($userId))
		{
			if ($user['is_admin'] && $options['permissionUserId'])
			{
				// force permissions for testing
				$user = $userModel->setPermissionsFromUserId($user, $options['permissionUserId']);
			}

			$object->_user = $user;
		}
		else
		{
			$object->_user = $userModel->getVisitingGuestUser();

			if ($options['languageId'])
			{
				$object->_user['language_id'] = $options['languageId'];
			}
		}

		$object->_user['referer'] = !empty($options['referer']) ? $options['referer'] : null;
		$object->_user['from_search'] = !empty($options['fromSearch']);
		$object->_user['is_robot'] = !empty($options['isRobot']);

		$object->_user['permissions'] = XenForo_Permission::unserializePermissions($object->_user['global_permission_cache']);

		$object->setVisitorLanguage($object->_user['language_id']);
		XenForo_Locale::setDefaultTimeZone($object->_user['timezone']);

		self::$_instance = $object;

		XenForo_CodeEvent::fire('visitor_setup', array(&self::$_instance));

		return self::$_instance;
	}

	public function setVisitorLanguage($languageId)
	{
		$languages = (XenForo_Application::isRegistered('languages')
			? XenForo_Application::get('languages')
			: XenForo_Model::create('XenForo_Model_Language')->getAllLanguagesForCache()
		);

		if ($languageId && !empty($languages[$languageId]))
		{
			$language = $languages[$languageId];
		}
		else
		{
			$defaultLanguageId = XenForo_Application::get('options')->defaultLanguageId;
			if (!empty($languages[$defaultLanguageId]))
			{
				$language = $languages[$defaultLanguageId];
			}
			else
			{
				$language = reset($languages);
			}
		}

		if (!$language)
		{
			return; // this probably shouldn't happen
		}
		if (empty($language['phrase_cache']))
		{
			$language['phrase_cache'] = array();
		}

		$this->_language = $language;

		XenForo_Phrase::setLanguageId($language['language_id']);
		XenForo_Phrase::setPhrases($language['phrase_cache']);

		XenForo_Locale::setDefaultLanguage($language);
	}

	/**
	 * Checks to verify that the visitor is browsing with a particular user agent
	 *
	 * @param string $browser
	 *
	 * @return boolean
	 */
	public static function isBrowsingWith($browser)
	{
		if (!isset($_SERVER['HTTP_USER_AGENT']))
		{
			return false;
		}

		$ua = $_SERVER['HTTP_USER_AGENT'];

		if ($browser == 'mobile')
		{
			if (self::isBrowsingWith('webkit'))
			{
				if (preg_match('# Mobile( Safari)?/#', $ua)) // iPhone, Android, etc
				{
					return true;
				}
				else if (preg_match('#NokiaN[^\/]*#', $ua))
				{
					return true;
				}
				else if (strpos('SymbianOS', $ua) !== false)
				{
					return true;
				}
			}
			else if (self::isBrowsingWith('opera') && preg_match('#Opera( |/)(Mini|8|9\.[0-7])#', $ua))
			{
				// well, this may not be mobile, but is very old :)
				return true;
			}
			else if (preg_match('#IEMobile/#', $ua))
			{
				return true;
			}
			else if (preg_match('#^BlackBerry#', $ua))
			{
				return true;
			}

			return false;
		}

		//TODO: Add version checking and more browsers
		$browser = strtolower($browser);
		if (array_key_exists($browser, self::$_browsers))
		{
			return (strpos($ua, self::$_browsers[$browser]) !== false);
		}
		else
		{
			return false;
		}
	}

	/**
	 * Returns whether or not the specified user is being followed by the visitor
	 *
	 * @param integer $userId
	 *
	 * @return boolean
	 */
	public function isFollowing($userId)
	{
		if (!$this->_user['user_id'] || $userId == $this->_user['user_id'])
		{
			return false;
		}

		return XenForo_Model::create('XenForo_Model_User')->isFollowing($userId, $this->_user);
	}

	/**
	 * Determines if the visitor is a member of the specified user group
	 *
	 * @param integer $userGroupId
	 * @param boolean $includeSecondaryGroups
	 *
	 * @return boolean
	 */
	public function isMemberOf($userGroupId, $includeSecondaryGroups = true)
	{
		static $userModel = null;
		if ($userModel === null)
		{
			$userModel = XenForo_Model::create('XenForo_Model_User');
		}

		return $userModel->isMemberOfUserGroup($this->_user, $userGroupId, $includeSecondaryGroups);
	}

	/**
	 * OO approach to getting a value from the visitor. Good if you want a single value in one line.
	 *
	 * @param string $name
	 *
	 * @return mixed False if the value can't be found
	 */
	public function get($name)
	{
		if (array_key_exists($name, $this->_user))
		{
			return $this->_user[$name];
		}
		else
		{
			return false;
		}
	}

	/**
	 * For ArrayAccess.
	 *
	 * @param string $offset
	 */
	public function offsetExists($offset)
	{
		return isset($this->_user[$offset]);
	}

	/**
	 * For ArrayAccess.
	 *
	 * @param string $offset
	 */
	public function offsetGet($offset)
	{
		return $this->_user[$offset];
	}

	/**
	 * For ArrayAccess.
	 *
	 * @param string $offset
	 * @param mixed $value
	 */
	public function offsetSet($offset, $value)
	{
		$this->_user[$offset] = $value;
	}

	/**
	 * For ArrayAccess.
	 *
	 * @param string $offset
	 */
	public function offsetUnset($offset)
	{
		unset($this->_user[$offset]);
	}

	/**
	 * Magic method for array access
	 */
	public function __get($name)
	{
		return $this->get($name);
	}
}
