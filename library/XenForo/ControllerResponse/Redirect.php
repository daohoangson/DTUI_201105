<?php

/**
* Redirect controller response. This indicates that we want to externally redirect the user
* to another page. Depending on the output type, this may not actually redirect. This may
* tell the user that we created the resource and give them the URL.
*
* @package XenForo_Mvc
*/
class XenForo_ControllerResponse_Redirect extends XenForo_ControllerResponse_Abstract
{
	/**
	* Use this only when a resource has been newly created and the redirect target is
	* the URL to the new resource. If the user is being redirected anywhere else,
	* use {@link SUCCESS}.
	*
	* @var int
	*/
	const RESOURCE_CREATED = 1;

	/**
	* Use this only when a resource has been updated and the redirect target is
	* the URL to the resource. If the user is being redirected anywhere else,
	* use {@link SUCCESS}.
	*
	* @var int
	*/
	const RESOURCE_UPDATED = 2;

	/**
	* Use this when no resource has been updated and the redirect target is the
	* canonical version of the target URL. For example, this would be used when
	* redirecting the URL /thread/1/new-post to /thread/1/page2#post25.
	*
	* This should be used when the URL receiving the request may reasonably redirect
	* to different pages at different times (ie, temporary redirect). If this redirect
	* is likely to be permanent, use {@link RESOURCE_CANONICAL_PERMANENT}.
	*
	* @var int
	*/
	const RESOURCE_CANONICAL = 3;

	/**
	* Use this when no resource has been updated and the redirect target is the
	* canonical version of the target URL. For example, this would be used when
	* redirecting the URL /post/25 to /thread/1#p25.
	*
	* This should only be used when there is no reasonable expectation that the redirect
	* target for a given request will vary (ie, permanent redirect). Otherwise, use
	* {@link RESOURCE_CANONICAL}.
	*
	* @var int
	*/
	const RESOURCE_CANONICAL_PERMANENT = 5;

	/**
	* General purpose redirect for when an action has been successfully carried out.
	* This might be used when a resource has been created or updated but isn't
	* the redirect target.
	*
	* @var int
	*/
	const SUCCESS = 4;

	public $redirectType = 4;

	public $redirectTarget = '';

	public $redirectMessage = '';

	public $redirectParams = array();
}