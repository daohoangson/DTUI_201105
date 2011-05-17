<?php

class XenForo_ViewAdmin_Language_ExportXml extends XenForo_ViewAdmin_Base
{
	public function renderXml()
	{
		$title = str_replace(' ', '-', utf8_romanize(utf8_deaccent($this->_params['language']['title'])));

		$this->setDownloadFileName('language-' . $title . '.xml');
		return $this->_params['xml']->saveXml();
	}
}