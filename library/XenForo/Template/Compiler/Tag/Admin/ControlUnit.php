<?php

/**
* Class to handle compiling template tag calls for "controlunit" in admin areas.
*
* @package XenForo_Template
*/
class XenForo_Template_Compiler_Tag_Admin_ControlUnit extends XenForo_Template_Compiler_Tag_Admin_Abstract implements XenForo_Template_Compiler_Tag_Interface
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
		$rowOptions = $this->_getRowOptions($compiler, $attributes, $children);
		$data = $this->_getDataAttributes($compiler, $attributes);
		if ($data)
		{
			$rowOptions['_data'] = $data;
		}

		if (!isset($rowOptions['label']))
		{
			throw $compiler->getNewCompilerException(new XenForo_Phrase('all_template_unit_tags_must_specify_label'));
		}

		$data = $this->_compileStandardData($compiler, $options, $rowOptions);
		$rowOptions = $this->_compileRowOptions($compiler, $rowOptions, $options, $htmlCode, $htmlVar);

		return $this->_getCompiledOutput($compiler, 'controlUnit', "$data[label], $rowOptions", $htmlCode, $htmlVar);
	}
}