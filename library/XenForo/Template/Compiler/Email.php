<?php

// TODO: potential problem with email compiler: style properties (can't come from current style, where do they come from?)

/**
 * Template compiler for email templates.
 *
 * @package XenForo_Template
 */
class XenForo_Template_Compiler_Email extends XenForo_Template_Compiler
{
	/**
	 * The type of compiler. This should be unique per class, based on the source
	 * for things like included templates, etc.
	 *
	 * @var string
	 */
	protected static $_compilerType = 'email';

	/**
	 * Set up the defaults. Primarily sets up the handlers for various functions/tags.
	 */
	protected function _setupDefaults()
	{
		parent::_setupDefaults();

		unset($this->_tagHandlers['include']);
	}

	/**
	* Helper to go to the model to get the parsed version of the specified template.
	*
	* @param string $title Title of template
	*
	* @return false|array
	*/
	protected function _getParsedTemplateFromModel($title, $styleId)
	{
		// include non-functional
		return false;
	}

	/**
	 * Gets the compiler type. This method generally needs to be overridden
	 * in child classes because of the lack of LSB.
	 *
	 * @return string
	 */
	public function getCompilerType()
	{
		return self::$_compilerType;
	}

	/**
	* Adds parsed templates to the template cache for the specified style.
	*
	* @param array $templates Keys are template names, values are parsed vesions of templates
	* @param integer $styleId ID of the style that the templates are from
	*/
	public static function setTemplateCache(array $templates, $styleId = 0)
	{
		self::_setTemplateCache($templates, $styleId, self::$_compilerType);
	}

	/**
	* Helper to reset the template cache to reclaim memory or for tests.
	*
	* @param integer|true $styleId Style ID to reset the cache for; true for all styles
	*/
	public static function resetTemplateCache($styleId = true)
	{
		self::_resetTemplateCache($styleId, self::$_compilerType);
	}

	/**
	 * Removes the named template from the compiler cache.
	 *
	 * @param string $title
	 */
	public static function removeTemplateFromCache($title)
	{
		self::_removeTemplateFromCache($title, self::$_compilerType);
	}
}