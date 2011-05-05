<?php

/**
 * BB code to BB code formatter that can strip quotes or all BB codes.
 *
 * @package XenForo_BbCode
 */
class XenForo_BbCode_Formatter_BbCode_Strip extends XenForo_BbCode_Formatter_BbCode_Abstract
{
	protected $_generalTagCallback = array('$this', 'handleTag');

	protected $_overrideCallbacks = array(
		'quote' => array('$this', 'handleQuoteTag'),
		'list' => array('$this', 'handleListTag')
	);

	protected $_skipTags = array('media', 'attach');

	/**
	 * The maximum quote depth allowed. -1 means unlimited.
	 *
	 * @var integer
	 */
	protected $_maxQuoteDepth = -1;

	/**
	 * Controls whether all tags will be stripped.
	 *
	 * @var boolean
	 */
	protected $_stripAllBbCode = false;

	/**
	 * Sets the maximum quote depth.
	 *
	 * @param integer $max
	 *
	 * @return $this Fluent interface
	 */
	public function setMaxQuoteDepth($max)
	{
		$this->_maxQuoteDepth = intval($max);

		return $this;
	}

	/**
	 * Sets the value for stripping non-quote tags.
	 *
	 * @param boolean $value
	 * @param integer|null $maxQuoteDepth
	 *
	 * @return $this Fluent interface
	 */
	public function stripAllBbCode($value, $maxQuoteDepth = null)
	{
		$this->_stripAllBbCode = $value;
		if ($maxQuoteDepth !== null)
		{
			$this->_maxQuoteDepth = $maxQuoteDepth;
		}

		return $this;
	}

	/**
	 * General purpose tag handler.
	 *
	 * @param array $tag
	 * @param array $rendererStates
	 *
	 * @return string
	 */
	public function handleTag(array $tag, array $rendererStates)
	{
		if ($this->_stripAllBbCode)
		{
			if (in_array($tag['tag'], $this->_skipTags))
			{
				return '';
			}

			return $this->renderSubTree($tag['children'], $rendererStates);
		}
		else
		{
			return $this->renderTagUnparsed($tag, $rendererStates);
		}
	}

	public function handleListTag(array $tag, array $rendererStates)
	{
		if ($this->_stripAllBbCode)
		{
			$rendered = $this->renderSubTree($tag['children'], $rendererStates);
			return str_replace('[*]', '', $rendered);
		}
		else
		{
			return $this->renderTagUnparsed($tag, $rendererStates);
		}
	}

	/**
	 * Deals with stripping nested quote tags.
	 *
	 * @param array $tag
	 * @param array $rendererStates

	 * @return string
	 */
	public function handleQuoteTag(array $tag, array $rendererStates)
	{
		if (empty($rendererStates['quoteDepth']))
		{
			$rendererStates['quoteDepth'] = 1;
		}
		else
		{
			$rendererStates['quoteDepth']++;
		}

		if ($this->_maxQuoteDepth > -1 && $rendererStates['quoteDepth'] > $this->_maxQuoteDepth)
		{
			return '';
		}

		if (!empty($tag['original']) && is_array($tag['original']))
		{
			list($prepend, $append) = $tag['original'];
		}
		else
		{
			$prepend = '';
			$append = '';
		}

		if ($rendererStates['quoteDepth'] == $this->_maxQuoteDepth)
		{
			// at the edge of the quote, so we want to ltrim whatever comes after
			foreach ($tag['children'] AS $key => $child)
			{
				if (is_array($child) && !empty($child['tag']) && $child['tag'] == 'quote' && isset($tag['children'][$key + 1]))
				{
					$after =& $tag['children'][$key + 1];
					if (is_string($after))
					{
						$after = ltrim($after);
					}
				}
			}
		}

		if ($this->_stripAllBbCode)
		{
			$prepend = '';
			$append = '';
		}

		return $this->filterString($prepend, $rendererStates)
			. $this->renderSubTree($tag['children'], $rendererStates)
			. $this->filterString($append, $rendererStates);
	}
}