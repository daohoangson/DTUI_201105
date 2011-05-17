<?php

/**
 * BB code parser and renderer.
 *
 * @package XenForo_BbCode
 */
class XenForo_BbCode_Parser
{
	/**
	 * The text that is to be parsed/rendered.
	 *
	 * @var string
	 */
	protected $_text = '';

	/**
	 * The current position in the text. Parsing will only occur from this point on.
	 *
	 * @var integer
	 */
	protected $_position = 0;

	/**
	 * The parse tree. This will only be populated while parsing.
	 * Format: strings are literal text; arrays are tag openings, with keys:
	 * 		* tag - tag name (lower case)
	 * 		* option - value for the tag's option
	 * 		* children - array of more children (same format as the whole tree)
	 *
	 * @var array
	 */
	protected $_tree = array();

	/**
	 * Stack of currently open tags, with references to the parent's position in the tree.
	 *
	 * @var array
	 */
	protected $_tagStack = array();

	/**
	 * Reference to the current tree context.
	 *
	 * @var array Reference.
	 */
	protected $_context = null;

	/**
	 * Trailing text in the current tree context. Used to fold multiple text entries together.
	 *
	 * @var string
	 */
	protected $_trailingText = '';

	/**
	 * The current states the parser is in. Can include things like "plainText", etc.
	 *
	 * @var array
	 */
	protected $_parserStates = array();

	/**
	 * Contains information about how the tags, smilies, strings, etc should be formatted.
	 *
	 * @var XenForo_BbCode_Formatter_Base
	 */
	protected $_formatter = null;

	/**
	 * List of valid tags from the tag handler.
	 *
	 * @var array
	 */
	protected $_tagList = array();

	/**
	 * Constructor.
	 *
	 * @param XenForo_BbCode_Formatter_Base $formatter Formatting rules.
	 */
	public function __construct(XenForo_BbCode_Formatter_Base $formatter)
	{
		$this->_formatter = $formatter;
		$this->_tagList = $formatter->getTags();
	}

	/**
	 * Parse the specified text for BB codes and return the syntax tree.
	 *
	 * @param string $text
	 *
	 * @return array
	 */
	public function parse($text)
	{
		$this->_resetParser();

		$this->_text = $text;
		$length = strlen($text);

		while ($this->_position < $length)
		{
			$success = $this->_parseTag();
			if (!$success)
			{
				$this->_pushText(substr($this->_text, $this->_position));
				$this->_position = $length;
				break;
			}
		}

		$this->_mergeTrailingText();

		$tree = $this->_tree;

		$this->_tree = array();
		$this->_tagStack = array();
		unset($this->_context); $this->_context = null; // break reference

		return $tree;
	}

	protected function _resetParser()
	{
		$this->_text = '';
		$this->_position = 0;
		$this->_tree = array();
		$this->_tagStack = array();
		$this->_context =& $this->_tree;
		$this->_trailingText = '';
		$this->_parserStates = array(
			'plainText' => false,
		);
	}

	/**
	 * Looks for the next tag in the text.
	 *
	 * @return boolean False if no more valid tags can possibly found; true otherwise
	 */
	protected function _parseTag()
	{
		$tagStartPosition = strpos($this->_text, '[', $this->_position);
		if ($tagStartPosition === false)
		{
			return false;
		}

		$tagContentEndPosition = strpos($this->_text, ']', $tagStartPosition);
		if ($tagContentEndPosition === false)
		{
			return false;
		}

		$tagEndPosition = $tagContentEndPosition + 1;

		if ($tagStartPosition != $this->_position)
		{
			$this->_pushText(substr($this->_text, $this->_position, $tagStartPosition - $this->_position));
			$this->_position = $tagStartPosition;
		}

		if ($this->_text[$tagStartPosition + 1] == '/')
		{
			$success = $this->_parseTagClose($tagStartPosition, $tagEndPosition, $tagContentEndPosition);
		}
		else
		{
			$success = $this->_parseTagOpen($tagStartPosition, $tagEndPosition, $tagContentEndPosition);
		}

		if ($success)
		{
			// successful parse, eat the whole tag
			$this->_position = $tagEndPosition;
		}
		else
		{
			// didn't parse the tag properly, eat the first char ([) and try again
			$this->_pushText($this->_text[$tagStartPosition]);
			$this->_position++;
		}

		return true;
	}

	/**
	 * Parses a closing tag. The "[" has already been matched.
	 *
	 * @param integer $tagStartPosition Position of the "["
	 * @param integer $tagEndPosition Position after the "]". May be modified if necessary.
	 * @param integer $tagContentEndPosition Position of the "]"
	 *
	 * @return boolean False if no more valid tags can possibly found; true otherwise
	 */
	protected function _parseTagClose($tagStartPosition, &$tagEndPosition, $tagContentEndPosition)
	{
		$tagNamePosition = $tagStartPosition + 2;
		$tagName = substr($this->_text, $tagNamePosition, $tagContentEndPosition - $tagNamePosition);

		if (preg_match('/[^a-z0-9_-]/i', $tagName))
		{
			return false;
		}

		$originalText = substr($this->_text, $tagStartPosition, $tagEndPosition - $tagStartPosition);
		$this->_pushTagClose($tagName, $originalText);

		return true;
	}

	/**
	 * Parses an opening tag. The "[" has already been matched.
	 *
	 * @param integer $tagStartPosition Position of the "["
	 * @param integer $tagEndPosition Position after the "]". May be modified if necessary.
	 * @param integer $tagContentEndPosition Position of the "]"
	 *
	 * @return boolean False if no more valid tags can possibly found; true otherwise
	 */
	protected function _parseTagOpen($tagStartPosition, &$tagEndPosition, $tagContentEndPosition)
	{
		$tagNamePosition = $tagStartPosition + 1;

		$tagOptionPosition = strpos($this->_text, '=', $tagStartPosition);
		if ($tagOptionPosition !== false && $tagOptionPosition < $tagContentEndPosition)
		{
			$tagDelim = $this->_text[$tagOptionPosition + 1];
			if ($tagDelim == '"' || $tagDelim == "'")
			{
				$tagContentEndPosition = strpos($this->_text, "$tagDelim]", $tagOptionPosition);
				if ($tagContentEndPosition === false)
				{
					return false;
				}

				$tagEndPosition = $tagContentEndPosition + 2;
				$tagOptionContentPosition = $tagOptionPosition + 2;
			}
			else
			{
				$tagOptionContentPosition = $tagOptionPosition + 1;
			}

			$tagName = substr($this->_text, $tagNamePosition, $tagOptionPosition - $tagNamePosition);
			$tagOption = trim(
				substr($this->_text, $tagOptionContentPosition, $tagContentEndPosition - $tagOptionContentPosition)
			);
		}
		else
		{
			$tagName = substr($this->_text, $tagNamePosition, $tagContentEndPosition - $tagNamePosition);
			$tagOption = null;
		}

		if (preg_match('/[^a-z0-9_-]/i', $tagName))
		{
			return false;
		}

		$originalText = substr($this->_text, $tagStartPosition, $tagEndPosition - $tagStartPosition);
		$this->_pushTagOpen($tagName, $tagOption, $originalText);

		return true;
	}

	/**
	 * Merges the trailing text into the current context.
	 */
	protected function _mergeTrailingText()
	{
		if ($this->_trailingText !== '')
		{
			$this->_context[] = $this->_trailingText;
			$this->_trailingText = '';
		}
	}

	/**
	 * Pushes a new plain text node onto the tree.
	 *
	 * @param string $text
	 */
	protected function _pushText($text)
	{
		$this->_trailingText .= strval($text);
	}

	/**
	 * Pushes a new tag opening onto the tree.
	 *
	 * @param string $tagName Name of the tag that was found
	 * @param string|null $tagOption Value for the tag's option
	 * @param string $originalText Original, plain text version of the matched tag (including [ and ])
	 */
	protected function _pushTagOpen($tagName, $tagOption = null, $originalText = '')
	{
		$tagNameLower = strtolower($tagName);

		$invalidTag = false;

		$tagInfo = $this->_getTagRule($tagNameLower);
		if (!$tagInfo)
		{
			// didn't find tag
			$invalidTag = true;
		}
		else if (!empty($this->_parserStates['plainText']))
		{
			$invalidTag = true;
		}
		else
		{
			$hasOption = (is_string($tagOption) && $tagOption !== '');

			if (isset($tagInfo['hasOption']) && $hasOption !== $tagInfo['hasOption'])
			{
				// we expecting an option and not given one or vice versa
				$invalidTag = true;
			}
			else if ($hasOption && isset($tagInfo['optionRegex']) && !preg_match($tagInfo['optionRegex'], $tagOption))
			{
				$invalidTag = true;
			}
			else if (!empty($tagInfo['parseCallback']))
			{
				$tagInfoChanges = call_user_func($tagInfo['parseCallback'], $tagInfo, $tagOption);
				if ($tagInfoChanges === false)
				{
					$invalidTag = true;
				}
				else if (is_array($tagInfoChanges) && isset($tagInfoChanges['plainChildren']))
				{
					$tagInfo['plainChildren'] = true;
				}
			}
		}

		if ($invalidTag)
		{
			$this->_pushText($originalText);
			return;
		}

		$this->_mergeTrailingText();

		$index = count($this->_context);
		$this->_context[$index] = array(
			'tag' => $tagNameLower,
			'option' => $tagOption,
			'original' => ($originalText ? array($originalText, "[/$tagName]") : null),
			'children' => array()
		);

		array_push($this->_tagStack, array(
			'tag' => $tagNameLower,
			'option' => $tagOption,
			'originalText' => $originalText,
			'tagContext' => &$this->_context[$index],
			'parentContext' => &$this->_context
		));
		$this->_context =& $this->_context[$index]['children'];

		if (!empty($tagInfo['plainChildren']))
		{
			$this->_parserStates['plainText'] = $tagNameLower;
		}
	}

	/**
	 * Pushes a tag closing onto the tree.
	 *
	 * @param string $tagName Name of the tag that was found
	 * @param string $originalText Original, plain text version of the matched tag (including [ and ])
	 */
	protected function _pushTagClose($tagName, $originalText = '')
	{
		$tagNameLower = strtolower($tagName);

		$elements = $this->_findInStack($tagName);
		if (!$elements)
		{
			// didn't find tag
			$this->_pushText($originalText);
			return;
		}

		if (!empty($this->_parserStates['plainText']))
		{
			if ($this->_parserStates['plainText'] != $tagNameLower)
			{
				// trying to close the a tag that did not put us in the plain text state
				$this->_pushText($originalText);
				return;
			}
			else
			{
				$this->_parserStates['plainText'] = false;
			}
		}

		// last entry is tag, remaining are invalid nested
		$this->_mergeTrailingText();

		$correctEntry = array_pop($elements);
		$this->_context =& $correctEntry['parentContext'];

		$tagContext =& $correctEntry['tagContext'];
		if ($originalText && is_array($tagContext['original']))
		{
			$tagContext['original'][1] = $originalText;
		}

		while ($replace = array_pop($elements))
		{
			$this->_pushTagOpen($replace['tag'], $replace['option'], $replace['originalText']);
		}
	}

	/**
	 * Finds the named tag in the currently open tags stack. If an array is
	 * returned, the last entry is the correct stack entry. Any other entries
	 * are tags that were opened before this but not closed (inner most first).
	 * These tags should be re-opened after closing this to force valid nesting.
	 *
	 * @param string $tagName Name of the tag to find
	 *
	 * @return array|false
	 */
	protected function _findInStack($tagName)
	{
		if (!$this->_tagStack)
		{
			return false;
		}

		$tagName = strtolower($tagName);

		$elements = array();
		while ($entry = array_pop($this->_tagStack))
		{
			$elements[] = $entry;

			if ($entry['tag'] == $tagName)
			{
				return $elements;
			}
		}

		// not found, put the stack back
		$this->_tagStack = array_reverse($elements);
		return false;
	}

	/**
	 * Gets information about the specified tag.
	 *
	 * @param string $tagName
	 *
	 * @return array|false
	 */
	protected function _getTagRule($tagName)
	{
		$tagName = strtolower($tagName);

		if (!empty($this->_tagList[$tagName]) && is_array($this->_tagList[$tagName]))
		{
			return $this->_tagList[$tagName];
		}
		else
		{
			return false;
		}
	}

	/**
	 * Renders the given text containing BB codes to the required output format
	 * (dependent on the given tags).
	 *
	 * @param string|array $text If array, is assumed to be an already parsed version
	 * @param array $extraStates A list of extra states to pass into the formatter
	 *
	 * @return string
	 */
	public function render($text, array $extraStates = array())
	{
		//echo '<pre>' . htmlspecialchars($text) . '</pre>';
		if (is_array($text))
		{
			$parsed = $text;
		}
		else
		{
			$parsed = $this->parse($text);
		}

		return $this->_formatter->renderTree($parsed, $extraStates);
	}
}