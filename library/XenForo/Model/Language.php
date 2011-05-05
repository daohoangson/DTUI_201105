<?php

class XenForo_Model_Language extends XenForo_Model
{
	public function getLanguageById($id, $fetchMaster = false)
	{
		if (strval($id) === '0')
		{
			if ($fetchMaster)
			{
				return array(
					'language_id' => 0,
					'title' => new XenForo_Phrase('master_language'),
					'parent_id' => 0
				);
			}
			else
			{
				return false;
			}
		}

		$localCacheKey = 'language_' . $id;
		if (($data = $this->_getLocalCacheData($localCacheKey)) === false)
		{
			$data = $this->_getDb()->fetchRow('
				SELECT *
				FROM xf_language
				WHERE language_id = ?
			', $id);

			$this->setLocalCacheData($localCacheKey, $data);
		}

		return $data;
	}

	public function getAllLanguages()
	{
		if (($languages = $this->_getLocalCacheData('allLanguages')) === false)
		{
			$languages = $this->fetchAllKeyed('
				SELECT *
				FROM xf_language
				ORDER BY title
			', 'language_id');

			$this->setLocalCacheData('allLanguages', $languages);
		}

		return $languages;
	}

	/**
	 * Gets the parent => language associations from a flat list of language info.
	 *
	 * @param array $languageList
	 *
	 * @return array [parent id][] => language id
	 */
	public function getLanguageTreeAssociations(array $languageList)
	{
		$parents = array();
		foreach ($languageList AS $language)
		{
			$parents[$language['parent_id']][] = $language['language_id'];
		}

		return $parents;
	}

	/**
	 * Gets a list of child language IDs that are direct children of the specified language.
	 *
	 * @param integer $languageId
	 *
	 * @return array Array of language IDs
	 */
	public function getDirectChildLanguageIds($languageId)
	{
		$languages = $this->getAllLanguages();
		$languageTree = $this->getLanguageTreeAssociations($languages);

		if (isset($languageTree[$languageId]))
		{
			return $languageTree[$languageId];
		}
		else
		{
			return array();
		}
	}

	/**
	 * Gets all children of a language ID, no matter how many levels below.
	 *
	 * @param integer $languageId
	 *
	 * @return array Array of language IDs
	 */
	public function getAllChildLanguageIds($languageId)
	{
		$languages = $this->getAllLanguages();
		$languageTree = $this->getLanguageTreeAssociations($languages);

		if (isset($languageTree[$languageId]))
		{
			return $this->_getAllChildLanguageIds($languageId, $languageTree);
		}
		else
		{
			return array();
		}
	}

	/**
	 * Internal handler to get call child language IDs.
	 *
	 * @param integer $parentId Parent language ID
	 * @param array $languageTree Tree of languages ([parent id][] => language id)
	 *
	 * @return array
	 */
	protected function _getAllChildLanguageIds($parentId, array $languageTree)
	{
		if (!isset($languageTree[$parentId]))
		{
			return array();
		}

		$children = array();
		foreach ($languageTree[$parentId] AS $childId)
		{
			$children[] = $childId;
			$children = array_merge($children, $this->_getAllChildLanguageIds($childId, $languageTree));
		}
	}

	/**
	 * Gets a list of languages in the form of a flattened tree. The return
	 * is an array containing all languages and their related info. Each language
	 * additionally includes a "depth" element that repesents the depth from
	 * the (implicit) master. Children of the master have a depth 0, unless
	 * $baseDepth is overridden.
	 *
	 * @param integer $baseDepth Starting depth value.
	 *
	 * @return array Format: [language id] => (array) language info, including depth
	 */
	public function getAllLanguagesAsFlattenedTree($baseDepth = 0)
	{
		$languages = $this->getAllLanguages();
		$tree = $this->getLanguageTreeAssociations($languages);

		return $this->_buildFlattenedLanguageTree($languages, $tree, 0, $baseDepth);
	}

	/**
	 * Returns an array of all languages, suitable for use in ACP template syntax as options source.
	 *
	 * @param array $languageTree
	 *
	 * @return array
	 */
	public function getLanguagesForOptionsTag($selectedId = null, $languageTree = null)
	{
		if ($languageTree === null)
		{
			$languageTree = $this->getAllLanguagesAsFlattenedTree();
		}

		$languages = array();
		foreach ($languageTree AS $id => $language)
		{
			$languages[$id] = array(
				'value' => $id,
				'label' => $language['title'],
				'selected' => ($selectedId == $id),
				'depth' => $language['depth']
			);
		}

		return $languages;
	}

	/**
	 * Builds the flattened tree recursively, incrementing the depth each time.
	 *
	 * @param array $languageList List of languages and their information
	 * @param array $tree Tree structure of languages ([parent id][] => language id)
	 * @param integer $root Where to start in the tree
	 * @param integer $depth Current/starting depth
	 *
	 * @return array List of languages with additional depth key
	 */
	protected function _buildFlattenedLanguageTree(array $languageList, array $tree, $root = 0, $depth = 0)
	{
		if (!isset($tree[$root]) || !is_array($tree[$root]))
		{
			return array();
		}

		$output = array();

		foreach ($tree[$root] AS $languageId)
		{
			$output[$languageId] = $languageList[$languageId];
			$output[$languageId]['depth'] = $depth;

			$output += $this->_buildFlattenedLanguageTree($languageList, $tree, $languageId, $depth + 1);
		}

		return $output;
	}

	/**
	 * Gets the base parent list for a language. This list starts with the *parent* of the given language ID, then
	 * works up the tree, eventually ending with 0.
	 *
	 * @param integer $languageId
	 *
	 * @return array List of parent language IDs (including 0)
	 */
	public function getLanguageBaseParentList($languageId)
	{
		$languages = $this->getAllLanguages();

		$parents = array();
		while (isset($languages[$languageId]) && $language = $languages[$languageId])
		{
			$parents[] = $language['parent_id'];
			$languageId = $language['parent_id'];
		}

		return $parents;
	}

	/**
	 * Recursively rebuilds the parent list in part of the language tree.
	 *
	 * @param integer $languageId First language to start with. All child will be rebuild.
	 */
	public function rebuildLanguageParentListRecursive($languageId)
	{
		$languages = $this->getAllLanguages();

		if (isset($languages[$languageId]))
		{
			$languageTree = $this->getLanguageTreeAssociations($languages);

			$baseParentList = $this->getLanguageBaseParentList($languageId);
			$this->_rebuildLanguageParentListRecursive($languageId, $baseParentList, $languages, $languageTree);
		}
	}

	/**
	 * Internal function to rebuild the language parent list recursively.
	 *
	 * @param integer $languageId Base lanaguage Id
	 * @param array $baseParentList Base parent list for the language. Should not include this language ID in it.
	 * @param array $languages List of languages
	 * @param array $languageTree Language tree
	 */
	protected function _rebuildLanguageParentListRecursive($languageId, array $baseParentList, array $languages, array $languageTree)
	{
		if (isset($languages[$languageId]))
		{
			$parentList = $baseParentList;
			array_unshift($parentList, $languageId);

			$db = $this->_getDb();
			$db->update(
				'xf_language',
				array('parent_list' => implode(',', $parentList)),
				'language_id = ' . $db->quote($languageId)
			);

			if (isset($languageTree[$languageId]))
			{
				foreach ($languageTree[$languageId] AS $childLanguageId)
				{
					$this->_rebuildLanguageParentListRecursive($childLanguageId, $parentList, $languages, $languageTree);
				}
			}
		}
	}

	/**
	 * Rebuilds the global phrase cache for all languages.
	 *
	 * @param array|null $globalPhraseTitles List of phrases that should be included. If null, uses flag from phrase table.
	 */
	public function rebuildLanguageGlobalPhraseCache(array $globalPhraseTitles = null)
	{
		$phraseModel = $this->_getPhraseModel();

		if ($globalPhraseTitles === null)
		{
			$globalPhraseTitles = $phraseModel->getGlobalPhraseCacheTitles();
		}

		$db = $this->_getDb();

		$languages = $this->getAllLanguages();
		$globalPhrases = $phraseModel->getEffectivePhraseValuesInAllLanguages($globalPhraseTitles);

		foreach ($languages AS $languageId => $language)
		{
			if (isset($globalPhrases[$languageId]))
			{
				$phrases = $globalPhrases[$languageId];
			}
			else
			{
				$phrases = array();
			}

			$db->update('xf_language',
				array('phrase_cache' => serialize($phrases)),
				'language_id = ' . $db->quote($languageId)
			);
		}
	}

	/**
	* Helper to determine whether the master language should be shown in lists.
	*
	* @return boolean
	*/
	public function showMasterLanguage()
	{
		return XenForo_Application::debugMode();
	}

	/**
	* Helper to get the default language
	*
	* @return array
	*/
	public function getDefaultLanguage()
	{
		return array(
			'language_id' => 0,
			'parent_id' => 0,
			'parent_list' => '',
			'title' => '',
			'date_format' => 'M j, Y',
			'time_format' => 'g:i A',
			'decimal_point' => '.',
			'thousands_separator' => ','
		);
	}

	/**
	 * Gets all languages in the format expected by the language cache.
	 *
	 * @return array Format: [language id] => info, with phrase cache as array
	 */
	public function getAllLanguagesForCache()
	{
		$this->resetLocalCacheData('allLanguages');

		$languages = $this->getAllLanguages();
		foreach ($languages AS &$language)
		{
			$language['phrase_cache'] = unserialize($language['phrase_cache']);
		}

		return $languages;
	}

	/**
	 * Rebuilds the full language cache.
	 *
	 * @return array Format: [language id] => info, with phrase cache as array
	 */
	public function rebuildLanguageCache()
	{
		$this->resetLocalCacheData('allLanguages');

		$languages = $this->getAllLanguagesForCache();

		$this->_getDataRegistryModel()->set('languages', $languages);

		return $languages;
	}

	/**
	 * Rebuilds all language caches (including global phrase cache).
	 */
	public function rebuildLanguageCaches()
	{
		$this->rebuildLanguageGlobalPhraseCache();
		$this->rebuildLanguageCache();
	}

	/**
	 * Generates the date and time format examples based on the
	 * current time.
	 *
	 * @return array [0] => date formats, [1] => time formats; keyed by format string
	 */
	public function getLanguageFormatExamples()
	{
		$dateFormatsRaw = array(
			'M j, Y',
			'F j, Y',
			'j M Y',
			'j F Y',
			'j/n/y',
			'n/j/y'
		);

		$dateFormats = array();
		foreach ($dateFormatsRaw AS $dateFormat)
		{
			$dateFormats[$dateFormat] = XenForo_Locale::getFormattedDate(XenForo_Application::$time, $dateFormat);
		}

		$timeFormatsRaw = array(
			'g:i A',
			'H:i'
		);

		$timeFormats = array();
		foreach ($timeFormatsRaw AS $timeFormat)
		{
			$timeFormats[$timeFormat] = XenForo_Locale::getFormattedDate(XenForo_Application::$time, $timeFormat);
		}

		return array($dateFormats, $timeFormats);
	}

	/**
	 * Returns the total number of phrases in the master language
	 *
	 * @return integer
	 */
	public function countMasterPhrases()
	{
		return $this->_getDb()->fetchOne('SELECT COUNT(*) FROM xf_phrase WHERE language_id = 0');
	}

	/**
	 * Counts the number of translated phrases in each non-master language
	 *
	 * @param array $languages Array of languages
	 *
	 * @return array The $languages array including a phraseCount key
	 */
	public function countTranslatedPhrasesPerLanguage(array $languages = array())
	{
		$totals = $this->_getDb()->fetchPairs('
			SELECT language_id, COUNT(*) AS phraseCount
			FROM xf_phrase
			WHERE language_id <> 0
			GROUP BY language_id
		');

		foreach ($totals AS $languageId => $phraseCount)
		{
			if (isset($languages[$languageId]))
			{
				$languages[$languageId]['phraseCount'] = $phraseCount;
			}
		}

		return $languages;
	}

	/**
	 * Gets the DOM document that represents a language file.
	 * This must be turned into XML (or HTML) by the caller.
	 *
	 * @param array $language
	 * @param string|null $limitAddOnId If specified, only exports phrases from the specified add-on
	 * @param boolean $getUntranslated If true, gets untranslated phrases in this language
	 *
	 * @return DOMDocument
	 */
	public function getLanguageXml(array $language, $limitAddOnId = null, $getUntranslated = false)
	{
		$document = new DOMDocument('1.0', 'utf-8');
		$document->formatOutput = true;

		$rootNode = $document->createElement('language');
		$rootNode->setAttribute('title', $language['title']);
		$rootNode->setAttribute('date_format', $language['date_format']);
		$rootNode->setAttribute('time_format', $language['time_format']);
		$rootNode->setAttribute('decimal_point', $language['decimal_point']);
		$rootNode->setAttribute('thousands_separator', $language['thousands_separator']);
		$rootNode->setAttribute('language_code', $language['language_code']);
		if ($limitAddOnId !== null)
		{
			$rootNode->setAttribute('addon_id', $limitAddOnId);
		}
		$document->appendChild($rootNode);

		$db = $this->_getDb();

		if ($getUntranslated)
		{
			$phrases = $db->fetchAll('
				SELECT phrase.*,
					IF(master.phrase_id, master.addon_id, phrase.addon_id) AS addon_id
				FROM xf_phrase_map AS map
				INNER JOIN xf_phrase AS phrase ON (map.phrase_id = phrase.phrase_id)
				LEFT JOIN xf_phrase AS master ON (master.title = phrase.title AND master.language_id = 0)
				WHERE map.language_id = ?
					AND ' . ($limitAddOnId === null ? '1=1' : 'master.addon_id = ' . $db->quote($limitAddOnId)) . '
				ORDER BY map.title
			', $language['language_id']);
		}
		else
		{
			$phrases = $db->fetchAll('
				SELECT phrase.*,
					IF(master.phrase_id, master.addon_id, phrase.addon_id) AS addon_id
				FROM xf_phrase AS phrase
				LEFT JOIN xf_phrase AS master ON (master.title = phrase.title AND master.language_id = 0)
				WHERE phrase.language_id = ?
					AND ' . ($limitAddOnId === null ? '1=1' : 'master.addon_id = ' . $db->quote($limitAddOnId)) . '
				ORDER BY phrase.title
			', $language['language_id']);
		}

		foreach ($phrases AS $phrase)
		{
			$phraseNode = $document->createElement('phrase');
			$phraseNode->setAttribute('title', $phrase['title']);
			$phraseNode->setAttribute('addon_id', $phrase['addon_id']);
			if ($phrase['global_cache'])
			{
				$phraseNode->setAttribute('global_cache', $phrase['global_cache']);
			}
			$phraseNode->setAttribute('version_id', $phrase['version_id']);
			$phraseNode->setAttribute('version_string', $phrase['version_string']);
			$phraseNode->appendChild($document->createCDATASection($phrase['phrase_text']));

			$rootNode->appendChild($phraseNode);
		}

		return $document;
	}

	/**
	 * Imports a language XML file.
	 *
	 * @param SimpleXMLElement $document
	 * @param integer $parentLanguageId If not overwriting, the ID of the parent lang
	 * @param integer $overwriteLanguageId If non-0, parent lang is ignored
	 *
	 * @return array List of cache rebuilders to run
	 */
	public function importLanguageXml(SimpleXMLElement $document, $parentLanguageId = 0, $overwriteLanguageId = 0)
	{
		if ($document->getName() != 'language')
		{
			throw new XenForo_Exception(new XenForo_Phrase('provided_file_is_not_valid_language_xml'), true);
		}

		$title = (string)$document['title'];
		if ($title === '')
		{
			throw new XenForo_Exception(new XenForo_Phrase('provided_file_is_not_valid_language_xml'), true);
		}

		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		$limitAddOnId = (string)$document['addon_id'];

		if ($overwriteLanguageId)
		{
			$db->delete('xf_phrase',
				'language_id = ' . $db->quote($overwriteLanguageId)
					. ($limitAddOnId != '' ? ' AND addon_id = ' . $db->quote($limitAddOnId) : '')
			);

			$existingPhrases = $this->_getPhraseModel()->getAllPhrasesInLanguage($overwriteLanguageId);
		}
		else
		{
			$existingPhrases = array();
		}

		$writer = XenForo_DataWriter::create('XenForo_DataWriter_Language');
		if ($overwriteLanguageId)
		{
			$writer->setExistingData($overwriteLanguageId);
		}
		else
		{
			$writer->set('title', (string)$document['title']);
			$writer->set('parent_id', $parentLanguageId);
		}

		$writer->bulkSet(array(
			'date_format' => (string)$document['date_format'],
			'time_format' => (string)$document['time_format'],
			'decimal_point' => (string)$document['decimal_point'],
			'thousands_separator' => (string)$document['thousands_separator'],
			'language_code' => (string)$document['language_code'],
		));
		$writer->save();

		$languageId = $writer->get('language_id');

		$this->_getPhraseModel()->importPhrasesXml($document, $languageId, null, $existingPhrases);

		XenForo_Db::commit($db);

		return array('Phrase', 'Template', 'AdminTemplate', 'EmailTemplate');
	}

	/**
	 * @return XenForo_Model_Phrase
	 */
	protected function _getPhraseModel()
	{
		return $this->getModelFromCache('XenForo_Model_Phrase');
	}
}