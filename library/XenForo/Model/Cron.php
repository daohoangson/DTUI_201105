<?php

/**
 * Model for cron behaviors.
 *
 * @package XenForo_Cron
 */
class XenForo_Model_Cron extends XenForo_Model
{
	/**
	 * Gets the specified cron entry.
	 *
	 * @param string $id
	 *
	 * @return array|false
	 */
	public function getCronEntryById($id)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_cron_entry
			WHERE entry_id = ?
		', $id);
	}

	/**
	 * Prepares a list of cron entries for display.
	 *
	 * @param array $entries
	 *
	 * @return array
	 */
	public function prepareCronEntries(array $entries)
	{
		foreach ($entries AS &$entry)
		{
			$entry = $this->prepareCronEntry($entry);
		}

		return $entries;
	}

	/**
	 * Prepares the given cron entry for display, by doing processing beyond
	 * the DB, preparing the title phrase, etc.
	 *
	 * @param array $entry
	 *
	 * @return array
	 */
	public function prepareCronEntry(array $entry)
	{
		$entry['runRules'] = unserialize($entry['run_rules']);
		$entry['title'] = new XenForo_Phrase($this->getCronEntryPhraseName($entry['entry_id']));

		return $entry;
	}

	/**
	 * Gets the default, prepared cron entry for use on the insert entry form.
	 *
	 * @return array
	 */
	public function getDefaultCronEntry()
	{
		return array(
			'entry_id' => '',
			'cron_class' => '',
			'cron_method' => '',
			'run_rules' => '',
			'active' => 1,
			'next_run' => 0,
			'addon_id' => null, // must fail isset

			'runRules' => array(
				'minutes' => array(0),
				'hours' => array(0),
				'day_type' => 'dom',
				'dom' => array(-1)
			),
			'title' => ''
		);
	}

	/**
	 * Gets all cron entries, ordered by their next run time.
	 *
	 * @return array Format: [entry id] => info
	 */
	public function getAllCronEntries()
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_cron_entry
			ORDER BY next_run
		', 'entry_id');
	}

	/**
	 * Gets all cron entries that belong to the specified add-on,
	 * ordered by their entry IDs.
	 *
	 * @param string $addOnId
	 *
	 * @return array Format: [entry id] => info
	 */
	public function getCronEntriesByAddOnId($addOnId)
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_cron_entry
			WHERE addon_id = ?
			ORDER BY entry_id
		', 'entry_id', $addOnId);
	}

	/**
	 * Gets the named cron entries.
	 *
	 * @param array $ids List of cron entries by IDs
	 *
	 * @return array Format: [entry id] => info
	 */
	public function getCronEntriesByIds(array $ids)
	{
		if (!$ids)
		{
			return array();
		}

		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_cron_entry
			WHERE entry_id IN (' . $this->_getDb()->quote($ids) . ')
			ORDER BY entry_id
		', 'entry_id');
	}

	/**
	 * Gets all cron entries that need to be run.
	 *
	 * @param integer|null $currentTime Current timestamp, null to use current time from application
	 *
	 * @return array Format: [entry id] => info
	 */
	public function getCronEntriesToRun($currentTime = null)
	{
		$currentTime = ($currentTime === null ? XenForo_Application::$time : $currentTime);

		return $this->fetchAllKeyed('
			SELECT entry.*
			FROM xf_cron_entry AS entry
			LEFT JOIN xf_addon AS addon ON (entry.addon_id = addon.addon_id)
			WHERE entry.active = 1
				AND entry.next_run < ?
				AND (addon.addon_id IS NULL OR addon.active = 1)
			ORDER BY entry.next_run
		', 'entry_id', $currentTime);
	}

	/**
	 * Atomically update the next run time for a cron entry. This allows you
	 * to determine whehter a cron entry still needs to be run.
	 *
	 * @param array $entry Cron entry info
	 *
	 * @return boolean True if updated (thus safe to run), false otherwise
	 */
	public function updateCronRunTimeAtomic(array $entry)
	{
		$runRules = unserialize($entry['run_rules']);
		$nextRun = $this->calculateNextRunTime($runRules);

		$updateResult = $this->_getDb()->query('
			UPDATE xf_cron_entry
			SET next_run = ?
			WHERE entry_id = ?
				AND next_run = ?
		', array($nextRun, $entry['entry_id'], $entry['next_run']));

		if ($updateResult->rowCount())
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Calculate the next run time for an entry using the given rules. Rules expected in keys:
	 *    minutes, hours, dow, dom (all arrays) and day_type (string: dow or dom)
	 * Array rules are in format: -1 means "any", any other value means on those specific
	 * occurances. DoW runs 0 (Sunday) to 6 (Saturday).
	 *
	 * @param array $runRules Run rules. See above for format.
	 * @param integer|null $currentTime Current timestamp; null to use current time from application
	 *
	 * @return integer Next run timestamp
	 */
	public function calculateNextRunTime(array $runRules, $currentTime = null)
	{
		$currentTime = ($currentTime === null ? XenForo_Application::$time : $currentTime);

		$nextRun = new DateTime('@' . $currentTime);
		$nextRun->modify('+1 minute');

		if (empty($runRules['minutes']))
		{
			$runRules['minutes'] = array(-1);
		}
		$this->_modifyRunTimeMinutes($runRules['minutes'], $nextRun);

		if (empty($runRules['hours']))
		{
			$runRules['hours'] = array(-1);
		}
		$this->_modifyRunTimeHours($runRules['hours'], $nextRun);

		if (!empty($runRules['day_type']))
		{
			if ($runRules['day_type'] == 'dow')
			{
				if (empty($runRules['dow']))
				{
					$runRules['dow'] = array(-1);
				}
				$this->_modifyRunTimeDayOfWeek($runRules['dow'], $nextRun);
			}
			else
			{
				if (empty($runRules['dom']))
				{
					$runRules['dom'] = array(-1);
				}
				$this->_modifyRunTimeDayOfMonth($runRules['dom'], $nextRun);
			}
		}

		return intval($nextRun->format('U'));
	}

	/**
	 * Modifies the next run time based on the minute rules.
	 *
	 * @param array $minuteRules Rules about what minutes are valid (-1, or any number of values 0-59)
	 * @param DateTime $nextRun Date calculation object. This will be modified.
	 */
	protected function _modifyRunTimeMinutes(array $minuteRules, DateTime &$nextRun)
	{
		$currentMinute = $nextRun->format('i');
		$this->_modifyRunTimeUnits($minuteRules, $nextRun, $currentMinute, 'minute', 'hour');
	}

	/**
	 * Modifies the next run time based on the hour rules.
	 *
	 * @param array $hourRules Rules about what hours are valid (-1, or any number of values 0-23)
	 * @param DateTime $nextRun Date calculation object. This will be modified.
	 */
	protected function _modifyRunTimeHours(array $hourRules, DateTime &$nextRun)
	{
		$currentHour = $nextRun->format('G');
		$this->_modifyRunTimeUnits($hourRules, $nextRun, $currentHour, 'hour', 'day');
	}

	/**
	 * Modifies the next run time based on the day of month rules. Note that if
	 * the required DoM doesn't exist (eg, Feb 30), it will be rolled over as if
	 * it did (eg, to Mar 2).
	 *
	 * @param array $hourRules Rules about what days are valid (-1, or any number of values 0-31)
	 * @param DateTime $nextRun Date calculation object. This will be modified.
	 */
	protected function _modifyRunTimeDayOfMonth(array $dayRules, DateTime &$nextRun)
	{
		$currentDay = $nextRun->format('j');
		$this->_modifyRunTimeUnits($dayRules, $nextRun, $currentDay, 'day', 'month');
	}

	/**
	 * Modifies the next run time based on the day of week rules.
	 *
	 * @param array $hourRules Rules about what days are valid (-1, or any number of values 0-6 [sunday to saturday])
	 * @param DateTime $nextRun Date calculation object. This will be modified.
	 */
	protected function _modifyRunTimeDayOfWeek(array $dayRules, DateTime &$nextRun)
	{
		$currentDay = $nextRun->format('w'); // 0 = sunday, 6 = saturday
		$this->_modifyRunTimeUnits($dayRules, $nextRun, $currentDay, 'day', 'week');
	}

	/**
	 * General purpose run time calculator for a set of rules.
	 *
	 * @param array $unitRules List of rules for unit. Array of ints, values -1 to unit-defined max.
	 * @param DateTime $nextRun Date calculation object. This will be modified.
	 * @param integer $currentUnitValue The current value for the specified unit type
	 * @param string $unitName Name of the current unit (eg, minute, hour, day, etc)
	 * @param string $rolloverUnitName Name of the unit to use when rolling over; one unit bigger (eg, minutes to hours)
	 */
	protected function _modifyRunTimeUnits(array $unitRules, DateTime &$nextRun, $currentUnitValue, $unitName, $rolloverUnitName)
	{
		if (sizeof($unitRules) && reset($unitRules) == -1)
		{
			// correct already
			return;
		}

		$currentUnitValue = intval($currentUnitValue);
		$rollover = null;

		sort($unitRules, SORT_NUMERIC);
		foreach ($unitRules AS $unitValue)
		{
			if ($unitValue == -1 || $unitValue == $currentUnitValue)
			{
				// already in correct position
				$rollover = null;
				break;
			}
			else if ($unitValue > $currentUnitValue)
			{
				// found unit later in date, adjust to time
				$nextRun->modify('+ ' . ($unitValue - $currentUnitValue) . " $unitName");
				$rollover = null;
				break;
			}
			else if ($rollover == null)
			{
				// found unit earlier in the date; use smallest value
				$rollover = $unitValue;
			}
		}

		if ($rollover !== null)
		{
			$nextRun->modify(($rollover - $currentUnitValue) . " $unitName");
			$nextRun->modify("+ 1 $rolloverUnitName");
		}
	}

	/**
	 * Runs the given entry if possible.
	 *
	 * @param array $entry Info about cron entry
	 */
	public function runEntry(array $entry)
	{
		if (XenForo_Application::autoload($entry['cron_class']) && method_exists($entry['cron_class'], $entry['cron_method']))
		{
			call_user_func(array($entry['cron_class'], $entry['cron_method']));
		}
	}

	/**
	 * Gets the minimum next run time stamp (ie, time next entry is due to run).
	 * If no entries are runnable, returns 0x7FFFFFFF (basically never run an entry).
	 *
	 * @return integer
	 */
	public function getMinimumNextRunTime()
	{
		$nextRunTime = $this->_getDb()->fetchOne('
			SELECT MIN(entry.next_run)
			FROM xf_cron_entry AS entry
			LEFT JOIN xf_addon AS addon ON (entry.addon_id = addon.addon_id)
			WHERE entry.active = 1
				AND (addon.addon_id IS NULL OR addon.active = 1)
		');
		if ($nextRunTime)
		{
			return $nextRunTime;
		}
		else
		{
			return 0x7FFFFFFF; // no entries to run, return time well in future
		}
	}

	/**
	 * Updates the data registry entry for the minimum next run time.
	 * Cron calls are not needed until that point.
	 *
	 * @return integer Minimum next run time
	 */
	public function updateMinimumNextRunTime()
	{
		$minimumRunTime = intval($this->getMinimumNextRunTime());

		$dataRegistryModel = $this->_getDataRegistryModel();
		$dataRegistryModel->set('cron', $minimumRunTime);

		return $minimumRunTime;
	}

	/**
	 * Gets the phrase name for a cron entry.
	 *
	 * @param string $entryId
	 *
	 * @return string
	 */
	public function getCronEntryPhraseName($entryId)
	{
		return 'cron_entry_' . $entryId;
	}

	/**
	 * Gets a cron entry's master title phrase text.
	 *
	 * @param string $entryId
	 *
	 * @return string
	 */
	public function getCronEntryMasterTitlePhraseValue($entryId)
	{
		$phraseName = $this->getCronEntryPhraseName($entryId);
		return $this->_getPhraseModel()->getMasterPhraseValue($phraseName);
	}

	/**
	 * Gets the file name for the development output.
	 *
	 * @return string
	 */
	public function getCronDevelopmentFileName()
	{
		$config = XenForo_Application::get('config');
		if (!$config->debug || !$config->development->directory)
		{
			return '';
		}

		return XenForo_Application::getInstance()->getRootDir()
			. '/' . $config->development->directory . '/file_output/cron.xml';
	}

	/**
	 * Gets the DOM document that represents the cron development file.
	 * This must be turned into XML (or HTML) by the caller.
	 *
	 * @return DOMDocument
	 */
	public function getCronDevelopmentXml()
	{
		$document = new DOMDocument('1.0', 'utf-8');
		$document->formatOutput = true;
		$rootNode = $document->createElement('cron');
		$document->appendChild($rootNode);

		$this->appendCronEntriesAddOnXml($rootNode, 'XenForo');

		return $document;
	}

	/**
	 * Appends the add-on cron entry XML to a given DOM element.
	 *
	 * @param DOMElement $rootNode Node to append all prefix elements to
	 * @param string $addOnId Add-on ID to be exported
	 */
	public function appendCronEntriesAddOnXml(DOMElement $rootNode, $addOnId)
	{
		$entries = $this->getCronEntriesByAddOnId($addOnId);

		$document = $rootNode->ownerDocument;

		foreach ($entries AS $entry)
		{
			$runRules = unserialize($entry['run_rules']);

			$entryNode = $document->createElement('entry');
			$entryNode->setAttribute('entry_id', $entry['entry_id']);
			$entryNode->setAttribute('cron_class', $entry['cron_class']);
			$entryNode->setAttribute('cron_method', $entry['cron_method']);
			$entryNode->setAttribute('active', $entry['active']);
			$entryNode->appendChild($document->createCDATASection(json_encode($runRules)));

			$rootNode->appendChild($entryNode);
		}
	}

	/**
	 * Deletes the cron entries that belong to the specified add-on.
	 *
	 * @param string $addOnId
	 */
	public function deleteCronEntriesForAddOn($addOnId)
	{
		$db = $this->_getDb();
		$db->delete('xf_cron_entry', 'addon_id = ' . $db->quote($addOnId));
	}

	/**
	 * Imports prefixes from the development XML format. This will overwrite all prefixes.
	 *
	 * @param string $fileName
	 */
	public function importCronDevelopmentXml($fileName)
	{
		$document = new SimpleXMLElement($fileName, 0, true);
		$this->importCronEntriesAddOnXml($document, 'XenForo');
	}

	/**
	 * Imports the cron entries for an add-on.
	 *
	 * @param SimpleXMLElement $xml XML element pointing to the root of the event data
	 * @param string $addOnId Add-on to import for
	 */
	public function importCronEntriesAddOnXml(SimpleXMLElement $xml, $addOnId)
	{
		$db = $this->_getDb();

		$addonEntries = $this->getCronEntriesByAddOnId($addOnId);

		XenForo_Db::beginTransaction($db);
		$this->deleteCronEntriesForAddOn($addOnId);

		$xmlEntries = XenForo_Helper_DevelopmentXml::fixPhpBug50670($xml->entry);

		$entryIds = array();
		foreach ($xmlEntries AS $entry)
		{
			$entryIds[] = (string)$entry['entry_id'];
		}

		$entries = $this->getCronEntriesByIds($entryIds);

		foreach ($xmlEntries AS $entry)
		{
			$entryId = (string)$entry['entry_id'];

			$dw = XenForo_DataWriter::create('XenForo_DataWriter_CronEntry');
			if (isset($entries[$entryId]))
			{
				$dw->setExistingData($entries[$entryId]);
			}

			if (isset($addonEntries[$entryId]))
			{
				$active = $addonEntries[$entryId]['active'];
			}
			else
			{
				$active = (string)$entry['active'];
			}

			$dw->setOption(XenForo_DataWriter_CronEntry::OPTION_REBUILD_CACHE, false);
			$dw->bulkSet(array(
				'entry_id' => $entryId,
				'cron_class' => (string)$entry['cron_class'],
				'cron_method' => (string)$entry['cron_method'],
				'active' => $active,
				'run_rules' => json_decode((string)$entry, true),
				'addon_id' => $addOnId
			));
			$dw->save();
		}

		$this->updateMinimumNextRunTime();

		XenForo_Db::commit($db);
	}

	/**
	 * @return XenForo_Model_Phrase
	 */
	protected function _getPhraseModel()
	{
		return $this->getModelFromCache('XenForo_Model_Phrase');
	}
}