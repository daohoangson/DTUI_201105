<?php
class DTUI_Listener {
	public static function navigation_tabs(array &$extraTabs, $selectedTabId) {
		$extraTabs['dtui'] = array(
			'title' => new XenForo_Phrase('dtui_title'),
			'href' => XenForo_Link::buildPublicLink('dtui-entry-point'),
			'position' => 'end',
			'linksTemplate' => 'dtui_links_template',
			'selected' => $selectedTabId == 'dtui',
		);
	}
}