<?php

/**
 * Model for avatars.
 *
 * @package XenForo_Avatar
 */
class XenForo_Model_Avatar extends XenForo_Model
{
	/**
	 * List of available avatar sizes. The largest must go first.
	 * Avatars of each size code (directory name) will be no bigger
	 * than the given pixel amount.
	 *
	 * @var array Format: [code] => max pixels
	 */
	protected static $_sizes = array(
		'l' => 192,
		'm' => 96,
		's' => 48
	);

	public static $imageQuality = 85;

	/**
	 * Processes an avatar upload for a user.
	 *
	 * @param XenForo_Upload $upload The uploaded avatar.
	 * @param integer $userId User ID avatar belongs to
	 * @param array|false $permissions User's permissions. False to skip permission checks
	 *
	 * @return array Changed avatar fields
	 */
	public function uploadAvatar(XenForo_Upload $upload, $userId, $permissions)
	{
		if (!$userId)
		{
			throw new XenForo_Exception('Missing user ID.');
		}

		if ($permissions !== false && !is_array($permissions))
		{
			throw new XenForo_Exception('Invalid permission set.');
		}

		$largestDimension = $this->getSizeFromCode('l');

		if (!$upload->isValid())
		{
			throw new XenForo_Exception($upload->getErrors(), true);
		}

		if (!$upload->isImage())
		{
			throw new XenForo_Exception(new XenForo_Phrase('uploaded_file_is_not_valid_image'), true);
		};

		$imageType = $upload->getImageInfoField('type');
		if (!in_array($imageType, array(IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG)))
		{
			throw new XenForo_Exception(new XenForo_Phrase('uploaded_file_is_not_valid_image'), true);
		}

		$baseTempFile = $upload->getTempFile();

		$width = $upload->getImageInfoField('width');
		$height = $upload->getImageInfoField('height');

		return $this->applyAvatar($userId, $baseTempFile, $imageType, $width, $height, $permissions);
	}

	/**
	 * Applies the avatar file to the specified user.
	 *
	 * @param integer $userId
	 * @param string $fileName
	 * @param constant|false $imageType Type of image (IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG)
	 * @param integer|false $width
	 * @param integer|false $height
	 * @param array|false $permissions
	 *
	 * @return array
	 */
	public function applyAvatar($userId, $fileName, $imageType = false, $width = false, $height = false, $permissions = false)
	{
		if (!$imageType || !$width || !$height)
		{
			$imageInfo = getimagesize($fileName);
			if (!$imageInfo)
			{
				throw new XenForo_Exception('Non-image passed in to applyAvatar');
			}
			$width = $imageInfo[0];
			$height = $imageInfo[1];
			$imageType = $imageInfo[2];
		}

		if (!in_array($imageType, array(IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG)))
		{
			throw new XenForo_Exception('Invalid image type passed in to applyAvatar');
		}

		// require 2:1 aspect ratio or squarer
		if ($width > 2 * $height || $height > 2 * $width)
		{
			throw new XenForo_Exception(new XenForo_Phrase('please_provide_an_image_whose_longer_side_is_no_more_than_twice_length'), true);
		}

		$outputFiles = array();
		$outputType = $imageType;

		reset(self::$_sizes);
		list($sizeCode, $maxDimensions) = each(self::$_sizes);

		$shortSide = ($width > $height ? $height : $width);

		if ($shortSide > $maxDimensions)
		{
			$newTempFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
			$image = XenForo_Image_Abstract::createFromFile($fileName, $imageType);
			if (!$image)
			{
				throw new XenForo_Exception(new XenForo_Phrase('image_could_be_processed_try_another_contact_owner'), true);
			}
			$image->thumbnailFixedShorterSide($maxDimensions);
			$image->output($outputType, $newTempFile, self::$imageQuality);

			$width = $image->getWidth();
			$height = $image->getHeight();

			$outputFiles[$sizeCode] = $newTempFile;
		}
		else
		{
			$outputFiles[$sizeCode] = $fileName;
		}

		if (is_array($permissions))
		{
			$maxFileSize = XenForo_Permission::hasPermission($permissions, 'avatar', 'maxFileSize');
			if ($maxFileSize != -1 && filesize($outputFiles[$sizeCode]) > $maxFileSize)
			{
				foreach ($outputFiles AS $tempFile)
				{
					if ($tempFile != $fileName)
					{
						@unlink($tempFile);
					}
				}

				throw new XenForo_Exception(new XenForo_Phrase('your_avatar_file_size_large_smaller_x', array(
					'size' => XenForo_Locale::numberFormat($maxFileSize, 'size')
				)), true);
			}
		}

		$crop = array(
			'x' => array('m' => 0),
			'y' => array('m' => 0),
		);

		while (list($sizeCode, $maxDimensions) = each(self::$_sizes))
		{
			$newTempFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
			$image = XenForo_Image_Abstract::createFromFile($fileName, $imageType);
			if (!$image)
			{
				continue;
			}

			$image->thumbnailFixedShorterSide($maxDimensions);

			if ($image->getOrientation() != XenForo_Image_Abstract::ORIENTATION_SQUARE)
			{
				$crop['x'][$sizeCode] = floor(($image->getWidth() - $maxDimensions) / 2);
				$crop['y'][$sizeCode] = floor(($image->getHeight() - $maxDimensions) / 2);
				$image->crop($crop['x'][$sizeCode], $crop['y'][$sizeCode], $maxDimensions, $maxDimensions);
			}

			$image->output($outputType, $newTempFile, self::$imageQuality);
			unset($image);

			$outputFiles[$sizeCode] = $newTempFile;
		}

		if (count($outputFiles) != count(self::$_sizes))
		{
			foreach ($outputFiles AS $tempFile)
			{
				if ($tempFile != $fileName)
				{
					@unlink($tempFile);
				}
			}
			throw new XenForo_Exception(new XenForo_Phrase('image_could_be_processed_try_another_contact_owner'), true);
		}

		// done in 2 loops as multiple items may point to same file
		foreach ($outputFiles AS $sizeCode => $tempFile)
		{
			$this->_writeAvatar($userId, $sizeCode, $tempFile);
		}
		foreach ($outputFiles AS $tempFile)
		{
			if ($tempFile != $fileName)
			{
				@unlink($tempFile);
			}
		}

		$dwData = array(
			'avatar_date' => XenForo_Application::$time,
			'avatar_width' => $width,
			'avatar_height' => $height,
			'avatar_crop_x' => $crop['x']['m'],
			'avatar_crop_y' => $crop['y']['m'],
			'gravatar' => '',
		);

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_User');
		$dw->setExistingData($userId);
		$dw->bulkSet($dwData);
		$dw->save();

		return $dwData;
	}

	/**
	 * Re-crops an existing avatar with a square, starting at the specified coordinates
	 *
	 * @param integer $userId
	 * @param integer $x
	 * @param integer $y
	 *
	 * @return array Changed avatar fields
	 */
	public function recropAvatar($userId, $x, $y)
	{
		$sizeList = self::$_sizes;

		// get rid of the first entry in the sizes array
		list($largeSizeCode, $largeMaxDimensions) = each($sizeList);

		$outputFiles = array();

		$avatarFile = $this->getAvatarFilePath($userId, $largeSizeCode);
		$imageInfo = getimagesize($avatarFile);
		if (!$imageInfo)
		{
			throw new XenForo_Exception('Non-image passed in to recropAvatar');
		}
		$imageType = $imageInfo[2];

		// now loop through the rest
		while (list($sizeCode, $maxDimensions) = each($sizeList))
		{
			$image = XenForo_Image_Abstract::createFromFile($avatarFile, $imageType);
			$image->thumbnailFixedShorterSide($maxDimensions);

			if ($image->getOrientation() != XenForo_Image_Abstract::ORIENTATION_SQUARE)
			{
				$ratio = $maxDimensions / $sizeList['m'];

				$xCrop = floor($ratio * $x);
				$yCrop = floor($ratio * $y);

				if ($image->getWidth() > $maxDimensions && $image->getWidth() - $xCrop < $maxDimensions)
				{
					$xCrop = $image->getWidth() - $maxDimensions;
				}
				if ($image->getHeight() > $maxDimensions && $image->getHeight() - $yCrop < $maxDimensions)
				{
					$yCrop = $image->getHeight() - $maxDimensions;
				}

				$image->crop($xCrop, $yCrop, $maxDimensions, $maxDimensions);
			}

			$newTempFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');

			$image->output($imageType, $newTempFile, self::$imageQuality);
			unset($image);

			$outputFiles[$sizeCode] = $newTempFile;
		}

		foreach ($outputFiles AS $sizeCode => $tempFile)
		{
			$this->_writeAvatar($userId, $sizeCode, $tempFile);
		}
		foreach ($outputFiles AS $tempFile)
		{
			@unlink($tempFile);
		}

		$dwData = array(
			'avatar_date' => XenForo_Application::$time,
			'avatar_crop_x' => $x,
			'avatar_crop_y' => $y,
			'gravatar' => '',
		);

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_User');
		$dw->setExistingData($userId);
		$dw->bulkSet($dwData);
		$dw->save();

		return $dwData;
	}

	/**
	 * Writes out an avatar.
	 *
	 * @param integer $userId
	 * @param string $size Size code
	 * @param string $tempFile Temporary avatar file. Will be moved.
	 *
	 * @return boolean
	 */
	protected function _writeAvatar($userId, $size, $tempFile)
	{
		if (!in_array($size, array_keys(self::$_sizes)))
		{
			throw new XenForo_Exception('Invalid avatar size.');
		}

		$filePath = $this->getAvatarFilePath($userId, $size);
		$directory = dirname($filePath);

		if (XenForo_Helper_File::createDirectory($directory, true) && is_writable($directory))
		{
			if (file_exists($filePath))
			{
				unlink($filePath);
			}

			$success = rename($tempFile, $filePath);
			if ($success)
			{
				XenForo_Helper_File::makeWritableByFtpUser($filePath);
			}

			return $success;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Get the file path to an avatar.
	 *
	 * @param integer $userId
	 * @param string $size Size code
	 *
	 * @return string
	 */
	public function getAvatarFilePath($userId, $size)
	{
		$group = floor($userId / 1000);

		return XenForo_Helper_File::getExternalDataPath()
			. "/avatars/$size/$group/$userId.jpg";
	}

	/**
	 * Deletes a user's avatar.
	 *
	 * @param integer $userId
	 * @param boolean $updateUser
	 *
	 * @return array Changed avatar fields
	 */
	public function deleteAvatar($userId, $updateUser = true)
	{
		foreach (array_keys(self::$_sizes) AS $size)
		{
			$filePath = $this->getAvatarFilePath($userId, $size);
			if (file_exists($filePath) && is_writable($filePath))
			{
				unlink($filePath);
			}
		}

		$dwData = array(
			'avatar_date' => 0,
			'avatar_width' => 0,
			'avatar_height' => 0,
			'avatar_crop_x' => 0,
			'avatar_crop_y' => 0,
			'gravatar' => '',
		);

		if ($updateUser)
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_User', XenForo_DataWriter::ERROR_SILENT);
			$dw->setExistingData($userId);
			$dw->bulkSet($dwData);
			$dw->save();
		}

		return $dwData;
	}

	/**
	 * Returns the _sizes array, defining what avatar sizes are available.
	 *
	 * @return array
	 */
	public static function getSizes()
	{
		return self::$_sizes;
	}

	/**
	 * Returns the maximum size (in pixels) of an avatar corresponding to the size code specified
	 *
	 * @param string $sizeCode (s,m,l)
	 *
	 * @return integer
	 */
	public static function getSizeFromCode($sizeCode)
	{
		return self::$_sizes[strtolower($sizeCode)];
	}

	/**
	 * Checks whether a Gravatar exists for a given email address
	 *
	 * @param string $email
	 *
	 * @return string|boolean Gravatar URL on success
	 */
	public static function gravatarExists($email, &$errorText = '', $size = 1, &$gravatarUrl = '')
	{
		if (!Zend_Validate::is($email, 'EmailAddress'))
		{
			$errorText = new XenForo_Phrase('gravatars_require_valid_email_addresses');
			return false;
		}

		try
		{
			$client = XenForo_Helper_Http::getClient(self::_getGravatarUrl($email, 1, 404), array(
				'maxredirects' => 0,
				'timeout' => 5
			));

			if ($client->request('HEAD')->getStatus() !== 200)
			{
				$errorText = new XenForo_Phrase('no_gravatar_found_for_specified_email_address');
				return false;
			}
		}
		catch (Exception $e)
		{
			XenForo_Error::logException($e, false);
			$errorText = new XenForo_Phrase('there_was_problem_communicating_with_gravatar');
			return false;
		}

		$gravatarUrl = self::_getGravatarUrl($email, $size, false);
		return true;
	}

	/**
	 * Builds a basic Gravatar URL for the given parameters

	 * @param string $email
	 * @param integer $size
	 * @param mixed $default
	 *
	 * @return string
	 */
	public static function _getGravatarUrl($email, $size, $default = false)
	{
		$md5 = md5($email);

		if (!empty($default))
		{
			$default = '&d=' . urlencode($default);
		}

		return "http://www.gravatar.com/avatar/{$md5}.jpg?s={$size}{$default}";
	}
}