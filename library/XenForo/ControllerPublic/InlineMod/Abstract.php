<?php

/**
 * Abstract inline mod controller. Provides helper functions for inline mod
 * interactions, provided the inline mod model functions follow the correct
 * convention.
 */
abstract class XenForo_ControllerPublic_InlineMod_Abstract extends XenForo_ControllerPublic_Abstract
{
	/**
	 * Name of the key where inline mod IDs are searched for. Eg, posts or threads.
	 *
	 * @var string
	 */
	public $inlineModKey = '';

	/**
	 * Gets the inline mod model for the specific type.
	 *
	 * @return XenForo_Model
	 */
	abstract public function getInlineModTypeModel();

	/**
	 * Gets the selected inline mod IDs.
	 *
	 * @param boolean $fromCookie True to read from the cookie input
	 *
	 * @return array
	 */
	public function getInlineModIds($fromCookie = true)
	{
		$ids = $this->_input->filterSingle($this->inlineModKey, XenForo_Input::UINT, array('array' => true));
		if ($fromCookie)
		{
			$cookie = XenForo_Helper_Cookie::getCookie('inlinemod_' . $this->inlineModKey);
			if (is_string($cookie))
			{
				$ids = array_merge($ids, explode(',', $cookie));
				$ids = array_map('intval', $ids);

				$ids = array_unique($ids);
			}
		}

		return $ids;
	}

	/**
	 * Clears the inline mod cookie for the given type.
	 */
	public function clearCookie()
	{
		XenForo_Helper_Cookie::deleteCookie('inlinemod_' . $this->inlineModKey);
	}

	/**
	 * Switching action, to redirect to the correct real action,
	 * based on the value of the "a" param.
	 *
	 * @return XenForo_ControllerResponse_Reroute
	 */
	public function actionSwitch()
	{
		if ($this->_input->filterSingle('delete', XenForo_Input::STRING) !== '')
		{
			$action = 'delete';
		}
		else if ($this->_input->filterSingle('approve', XenForo_Input::STRING) !== '')
		{
			$action = 'approve';
		}
		else
		{
			$action = $this->_input->filterSingle('a', XenForo_Input::STRING);
		}

		return $this->responseReroute(get_class($this), $action);
	}

	/**
	 * Removes all selected inline mod items for the requested type.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDeselect()
	{
		$this->_assertPostOnly();

		$this->clearCookie();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			$this->getDynamicRedirect()
		);
	}

	public function actionChoose()
	{
		return $this->responseView('XenForo_ViewPublic_InlineMod_Choose', 'inline_mod_chooser');
	}

	/**
	 * Executes the specified inline mod action. This is a helper to wrap
	 * up the boilerplate code. Checks for post, executes the action, and returns
	 * the redirect response.
	 *
	 * @param string $functionName Name of function to call in the inline mod model
	 * @param array $options List of options to pass to function
	 * @param array $setupOptions Options that control the setup and calling of the function
	 *
	 * @return XenForo_ControllerResponse_Redirect
	 */
	public function executeInlineModAction($functionName, $options = array(), $setupOptions = array())
	{
		$this->_assertPostOnly();

		$setupOptions = array_merge(
			array('fromCookie' => true),
			$setupOptions
		);

		$ids = $this->getInlineModIds($setupOptions['fromCookie']);

		if (!$this->getInlineModTypeModel()->$functionName($ids, $options, $errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}

		$this->clearCookie();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			$this->getDynamicRedirect()
		);
	}

	/**
	 * Session activity details.
	 * @see XenForo_Controller::getSessionActivityDetailsForList()
	 */
	public static function getSessionActivityDetailsForList(array $activities)
	{
		return new XenForo_Phrase('performing_moderation_duties');
	}
}