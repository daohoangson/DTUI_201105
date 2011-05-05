<?php

class XenForo_ControllerHelper_Xml extends XenForo_ControllerHelper_Abstract
{
	/**
	 * Returns a SimpleXML element from the provided file name (or XenForo_Upload object)
	 * provided it is valid. If it is not valid, an error is thrown.
	 *
	 * @param string|XenForo_Upload $file
	 *
	 * @return SimpleXMLElement
	 */
	public function getXmlFromFile($file)
	{
		if ($file instanceof XenForo_Upload)
		{
			$file = $file->getTempFile();
		}

		if (!file_exists($file))
		{
			throw $this->_controller->responseException($this->_controller->responseError(
				new XenForo_Phrase('please_enter_valid_file_name_requested_file_not_read')
			));
		}

		try
		{
			return new SimpleXMLElement($file, 0, true);
		}
		catch (Exception $e)
		{
			throw $this->_controller->responseException($this->_controller->responseError(
				new XenForo_Phrase('provided_file_was_not_valid_xml_file')
			));
		}
	}
}