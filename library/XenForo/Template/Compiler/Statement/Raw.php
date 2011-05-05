<?php

class XenForo_Template_Compiler_Statement_Raw extends XenForo_Template_Compiler_Statement_Abstract
{
	public function getFullStatements($outputVar)
	{
		$output = '';

		foreach ($this->_statements AS $statement)
		{
			if (is_string($statement))
			{
				$output .= $statement;
			}
			else
			{
				$output .= $statement->getFullStatements($outputVar);
			}
		}

		return $output;
	}
}