<?php

/**
* Class to handle compiling template function calls for "date", "time", and "datetime".
* A formatted date or time will be output based on the timestamp in the first argument
* and the browsing user's language.
*
* An optional format override can be passed as the second argument. If provided,
* this argument should be a named format.
*
* @package XenForo_Template
*/
class XenForo_Template_Compiler_Function_DateTime implements XenForo_Template_Compiler_Function_Interface
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
			$arguments[1] = '';
		}

		switch ($function)
		{
			case 'date':
			case 'time':
			case 'datetime':
				$phpFunction = $function;
				break;

			default:
				$phpFunction = 'datetime';
		}

		return 'XenForo_Template_Helper_Core::' . $phpFunction
			. '(' . $compiler->compileAndCombineSegments($arguments[0], array_merge($options, array('varEscape' => false))) . ', '
			. $compiler->compileAndCombineSegments($arguments[1], array_merge($options, array('varEscape' => false))) . ')';
	}
}