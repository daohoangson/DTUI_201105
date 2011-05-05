<?php

/**
 * Core upload handler.
 *
 * @package XenForo_Upload
 */
class XenForo_Upload
{
	/**
	 * The user-supplied file name of the upload.
	 *
	 * @var string
	 */
	protected $_fileName = '';

	/**
	 * The extension from the user-supplied file name.
	 *
	 * @var string
	 */
	protected $_extension = '';

	/**
	 * Full path to the temporary file created by the upload.
	 *
	 * @var string
	 */
	protected $_tempFile = '';

	/**
	 * If the upload is an image, information about the image.
	 * If null, state is unknown; if false, not an image. Otherwise, array with keys:
	 * 	* from getimagesize()
	 * 	* width/height/type
	 *
	 * @var array|false|null
	 */
	protected $_imageInfo = null;

	/**
	 * True if errors have been checked.
	 *
	 * @var boolean
	 */
	protected $_errorsChecked = false;

	/**
	 * List of errors that occured in the upload.
	 *
	 * @var array
	 */
	protected $_errors = array();

	/**
	 * List of allowed attachment extensions.
	 *
	 * @var array
	 */
	protected $_allowedExtensions = array();

	/**
	 * Maximum attachment file size in bytes.
	 *
	 * @var integer
	 */
	protected $_maxFileSize = 0;

	/**
	 * Maximum attachment image width in pixels.
	 *
	 * @var integer
	 */
	protected $_maxWidth = 0;

	/**
	 * Maximum attachment image height in pixels.
	 *
	 * @var integer
	 */
	protected $_maxHeight = 0;

	/**
	 * If true, automatically resizes images that are
	 * too large.
	 *
	 * @var boolean
	 */
	protected $_autoResize = true;

	/**
	 * Constructor.
	 *
	 * @param string $fileName User-supplied file name
	 * @param string $tempFile Upload temporary file name; this can be an empty string to account for uploads that are too large
	 */
	public function __construct($fileName, $tempFile)
	{
		if ($tempFile && !file_exists($tempFile) && !is_readable($tempFile))
		{
			throw new XenForo_Exception('The temporary file for the upload cannot be found.');
		}

		$this->_fileName = $fileName;
		$this->_extension = XenForo_Helper_File::getFileExtension($fileName);
		$this->_tempFile = $tempFile;

		// TODO: clean up internal files on shut down (can't use destruct as files may still be used)
	}

	/**
	 * Set the constraints for this upload. Possible keys:
	 * 	* extensions - array of allowed extensions
	 * 	* size - max file size in bytes
	 * 	* width - max image width in pixels
	 * 	* height - max image height in pixels
	 *
	 * @param array $constraints See above for format.
	 */
	public function setConstraints(array $constraints)
	{
		if ($this->_errorsChecked || $this->_imageInfo !== null)
		{
			throw new XenForo_Exception('Cannot set upload constraints after checking upload state.');
		}

		if (!empty($constraints['extensions']) && is_array($constraints['extensions']))
		{
			$this->_allowedExtensions = array_map('strtolower', $constraints['extensions']);
		}
		if (!empty($constraints['size']) && $constraints['size'] > 0)
		{
			$this->_maxFileSize = intval($constraints['size']);
		}
		if (!empty($constraints['width']) && $constraints['width'] > 0)
		{
			$this->_maxWidth = intval($constraints['width']);
		}
		if (!empty($constraints['height']) && $constraints['height'] > 0)
		{
			$this->_maxHeight = intval($constraints['height']);
		}
	}

	/**
	 * Returns true if the upload is valid.
	 *
	 * @return boolean
	 */
	public function isValid()
	{
		if (!$this->_errorsChecked)
		{
			$this->_checkForErrors();
		}

		return (count($this->_errors) == 0);
	}

	/**
	 * Returns true if the upload is a valid image.
	 *
	 * @return boolean
	 */
	public function isImage()
	{
		$this->_checkImageState();
		return ($this->_imageInfo ? true : false);
	}

	/**
	 * Checks the state of the upload to determine if it's
	 * a valid image.
	 */
	protected function _checkImageState()
	{
		if ($this->_imageInfo !== null)
		{
			return;
		}

		$this->_imageInfo = false; // default to not an image

		if (!$this->_tempFile)
		{
			return;
		}

		$imageInfo = @getimagesize($this->_tempFile);
		if (!$imageInfo)
		{
			return;
		}

		$imageInfo['width'] = $imageInfo[0];
		$imageInfo['height'] = $imageInfo[1];
		$imageInfo['type'] = $imageInfo[2];

		$type = $imageInfo['type'];
		$extensionMap = array(
			IMAGETYPE_GIF => array('gif'),
			IMAGETYPE_JPEG => array('jpg', 'jpeg', 'jpe'),
			IMAGETYPE_PNG => array('png')
		);
		if (!isset($extensionMap[$type]))
		{
			return; // only consider gif, jpeg, png to be images in this system
		}
		if (!in_array($this->_extension, $extensionMap[$type]))
		{
			$this->_errors['extension'] = new XenForo_Phrase('contents_of_uploaded_image_do_not_match_files_extension');
			return;
		}

		$this->_imageInfo = $imageInfo;

		if (($this->_maxWidth && $imageInfo['width'] > $this->_maxWidth)
			|| ($this->_maxHeight && $imageInfo['height'] > $this->_maxHeight))
		{
			$image = XenForo_Image_Abstract::createFromFile($this->_tempFile, $type);
			if ($image)
			{
				$image->thumbnail($this->_maxWidth, $this->_maxHeight);
				$thumbSuccess = $image->output($type, $this->_tempFile);
				if (!$thumbSuccess)
				{
					$this->_errors['dimensions'] = new XenForo_Phrase('uploaded_image_is_too_big');
				}
				// TODO: doesn't resize if the file size is too big (and fails if resizes but still too big)
			}
			else
			{
				// error thumbnailing, treat as non-image
				$this->_imageInfo = false;
			}
		}
	}

	/**
	 * Checks for errors in the upload.
	 */
	protected function _checkForErrors()
	{
		$this->_checkImageState();

		if ($this->_allowedExtensions && !in_array($this->_extension, $this->_allowedExtensions))
		{
			$this->_errors['extension'] = new XenForo_Phrase('uploaded_file_does_not_have_an_allowed_extension');
		}

		if ($this->_tempFile && $this->_maxFileSize && filesize($this->_tempFile) > $this->_maxFileSize)
		{
			$this->_errors['fileSize'] = new XenForo_Phrase('uploaded_file_is_too_large');
		}

		if (!$this->_tempFile)
		{
			$this->_errors['fileSize'] = new XenForo_Phrase('uploaded_file_is_too_large_for_server_to_process');
		}

		$this->_errorsChecked = true;
	}

	/**
	 * Gets the user-supplied file name.
	 *
	 * @return string
	 */
	public function getFileName()
	{
		return $this->_fileName;
	}

	/**
	 * Gets the path to the temporary file.
	 *
	 * @return string
	 */
	public function getTempFile()
	{
		return $this->_tempFile;
	}

	/**
	 * Gets the errors for the upload.
	 *
	 * @return array
	 */
	public function getErrors()
	{
		return $this->_errors;
	}

	/**
	 * Gets the value of a specific image info field.
	 *
	 * @param string $field
	 *
	 * @return mixed|false Mixed scalar, or false if not an image or invalid field
	 */
	public function getImageInfoField($field)
	{
		$this->_checkImageState();
		if ($this->_imageInfo && isset($this->_imageInfo[$field]))
		{
			return $this->_imageInfo[$field];
		}
		else
		{
			return false;
		}
	}

	/**
	 * Gets the files that were uploaded into the specified form field (via HTTP POST).
	 *
	 * @param string $formField Name of the form field
	 * @param array|null $source Source array ($_FILES by default).
	 *
	 * @return array Format: [] => XenForo_Upload objects
	 */
	public static function getUploadedFiles($formField, array $source = null)
	{
		if ($source === null)
		{
			$source = $_FILES;
		}
		if (empty($source[$formField]))
		{
			return array();
		}

		$files = array();
		$field = $source[$formField];

		if (isset($field['name']))
		{
			if (is_array($field['name']))
			{
				foreach (array_keys($field['name']) AS $key)
				{
					if ($field['name'][$key])
					{
						$files[] = new XenForo_Upload($field['name'][$key], $field['tmp_name'][$key]);
					}
				}
			}
			else if ($field['name'])
			{
				$files[] = new XenForo_Upload($field['name'], $field['tmp_name']);
			}
		}

		return $files;
	}

	/**
	 * Gets the file that was uploaded into the specified form field (via HTTP POST).
	 *
	 * @param string $formField Name of the form field
	 * @param array|null $source Source array ($_FILES by default).
	 *
	 * @return XenForo_Upload (or false)
	 */
	public static function getUploadedFile($formField, array $source = null)
	{
		$files = XenForo_Upload::getUploadedFiles($formField, $source);
		return reset($files);
	}
}