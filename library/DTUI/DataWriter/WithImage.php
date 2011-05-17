<?php
abstract class DTUI_DataWriter_WithImage extends XenForo_DataWriter {
	const IMAGE_PREPARED = 'imagePrepared';
	
	final protected function _postSave() {
		$uploaded = $this->getExtraData(self::IMAGE_PREPARED);
		
		if ($uploaded) {
			if ($this->isUpdate()) {
				$this->_removeOldImages();
			}
			
			$this->_moveImages($uploaded);
		} else {
			$this->_moveOldImages();
		}
	}
	
	final protected function _postDelete() {
		$this->_removeOldImages();
	}
	
	public function setImage(XenForo_Upload $upload) {
		if (!$upload->isValid()) {
			throw new XenForo_Exception($upload->getErrors(), true);
		}

		if (!$upload->isImage()) {
			throw new XenForo_Exception(new XenForo_Phrase('uploaded_file_is_not_valid_image'), true);
		};

		$imageType = $upload->getImageInfoField('type');
		if (!in_array($imageType, array(IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG))) {
			throw new XenForo_Exception(new XenForo_Phrase('uploaded_file_is_not_valid_image'), true);
		}

		$this->setExtraData(self::IMAGE_PREPARED, $this->_prepareImage($upload));
		
		return true;
	}
	
	protected function _prepareImage(XenForo_Upload $upload) {
		$outputFiles = array();
		$fileName = $upload->getTempFile();
		$imageType = $upload->getImageInfoField('type');
		$outputType = $imageType;
		$width = $upload->getImageInfoField('width');
		$height = $upload->getImageInfoField('height');
		$imageSizes = $this->_getImageSizes();
		$imageQuality = $this->_getImageQuality();

		reset($imageSizes);

		while (list($sizeCode, $maxDimensions) = each($imageSizes)) {
			$newTempFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
			$image = XenForo_Image_Abstract::createFromFile($fileName, $imageType);
			if (!$image) {
				continue;
			}

			if ($maxDimensions > 0) {
				$image->thumbnailFixedShorterSide($maxDimensions);
	
				if ($image->getOrientation() != XenForo_Image_Abstract::ORIENTATION_SQUARE) {
					$x = floor(($image->getWidth() - $maxDimensions) / 2);
					$y = floor(($image->getHeight() - $maxDimensions) / 2);
					$image->crop($x, $y, $maxDimensions, $maxDimensions);
				}
			}

			$image->output($outputType, $newTempFile, $imageQuality);
			unset($image);

			$outputFiles[$sizeCode] = $newTempFile;
		}

		if (count($outputFiles) != count($imageSizes)) {
			foreach ($outputFiles AS $tempFile) {
				if ($tempFile != $fileName) {
					@unlink($tempFile);
				}
			}
			throw new XenForo_Exception('Non-image passed in to _prepareImage');
		}
		
		return $outputFiles;
	}
	
	protected function _moveImages($uploaded) {
		if (is_array($uploaded)) {
			$data = $this->getMergedData();
			foreach ($uploaded as $sizeCode => $tempFile) {
				$filePath = $this->_getImageFilePath($data, $sizeCode);
				$directory = dirname($filePath);
 
				if (XenForo_Helper_File::createDirectory($directory, true) && is_writable($directory)) {
					if (file_exists($filePath)) {
						unlink($filePath);
					}
					
					$success = @rename($tempFile, $filePath);
					if ($success) {
						XenForo_Helper_File::makeWritableByFtpUser($filePath);
					}
				}
			}
		}
	}
	
	protected function _removeOldImages() {
		$existingData = $this->getMergedExistingData();
		foreach (array_keys($this->_getImageSizes()) as $sizeCode) {
			$filePath = $this->_getImageFilePath($existingData, $sizeCode);
			if (!empty($filePath)) @unlink($filePath);
		}
	}
	
	protected function _moveOldImages() {
		$existingData = $this->getMergedExistingData();
		$data = $this->getMergedData();
		
		foreach (array_keys($this->_getImageSizes()) as $sizeCode) {
			$existingFilePath = $this->_getImageFilePath($existingData, $sizeCode);
			$filePath = $this->_getImageFilePath($data, $sizeCode);
			
			if ($existingFilePath == $filePath) {
				return; // stop running any further
			}
			
			if ($existingFilePath == '' OR $filePath == '') {
				// nothing to do here
				continue;
			}
			
			if ($existingFilePath != $filePath) {
				// move the image now
				@rename($existingFilePath, $filePath);
			}
		}
	}
	
	protected function _getImageSizes() {
		return $this->_getImageModel()->getImageSizes();
	}
	
	protected function _getImageQuality() {
		return 85;
	}
	
	protected function _getImageFilePath(array $data, $sizeCode) {
		return $this->_getImageModel()->getImageFilePath($data, $sizeCode);
	}
	
	abstract protected function _getImageModel();
}