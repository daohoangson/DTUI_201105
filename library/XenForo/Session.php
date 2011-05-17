<?php

// TODO: implement caching

/**
 * Session object.
 *
 * @package XenForo_Core
 */
class XenForo_Session
{
	/**
	 * Cache object. If specified, the session will be stored here instead of the DB.
	 *
	 * @var Zend_Cache_Core
	 */
	protected $_cache = null;

	/**
	 * DB object. If no cache is specified, the session will be stored in the DB.
	 *
	 * @var Zend_Db_Adapter_Abstract
	 */
	protected $_db = null;

	/**
	 * Session configuration. See constructor.
	 *
	 * @var array
	 */
	protected $_config = array();

	/**
	 * Session identifier. An md5 hash.
	 *
	 * @var string
	 */
	protected $_sessionId = '';

	/**
	 * Array of arbitrary session information.
	 *
	 * @var string
	 */
	protected $_session = array();

	/**
	 * Determines whether the data inside the session has changed (and needs
	 * to be resaved).
	 *
	 * @var boolean
	 */
	protected $_dataChanged = false;

	/**
	 * True if the session already exists. Becomes true after a session is saved.
	 *
	 * @var boolean
	 */
	protected $_sessionExists = false;

	/**
	 * True if the session has been saved on this request.
	 *
	 * @var boolean
	 */
	protected $_saved = false;

	/**
	 * Search engine domains (excluding TLD)
	 *
	 * @var array
	 */
	public static $searchDomains = array
	(
		'alltheweb',
		'altavista',
		'ask',
		'bing',
		'dogpile',
		'excite',
		'google',
		'lycos',
		'mamma',
		'metacrawler',
		'search',
		'webcrawler',
		'yahoo',
	);

	/**
	 * Known robot user agent substrings
	 *
	 * @var array
	 */
	public static $knownRobots = array
	(
		'avsearch',
		'baiduspider',
		'bingbot',
		'crawler',
		'facebookexternalhit',
		'feedfetcher-google',
		'feedzirra',
		'googlebot',
		'kscrawler',
		'magpie-crawler',
		'nutch',
		'php/',
		'scooter',
		'scoutjet',
		'sogou web spider',
		'twitterbot',
		'xenforo signature generator',
		'yahoo! slurp',
		'yandexbot',
		'zend_http_client',
	);

	/**
	 * Constructor.
	 *
	 * @param array $config Config elements to override default.
	 * @param Zend_Cache_Core|null $cache
	 * @param Zend_Db_Adapter_Abstract|null $db
	 */
	public function __construct(array $config = array(), Zend_Cache_Core $cache = null, Zend_Db_Adapter_Abstract $db = null)
	{
		if (empty($config['admin']))
		{
			$defaultConfig = array(
				'table' => 'xf_session',
				'cookie' => 'session',
				'lifetime' => 3600
			);
		}
		else
		{
			$defaultConfig = array(
				'table' => 'xf_session_admin',
				'cookie' => 'session_admin',
				'lifetime' => (XenForo_Application::debugMode() ? 86400 : 3600) // longer lifetime in debug mode to get in the way less
			);
			unset($config['admin']);
		}
		$defaultConfig['ipCidrMatch'] = 24;

		$this->_config = array_merge($defaultConfig, $config);

		if (!$cache)
		{
			$cache = XenForo_Application::get('cache');
		}
		if ($cache)
		{
			$this->_cache = $cache;
		}

		if (!$db)
		{
			$db = XenForo_Application::get('db');
		}
		$this->_db = $db;
	}

	/**
	 * Starts running the public session handler. This will automatically log in the user via
	 * cookies if needed, and setup the visitor object. The session will be registered in the
	 * registry.
	 *
	 * @param Zend_Controller_Request_Http|null $request
	 *
	 * @return XenForo_Session
	 */
	public static function startPublicSession(Zend_Controller_Request_Http $request = null)
	{
		if (!$request)
		{
			$request = new Zend_Controller_Request_Http();
		}

		$session = self::getPublicSession($request);
		XenForo_Application::set('session', $session);

		$options = $session->getAll();

		$cookiePrefix = XenForo_Application::get('config')->cookie->prefix;
		$cookieStyleId = $request->getCookie($cookiePrefix . 'style_id');
		$cookieLanguageId = $request->getCookie($cookiePrefix . 'language_id');

		$options['languageId'] = $cookieLanguageId;

		$permTest = $session->get('permissionTest');
		if ($permTest && !empty($permTest['user_id']))
		{
			$options['permissionUserId'] = $permTest['user_id'];
		}

		$visitor = XenForo_Visitor::setup($session->get('user_id'), $options);

		if (!$visitor['user_id'])
		{
			if ($request->isPost())
			{
				$guestUsername = $request->get('_guestUsername');
				if (is_string($guestUsername))
				{
					$session->set('guestUsername', $guestUsername);
				}
			}

			$guestUsername = $session->get('guestUsername');
			if (is_string($guestUsername))
			{
				$visitor['username'] = $guestUsername;
			}
		}
		if ($cookieStyleId)
		{
			$visitor['style_id'] = $cookieStyleId;
		}

		if ($session->get('previousActivity') === false)
		{
			$session->set('previousActivity', $visitor['last_activity']);
		}

		return $session;
	}

	/**
	 * This simply gets public session, from cookies if necessary.
	 *
	 * @param Zend_Controller_Request_Http $request
	 *
	 * @return XenForo_Session
	 */
	public static function getPublicSession(Zend_Controller_Request_Http $request)
	{
		$session = new XenForo_Session();
		$session->start();

		if (!$session->sessionExists())
		{
			$cookiePrefix = XenForo_Application::get('config')->cookie->prefix;
			$userCookie = $request->getCookie($cookiePrefix . 'user');

			if ($userCookie)
			{
				if ($userId = XenForo_Model::create('XenForo_Model_User')->loginUserByRememberCookie($userCookie))
				{
					$session->changeUserId($userId);
				}
				else
				{
					XenForo_Helper_Cookie::deleteCookie('user', true);
				}
			}

			if (!empty($_SERVER['HTTP_USER_AGENT']))
			{
				$session->set('userAgent', $_SERVER['HTTP_USER_AGENT']);

				$session->set('isRobot', self::isRobot($_SERVER['HTTP_USER_AGENT']));
			}

			if (!empty($_SERVER['HTTP_REFERER']))
			{
				$session->set('referer', $_SERVER['HTTP_REFERER']);

				$session->set('fromSearch', self::isSearchReferer($_SERVER['HTTP_REFERER']));
			}
		}

		return $session;
	}

	/**
	 * Starts the admin session and sets up the visitor.
	 *
	 * @param Zend_Controller_Request_Http|null $request
	 *
	 * @return XenForo_Session
	 */
	public static function startAdminSession(Zend_Controller_Request_Http $request = null)
	{
		$session = new XenForo_Session(array('admin' => true));
		$session->start();
		XenForo_Application::set('session', $session);

		XenForo_Visitor::setup($session->get('user_id'));

		return $session;
	}

	/**
	 * Starts the session running.
	 *
	 * @param string|null Session ID. If not provided, read from cookie.
	 * @param string|null IPv4 address in string format, for limiting access. If null, grabbed automatically.
	 */
	public function start($sessionId = null, $ipAddress = null)
	{
		if (!headers_sent())
		{
			header('Expires: Thu, 19 Nov 1981 08:52:00 GMT');
			header('Cache-control: private, max-age=0');
		}

		if ($sessionId === null)
		{
			if (isset($_POST['_xfSessionId']))
			{
				$sessionId = $_POST['_xfSessionId'];
			}
			else
			{
				$cookie = XenForo_Application::get('config')->cookie->prefix . $this->_config['cookie'];
				$sessionId = (isset($_COOKIE[$cookie]) ? $_COOKIE[$cookie] : '');
			}

			$sessionId = strval($sessionId);
		}

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

		$this->_setup($sessionId, $ipAddress);
	}

	/**
	 * Sets up the session.
	 *
	 * @param string $sessionId Session ID to look up, if one exists
	 * @param integer|false $ipAddress IPv4 address in int format (ip2long), for access limiting.
	 * @param array|null $defaultSession If no session can be found, uses this as the default session value
	 */
	protected function _setup($sessionId = '', $ipAddress = false, array $defaultSession = null)
	{
		$sessionId = strval($sessionId);

		if ($sessionId)
		{
			$session = $this->getSessionFromSource($sessionId);
			if ($session && !$this->sessionMatchesIp($session, $ipAddress))
			{
				$session = false;
			}
		}
		else
		{
			$session = false;
		}

		if (!is_array($session))
		{
			if ($defaultSession === null)
			{
				$defaultSession = array('sessionStart' => XenForo_Application::$time);
			}

			$sessionId = md5(uniqid(microtime(true), true));
			$session = $defaultSession;
			$sessionExists = false;
		}
		else
		{
			$sessionExists = true;
		}

		if (!isset($session['ip']))
		{
			$session['ip'] = $ipAddress;
		}

		$this->_session = $session;
		$this->_sessionId = $sessionId;
		$this->_sessionExists = $sessionExists;
	}

	/**
	 * Deletes the current session. The session cookie will be removed as well.
	 */
	public function delete()
	{
		if ($this->_sessionExists)
		{
			$this->deleteSessionFromSource($this->_sessionId);
			if (!headers_sent())
			{
				XenForo_Helper_Cookie::deleteCookie($this->_config['cookie'], true);
			}
		}

		$this->_session = array();
		$this->_dataChanged = false;
		$this->_sessionId = '';
		$this->_sessionExists = false;
		$this->_saved = false;
	}

	/**
	 * Saves the current session. If a session is being created, the session cookie will be created.
	 */
	public function save()
	{
		if (!$this->_sessionId || $this->_saved)
		{
			return;
		}

		if (!$this->_sessionExists)
		{
			$this->_db->insert($this->_config['table'], array(
				'session_id' => $this->_sessionId,
				'session_data' => serialize($this->_session),
				'expiry_date' => XenForo_Application::$time + $this->_config['lifetime']
			));

			if (!headers_sent())
			{
				XenForo_Helper_Cookie::setCookie($this->_config['cookie'], $this->_sessionId, 0, true);
			}
		}
		else
		{
			$data = array(
				'expiry_date' => XenForo_Application::$time + $this->_config['lifetime']
			);

			if ($this->_dataChanged)
			{
				$data['session_data'] = serialize($this->_session);
			}

			$this->_db->update($this->_config['table'], $data, 'session_id = ' . $this->_db->quote($this->_sessionId));
		}

		$this->_sessionExists = true;
		$this->_saved = true;
		$this->_dataChanged = false;
	}

	/**
	 * Maintains the current session values (if desired), but changes the session ID.
	 * Use this when the context (eg, user ID) of a session changes.
	 *
	 * @param boolean $keepExisting If true, keeps the existing info; if false, session data is removed
	 */
	public function regenerate($keepExisting = true)
	{
		if ($this->_sessionExists)
		{
			$this->deleteSessionFromSource($this->_sessionId);
		}

		$this->_setup('', $this->get('ip'), ($keepExisting ? $this->_session : null));
	}

	/**
	 * Gets the session ID.
	 *
	 * @return string
	 */
	public function getSessionId()
	{
		return $this->_sessionId;
	}

	/**
	 * Gets the specified data from the session.
	 *
	 * @param string $key
	 *
	 * @return mixed
	 */
	public function get($key)
	{
		return (isset($this->_session[$key]) ? $this->_session[$key] : false);
	}

	/**
	 * Gets all data from the session.
	 *
	 * @return array
	 */
	public function getAll()
	{
		return array_merge($this->_session, array('session_id' => $this->getSessionId()));
	}

	/**
	 * Sets the specified data into the session. Can't be called after saving.
	 *
	 * @param string $key
	 * @param mixed $value
	 */
	public function set($key, $value)
	{
		if ($this->_saved)
		{
			throw new XenForo_Exception('The session has been saved and is now read-only.');
		}

		$this->_session[$key] = $value;
		$this->_dataChanged = true;
	}

	/**
	 * Removes the specified data from the session.
	 *
	 * @param string $key
	 */
	public function remove($key)
	{
		if ($this->_saved)
		{
			throw new XenForo_Exception('The session has been saved and is now read-only.');
		}

		unset($this->_session[$key]);
		$this->_dataChanged = true;
	}

	/**
	 * Changes the user ID of the session and automatically regenerates it to prevent session hijacking.
	 *
	 * @param integer $userId
	 */
	public function changeUserId($userId)
	{
		$this->regenerate();
		$this->set('user_id', intval($userId));
	}

	/**
	 * True if the session exists (existed in previous request or has been saved).
	 *
	 * @return boolean
	 */
	public function sessionExists()
	{
		return $this->_sessionExists;
	}

	/**
	 * True if the session has been saved in this request (and thus write locked).
	 *
	 * @return boolean
	 */
	public function saved()
	{
		return $this->_saved;
	}

	/**
	 * Determines if the existing session matches the given IP address. Looks
	 * for the session's IP in the ip key. If not found or not an int, check passes.
	 *
	 * @param array $session
	 * @param integer|false $ipAddress IPv4 address as int or false to prevent IP check
	 *
	 * @return boolean
	 */
	public function sessionMatchesIp(array $session, $ipAddress)
	{
		if (!isset($session['ip']) || !is_integer($session['ip']) || !is_integer($ipAddress))
		{
			return true; // no IP to check against
		}

		$this->_config['ipCidrMatch'] = intval($this->_config['ipCidrMatch']);
		if ($this->_config['ipCidrMatch'] <= 0)
		{
			return true; // iP check disabled
		}

		$shiftAmount = 32 - min($this->_config['ipCidrMatch'], 32);
		return (($session['ip'] >> $shiftAmount) === ($ipAddress >> $shiftAmount));
	}

	/**
	 * Gets the specified session data from the source.
	 *
	 * @param string $sessionId
	 *
	 * @return array|false
	 */
	public function getSessionFromSource($sessionId)
	{
		$session = $this->_db->fetchRow('
			SELECT session_data, expiry_date
			FROM ' . $this->_config['table'] . '
			WHERE session_id = ?
		', $sessionId);

		if (!$session)
		{
			return false;
		}
		else if ($session['expiry_date'] < XenForo_Application::$time)
		{
			return false;
		}
		else
		{
			$data = unserialize($session['session_data']);
			return (is_array($data) ? $data : false);
		}
	}

	/**
	 * Deletes the specified session from the source.
	 *
	 * @param string $sessionId
	 */
	public function deleteSessionFromSource($sessionId)
	{
		$this->_db->delete($this->_config['table'],
			'session_id = ' . $this->_db->quote($sessionId)
		);
	}

	/**
	 * Deletes all sessions that have expired.
	 */
	public function deleteExpiredSessions()
	{
		$this->_db->delete($this->_config['table'],
			'expiry_date < ' . XenForo_Application::$time
		);
	}

	/**
	 * Checks whether or not the referer is a search engine.
	 *
	 * @param string $referer
	 *
	 * @return string|boolean
	 */
	public static function isSearchReferer($referer)
	{
		if ($url = @parse_url($referer) && !empty($url['host']))
		{
			$url['host'] = strtolower($url['host']);

			if ($url['host'] == XenForo_Application::$host)
			{
				return false;
			}

			if (in_array($url['host'], self::$searchDomains))
			{
				return $url['host'];
			}

			if (preg_match('#((^|\.)(' . implode('|', array_map('preg_quote', self::$searchDomains)) . ')(\.co)?\.[a-z]{2,})$#i', $url['host'], $match))
			{
				return $match[3];
			}
		}

		return false;
	}

	/**
	 * Checks whether or not the user agent is a known robot.
	 *
	 * @param string $userAgent
	 *
	 * @return string|boolean
	 */
	public static function isRobot($userAgent)
	{
		if (preg_match('#(' . implode('|', array_map('preg_quote', self::$knownRobots)) . ')#i', strtolower($userAgent), $match))
		{
			return $match[1];
		}

		return false;
	}
}