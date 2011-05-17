<?php

abstract class XenForo_Importer_Abstract
{
	/**
	 * @var Zend_Db_Adapter_Abstract
	 */
	protected $_db;

	/**
	 * @var XenForo_Model_Import
	 */
	protected $_importModel;

	/**
	 * Import session
	 *
	 * @var XenForo_ImportSession
	 */
	protected $_session;

	/**
	 * Calling controller
	 *
	 * @var XenForo_ControllerAdmin_Abstract
	 */
	protected $_controller;

	public static function getName()
	{
		throw new XenForo_Exception('The getName function must be overridden.');
	}

	abstract public function configure(XenForo_ControllerAdmin_Abstract $controller, array &$config);

	abstract public function getSteps();

	public function __construct()
	{
		$this->_db = XenForo_Application::get('db');
		$this->_importModel = XenForo_Model::create('XenForo_Model_Import');

		$this->_db->setProfiler(false); // this causes lots of memory usage in debug mode, so stop that
	}

	public function getImportCompleteMessages()
	{
		return array();

	}

	protected function _bootstrap(array $config) {}

	public function runStep(XenForo_ControllerAdmin_Abstract $controller, XenForo_ImportSession $session, $step, $start, array $options = array())
	{
		if (!$step || !method_exists($this, 'step' . $step))
		{
			throw new XenForo_Exception('Invalid step ' . $step);
		}

		ignore_user_abort(true);

		$this->_session = $session;
		$this->_controller = $controller;

		$this->_bootstrap($session->getConfig());

		$steps = $this->getSteps();
		if (isset($steps[$step]))
		{
			if (!$this->_importModel->canRunStep($step, $steps, $session->getRunSteps()))
			{
				throw new XenForo_Exception('Step ' . $step . ' cannot be run.');
			}
		}

		return $this->{'step' . $step}($start, $options);
	}

	public function configStep(XenForo_ControllerAdmin_Abstract $controller, XenForo_ImportSession $session, $step, array &$options)
	{
		if (!$step || !method_exists($this, 'configStep' . $step))
		{
			return false;
		}

		$this->_session = $session;
		$this->_controller = $controller;

		return $this->{'configStep' . $step}($options);
	}

	public function getKey()
	{
		return str_replace('XenForo_Importer_', '', get_class($this));
	}

	public function getStep($step)
	{
		$steps = $this->getSteps();
		return (isset($steps[$step]) ? $steps[$step] : false);
	}

	protected function _mapLookUp(array $map, $key, $default = null, $lowerKey = true)
	{
		if ($lowerKey)
		{
			$key = strtolower($key);
		}
		return (isset($map[$key]) ? $map[$key] : $default);
	}

	protected function _mapLookUpList(array $map, array $keys, $lowerKey = true)
	{
		$output = array();
		foreach ($keys AS $key)
		{
			if ($lowerKey)
			{
				$key = strtolower($key);
			}
			if (isset($map[$key]))
			{
				$output[] = $map[$key];
			}
		}

		return $output;
	}

	protected function _getProgressOutput($lastId, $maxId)
	{
		return XenForo_Locale::numberFormat(100 * $lastId / $maxId, 2) . '%';
	}

	/**
	 * Convert the given text to valid UTF-8
	 *
	 * @param string $string
	 * @param boolean $entities Convert &lt; (and other) entities back to < characters
	 *
	 * @return string
	 */
	protected function _convertToUtf8($string, $entities = null)
	{
		// note: assumes charset is ascii compatible
		if (preg_match('/[\x80-\xff]/', $string))
		{
			if (function_exists('iconv'))
			{
				$string = @iconv($this->_charset, 'utf-8//IGNORE', $string);
			}
			else if (function_exists('mb_convert_encoding'))
			{
				$string = mb_convert_encoding($string, 'utf-8', $this->_charset);
			}
		}

		$string = utf8_unhtml($string, $entities);

		return preg_replace('/[\xF0-\xF7].../', '', $string);
	}
}