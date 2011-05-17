<?php

/**
 * View for displaying a form to upload more attachments, and listing those that already exist
 *
 * @package XenForo_Attachment
 */
class XenForo_ViewPublic_Attachment_DoUpload extends XenForo_ViewPublic_Base
{
	public function renderJson()
	{
		$attach = $this->_prepareAttachmentForJson($this->_params['attachment']);
		if (!empty($this->_params['message']))
		{
			$attach['message'] = $this->_params['message'];
		}
		return XenForo_ViewRenderer_Json::jsonEncodeForOutput($attach);
	}

	/**
	 * Reduces down an array of attachment data into information we don't mind exposing,
	 * and includes the attachment_editor_attachment template for each attachment.
	 *
	 * @param array $attachment
	 *
	 * @return array
	 */
	protected function _prepareAttachmentForJson(array $attachment)
	{
		$keys = array('attachment_id', 'attach_date', 'filename', 'thumbnailUrl');

		$template = $this->createTemplateObject('attachment_editor_attachment', array('attachment' => $attachment));

		$attachment = XenForo_Application::arrayFilterKeys($attachment, $keys);

		$attachment['templateHtml'] = $template;

		return $attachment;
	}
}