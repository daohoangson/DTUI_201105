<?php

/**
 * Controller for BB code media sites.
 *
 * @package XenForo_BbCode
 */
class XenForo_ControllerAdmin_BbCodeMediaSite extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		$this->assertAdminPermission('bbCodeSmilie');
	}

	/**
	 * Lists all BB code media sites.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIndex()
	{
		$viewParams = array(
			'sites' => $this->_getBbCodeModel()->getAllBbCodeMediaSites()
		);
		return $this->responseView('XenForo_ViewAdmin_BbCodeMediaSite_List', 'bb_code_media_site_list', $viewParams);
	}

	/**
	 * Gets the media site add/edit form responst.
	 *
	 * @param array $site
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	protected function _getMediaSiteAddEditResponse(array $site)
	{
		$viewParams = array(
			'site' => $site
		);
		return $this->responseView('XenForo_ViewAdmin_BbCodeMediaSite_Edit', 'bb_code_media_site_edit', $viewParams);
	}

	/**
	 * Displays a form to create a new media site.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionAdd()
	{
		return $this->_getMediaSiteAddEditResponse(array());
	}

	/**
	 * Displays a form to edit an existing media site.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEdit()
	{
		$mediaSiteId = $this->_input->filterSingle('media_site_id', XenForo_Input::STRING);
		$site = $this->_getBbCodeMediaSiteOrError($mediaSiteId);

		return $this->_getMediaSiteAddEditResponse($site);
	}

	/**
	 * Updates an existing media site or inserts a new one.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSave()
	{
		$this->_assertPostOnly();

		$currentMediaSiteId = $this->_input->filterSingle('media_site_id', XenForo_Input::STRING);
		$newMediaSiteId = $this->_input->filterSingle('new_media_site_id', XenForo_Input::STRING);

		$dwInput = $this->_input->filter(array(
			'site_title' => XenForo_Input::STRING,
			'site_url' => XenForo_Input::STRING,
			'match_urls' => XenForo_Input::STRING,
			'embed_html' => XenForo_Input::STRING,
			'supported' => XenForo_Input::UINT,
		));

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_BbCodeMediaSite');
		if ($currentMediaSiteId)
		{
			$dw->setExistingData($currentMediaSiteId);
		}
		$dw->set('media_site_id', $newMediaSiteId);
		$dw->bulkSet($dwInput);
		$dw->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('bb-code-media-sites') . $this->getLastHash($dw->get('media_site_id'))
		);
	}

	/**
	 * Deletes the specified media site.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDelete()
	{
		if ($this->isConfirmedPost())
		{
			return $this->_deleteData(
				'XenForo_DataWriter_BbCodeMediaSite', 'media_site_id',
				XenForo_Link::buildAdminLink('bb-code-media-sites')
			);
		}
		else // show confirmation dialog
		{
			$mediaSiteId = $this->_input->filterSingle('media_site_id', XenForo_Input::STRING);
			$site = $this->_getBbCodeMediaSiteOrError($mediaSiteId);

			$viewParams = array(
				'site' => $site
			);
			return $this->responseView('XenForo_ViewAdmin_BbCodeMediaSite_Delete', 'bb_code_media_site_delete', $viewParams);
		}
	}

	/**
	 * Gets the specified record or errors.
	 *
	 * @param string $id
	 *
	 * @return array
	 */
	protected function _getBbCodeMediaSiteOrError($id)
	{
		$info = $this->_getBbCodeModel()->getBbCodeMediaSiteById($id);
		if (!$info)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_bb_code_media_site_not_found'), 404));
		}

		return $info;
	}

	/**
	 * @return XenForo_Model_BbCode
	 */
	protected function _getBbCodeModel()
	{
		return $this->getModelFromCache('XenForo_Model_BbCode');
	}
}