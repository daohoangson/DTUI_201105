<?php

/**
* Class to handle compiling template function calls for "array".
*
* @package XenForo_Template
*/
class XenForo_Template_Compiler_Function_Array implements XenForo_Template_Compiler_Function_Interface
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
		$params = $compiler->getNamedParamsAsPhpCode(
			$compiler->parseNamedArguments($arguments),
			array_merge($options, array('varEscape' => false))
		);

		return $params;
	}
}