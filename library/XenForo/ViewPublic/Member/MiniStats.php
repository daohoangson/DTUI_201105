<?php

class XenForo_ViewPublic_Member_MiniStats extends XenForo_ViewPublic_Base
{
	public function renderXml()
	{
		$document = new DOMDocument('1.0', 'utf-8');
		$document->formatOutput = true;

		$rootNode = $document->createElement('user');
		$document->appendChild($rootNode);

		XenForo_Helper_DevelopmentXml::createDomElements($rootNode, $this->_params['user']);

		return $document->saveXML();
	}

	public function renderJson()
	{
		return $this->_params['user'];
	}
}