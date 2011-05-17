<?php

/**
* Class that represents a result to be returned by a {@link XenForo_Route_Interface}.
*
* @package XenForo_Mvc
*/
class XenForo_RouteMatch
{
	/**
	* If not null, represents a modified version of the routing path to be passed
	* into subsequent rules (if there are any). Useful only when the rule generating
	* the object doesn't list a controller and action. Can be set to an empty string
	* to cause the next rule to receive {@link XenForo_Router::$_routePathIfEmpty} as the
	* routing path.
	*
	* @var null|string
	*/
	public $_modifiedRoutePath = null;

	/**
	* If not empty, the type of response (eg, html, json) that should be returned via
	* the response. If left as an empty string, any previously set type will be used.
	*
	* @var string
	*/
	protected $_responseType = '';

	/**
	* The name of the controller class to handle this match. If not empty, no further
	* rules will be processed and routing will finishe.
	*
	* @var string
	*/
	protected $_controllerName = '';

	/**
	* The name of the action to call in the specified controller class. Applies only when
	* {@link $controllerName} is not empty; must be specified if a controller is specified.
	*
	* @var string
	*/
	protected $_action = '';

	/**
	 * The major section of the page being routed to. This is used as a navigation aid;
	 * for example, to show the right tab and child sections.
	 *
	 * @var string
	 */
	protected $_majorSection = '';

	/**
	 * The minor section of the page being routed to. This can, for example, allow the
	 * current section within a tab to be displayed as if it's selected.
	 *
	 * @var string
	 */
	protected $_minorSection = '';

	/**
	* Constructor. Allows quick set of the controller and action. Other elements
	* should be set directly via the properties.
	*
	* @param string If routing to a controller, the controller name to call
	* @param string|false If routing to a controller, the action in that controller
	* @param string The major section of the page we're being routed to
	* @param string The minor section of the page we're being routed to
	*/
	public function __construct($controllerName = '', $action = false, $majorSection = '', $minorSection = '')
	{
		$this->setControllerName($controllerName);
		if ($action !== false)
		{
			$this->setAction($action);
		}

		$this->setSections($majorSection, $minorSection);
	}

	/**
	 * Sets the controller name.
	 *
	 * @param string $controllerName
	 */
	public function setControllerName($controllerName)
	{
		$this->_controllerName = strval($controllerName);
	}

	/**
	 * Gets the controller name.
	 *
	 * @return string
	 */
	public function getControllerName()
	{
		return $this->_controllerName;
	}

	/**
	* Helper method to set the action. This will automatically translate the
	* action into a a more usable form, by replacing dashes with word breaks.
	* For example, confirm-test will be mapped to ConfirmTest.
	*
	* @param string
	*/
	public function setAction($action)
	{
		$this->_action = $action;
	}

	/**
	 * Gets the action.
	 *
	 * @return string
	 */
	public function getAction()
	{
		return $this->_action;
	}

	/**
	 * Sets the response type.
	 *
	 * @param string $responseType
	 */
	public function setResponseType($responseType)
	{
		$this->_responseType = strval($responseType);
	}

	/**
	 * Gets the response type.
	 *
	 * @return string
	 */
	public function getResponseType()
	{
		return $this->_responseType;
	}

	/**
	 * Sets the modified route path that will be passed to subsequent matches.
	 *
	 * @param string|null $routePath
	 */
	public function setModifiedRoutePath($routePath)
	{
		$this->_modifiedRoutePath = $routePath;
	}

	/**
	 * Gets the modified route path. If null, no modification is requested.
	 *
	 * @return string|null
	 */
	public function getModifiedRoutePath()
	{
		return $this->_modifiedRoutePath;
	}

	/**
	 * Sets the major and minor sections that we're routing to. This is used to
	 * aid navigation.
	 *
	 * @param string $majorSection
	 * @param string $minorSection
	 */
	public function setSections($majorSection, $minorSection = '')
	{
		$this->_majorSection = strval($majorSection);
		$this->_minorSection = strval($minorSection);
	}

	/**
	 * Gets the major section that the routing points to.
	 *
	 * @return string
	 */
	public function getMajorSection()
	{
		return $this->_majorSection;
	}

	/**
	 * Gets the minor section that the routing points to.
	 *
	 * @return string
	 */
	public function getMinorSection()
	{
		return $this->_minorSection;
	}
}