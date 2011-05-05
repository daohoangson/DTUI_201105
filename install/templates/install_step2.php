<?php
	class_exists('XenForo_Application', false) || die('Invalid');

	$__extraData['title'] = 'Install';
?>

<div class="baseHtml">
	<ol>
	<?php if ($removed) { ?>
		<li>Removed old tables...</li>
	<?php } ?>
	<li>Created tables...</li>
	<li>Inserted default data...</li>
	</ol>
</div>

<form action="index.php?install/step/2b" method="post" class="xenForm" id="continueForm">
	<input type="submit" value="Continue..." accesskey="s" class="button primary" />
</form>
<script>$('#continueForm').submit();</script>