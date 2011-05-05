<?php

/**
* Data writer for BB code media sites.
*
* @package XenForo_BbCode
*/
class XenForo_DataWriter_BbCodeMediaSite extends XenForo_DataWriter
{
	/**
	 * Title of the phrase that will be created when a call to set the
	 * existing data fails (when the data doesn't exist).
	 *
	 * @var string
	 */
	protected $_existingDataErrorPhrase = 'requested_bb_code_media_site_not_found';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_bb_code_media_site' => array(
				'media_site_id' => array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 25,
						'verification' => array('$this', '_verifyMediaSiteId'),
						'requiredError' => 'please_enter_valid_media_site_id'
				),
				'site_title'    => array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 50,
						 'requiredError' => 'please_enter_valid_title'
				),
				'site_url'      => array('type' => self::TYPE_STRING, 'default' => '',    'maxLength' => 100,
						'verification' => array('$this', '_verifySiteUrl')
				),
				'match_urls'    => array('type' => self::TYPE_STRING, 'default' => '',
						'verification' => array('$this', '_verifyMatchUrls')
				),
				'embed_html'    => array('type' => self::TYPE_STRING, 'required' => true,
						'requiredError' => 'please_enter_embed_html'
				),
				'supported'    => array('type' => self::TYPE_BOOLEAN, 'default' => 1),
			)
		);
	}

	/**
	* Gets the actual existing data out of data that was passed in. See parent for explanation.
	*
	* @param mixed
	*
	* @return array|false
	*/
	protected function _getExistingData($data)
	{
		if (!$id = $this->_getExistingPrimaryKey($data, 'media_site_id'))
		{
			return false;
		}

		return array('xf_bb_code_media_site' => $this->_getBbCodeModel()->getBbCodeMediaSiteById($id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'media_site_id = ' . $this->_db->quote($this->getExisting('media_site_id'));
	}

	/**
	 * Verifies that the media site ID is valid.
	 *
	 * @param string $siteId
	 *
	 * @return boolean
	 */
	protected function _verifyMediaSiteId(&$siteId)
	{
		$siteId = strtolower($siteId);

		if (preg_match('/[^a-zA-Z0-9_]/', $siteId))
		{
			$this->error(new XenForo_Phrase('please_enter_an_id_using_only_alphanumeric'), 'media_site_id');
			return false;
		}

		if ($this->isInsert() || $siteId != $this->getExisting('media_site_id'))
		{
			$existing = $this->_getBbCodeModel()->getBbCodeMediaSiteById($siteId);
			if ($existing)
			{
				$this->error(new XenForo_Phrase('media_site_ids_must_be_unique'), 'media_site_id');
				return false;
			}
		}

		return true;
	}

	/**
	 * Verifies that the site URL is valid.
	 *
	 * @param string $url
	 *
	 * @return boolean
	 */
	protected function _verifySiteUrl($url)
	{
		if ($url === '')
		{
			return true;
		}

		return XenForo_DataWriter_Helper_Uri::verifyUri($url, $this, 'homepage');
	}

	/**
	 * Verifies the match URLs.
	 *
	 * @param string $urls
	 *
	 * @return boolean
	 */
	protected function _verifyMatchUrls(&$urls)
	{
		$urlOptions = preg_split('/(\r?\n)+/', $urls);
		foreach ($urlOptions AS $key => &$url)
		{
			if ($url === '')
			{
				unset($urlOptions[$key]);
				continue;
			}

			$url = preg_replace('/\*{2,}/', '*', $url);

			if ($url[0] == '*')
			{
				$url = substr($url, 1);
			}

			if (substr($url, -1) == '*')
			{
				$url = substr($url, 0, -1);
			}
		}

		$urls = implode("\n", $urlOptions);
		return true;
	}

	/**
	 * Post-save handling.
	 */
	protected function _postSave()
	{
		$this->_getBbCodeModel()->rebuildBbCodeCache();
	}

	/**
	 * Post-delete handling.
	 */
	protected function _postDelete()
	{
		$this->_getBbCodeModel()->rebuildBbCodeCache();
	}

	/**
	 * @return XenForo_Model_BbCode
	 */
	protected function _getBbCodeModel()
	{
		return $this->getModelFromCache('XenForo_Model_BbCode');
	}
}