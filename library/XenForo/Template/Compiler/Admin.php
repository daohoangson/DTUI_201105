<?php

/**
 * Template compiler for administrative templates.
 *
 * @package XenForo_Template
 */
class XenForo_Template_Compiler_Admin extends XenForo_Template_Compiler
{
	/**
	 * The type of compiler. This should be unique per class, based on the source
	 * for things like included templates, etc.
	 *
	 * @var string
	 */
	protected static $_compilerType = 'admin';

	/**
	 * Set up the defaults. Primarily sets up the handlers for various functions/tags.
	 */
	protected function _setupDefaults()
	{
		parent::_setupDefaults();

		$this->addFunctionHandlers(array(
			'adminpagenav' => new XenForo_Template_Compiler_Function_PageNav(),
		));

		$this->addTagHandlers(array(
			'textboxunit'  => new XenForo_Template_Compiler_Tag_Admin_TextBoxUnit(),
			'textbox'      => new XenForo_Template_Compiler_Tag_Admin_TextBoxUnit(),

			'passwordunit' => new XenForo_Template_Compiler_Tag_Admin_PasswordUnit(),
			'password'     => new XenForo_Template_Compiler_Tag_Admin_PasswordUnit(),

			'uploadunit'   => new XenForo_Template_Compiler_Tag_Admin_UploadUnit(),
			'upload'       => new XenForo_Template_Compiler_Tag_Admin_UploadUnit(),

			'selectunit'   => new XenForo_Template_Compiler_Tag_Admin_SelectUnit(),
			'select'       => new XenForo_Template_Compiler_Tag_Admin_SelectUnit(),

			'radiounit'    => new XenForo_Template_Compiler_Tag_Admin_RadioUnit(),
			'radio'        => new XenForo_Template_Compiler_Tag_Admin_RadioUnit(),

			'checkboxunit' => new XenForo_Template_Compiler_Tag_Admin_CheckBoxUnit(),
			'checkbox'     => new XenForo_Template_Compiler_Tag_Admin_CheckBoxUnit(),

			'spinboxunit'  => new XenForo_Template_Compiler_Tag_Admin_SpinBoxUnit(),
			'spinbox'  => new XenForo_Template_Compiler_Tag_Admin_SpinBoxUnit(),

			'comboboxunit' => new XenForo_Template_Compiler_Tag_Admin_ComboBoxUnit(),
			'combobox' => new XenForo_Template_Compiler_Tag_Admin_ComboBoxUnit(),

			// these don't have corresponding non-unit versions
			'controlunit'  => new XenForo_Template_Compiler_Tag_Admin_ControlUnit(),
			'submitunit'   => new XenForo_Template_Compiler_Tag_Admin_SubmitUnit(),

			'form'   => new XenForo_Template_Compiler_Tag_Admin_Form(),

			'listitem'     => new XenForo_Template_Compiler_Tag_Admin_ListItem(),
			'popup'        => new XenForo_Template_Compiler_Tag_Admin_Popup(),
			'adminpagenav' => new XenForo_Template_Compiler_Tag_PageNav(),
		));

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
		$template = XenForo_Model::create('XenForo_Model_AdminTemplate')->getAdminTemplateByTitle($title);
		if (isset($template['template_parsed']))
		{
			return array(
				'id' => $template['template_id'],
				'data' => unserialize($template['template_parsed'])
			);
		}
		else
		{
			return false;
		}
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
	* @param integer|true $styleId Style ID to reset the cache for; true for all
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