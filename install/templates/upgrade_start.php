<?php
	class_exists('XenForo_Application', false) || die('Invalid');

	$__extraData['title'] = 'Upgrade System';
?>

<?php if ($errors) { ?>
	<p class="text">The following errors occurred while verifying that your server can run XenForo:</p>
	<div class="baseHtml">
		<ul>
		<?php foreach ($errors AS $error) { ?>
			<li><?php echo $error; ?></li>
		<?php } ?>
		</ul>
	</div>
	<p class="text">Please correct these errors and try again.</p>
<?php } else { ?>
	<form action="index.php?upgrade/run" method="post" class="xenForm">
		<dl class="ctrlUnit fullWidth">
			<dt></dt>
			<dd>Click the button below to begin the upgrade to <strong><?php echo $targetVersion; ?></strong>.</dd>
		</dl>

		<dl class="ctrlUnit submitUnit">
			<dt></dt>
			<dd><input type="submit" value="Begin Upgrade" accesskey="s" class="button primary" /></dd>
		</dl>

		<input type="hidden" name="_xfToken" value="<?php echo htmlspecialchars($visitor['csrf_token_page']); ?>" />
	</form>
<?php } ?>