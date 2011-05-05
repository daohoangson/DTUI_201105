<?php

/**
 * Interface for data writers that can be called via discussion DWs to update
 * denormalized container data.
 *
 * @package XenForo_Discussion
 */
interface XenForo_DataWriter_DiscussionContainerInterface
{
	/**
	 * Updates denormalized counters, based on changes made to the provided
	 * discussion, after the discussion has been saved.
	 *
	 * @param XenForo_DataWriter_DiscussionMessage $messageDw
	 * @param boolean $forceInsert True if code should act like the discussion is being inserted (useful for moves)
	 */
	public function updateCountersAfterDiscussionSave(XenForo_DataWriter_Discussion $discussionDw, $forceInsert = false);

	/**
	 * Updates denormalized counters. Used after a discussion has been deleted.
	 *
	 * @param XenForo_DataWriter_DiscussionMessage $messageDw
	 */
	public function updateCountersAfterDiscussionDelete(XenForo_DataWriter_Discussion $discussionDw);
}