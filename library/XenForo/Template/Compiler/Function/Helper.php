<?php

/**
* Class to handle compiling template function calls for "helper".
*
* @package XenForo_Template
*/
class XenForo_Template_Compiler_Function_Helper implements XenForo_Template_Compiler_Function_Interface
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
		if (count($arguments) < 1)
		{
			throw $compiler->getNewCompilerArgumentException();
		}

		$noEscapeOptions = array_merge($options, array('varEscape' => false));

		$functionCompiled = $compiler->compileAndCombineSegments(array_shift($arguments), $noEscapeOptions);

		$outputArgs = array();
		foreach ($arguments AS $argument)
		{
			$outputArgs[] = $compiler->compileAndCombineSegments($argument, $noEscapeOptions);
		}
		$argumentsCompiled = $compiler->buildNamedParamCode($outputArgs);

		return 'XenForo_Template_Helper_Core::callHelper(' . $functionCompiled . ', ' . $argumentsCompiled . ')';
	}
}