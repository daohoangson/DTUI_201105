<?php

/**
* Base template rendering class.
*
* Note that due to a lack of late static binding support, all static properties
* and any method that deals with those properties (via "self", regardless of whether
* it's static or not) must be (re)defined in child classes!
*
* @package XenForo_Core
*/
abstract class XenForo_Template_Abstract
{
	/**
	* Cached template data. Key is the template name; value is the compiled template.
	* All child classes must redefine this property!
	*
	* @var array
	*/
	protected static $_templateCache = array();

	/**
	* A list of templates that still need to be loaded. Key is the template name.
	* All child classes must redefine this property!
	*
	* @var array
	*/
	protected static $_toLoad = array();

	/**
	* Base path to compiled templates that are stored on disk.
	* All child classes must redefine this property!
	*
	* @var string
	*/
	protected static $_filePath = '';

	/**
	* Array of required external resources for this type of template.
	* All child classes must redefine this property!
	*
	* @var array
	*/
	protected static $_required = array();

	/**
	* Name of the template to load.
	*
	* @var string
	*/
	protected $_templateName;

	/**
	* Key-value params to make available in the template.
	*
	* @var array
	*/
	protected $_params = array();

	/**
	 * PHP errors generated during template evaluation.
	 *
	 * @var array
	 */
	protected $_templateErrors = array();

	/**
	 * The ID of the language that templates will be retrieved from.
	 *
	 * @var integer
	 */
	protected static $_languageId = 0;

	/**
	* Constructor
	*
	* @param string Template name
	* @param array  Key-value parameters
	*/
	public function __construct($templateName, array $params = array())
	{
		XenForo_CodeEvent::fire('template_create', array(&$templateName, &$params, $this));

		$this->_templateName = $templateName;
		$this->preloadTemplate($templateName);

		if ($params)
		{
			$this->setParams($params);
		}
	}

	/**
	 * Creates a new template object of the current type. Mainly helpful
	 * if an event only has the current template object in scope.
	 *
	 * @param string $templateName
	 * @param array $params
	 *
	 * @return XenForo_Template_Abstract
	 */
	public function create($templateName, array $params = array())
	{
		$class = get_class($this);
		return new $class($templateName, $params);
	}

	/**
	 * Sets the language ID that templates will be retrieved from.
	 *
	 * @param integer $languageId
	 */
	public static function setLanguageId($languageId)
	{
		self::$_languageId = intval($languageId);
	}

	/**
	* Add an array of params to the template. Overwrites parameters with the same name.
	*
	* @param array
	*/
	public function setParams(array $params)
	{
		$this->_params = ($this->_params ? XenForo_Application::mapMerge($this->_params, $params) : $params);
	}

	/**
	* Add a single param to the template. Overwrites parameters with the same name.
	*
	* @param string
	*/
	public function setParam($key, $value)
	{
		$this->_params[$key] = $value;
	}

	/**
	 * Get all template parameters.
	 *
	 * @return array
	 */
	public function getParams()
	{
		return $this->_params;
	}

	/**
	 * Get a single template parameter.
	 *
	 * @param string
	 *
	 * @return mixed Null if not found.
	 */
	public function getParam($key)
	{
		if (array_key_exists($key, $this->_params))
		{
			return $this->_params[$key];
		}

		return null;
	}

	/**
	 * @return string
	 */
	public function getTemplateName()
	{
		return $this->_templateName;
	}

	/**
	* Renders the specified template and returns the output.
	*
	* @return string
	*/
	public function render()
	{
		$__template = $this->_loadTemplate($this->_templateName);
		if ($__template === '')
		{
			return '';
		}

		XenForo_Phrase::loadPhrases();

		set_error_handler(array($this, 'handleTemplateError'));
		$this->_templateErrors = array();

		$__output = $this->_renderInternal($__template, $__extraData);

		restore_error_handler();

		XenForo_CodeEvent::fire('template_post_render', array($this->_templateName, &$__output, &$__extraData, $this));

		if (is_array($__extraData) && !empty($__extraData))
		{
			$this->_mergeExtraContainerData($__extraData);
		}

		if ($this->_templateErrors && XenForo_Application::debugMode())
		{
			if ($this->_usingTemplateFiles())
			{
				$templateCode = file_get_contents($__template);
			}
			else
			{
				$templateCode = $__template;
			}

			$lines = preg_split('/\r?\n/', $__template);

			echo "<div class=\"baseHtml\"><h4>Template Errors: " . htmlspecialchars($this->_templateName) . "</h4><ol>\n";
			foreach ($this->_templateErrors AS $error)
			{
				$contextLine = ($error['line'] > 1 ? $error['line'] - 2 : 0);
				$context = array_slice($lines, $contextLine, 3, true);

				echo "\t<li><i>" . htmlspecialchars($error['error']) . "</i> in " . htmlspecialchars($error['file']) . ", line $error[line]";
				if ($context)
				{
					echo ": <pre>";
					foreach ($context AS $lineNum => $contextLine)
					{
						echo ($lineNum + 1) . ": " . htmlspecialchars($contextLine) . "\n";
					}
					echo "</pre>";
				}
				echo "</li>\n";

			}
			echo "</ol></div>\n\n";
		}

		return $__output;
	}

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
		$__params = $this->_params; // special variable for dumping purposes
		extract($this->_params);

		$__output = '';
		$__extraData = array();

		if ($this->_usingTemplateFiles())
		{
			include($__template);
		}
		else
		{
			eval($__template);
		}

		return $__output;
	}

	/**
	 * Calls the specified template hook event.
	 *
	 * Params passed by template explicitly will respect mappings and greater context.
	 * Raw params are still available via the template object.
	 *
	 * @param string $name Name of the hook
	 * @param string $contents Contents of the hook; may be empty
	 * @param array $params List of params to pass specifically; these will respect mappings.
	 *
	 * @return string New version of the contents (could be modified)
	 */
	public function callTemplateHook($name, $contents, array $params)
	{
		XenForo_CodeEvent::fire('template_hook', array($name, &$contents, $params, $this));

		return $contents;
	}

	/**
	 * Error handler that traps errors in templates.
	 *
	 * @param integer $errorType Type of error (one of the E_* constants)
	 * @param string $errorString
	 * @param string $file
	 * @param integer $line
	 */
	public function handleTemplateError($errorType, $errorString, $file, $line)
	{
		if ($errorType == E_NOTICE)
		{
			return;
		}

		if ($errorType & error_reporting())
		{
			$this->_templateErrors[] = array(
				'type' => $errorType,
				'error' => $errorString,
				'file' => $file,
				'line' => $line
			);
		}
	}

	/**
	* Gets required external resources as HTML for use in a template directly.
	*
	* @param string Type of requirement to fetch
	*
	* @return string Requirements as HTML
	*/
	public function getRequiredExternalsAsHtml($type)
	{
		$required = $this->_getRequiredExternals();
		if (empty($required[$type]))
		{
			return '';
		}

		$typeRequired = array_unique($required[$type]);

		switch ($type)
		{
			case 'js':
				return $this->getRequiredJavaScriptAsHtml($typeRequired);

			case 'css':
				return $this->getRequiredCssAsHtml($this->getRequiredCssUrl($typeRequired));

			default:
				return false;
		}
	}

	public function getRequiredExternalsAsJson()
	{
		$required = $this->_getRequiredExternals();

		$output = array();

		foreach ($required AS $type => $externals)
		{
			if ($type == 'js')
			{
				$externals = $this->addJsVersionToJsUrls($externals);
			}
			foreach ($externals AS $external)
			{
				$output[$external] = true;
			}
		}

		return json_encode($output);
	}

	/**
	 * Gets required externals in a structured way. Values will be returned as a list of URLs.
	 *
	 * @param string $type
	 *
	 * @return array List of URLs
	 */
	public function getRequiredExternals($type)
	{
		$required = $this->_getRequiredExternals();
		if (empty($required[$type]))
		{
			return '';
		}

		$typeRequired = array_reverse(array_unique($required[$type]));

		switch ($type)
		{
			case 'js':
				return $this->addJsVersionToJsUrls($typeRequired);

			case 'css':
				return array(
					'stylesheets' => $typeRequired,
					'urlTemplate' => $this->getRequiredCssUrl(array('__sentinel__'))
				);

			default:
				return false;
		}
	}

	/**
	 * Gets the list of required JavaScript files as HTML script tags.
	 *
	 * @param array $requirements Array of paths to JS files.
	 *
	 * @return string
	 */
	public function getRequiredJavaScriptAsHtml(array $requirements)
	{
		$javaScriptSource = XenForo_Application::get('options')->javaScriptSource;

		$output = '';
		foreach ($this->addJsVersionToJsUrls($requirements) AS $requirement)
		{
			$requirement = preg_replace('#^js/#', $javaScriptSource . '/', $requirement);

			$output .= "\t" . '<script type="text/javascript" src="' . $requirement . '"></script>' . "\n";
		}

		return $output;
	}

	protected function addJsVersionToJsUrls(array $jsFiles)
	{
		$key = '_v=' . XenForo_Application::$jsVersion;
		foreach ($jsFiles AS &$file)
		{
			$file = $file . (strpos($file, '?') ? '&' : '?') . $key;
		}
		return $jsFiles;
	}

	/**
	 * Gets the required CSS as an HTML tag. Expected arg is simple a URL.
	 *
	 * @param string $requirement
	 *
	 * @return string
	 */
	public function getRequiredCssAsHtml($requirement)
	{
		return '<link rel="stylesheet" type="text/css" href="' . htmlspecialchars($requirement) . "\" />\n";
	}

	/**
	 * Gets the URL to fetch the list of required CSS templates. Requirements
	 * should be a list of CSS templates, not including the trailing ".css".
	 *
	 * @param array $requirements
	 *
	 * @return string
	 */
	abstract public function getRequiredCssUrl(array $requirements);

	/**
	* Implicit string cast renders the template.
	*
	* @return string
	*/
	public function __toString()
	{
		return $this->render();
	}

	/**
	* Load the named template.
	*
	* @param string Template name
	*
	* @return string Compiled version of the template
	*/
	protected function _loadTemplate($templateName)
	{
		if ($template = $this->_loadTemplateFilePath($templateName))
		{
			return $template;
		}
		else if ($template = $this->_loadTemplateFromCache($templateName))
		{
			return $template;
		}
		else
		{
			$this->_loadTemplates();
			return $this->_loadTemplateFromCache($templateName);
		}
	}

	/**
	* Bulk load all templates that are required.
	*/
	protected function _loadTemplates()
	{
		$toLoad = $this->getToLoadList();
		if (!$toLoad)
		{
			return;
		}

		$templates = $this->_getTemplatesFromDataSource(array_keys($toLoad));
		if ($templates)
		{
			$this->_mergeIntoTemplateCache($templates);
		}

		$this->_resetToLoadList();
	}

	/**
	* Adds required external for this type of template to be output later.
	*
	* @param string Type of requirement
	* @param string Value for requirement
	*/
	public function addRequiredExternal($type, $requirement)
	{
		$existing = $this->_getRequiredExternals();

		$existing[$type][] = $requirement;

		$this->_setRequiredExternals($existing);
	}

	/**
	* Goes to the data source to load the list of templates.
	*
	* @param array Template list
	*
	* @return array Key-value pairs of template titles/compiled templates
	*/
	abstract protected function _getTemplatesFromDataSource(array $templateList);

	/**
	* Helper function get the list of templates that are waiting to be loaded.
	*
	* @return array
	*/
	abstract public function getToLoadList();

	/**
	* Resets the to load list to empty.
	*/
	abstract protected function _resetToLoadList();

	/**
	* Merges key-value pairs of template names/compiled templates into the local template
	* cache.
	*
	* @param array Templates (key: name, value: compiled output)
	*/
	abstract protected function _mergeIntoTemplateCache(array $templates);

	/**
	* Non-static method for pre-loading a template.
	*
	* @param string Template name
	*/
	abstract protected function _preloadTemplate($templateName);

	/**
	* Loads a template out of the local template cache. If the template does not
	* exist, it will be set to an empty string. This will be overwritten if
	* the template is loaded from the data source.
	*
	* @param string Template name
	*
	* @return string Compiled template
	*/
	abstract protected function _loadTemplateFromCache($templateName);

	/**
	* Loads the file path where a template is located in the file system, if
	* templates are being stored in the file system.
	*
	* @param string Template name
	*
	* @param string Empty string (not using file system) or file path
	*/
	abstract protected function _loadTemplateFilePath($templateName);

	/**
	* Gets the list of required external resources.
	*
	* @return array
	*/
	abstract protected function _getRequiredExternals();

	/**
	* Sets the list of required external resources.
	*
	* @param array
	*/
	abstract protected function _setRequiredExternals(array $required);

	/**
	 * Merges in extra container data from the template render.
	 *
	 * @param array
	 */
	abstract protected function _mergeExtraContainerData(array $extraData);

	/**
	* Determines whether we are using templates in the file system.
	*
	* @return boolean
	*/
	abstract protected function _usingTemplateFiles();

	/**
	* Specify a template that needs to be preloaded for use later. This is useful
	* if you think a render is going to be called before the template you require
	* is to be used.
	*
	* @param string Template to preload
	*/
	public static function preloadTemplate($templateName)
	{
		throw new XenForo_Exception('This function must be overridden in a child class.');
	}

	/**
	* Manually sets a template. This is primarily useful for testing.
	*
	* @param string Name of the template
	* @param string Value for the template
	*/
	public static function setTemplate($templateName, $templateValue)
	{
		throw new XenForo_Exception('This function must be overridden in a child class.');
	}

	/**
	* Resets the template system state.
	*/
	public static function reset()
	{
		throw new XenForo_Exception('This function must be overridden in a child class.');
	}
}