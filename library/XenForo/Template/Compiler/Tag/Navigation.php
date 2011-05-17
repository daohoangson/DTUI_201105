<?php

/**
* Class to handle compiling template tag calls for "navigation" and "breadcrumb".
*
* @package XenForo_Template
*/
class XenForo_Template_Compiler_Tag_Navigation implements XenForo_Template_Compiler_Tag_Interface
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
		if ($tag == 'breadcrumb')
		{
			throw $compiler->getNewCompilerException(new XenForo_Phrase('breadcrumb_tag_must_be_within_navigation_tag'));
		}

		if (empty($options['allowRawStatements']))
		{
			throw $compiler->getNewCompilerException(new XenForo_Phrase('x_tags_only_used_where_full_statements_allowed', array('tag' => 'navigation')));
		}

		$rawStatement = $compiler->getNewRawStatement();
		$rawStatement->addStatement("\$__extraData['navigation'] = array();\n");

		foreach ($children AS $child)
		{
			if ($compiler->isSegmentNamedTag($child, 'breadcrumb'))
			{
				if (isset($child['attributes']['source']))
				{
					$sourceVar = $compiler->compileVarRef($child['attributes']['source'], $options);
					$rawStatement->addStatement(
						'$__extraData[\'navigation\'] = XenForo_Template_Helper_Core::appendBreadCrumbs($__extraData[\'navigation\'], '
						. $sourceVar . ");\n"
					);
				}
				else
				{
					$parts = array();
					foreach ($child['attributes'] AS $name => $value)
					{
						$parts[] = "'" . $compiler->escapeSingleQuotedString($name) . "' => " . $compiler->compileAndCombineSegments($value, $options);
					}

					$parts[] = "'value' => " . $compiler->compileAndCombineSegments($child['children'], $options);

					$rawStatement->addStatement('$__extraData[\'navigation\'][] = array(' . implode(', ', $parts) . ");\n");
				}
			}
			else if (is_string($child) && trim($child) === '')
			{
				// whitespace -- ignore it
			}
			else
			{
				throw $compiler->getNewCompilerException(new XenForo_Phrase('invalid_data_found_in_navigation_tag'), $child);
			}
		}

		return $rawStatement;
	}
}