<?php

/**
* Data writer for nodes.
*
* @package XenForo_Node
*/
class XenForo_DataWriter_Node extends XenForo_DataWriter
{
	/**
	 * Option to prevent the nested set info (lft, rgt, depth) from being set by user input
	 * (by default this info should be set only through a rebuild based on parent_node_id and display_order values).
	 * If this option is enabled, no further safeguards of the nested set info are enabled.
	 *
	 * @var string
	 */
	const OPTION_ALLOW_NESTED_SET_WRITE = 'allowNestedSetWrite';

	/**
	 * Option to rebuild nested set info etc. if necessary after insert/update/delete
	 *
	 * @var string
	 */
	const OPTION_POST_WRITE_UPDATE_CHILD_NODES = 'updateChildNodesAfterDbWrite';

	/**
	 * Optional destination parent node for children of a node to be deleted
	 *
	 * @var integer
	 */
	const OPTION_CHILD_NODE_DESTINATION_PARENT_ID = 'destinationForChildrenOfDeletedNode';

	/**
	 * Option that represents whether the minimum run cache will be automatically
	 * rebuilt. Defaults to true.
	 *
	 * @var string
	 */
	const OPTION_REBUILD_CACHE = 'rebuildCache';

	/**
	 * Title of the phrase that will be created when a call to set the
	 * existing data fails (when the data doesn't exist).
	 *
	 * @var string
	 */
	protected $_existingDataErrorPhrase = 'requested_node_not_found';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_node' => array(
				'node_id'            => array('type' => self::TYPE_UINT, 'autoIncrement' => true),
				'title'              => array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 50,
						'requiredError' => 'please_enter_valid_title'
				),
				'node_name'          => array('type' => self::TYPE_STRING, 'default' => null, 'verification' => array('$this', '_verifyNodeName'), 'maxLength' => 50),
				'description'        => array('type' => self::TYPE_STRING, 'default' => ''),
				'node_type_id'       => array('type' => self::TYPE_BINARY, 'required' => true, 'maxLength' => 25),
				'parent_node_id'     => array('type' => self::TYPE_UINT, 'default' => 0, 'required' => true),
				'display_order'      => array('type' => self::TYPE_UINT, 'default' => 1),
				'lft'                => array('type' => self::TYPE_UINT, 'verification' => array('$this', '_verifyNestedSetInfo')),
				'rgt'                => array('type' => self::TYPE_UINT, 'verification' => array('$this', '_verifyNestedSetInfo')),
				'depth'              => array('type' => self::TYPE_UINT, 'verification' => array('$this', '_verifyNestedSetInfo')),
				'style_id'           => array('type' => self::TYPE_UINT, 'default' => 0),
				'effective_style_id' => array('type' => self::TYPE_UINT, 'default' => 0),
				'display_in_list'    => array('type' => self::TYPE_BOOLEAN, 'default' => 1),
			)
		);
	}

	/**
	 * @return XenForo_Model_Node
	 */
	protected function _getNodeModel()
	{
		return $this->getModelFromCache('XenForo_Model_Node');
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
		if (!$nodeId = $this->_getExistingPrimaryKey($data))
		{
			return false;
		}

		return array('xf_node' => $this->_getNodeModel()->getNodeById($nodeId));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'node_id = ' . $this->_db->quote($this->getExisting('node_id'));
	}

	/**
	* Gets the default set of options for this data writer.
	*
	* @return array
	*/
	protected function _getDefaultOptions()
	{
		return array(
			self::OPTION_ALLOW_NESTED_SET_WRITE => false,
			self::OPTION_POST_WRITE_UPDATE_CHILD_NODES => true,
			self::OPTION_CHILD_NODE_DESTINATION_PARENT_ID => false,
			self::OPTION_REBUILD_CACHE => true
		);
	}

	/**
	 * Prevents lft, rgt and depth fields from being set manually,
	 * if OPTION_ALLOW_NESTED_SET_WRITE is false
	 *
	 * @param integer $data
	 *
	 * @return boolean
	 */
	protected function _verifyNestedSetInfo($data)
	{
		if (!$this->getOption(self::OPTION_ALLOW_NESTED_SET_WRITE))
		{
			throw new XenForo_Exception('Nested set data can not be set unless OPTION_ALLOW_NESTED_SET_WRITE is enabled.');
			return false;
		}

		return true;
	}

	/**
	 * Verifies that a node name is valid - a-z0-9_-+ valid characters
	 *
	 * @param string $data
	 *
	 * @return boolean
	 */
	protected function _verifyNodeName(&$data)
	{
		if (!$data)
		{
			$data = null;
			return true;
		}

		if (!preg_match('/^[a-z0-9_\-]+$/i', $data))
		{
			$this->error(new XenForo_Phrase('please_enter_node_name_using_alphanumeric'), 'node_name');
			return false;
		}

		if ($data === strval(intval($data)) || $data == '-')
		{
			$this->error(new XenForo_Phrase('node_names_contain_more_numbers_hyphen'), 'node_name');
			return false;
		}

		return true;
	}

	protected function _preSave()
	{
		if ($this->get('node_name') && ($this->isChanged('node_name') || $this->isChanged('node_type_id')))
		{
			$conflict = $this->_getNodeModel()->getNodeByName($this->get('node_name'), $this->get('node_type_id'));
			if ($conflict && $conflict['node_id'] != $this->get('node_id'))
			{
				$this->error(new XenForo_Phrase('node_names_must_be_unique'), 'node_name');
			}
		}
	}

	/**
	* Post-save handler.
	* If parent_node_id or display_order has changed, trigger a rebuild of the nested set info for all nodes
	*/
	protected function _postSave()
	{
		if ($this->getOption(self::OPTION_POST_WRITE_UPDATE_CHILD_NODES))
		{
			if ($this->isChanged('parent_node_id')
				|| $this->isChanged('display_order')
				|| $this->isChanged('style_id')
			)
			{
				$this->_getNodeModel()->updateNestedSetInfo();
			}
		}

		if ($this->isChanged('parent_node_id'))
		{
			if ($this->getOption(self::OPTION_REBUILD_CACHE))
			{
				$this->getModelFromCache('XenForo_Model_Permission')->rebuildPermissionCache();
			}
		}
	}

	/**
	 * Post-delete handler
	 * If there is are child nodes of the deleted node, delete or move them accordingly
	 *
	 * @see library/XenForo/XenForo_DataWriter#_postDelete()
	 */
	protected function _postDelete()
	{
		$this->deleteNodeModerators();
		$this->deleteNodePermissions();

		if ($this->getOption(self::OPTION_POST_WRITE_UPDATE_CHILD_NODES))
		{
			$nodeModel = $this->_getNodeModel();

			if ($nodeModel->hasChildNodes(array('lft' => $this->getExisting('lft'), 'rgt' => $this->getExisting('rgt'))))
			{
				$nodeTypes = $nodeModel->getAllNodeTypes();

				$moveToNodeId = $this->getOption(self::OPTION_CHILD_NODE_DESTINATION_PARENT_ID);

				if ($moveToNodeId !== false)
				{
					$nodeModel->moveChildNodes($this->_existingData['xf_node'], $moveToNodeId, false);
				}
				else
				{
					$nodeModel->deleteChildNodes($this->_existingData['xf_node'], false);
				}
			}

			// we deleted and possibly moved stuff, so we need to do a rebuild of the nested set info
			$this->_getNodeModel()->updateNestedSetInfo();
		}

		if ($this->getOption(self::OPTION_REBUILD_CACHE))
		{
			$this->getModelFromCache('XenForo_Model_Permission')->rebuildPermissionCache();
		}
	}

	public function deleteNodePermissions()
	{
		$db = $this->_db;
		$nodeId = $this->get('node_id');
		$nodeIdQuoted = $db->quote($nodeId);

		$db->delete('xf_permission_cache_content', "content_type = 'node' AND content_id = $nodeIdQuoted");
		$db->delete('xf_permission_entry_content', "content_type = 'node' AND content_id = $nodeIdQuoted");
	}

	public function deleteNodeModerators()
	{
		$moderators = $this->getModelFromCache('XenForo_Model_Moderator')->getContentModerators(array(
			'content' => array('node', $this->get('node_id'))
		));
		foreach ($moderators AS $moderator)
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_ModeratorContent', XenForo_DataWriter::ERROR_SILENT);
			$dw->setExistingData($moderator, true);
			$dw->delete();
		}
	}
}