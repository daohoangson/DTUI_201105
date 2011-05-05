<?php
/**
* Data writer for link forums.
*
* @package XenForo_LinkForum
*/
class XenForo_DataWriter_LinkForum extends XenForo_DataWriter_Node
{
	/**
	 * Title of the phrase that will be created when a call to set the
	 * existing data fails (when the data doesn't exist).
	 *
	 * @var string
	 */
	protected $_existingDataErrorPhrase = 'requested_link_forum_not_found';

	/**
	 * Returns all xf_node fields, plus link-specific fields
	 */
	protected function _getFields()
	{
		return parent::_getFields() + array('xf_link_forum' => array(
			'node_id'        => array('type' => self::TYPE_UINT, 'default' => array('xf_node', 'node_id'), 'required' => true),
			'link_url'       => array('type' => self::TYPE_STRING, 'maxLength' => 150, 'required' => true,
					'requiredError' => 'please_enter_valid_url'
			),
			'redirect_count' => array('type' => self::TYPE_UINT_FORCED, 'default' => 0),
		));
	}
	// note: link_url is not validated as a URL, as there's value in allowing relative URLs, etc

	/**
	* Gets the actual existing data out of data that was passed in. See parent for explanation.
	*
	* @param mixed
	*
	* @return array|false
	*/
	protected function _getExistingData($data)
	{
		if (!$nodeId = $this->_getExistingPrimaryKey($data))
		{
			return false;
		}

		$linkForum = $this->getModelFromCache('XenForo_Model_LinkForum')->getLinkForumById($nodeId);
		if (!$linkForum)
		{
			return false;
		}

		return $this->getTablesDataFromArray($linkForum);
	}
}