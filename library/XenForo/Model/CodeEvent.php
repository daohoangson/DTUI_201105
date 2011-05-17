<?php

/**
 * Model to work with code events.
 *
 * @package XenForo_CodeEvents
 */
class XenForo_Model_CodeEvent extends XenForo_Model
{
	/**
	 * Gets all code events, ordered by their event IDs.
	 *
	 * @return array Format: [event id] => info
	 */
	public function getAllEvents()
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_code_event
			ORDER BY event_id
		', 'event_id');
	}

	/**
	 * Gets all events that belong to an add-on, ordered by their event IDs.
	 *
	 * @param string $addOnId
	 *
	 * @return array Format: [event id] => info
	 */
	public function getEventsByAddOn($addOnId)
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_code_event
			WHERE addon_id = ?
			ORDER BY event_id
		', 'event_id', $addOnId);
	}

	/**
	 * Gets the specified code event based on its ID.
	 *
	 * @param string $id
	 *
	 * @return array|false
	 */
	public function getEventById($id)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_code_event
			WHERE event_id = ?
		', $id);
	}

	/**
	 * Gets multiple code events based on a list of IDs.
	 *
	 * @param array $ids Event IDs
	 *
	 * @return array Format: [event id] => info
	 */
	public function getEventsByIds(array $ids)
	{
		if (!$ids)
		{
			return array();
		}

		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_code_event
			WHERE event_id IN (' . $this->_getDb()->quote($ids) . ')
		', 'event_id');
	}

	/**
	 * Gets all code events as key-value pair options.
	 *
	 * @return array Format: [event id] => event id
	 */
	public function getEventOptions()
	{
		return $this->_getDB()->fetchPairs('
			SELECT event_id, event_id
			FROM xf_code_event
			ORDER BY event_id
		');
	}

	/**
	 * Gets the file name for the development output.
	 *
	 * @return string
	 */
	public function getEventsDevelopmentFileName()
	{
		$config = XenForo_Application::get('config');
		if (!$config->debug || !$config->development->directory)
		{
			return '';
		}

		return XenForo_Application::getInstance()->getRootDir()
			. '/' . $config->development->directory . '/file_output/code_events.xml';
	}

	/**
	 * Determines if the option development file is writable. If the file
	 * does not exist, it checks whether the parent directory is writable.
	 *
	 * @param $fileName
	 *
	 * @return boolean
	 */
	public function canWriteEventsDevelopmentFile($fileName)
	{
		return file_exists($fileName) ? is_writable($fileName) : is_writable(dirname($fileName));
	}

	/**
	 * Gets the code events development XML data.
	 *
	 * @return DOMDocument
	 */
	public function getEventsDevelopmentXml()
	{
		$document = new DOMDocument('1.0', 'utf-8');
		$document->formatOutput = true;
		$rootNode = $document->createElement('code_events');
		$document->appendChild($rootNode);

		$this->appendEventsAddOnXml($rootNode, 'XenForo');

		return $document;
	}

	/**
	 * Appends the code events XML for an add-on to a specified node.
	 *
	 * @param DOMElement $rootNode XML node to append data to as children
	 * @param string $addOnId Add-on to get data for
	 */
	public function appendEventsAddOnXml(DOMElement $rootNode, $addOnId)
	{
		$events = $this->getEventsByAddOn($addOnId);

		$document = $rootNode->ownerDocument;

		foreach ($events AS $event)
		{
			$eventNode = $document->createElement('event');
			$eventNode->setAttribute('event_id', $event['event_id']);
			$eventNode->appendChild($document->createCDATASection($event['description']));
			$rootNode->appendChild($eventNode);
		}
	}

	/**
	 * Deletes the code events that belong to the specified add-on.
	 *
	 * @param string $addOnId
	 */
	public function deleteEventsForAddOn($addOnId)
	{
		$db = $this->_getDb();
		$db->delete('xf_code_event', 'addon_id = ' . $db->quote($addOnId));
	}

	/**
	 * Imports the code events development XML data.
	 *
	 * @param string $fileName File to read the XML from
	 */
	public function importEventsDevelopmentXml($fileName)
	{
		$document = new SimpleXMLElement($fileName, 0, true);
		$this->importEventsAddOnXml($document, 'XenForo');
	}

	/**
	 * Imports the code events for an add-on.
	 *
	 * @param SimpleXMLElement $xml XML element pointing to the root of the event data
	 * @param string $addOnId Add-on to import for
	 */
	public function importEventsAddOnXml(SimpleXMLElement $xml, $addOnId)
	{
		$db = $this->_getDb();

		XenForo_Db::beginTransaction($db);
		$this->deleteEventsForAddOn($addOnId);

		$xmlEvents = XenForo_Helper_DevelopmentXml::fixPhpBug50670($xml->event);

		$eventIds = array();
		foreach ($xmlEvents AS $event)
		{
			$eventIds[] = (string)$event['event_id'];
		}

		$events = $this->getEventsByIds($eventIds);

		foreach ($xmlEvents AS $event)
		{
			$eventId = (string)$event['event_id'];

			$dw = XenForo_DataWriter::create('XenForo_DataWriter_CodeEvent');
			if (isset($events[$eventId]))
			{
				$dw->setExistingData($events[$eventId]);
			}
			$dw->bulkSet(array(
				'event_id' => $eventId,
				'description' => (string)$event,
				'addon_id' => $addOnId
			));
			$dw->save();
		}

		XenForo_Db::commit($db);
	}

	/**
	 * Gets all event listeners, ordered by their event, grouped by the
	 * add-on they belong to.
	 *
	 * @return array Format: [addon id][event listener id] => info
	 */
	public function getEventListenersGroupedByAddOn()
	{
		$output = array();

		foreach ($this->getAllEventListeners() AS $listener)
		{
			$output[$listener['addon_id']][$listener['event_listener_id']] = $listener;
		}

		return $output;
	}

	/**
	 * Gets an array of all event listeners, ordered by their event and execution order,
	 * keyed by event_listener_id
	 *
	 * @return array
	 */
	public function getAllEventListeners()
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_code_event_listener
			ORDER BY event_id, execute_order
		', 'event_listener_id');
	}

	/**
	 * Gets the specified event listener.
	 *
	 * @param integer $id
	 *
	 * @return array|false
	 */
	public function getEventListenerById($id)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_code_event_listener
			WHERE event_listener_id = ?
		', $id);
	}

	/**
	 * Gets all event listeners for the specified add-on in event and execute order.
	 *
	 * @param string $addOnId
	 *
	 * @return array Format: [event listener id] => info
	 */
	public function getEventListenersByAddOn($addOnId)
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_code_event_listener
			WHERE addon_id = ?
			ORDER BY event_id, execute_order
		', 'event_listener_id', $addOnId);
	}

	/**
	 * Gets the event listener array that's used for the cache.
	 *
	 * @return array Format: [event id][] => [callback class, callback method]
	 */
	public function getEventListenerArray()
	{
		$output = array();

		$listenerResult = $this->_getDb()->query('
			SELECT listener.event_id, listener.callback_class, listener.callback_method
			FROM xf_code_event_listener AS listener
			LEFT JOIN xf_addon AS addon ON
				(addon.addon_id = listener.addon_id AND addon.active = 1)
			WHERE listener.active = 1
				AND (addon.addon_id IS NOT NULL OR listener.addon_id = \'\')
			ORDER BY listener.event_id ASC, listener.execute_order
		');
		while ($listener = $listenerResult->fetch())
		{
			$output[$listener['event_id']][] = array($listener['callback_class'], $listener['callback_method']);
		}

		return $output;
	}

	/**
	 * Gets the default event listener record.
	 *
	 * @return array
	 */
	public function getDefaultEventListener()
	{
		return array(
			'event_listener_id' => 0,
			'event_id' => '',
			'execute_order' => 10,
			'description' => '',
			'callback_class' => '',
			'callback_method' => '',
			'active' => 1,
			'addon_id' => null // must fail isset
		);
	}

	/**
	 * Appends the code event listeners XML for an add-on to a specified node.
	 *
	 * @param DOMElement $rootNode XML node to append data to as children
	 * @param string $addOnId Add-on to get data for
	 */
	public function appendEventListenersAddOnXml(DOMElement $rootNode, $addOnId)
	{
		$document = $rootNode->ownerDocument;

		$listeners = $this->getEventListenersByAddOn($addOnId);
		foreach ($listeners AS $listener)
		{
			$listenerNode = $document->createElement('listener');
			$listenerNode->setAttribute('event_id', $listener['event_id']);
			$listenerNode->setAttribute('execute_order', $listener['execute_order']);
			$listenerNode->setAttribute('callback_class', $listener['callback_class']);
			$listenerNode->setAttribute('callback_method', $listener['callback_method']);
			$listenerNode->setAttribute('active', $listener['active']);
			$listenerNode->setAttribute('description', $listener['description']);

			$rootNode->appendChild($listenerNode);
		}
	}

	/**
	 * Deletes the code event listeners that belong to the specified add-on.
	 *
	 * @param string $addOnId
	 */
	public function deleteEventListenersForAddOn($addOnId)
	{
		$db = $this->_getDb();
		$db->delete('xf_code_event_listener', 'addon_id = ' . $db->quote($addOnId));
	}

	/**
	 * Imports the code event listeners for an add-on.
	 *
	 * @param SimpleXMLElement $xml XML element pointing to the root of the listeners data
	 * @param string $addOnId Add-on to import for
	 */
	public function importEventListenersAddOnXml(SimpleXMLElement $xml, $addOnId)
	{
		$db = $this->_getDb();

		XenForo_Db::beginTransaction($db);
		$this->deleteEventListenersForAddOn($addOnId);

		$xmlListeners = XenForo_Helper_DevelopmentXml::fixPhpBug50670($xml->listener);
		foreach ($xmlListeners AS $event)
		{
			$eventId = (string)$event['event_id'];

			$dw = XenForo_DataWriter::create('XenForo_DataWriter_CodeEventListener');
			$dw->setOption(XenForo_DataWriter_CodeEventListener::OPTION_REBUILD_CACHE, false);
			$dw->bulkSet(array(
				'event_id' => (string)$event['event_id'],
				'execute_order' => (string)$event['execute_order'],
				'callback_class' => (string)$event['callback_class'],
				'callback_method' => (string)$event['callback_method'],
				'active' => (string)$event['active'],
				'description' => (string)$event['description'],
				'addon_id' => $addOnId
			));
			$dw->save();
		}

		$this->rebuildEventListenerCache();

		XenForo_Db::commit($db);
	}

	/**
	 * Rebuilds the event listener cache.
	 *
	 * @return array
	 */
	public function rebuildEventListenerCache()
	{
		$cache = $this->getEventListenerArray();
		$this->_getDataRegistryModel()->set('codeEventListeners', $cache);

		return $cache;
	}
}