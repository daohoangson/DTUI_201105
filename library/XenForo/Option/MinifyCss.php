<?php

class XenForo_Option_MinifyCss
{
	/**
	 * Updates the build date of styles after the value of the minifyCss option changes.
	 *
	 * @param boolean $option
	 * @param XenForo_DataWriter $dw
	 * @param string $fieldName
	 *
	 * @return boolean
	 */
	public static function verifyOption(&$option, XenForo_DataWriter $dw, $fieldName)
	{
		XenForo_Model::create('XenForo_Model_Style')->updateAllStylesLastModifiedDate();

		return true;
	}
}