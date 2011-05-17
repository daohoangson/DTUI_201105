<?php

/**
 * This is a helper class to output files via the MVC architecture. It allows outputting
 * of large data via readfile(). Note that the file can be read as a string if needed and
 * the file contents will be cached.
 */
class XenForo_FileOutput
{
	protected $_fileName = '';
	protected $_contents = null;

	public function __construct($fileName)
	{
		if (!file_exists($fileName))
		{
			throw new XenForo_Exception('File does not exist');
		}
		if (!is_readable($fileName))
		{
			throw new XenForo_Exception('File is not readable');
		}

		$this->_fileName = $fileName;
	}

	public function __toString()
	{
		return $this->getContents();
	}

	public function output()
	{
		if ($this->_contents === null)
		{
			readfile($this->_fileName);
		}
		else
		{
			echo $this->_contents;
		}
	}

	public function getFileName()
	{
		return $this->_fileName;
	}

	public function getContents()
	{
		if ($this->_contents === null)
		{
			$this->_contents = file_get_contents($this->_fileName);
		}

		return $this->_contents;
	}
}