<?php

class XenForo_ViewPublic_Helper_Search
{
	public static function renderSearchResults(XenForo_View $view, array $results, array $search = array(), array $handlers = null)
	{
		if ($handlers === null)
		{
			$handlers = $results['handlers'];
			$results = $results['results'];
		}

		$output = array();
		foreach ($results AS $result)
		{
			$output[] = $handlers[$result[XenForo_Model_Search::CONTENT_TYPE]]->renderResult($view, $result['content'], $search);
		}

		return $output;
	}
}