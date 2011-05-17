<?php

/**
 * Exports a style as XML.
 *
 * @package XenForo_Style
 */
class XenForo_ViewAdmin_Style_Export extends XenForo_ViewAdmin_Base
{
	public function renderXml()
	{
		$title = str_replace(' ', '-', utf8_romanize(utf8_deaccent($this->_params['style']['title'])));

		$this->setDownloadFileName('style-' . $title . '.xml');
		return $this->_params['xml']->saveXml();
	}
}