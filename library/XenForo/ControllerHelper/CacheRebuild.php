<?php

class XenForo_ControllerHelper_CacheRebuild extends XenForo_ControllerHelper_Abstract
{
	public function rebuildCache(array $input, $redirect, $submitUrl, $doRebuild = false)
	{
		$caches = $input['caches'];
		$position = $input['position'];

		$viewParams = array(
			'detailedMessage' => ''
		);

		if ($doRebuild && $caches)
		{
			$cache = array_shift($caches);
			if (!is_array($cache))
			{
				$options = array();
			}
			else
			{
				list($cache, $options) = $cache;
			}

			$rebuilt = XenForo_CacheRebuilder_Abstract::getCacheRebuilder($cache)->rebuild($position, $options, $detailedMessage);
			if (is_int($rebuilt))
			{
				// still doing this one
				array_unshift($caches, array($cache, $options));
				$position = $rebuilt;

				$viewParams['detailedMessage'] = $detailedMessage;
			}
			else
			{
				$position = 0;
			}
		}

		if (!$caches)
		{
			return $this->_controller->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				$redirect
			);
		}

		$nextCache = reset($caches);
		if (is_array($nextCache))
		{
			list($nextCache) = $nextCache;
		}
		$nextBuilder = XenForo_CacheRebuilder_Abstract::getCacheRebuilder($nextCache);

		$viewParams['rebuildMessage'] = $nextBuilder->getRebuildMessage();
		$viewParams['showExitLink'] = $nextBuilder->showExitLink();
		$viewParams['submitUrl'] = $submitUrl;
		$viewParams['elements'] = array(
			'caches' => json_encode($caches),
			'position' => $position,
			'redirect' => $redirect
		);

		return $viewParams;
	}
}