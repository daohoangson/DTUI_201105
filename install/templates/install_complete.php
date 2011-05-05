<?php
	class_exists('XenForo_Application', false) || die('Invalid');

	$__extraData['title'] = 'Installation Complete';
?>

<p class="text">XenForo <?php echo XenForo_Application::$version; ?> has been installed successfully!</p>

<p class="text"><a href="../admin.php" class="button primary">Enter your control panel</a></p>