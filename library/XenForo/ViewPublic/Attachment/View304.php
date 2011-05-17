<?php

/**
 * View to handle returning 304 .
 *
 * @package XenForo_Attachment
 */
class XenForo_ViewPublic_Attachment_View304 extends XenForo_ViewPublic_Base
{
	public function renderRaw()
	{
		$this->_response->setHttpResponseCode(304);
		$this->_response->clearHeader('Last-Modified');

		return '';
	}
}