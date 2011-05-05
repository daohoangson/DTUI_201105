<?php
/* Driver template for the PHP_XenForo_Template_Compiler_Parser_rGenerator parser generator. (PHP port of LEMON)
*/

/**
 * This can be used to store both the string representation of
 * a token, and any useful meta-data associated with the token.
 *
 * meta-data should be stored as an array
 */
class XenForo_Template_Compiler_Parser_yyToken implements ArrayAccess
{
    public $string = '';
    public $metadata = array();

    function __construct($s, $m = array())
    {
        if ($s instanceof XenForo_Template_Compiler_Parser_yyToken) {
            $this->string = $s->string;
            $this->metadata = $s->metadata;
        } else {
            $this->string = (string) $s;
            if ($m instanceof XenForo_Template_Compiler_Parser_yyToken) {
                $this->metadata = $m->metadata;
            } elseif (is_array($m)) {
                $this->metadata = $m;
            }
        }
    }

    function __toString()
    {
        return $this->_string;
    }

    function offsetExists($offset)
    {
        return isset($this->metadata[$offset]);
    }

    function offsetGet($offset)
    {
        return $this->metadata[$offset];
    }

    function offsetSet($offset, $value)
    {
        if ($offset === null) {
            if (isset($value[0])) {
                $x = ($value instanceof XenForo_Template_Compiler_Parser_yyToken) ?
                    $value->metadata : $value;
                $this->metadata = array_merge($this->metadata, $x);
                return;
            }
            $offset = count($this->metadata);
        }
        if ($value === null) {
            return;
        }
        if ($value instanceof XenForo_Template_Compiler_Parser_yyToken) {
            if ($value->metadata) {
                $this->metadata[$offset] = $value->metadata;
            }
        } elseif ($value) {
            $this->metadata[$offset] = $value;
        }
    }

    function offsetUnset($offset)
    {
        unset($this->metadata[$offset]);
    }
}

/** The following structure represents a single element of the
 * parser's stack.  Information stored includes:
 *
 *   +  The state number for the parser at this level of the stack.
 *
 *   +  The value of the token stored at this level of the stack.
 *      (In other words, the "major" token.)
 *
 *   +  The semantic value stored at this level of the stack.  This is
 *      the information used by the action routines in the grammar.
 *      It is sometimes called the "minor" token.
 */
class XenForo_Template_Compiler_Parser_yyStackEntry
{
    public $stateno;       /* The state-number */
    public $major;         /* The major token value.  This is the code
                     ** number for the token at this stack level */
    public $minor; /* The user-supplied minor token value.  This
                     ** is the value of the token  */
};

// code external to the class is included here

// declare_class is output here
#line 2 "Parser.y"
class XenForo_Template_Compiler_Parser #line 102 "Parser.php"
{
/* First off, code is included which follows the "include_class" declaration
** in the input file. */
#line 4 "Parser.y"

	protected $_context = array(0 => array());
	protected $_contextKey = 0;
	protected $_tagList = array();
	protected $_lastPlainText = false;

	protected $_lineNumber = 0;

	public function setLineNumber($lineNumber)
	{
		$this->_lineNumber = intval($lineNumber);
	}

	protected function _getSimpleVariable($var)
	{
		$parts = explode('.', substr($var, 1));

		$name = array_shift($parts);
		foreach ($parts AS $key => $part)
		{
			if ($part === '')
			{
				unset($parts[$key]);
			}
		}

		return array(
			'type' => 'CURLY_VAR',
			'name' => $name,
			'keys' => array_values($parts),
			'line' => $this->_lineNumber
		);
	}

	protected function _addOp($op)
	{
		if ($this->_lastPlainText && is_string($op) && count($this->_context[$this->_contextKey]))
		{
			$keys = array_keys($this->_context[$this->_contextKey]);
			$lastKey = end($keys);

			$this->_context[$this->_contextKey][$lastKey] .= $op;
		}
		else
		{
			$this->_context[$this->_contextKey][] = $op;
			$this->_lastPlainText = is_string($op);
		}
	}

	protected function _pushTagContext($tag)
	{
		$this->_tagList[] = $tag;

		$this->_contextKey++;
		$this->_context[$this->_contextKey] = array();
	}

	protected function _popTagContext($tag)
	{
		if (empty($this->_tagList))
		{
			throw new XenForo_Template_Compiler_Exception(
				new XenForo_Phrase('template_tags_not_well_formed_none_expected', array('tag' => $tag)), true
			);
		}

		$expectedTag = array_pop($this->_tagList);
		if ($expectedTag != $tag)
		{
			throw new XenForo_Template_Compiler_Exception(
				new XenForo_Phrase('template_tags_not_well_formed_expected', array('tag' => $tag, 'expected' => $expectedTag)), true
			);
		}
		else
		{
			$innerContext = $this->_context[$this->_contextKey];
			unset($this->_context[$this->_contextKey]);

			$this->_contextKey--;
			$keys = array_keys($this->_context[$this->_contextKey]);
			$key = end($keys);
			$this->_context[$this->_contextKey][$key]['children'] = $innerContext;
		}

		$this->_lastPlainText = false;
	}

	public function getOutput()
	{
		if ($this->_contextKey != 0)
		{
			$expectedTag = array_pop($this->_tagList);
			throw new XenForo_Template_Compiler_Exception(new XenForo_Phrase('template_tags_not_well_formed_not_closed', array('tag' => $expectedTag)), true);
		}

		return $this->_context[0];
	}
#line 206 "Parser.php"

/* Next is all token values, as class constants
*/
/* 
** These constants (all generated automatically by the parser generator)
** specify the various kinds of tokens (terminals) that the parser
** understands. 
**
** Each symbol here is a terminal symbol in the grammar.
*/
    const PLAIN_TEXT                     =  1;
    const TAG_COMMENT                    =  2;
    const TAG_CLOSE                      =  3;
    const TAG_OPEN                       =  4;
    const TAG_SELF_CLOSE                 =  5;
    const TAG_END                        =  6;
    const TAG_ATTRIBUTE                  =  7;
    const DOUBLE_QUOTE                   =  8;
    const SINGLE_QUOTE                   =  9;
    const CURLY_VAR                      = 10;
    const CURLY_END                      = 11;
    const CURLY_ARRAY_DIM                = 12;
    const CURLY_VAR_KEY                  = 13;
    const CURLY_FUNCTION                 = 14;
    const SIMPLE_VARIABLE                = 15;
    const LITERAL                        = 16;
    const CURLY_ARG_SEP                  = 17;
    const YY_NO_ACTION = 78;
    const YY_ACCEPT_ACTION = 77;
    const YY_ERROR_ACTION = 76;

/* Next are that tables used to determine what action to take based on the
** current state and lookahead token.  These tables are used to implement
** functions that take a state number and lookahead value and return an
** action integer.  
**
** Suppose the action integer is N.  Then the action is determined as
** follows
**
**   0 <= N < self::YYNSTATE                              Shift N.  That is,
**                                                        push the lookahead
**                                                        token onto the stack
**                                                        and goto state N.
**
**   self::YYNSTATE <= N < self::YYNSTATE+self::YYNRULE   Reduce by rule N-YYNSTATE.
**
**   N == self::YYNSTATE+self::YYNRULE                    A syntax error has occurred.
**
**   N == self::YYNSTATE+self::YYNRULE+1                  The parser accepts its
**                                                        input. (and concludes parsing)
**
**   N == self::YYNSTATE+self::YYNRULE+2                  No such action.  Denotes unused
**                                                        slots in the yy_action[] table.
**
** The action table is constructed as a single large static array $yy_action.
** Given state S and lookahead X, the action is computed as
**
**      self::$yy_action[self::$yy_shift_ofst[S] + X ]
**
** If the index value self::$yy_shift_ofst[S]+X is out of range or if the value
** self::$yy_lookahead[self::$yy_shift_ofst[S]+X] is not equal to X or if
** self::$yy_shift_ofst[S] is equal to self::YY_SHIFT_USE_DFLT, it means that
** the action is not in the table and that self::$yy_default[S] should be used instead.  
**
** The formula above is for computing the action when the lookahead is
** a terminal symbol.  If the lookahead is a non-terminal (as occurs after
** a reduce action) then the static $yy_reduce_ofst array is used in place of
** the static $yy_shift_ofst array and self::YY_REDUCE_USE_DFLT is used in place of
** self::YY_SHIFT_USE_DFLT.
**
** The following are the tables generated in this section:
**
**  self::$yy_action        A single table containing all actions.
**  self::$yy_lookahead     A table containing the lookahead for each entry in
**                          yy_action.  Used to detect hash collisions.
**  self::$yy_shift_ofst    For each state, the offset into self::$yy_action for
**                          shifting terminals.
**  self::$yy_reduce_ofst   For each state, the offset into self::$yy_action for
**                          shifting non-terminals after a reduce.
**  self::$yy_default       Default action for each state.
*/
    const YY_SZ_ACTTAB = 76;
static public $yy_action = array(
 /*     0 */     9,   12,   11,    4,   16,   21,    1,   39,   44,   42,
 /*    10 */    43,   27,   28,   11,   26,   20,   21,    1,   38,   22,
 /*    20 */    42,   43,    6,   10,    2,   26,   20,   34,   32,   30,
 /*    30 */    15,   19,   31,   36,   18,    7,   11,   23,   11,   29,
 /*    40 */     1,    5,    1,   17,   22,   25,   24,   11,   65,   42,
 /*    50 */    43,    1,   65,   22,   65,   37,   65,   11,   77,    3,
 /*    60 */    65,    1,   65,   22,   35,   33,   13,   14,   42,   43,
 /*    70 */    41,    8,   11,   65,   65,   40,
    );
    static public $yy_lookahead = array(
 /*     0 */     8,    9,   10,   24,   23,   21,   14,   15,   16,   25,
 /*    10 */    26,    8,   28,   10,   30,   31,   21,   14,   11,   16,
 /*    20 */    25,   26,   24,   28,   17,   30,   31,    1,    2,    3,
 /*    30 */     4,   29,    5,    6,    7,   24,   10,    9,   10,   25,
 /*    40 */    14,   24,   14,   27,   16,   21,    9,   10,   32,   25,
 /*    50 */    26,   14,   32,   16,   32,    8,   32,   10,   19,   20,
 /*    60 */    32,   14,   32,   16,   21,   22,    8,    9,   25,   26,
 /*    70 */    11,   12,   10,   32,   32,   13,
);
    const YY_SHIFT_USE_DFLT = -9;
    const YY_SHIFT_MAX = 19;
    static public $yy_shift_ofst = array(
 /*     0 */    -9,   -8,   -8,   26,   47,    3,   28,   37,   62,   -9,
 /*    10 */    -9,   -9,   -9,   -9,   -9,   -9,   27,   59,   58,    7,
);
    const YY_REDUCE_USE_DFLT = -22;
    const YY_REDUCE_MAX = 15;
    static public $yy_reduce_ofst = array(
 /*     0 */    39,   -5,  -16,   43,   24,   24,   24,   24,   14,   17,
 /*    10 */     2,   16,   -2,  -21,   11,  -19,
);
    static public $yyExpectedTokens = array(
        /* 0 */ array(),
        /* 1 */ array(8, 9, 10, 14, 15, 16, ),
        /* 2 */ array(8, 9, 10, 14, 15, 16, ),
        /* 3 */ array(1, 2, 3, 4, 10, 14, ),
        /* 4 */ array(8, 10, 14, 16, ),
        /* 5 */ array(8, 10, 14, 16, ),
        /* 6 */ array(9, 10, 14, 16, ),
        /* 7 */ array(9, 10, 14, 16, ),
        /* 8 */ array(10, 13, ),
        /* 9 */ array(),
        /* 10 */ array(),
        /* 11 */ array(),
        /* 12 */ array(),
        /* 13 */ array(),
        /* 14 */ array(),
        /* 15 */ array(),
        /* 16 */ array(5, 6, 7, ),
        /* 17 */ array(11, 12, ),
        /* 18 */ array(8, 9, ),
        /* 19 */ array(11, 17, ),
        /* 20 */ array(),
        /* 21 */ array(),
        /* 22 */ array(),
        /* 23 */ array(),
        /* 24 */ array(),
        /* 25 */ array(),
        /* 26 */ array(),
        /* 27 */ array(),
        /* 28 */ array(),
        /* 29 */ array(),
        /* 30 */ array(),
        /* 31 */ array(),
        /* 32 */ array(),
        /* 33 */ array(),
        /* 34 */ array(),
        /* 35 */ array(),
        /* 36 */ array(),
        /* 37 */ array(),
        /* 38 */ array(),
        /* 39 */ array(),
        /* 40 */ array(),
        /* 41 */ array(),
        /* 42 */ array(),
        /* 43 */ array(),
        /* 44 */ array(),
);
    static public $yy_default = array(
 /*     0 */    51,   76,   76,   45,   76,   76,   76,   76,   76,   75,
 /*    10 */    70,   62,   75,   75,   75,   56,   76,   76,   76,   76,
 /*    20 */    67,   68,   73,   72,   55,   74,   66,   71,   69,   61,
 /*    30 */    50,   52,   49,   48,   46,   47,   53,   54,   63,   64,
 /*    40 */    60,   59,   57,   58,   65,
);
/* The next thing included is series of defines which control
** various aspects of the generated parser.
**    self::YYNOCODE      is a number which corresponds
**                        to no legal terminal or nonterminal number.  This
**                        number is used to fill in empty slots of the hash 
**                        table.
**    self::YYFALLBACK    If defined, this indicates that one or more tokens
**                        have fall-back values which should be used if the
**                        original value of the token will not parse.
**    self::YYSTACKDEPTH  is the maximum depth of the parser's stack.
**    self::YYNSTATE      the combined number of states.
**    self::YYNRULE       the number of rules in the grammar
**    self::YYERRORSYMBOL is the code number of the error symbol.  If not
**                        defined, then do no error processing.
*/
    const YYNOCODE = 33;
    const YYSTACKDEPTH = 100;
    const YYNSTATE = 45;
    const YYNRULE = 31;
    const YYERRORSYMBOL = 18;
    const YYERRSYMDT = 'yy0';
    const YYFALLBACK = 0;
    /** The next table maps tokens into fallback tokens.  If a construct
     * like the following:
     * 
     *      %fallback ID X Y Z.
     *
     * appears in the grammer, then ID becomes a fallback token for X, Y,
     * and Z.  Whenever one of the tokens X, Y, or Z is input to the parser
     * but it does not parse, the type of the token is changed to ID and
     * the parse is retried before an error is thrown.
     */
    static public $yyFallback = array(
    );
    /**
     * Turn parser tracing on by giving a stream to which to write the trace
     * and a prompt to preface each trace message.  Tracing is turned off
     * by making either argument NULL 
     *
     * Inputs:
     * 
     * - A stream resource to which trace output should be written.
     *   If NULL, then tracing is turned off.
     * - A prefix string written at the beginning of every
     *   line of trace output.  If NULL, then tracing is
     *   turned off.
     *
     * Outputs:
     * 
     * - None.
     * @param resource
     * @param string
     */
    static function Trace($TraceFILE, $zTracePrompt)
    {
        if (!$TraceFILE) {
            $zTracePrompt = 0;
        } elseif (!$zTracePrompt) {
            $TraceFILE = 0;
        }
        self::$yyTraceFILE = $TraceFILE;
        self::$yyTracePrompt = $zTracePrompt;
    }

    /**
     * Output debug information to output (php://output stream)
     */
    static function PrintTrace()
    {
        self::$yyTraceFILE = fopen('php://output', 'w');
        self::$yyTracePrompt = '';
    }

    /**
     * @var resource|0
     */
    static public $yyTraceFILE;
    /**
     * String to prepend to debug output
     * @var string|0
     */
    static public $yyTracePrompt;
    /**
     * @var int
     */
    public $yyidx;                    /* Index of top element in stack */
    /**
     * @var int
     */
    public $yyerrcnt;                 /* Shifts left before out of the error */
    /**
     * @var array
     */
    public $yystack = array();  /* The parser's stack */

    /**
     * For tracing shifts, the names of all terminals and nonterminals
     * are required.  The following table supplies these names
     * @var array
     */
    static public $yyTokenName = array( 
  '$',             'PLAIN_TEXT',    'TAG_COMMENT',   'TAG_CLOSE',   
  'TAG_OPEN',      'TAG_SELF_CLOSE',  'TAG_END',       'TAG_ATTRIBUTE',
  'DOUBLE_QUOTE',  'SINGLE_QUOTE',  'CURLY_VAR',     'CURLY_END',   
  'CURLY_ARRAY_DIM',  'CURLY_VAR_KEY',  'CURLY_FUNCTION',  'SIMPLE_VARIABLE',
  'LITERAL',       'CURLY_ARG_SEP',  'error',         'start',       
  'in',            'curly',         'tag',           'tag_attributes',
  'quoted_inner',  'curly_var',     'curly_function',  'curly_var_inner',
  'curly_function_argument',  'curly_function_extra_args',  'double_quoted',  'single_quoted',
    );

    /**
     * For tracing reduce actions, the names of all rules are required.
     * @var array
     */
    static public $yyRuleName = array(
 /*   0 */ "start ::= in",
 /*   1 */ "in ::= in PLAIN_TEXT",
 /*   2 */ "in ::= in curly",
 /*   3 */ "in ::= in tag",
 /*   4 */ "in ::= in TAG_COMMENT",
 /*   5 */ "in ::= in TAG_CLOSE",
 /*   6 */ "in ::=",
 /*   7 */ "tag ::= TAG_OPEN tag_attributes TAG_SELF_CLOSE",
 /*   8 */ "tag ::= TAG_OPEN tag_attributes TAG_END",
 /*   9 */ "tag_attributes ::= tag_attributes TAG_ATTRIBUTE DOUBLE_QUOTE quoted_inner DOUBLE_QUOTE",
 /*  10 */ "tag_attributes ::= tag_attributes TAG_ATTRIBUTE SINGLE_QUOTE quoted_inner SINGLE_QUOTE",
 /*  11 */ "tag_attributes ::=",
 /*  12 */ "curly ::= curly_var",
 /*  13 */ "curly ::= curly_function",
 /*  14 */ "curly_var ::= CURLY_VAR curly_var_inner CURLY_END",
 /*  15 */ "curly_var_inner ::= curly_var_inner CURLY_ARRAY_DIM CURLY_VAR_KEY",
 /*  16 */ "curly_var_inner ::= curly_var_inner CURLY_ARRAY_DIM curly_var",
 /*  17 */ "curly_var_inner ::=",
 /*  18 */ "curly_function ::= CURLY_FUNCTION curly_function_argument curly_function_extra_args CURLY_END",
 /*  19 */ "curly_function_argument ::= SIMPLE_VARIABLE",
 /*  20 */ "curly_function_argument ::= LITERAL",
 /*  21 */ "curly_function_argument ::= double_quoted",
 /*  22 */ "curly_function_argument ::= single_quoted",
 /*  23 */ "curly_function_argument ::= curly",
 /*  24 */ "curly_function_extra_args ::= curly_function_extra_args CURLY_ARG_SEP curly_function_argument",
 /*  25 */ "curly_function_extra_args ::=",
 /*  26 */ "double_quoted ::= DOUBLE_QUOTE quoted_inner DOUBLE_QUOTE",
 /*  27 */ "single_quoted ::= SINGLE_QUOTE quoted_inner SINGLE_QUOTE",
 /*  28 */ "quoted_inner ::= quoted_inner LITERAL",
 /*  29 */ "quoted_inner ::= quoted_inner curly",
 /*  30 */ "quoted_inner ::=",
    );

    /**
     * This function returns the symbolic name associated with a token
     * value.
     * @param int
     * @return string
     */
    function tokenName($tokenType)
    {
        if ($tokenType === 0) {
            return 'End of Input';
        }
        if ($tokenType > 0 && $tokenType < count(self::$yyTokenName)) {
            return self::$yyTokenName[$tokenType];
        } else {
            return "Unknown";
        }
    }

    /**
     * The following function deletes the value associated with a
     * symbol.  The symbol can be either a terminal or nonterminal.
     * @param int the symbol code
     * @param mixed the symbol's value
     */
    static function yy_destructor($yymajor, $yypminor)
    {
        switch ($yymajor) {
        /* Here is inserted the actions which take place when a
        ** terminal or non-terminal is destroyed.  This can happen
        ** when the symbol is popped from the stack during a
        ** reduce or during error processing or when a parser is 
        ** being destroyed before it is finished parsing.
        **
        ** Note: during a reduce, the only symbols destroyed are those
        ** which appear on the RHS of the rule, but which are not used
        ** inside the C code.
        */
            default:  break;   /* If no destructor action specified: do nothing */
        }
    }

    /**
     * Pop the parser's stack once.
     *
     * If there is a destructor routine associated with the token which
     * is popped from the stack, then call it.
     *
     * Return the major token number for the symbol popped.
     * @param XenForo_Template_Compiler_Parser_yyParser
     * @return int
     */
    function yy_pop_parser_stack()
    {
        if (!count($this->yystack)) {
            return;
        }
        $yytos = array_pop($this->yystack);
        if (self::$yyTraceFILE && $this->yyidx >= 0) {
            fwrite(self::$yyTraceFILE,
                self::$yyTracePrompt . 'Popping ' . self::$yyTokenName[$yytos->major] .
                    "\n");
        }
        $yymajor = $yytos->major;
        self::yy_destructor($yymajor, $yytos->minor);
        $this->yyidx--;
        return $yymajor;
    }

    /**
     * Deallocate and destroy a parser.  Destructors are all called for
     * all stack elements before shutting the parser down.
     */
    function __destruct()
    {
        while ($this->yyidx >= 0) {
            $this->yy_pop_parser_stack();
        }
        if (is_resource(self::$yyTraceFILE)) {
            fclose(self::$yyTraceFILE);
        }
    }

    /**
     * Based on the current state and parser stack, get a list of all
     * possible lookahead tokens
     * @param int
     * @return array
     */
    function yy_get_expected_tokens($token)
    {
        $state = $this->yystack[$this->yyidx]->stateno;
        $expected = self::$yyExpectedTokens[$state];
        if (in_array($token, self::$yyExpectedTokens[$state], true)) {
            return $expected;
        }
        $stack = $this->yystack;
        $yyidx = $this->yyidx;
        do {
            $yyact = $this->yy_find_shift_action($token);
            if ($yyact >= self::YYNSTATE && $yyact < self::YYNSTATE + self::YYNRULE) {
                // reduce action
                $done = 0;
                do {
                    if ($done++ == 100) {
                        $this->yyidx = $yyidx;
                        $this->yystack = $stack;
                        // too much recursion prevents proper detection
                        // so give up
                        return array_unique($expected);
                    }
                    $yyruleno = $yyact - self::YYNSTATE;
                    $this->yyidx -= self::$yyRuleInfo[$yyruleno]['rhs'];
                    $nextstate = $this->yy_find_reduce_action(
                        $this->yystack[$this->yyidx]->stateno,
                        self::$yyRuleInfo[$yyruleno]['lhs']);
                    if (isset(self::$yyExpectedTokens[$nextstate])) {
                        $expected += self::$yyExpectedTokens[$nextstate];
                            if (in_array($token,
                                  self::$yyExpectedTokens[$nextstate], true)) {
                            $this->yyidx = $yyidx;
                            $this->yystack = $stack;
                            return array_unique($expected);
                        }
                    }
                    if ($nextstate < self::YYNSTATE) {
                        // we need to shift a non-terminal
                        $this->yyidx++;
                        $x = new XenForo_Template_Compiler_Parser_yyStackEntry;
                        $x->stateno = $nextstate;
                        $x->major = self::$yyRuleInfo[$yyruleno]['lhs'];
                        $this->yystack[$this->yyidx] = $x;
                        continue 2;
                    } elseif ($nextstate == self::YYNSTATE + self::YYNRULE + 1) {
                        $this->yyidx = $yyidx;
                        $this->yystack = $stack;
                        // the last token was just ignored, we can't accept
                        // by ignoring input, this is in essence ignoring a
                        // syntax error!
                        return array_unique($expected);
                    } elseif ($nextstate === self::YY_NO_ACTION) {
                        $this->yyidx = $yyidx;
                        $this->yystack = $stack;
                        // input accepted, but not shifted (I guess)
                        return $expected;
                    } else {
                        $yyact = $nextstate;
                    }
                } while (true);
            }
            break;
        } while (true);
        return array_unique($expected);
    }

    /**
     * Based on the parser state and current parser stack, determine whether
     * the lookahead token is possible.
     * 
     * The parser will convert the token value to an error token if not.  This
     * catches some unusual edge cases where the parser would fail.
     * @param int
     * @return bool
     */
    function yy_is_expected_token($token)
    {
        if ($token === 0) {
            return true; // 0 is not part of this
        }
        $state = $this->yystack[$this->yyidx]->stateno;
        if (in_array($token, self::$yyExpectedTokens[$state], true)) {
            return true;
        }
        $stack = $this->yystack;
        $yyidx = $this->yyidx;
        do {
            $yyact = $this->yy_find_shift_action($token);
            if ($yyact >= self::YYNSTATE && $yyact < self::YYNSTATE + self::YYNRULE) {
                // reduce action
                $done = 0;
                do {
                    if ($done++ == 100) {
                        $this->yyidx = $yyidx;
                        $this->yystack = $stack;
                        // too much recursion prevents proper detection
                        // so give up
                        return true;
                    }
                    $yyruleno = $yyact - self::YYNSTATE;
                    $this->yyidx -= self::$yyRuleInfo[$yyruleno]['rhs'];
                    $nextstate = $this->yy_find_reduce_action(
                        $this->yystack[$this->yyidx]->stateno,
                        self::$yyRuleInfo[$yyruleno]['lhs']);
                    if (isset(self::$yyExpectedTokens[$nextstate]) &&
                          in_array($token, self::$yyExpectedTokens[$nextstate], true)) {
                        $this->yyidx = $yyidx;
                        $this->yystack = $stack;
                        return true;
                    }
                    if ($nextstate < self::YYNSTATE) {
                        // we need to shift a non-terminal
                        $this->yyidx++;
                        $x = new XenForo_Template_Compiler_Parser_yyStackEntry;
                        $x->stateno = $nextstate;
                        $x->major = self::$yyRuleInfo[$yyruleno]['lhs'];
                        $this->yystack[$this->yyidx] = $x;
                        continue 2;
                    } elseif ($nextstate == self::YYNSTATE + self::YYNRULE + 1) {
                        $this->yyidx = $yyidx;
                        $this->yystack = $stack;
                        if (!$token) {
                            // end of input: this is valid
                            return true;
                        }
                        // the last token was just ignored, we can't accept
                        // by ignoring input, this is in essence ignoring a
                        // syntax error!
                        return false;
                    } elseif ($nextstate === self::YY_NO_ACTION) {
                        $this->yyidx = $yyidx;
                        $this->yystack = $stack;
                        // input accepted, but not shifted (I guess)
                        return true;
                    } else {
                        $yyact = $nextstate;
                    }
                } while (true);
            }
            break;
        } while (true);
        $this->yyidx = $yyidx;
        $this->yystack = $stack;
        return true;
    }

    /**
     * Find the appropriate action for a parser given the terminal
     * look-ahead token iLookAhead.
     *
     * If the look-ahead token is YYNOCODE, then check to see if the action is
     * independent of the look-ahead.  If it is, return the action, otherwise
     * return YY_NO_ACTION.
     * @param int The look-ahead token
     */
    function yy_find_shift_action($iLookAhead)
    {
        $stateno = $this->yystack[$this->yyidx]->stateno;
     
        /* if ($this->yyidx < 0) return self::YY_NO_ACTION;  */
        if (!isset(self::$yy_shift_ofst[$stateno])) {
            // no shift actions
            return self::$yy_default[$stateno];
        }
        $i = self::$yy_shift_ofst[$stateno];
        if ($i === self::YY_SHIFT_USE_DFLT) {
            return self::$yy_default[$stateno];
        }
        if ($iLookAhead == self::YYNOCODE) {
            return self::YY_NO_ACTION;
        }
        $i += $iLookAhead;
        if ($i < 0 || $i >= self::YY_SZ_ACTTAB ||
              self::$yy_lookahead[$i] != $iLookAhead) {
            if (count(self::$yyFallback) && $iLookAhead < count(self::$yyFallback)
                   && ($iFallback = self::$yyFallback[$iLookAhead]) != 0) {
                if (self::$yyTraceFILE) {
                    fwrite(self::$yyTraceFILE, self::$yyTracePrompt . "FALLBACK " .
                        self::$yyTokenName[$iLookAhead] . " => " .
                        self::$yyTokenName[$iFallback] . "\n");
                }
                return $this->yy_find_shift_action($iFallback);
            }
            return self::$yy_default[$stateno];
        } else {
            return self::$yy_action[$i];
        }
    }

    /**
     * Find the appropriate action for a parser given the non-terminal
     * look-ahead token $iLookAhead.
     *
     * If the look-ahead token is self::YYNOCODE, then check to see if the action is
     * independent of the look-ahead.  If it is, return the action, otherwise
     * return self::YY_NO_ACTION.
     * @param int Current state number
     * @param int The look-ahead token
     */
    function yy_find_reduce_action($stateno, $iLookAhead)
    {
        /* $stateno = $this->yystack[$this->yyidx]->stateno; */

        if (!isset(self::$yy_reduce_ofst[$stateno])) {
            return self::$yy_default[$stateno];
        }
        $i = self::$yy_reduce_ofst[$stateno];
        if ($i == self::YY_REDUCE_USE_DFLT) {
            return self::$yy_default[$stateno];
        }
        if ($iLookAhead == self::YYNOCODE) {
            return self::YY_NO_ACTION;
        }
        $i += $iLookAhead;
        if ($i < 0 || $i >= self::YY_SZ_ACTTAB ||
              self::$yy_lookahead[$i] != $iLookAhead) {
            return self::$yy_default[$stateno];
        } else {
            return self::$yy_action[$i];
        }
    }

    /**
     * Perform a shift action.
     * @param int The new state to shift in
     * @param int The major token to shift in
     * @param mixed the minor token to shift in
     */
    function yy_shift($yyNewState, $yyMajor, $yypMinor)
    {
        $this->yyidx++;
        if ($this->yyidx >= self::YYSTACKDEPTH) {
            $this->yyidx--;
            if (self::$yyTraceFILE) {
                fprintf(self::$yyTraceFILE, "%sStack Overflow!\n", self::$yyTracePrompt);
            }
            while ($this->yyidx >= 0) {
                $this->yy_pop_parser_stack();
            }
            /* Here code is inserted which will execute if the parser
            ** stack ever overflows */
            return;
        }
        $yytos = new XenForo_Template_Compiler_Parser_yyStackEntry;
        $yytos->stateno = $yyNewState;
        $yytos->major = $yyMajor;
        $yytos->minor = $yypMinor;
        array_push($this->yystack, $yytos);
        if (self::$yyTraceFILE && $this->yyidx > 0) {
            fprintf(self::$yyTraceFILE, "%sShift %d\n", self::$yyTracePrompt,
                $yyNewState);
            fprintf(self::$yyTraceFILE, "%sStack:", self::$yyTracePrompt);
            for($i = 1; $i <= $this->yyidx; $i++) {
                fprintf(self::$yyTraceFILE, " %s",
                    self::$yyTokenName[$this->yystack[$i]->major]);
            }
            fwrite(self::$yyTraceFILE,"\n");
        }
    }

    /**
     * The following table contains information about every rule that
     * is used during the reduce.
     *
     * <pre>
     * array(
     *  array(
     *   int $lhs;         Symbol on the left-hand side of the rule
     *   int $nrhs;     Number of right-hand side symbols in the rule
     *  ),...
     * );
     * </pre>
     */
    static public $yyRuleInfo = array(
  array( 'lhs' => 19, 'rhs' => 1 ),
  array( 'lhs' => 20, 'rhs' => 2 ),
  array( 'lhs' => 20, 'rhs' => 2 ),
  array( 'lhs' => 20, 'rhs' => 2 ),
  array( 'lhs' => 20, 'rhs' => 2 ),
  array( 'lhs' => 20, 'rhs' => 2 ),
  array( 'lhs' => 20, 'rhs' => 0 ),
  array( 'lhs' => 22, 'rhs' => 3 ),
  array( 'lhs' => 22, 'rhs' => 3 ),
  array( 'lhs' => 23, 'rhs' => 5 ),
  array( 'lhs' => 23, 'rhs' => 5 ),
  array( 'lhs' => 23, 'rhs' => 0 ),
  array( 'lhs' => 21, 'rhs' => 1 ),
  array( 'lhs' => 21, 'rhs' => 1 ),
  array( 'lhs' => 25, 'rhs' => 3 ),
  array( 'lhs' => 27, 'rhs' => 3 ),
  array( 'lhs' => 27, 'rhs' => 3 ),
  array( 'lhs' => 27, 'rhs' => 0 ),
  array( 'lhs' => 26, 'rhs' => 4 ),
  array( 'lhs' => 28, 'rhs' => 1 ),
  array( 'lhs' => 28, 'rhs' => 1 ),
  array( 'lhs' => 28, 'rhs' => 1 ),
  array( 'lhs' => 28, 'rhs' => 1 ),
  array( 'lhs' => 28, 'rhs' => 1 ),
  array( 'lhs' => 29, 'rhs' => 3 ),
  array( 'lhs' => 29, 'rhs' => 0 ),
  array( 'lhs' => 30, 'rhs' => 3 ),
  array( 'lhs' => 31, 'rhs' => 3 ),
  array( 'lhs' => 24, 'rhs' => 2 ),
  array( 'lhs' => 24, 'rhs' => 2 ),
  array( 'lhs' => 24, 'rhs' => 0 ),
    );

    /**
     * The following table contains a mapping of reduce action to method name
     * that handles the reduction.
     * 
     * If a rule is not set, it has no handler.
     */
    static public $yyReduceMap = array(
        1 => 1,
        2 => 1,
        3 => 3,
        5 => 5,
        7 => 7,
        8 => 8,
        9 => 9,
        10 => 9,
        12 => 12,
        13 => 12,
        20 => 12,
        21 => 12,
        22 => 12,
        23 => 12,
        14 => 14,
        15 => 15,
        16 => 15,
        24 => 15,
        18 => 18,
        19 => 19,
        26 => 26,
        27 => 26,
        28 => 28,
        29 => 28,
    );
    /* Beginning here are the reduction cases.  A typical example
    ** follows:
    **  #line <lineno> <grammarfile>
    **   function yy_r0($yymsp){ ... }           // User supplied code
    **  #line <lineno> <thisfile>
    */
#line 107 "Parser.y"
    function yy_r1(){
		$this->_addOp($this->yystack[$this->yyidx + 0]->minor);
	    }
#line 963 "Parser.php"
#line 117 "Parser.y"
    function yy_r3(){
		$tag = $this->yystack[$this->yyidx + 0]->minor;
		if ($tag['type'] == 'TAG_SELF_CLOSE')
		{
			$tag['type'] = 'TAG';
			$tag['children'] = array();
			$this->_addOp($tag);
		}
		else
		{
			// new tag
			$tag['type'] = 'TAG';
			$tag['children'] = array();
			$this->_addOp($tag);
			$this->_pushTagContext($tag['name'], $tag['children']);
		}
	    }
#line 982 "Parser.php"
#line 138 "Parser.y"
    function yy_r5(){
		$this->_popTagContext($this->yystack[$this->yyidx + 0]->minor);
	    }
#line 987 "Parser.php"
#line 145 "Parser.y"
    function yy_r7(){
		$attributes = $this->yystack[$this->yyidx + -1]->minor;
		if (!is_array($attributes))
		{
			$attributes = array();
		}

		$this->_retvalue = array(
			'type' => 'TAG_SELF_CLOSE',
			'name' => $this->yystack[$this->yyidx + -2]->minor,
			'attributes' => $attributes,
			'line' => $this->_lineNumber
		);
	    }
#line 1003 "Parser.php"
#line 161 "Parser.y"
    function yy_r8(){
		$attributes = $this->yystack[$this->yyidx + -1]->minor;
		if (!is_array($attributes))
		{
			$attributes = array();
		}

		$this->_retvalue = array(
			'type' => 'TAG_OPEN',
			'name' => $this->yystack[$this->yyidx + -2]->minor,
			'attributes' => $attributes,
			'line' => $this->_lineNumber
		);
	    }
#line 1019 "Parser.php"
#line 177 "Parser.y"
    function yy_r9(){
		$existing = $this->yystack[$this->yyidx + -4]->minor;
		if (!is_array($existing))
		{
			$existing = array();
		}

		$val = $this->yystack[$this->yyidx + -1]->minor;
		if ($val === null)
		{
			$val = '';
		}
		$existing[$this->yystack[$this->yyidx + -3]->minor] = $val;

		$this->_retvalue = $existing;
	    }
#line 1037 "Parser.php"
#line 215 "Parser.y"
    function yy_r12(){
		$this->_retvalue = $this->yystack[$this->yyidx + 0]->minor;
	    }
#line 1042 "Parser.php"
#line 225 "Parser.y"
    function yy_r14(){
		$this->_retvalue = array(
			'type' => 'CURLY_VAR',
			'name' => $this->yystack[$this->yyidx + -2]->minor,
			'keys' => is_array($this->yystack[$this->yyidx + -1]->minor) ? $this->yystack[$this->yyidx + -1]->minor : array(),
			'line' => $this->_lineNumber
		);
	    }
#line 1052 "Parser.php"
#line 235 "Parser.y"
    function yy_r15(){
		$this->_retvalue = is_array($this->yystack[$this->yyidx + -2]->minor) ? $this->yystack[$this->yyidx + -2]->minor : array();
		$this->_retvalue[] = $this->yystack[$this->yyidx + 0]->minor;
	    }
#line 1058 "Parser.php"
#line 249 "Parser.y"
    function yy_r18(){
		$arguments = array($this->yystack[$this->yyidx + -2]->minor);
		if ($this->yystack[$this->yyidx + -1]->minor)
		{
			$arguments = array_merge($arguments, $this->yystack[$this->yyidx + -1]->minor);
		}

		$this->_retvalue = array(
			'type' => 'CURLY_FUNCTION',
			'name' => $this->yystack[$this->yyidx + -3]->minor,
			'arguments' => $arguments,
			'line' => $this->_lineNumber
		);
	    }
#line 1074 "Parser.php"
#line 265 "Parser.y"
    function yy_r19(){
		$this->_retvalue = $this->_getSimpleVariable($this->yystack[$this->yyidx + 0]->minor);
	    }
#line 1079 "Parser.php"
#line 294 "Parser.y"
    function yy_r26(){
		$this->_retvalue = is_array($this->yystack[$this->yyidx + -1]->minor) ? $this->yystack[$this->yyidx + -1]->minor : array();
	    }
#line 1084 "Parser.php"
#line 304 "Parser.y"
    function yy_r28(){
		$this->_retvalue = is_array($this->yystack[$this->yyidx + -1]->minor) ? $this->yystack[$this->yyidx + -1]->minor : array();
		$this->_retvalue[] = $this->yystack[$this->yyidx + 0]->minor;
	    }
#line 1090 "Parser.php"

    /**
     * placeholder for the left hand side in a reduce operation.
     * 
     * For a parser with a rule like this:
     * <pre>
     * rule(A) ::= B. { A = 1; }
     * </pre>
     * 
     * The parser will translate to something like:
     * 
     * <code>
     * function yy_r0(){$this->_retvalue = 1;}
     * </code>
     */
    private $_retvalue;

    /**
     * Perform a reduce action and the shift that must immediately
     * follow the reduce.
     * 
     * For a rule such as:
     * 
     * <pre>
     * A ::= B blah C. { dosomething(); }
     * </pre>
     * 
     * This function will first call the action, if any, ("dosomething();" in our
     * example), and then it will pop three states from the stack,
     * one for each entry on the right-hand side of the expression
     * (B, blah, and C in our example rule), and then push the result of the action
     * back on to the stack with the resulting state reduced to (as described in the .out
     * file)
     * @param int Number of the rule by which to reduce
     */
    function yy_reduce($yyruleno)
    {
        //int $yygoto;                     /* The next state */
        //int $yyact;                      /* The next action */
        //mixed $yygotominor;        /* The LHS of the rule reduced */
        //XenForo_Template_Compiler_Parser_yyStackEntry $yymsp;            /* The top of the parser's stack */
        //int $yysize;                     /* Amount to pop the stack */
        $yymsp = $this->yystack[$this->yyidx];
        if (self::$yyTraceFILE && $yyruleno >= 0 
              && $yyruleno < count(self::$yyRuleName)) {
            fprintf(self::$yyTraceFILE, "%sReduce (%d) [%s].\n",
                self::$yyTracePrompt, $yyruleno,
                self::$yyRuleName[$yyruleno]);
        }

        $this->_retvalue = $yy_lefthand_side = null;
        if (array_key_exists($yyruleno, self::$yyReduceMap)) {
            // call the action
            $this->_retvalue = null;
            $this->{'yy_r' . self::$yyReduceMap[$yyruleno]}();
            $yy_lefthand_side = $this->_retvalue;
        }
        $yygoto = self::$yyRuleInfo[$yyruleno]['lhs'];
        $yysize = self::$yyRuleInfo[$yyruleno]['rhs'];
        $this->yyidx -= $yysize;
        for($i = $yysize; $i; $i--) {
            // pop all of the right-hand side parameters
            array_pop($this->yystack);
        }
        $yyact = $this->yy_find_reduce_action($this->yystack[$this->yyidx]->stateno, $yygoto);
        if ($yyact < self::YYNSTATE) {
            /* If we are not debugging and the reduce action popped at least
            ** one element off the stack, then we can push the new element back
            ** onto the stack here, and skip the stack overflow test in yy_shift().
            ** That gives a significant speed improvement. */
            if (!self::$yyTraceFILE && $yysize) {
                $this->yyidx++;
                $x = new XenForo_Template_Compiler_Parser_yyStackEntry;
                $x->stateno = $yyact;
                $x->major = $yygoto;
                $x->minor = $yy_lefthand_side;
                $this->yystack[$this->yyidx] = $x;
            } else {
                $this->yy_shift($yyact, $yygoto, $yy_lefthand_side);
            }
        } elseif ($yyact == self::YYNSTATE + self::YYNRULE + 1) {
            $this->yy_accept();
        }
    }

    /**
     * The following code executes when the parse fails
     * 
     * Code from %parse_fail is inserted here
     */
    function yy_parse_failed()
    {
        if (self::$yyTraceFILE) {
            fprintf(self::$yyTraceFILE, "%sFail!\n", self::$yyTracePrompt);
        }
        while ($this->yyidx >= 0) {
            $this->yy_pop_parser_stack();
        }
        /* Here code is inserted which will be executed whenever the
        ** parser fails */
    }

    /**
     * The following code executes when a syntax error first occurs.
     * 
     * %syntax_error code is inserted here
     * @param int The major type of the error token
     * @param mixed The minor type of the error token
     */
    function yy_syntax_error($yymajor, $TOKEN)
    {
#line 3 "Parser.y"
 throw new XenForo_Template_Compiler_Exception(new XenForo_Phrase('line_x_template_syntax_error', array('number' => $this->_lineNumber)), true); #line 1204 "Parser.php"
    }

    /**
     * The following is executed when the parser accepts
     * 
     * %parse_accept code is inserted here
     */
    function yy_accept()
    {
        if (self::$yyTraceFILE) {
            fprintf(self::$yyTraceFILE, "%sAccept!\n", self::$yyTracePrompt);
        }
        while ($this->yyidx >= 0) {
            $stack = $this->yy_pop_parser_stack();
        }
        /* Here code is inserted which will be executed whenever the
        ** parser accepts */
    }

    /**
     * The main parser program.
     * 
     * The first argument is the major token number.  The second is
     * the token value string as scanned from the input.
     *
     * @param int the token number
     * @param mixed the token value
     * @param mixed any extra arguments that should be passed to handlers
     */
    function doParse($yymajor, $yytokenvalue)
    {
//        $yyact;            /* The parser action. */
//        $yyendofinput;     /* True if we are at the end of input */
        $yyerrorhit = 0;   /* True if yymajor has invoked an error */
        
        /* (re)initialize the parser, if necessary */
        if ($this->yyidx === null || $this->yyidx < 0) {
            /* if ($yymajor == 0) return; // not sure why this was here... */
            $this->yyidx = 0;
            $this->yyerrcnt = -1;
            $x = new XenForo_Template_Compiler_Parser_yyStackEntry;
            $x->stateno = 0;
            $x->major = 0;
            $this->yystack = array();
            array_push($this->yystack, $x);
        }
        $yyendofinput = ($yymajor==0);
        
        if (self::$yyTraceFILE) {
            fprintf(self::$yyTraceFILE, "%sInput %s\n",
                self::$yyTracePrompt, self::$yyTokenName[$yymajor]);
        }
        
        do {
            $yyact = $this->yy_find_shift_action($yymajor);
            if ($yymajor < self::YYERRORSYMBOL &&
                  !$this->yy_is_expected_token($yymajor)) {
                // force a syntax error
                $yyact = self::YY_ERROR_ACTION;
            }
            if ($yyact < self::YYNSTATE) {
                $this->yy_shift($yyact, $yymajor, $yytokenvalue);
                $this->yyerrcnt--;
                if ($yyendofinput && $this->yyidx >= 0) {
                    $yymajor = 0;
                } else {
                    $yymajor = self::YYNOCODE;
                }
            } elseif ($yyact < self::YYNSTATE + self::YYNRULE) {
                $this->yy_reduce($yyact - self::YYNSTATE);
            } elseif ($yyact == self::YY_ERROR_ACTION) {
                if (self::$yyTraceFILE) {
                    fprintf(self::$yyTraceFILE, "%sSyntax Error!\n",
                        self::$yyTracePrompt);
                }
                if (self::YYERRORSYMBOL) {
                    /* A syntax error has occurred.
                    ** The response to an error depends upon whether or not the
                    ** grammar defines an error token "ERROR".  
                    **
                    ** This is what we do if the grammar does define ERROR:
                    **
                    **  * Call the %syntax_error function.
                    **
                    **  * Begin popping the stack until we enter a state where
                    **    it is legal to shift the error symbol, then shift
                    **    the error symbol.
                    **
                    **  * Set the error count to three.
                    **
                    **  * Begin accepting and shifting new tokens.  No new error
                    **    processing will occur until three tokens have been
                    **    shifted successfully.
                    **
                    */
                    if ($this->yyerrcnt < 0) {
                        $this->yy_syntax_error($yymajor, $yytokenvalue);
                    }
                    $yymx = $this->yystack[$this->yyidx]->major;
                    if ($yymx == self::YYERRORSYMBOL || $yyerrorhit ){
                        if (self::$yyTraceFILE) {
                            fprintf(self::$yyTraceFILE, "%sDiscard input token %s\n",
                                self::$yyTracePrompt, self::$yyTokenName[$yymajor]);
                        }
                        $this->yy_destructor($yymajor, $yytokenvalue);
                        $yymajor = self::YYNOCODE;
                    } else {
                        while ($this->yyidx >= 0 &&
                                 $yymx != self::YYERRORSYMBOL &&
        ($yyact = $this->yy_find_shift_action(self::YYERRORSYMBOL)) >= self::YYNSTATE
                              ){
                            $this->yy_pop_parser_stack();
                        }
                        if ($this->yyidx < 0 || $yymajor==0) {
                            $this->yy_destructor($yymajor, $yytokenvalue);
                            $this->yy_parse_failed();
                            $yymajor = self::YYNOCODE;
                        } elseif ($yymx != self::YYERRORSYMBOL) {
                            $u2 = 0;
                            $this->yy_shift($yyact, self::YYERRORSYMBOL, $u2);
                        }
                    }
                    $this->yyerrcnt = 3;
                    $yyerrorhit = 1;
                } else {
                    /* YYERRORSYMBOL is not defined */
                    /* This is what we do if the grammar does not define ERROR:
                    **
                    **  * Report an error message, and throw away the input token.
                    **
                    **  * If the input token is $, then fail the parse.
                    **
                    ** As before, subsequent error messages are suppressed until
                    ** three input tokens have been successfully shifted.
                    */
                    if ($this->yyerrcnt <= 0) {
                        $this->yy_syntax_error($yymajor, $yytokenvalue);
                    }
                    $this->yyerrcnt = 3;
                    $this->yy_destructor($yymajor, $yytokenvalue);
                    if ($yyendofinput) {
                        $this->yy_parse_failed();
                    }
                    $yymajor = self::YYNOCODE;
                }
            } else {
                $this->yy_accept();
                $yymajor = self::YYNOCODE;
            }            
        } while ($yymajor != self::YYNOCODE && $this->yyidx >= 0);
    }
}