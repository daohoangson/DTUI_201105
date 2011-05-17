<?php

/**
* Class to handle compiling template function calls for "calc"
*
* @package XenForo_Template
*/
class XenForo_Template_Compiler_Function_Calc implements XenForo_Template_Compiler_Function_Interface
{
	/**
	* Compile the function and return PHP handle it.
	*
	* @param XenForo_Template_Compiler The invoking compiler
	* @param string                 Name of the function called
	* @param array                  Arguments to the function (should have at least 1)
	* @param array                  Compilation options
	*
	* @return string
	*/
	public function compile(XenForo_Template_Compiler $compiler, $function, array $arguments, array $options)
	{
		if (count($arguments) != 1)
		{
			throw $compiler->getNewCompilerArgumentException();
		}

		$placeholders = array();

		if (is_string($arguments[0]))
		{
			$expression = $arguments[0];
		}
		else
		{
			$expression = '';
			foreach ($compiler->prepareSegmentsForIteration($arguments[0]) AS $segment)
			{
				if (is_string($segment))
				{
					if (strpos($segment, '?') !== false)
					{
						throw $compiler->getNewCompilerException(new XenForo_Phrase('invalid_math_expression'));
					}

					$expression .= $segment;
				}
				else
				{
					$expression .= '?';
					$placeholders[] = $compiler->compileSegment($segment, array_merge($options, array('varEscape' => false)));
				}
			}
		}

		return $this->_parseMathExpression($compiler, $expression, $placeholders);
	}

	/**
	* Parses the math expression. "?" values represent placeholders for variables.
	* Returns valid PHP code for the expression.
	*
	* @param XenForo_Template_Compiler The invoking compiler
	* @param string                 The expression to parse. This value will be modified.
	* @param array                  Placeholders to replace "?" with. This value will be modified.
	* @param boolean                True if parsing an internal expression (with parens)
	* @param boolean                True if parsing a function (allows commas to separate args)
	*
	* @return string PHP code
	*/
	protected function _parseMathExpression(XenForo_Template_Compiler $compiler, &$expression, array &$placeholders,
		$internalExpression = false, $isFunction = false
	)
	{
		if ($internalExpression && $isFunction && strlen($expression) > 0 && $expression[0] == ')')
		{
			$expression = substr($expression, 1);
			return '()';
		}

		$state = 'value';
		$endState = 'operator';

		$compiled = '';

		do
		{
			$eatChars = 0;

			if ($state == 'value')
			{
				if (preg_match('#^\s+#', $expression, $match))
				{
					// ignore whitespace
					$eatChars = strlen($match[0]);
				}
				else if ($expression[0] == '?')
				{
					$compiled .= array_shift($placeholders);
					$state = 'operator';
					$eatChars = 1;
				}
				else if ($expression[0] == '(')
				{
					$expression = substr($expression, 1);
					$compiled .= $this->_parseMathExpression($compiler, $expression, $placeholders, true);
					$state = 'operator';
					continue; // not eating anything, so must continue
				}
				else if ($expression[0] == '-')
				{
					// negation, not subtraction
					$compiled .= '-';
					$state = 'value'; // we still need a value after this
					$eatChars = 1;
				}
				else if (preg_match('#^\d+(\.\d+)?#', $expression, $match))
				{
					$compiled .= $match[0];
					$state = 'operator';
					$eatChars = strlen($match[0]);
				}
				else if (preg_match('#^(abs|ceil|floor|max|min|pow|round)\(#i', $expression, $match))
				{
					$expression = substr($expression, strlen($match[0]));
					$compiled .= $match[1] . $this->_parseMathExpression($compiler, $expression, $placeholders, true, true);
					$state = 'operator';
					continue; // not eating anything, so must continue
				}
			}
			else if ($state == 'operator')
			{
				if (preg_match('#^\s+#', $expression, $match))
				{
					// ignore whitespace
					$eatChars = strlen($match[0]);
				}
				else
				{
					switch ($expression[0])
					{
						case '*':
						case '+':
						case '-':
						case '/':
						case '%':
							$eatChars = 1;
							$compiled .= " $expression[0] ";
							$state = 'value';
							break;

						case ',':
							if ($isFunction)
							{
								$eatChars = 1;
								$compiled .= ", ";
								$state = 'value';
							}
							else
							{
								// otherwise it wasn't expected
								throw $compiler->getNewCompilerException(new XenForo_Phrase('invalid_math_expression'));
							}
							break;

						case ')':
							if ($internalExpression)
							{
								// eat and return successfully
								$eatChars = 1;
								$state = false;
							}
							else
							{
								// otherwise it wasn't expected
								throw $compiler->getNewCompilerException(new XenForo_Phrase('invalid_math_expression'));
							}
							break;
					}
				}
			}

			if ($eatChars)
			{
				$expression = substr($expression, $eatChars);
			}
			else
			{
				// prevent infinite loops -- if you want to avoid this, use "continue"
				throw $compiler->getNewCompilerException(new XenForo_Phrase('invalid_math_expression'));
			}
		} while ($state !== false && $expression !== '' && $expression !== false);

		if ($internalExpression && $state !== false)
		{
			throw $compiler->getNewCompilerException(new XenForo_Phrase('invalid_math_expression'));
		}

		if ($state != $endState && $state !== false)
		{
			// operator is the end state -- means we're expecting an operator, so it can be anything
			throw $compiler->getNewCompilerException(new XenForo_Phrase('invalid_math_expression'));
		}

		return "($compiled)";
	}
}