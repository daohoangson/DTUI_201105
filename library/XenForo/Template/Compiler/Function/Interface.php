<?php

/**
* Interface for template function handlers.
*
* @package XenForo_Template
*/
interface XenForo_Template_Compiler_Function_Interface
{
	/**
	* Compile the specified function and return PHP code to handle it.
	*
	* @param XenForo_Template_Compiler The invoking compiler
	* @param string                 Name of the function called
	* @param array                  Arguments to the function (should have at least 1)
	* @param array                  Compilation options
	*
	* @return string
	*/
	public function compile(XenForo_Template_Compiler $compiler, $function, array $arguments, array $options);
}