<?php

/**
 * Inline moderation actions for profile posts
 *
 * @package XenForo_ProfilePost
 */
class XenForo_ControllerPublic_InlineMod_ProfilePost extends XenForo_ControllerPublic_InlineMod_Abstract
{
	/**
	 * Key for inline mod data.
	 *
	 * @var string
	 */
	public $inlineModKey = 'profilePosts';

	/**
	 * @return XenForo_Model_InlineMod_ProfilePost
	 */
	public function getInlineModTypeModel()
	{
		return $this->getModelFromCache('XenForo_Model_InlineMod_ProfilePost');
	}

	/**
	 * Profile post deletion handler
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDelete()
	{
		if ($this->isConfirmedPost())
		{
			$hardDelete = $this->_input->filterSingle('hard_delete', XenForo_Input::STRING);
			$options = array(
				'deleteType' => ($hardDelete ? 'hard' : 'soft'),
				'reason' => $this->_input->filterSingle('reason', XenForo_Input::STRING)
			);

			return $this->executeInlineModAction('deleteProfilePosts', $options, array('fromCookie' => false));
		}
		else // show confirmation dialog
		{
			$profilePostIds = $this->getInlineModIds();

			$handler = $this->_getInlineModProfilePostModel();
			if (!$handler->canDeleteProfilePosts($profilePostIds, 'soft', $errorPhraseKey))
			{
				throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
			}

			$redirect = $this->getDynamicRedirect();

			if (!$profilePostIds)
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					$redirect
				);
			}

			$viewParams = array(
				'profilePostIds' => $profilePostIds,
				'profilePostCount' => count($profilePostIds),
				'canHardDelete' => $handler->canDeleteProfilePosts($profilePostIds, 'hard'),
				'redirect' => $redirect,
			);

			return $this->responseView('XenForo_ViewPublic_InlineMod_ProfilePost_Delete', 'inline_mod_profile_post_delete', $viewParams);
		}
	}

	/**
	 * Undeletes the specified profile posts.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionUndelete()
	{
		return $this->executeInlineModAction('undeleteProfilePosts');
	}

	/**
	 * Approves the specified profile posts.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionApprove()
	{
		return $this->executeInlineModAction('approveProfilePosts');
	}

	/**
	 * Unapproves the specified profile posts.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionUnapprove()
	{
		return $this->executeInlineModAction('unapproveProfilePosts');
	}

	/**
	 * @return XenForo_Model_InlineMod_ProfilePost
	 */
	public function _getInlineModProfilePostModel()
	{
		return $this->getModelFromCache('XenForo_Model_InlineMod_ProfilePost');
	}
}