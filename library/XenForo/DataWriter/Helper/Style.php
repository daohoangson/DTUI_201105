<?php

class XenForo_DataWriter_Helper_Style
{
	/**
	 * Verifies that the provided integer is a valid style ID
	 *
	 * @param integer $styleId
	 *
	 * @return boolean
	 */
	public static function verifyStyleId($styleId, XenForo_DataWriter $dw, $fieldName = false)
	{
		if ($styleId === 0)
		{
			// explicitly set to 0, use system default
			return true;
		}

		if ($dw->getModelFromCache('XenForo_Model_Style')->getStyleById($styleId))
		{
			return true;
		}

		$dw->error(new XenForo_Phrase('please_select_valid_style'), $fieldName);
		return false;
	}
}