<?php

/**
* Concrete renderer for JSON output.
*
* @package XenForo_Mvc
*/
class XenForo_ViewRenderer_Json extends XenForo_ViewRenderer_Abstract
{
	/**
	 * Constructor
	 * @see XenForo_ViewRenderer_Abstract::__construct()
	 */
	public function __construct(XenForo_Dependencies_Abstract $dependencies, Zend_Controller_Response_Http $response, Zend_Controller_Request_Http $request)
	{
		// TODO: Facebook sends text/javascript instead of application/json. SMV says that's a good thing.
		parent::__construct($dependencies, $response, $request);
		$this->_response->setHeader('Content-Type', 'application/json; charset=UTF-8', true);
	}

	/**
	 * Simple handler for JSON redirects - do not redirect, just send status:ok and redirect:$redirectTarget
	 *
	 * @param integer Type of redirect. See {@link XenForo_ControllerResponse_Redirect}
	 * @param string  Target to redirect to
	 * @param mixed   Redirect message
	 * @param array   Redirect parameters
	 *
	 * @return string JSON-encoded array
	 */
	public function renderRedirect($redirectType, $redirectTarget, $redirectMessage = null, array $redirectParams = array())
	{
		$redirectParams['_redirectStatus'] = 'ok';
		$redirectParams['_redirectTarget'] = $redirectTarget;
		$redirectParams['_redirectMessage'] = (is_null($redirectMessage) ? new XenForo_Phrase('redirect_changes_saved_successfully') : $redirectMessage);

		return self::jsonEncodeForOutput($redirectParams);
	}

	/**
	* Renders an error.
	* @see XenForo_ViewRenderer_Abstract::renderError()
	*
	* @param string|array Error message
	*
	* @return string JSON-encoded array
	*/
	public function renderError($error)
	{
		if (!is_array($error))
		{
			$error = array($error);
		}

		$template = $this->createTemplateObject('error', array('error' => $error, 'showHeading' => true));
		$templateHtml = $template->render();

		return self::jsonEncodeForOutput(array(
			'error' => $error,
			'templateHtml' => $templateHtml
		));
	}

	/**
	 * Renders a message.
	 *
	 * @see XenForo_ViewRenderer_Abstract::renderMessage()
	 */
	public function renderMessage($message)
	{
		return self::jsonEncodeForOutput(array(
			'status' => 'ok',
			'message' => $message
		));
	}

	/**
	* Renders a view.
	* @see XenForo_ViewRenderer_Abstract::renderView()
	*/
	public function renderView($viewName, array $params = array(), $templateName = '', XenForo_ControllerResponse_View $subView = null)
	{
		if ($subView)
		{
			return $this->renderSubView($subView);
		}

		$viewOutput = $this->renderViewObject($viewName, 'Json', $params, $templateName);

		if (is_array($viewOutput))
		{
			return self::jsonEncodeForOutput($viewOutput);
		}
		else if ($viewOutput === null)
		{
			return self::jsonEncodeForOutput(
				$this->getDefaultOutputArray($viewName, $params, $templateName)
			);
		}
		else
		{
			return $viewOutput;
		}
	}

	/**
	* Renders the container.
	* @see XenForo_ViewRenderer_Abstract::renderContainer()
	*
	* @param string
	* @param array
	*
	* @return string
	*/
	public function renderContainer($contents, array $params = array())
	{
		return $contents;
	}

	/**
	* Fallback for rendering an "unrepresentable" message.
	* @see XenForo_ViewRenderer_Abstract::renderUnrepresentable()
	*
	* @return string JSON-encoded array
	*/
	public function renderUnrepresentable()
	{
		return $this->renderError(new XenForo_Phrase('requested_page_is_unrepresentable_in_json'));
	}

	/**
	 * JSON encodes an input for direct output. This renders any objects
	 * with string representations to strings.
	 *
	 * @param mixed $input Data to JSON encode. Likely an array, but not always.
	 *
	 * @return string JSON encoded output
	 */
	public static function jsonEncodeForOutput($input)
	{
		if (is_array($input))
		{
			$input = self::_stringifyObjectsInArray($input);
		}
		else if (is_object($input) && method_exists($input, '__toString'))
		{
			$input = $input->__toString();
		}

		if (is_string($input))
		{
			$input = array('_response' => $input);
		}

		return json_encode($input);
	}

	/**
	 * Loops through the given array (recursively) and stringifies any
	 * objects it can.
	 *
	 * @param array $array Array to search
	 *
	 * @return array Array with objects stringified
	 */
	protected static function _stringifyObjectsInArray(array $array)
	{
		foreach ($array AS $name => &$value)
		{
			if (is_array($value))
			{
				$value = self::_stringifyObjectsInArray($value);
			}
			else if (is_object($value) && method_exists($value, '__toString'))
			{
				$value = $value->__toString();
			}
		}

		return $array;
	}

	/**
	 * Builds the default data to be returned by the JSON view,
	 * including an HTML-rendered template, array of required JS and CSS,
	 * title and h1 parameters and the navigation array.
	 *
	 * @param string $viewName
	 * @param array $params
	 * @param string $templateName
	 *
	 * @return array
	 */
	public function getDefaultOutputArray($viewName, $params, $templateName)
	{
		$viewOutput = $this->renderViewObject($viewName, 'Html', $params, $templateName);

		if ($viewOutput === null)
		{
			// no class found
			$template = $this->createTemplateObject($templateName, $params);
			$viewOutput = $template->render();
		}
		else
		{
			$template = $this->createTemplateObject($templateName, array());
		}

		// replace {$requestPaths.requestUri} with a token
		$requestPaths = XenForo_Application::get('requestPaths');
		$viewOutput = str_replace(
			htmlspecialchars($requestPaths['requestUri']),
			htmlspecialchars((string)$this->_request->get('_xfRequestUri')),
			$viewOutput
		);

		$output = array(
			'templateHtml' => $viewOutput,
			'css' => $template->getRequiredExternals('css'),
			'js' => $template->getRequiredExternals('js'),
		);

		$extraContainerData = $this->_dependencies->getExtraContainerData();

		if (!empty($extraContainerData['title']))
		{
			$output['title'] = $extraContainerData['title'];
		}

		if (!empty($extraContainerData['h1']))
		{
			$output['h1'] = $extraContainerData['h1'];
		}

		if (!empty($extraContainerData['sidebar']))
		{
			$output['sidebarHtml'] = $extraContainerData['sidebar'];
		}

		if (!empty($extraContainerData['navigation']))
		{
			$navigation = array();
			foreach ($extraContainerData['navigation'] AS $breadCrumb)
			{
				if (!isset($breadCrumb['href']))
				{
					$breadCrumb['href'] = '';
				}
				$navigation[] = array($breadCrumb['href'], $breadCrumb['value']);
			}
			$output['navigation'] = $navigation;
		}

		return $output;
	}
}