<?php

/**
 * View for displaying a form to upload more attachments, and listing those that already exist
 *
 * @package XenForo_Attachment
 */
class XenForo_ViewPublic_Attachment_Upload extends XenForo_ViewPublic_Base
{
	public function renderJson()
	{
		$this->_templateName = 'attachment_upload_overlay';
	}
}