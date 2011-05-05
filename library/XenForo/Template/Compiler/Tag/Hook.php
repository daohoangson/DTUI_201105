<?php

/**
* Class to handle compiling template tag calls for "hook".
*
* @package XenForo_Template
*/
class XenForo_Template_Compiler_Tag_Hook implements XenForo_Template_Compiler_Tag_Interface
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
		if (empty($options['allowRawStatements']))
		{
			throw $compiler->getNewCompilerException(new XenForo_Phrase('x_tags_only_used_where_full_statements_allowed', array('tag' => 'hook')));
		}

		if (empty($attributes['name']))
		{
			throw $compiler->getNewCompilerException(new XenForo_Phrase('missing_attribute_x_for_tag_y', array(
				'attribute' => 'name',
				'tag' => 'hook'
			)));
		}

		$name = $compiler->compileAndCombineSegments($attributes['name'], $options);
		$compiled = $compiler->compileIntoVariable($children, $var, $options);

		if (!empty($attributes['params']))
		{
			$params = $compiler->compileAndCombineSegments($attributes['params'], array_merge($options, array('varEscape' => false)));
		}
		else
		{
			$params = 'array()';
		}

		$statement = $compiler->getNewRawStatement();
		$statement->addStatement($compiled);
		$statement->addStatement(
			'$' . $compiler->getOutputVar() . ' .= $this->callTemplateHook(' . $name . ', $' . $var . ', ' . $params . ");\n"
			. 'unset($' . $var . ");\n"
		);
		return $statement;
	}
}