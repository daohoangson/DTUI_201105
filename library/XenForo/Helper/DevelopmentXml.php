<?php

// TODO: rename to XenForo_Helper_Xml

/**
 * Helper for working with XML files/data.
 */
class XenForo_Helper_DevelopmentXml
{
	/**
	 * Makes changes to a meta-data file and writes it out.
	 *
	 * @param string $metaDataFile Path to meta-data file
	 * @param string $title The title of the key being modified
	 * @param array|false $metaData If array, inserts/updates the key; if false, deletes the key
	 * @param array|null $metaDataKeys If updating and this is specified, names the keys that will be written; if null, all keys written
	 */
	public static function writeMetaDataOutput($metaDataFile, $title, $metaData, array $metaDataKeys = null)
	{
		if (file_exists($metaDataFile))
		{
			if (!is_writable($metaDataFile))
			{
				throw new XenForo_Exception("Metadata XML file $metaDataFile is not writable");
			}

			$document = new DOMDocument();
			$document->load($metaDataFile);
			$rootNode = $document->documentElement;
		}
		else
		{
			if (!is_writable(dirname($metaDataFile)))
			{
				throw new XenForo_Exception("Metadata XML file $metaDataFile is not writable");
			}

			$document = new DOMDocument();
			$rootNode = $document->createElement('metadata');
			$document->appendChild($rootNode);
		}

		$lowerTitle = strtolower($title);
		$inserted = false;

		if (is_array($metaData))
		{
			$newNode = $document->createElement('item');
			$newNode->setAttribute('title', $title);

			if (!empty($metaDataKeys))
			{
				foreach ($metaDataKeys AS $key)
				{
					$value = (isset($metaData[$key]) ? $metaData[$key] : '');
					$newNode->setAttribute($key, $value);
				}
			}
			else
			{
				foreach ($metaData AS $key => $value)
				{
					$newNode->setAttribute($key, $value);
				}
			}
		}
		else
		{
			$newNode = null;
		}

		$node = $rootNode->firstChild;
		while ($node)
		{
			if ($node->nodeType == XML_ELEMENT_NODE)
			{
				$nodeTitle = strtolower($node->getAttribute('title'));
				if ($nodeTitle == $lowerTitle)
				{
					if (!$newNode || $inserted)
					{
						$previous = $node->previousSibling;
						if ($previous && $previous->nodeType == XML_TEXT_NODE)
						{
							$rootNode->removeChild($previous);
						}
						$rootNode->removeChild($node);
					}
					else
					{
						$rootNode->replaceChild($newNode, $node);
					}
					$inserted = true;
				}
				else if ($newNode && strcmp($lowerTitle, $nodeTitle) < 0 && !$inserted)
				{
					$rootNode->insertBefore($newNode, $node);
					$rootNode->insertBefore($document->createTextNode("\n  "), $node);
					$inserted = true;
				}
			}

			$node = $node->nextSibling;
		}

		$lastChild = $rootNode->lastChild;
		if (!$lastChild || $lastChild->nodeType != XML_TEXT_NODE)
		{
			$rootNode->appendChild($document->createTextNode("\n"));
		}
		$lastChild = $rootNode->lastChild;

		if (!$inserted && $newNode)
		{
			$rootNode->insertBefore($document->createTextNode("\n  "), $lastChild);
			$rootNode->insertBefore($newNode, $lastChild);
		}

		$document->save($metaDataFile);
	}

	/**
	 * Reads all of the meta-data out of the specified file.
	 *
	 * @param string $metaDataFile Path to meta data file
	 *
	 * @return array Format: [title] => meta-data
	 */
	public static function readMetaDataFile($metaDataFile)
	{
		if (file_exists($metaDataFile))
		{
			$metaData = array();
			$xml = new SimpleXmlElement($metaDataFile, 0, true);
			foreach ($xml->item AS $tag)
			{
				$title = (string)$tag['title'];
				if (isset($metaData[$title]))
				{
					continue; // give precedence to earlier entries as they are more up to date
				}

				$attributes = array();
				foreach ($tag->attributes() AS $key => $value)
				{
					$attributes[(string)$key] = (string)$value;
				}

				$metaData[$title] = $attributes;
			}
		}
		else
		{
			$metaData = array();
		}

		return $metaData;
	}

	/**
	 * Workaround for PHP bug 50670, where SimpleXML iteration fails on large data sets
	 * when complex work is done on each iteration.
	 *
	 * See http://bugs.php.net/bug.php?id=50670
	 *
	 * @param array|object $input
	 *
	 * @return array
	 */
	public static function fixPhpBug50670($input)
	{
		if (!$input)
		{
			return array();
		}

		$output = array();

		foreach ($input AS $item)
		{
			$output[] = $item;
		}

		return $output;
	}

	/**
	 * Creates a DOM element. This automatically escapes the value (unlike createElement).
	 *
	 * @param DOMDocument $document
	 * @param string $tagName
	 * @param string|DOMNode|false $value If not false, the value for child nodes
	 *
	 * @return DOMElement
	 */
	public static function createDomElement(DOMDocument $document, $tagName, $value = false)
	{
		$e = $document->createElement($tagName);
		if (is_scalar($value))
		{
			$e->appendChild($document->createTextNode($value));
		}
		else if ($value instanceof DOMNode)
		{
			$e->appendChild($value);
		}
		return $e;
	}

	public static function createDomElements(DOMElement $rootNode, array $pairs)
	{
		$document = $rootNode->ownerDocument;

		foreach ($pairs AS $key => $value)
		{
			$rootNode->appendChild(self::createDomElement($document, $key, $value));
		}

		return $rootNode;
	}
}