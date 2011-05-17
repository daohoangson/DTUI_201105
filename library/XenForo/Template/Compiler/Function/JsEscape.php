<?php

/**
* Class to handle compiling template function calls for "jsescape". This function
* escapes the first argument for JavaScript. Note that the first argument is a value,
* not a variable reference. Variables used within will be HTML escaped first, unless
* you use raw.
*
* The second argument controls the context for escaping (single, double).
*
* @package XenForo_Template
*/
class XenForo_Template_Compiler_Function_JsEscape implements XenForo_Template_Compiler_Function_Interface
{
	/**
	* Compile the content in the first argument and escape it for JS based on the second.
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
		if ($argc != 1 && $argc != 2)
		{
			throw $compiler->getNewCompilerArgumentException();
		}

		if (empty($arguments[1]))
		{
			$arguments[1] = 'double';
		}

		if (!is_string($arguments[1]))
		{
			throw $compiler->getNewCompilerException(new XenForo_Phrase('argument_must_be_string'));
		}

		switch ($arguments[1])
		{
			case 'double':
			case 'single':
				break;

			default:
				throw $compiler->getNewCompilerException(new XenForo_Phrase('invalid_argument'));
		}

		return 'XenForo_Template_Helper_Core::jsEscape(' . $compiler->compileAndCombineSegments($arguments[0], $options) . ', \'' . $arguments[1] . '\')';
	}
}