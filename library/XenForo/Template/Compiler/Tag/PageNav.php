<?php

/**
* Class to handle compiling template tag calls for "pagenav" and "adminpagenav".
*
* @package XenForo_Template
*/
class XenForo_Template_Compiler_Tag_PageNav implements XenForo_Template_Compiler_Tag_Interface
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
		if (empty($attributes['perpage']))
		{
			throw $compiler->getNewCompilerException(new XenForo_Phrase('missing_attribute_x_for_tag_y', array(
				'attribute' => 'perpage',
				'tag' => 'pagenav'
			)));
		}
		if (empty($attributes['total']))
		{
			throw $compiler->getNewCompilerException(new XenForo_Phrase('missing_attribute_x_for_tag_y', array(
				'attribute' => 'total',
				'tag' => 'pagenav'
			)));
		}
		if (empty($attributes['page']))
		{
			throw $compiler->getNewCompilerException(new XenForo_Phrase('missing_attribute_x_for_tag_y', array(
				'attribute' => 'page',
				'tag' => 'pagenav'
			)));
		}
		if (empty($attributes['link']))
		{
			throw $compiler->getNewCompilerException(new XenForo_Phrase('missing_attribute_x_for_tag_y', array(
				'attribute' => 'link',
				'tag' => 'pagenav'
			)));
		}

		if (!empty($attributes['linkdata']))
		{
			$linkData = $compiler->compileAndCombineSegments($attributes['linkdata'], array_merge($options, array('varEscape' => false)));
		}
		else
		{
			$linkData = 'false';
		}

		if (!empty($attributes['unreadlink']))
		{
			$unreadLink = $compiler->compileAndCombineSegments($attributes['unreadlink'], $options);
		}
		else
		{
			$unreadLink = 'false';
		}

		if (!empty($attributes['linkparams']))
		{
			$linkParams = $compiler->compileAndCombineSegments($attributes['linkparams'], array_merge($options, array('varEscape' => false)));
		}
		else
		{
			$linkParams = 'array()';
		}

		if (!empty($attributes['options']))
		{
			$tagOptions = $compiler->compileAndCombineSegments($attributes['options'], array_merge($options, array('varEscape' => false)));
		}
		else
		{
			$tagOptions = 'array()';
		}

		$type = ($tag == 'adminpagenav' ? 'admin' : 'public');

		return 'XenForo_Template_Helper_Core::pageNavTag('
			. '\'' . $type . '\', '
			. $compiler->compileAndCombineSegments($attributes['perpage'], $options) . ', '
			. $compiler->compileAndCombineSegments($attributes['total'], $options) . ', '
			. $compiler->compileAndCombineSegments($attributes['page'], $options) . ', '
			. $compiler->compileAndCombineSegments($attributes['link'], $options) . ', '
			. $linkData . ', ' . $linkParams . ', ' . $unreadLink . ', ' . $tagOptions . ')';
	}
}