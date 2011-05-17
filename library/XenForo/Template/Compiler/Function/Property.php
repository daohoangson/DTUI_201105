<?php

/**
* Class to handle compiling template function calls for "property", for accessing
* style properties.
*
* @package XenForo_Template
*/
class XenForo_Template_Compiler_Function_Property implements XenForo_Template_Compiler_Function_Interface
{
	/**
	* Compile the function and return PHP handle it.
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

		return "XenForo_Template_Helper_Core::styleProperty("
			. $compiler->compileAndCombineSegments($arguments[0], array_merge($options, array('varEscape' => false)))
			. ")";
	}
}