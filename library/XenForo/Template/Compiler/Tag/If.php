<?php

/**
* Class to handle compiling template tag calls for if/elseif/else/contentcheck.
*
* @package XenForo_Template
*/
class XenForo_Template_Compiler_Tag_If implements XenForo_Template_Compiler_Tag_Interface
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
		if ($tag == 'contentcheck')
		{
			throw $compiler->getNewCompilerException(new XenForo_Phrase('contentcheck_tag_found_that_was_not_direct_child_of_an_if_tag_with'));
		}
		else if ($tag != 'if')
		{
			throw $compiler->getNewCompilerException(new XenForo_Phrase('else_or_else_if_tag_not_found_that_was_not_direct_child_of_an_if_tag'));
		}

		if (empty($options['allowRawStatements']))
		{
			throw $compiler->getNewCompilerException(new XenForo_Phrase('x_tags_only_used_where_full_statements_allowed', array('tag' => 'if')));
		}

		$parts = array(
			0 => array(
				'is' => isset($attributes['is']) ? $attributes['is'] : '',
				'hascontent' => isset($attributes['hascontent']) ? $attributes['hascontent'] : '',
				'segments' => array(),
				'line' => $compiler->getLineNumber()
			)
		);
		$partKey = 0;

		$haveElse = false;

		foreach ($children AS $child)
		{
			if ($compiler->isSegmentNamedTag($child, 'elseif'))
			{
				if ($haveElse)
				{
					throw $compiler->getNewCompilerException(new XenForo_Phrase('else_if_tag_found_after_else_tag'), $child);
				}

				if (!empty($child['children']))
				{
					throw $compiler->getNewCompilerException(new XenForo_Phrase('else_if_tags_may_not_have_children'), $child);
				}

				$partKey++;
				$parts[$partKey] = array(
					'is' => isset($child['attributes']['is']) ? $child['attributes']['is'] : '',
					'hascontent' => isset($child['attributes']['hascontent']) ? $child['attributes']['hascontent'] : '',
					'segments' => array(),
					'line' => $child['line']
				);
			}
			else if ($compiler->isSegmentNamedTag($child, 'else'))
			{
				if (!empty($child['children']))
				{
					throw $compiler->getNewCompilerException(new XenForo_Phrase('else_tags_may_not_have_children'), $child);
				}

				$haveElse = true;

				$partKey++;
				$parts[$partKey] = array(
					'else' => true,
					'segments' => array(),
					'line' => $child['line']
				);
			}
			else
			{
				$parts[$partKey]['segments'][] = $child;
			}
		}

		$ifStatement = $compiler->getNewRawStatement();
		$prependCheckStatements = $compiler->getNewRawStatement();
		$allCheckVars = array();

		foreach ($parts AS $partKey => $part)
		{
			$conditionType = ($partKey == 0 ? 'if' : 'else if');

			if (!empty($part['is']))
			{
				$condition = $compiler->parseConditionExpression($part['is'], $options);

				$ifStatement->addStatement($conditionType . ' ' . $condition . "\n{\n")
					->addStatement($compiler->compileSegments($part['segments'], $options))
					->addStatement("}\n");
			}
			else if (!empty($part['hascontent']))
			{
				$childStatement = $compiler->getNewStatementCollection();
				$checkVars = array();

				foreach ($part['segments'] AS $segment)
				{
					if ($compiler->isSegmentNamedTag($segment, 'contentcheck'))
					{
						$prependCheckStatements->addStatement(
							$compiler->compileIntoVariable($segment['children'], $checkVar, $options)
						);

						$childStatement->addStatement('$' . $checkVar);

						$checkVars[] = $checkVar;
						$allCheckVars[] = '$' . $checkVar;
					}
					else
					{
						$childStatement->addStatement($compiler->compileSegment($segment, $options));
					}
				}

				if (!$checkVars)
				{
					throw $compiler->getNewCompilerException(
						new XenForo_Phrase('cannot_have_content_checking_if_tag_without_contentcheck_part'), $part['line']
					);
				}

				$conditionParts = array();
				foreach ($checkVars AS $checkCondition)
				{
					$conditionParts[] = 'trim($' . $checkCondition . ") !== ''";
				}

				$checkCond = implode(' || ', $conditionParts);

				$ifStatement->addStatement("$conditionType ($checkCond)\n{\n")
                	->addStatement($childStatement)
					->addStatement("}\n");
			}
			else if (!empty($part['else']))
			{
				$ifStatement->addStatement("else\n{\n")
					->addStatement($compiler->compileSegments($part['segments'], $options))
					->addStatement("}\n");
			}
			else
			{
				throw $compiler->getNewCompilerException(
					new XenForo_Phrase('invalid_if_or_else_if_tag_missing_is_or_hascontent'), $part['line']
				);
			}
		}

		$prependCheckStatements->addStatement($ifStatement);

		if ($allCheckVars)
		{
			$prependCheckStatements->addStatement("unset(" . implode(', ', $allCheckVars) . ");\n");
		}

		return $prependCheckStatements;
	}
}