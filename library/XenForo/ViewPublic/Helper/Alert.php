<?php

/**
 * Class to help display alerts.
 *
 * @package XenForo_NewsFeed
 */
class XenForo_ViewPublic_Helper_Alert
{
	/**
	 * Private constructor. Use statically.
	 */
	private function __construct()
	{
	}

	/**
	 * Attaches a template object to each record in the incoming alerts array
	 *
	 * @param XenForo_View $view
	 * @param array $newsFeed
	 * @param array $handlers
	 *
	 * @return array $newsFeed
	 */
	public static function getTemplates(XenForo_View $view, array $alerts, array $handlers)
	{
		foreach ($alerts AS $id => $item)
		{
			$handler = $handlers[$item['alert_handler_class']];

			$alerts[$id]['template'] = $handler->renderHtml($item, $view);
		}

		return $alerts;
	}

	/**
	 * Splits an array into individual chunks of days, keyed by the midnight timestamp of the day specified by each item
	 *
	 * @param array $items
	 * @param string $dateField
	 *
	 * @return array [$midnight] => $item
	 */
	public static function dateSplit(array $items, $dateField)
	{
		$newItems = array();

		foreach ($items AS $key => $value)
		{
			$newItems[XenForo_Locale::date($value[$dateField])][$key] = $value;
		}

		return $newItems;
	}
}