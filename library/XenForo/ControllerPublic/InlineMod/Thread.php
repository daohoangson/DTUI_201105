<?php

/**
 * Inline moderation actions for threads
 *
 * @package XenForo_Thread
 */
class XenForo_ControllerPublic_InlineMod_Thread extends XenForo_ControllerPublic_InlineMod_Abstract
{
	/**
	 * Key for inline mod data.
	 *
	 * @var string
	 */
	public $inlineModKey = 'threads';

	/**
	 * @return XenForo_Model_InlineMod_Thread
	 */
	public function getInlineModTypeModel()
	{
		return $this->getModelFromCache('XenForo_Model_InlineMod_Thread');
	}

	/**
	 * Thread deletion handler.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDelete()
	{
		if ($this->isConfirmedPost())
		{
			$threadIds = $this->getInlineModIds(false);

			$hardDelete = $this->_input->filterSingle('hard_delete', XenForo_Input::STRING);
			$options = array(
				'deleteType' => ($hardDelete ? 'hard' : 'soft'),
				'reason' => $this->_input->filterSingle('reason', XenForo_Input::STRING)
			);

			$deleted = $this->_getInlineModThreadModel()->deleteThreads(
				$threadIds, $options, $errorPhraseKey
			);
			if (!$deleted)
			{
				throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
			}

			$this->clearCookie();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				$this->getDynamicRedirect(false, false)
			);
		}
		else // show confirmation dialog
		{
			$threadIds = $this->getInlineModIds();

			$handler = $this->_getInlineModThreadModel();
			if (!$handler->canDeleteThreads($threadIds, 'soft', $errorPhraseKey))
			{
				throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
			}

			$redirect = $this->getDynamicRedirect();

			if (!$threadIds)
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					$redirect
				);
			}

			$viewParams = array(
				'threadIds' => $threadIds,
				'threadCount' => count($threadIds),
				'canHardDelete' => $handler->canDeleteThreads($threadIds, 'hard'),
				'redirect' => $redirect,
			);

			return $this->responseView('XenForo_ViewPublic_InlineMod_Thread_Delete', 'inline_mod_thread_delete', $viewParams);
		}
	}

	/**
	 * Undeletes the specified threads.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionUndelete()
	{
		return $this->executeInlineModAction('undeleteThreads');
	}

	/**
	 * Approves the specified threads.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionApprove()
	{
		return $this->executeInlineModAction('approveThreads');
	}

	/**
	 * Unapproves the specified threads.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionUnapprove()
	{
		return $this->executeInlineModAction('unapproveThreads');
	}

	/**
	 * Lock the specified threads.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionLock()
	{
		return $this->executeInlineModAction('lockThreads');
	}

	/**
	 * Unlock the specified threads.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionUnlock()
	{
		return $this->executeInlineModAction('unlockThreads');
	}

	/**
	 * Stick the specified threads.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionStick()
	{
		return $this->executeInlineModAction('stickThreads');
	}

	/**
	 * Unstick the specified threads.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionUnstick()
	{
		return $this->executeInlineModAction('unstickThreads');
	}

	/**
	 * Thread move handler
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionMove()
	{
		if ($this->isConfirmedPost())
		{
			$threadIds = $this->getInlineModIds(false);
			$input = $this->_input->filter(array(
				'node_id' => XenForo_Input::UINT,
				'create_redirect' => XenForo_Input::STRING,
				'redirect_ttl_value' => XenForo_Input::UINT,
				'redirect_ttl_unit' => XenForo_Input::STRING
			));

			$viewableNodes = $this->getModelFromCache('XenForo_Model_Node')->getViewableNodeList();
			if (isset($viewableNodes[$input['node_id']]))
			{
				$targetNode = $viewableNodes[$input['node_id']];
			}
			else
			{
				return $this->responseNoPermission();
			}

			if ($input['create_redirect'] == 'permanent')
			{
				$options = array('redirect' => true, 'redirectExpiry' => 0);
			}
			else if ($input['create_redirect'] == 'expiring')
			{
				$expiryDate = strtotime('+' . $input['redirect_ttl_value'] . ' ' . $input['redirect_ttl_unit']);
				$options = array('redirect' => true, 'redirectExpiry' => $expiryDate);
			}
			else
			{
				$options = array('redirect' => false);
			}

			if (!$this->_getInlineModThreadModel()->moveThreads($threadIds, $input['node_id'], $options, $errorPhraseKey))
			{
				throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
			}

			$this->clearCookie();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('forums', $targetNode)
			);
		}
		else // show confirmation dialog
		{
			$threadIds = $this->getInlineModIds();

			$handler = $this->_getInlineModThreadModel();
			if (!$handler->canMoveThreads($threadIds, 0, $errorPhraseKey))
			{
				throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
			}

			$redirect = $this->getDynamicRedirect();

			if (!$threadIds)
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					$redirect
				);
			}

			$firstThread = $this->_getThreadModel()->getThreadById(reset($threadIds));

			$viewParams = array(
				'threadIds' => $threadIds,
				'threadCount' => count($threadIds),
				'firstThread' => $firstThread,
				'nodeOptions' => $this->getModelFromCache('XenForo_Model_Node')->getViewableNodeList(),
				'redirect' => $redirect,
			);

			return $this->responseView('XenForo_ViewPublic_InlineMod_Thread_Move', 'inline_mod_thread_move', $viewParams);
		}
	}

	/**
	 * Thread merge handler
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionMerge()
	{
		if ($this->isConfirmedPost())
		{
			$threadIds = $this->getInlineModIds(false);
			$input = $this->_input->filter(array(
				'target_thread_id' => XenForo_Input::UINT,
				'create_redirect' => XenForo_Input::STRING,
				'redirect_ttl_value' => XenForo_Input::UINT,
				'redirect_ttl_unit' => XenForo_Input::STRING
			));

			if ($input['create_redirect'] == 'permanent')
			{
				$options = array('redirect' => true, 'redirectExpiry' => 0);
			}
			else if ($input['create_redirect'] == 'expiring')
			{
				$expiryDate = strtotime('+' . $input['redirect_ttl_value'] . ' ' . $input['redirect_ttl_unit']);
				$options = array('redirect' => true, 'redirectExpiry' => $expiryDate);
			}
			else
			{
				$options = array('redirect' => false);
			}

			$targetThread = $this->_getInlineModThreadModel()->mergeThreads($threadIds, $input['target_thread_id'], $options, $errorPhraseKey);
			if (!$targetThread)
			{
				throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
			}

			$this->clearCookie();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('threads', $targetThread)
			);
		}
		else // show confirmation dialog
		{
			$threadIds = $this->getInlineModIds();

			$handler = $this->_getInlineModThreadModel();
			if (!$handler->canMergeThreads($threadIds, $errorPhraseKey))
			{
				throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
			}

			$redirect = $this->getDynamicRedirect();

			if (!$threadIds)
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					$redirect
				);
			}

			$threads = $this->_getThreadModel()->getThreadsByIds($threadIds);

			$viewParams = array(
				'threadIds' => $threadIds,
				'threadCount' => count($threadIds),
				'threads' => $threads,
				'redirect' => $redirect,
			);

			return $this->responseView('XenForo_ViewPublic_InlineMod_Thread_Merge', 'inline_mod_thread_merge', $viewParams);
		}
	}

	/**
	 * @return XenForo_Model_InlineMod_Thread
	 */
	public function _getInlineModThreadModel()
	{
		return $this->getModelFromCache('XenForo_Model_InlineMod_Thread');
	}

	/**
	 * @return XenForo_Model_Thread
	 */
	public function _getThreadModel()
	{
		return $this->getModelFromCache('XenForo_Model_Thread');
	}
}