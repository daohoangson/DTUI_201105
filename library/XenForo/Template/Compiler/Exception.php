<?php

class XenForo_Template_Compiler_Exception extends XenForo_Exception
{
	protected $_lineNumber = 0;

	public function setLineNumber($lineNumber)
	{
		$this->_lineNumber = intval($lineNumber);
	}

	public function getLineNumber()
	{
		return $this->_lineNumber;
	}
}