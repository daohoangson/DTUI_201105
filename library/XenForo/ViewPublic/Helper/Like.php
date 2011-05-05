<?php

class XenForo_ViewPublic_Helper_Like
{
	/**
	 * Fetches view parameters for a like link return.
	 * Gets the like/unlike phrase, and like/unlike CSS class instructions.
	 *
	 * @see XenForo_ViewPublic_Post_LikeConfirmed::renderJson() for an example.
	 *
	 * @param boolean $liked
	 *
	 * @return array
	 */
	public static function getLikeViewParams($liked)
	{
		$output = array();

		if ($liked)
		{
			$output['term'] = new XenForo_Phrase('unlike');

			$output['cssClasses'] = array(
				'like' => '-',
				'unlike' => '+'
			);
		}
		else
		{
			$output['term'] = new XenForo_Phrase('like');

			$output['cssClasses'] = array(
				'like' => '+',
				'unlike' => '-'
			);
		}

		return $output;
	}

}