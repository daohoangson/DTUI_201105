<?php

/**
* Class to handle compiling template function calls for "escape". This function
* is similar to {$} syntax, but always does HTML escaping, regardless of context.
*
* @package XenForo_Template
*/
class XenForo_Template_Compiler_Function_Escape implements XenForo_Template_Compiler_Function_Interface
{
	/**
	* Compile the var named in the first argument and return PHP code to access and escape it.
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
		if (count($arguments) < 1)
		{
			throw $compiler->getNewCompilerArgumentException();
		}

		$compileOptions = array_merge($options, array('varEscape' => false));

		if (!empty($arguments[1]))
		{
			$doubleEncode = $compiler->parseConditionExpression($arguments[1], $options);
		}
		else
		{
			$doubleEncode = 'true';
		}

		// note: ISO-8859-1 is fine since we use UTF-8 and are only replacing basic chars
		return 'htmlspecialchars(' . $compiler->compileAndCombineSegments($arguments[0], $compileOptions)
			. ', ENT_COMPAT, \'ISO-8859-1\', ' . $doubleEncode . ')';
	}
}