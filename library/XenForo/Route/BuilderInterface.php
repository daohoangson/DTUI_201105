<?php

/**
* Interface for a route that can build a link to a specified type of data.
*
* @package XenForo_Mvc
*/
interface XenForo_Route_BuilderInterface
{
	/**
	 * Method to build a link to the specified page/action with the provided
	 * data and params.
	 *
	 * Unhandled extra params will be turned into a query string after this
	 * function is called. If a param is handled by the core URL, it should
	 * be unset from the extra params list.
	 *
	 * @param string $originalPrefix Original prefix for the type of link to be generated;
	 * 		this is a known value, but shouldn't be displayed to the user
	 * @param string $outputPrefix The configured output that means the same thing as the original prefix but is user configured
	 * @param string $action Action to take on the data
	 * @param string $extension Specified extension for the link
	 * @param mixed $data Info about data to link to specifically (eg, info about a thread)
	 * @param array $extraParams Extra params that modify how the link is built
	 *
	 * @return string|false Core link if handled, false otherwise
	 */
	public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams);
}