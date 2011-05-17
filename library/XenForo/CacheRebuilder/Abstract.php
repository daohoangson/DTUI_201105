<?php

/**
 * Base class for cache/data rebuilders.
 *
 * @package XenForo_CacheRebuilder
 */
abstract class XenForo_CacheRebuilder_Abstract
{
	/**
	 * List of cache builders.
	 *
	 * @var array [key name] => class name
	 */
	public static $builders = array(
		'AdminTemplate' => 'XenForo_CacheRebuilder_AdminTemplate',
		'EmailTemplate' => 'XenForo_CacheRebuilder_EmailTemplate',
		'Forum' => 'XenForo_CacheRebuilder_Forum',
		'ImportAdminTemplate' => 'XenForo_CacheRebuilder_ImportAdminTemplate',
		'ImportEmailTemplate' => 'XenForo_CacheRebuilder_ImportEmailTemplate',
		'ImportMasterData' => 'XenForo_CacheRebuilder_ImportMasterData',
		'ImportPhrase' => 'XenForo_CacheRebuilder_ImportPhrase',
		'ImportTemplate' => 'XenForo_CacheRebuilder_ImportTemplate',
		'Phrase' => 'XenForo_CacheRebuilder_Phrase',
		'Permission' => 'XenForo_CacheRebuilder_Permission',
		'Poll' => 'XenForo_CacheRebuilder_Poll',
		'SearchIndex' => 'XenForo_CacheRebuilder_SearchIndex',
		'Template' => 'XenForo_CacheRebuilder_Template',
		'Thread' => 'XenForo_CacheRebuilder_Thread',
		'User' => 'XenForo_CacheRebuilder_User'
	);

	/**
	 * Key name used for this object.
	 *
	 * @var string
	 */
	protected $_keyName = '';

	/**
	 * Gets a message about the type of content being rebuilt.
	 * Likely depends on phrases existing.
	 *
	 * @return string|XenForo_Phrase
	 */
	abstract public function getRebuildMessage();

	/**
	 * Rebuilds the data as requested. If there is a large amount of data, it should
	 * only be partially rebuilt in each invocation.
	 *
	 * If true is returned, then the rebuild is done. Otherwise, an integer should be returned.
	 * This will be passed to the next call as the position.
	 *
	 * @param integer $position Position to start building from.
	 * @param array $options List of options. Can be modified and updated value will be passed to next call.
	 * @param string $detailedMessage A detailed message about the progress to return.
	 *
	 * @return integer|true
	 */
	abstract public function rebuild($position = 0, array &$options = array(), &$detailedMessage = '');

	/**
	 * Constructor.
	 *
	 * @param string$keyName
	 */
	public function __construct($keyName)
	{
		$this->_keyName = $keyName;

		@set_time_limit(0);
		ignore_user_abort(true);
		XenForo_Application::get('db')->setProfiler(false); // this can use a lot of memory
	}

	/**
	 * Whether or not an exit link should be shown. Only show this if the rebuild can be interrupted
	 * without doing bad things.
	 *
	 * @return boolean
	 */
	public function showExitLink()
	{
		return false;
	}

	/**
	 * Gets the key name.
	 *
	 * @return string
	 */
	public function getKeyName()
	{
		return $this->_keyName;
	}

	/**
	 * Gets the specified cache rebuilder.
	 *
	 * @param string $keyName
	 *
	 * @return XenForo_CacheRebuilder_Abstract
	 */
	public static function getCacheRebuilder($keyName)
	{
		if (!isset(self::$builders[$keyName]))
		{
			throw new XenForo_Exception('Invalid cache builder ' . $keyName . ' specified.');
		}

		$class = self::$builders[$keyName];
		return new $class($keyName);
	}

	/**
	 * Gets a controller response that reroutes to start rebuilding the caches.
	 * Admin CP only.
	 *
	 * @param XenForo_ControllerAdmin_Abstract $controller
	 * @param array $caches List of caches to build. Either array of strings, or array of pairs: [cache name, options]
	 * @param string|null $redirect URL to redirect to; null to use referrer
	 *
	 * @return XenForo_ControllerResponse_Reroute
	 */
	public static function getRebuilderResponse(XenForo_ControllerAdmin_Abstract $controller, $caches, $redirect = null)
	{
		$controller->getRequest()->setParam('caches', $caches);
		if ($redirect !== null)
		{
			$controller->getRequest()->setParam('redirect', $redirect);
		}

		return $controller->responseReroute('XenForo_ControllerAdmin_Tools', 'cacheRebuild');
	}
}