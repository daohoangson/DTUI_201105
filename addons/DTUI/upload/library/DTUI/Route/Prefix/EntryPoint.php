<?php
class DTUI_Route_Prefix_EntryPoint implements XenForo_Route_Interface {
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router) {
		$parts = explode('/', $routePath);
		if (count($parts) > 1) {
			$data = array_pop($parts);
			$request->setParam('data', $data);
			$action = implode('/', $parts);
		} else {
			$action = $routePath;
		}
		
		return $router->getRouteMatch('DTUI_ControllerPublic_EntryPoint', $action, 'dtui');
	}

	public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams) {
		if (!empty($data)) {
			return XenForo_Link::buildBasicLink($outputPrefix, $action . '/' . $data, $extension);
		} else {
			return XenForo_Link::buildBasicLink($outputPrefix, $action, $extension);
		}
	}
}