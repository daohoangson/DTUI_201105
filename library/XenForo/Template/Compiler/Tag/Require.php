<?php

/**
* Class to handle compiling template tag calls for "require".
*
* @package XenForo_Template
*/
class XenForo_Template_Compiler_Tag_Require implements XenForo_Template_Compiler_Tag_Interface
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
			throw $compiler->getNewCompilerException(new XenForo_Phrase('x_tags_only_used_where_full_statements_allowed', array('tag' => 'require')));
		}

		$requirements = $compiler->getNamedAttributes($attributes, array('css', 'js'));

		if (!$requirements)
		{
			throw $compiler->getNewCompilerException(new XenForo_Phrase('require_tag_does_not_specify_any_known_types_css_or_js'));
		}

		if (isset($requirements['css']))
		{
			$css = $requirements['css'];
			if (empty($css) || count($css) != 1 || !is_string($css[0]))
			{
				throw $compiler->getNewCompilerException(new XenForo_Phrase('only_literal_css_templates_may_be_included_by_require_tag'));
			}

			if (substr($css[0], -4) != '.css')
			{
				throw $compiler->getNewCompilerException(new XenForo_Phrase('all_required_css_templates_must_end_in_'));
			}

			$requirements['css'][0] = substr($css[0], 0, -4);
		}

		$statement = $compiler->getNewRawStatement();

		foreach ($requirements AS $attribute => $value)
		{
			$statement->addStatement('$this->addRequiredExternal(\'' . $compiler->escapeSingleQuotedString($attribute) . '\', '
				. $compiler->compileAndCombineSegments($value) . ");\n"
			);
		}

		return $statement;
	}
}