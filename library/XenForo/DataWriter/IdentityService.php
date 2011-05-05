<?php

/**
* Data writer for identity services.
*
* @package XenForo_User
*/
class XenForo_DataWriter_IdentityService extends XenForo_DataWriter
{
	/**
	 * Constant for extra data that holds the value for the phrase
	 * that is the title of this service (labelPhrase).
	 *
	 * This value is required on inserts.
	 *
	 * @var string
	 */
	const DATA_NAME = 'labelPhrase';

	/**
	 * Constant for extra data that holds the value for the phrase
	 * that is the way the account name is described by this service (hintPhrase).
	 *
	 * This value is required on inserts.
	 *
	 * @var string
	 */
	const DATA_HINT = 'hintPhrase';

	/**
	 * Title of the phrase that will be created when a call to set the
	 * existing data fails (when the data doesn't exist).
	 *
	 * @var string
	 */
	protected $_existingDataErrorPhrase = 'requested_identity_service_not_found';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_identity_service' => array(
				'identity_service_id' => array('type' => self::TYPE_STRING, 'maxLength' => 25, 'required' => true,
					'verification' => array('$this', '_verifyIdentityServiceId'), 'requiredError' => 'please_enter_valid_identity_service_id'),
				'model_class'  => array('type' => self::TYPE_STRING, 'maxLength' => 75, 'required' => true,
					'verification' => array('$this', '_verifyModelClass'), 'requiredError' => 'please_enter_valid_model_class'),
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
		if (!$id = $this->_getExistingPrimaryKey($data, 'identity_service_id'))
		{
			return false;
		}

		return array('xf_identity_service' => $this->_getUserModel()->getIdentityService($id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'identity_service_id = ' . $this->_db->quote($this->getExisting('identity_service_id'));
	}

	/**
	 * Verifies that the link ID is valid.
	 *
	 * @param string $newId
	 *
	 * @return boolean
	 */
	protected function _verifyIdentityServiceId($newId)
	{
		if (preg_match('/[^a-zA-Z0-9_]/', $newId))
		{
			$this->error(new XenForo_Phrase('please_enter_an_id_using_only_alphanumeric'), 'identity_service_id');
			return false;
		}

		if ($this->isInsert() || $newId != $this->getExisting('identity_service_id'))
		{
			$newIdConflict = $this->_getUserModel()->getIdentityService($newId);
			if ($newIdConflict)
			{
				$this->error(new XenForo_Phrase('identity_service_ids_must_be_unique'), 'identity_service_id');
				return false;
			}
		}

		return true;
	}

	/**
	 * Verifies that the model class is valid.
	 *
	 * @param string $modelClass
	 *
	 * @return boolean
	 */
	protected function _verifyModelClass($modelClass)
	{
		if (!XenForo_Application::autoload($modelClass))
		{
			$this->error(new XenForo_Phrase('please_specify_valid_class_that_extends_xenforo_model'), 'model_class');
			return false;
		}

		return true;
	}

	/**
	 * Pre-save handling.
	 */
	protected function _preSave()
	{
		$name = $this->getExtraData(self::DATA_NAME);
		if ($name !== null && strlen($name) == 0)
		{
			$this->error(new XenForo_Phrase('please_enter_valid_name'), 'name');
		}

		$hint = $this->getExtraData(self::DATA_HINT);
		if ($hint !== null && strlen($hint) == 0)
		{
			$this->error(new XenForo_Phrase('please_enter_valid_hint'), 'hint');
		}
	}

	/**
	 * Post-save handling.
	 */
	protected function _postSave()
	{
		if ($this->isUpdate() && $this->isChanged('identity_service_id'))
		{
			$this->_renameMasterPhrase(
				$this->_getNamePhraseName($this->getExisting('identity_service_id')),
				$this->_getNamePhraseName($this->get('identity_service_id'))
			);

			$this->_renameMasterPhrase(
				$this->_getHintPhraseName($this->getExisting('identity_service_id')),
				$this->_getHintPhraseName($this->get('identity_service_id'))
			);
		}

		$labelPhrase = $this->getExtraData(self::DATA_NAME);
		if ($labelPhrase !== null)
		{
			$this->_insertOrUpdateMasterPhrase(
				$this->_getNamePhraseName($this->get('identity_service_id')), $labelPhrase, ''
			);
		}

		$hintPhrase = $this->getExtraData(self::DATA_HINT);
		if ($hintPhrase !== null)
		{
			$this->_insertOrUpdateMasterPhrase(
				$this->_getHintPhraseName($this->get('identity_service_id')), $hintPhrase, ''
			);
		}

		//TODO: If identity_service_id changes, update all xf_user_identity records, and denormalized xf_user_profile.identities fields?
	}

	/**
	 * Post-delete handling.
	 */
	protected function _postDelete()
	{
		$this->_deleteMasterPhrase($this->_getNamePhraseName($this->get('identity_service_id')));
		$this->_deleteMasterPhrase($this->_getHintPhraseName($this->get('identity_service_id')));
	}

	/**
	 * Gets the name of the label phrase for this service.
	 *
	 * @param string $identityServiceId
	 *
	 * @return string
	 */
	protected function _getNamePhraseName($identityServiceId)
	{
		return $this->_getUserModel()->getIdentityServiceNamePhraseName($identityServiceId);
	}

	/**
	 * Gets the name of the hint phrase for this service.
	 *
	 * @param string $identityServiceId
	 *
	 * @return string
	 */
	protected function _getHintPhraseName($identityServiceId)
	{
		return $this->_getUserModel()->getIdentityServiceHintPhraseName($identityServiceId);
	}
}