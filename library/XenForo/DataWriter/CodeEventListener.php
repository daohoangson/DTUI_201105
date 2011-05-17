<?php

/**
* Data writer for code event listeners.
*
* @package XenForo_CodeEvents
*/
class XenForo_DataWriter_CodeEventListener extends XenForo_DataWriter
{
	/**
	 * Option that represents whether the option cache will be automatically
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
	protected $_existingDataErrorPhrase = 'requested_code_event_listener_not_found';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_code_event_listener' => array(
				'event_listener_id' => array('type' => self::TYPE_UINT,   'autoIncrement' => true),
				'event_id'          => array('type' => self::TYPE_STRING, 'maxLength' => 50, 'required' => true,
						'requiredError' => 'please_select_valid_code_event'
				),
				'execute_order'     => array('type' => self::TYPE_UINT,   'default' => 10),
				'description'       => array('type' => self::TYPE_STRING, 'default' => ''),
				'callback_class'    => array('type' => self::TYPE_STRING, 'maxLength' => 75, 'required' => true,
						'requiredError' => 'please_enter_valid_callback_class'
				),
				'callback_method'   => array('type' => self::TYPE_STRING, 'maxLength' => 50, 'required' => true,
						'requiredError' => 'please_enter_valid_callback_method'
				),
				'active'            => array('type' => self::TYPE_UINT,   'allowedValues' => array(0, 1), 'default' => 1),
				'addon_id'          => array('type' => self::TYPE_STRING, 'maxLength' => 25, 'default' => '')
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
		if (!$id = $this->_getExistingPrimaryKey($data, 'event_listener_id'))
		{
			return false;
		}

		return array('xf_code_event_listener' => $this->_getCodeEventModel()->getEventListenerById($id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'event_listener_id = ' . $this->_db->quote($this->getExisting('event_listener_id'));
	}

	/**
	 * Gets the default options for this data writer.
	 */
	protected function _getDefaultOptions()
	{
		return array(
			self::OPTION_REBUILD_CACHE => true,
		);
	}

	/**
	 * Pre-save handling.
	 */
	protected function _preSave()
	{
		if ($this->isChanged('callback_class') || $this->isChanged('callback_method'))
		{
			$class = $this->get('callback_class');
			$method = $this->get('callback_method');

			if (!XenForo_Application::autoload($class) || !method_exists($class, $method))
			{
				$this->error(new XenForo_Phrase('please_enter_valid_callback_method'), 'callback_method');
			}
		}
	}

	/**
	 * Post-save handling.
	 */
	protected function _postSave()
	{
		$this->_rebuildCache();
	}

	/**
	 * Post-delete handling.
	 */
	protected function _postDelete()
	{
		if ($this->getOption(self::OPTION_REBUILD_CACHE))
		{
			$this->_rebuildCache();
		}
	}

	/**
	 * Rebuilds the event listener cache if necessary.
	 */
	protected function _rebuildCache()
	{
		$this->_getCodeEventModel()->rebuildEventListenerCache();
	}

	/**
	 * Gets the code event model object.
	 *
	 * @return XenForo_Model_CodeEvent
	 */
	protected function _getCodeEventModel()
	{
		return $this->getModelFromCache('XenForo_Model_CodeEvent');
	}
}