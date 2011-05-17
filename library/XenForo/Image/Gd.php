<?php

/**
 * Image processor using GD.
 *
 * @package XenForo_Image
 */
class XenForo_Image_Gd extends XenForo_Image_Abstract
{
	/**
	 * The GD image resource.
	 *
	 * @var resource
	 */
	protected $_image = null;

	/**
	 * Constructor.
	 *
	 * @param resource $image GD image resource
	 */
	protected function __construct($image)
	{
		$this->_setImage($image);
	}

	/**
	 * Creates a blank image.
	 *
	 * @param integer $width
	 * @param integer $height
	 *
	 * @return XenForo_Image_Gd
	 */
	public static function createImageDirect($width, $height)
	{
		return new self(imagecreatetruecolor($width, $height));
	}

	/**
	 * Creates an image from an existing file.
	 *
	 * @param string $fileName
	 * @param integer $inputType IMAGETYPE_XYZ constant representing image type
	 *
	 * @return XenForo_Image_Gd|false
	 */
	public static function createFromFileDirect($fileName, $inputType)
	{
		$invalidType = false;

		try
		{
			switch ($inputType)
			{
				case IMAGETYPE_GIF:
					if (!function_exists('imagecreatefromgif'))
					{
						return false;
					}
					$image = imagecreatefromgif($fileName);
					break;

				case IMAGETYPE_JPEG:
					if (!function_exists('imagecreatefromjpeg'))
					{
						return false;
					}
					$image = imagecreatefromjpeg($fileName);
					break;

				case IMAGETYPE_PNG:
					if (!function_exists('imagecreatefrompng'))
					{
						return false;
					}
					$image = imagecreatefrompng($fileName);
					break;

				default:
					$invalidType = true;
			}
		}
		catch (Exception $e)
		{
			return false;
		}

		if ($invalidType)
		{
			throw new XenForo_Exception('Invalid image type given. Expects IMAGETYPE_XXX constant.');
		}

		return new self($image);
	}

	/**
	 * Thumbnails the image.
	 *
	 * @see XenForo_Image_Abstract::thumbnail()
	 */
	public function thumbnail($maxWidth, $maxHeight = 0)
	{
		if ($maxWidth < 10)
		{
			$maxWidth = 10;
		}
		if ($maxHeight < 10)
		{
			$maxHeight = $maxWidth;
		}

		if ($this->_width < $maxWidth && $this->_height < $maxHeight)
		{
			return false;
		}

		$ratio = $this->_width / $this->_height;

		$maxRatio = ($maxWidth / $maxHeight);

		if ($maxRatio > $ratio)
		{
			$width = max(1, $maxHeight * $ratio);
			$height = $maxHeight;
		}
		else
		{
			$width = $maxWidth;
			$height = max(1, $maxWidth / $ratio);
		}

		$newImage = imagecreatetruecolor($width, $height);
		$this->_preallocateBackground($newImage);

		imagecopyresampled(
			$newImage, $this->_image,
			0, 0, 0, 0,
			$width, $height, $this->_width, $this->_height
		);
		$this->_setImage($newImage);

		return true;
	}

	/**
	 * Produces a thumbnail of the current image whose shorter side is the specified length
	 *
	 * @see XenForo_Image_Abstract::thumbnailFixedShorterSide
	 */
	public function thumbnailFixedShorterSide($shortSideLength)
	{
		if ($shortSideLength < 10)
		{
			$shortSideLength = 10;
		}

		$ratio = $this->_width / $this->_height;
		if ($ratio > 1) // landscape
		{
			$width = $shortSideLength * $ratio;
			$height = $shortSideLength;
		}
		else
		{
			$width = $shortSideLength;
			$height = max(1, $shortSideLength / $ratio);
		}

		$newImage = imagecreatetruecolor($width, $height);
		$this->_preallocateBackground($newImage);

		imagecopyresampled(
			$newImage, $this->_image,
			0, 0, 0, 0,
			$width, $height, $this->_width, $this->_height);
		$this->_setImage($newImage);
	}

	/**
	 * Crops the image.
	 *
	 * @see XenForo_Image_Abstract::crop()
	 */
	public function crop($x, $y, $width, $height)
	{
		$newImage = imagecreatetruecolor($width, $height);
		$this->_preallocateBackground($newImage);

		imagecopyresampled(
			$newImage, $this->_image,
			0, 0, $x, $y,
			$width, $height, $width, $height
		);
		$this->_setImage($newImage);
	}

	/**
	 * Outputs the image.
	 *
	 * @see XenForo_Image_Abstract::output()
	 */
	public function output($outputType, $outputFile = null, $quality = 85)
	{
		switch ($outputType)
		{
			case IMAGETYPE_GIF: $success = imagegif($this->_image, $outputFile); break;
			case IMAGETYPE_JPEG: $success = imagejpeg($this->_image, $outputFile, $quality); break;
			case IMAGETYPE_PNG:
				// "quality" seems to be misleading, always force 9
				$success = imagepng($this->_image, $outputFile, 9, PNG_ALL_FILTERS);
				break;

			default:
				throw new XenForo_Exception('Invalid output type given. Expects IMAGETYPE_XXX constant.');
		}

		return $success;
	}

	protected function _preallocateBackground($image)
	{
		imagesavealpha($image, true);
		$color = imagecolorallocatealpha($image, 255, 255, 255, 127);
		imagefill($image, 0, 0, $color);
	}

	/**
	 * Sets the internal GD image resource.
	 *
	 * @param resource $image
	 */
	protected function _setImage($image)
	{
		$this->_image = $image;
		$this->_width = imagesx($image);
		$this->_height = imagesy($image);
	}
}