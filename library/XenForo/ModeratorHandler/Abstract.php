<?php

/**
 * Abstract moderator handler. Moderator handlers deal with the content-specific
 * differences in content moderators.
 *
 * @package XenForo_Moderator
 */
abstract class XenForo_ModeratorHandler_Abstract
{
	/**
	 * Gets the moderator permission interface group IDs that apply to this type of content.
	 *
	 * @return array
	 */
	abstract public function getModeratorInterfaceGroupIds();

	/**
	 * Gets the option that shows up on the moderator add "choice" screen for this content type.
	 * The return value should be an array that can be passed as a <xen:options /> tag
	 * (label, value, selected, disabled, etc).
	 *
	 * @param XenForo_View $view
	 * @param integer $selectedContentId If being redirected back to this page, there may be a selected content ID.
	 * @param string $contentType The name of the content type
	 *
	 * @return array
	 */
	abstract public function getAddModeratorOption(XenForo_View $view, $selectedContentId, $contentType);

	/**
	 * Gets the title of multiple pieces of content in this content type.
	 * The return should be an array keyed by matching keys of the IDs param.
	 * Note that an ID value may occur multiple times.
	 *
	 * Note that the title may be more than just the title, if necessary. It may
	 * include other necessary disambiguation, such as the content type ("Forum - Name").
	 *
	 * @param array $ids
	 *
	 * @return array Format: [id key (key of param)] => title
	 */
	abstract public function getContentTitles(array $ids);

	/**
	 * Helper to get the content title of one piece of content.
	 *
	 * @param integer $id
	 *
	 * @return string
	 */
	public function getContentTitle($id)
	{
		$titles = $this->getContentTitles(array($id));
		return reset($titles);
	}
}