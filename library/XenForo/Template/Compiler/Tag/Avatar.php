<?php

/**
* Class to handle compiling template tag calls for "avatar".
*
* @package XenForo_Template
*/
class XenForo_Template_Compiler_Tag_Avatar implements XenForo_Template_Compiler_Tag_Interface
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
		// user array
		if (!empty($attributes['user']))
		{
			$user = $compiler->compileVarRef($attributes['user'], $options);
		}
		else
		{
			throw $compiler->getNewCompilerException(new XenForo_Phrase('missing_x_attribute_for_y_tag', array(
				'attribute' => 'user',
				'tag' => 'avatar'
			)));
		}

		// avatar image mode (span or img)
		if (isset($attributes['img']))
		{
			$img = $compiler->parseConditionExpression($attributes['img'], $options);
		}
		else
		{
			$img = 'false';
		}

		return 'XenForo_Template_Helper_Core::avatarHtml('
			. $user . ','
			. $img . ','
			. $compiler->getNamedParamsAsPhpCode($attributes, $options, array('code')) . ','
			. $compiler->compileAndCombineSegments($children, $options) . ')';
	}
}