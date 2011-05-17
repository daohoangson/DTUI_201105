<?php

/**
 * Abstract BB code to BB code formatter. This serves as a basis for other formatters
 * that want to make BB code translations and return the result as BB code.
 *
 * @package XenForo_BbCode
 */
abstract class XenForo_BbCode_Formatter_BbCode_Abstract extends XenForo_BbCode_Formatter_Base
{
	/**
	 * Controls whether text is censored before returning.
	 *
	 * @var boolean
	 */
	protected $_censorString = false;

	/**
	 * Callback that can be overriden in children. If specified, all tags will callback to this function.
	 *
	 * @var callback|null
	 */
	protected $_generalTagCallback = null;

	/**
	 * If specified, callbacks that override the named tag.
	 *
	 * @var array [tag] => callback (can have $this as string)
	 */
	protected $_overrideCallbacks = array();

	/**
	 * Controls whether or not the string is censored.
	 *
	 * @param string $value
	 */
	public function setCensoring($value)
	{
		$this->_censorString = $value;
	}

	/**
	 * Gets the list of valid BB code tags. This removes most behaviors.
	 *
	 * @see XenForo_BbCode_Formatter_Base::getTags()
	 */
	public function getTags()
	{
		if ($this->_tags !== null)
		{
			return $this->_tags;
		}

		if (is_array($this->_generalTagCallback) && $this->_generalTagCallback[0] == '$this')
		{
			$this->_generalTagCallback[0] = $this;
		}

		$tags = parent::getTags();
		foreach ($tags AS $tagName => &$tag)
		{
			unset($tag['replace'], $tag['callback'], $tag['trimLeadingLinesAfter']);
			if (!empty($this->_overrideCallbacks[$tagName]))
			{
				$override = $this->_overrideCallbacks[$tagName];
				if (is_array($override) && $override[0] == '$this')
				{
					$override[0] = $this;
				}

				$tag['callback'] = $override;
			}
			else if ($this->_generalTagCallback)
			{
				$tag['callback'] = $this->_generalTagCallback;
			}
		}

		return $tags;
	}

	/**
	 * Default, empty string filterer.
	 *
	 * @see XenForo_BbCode_Formatter_Base::filterString()
	 */
	public function filterString($string, array $rendererStates)
	{
		if ($this->_censorString)
		{
			$string = XenForo_Helper_String::censorString($string);
		}

		return $string;
	}
}