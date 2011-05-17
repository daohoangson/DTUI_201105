<?php

/**
 * Model for polls.
 *
 * @package XenForo_Poll
 */
class XenForo_Model_Poll extends XenForo_Model
{
	/**
	 * Gets the specified poll.
	 *
	 * @param integer $id
	 *
	 * @return array|false
	 */
	public function getPollById($id)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_poll
			WHERE poll_id = ?
		', $id);
	}

	/**
	 * Gets the specified poll by the content it belongs to.
	 *
	 * @param string $contentType
	 * @param integer $contentId
	 *
	 * @return array|false
	 */
	public function getPollByContent($contentType, $contentId)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_poll
			WHERE content_type = ?
				AND content_id = ?
		', array($contentType, $contentId));
	}

	/**
	 * Gets poll IDs starting from after the specified start, up to the given limit
	 *
	 * @param integer $start
	 * @param integer $limit
	 */
	public function getPollIdsInRange($start, $limit)
	{
		$db = $this->_getDb();

		return $db->fetchCol($db->limit('
			SELECT poll_id
			FROM xf_poll
			WHERE poll_id > ?
			ORDER BY poll_id
		', $limit), $start);
	}

	/**
	 * Gets poll response.
	 *
	 * @param integer $id
	 *
	 * @return array|false
	 */
	public function getPollResponseById($id)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_poll_response
			WHERE poll_response_id = ?
		', $id);
	}

	/**
	 * Gets all poll responses that belong to the specified poll.
	 *
	 * @param $pollId
	 *
	 * @return array [poll response id] => info
	 */
	public function getPollResponsesInPoll($pollId)
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_poll_response
			WHERE poll_id = ?
			ORDER BY poll_response_id
		', 'poll_response_id', $pollId);
	}

	/**
	 * Gets poll response cache for use in the poll table.
	 *
	 * @param integer $pollId
	 *
	 * @return array [poll response id] => [response, response_vote_count, voters]
	 */
	public function getPollResponseCache($pollId)
	{
		$responses = $this->getPollResponsesInPoll($pollId);
		$output = array();

		foreach ($responses AS $response)
		{
			$output[$response['poll_response_id']] = array(
				'response' => $response['response'],
				'response_vote_count' => $response['response_vote_count'],
				'voters' => unserialize($response['voters'])
			);
		}

		return $output;
	}

	/**
	 * Rebuilds the poll response cache in the specified poll.
	 *
	 * @param integer $pollId
	 *
	 * @return array The response cache
	 */
	public function rebuildPollResponseCache($pollId)
	{
		$cache = $this->getPollResponseCache($pollId);

		$db = $this->_getDb();
		$db->update('xf_poll',
			array('responses' => serialize($cache)),
			'poll_id = ' . $db->quote($pollId)
		);

		return $cache;
	}

	/**
	 * Prepares the poll responses for viewing from the poll record's response cache.
	 *
	 * @param array|string $responses Serialized array or array itself
	 * @param array|null $viewingUser
	 *
	 * @return array|false Responses prepared; false if responses can't be prepared
	 */
	public function preparePollResponsesFromCache($responses, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!is_array($responses))
		{
			$responses = unserialize($responses);
		}
		if (!is_array($responses))
		{
			return false;
		}

		foreach ($responses AS &$response)
		{
			$response['response'] = XenForo_Helper_String::censorString($response['response']);
			$response['hasVoted'] = isset($response['voters'][$viewingUser['user_id']]);
		}

		return $responses;
	}

	/**
	 * Prepares the poll for viewing.
	 *
	 * @param array $poll
	 * @param boolean $canVote If user can vote based on content-specified permissions
	 * @param array|null $viewingUser
	 *
	 * @return array
	 */
	public function preparePoll(array $poll, $canVote, array $viewingUser = null)
	{
		if (!is_array($poll['responses']))
		{
			$poll['responses'] = $this->preparePollResponsesFromCache($poll['responses'], $viewingUser);
		}
		if (!is_array($poll['responses']))
		{
			$poll['responses'] = $this->preparePollResponsesFromCache(
				$this->rebuildPollResponseCache($poll['poll_id']),
				$viewingUser
			);
		}

		$poll['hasVoted'] = false;
		foreach ($poll['responses'] AS $response)
		{
			if (!empty($response['hasVoted']))
			{
				$poll['hasVoted'] = true;
				break;
			}
		}

		$poll['open'] = (!$poll['close_date'] || $poll['close_date'] > XenForo_Application::$time);

		if (!$canVote || $poll['hasVoted'] || !$poll['open'])
		{
			$poll['canVote'] = false;
		}
		else
		{
			$poll['canVote'] = true;
		}

		$poll['question'] = XenForo_Helper_String::censorString($poll['question']);

		return $poll;
	}

	/**
	 * Determines if the viewing user can voe on the poll. This does not take into account
	 * content-specific permissions.
	 *
	 * @param array $poll
	 * @param string $errorPhraseKey
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canVoteOnPoll(array $poll, &$errorPhraseKey = '', array $viewingUser = null)
	{
		if ($poll['close_date'] && $poll['close_date'] < XenForo_Application::$time)
		{
			return false;
		}

		$this->standardizeViewingUserReference($viewingUser);

		if (!$viewingUser['user_id'])
		{
			return false;
		}

		$voted = $this->_getDb()->fetchRow('
			SELECT poll_response_id
			FROM xf_poll_vote
			WHERE poll_id = ?
				AND user_id = ?
		', array($poll['poll_id'], $viewingUser['user_id']));
		return ($voted ? false : true);
	}

	/**
	 * Votes on the specified poll.
	 *
	 * @param integer $pollId
	 * @param integer|array $votes One or more poll response IDs to vote on. This does not check if the poll allows multiple votes.
	 * @param integer|null $userId
	 * @param integer|null $voteDate
	 *
	 * @return boolean
	 */
	public function voteOnPoll($pollId, $votes, $userId = null, $voteDate = null)
	{
		if (!is_array($votes))
		{
			if (!$votes)
			{
				return false;
			}
			$votes = array($votes);
		}
		if (!$votes)
		{
			return false;
		}

		if ($userId === null)
		{
			$userId = XenForo_Visitor::getUserId();
		}
		if (!$userId)
		{
			return false;
		}

		if ($voteDate === null)
		{
			$voteDate = XenForo_Application::$time;
		}

		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		$responses = $this->getPollResponsesInPoll($pollId);

		foreach ($votes AS $voteResponseId)
		{
			if (isset($responses[$voteResponseId]))
			{
				$db->insert('xf_poll_vote', array(
					'user_id' => $userId,
					'poll_response_id' => $voteResponseId,
					'poll_id' => $pollId,
					'vote_date' => $voteDate
				));

				$voterCache = $this->getPollResponseVoterCache($voteResponseId);
				$db->query('
					UPDATE xf_poll_response SET
						response_vote_count = response_vote_count + 1,
						voters = ?
					WHERE poll_response_id = ?
				', array(serialize($voterCache), $voteResponseId));
			}
		}

		$pollDw = XenForo_DataWriter::create('XenForo_DataWriter_Poll');
		$pollDw->setExistingData($pollId);
		$pollDw->set('voter_count', $pollDw->get('voter_count') + 1);
		$pollDw->save();

		XenForo_Db::commit($db);

		return true;
	}

	public function getPollResponseVoterCache($pollResponseId)
	{
		return $this->fetchAllKeyed('
			SELECT poll_vote.user_id, user.username
			FROM xf_poll_vote AS poll_vote
			LEFT JOIN xf_user AS user ON (poll_vote.user_id = user.user_id)
			WHERE poll_vote.poll_response_id = ?
		', 'user_id', $pollResponseId);
	}

	public function getPollVoterCount($pollId)
	{
		return $this->_getDb()->fetchOne('
			SELECT COUNT(DISTINCT user_id)
			FROM xf_poll_vote
			WHERE poll_id = ?
		', $pollId);
	}

	public function rebuildPollData($pollId)
	{
		$db = $this->_getDb();

		$votes = array();
		$voters = array();
		$results = $db->query('
			SELECT poll_vote.poll_response_id, poll_vote.user_id, user.username
			FROM xf_poll_vote AS poll_vote
			LEFT JOIN xf_user AS user ON (poll_vote.user_id = user.user_id)
			WHERE poll_vote.poll_id = ?
		', $pollId);
		while ($vote = $results->fetch())
		{
			$votes[$vote['poll_response_id']][$vote['user_id']] = array(
				'user_id' => $vote['user_id'],
				'username' => $vote['username']
			);
			$voters[$vote['user_id']] = true;
		}

		$responses = $this->getPollResponsesInPoll($pollId);

		XenForo_Db::beginTransaction($db);

		foreach ($responses AS $responseId => $response)
		{
			if (!isset($votes[$responseId]))
			{
				$db->update('xf_poll_response', array(
					'response_vote_count' => 0,
					'voters' => ''
				), 'poll_response_id = ' . $db->quote($responseId));
			}
			else
			{
				$db->update('xf_poll_response', array(
					'response_vote_count' => count($votes[$responseId]),
					'voters' => serialize($votes[$responseId])
				), 'poll_response_id = ' . $db->quote($responseId));
			}
		}

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_Poll', XenForo_DataWriter::ERROR_SILENT);
		if ($dw->setExistingData($pollId))
		{
			$dw->set('voter_count', count($voters));
			$dw->set('responses', serialize($this->getPollResponseCache($pollId)));
			$dw->save();
		}

		XenForo_Db::commit($db);
	}
}