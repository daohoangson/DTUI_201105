<?php

/**
 * Abstract image processor. Includes factory for creating concrete
 * image processors.
 *
 * @package XenForo_Image
 */
abstract class XenForo_Image_Abstract
{
	const ORIENTATION_LANDSCAPE = 'landscape';
	const ORIENTATION_PORTRAIT = 'portrait';
	const ORIENTATION_SQUARE = 'square';

	/**
	 * Width of the image. This must stay current with manipulations.
	 *
	 * @var integer
	 */
	protected $_width = 0;

	/**
	 * Height of the image. This must stay current with manipulations.
	 *
	 * @var integer
	 */
	protected $_height = 0;

	/**
	 * Protected constructor. Use static method to create.
	 */
	protected function __construct() {}

	/**
	 * Thumbnails the current image.
	 *
	 * @param integer $maxWidth The maximum width of the thumb.
	 * @param integer $maxHeight Maximum height of the thumb; if not specified, uses max width.
	 *
	 * @return boolean True if thumbnailing was necessary
	 */
	abstract public function thumbnail($maxWidth, $maxHeight = 0);

	/**
	 * Produces a thumbnail of the current image whose shorter side is the specified length
	 *
	 * @param integer $shortSideWidth
	 */
	abstract public function thumbnailFixedShorterSide($shortSideWidth);

	/**
	 * Crops the current image.
	 *
	 * @param $x Crop start x position
	 * @param $y Crop start y position
	 * @param $width Crop width
	 * @param $height Crop height
	 */
	abstract public function crop($x, $y, $width, $height);

	/**
	 * Outputs the processed image. If no output file is specified, the image
	 * is printed to the screen.
	 *
	 * @param constant $outputType One of the IMAGETYPE_XYZ constants
	 * @param string|null $outputFile If specified, the file to write to; else, prints to screen
	 * @param integer $quality Quality of outputted file (from 0 to 100)
	 *
	 * @return boolean True on success
	 */
	abstract public function output($outputType, $outputFile = null, $quality = 85);

	/**
	 * Gets the orientation of the image (landscape, portrait, square)
	 *
	 * @return string One of the ORIENTATION_ constants
	 */
	public function getOrientation()
	{
		$w = $this->getWidth();
		$h = $this->getHeight();

		if ($w == $h)
		{
			return self::ORIENTATION_SQUARE;
		}
		else if ($w > $h)
		{
			return self::ORIENTATION_LANDSCAPE;
		}
		else
		{
			return self::ORIENTATION_PORTRAIT;
		}
	}

	/**
	 * Gets the width of the image with current manipulations.
	 *
	 * @return integer
	 */
	public function getWidth()
	{
		return $this->_width;
	}

	/**
	 * Gets the height of the image with current manipulations.
	 *
	 * @return integer
	 */
	public function getHeight()
	{
		return $this->_height;
	}

	/**
	 * Creates a blank image.
	 *
	 * @param integer $width
	 * @param integer $height
	 *
	 * @return XenForo_Image_Abstract
	 */
	public static function createImageDirect($width, $height)
	{
		throw new XenForo_Exception('Must be overridden');
	}

	/**
	 * Creates an image from an existing file.
	 *
	 * @param string $fileName
	 * @param integer $inputType IMAGETYPE_XYZ constant representing image type
	 *
	 * @return XenForo_Image_Abstract|false
	 */
	public static function createFromFileDirect($fileName, $inputType)
	{
		throw new XenForo_Exception('Must be overridden');
	}

	/**
	 * Creates a blank image.
	 *
	 * @param integer $width
	 * @param integer $height
	 *
	 * @return XenForo_Image_Abstract
	 */
	public static function createImage($width, $height)
	{
		$class = self::_getDefaultClassName();
		return call_user_func(array($class, 'createImageDirect'), $width, $height);
	}

	/**
	 * Creates an image from an existing file.
	 *
	 * @param string $fileName
	 * @param integer $inputType IMAGETYPE_XYZ constant representing image type
	 *
	 * @return XenForo_Image_Abstract
	 */
	public static function createFromFile($fileName, $inputType)
	{
		$class = self::_getDefaultClassName();
		return call_user_func(array($class, 'createFromFileDirect'), $fileName, $inputType);
	}

	/**
	 * Gets the name of the default image processing class (this may
	 * come from an option).
	 *
	 * @return string
	 */
	protected static function _getDefaultClassName()
	{
		return 'XenForo_Image_Gd';
	}
}