<?php
/**
* Data writer for Categories.
*
* @package XenForo_Category
*/
class XenForo_DataWriter_Category extends XenForo_DataWriter_Node
{
	/**
	 * Title of the phrase that will be created when a call to set the
	 * existing data fails (when the data doesn't exist).
	 *
	 * @var string
	 */
	protected $_existingDataErrorPhrase = 'requested_category_not_found';

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

		return array('xf_node' => $this->getModelFromCache('XenForo_Model_Node')->getNodeById($nodeId));
	}
}