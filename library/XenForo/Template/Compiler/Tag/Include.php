<?php

/**
* Class to handle compiling template tag calls for "include".
*
* @package XenForo_Template
*/
class XenForo_Template_Compiler_Tag_Include implements XenForo_Template_Compiler_Tag_Interface
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
			throw $compiler->getNewCompilerException(new XenForo_Phrase('x_tags_only_used_where_full_statements_allowed', array('tag' => 'include')));
		}

		if (empty($attributes['template']) || count($attributes['template']) != 1 || !is_string($attributes['template'][0]))
		{
			throw $compiler->getNewCompilerException(new XenForo_Phrase('invalid_template_include_specified'));
		}

		$include = $attributes['template'][0];
		$template = $compiler->includeParsedTemplate($include);

		$statement = $compiler->getNewRawStatement();
		$tempVars = array();

		if ($template)
		{
			$mapVars = array();
			foreach ($children AS $child)
			{
				if ($compiler->isSegmentNamedTag($child, 'map'))
				{
					$childAttr = $child['attributes'];
					if (empty($childAttr['from']) || empty($childAttr['to']))
					{
						throw $compiler->getNewCompilerException(new XenForo_Phrase('included_template_variable_mappings_must_include_from_and_to_attributes'));
					}

					$from = $compiler->compileVarRef($childAttr['from'], $options);

					if (count($childAttr['to']) != 1 || !is_string($childAttr['to'][0]))
					{
						throw $compiler->getNewCompilerException(new XenForo_Phrase('invalid_template_include_variable_mapping_specified'));
					}

					if (!preg_match('#^\$([a-zA-Z_][a-zA-Z0-9_]*)$#', $childAttr['to'][0]))
					{
						throw $compiler->getNewCompilerException(new XenForo_Phrase('invalid_template_include_variable_mapping_specified'));
					}

					// "from $outer" and "to $inner"; when processed (within inner template), need to map the other direction.
					$mapVars[substr($childAttr['to'][0], 1)] = substr($from, 1);
				}
				else if ($compiler->isSegmentNamedTag($child, 'set'))
				{
					// take var as "to" and compile into a temporary variable
					$childAttr = $child['attributes'];
					if (empty($childAttr['var']))
					{
						throw $compiler->getNewCompilerException(new XenForo_Phrase('included_template_variable_assignments_must_include_var_attribute'));
					}

					if (count($childAttr['var']) != 1 || !is_string($childAttr['var'][0]))
					{
						throw $compiler->getNewCompilerException(new XenForo_Phrase('invalid_template_include_variable_assignment_specified'));
					}

					$mapRegex = '#^\$([a-zA-Z_][a-zA-Z0-9_]*)$#';

					if (!preg_match($mapRegex, $childAttr['var'][0]))
					{
						throw $compiler->getNewCompilerException(new XenForo_Phrase('invalid_template_include_variable_assignment_specified'));
					}

					$childOutput = $compiler->compileIntoVariable($child['children'], $setVar, $options);
					$statement->addStatement($childOutput);
					$mapVars[substr($childAttr['var'][0], 1)] = $setVar;
					$tempVars[] = $setVar;
				}
			}

			$oldMap = $compiler->getVariableMap();
			$compiler->setVariableMap($mapVars, true);

			$compiled = $compiler->compileIntoVariable($template, $var, $options);
			$tempVars[] = $var;

			$compiler->setVariableMap($oldMap);

			$statement->addStatement($compiled);
			$statement->addStatement(
				'$' . $compiler->getOutputVar() . ' .= $' . $var . ";\n"
				. 'unset($' . implode(', $', $tempVars) . ");\n"
			);
			return $statement;
		}
		else
		{
			return '';
		}
	}
}