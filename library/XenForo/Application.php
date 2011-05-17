<?php

if (!defined('XENFORO_AUTOLOADER_SETUP')) { die('No access'); }

/**
* Base XenForo application class. Sets up the environment as necessary and acts as the
* registry for the application. Can broker to the autoload as well.
*
* @package XenForo_Core
*/
class XenForo_Application extends Zend_Registry
{
	const URL_ID_DELIMITER = '.';

	/**
	 * Current printable and encoded versions. These are used for visual output
	 * and installation/upgrading.
	 *
	 * @var string
	 * @var integer
	 */
	public static $version = '1.0.1';
	public static $versionId = 1000170; // abbccde = a.b.c d (alpha: 1, beta: 3, RC: 5, stable: 7, PL: 9) e

	/**
	 * JavaScript cache buster variable
	 *
	 * @var string
	 */
	public static $jsVersion = '';

	/**
	 * jQuery version currently in use. See XenForo_Dependencies_Public::getJquerySource()
	 *
	 * @var string
	 */
	public static $jQueryVersion = '1.4.4';

	/**
	* Path to directory containing the application's configuration file(s).
	*
	* @var string
	*/
	protected $_configDir = '.';

	/**
	* Path to applications root directory. Specific directories will be looked for within this.
	*
	* @var string
	*/
	protected $_rootDir = '.';

	/**
	* Stores whether the application has been initialized yet.
	*
	* @var boolean
	*/
	protected $_initialized = false;

	/**
	* Un-used lazy loaders for the registry. When a lazy loader is called, it
	* is removed from the list. Key is the index and value is an array:
	*    0 => callback
	*    1 => array of arguments
	*
	* @var array
	*/
	protected $_lazyLoaders = array();

	/**
	 * If true, any PHP errors/warnings/notices that come up will be handled
	 * by our error handler. Otherwise, they will be deferred to any previously
	 * registered handler (probably PHP's).
	 *
	 * @var boolean
	 */
	protected static $_handlePhpError = true;

	/**
	 * Controls whether the application is in debug mode.
	 *
	 * @var boolean
	 */
	protected static $_debug;

	/**
	 * Cache of random data. String of hex characters.
	 *
	 * @var string
	 */
	protected static $_randomData = '';

	/**
	 * Cache of dynamic inheritance classes and what they resolve to.
	 *
	 * @var array
	 */
	protected static $_classCache = array();

	/**
	 * Unix timestamp representing the current webserver date and time.
	 * This should be used whenever 'now' needs to be referred to.
	 *
	 * @var integer
	 */
	public static $time = 0;

	/**
	 * Hostname of the server
	 *
	 * @var string
	 */
	public static $host = 'localhost';

	/**
	 * Are we using SSL?
	 *
	 * @var boolean
	 */
	public static $secure = false;

	/**
	 * Value we can use as a sentinel to stand for variable integer values
	 *
	 * @var string
	 */
	public static $integerSentinel = '{{sentinel}}';

	/**
	 * Relative path to the thumbnails / avatars (etc.) directory from the base installation directory.
	 * Must be web accessible and server-writable.
	 * Examples 'data', 'foo/bar/data', '../path/to/thingy'.
	 *
	 * @var string
	 */
	public static $externalDataPath = 'data';

	/**
	* Begin the application. This causes the environment to be setup as necessary.
	*
	* @param string Path to application configuration directory. See {@link $_configDir}.
	* @param string Path to application root directory. See {@link $_rootDir}.
	* @param boolean True to load default data (config, DB, etc)
	*/
	public function beginApplication($configDir = '.', $rootDir = '.', $loadDefaultData = true)
	{
		if ($this->_initialized)
		{
			return;
		}

		if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc())
		{
			self::undoMagicQuotes($_GET);
			self::undoMagicQuotes($_POST);
			self::undoMagicQuotes($_COOKIE);
			self::undoMagicQuotes($_REQUEST);
		}
		if (function_exists('get_magic_quotes_runtime') && get_magic_quotes_runtime())
		{
			@set_magic_quotes_runtime(false);
		}

		@ini_set('memory_limit', 128 * 1024 * 1024);
		ignore_user_abort(true);

		@ini_set('output_buffering', false);
		while (@ob_end_clean());

		error_reporting(E_ALL | E_STRICT & ~8192);
		set_error_handler(array('XenForo_Application', 'handlePhpError'));
		set_exception_handler(array('XenForo_Application', 'handleException'));

		//@ini_set('pcre.backtrack_limit', 1000000);

		date_default_timezone_set('UTC');

		self::$time = time();

		self::$host = (empty($_SERVER['HTTP_HOST']) ? '' : $_SERVER['HTTP_HOST']);

		self::$secure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on');

		require(XenForo_Autoloader::getInstance()->autoloaderClassToFile('Lgpl_utf8'));

		$this->_configDir = $configDir;
		$this->_rootDir = $rootDir;
		$this->addLazyLoader('requestPaths', array($this, 'loadRequestPaths'));

		if ($loadDefaultData)
		{
			$this->loadDefaultData();
		}

		$this->_initialized = true;
	}

	/**
	 * Loads the default data for the application (config, DB, options, etc).
	 */
	public function loadDefaultData()
	{
		$config = $this->loadConfig();
		self::set('config', $config);
		self::setDebugMode($config->debug);
		self::$jsVersion = substr(md5(self::$versionId . $config->jsVersion), 0, 8);
		self::$externalDataPath = (string)$config->externalDataPath;

		$this->addLazyLoader('db', array($this, 'loadDb'), $config->db);
		$this->addLazyLoader('cache', array($this, 'loadCache'), $config->cache);
		$this->addLazyLoader('options', array($this, 'loadOptions'));
		$this->addLazyLoader('simpleCache', array($this, 'loadSimpleCache'));
	}

	/**
	* Helper function to initialize the application.
	*
	* @param string Path to application configuration directory. See {@link $_configDir}.
	* @param string Path to application root directory. See {@link $_rootDir}.
	* @param boolean True to load default data (config, DB, etc)
	*/
	public static function initialize($configDir = '.', $rootDir = '.', $loadDefaultData = true)
	{
		self::setClassName(__CLASS__);
		self::getInstance()->beginApplication($configDir, $rootDir, $loadDefaultData);
	}

	/**
	 * Handler for set_error_handler to convert notices, warnings, and other errors
	 * into exceptions.
	 *
	 * @param integer $errorType Type of error (one of the E_* constants)
	 * @param string $errorString
	 * @param string $file
	 * @param integer $line
	 */
	public static function handlePhpError($errorType, $errorString, $file, $line)
	{
		if (!self::$_handlePhpError)
		{
			return false;
		}

		if ($errorType & error_reporting())
		{
			throw new ErrorException($errorString, 0, $errorType, $file, $line);
		}
	}

	/**
	 * Disables our PHP error handler, in favor of a previously registered one
	 * (or the default PHP error handler).
	 */
	public static function disablePhpErrorHandler()
	{
		self::$_handlePhpError = false;
	}

	/**
	 * Enables our PHP error handler.
	 */
	public static function enablePhpErrorHandler()
	{
		self::$_handlePhpError = true;
	}

	/**
	 * Default exception handler.
	 *
	 * @param Exception $e
	 */
	public static function handleException(Exception $e)
	{
		XenForo_Error::logException($e);
		XenForo_Error::unexpectedException($e);
	}

	/**
	 * Returns true if the application is in debug mode.
	 *
	 * @return boolean
	 */
	public static function debugMode()
	{
		return self::$_debug;
	}

	/**
	 * Sets the debug mode value.
	 *
	 * @param boolean $debug
	 */
	public static function setDebugMode($debug)
	{
		self::$_debug = (boolean)$debug;

		if (self::$_debug)
		{
			@ini_set('display_errors', true);
		}
	}

	/**
	 * Determines whether we should try to write to the development files.
	 *
	 * @return boolean
	 */
	public static function canWriteDevelopmentFiles()
	{
		return (self::debugMode() && XenForo_Application::get('config')->development->directory);
	}

	/**
	 * Resolves dynamic, run time inheritance for the specified class.
	 * The classes to be loaded for this base class are grabbed via the event.
	 * These classes must inherit from from XFCP_x, which is a non-existant
	 * class that is dynamically created, inheriting from the correct class
	 * as needed.
	 *
	 * If a fake base is needed when the base class doesn't exist, and there
	 * are no classes extending it, false will still be returned! This prevents
	 * an unnecessary eval.
	 *
	 * @param string $class Name of class
	 * @param string $type Type of class (for determining event to fire)
	 * @param string|false $fakeBase If the specified class doesn't exist, an alternative base can be specified
	 *
	 * @return false|string False or name of class to instantiate
	 */
	public static function resolveDynamicClass($class, $type, $fakeBase = false)
	{
		if (!XenForo_Application::autoload($class))
		{
			if ($fakeBase)
			{
				$fakeNeeded = true;
			}
			else
			{
				return false;
			}
		}
		else
		{
			$fakeNeeded = false;
		}

		if (!empty(self::$_classCache[$class]))
		{
			return self::$_classCache[$class];
		}

		$createClass = $class;

		$extend = array();
		XenForo_CodeEvent::fire('load_class_' . $type, array($class, &$extend));

		if ($fakeNeeded)
		{
			if (!$extend)
			{
				return false;
			}

			eval('class ' . $class . ' extends ' . $fakeBase . ' {}');
		}

		if ($extend)
		{
			try
			{
				foreach ($extend AS $dynamicClass)
				{
					// XenForo Class Proxy, in case you're wondering
					$proxyClass = 'XFCP_' . $dynamicClass;
					eval('class ' . $proxyClass . ' extends ' . $createClass . ' {}');
					XenForo_Application::autoload($dynamicClass);
					$createClass = $dynamicClass;
				}
			}
			catch (Exception $e)
			{
				self::$_classCache[$class] = $class;
				throw $e;
			}
		}

		self::$_classCache[$class] = $createClass;
		return $createClass;
	}

	/**
	* Gets the path to the configuration directory.
	*
	* @return string
	*/
	public function getConfigDir()
	{
		return $this->_configDir;
	}

	/**
	* Gets the path to the application root directory.
	*
	* @return string
	*/
	public function getRootDir()
	{
		return $this->_rootDir;
	}

	/**
	* Load the configuration file. Mixes in over top of the default values. Provided
	* a default is specified in {@link loadDefaultConfig}, all elements available
	* to the config will always be defined. Non-default elements may still be defined
	* in the loaded configuration.
	*
	* @return Zend_Config
	*/
	public function loadConfig()
	{
		if (file_exists($this->_configDir . '/config.php'))
		{
			$defaultConfig = $this->loadDefaultConfig();

			$config = array();
			require($this->_configDir . '/config.php');

			$outputConfig = new Zend_Config(array(), true);
			$outputConfig->merge($defaultConfig)
			             ->merge(new Zend_Config($config))
			             ->setReadOnly();
			return $outputConfig;
		}
		else
		{
			if (XenForo_Model::create('XenForo_Install_Model_Install')->isInstalled())
			{
				// TODO: ideally, we want a better way to display a fatal error like this
				echo "Couldn't load library/config.php file.";
				exit;
			}
			else
			{
				header('Location: install/index.php');
				exit;
			}
		}
	}

	/**
	* Load the default configuration. User-specified versions will override this.
	*
	* @return Zend_Config
	*/
	public function loadDefaultConfig()
	{
		return new Zend_Config(array(
			'db' => array(
				'adapter' => 'mysqli',
				'host' => 'localhost',
				'port' => '3306',
				'username' => '',
				'password' => '',
				'dbname' => ''
			),
			'cache' => array(
				'enabled' => false,
				'frontend' => 'core',
				'frontendOptions' => array(
					'caching' => true,
					'cache_id_prefix' => 'xf_'
				),
				'backend' => 'file',
				'backendOptions' => array(
					'file_name_prefix' => 'xf_'
				)
			),
			'debug' => false,
			'enableListeners' => true,
			'development' => array(
				'directory' => '', // relative to the configuration directory
				'default_addon' => ''
			),
			'superAdmins' => '1',
			'globalSalt' => '4984f8a5687709e87712c8b82164faf9',
			'jsVersion' => '',
			'cookie' => array(
				'prefix' => 'xf_',
				'path' => '/',
				'domain' => ''
			),
			'enableMail' => true,
			'internalDataPath' => 'internal_data',
			'externalDataPath' => 'data',
			'checkVersion' => true,
			'enableGzip' => true,
			'enableContentLength' => true,
		));
	}

	/**
	* Load the database object.
	*
	* @param Zend_Configuration Configuration to use
	*
	* @return Zend_Db_Adapter_Abstract
	*/
	public function loadDb(Zend_Config $dbConfig)
	{
		$db = Zend_Db::factory($dbConfig->adapter,
			array(
				'host' => $dbConfig->host,
				'port' => $dbConfig->port,
				'username' => $dbConfig->username,
				'password' => $dbConfig->password,
				'dbname' => $dbConfig->dbname,
				'charset' => 'utf8'
			)
		);

		switch (get_class($db))
		{
			case 'Zend_Db_Adapter_Mysqli':
				$db->getConnection()->query("SET @@session.sql_mode='STRICT_ALL_TABLES'");
				break;
			case 'Zend_Db_Adapter_Pdo_Mysql':
				$db->getConnection()->exec("SET @@session.sql_mode='STRICT_ALL_TABLES'");
				break;
		}

		if (self::debugMode())
		{
			$db->setProfiler(true);
		}

		return $db;
	}

	/**
	* Load the cache object.
	*
	* @param Zend_Configuration Configuration to use
	*
	* @return Zend_Cache_Core|Zend_Cache_Frontend|false
	*/
	public function loadCache(Zend_Config $cacheConfig)
	{
		if (!$cacheConfig->enabled)
		{
			return false;
		}

		return Zend_Cache::factory(
		    $cacheConfig->frontend,
		    $cacheConfig->backend,
		    $cacheConfig->frontendOptions->toArray(),
		    $cacheConfig->backendOptions->toArray()
		);
	}

	/**
	* Loads the list of options from the cache if possible and rebuilds
	* it from the DB if necessary.
	*
	* @return XenForo_Options
	*/
	public function loadOptions()
	{
		$options = XenForo_Model::create('XenForo_Model_DataRegistry')->get('options');
		if (!is_array($options))
		{
			$options = XenForo_Model::create('XenForo_Model_Option')->rebuildOptionCache();
		}

		$optionsObj = new XenForo_Options($options);
		self::setDefaultsFromOptions($optionsObj);

		return $optionsObj;
	}

	/**
	 * Setup necessary system defaults based on the options.
	 *
	 * @param XenForo_Options $options
	 */
	public static function setDefaultsFromOptions(XenForo_Options $options)
	{
		if ($options->useFriendlyUrls)
		{
			XenForo_Link::useFriendlyUrls(true);
		}
	}

	/**
	 * Loads the request paths from a default request object.
	 *
	 * @return array
	 */
	public function loadRequestPaths()
	{
		return self::getRequestPaths(new Zend_Controller_Request_Http());
	}

	/**
	 * Gets the request paths from the specified request object.
	 *
	 * @param Zend_Controller_Request_Http $request
	 *
	 * @return array Keys: basePath, host, protocol, fullBasePath, requestUri
	 */
	public static function getRequestPaths(Zend_Controller_Request_Http $request)
	{
		$basePath = $request->getBasePath();
		if ($basePath === '' || substr($basePath, -1) != '/')
		{
			$basePath .= '/';
		}

		$host = $request->getServer('HTTP_HOST');
		if (!$host)
		{
			$host = $request->getServer('SERVER_NAME');
			$serverPort = intval($request->getServer('SERVER_PORT'));
			if ($serverPort && $serverPort != 80 && $serverPort != 443)
			{
				$host .= ':' . $serverPort;
			}
		}

		$protocol = ($request->isSecure() ? 'https' : 'http');

		$requestUri = $request->getRequestUri();

		return array(
			'basePath' => $basePath,
			'host' => $host,
			'protocol' => $protocol,
			'fullBasePath' => $protocol . '://' . $host . $basePath,
			'requestUri' => $requestUri,
			'fullUri' => $protocol . '://' . $host . $requestUri
		);
	}

	/**
	* Add a lazy loader to the application registry. This lazy loader callback
	* will be called if the specified index is not in the registry.
	*
	* The 3rd argument and on will be passed to the lazy loader callback.
	*
	* @param string   Index to assign lazy loader to
	* @param callback Callback to call when triggered
	*/
	public function addLazyLoader($index, $callback)
	{
		if (!is_callable($callback, true))
		{
			throw new Zend_Exception("Invalid callback for lazy loading '$index'");
		}

		$arguments = array_slice(func_get_args(), 2);

		$this->_lazyLoaders[$index] = array($callback, $arguments);
	}

	/**
	* Removes the lazy loader from the specified index.
	*
	* @param string Index to remove from
	*
	* @return boolean
	*/
	public function removeLazyLoader($index)
	{
		if (isset($this->_lazyLoaders[$index]))
		{
			unset($this->_lazyLoaders[$index]);
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Loads simple cache data from the source.
	 *
	 * @return array
	 */
	public function loadSimpleCache()
	{
		$return = XenForo_Model::create('XenForo_Model_DataRegistry')->get('simpleCache');
		return (is_array($return) ? $return : array());
	}

	/**
	 * Gets the specified simple cache data. The simple cache is for data that you want
	 * available on on pages, but don't need to special rebuild behaviors for.
	 *
	 * @param string $key
	 *
	 * @return mixed|false False if not in the cache
	 */
	public static function getSimpleCacheData($key)
	{
		$cache = self::get('simpleCache');
		return (isset($cache[$key]) ? $cache[$key] : false);
	}

	/**
	 * Sets the specified simple cache data. This data will be persisted over pages
	 * indefinitely. Values of false will remove the cache data.
	 *
	 * @param string $key
	 * @param mixed $value If false, the specified cache key is removed
	 */
	public static function setSimpleCacheData($key, $value)
	{
		$cache = self::get('simpleCache');
		if ($value === false)
		{
			unset($cache[$key]);
		}
		else
		{
			$cache[$key] = $value;
		}

		XenForo_Model::create('XenForo_Model_DataRegistry')->set('simpleCache', $cache);
		self::set('simpleCache', $cache);
	}

	/**
	* Execute lazy loader for an index if there is one. The loaded data is returned
	* via a reference parameter, not the return value of the method. The return
	* value is true only if the lazy loader was executed.
	*
	* Once called, the data is set to the registry and the lazy loader is removed.
	*
	* @param string Index to lazy load
	* @param mixed  By ref; data returned by lazy loader
	*
	* @return boolean True if a lazy loader was called
	*/
	public function lazyLoad($index, &$return)
	{
		if (isset($this->_lazyLoaders[$index]))
		{
			$lazyLoader = $this->_lazyLoaders[$index];

			$return = call_user_func_array($lazyLoader[0], $lazyLoader[1]);

			$this->offsetSet($index, $return);
			$this->removeLazyLoader($index);

			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	* getter method, basically same as offsetGet().
	*
	* This method can be called from an object of type Zend_Registry, or it
	* can be called statically.  In the latter case, it uses the default
	* static instance stored in the class.
	*
	* @param string $index - get the value associated with $index
	* @return mixed
	* @throws Zend_Exception if no entry is registerd for $index.
	*/
	public static function get($index)
	{
		$instance = self::getInstance();

		if (!$instance->offsetExists($index))
		{
			if ($instance->lazyLoad($index, $return))
			{
				return $return;
			}
			else
			{
				throw new Zend_Exception("No entry is registered for key '$index'");
			}
		}

		return $instance->offsetGet($index);
	}

	/**
	 * Attempts to get the specified index. If it cannot be found, the callback
	 * is called and the result from the callback is set into the registry for that
	 * index.
	 *
	 * @param string $index Index to look for
	 * @param callback $callback Callback function to call if not found
	 * @param array $args Arguments to pass to callback
	 *
	 * @return mixed
	 */
	public static function getWithFallback($index, $callback, array $args = array())
	{
		if (self::isRegistered($index))
		{
			return self::get($index);
		}
		else
		{
			$result = call_user_func_array($callback, $args);
			self::set($index, $result);
			return $result;
		}
	}

	/**
	* Helper method to autoload a class. Could simply call the autoloader directly
	* but this method is recommended to reduce dependencies.
	*
	* @param string $class Class to load
	*
	* @return boolean
	*/
	public static function autoload($class)
	{
		return XenForo_Autoloader::getInstance()->autoload($class);
	}

	/**
	* Helper method to remove the result of magic_quotes_gpc being applied to the
	* input super globals
	*
	* @param array The array to have slashes stripped, this is passed by reference
	* @param integer Recursion depth to prevent malicious use
	*/
	public static function undoMagicQuotes(&$array, $depth = 0)
	{
		if ($depth > 10 || !is_array($array))
		{
			return;
		}

		foreach ($array AS $key => $value)
		{
			if (is_array($value))
			{
				self::undoMagicQuotes($array[$key], $depth + 1);
			}
			else
			{
				$array[$key] = stripslashes($value);
			}

			if (is_string($key))
			{
				$new_key = stripslashes($key);
				if ($new_key != $key)
				{
					$array[$new_key] = $array[$key];
					unset($array[$key]);
				}
			}
		}
	}

	/**
	 * Gzips the given content if the browser supports it.
	 *
	 * @param string $content Content to gzip; this will be modified if necessary
	 *
	 * @return array List of HTTP headers to add
	 */
	public static function gzipContentIfSupported(&$content)
	{
		if (@ini_get('zlib.output_compression'))
		{
			return array();
		}

		if (!function_exists('gzencode') || empty($_SERVER['HTTP_ACCEPT_ENCODING']))
		{
			return array();
		}

		if (!is_string($content))
		{
			return array();
		}

		if (!self::get('config')->enableGzip)
		{
			return array();
		}

		$headers = array();

		if (strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false)
		{
			$headers[] = array('Content-Encoding', 'gzip', true);
			$headers[] = array('Vary', 'Accept-Encoding', false);

			$content = gzencode($content, 1);
		}

		return $headers;
	}

	/**
	 * Returns a version of the input $data that contains only the array keys defined in $keys
	 *
	 * Example: arrayFilterKeys(array('a' => 1, 'b' => 2, 'c' => 3), array('b', 'c'))
	 * Returns: array('b' => 2, 'c' => 3)
	 *
	 * @param array $data
	 * @param array $keys
	 *
	 * @return array $data
	 */
	public static function arrayFilterKeys(array $data, array $keys)
	{
		// this version will not warn on undefined indexes: return array_intersect_key($data, array_flip($keys));

		$array = array();

		foreach ($keys AS $key)
		{
			$array[$key] = $data[$key];
		}

		return $array;
	}

	/**
	 * This is a simplified version of a function similar to array_merge_recursive. It is
	 * designed to recursively merge associative arrays (maps). If each array shares a key,
	 * that key is recursed and the child keys are merged.
	 *
	 * This function does not handle merging of non-associative arrays (numeric keys) as
	 * a special case.
	 *
	 * More than 2 arguments may be passed if desired.
	 *
	 * @param array $first
	 * @param array $second
	 *
	 * @return array
	 */
	public static function mapMerge(array $first, array $second)
	{
		$s = microtime(true);
		$args = func_get_args();
		unset($args[0]);

		foreach ($args AS $arg)
		{
			if (!is_array($arg) || !$arg)
			{
				continue;
			}
			foreach ($arg AS $key => $value)
			{
				if (array_key_exists($key, $first) && is_array($value) && is_array($first[$key]))
				{
					$first[$key] = self::mapMerge($first[$key], $value);
				}
				else
				{
					$first[$key] = $value;
				}
			}
		}

		return $first;
	}

	/**
	 * Parses a query string (x=y&a=b&c[]=d) into a structured array format.
	 *
	 * @param string $string
	 *
	 * @return array
	 */
	public static function parseQueryString($string)
	{
		parse_str($string, $output);
		if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc())
		{
			XenForo_Application::undoMagicQuotes($output);
		}

		return $output;
	}

	/**
	 * Generates a psuedo-random string of the specified length.
	 *
	 * @param integer $length
	 *
	 * @return string
	 */
	public static function generateRandomString($length)
	{
		while (strlen(self::$_randomData) < $length)
		{
			// openssl_random_pseudo_bytes is *ridiculously* slow on windows
			if (function_exists('openssl_random_pseudo_bytes') && substr(PHP_OS, 0, 3) != 'WIN')
			{
				self::$_randomData .= bin2hex(openssl_random_pseudo_bytes(max($length, 1024) / 2));
			}
			else
			{
				self::$_randomData .= md5(uniqid(mt_rand(), true));
			}
		}

		$return = substr(self::$_randomData, 0, $length);
		self::$_randomData = substr(self::$_randomData, $length);

		return $return;
	}
}