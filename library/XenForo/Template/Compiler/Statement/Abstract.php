<?php

abstract class XenForo_Template_Compiler_Statement_Abstract
{
	protected $_statements = array();

	public function __construct($statement = '')
	{
		$this->addStatement($statement);
	}

	public function addStatement($statement)
	{
		if (!is_string($statement) && !($statement instanceof self))
		{
			throw new XenForo_Template_Compiler_Exception(new XenForo_Phrase('invalid_statement'), true);
		}

		if ($statement !== '')
		{
			$this->_statements[] = $statement;
		}

		return $this;
	}

	abstract public function getFullStatements($outputVar);
}