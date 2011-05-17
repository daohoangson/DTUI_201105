<?php

class XenForo_Template_Compiler_Lexer
{
	private $_input;
	private $_counter = 0;
	public $token;
	public $value;
	public $line = 1;

	public $match;

	public function __construct($input)
	{
		$this->_input = $input;
	}

	protected function _addMatch($token, $info = '')
	{
		$this->match = array($token, $info);
	}


    private $_yy_state = 1;
    private $_yy_stack = array();

    function yylex()
    {
        return $this->{'yylex' . $this->_yy_state}();
    }

    function yypushstate($state)
    {
        array_push($this->_yy_stack, $this->_yy_state);
        $this->_yy_state = $state;
    }

    function yypopstate()
    {
        $this->_yy_state = array_pop($this->_yy_stack);
    }

    function yybegin($state)
    {
        $this->_yy_state = $state;
    }



    function yylex1()
    {
        $tokenMap = array (
              1 => 0,
              2 => 0,
              3 => 0,
              4 => 1,
              6 => 0,
              7 => 0,
              8 => 0,
            );
        if ($this->_counter >= strlen($this->_input)) {
            return false; // end of input
        }
        $yy_global_pattern = "/^(\\{\\$)|^(\\{xen:)|^(<xen:)|^(<\/xen:([a-zA-Z0-9_]+)>)|^([^{<]+)|^(\\{)|^(<)/";

        do {
            if (preg_match($yy_global_pattern, substr($this->_input, $this->_counter), $yymatches)) {
                $yysubmatches = $yymatches;
                $yymatches = array_filter($yymatches, 'strlen'); // remove empty sub-patterns
                if (!count($yymatches)) {
                    throw new Exception('Error: lexing failed because a rule matched' .
                        'an empty string.  Input "' . substr($this->_input,
                        $this->_counter, 5) . '... state START');
                }
                next($yymatches); // skip global match
                $this->token = key($yymatches); // token number
                if ($tokenMap[$this->token]) {
                    // extract sub-patterns for passing to lex function
                    $yysubmatches = array_slice($yysubmatches, $this->token + 1,
                        $tokenMap[$this->token]);
                } else {
                    $yysubmatches = array();
                }
                $this->value = current($yymatches); // token value
                $r = $this->{'yy_r1_' . $this->token}($yysubmatches);
                if ($r === null) {
                    $this->_counter += strlen($this->value);
                    $this->line += substr_count($this->value, "\n");
                    // accept this token
                    return true;
                } elseif ($r === true) {
                    // we have changed state
                    // process this token in the new state
                    return $this->yylex();
                } elseif ($r === false) {
                    $this->_counter += strlen($this->value);
                    $this->line += substr_count($this->value, "\n");
                    if ($this->_counter >= strlen($this->_input)) {
                        return false; // end of input
                    }
                    // skip this token
                    continue;
                } else {                    $yy_yymore_patterns = array(
        1 => array(0, "^(\\{xen:)|^(<xen:)|^(<\/xen:([a-zA-Z0-9_]+)>)|^([^{<]+)|^(\\{)|^(<)"),
        2 => array(0, "^(<xen:)|^(<\/xen:([a-zA-Z0-9_]+)>)|^([^{<]+)|^(\\{)|^(<)"),
        3 => array(0, "^(<\/xen:([a-zA-Z0-9_]+)>)|^([^{<]+)|^(\\{)|^(<)"),
        4 => array(1, "^([^{<]+)|^(\\{)|^(<)"),
        6 => array(1, "^(\\{)|^(<)"),
        7 => array(1, "^(<)"),
        8 => array(1, ""),
    );

                    // yymore is needed
                    do {
                        if (!strlen($yy_yymore_patterns[$this->token][1])) {
                            throw new Exception('cannot do yymore for the last token');
                        }
                        $yysubmatches = array();
                        if (preg_match('/' . $yy_yymore_patterns[$this->token][1] . '/',
                              substr($this->_input, $this->_counter), $yymatches)) {
                            $yysubmatches = $yymatches;
                            $yymatches = array_filter($yymatches, 'strlen'); // remove empty sub-patterns
                            next($yymatches); // skip global match
                            $this->token += key($yymatches) + $yy_yymore_patterns[$this->token][0]; // token number
                            $this->value = current($yymatches); // token value
                            $this->line = substr_count($this->value, "\n");
                            if ($tokenMap[$this->token]) {
                                // extract sub-patterns for passing to lex function
                                $yysubmatches = array_slice($yysubmatches, $this->token + 1,
                                    $tokenMap[$this->token]);
                            } else {
                                $yysubmatches = array();
                            }
                        }
                    	$r = $this->{'yy_r1_' . $this->token}($yysubmatches);
                    } while ($r !== null && !is_bool($r));
			        if ($r === true) {
			            // we have changed state
			            // process this token in the new state
			            return $this->yylex();
                    } elseif ($r === false) {
                        $this->_counter += strlen($this->value);
                        $this->line += substr_count($this->value, "\n");
                        if ($this->_counter >= strlen($this->_input)) {
                            return false; // end of input
                        }
                        // skip this token
                        continue;
			        } else {
	                    // accept
	                    $this->_counter += strlen($this->value);
	                    $this->line += substr_count($this->value, "\n");
	                    return true;
			        }
                }
            } else {
                throw new Exception('Unexpected input at line' . $this->line .
                    ': ' . $this->_input[$this->_counter]);
            }
            break;
        } while (true);

    } // end function


    const START = 1;
    function yy_r1_1($yy_subpatterns)
    {

	$this->yypushstate(self::CURLY_START);
	return true;
    }
    function yy_r1_2($yy_subpatterns)
    {

	$this->yypushstate(self::CURLY_START);
	return true;
    }
    function yy_r1_3($yy_subpatterns)
    {

	$this->yypushstate(self::TAG_OPEN);
	return true;
    }
    function yy_r1_4($yy_subpatterns)
    {

	$this->_addMatch(XenForo_Template_Compiler_Parser::TAG_CLOSE, $yy_subpatterns[0]);
    }
    function yy_r1_6($yy_subpatterns)
    {

	$this->_addMatch(XenForo_Template_Compiler_Parser::PLAIN_TEXT, $this->value);
    }
    function yy_r1_7($yy_subpatterns)
    {

	$this->_addMatch(XenForo_Template_Compiler_Parser::PLAIN_TEXT, $this->value);
    }
    function yy_r1_8($yy_subpatterns)
    {

	$this->_addMatch(XenForo_Template_Compiler_Parser::PLAIN_TEXT, $this->value);
    }



    function yylex2()
    {
        $tokenMap = array (
              1 => 1,
              3 => 1,
              5 => 1,
              7 => 0,
              8 => 0,
            );
        if ($this->_counter >= strlen($this->_input)) {
            return false; // end of input
        }
        $yy_global_pattern = "/^(<xen:comment>([\s\S]*?)<\/xen:comment>)|^(<xen:untreated>([\s\S]*?)<\/xen:untreated>)|^(<xen:([a-zA-Z0-9_]+))|^(>)|^(\/>)/";

        do {
            if (preg_match($yy_global_pattern, substr($this->_input, $this->_counter), $yymatches)) {
                $yysubmatches = $yymatches;
                $yymatches = array_filter($yymatches, 'strlen'); // remove empty sub-patterns
                if (!count($yymatches)) {
                    throw new Exception('Error: lexing failed because a rule matched' .
                        'an empty string.  Input "' . substr($this->_input,
                        $this->_counter, 5) . '... state TAG_OPEN');
                }
                next($yymatches); // skip global match
                $this->token = key($yymatches); // token number
                if ($tokenMap[$this->token]) {
                    // extract sub-patterns for passing to lex function
                    $yysubmatches = array_slice($yysubmatches, $this->token + 1,
                        $tokenMap[$this->token]);
                } else {
                    $yysubmatches = array();
                }
                $this->value = current($yymatches); // token value
                $r = $this->{'yy_r2_' . $this->token}($yysubmatches);
                if ($r === null) {
                    $this->_counter += strlen($this->value);
                    $this->line += substr_count($this->value, "\n");
                    // accept this token
                    return true;
                } elseif ($r === true) {
                    // we have changed state
                    // process this token in the new state
                    return $this->yylex();
                } elseif ($r === false) {
                    $this->_counter += strlen($this->value);
                    $this->line += substr_count($this->value, "\n");
                    if ($this->_counter >= strlen($this->_input)) {
                        return false; // end of input
                    }
                    // skip this token
                    continue;
                } else {                    $yy_yymore_patterns = array(
        1 => array(0, "^(<xen:untreated>([\s\S]*?)<\/xen:untreated>)|^(<xen:([a-zA-Z0-9_]+))|^(>)|^(\/>)"),
        3 => array(1, "^(<xen:([a-zA-Z0-9_]+))|^(>)|^(\/>)"),
        5 => array(2, "^(>)|^(\/>)"),
        7 => array(2, "^(\/>)"),
        8 => array(2, ""),
    );

                    // yymore is needed
                    do {
                        if (!strlen($yy_yymore_patterns[$this->token][1])) {
                            throw new Exception('cannot do yymore for the last token');
                        }
                        $yysubmatches = array();
                        if (preg_match('/' . $yy_yymore_patterns[$this->token][1] . '/',
                              substr($this->_input, $this->_counter), $yymatches)) {
                            $yysubmatches = $yymatches;
                            $yymatches = array_filter($yymatches, 'strlen'); // remove empty sub-patterns
                            next($yymatches); // skip global match
                            $this->token += key($yymatches) + $yy_yymore_patterns[$this->token][0]; // token number
                            $this->value = current($yymatches); // token value
                            $this->line = substr_count($this->value, "\n");
                            if ($tokenMap[$this->token]) {
                                // extract sub-patterns for passing to lex function
                                $yysubmatches = array_slice($yysubmatches, $this->token + 1,
                                    $tokenMap[$this->token]);
                            } else {
                                $yysubmatches = array();
                            }
                        }
                    	$r = $this->{'yy_r2_' . $this->token}($yysubmatches);
                    } while ($r !== null && !is_bool($r));
			        if ($r === true) {
			            // we have changed state
			            // process this token in the new state
			            return $this->yylex();
                    } elseif ($r === false) {
                        $this->_counter += strlen($this->value);
                        $this->line += substr_count($this->value, "\n");
                        if ($this->_counter >= strlen($this->_input)) {
                            return false; // end of input
                        }
                        // skip this token
                        continue;
			        } else {
	                    // accept
	                    $this->_counter += strlen($this->value);
	                    $this->line += substr_count($this->value, "\n");
	                    return true;
			        }
                }
            } else {
                throw new Exception('Unexpected input at line' . $this->line .
                    ': ' . $this->_input[$this->_counter]);
            }
            break;
        } while (true);

    } // end function


    const TAG_OPEN = 2;
    function yy_r2_1($yy_subpatterns)
    {

	$this->_addMatch(XenForo_Template_Compiler_Parser::TAG_COMMENT, $yy_subpatterns[0]);

	$this->yypopstate();
    }
    function yy_r2_3($yy_subpatterns)
    {

	$this->_addMatch(XenForo_Template_Compiler_Parser::PLAIN_TEXT, $yy_subpatterns[0]);

	$this->yypopstate();
    }
    function yy_r2_5($yy_subpatterns)
    {

	$this->_addMatch(XenForo_Template_Compiler_Parser::TAG_OPEN, $yy_subpatterns[0]);

	$this->yypushstate(self::TAG_INNER);
    }
    function yy_r2_7($yy_subpatterns)
    {

	$this->_addMatch(XenForo_Template_Compiler_Parser::TAG_END);

	$this->yypopstate();
    }
    function yy_r2_8($yy_subpatterns)
    {

	$this->_addMatch(XenForo_Template_Compiler_Parser::TAG_SELF_CLOSE);

	$this->yypopstate();
    }



    function yylex3()
    {
        $tokenMap = array (
              1 => 1,
              3 => 0,
              4 => 0,
              5 => 0,
              6 => 0,
              7 => 0,
            );
        if ($this->_counter >= strlen($this->_input)) {
            return false; // end of input
        }
        $yy_global_pattern = "/^(([a-zA-Z0-9_-]+)=)|^(\")|^(')|^([ \n\r\t]+)|^(>)|^(\/>)/";

        do {
            if (preg_match($yy_global_pattern, substr($this->_input, $this->_counter), $yymatches)) {
                $yysubmatches = $yymatches;
                $yymatches = array_filter($yymatches, 'strlen'); // remove empty sub-patterns
                if (!count($yymatches)) {
                    throw new Exception('Error: lexing failed because a rule matched' .
                        'an empty string.  Input "' . substr($this->_input,
                        $this->_counter, 5) . '... state TAG_INNER');
                }
                next($yymatches); // skip global match
                $this->token = key($yymatches); // token number
                if ($tokenMap[$this->token]) {
                    // extract sub-patterns for passing to lex function
                    $yysubmatches = array_slice($yysubmatches, $this->token + 1,
                        $tokenMap[$this->token]);
                } else {
                    $yysubmatches = array();
                }
                $this->value = current($yymatches); // token value
                $r = $this->{'yy_r3_' . $this->token}($yysubmatches);
                if ($r === null) {
                    $this->_counter += strlen($this->value);
                    $this->line += substr_count($this->value, "\n");
                    // accept this token
                    return true;
                } elseif ($r === true) {
                    // we have changed state
                    // process this token in the new state
                    return $this->yylex();
                } elseif ($r === false) {
                    $this->_counter += strlen($this->value);
                    $this->line += substr_count($this->value, "\n");
                    if ($this->_counter >= strlen($this->_input)) {
                        return false; // end of input
                    }
                    // skip this token
                    continue;
                } else {                    $yy_yymore_patterns = array(
        1 => array(0, "^(\")|^(')|^([ \n\r\t]+)|^(>)|^(\/>)"),
        3 => array(0, "^(')|^([ \n\r\t]+)|^(>)|^(\/>)"),
        4 => array(0, "^([ \n\r\t]+)|^(>)|^(\/>)"),
        5 => array(0, "^(>)|^(\/>)"),
        6 => array(0, "^(\/>)"),
        7 => array(0, ""),
    );

                    // yymore is needed
                    do {
                        if (!strlen($yy_yymore_patterns[$this->token][1])) {
                            throw new Exception('cannot do yymore for the last token');
                        }
                        $yysubmatches = array();
                        if (preg_match('/' . $yy_yymore_patterns[$this->token][1] . '/',
                              substr($this->_input, $this->_counter), $yymatches)) {
                            $yysubmatches = $yymatches;
                            $yymatches = array_filter($yymatches, 'strlen'); // remove empty sub-patterns
                            next($yymatches); // skip global match
                            $this->token += key($yymatches) + $yy_yymore_patterns[$this->token][0]; // token number
                            $this->value = current($yymatches); // token value
                            $this->line = substr_count($this->value, "\n");
                            if ($tokenMap[$this->token]) {
                                // extract sub-patterns for passing to lex function
                                $yysubmatches = array_slice($yysubmatches, $this->token + 1,
                                    $tokenMap[$this->token]);
                            } else {
                                $yysubmatches = array();
                            }
                        }
                    	$r = $this->{'yy_r3_' . $this->token}($yysubmatches);
                    } while ($r !== null && !is_bool($r));
			        if ($r === true) {
			            // we have changed state
			            // process this token in the new state
			            return $this->yylex();
                    } elseif ($r === false) {
                        $this->_counter += strlen($this->value);
                        $this->line += substr_count($this->value, "\n");
                        if ($this->_counter >= strlen($this->_input)) {
                            return false; // end of input
                        }
                        // skip this token
                        continue;
			        } else {
	                    // accept
	                    $this->_counter += strlen($this->value);
	                    $this->line += substr_count($this->value, "\n");
	                    return true;
			        }
                }
            } else {
                throw new Exception('Unexpected input at line' . $this->line .
                    ': ' . $this->_input[$this->_counter]);
            }
            break;
        } while (true);

    } // end function


    const TAG_INNER = 3;
    function yy_r3_1($yy_subpatterns)
    {

	$this->_addMatch(XenForo_Template_Compiler_Parser::TAG_ATTRIBUTE, $yy_subpatterns[0]);
    }
    function yy_r3_3($yy_subpatterns)
    {

	$this->_addMatch(XenForo_Template_Compiler_Parser::DOUBLE_QUOTE, '');

	$this->yypushstate(self::DOUBLE_QUOTED);
    }
    function yy_r3_4($yy_subpatterns)
    {

	$this->_addMatch(XenForo_Template_Compiler_Parser::SINGLE_QUOTE, '');

	$this->yypushstate(self::SINGLE_QUOTED);
    }
    function yy_r3_5($yy_subpatterns)
    {

	return false;
    }
    function yy_r3_6($yy_subpatterns)
    {

	$this->yypopstate();
	return true;
    }
    function yy_r3_7($yy_subpatterns)
    {

	$this->yypopstate();
	return true;
    }



    function yylex4()
    {
        $tokenMap = array (
              1 => 1,
              3 => 1,
              5 => 0,
            );
        if ($this->_counter >= strlen($this->_input)) {
            return false; // end of input
        }
        $yy_global_pattern = "/^(\\{\\$([a-zA-Z_][a-zA-Z0-9_]*))|^(\\{xen:([a-zA-Z0-9_]+)[ \n\r\t]+)|^(\\})/";

        do {
            if (preg_match($yy_global_pattern, substr($this->_input, $this->_counter), $yymatches)) {
                $yysubmatches = $yymatches;
                $yymatches = array_filter($yymatches, 'strlen'); // remove empty sub-patterns
                if (!count($yymatches)) {
                    throw new Exception('Error: lexing failed because a rule matched' .
                        'an empty string.  Input "' . substr($this->_input,
                        $this->_counter, 5) . '... state CURLY_START');
                }
                next($yymatches); // skip global match
                $this->token = key($yymatches); // token number
                if ($tokenMap[$this->token]) {
                    // extract sub-patterns for passing to lex function
                    $yysubmatches = array_slice($yysubmatches, $this->token + 1,
                        $tokenMap[$this->token]);
                } else {
                    $yysubmatches = array();
                }
                $this->value = current($yymatches); // token value
                $r = $this->{'yy_r4_' . $this->token}($yysubmatches);
                if ($r === null) {
                    $this->_counter += strlen($this->value);
                    $this->line += substr_count($this->value, "\n");
                    // accept this token
                    return true;
                } elseif ($r === true) {
                    // we have changed state
                    // process this token in the new state
                    return $this->yylex();
                } elseif ($r === false) {
                    $this->_counter += strlen($this->value);
                    $this->line += substr_count($this->value, "\n");
                    if ($this->_counter >= strlen($this->_input)) {
                        return false; // end of input
                    }
                    // skip this token
                    continue;
                } else {                    $yy_yymore_patterns = array(
        1 => array(0, "^(\\{xen:([a-zA-Z0-9_]+)[ \n\r\t]+)|^(\\})"),
        3 => array(1, "^(\\})"),
        5 => array(1, ""),
    );

                    // yymore is needed
                    do {
                        if (!strlen($yy_yymore_patterns[$this->token][1])) {
                            throw new Exception('cannot do yymore for the last token');
                        }
                        $yysubmatches = array();
                        if (preg_match('/' . $yy_yymore_patterns[$this->token][1] . '/',
                              substr($this->_input, $this->_counter), $yymatches)) {
                            $yysubmatches = $yymatches;
                            $yymatches = array_filter($yymatches, 'strlen'); // remove empty sub-patterns
                            next($yymatches); // skip global match
                            $this->token += key($yymatches) + $yy_yymore_patterns[$this->token][0]; // token number
                            $this->value = current($yymatches); // token value
                            $this->line = substr_count($this->value, "\n");
                            if ($tokenMap[$this->token]) {
                                // extract sub-patterns for passing to lex function
                                $yysubmatches = array_slice($yysubmatches, $this->token + 1,
                                    $tokenMap[$this->token]);
                            } else {
                                $yysubmatches = array();
                            }
                        }
                    	$r = $this->{'yy_r4_' . $this->token}($yysubmatches);
                    } while ($r !== null && !is_bool($r));
			        if ($r === true) {
			            // we have changed state
			            // process this token in the new state
			            return $this->yylex();
                    } elseif ($r === false) {
                        $this->_counter += strlen($this->value);
                        $this->line += substr_count($this->value, "\n");
                        if ($this->_counter >= strlen($this->_input)) {
                            return false; // end of input
                        }
                        // skip this token
                        continue;
			        } else {
	                    // accept
	                    $this->_counter += strlen($this->value);
	                    $this->line += substr_count($this->value, "\n");
	                    return true;
			        }
                }
            } else {
                throw new Exception('Unexpected input at line' . $this->line .
                    ': ' . $this->_input[$this->_counter]);
            }
            break;
        } while (true);

    } // end function


    const CURLY_START = 4;
    function yy_r4_1($yy_subpatterns)
    {

	$this->yypushstate(self::CURLY_VAR_START);
	return true;
    }
    function yy_r4_3($yy_subpatterns)
    {

	$this->yypushstate(self::CURLY_FUNCTION_START);
	return true;
    }
    function yy_r4_5($yy_subpatterns)
    {

	$this->_addMatch(XenForo_Template_Compiler_Parser::CURLY_END);

	$this->yypopstate();
    }



    function yylex5()
    {
        $tokenMap = array (
              1 => 1,
              3 => 0,
            );
        if ($this->_counter >= strlen($this->_input)) {
            return false; // end of input
        }
        $yy_global_pattern = "/^(\\{\\$([a-zA-Z_][a-zA-Z0-9_]*))|^(\\})/";

        do {
            if (preg_match($yy_global_pattern, substr($this->_input, $this->_counter), $yymatches)) {
                $yysubmatches = $yymatches;
                $yymatches = array_filter($yymatches, 'strlen'); // remove empty sub-patterns
                if (!count($yymatches)) {
                    throw new Exception('Error: lexing failed because a rule matched' .
                        'an empty string.  Input "' . substr($this->_input,
                        $this->_counter, 5) . '... state CURLY_VAR_START');
                }
                next($yymatches); // skip global match
                $this->token = key($yymatches); // token number
                if ($tokenMap[$this->token]) {
                    // extract sub-patterns for passing to lex function
                    $yysubmatches = array_slice($yysubmatches, $this->token + 1,
                        $tokenMap[$this->token]);
                } else {
                    $yysubmatches = array();
                }
                $this->value = current($yymatches); // token value
                $r = $this->{'yy_r5_' . $this->token}($yysubmatches);
                if ($r === null) {
                    $this->_counter += strlen($this->value);
                    $this->line += substr_count($this->value, "\n");
                    // accept this token
                    return true;
                } elseif ($r === true) {
                    // we have changed state
                    // process this token in the new state
                    return $this->yylex();
                } elseif ($r === false) {
                    $this->_counter += strlen($this->value);
                    $this->line += substr_count($this->value, "\n");
                    if ($this->_counter >= strlen($this->_input)) {
                        return false; // end of input
                    }
                    // skip this token
                    continue;
                } else {                    $yy_yymore_patterns = array(
        1 => array(0, "^(\\})"),
        3 => array(0, ""),
    );

                    // yymore is needed
                    do {
                        if (!strlen($yy_yymore_patterns[$this->token][1])) {
                            throw new Exception('cannot do yymore for the last token');
                        }
                        $yysubmatches = array();
                        if (preg_match('/' . $yy_yymore_patterns[$this->token][1] . '/',
                              substr($this->_input, $this->_counter), $yymatches)) {
                            $yysubmatches = $yymatches;
                            $yymatches = array_filter($yymatches, 'strlen'); // remove empty sub-patterns
                            next($yymatches); // skip global match
                            $this->token += key($yymatches) + $yy_yymore_patterns[$this->token][0]; // token number
                            $this->value = current($yymatches); // token value
                            $this->line = substr_count($this->value, "\n");
                            if ($tokenMap[$this->token]) {
                                // extract sub-patterns for passing to lex function
                                $yysubmatches = array_slice($yysubmatches, $this->token + 1,
                                    $tokenMap[$this->token]);
                            } else {
                                $yysubmatches = array();
                            }
                        }
                    	$r = $this->{'yy_r5_' . $this->token}($yysubmatches);
                    } while ($r !== null && !is_bool($r));
			        if ($r === true) {
			            // we have changed state
			            // process this token in the new state
			            return $this->yylex();
                    } elseif ($r === false) {
                        $this->_counter += strlen($this->value);
                        $this->line += substr_count($this->value, "\n");
                        if ($this->_counter >= strlen($this->_input)) {
                            return false; // end of input
                        }
                        // skip this token
                        continue;
			        } else {
	                    // accept
	                    $this->_counter += strlen($this->value);
	                    $this->line += substr_count($this->value, "\n");
	                    return true;
			        }
                }
            } else {
                throw new Exception('Unexpected input at line' . $this->line .
                    ': ' . $this->_input[$this->_counter]);
            }
            break;
        } while (true);

    } // end function


    const CURLY_VAR_START = 5;
    function yy_r5_1($yy_subpatterns)
    {

	$this->_addMatch(XenForo_Template_Compiler_Parser::CURLY_VAR, $yy_subpatterns[0]);

	$this->yypushstate(self::CURLY_VAR_INNER);
    }
    function yy_r5_3($yy_subpatterns)
    {

	$this->yypopstate();
	return true;
    }



    function yylex6()
    {
        $tokenMap = array (
              1 => 0,
              2 => 0,
              3 => 0,
              4 => 0,
            );
        if ($this->_counter >= strlen($this->_input)) {
            return false; // end of input
        }
        $yy_global_pattern = "/^(\\.)|^(\\})|^(\\{\\$)|^([^.{}\"'\s]+)/";

        do {
            if (preg_match($yy_global_pattern, substr($this->_input, $this->_counter), $yymatches)) {
                $yysubmatches = $yymatches;
                $yymatches = array_filter($yymatches, 'strlen'); // remove empty sub-patterns
                if (!count($yymatches)) {
                    throw new Exception('Error: lexing failed because a rule matched' .
                        'an empty string.  Input "' . substr($this->_input,
                        $this->_counter, 5) . '... state CURLY_VAR_INNER');
                }
                next($yymatches); // skip global match
                $this->token = key($yymatches); // token number
                if ($tokenMap[$this->token]) {
                    // extract sub-patterns for passing to lex function
                    $yysubmatches = array_slice($yysubmatches, $this->token + 1,
                        $tokenMap[$this->token]);
                } else {
                    $yysubmatches = array();
                }
                $this->value = current($yymatches); // token value
                $r = $this->{'yy_r6_' . $this->token}($yysubmatches);
                if ($r === null) {
                    $this->_counter += strlen($this->value);
                    $this->line += substr_count($this->value, "\n");
                    // accept this token
                    return true;
                } elseif ($r === true) {
                    // we have changed state
                    // process this token in the new state
                    return $this->yylex();
                } elseif ($r === false) {
                    $this->_counter += strlen($this->value);
                    $this->line += substr_count($this->value, "\n");
                    if ($this->_counter >= strlen($this->_input)) {
                        return false; // end of input
                    }
                    // skip this token
                    continue;
                } else {                    $yy_yymore_patterns = array(
        1 => array(0, "^(\\})|^(\\{\\$)|^([^.{}\"'\s]+)"),
        2 => array(0, "^(\\{\\$)|^([^.{}\"'\s]+)"),
        3 => array(0, "^([^.{}\"'\s]+)"),
        4 => array(0, ""),
    );

                    // yymore is needed
                    do {
                        if (!strlen($yy_yymore_patterns[$this->token][1])) {
                            throw new Exception('cannot do yymore for the last token');
                        }
                        $yysubmatches = array();
                        if (preg_match('/' . $yy_yymore_patterns[$this->token][1] . '/',
                              substr($this->_input, $this->_counter), $yymatches)) {
                            $yysubmatches = $yymatches;
                            $yymatches = array_filter($yymatches, 'strlen'); // remove empty sub-patterns
                            next($yymatches); // skip global match
                            $this->token += key($yymatches) + $yy_yymore_patterns[$this->token][0]; // token number
                            $this->value = current($yymatches); // token value
                            $this->line = substr_count($this->value, "\n");
                            if ($tokenMap[$this->token]) {
                                // extract sub-patterns for passing to lex function
                                $yysubmatches = array_slice($yysubmatches, $this->token + 1,
                                    $tokenMap[$this->token]);
                            } else {
                                $yysubmatches = array();
                            }
                        }
                    	$r = $this->{'yy_r6_' . $this->token}($yysubmatches);
                    } while ($r !== null && !is_bool($r));
			        if ($r === true) {
			            // we have changed state
			            // process this token in the new state
			            return $this->yylex();
                    } elseif ($r === false) {
                        $this->_counter += strlen($this->value);
                        $this->line += substr_count($this->value, "\n");
                        if ($this->_counter >= strlen($this->_input)) {
                            return false; // end of input
                        }
                        // skip this token
                        continue;
			        } else {
	                    // accept
	                    $this->_counter += strlen($this->value);
	                    $this->line += substr_count($this->value, "\n");
	                    return true;
			        }
                }
            } else {
                throw new Exception('Unexpected input at line' . $this->line .
                    ': ' . $this->_input[$this->_counter]);
            }
            break;
        } while (true);

    } // end function


    const CURLY_VAR_INNER = 6;
    function yy_r6_1($yy_subpatterns)
    {

	$this->_addMatch(XenForo_Template_Compiler_Parser::CURLY_ARRAY_DIM);
    }
    function yy_r6_2($yy_subpatterns)
    {

	$this->yypopstate();
	return true;
    }
    function yy_r6_3($yy_subpatterns)
    {

	$this->yypushstate(self::CURLY_START);
	return true;
    }
    function yy_r6_4($yy_subpatterns)
    {

	$this->_addMatch(XenForo_Template_Compiler_Parser::CURLY_VAR_KEY, $this->value);
    }



    function yylex7()
    {
        $tokenMap = array (
              1 => 1,
              3 => 0,
            );
        if ($this->_counter >= strlen($this->_input)) {
            return false; // end of input
        }
        $yy_global_pattern = "/^(\\{xen:([a-zA-Z0-9_]+)[ \n\r\t]+)|^(\\})/";

        do {
            if (preg_match($yy_global_pattern, substr($this->_input, $this->_counter), $yymatches)) {
                $yysubmatches = $yymatches;
                $yymatches = array_filter($yymatches, 'strlen'); // remove empty sub-patterns
                if (!count($yymatches)) {
                    throw new Exception('Error: lexing failed because a rule matched' .
                        'an empty string.  Input "' . substr($this->_input,
                        $this->_counter, 5) . '... state CURLY_FUNCTION_START');
                }
                next($yymatches); // skip global match
                $this->token = key($yymatches); // token number
                if ($tokenMap[$this->token]) {
                    // extract sub-patterns for passing to lex function
                    $yysubmatches = array_slice($yysubmatches, $this->token + 1,
                        $tokenMap[$this->token]);
                } else {
                    $yysubmatches = array();
                }
                $this->value = current($yymatches); // token value
                $r = $this->{'yy_r7_' . $this->token}($yysubmatches);
                if ($r === null) {
                    $this->_counter += strlen($this->value);
                    $this->line += substr_count($this->value, "\n");
                    // accept this token
                    return true;
                } elseif ($r === true) {
                    // we have changed state
                    // process this token in the new state
                    return $this->yylex();
                } elseif ($r === false) {
                    $this->_counter += strlen($this->value);
                    $this->line += substr_count($this->value, "\n");
                    if ($this->_counter >= strlen($this->_input)) {
                        return false; // end of input
                    }
                    // skip this token
                    continue;
                } else {                    $yy_yymore_patterns = array(
        1 => array(0, "^(\\})"),
        3 => array(0, ""),
    );

                    // yymore is needed
                    do {
                        if (!strlen($yy_yymore_patterns[$this->token][1])) {
                            throw new Exception('cannot do yymore for the last token');
                        }
                        $yysubmatches = array();
                        if (preg_match('/' . $yy_yymore_patterns[$this->token][1] . '/',
                              substr($this->_input, $this->_counter), $yymatches)) {
                            $yysubmatches = $yymatches;
                            $yymatches = array_filter($yymatches, 'strlen'); // remove empty sub-patterns
                            next($yymatches); // skip global match
                            $this->token += key($yymatches) + $yy_yymore_patterns[$this->token][0]; // token number
                            $this->value = current($yymatches); // token value
                            $this->line = substr_count($this->value, "\n");
                            if ($tokenMap[$this->token]) {
                                // extract sub-patterns for passing to lex function
                                $yysubmatches = array_slice($yysubmatches, $this->token + 1,
                                    $tokenMap[$this->token]);
                            } else {
                                $yysubmatches = array();
                            }
                        }
                    	$r = $this->{'yy_r7_' . $this->token}($yysubmatches);
                    } while ($r !== null && !is_bool($r));
			        if ($r === true) {
			            // we have changed state
			            // process this token in the new state
			            return $this->yylex();
                    } elseif ($r === false) {
                        $this->_counter += strlen($this->value);
                        $this->line += substr_count($this->value, "\n");
                        if ($this->_counter >= strlen($this->_input)) {
                            return false; // end of input
                        }
                        // skip this token
                        continue;
			        } else {
	                    // accept
	                    $this->_counter += strlen($this->value);
	                    $this->line += substr_count($this->value, "\n");
	                    return true;
			        }
                }
            } else {
                throw new Exception('Unexpected input at line' . $this->line .
                    ': ' . $this->_input[$this->_counter]);
            }
            break;
        } while (true);

    } // end function


    const CURLY_FUNCTION_START = 7;
    function yy_r7_1($yy_subpatterns)
    {

	$this->_addMatch(XenForo_Template_Compiler_Parser::CURLY_FUNCTION, $yy_subpatterns[0]);

	$this->yypushstate(self::CURLY_FUNCTION_INNER);
    }
    function yy_r7_3($yy_subpatterns)
    {

	$this->yypopstate();
	return true;
    }



    function yylex8()
    {
        $tokenMap = array (
              1 => 0,
              2 => 0,
              3 => 0,
              4 => 0,
              5 => 0,
              6 => 0,
              7 => 0,
              8 => 0,
            );
        if ($this->_counter >= strlen($this->_input)) {
            return false; // end of input
        }
        $yy_global_pattern = "/^(\\})|^(\\{)|^([ \n\r\t]+)|^(\\$[a-zA-Z_][a-zA-Z0-9_.-]*)|^([a-zA-Z0-9_$.\/:\-]+)|^(,)|^(\")|^(')/";

        do {
            if (preg_match($yy_global_pattern, substr($this->_input, $this->_counter), $yymatches)) {
                $yysubmatches = $yymatches;
                $yymatches = array_filter($yymatches, 'strlen'); // remove empty sub-patterns
                if (!count($yymatches)) {
                    throw new Exception('Error: lexing failed because a rule matched' .
                        'an empty string.  Input "' . substr($this->_input,
                        $this->_counter, 5) . '... state CURLY_FUNCTION_INNER');
                }
                next($yymatches); // skip global match
                $this->token = key($yymatches); // token number
                if ($tokenMap[$this->token]) {
                    // extract sub-patterns for passing to lex function
                    $yysubmatches = array_slice($yysubmatches, $this->token + 1,
                        $tokenMap[$this->token]);
                } else {
                    $yysubmatches = array();
                }
                $this->value = current($yymatches); // token value
                $r = $this->{'yy_r8_' . $this->token}($yysubmatches);
                if ($r === null) {
                    $this->_counter += strlen($this->value);
                    $this->line += substr_count($this->value, "\n");
                    // accept this token
                    return true;
                } elseif ($r === true) {
                    // we have changed state
                    // process this token in the new state
                    return $this->yylex();
                } elseif ($r === false) {
                    $this->_counter += strlen($this->value);
                    $this->line += substr_count($this->value, "\n");
                    if ($this->_counter >= strlen($this->_input)) {
                        return false; // end of input
                    }
                    // skip this token
                    continue;
                } else {                    $yy_yymore_patterns = array(
        1 => array(0, "^(\\{)|^([ \n\r\t]+)|^(\\$[a-zA-Z_][a-zA-Z0-9_.-]*)|^([a-zA-Z0-9_$.\/:\-]+)|^(,)|^(\")|^(')"),
        2 => array(0, "^([ \n\r\t]+)|^(\\$[a-zA-Z_][a-zA-Z0-9_.-]*)|^([a-zA-Z0-9_$.\/:\-]+)|^(,)|^(\")|^(')"),
        3 => array(0, "^(\\$[a-zA-Z_][a-zA-Z0-9_.-]*)|^([a-zA-Z0-9_$.\/:\-]+)|^(,)|^(\")|^(')"),
        4 => array(0, "^([a-zA-Z0-9_$.\/:\-]+)|^(,)|^(\")|^(')"),
        5 => array(0, "^(,)|^(\")|^(')"),
        6 => array(0, "^(\")|^(')"),
        7 => array(0, "^(')"),
        8 => array(0, ""),
    );

                    // yymore is needed
                    do {
                        if (!strlen($yy_yymore_patterns[$this->token][1])) {
                            throw new Exception('cannot do yymore for the last token');
                        }
                        $yysubmatches = array();
                        if (preg_match('/' . $yy_yymore_patterns[$this->token][1] . '/',
                              substr($this->_input, $this->_counter), $yymatches)) {
                            $yysubmatches = $yymatches;
                            $yymatches = array_filter($yymatches, 'strlen'); // remove empty sub-patterns
                            next($yymatches); // skip global match
                            $this->token += key($yymatches) + $yy_yymore_patterns[$this->token][0]; // token number
                            $this->value = current($yymatches); // token value
                            $this->line = substr_count($this->value, "\n");
                            if ($tokenMap[$this->token]) {
                                // extract sub-patterns for passing to lex function
                                $yysubmatches = array_slice($yysubmatches, $this->token + 1,
                                    $tokenMap[$this->token]);
                            } else {
                                $yysubmatches = array();
                            }
                        }
                    	$r = $this->{'yy_r8_' . $this->token}($yysubmatches);
                    } while ($r !== null && !is_bool($r));
			        if ($r === true) {
			            // we have changed state
			            // process this token in the new state
			            return $this->yylex();
                    } elseif ($r === false) {
                        $this->_counter += strlen($this->value);
                        $this->line += substr_count($this->value, "\n");
                        if ($this->_counter >= strlen($this->_input)) {
                            return false; // end of input
                        }
                        // skip this token
                        continue;
			        } else {
	                    // accept
	                    $this->_counter += strlen($this->value);
	                    $this->line += substr_count($this->value, "\n");
	                    return true;
			        }
                }
            } else {
                throw new Exception('Unexpected input at line' . $this->line .
                    ': ' . $this->_input[$this->_counter]);
            }
            break;
        } while (true);

    } // end function


    const CURLY_FUNCTION_INNER = 8;
    function yy_r8_1($yy_subpatterns)
    {

	$this->yypopstate();
	return true;
    }
    function yy_r8_2($yy_subpatterns)
    {

	$this->yypushstate(self::CURLY_START);
	return true;
    }
    function yy_r8_3($yy_subpatterns)
    {

	return false;
    }
    function yy_r8_4($yy_subpatterns)
    {

	$this->_addMatch(XenForo_Template_Compiler_Parser::SIMPLE_VARIABLE, $this->value);
    }
    function yy_r8_5($yy_subpatterns)
    {

	$this->_addMatch(XenForo_Template_Compiler_Parser::LITERAL, $this->value);
    }
    function yy_r8_6($yy_subpatterns)
    {

	$this->_addMatch(XenForo_Template_Compiler_Parser::CURLY_ARG_SEP);
    }
    function yy_r8_7($yy_subpatterns)
    {

	$this->_addMatch(XenForo_Template_Compiler_Parser::DOUBLE_QUOTE);

	$this->yypushstate(self::DOUBLE_QUOTED);
    }
    function yy_r8_8($yy_subpatterns)
    {

	$this->_addMatch(XenForo_Template_Compiler_Parser::SINGLE_QUOTE);

	$this->yypushstate(self::SINGLE_QUOTED);
    }



    function yylex9()
    {
        $tokenMap = array (
              1 => 0,
              2 => 0,
              3 => 0,
              4 => 0,
              5 => 0,
            );
        if ($this->_counter >= strlen($this->_input)) {
            return false; // end of input
        }
        $yy_global_pattern = "/^(\\{\\$)|^(\\{xen:)|^(\\{)|^(\")|^([^{\"]+)/";

        do {
            if (preg_match($yy_global_pattern, substr($this->_input, $this->_counter), $yymatches)) {
                $yysubmatches = $yymatches;
                $yymatches = array_filter($yymatches, 'strlen'); // remove empty sub-patterns
                if (!count($yymatches)) {
                    throw new Exception('Error: lexing failed because a rule matched' .
                        'an empty string.  Input "' . substr($this->_input,
                        $this->_counter, 5) . '... state DOUBLE_QUOTED');
                }
                next($yymatches); // skip global match
                $this->token = key($yymatches); // token number
                if ($tokenMap[$this->token]) {
                    // extract sub-patterns for passing to lex function
                    $yysubmatches = array_slice($yysubmatches, $this->token + 1,
                        $tokenMap[$this->token]);
                } else {
                    $yysubmatches = array();
                }
                $this->value = current($yymatches); // token value
                $r = $this->{'yy_r9_' . $this->token}($yysubmatches);
                if ($r === null) {
                    $this->_counter += strlen($this->value);
                    $this->line += substr_count($this->value, "\n");
                    // accept this token
                    return true;
                } elseif ($r === true) {
                    // we have changed state
                    // process this token in the new state
                    return $this->yylex();
                } elseif ($r === false) {
                    $this->_counter += strlen($this->value);
                    $this->line += substr_count($this->value, "\n");
                    if ($this->_counter >= strlen($this->_input)) {
                        return false; // end of input
                    }
                    // skip this token
                    continue;
                } else {                    $yy_yymore_patterns = array(
        1 => array(0, "^(\\{xen:)|^(\\{)|^(\")|^([^{\"]+)"),
        2 => array(0, "^(\\{)|^(\")|^([^{\"]+)"),
        3 => array(0, "^(\")|^([^{\"]+)"),
        4 => array(0, "^([^{\"]+)"),
        5 => array(0, ""),
    );

                    // yymore is needed
                    do {
                        if (!strlen($yy_yymore_patterns[$this->token][1])) {
                            throw new Exception('cannot do yymore for the last token');
                        }
                        $yysubmatches = array();
                        if (preg_match('/' . $yy_yymore_patterns[$this->token][1] . '/',
                              substr($this->_input, $this->_counter), $yymatches)) {
                            $yysubmatches = $yymatches;
                            $yymatches = array_filter($yymatches, 'strlen'); // remove empty sub-patterns
                            next($yymatches); // skip global match
                            $this->token += key($yymatches) + $yy_yymore_patterns[$this->token][0]; // token number
                            $this->value = current($yymatches); // token value
                            $this->line = substr_count($this->value, "\n");
                            if ($tokenMap[$this->token]) {
                                // extract sub-patterns for passing to lex function
                                $yysubmatches = array_slice($yysubmatches, $this->token + 1,
                                    $tokenMap[$this->token]);
                            } else {
                                $yysubmatches = array();
                            }
                        }
                    	$r = $this->{'yy_r9_' . $this->token}($yysubmatches);
                    } while ($r !== null && !is_bool($r));
			        if ($r === true) {
			            // we have changed state
			            // process this token in the new state
			            return $this->yylex();
                    } elseif ($r === false) {
                        $this->_counter += strlen($this->value);
                        $this->line += substr_count($this->value, "\n");
                        if ($this->_counter >= strlen($this->_input)) {
                            return false; // end of input
                        }
                        // skip this token
                        continue;
			        } else {
	                    // accept
	                    $this->_counter += strlen($this->value);
	                    $this->line += substr_count($this->value, "\n");
	                    return true;
			        }
                }
            } else {
                throw new Exception('Unexpected input at line' . $this->line .
                    ': ' . $this->_input[$this->_counter]);
            }
            break;
        } while (true);

    } // end function


    const DOUBLE_QUOTED = 9;
    function yy_r9_1($yy_subpatterns)
    {

	$this->yypushstate(self::CURLY_START);
	return true;
    }
    function yy_r9_2($yy_subpatterns)
    {

	$this->yypushstate(self::CURLY_START);
	return true;
    }
    function yy_r9_3($yy_subpatterns)
    {

	$this->_addMatch(XenForo_Template_Compiler_Parser::LITERAL, $this->value);
    }
    function yy_r9_4($yy_subpatterns)
    {

	$this->_addMatch(XenForo_Template_Compiler_Parser::DOUBLE_QUOTE);

	$this->yypopstate();
    }
    function yy_r9_5($yy_subpatterns)
    {

	$this->_addMatch(XenForo_Template_Compiler_Parser::LITERAL, $this->value);
    }



    function yylex10()
    {
        $tokenMap = array (
              1 => 0,
              2 => 0,
              3 => 0,
              4 => 0,
              5 => 0,
            );
        if ($this->_counter >= strlen($this->_input)) {
            return false; // end of input
        }
        $yy_global_pattern = "/^(\\{\\$)|^(\\{xen:)|^(\\{)|^(')|^([^{']+)/";

        do {
            if (preg_match($yy_global_pattern, substr($this->_input, $this->_counter), $yymatches)) {
                $yysubmatches = $yymatches;
                $yymatches = array_filter($yymatches, 'strlen'); // remove empty sub-patterns
                if (!count($yymatches)) {
                    throw new Exception('Error: lexing failed because a rule matched' .
                        'an empty string.  Input "' . substr($this->_input,
                        $this->_counter, 5) . '... state SINGLE_QUOTED');
                }
                next($yymatches); // skip global match
                $this->token = key($yymatches); // token number
                if ($tokenMap[$this->token]) {
                    // extract sub-patterns for passing to lex function
                    $yysubmatches = array_slice($yysubmatches, $this->token + 1,
                        $tokenMap[$this->token]);
                } else {
                    $yysubmatches = array();
                }
                $this->value = current($yymatches); // token value
                $r = $this->{'yy_r10_' . $this->token}($yysubmatches);
                if ($r === null) {
                    $this->_counter += strlen($this->value);
                    $this->line += substr_count($this->value, "\n");
                    // accept this token
                    return true;
                } elseif ($r === true) {
                    // we have changed state
                    // process this token in the new state
                    return $this->yylex();
                } elseif ($r === false) {
                    $this->_counter += strlen($this->value);
                    $this->line += substr_count($this->value, "\n");
                    if ($this->_counter >= strlen($this->_input)) {
                        return false; // end of input
                    }
                    // skip this token
                    continue;
                } else {                    $yy_yymore_patterns = array(
        1 => array(0, "^(\\{xen:)|^(\\{)|^(')|^([^{']+)"),
        2 => array(0, "^(\\{)|^(')|^([^{']+)"),
        3 => array(0, "^(')|^([^{']+)"),
        4 => array(0, "^([^{']+)"),
        5 => array(0, ""),
    );

                    // yymore is needed
                    do {
                        if (!strlen($yy_yymore_patterns[$this->token][1])) {
                            throw new Exception('cannot do yymore for the last token');
                        }
                        $yysubmatches = array();
                        if (preg_match('/' . $yy_yymore_patterns[$this->token][1] . '/',
                              substr($this->_input, $this->_counter), $yymatches)) {
                            $yysubmatches = $yymatches;
                            $yymatches = array_filter($yymatches, 'strlen'); // remove empty sub-patterns
                            next($yymatches); // skip global match
                            $this->token += key($yymatches) + $yy_yymore_patterns[$this->token][0]; // token number
                            $this->value = current($yymatches); // token value
                            $this->line = substr_count($this->value, "\n");
                            if ($tokenMap[$this->token]) {
                                // extract sub-patterns for passing to lex function
                                $yysubmatches = array_slice($yysubmatches, $this->token + 1,
                                    $tokenMap[$this->token]);
                            } else {
                                $yysubmatches = array();
                            }
                        }
                    	$r = $this->{'yy_r10_' . $this->token}($yysubmatches);
                    } while ($r !== null && !is_bool($r));
			        if ($r === true) {
			            // we have changed state
			            // process this token in the new state
			            return $this->yylex();
                    } elseif ($r === false) {
                        $this->_counter += strlen($this->value);
                        $this->line += substr_count($this->value, "\n");
                        if ($this->_counter >= strlen($this->_input)) {
                            return false; // end of input
                        }
                        // skip this token
                        continue;
			        } else {
	                    // accept
	                    $this->_counter += strlen($this->value);
	                    $this->line += substr_count($this->value, "\n");
	                    return true;
			        }
                }
            } else {
                throw new Exception('Unexpected input at line' . $this->line .
                    ': ' . $this->_input[$this->_counter]);
            }
            break;
        } while (true);

    } // end function


    const SINGLE_QUOTED = 10;
    function yy_r10_1($yy_subpatterns)
    {

	$this->yypushstate(self::CURLY_START);
	return true;
    }
    function yy_r10_2($yy_subpatterns)
    {

	$this->yypushstate(self::CURLY_START);
	return true;
    }
    function yy_r10_3($yy_subpatterns)
    {

	$this->_addMatch(XenForo_Template_Compiler_Parser::LITERAL, $this->value);
    }
    function yy_r10_4($yy_subpatterns)
    {

	$this->_addMatch(XenForo_Template_Compiler_Parser::SINGLE_QUOTE);

	$this->yypopstate();
    }
    function yy_r10_5($yy_subpatterns)
    {

	$this->_addMatch(XenForo_Template_Compiler_Parser::LITERAL, $this->value);
    }


}

