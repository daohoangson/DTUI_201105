<?php

/**
* Class to handle compiling template tag calls for "popup" in admin areas.
*
* @package XenForo_Template
*/
class XenForo_Template_Compiler_Tag_Admin_Popup extends XenForo_Template_Compiler_Tag_Admin_Abstract implements XenForo_Template_Compiler_Tag_Interface
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
		return self::compilePopup($compiler, $attributes, $children, $options, 'div');
	}

	/**
	 * Helper to compile a popup statically. Used to allow list rows to modify
	 * the compilation behavior.
	 *
	 * @param XenForo_Template_Compiler $compiler
	 * @param array $attributes
	 * @param array $children Child tags
	 * @param array $options Compiler option
	 * @param string $wrapTag The HTML tag to wrap with (probably div or li)
	 * @param string $extraMenuClass An extra menu class (or multiple to add)
	 *
	 * @return XenForo_Compiler_Statement_Raw
	 */
	public static function compilePopup(XenForo_Template_Compiler $compiler, array $attributes, array $children, array $options,
		$wrapTag, $extraMenuClass = ''
	)
	{
		if (!isset($attributes['title']))
		{
			throw $compiler->getNewCompilerException(new XenForo_Phrase('popups_must_specify_title'));
		}

		$choiceOutputVar = $compiler->getUniqueVar();
		$choicesCode = self::compilePopupChildren($choiceOutputVar, $children, $compiler, $options);

		$controlData = $compiler->getNamedParamsAsPhpCode($attributes, $options);

		$statement = $compiler->getNewRawStatement();
		$statement->addStatement('$' . $choiceOutputVar . " = array();\n");
		$statement->addStatement($choicesCode);
		$statement->addStatement(
			'$' . $compiler->getOutputVar() . ' .= XenForo_Template_Helper_Admin::popup('
			. $controlData . ', $' . $choiceOutputVar . ', \'' . $compiler->escapeSingleQuotedString($wrapTag)
			. '\', \'' . $compiler->escapeSingleQuotedString($extraMenuClass) . "');\n"
		);
		$statement->addStatement('unset($' . $choiceOutputVar . ");\n");

		return $statement;
	}

	/**
	 * Compiles the children of a popup.
	 *
	 * @param string $newOutputVar Name of the compiler output var
	 * @param array $children Children of popup
	 * @param XenForo_Template_Compiler $compiler
	 * @param array $options Compiler options
	 *
	 * @return string|XenForo_Template_Statement_Raw
	 */
	public static function compilePopupChildren($newOutputVar, array $children, XenForo_Template_Compiler $compiler, array $options)
	{
		$oldOutputVar = $compiler->getOutputVar();
		$compiler->setOutputVar($newOutputVar);

		$code = array();

		foreach ($children AS $child)
		{
			if ($compiler->isSegmentNamedTag($child, 'foreach'))
			{
				$inner = self::compilePopupChildren($newOutputVar, $child['children'], $compiler, $options);
				$code[] = XenForo_Template_Compiler_Tag_Foreach::compileForeach($inner, $compiler, $child['attributes'], $options);
				continue;
			}

			$choice = false;
			$tempVar = false;

			if ($compiler->isSegmentNamedTag($child, 'link'))
			{
				if (!isset($child['attributes']['href']))
				{
					throw $compiler->getNewCompilerException(new XenForo_Phrase('popup_links_must_specify_an_href'), $child);
				}

				$choice = array(
					'href' => $compiler->compileAndCombineSegments($child['attributes']['href'], $options),
					'text' => $compiler->compileAndCombineSegments($child['children'], $options),
				);
			}
			else if ($compiler->isSegmentNamedTag($child, 'html'))
			{
				$code[] = $compiler->compileIntoVariable($child['children'], $htmlOutputVar, $options);
				$choice = array(
					'html' => '$' . $htmlOutputVar,
				);
				$tempVar = '$' . $htmlOutputVar;
			}

			if ($choice)
			{
				$choiceCode = '$' . $newOutputVar . '[] = ' . $compiler->buildNamedParamCode($choice) . ";\n";
				if ($tempVar)
				{
					$choiceCode .= 'unset(' . $tempVar . ");\n";
				}

				if (isset($child['attributes']['displayif']))
				{
					$condition = $compiler->parseConditionExpression($child['attributes']['displayif'], $options);
					$code[] = 'if ' . $condition . "\n{\n" . $choiceCode . "}\n";
				}
				else
				{
					$code[] = $choiceCode;
				}
			}
		}

		$compiler->setOutputVar($oldOutputVar);

		if ($code)
		{
			$statement = $compiler->getNewRawStatement();
			foreach ($code AS $codeStatement)
			{
				$statement->addStatement($codeStatement);
			}
			return $statement;
		}
		else
		{
			return '';
		}
	}
}