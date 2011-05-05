<?php

/**
* Data writer for cron entries.
*
* @package XenForo_Cron
*/
class XenForo_DataWriter_CronEntry extends XenForo_DataWriter
{
	/**
	 * Constant for extra data that holds the value for the phrase
	 * that is the title of this section.
	 *
	 * This value is required on inserts.
	 *
	 * @var string
	 */
	const DATA_TITLE = 'phraseTitle';

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
	protected $_existingDataErrorPhrase = 'requested_cron_entry_not_found';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_cron_entry' => array(
				'entry_id'     => array('type' => self::TYPE_STRING,  'required' => true, 'maxLength' => 25,
						'verification' => array('$this', '_verifyEntryId'), 'requiredError' => 'please_enter_valid_cron_entry_id'
				),
				'cron_class'  => array('type' => self::TYPE_STRING,  'required' => true, 'maxLength' => 75,
						'requiredError' => 'please_enter_valid_callback_class'
				),
				'cron_method' => array('type' => self::TYPE_STRING,  'required' => true, 'maxLength' => 50,
						'requiredError' => 'please_enter_valid_callback_method'
				),
				'run_rules'   => array('type' => self::TYPE_UNKNOWN, 'required' => true,
						'verification' => array('$this', '_verifyRunRules')
				),
				'active'      => array('type' => self::TYPE_BOOLEAN, 'default' => 1),
				'next_run'    => array('type' => self::TYPE_UINT,    'default' => 0),
				'addon_id'    => array('type' => self::TYPE_STRING,  'maxLength' => 25, 'default' => '')
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
		if (!$id = $this->_getExistingPrimaryKey($data, 'entry_id'))
		{
			return false;
		}

		return array('xf_cron_entry' => $this->_getCronModel()->getCronEntryById($id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'entry_id = ' . $this->_db->quote($this->getExisting('entry_id'));
	}

	/**
	 * Gets the default options for this data writer.
	 */
	protected function _getDefaultOptions()
	{
		return array(
			self::OPTION_REBUILD_CACHE => true
		);
	}

	/**
	 * Verifies the cron entry ID.
	 *
	 * @param string $entryId
	 *
	 * @return boolean
	 */
	protected function _verifyEntryId($entryId)
	{
		if (preg_match('/[^a-zA-Z0-9_]/', $entryId))
		{
			$this->error(new XenForo_Phrase('please_enter_an_id_using_only_alphanumeric'), 'entry_id');
			return false;
		}

		if ($this->isInsert() || $entryId != $this->getExisting('entry_id'))
		{
			$existing = $this->_getCronModel()->getCronEntryById($entryId);
			if ($existing)
			{
				$this->error(new XenForo_Phrase('cron_entry_ids_must_be_unique'), 'entry_id');
				return false;
			}
		}

		return true;
	}

	/**
	 * Verifies the cron run rules.
	 *
	 * @param string|array $runRules String may be serialized value
	 *
	 * @return boolean
	 */
	protected function _verifyRunRules(&$runRules)
	{
		$runRulesNew = $runRules;

		if (!is_array($runRulesNew))
		{
			$runRulesNew = unserialize($runRulesNew);
			if (!is_array($runRulesNew))
			{
				$runRulesNew = array();
			}
		}

		$runRules = serialize($runRulesNew);

		return true;
	}

	/**
	 * Pre-save handler.
	 */
	protected function _preSave()
	{
		if ($this->isInsert() && !$this->isChanged('active'))
		{
			$this->set('active', 1);
		}

		$titlePhrase = $this->getExtraData(self::DATA_TITLE);
		if ($titlePhrase !== null && strlen($titlePhrase) == 0)
		{
			$this->error(new XenForo_Phrase('please_enter_valid_title'), 'title');
		}

		if ($this->isChanged('cron_class') || $this->isChanged('cron_method'))
		{
			$class = $this->get('cron_class');
			$method = $this->get('cron_method');

			if (!XenForo_Application::autoload($class) || !method_exists($class, $method))
			{
				$this->error(new XenForo_Phrase('please_enter_valid_callback_method'), 'cron_method');
			}
		}

		if ($this->get('active'))
		{
			$runRules = unserialize($this->get('run_rules'));
			if (!is_array($runRules))
			{
				$runRules = array();
			}

			$this->set('next_run', $this->_getCronModel()->calculateNextRunTime($runRules));
		}
		else
		{
			$this->set('next_run', 0x7FFFFFFF); // waay in future
		}
	}

	/**
	* Post-save handler.
	*/
	protected function _postSave()
	{
		if ($this->getOption(self::OPTION_REBUILD_CACHE))
		{
			$this->_getCronModel()->updateMinimumNextRunTime();
		}

		if ($this->isUpdate() && $this->isChanged('entry_id'))
		{
			$db = $this->_db;

			$this->_renameMasterPhrase(
				$this->_getTitlePhraseName($this->getExisting('entry_id')),
				$this->_getTitlePhraseName($this->get('entry_id'))
			);
		}

		$titlePhrase = $this->getExtraData(self::DATA_TITLE);
		if ($titlePhrase !== null)
		{
			$this->_insertOrUpdateMasterPhrase(
				$this->_getTitlePhraseName($this->get('entry_id')), $titlePhrase, $this->get('addon_id')
			);
		}
	}

	/**
	 * Post-delete handler.
	 */
	protected function _postDelete()
	{
		if ($this->getOption(self::OPTION_REBUILD_CACHE))
		{
			$this->_getCronModel()->updateMinimumNextRunTime();
		}

		$this->_deleteMasterPhrase($this->_getTitlePhraseName($this->get('entry_id')));
	}

	/**
	 * Gets the name of the cron entry's title phrase.
	 *
	 * @param string $id
	 *
	 * @return string
	 */
	protected function _getTitlePhraseName($id)
	{
		return $this->_getCronModel()->getCronEntryPhraseName($id);
	}

	/**
	 * @return XenForo_Model_Cron
	 */
	protected function _getCronModel()
	{
		return $this->getModelFromCache('XenForo_Model_Cron');
	}
}