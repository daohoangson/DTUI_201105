<?php

/**
* Class to handle compiling template function calls for "number". A formatted number
* will be output based on the number in the first argument and the browsing user's language.
*
* Optionally, the number of decimals to display can be passed in the second argument.
* This defaults to 0.
*
* @package XenForo_Template
*/
class XenForo_Template_Compiler_Function_Number implements XenForo_Template_Compiler_Function_Interface
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
		if ($argc != 1 && $argc != 2)
		{
			throw $compiler->getNewCompilerArgumentException();
		}

		if (empty($arguments[1]))
		{
			$arguments[1] = '0';
		}

		return 'XenForo_Template_Helper_Core::numberFormat(' . $compiler->compileAndCombineSegments($arguments[0], array_merge($options, array('varEscape' => false))) . ', '
			. $compiler->compileAndCombineSegments($arguments[1], array_merge($options, array('varEscape' => false))) . ')';
	}
}