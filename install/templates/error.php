<?php
	class_exists('XenForo_Application', false) || die('Invalid');

	$__extraData['title'] = 'Error';
?>

<div class="errorOverlay">
	<?php if (count($error) == 1) { ?>
		<?php list($key, $value) = each($error); ?>
		<?php if ($showHeading) { ?><h2 class="heading">The following error occurred:</h2><?php } ?>

		<div class="baseHtml">
			<label for="ctrl_<?php echo htmlspecialchars($key); ?>" class="close"><?php echo $value; ?></label>
		</div>
	<?php } else { ?>
		<?php if ($showHeading) { ?><h2 class="heading">Please correct the following errors:</h2><?php } ?>

		<div class="baseHtml">
			<ul>
			<?php foreach ($error AS $key => $value) { ?>
				<li><label for="ctrl_<?php echo htmlspecialchars($key); ?>" class="close"><?php echo $value; ?></label></li>
			<?php } ?>
			</ul>
		</div>
	<?php } ?>

	<div class="close"></div>
</div>