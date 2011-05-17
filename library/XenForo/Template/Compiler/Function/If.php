<?php

/**
* Class to handle compiling template function calls for "if". If the specified
* condition evaluates to true, the true argument will be evaluated. Otherwise,
* the false argument will be evaluated (if specified).
*
* @package XenForo_Template
*/
class XenForo_Template_Compiler_Function_If implements XenForo_Template_Compiler_Function_Interface
{
	/**
	* Compiles the function call.
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
		if ($argc != 2 && $argc != 3)
		{
			throw $compiler->getNewCompilerArgumentException();
		}

		$condition = $compiler->parseConditionExpression($arguments[0], $options);
		$true = $compiler->compileAndCombineSegments($arguments[1], $options);

		if (!isset($arguments[2]))
		{
			$arguments[2] = '';
		}
		$false = $compiler->compileAndCombineSegments($arguments[2], $options);

		return '(' . $condition . ' ? (' . $true . ') : (' . $false . '))';
	}
}