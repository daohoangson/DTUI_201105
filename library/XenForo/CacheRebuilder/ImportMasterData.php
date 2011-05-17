<?php

/**
 * Cache rebuilder for core master data imports.
 *
 * @package XenForo_CacheRebuild
 */
class XenForo_CacheRebuilder_ImportMasterData extends XenForo_CacheRebuilder_Abstract
{
	/**
	 * Gets rebuild message.
	 */
	public function getRebuildMessage()
	{
		return new XenForo_Phrase('core_master_data');
	}

	/**
	 * Rebuilds the data.
	 *
	 * @see XenForo_CacheRebuilder_Abstract::rebuild()
	 */
	public function rebuild($position = 0, array &$options = array(), &$detailedMessage = '')
	{
		$options = array_merge(array(
			'root' => XenForo_Application::getInstance()->getRootDir() . '/install/data'
		), $options);

		$filesRoot = $options['root'];

		$detailedMessage = str_repeat(' . ', $position + 1);

		if ($position == 0)
		{
			XenForo_Model::create('XenForo_Model_AdminNavigation')->importAdminNavigationDevelopmentXml($filesRoot . '/admin_navigation.xml');
			XenForo_Model::create('XenForo_Model_Admin')->importAdminPermissionsDevelopmentXml($filesRoot . '/admin_permissions.xml');
			XenForo_Model::create('XenForo_Model_Option')->importOptionsDevelopmentXml($filesRoot . '/options.xml');
			XenForo_Model::create('XenForo_Model_RoutePrefix')->importPrefixesDevelopmentXml($filesRoot . '/route_prefixes.xml');
		}
		else if ($position == 1)
		{
			XenForo_Model::create('XenForo_Model_StyleProperty')->importStylePropertyDevelopmentXml($filesRoot . '/style_properties.xml', 0);
			XenForo_Model::create('XenForo_Model_StyleProperty')->importStylePropertyDevelopmentXml($filesRoot . '/admin_style_properties.xml', -1);
		}
		else
		{
			XenForo_Model::create('XenForo_Model_CodeEvent')->importEventsDevelopmentXml($filesRoot . '/code_events.xml');
			XenForo_Model::create('XenForo_Model_Cron')->importCronDevelopmentXml($filesRoot . '/cron.xml');
			XenForo_Model::create('XenForo_Model_Permission')->importPermissionsDevelopmentXml($filesRoot . '/permissions.xml');

			XenForo_Model::create('XenForo_Model_Node')->rebuildNodeTypeCache();
			XenForo_Model::create('XenForo_Model_ContentType')->rebuildContentTypeCache();
			XenForo_Model::create('XenForo_Model_Smilie')->rebuildSmilieCache();

			return true;
		}

		return $position + 1;
	}
}