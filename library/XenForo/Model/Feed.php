<?php

class XenForo_Model_Feed extends XenForo_Model
{
	/**
	 * Valid values (seconds) for feed.frequency
	 *
	 * @var array
	 */
	protected static $_frequencyValues = array(
		600, // 10 mins
		1200, // 20 mins
		1800, // 30 mins
		3600, // 1 hour
		7200, // 2 hours
		14400, // 4 hours
		21600, // 6 hours
		43200, // 12 hours
	);

	/**
	 * Maximum number of entries to be fetched per-feed per-import.
	 *
	 * @var integer
	 */
	protected static $_maxEntriesPerImport = 5;

	/**
	 * Fetch all info for a single feed record.
	 *
	 * @param integer $feedId
	 *
	 * @return array
	 */
	public function getFeedById($feedId)
	{
		return $this->_getDb()->fetchRow('
			SELECT feed.*, IF(feed.active AND node.node_id, 1, 0) AS active,
				node.title AS node_title,
				user.username
			FROM xf_feed AS feed
			LEFT JOIN xf_node AS node ON
				(node.node_id = feed.node_id)
			LEFT JOIN xf_user AS user ON
				(user.user_id = feed.user_id)
			WHERE feed.feed_id = ?
		', $feedId);
	}

	/**
	 * Fetch feeds matching the parameters specified
	 *
	 * @param array $conditions
	 * @param array $fetchOptions (unused at present)
	 *
	 * @return array
	 */
	public function getFeeds(array $conditions = array(), array $fetchOptions = array())
	{
		$whereConditions = $this->prepareFeedConditions($conditions);

		return $this->fetchAllKeyed('
			SELECT feed.*, IF(feed.active AND node.node_id, 1, 0) AS active,
				node.title AS node_title,
				user.username
			FROM xf_feed AS feed
			LEFT JOIN xf_node AS node ON
				(node.node_id = feed.node_id)
			LEFT JOIN xf_user AS user ON
				(user.user_id = feed.user_id)
			WHERE ' . $whereConditions . '
			ORDER BY feed.title
		', 'feed_id');
	}

	/**
	 * Fetch all feed records with no conditions
	 *
	 * @return array
	 */
	public function getAllFeeds()
	{
		return $this->getFeeds();
	}

	/**
	 * Prepares the SQL WHERE conditions for getFeeds()
	 *
	 * @param array $conditions
	 *
	 * @return string
	 */
	public function prepareFeedConditions(array $conditions)
	{
		$sqlConditions = array();

		if (isset($conditions['time_now']))
		{
			$sqlConditions[] = "feed.last_fetch + feed.frequency < {$conditions['time_now']}";
		}

		if (isset($conditions['active']))
		{
			$sqlConditions[] = 'feed.active = ' . ($conditions['active'] ? 1 : 0) . ' AND node.node_id IS NOT NULL';
		}

		return $this->getConditionsForClause($sqlConditions);
	}

	/**
	 * Fetch all feeds that are due to be fetched,
	 * based on the current time and the last_fetch
	 * time for each feed with its frequency.
	 *
	 * @return array
	 */
	protected function _getDueFeeds()
	{
		return $this->getFeeds(array(
			'time_now' => XenForo_Application::$time,
			'active' => true,
		));
	}

	/**
	 * Returns an array containing the default non-empty data for a new feed
	 *
	 * @return array
	 */
	public function getDefaultFeedArray()
	{
		return array(
			// non-empty values
			'frequency' => 1800,
			'discussion_visible' => 1,
			'discussion_open' => 1,

			// empty values
			'title' => '',
			'url' => '',
			'node_id' => 0,
			'user_id' => 0,
			'title_template' => '',
			'message_template' => '{content}' . "\n\n" . '[url="{link}"]' . new XenForo_Phrase('continue_reading') . '[/url]',
			'discussion_sticky' => 0,
			'last_fetch' => 0
		);
	}

	/**
	 * Fetch the latest data for a feed from its specified URL.
	 * Individual entries are returned in the 'entries' key of the return array.
	 *
	 * @param string $url
	 * @param Exception|null $e Exception that occurs when reading feed
	 *
	 * @return array
	 */
	public function getFeedData($url, Exception &$e = null)
	{
		try
		{
			$feed = Zend_Feed_Reader::import($url);
		}
		catch (Exception $feedEx)
		{
			$e = $feedEx;
			return array();
		}

		$data = array(
			'id' => $feed->getId(),
			'title' => $feed->getTitle(),
			'link' => $feed->getLink(),
			'date_modified' => $feed->getDateModified(),
			'description' => $feed->getDescription(),
			'language' => $feed->getLanguage(),
			'image' => $feed->getImage(),
			'generator' => $feed->getGenerator(),
			'entries' => array()
		);

		foreach ($feed as $entry)
		{
			$entryData = array(
				'id' => $entry->getId(),
				'title' => html_entity_decode($entry->getTitle(), ENT_COMPAT, 'utf-8'),
				'description' => $entry->getDescription(),
				'date_modified' => null,
				'authors' => $entry->getAuthors(),
				'link' => $entry->getLink(),
				'content_html' => $entry->getContent()
			);

			if (utf8_strlen($entryData['id']) > 250)
			{
				$entryData['id'] = md5($entryData['id']);
			}

			try
			{
				$entryData['date_modified'] = $entry->getDateModified();
			}
			catch (Zend_Exception $e) {} // triggered with invalid date format

			if (!empty($entryData['date_modified']) && $entryData['date_modified'] instanceof Zend_Date)
			{
				$entryData['date_modified'] = $entryData['date_modified']->getTimeStamp();
			}
			else
			{
				$entryData['date_modified'] = XenForo_Application::$time;
			}

			$entryData['date_modified'] = XenForo_Locale::dateTime($entryData['date_modified'], 'absolute');

			$data['entries'][] = $entryData;
		}

		return $data;
	}

	/**
	 * Prepares data fetched from getFeedData() for use in posts
	 *
	 * @param array $feedData
	 * @param array $feed
	 *
	 * @return array
	 */
	public function prepareFeedData(array $feedData, array $feed)
	{
		$feed['baseUrl'] = $this->getFeedBaseUrl($feed['url']);

		foreach ($feedData['entries'] AS &$entry)
		{
			$entry = $this->prepareFeedEntry($entry, $feedData, $feed);
		}

		return $feedData;
	}

	/**
	 * Prepares the data from a single feed entry for use in posts
	 *
	 * @param array $entry
	 * @param array $feedData
	 * @param array $feed
	 *
	 * @return array
	 */
	public function prepareFeedEntry(array $entry, array $feedData, array $feed)
	{
		$entry['content'] = XenForo_Html_Renderer_BbCode::renderFromHtml($entry['content_html'], array(
			'baseUrl' => $feed['baseUrl']
		));

		$entry['author'] = $this->_getAuthorNamesFromArray($entry['authors']);

		if (empty($feed['message_template']))
		{
			$entry['message'] = $entry['content'];
		}
		else
		{
			$entry['message'] = $this->_replaceTokens($feed['message_template'], $entry);
		}

		$entry['message'] = trim($entry['message']);
		if ($entry['message'] === '')
		{
			$entry['message'] = '[url]' . $entry['link'] . '[/url]';
		}

		if (!empty($feed['title_template']))
		{
			$entry['title'] = $this->_replaceTokens($feed['title_template'], $entry);
		}

		return $entry;
	}

	/**
	 * Fetch the base URL for the feed
	 *
	 * @param string URL
	 *
	 * @return string Base URL
	 */
	public function getFeedBaseUrl($url)
	{
		return dirname($url);
	}

	/**
	 * Searches the given template string for {token} and replaces it with $entry[token]
	 *
	 * @param string $template
	 * @param array $entry
	 */
	protected function _replaceTokens($template, array $entry)
	{
		if (preg_match_all('/\{([a-z0-9_]+)\}/i', $template, $matches))
		{
			foreach ($matches[1] AS $token)
			{
				if (isset($entry[$token]))
				{
					$template = str_replace('{' . $token . '}', $entry[$token], $template);
				}
			}
		}

		return $template;
	}

	/**
	 * Attempts to convert a Zend_Feed_Reader_Collection_Author object
	 * into a comma-separated list of names.
	 *
	 * @param Zend_Feed_Reader_Collection_Author|null $feedAuthors
	 *
	 * @return string
	 */
	protected function _getAuthorNamesFromArray($feedAuthors)
	{
		$authorNames = array();

		if ($feedAuthors)
		{
			foreach ($feedAuthors AS $author)
			{
				if (isset($author['name']))
				{
					$authorNames[] = $author['name'];
				}
				else if (isset($author['email']))
				{
					$authorNames[] = $author['email'];
				}
			}
		}

		return implode(', ', $authorNames);
	}

	/**
	 * Returns the array of possible frequency values
	 *
	 * @return array
	 */
	public function getFrequencyValues()
	{
		return self::$_frequencyValues;
	}

	/**
	 * Checks the current feed data against a list of already-imported entries
	 * and removes any entries from the data that have already been imported.
	 *
	 * @param array $feedData
	 * @param array $feed
	 *
	 * @return array
	 */
	protected function _checkProcessedEntries(array $feedData, array $feed)
	{
		$ids = array();

		foreach ($feedData['entries'] AS $i => &$entry)
		{
			$ids[$entry['id']] = $i;

			$entry['hash'] = md5($entry['id'] . $entry['title'] . $entry['content_html']);
		}

		if (!$ids)
		{
			return $feedData;
		}

		$existing = $this->_getDb()->fetchCol('
			SELECT unique_id
			FROM xf_feed_log
			WHERE feed_id = ?
				AND unique_id IN (' . $this->_getDb()->quote(array_keys($ids)) . ')
		', $feed['feed_id']);

		foreach ($existing AS $id)
		{
			if (isset($ids[$id]))
			{
				unset($feedData['entries'][$ids[$id]]);
			}
		}

		$feedData['entries'] = $this->_limitEntries($feedData['entries'], self::$_maxEntriesPerImport);

		return $feedData;
	}

	/**
	 * Limits the number of entries in the given array to the number specified.
	 *
	 * @param array $entries
	 * @param integer $maxEntries
	 */
	protected function _limitEntries(array $entries, $maxEntries = 0)
	{
		if ($maxEntries)
		{
			return array_slice($entries, 0, $maxEntries, true);
		}
		else
		{
			return $entries;
		}
	}

	/**
	 * Inserts the data of a single feed entry
	 *
	 * @param array $entryData
	 * @param array $feedData
	 * @param array $feed
	 */
	protected function _insertFeedEntry(array $entryData, array $feedData, array $feed)
	{
		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		$writer = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread', XenForo_DataWriter::ERROR_SILENT);
		$writer->set('node_id', $feed['node_id']);
		$writer->set('discussion_state', $feed['discussion_visible'] ? 'visible' : 'moderated');
		$writer->set('discussion_open', $feed['discussion_open']);
		$writer->set('sticky', $feed['discussion_sticky']);
		$writer->set('title', XenForo_Helper_String::wholeWordTrim($entryData['title'], 95));
		$writer->set('user_id', $feed['user_id']);

		// TODO: The wholeWordTrim() used here may not be exactly ideal. Any better ideas?
		if ($feed['user_id'])
		{
			// post as the specified registered user
			$writer->set('username', $feed['username']);
		}
		else if ($entryData['author'])
		{
			// post as guest, using the author name(s) from the entry
			$writer->set('username', XenForo_Helper_String::wholeWordTrim($entryData['author'], 25, 0, ''));
		}
		else
		{
			// post as guest, using the feed title
			$writer->set('username', XenForo_Helper_String::wholeWordTrim($feed['title'], 25, 0, ''));
		}

		$postWriter = $writer->getFirstMessageDw();
		$postWriter->setOption(XenForo_DataWriter_DiscussionMessage::OPTION_IS_AUTOMATED, true);
		$postWriter->setOption(XenForo_DataWriter_DiscussionMessage::OPTION_VERIFY_GUEST_USERNAME, false);
		$postWriter->set('message', $entryData['message']);

		$writer->save();

		if ($writer->get('thread_id'))
		{
			$db->query('
				INSERT INTO xf_feed_log
					(feed_id, unique_id, hash, thread_id)
				VALUES
					(?, ?, ?, ?)
				ON DUPLICATE KEY UPDATE
					hash = VALUES(hash),
					thread_id = VALUES(thread_id)
			', array($feed['feed_id'], utf8_substr($entryData['id'], 0, 250), $entryData['hash'], $writer->get('thread_id')));
		}

		XenForo_Db::commit($db);
	}

	/**
	 * Inserts the data from a single feed.
	 *
	 * @param array $feedData
	 * @param array $feed
	 */
	protected function _insertFeedData(array $feedData, array $feed)
	{
		// insert feed data and update feed
		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		$feedData['entries'] = array_reverse($feedData['entries']); // post the newest stuff at the end

		foreach ($feedData['entries'] AS $entry)
		{
			$this->_insertFeedEntry($entry, $feedData, $feed);
		}

		// update feed
		$dw = XenForo_DataWriter::create('XenForo_DataWriter_Feed');
		$dw->setExistingData($feed['feed_id']);
		$dw->set('last_fetch', XenForo_Application::$time);
		$dw->save();

		XenForo_Db::commit($db);
	}

	/**
	 * Prepares and inserts the data from a single feed
	 *
	 * @param array $feed
	 */
	public function importFeedData(array $feed)
	{
		$feedData = $this->getFeedData($feed['url']);
		if (!$feedData)
		{
			return;
		}
		$feedData = $this->_checkProcessedEntries($feedData, $feed); // filter dupes
		$feedData = $this->prepareFeedData($feedData, $feed);

		$this->_insertFeedData($feedData, $feed);
	}

	/**
	 * Prepares and imports the data from all feeds due to be imported
	 */
	public function scheduledImport()
	{
		foreach ($this->_getDueFeeds() AS $feedId => $feed)
		{
			$this->importFeedData($feed);
		}
	}
}