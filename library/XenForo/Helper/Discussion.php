<?php

class XenForo_Helper_Discussion
{
	/**
	 * Gets an array containing the last few page numbers for a thread
	 *
	 * @param integer Number of replies in the thread in question
	 *
	 * @return array [5,6,7]
	 */
	public static function getLastPageNumbers($replyCount, $perPage = null, $maxLinks = null)
	{
		if ($perPage === null)
		{
			$perPage = XenForo_Application::get('options')->messagesPerPage;
		}

		if ($maxLinks === null)
		{
			$maxLinks = XenForo_Application::get('options')->lastPageLinks;
		}

		$pageCount = ceil(($replyCount + 1) / $perPage);

		$startPage = max(2, $pageCount - ($maxLinks - 1));

		$pages = array();
		for ($i = $startPage; $i <= $pageCount; $i++)
		{
			$pages[] = $i;
		}

		return $pages;
	}
}