<?php
	class_exists('XenForo_Application', false) || die('Invalid');

	$__extraData['title'] = 'Configuration Builder';
?>

<form action="index.php?install/config" method="post" class="xenForm">
	<p class="text">
		To install XenForo, you must know how to connect to your MySQL server.
		If your hosting comes with cPanel or Plesk, you can find this information there.<br />
		<br />
		If you are unsure what to enter here, please contact your host. These values are specific to you.
	</p>

	<dl class="ctrlUnit">
		<dt><label for="ctrl_config_db_host">MySQL Server:</label></dt>
		<dd>
			<input type="text" name="config[db][host]" value="localhost" class="textCtrl" id="ctrl_config_db_host" />
			<p class="explain">Do not change this if you are unsure.</p>
		</dd>
	</dl>

	<dl class="ctrlUnit">
		<dt><label for="ctrl_config_db_port">MySQL Port:</label></dt>
		<dd>
			<input type="text" name="config[db][port]" value="3306" class="textCtrl" id="ctrl_config_db_port" />
			<p class="explain">Do not change this if you are unsure.</p>
		</dd>
	</dl>

	<dl class="ctrlUnit">
		<dt><label for="ctrl_config_db_username">MySQL User Name:</label></dt>
		<dd>
			<input type="text" name="config[db][username]" value="" class="textCtrl" id="ctrl_config_db_username" />
		</dd>
	</dl>

	<dl class="ctrlUnit">
		<dt><label for="ctrl_config_db_password">MySQL Password:</label></dt>
		<dd>
			<input type="text" name="config[db][password]" value="" class="textCtrl" autocomplete="off" id="ctrl_config_db_password" />
		</dd>
	</dl>

	<dl class="ctrlUnit">
		<dt><label for="ctrl_config_db_dbname">MySQL Database Name:</label></dt>
		<dd>
			<input type="text" name="config[db][dbname]" value="" class="textCtrl" id="ctrl_config_db_dbname" />
			<p class="explain">This database must already exist.</p>
		</dd>
	</dl>

	<dl class="ctrlUnit submitUnit">
		<dt></dt>
		<dd><input type="submit" value="Test &amp; Generate Configuration" accesskey="s" class="button primary" /></dd>
	</dl>
</form>

<!-- TODO: expand to cover more options -->