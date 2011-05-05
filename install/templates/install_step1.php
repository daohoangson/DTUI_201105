<?php
	class_exists('XenForo_Application', false) || die('Invalid');

	$__extraData['title'] = 'Verify Configuration';
?>
<p class="text">A configuration file already exists. Would you like to use the existing values?</p>

<div class="pairsRows">
	<dl>
		<dt>MySQL Server:</dt>
		<dd><?php echo htmlspecialchars($config['db']['host']); ?></dd>
	</dl>

	<dl>
		<dt>MySQL User Name:</dt>
		<dd><?php echo htmlspecialchars($config['db']['username']); ?></dd>
	</dl>

	<dl>
		<dt>MySQL Database Name:</dt>
		<dd><?php echo htmlspecialchars($config['db']['dbname']); ?></dd>
	</dl>
</div>

<p class="text">
	<a href="index.php?install/step/1b" class="button primary">Use these values</a>
	<a href="index.php?install/config" class="button">Edit configuration</a>
</p>