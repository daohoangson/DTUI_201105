<?php

/**
* Class to handle compiling template function calls for "raw". This function
* uses disables escaping on the named variable reference.
*
* @package XenForo_Template
*/
class XenForo_Template_Compiler_Function_Raw implements XenForo_Template_Compiler_Function_Interface
{
	/**
	* Compile the var named in the first argument and return PHP code to access it raw.
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
		if (count($arguments) != 1)
		{
			throw $compiler->getNewCompilerArgumentException();
		}

		return $compiler->compileVarRef($arguments[0], $options);
	}
}