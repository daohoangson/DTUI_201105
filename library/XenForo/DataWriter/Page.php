<?php
/**
* Data writer for Pages.
*
* @package XenForo_Page
*/
class XenForo_DataWriter_Page extends XenForo_DataWriter_Node
{
	/**
	 * Title of the phrase that will be created when a call to set the
	 * existing data fails (when the data doesn't exist).
	 *
	 * @var string
	 */
	protected $_existingDataErrorPhrase = 'requested_page_not_found';

	/**
	 * Returns all xf_node fields, plus page-specific fields
	 *
	 * @see library/XenForo/DataWriter/XenForo_DataWriter_Node#_getFields()
	 */
	protected function _getFields()
	{
		$fields = parent::_getFields() + array('xf_page' => array(
			'node_id' =>
				array('type' => self::TYPE_UINT, 'default' => array('xf_node', 'node_id'), 'required' => true),
			'publish_date' =>
				array('type' => self::TYPE_UINT, 'default' => XenForo_Application::$time),
			'modified_date' =>
				array('type' => self::TYPE_UINT, 'default' => XenForo_Application::$time),
			'view_count' =>
				array('type' => self::TYPE_UINT, 'default' => 0),
			'log_visits' =>
				array('type' => self::TYPE_BOOLEAN, 'default' => 0),
			'list_siblings' =>
				array('type' => self::TYPE_BOOLEAN, 'default' => 0),
			'list_children' =>
				array('type' => self::TYPE_BOOLEAN, 'default' => 0),
			'callback_class' =>
				array('type' => self::TYPE_STRING, 'default' => ''),
			'callback_method' =>
				array('type' => self::TYPE_STRING, 'default' =>'')
		));

		$fields['xf_node']['node_name']['required'] = true;
		$fields['xf_node']['node_name']['requiredError'] = 'please_enter_valid_url_portion';

		return $fields;
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

		$page = $this->getModelFromCache('XenForo_Model_Page')->getPageById($nodeId);
		if (!$page)
		{
			return false;
		}

		return $this->getTablesDataFromArray($page);
	}

	/**
	 * @see XenForo_DataWriter_Node::_preSave()
	 */
	protected function _preSave()
	{
		parent::_preSave();

		if ($this->get('callback_class') || $this->get('callback_method'))
		{
			$class = $this->get('callback_class');
			$method = $this->get('callback_method');

			if (!XenForo_Application::autoload($class) || !method_exists($class, $method))
			{
				$this->error(new XenForo_Phrase('please_enter_valid_callback_method'), 'callback_method');
			}
		}
	}

	protected function _postDelete()
	{
		$template = $this->getModelFromCache('XenForo_Model_Template')->getTemplateInStyleByTitle(
			$this->getModelFromCache('XenForo_Model_Page')->getTemplateTitle($this->getMergedData())
		);
		if ($template)
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_Template', XenForo_DataWriter::ERROR_SILENT);
			$dw->setExistingData($template, true);
			$dw->delete();
		}

		parent::_postDelete();
	}

	// TODO: delete template?
}