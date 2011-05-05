<?php

/**
* Renderer for install templates.
*
* @package XenForo_Core
*/
class XenForo_Template_Install extends XenForo_Template_Abstract
{
	/**
	* Base path to compiled templates that are stored on disk.
	*
	* @var string
	*/
	protected static $_filePath = '';

	/**
	 * Extra container data from template renders.
	 *
	 * @var array
	 */
	protected static $_extraData = array();

	/**
	 * Internal template rendering.
	 *
	 * @param string $__template Template text or name of template file
	 * @param array $__extraData Returned extra data from the render
	 *
	 * @return string Rendered template
	 */
	protected function _renderInternal($__template, &$__extraData)
	{
		extract($this->_params);

		$__extraData = array();

		ob_start();
		include($__template);
		return ob_get_clean();
	}

	/**
	 * Gets the URL to fetch the list of required CSS templates. Requirements
	 * should be a list of CSS templates, not including the trailing ".css".
	 *
	 * @param array $requirements
	 *
	 * @return string
	 */
	public function getRequiredCssUrl(array $requirements)
	{
		return '';
	}

	/**
	* Goes to the data source to load the list of templates.
	*
	* @param array Template list
	*
	* @return array Key-value pairs of template titles/compiled templates
	*/
	protected function _getTemplatesFromDataSource(array $templateList)
	{
		return array();
	}

	/**
	* Helper function get the list of templates that are waiting to be loaded.
	*
	* @return array
	*/
	public function getToLoadList()
	{
		return array();
	}

	/**
	* Resets the to load list to empty.
	*/
	protected function _resetToLoadList() {}

	/**
	* Merges key-value pairs of template names/compiled templates into the local template
	* cache.
	*
	* @param array Templates (key: name, value: compiled output)
	*/
	protected function _mergeIntoTemplateCache(array $templates) {}

	/**
	* Non-static method for pre-loading a template.
	*
	* @param string Template name
	*/
	protected function _preloadTemplate($templateName) {}

	/**
	* Loads a template out of the local template cache. If the template does not
	* exist, it will be set to an empty string. This will be overwritten if
	* the template is loaded from the data source.
	*
	* @param string Template name
	*
	* @return string Compiled template
	*/
	protected function _loadTemplateFromCache($templateName)
	{
		return '';
	}

	/**
	* Loads the file path where a template is located in the file system, if
	* templates are being stored in the file system.
	*
	* @param string Template name
	*
	* @param string Empty string (not using file system) or file path
	*/
	protected function _loadTemplateFilePath($templateName)
	{
		return self::$_filePath . '/' . preg_replace('/[^a-z0-9_\.-]/i', '', $templateName) . '.php';
	}

	/**
	* Determines whether we are using templates in the file system.
	*
	* @return boolean
	*/
	protected function _usingTemplateFiles()
	{
		return true;
	}

	/**
	* Gets the list of required external resources.
	*
	* @return array
	*/
	protected function _getRequiredExternals()
	{
		return array();
	}

	/**
	* Sets the list of required external resources.
	*
	* @param array
	*/
	protected function _setRequiredExternals(array $required) {}

	/**
	 * Merges in extra container data from the template render.
	 *
	 * @param array
	 */
	protected function _mergeExtraContainerData(array $extraData)
	{
		self::$_extraData = XenForo_Application::mapMerge(self::$_extraData, $extraData);
	}

	/**
	 * Gets extra container data.
	 *
	 * @return array
	 */
	public static function getExtraContainerData()
	{
		return self::$_extraData;
	}

	/**
	* Specify a template that needs to be preloaded for use later. This is useful
	* if you think a render is going to be called before the template you require
	* is to be used.
	*
	* @param string Template to preload
	*/
	public static function preloadTemplate($templateName) {}

	/**
	* Manually sets a template. This is primarily useful for testing.
	*
	* @param string Name of the template
	* @param string Value for the template
	*/
	public static function setTemplate($templateName, $templateValue) {}

	/**
	* Resets the template system state.
	*/
	public static function reset() {}

	/**
	 * Sets the path to the templates.
	 *
	 * @param string $filePath
	 */
	public static function setFilePath($filePath)
	{
		self::$_filePath = $filePath;
	}
}