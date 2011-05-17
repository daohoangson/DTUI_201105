<?php

/**
* Data writer for admin navigation.
*
* @package XenForo_AdminNavigation
*/
class XenForo_DataWriter_AdminNavigation extends XenForo_DataWriter
{
	/**
	 * Constant for extra data that holds the value for the phrase
	 * that is the title of this navigation entry.
	 *
	 * This value is required on inserts.
	 *
	 * @var string
	 */
	const DATA_TITLE = 'phraseTitle';

	/**
	 * Title of the phrase that will be created when a call to set the
	 * existing data fails (when the data doesn't exist).
	 *
	 * @var string
	 */
	protected $_existingDataErrorPhrase = 'requested_admin_navigation_entry_not_found';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_admin_navigation' => array(
				'navigation_id'         => array('type' => self::TYPE_STRING, 'maxLength' => 25, 'required' => true,
						'verification' => array('$this', '_verifyNavigationId'),
						'requiredError' => 'please_enter_valid_admin_navigation_id'
				),
				'parent_navigation_id'  => array('type' => self::TYPE_STRING, 'maxLength' => 25, 'default' => ''),
				'display_order'         => array('type' => self::TYPE_UINT,   'default' => 1),
				'link'                  => array('type' => self::TYPE_STRING, 'maxLength' => 50, 'default' => ''),
				'admin_permission_id'   => array('type' => self::TYPE_STRING, 'maxLength' => 25, 'default' => ''),
				'debug_only'            => array('type' => self::TYPE_BOOLEAN, 'default' => 0),
				'hide_no_children'      => array('type' => self::TYPE_BOOLEAN, 'default' => 0),
				'addon_id'              => array('type' => self::TYPE_STRING, 'maxLength' => 25, 'default' => '')
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
		if (!$id = $this->_getExistingPrimaryKey($data, 'navigation_id'))
		{
			return false;
		}

		return array('xf_admin_navigation' => $this->_getAdminNavigationModel()->getAdminNavigationEntryById($id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'navigation_id = ' . $this->_db->quote($this->getExisting('navigation_id'));
	}

	/**
	 * Verifies that the navigation ID is valid.
	 *
	 * @param string $newId
	 *
	 * @return boolean
	 */
	protected function _verifyNavigationId($newId)
	{
		if (preg_match('/[^a-zA-Z0-9_]/', $newId))
		{
			$this->error(new XenForo_Phrase('please_enter_an_id_using_only_alphanumeric'), 'navigation_id');
			return false;
		}

		if ($this->isInsert() || $newId != $this->getExisting('navigation_id'))
		{
			$newIdConflict = $this->_getAdminNavigationModel()->getAdminNavigationEntryById($newId);
			if ($newIdConflict)
			{
				$this->error(new XenForo_Phrase('admin_navigation_ids_must_be_unique'), 'navigation_id');
				return false;
			}
		}

		return true;
	}

	/**
	 * Pre-save handling.
	 */
	protected function _preSave()
	{
		$titlePhrase = $this->getExtraData(self::DATA_TITLE);
		if ($titlePhrase !== null && strlen($titlePhrase) == 0)
		{
			$this->error(new XenForo_Phrase('please_enter_valid_title'), 'title');
		}

		if ($this->isUpdate() && $this->isChanged('parent_navigation_id'))
		{
			if (!$this->_getAdminNavigationModel()->isAdminNavigationEntryValidParent(
				$this->get('parent_navigation_id'), $this->getExisting('navigation_id')
			))
			{
				$this->error(new XenForo_Phrase('please_select_valid_parent_navigation_entry'), 'parent_navigation_id');
			}
		}
	}

	/**
	 * Post-save handling.
	 */
	protected function _postSave()
	{
		if ($this->isUpdate() && $this->isChanged('navigation_id'))
		{
			$this->_renameMasterPhrase(
				$this->_getTitlePhraseName($this->getExisting('navigation_id')),
				$this->_getTitlePhraseName($this->get('navigation_id'))
			);

			$this->_db->update('xf_admin_navigation',
				array('parent_navigation_id' => $this->get('navigation_id')),
				'parent_navigation_id = ' . $this->_db->quote($this->getExisting('navigation_id'))
			);
		}

		$titlePhrase = $this->getExtraData(self::DATA_TITLE);
		if ($titlePhrase !== null)
		{
			$this->_insertOrUpdateMasterPhrase(
				$this->_getTitlePhraseName($this->get('navigation_id')), $titlePhrase, $this->get('addon_id')
			);
		}
	}

	/**
	 * Post-delete handling.
	 */
	protected function _postDelete()
	{
		$navigationId = $this->get('navigation_id');

		$this->_deleteMasterPhrase($this->_getTitlePhraseName($navigationId));

		$children = $this->_getAdminNavigationModel()->getAdminNavigationEntriesWithParent($navigationId);
		foreach ($children AS $child)
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_AdminNavigation');
			$dw->setExistingData($child, true);
			$dw->delete();
		}
	}

	/**
	 * Gets the name of the title phrase for this navigation entry.
	 *
	 * @param string $navigationId
	 *
	 * @return string
	 */
	protected function _getTitlePhraseName($navigationId)
	{
		return $this->_getAdminNavigationModel()->getAdminNavigationPhraseName($navigationId);
	}

	/**
	 * @return XenForo_Model_AdminNavigation
	 */
	protected function _getAdminNavigationModel()
	{
		return $this->getModelFromCache('XenForo_Model_AdminNavigation');
	}
}