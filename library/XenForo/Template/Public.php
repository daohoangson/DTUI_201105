<?php

/**
* Renderer for public-facing templates.
*
* @package XenForo_Core
*/
class XenForo_Template_Public extends XenForo_Template_Abstract
{
	/**
	* Cached template data. Key is the template name; value is the compiled template.
	*
	* @var array
	*/
	protected static $_templateCache = array();

	/**
	* A list of templates that still need to be loaded. Key is the template name.
	*
	* @var array
	*/
	protected static $_toLoad = array();

	/**
	* Base path to compiled templates that are stored on disk.
	*
	* @var string
	*/
	protected static $_filePath = '';

	/**
	 * The ID of the style that templates will be retrieved from.
	 *
	 * @var integer
	 */
	protected static $_styleId = 0;

	/**
	* Array of required external resources for this type of template.
	*
	* @var array
	*/
	protected static $_required = array();

	/**
	 * Extra container data from template renders.
	 *
	 * @var array
	 */
	protected static $_extraData = array();

	/**
	 * Sets the style ID that templates will be retrieved from.
	 *
	 * @param integer $styleId
	 */
	public static function setStyleId($styleId)
	{
		self::$_styleId = intval($styleId);
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
		sort($requirements);

		if (isset($this->_params['visitorStyle']['last_modified_date']))
		{
			$modDate = intval($this->_params['visitorStyle']['last_modified_date']);
		}
		else
		{
			$modDate = 0;
		}

		return 'css.php?css=' . implode(',', array_map('urlencode', $requirements))
			. '&style=' . self::$_styleId
			. '&d=' . $modDate;
	}

	/**
	* Renders the specified template and returns the output.
	*
	* @return string
	*/
	public function render()
	{
		if (!isset($this->_params['_styleId']))
		{
			$this->_params['_styleId'] = self::$_styleId;
		}

		return parent::render();
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
		$db = XenForo_Application::get('db');

		return $db->fetchPairs('
			SELECT title, template_compiled
			FROM xf_template_compiled
			WHERE title IN (' . $db->quote($templateList) . ')
				AND style_id = ?
				AND language_id = ?
		', array(self::$_styleId, self::$_languageId));
	}

	/**
	* Helper function get the list of templates that are waiting to be loaded.
	*
	* @return array
	*/
	public function getToLoadList()
	{
		return self::$_toLoad;
	}

	/**
	* Resets the to load list to empty.
	*/
	protected function _resetToLoadList()
	{
		self::$_toLoad = array();
	}

	/**
	* Merges key-value pairs of template names/compiled templates into the local template
	* cache.
	*
	* @param array Templates (key: name, value: compiled output)
	*/
	protected function _mergeIntoTemplateCache(array $templates)
	{
		self::$_templateCache = array_merge(self::$_templateCache, $templates);
	}

	/**
	* Non-static method for pre-loading a template.
	*
	* @param string Template name
	*/
	protected function _preloadTemplate($templateName)
	{
		self::preloadTemplate($templateName);
	}

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
		if (isset(self::$_templateCache[$templateName]))
		{
			return self::$_templateCache[$templateName];
		}
		else
		{
			// set it for next time. If we load it, this will be overwritten
			self::$_templateCache[$templateName] = '';
			return '';
		}
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
		if (self::$_filePath)
		{
			return self::$_filePath . '/' . preg_replace('/[^a-z0-9_\.-]/i', '', $templateName) . '.php';
		}
		else
		{
			return '';
		}
	}

	/**
	* Determines whether we are using templates in the file system.
	*
	* @return boolean
	*/
	protected function _usingTemplateFiles()
	{
		return (self::$_filePath != '');
	}

	/**
	* Gets the list of required external resources.
	*
	* @return array
	*/
	protected function _getRequiredExternals()
	{
		return self::$_required;
	}

	/**
	* Sets the list of required external resources.
	*
	* @param array
	*/
	protected function _setRequiredExternals(array $required)
	{
		self::$_required = $required;
	}

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
	public static function preloadTemplate($templateName)
	{
		if (!isset(self::$_templateCache[$templateName]))
		{
			self::$_toLoad[$templateName] = true;
		}
	}

	/**
	* Manually sets a template. This is primarily useful for testing.
	*
	* @param string Name of the template
	* @param string Value for the template
	*/
	public static function setTemplate($templateName, $templateValue)
	{
		self::$_templateCache[$templateName] = $templateValue;
	}

	/**
	* Resets the template system state.
	*/
	public static function reset()
	{
		self::$_templateCache = array();
		self::$_toLoad = array();
	}
}