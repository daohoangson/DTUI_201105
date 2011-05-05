<?php

/**
* Class that resolves a path in the URI or other part of the request to a controller/action.
* Also allows the type of response that is desired to be controlled based on input.
* Individual matches can make modifications to the routing path to be passed to other rules.
*
* Rules will continue matching until a {@link XenForo_RouteMatch} object is returned that
* has a {@link XenForo_RouteMatch::$controllerName controller name} specified.
*
* @package XenForo_Mvc
*/
class XenForo_Router
{
	/**
	* Stack of rules to match against. Once a match is found, further matching stops.
	*
	* @see addRules()
	* @var array
	*/
	protected $_rules = array();

	/**
	* The default response type, if it's not modified based on input.
	*
	* @var string
	*/
	protected $_defaultResponseType = 'html';

	/**
	* The route path to apply if the path ever becomes empty.
	*
	* @var string
	*/
	protected $_routePathIfEmpty = 'index';

	/**
	* Match against the rules stack. If no match can be found, the {@link getNotFoundError()}
	* handler is invoked.
	*
	* @param Zend_Controller_Request_Http Request object
	*
	* @return XenForo_RouteMatch|false Final information (including controller and action) about where to route to
	*/
	public function match(Zend_Controller_Request_Http $request)
	{
		$routePath = $this->getRoutePath($request);
		$request->setParam('_origRoutePath', $routePath);

		$responseType = $this->_defaultResponseType;
		$finalRouteMatch = null;

		foreach ($this->_rules AS $rule)
		{
			if ($routePath === '')
			{
				// must always have a route path of some sort
				$routePath = $this->_routePathIfEmpty;
			}

			if (!$match = $rule->match($routePath, $request, $this))
			{
				continue;
			}

			if ($match->getResponseType())
			{
				$responseType = $match->getResponseType();
			}

			if ($match->getControllerName())
			{
				$finalRouteMatch = $match;
				$request->setParam('_matchedRoutePath', $routePath);
				break;
			}

			if ($match->getModifiedRoutePath() !== null)
			{
				$routePath = $match->getModifiedRoutePath();
			}
		}

		// a bit of magic - if the query string specifies a _xfResponseType parameter,
		// use THAT as the extension, overriding anything else.
		if ($request->has('_xfResponseType'))
		{
			$responseType = $request->get('_xfResponseType');
		}

		if ($finalRouteMatch)
		{
			$finalRouteMatch->setResponseType($responseType);
			return $finalRouteMatch;
		}
		else
		{
			$match = $this->getRouteMatch();
			$match->setResponseType($responseType);
			return $match;
		}
	}

	/**
	* Gets the path the to be routed based on the URL of the request
	*
	* @param Zend_Controller_Request_Http Request object
	*
	* @return string Routing path
	*/
	public function getRoutePath(Zend_Controller_Request_Http $request)
	{
		$baseUrl = $request->getBaseUrl();
		$requestUri = $request->getRequestUri();

		if (substr($requestUri, 0, strlen($baseUrl)) == $baseUrl)
		{
			$routeBase = substr($requestUri, strlen($baseUrl));

			if (preg_match('#^/([^?]+)(\?|$)#U', $routeBase, $match))
			{
				// rewrite approach (starts with /). Must be non-empty rewrite up to query string.
				return urldecode($match[1]);
			}
			else if (preg_match('#\?([^=&]+)(&|$)#U', $routeBase, $match))
			{
				// query string approach. Must start with non-empty, non-named param.
				return urldecode($match[1]);
			}
		}

		if (($namedRouteVar = $request->getParam('_')) !== null)
		{
			return $namedRouteVar;
		}

		return '';
	}

	/**
	* Adds a new routing rule to the end of the chain or overwrites an existing rule by name.
	*
	* @param XenForo_Route_Interface Routing rule
	* @param string               Name of the rule. If it already exists, it is overwritten.
	*
	* @return XenForo_Router Fluent interface ($this)
	*/
	public function addRule(XenForo_Route_Interface $route, $name)
	{
		$this->_rules[$name] = $route;

		return $this;
	}

	/**
	* Get the current routing rules
	*
	* @return array
	*/
	public function getRules()
	{
		return $this->_rules;
	}

	/**
	* Reset (remove) all routing rules.
	*
	* @return XenForo_Router Fluent interface ($this)
	*/
	public function resetRules()
	{
		$this->_rules = array();

		return $this;
	}

	/**
	 * Resolves the action from a route that looks like name.123/action and sets
	 * the "123" param into the specified parameter name.
	 *
	 * Supports name.123/action1/action2 (returns "action1/action2"). If given
	 * "action1/action2", this will return the full string as the action as long as action1
	 * does not have a "." in it.
	 *
	 * @param string $routePath Full path to route against. This should not include a prefix.
	 * @param Zend_Controller_Request_Http $request Request object
	 * @param string $paramName Name of the parameter to be registered with the request object (if found)
	 * @param string $defaultActionWithParam If there's no action and there is an int param, use this as the default action
	 *
	 * @return string The requested action
	 */
	public function resolveActionWithIntegerParam($routePath, Zend_Controller_Request_Http $request, $paramName, $defaultActionWithParam = '')
	{
		$parts = explode('/', $routePath, 2);
		$action = isset($parts[1]) ? $parts[1] : '';

		$paramParts = explode(XenForo_Application::URL_ID_DELIMITER, $parts[0]);
		$paramId = end($paramParts);

		if (count($paramParts) > 1
			|| $paramId === strval(intval($paramId))
		)
		{
			$request->setParam($paramName, intval($paramId));
			if ($action === '')
			{
				$action = $defaultActionWithParam;
			}
			return $action;
		}
		else
		{
			return $routePath;
		}
	}

	/**
	 * Resolves an action with an integer or a string parameter, such as in situations
	 * where a slug is an optional component. Note that <title>.<int>/<action> and
	 * <string>/<action> will work, but <action> on its own will be matched as a string.
	 *
	 * This method is not an ideal function to use when you are not guaranteed to have data.
	 *
	 * @param strign $routePath
	 * @param Zend_Controller_Request_Http $request
	 * @param string $intParamName Name of the parameter to set if int is found
	 * @param string $stringParamName Name of the parameter to set if string is found
	 */
	public function resolveActionWithIntegerOrStringParam($routePath, Zend_Controller_Request_Http $request, $intParamName, $stringParamName)
	{
		$parts = explode('/', $routePath, 2);
		$action = isset($parts[1]) ? $parts[1] : '';

		$paramParts = explode(XenForo_Application::URL_ID_DELIMITER, $parts[0]);
		$paramId = end($paramParts);

		if (count($paramParts) > 1
			|| $paramId === strval(intval($paramId))
		)
		{
			$request->setParam($intParamName, intval($paramId));
			return $action;
		}
		else
		{
			if ($paramId != '-') // special case: not set
			{
				$request->setParam($stringParamName, $paramId);
			}
			return $action;
		}
	}

	/**
	 * Resolves the action from a route that may have a string parameter. If there
	 * are no slashes, then an action is assumed. If there is a slash, then the first
	 * item is considered the string param.
	 *
	 * For example: list => "list" action. blah/list => "list" action, with "blah" param.
	 *
	 * @param string $routePath Full path to route against. This should not include a prefix.
	 * @param Zend_Controller_Request_Http $request Request object
	 * @param string $paramName Name of the parameter to be registered with the request object (if found)
	 *
	 * @return string The requested action
	 */
	public function resolveActionWithStringParam($routePath, Zend_Controller_Request_Http $request, $paramName)
	{
		$components = explode('/', $routePath);
		if (isset($components[1]))
		{
			// param comes first but we have an action: <param>/<action...>
			$request->setParam($paramName, $components[0]);

			unset($components[0]);
			return implode('', $components);
		}
		else
		{
			return $routePath;
		}
	}

	/**
	 * Checks for the presence of 'page-x' as the action component of a route
	 *
	 * @param string $action
	 * @param Zend_Controller_Request_Http $request
	 *
	 * @return string
	 */
	public function resolveActionAsPageNumber($action, Zend_Controller_Request_Http $request)
	{
		if (preg_match('#^page-(\d+)$#i', $action, $match))
		{
			$action = '';
			$request->setParam('page', $match[1]);
		}

		return $action;
	}

	/**
	 * Gets a route match object.
	 *
	 * @param string $controllerName
	 * @param string|false $action
	 * @param string $majorSection
	 * @param string $minorSection
	 *
	 * @return XenForo_RouteMatch
	 */
	public function getRouteMatch($controllerName = '', $action = false, $majorSection = '', $minorSection = '')
	{
		return new XenForo_RouteMatch($controllerName, $action, $majorSection, $minorSection);
	}
}