<?php

/**
* Abstract base for views. A child of this class is not required if you simply
* want to render an HTML template with no other processing.
*
* Views must implement renderX methods, where X represents the response type they
* handle (eg, renderHtml or renderJson). These methods take no arguments and should
* return a string if they successfully rendered content or false if the content
* is really unrepresentable.
*
* @package XenForo_Mvc
*/
abstract class XenForo_View
{
	/**
	* View renderer that created this view
	*
	* @var XenForo_ViewRenderer_Abstract
	*/
	protected $_renderer;

	/**
	* Response object. Should be used to modify headers/state as necessary
	*
	* @var Zend_Controller_Response_Http
	*/
	protected $_response;

	/**
	* The template that should be used to output. This can be used as is, as a basis,
	* or just ignored entirely. Each view will handle it differently.
	*
	* @var string
	*/
	protected $_templateName;

	/**
	* Key-value parameters that can be used by the view.
	*
	* @var array
	*/
	protected $_params = array();

	/**
	* Constructor
	*
	* @param XenForo_ViewRenderer_Abstract    View renderer
	* @param Zend_Controller_Response_Http Response object
	* @param array                         View params
	* @param string                        Template name to render (possibly ignored)
	*/
	public function __construct(XenForo_ViewRenderer_Abstract $renderer, Zend_Controller_Response_Http $response, array $params = array(), $templateName = '')
	{
		$this->_renderer = $renderer;
		$this->_response = $response;
		$this->_templateName = $templateName;

		if ($params)
		{
			$this->setParams($params);
		}
	}

	/**
	* Add an array of params to the view. Overwrites parameters with the same name.
	*
	* @param array
	*/
	public function setParams(array $params)
	{
		$this->_params = array_merge($this->_params, $params);
	}

	/**
	 * Gets the view params.
	 *
	 * @return array
	 */
	public function getParams()
	{
		return $this->_params;
	}

	/**
	 * Gets the view template name
	 *
	 * @return string
	 */
	public function getTemplateName()
	{
		return $this->_templateName;
	}

	/**
	 * This is a global param preparation method. It is called for all view output
	 * types. It is also called even if the required renderX method is not available.
	 *
	 * This method can be overridden to modify params and then let the code fallback to
	 * the behavior as if the view did not exist (by not defining the renderX method).
	 */
	public function prepareParams()
	{
	}

	/**
	* Creates an HTML template object for rendering using the view renderer.
	*
	* @param string Name of the template to create
	* @param array  Key-value parameters to pass to the template
	*
	* @return XenForo_Template_Abstract
	*/
	public function createTemplateObject($templateName, array $params = array())
	{
		return $this->_renderer->createTemplateObject($templateName, $params);
	}

	/**
	 * Creates the HTML template object to render this view's own specified template,
	 * using the given params.
	 *
	 * @return XenForo_Template_Abstract
	 */
	public function createOwnTemplateObject()
	{
		return $this->createTemplateObject($this->_templateName, $this->_params);
	}

	/**
	 * Tells the browser that the data should be downloaded (rather than displayed)
	 * using the specified file name.
	 *
	 * @param string $fileName
	 * @param boolean $inline True if the attachment should be shown inline - use with caution!
	 */
	public function setDownloadFileName($fileName, $inline = false)
	{
		$type = ($inline ? 'inline' : 'attachment');

		$this->_response->setHeader('Content-Disposition',
			$type . '; filename="' . str_replace('"', '', $fileName) . '"',
			true
		);
	}

	/**
	 * Pre-loads the specified template.
	 *
	 * @param string $template
	 */
	public function preLoadTemplate($template)
	{
		$this->_renderer->preloadTemplate($template);
	}
}