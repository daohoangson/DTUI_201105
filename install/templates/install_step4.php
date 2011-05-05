<?php
	class_exists('XenForo_Application', false) || die('Invalid');

	$__extraData['title'] = 'Setup Options';
?>

<form action="index.php?install/step/4b" method="post" class="xenForm">
	<?php foreach ($renderedOptions AS $renderedOption) {
		echo $renderedOption;
	} ?>

	<dl class="ctrlUnit submitUnit">
		<dt></dt>
		<dd><input type="submit" value="Setup Options" accesskey="s" class="button primary" /></dd>
	</dl>
</form>