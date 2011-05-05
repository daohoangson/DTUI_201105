<?php

/**
* Phrase rendering class.
*
* @package XenForo_Core
*/
class XenForo_Phrase
{
	/**
	* Cached phrase data. Key is the phrase name; value is the phrase text.
	*
	* @var array
	*/
	protected static $_phraseCache = array();

	/**
	* A list of phrases that still need to be loaded. Key is the phrase name.
	*
	* @var array
	*/
	protected static $_toLoad = array();

	/**
	 * The function that should be called to escape phrase parameters. This should
	 * be set by the view renderer. Set it to false to disable escaping completely.
	 *
	 * @param false|callback
	 */
	protected static $_escapeCallback = 'htmlspecialchars';

	/**
	* Name of the phrase to load.
	*
	* @var string
	*/
	protected $_phraseName;

	/**
	* Key-value params to make available in the phrase.
	*
	* @var array
	*/
	protected $_params = array();

	/**
	* Whether the params should be inserted into the phrase raw or escaped.
	* If this is true or false, it applies to all params. If it is an array,
	* the individual params are looked up as keys and the value is treated as a
	* a boolean; params that aren't set will be escaped.
	*
	* @var boolean|array
	*/
	protected $_insertParamsEscaped = true;

	/**
	 * The ID of the language that phrases will be retrieved from.
	 *
	 * @var integer
	 */
	protected static $_languageId = 0;

	/**
	* Constructor
	*
	* @param string|array  Phrase name (or, array(phraseName, arg1 => x, arg2 => y...)
	* @param array         Key-value parameters
	* @param boolean|array See {@link $_insertParamsEscaped}
	*/
	public function __construct($phraseName, array $params = array(), $insertParamsEscaped = true)
	{
		// deal with all data being passed through the $phraseName parameter
		if (is_array($phraseName))
		{
			$phraseKey = $phraseName[0];
				unset($phraseName[0]);

			$params = array_merge($phraseName, $params);

			$phraseName = $phraseKey;
		}
		else if ($phraseName instanceof XenForo_Phrase)
		{
			$phraseKey = $phraseName->getPhraseName();
			$params = array_merge($phraseName->getParams(), $params);

			$phraseName = $phraseKey;
		}

		$this->_phraseName = $phraseName;
		if ($params)
		{
			$this->setParams($params);
		}

		$this->setInsertParamsEscaped($insertParamsEscaped);

		self::preloadPhrase($phraseName);
	}

	/**
	 * Sets the language ID that phrases will be retrieved from.
	 *
	 * @param integer $languageId
	 */
	public static function setLanguageId($languageId)
	{
		self::$_languageId = intval($languageId);
	}

	/**
	 * Gets the language ID that phrases will be retrieived from.
	 *
	 * @return integer
	 */
	public static function getLanguageId()
	{
		return self::$_languageId;
	}

	/**
	* Add an array of params to the phrase. Overwrites parameters with the same name.
	*
	* @param array
	*/
	public function setParams(array $params)
	{
		$this->_params = array_merge($this->_params, $params);
	}

	/**
	 * Gets the params.
	 *
	 * @return array
	 */
	public function getParams()
	{
		return $this->_params;
	}

	/**
	 * Gets the phrase name.
	 *
	 * @return string
	 */
	public function getPhraseName()
	{
		return $this->_phraseName;
	}

	/**
	* Sets whether inserted parameters should be automatically escaped.
	* @see $_insertParamsEscaped
	*
	* @param array|boolean
	*/
	public function setInsertParamsEscaped($insertParamsEscaped)
	{
		if (is_array($insertParamsEscaped))
		{
			if (is_array($this->_insertParamsEscaped))
			{
				$this->_insertParamsEscaped = array_merge($this->_insertParamsEscaped, $insertParamsEscaped);
			}
			else
			{
				$this->_insertParamsEscaped = $insertParamsEscaped;
			}
		}
		else
		{
			$this->_insertParamsEscaped = (bool)$insertParamsEscaped;
		}
	}

	/**
	* Renders the specified phrase and returns the output.
	*
	* @return string
	*/
	public function render()
	{
		$phrase = self::_loadPhrase($this->_phraseName);
		if (!is_string($phrase))
		{
			return $this->_phraseName;
		}

		if (empty($this->_params))
		{
			return $phrase;
		}

		$phrase = preg_replace_callback('/\{([a-z0-9_-]+)\}/i', array($this, '_replaceParam'), $phrase);

		return $phrase;
	}

	/**
	* Callback function for regular expression to replace a named parameter with a value.
	*
	* @param array Match array. Looks in key "param".
	*
	* @return string Replaced value
	*/
	protected function _replaceParam($match)
	{
		$paramName = $match[1];

		if (!array_key_exists($paramName, $this->_params))
		{
			return $match[0];
		}

		return $this->_escapeParam($paramName, $this->_params[$paramName]);
	}

	/**
	* Escapes the named parameter if necessary, based on {@link $_insertParamsEscaped).
	*
	* @param string Name of parameter
	* @param string Value of parameter
	*
	* @return string Escaped parameter
	*/
	protected function _escapeParam($paramName, $paramValue)
	{
		if (!self::$_escapeCallback || $paramValue instanceof XenForo_Phrase)
		{
			return $paramValue;
		}

		if (is_array($this->_insertParamsEscaped))
		{
			if (!isset($this->_insertParamsEscaped[$paramName]) || $this->_insertParamsEscaped[$paramName] !== false)
			{
				// escape selective and this one is to be escaped or wasn't explicitly disabled
				$paramValue = call_user_func(self::$_escapeCallback, $paramValue);
			}
		}
		else if ($this->_insertParamsEscaped !== false)
		{
			// escape all
			$paramValue = call_user_func(self::$_escapeCallback, $paramValue);
		}

		return $paramValue;
	}

	/**
	* Implicit string cast renders the phrase.
	*
	* @return string
	*/
	public function __toString()
	{
		return $this->render();
	}

	/**
	* Load the named phrase.
	*
	* @param string Phrase name
	*
	* @return string Compiled version of the phrase
	*/
	protected static function _loadPhrase($phraseName)
	{
		if (!isset(self::$_phraseCache[$phraseName]))
		{
			self::loadPhrases();

			if (!isset(self::$_phraseCache[$phraseName]))
			{
				// couldn't find this phrase
				self::$_phraseCache[$phraseName] = false;
			}
		}

		return self::$_phraseCache[$phraseName];
	}

	/**
	* Bulk load all phrases that are required.
	*/
	public static function loadPhrases()
	{
		if (!self::$_toLoad)
		{
			return;
		}

		$db = XenForo_Application::get('db');

		$phrases = $db->fetchPairs('
			SELECT title, phrase_text
			FROM xf_phrase_compiled
			WHERE language_id = ?
				AND title IN (' . $db->quote(array_keys(self::$_toLoad)) . ')
		', self::$_languageId);

		self::$_phraseCache = array_merge(self::$_phraseCache, $phrases);
		self::$_toLoad = array();
	}

	/**
	* Specify a phrase that needs to be preloaded for use later. This is useful
	* if you think a render is going to be called before the phrase you require
	* is to be used.
	*
	* @param string Phrase to preload
	*/
	public static function preloadPhrase($phraseName)
	{
		if (!isset(self::$_phraseCache[$phraseName]))
		{
			self::$_toLoad[$phraseName] = true;
		}
	}

	/**
	* Manually sets a phrase. This is primarily useful for testing.
	*
	* @param string Name of the phrase
	* @param string Value for the phrase
	*/
	public static function setPhrase($phraseName, $phraseValue)
	{
		self::$_phraseCache[$phraseName] = $phraseValue;
	}

	/**
	 * Manually sets the cache values for collection of phrases.
	 *
	 * @param array $phrases
	 */
	public static function setPhrases(array $phrases)
	{
		self::$_phraseCache = array_merge(self::$_phraseCache, $phrases);
	}

	/**
	* Resets the phrase system state.
	*/
	public static function reset()
	{
		self::$_phraseCache = array();
		self::$_toLoad = array();
	}

	/**
	 * Sets the param escaping callback function.
	 *
	 * @param false|callback $callback
	 */
	public static function setEscapeCallback($callback)
	{
		self::$_escapeCallback = $callback;
	}
}