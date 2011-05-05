<?php

/**
* General base class for controllers. Controllers should implement methods named
* actionX with no arguments. These will be called by the dispatcher based on the
* requested route. They should return the object returned by {@link responseReroute()},
* {@link responseError()}, or {@link responseView()},.
*
* All responses can take paramaters that will be passed to the container view
* (ie, two-phase view), if there is one.
*
* @package XenForo_Mvc
*/
abstract class XenForo_Controller
{
	/**
	* Request object.
	*
	* @var Zend_Controller_Request_Http
	*/
	protected $_request;

	/**
	* Response object.
	*
	* @var Zend_Controller_Response_Http
	*/
	protected $_response;

	/**
	 * The route match object for this request.
	 *
	 * @var XenForo_RouteMatch
	 */
	protected $_routeMatch;

	/**
	* Input object.
	*
	* @var XenForo_Input
	*/
	protected $_input;

	/**
	 * Standard approach to caching model objects for the lifetime of the controller.
	 *
	 * @var array
	 */
	protected $_modelCache = array();

	/**
	 * List of explicit changes to the view state. View state changes are specific
	 * to the dependency manager, but may include things like changing the styleId.
	 *
	 * @var array Key-value pairs
	 */
	protected $_viewStateChanges = array();

	/**
	 * Container for various items that have been "executed" in one controller and
	 * shouldn't be executed again in this request.
	 *
	 * @var array
	 */
	protected static $_executed = array();

	/**
	 * Gets the response for a generic no permission page.
	 *
	 * @return XenForo_ControllerResponse_Error
	 */
	abstract public function responseNoPermission();

	/**
	* Constructor
	*
	* @param Zend_Controller_Request_Http
	* @param Zend_Controller_Response_Http
	* @param XenForo_RouteMatch
	*/
	public function __construct(Zend_Controller_Request_Http $request, Zend_Controller_Response_Http $response, XenForo_RouteMatch $routeMatch)
	{
		$this->_request = $request;
		$this->_response = $response;
		$this->_routeMatch = $routeMatch;
		$this->_input = new XenForo_Input($this->_request);
	}

	/**
	 * Gets the specified model object from the cache. If it does not exist,
	 * it will be instantiated.
	 *
	 * @param string $class Name of the class to load
	 *
	 * @return XenForo_Model
	 */
	public function getModelFromCache($class)
	{
		if (!isset($this->_modelCache[$class]))
		{
			$this->_modelCache[$class] = XenForo_Model::create($class);
		}

		return $this->_modelCache[$class];
	}

	/**
	 * Gets the request object.
	 *
	 * @return Zend_Controller_Request_Http
	 */
	public function getRequest()
	{
		return $this->_request;
	}

	/**
	 * Gets the input object.
	 *
	 * @return XenForo_Input
	 */
	public function getInput()
	{
		return $this->_input;
	}

	/**
	 * Sets a change to the view state.
	 *
	 * @param string $state Name of state to change
	 * @param mixed $data
	 */
	public function setViewStateChange($state, $data)
	{
		$this->_viewStateChanges[$state] = $data;
	}

	/**
	 * Gets all the view state changes.
	 *
	 * @return array Key-value pairs
	 */
	public function getViewStateChanges()
	{
		return $this->_viewStateChanges;
	}

	/**
	 * Gets the type of response that has been requested.
	 *
	 * @return string
	 */
	public function getResponseType()
	{
		return $this->_routeMatch->getResponseType();
	}

	/**
	 * Gets the route match for this request. This can be modified to change
	 * the response type, and the major/minor sections that will be used to
	 * setup navigation.
	 *
	 * @return XenForo_RouteMatch
	 */
	public function getRouteMatch()
	{
		return $this->_routeMatch;
	}

	/**
	 * Checks a request for CSRF issues. This is only checked for POST requests
	 * (with session info) that aren't Ajax requests (relies on browser-level
	 * cross-domain policies).
	 *
	 * The token is retrieved from the "_xfToken" request param.
	 *
	 * @param string $action
	 */
	protected function _checkCsrf($action)
	{
		if (isset(self::$_executed['csrf']))
		{
			return;
		}
		self::$_executed['csrf'] = true;

		if (!XenForo_Application::isRegistered('session'))
		{
			return;
		}

		if ($this->_request->isPost() || substr($this->getResponseType(), 0, 2) == 'js')
		{
			// post and all json requests require a token
			$this->_checkCsrfFromToken($this->_request->getParam('_xfToken'));
		}
	}

	/**
	 * Performs particular actions if the request method is POST
	 *
	 * @param string $action
	 */
	protected function _handlePost($action)
	{
		if ($this->_request->isPost() && $delay = XenForo_Application::get('options')->delayPostResponses)
		{
			usleep($delay * 1000000);
		}
	}

	/**
	 * Gets for a CSRF issue using a standard formatted token.
	 * Throws an exception if a CSRF issue is detected.
	 *
	 * @param string $token Format: <user id>,<request time>,<token>
	 * @param boolean $throw If true, an exception is thrown when failing; otherwise, a return is used
	 *
	 * @return boolean True if passed, false otherwise; only applies when $throw is false
	 */
	protected function _checkCsrfFromToken($token, $throw = true)
	{
		$visitingUser = XenForo_Visitor::getInstance();
		$visitingUserId = $visitingUser['user_id'];
		if (!$visitingUserId)
		{
			// don't check for guests
			return true;
		}

		$token = strval($token);

		$csrfAttempt = 'invalid';
		if ($token === '')
		{
			$csrfAttempt = 'missing';
		}

		$tokenParts = explode(',', $token);
		if (count($tokenParts) == 3)
		{
			list($tokenUserId, $tokenTime, $tokenValue) = $tokenParts;

			if (strval($tokenUserId) === strval($visitingUserId))
			{
				if (($tokenTime + 86400) < XenForo_Application::$time)
				{
					$csrfAttempt = 'expired';
				}
				else if (sha1($tokenTime . $visitingUser['csrf_token']) == $tokenValue)
				{
					$csrfAttempt = false;
				}
			}
		}

		if ($csrfAttempt)
		{
			if ($throw)
			{
				throw $this->responseException(
					$this->responseError(new XenForo_Phrase('security_error_occurred'))
				);
			}
			else
			{
				return false;
			}
		}

		return true;
	}

	/**
	* Setup the session.
	*
	* @param string $action
	*/
	protected function _setupSession($action)
	{
		if (XenForo_Application::isRegistered('session'))
		{
			return;
		}

		$session = XenForo_Session::startPublicSession($this->_request);
	}

	/**
	* This function is called immediately before an action is dispatched.
	*
	* @param string Action that is requested
	*/
	final public function preDispatch($action)
	{
		$this->_preDispatchFirst($action);

		$this->_setupSession($action);
		$this->_checkCsrf($action);
		$this->_handlePost($action);

		$this->_preDispatchType($action);
		$this->_preDispatch($action);

		XenForo_CodeEvent::fire('controller_pre_dispatch', array($this, $action));
	}

	/**
	 * Method designed to be overridden by child classes to add pre-dispatch behaviors
	 * before any other pre-dispatch checks are called.
	 *
	 * @param string $action
	 */
	protected function _preDispatchFirst($action)
	{
	}

	/**
	 * Method designed to be overridden by child classes to add pre-dispatch
	 * behaviors. This differs from {@link _preDispatch()} in that it is designed
	 * for abstract controller type classes to override. Specific controllers
	 * should override preDispatch instead.
	 *
	 * @param string $action Action that is requested
	 */
	protected function _preDispatchType($action)
	{
	}

	/**
	* Method designed to be overridden by child classes to add pre-dispatch
	* behaviors. This method should only be overridden by specific, concrete
	* controllers.
	*
	* @param string Action that is requested
	*/
	protected function _preDispatch($action)
	{
	}

	/**
	* This function is called immediately after an action is dispatched.
	*
	* @param mixed The response from the controller. Generally, a XenForo_ControllerResponse_Abstract object.
	* @param string The name of the final controller that was invoked
	* @param string The name of the final action that was invoked
	*/
	final public function postDispatch($controllerResponse, $controllerName, $action)
	{
		$this->updateSession($controllerResponse, $controllerName, $action);
		$this->updateSessionActivity($controllerResponse, $controllerName, $action);
		$this->_postDispatch($controllerResponse, $controllerName, $action);
	}

	/**
	* Method designed to be overridden by child classes to add post-dispatch behaviors
	*
	* @param mixed The response from the controller. Generally, a XenForo_ControllerResponse_Abstract object.
	* @param string The name of the final controller that was invoked
	* @param string The name of the final action that was invoked
	*/
	protected function _postDispatch($controllerResponse, $controllerName, $action)
	{
	}

	/**
	 * Updates the session records. This should run on all pages, provided they not rerouting
	 * to another controller. Session saving should handle double calls, if they happen.
	 *
	 * @param mixed $controllerResponse The response from the controller. Generally, a XenForo_ControllerResponse_Abstract object.
	 * @param string $controllerName
	 * @param string $action
	 */
	public function updateSession($controllerResponse, $controllerName, $action)
	{
		if (!XenForo_Application::isRegistered('session'))
		{
			return;
		}

		if (!$controllerResponse || $controllerResponse instanceof XenForo_ControllerResponse_Reroute)
		{
			return;
		}

		XenForo_Application::get('session')->save();
	}

	/**
	 * Update a user's session activity.
	 *
	 * @param mixed $controllerResponse The response from the controller. Generally, a XenForo_ControllerResponse_Abstract object.
	 * @param string $controllerName
	 * @param string $action
	 */
	public function updateSessionActivity($controllerResponse, $controllerName, $action)
	{
		if (!XenForo_Application::isRegistered('session'))
		{
			return;
		}

		if ($controllerResponse instanceof XenForo_ControllerResponse_Abstract)
		{
			switch (get_class($controllerResponse))
			{
				case 'XenForo_ControllerResponse_Redirect':
				case 'XenForo_ControllerResponse_Reroute':
					return; // don't update anything, assume the next page will do it

				case 'XenForo_ControllerResponse_Message':
				case 'XenForo_ControllerResponse_View':
					$newState = 'valid';
					break;

				default:
					$newState = 'error';
			}
		}
		else
		{
			$newState = 'error';
		}

		if ($this->canUpdateSessionActivity($controllerName, $action, $newState))
		{
			$this->getModelFromCache('XenForo_Model_User')->updateSessionActivity(
				XenForo_Visitor::getUserId(), $this->_request->getClientIp(false),
				$controllerName, $action, $newState, $this->_request->getUserParams()
			);
		}
	}

	/**
	 * Can this controller update the session activity? Returns false by default for AJAX requests.
	 * Override this in specific controllers if you want action-specific behaviour.
	 *
	 * @param string $controllerName
	 * @param string $action
	 * @param string $newState
	 *
	 * @return boolean
	 */
	public function canUpdateSessionActivity($controllerName, $action, &$newState)
	{
		// don't update session activity for an AJAX request
		if ($this->_request->isXmlHttpRequest())
		{
			return false;
		}

		return true;
	}

	/**
	 * Gets session activity details of activity records that are pointing to this controller.
	 * This must check the visiting user's permissions before returning item info.
	 * Return value may be:
	 * 		* false - means page is unknown
	 * 		* string/XenForo_Phrase - gives description for all, but no item details
	 * 		* array (keyed by activity keys) of strings/XenForo_Phrase objects - individual description, no item details
	 * 		* array (keyed by activity keys) of arrays. Sub-arrays keys: 0 = description, 1 = specific item title, 2 = specific item url.
	 *
	 * @param array $activities List of activity records
	 *
	 * @return mixed See above.
	 */
	public static function getSessionActivityDetailsForList(array $activities)
	{
		return false;
	}

	/**
	 * Checks for the presence of the _xfNoRedirect parameter that is sent by AutoValidator forms when they submit via AJAX
	 *
	 * @return boolean
	 */
	protected function _noRedirect()
	{
		return ($this->_input->filterSingle('_xfNoRedirect', XenForo_Input::UINT) ? true : false);
	}

	/**
	 * Canonicalizes the request URL based on the given link URL. Canonicalization will
	 * only happen when requesting an HTML page, as it is primarily an SEO benefit.
	 *
	 * A response exception will be thrown is redirection is required.
	 *
	 * @param string $linkUrl
	 */
	public function canonicalizeRequestUrl($linkUrl)
	{
		if ($this->getResponseType() != 'html')
		{
			return;
		}

		if (!$this->_request->isGet())
		{
			return;
		}

		$linkUrl = strval($linkUrl);

		if (strlen($linkUrl) == 0)
		{
			return;
		}

		if ($linkUrl[0] == '.')
		{
			$linkUrl = substr($linkUrl, 1);
		}

		$basePath = $this->_request->getBasePath();
		$requestUri = $this->_request->getRequestUri();

		if (substr($requestUri, 0, strlen($basePath)) != $basePath)
		{
			return;
		}

		$routeBase = substr($requestUri, strlen($basePath));
		if (isset($routeBase[0]) && $routeBase[0] === '/')
		{
			$routeBase = substr($routeBase, 1);
		}

		if (preg_match('#^([^?]*\?[^=&]*)(&(.*))?$#U', $routeBase, $match))
		{
			$requestUrlPrefix = $match[1];
			$requestParams = isset($match[3]) ? $match[3] : false;
		}
		else
		{
			$parts = explode('?', $routeBase);
			$requestUrlPrefix = $parts[0];
			$requestParams = isset($parts[1]) ? $parts[1]: false;
		}

		if (preg_match('#^([^?]*\?[^=&]*)(&(.*))?$#U', $linkUrl, $match))
		{
			$linkUrlPrefix = $match[1];
			//$linkParams = isset($match[3]) ? $match[3] : false;
		}
		else
		{
			$parts = explode('?', $linkUrl);
			$linkUrlPrefix = $parts[0];
			//$linkParams = isset($parts[1]) ? $parts[1]: false;
		}

		if (urldecode($requestUrlPrefix) != urldecode($linkUrlPrefix))
		{
			$redirectUrl = $linkUrlPrefix;
			if ($requestParams !== false)
			{
				$redirectUrl .= (strpos($redirectUrl, '?') === false ? '?' : '&') . $requestParams;
			}

			throw $this->responseException($this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
				$redirectUrl
			));
		}
	}

	/**
	 * Ensures that the page that has been requested is valid based on the total
	 * number of results. If it's not valid, the page is redirected to the last
	 * valid page (via a response exception).
	 *
	 * @param integer $page
	 * @param integer $perPage
	 * @param integer $total
	 * @param string $linkType
	 * @param mixed $linkData
	 */
	public function canonicalizePageNumber($page, $perPage, $total, $linkType, $linkData = null)
	{
		if ($this->getResponseType() != 'html' || !$this->_request->isGet())
		{
			return;
		}

		if ($perPage < 1 || $total < 1)
		{
			return;
		}

		$page = max(1, $page);
		$maxPage = ceil($total / $perPage);

		if ($page <= $maxPage)
		{
			return; // within the range
		}

		$params = $_GET;
		if ($maxPage <= 1)
		{
			unset($params['page']);
		}
		else
		{
			$params['page'] = $maxPage;
		}

		$redirectUrl = $this->_buildLink($linkType, $linkData, $params);

		throw $this->responseException($this->responseRedirect(
			XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL,
			$redirectUrl
		));
	}

	/**
	 * If the controller needs to build a link in a type-specific way (when the type isn't
	 * known), this function can be used. As of this writing, only canonicalizePageNumber
	 * uses this function.
	 *
	 * @param string $type
	 * @param mixed $data
	 * @param array $params
	 *
	 * @return string URL for link
	 */
	protected function _buildLink($type, $data = null, array $params = array())
	{
		throw new XenForo_Exception('_buildLink must be overridden in the abstract controller for the specified type.');
	}

	/**
	* Controller response for when you want to reroute to a different controller/action.
	*
	* @param string Name of the controller to reroute to
	* @param string Name of the action to reroute to
	* @param array  Key-value pairs of parameters to pass to the container view
	*
	* @return XenForo_ControllerResponse_Reroute
	*/
	public function responseReroute($controllerName, $action, array $containerParams = array())
	{
		$controllerResponse = new XenForo_ControllerResponse_Reroute();
		$controllerResponse->controllerName = $controllerName;
		$controllerResponse->action = $action;
		$controllerResponse->containerParams = $containerParams;

		return $controllerResponse;
	}

	/**
	* Controller response for when you want to redirect to a different URL. This will
	* happen in a separate request.
	*
	* @param integer See {@link XenForo_ControllerResponse_Redirect}
	* @param string Target to redirect to
	* @param mixed Message with which to redirect
	* @param array Extra parameters for the redirect
	*
	* @return XenForo_ControllerResponse_Redirect
	*/
	public function responseRedirect($redirectType, $redirectTarget, $redirectMessage = null, array $redirectParams = array())
	{
		switch ($redirectType)
		{
			case XenForo_ControllerResponse_Redirect::RESOURCE_CREATED:
			case XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED:
			case XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL:
			case XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT:
			case XenForo_ControllerResponse_Redirect::SUCCESS:
				break;

			default:
				throw new XenForo_Exception('Unknown redirect type');
		}

		$controllerResponse = new XenForo_ControllerResponse_Redirect();
		$controllerResponse->redirectType = $redirectType;
		$controllerResponse->redirectTarget = $redirectTarget;
		$controllerResponse->redirectMessage = $redirectMessage;
		$controllerResponse->redirectParams = $redirectParams;

		return $controllerResponse;
	}

	/**
	* Controller response for when you want to throw an error and display it to the user.
	*
	* @param string|array  Error text to be use
	* @param integer An optional HTTP response code to output
	* @param array   Key-value pairs of parameters to pass to the container view
	*
	* @return XenForo_ControllerResponse_Error
	*/
	public function responseError($error, $responseCode = 200, array $containerParams = array())
	{
		$controllerResponse = new XenForo_ControllerResponse_Error();
		$controllerResponse->errorText = $error;
		$controllerResponse->responseCode = $responseCode;
		$controllerResponse->containerParams = $containerParams;

		return $controllerResponse;
	}

	/**
	* Controller response for when you want to display a message to a user.
	*
	* @param string  Error text to be use
	* @param array   Key-value pairs of parameters to pass to the container view
	*
	* @return XenForo_ControllerResponse_Message
	*/
	public function responseMessage($message, array $containerParams = array())
	{
		$controllerResponse = new XenForo_ControllerResponse_Message();
		$controllerResponse->message = $message;
		$controllerResponse->containerParams = $containerParams;

		return $controllerResponse;
	}

	/**
	 * Gets the exception object for controller response-style behavior. This object
	 * cannot be returned from the controller; an exception must be thrown with it.
	 *
	 * This allows any type of controller response to be invoked via an exception.
	 *
	 * @param XenForo_ControllerResponse_Abstract $controllerResponse Type of response to invoke
	 * @param integer HTTP response code
	 *
	 * @return XenForo_ControllerResponse_Exception
	 */
	public function responseException(XenForo_ControllerResponse_Abstract $controllerResponse, $responseCode = null)
	{
		if ($responseCode)
		{
			$controllerResponse->responseCode = $responseCode;
		}
		return new XenForo_ControllerResponse_Exception($controllerResponse);
	}

	/**
	 * Gets the response for a generic CAPTCHA failed error.
	 *
	 * @return XenForo_ControllerResponse_Error
	 */
	public function responseCaptchaFailed()
	{
		return $this->responseError(new XenForo_Phrase('did_not_complete_the_captcha_verification_properly'));
	}

	/**
	 * Gets a general no permission error wrapped in an exception response.
	 *
	 * @return XenForo_ControllerResponse_Exception
	 */
	public function getNoPermissionResponseException()
	{
		return $this->responseException($this->responseNoPermission());
	}

	/**
	 * Gets a specific error or a general no permission response exception.
	 * If the first param is a string and $stringToPhrase is true, it will be treated
	 * as a phrase key and turned into a phrase.
	 *
	 * If a specific phrase is requested, a general error will be thrown. Otherwise,
	 * a generic no permission error will be shown.
	 *
	 * @param string|XenForo_Phrase|mixed $errorPhraseKey A phrase key, a phrase object, or hard coded text. Or, may be empty.
	 * @param boolean $stringToPhrase If true and the $errorPhraseKey is a string, $errorPhraseKey is treated as the name of a phrase.
	 *
	 * @return XenForo_ControllerResponse_Exception
	 */
	public function getErrorOrNoPermissionResponseException($errorPhraseKey, $stringToPhrase = true)
	{
		if ($errorPhraseKey && (is_string($errorPhraseKey) || is_array($errorPhraseKey)) && $stringToPhrase)
		{
			$error = new XenForo_Phrase($errorPhraseKey);
		}
		else
		{
			$error = $errorPhraseKey;
		}

		if ($errorPhraseKey)
		{
			return $this->responseException($this->responseError($error));
		}
		else
		{
			return $this->getNoPermissionResponseException();
		}
	}

	/**
	 * Gets the response for a generic flooding page.
	 *
	 * @param integer $floodSeconds Numbers of seconds the user must wait to perform the action
	 *
	 * @return XenForo_ControllerResponse_Error
	 */
	public function responseFlooding($floodSeconds)
	{
		return $this->responseError(new XenForo_Phrase('must_wait_x_seconds_before_performing_this_action', array('count' => $floodSeconds)));
	}

	/**
	 * Helper to assert that this action is available over POST only. Throws
	 * an exception if the request is not via POST.
	 */
	protected function _assertPostOnly()
	{
		if (!$this->_request->isPost())
		{
			throw $this->responseException(
				$this->responseError(new XenForo_Phrase('action_available_via_post_only'), 500)
			);
		}
	}

	/**
	 * Fetches name/value/existingDataKey from input. Primarily used for AJAX autovalidation actions of single fields.
	 *
	 * @return array [name, value, existingDataKey]
	 */
	protected function _getFieldValidationInputParams()
	{
		return $this->_input->filter(array(
			'name'            => XenForo_Input::STRING,
			'value'           => XenForo_Input::STRING,
			'existingDataKey' => XenForo_Input::STRING,
		));
	}

	/**
	 * Validates a field against a DataWriter.
	 * Expects 'name' and 'value' keys to be present in the request.
	 *
	 * @param string Name of DataWriter against which this field will be validated
	 * @param array Array containing name, value or existingDataKey, which will override those fetched from _getFieldValidationInputParams
	 *
	 * @return XenForo_ControllerResponse_Redirect|XenForo_ControllerResponse_Error
	 */
	protected function _validateField($dataWriterName, array $data = array())
	{
		$data = array_merge($this->_getFieldValidationInputParams(), $data);

		$writer = XenForo_DataWriter::create($dataWriterName);

		if (!empty($data['existingDataKey']) || $data['existingDataKey'] === '0')
		{
			$writer->setExistingData($data['existingDataKey']);
		}

		$writer->set($data['name'], $data['value']);

		if ($errors = $writer->getErrors())
		{
			return $this->responseError($errors);
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			'',
			new XenForo_Phrase('redirect_field_validated', array('name' => $data['name'], 'value' => $data['value']))
		);
	}

	/**
	 * Instructs a DataWriter to delete data based on a POST input parameter.
	 *
	 * @param string Name of DataWriter class that will perform the deletion
	 * @param string|array Name of input parameter that contains the existing data key OR array containing the keys for a multi-key parameter
	 * @param string URL to which to redirect on success
	 * @param string Redirection message to show on successful deletion
	 */
	protected function _deleteData($dataWriterName, $existingDataKeyName, $redirectLink, $redirectMessage = null)
	{
		$this->_assertPostOnly();

		$dw = XenForo_DataWriter::create($dataWriterName);

		$dw->setExistingData((is_array($existingDataKeyName)
			? $existingDataKeyName
			: $this->_input->filterSingle($existingDataKeyName, XenForo_Input::STRING)
		));

		$dw->delete();

		if (is_null($redirectMessage))
		{
			$redirectMessage = new XenForo_Phrase('deletion_successful');
		}

		return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, $redirectLink, $redirectMessage);
	}

	/**
	 * Returns true if the request method is POST and an _xfConfirm parameter exists and is true
	 *
	 * @return boolean
	 */
	public function isConfirmedPost()
	{
		return ($this->_request->isPost() && $this->_input->filterSingle('_xfConfirm', XenForo_Input::UINT));
	}

	/**
	* Controller response for when you want to output using a view class.
	*
	* @param string Name of the view class to be rendered
	* @param string Name of the template that should be displayed (may be ignored by view)
	* @param array  Key-value pairs of parameters to pass to the view
	* @param array  Key-value pairs of parameters to pass to the container view
	*
	* @return XenForo_ControllerResponse_View
	*/
	public function responseView($viewName, $templateName = 'DEFAULT', array $params = array(), array $containerParams = array())
	{
		$controllerResponse = new XenForo_ControllerResponse_View();
		$controllerResponse->viewName = $viewName;
		$controllerResponse->templateName = $templateName;
		$controllerResponse->params = $params;
		$controllerResponse->containerParams = $containerParams;

		return $controllerResponse;
	}

	/**
	 * Creates the specified helper class. If no underscore is present in the class
	 * name, "XenForo_ControllerHelper_" is prefixed. Otherwise, a full class name
	 * is assumed.
	 *
	 * @param string $class Full class name, or partial suffix (if no underscore)
	 *
	 * @return XenForo_ControllerHelper_Abstract
	 */
	public function getHelper($class)
	{
		if (strpos($class, '_') === false)
		{
			$class = 'XenForo_ControllerHelper_' . $class;
		}

		return new $class($this);
	}

	/**
	 * Gets a valid record or throws an exception.
	 *
	 * @param mixed $id ID of the record to get
	 * @param XenForo_Model $model Model object to request from
	 * @param string $method Method to call in the model object
	 * @param string $errorPhraseKey Key of error phrase to use when not found
	 *
	 * @return array
	 */
	public function getRecordOrError($id, $model, $method, $errorPhraseKey)
	{
		$info = $model->$method($id);
		if (!$info)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase($errorPhraseKey), 404));
		}

		return $info;
	}

	/**
	 * Gets a dynamic redirect target based on a redirect param or the referrer.
	 *
	 * @param string|false $fallbackUrl Fallback if no redirect or referrer is available; if false, uses index
	 * @param boolean $useReferrer True uses the referrer if no redirect param is available
	 *
	 * @return string
	 */
	public function getDynamicRedirect($fallbackUrl = false, $useReferrer = true)
	{
		$redirect = $this->_input->filterSingle('redirect', XenForo_Input::STRING);
		if (!$redirect && $useReferrer)
		{
			$redirect = $this->_request->getServer('HTTP_REFERER');
		}

		if ($redirect)
		{
			$redirectParts = @parse_url(XenForo_Link::convertUriToAbsoluteUri($redirect, true));
			if ($redirectParts)
			{
				$paths = XenForo_Application::get('requestPaths');
				$pageParts = @parse_url($paths['fullUri']);

				if ($pageParts && $pageParts['host'] == $redirectParts['host'])
				{
					return $redirect;
				}
			}
		}

		if ($fallbackUrl === false)
		{
			if ($this instanceof XenForo_ControllerAdmin_Abstract)
			{
				$fallbackUrl = XenForo_Link::buildAdminLink('index');
			}
			else
			{
				$fallbackUrl = XenForo_Link::buildPublicLink('index');
			}
		}

		return $fallbackUrl;
	}

	/**
	 * Turns a serialized (by jQuery) query string from input into a XenForo_Input object.
	 *
	 * @param string Name of index to fetch from $this->_input
	 * @param boolean On error, throw an exception or return false
	 * @param string
	 *
	 * @return XenForo_Input|false
	 */
	protected function _getInputFromSerialized($varname, $throw = true, &$errorPhraseKey = null)
	{
		if ($inputString = $this->_input->filterSingle($varname, XenForo_Input::STRING))
		{
			try
			{
				return new XenForo_Input(XenForo_Application::parseQueryString($inputString));
			}
			catch (Exception $e)
			{
				$errorPhraseKey = 'string_could_not_be_converted_to_input';

				if ($throw)
				{
					throw $this->responseException(
						$this->responseError(new XenForo_Phrase($errorPhraseKey))
					);
				}
			}
		}

		return false;
	}

	/**
	 * Checks for a match of one or more IPs against a list of IP and IP fragments
	 *
	 * @param string|array IP address(es)
	 * @param array List of IP addresses
	 *
	 * @return boolean
	 */
	public function ipMatch($checkIps, array $ipList)
	{
		if (!is_array($checkIps))
		{
			$checkIps = array($checkIps);
		}

		foreach ($checkIps AS $ip)
		{
			$ipClassABlock = intval($ip);
			$long = sprintf('%u', ip2long($ip));

			if (isset($ipList[$ipClassABlock]))
			{
				foreach ($ipList[$ipClassABlock] AS $range)
				{
					if ($long >= $range[0] && $long <= $range[1])
					{
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Returns an array of IPs for the current client
	 *
	 * @return
	 */
	protected function _getClientIps()
	{
		$ips = preg_split('/,\s*/', $this->_request->getClientIp(true));
		$ips[] = $this->_request->getClientIp(false);

		return array_unique($ips);
	}
}