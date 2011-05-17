<?php

/**
 * Model for content types.
 *
 * @package XenForo_ContentType
 */
class XenForo_Model_ContentType extends XenForo_Model
{
	/**
	 * Gets all fields for each content type.
	 *
	 * @return array Format: [content type][field name] => value
	 */
	public function getAllContentTypeFields()
	{
		$fields = array();
		$fieldResult = $this->_getDb()->query('
			SELECT *
			FROM xf_content_type_field
		');
		while ($field = $fieldResult->fetch())
		{
			$fields[$field['content_type']][$field['field_name']] = $field['field_value'];
		}

		return $fields;
	}

	/**
	 * Gets all content types and info.
	 *
	 * @return array Format: [content type] => info
	 */
	public function getAllContentTypes()
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_content_type
		', 'content_type');
	}

	/**
	 * Gets the content types in the format for the cache.
	 *
	 * @return array Format: [content type][field name] => value
	 */
	public function getContentTypesForCache()
	{
		$contentTypes = $this->getAllContentTypes();
		$fields = $this->getAllContentTypeFields();

		$cache = array();
		foreach ($contentTypes AS &$contentType)
		{
			$typeName = $contentType['content_type'];
			if (isset($fields[$typeName]))
			{
				$cache[$typeName] = $fields[$typeName];
			}
			else
			{
				$cache[$typeName] = array();
			}
		}

		return $cache;
	}

	/**
	 * Rebuilds the content type cache (both globally an internal to each content type).
	 *
	 * @return array Global cache. Format: [content type][field name] => value
	 */
	public function rebuildContentTypeCache()
	{
		$contentTypes = $this->getContentTypesForCache();

		$db = $this->_getDb();
		foreach ($contentTypes AS $contentType => $fields)
		{
			$db->update('xf_content_type',
				array('fields' => serialize($fields)),
				'content_type = ' . $db->quote($contentType)
			);
		}

		$this->_getDataRegistryModel()->set('contentTypes', $contentTypes);

		return $contentTypes;
	}
}