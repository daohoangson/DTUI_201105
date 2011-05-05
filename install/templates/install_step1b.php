<?php
	class_exists('XenForo_Application', false) || die('Invalid');

	$__extraData['title'] = 'Verify Configuration';
?>

<p class="text">Your configuration has been verified.</p>

<?php if ($existingInstall) { ?>
	<p class="text">XenForo is already installed in your database. Continuing will remove all XenForo-related data from your database!</p>

	<form action="index.php?install/step/2" method="post" class="xenForm">
		<dl class="ctrlUnit">
			<dt></dt>
			<dd>
				<ul>
					<li><label><input type="checkbox" name="remove" value="1" /> Remove all XenForo-related data, including posts and users</label></li>
				</ul>
			</dd>
		</dl>

		<dl class="ctrlUnit submitUnit">
			<dt></dt>
			<dd><input type="submit" value="Begin Installation" accesskey="s" class="button primary" /></dd>
		</dl>
	</form>
<?php } else { ?>
	<form action="index.php?install/step/2" method="post" class="xenForm">
		<input type="submit" value="Begin Installation" accesskey="s" class="button primary" />
	</form>
<?php }