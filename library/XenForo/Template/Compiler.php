<?php

/**
 * General template compiling class. This takes a string (template) and converts it
 * into PHP code. This code represents the full statements.
 *
 * Most methods are public so as to be usable by the tag/function handlers. Externally,
 * {@link compile()} is the primary method to use for basic compilation.
 *
 * @package XenForo_Template
 */
class XenForo_Template_Compiler
{
	/**
	* Local cache parsed templates. Used for includes
	*
	* @var array
	*/
	protected static $_templateCache = array();

	/**
	 * The type of compiler. This should be unique per class, based on the source
	 * for things like included templates, etc.
	 *
	 * @var string
	 */
	protected static $_compilerType = 'public';

	/**
	 * The text to compile
	 *
	 * @var string
	 */
	protected $_text = '';

	/**
	 * Array of objects that handle the named template tags. Key is tag name (lower case)
	 * and value is the object.
	 *
	 * @var array
	 */
	protected $_tagHandlers = array();

	/**
	 * Array of objects that handle the named template functions. Key is the function
	 * name (lower case) and value is the object.
	 *
	 * @var array
	 */
	protected $_functionHandlers = array();

	/**
	 * Default options for compilation. These will be used if individual handles do not
	 * override them. Handlers may override all of them or individual ones.
	 *
	 * @var array
	 */
	protected $_options = array(
		'varEscape' => 'htmlspecialchars',
		'allowRawStatements' => true,
		'disableVarMap' => false
	);

	/**
	* Name of the variable that the should be used to create full statements
	*
	* @var string
	*/
	protected $_outputVar = '__output';

	/**
	 * Counter to create a unique variable name
	 *
	 * @var integer
	 */
	protected $_uniqueVarCount = 0;

	/**
	 * Prefix for a variable that holds internal content for the compiler.
	 *
	 * @var string
	 */
	protected $_uniqueVarPrefix = '__compilerVar';

	/**
	 * Controls whether external data (phrases, includes) should be followed
	 * and inserted when compiling. This can be set to false for test compiles.
	 *
	 * @var boolean
	 */
	protected $_followExternal = true;

	protected $_styleId = 0;
	protected $_languageId = 0;
	protected $_title = '';
	protected $_includedTemplates = array();

	protected static $_phraseCache = array();
	protected $_includedPhrases = array();
	protected $_enableDynamicPhraseLoad = true;

	/**
	 * Line number currently on in the original version of the template.
	 *
	 * @var integer
	 */
	protected $_lineNumber = 0;

	/**
	 * Key value set of variables to map. This is primarily used for includes.
	 * Key is the from, value is the to.
	 *
	 * @var array
	 */
	protected $_variableMap = array();

	/**
	 * Constructor. Sets up text.
	 *
	 * @param string Text to compile
	 */
	public function __construct($text = '')
	{
		if ($text !== '')
		{
			$this->setText($text);
		}

		$this->_setupDefaults();
	}

	/**
	 * Set up the defaults. Primarily sets up the handlers for various functions/tags.
	 */
	protected function _setupDefaults()
	{
		$this->addFunctionHandlers(array(
			'raw'       => new XenForo_Template_Compiler_Function_Raw(),
			'escape'    => new XenForo_Template_Compiler_Function_Escape(),
			'urlencode' => new XenForo_Template_Compiler_Function_UrlEncode(),
			'jsescape'  => new XenForo_Template_Compiler_Function_JsEscape(),

			'phrase'    => new XenForo_Template_Compiler_Function_Phrase(),
			'property'  => new XenForo_Template_Compiler_Function_Property(),
			'pagenav'   => new XenForo_Template_Compiler_Function_PageNav(),

			'if'        => new XenForo_Template_Compiler_Function_If(),
			'checked'   => new XenForo_Template_Compiler_Function_CheckedSelected(),
			'selected'  => new XenForo_Template_Compiler_Function_CheckedSelected(),

			'date'      => new XenForo_Template_Compiler_Function_DateTime(),
			'time'      => new XenForo_Template_Compiler_Function_DateTime(),
			'datetime'  => new XenForo_Template_Compiler_Function_DateTime(),

			'number'    => new XenForo_Template_Compiler_Function_Number(),

			'link'      => new XenForo_Template_Compiler_Function_Link(),
			'adminlink' => new XenForo_Template_Compiler_Function_Link(),

			'calc'      => new XenForo_Template_Compiler_Function_Calc(),
			'array'     => new XenForo_Template_Compiler_Function_Array(),
			'count'     => new XenForo_Template_Compiler_Function_Count(),
			'helper'    => new XenForo_Template_Compiler_Function_Helper(),
			'string'    => new XenForo_Template_Compiler_Function_String(),
		));

		$this->addTagHandlers(array(
			'foreach'      => new XenForo_Template_Compiler_Tag_Foreach(),

			'if'           => new XenForo_Template_Compiler_Tag_If(),
			'elseif'       => new XenForo_Template_Compiler_Tag_If(),
			'else'         => new XenForo_Template_Compiler_Tag_If(),
			'contentcheck' => new XenForo_Template_Compiler_Tag_If(),

			'navigation'   => new XenForo_Template_Compiler_Tag_Navigation(),
			'breadcrumb'   => new XenForo_Template_Compiler_Tag_Navigation(),

			'title'        => new XenForo_Template_Compiler_Tag_Title(),
			'description'  => new XenForo_Template_Compiler_Tag_Description(),
			'h1'           => new XenForo_Template_Compiler_Tag_H1(),
			'sidebar'      => new XenForo_Template_Compiler_Tag_Sidebar(),
			'topctrl'      => new XenForo_Template_Compiler_Tag_TopCtrl(),
			'container'    => new XenForo_Template_Compiler_Tag_Container(),

			'require'      => new XenForo_Template_Compiler_Tag_Require(),
			'include'      => new XenForo_Template_Compiler_Tag_Include(),
			'edithint'     => new XenForo_Template_Compiler_Tag_EditHint(),
			'set'          => new XenForo_Template_Compiler_Tag_Set(),
			'hook'         => new XenForo_Template_Compiler_Tag_Hook(),

			'formaction'   => new XenForo_Template_Compiler_Tag_FormAction(),

			'datetime'     => new XenForo_Template_Compiler_Tag_DateTime(),
			'avatar'       => new XenForo_Template_Compiler_Tag_Avatar(),
			'username'     => new XenForo_Template_Compiler_Tag_Username(),
			'likes'        => new XenForo_Template_Compiler_Tag_Likes(),
			'follow'       => new XenForo_Template_Compiler_Tag_Follow(),
			'pagenav'      => new XenForo_Template_Compiler_Tag_PageNav(),

		// note: comment and untreated are handled by the lexer/parser
		));
	}

	/**
	* Modifies the default options. Note that this merges into the options, maintaining
	* any that are not specified in the parameter.
	*
	* @param array
	*
	* @return XenForo_Template_Compiler Fluent interface ($this)
	*/
	public function setDefaultOptions(array $options)
	{
		$this->_options = array_merge($this->_options, $options);
		return $this;
	}

	/**
	* Gets the current set of default options.
	*
	* @return array
	*/
	public function getDefaultOptions()
	{
		return $this->_options;
	}

	/**
	* Sets the text to be compiled.
	*
	* @param string
	*/
	public function setText($text)
	{
		$this->_text = strval($text);
	}

	/**
	* Adds or replaces a template tag handler.
	*
	* @param string					  Name of tag to handle
	* @param XenForo_Template_Compiler_Tag_Interface Handler object
	*
	* @return XenForo_Template_Compiler Fluent interface ($this)
	*/
	public function addTagHandler($tag, XenForo_Template_Compiler_Tag_Interface $handler)
	{
		$this->_tagHandlers[strtolower($tag)] = $handler;
		return $this;
	}

	/**
	* Adds or replaces an array of template tag handlers.
	*
	* @param array Tag handlers; key: tag name, value: object
	*
	* @return XenForo_Template_Compiler Fluent interface ($this)
	*/
	public function addTagHandlers(array $tags)
	{
		foreach ($tags AS $tag => $handler)
		{
			$this->addTagHandler($tag, $handler);
		}

		return $this;
	}

	/**
	* Adds or replaces a template function handler.
	*
	* @param string						   Name of function to handle
	* @param XenForo_Template_Compiler_Function_Interface Handler object
	*
	* @return XenForo_Template_Compiler Fluent interface ($this)
	*/
	public function addFunctionHandler($function, XenForo_Template_Compiler_Function_Interface $handler)
	{
		$this->_functionHandlers[strtolower($function)] = $handler;
		return $this;
	}

	/**
	* Adds or replaces an array of template function handlers.
	*
	* @param array Function handlers; key: function name, value: object
	*
	* @return XenForo_Template_Compiler Fluent interface ($this)
	*/
	public function addFunctionHandlers(array $functions)
	{
		foreach ($functions AS $function => $handler)
		{
			$this->addFunctionHandler($function, $handler);
		}

		return $this;
	}

	/**
	* Gets the variable name that full statements will write their contents into.
	*
	* @return string
	*/
	public function getOutputVar()
	{
		return $this->_outputVar;
	}

	/**
	* Sets the variable name that full statements will write their contents into.
	*
	* @param string
	*/
	public function setOutputVar($_outputVar)
	{
		$this->_outputVar = strval($_outputVar);
	}

	/**
	 * Gets a unique variable name for an internal variable.
	 *
	 * @return string
	 */
	public function getUniqueVar()
	{
		return $this->_uniqueVarPrefix . ++$this->_uniqueVarCount;
	}

	/**
	* Compiles this template from a string. Returns any number of statements.
	*
	* @param string $title Title of this template (required to prevent circular references)
	* @param integer $styleId Style ID this template belongs to (for template includes)
	* @param integer $languageId Language ID this compilation is for (used for phrases)
	*
	* @return string
	*/
	public function compile($title = '', $styleId = 0, $languageId = 0)
	{
		$segments = $this->lexAndParse();
		return $this->compileParsed($segments, $title, $styleId, $languageId);
	}

	/**
	* Compiles this template from its parsed output.
	*
	* @param string|array $segments
	* @param string $title Title of this template (required to prevent circular references)
	* @param integer $styleId Style ID this template belongs to (for template includes)
	* @param integer $languageId Language ID this compilation is for (used for phrases)
	*
	* @return string
	*/
	public function compileParsed($segments, $title, $styleId, $languageId)
	{
		$this->_title = $title;
		$this->_styleId = $styleId;
		$this->_languageId = $languageId;
		$this->_includedTemplates = array();

		if (!is_string($segments) && !is_array($segments))
		{
			throw new XenForo_Exception('Got unexpected, non-string/non-array segments for compilation.');
		}

		$this->_findAndLoadPhrasesFromSegments($segments);

		$statements = $this->compileSegments($segments);
		return $this->getOutputVarInitializer() . $statements->getFullStatements($this->_outputVar);
	}

	/**
	* Compiles this template from its parsed output. The template is considered to be plain
	* text (the default variable escaping is disabled).
	*
	* @param string|array $segments
	* @param string $title Title of this template (required to prevent circular references)
	* @param integer $styleId Style ID this template belongs to (for template includes)
	* @param integer $languageId Language ID this compilation is for (used for phrases)
	*
	* @return string
	*/
	public function compileParsedPlainText($segments, $title, $styleId, $languageId)
	{
		$existingOptions = $this->getDefaultOptions();
		$this->setDefaultOptions(array('varEscape' => false));

		$compiled = $this->compileParsed($segments, $title, $styleId, $languageId);

		$this->setDefaultOptions($existingOptions);
		return $compiled;
	}

	/**
	* Helper funcion to compile the provided segments into the specified variable.
	* This is commonly used to simplify compilation of data that needs to be passed
	* into a function (eg, the children of a form tag).
	*
	* @param string|array $segments Segmenets
	* @param string $var Name of the variable to compile into. If generateVar is true, this will be written to (by ref).
	* @param array  $options Compiler options
	* @param boolean $generateVar Whether to generate the var in argument 2 or use the provided input
	*
	* @return string Full compiled statements
	*/
	public function compileIntoVariable($segments, &$var = '', array $options = null, $generateVar = true)
	{
		if ($generateVar)
		{
			$var = $this->getUniqueVar();
		}

		$oldOutputVar = $this->getOutputVar();
		$this->setOutputVar($var);

		$output =
			$this->getOutputVarInitializer()
			. $this->compileSegments($segments, $options)->getFullStatements($var);

		$this->setOutputVar($oldOutputVar);

		return $output;
	}

	/**
	* Gets the PHP statement that initializers the output var.
	*
	* @return string
	*/
	public function getOutputVarInitializer()
	{
		return '$' . $this->_outputVar . " = '';\n";
	}

	/**
	* Combine uncompiled segments into a string of PHP code. This is simply a helper
	* function that compiles and then combines them for you.
	*
	* @param string|array Segment(s)
	* @param array|null   Override options. If specified, this represents all options.
	*
	* @return string Valid PHP code
	*/
	public function compileAndCombineSegments($segments, array $options = null)
	{
		if (!is_array($options))
		{
			$options = $this->_options;
		}
		$options = array_merge($options, array('allowRawStatements' => false));

		return $this->compileSegments($segments, $options)->getPartialStatement();
	}

	/**
	* Lex and parse the template into segments for final compilation.
	*
	* @return array Parsed segments
	*/
	public function lexAndParse()
	{
		$lexer = new XenForo_Template_Compiler_Lexer($this->_text);
		$parser = new XenForo_Template_Compiler_Parser();

		try
		{
			while ($lexer->yylex() !== false)
			{
				$parser->doParse($lexer->match[0], $lexer->match[1]);
				$parser->setLineNumber($lexer->line); // if this is before the doParse, it seems to give wrong numbers
			}
			$parser->doParse(0, 0);
		}
		catch (Exception $e)
		{
			// from lexer, can't use the base exception, re-throw
			throw new XenForo_Template_Compiler_Exception(new XenForo_Phrase('line_x_template_syntax_error', array('number' => $lexer->line)), true);
		}
		// XenForo_Template_Compiler_Exception: ok -- no need to catch and rethrow

		return $parser->getOutput();
	}

	/**
	* Compile segments into an array of PHP code.
	*
	* @param string|array Segment(s)
	* @param array|null   Override options. If specified, this represents all options.
	*
	* @return XenForo_Template_Compiler_Statement_Collection Collection of parts of a statement or sub statements
	*/
	public function compileSegments($segments, array $options = null)
	{
		$segments = $this->prepareSegmentsForIteration($segments);

		if (!is_array($options))
		{
			$options = $this->_options;
		}

		$statement = $this->getNewStatementCollection();

		foreach ($segments AS $segment)
		{
			$compiled = $this->compileSegment($segment, $options);
			if ($compiled !== '' && $compiled !== null)
			{
				$statement->addStatement($compiled);
			}
		}

		return $statement;
	}

	/**
	* Prepare a collection of segments for iteration. This sanitizes the segments
	* so that each step will give you the next segment, which itself may be a string
	* or an array.
	*
	* @param string|array
	*
	* @return array
	*/
	public function prepareSegmentsForIteration($segments)
	{
		if (!is_array($segments))
		{
			// likely a string (simple literal)
			$segments = array($segments);
		}
		else if (isset($segments['type']))
		{
			// a simple curly var/function
			$segments = array($segments);
		}

		return $segments;
	}

	/**
	* Compile segment into PHP code
	*
	* @param string|array Segment
	* @param array		Override options, must be specified
	*
	* @return string
	*/
	public function compileSegment($segment, array $options)
	{
		if (is_string($segment))
		{
			$this->setLastVistedSegment($segment);
			return $this->compilePlainText($segment, $options);
		}
		else if (is_array($segment) && isset($segment['type']))
		{
			$this->setLastVistedSegment($segment);

			switch ($segment['type'])
			{
				case 'TAG':
					return $this->compileTag(
						$segment['name'], $segment['attributes'],
						isset($segment['children']) ? $segment['children'] : array(),
						$options
					);

				case 'CURLY_VAR':
					return $this->compileVar($segment['name'], $segment['keys'], $options);

				case 'CURLY_FUNCTION':
					return $this->compileFunction($segment['name'], $segment['arguments'], $options);
			}
		}
		else if ($segment === null)
		{
			return '';
		}

		throw $this->getNewCompilerException(new XenForo_Phrase('internal_compiler_error_unknown_segment_type'));
	}

	/**
	 * Sets the last segment that has been visited, updating the line number
	 * to reflect this.
	 *
	 * @param mixed $segment
	 */
	public function setLastVistedSegment($segment)
	{
		if (is_array($segment) && isset($segment['type']))
		{
			if (!empty($segment['line']))
			{
				$this->_lineNumber = $segment['line'];
			}
		}
	}

	/**
	* Escape a string for use inside a single-quoted string.
	*
	* @param string
	*
	* @return string
	*/
	public function escapeSingleQuotedString($string)
	{
		return str_replace(array('\\', "'"), array('\\\\', "\'"), $string);
	}

	/**
	* Compile a plain text segment.
	*
	* @param string Text to compile
	* @param array  Options
	*/
	public function compilePlainText($text, array $options)
	{
		return "'" . $this->escapeSingleQuotedString($text) . "'";
	}

	/**
	* Compile a tag segment. Mostly handled by the specified tag handler.
	*
	* @param string Tag found
	* @param array  Attributes (key: name, value: value)
	* @param array  Any nodes (text, var, tag) that are within this tag
	* @param array  Options
	*/
	public function compileTag($tag, array $attributes, array $children, array $options)
	{
		$tag = strtolower($tag);

		if (isset($this->_tagHandlers[$tag]))
		{
			return $this->_tagHandlers[$tag]->compile($this, $tag, $attributes, $children, $options);
		}
		else
		{
			throw $this->getNewCompilerException(new XenForo_Phrase('unknown_tag_x', array('tag' => $tag)));
		}
	}

	/**
	* Compile a var segment.
	*
	* @param string Name of variable found, not including keys
	* @param array  Keys, may be empty
	* @param array  Options
	*/
	public function compileVar($name, $keys, array $options)
	{
		$name = $this->resolveMappedVariable($name, $options);

		$varName = '$' . $name;

		if (!empty($keys) && is_array($keys))
		{
			foreach ($keys AS $key)
			{
				if (is_string($key))
				{
					$varName .= "['" . $this->escapeSingleQuotedString($key) . "']";
				}
				else if (isset($key['type']) && $key['type'] == 'CURLY_VAR')
				{
					$varName .= '[' . $this->compileVar($key['name'], $key['keys'], array_merge($options, array('varEscape' => false))) . ']';
				}
			}
		}

		if (!empty($options['varEscape']))
		{
			return $options['varEscape'] . '(' . $varName . ')';
		}
		else
		{
			return $varName;
		}
	}

	/**
	* Compile a function segment.
	*
	* @param string Name of function found
	* @param array  Arguments (really should have at least 1 value). Each argument may be any number of segments
	* @param array  Options
	*/
	public function compileFunction($function, array $arguments, array $options)
	{
		$function = strtolower($function);

		if (isset($this->_functionHandlers[$function]))
		{
			return $this->_functionHandlers[$function]->compile($this, $function, $arguments, $options);
		}
		else
		{
			throw $this->getNewCompilerException(new XenForo_Phrase('unknown_function_x', array('function' => $function)));
		}
	}

	/**
	* Compiles a variable reference. A var ref is a string that looks somewhat like a variable.
	* It is used in some arguments to simplify variable access and only allow variables.
	*
	* Data received is any number of segments containing strings or variable segments.
	*
	* Examples: $var, $var.key, $var.{$key}.2, {$key}, {$key}.blah, {$key.blah}.x
	*
	* @param string|array Variable reference segment(s)
	* @param array		Options
	*
	* @return string PHP code to access named variable
	*/
	public function compileVarRef($varRef, array $options)
	{
		$replacements = array();

		if (is_array($varRef))
		{
			if (!isset($varRef[0]))
			{
				$varRef = array($varRef);
			}

			$newVarRef = '';
			foreach ($varRef AS $segment)
			{
				if (is_string($segment))
				{
					$newVarRef .= $segment;
				}
				else
				{
					$newVarRef .= '?';
					$replacements[] = $segment;
				}
			}

			$varRef = $newVarRef;
		}

		$parts = explode('.', $varRef);

		$variable = array_shift($parts);
		if ($variable == '?')
		{
			$variable = $this->compileSegment(array_shift($replacements), array_merge($options, array('varEscape' => false)));
			if (!preg_match('#^\$[a-zA-Z_]#', $variable))
			{
				throw $this->getNewCompilerException(new XenForo_Phrase('invalid_variable_reference'));
			}
		}
		else if (!preg_match('#^\$([a-zA-Z_][a-zA-Z0-9_]*)$#', $variable))
		{
			throw $this->getNewCompilerException(new XenForo_Phrase('invalid_variable_reference'));
		}

		$keys = array();
		foreach ($parts AS $part)
		{
			if ($part == '?')
			{
				$part = $this->compileSegment(array_shift($replacements), array_merge($options, array('varEscape' => false)));
			}
			else if ($part === '' || strpos($part, '?') !== false)
			{
				// empty key or simply contains a replacement
				throw $this->getNewCompilerException(new XenForo_Phrase('invalid_variable_reference'));
			}
			else
			{
				$part = "'" . $this->escapeSingleQuotedString($part) . "'";
			}

			$keys[] = '[' . $part . ']';
		}

		$variable = '$' . $this->resolveMappedVariable(substr($variable, 1), $options);

		return $variable . implode('', $keys);
	}

	/**
	* Parses a set of named arguments. Each argument should be in the form of "key=value".
	* The key must be literal, but the value can be anything.
	*
	* @param array Arguments to treat as named
	*
	* @return array Key is the argument name, value is segment(s) to be compiled
	*/
	function parseNamedArguments(array $arguments)
	{
		$params = array();
		foreach ($arguments AS $argument)
		{
			if (!isset($argument[0]) || !is_string($argument[0]) || !preg_match('#^([a-z0-9_\.]+)=#i', $argument[0], $match))
			{
				throw $this->getNewCompilerException(new XenForo_Phrase('named_parameter_not_specified_correctly'));
			}

			$name = $match[1];

			$nameRemoved = substr($argument[0], strlen($match[0]));
			if ($nameRemoved === false)
			{
				// we ate the whole string, remove the argument
				unset($argument[0]);
			}
			else
			{
				$argument[0] = $nameRemoved;
			}

			$nameParts = explode('.', $name);
			if (count($nameParts) > 1)
			{
				$pointer =& $params;
				foreach ($nameParts AS $namePart)
				{
					if (!isset($pointer[$namePart]))
					{
						$pointer[$namePart] = array();
					}
					$pointer =& $pointer[$namePart];
				}
				$pointer = $argument;
			}
			else
			{
				$params[$name] = $argument;
			}
		}

		return $params;
	}

	/**
	 * Compiled a set of named params into a set of named params that can be used as PHP code.
	 * The key is a single quoted string.
	 *
	 * @param array See {@link parseNamedArguments()}. Key is the name, value is segments for that param.
	 * @param array Compiler options
	 * @param array A list of named params should be compiled as conditions instead of plain output
	 *
	 * @return array
	 */
	public function compileNamedParams(array $params, array $options, array $compileAsCondition = array())
	{
		$compiled = array();
		foreach ($params AS $name => $value)
		{
			if (in_array($name, $compileAsCondition))
			{
				$compiled[$name] = $this->parseConditionExpression($value, $options);
			}
			else
			{
				if (is_array($value))
				{
					// if an associative array, not a list of segments
					reset($value);
					list($key, ) = each($value);
					if (is_string($key))
					{
						$compiled[$name] = $this->compileNamedParams($value, $options);
						continue;
					}
				}

				$compiled[$name] = $this->compileAndCombineSegments($value, $options);
			}
		}

		return $compiled;
	}

	/**
	 * Build actual PHP code from a set of compiled named params
	 *
	 * @param array $compiled Already compiled named params. See {@link compileNamedParams}.
	 *
	 * @return string
	 */
	public function buildNamedParamCode(array $compiled)
	{
		if (!$compiled)
		{
			return 'array()';
		}

		$output = "array(\n";
		$i = 0;
		foreach ($compiled AS $name => $value)
		{
			if (is_array($value))
			{
				$value = $this->buildNamedParamCode($value);
			}

			if ($i > 0)
			{
				$output .= ",\n";
			}
			$output .= "'" . $this->escapeSingleQuotedString($name) . "' => $value";

			$i++;
		}

		$output .= "\n)";

		return $output;
	}

	/**
	* Takes a compiled set of named parameters and turns them into PHP code (an array).
	*
	* @param array See {@link parseNamedArguments()}. Key is the name, value is segments for that param.
	* @param array Compiler options
	* @param array A list of named params should be compiled as conditions instead of plain output
	*
	* @return string PHP code for an array
	*/
	public function getNamedParamsAsPhpCode(array $params, array $options, array $compileAsCondition = array())
	{
		$compiled = $this->compileNamedParams($params, $options, $compileAsCondition);
		return $this->buildNamedParamCode($compiled);
	}

	/**
	* Creates a new raw statement handler.
	*
	* @param string Quickly set a statement
	*
	* @return XenForo_Template_Compiler_Statement_Raw
	*/
	public function getNewRawStatement($statement = '')
	{
		return new XenForo_Template_Compiler_Statement_Raw($statement);
	}

	/**
	* Creates a new statement collection handler.
	*
	* @return XenForo_Template_Compiler_Statement_Collection
	*/
	public function getNewStatementCollection()
	{
		return new XenForo_Template_Compiler_Statement_Collection();
	}

	/**
	* Creates a new compiler exception.
	*
	* @param string $message Optional message
	* @param mixed $segment The segment that caused this. If specified and has a line number, that line is reported.
	*
	* @return XenForo_Template_Compiler_Exception
	*/
	public function getNewCompilerException($message = '', $segment = false)
	{
		if (is_array($segment) && !empty($segment['line']))
		{
			$lineNumber = $segment['line'];
		}
		else if (is_int($segment) && !empty($segment))
		{
			$lineNumber = $segment;
		}
		else
		{
			$lineNumber = $this->_lineNumber;
		}

		if ($lineNumber)
		{
			$message = new XenForo_Phrase('line_x', array('line' => $lineNumber)) . ': ' . $message;
		}

		$e = new XenForo_Template_Compiler_Exception($message, true);
		$e->setLineNumber($lineNumber);

		return $e;
	}

	/**
	* Creates a new compiler exception for an incorrect amount of arguments.
	*
	* @param mixed $segment The segment that caused this. If specified and has a line number, that line is reported.
	*
	* @return XenForo_Template_Compiler_Exception
	*/
	public function getNewCompilerArgumentException($segment = false)
	{
		return $this->getNewCompilerException(new XenForo_Phrase('incorrect_arguments'), $segment);
	}

	/**
	* Determines if the segment is a tag with the specified name.
	*
	* @param string|array Segment
	* @param string	   Tag name
	*
	* @return boolean
	*/
	public function isSegmentNamedTag($segment, $tagName)
	{
		return (is_array($segment) && isset($segment['type']) && $segment['type'] == 'TAG' && $segment['name'] == $tagName);
	}

	/**
	* Parses a conditional expression into valid PHP code
	*
	* @param string|array The original unparsed condition. This will consist of plaintext or curly var/function segments.
	* @param array		Compiler options
	*
	* @return string Valid PHP code for the condition
	*/
	public function parseConditionExpression($origCondition, array $options)
	{
		$placeholders = array();
		$placeholderChar = "\x1A"; // substitute character in ascii

		if ($origCondition === '')
		{
			throw $this->getNewCompilerException(new XenForo_Phrase('invalid_condition_expression'));
		}

		if (is_string($origCondition))
		{
			$condition = $origCondition;
		}
		else
		{
			$condition = '';
			foreach ($this->prepareSegmentsForIteration($origCondition) AS $segment)
			{
				if (is_string($segment))
				{
					if (strpos($segment, $placeholderChar) !== false)
					{
						throw $this->getNewCompilerException(new XenForo_Phrase('invalid_condition_expression'));
					}

					$condition .= $segment;
				}
				else
				{
					$condition .= $placeholderChar;
					$placeholders[] = $this->compileSegment($segment, array_merge($options, array('varEscape' => false)));
				}
			}
		}

		return $this->_parseConditionExpression($condition, $placeholders);
	}

	/**
	* Internal function for parsing a condition expression. Note that the variables
	* passed into this will be modified by reference.
	*
	* @param string  Expression with placeholders replaced with "\x1A"
	* @param array   Placeholders for variables/functions
	* @param boolean Whether to return when we match a right parenthesis
	*
	* @return string Parsed condition
	*/
	protected function _parseConditionExpression(&$expression, array &$placeholders,
		$internalExpression = false, $isFunction = false)
	{
		if ($internalExpression && $isFunction && strlen($expression) > 0 && $expression[0] == ')')
		{
			$expression = substr($expression, 1);
			return '()';
		}

		$state = 'value';
		$endState = 'operator';

		$compiled = '';

		$allowedFunctions = 'is_array|is_object|is_string|isset|empty'
			. '|array|array_key_exists|count|in_array|array_search'
			. '|preg_match|preg_match_all|strpos|stripos|strlen'
			. '|ceil|floor|round|max|min|mt_rand|rand';

		do
		{
			$eatChars = 0;

			if ($state == 'value')
			{
				if (preg_match('#^\s+#', $expression, $match))
				{
					// ignore whitespace
					$eatChars = strlen($match[0]);
				}
				else if ($expression[0] == "\x1A")
				{
					$compiled .= array_shift($placeholders);
					$state = 'operator';
					$eatChars = 1;
				}
				else if ($expression[0] == '(')
				{
					$expression = substr($expression, 1);
					$compiled .= $this->_parseConditionExpression($expression, $placeholders, true);
					$state = 'operator';
					continue; // not eating anything, so must continue
				}
				else if (preg_match('#^(\-|!)#', $expression, $match))
				{
					$compiled .= $match[0];
					$state = 'value'; // we still need a value after this, simply modifies the following value
					$eatChars = strlen($match[0]);
				}
				else if (preg_match('#^(\d+(\.\d+)?|true|false|null)#', $expression, $match))
				{
					$compiled .= $match[0];
					$state = 'operator';
					$eatChars = strlen($match[0]);
				}
				else if (preg_match('#^(' . $allowedFunctions . ')\(#i', $expression, $match))
				{
					$expression = substr($expression, strlen($match[0]));
					$compiled .= $match[1] . $this->_parseConditionExpression($expression, $placeholders, true, true);
					$state = 'operator';
					continue; // not eating anything, so must continue
				}
				else if (preg_match('#^(\'|")#', $expression, $match))
				{
					$quoteClosePos = strpos($expression, $match[0], 1); // skip initial
					if ($quoteClosePos === false)
					{
						throw $this->getNewCompilerException(new XenForo_Phrase('invalid_condition_expression'));
					}

					$quoted = substr($expression, 1, $quoteClosePos - 1);

					$string = array();
					$i = 0;
					foreach (explode("\x1A", $quoted) AS $quotedPart)
					{
						if ($i % 2 == 1)
						{
							// odd parts have a ? before them
							$string[] = array_shift($placeholders);
						}

						if ($quotedPart !== '')
						{
							$string[] = "'" . $this->escapeSingleQuotedString($quotedPart) . "'";
						}

						$i++;
					}

					if (!$string)
					{
						$string[] = "''";
					}

					$compiled .= '(' . implode(' . ', $string) . ')';

					$eatChars = strlen($quoted) + 2; // 2 = quotes on either side
					$state = 'operator';
				}
			}
			else if ($state == 'operator')
			{
				if (preg_match('#^\s+#', $expression, $match))
				{
					// ignore whitespace
					$eatChars = strlen($match[0]);
				}
				else if (preg_match('#^(\*|\+|\-|/|%|===|==|!==|!=|>=|<=|<|>|\|\||&&|and|or|xor|&|\|)#i', $expression, $match))
				{
					$eatChars = strlen($match[0]);
					$compiled .= " $match[0] ";
					$state = 'value';
				}
				else if ($expression[0] == ')' && $internalExpression)
				{
					// eat and return successfully
					$eatChars = 1;
					$state = false;
				}
				else if ($expression[0] == ',' && $isFunction)
				{
					$eatChars = 1;
					$compiled .= ", ";
					$state = 'value';
				}
			}

			if ($eatChars)
			{
				$expression = substr($expression, $eatChars);
			}
			else
			{
				// prevent infinite loops -- if you want to avoid this, use "continue"
				throw $this->getNewCompilerException(new XenForo_Phrase('invalid_condition_expression'));
			}
		} while ($state !== false && $expression !== '' && $expression !== false);

		if ($state != $endState && $state !== false)
		{
			// operator is the end state -- means we're expecting an operator, so it can be anything
			throw $this->getNewCompilerException(new XenForo_Phrase('invalid_condition_expression'));
		}

		return "($compiled)";
	}

	/**
	 * Gets the literal value of a curly function's argument. If a literal value
	 * cannot be obtained, false is returned.
	 *
	 * @param string|array $argument
	 *
	 * @return string|false Literal value or false
	 */
	public function getArgumentLiteralValue($argument)
	{
		if (is_string($argument))
		{
			return $argument;
		}
		else if (is_array($argument) && sizeof($argument) == 1 && is_string($argument[0]))
		{
			return $argument[0];
		}
		else
		{
			return false;
		}
	}

	/**
	* Quickly gets multiple named attributes and returns any of them that exist.
	*
	* @param array Attributes for the tag
	* @param array Attributes to fetch
	*
	* @return array Any attributes that existed
	*/
	public function getNamedAttributes(array $attributes, array $wantedAttributes)
	{
		$output = array();
		foreach ($wantedAttributes AS $wanted)
		{
			if (isset($attributes[$wanted]))
			{
				$output[$wanted] = $attributes[$wanted];
			}
		}

		return $output;
	}

	/**
	 * Sets whether external data (phrases, includes) should be "followed" and fetched.
	 * This can be set to false when doing a test compile.
	 *
	 * @param boolean $value
	 */
	public function setFollowExternal($value)
	{
		$this->_followExternal = (bool)$value;
	}

	/**
	 * Sets the line number manually. This may be needed to report a more
	 * accurate line number when tags manually handle child tags (eg, if).
	 *
	 * If this function is not used, the line number from the last tag
	 * handled by {@link compileSegment()} will be used.
	 *
	 * @param integer $lineNumber
	 */
	public function setLineNumber($lineNumber)
	{
		$this->_lineNumber = intval($lineNumber);
	}

	/**
	 * Gets the current line number.
	 *
	 * @return integer
	 */
	public function getLineNumber()
	{
		return $this->_lineNumber;
	}

	/**
	 * Merges phrases into the existing phrase cache. Phrases are expected
	 * for all languages.
	 *
	 * @param array $phraseData Format: [language id][title] => value
	 */
	public function mergePhraseCache(array $phraseData)
	{
		foreach ($phraseData AS $languageId => $phrases)
		{
			if (!is_array($phrases))
			{
				continue;
			}

			if (isset(self::$_phraseCache[$languageId]))
			{
				self::$_phraseCache[$languageId] = array_merge(self::$_phraseCache[$languageId], $phrases);
			}
			else
			{
				self::$_phraseCache[$languageId] = $phrases;
			}
		}
	}

	/**
	 * Resets the phrase cache. This should be done when a phrase value
	 * changes, before compiling templates.
	 */
	public static function resetPhraseCache()
	{
		self::$_phraseCache = array();
	}

	/**
	 * Gets the value for a phrase in the language the compiler is compiling for.
	 *
	 * @param string $title
	 *
	 * @return string|false
	 */
	public function getPhraseValue($title)
	{
		if (!$this->_followExternal)
		{
			return false;
		}

		$this->_includedPhrases[$title] = true;

		if (isset(self::$_phraseCache[$this->_languageId][$title]))
		{
			return self::$_phraseCache[$this->_languageId][$title];
		}
		else
		{
			return false;
		}
	}

	/**
	 * Disables dynamic phrase loading. Generally only desired for tests.
	 */
	public function disableDynamicPhraseLoad()
	{
		$this->_enableDynamicPhraseLoad = false;
	}

	/**
	 * Gets a list of all phrases included in this template.
	 *
	 * @return array List of phrase titles
	 */
	public function getIncludedPhrases()
	{
		return array_keys($this->_includedPhrases);
	}

	/**
	* Gets an already parsed template for inclusion.
	*
	* @param string $title Name of the template to include
	*
	* @return string|array Segments
	*/
	public function includeParsedTemplate($title)
	{
		if ($title == $this->_title)
		{
			throw $this->getNewCompilerException(new XenForo_Phrase('circular_reference_found_in_template_includes'));
		}

		if (!$this->_followExternal)
		{
			return '';
		}

		if (!isset(self::$_templateCache[$this->getCompilerType()][$this->_styleId][$title]))
		{
			self::$_templateCache[$this->getCompilerType()][$this->_styleId][$title] = $this->_getParsedTemplateFromModel($title, $this->_styleId);
		}

		$info = self::$_templateCache[$this->getCompilerType()][$this->_styleId][$title];
		if (is_array($info))
		{
			if (empty($this->_includedTemplates[$info['id']]))
			{
				// cache phrases for this template as we haven't included it
				$this->_findAndLoadPhrasesFromSegments($info['data']);
			}

			$this->_includedTemplates[$info['id']] = true;
			return $info['data'];
		}
		else
		{
			return '';
		}
	}

	/**
	 * Finds the phrases used by the specified segments, loads them, and then
	 * merges them into the local phrase cache.
	 *
	 * @param array|string $segments
	 */
	protected function _findAndLoadPhrasesFromSegments($segments)
	{
		if (!$this->_enableDynamicPhraseLoad)
		{
			return;
		}

		$phrasesUsed = $this->identifyPhrasesInParsedTemplate($segments);
		foreach ($phrasesUsed AS $key => $title)
		{
			if (isset(self::$_phraseCache[$this->_languageId][$title]))
			{
				unset($phrasesUsed[$key]);
			}
		}

		if ($phrasesUsed)
		{
			$phraseData = XenForo_Model::create('XenForo_Model_Phrase')->getEffectivePhraseValuesInAllLanguages($phrasesUsed);
			$this->mergePhraseCache($phraseData);
		}
	}

	/**
	* Helper to go to the model to get the parsed version of the specified template.
	*
	* @param string $title Title of template
	* @param integer $styleId ID of the style the template should apply to
	*
	* @return false|array Array should have keys of id and data (data should be parsed version of template)
	*/
	protected function _getParsedTemplateFromModel($title, $styleId)
	{
		$template = XenForo_Model::create('XenForo_Model_Template')->getEffectiveTemplateByTitle($title, $styleId);
		if (isset($template['template_parsed']))
		{
			return array(
				'id' => $template['template_map_id'],
				'data' => unserialize($template['template_parsed'])
			);
		}
		else
		{
			return false;
		}
	}

	/**
	* Adds parsed templates to the template cache for the specified style.
	*
	* @param array $templates Keys are template names, values are parsed vesions of templates
	* @param integer $styleId ID of the style that the templates are from
	*/
	public static function setTemplateCache(array $templates, $styleId = 0)
	{
		self::_setTemplateCache($templates, $styleId, self::$_compilerType);
	}

	/**
	 * Internal handler for setting the template cache.
	 *
	 * @param array $templates
	 * @param integer $styleId
	 * @param string $compilerType
	 */
	protected static function _setTemplateCache(array $templates, $styleId, $compilerType)
	{
		if (empty(self::$_templateCache[$compilerType][$styleId]))
		{
			self::$_templateCache[$compilerType][$styleId] = $templates;
		}
		else
		{
			self::$_templateCache[$compilerType][$styleId] = array_merge(self::$_templateCache[$compilerType][$styleId], $templates);
		}

	}

	/**
	* Helper to reset the template cache to reclaim memory or for tests.
	*
	* @param integer|true $styleId Style ID to reset the cache for; true for all styles
	*/
	public static function resetTemplateCache($styleId = true)
	{
		self::_resetTemplateCache($styleId, self::$_compilerType);
	}

	/**
	 * Internal handler for resetting the template cache.
	 *
	 * @param integer|boolean $styleId
	 * @param string $compilerType
	 */
	protected static function _resetTemplateCache($styleId, $compilerType)
	{
		if ($styleId === true)
		{
			self::$_templateCache[$compilerType] = array();
		}
		else
		{
			self::$_templateCache[$compilerType][$styleId] = array();
		}
	}

	/**
	 * Removes the named template from the compiler cache.
	 *
	 * @param string $title
	 */
	public static function removeTemplateFromCache($title)
	{
		self::_removeTemplateFromCache($title, self::$_compilerType);
	}

	/**
	 * Internal handler to remove the named template from the specified compiler
	 * cache.
	 *
	 * @param string $title
	 * @param string $compilerType
	 */
	protected static function _removeTemplateFromCache($title, $compilerType)
	{
		if (!$title || !isset(self::$_templateCache[$compilerType]))
		{
			return;
		}

		foreach (self::$_templateCache[$compilerType] AS $styleId => $style)
		{
			if (isset($style[$title]))
			{
				unset(self::$_templateCache[$compilerType][$styleId][$title]);
			}
		}
	}

	/**
	* Gets the list of included template IDs (map or actual template IDs).
	*
	* @return array
	*/
	public function getIncludedTemplates()
	{
		return array_keys($this->_includedTemplates);
	}

	/**
	 * Gets the compiler type. This method generally needs to be overridden
	 * in child classes because of the lack of LSB.
	 *
	 * @return string
	 */
	public function getCompilerType()
	{
		return self::$_compilerType;
	}

	/**
	 * Identifies the list of phrases that exist in a parsed template. This list
	 * can be populated even if the template is invalid.
	 *
	 * @param string|array $segments List of parsed segments
	 *
	 * @return array Unique list of phrases used in this template
	 */
	public function identifyPhrasesInParsedTemplate($segments)
	{
		$phrases = $this->_identifyPhrasesInSegments($segments);
		return array_unique($phrases);
	}

	/**
	 * Internal handler to get the phrases that are used in a collection of
	 * template segments.
	 *
	 * @param string|array $segments
	 *
	 * @return array List of phrases used in these segments; phrases may be repeated
	 */
	protected function _identifyPhrasesInSegments($segments)
	{
		$phrases = array();

		foreach ($this->prepareSegmentsForIteration($segments) AS $segment)
		{
			if (!is_array($segment) || !isset($segment['type']))
			{
				continue;
			}

			switch ($segment['type'])
			{
				case 'TAG':
					$phrases = array_merge($phrases,
						$this->_identifyPhrasesInSegments($segment['children'])
					);

					foreach ($segment['attributes'] AS $attribute)
					{
						$phrases = array_merge($phrases, $this->_identifyPhrasesInSegments($attribute));
					}
					break;

				case 'CURLY_FUNCTION':
					if ($segment['name'] == 'phrase' && isset($segment['arguments'][0]))
					{
						$literalValue = $this->getArgumentLiteralValue($segment['arguments'][0]);
						if ($literalValue !== false)
						{
							$phrases[] = $literalValue;
						}
					}

					foreach ($segment['arguments'] AS $argument)
					{
						$phrases = array_merge($phrases, $this->_identifyPhrasesInSegments($argument));
					}
			}
		}

		return $phrases;
	}

	/**
	 * Resolves the mapping for the specified variable.
	 *
	 * @param string $name
	 * @param array $options Compiler options
	 *
	 * @return string
	 */
	public function resolveMappedVariable($name, array $options)
	{
		if (!empty($options['disableVarMap']))
		{
			return $name;
		}

		$visited = array(); // loop protection

		while (isset($this->_variableMap[$name]) && !isset($visited[$name]))
		{
			$visited[$name] = true;
			$name = $this->_variableMap[$name];
		}

		return $name;
	}

	/**
	 * Gets the variable map list.
	 *
	 * @return array
	 */
	public function getVariableMap()
	{
		return $this->_variableMap;
	}

	/**
	 * Sets/merges the variable map list.
	 *
	 * @param array $map
	 * @param boolean $merge If true, merges; otherwise, overwrites
	 */
	public function setVariableMap(array $map, $merge = false)
	{
		if ($merge)
		{
			if ($map)
			{
				$this->_variableMap = array_merge($this->_variableMap, $map);
			}
		}
		else
		{
			$this->_variableMap = $map;
		}
	}
}