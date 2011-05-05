<?php

/**
* View controller response. This should be used when there is advanced behavior
* that needs to be handled to render the page.
*
* @package XenForo_Mvc
*/
class XenForo_ControllerResponse_View extends XenForo_ControllerResponse_Abstract
{
	/**
	* Name of the view class to be rendered
	*
	* @var string
	*/
	public $viewName = '';

	/**
	 * Name of the template to be rendered.
	 *
	 * @var string
	 */
	public $templateName = '';

	/**
	* Key-value pairs of parameters to pass to the view
	*
	* @var array
	*/
	public $params = array();

	/**
	 * Key-value pairs of parameters to pass to the container
	 *
	 * @var array
	 */
	public $containerParams = array();

	/**
	 * View that should be rendered within this view.
	 *
	 * @var XenForo_ControllerResponse_View|null
	 */
	public $subView = null;
}