<?php

/**
 * XenForo options accessor class.
 *
 * @package XenForo_Options
 */
class XenForo_Options
{
	/**
	 * Collection of options.
	 *
	 * @var array
	 */
	protected $_options = array();

	/**
	 * Constructor. Sets up the accessor using the provided options.
	 *
	 * @param array $options Collection of options. Keys represent option names.
	 */
	public function __construct(array $options)
	{
		$this->setOptions($options);
	}

	/**
	 * Gets an option. If the option exists and is an array, then...
	 * 	* if no sub-option is specified but an $optionName key exists in the option, return the value for that key
	 *  * if no sub-option is specified and no $optionName key exists, return the whole option array
	 *  * if the sub-option === false, the entire option is returned, regardless of what keys exist
	 *  * if a sub-option is specified and the key exists, return the value for that key
	 *  * if a sub-option is specified and the key does not exist, return null
	 * If the option is not an array, then the value of the option is returned (provided no sub-option is specified).
	 * Otherwise, null is returned.
	 *
	 * @param string $optionName Name of the option
	 * @param null|false|string $subOption Sub-option. See above for usage.
	 *
	 * @return null|mixed Null if the option doesn't exist (see above) or the option's value.
	 */
	public function get($optionName, $subOption = null)
	{
		if (!isset($this->_options[$optionName]))
		{
			return null;
		}

		$option = $this->_options[$optionName];

		if (is_array($option))
		{
			if ($subOption === null)
			{
				return (isset($option[$optionName]) ? $option[$optionName] : $option);
			}
			else if ($subOption === false)
			{
				return $option;
			}
			else
			{
				return (isset($option[$subOption]) ? $option[$subOption] : null);
			}
		}
		else
		{
			return ($subOption === null ? $option : null);
		}
	}

	/**
	 * Gets all options in their raw form.
	 *
	 * @return array
	 */
	public function getOptions()
	{
		return $this->_options;
	}

	/**
	 * Sets the collection of options manually.
	 *
	 * @param array $options
	 */
	public function setOptions(array $options)
	{
		$this->_options = $options;
	}

	/**
	 * Magic getter for first-order options. This method cannot be used
	 * for getting a sub-option! You must use {@link get()} for that.
	 *
	 * This is equivalent to calling get() with no sub-option, which means
	 * the "main" sub-option will be returned (if applicable).
	 *
	 * @param string $option
	 *
	 * @return null|mixed
	 */
	public function __get($option)
	{
		return $this->get($option);
	}

	/**
	 * Returns true if the named option exists. Do not use this approach
	 * for sub-options!
	 *
	 * This is equivalent to calling get() with no sub-option, which means
	 * the "main" sub-option will be returned (if applicable).
	 *
	 * @param string $option
	 *
	 * @return boolean
	 */
	public function __isset($option)
	{
		return ($this->get($option) !== null);
	}

	/**
	 * Sets an option or a particular sub-option (first level array key).
	 *
	 * @param string $option
	 * @param mixed $subOption If $value is null, then this is treated as the value; otherwise, a specific array key to change
	 * @param mixed|null $value If null, ignored
	 */
	public function set($option, $subOption, $value = null)
	{
		if ($value === null)
		{
			$value = $subOption;
			$subOption = false;
		}

		if ($subOption === false)
		{
			$this->_options[$option] = $value;
		}
		else if (isset($this->_options[$option]) && is_array($this->_options[$option]))
		{
			$this->_options[$option][$subOption] = $value;
		}
		else
		{
			throw new XenForo_Exception('Tried to write sub-option to invalid/non-array option.');
		}
	}

	/**
	 * Magic set method. Only sets whole options.
	 *
	 * @param string $name
	 * @param mixed $value
	 */
	public function __set($name, $value)
	{
		$this->set($name, $value);
	}
}