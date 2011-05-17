<?php

/**
* Handles front controller dependencies for public-facing pages.
*
* @package XenForo_Mvc
*/
class XenForo_Dependencies_Public extends XenForo_Dependencies_Abstract
{
	/**
	 * List of data to pre-load from the data registry. You must process this data
	 * via {@link _handleCustomPreLoadedData()}.
	 *
	 * @var array
	 */
	protected $_dataPreLoadFromRegistry = array(
		'routesPublic', 'nodeTypes',
		'bannedIps', 'discouragedIps',
		'styles', 'displayStyles', 'smilies', 'bbCode',
		'trophyUserTitles', 'reportCounts', 'moderationCounts'
	);

	/**
	 * Handles the custom data that needs to be preloaded.
	 *
	 * @param array $data Data that was loaded. Unsuccessfully loaded items will have a value of null
	 */
	protected function _handleCustomPreloadedData(array &$data)
	{
		if (!is_array($data['routesPublic']))
		{
			$data['routesPublic'] = XenForo_Model::create('XenForo_Model_RoutePrefix')->rebuildRoutePrefixTypeCache('public');
		}
		XenForo_Link::setHandlerInfoForGroup('public', $data['routesPublic']);

		if (!is_array($data['bannedIps']))
		{
			$data['bannedIps'] = XenForo_Model::create('XenForo_Model_Banning')->rebuildBannedIpCache();
		}
		XenForo_Application::set('bannedIps', $data['bannedIps']);

		if (!is_array($data['discouragedIps']))
		{
			$data['discouragedIps'] = XenForo_Model::create('XenForo_Model_Banning')->rebuildDiscouragedIpCache();
		}
		XenForo_Application::set('discouragedIps', $data['discouragedIps']);

		if (!is_array($data['styles']))
		{
			$data['styles'] = XenForo_Model::create('XenForo_Model_Style')->rebuildStyleCache();
		}
		XenForo_Application::set('styles', $data['styles']);

		if (!is_array($data['nodeTypes']))
		{
			$data['nodeTypes'] = XenForo_Model::create('XenForo_Model_Node')->rebuildNodeTypeCache();
		}
		XenForo_Application::set('nodeTypes', $data['nodeTypes']);

		if (!is_array($data['smilies']))
		{
			$data['smilies'] = XenForo_Model::create('XenForo_Model_Smilie')->rebuildSmilieCache();
		}
		XenForo_Application::set('smilies', $data['smilies']);

		if (!is_array($data['bbCode']))
		{
			$data['bbCode'] = XenForo_Model::create('XenForo_Model_BbCode')->rebuildBbCodeCache();
		}
		XenForo_Application::set('bbCode', $data['bbCode']);

		if (!is_array($data['displayStyles']))
		{
			$data['displayStyles'] = XenForo_Model::create('XenForo_Model_UserGroup')->rebuildDisplayStyleCache();
		}
		XenForo_Application::set('displayStyles', $data['displayStyles']);
		XenForo_Template_Helper_Core::setDisplayStyles($data['displayStyles']);

		if (!is_array($data['trophyUserTitles']))
		{
			$data['trophyUserTitles'] = XenForo_Model::create('XenForo_Model_Trophy')->rebuildTrophyUserTitleCache();
		}
		XenForo_Application::set('trophyUserTitles', $data['trophyUserTitles']);
		XenForo_Template_Helper_Core::setUserTitles($data['trophyUserTitles']);

		if (is_array($data['reportCounts']))
		{
			XenForo_Application::set('reportCounts', $data['reportCounts']);
		}
		if (is_array($data['moderationCounts']))
		{
			XenForo_Application::set('moderationCounts', $data['moderationCounts']);
		}
	}

	/**
	* Routes the request.
	*
	* @see XenForo_Dependencies_Abstract::route()
	*/
	public function route(Zend_Controller_Request_Http $request)
	{
		$router = new XenForo_Router();
		$router->addRule(new XenForo_Route_ResponseSuffix(), 'ResponseSuffix')
		       ->addRule(new XenForo_Route_Prefix('public'), 'Prefix');

		return $router->match($request);
	}

	/**
	* Determines if the controller matched by the route can be dispatched. Use this
	* function to ensure, for example, that an admin page only shows an admin controller.
	*
	* @param mixed  Likely a XenForo_Controller object, but not guaranteed
	* @param string Name of the action to call
	*
	* @return boolean
	*/
	public function allowControllerDispatch($controller, $action)
	{
		return ($controller instanceof XenForo_ControllerPublic_Abstract);
	}

	/**
	* Gets the routing information for a not found error
	*
	* @return array Format: [0] => controller name, [1] => action
	*/
	public function getNotFoundErrorRoute()
	{
		return array('XenForo_ControllerPublic_Error', 'ErrorNotFound');
	}

	/**
	* Gets the routing information for a search error
	*
	* @return array Format: [0] => controller name, [1] => action
	*/
	public function getServerErrorRoute()
	{
		return array('XenForo_ControllerPublic_Error', 'ErrorServer');
	}

	/**
	* Creates the view renderer for a specified response type. If an invalid
	* type is specified, false is returned.
	*
	* @param Zend_Controller_Response_Http Response object
	* @param string                        Type of response
	* @param Zend_Controller_Request_Http  Request object
	*
	* @return XenForo_ViewRenderer_Abstract|false
	*/
	public function getViewRenderer(Zend_Controller_Response_Http $response, $responseType, Zend_Controller_Request_Http $request)
	{
		$renderer = parent::getViewRenderer($response, $responseType, $request);
		if (!$renderer)
		{
			$renderer = new XenForo_ViewRenderer_HtmlPublic($this, $response, $request);
		}
		return $renderer;
	}

	/**
	 * Gets the base view class name for this type.
	 */
	public function getBaseViewClassName()
	{
		return 'XenForo_ViewPublic_Base';
	}

	/**
	* Helper method to create a template object for rendering.
	*
	* @param string Name of the template to be used
	* @param array  Key-value parameters to pass to the template
	*
	* @return XenForo_Template_Public
	*/
	public function createTemplateObject($templateName, array $params = array())
	{
		if ($params)
		{
			$params = XenForo_Application::mapMerge($this->_defaultTemplateParams, $params);
		}
		else
		{
			$params = $this->_defaultTemplateParams;
		}

		return new XenForo_Template_Public($templateName, $params);
	}

	/**
	 * Gets extra container data from template renders.
	 */
	public function getExtraContainerData()
	{
		return XenForo_Template_Public::getExtraContainerData();
	}

	/**
	* Preloads a template with the template handler for use later.
	*
	* @param string Template name
	*/
	public function preloadTemplate($templateName)
	{
		XenForo_Template_Public::preLoadTemplate($templateName);
	}

	/**
	 * Performs any pre-view rendering setup, such as getting style information and
	 * ensuring the correct data is registered.
	 *
	 * @param XenForo_ControllerResponse_Abstract|null $controllerResponse
	 */
	public function preRenderView(XenForo_ControllerResponse_Abstract $controllerResponse = null)
	{
		parent::preRenderView($controllerResponse);

		if (!empty($this->_viewStateChanges['styleId']))
		{
			$styleId = $this->_viewStateChanges['styleId'];
			$forceStyleId = true;
		}
		else
		{
			$user = XenForo_Visitor::getInstance();
			$styleId = (!empty($user['style_id']) ? $user['style_id'] : 0);
			$forceStyleId = ($user['is_admin'] ? true : false);
		}

		XenForo_Template_Abstract::setLanguageId(XenForo_Phrase::getLanguageId());

		$styles = (XenForo_Application::isRegistered('styles')
			? XenForo_Application::get('styles')
			: XenForo_Model::create('XenForo_Model_Style')->getAllStyles()
		);

		if ($styleId && isset($styles[$styleId]) && ($styles[$styleId]['user_selectable'] || $forceStyleId))
		{
			$style = $styles[$styleId];
		}
		else
		{
			$defaultStyleId = XenForo_Application::get('options')->defaultStyleId;
			$style = (isset($styles[$defaultStyleId]) ? $styles[$defaultStyleId] : reset($styles));
		}

		if ($style)
		{
			XenForo_Template_Helper_Core::setStyleProperties(unserialize($style['properties']));
			XenForo_Template_Public::setStyleId($style['style_id']);
		}

		// setup the default template params
		if ($style)
		{
			$this->_defaultTemplateParams['visitorStyle'] = $style;
		}
	}

	/**
	* Gets the effective set of container params. This includes combining
	* and specific container params with any global ones. For example, a specific
	* container param may refer to the section the page is in, so this function
	* could load the other options that are specific to this section.
	*
	* @param array $params Container params from the controller/view
	* @param Zend_Controller_Request_Http $request
	*
	* @return array
	*/
	public function getEffectiveContainerParams(array $params, Zend_Controller_Request_Http $request)
	{
		$options = XenForo_Application::get('options');

		$visitor = XenForo_Visitor::getInstance();

		$params['canSearch'] = $visitor->canSearch();
		$params['canUploadAvatar'] = $visitor->canUploadAvatar();
		$params['canEditSignature'] = $visitor->canEditSignature();
		$params['canUpdateStatus'] = $visitor->canUpdateStatus();
		$params['isAwaitingEmailConfirmation'] = ($visitor['user_id'] && in_array($visitor['user_state'], array('email_confirm', 'email_confirm_edit')));
		$params['tosUrl'] = self::getTosUrl();
		$params['jQuerySource'] = self::getJquerySource();
		$params['jQuerySourceLocal'] = self::getJquerySource(true);
		$params['javaScriptSource'] = $options->javaScriptSource;

		$params['showBoardClosedNoticed'] = (
			!$options->boardActive
			&& XenForo_Visitor::getInstance()->get('is_admin')
		);

		$params = XenForo_Application::mapMerge($params,
			parent::getEffectiveContainerParams($params, $request),
			$this->_getCronContainerParams(),
			$this->_getStyleLanguageChangerParams($request),
			$this->_getNavigationContainerParams(empty($params['majorSection']) ? '' : $params['majorSection'])
		);

		XenForo_CodeEvent::fire('container_public_params', array(&$params, $this));

		return $params;
	}

	protected function _getStyleLanguageChangerParams(Zend_Controller_Request_Http $request)
	{
		$params = array();

		$canChangeStyleLanguage = ($request->isGet() && empty($this->_viewStateChanges['styleId']));
		if ($request->isGet())
		{
			if (!empty($this->_viewStateChanges['styleId']))
			{
				$params['canChangeStyle'] = false;
			}
			else
			{
				$styles = (XenForo_Application::isRegistered('styles')
					? XenForo_Application::get('styles')
					: array()
				);

				if (count($styles) <= 1)
				{
					$params['canChangeStyle'] = false;
				}
				else if (XenForo_Visitor::hasInstance() && XenForo_Visitor::getInstance()->is_admin)
				{
					$params['canChangeStyle'] = (count($styles) > 1);
				}
				else
				{
					$changable = 0;
					$params['canChangeStyle'] = false;

					foreach ($styles AS $style)
					{
						if ($style['user_selectable'])
						{
							$changable++;
							if ($changable > 1)
							{
								$params['canChangeStyle'] = true;
								break;
							}
						}
					}
				}
			}

			$languages = (XenForo_Application::isRegistered('languages')
				? XenForo_Application::get('languages')
				: array()
			);
			$params['canChangeLanguage'] = (count($languages) > 1);
		}
		else
		{
			$params['canChangeStyle'] = false;
			$params['canChangeLanguage'] = false;
		}

		return $params;
	}

	protected function _getNavigationContainerParams($selectedTabId)
	{
		$tabs = array(
			'forums' => array(
				'title' => new XenForo_Phrase('forums'),
				'href' => XenForo_Link::buildPublicLink('full:index')
			),
			'members' => array(
				'title' => new XenForo_Phrase('members'),
				'href' => XenForo_Link::buildPublicLink('full:members')
			),
			'help' => array(
				'title' => new XenForo_Phrase('help'),
				'href' => XenForo_Link::buildPublicLink('full:help')
			)
		);
		if (XenForo_Visitor::getUserId())
		{
			$tabs['account'] = array(
				'title' => new XenForo_Phrase('your_account'),
				'href' => XenForo_Link::buildPublicLink('full:account')
			);
		}

		$extraTabs = array();
		XenForo_CodeEvent::fire('navigation_tabs', array(&$extraTabs, $selectedTabId));

		if (!empty($extraTabs[$selectedTabId]))
		{
			$selectedTab = $extraTabs[$selectedTabId];
			$extraTabs[$selectedTabId]['selected'] = true;
		}
		else if (!empty($tabs[$selectedTabId]))
		{
			$selectedTab = $tabs[$selectedTabId];
			$tabs[$selectedTabId]['selected'] = true;
		}
		else
		{
			$selectedTabId = '';
			$selectedTab = false;
		}

		$extraTabsGrouped = array();
		foreach ($extraTabs AS $extraTabId => $extraTab)
		{
			if (empty($extraTab['position']))
			{
				$extraTab['position'] = 'middle';
			}

			$extraTabsGrouped[$extraTab['position']][$extraTabId] = $extraTab;
		}

		if (!empty($extraTabsGrouped['home']))
		{
			list($homeTabId, $homeTab) = each($extraTabsGrouped['home']);
		}
		else
		{
			$homeTabId = false;
			$homeTab = false;
		}

		$options = XenForo_Application::get('options');

		$showHomeLink = false;

		if ($options->homePageUrl)
		{
			$showHomeLink = true;
			$homeLink = $options->homePageUrl;
			$logoLink = ($options->logoLink ? $homeLink : XenForo_Link::buildPublicLink('full:index'));
		}
		else if ($homeTab)
		{
			$homeLink = $homeTab['href'];
			$logoLink = ($options->logoLink ? $homeTab['href'] : XenForo_Link::buildPublicLink('full:index'));
		}
		else
		{
			$homeTabId = 'forums';
			$homeTab = $tabs['forums'];

			$homeLink = $homeTab['href'];
			$logoLink = $homeLink;
		}

		return array(
			'tabs' => $tabs,
			'extraTabs' => $extraTabsGrouped,

			'selectedTab' => $selectedTab,
			'selectedTabId' => $selectedTabId,

			'homeTab' => $homeTab,
			'homeTabId' => $homeTabId,

			'showHomeLink' => $showHomeLink,
			'homeLink' => $homeLink,
			'logoLink' => $logoLink
		);
	}

	public static function getTosUrl()
	{
		$options = XenForo_Application::get('options');
		switch ($options->tosUrl['type'])
		{
			case 'default': return XenForo_Link::buildPublicLink('help/terms'); break;
			case 'custom': return $options->tosUrl['custom']; break;
			default: return '';
		}
	}

	/**
	 * Fetch the path / URL to the jQuery core library
	 *
	 * @param boolean $forceLocal If true, forces the local version of jQuery
	 *
	 * @return string
	 */
	public static function getJquerySource($forceLocal = false)
	{
		$jQueryVersion = XenForo_Application::$jQueryVersion;
		$min = '.min';

		$options = XenForo_Application::get('options');

		// CDN sources from http://docs.jquery.com/Downloading_jQuery#CDN_Hosted_jQuery
		$source = ($forceLocal ? 'local' : $options->jQuerySource);
		switch ($source)
		{
			case 'jquery':
			case 'mt':
				return "http://code.jquery.com/jquery-{$jQueryVersion}{$min}.js";

			case 'google':
				return "http://ajax.googleapis.com/ajax/libs/jquery/{$jQueryVersion}/jquery{$min}.js";

			case 'microsoft':
				return "http://ajax.microsoft.com/ajax/jquery/jquery-{$jQueryVersion}{$min}.js";

			default:
				return "{$options->javaScriptSource}/jquery/jquery-{$jQueryVersion}{$min}.js";
		}
	}
}