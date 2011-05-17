<?php

/**
* Data writer for options.
*
* @package XenForo_Options
*/
class XenForo_DataWriter_Option extends XenForo_DataWriter
{
	/**
	 * Constant for extra data that holds the value for the phrase
	 * that is the title of this link.
	 *
	 * This value is required on inserts.
	 *
	 * @var string
	 */
	const DATA_TITLE = 'phraseTitle';

	/**
	 * Constant for extra data that holds the value for the phrase
	 * that is the explantion of this option.
	 *
	 * @var string
	 */
	const DATA_EXPLAIN = 'phraseExplain';

	/**
	 * Option that represents whether the option cache will be automatically
	 * rebuilt. Defaults to true.
	 *
	 * @var string
	 */
	const OPTION_REBUILD_CACHE = 'rebuildCache';

	/**
	 * Option that represents whether to validate an option value or simply trust
	 * it as being valid. Trust (false) is generally only used for imports. Defaults to
	 * true.
	 *
	 * @var string
	 */
	const OPTION_VALIDATE_VALUE = 'validateValue';

	/**
	 * Title of the phrase that will be created when a call to set the
	 * existing data fails (when the data doesn't exist).
	 *
	 * @var string
	 */
	protected $_existingDataErrorPhrase = 'requested_option_not_found';

	/**
	 * If this is set, it represents a set of group relations to *replace*.
	 * When it is null, no relations will be updated.
	 *
	 * @var null|array
	 */
	protected $_relations = null;

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_option' => array(
				'option_id'         => array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 50,
						'verification' => array('$this', '_verifyOptionId'), 'requiredError' => 'please_enter_valid_option_id'
				),
				'option_value'      => array('type' => self::TYPE_UNKNOWN, 'default' => ''),
				'default_value'     => array('type' => self::TYPE_BINARY, 'default' => ''),
				'edit_format'       => array('type' => self::TYPE_STRING, 'required' => true,
						'allowedValues' => array('textbox', 'spinbox', 'onoff', 'radio', 'select', 'checkbox', 'template', 'callback')
				),
				'edit_format_params' => array('type' => self::TYPE_STRING, 'default' => ''),
				'data_type'         => array('type' => self::TYPE_STRING, 'required' => true,
						'allowedValues' => array('string', 'integer', 'numeric', 'array', 'boolean', 'positive_integer', 'unsigned_integer', 'unsigned_numeric')
				),
				'sub_options'       => array('type' => self::TYPE_STRING, 'default' => ''),
				'can_backup'        => array('type' => self::TYPE_UINT,   'default' => 1, 'min' => 0, 'max' => 1),
				'validation_class'  => array('type' => self::TYPE_STRING, 'default' => '', 'maxLength' => 75),
				'validation_method' => array('type' => self::TYPE_STRING, 'default' => '', 'maxLength' => 50),
				'addon_id'          => array('type' => self::TYPE_STRING, 'default' => '', 'maxLength' => 25)
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
		if (!$id = $this->_getExistingPrimaryKey($data, 'option_id'))
		{
			return false;
		}

		return array('xf_option' => $this->_getOptionModel()->getOptionById($id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'option_id = ' . $this->_db->quote($this->getExisting('option_id'));
	}

	/**
	 * Gets the default options for this data writer.
	 */
	protected function _getDefaultOptions()
	{
		return array(
			self::OPTION_REBUILD_CACHE => true,
			self::OPTION_VALIDATE_VALUE => true
		);
	}

	/**
	 * Verifies that the option ID contains valid characters and does not already exist.
	 *
	 * @param $optionId
	 *
	 * @return boolean
	 */
	protected function _verifyOptionId($optionId)
	{
		if (preg_match('/[^a-zA-Z0-9_]/', $optionId))
		{
			$this->error(new XenForo_Phrase('please_enter_an_id_using_only_alphanumeric'), 'option_id');
			return false;
		}

		if ($optionId !== $this->getExisting('option_id'))
		{
			if ($this->_getOptionModel()->getOptionById($optionId))
			{
				$this->error(new XenForo_Phrase('option_ids_must_be_unique'), 'option_id');
				return false;
			}
		}

		return true;
	}

	/**
	 * Sets the group relationships for this option.
	 *
	 * @param array $relations List of group relations, format: [group id] => display order.
	 */
	public function setRelations(array $relations)
	{
		$this->_relations = $relations;
	}

	/**
	 * Pre-save handling.
	 */
	protected function _preSave()
	{
		// if is insert and changing default_value and didn't set option_value -> set value = default
		if ($this->isInsert() && $this->isChanged('default_value') && !$this->isChanged('option_value'))
		{
			$this->set('option_value', $this->get('default_value'));
		}

		if (is_array($this->_relations) && count($this->_relations) == 0)
		{
			if ($this->isInsert())
			{
				$this->error(new XenForo_Phrase('this_option_must_belong_to_at_least_one_group'), 'relations');
			}
			else
			{
				$this->error(new XenForo_Phrase('it_is_not_possible_to_remove_this_option_from_all_groups'), 'relations');
			}
		}

		if ($this->isChanged('validation_class') || $this->isChanged('validation_method'))
		{
			$this->_validateValidationClassAndMethod($this->get('validation_class'), $this->get('validation_method'));
		}

		if ($this->isChanged('edit_format') || $this->isChanged('data_type'))
		{
			$this->_validateDataTypeForEditFormat($this->get('data_type'), $this->get('edit_format'));
		}

		if ($this->get('data_type') == 'array' && $this->get('sub_options') === '')
		{
			$this->error(new XenForo_Phrase('please_enter_list_of_sub_options_for_this_array'), 'sub_options');
		}

		if ($this->isChanged('data_type') && $this->get('data_type') !== 'array')
		{
			$this->set('sub_options', '');
		}

		if ($this->isChanged('option_value') && $this->getOption(self::OPTION_VALIDATE_VALUE))
		{
			$optionValue = $this->_validateOptionValuePreSave($this->get('option_value'));
			if ($optionValue === false)
			{
				$this->error(new XenForo_Phrase('please_enter_valid_value_for_this_option'), $this->get('option_id'), false);
			}
			else
			{
				$this->_setInternal('xf_option', 'option_value', $optionValue);
			}
		}

		$titlePhrase = $this->getExtraData(self::DATA_TITLE);
		if ($titlePhrase !== null && strlen($titlePhrase) == 0)
		{
			$this->error(new XenForo_Phrase('please_enter_valid_title'), 'title');
		}
	}

	/**
	 * Validates the the validation class and method are valid.
	 *
	 * @param string $class Class name
	 * @param string $method Method name
	 *
	 * @return boolean
	 */
	protected function _validateValidationClassAndMethod($class, $method)
	{
		if ($class && !XenForo_Application::autoload($class))
		{
			$this->error(new XenForo_Phrase('callback_class_x_for_option_y_is_not_valid', array('option' => $this->get('option_id'), 'class' => $class)), 'validation');
			return false;
		}

		return true;
	}

	/**
	 * Validates the data type based on the selected edit format. Only limited
	 * formats may use the "array" data types.
	 *
	 * @param string $dataType Data type
	 * @param string $editFormat Edit format
	 *
	 * @return boolean
	 */
	protected function _validateDataTypeForEditFormat($dataType, $editFormat)
	{
		$optionModel = $this->_getOptionModel();

		switch ($editFormat)
		{
			case 'callback':
			case 'template':
				// these can be anything
				break;

			case 'checkbox':
				if ($dataType != 'array')
				{
					$this->error(new XenForo_Phrase('please_select_data_type_array_if_you_want_to_allow_multiple_selections'), 'data_type');
					return false;
				}
				break;

			case 'textbox':
			case 'spinbox':
			case 'onoff':
			case 'radio':
			case 'select':
				if ($dataType == 'array')
				{
					$this->error(new XenForo_Phrase('please_select_data_type_other_than_array_if_you_want_to_allow_single'), 'data_type');
					return false;
				}
				break;
		}

		return true;
	}

	/**
	 * Validates an option value for pre-save.
	 *
	 * @param mixed $optionValue Unvalidated option
	 *
	 * @return string Validated option. Options are serialized; all other types a strval'd
	 */
	protected function _validateOptionValuePreSave($optionValue)
	{
		switch ($this->get('data_type'))
		{
			case 'string':  $optionValue = strval($optionValue); break;
			case 'integer': $optionValue = intval($optionValue); break;
			case 'numeric': $optionValue = strval($optionValue) + 0; break;
			case 'boolean': $optionValue = ($optionValue ? 1 : 0); break;

			case 'array':
				if (!is_array($optionValue))
				{
					$unserialized = @unserialize($optionValue);
					if (is_array($unserialized))
					{
						$optionValue = $unserialized;
					}
					else
					{
						$optionValue = array();
					}
				}
				break;

			case 'unsigned_integer':
				$optionValue = max(0, intval($optionValue));
				break;

			case 'unsigned_numeric':
				$optionValue = max(0, (strval($optionValue) + 0));
				break;

			case 'positive_integer':
				$optionValue = max(1, intval($optionValue));
				break;
		}

		$validationClass = $this->get('validation_class');
		$validationMethod = $this->get('validation_method');

		if ($validationClass && $validationMethod && $this->_validateValidationClassAndMethod($validationClass, $validationMethod))
		{
			$success = (boolean)call_user_func_array(
				array($validationClass, $validationMethod),
				array(&$optionValue, $this, $this->get('option_id'))
			);
			if (!$success)
			{
				return false;
			}
		}

		if (is_array($optionValue))
		{
			if ($this->get('data_type') != 'array')
			{
				$this->error(new XenForo_Phrase('only_array_data_types_may_be_represented_as_array_values'), 'data_type');
			}
			else
			{
				$subOptions = preg_split('/(\r\n|\n|\r)+/', trim($this->get('sub_options')), -1, PREG_SPLIT_NO_EMPTY);
				$newOptionValue = array();
				$allowAny = false;

				foreach ($subOptions AS $subOption)
				{
					if ($subOption == '*')
					{
						$allowAny = true;
					}
					else if (!isset($optionValue[$subOption]))
					{
						$newOptionValue[$subOption] = false;
					}
					else
					{
						$newOptionValue[$subOption] = $optionValue[$subOption];
						unset($optionValue[$subOption]);
					}
				}

				if ($allowAny)
				{
					// allow any keys, so bring all the remaining ones over
					$newOptionValue += $optionValue;
				}
				else if (count($optionValue) > 0)
				{
					$this->error(new XenForo_Phrase('following_sub_options_unknown_x', array('subOptions' => implode(', ', array_keys($optionValue)))), 'sub_options');
				}

				$optionValue = $newOptionValue;
			}

			$optionValue = serialize($optionValue);
		}

		return strval($optionValue);
	}

	/**
	 * Post-save handling.
	 */
	protected function _postSave()
	{
		$db = $this->_db;

		if (is_array($this->_relations))
		{
			$this->_updateRelatedGroupList($this->_relations);
		}

		if ($this->isUpdate() && $this->isChanged('option_id'))
		{
			$db->update('xf_option_group_relation',
				array('option_id' => $this->get('option_id')),
				'option_id = ' . $db->quote($this->getExisting('option_id'))
			);

			$this->_renameMasterPhrase(
				$this->_getTitlePhraseName($this->getExisting('option_id')),
				$this->_getTitlePhraseName($this->get('option_id'))
			);

			$this->_renameMasterPhrase(
				$this->_getExplainPhraseName($this->getExisting('option_id')),
				$this->_getExplainPhraseName($this->get('option_id'))
			);
		}

		$titlePhrase = $this->getExtraData(self::DATA_TITLE);
		if ($titlePhrase !== null)
		{
			$this->_insertOrUpdateMasterPhrase(
				$this->_getTitlePhraseName($this->get('option_id')), $titlePhrase, $this->get('addon_id')
			);
		}

		$explainPhrase = $this->getExtraData(self::DATA_EXPLAIN);
		if ($explainPhrase !== null)
		{
			$this->_insertOrUpdateMasterPhrase(
				$this->_getExplainPhraseName($this->get('option_id')), $explainPhrase, $this->get('addon_id')
			);
		}

		$this->_rebuildOptionCache();
	}

	/**
	 * Updates (replaces) the related group list (the list of groups this
	 * option belongs to).
	 *
	 * @param array $relations Format: [group id] => display order
	 */
	protected function _updateRelatedGroupList(array $relations)
	{
		$db = $this->_db;
		if ($this->isUpdate())
		{
			$db->delete('xf_option_group_relation', 'option_id = ' . $db->quote($this->getExisting('option_id')));
		}

		foreach ($relations AS $groupId => $displayOrder)
		{
			$displayOrder = intval($displayOrder);
			if ($displayOrder < 0)
			{
				$displayOrder = 0;
			}

			$db->insert('xf_option_group_relation', array(
				'option_id' => $this->get('option_id'),
				'group_id' => $groupId,
				'display_order' => intval($displayOrder)
			));
		}
	}

	/**
	 * Post-delete handling.
	 */
	protected function _postDelete()
	{
		$db = $this->_db;
		$db->delete('xf_option_group_relation', 'option_id = ' . $db->quote($this->get('option_id')));

		$this->_deleteMasterPhrase($this->_getTitlePhraseName($this->get('option_id')));
		$this->_deleteMasterPhrase($this->_getExplainPhraseName($this->get('option_id')));

		$this->_rebuildOptionCache();
	}

	/**
	 * Rebuilds the option cache if rebuild option is enabled.
	 */
	protected function _rebuildOptionCache()
	{
		if ($this->getOption(self::OPTION_REBUILD_CACHE))
		{
			$this->_getOptionModel()->rebuildOptionCache();
		}
	}

	/**
	 * Gets the name of the title phrase for this option.
	 *
	 * @param string $optionId
	 *
	 * @return string
	 */
	protected function _getTitlePhraseName($optionId)
	{
		return $this->_getOptionModel()->getOptionTitlePhraseName($optionId);
	}

	/**
	 * Gets the name of the explain phrase for this option.
	 *
	 * @param string $optionId
	 *
	 * @return string
	 */
	protected function _getExplainPhraseName($optionId)
	{
		return $this->_getOptionModel()->getOptionExplainPhraseName($optionId);
	}

	/**
	 * Load option model from cache.
	 *
	 * @return XenForo_Model_Option
	 */
	protected function _getOptionModel()
	{
		return $this->getModelFromCache('XenForo_Model_Option');
	}
}