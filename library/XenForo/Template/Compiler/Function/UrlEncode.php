<?php

/**
* Class to handle compiling template function calls for "urlencode". This function
* uses URL encoding instead of HTML escaping on the named variable reference.
*
* @package XenForo_Template
*/
class XenForo_Template_Compiler_Function_UrlEncode implements XenForo_Template_Compiler_Function_Interface
{
	/**
	* Compile the var named in the first argument and return PHP code to access and urlencode it.
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

		return 'urlencode(' . $compiler->compileAndCombineSegments($arguments[0], array_merge($options, array('varEscape' => false))) . ')';
	}
}