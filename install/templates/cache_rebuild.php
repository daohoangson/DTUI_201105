<?php
	class_exists('XenForo_Application', false) || die('Invalid');

	$__extraData['title'] = 'Rebuilding...';
?>

<form action="<?php echo $submitUrl; ?>" method="post" class="CacheRebuild">

	<p id="ProgressText">Rebuilding... <span class="RebuildMessage"><?php echo htmlspecialchars($rebuildMessage); ?></span> <span class="DetailedMessage"><?php echo htmlspecialchars($detailedMessage); ?></span></p>
	<p id="ErrorText" style="display: none">An error occurred or the request was stopped.</p>

	<input type="hidden" name="process" value="1" />
	<?php foreach ($elements AS $name => $value) { ?>
		<input type="hidden" name="<?php echo htmlspecialchars($name); ?>" value="<?php echo htmlspecialchars($value); ?>" />
	<?php } ?>

	<input type="submit" class="button" value="Rebuild Caches" />

	<?php if (!empty($visitor)) { ?>
		<input type="hidden" name="_xfToken" value="<?php echo htmlspecialchars($visitor['csrf_token_page']); ?>" />
	<?php } ?>
</form>