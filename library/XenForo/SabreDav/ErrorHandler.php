<?php

class XenForo_SabreDav_ErrorHandler
{
	public static function assertNoErrors(XenForo_DataWriter $dw, $checkMethod, $dataType)
	{
		switch (strtolower($checkMethod))
		{
			case 'delete';
			case 'predelete':
				$checkMethod = 'preDelete';
				break;

			case 'save':
			case 'presave':
			default:
				$checkMethod = 'preSave';
				break;
		}

		$dw->$checkMethod();

		if ($errors = $dw->getErrors())
		{
			$errorString = implode("\n\t", $errors);

			XenForo_Helper_File::log('webdav-error', sprintf("%s:\n\t%s\n\t%s", $dataType, $dw->get('title'), $errorString), false);

			// Note that in order to have Dreamweaver actually show an error, we have to use 'Forbidden'.
			throw new Sabre_DAV_Exception_Forbidden($errorString);
		}
	}
}