<?php

/**
* Class to handle compiling template function calls for "count". A formatted number
* will be output based on the number of items in the first (array) argument and the browsing user's language.
*
* Optionally, the number of decimals to display can be passed in the second argument.
* This defaults to 0.
*
* A value of 'false' for the second argument will output an unformatted integer.
*
* Examples:
* {xen:count $arr}        -> 1,234
* {xen:count $arr, 2}     -> 1,234.00
* {xen:count $arr, false} -> 1234
*
* @package XenForo_Template
*/
class XenForo_Template_Compiler_Function_Count implements XenForo_Template_Compiler_Function_Interface
{
	/**
	* Compiles the function call.
	*
	* @param XenForo_Template_Compiler The invoking compiler
	* @param string                 Name of the function called
	* @param array                  Arguments to the function
	* @param array                  Compilation options
	*
	* @return string
	*/
	public function compile(XenForo_Template_Compiler $compiler, $function, array $arguments, array $options)
	{
		$argc = count($arguments);
		if (($argc != 1 && $argc != 2) || !is_array($arguments[0]))
		{
			throw $compiler->getNewCompilerArgumentException();
		}

		if (empty($arguments[1]))
		{
			$arguments[1] = '0';
		}
		else if ($arguments[1] === 'false')
		{
			return 'count(' . $compiler->compileAndCombineSegments($arguments[0], array_merge($options, array('varEscape' => false))) . ')';
		}

		return 'XenForo_Template_Helper_Core::numberFormat(count('
			. $compiler->compileAndCombineSegments($arguments[0], array_merge($options, array('varEscape' => false))) . '), '
			. $compiler->compileAndCombineSegments($arguments[1], array_merge($options, array('varEscape' => false)))
		. ')';
	}
}