<?php
class DTUI_Helper_QrCode {
	public static function getUrl($contents, $width = 350, $height = 350) {
		if (is_array($contents)) {
			$contents = XenForo_ViewRenderer_Json::jsonEncodeForOutput($contents);
		}
		
		return 'http://chart.apis.google.com/chart?cht=qr&chs=' . $width . 'x' . $height . '&chl=' . urlencode($contents);
	}
}