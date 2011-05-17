<?php

/**
* Abstract base for template tags that apply to the admin area.
*
* @package XenForo_Template
*/
abstract class XenForo_Template_Compiler_Tag_Admin_Abstract
{
	/**
	 * Standard options that apply to the row for (almost) all *unit tags.
	 *
	 * @var array
	 */
	protected static $_standardRowOptions = array(
		'label',
		'hint',
		'explain',
		'class'
	);

	/**
	 * Standard control-level options. These apply to most controls but not all.
	 * Use this if the control support most or all of the standard options.
	 *
	 * @var array
	 */
	protected static $_standardControlOptions = array(
		'id',
		'name',
		'value',
		'title',
		'inputclass',
		'listclass',
		'after',
	);

	protected static $_standardOptionTags = array(
		'option',
		'options',
		'optgroup',
		'foreach'
	);

	/**
	* Gets the standard data out of a list of segments. Data will be pulled from tags
	* in that list and their attributes only.
	*
	* @param XenForo_Template_Compiler
	* @param array List of segments
	* @param array An optional list of additional tags that are allowed
	*
	* @return array Keys are standard data types that were found
	*/
	protected function _getStandardAdminTagData(XenForo_Template_Compiler $compiler, array $segments, array $extraAllowedTags = array())
	{
		$segments = $compiler->prepareSegmentsForIteration($segments);

		$output = array();
		$foundContent = false;

		foreach ($segments AS $segment)
		{
			if (is_string($segment))
			{
				if (trim($segment) !== '')
				{
					$foundContent = true;
				}
				continue;
			}
			else if (!isset($segment['type']) || $segment['type'] != 'TAG')
			{
				$foundContent = true;
				continue;
			}

			switch ($segment['name'])
			{
				case 'label';
					$attributes = $segment['attributes'];

					if (isset($attributes['hidden']))
					{
						$output['labelHidden'] = $attributes['hidden'];
					}

					if (isset($attributes['hint']))
					{
						$output['hint'] = $attributes['hint'];
					}

					$output['label'] = $segment['children'];
				break;

				case 'hint':
				case 'explain':
				case 'html':
					$output[$segment['name']] = $segment['children'];
				break;

				default:
					if (!$extraAllowedTags || !in_array($segment['name'], $extraAllowedTags))
					{
						// all other tags are "content" tags
						$foundContent = true;
					}
			}
		}

		if ($output && $foundContent)
		{
			// found some known tags, some content -- error
			throw $compiler->getNewCompilerException(new XenForo_Phrase('found_unexpected_content_within_an_admin_control_tag'));
		}
		else if ($foundContent)
		{
			// content only -- treat it as HTML tag
			$output = array('html' => $segments);
		}

		return $output;
	}

	/**
	 * Gets the standard row options from attributes or child tags.
	 *
	 * @param XenForo_Template_Compiler $compiler
	 * @param array $attributes Attributes
	 * @param array $children Child elements
	 * @param array $extraAllowedTags List of extra allowed child tags (that won't be treated as "content")
	 *
	 * @return array Key-value pairs of row options
	 */
	protected function _getRowOptions(XenForo_Template_Compiler $compiler, array $attributes, array $children, array $extraAllowedTags = array())
	{
		return array_merge(
			$compiler->getNamedAttributes($attributes, self::$_standardRowOptions),
			$this->_getStandardAdminTagData($compiler, $children, $extraAllowedTags)
		);
	}

	/**
	 * Gets the control options for a given tag.
	 *
	 * @param XenForo_Template_Compiler $compiler
	 * @param array $attributes Raw key-value attribues list
	 * @param array $extraExpected Extra expected attributes, on top of the standard
	 * @param boolean $includeStandard If true, include the standard control tags
	 * @param boolean $allowData If true, allow all data-* attributes
	 *
	 * @return array Key-value pairs of expected attributes; data attributes in _data key
	 */
	protected function _getControlOptions(XenForo_Template_Compiler $compiler, array $attributes, array $extraExpected = array(),
		$includeStandard = true, $allowData = true
	)
	{
		if ($includeStandard)
		{
			$extraExpected = array_merge($extraExpected, self::$_standardControlOptions);
		}

		$controlOptions = $compiler->getNamedAttributes($attributes, $extraExpected);

		if ($allowData)
		{
			$data = $this->_getDataAttributes($compiler, $attributes);
			if ($data)
			{
				$controlOptions['_data'] = $data;
			}
		}

		return $controlOptions;
	}

	/**
	 * Gets all data-* attributes and returns them as an array without the
	 * data prefix.
	 *
	 * @param array $attributes
	 *
	 * @return array Key-value data pairs, without the "data-" prefix
	 */
	protected function _getDataAttributes(XenForo_Template_Compiler $compiler, array $attributes)
	{
		$data = array();

		foreach ($attributes AS $key => $value)
		{
			if (strtolower(substr($key, 0, 5)) == 'data-')
			{
				$dataKey = substr($key, 5);
				if (is_string($dataKey) && strlen($dataKey) >= 1)
				{
					if (strval(intval($dataKey)) === $dataKey)
					{
						throw $compiler->getNewCompilerException(new XenForo_Phrase('data_attributes_names_may_not_look_like_integers'));
					}

					$data[$dataKey] = $value;
				}
			}
		}

		return $data;
	}

	/**
	* Compiles the standard data and removes it from the optional extra data for
	* use as separate arguments.
	*
	* @param XenForo_Template_Compiler
	* @param array Options for the compiler
	* @param array Row options. Will be modified by reference.
	* @param array Control options. Will be modified by reference.
	*
	* @return array Standardized data (label, name, value)
	*/
	protected function _compileStandardData(XenForo_Template_Compiler $compiler, array $compilerOptions, array &$rowOptions, array &$controlOptions = array())
	{
		if (isset($rowOptions['label']))
		{
			$label = $rowOptions['label'];
			unset($rowOptions['label']);
		}
		else
		{
			$label = '';
		}

		if (isset($controlOptions['name']))
		{
			$name = $controlOptions['name'];
			unset($controlOptions['name']);
		}
		else
		{
			$name = '';
		}

		if (isset($controlOptions['value']))
		{
			$value = $controlOptions['value'];
			unset($controlOptions['value']);
		}
		else
		{
			$value = '';
		}

		return array(
			'label' => $compiler->compileAndCombineSegments($label, $compilerOptions),
			'name' => $compiler->compileAndCombineSegments($name, $compilerOptions),
			'value' => $compiler->compileAndCombineSegments($value, $compilerOptions)
		);
	}

	/**
	 * Helper to compile the standard row options with the default options.
	 *
	 * @param XenForo_Template_Compiler $compiler
	 * @param array $rowOptions
	 * @param array $compilerOptions
	 *
	 * @return string
	 */
	protected function _compileRowOptions(XenForo_Template_Compiler $compiler, array $rowOptions, array $compilerOptions,
		&$htmlCode = '', &$htmlOutputVar = '')
	{
		if (isset($rowOptions['html']))
		{
			$htmlCode = $compiler->compileIntoVariable($rowOptions['html'], $htmlOutputVar, $compilerOptions);
			unset($rowOptions['html']);
		}
		else
		{
			$htmlCode = '';
			$htmlOutputVar = '';
		}

		$params = $compiler->compileNamedParams($rowOptions, $compilerOptions, array('labelHidden'));
		if ($htmlCode !== '' && !empty($htmlOutputVar))
		{
			$params["html"] = '$' . $htmlOutputVar;
		}

		return $compiler->buildNamedParamCode($params);
	}

	/**
	 * Gets the choices that apply to this tag, via option/optgroup/options tags.
	 *
	 * @param array $children Child tags to search
	 * @param XenForo_Template_Compiler $compiler Compiler
	 * @param array $options Compiler options
	 * @param string $newOutputVar
	 *
	 * @return string
	 */
	protected function _getChoicesCode(array $children, XenForo_Template_Compiler $compiler, array $options, &$newOutputVar = '')
	{
		$oldOutputVar = $compiler->getOutputVar();
		$newOutputVar = $compiler->getUniqueVar();
		$compiler->setOutputVar($newOutputVar);

		$code = '$' . $newOutputVar . " = array();\n";

		foreach ($children AS $child)
		{
			$compiler->setLastVistedSegment($child);
			$code .= $this->_compileChoiceChild($newOutputVar, $child, $compiler, $options);
		}

		$compiler->setOutputVar($oldOutputVar);

		return $code;
	}

	protected function _compileChoiceChild($newOutputVar, $child, XenForo_Template_Compiler $compiler, array $options)
	{
		if ($compiler->isSegmentNamedTag($child, 'foreach'))
		{
			$inner = '';
			foreach ($child['children'] AS $grandChild)
			{
				$inner .= $this->_compileChoiceChild($newOutputVar, $grandChild, $compiler, $options);
			}
			$statement = XenForo_Template_Compiler_Tag_Foreach::compileForeach($inner, $compiler, $child['attributes'], $options);
			return $statement->getFullStatements($newOutputVar);
		}
		else if ($compiler->isSegmentNamedTag($child, 'option'))
		{
			$choice = $compiler->getNamedAttributes($child['attributes'], array('label', 'name', 'value', 'selected', 'hint', 'id', 'class', 'inputclass', 'title', 'depth', 'disabled'));

			$childrenAsLabel = (isset($choice['label']) ? false : true); // if label attribute, then assume children as "special"
			$foundOther = false;
			$disabledControls = array();

			foreach ($child['children'] AS $optionChild)
			{
				if (!is_array($optionChild) || !isset($optionChild['type']) || $optionChild['type'] != 'TAG')
				{
					continue;
				}

				$optionChildName = strtolower($optionChild['name']);

				switch ($optionChildName)
				{
					case 'label':
					case 'hint':
						$choice[$optionChildName] = $optionChild['children'];
						$childrenAsLabel = false;
						break;

					case 'checkbox':
					case 'combobox':
					case 'password':
					case 'radio':
					case 'select':
					case 'spinbox':
					case 'textbox':
					case 'upload':
						$disabledControls[] = $optionChild;
						$childrenAsLabel = false;
						break;

					case 'disabled':
						$disabledControls[] = $optionChild['children'];
						$childrenAsLabel = false;
						break;

					default:
						$foundOther = $optionChild;
				}
			}

			if (!isset($choice['label']))
			{
				if (!$childrenAsLabel)
				{
					throw $compiler->getNewCompilerException(new XenForo_Phrase('missing_label_for_option_tag'), $child);
				}

				$choice['label'] = $child['children'];
			}

			if (!$childrenAsLabel && $foundOther)
			{
				// have special tags as child and found unexpected
				throw $compiler->getNewCompilerException(new XenForo_Phrase('found_unexpected_tag_x_as_disabled_control', array('tag' => $foundOther['name'])), $foundOther);
			}

			$compiled = $compiler->compileNamedParams($choice, $options, array('selected'));

			$disabledCode = '';
			if ($disabledControls)
			{
				$compiled['disabled'] = array();

				foreach ($disabledControls AS $disabled)
				{
					$disabledCode .= $compiler->compileIntoVariable($disabled, $disabledOutputVar, $options, true);
					$compiled['disabled'][] = '$' . $disabledOutputVar;
				}
			}

			$additionalCode = $disabledCode . '$' . $newOutputVar . '[] = ' . $compiler->buildNamedParamCode($compiled) . ";\n";

			if (isset($compiled['disabled']))
			{
				$additionalCode .= 'unset(' . implode(', ', $compiled['disabled']) . ");\n";
			}

			if (!empty($child['attributes']['displayif']))
			{
				$condition = $compiler->parseConditionExpression($child['attributes']['displayif'], $options);
				return 'if ' . $condition . "\n{\n" . $additionalCode . "}\n";
			}
			else
			{
				return $additionalCode;
			}
		}
		else if ($compiler->isSegmentNamedTag($child, 'options'))
		{
			if (!isset($child['attributes']['source']))
			{
				throw $compiler->getNewCompilerException(new XenForo_Phrase('options_tag_must_have_source_attribute'), $child);
			}
			$sourceVar = $compiler->compileVarRef($child['attributes']['source'], array_merge($options, array('varEscape' => false)));

			if (!empty($child['attributes']['raw']))
			{
				$raw = $compiler->parseConditionExpression($child['attributes']['raw'], $options);
			}
			else
			{
				$raw = 'false';
			}

			return '$' . $newOutputVar . ' = XenForo_Template_Helper_Admin::mergeOptionArrays('
				. '$' . $newOutputVar . ', ' . $sourceVar . ', '. $raw . ");\n";
		}
		else if ($compiler->isSegmentNamedTag($child, 'optgroup'))
		{
			if (!isset($child['attributes']['label']))
			{
				throw $compiler->getNewCompilerException(new XenForo_Phrase('optgroups_must_have_label'), $child);
			}

			$label = $compiler->compileAndCombineSegments($child['attributes']['label']);

			$groupVar = null; // changed by next line
			$code = $this->_getChoicesCode($child['children'], $compiler, $options, $groupVar);
			$code .= '$' . $newOutputVar . '[' . $label . '] = $' . $groupVar . ";\n";
			$code .= 'unset($' . $groupVar . ");\n";

			return $code;
		}
		else
		{
			return '';
		}
	}

	/**
	 * Gets the compiled output for a function call, with extra preceding statements
	 * and variables to unset.
	 *
	 * @param XenForo_Template_Compiler $compiler
	 * @param string $function Function to call in XenForo_Template_Helper_Admin namespace
	 * @param string $args String of PHP code for args
	 * @param string|array $extraStatements List of extra, raw PHP statements to prepend
	 * @param string|array $unsetVars List of variables to unset at the end, not including leading
	 *
	 * @return XenForo_Template_Compiler_Statement_Raw
	 */
	protected function _getCompiledOutput(XenForo_Template_Compiler $compiler, $function, $args, $extraStatements = false, $unsetVars = false)
	{
		$statement = $compiler->getNewRawStatement();

		if (!is_array($extraStatements))
		{
			$extraStatements = array($extraStatements);
		}

		foreach ($extraStatements AS $extra)
		{
			if (is_string($extra) && $extra !== '')
			{
				$statement->addStatement($extra);
			}
		}

		$statement->addStatement(
			'$' . $compiler->getOutputVar() . ' .= XenForo_Template_Helper_Admin::' . $function . '(' . $args . ");\n"
		);

		if (is_string($unsetVars))
		{
			$unsetVars = array($unsetVars);
		}

		if (is_array($unsetVars))
		{
			$unset = '';
			foreach ($unsetVars AS $unsetVar)
			{
				if (!is_string($unsetVar) || $unsetVar === '')
				{
					continue;
				}
				if ($unset)
				{
					$unset .= ', ';
				}
				$unset .= '$' . $unsetVar;
			}

			if ($unset)
			{
				$statement->addStatement('unset(' . $unset . ");\n");
			}
		}

		return $statement;
	}

}