<?php

class XenForo_ControllerPublic_Help extends XenForo_ControllerPublic_Abstract
{
	public function actionIndex()
	{
		$viewParams = array(
			'tosUrl' => XenForo_Dependencies_Public::getTosUrl()
		);

		return $this->_getWrapper('',
			$this->responseView('XenForo_ViewPublic_Help_Index', 'help_index', $viewParams)
		);
	}

	public function actionSmilies()
	{
		/* @var $smilieModel XenForo_Model_Smilie */
		$smilieModel = $this->getModelFromCache('XenForo_Model_Smilie');

		$smilies = $smilieModel->getAllSmilies();

		$viewParams = array(
			'smilies' => $smilieModel->prepareSmiliesForList($smilies)
		);

		return $this->_getWrapper('smilies',
			$this->responseView('XenForo_ViewPublic_Help_Smilies', 'help_smilies', $viewParams)
		);
	}

	public function actionBbCodes()
	{
		$viewParams = array(
			'mediaSites' => $this->getModelFromCache('XenForo_Model_BbCode')->getAllBbCodeMediaSites()
		);

		return $this->_getWrapper('bbCodes',
			$this->responseView('XenForo_ViewPublic_Help_BbCodes', 'help_bb_codes', $viewParams)
		);
	}

	public function actionTrophies()
	{
		/* @var $trophyModel XenForo_Model_Trophy */
		$trophyModel = $this->getModelFromCache('XenForo_Model_Trophy');

		$viewParams = array(
			'trophies' => $trophyModel->prepareTrophies($trophyModel->getAllTrophies())
		);

		return $this->_getWrapper('trophies',
			$this->responseView('XenForo_ViewPublic_Help_Trophies', 'help_trophies', $viewParams)
		);
	}

	public function actionTerms()
	{
		$options = XenForo_Application::get('options');

		if (!$options->tosUrl['type'])
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
				XenForo_Link::buildPublicLink('index')
			);
		}
		else if ($options->tosUrl['type'] == 'custom')
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
				$options->tosUrl['custom']
			);
		}

		return $this->_getWrapper('terms',
			$this->responseView('XenForo_ViewPublic_Help_Terms', 'help_terms')
		);
	}

	/**
	 * Session activity details.
	 * @see XenForo_Controller::getSessionActivityDetailsForList()
	 */
	public static function getSessionActivityDetailsForList(array $activities)
	{
		return new XenForo_Phrase('viewing_help');
	}

	protected function _getWrapper($selected, XenForo_ControllerResponse_View $subView)
	{
		$viewParams = array(
			'selected' => $selected,
			'tosUrl' => XenForo_Dependencies_Public::getTosUrl()
		);

		$wrapper = $this->responseView('XenForo_ViewPublic_Help_Wrapper', 'help_wrapper', $viewParams);
		$wrapper->subView = $subView;

		return $wrapper;
	}

	protected function _assertViewingPermissions($action)
	{
		if (strtolower($action) == 'terms')
		{
			return;
		}

		parent::_assertViewingPermissions($action);
	}
}