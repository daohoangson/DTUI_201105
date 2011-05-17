<?php

/**
* Class to handle compiling template tag calls for "passwordunit" in admin areas.
*
* @package XenForo_Template
*/
class XenForo_Template_Compiler_Tag_Admin_PasswordUnit extends XenForo_Template_Compiler_Tag_Admin_Abstract implements XenForo_Template_Compiler_Tag_Interface
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
		$hasWrapper = ($tag == 'passwordunit');

		if ($hasWrapper)
		{
			$rowOptions = $this->_getRowOptions($compiler, $attributes, $children);
			if (!isset($rowOptions['label']))
			{
				throw $compiler->getNewCompilerException(new XenForo_Phrase('all_template_unit_tags_must_specify_label'));
			}
		}
		else
		{
			$rowOptions = array();
		}

		$controlOptions = $this->_getControlOptions($compiler, $attributes, array('maxlength', 'size'));

		if (!isset($controlOptions['name']))
		{
			throw $compiler->getNewCompilerException(new XenForo_Phrase('password_tags_must_specify_name'));
		}

		$data = $this->_compileStandardData($compiler, $options, $rowOptions, $controlOptions);
		$controlOptions = $compiler->getNamedParamsAsPhpCode($controlOptions, $options);

		if ($hasWrapper)
		{
			$rowOptions = $this->_compileRowOptions($compiler, $rowOptions, $options, $htmlCode, $htmlVar);
			$function = 'passwordUnit';
			$args = "$data[label], $data[name], $data[value], $rowOptions, $controlOptions";
		}
		else
		{
			$htmlCode = '';
			$htmlVar = '';
			$function = 'password';
			$args = "$data[name], $data[value], $controlOptions";
		}

		return $this->_getCompiledOutput($compiler, $function, $args, $htmlCode, $htmlVar);
	}
}