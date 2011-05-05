<?php

/**
* Class to handle compiling template tag calls for "foreach".
*
* @package XenForo_Template
*/
class XenForo_Template_Compiler_Tag_Foreach implements XenForo_Template_Compiler_Tag_Interface
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
			throw $compiler->getNewCompilerException(new XenForo_Phrase('x_tags_only_used_where_full_statements_allowed', array('tag' => 'foreach')));
		}

		return self::compileForeach(
			$compiler->compileSegments($children),
			$compiler, $attributes, $options
		);
	}

	/**
	 * Helper to allow foreach tags to be compiled in multiple places with easier validation.
	 *
	 * @param string $inner Compiled inner code
	 * @param XenForo_Template_Compiler $compiler
	 * @param array $attributes
	 * @param array $options
	 *
	 * @return XenForo_Compiler_Statement_Raw
	 */
	public static function compileForeach($inner, XenForo_Template_Compiler $compiler, array $attributes, array $options)
	{
		if (empty($attributes['loop']))
		{
			throw $compiler->getNewCompilerException(new XenForo_Phrase('missing_attribute_x_for_tag_y', array(
				'attribute' => 'loop',
				'tag' => 'foreach'
			)));
		}

		if (empty($attributes['value']))
		{
			throw $compiler->getNewCompilerException(new XenForo_Phrase('missing_attribute_x_for_tag_y', array(
				'attribute' => 'value',
				'tag' => 'foreach'
			)));
		}

		$noMapOptions = array_merge($options, array('disableVarMap' => true));

		$loop = $compiler->compileVarRef($attributes['loop'], $options);
		$value = $compiler->compileVarRef($attributes['value'], $noMapOptions);

		if (isset($attributes['key']))
		{
			$key = $compiler->compileVarRef($attributes['key'], $noMapOptions);
			$keyCode = $key . ' => ';
		}
		else
		{
			$keyCode = '';
		}

		if (isset($attributes['i']))
		{
			$i = $compiler->compileVarRef($attributes['i'], $noMapOptions);
		}
		else
		{
			$i = '';
		}

		if (isset($attributes['count']))
		{
			$count = $compiler->compileVarRef($attributes['count'], $noMapOptions);
		}
		else
		{
			$count = '';
		}

		$statement = $compiler->getNewRawStatement();

		if ($i)
		{
			$statement->addStatement($i . " = 0;\n");
		}

		if ($count)
		{
			$statement->addStatement($count . ' = count(' . $loop . ");\n");
		}

		$statement->addStatement('foreach (' . $loop . ' AS ' . $keyCode . $value . ")\n{\n");

		if ($i)
		{
			$statement->addStatement($i . "++;\n");
		}

		$statement->addStatement($inner)->addStatement("}\n");

		return $statement;
	}
}