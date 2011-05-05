<?php
	class_exists('XenForo_Application', false) || die('Invalid');

	$__extraData['title'] = 'Upgrade System Login';
?>

<form action="../admin.php?login/login" method="post" class="xenForm">

	<dl class="ctrlUnit">
		<dt><label for="ctrl_login">Name or email:</label></dt>
		<dd>
			<input type="text" name="login" value="" class="textCtrl" id="ctrl_login" />
		</dd>
	</dl>

	<dl class="ctrlUnit">
		<dt><label for="ctrl_password">Password:</label></dt>
		<dd>
			<input type="password" name="password" value="" class="textCtrl" id="ctrl_password" />
		</dd>
	</dl>

	<dl class="ctrlUnit submitUnit">
		<dt></dt>
		<dd><input type="submit" value="Log in" accesskey="s" class="button primary" /></dd>
	</dl>

	<input type="hidden" name="redirect" value="<?php echo htmlspecialchars($requestPaths['requestUri']); ?>" />
	<input type="hidden" name="upgrade" value="1" />
	<input type="hidden" name="_xfToken" value="<?php echo htmlspecialchars($visitor['csrf_token_page']); ?>" />
</form>