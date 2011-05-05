<?php

/**
 * Helper for file-related functions.
 *
 * @package XenForo_Helper
 */
abstract class XenForo_Helper_File
{
	/**
	 * Recursively creates directories until the full path is created.
	 *
	 * @param string $path Directory path to create
	 * @param boolean $createIndexHtml If true, creates an index.html file in the created directory
	 *
	 * @return boolean True on success
	 */
	public static function createDirectory($path, $createIndexHtml = false)
	{
		$path = preg_replace('#/+$#', '', $path);

		if (file_exists($path) && is_dir($path))
		{
			return true;
		}

		$path = str_replace('\\', '/', $path);
		$parts = explode('/', $path);
		$pathPartCount = count($parts);
		$partialPath = '';

		$rootDir = XenForo_Application::getInstance()->getRootDir();

		// find the "lowest" part that exists (and is a dir)...
		for ($i = $pathPartCount - 1; $i >= 0; $i--)
		{
			$partialPath = implode('/', array_slice($parts, 0, $i + 1));
			if ($partialPath == $rootDir)
			{
				return false; // can't go above the root dir
			}

			if (file_exists($partialPath))
			{
				if (!is_dir($partialPath))
				{
					return false;
				}
				else
				{
					break;
				}
			}
		}
		if ($i < 0)
		{
			return false;
		}

		$i++; // skip over the last entry (as it exists)

		// ... now create directories for anything below it
		for (; $i < $pathPartCount; $i++)
		{
			$partialPath .= '/' . $parts[$i];
			if (!mkdir($partialPath))
			{
				return false;
			}
			else if ($createIndexHtml)
			{
				XenForo_Helper_File::makeWritableByFtpUser($partialPath);

				$fp = @fopen($partialPath . '/index.html', 'w');
				if ($fp)
				{
					fwrite($fp, ' ');
					fclose($fp);

					XenForo_Helper_File::makeWritableByFtpUser($partialPath . '/index.html');
				}
			}
		}

		return true;
	}

	/**
	 * Gets a file's extension in lower case. This only includes the last
	 * extension (eg, x.tar.gz -> gz).
	 *
	 * @param string $filename
	 *
	 * @return string
	 */
	public static function getFileExtension($filename)
	{
		return strtolower(substr(strrchr($filename, '.'), 1));
	}

	/**
	 * Gets the name of a temporary directory where files can be written.
	 *
	 * @return string
	 */
	public static function getTempDir()
	{
		return self::getInternalDataPath() . '/temp';
	}

	/**
	 * Ensures that the specified file can be written to by the FTP user.
	 * This generally doesn't need to do anything if PHP is running via
	 * some form of suexec. It's primarily needed when running as apache.
	 *
	 * @param string $file
	 * @return boolean
	 */
	public static function makeWritableByFtpUser($file)
	{
		// TODO: detect suexec, suphp, etc and don't change from default
		return chmod($file, 0777);
	}

	/**
	 * Gets the path to the internal data directory (internal_data).
	 * This directory can be moved above the web root.
	 *
	 * @return string Absolute path
	 */
	public static function getInternalDataPath()
	{
		if (!XenForo_Application::isRegistered('config'))
		{
			$path = 'internal_data';
		}
		else
		{
			$path = XenForo_Application::get('config')->internalDataPath;
		}

		if (preg_match('#^/|\\|[a-z]:#i', $path))
		{
			// absolute path
			return $path;
		}
		else
		{
			return XenForo_Application::getInstance()->getRootDir() . '/' . $path;
		}
	}

	/**
	 * Gets the path to the external data directory (data)
	 * This directory can NOT be moved above the web root.
	 *
	 * @return string Absolute path
	 */
	public static function getExternalDataPath()
	{
		if (!XenForo_Application::isRegistered('config'))
		{
			$path = 'data';
		}
		else
		{
			$path = XenForo_Application::get('config')->externalDataPath;
		}

		if (preg_match('#^/|\\|[a-z]:#i', $path))
		{
			// absolute path
			return $path;
		}
		else
		{
			return XenForo_Application::getInstance()->getRootDir() . '/' . $path;
		}
	}

	/**
	 * Method for writing out a file log.
	 *
	 * @param string $logName 'foo' will write to {internalDataPath}/foo.log
	 * @param string $logEntry The string to write into the log. Line break not required.
	 * @param boolean $append Append the log entry to the end of the existing log. Otherwise, start again.
	 *
	 * @return boolean True on successful log write
	 */
	public static function log($logName, $logEntry, $append = true)
	{
		$logName = preg_replace('/[^a-z0-9._-]/i', '', $logName);

		if ($fp = @fopen(self::getInternalDataPath() . '/' . $logName . '.log', ($append ? 'a' : 'w')))
		{
			fwrite($fp, date('Y-m-d H:i:s') . ' ' . $logEntry . "\n");
			fclose($fp);

			return true;
		}

		return false;
	}
}