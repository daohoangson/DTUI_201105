<?php
	class_exists('XenForo_Application', false) || die('Invalid');

	$__extraData['title'] = 'No Upgrade Available';
?>

<p class="text">
	You are already running the current version (<?php echo XenForo_Application::$version; ?>).
	To do a fresh install, <a href="index.php?install/">click here</a>.
</p>

<p class="text"><a href="index.php?upgrade/rebuild" class="button primary">Rebuild Master Data</a></p>