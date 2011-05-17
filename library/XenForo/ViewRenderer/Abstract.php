<?php

/**
* Abstract handler for view rendering of a particular response type.
* Handles rendering of different types of controller responses.
*
* @package XenForo_Mvc
*/
abstract class XenForo_ViewRenderer_Abstract
{
	/**
	* Response object. Generally should only be used to manipulate response codes if needed.
	*
	* @var Zend_Controller_Response_Http
	*/
	protected $_response;

	/**
	 * Request object. Can be used to manipulate output based in input parameters AT YOUR OWN RISK.
	 * Strictly, making use of this breaks MVC principles, so avoid it if you can.
	 *
	 * @var Zend_Controller_Request_Http
	 */
	protected $_request;

	/**
	* Application dependencies.
	*
	* @var XenForo_Dependencies_Abstract
	*/
	protected $_dependencies;

	/**
	* Determines whether the container needs to be rendered. This may apply to an
	* entire renderer or just individual render types.
	*
	* @var boolean
	*/
	protected $_needsContainer = true;

	/**
	* Constructor.
	*
	* @param XenForo_Dependencies_Abstract
	* @param Zend_Controller_Response_Http
	* @param Zend_Controller_Request_Http
	*/
	public function __construct(XenForo_Dependencies_Abstract $dependencies, Zend_Controller_Response_Http $response, Zend_Controller_Request_Http $request)
	{
		$this->_dependencies = $dependencies;
		$this->_response = $response;
		$this->_request = $request;

		$this->_preloadContainerData();
	}

	/**
	* Renders a redirect. Most renderers will actually redirect, but some may not.
	*
	* @param integer Type of redirect. See {@link XenForo_ControllerResponse_Redirect}
	* @param string  Target to redirect to
	* @param mixed   Redirect message (unused by some redirect methods)
	* @param array   Extra redirect parameters (unused by HTML)
	*
	* @return string Empty string (nothing to display)
	*/
	public function renderRedirect($redirectType, $redirectTarget, $redirectMessage = null, array $redirectParams = array())
	{
		switch ($redirectType)
		{
			case XenForo_ControllerResponse_Redirect::RESOURCE_CREATED:
			case XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED:
			case XenForo_ControllerResponse_Redirect::SUCCESS:
				$this->_response->setRedirect($redirectTarget, 303);
				break;

			case XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL:
				$this->_response->setRedirect($redirectTarget, 307);
				break;

			case XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT:
				$this->_response->setRedirect($redirectTarget, 301);
				break;

			default:
				throw new XenForo_Exception('Unknown redirect type');
		}

		$this->_needsContainer = false;

		return '';
	}

	/**
	* Renders output of an error.
	*
	* @param string Text of the error to render
	*
	* @return string|false Rendered output. False if rendering wasn't possible (see {@link renderUnrepresentable()}).
	*/
	abstract public function renderError($errorText);

	/**
	* Renders output of an message.
	*
	* @param string Text of the message to render
	*
	* @return string|false Rendered output. False if rendering wasn't possible (see {@link renderUnrepresentable()}).
	*/
	abstract public function renderMessage($message);

	/**
	* Renders output of a view. Should instantiate the view object and render it.
	* Note that depending on response type, this class may have to manipulate the
	* view name or instantiate a different object.
	*
	* @param string Name of the view to create
	* @param array  Key-value array of parameters for the view.
	* @param string Name of the template that will be used to display (may be ignored by view)
	* @param XenForo_ControllerResponse_View|null A sub-view that will be rendered internal to this view
	*
	* @return string|XenForo_Template_Abstract|false Rendered output. False if rendering wasn't possible (see {@link renderUnrepresentable()}).
	*/
	abstract public function renderView($viewName, array $params = array(), $templateName = '', XenForo_ControllerResponse_View $subView = null);

	/**
	* Renders the container output for a page. This often represents the "chrome" of
	* a page, including aspects like the header and footer. The content from the other
	* render methods will generally be put inside this.
	*
	* Note that not all response types will have a container. In which case, they
	* should return the inner contents directly.
	*
	* @param string Contents from a previous render method
	* @param array  Key-value pairs to manipulate the container
	*
	* @return string Rendered output
	*/
	abstract public function renderContainer($contents, array $params = array());

	/**
	* Data that should be preloaded for the container. Templates/phrases may be
	* accidentally (or intentionally) rendered in the view or before the container
	* is set to be rendered. Preloading data here can allow all the data to be fetched
	* at once.
	*/
	protected function _preloadContainerData()
	{
	}

	/**
	 * Replaces the place holders for required externals with the actual requirements.
	 * This approach is needed to ensure that all requirements are properly included,
	 * even if they are included after the comment has been rendered.
	 *
	 * @param XenForo_Template_Abstract $template The container template object; used to get the requirements
	 * @param string $rendered Already rendered output
	 *
	 * @return string
	 */
	public function replaceRequiredExternalPlaceholders(XenForo_Template_Abstract $template, $rendered)
	{
		return str_replace(
			array(
				'<!--XenForo_Require:JS-->',
				'<!--XenForo_Require:CSS-->',
				'{/*<!--XenForo_Required_Scripts-->*/}',
			),
			array(
				$template->getRequiredExternalsAsHtml('js'),
				$template->getRequiredExternalsAsHtml('css'),
				$template->getRequiredExternalsAsJson(),
			),
			$rendered
		);
	}

	/**
	* Fallback for rendering an "unrepresentable" message. Method is called when
	* the concrete rendering function returns false or no concrete rendering function
	* is available.
	*
	* @return string Rendered output
	*/
	abstract public function renderUnrepresentable();

	/**
	* General helper method to create and render a view object for the specified
	* response type. Returns null if no class can be loaded or no view method has
	* been defined. Otherwise, the return is defined by the view render method,
	* which should return either a string (rendered content) or false (unrepresentable).
	*
	* @param string View class name
	* @param string Response type (translated to method name as render$type)
	* @param array  Key-value parameters to pass to view. May be modified by the prepareParams call within.
	* @param string Template name to pass to view (may be ignored by view)
	*
	* @return string|false|null
	*/
	public function renderViewObject($class, $responseType, array &$params = array(), &$templateName = '')
	{
		$baseViewClass = $this->_dependencies->getBaseViewClassName();

		$class = XenForo_Application::resolveDynamicClass($class, 'view', $baseViewClass);
		if (!$class)
		{
			$class = $baseViewClass;
		}

		$view = new $class($this, $this->_response, $params, $templateName);
		if (!$view instanceof $baseViewClass)
		{
			throw new XenForo_Exception('View must be a child of ' . $baseViewClass);
		}
		$view->prepareParams();

		$responseType = ucfirst(strtolower($responseType));
		$renderMethod = 'render' . $responseType;

		if (method_exists($view, 'render' . $responseType))
		{
			$return = $view->$renderMethod();
		}
		else
		{
			$return = null;
		}

		$templateName = $view->getTemplateName();
		$params = $view->getParams();

		return $return;
	}

	/**
	 * Renders a sub or child view.
	 *
	 * @param XenForo_ControllerResponse_View $subView
	 *
	 * @return string|XenForo_Template_Abstract|false
	 */
	public function renderSubView(XenForo_ControllerResponse_View $subView)
	{
		return $this->renderView($subView->viewName, $subView->params, $subView->templateName, $subView->subView);
	}

	/**
	* Helper method to create a template object for rendering. Templates only represent
	* HTML output, so no response type is needed. However, they can be used in any response type.
	*
	* @param string Name of the template to create
	* @param array  Key-value parameters to pass to the template
	*
	* @return XenForo_Template_Abstract
	*/
	public function createTemplateObject($templateName, array $params = array())
	{
		return $this->_dependencies->createTemplateObject($templateName, $params);
	}

	/**
	* Preloads a template with the template handler for use later.
	*
	* @param string Template name
	*/
	public function preloadTemplate($templateName)
	{
		return $this->_dependencies->preloadTemplate($templateName);
	}

	/**
	* Gets the 'needs container' setting.
	*
	* @return boolean
	*/
	public function getNeedsContainer()
	{
		return $this->_needsContainer;
	}

	/**
	 * Sets the 'needs container' setting
	 * @param boolean $required
	 *
	 * @return boolean
	 */
	public function setNeedsContainer($required)
	{
		$this->_needsContainer = $required;
		return $this->_needsContainer;
	}

	/**
	 * Gets the dependencies handler object.
	 *
	 * @return XenForo_Dependencies_Abstract
	 */
	public function getDependencyHandler()
	{
		return $this->_dependencies;
	}
}