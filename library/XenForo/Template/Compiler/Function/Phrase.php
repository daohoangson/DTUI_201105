<?php

/**
* Class to handle compiling template function calls for "phrase". This function
* compiles language specific text into the template for quick access.
*
* The first argument must be a literal that represents the phrase name.
*
* Additional arguments should be passed in as "name=value", where name is a literal
* and value can be anything. These will then be inserted into the phrase at the named
* positions.
*
* @package XenForo_Template
*/
class XenForo_Template_Compiler_Function_Phrase implements XenForo_Template_Compiler_Function_Interface
{
	protected $_params = array();

	/**
	* Compiles the phrase call.
	*
	* @param XenForo_Template_Compiler The invoking compiler
	* @param string                 Name of the function called
	* @param array                  Arguments to the function (should have at least 1)
	* @param array                  Compilation options
	*
	* @return string
	*/
	public function compile(XenForo_Template_Compiler $compiler, $function, array $arguments, array $options)
	{
		$argc = count($arguments);
		if ($argc < 1)
		{
			throw $compiler->getNewCompilerArgumentException();
		}

		$phraseName = $compiler->getArgumentLiteralValue(array_shift($arguments));
		if ($phraseName === false)
		{
			throw $compiler->getNewCompilerException(new XenForo_Phrase('phrase_name_must_be_literal'));
		}

		$phraseValue = $compiler->getPhraseValue($phraseName);
		if ($phraseValue === false)
		{
			return "'" . $compiler->escapeSingleQuotedString($phraseName) . "'";
		}

		$this->_params = $compiler->compileNamedParams($compiler->parseNamedArguments($arguments), $options);

		$phraseValueEscaped = $compiler->escapeSingleQuotedString($phraseValue);
		$phraseValueEscaped = preg_replace_callback('/\{([a-z0-9_-]+)\}/i', array($this, '_replaceParam'), $phraseValueEscaped);

		if ($phraseValueEscaped === '')
		{
			return '';
		}

		$this->_params = array();
		return "'" . $phraseValueEscaped . "'";
	}

	protected function _replaceParam(array $match)
	{
		$paramName = $match[1];

		if (!isset($this->_params[$paramName]))
		{
			return $match[0];
		}

		$code = (string)$this->_params[$paramName];
		if ($code === '')
		{
			return '';
		}

		return "' . " . $this->_params[$paramName] . " . '";
	}
}