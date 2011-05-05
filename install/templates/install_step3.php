<?php
	class_exists('XenForo_Application', false) || die('Invalid');

	$__extraData['title'] = 'Setup Administrator';
?>

<form action="index.php?install/step/3b" method="post" class="xenForm">
	<dl class="ctrlUnit">
		<dt><label for="ctrl_username">User name:</label></dt>
		<dd>
			<input type="text" name="username" class="textCtrl" id="ctrl_username" />
		</dd>
	</dl>

	<dl class="ctrlUnit">
		<dt><label for="ctrl_password">Password:</label></dt>
		<dd>
			<input type="password" name="password" class="textCtrl" id="ctrl_password" />
		</dd>
	</dl>

	<dl class="ctrlUnit">
		<dt><label for="ctrl_password_confirm">Confirm Password:</label></dt>
		<dd>
			<input type="password" name="password_confirm" class="textCtrl" id="ctrl_password_confirm" />
		</dd>
	</dl>

	<dl class="ctrlUnit">
		<dt><label for="ctrl_email">Email:</label></dt>
		<dd>
			<input type="text" name="email" class="textCtrl" id="ctrl_email" />
		</dd>
	</dl>

	<dl class="ctrlUnit submitUnit">
		<dt></dt>
		<dd><input type="submit" value="Create Administrator" accesskey="s" class="button primary" /></dd>
	</dl>
</form>