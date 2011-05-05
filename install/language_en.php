<?php

return array(
	'admin_templates' => 'Admin Templates',
	'admin_templates_importing' => 'Admin Templates (Importing)',
	'core_master_data' => 'Core Master Data',
	'email_templates' => 'Email Templates',
	'email_templates_importing' => 'Email Templates (Importing)',
	'phrases' => 'Phrases',
	'phrases_importing' => 'Phrases (Importing)',
	'templates' => 'Templates',
	'templates_importing' => 'Templates (Importing)',
	'xenforo_copyright' => '<a href="http://xenforo.com" class="concealed" target="XenForo">Forum software by XenForo&trade;</a>, &copy;2011 XenForo Ltd.',
	'you_have_completed_installation_to_reinstall' => 'You have already completed installation. If you wish to reinstall, please delete the file internal_data/install-lock.php.',
	'you_cannot_proceed_unless_tables_removed' => 'You cannot proceed unless all XenForo database tables are removed.',
	'config_file_x_could_not_be_found' => 'The configuration file {file} could not be found.',
	'following_error_occurred_while_connecting_database' => '
		<div class="baseHtml">
			<p>The following error occurred while connecting to the database:</p>
			<blockquote><b>{error}</b></blockquote>
			<p>This indicates that your configuration information is not correct. Please check the values you have entered. If you are unsure what values are correct or how to proceed, please contact your host for help. These values are specific to your server.</p>
		</div>
	',
	'you_do_not_have_permission_upgrade' => 'You do not have permission to upgrade XenForo.', // TODO: add note about adjusting super admins
	'uh_oh_upgrade_did_not_complete' => 'Uh oh! The upgrade did not complete successfully. <a href="index.php">Please try again.</a>',
	'you_do_not_have_permission_view_page' => 'You do not have permission to view this page or perform this action.',
	'php_version_x_does_not_meet_requirements' => 'PHP 5.2.4 or newer is required. {version} does not meet this requirement. Please ask your host to upgrade PHP.',
	'php_must_not_be_in_safe_mode' => 'PHP must not be running in safe_mode. Please ask your host to disable the PHP safe_mode setting.',
	'required_php_extension_x_not_found' => 'The required PHP extension {extension} could not be found. Please ask your host to install this extension.',
	'gd_jpeg_support_missing' => 'The required PHP extension GD was found, but JPEG support is missing. Please ask your host to add support for JPEG images.',
	'required_php_xml_extensions_not_found' => 'The required PHP extensions for XML handling (DOM and SimpleXML) could not be found. Please ask your host to install these extensions.',
	'mysql_version_x_does_not_meet_requirements' => 'MySQL 5.0 or newer is required. {version} does not meet this requirement. Please ask your host to upgrade MySQL.',
	'directory_x_must_be_writable' => 'The directory {directory} must be writable. Please change the permissions on this directory to be world writable (chmod 0777). If the directory does not exist, please create it.',
	'all_directories_under_x_must_be_writable' => 'All directories under {directory} must be writable. Please change the permissions on these directories to be world writable (chmod 0777).',
);