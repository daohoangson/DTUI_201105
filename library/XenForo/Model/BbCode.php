<?php

/**
 * Model for BB code related behaviors.
 *
 * @package XenForo_BbCode
 */
class XenForo_Model_BbCode extends XenForo_Model
{
	/**
	 * Gets the specified BB code media site.
	 *
	 * @param string $id
	 *
	 * @return array|false
	 */
	public function getBbCodeMediaSiteById($id)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_bb_code_media_site
			WHERE media_site_id = ?
		', $id);
	}

	/**
	 * Gets all BB code media sites, ordered by title.
	 *
	 * @return array [site id] => info
	 */
	public function getAllBbCodeMediaSites()
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_bb_code_media_site
			ORDER BY site_title
		', 'media_site_id');
	}

	/**
	 * Converts a sring of line-break-separated BB code media site match URLs into
	 * an array of regexes to match against.
	 *
	 * @param string $urls
	 *
	 * @return array
	 */
	public function convertMatchUrlsToRegexes($urls)
	{
		if (!$urls)
		{
			return array();
		}

		$urls = preg_split('/(\r?\n)+/', $urls, -1, PREG_SPLIT_NO_EMPTY);
		$regexes = array();
		foreach ($urls AS $url)
		{
			$url = preg_quote($url, '#');
			$url = str_replace('\\*', '.*', $url);
			$url = str_replace('\{\$id\}', '(?P<id>[^"\'?&;/<>\#\[\]]+)', $url);
			$url = str_replace('\{\$id\:digits\}', '(?P<id>[0-9]+)', $url);

			$regexes[] = '#' . $url . '#i';
		}

		return $regexes;
	}

	/**
	 * Gets the BB code media site data for the cache.
	 *
	 * @return array
	 */
	public function getBbCodeMediaSitesForCache()
	{
		$sites = $this->getAllBbCodeMediaSites();
		$cache = array();
		foreach ($sites AS &$site)
		{
			$cache[$site['media_site_id']] = array(
				'embed_html' => $site['embed_html']
			);
		}

		return $cache;
	}

	/**
	 * Gets the BB code cache data.
	 *
	 * @return array
	 */
	public function getBbCodeCache()
	{
		return array(
			'mediaSites' => $this->getBbCodeMediaSitesForCache()
		);
	}

	/**
	 * Rebuilds the BB code cache.
	 *
	 * @return array
	 */
	public function rebuildBbCodeCache()
	{
		$cache = $this->getBbCodeCache();

		$this->_getDataRegistryModel()->set('bbCode', $cache);
		return $cache;
	}
}