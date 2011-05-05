<?php

/**
 * Controller for attachment-related actions.
 *
 * @package XenForo_Attachment
 */
class XenForo_ControllerPublic_Attachment extends XenForo_ControllerPublic_Abstract
{
	/**
	 * Viewing an attachment.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIndex()
	{
		$attachmentId = $this->_input->filterSingle('attachment_id', XenForo_Input::UINT);
		$attachment = $this->_getAttachmentOrError($attachmentId);

		$tempHash = $this->_input->filterSingle('temp_hash', XenForo_Input::STRING);

		$attachmentModel = $this->_getAttachmentModel();

		if (!$attachmentModel->canViewAttachment($attachment, $tempHash))
		{
			return $this->responseNoPermission();
		}

		$filePath = $attachmentModel->getAttachmentDataFilePath($attachment);
		if (!file_exists($filePath) || !is_readable($filePath))
		{
			return $this->responseError(new XenForo_Phrase('attachment_cannot_be_shown_at_this_time'));
		}

		$this->canonicalizeRequestUrl(
			XenForo_Link::buildPublicLink('attachments', $attachment)
		);

		$eTag = $this->_request->getServer('HTTP_IF_NONE_MATCH');
		if ($eTag && $eTag == $attachment['attach_date'])
		{
			$this->_routeMatch->setResponseType('raw');
			return $this->responseView('XenForo_ViewPublic_Attachment_View304');
		}

		if (!$this->_input->filterSingle('embedded', XenForo_Input::UINT))
		{
			$attachmentModel->logAttachmentView($attachmentId);
		}

		$this->_routeMatch->setResponseType('raw');

		$viewParams = array(
			'attachment' => $attachment,
			'attachmentFile' => $filePath
		);

		return $this->responseView('XenForo_ViewPublic_Attachment_View', '', $viewParams);
	}

	/**
	 * Shows the form for uploading and managing attachments in various contexts.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionUpload()
	{
		$input = $this->_input->filter(array(
			'hash' => XenForo_Input::STRING,
			'content_type' => XenForo_Input::STRING,
			'content_data' => array(XenForo_Input::UINT, 'array' => true)
		));
		if (!$input['hash'])
		{
			$input['hash'] = $this->_input->filterSingle('temp_hash', XenForo_Input::STRING);
		}

		$this->_assertCanUploadAndManageAttachments($input['hash'], $input['content_type'], $input['content_data']);

		$attachmentModel = $this->_getAttachmentModel();
		$attachmentHandler = $attachmentModel->getAttachmentHandler($input['content_type']); // known to be valid
		$contentId = $attachmentHandler->getContentIdFromContentData($input['content_data']);

		$existingAttachments = ($contentId
			? $attachmentModel->getAttachmentsByContentId($input['content_type'], $contentId)
			: array()
		);
		$newAttachments = $attachmentModel->getAttachmentsByTempHash($input['hash']);

		$maxAttachments = $attachmentHandler->getAttachmentCountLimit();
		if ($maxAttachments === true)
		{
			$canUpload = true;
			$remainingUploads = true;
		}
		else
		{
			$remainingUploads = $maxAttachments - (count($existingAttachments) + count($newAttachments));
			$canUpload = ($remainingUploads > 0);
		}

		$viewParams = array(
			'attachmentConstraints' => $attachmentModel->getAttachmentConstraints(),
			'existingAttachments' => $existingAttachments,
			'newAttachments' => $newAttachments,

			'canUpload' => $canUpload,
			'remainingUploads' => $remainingUploads,

			'hash' => $input['hash'],
			'contentType' => $input['content_type'],
			'contentData' => $input['content_data'],
			'attachmentParams' => array(
				'hash' => $input['hash'],
				'content_type' => $input['content_type'],
				'content_data' => $input['content_data']
			)
		);

		return $this->responseView('XenForo_ViewPublic_Attachment_Upload', 'attachment_upload', $viewParams);
	}

	/**
	 * Handles uploading new attachments (and redirecting delete requests).
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDoUpload()
	{
		$this->_assertPostOnly();

		$deleteArray = array_keys($this->_input->filterSingle('delete', XenForo_Input::ARRAY_SIMPLE));
		$delete = reset($deleteArray);
		if ($delete)
		{
			$this->_request->setParam('attachment_id', $delete);
			return $this->responseReroute(__CLASS__, 'delete');
		}

		$input = $this->_input->filter(array(
			'hash' => XenForo_Input::STRING,
			'content_type' => XenForo_Input::STRING,
			'content_data' => array(XenForo_Input::UINT, 'array' => true)
		));
		if (!$input['hash'])
		{
			$input['hash'] = $this->_input->filterSingle('temp_hash', XenForo_Input::STRING);
		}

		$this->_assertCanUploadAndManageAttachments($input['hash'], $input['content_type'], $input['content_data']);

		$attachmentModel = $this->_getAttachmentModel();
		$attachmentHandler = $attachmentModel->getAttachmentHandler($input['content_type']); // known to be valid
		$contentId = $attachmentHandler->getContentIdFromContentData($input['content_data']);

		$existingAttachments = ($contentId
			? $attachmentModel->getAttachmentsByContentId($input['content_type'], $contentId)
			: array()
		);
		$newAttachments = $attachmentModel->getAttachmentsByTempHash($input['hash']);

		$maxAttachments = $attachmentHandler->getAttachmentCountLimit();
		if ($maxAttachments !== true)
		{
			$remainingUploads = $maxAttachments - (count($existingAttachments) + count($newAttachments));
			if ($remainingUploads <= 0)
			{
				return $this->responseError(new XenForo_Phrase(
					'you_may_not_upload_more_files_with_message_allowed_x',
					array('total' => $maxAttachments)
				));
			}
		}

		$attachmentConstraints = $attachmentModel->getAttachmentConstraints();

		$file = XenForo_Upload::getUploadedFile('upload');
		if (!$file)
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('attachments/upload', false, array(
					'hash' => $input['hash'],
					'content_type' => $input['content_type'],
					'content_data' => $input['content_data']
				))
			);
		}

		$file->setConstraints($attachmentConstraints);
		if (!$file->isValid())
		{
			return $this->responseError($file->getErrors());
		}
		$dataId = $attachmentModel->insertUploadedAttachmentData($file, XenForo_Visitor::getUserId());
		$attachmentId = $attachmentModel->insertTemporaryAttachment($dataId, $input['hash']);

		$message = new XenForo_Phrase('upload_completed_successfully');

		// return a view if noredirect has been requested and we are not deleting
		if ($this->_noRedirect())
		{
			$contentId = $attachmentHandler->getContentIdFromContentData($input['content_data']);

			//$newAttachments = $attachmentModel->getAttachmentsByTempHash($input['hash']);

			$attachment = $attachmentModel->getAttachmentById($attachmentId);

			$viewParams = array(
				'attachment' => $attachmentModel->prepareAttachment($attachment),
				'message' => $message
			);

			return $this->responseView('XenForo_ViewPublic_Attachment_DoUpload', '', $viewParams);
		}
		else
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('attachments/upload', false, array(
					'hash' => $input['hash'],
					'content_type' => $input['content_type'],
					'content_data' => $input['content_data']
				)),
				$message
			);
		}
	}

	/**
	 * Deletes the specified attachment.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDelete()
	{
		$this->_assertPostOnly();

		$input = $this->_input->filter(array(
			'attachment_id' => XenForo_Input::UINT,
			'hash' => XenForo_Input::STRING,
			'content_type' => XenForo_Input::STRING,
			'content_data' => array(XenForo_Input::UINT, 'array' => true)
		));
		if (!$input['hash'])
		{
			$input['hash'] = $this->_input->filterSingle('temp_hash', XenForo_Input::STRING);
		}

		$attachment = $this->_getAttachmentOrError($input['attachment_id']);
		if (!$this->_getAttachmentModel()->canDeleteAttachment($attachment, $input['hash']))
		{
			return $this->responseNoPermission();
		}

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_Attachment');
		$dw->setExistingData($attachment, true);
		$dw->delete();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('attachments/upload', false, array(
				'hash' => $input['hash'],
				'content_type' => $input['content_type'],
				'content_data' => $input['content_data']
			))
		);
	}

	public function updateSessionActivity($controllerResponse, $controllerName, $action) {}

	/**
	 * Asserts that the viewing user can upload and manage attachments.
	 *
	 * @param string $hash Unique hash
	 * @param string $contentType
	 * @param array $contentData
	 */
	protected function _assertCanUploadAndManageAttachments($hash, $contentType, array $contentData)
	{
		if (!$hash)
		{
			throw $this->getNoPermissionResponseException();
		}

		$attachmentHandler = $this->_getAttachmentModel()->getAttachmentHandler($contentType);
		if (!$attachmentHandler || !$attachmentHandler->canUploadAndManageAttachments($contentData))
		{
			 throw $this->getNoPermissionResponseException();
		}
	}

	/**
	 * Gets the specified attachment or throws an error.
	 *
	 * @param integer $attachment
	 *
	 * @return array
	 */
	protected function _getAttachmentOrError($attachmentId)
	{
		$attachment = $this->_getAttachmentModel()->getAttachmentById($attachmentId);
		if (!$attachment)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_attachment_not_found'), 404));
		}

		return $attachment;
	}

	/**
	 * @return XenForo_Model_Attachment
	 */
	protected function _getAttachmentModel()
	{
		return $this->getModelFromCache('XenForo_Model_Attachment');
	}
}