<?php
	class_exists('XenForo_Application', false) || die('Invalid');

	$__extraData['title'] = 'Upgrade Complete';
?>

<p class="text">Your upgrade to <?php echo htmlspecialchars($version); ?> has completed successfully!</p>

<p class="text"><a href="../admin.php" class="button primary">Enter your control panel</a></p>