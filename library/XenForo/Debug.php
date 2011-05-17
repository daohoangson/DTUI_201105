<?php

/**
 * Helpers for debugging output.
 *
 * @package XenForo_Core
 */
class XenForo_Debug
{
	/**
	 * Private constructor. Use statically.
	 */
	private function __construct()
	{
	}

	/**
	 * Gets the debug HTML output. This is triggered by the _debug=1 URL parameter.
	 *
	 * @return string
	 */
	public static function getDebugHtml()
	{
		if (XenForo_Application::isRegistered('page_start_time'))
		{
			$pageTime = microtime(true) - XenForo_Application::get('page_start_time');
		}
		else
		{
			$pageTime = 0;
		}

		$memoryUsage = memory_get_usage();
		$memoryUsagePeak = memory_get_peak_usage();

		if (XenForo_Application::isRegistered('db'))
		{
			$dbDebug = self::getDatabaseDebugInfo(XenForo_Application::get('db'));
		}
		else
		{
			$dbDebug = array(
				'queryCount' => 0,
				'totalQueryRunTime' => 0,
				'queryHtml' => ''
			);
		}

		if ($pageTime > 0)
		{
			$dbPercent = ($dbDebug['totalQueryRunTime'] / $pageTime) * 100;
		}
		else
		{
			$dbPercent = 0;
		}

		$includedFiles = self::getIncludedFilesDebugInfo(get_included_files());

		$return = "<h1>Page Time: " . number_format($pageTime, 4) . "s</h1>"
			. "<h2>Memory: " . number_format($memoryUsage / 1024 / 1024, 4) . " MB "
			. "(Peak: " . number_format($memoryUsagePeak / 1024 / 1024, 4) . " MB)</h2>"
			. "<h2>Queries ($dbDebug[queryCount], time: " . number_format($dbDebug['totalQueryRunTime'], 4) . "s, "
			. number_format($dbPercent, 1) . "%)</h2>"
			. $dbDebug['queryHtml']
			. "<h2>Included Files ($includedFiles[includedFileCount], XenForo Classes: $includedFiles[includedXenForoClasses])</h2>"
			. $includedFiles['includedFileHtml'];

		return $return;
	}

	/**
	 * Gets database debug information, including query count and run time and
	 * the actual queries that were run.
	 *
	 * @param Zend_Db_Adapter_Abstract $db
	 *
	 * @return array Keys: queryCount, totalQueryRunTime, queryHtml
	 */
	public static function getDatabaseDebugInfo(Zend_Db_Adapter_Abstract $db)
	{
		$return = array(
			'queryCount' => 0,
			'totalQueryRunTime' => 0,
			'queryHtml' => ''
		);

		/* @var $profiler Zend_Db_Profiler */
		$profiler = $db->getProfiler();
		$return['queryCount'] = $profiler->getTotalNumQueries();

		if ($return['queryCount'])
		{
			$return['queryHtml'] .= '<ol>';

			$queries = $profiler->getQueryProfiles();
			foreach ($queries AS $query)
			{
				$queryText = rtrim($query->getQuery());
				if (preg_match('#(^|\n)(\t+)([ ]*)(?=\S)#', $queryText, $match))
				{
					$queryText = preg_replace('#(^|\n)\t{1,' . strlen($match[2]) . '}#', '$1', $queryText);
				}

				$boundParams = array();
				foreach ($query->getQueryParams() AS $param)
				{
					$boundParams[] = htmlspecialchars($param);
				}

				$explainOutput = '';

				if (preg_match('#^\s*SELECT\s#i', $queryText)
					&& in_array(get_class($db), array('Zend_Db_Adapter_Mysqli'))
				)
				{
					$explainQuery = $db->query(
						'EXPLAIN ' . $query->getQuery(),
						$query->getQueryParams()
					);
					$explainRows = $explainQuery->fetchAll();
					if ($explainRows)
					{
						$explainOutput .= '<table border="1">'
							. '<tr>'
							. '<th>Select Type</th><th>Table</th><th>Type</th><th>Possible Keys</th>'
							. '<th>Key</th><th>Key Len</th><th>Ref</th><th>Rows</th><th>Extra</th>'
							. '</tr>';

						foreach ($explainRows AS $explainRow)
						{
							foreach ($explainRow AS $key => $value)
							{
								if (trim($value) === '')
								{
									$explainRow[$key] = '&nbsp;';
								}
								else
								{
									$explainRow[$key] = htmlspecialchars($value);
								}
							}

							$explainOutput .= '<tr>'
								. '<td>' . $explainRow['select_type'] . '</td>'
								. '<td>' . $explainRow['table'] . '</td>'
								. '<td>' . $explainRow['type'] . '</td>'
								. '<td>' . $explainRow['possible_keys'] . '</td>'
								. '<td>' . $explainRow['key'] . '</td>'
								. '<td>' . $explainRow['key_len'] . '</td>'
								. '<td>' . $explainRow['ref'] . '</td>'
								. '<td>' . $explainRow['rows'] . '</td>'
								. '<td>' . $explainRow['Extra'] . '</td>'
								. '</tr>';
						}

						$explainOutput .= '</table>';
					}
				}

				$return['queryHtml'] .= '<li>'
					. '<pre>' . htmlspecialchars($queryText) . '</pre>'
					. ($boundParams ? '<div><strong>Params:</strong> ' . implode(', ', $boundParams) . '</div>' : '')
					. '<div><strong>Run Time:</strong> ' . number_format($query->getElapsedSecs(), 6) . '</div>'
					. $explainOutput
					. "</li>\n";

				$return['totalQueryRunTime'] += $query->getElapsedSecs();
			}

			$return['queryHtml'] .= '</ol>';
		}

		return $return;
	}

	/**
	 * Gets included files debug info.
	 *
	 * @param array $includedFiles
	 *
	 * @return array Keys: includedFileCount, incldedFileHtml, includedForoClasses
	 */
	public static function getIncludedFilesDebugInfo(array $includedFiles)
	{
		$return = array(
			'includedFileCount' => count($includedFiles),
			'includedFileHtml' => '<ol>',
			'includedXenForoClasses' => 0
		);

		$baseDir = dirname(reset($includedFiles));

		foreach ($includedFiles AS $file)
		{
			$file = preg_replace('#^' . preg_quote($baseDir, '#') . '(\\\\|/)#', '', $file);
			$file = htmlspecialchars($file);

			if (preg_match('#^library(/|\\\\)XenForo(/|\\\\)#', $file))
			{
				$return['includedXenForoClasses']++;
			}
			$file = preg_replace('#^library(/|\\\\)XenForo(/|\\\\)#', '<b>$0</b>', $file);

			$return['includedFileHtml'] .= '<li>' . $file . '</li>' . "\n";
		}
		$return['includedFileHtml'] .= '</ol>';

		return $return;
	}

	public static function getDebugTemplateParams()
	{
		$params = array();

		$request = new Zend_Controller_Request_Http();
		$pageUrl = $request->getRequestUri();
		$params['debug_url'] = $pageUrl . (strpos($pageUrl, '?') !== false ? '&' : '?') . '_debug=1';

		if (XenForo_Application::isRegistered('page_start_time'))
		{
			$params['page_time'] = microtime(true) - XenForo_Application::get('page_start_time');
		}

		$params['memory_usage'] = memory_get_usage();

		if (XenForo_Application::isRegistered('db'))
		{
			$profiler = XenForo_Application::get('db')->getProfiler();
			$params['db_queries'] = $profiler->getTotalNumQueries();
		}

		return $params;
	}
}