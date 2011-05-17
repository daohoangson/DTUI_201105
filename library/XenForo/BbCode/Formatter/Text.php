<?php

/**
 * BB code formatter that follows the formatting of the text as plain text.
 *
 * @package XenForo_BbCode
 */
class XenForo_BbCode_Formatter_Text extends XenForo_BbCode_Formatter_Base
{
	protected $_simpleReplacements = array(
		'left' => "%s\n",
		'center' => "%s\n",
		'right' => "%s\n"
	);

	protected $_advancedReplacements = array(
		'quote' => array('$this', 'handleTagQuote'),
		'img' => array('$this', 'handleTagImg'),
		'media' => array('$this', 'handleTagMedia'),
		'attach' => array('$this', 'handleTagAttach'),
		'list' => array('$this', 'handleTagList')
	);

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

		$callback = array($this, 'handleTag');

		$tags = parent::getTags();
		foreach ($tags AS $tagName => &$tag)
		{
			unset($tag['replace'], $tag['callback']);
			$tag['callback'] = $callback;
		}

		return $tags;
	}

	public function filterString($string, array $rendererStates)
	{
		$string = XenForo_Helper_String::censorString($string);

		return $string;
	}

	public function handleTag(array $tag, array $rendererStates)
	{
		$tagName = $tag['tag'];

		if (isset($this->_advancedReplacements[$tagName]))
		{
			$callback = $this->_advancedReplacements[$tagName];
			if (is_array($callback) && $callback[0] == '$this')
			{
				$callback[0] = $this;
			}

			return call_user_func($callback, $tag, $rendererStates);
		}

		$output = $this->renderSubTree($tag['children'], $rendererStates);

		if (isset($this->_simpleReplacements[$tagName]))
		{
			$output = sprintf($this->_simpleReplacements[$tagName], $output);
		}

		return $output;
	}

	public function handleTagQuote(array $tag, array $rendererStates)
	{
		$output = $this->renderSubTree($tag['children'], $rendererStates);

		return "\n----------\n" . trim($output) . "\n----------\n";
	}

	public function handleTagImg(array $tag, array $rendererStates)
	{
		return '[IMG]';
	}

	public function handleTagMedia(array $tag, array $rendererStates)
	{
		return '[MEDIA]';
	}

	public function handleTagAttach(array $tag, array $rendererStates)
	{
		return '[ATTACH]';
	}

	public function handleTagList(array $tag, array $rendererStates)
	{
		$bullets = explode('[*]', trim($this->renderSubTree($tag['children'], $rendererStates)));

		$output = '';
		foreach ($bullets AS $bullet)
		{
			$bullet = trim($bullet);
			if ($bullet !== '')
			{
				$output .= $bullet . "\n";
			}
		}

		return $output;
	}
}