<?php

/**
 * Handler for searching with MySQL's full text search.
 *
 * @package XenForo_Search
 */
class XenForo_Search_SourceHandler_MySqlFt extends XenForo_Search_SourceHandler_Abstract
{
	/**
	 * @var Zend_Db_Adapter_Abstract
	 */
	protected $_db = null;

	/**
	 * Minimum character length of searchable words. Used for error/warning detection.
	 *
	 * @var integer
	 */
	protected $_minWordLength = 4; // TODO: source from option

	protected $_bulkInserts = array();
	protected $_bulkInsertLength = 0;

	/**
	 * Determines if this source supports relevance sorting.
	 *
	 * @return boolean;
	 */
	public function supportsRelevance()
	{
		return false;
	}

	/**
	 * Inserts or replaces into the index.
	 *
	 * @see XenForo_Search_SourceHandler_Abstract::insertIntoIndex()
	 */
	public function insertIntoIndex($contentType, $contentId, $title, $message, $itemDate, $userId, $discussionId = 0, array $metadata = array())
	{
		$metadataPieces = array(
			$this->getMetadataKey('user', $userId),
			$this->getMetadataKey('content', $contentType)
		);
		foreach ($metadata AS $metadataKey => $value)
		{
			$metadataPieces[] = $this->getMetadataKey($metadataKey, $value);
		}

		$db = $this->_getDb();
		$row = '(' . $db->quote($contentType) . ', ' . $db->quote(intval($contentId))
			. ', ' . $db->quote($title) . ', ' . $db->quote($message)
			. ', ' . $db->quote(implode(' ', $metadataPieces))
			. ', ' . $db->quote(intval($itemDate)) . ', ' . $db->quote(intval($userId))
			. ', ' . $db->quote(intval($discussionId)) . ')';

		if ($this->_isRebuild)
		{
			$this->_bulkInserts[] = $row;
			$this->_bulkInsertLength += strlen($row);

			if ($this->_bulkInsertLength > 500000)
			{
				$this->_pushToIndex($this->_bulkInserts);

				$this->_bulkInserts = array();
				$this->_bulkInsertLength = 0;
			}
		}
		else
		{
			$this->_pushToIndex($row);
		}
	}

	/**
	 * Runs the actual query to replace/update the index.
	 *
	 * @param string|array $record A record (SQL) or array of SQL
	 */
	protected function _pushToIndex($record)
	{
		if (is_array($record))
		{
			$record = implode(',', $record);
		}

		if (!$record)
		{
			return;
		}

		$this->_getDb()->query('
			REPLACE ' . ($this->_isRebuild ? '' : 'DELAYED') . ' INTO xf_search_index
				(content_type, content_id,
				title, message, metadata,
				item_date, user_id, discussion_id)
			VALUES
				' . $record
		);
	}

	/**
	 * When rebuilding, it might be advantageous to bulk update records. This function
	 * must be called to ensure that all records are updated together.
	 */
	public function finalizeRebuildSet()
	{
		$this->_pushToIndex($this->_bulkInserts);
	}

	/**
	 * Updates a record in the index.
	 *
	 * @see XenForo_Search_SourceHandler_Abstract::updateIndex()
	 */
	public function updateIndex($contentType, $contentId, array $fieldUpdates)
	{
		$db = $this->_getDb();
		$db->update('xf_search_index',
			$fieldUpdates,
			'content_type = ' . $db->quote($contentType) . ' AND content_id = ' . $db->quote($contentId)
		);
	}

	/**
	 * Deletes one or more record from the index.
	 *
	 * @see XenForo_Search_SourceHandler_Abstract::deleteFromIndex()
	 */
	public function deleteFromIndex($contentType, array $contentIds)
	{
		if (!$contentIds)
		{
			return;
		}

		$db = $this->_getDb();
		$db->delete('xf_search_index',
			'content_type = ' . $db->quote($contentType) . ' AND content_id IN (' . $db->quote($contentIds) . ')'
		);
	}

	/**
	 * Executes a search against the full text index.
	 *
	 * @param string $searchQuery Text to search for
	 * @param boolean $titleOnly If true, only searches text in titles
	 * @param array $processedConstraints Structured constraints
	 * @param array $orderParts Structured ordered by parts
	 * @param string $groupByDiscussionType If grouping, content type of grouped results
	 * @param integer $maxResults
	 * @param XenForo_Search_DataHandler_Abstract $typeHandler Type-specific handler, for joins
	 *
	 * @return array Search results ([] => array(content type, id))
	 */
	public function executeSearch($searchQuery, $titleOnly, array $processedConstraints, array $orderParts,
		$groupByDiscussionType, $maxResults, XenForo_Search_DataHandler_Abstract $typeHandler = null
	)
	{
		$db = $this->_getDb();

		$queryParts = $this->tokenizeQuery($searchQuery);
		$searchQuery = $this->parseTokenizedQuery($queryParts, $processedConstraints);

		if ($titleOnly)
		{
			$matchFields = 'search_index.title, search_index.metadata';
		}
		else
		{
			$matchFields = 'search_index.title, search_index.message, search_index.metadata';
		}

		$tables = array();
		$whereClauses = array();

		foreach ($processedConstraints AS $constraint)
		{
			if (isset($constraint['query']) && !isset($constraint['metadata']))
			{
				// pull queries without metadata alternatives
				list($queryTable, $queryField, $queryOperator, $queryValues) = $constraint['query'];

				if (is_array($queryValues) && count($queryValues) == 0)
				{
					continue;
				}

				if ($queryOperator == '=' && is_array($queryValues))
				{
					$whereClauses[] = "$queryTable.$queryField IN (" . $db->quote($queryValues) . ")";
				}
				else
				{
					$whereClauses[] = "$queryTable.$queryField $queryOperator " . $db->quote(strval($queryValues));
				}

				$tables[] = $queryTable;
			}
		}

		$orderFields = array();
		foreach ($orderParts AS $order)
		{
			list($orderTable, $orderField, $orderDirection) = $order;

			$orderFields[] = "$orderTable.$orderField $orderDirection";
			$tables[] = $orderTable;
		}
		$orderClause = ($orderFields ? 'ORDER BY ' . implode(', ', $orderFields) : 'ORDER BY NULL');

		$tables = array_flip($tables);
		unset($tables['search_index']);
		if ($typeHandler)
		{
			$joinStructures = $typeHandler->getJoinStructures($tables);
			$joins = array();
			foreach ($joinStructures AS $tableAlias => $joinStructure)
			{
				list($relationshipTable, $relationshipField) = $joinStructure['relationship'];
				$joins[] = "INNER JOIN $joinStructure[table] AS $tableAlias ON
					($tableAlias.$joinStructure[key] = $relationshipTable.$relationshipField)";
			}
		}
		else
		{
			$joins = array();
		}

		$extraWhere = ($whereClauses ? 'AND (' . implode(') AND (', $whereClauses) . ')' : '');

		if ($groupByDiscussionType)
		{
			$selectFields = $db->quote($groupByDiscussionType) . ' AS content_type, search_index.discussion_id AS content_id';
			$groupByClause = 'GROUP BY search_index.discussion_id';
		}
		else
		{
			$selectFields = 'search_index.content_type, search_index.content_id';
			$groupByClause = '';
		}

		if ($maxResults < 1)
		{
			$maxResults = 100;
		}
		$maxResults = intval($maxResults);

		if ($this->_searcher && $this->_searcher->hasErrors())
		{
			return array();
		}

		return $db->fetchAll("
			SELECT $selectFields
			FROM xf_search_index AS search_index
			" . implode("\n", $joins) . "
			WHERE MATCH($matchFields) AGAINST (? IN BOOLEAN MODE)
				$extraWhere
			$groupByClause
			$orderClause
			LIMIT $maxResults
		", $searchQuery, Zend_Db::FETCH_NUM);
	}

	/**
	 * Searches for content by the specified user ID.
	 *
	 * @see XenForo_Search_SourceHandler_Abstract::executeSearchByUserId()
	 */
	public function executeSearchByUserId($userId, $maxDate, $maxResults)
	{
		$selectFields = 'search_index.content_type, search_index.content_id';

		$db = $this->_getDb();

		if ($maxDate)
		{
			$dateCutOff = 'AND search_index.item_date < ' . $db->quote($maxDate);
		}
		else
		{
			$dateCutOff = '';
		}

		return $db->fetchAll($db->limit(
			'
				SELECT ' . $selectFields . '
				FROM xf_search_index AS search_index
				WHERE search_index.user_id = ?
					' . $dateCutOff . '
				ORDER BY search_index.item_date DESC
			', $maxResults
		), $userId, Zend_Db::FETCH_NUM);
	}

	/**
	 * Gets the string form of a piece of metadata.
	 *
	 * @param string $keyName Type of metadata
	 * @param string|array $value Metadata value; if an array, gets metadata for each value
	 *
	 * @return string|array String if $value was a string, array if $value was an array
	 */
	public function getMetadataKey($keyName, $value)
	{
		if (is_array($value))
		{
			$output = array();
			foreach ($value AS $childValue)
			{
				$output[] = '_md_' . $keyName . '_' . preg_replace('/[^a-z0-9_]/i', '', strval($childValue));
			}

			return $output;
		}
		else
		{
			return '_md_' . $keyName . '_' . preg_replace('/[^a-z0-9_]/i', '', $value);
		}
	}

	/**
	 * Tokenizes a search query into parts.
	 *
	 * @param string $query
	 *
	 * @return array Tokenized query, [] => array(0 => modifier (empty, +, -, |), 1 => term). Term will include ".." if given.
	 */
	public function tokenizeQuery($query)
	{
		$query = str_replace(array('(', ')'), '', trim($query)); // don't support grouping yet

		preg_match_all('/
			(?<=\s|^)
			(?P<modifier>\-|\+|\||)
			\s*
			(?P<term>"(?P<quoteTerm>[^"]+)"|\S+)
		/ix', $query, $matches, PREG_SET_ORDER);

		$output = array();
		$i = 0;

		$haveWords = false;
		$invalidWords = array();

		foreach ($matches AS $match)
		{
			if ($match['modifier'] == '|' && $i > 0 && $output[$i - 1][0] == '')
			{
				$output[$i - 1][0] = '|';
			}
			else if ($match['modifier'] == '|' && $i == 0)
			{
				$match['modifier'] = '';
			}

			$output[$i] = array($match['modifier'], $match['term']);

			$words = (!empty($match['quoteTerm']) ? $this->splitWords($match['quoteTerm']) : $this->splitWords($match['term']));
			foreach ($words AS $word)
			{
				if (utf8_strlen($word) < $this->_minWordLength)
				{
					$invalidWords[] = $word;
				}
				else if (in_array($word, self::$stopWords))
				{
					$invalidWords[] = $word;
				}
				else
				{
					$haveWords = true;
				}
			}

			$i++;
		}

		if (!$haveWords)
		{
			if ($invalidWords)
			{
				$this->error(new XenForo_Phrase('search_could_not_be_completed_because_search_keywords_were_too'), 'keywords');
			}
			else
			{
				//$this->error(new XenForo_Phrase('please_enter_valid_search_query'), 'keywords');
			}
		}
		else if ($invalidWords)
		{
			$this->warning(
				new XenForo_Phrase(
					'following_words_were_not_included_in_your_search_x',
					array('words' => implode(', ', $invalidWords))
				), 'keywords'
			);
		}

		return $output;
	}

	/**
	 * Split words by the delimiters used by MySQL.
	 *
	 * @param string $words
	 *
	 * @return array
	 */
	public function splitWords($words)
	{
		// delimiters: 0 - 38, 40, 41, 43 - 47, 58 - 64, 91 - 94, 96, 123 - 127
		return preg_split('/[\x00-\x20\x28\x29\x2B-\x2F\x3A-\x40\x5B-\x5E\x60\x7B-\x7F]/', $words);
	}

	/**
	 * Parses a tokenized query into the final query to give to MySQL. Metadata
	 * will also be searched in the query.
	 *
	 * @param array $query Tokenized query
	 * @param array $processedConstraints Constraints, in source-agnostic format
	 *
	 * @return string Query ready for MySQL
	 */
	public function parseTokenizedQuery(array $query, array $processedConstraints)
	{
		$output = '';
		foreach ($query AS $part)
		{
			if ($part[0] == '')
			{
				$part[0] = '+';
			}
			else if ($part[0] == '|')
			{
				$part[0] = ''; // default in mysql
			}

			$output .= ' ' . $part[0] . $part[1];
		}

		foreach ($processedConstraints AS $constraint)
		{
			if (!isset($constraint['metadata']))
			{
				continue;
			}

			list($metadataField, $metadataValues) = $constraint['metadata'];
			$metadata = $this->getMetadataKey($metadataField, $metadataValues);

			if (is_array($metadata))
			{
				$output .= ' +(' . implode(' ', $metadata) . ')';
			}
			else
			{
				$output .= ' +' . $metadata;
			}
		}

		return trim($output);
	}

	/**
	 * @return Zend_Db_Adapter_Abstract
	 */
	protected function _getDb()
	{
		if ($this->_db === null)
		{
			$this->_db = XenForo_Application::get('db');
		}

		return $this->_db;
	}

	public static $stopWords = array(
		'a\'s', 'able', 'about', 'above', 'according', 'accordingly', 'across', 'actually',
		'after', 'afterwards', 'again', 'against', 'ain\'t', 'all', 'allow', 'allows',
		'almost', 'alone', 'along', 'already', 'also', 'although', 'always', 'am',
		'among', 'amongst', 'an', 'and', 'another', 'any', 'anybody', 'anyhow',
		'anyone', 'anything', 'anyway', 'anyways', 'anywhere', 'apart', 'appear', 'appreciate',
		'appropriate', 'are', 'aren\'t', 'around', 'as', 'aside', 'ask', 'asking',
		'associated', 'at', 'available', 'away', 'awfully', 'be', 'became', 'because',
		'become', 'becomes', 'becoming', 'been', 'before', 'beforehand', 'behind', 'being',
		'believe', 'below', 'beside', 'besides', 'best', 'better', 'between', 'beyond',
		'both', 'brief', 'but', 'by', 'c\'mon', 'c\'s', 'came', 'can',
		'can\'t', 'cannot', 'cant', 'cause', 'causes', 'certain', 'certainly', 'changes',
		'clearly', 'co', 'com', 'come', 'comes', 'concerning', 'consequently', 'consider',
		'considering', 'contain', 'containing', 'contains', 'corresponding', 'could', 'couldn\'t', 'course',
		'currently', 'definitely', 'described', 'despite', 'did', 'didn\'t', 'different', 'do',
		'does', 'doesn\'t', 'doing', 'don\'t', 'done', 'down', 'downwards', 'during',
		'each', 'edu', 'eg', 'eight', 'either', 'else', 'elsewhere', 'enough',
		'entirely', 'especially', 'et', 'etc', 'even', 'ever', 'every', 'everybody',
		'everyone', 'everything', 'everywhere', 'ex', 'exactly', 'example', 'except', 'far',
		'few', 'fifth', 'first', 'five', 'followed', 'following', 'follows', 'for',
		'former', 'formerly', 'forth', 'four', 'from', 'further', 'furthermore', 'get',
		'gets', 'getting', 'given', 'gives', 'go', 'goes', 'going', 'gone',
		'got', 'gotten', 'greetings', 'had', 'hadn\'t', 'happens', 'hardly', 'has',
		'hasn\'t', 'have', 'haven\'t', 'having', 'he', 'he\'s', 'hello', 'help',
		'hence', 'her', 'here', 'here\'s', 'hereafter', 'hereby', 'herein', 'hereupon',
		'hers', 'herself', 'hi', 'him', 'himself', 'his', 'hither', 'hopefully',
		'how', 'howbeit', 'however', 'i\'d', 'i\'ll', 'i\'m', 'i\'ve', 'ie',
		'if', 'ignored', 'immediate', 'in', 'inasmuch', 'inc', 'indeed', 'indicate',
		'indicated', 'indicates', 'inner', 'insofar', 'instead', 'into', 'inward', 'is',
		'isn\'t', 'it', 'it\'d', 'it\'ll', 'it\'s', 'its', 'itself', 'just',
		'keep', 'keeps', 'kept', 'know', 'known', 'knows', 'last', 'lately',
		'later', 'latter', 'latterly', 'least', 'less', 'lest', 'let', 'let\'s',
		'like', 'liked', 'likely', 'little', 'look', 'looking', 'looks', 'ltd',
		'mainly', 'many', 'may', 'maybe', 'me', 'mean', 'meanwhile', 'merely',
		'might', 'more', 'moreover', 'most', 'mostly', 'much', 'must', 'my',
		'myself', 'name', 'namely', 'nd', 'near', 'nearly', 'necessary', 'need',
		'needs', 'neither', 'never', 'nevertheless', 'new', 'next', 'nine', 'no',
		'nobody', 'non', 'none', 'noone', 'nor', 'normally', 'not', 'nothing',
		'novel', 'now', 'nowhere', 'obviously', 'of', 'off', 'often', 'oh',
		'ok', 'okay', 'old', 'on', 'once', 'one', 'ones', 'only',
		'onto', 'or', 'other', 'others', 'otherwise', 'ought', 'our', 'ours',
		'ourselves', 'out', 'outside', 'over', 'overall', 'own', 'particular', 'particularly',
		'per', 'perhaps', 'placed', 'please', 'plus', 'possible', 'presumably', 'probably',
		'provides', 'que', 'quite', 'qv', 'rather', 'rd', 're', 'really',
		'reasonably', 'regarding', 'regardless', 'regards', 'relatively', 'respectively', 'right', 'said',
		'same', 'saw', 'say', 'saying', 'says', 'second', 'secondly', 'see',
		'seeing', 'seem', 'seemed', 'seeming', 'seems', 'seen', 'self', 'selves',
		'sensible', 'sent', 'serious', 'seriously', 'seven', 'several', 'shall', 'she',
		'should', 'shouldn\'t', 'since', 'six', 'so', 'some', 'somebody', 'somehow',
		'someone', 'something', 'sometime', 'sometimes', 'somewhat', 'somewhere', 'soon', 'sorry',
		'specified', 'specify', 'specifying', 'still', 'sub', 'such', 'sup', 'sure',
		't\'s', 'take', 'taken', 'tell', 'tends', 'th', 'than', 'thank',
		'thanks', 'thanx', 'that', 'that\'s', 'thats', 'the', 'their', 'theirs',
		'them', 'themselves', 'then', 'thence', 'there', 'there\'s', 'thereafter', 'thereby',
		'therefore', 'therein', 'theres', 'thereupon', 'these', 'they', 'they\'d', 'they\'ll',
		'they\'re', 'they\'ve', 'think', 'third', 'this', 'thorough', 'thoroughly', 'those',
		'though', 'three', 'through', 'throughout', 'thru', 'thus', 'to', 'together',
		'too', 'took', 'toward', 'towards', 'tried', 'tries', 'truly', 'try',
		'trying', 'twice', 'two', 'un', 'under', 'unfortunately', 'unless', 'unlikely',
		'until', 'unto', 'up', 'upon', 'us', 'use', 'used', 'useful',
		'uses', 'using', 'usually', 'value', 'various', 'very', 'via', 'viz',
		'vs', 'want', 'wants', 'was', 'wasn\'t', 'way', 'we', 'we\'d',
		'we\'ll', 'we\'re', 'we\'ve', 'welcome', 'well', 'went', 'were', 'weren\'t',
		'what', 'what\'s', 'whatever', 'when', 'whence', 'whenever', 'where', 'where\'s',
		'whereafter', 'whereas', 'whereby', 'wherein', 'whereupon', 'wherever', 'whether', 'which',
		'while', 'whither', 'who', 'who\'s', 'whoever', 'whole', 'whom', 'whose',
		'why', 'will', 'willing', 'wish', 'with', 'within', 'without', 'won\'t',
		'wonder', 'would', 'wouldn\'t', 'yes', 'yet', 'you', 'you\'d', 'you\'ll',
		'you\'re', 'you\'ve', 'your', 'yours', 'yourself', 'yourselves', 'zero'
	);
}
