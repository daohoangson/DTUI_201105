<?php
abstract class DTUI_Model_WithImage extends XenForo_Model {
	public function prepareImages(array &$data) {
		$data['images'] = array();
		foreach (array_keys($this->getImageSizes()) as $sizeCode) {
			if (file_exists($this->getImageFilePath($data, $sizeCode))) {
				$data['images'][$sizeCode] = $this->getImageUrl($data, $sizeCode);
			} else {
				// TODO: default image
			}
		} 
	}
	
	public function prepareImagesMultiple(array &$dataMultiple) {
		foreach ($dataMultiple as &$data) {
			$this->prepareImages($data);
		}
	}
	
	public function getImageFilePath(array $data, $sizeCode) {
		$internal = ltrim($this->_getImageInternal($data, $sizeCode), '/');
		
		if (!empty($internal)) {
			return XenForo_Helper_File::getExternalDataPath() . '/' . $internal;
		} else {
			return '';
		}
	}
	
	public function getImageUrl(array $data, $sizeCode) {
		$internal = $this->_getImageInternal($data, $sizeCode);
		
		if (!empty($internal)) {
			$requestPaths = XenForo_Application::get('requestPaths');
			
			return rtrim($requestPaths['fullBasePath'], '/') . '/' . ltrim(XenForo_Application::$externalDataPath, '/') . '/' . ltrim($internal, '/');
		} else {
			return '';
		}
	}
	
	public function getImageSizes() {
		return array(
			'l' => 96,
			'm' => 48,
			's' => 24,
			'u' => 0,
		); 
	}
	
	protected function _getImageFileNameFromName($name) {
		$safe = strtolower(preg_replace('/[^a-zA-Z_]/', '', str_replace(' ', '_', preg_replace('/\s{2,}/', '', $name))));
		
		if (!empty($safe)) {
			return '_' . $safe . '_';
		} else {
			return '';
		}
	}
	
	abstract protected function _getImageInternal(array $data, $sizeCode);
}