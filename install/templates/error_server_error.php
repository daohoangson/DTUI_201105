<?php
	class_exists('XenForo_Application', false) || die('Invalid');

	$__extraData['title'] = 'Server Error';
?>

<div class="baseHtml exception">
	<h2>Server Error</h2>
	<p><?php echo htmlspecialchars($traceHtml['error']); ?></p>
	<ol class="traceHtml">
		<?php echo $traceHtml['traceHtml']; ?>
	</ol>
</div>
