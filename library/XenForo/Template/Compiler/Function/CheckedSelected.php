<?php

/**
* Class to handle compiling template function calls for "checked" and "selected".
* If the specified condition is true, the checked/selected HTML attribute will be
* outputted.
*
* @package XenForo_Template
*/
class XenForo_Template_Compiler_Function_CheckedSelected implements XenForo_Template_Compiler_Function_Interface
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
		if ($argc != 1)
		{
			throw $compiler->getNewCompilerArgumentException();
		}

		$condition = $compiler->parseConditionExpression($arguments[0], $options);
		$true = ($function == 'checked' ? 'checked="checked"' : 'selected="selected"');

		return '(' . $condition . ' ? \'' . $true . '\' : \'\')';
	}
}