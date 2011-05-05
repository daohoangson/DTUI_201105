<?php

/**
* Class to handle compiling template function calls for "link" and "adminlink".
*
* @package XenForo_Template
*/
class XenForo_Template_Compiler_Function_Link implements XenForo_Template_Compiler_Function_Interface
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
		if ($argc < 1)
		{
			throw $compiler->getNewCompilerArgumentException();
		}

		$type = array_shift($arguments);

		$data = 'false';
		if (isset($arguments[0]))
		{
			$dataRef = array_shift($arguments);
			$data = $compiler->compileAndCombineSegments($dataRef, array_merge($options, array('varEscape' => false)));
		}

		$params = $compiler->getNamedParamsAsPhpCode(
			$compiler->parseNamedArguments($arguments),
			array_merge($options, array('varEscape' => false))
		);

		$phpFunction = ($function == 'adminlink' ? 'adminLink' : 'link');

		if ($options['varEscape'] != 'htmlspecialchars')
		{
			$varEscapeParam = ', ' . ($options['varEscape'] ? "'$options[varEscape]'" : 'false');
		}
		else
		{
			$varEscapeParam = '';
		}

		return 'XenForo_Template_Helper_Core::' . $phpFunction . "("
			. $compiler->compileAndCombineSegments($type, $options) . ', ' . $data . ', ' . $params . $varEscapeParam . ')';
	}
}