<?php

/**
 * View to handle displaying an attachment.
 *
 * @package XenForo_Attachment
 */
class XenForo_ViewPublic_Attachment_View extends XenForo_ViewPublic_Base
{
	public function renderRaw()
	{
		$attachment = $this->_params['attachment'];

		$extension = XenForo_Helper_File::getFileExtension($attachment['filename']);
		$imageTypes = array(
			'gif' => 'image/gif',
			'jpg' => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'jpe' => 'image/jpeg',
			'png' => 'image/png'
		);

		if (in_array($extension, array_keys($imageTypes)))
		{
			$this->_response->setHeader('Content-type', $imageTypes[$extension], true);
			$this->setDownloadFileName($attachment['filename'], true);
		}
		else
		{
			$this->_response->setHeader('Content-type', 'unknown/unknown', true);
			$this->setDownloadFileName($attachment['filename']);
		}

		$this->_response->setHeader('ETag', $attachment['attach_date'], true);
		$this->_response->setHeader('Content-Length', $attachment['file_size'], true);

		return new XenForo_FileOutput($this->_params['attachmentFile']);
	}
}