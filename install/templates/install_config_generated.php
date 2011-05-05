<?php
	class_exists('XenForo_Application', false) || die('Invalid');

	$__extraData['title'] = 'Configuration Builder';
?>

<?php if ($written) { ?>
	<p class="text">The configuration information you entered is valid and has been written out to <?php echo htmlspecialchars($configFile); ?> automatically.</p>
<?php } else { ?>
	<p class="text">The configuration information you entered is valid.</p>

	<form action="index.php?install/config/save" method="post">
		<input type="hidden" name="config" value="<?php echo htmlspecialchars(json_encode($config)); ?>" />
		<input type="submit" value="Save Configuration" accesskey="s" class="button primary" />
	</form>

	<p class="text">
		Please save the configuration using the button above and upload it to <?php echo htmlspecialchars($configFile); ?>.
		Once this is completed, use the button below to continue.
	</p>
<?php } ?>

<p class="text"><a href="index.php?install/step/1b" class="button">Continue with Installation</a></p>