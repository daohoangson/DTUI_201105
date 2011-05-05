<?php

/**
 * Helper to allow deferred rendering of BB codes in text. This is useful when
 * putting BB code into a template. The text does not need to explicitly
 * be rendered in the View.
 *
 * When this object is coerced to a string, the text will be rendered.
 *
 * @package XenForo_BbCode
 */
class XenForo_BbCode_TextWrapper
{
	/**
	 * Text to render. May be already parsed array.
	 *
	 * @var string|array
	 */
	protected $_text = '';

	/**
	 * @var XenForo_BbCode_Parser
	 */
	protected $_parser = null;

	/**
	 * Extra states for the formatter.
	 *
	 * @var array
	 */
	protected $_extraStates = array();

	/**
	 * Constructor.
	 *
	 * @param string|array $text May be already parsed array
	 * @param XenForo_BbCode_Parser $parser
	 * @param array $extraStates
	 */
	public function __construct($text, XenForo_BbCode_Parser $parser, array $extraStates = array())
	{
		$this->_text = $text;
		$this->_parser = $parser;
		$this->_extraStates = $extraStates;
	}

	/**
	 * Renders the text.
	 *
	 * @return string
	 */
	public function __toString()
	{
		return $this->_parser->render($this->_text, $this->_extraStates);
	}
}