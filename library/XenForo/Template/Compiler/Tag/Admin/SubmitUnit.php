<?php

/**
* Class to handle compiling template tag calls for "submitunit" in admin areas.
*
* @package XenForo_Template
*/
class XenForo_Template_Compiler_Tag_Admin_SubmitUnit extends XenForo_Template_Compiler_Tag_Admin_Abstract implements XenForo_Template_Compiler_Tag_Interface
{
	/**
	* Compile the specified tag and return PHP code to handle it.
	*
	* @param XenForo_Template_Compiler The invoking compiler
	* @param string                 Name of the tag called
	* @param array                  Attributes for the tag (may be empty)
	* @param array                  Nodes (tags/curlies/text) within this tag (may be empty)
	* @param array                  Compilation options
	*
	* @return string
	*/
	public function compile(XenForo_Template_Compiler $compiler, $tag, array $attributes, array $children, array $options)
	{
		$controlOptions = $compiler->getNamedAttributes($attributes, array('save', 'name', 'reset', 'savekey', 'saveclass', 'resetkey', 'resetclass'));
		$controlOptions = $compiler->getNamedParamsAsPhpCode($controlOptions, $options);

		$childOutput = $compiler->compileIntoVariable($children, $submitVar, $options);

		$statement = $compiler->getNewRawStatement();
		$statement->addStatement($childOutput);
		$statement->addStatement(
			'$' . $compiler->getOutputVar() . ' .= XenForo_Template_Helper_Admin::submitUnit($' . $submitVar . ', ' . $controlOptions . ");\n"
			. 'unset($' . $submitVar . ");\n"
		);

		return $statement;
	}
}