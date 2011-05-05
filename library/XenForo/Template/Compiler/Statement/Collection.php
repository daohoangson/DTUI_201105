<?php

class XenForo_Template_Compiler_Statement_Collection extends XenForo_Template_Compiler_Statement_Abstract
{
	public function getFullStatements($outputVar)
	{
		$output = '';
		$partial = '';

		foreach ($this->_statements AS $statement)
		{
			if (is_string($statement))
			{
				if ($statement !== '')
				{
					$partial .= ($partial === '' ? $statement : ' . ' . $statement);
				}
			}
			else
			{
				if ($partial)
				{
					$output .= $this->_getFullStatementFromPartial($partial, $outputVar);
					$partial = '';
				}

				$childStatement = $statement->getFullStatements($outputVar);
				if ($childStatement !== '')
				{
					$output .= $childStatement;
				}
			}
		}

		if ($partial)
		{
			$output .= $this->_getFullStatementFromPartial($partial, $outputVar);
		}

		return $output;
	}

	public function getPartialStatement()
	{
		$output = '';
		$partial = '';

		foreach ($this->_statements AS $statement)
		{
			if (is_string($statement))
			{
				if ($statement !== '')
				{
					$partial .= ($partial === '' ? $statement : ' . ' . $statement);
				}
			}
			else
			{
				throw new XenForo_Template_Compiler_Exception('Statement contains more than just partial statements and only partial statements were requested');
			}
		}

		if ($partial === '')
		{
			return "''";
		}
		else
		{
			return $partial;
		}
	}

	protected function _getFullStatementFromPartial($partial, $outputVar)
	{
		return '$' . $outputVar . ' .= ' . $partial . ";\n";
	}
}