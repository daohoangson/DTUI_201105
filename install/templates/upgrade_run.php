<?php
	class_exists('XenForo_Application', false) || die('Invalid');

	$__extraData['title'] = 'Upgrading...';
?>

Upgrading... <?php echo $versionName; ?>,  Step <?php echo htmlspecialchars($step); ?>
<img src="../styles/default/xenforo/widgets/ajaxload.info_000000_facebook.gif" alt="Loading..." id="loadingImage" style="display:none" />

<form action="index.php?upgrade/run" method="post" id="continueForm">
	<input type="submit" value="Continue" class="button" />

	<input type="hidden" name="run_version" value="<?php echo htmlspecialchars($newRunVersion); ?>" />
	<input type="hidden" name="step" value="<?php echo htmlspecialchars($newStep); ?>" />
	<input type="hidden" name="_xfToken" value="<?php echo htmlspecialchars($visitor['csrf_token_page']); ?>" />
</form>

<script type="text/javascript">
$(function() {
	$('#continueForm').submit();
	$('#continueForm .button').hide();
	$('#loadingImage').show();
});
</script>