<?php

/**
* Class to handle compiling template tag calls for "datetime".
*
* @package XenForo_Template
*/
class XenForo_Template_Compiler_Tag_DateTime implements XenForo_Template_Compiler_Tag_Interface
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
		if (!empty($attributes['time']))
		{
			$time = $compiler->compileVarRef($attributes['time'], $options);
		}
		else if (!empty($children))
		{
			$time = $compiler->compileAndCombineSegments($children, $options);
		}
		else
		{
			throw $compiler->getNewCompilerException(new XenForo_Phrase('missing_attribute_x_for_tag_y', array(
				'attribute' => 'time',
				'tag' => 'datetime'
			)));
		}

		return 'XenForo_Template_Helper_Core::dateTimeHtml('
			. $time . ','
			. $compiler->getNamedParamsAsPhpCode($attributes, $options, array('code')) . ')';
	}
}