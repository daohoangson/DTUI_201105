<?php

/**
* Class to handle compiling template tag calls for "username".
*
* @package XenForo_Template
*/
class XenForo_Template_Compiler_Tag_Username implements XenForo_Template_Compiler_Tag_Interface
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
			unset($attributes['user']);
		}
		else
		{
			throw $compiler->getNewCompilerException(new XenForo_Phrase('missing_x_attribute_for_y_tag', array(
				'attribute' => 'user',
				'tag' => 'username'
			)));
		}

		if (!empty($attributes['rich']))
		{
			$rich = $compiler->parseConditionExpression($attributes['rich'], $options);
		}
		else
		{
			$rich = 'false';
		}
		unset($attributes['rich']);

		return 'XenForo_Template_Helper_Core::userNameHtml('
			. $user . ','
			. $compiler->compileAndCombineSegments($children, $options) . ','
			. $rich . ','
			. $compiler->getNamedParamsAsPhpCode($attributes, $options, array('code')) . ')';
	}
}