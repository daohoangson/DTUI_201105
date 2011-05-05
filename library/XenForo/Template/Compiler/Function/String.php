<?php

/**
* Class to handle compiling template function calls for "string".
*
* @package XenForo_Template
*/
class XenForo_Template_Compiler_Function_String implements XenForo_Template_Compiler_Function_Interface
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
		if (count($arguments) < 2)
		{
			throw $compiler->getNewCompilerArgumentException();
		}

		$noEscapeOptions = array_merge($options, array('varEscape' => false));

		$functionCompiled = $compiler->compileAndCombineSegments(array_shift($arguments), $noEscapeOptions);

		$outputArgs = array();
		foreach ($arguments AS $argument)
		{
			$outputArgs[] = $compiler->compileAndCombineSegments($argument, $options);
		}
		$argumentsCompiled = $compiler->buildNamedParamCode($outputArgs);

		return 'XenForo_Template_Helper_Core::string(' . $functionCompiled . ', ' . $argumentsCompiled . ')';
	}
}