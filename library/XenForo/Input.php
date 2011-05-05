<?php

/**
* Input Filtering Class
*
* @package XenForo_Core
*/
class XenForo_Input
{
	const STRING     = 'string';
	const NUM        = 'num';
	const UNUM       = 'unum';
	const INT        = 'int';
	const UINT       = 'uint';
	const FLOAT      = 'float';
	const BINARY     = 'binary';
	const ARRAY_SIMPLE = 'array_simple';
	const JSON_ARRAY = 'json_array';
	const DATE_TIME       = 'dateTime';

	/**
	* Default values for the input types
	*
	* @var array
	*/
	protected static $_DEFAULTS = array(
		self::STRING    => '',
		self::NUM       => 0,
		self::UNUM      => 0,
		self::INT       => 0,
		self::UINT      => 0,
		self::FLOAT     => 0.0,
		self::BINARY    => '',
		self::ARRAY_SIMPLE => array(),
		self::JSON_ARRAY => array(),
		self::DATE_TIME => 0
	);

	/**
	 * Map of from-to pairs of things to manipulate in strings.
	 *
	 * @var array
	 */
	protected static $_strClean = array(
		"\r" => '', // Strip carriage returns, because jQuery does so in .val()
		"\0" => '',
		"\xC2\xA0" => ' ',
	);

	/**
	* Cached cleaned variables. Key is the variable name as it was pulled
	*
	* @var array
	*/
	protected $_cleanedVariables = array();

	/**
	* The request object that variables will be read from. May be null
	* if source data is populated instead
	*
	* @var Zend_Controller_Request_Http|null
	*/
	protected $_request = null;

	/**
	 * Alternative to the request, data can come from an array.
	 *
	 * @var array|null
	 */
	protected $_sourceData = null;

	/**
	* Constructor
	*
	* @param Zend_Controller_Request_Http|array $source Source of input
	*/
	public function __construct($source)
	{
		if ($source instanceof Zend_Controller_Request_Http)
		{
			$this->_request = $source;
		}
		else if (is_array($source))
		{
			$this->_sourceData = $source;
		}
		else
		{
			throw new XenForo_Exception('Must pass an array or Zend_Controller_Request_Http object to XenForo_Input');
		}
	}

	/**
	* Filter an individual item
	*
	* @param string $variableName Name of the input variable
	* @param mixed $filterData Filter information, can be a single constant or an array containing a filter and options
	* @param array $options Filtering options
	*
	* @return mixed Value after being cleaned
	*/
	public function filterSingle($variableName, $filterData, array $options = array())
	{
		$filters = array();

		if (is_string($filterData))
		{
			$filters = array($filterData);
		}
		else if (is_array($filterData) && isset($filterData[0]))
		{
			$filters = is_array($filterData[0]) ? $filterData[0] : array($filterData[0]);

			if (isset($filterData[1]) && is_array($filterData[1]))
			{
				$options = array_merge($options, $filterData[1]);
			}
			else
			{
				unset($filterData[0]);
				$options = array_merge($options, $filterData);
			}
		}
		else
		{
			throw new XenForo_Exception("Invalid data passed to " . __CLASS__ . "::" . __METHOD__);
		}

		$firstFilter = reset($filters);

		if (isset($options['default']))
		{
			$defaultData = $options['default'];
		}
		else if (array_key_exists($firstFilter, self::$_DEFAULTS))
		{
			$defaultData = self::$_DEFAULTS[$firstFilter];
		}
		else
		{
			$defaultData = null;
		}

		if ($this->_request)
		{
			$data = $this->_request->getParam($variableName);
		}
		else
		{
			$data = (isset($this->_sourceData[$variableName]) ? $this->_sourceData[$variableName] : null);
		}

		if ($data === null)
		{
			$data = $defaultData;
		}

		foreach ($filters AS $filterName)
		{
			if (isset($options['array']))
			{
				if (is_array($data))
				{
					foreach (array_keys($data) AS $key)
					{
						$data[$key] = self::_doClean($filterName, $options, $data[$key], $defaultData);
					}
				}
				else
				{
					$data = array();
					break;
				}
			}
			else
			{
				$data = self::_doClean($filterName, $options, $data, $defaultData);
			}
		}

		$this->_cleanedVariables[$variableName] = $data;
		return $data;
	}

	protected static function _doClean($filterName, array $filterOptions, $data, $defaultData)
	{
		switch ($filterName)
		{
			case self::STRING:
				$data = strval($data);
				if (!utf8_check($data))
				{
					$data = $defaultData;
				}

				$data = strtr($data, self::$_strClean);

				if (empty($filterOptions['noTrim']))
				{
					$data = utf8_trim($data);
				}
			break;

			case self::NUM:
				$data = strval($data) + 0;
			break;

			case self::UNUM:
				$data = strval($data) + 0;
				$data = ($data < 0) ? $defaultData : $data;
			break;

			case self::INT:
				$data = intval($data);
			break;

			case self::UINT:
				$data = ($data = intval($data)) < 0 ? $defaultData : $data;
			break;

			case self::FLOAT:
				$data = floatval($data);
			break;

			case self::BINARY:
				$data = strval($data);
			break;

			case self::ARRAY_SIMPLE:
				if (!is_array($data))
				{
					$data = $defaultData;
				}
			break;

			case self::JSON_ARRAY:
				if (is_string($data))
				{
					$data = json_decode($data, true);
				}
				if (!is_array($data))
				{
					$data = $defaultData;
				}
			break;

			case self::DATE_TIME:
				if (!$data)
				{
					$data = 0;
				}
				else if (is_string($data))
				{
					$data = trim($data);

					if ($data === strval(intval($data)))
					{
						// data looks like an int, treat as timestamp
						$data = intval($data);
					}
					else
					{
						$tz = (XenForo_Visitor::hasInstance() ? XenForo_Locale::getDefaultTimeZone() : null);

						try
						{
							$date = new DateTime($data, $tz);
							if (!empty($filterOptions['dayEnd']))
							{
								$date->setTime(23, 59, 59);
							}

							$data = $date->format('U');
						}
						catch (Exception $e)
						{
							$data = 0;
						}
					}
				}

				if (!is_int($data))
				{
					$data = intval($data);
				}
			break;

			default:
				if ($filterName instanceof Zend_Validate_Interface)
				{
					if ($filterName->isValid($data) === false)
					{
						$data = $defaultData;
					}
				}
				else
				{
					throw new XenForo_Exception("Unknown input type in " . __CLASS__ . "::" . __METHOD__);
				}
		}

		return $data;
	}

	/**
	 * Cleans invalid characters out of a string, such as nulls, nbsp, \r, etc.
	 * Characters may not strictly be invalid, but can cause confusion/bugs.
	 *
	 * @param string $string
	 *
	 * @return string
	 */
	public static function cleanString($string)
	{
		return strtr(strval($string), self::$_strClean);
	}

	/**
	* Filter an array of items
	*
	* @param array	Key-value pairs with the value being in the format expected by filterSingle. {@link XenForo_Input::filterSingle()}
	*
	* @return array key-value pairs with the cleaned value
	*/
	public function filter(array $filters)
	{
		$data = array();
		foreach ($filters AS $variableName => $filterData)
		{
			$data[$variableName] = $this->filterSingle($variableName, $filterData);
		}

		return $data;
	}

	/**
	 * Statically filters a piece of data as the requested type.
	 *
	 * @param mixed $data
	 * @param constant $filterName
	 * @param array $options
	 *
	 * @return mixed
	 */
	public static function rawFilter($data, $filterName, array $options = array())
	{
		return self::_doClean($filterName, $options, $data, self::$_DEFAULTS[$filterName]);
	}

	/**
	 * Returns true if the given key was included in the request at all.
	 *
	 * @param string $key
	 *
	 * @return boolean
	 */
	public function inRequest($key)
	{
		if ($this->_request)
		{
			return isset($this->_request->$key);
		}
		else
		{
			return isset($this->_sourceData[$key]);
		}
	}

	/**
	 * Gets all input.
	 *
	 * @return array
	 */
	public function getInput()
	{
		return $this->_request->getParams();
	}

	public function __get($key)
	{
		if (array_key_exists($key, $this->_cleanedVariables))
		{
			return $this->_cleanedVariables[$key];
		}
	}

	public function __isset($key)
	{
		return array_key_exists($key, $this->_cleanedVariables);
	}
}
