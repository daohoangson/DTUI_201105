<?php

class XenForo_ViewPublic_Helper_User
{
	/**
	 * Generates left and top CSS positions for a user avatar to be cropped within .avatarCropper
	 *
	 * @param array $user Must contain avatar_width, avatar_height, avatar_crop_x and avatar_crop_y keys
	 *
	 * @return array (top: Xpx; top: Ypx)
	 */
	public static function getAvatarCropCss(array $user)
	{
		$largeSize = 192;

		if ($user['avatar_width'])
		{
			if ($user['avatar_width'] >= $largeSize || $user['avatar_height'] >= $largeSize)
			{
				if ($user['avatar_width'] > $user['avatar_height'])
				{
					// landscape
					$ratio = -1 * $user['avatar_height'] / 96;
					$left = max($user['avatar_crop_x'] * $ratio, $largeSize - $user['avatar_width']);
					return array(
						'left' => $left . 'px',
						'top' => max(0, ($largeSize - $user['avatar_height']) / 2) . 'px'
					);
				}
				else if ($user['avatar_width'] < $user['avatar_height'])
				{
					// portrait
					$ratio = -1 * $user['avatar_width'] / 96;
					$top = max($user['avatar_crop_y'] * $ratio, $largeSize - $user['avatar_height']);
					return array(
						'left' => max(0, ($largeSize - $user['avatar_width']) / 2) . 'px',
						'top' => $top . 'px'
					);
				}
			}
			else
			{
				// center small image
				return array(
					'left' => ($largeSize - $user['avatar_width']) / 2 . 'px',
					'top' => ($largeSize - $user['avatar_height']) / 2 . 'px'
				);
			}
		}

		return array('left' => '0px', 'top' => '0px');
	}
}