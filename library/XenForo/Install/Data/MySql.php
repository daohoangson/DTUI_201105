<?php

class XenForo_Install_Data_MySql
{
	public static function getTables()
	{
		$tables = array();

$tables['xf_addon'] = "
	CREATE TABLE xf_addon (
		addon_id VARCHAR(25) NOT NULL,
		title VARCHAR(75) NOT NULL,
		version_string VARCHAR(30) NOT NULL DEFAULT '',
		version_id INT UNSIGNED NOT NULL DEFAULT 0,
		url VARCHAR(100) NOT NULL,
		install_callback_class VARCHAR(75) NOT NULL DEFAULT '',
		install_callback_method VARCHAR(75) NOT NULL DEFAULT '',
		uninstall_callback_class VARCHAR(75) NOT NULL DEFAULT '',
		uninstall_callback_method VARCHAR(75) NOT NULL DEFAULT '',
		active TINYINT UNSIGNED NOT NULL,
		PRIMARY KEY (addon_id),
		KEY title (title)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_admin'] = "
	CREATE TABLE xf_admin (
		user_id INT UNSIGNED NOT NULL,
		extra_user_group_ids VARBINARY(255) NOT NULL,
		last_login INT UNSIGNED NOT NULL DEFAULT 0,
		permission_cache MEDIUMBLOB,
		PRIMARY KEY (user_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_admin_navigation'] = "
	CREATE TABLE xf_admin_navigation (
		navigation_id VARCHAR(25) NOT NULL,
		parent_navigation_id VARCHAR(25) NOT NULL,
		display_order INT UNSIGNED NOT NULL DEFAULT 0,
		link VARCHAR(50) NOT NULL DEFAULT '',
		admin_permission_id VARCHAR(25) NOT NULL DEFAULT '',
		debug_only TINYINT UNSIGNED NOT NULL DEFAULT 0,
		hide_no_children TINYINT UNSIGNED NOT NULL DEFAULT 0,
		addon_id varchar(25) NOT NULL DEFAULT '',
		PRIMARY KEY (navigation_id),
		KEY parent_navigation_id_display_order (parent_navigation_id, display_order)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_admin_permission'] = "
	CREATE TABLE xf_admin_permission (
		admin_permission_id VARCHAR(25) NOT NULL,
		display_order INT UNSIGNED NOT NULL DEFAULT 0,
		addon_id VARCHAR(25) NOT NULL DEFAULT '',
		PRIMARY KEY (admin_permission_id),
		KEY display_order (display_order)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_admin_permission_entry'] = "
	CREATE TABLE xf_admin_permission_entry (
		user_id INT(11) NOT NULL,
		admin_permission_id VARCHAR(25) NOT NULL,
		PRIMARY KEY (user_id, admin_permission_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_admin_template'] = "
	CREATE TABLE xf_admin_template (
		template_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
		title VARCHAR(50) NOT NULL,
		template MEDIUMTEXT NOT NULL COMMENT 'User-editable HTML and template syntax',
		template_parsed MEDIUMBLOB NOT NULL,
		addon_id VARCHAR(25) NOT NULL DEFAULT '',
		UNIQUE KEY title (title)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_admin_template_compiled'] = "
	CREATE TABLE xf_admin_template_compiled (
		language_id INT UNSIGNED NOT NULL,
		title VARCHAR(50) NOT NULL,
		template_compiled MEDIUMBLOB NOT NULL COMMENT 'Executable PHP code built by template compiler',
		PRIMARY KEY (language_id, title)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_admin_template_include'] = "
	CREATE TABLE xf_admin_template_include (
		source_id INT UNSIGNED NOT NULL,
		target_id INT UNSIGNED NOT NULL,
		PRIMARY KEY (source_id, target_id),
		KEY target (target_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_admin_template_phrase'] = "
	CREATE TABLE xf_admin_template_phrase (
		template_id INT UNSIGNED NOT NULL,
		phrase_title VARCHAR(75) NOT NULL,
		PRIMARY KEY (template_id, phrase_title),
		KEY phrase_title (phrase_title)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_attachment'] = "
	CREATE TABLE xf_attachment (
		attachment_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		data_id INT UNSIGNED NOT NULL,
		content_type VARCHAR(25) NOT NULL,
		content_id INT UNSIGNED NOT NULL,
		attach_date INT UNSIGNED NOT NULL,
		temp_hash VARCHAR(32) NOT NULL DEFAULT '',
		unassociated TINYINT UNSIGNED NOT NULL,
		view_count INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (attachment_id),
		KEY content_type_id_date (content_type, content_id, attach_date),
		KEY temp_hash_attach_date (temp_hash, attach_date),
		KEY unassociated_attach_date (unassociated, attach_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_attachment_data'] = "
	CREATE TABLE xf_attachment_data (
		data_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		user_id INT UNSIGNED NOT NULL,
		upload_date INT UNSIGNED NOT NULL,
		filename VARCHAR(100) NOT NULL,
		file_size INT UNSIGNED NOT NULL,
		file_hash VARCHAR(32) NOT NULL,
		width INT UNSIGNED NOT NULL DEFAULT '0',
		height INT UNSIGNED NOT NULL DEFAULT '0',
		thumbnail_width INT UNSIGNED NOT NULL DEFAULT '0',
		thumbnail_height INT UNSIGNED NOT NULL DEFAULT '0',
		attach_count INT UNSIGNED NOT NULL DEFAULT '0',
		PRIMARY KEY (data_id),
		KEY user_id_upload_date (user_id, upload_date),
		KEY attach_count (attach_count)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_attachment_view'] = "
	CREATE TABLE xf_attachment_view (
		attachment_id INT UNSIGNED NOT NULL,
		KEY attachment_id (attachment_id)
	) ENGINE = MEMORY CHARACTER SET utf8 COLLATE utf8_general_ci
";


$tables['xf_ban_email'] = "
	CREATE TABLE xf_ban_email (
		banned_email VARCHAR(50) NOT NULL,
		PRIMARY KEY (banned_email)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_bb_code_media_site'] = "
	CREATE TABLE xf_bb_code_media_site (
		media_site_id VARCHAR(25) NOT NULL,
		site_title VARCHAR(50) NOT NULL,
		site_url VARCHAR(100) NOT NULL DEFAULT '',
		match_urls TEXT NOT NULL,
		embed_html TEXT NOT NULL,
		supported TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'If 0, this media type will not be listed as available, but will still be usable.',
		PRIMARY KEY (media_site_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_code_event'] = "
	CREATE TABLE xf_code_event (
		event_id VARCHAR(50) NOT NULL PRIMARY KEY,
		description TEXT NOT NULL,
		addon_id VARCHAR(25) NOT NULL DEFAULT ''
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_code_event_listener'] = "
	CREATE TABLE xf_code_event_listener (
		event_listener_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		event_id VARCHAR(50) NOT NULL,
		execute_order INT UNSIGNED NOT NULL,
		description TEXT NOT NULL,
		callback_class VARCHAR(75) NOT NULL,
		callback_method VARCHAR(50) NOT NULL,
		active TINYINT UNSIGNED NOT NULL,
		addon_id VARCHAR(25) NOT NULL,
		PRIMARY KEY  (event_listener_id),
		KEY event_id_execute_order (event_id, execute_order),
		KEY addon_id_event_id (addon_id, event_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";


$tables['xf_content_type'] = "
	CREATE TABLE xf_content_type (
		content_type VARCHAR(25) NOT NULL,
		addon_id VARCHAR(25) NOT NULL DEFAULT '',
		fields MEDIUMBLOB NOT NULL,
		PRIMARY KEY (content_type)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_content_type_field'] = "
	CREATE TABLE xf_content_type_field (
		content_type VARCHAR(25) NOT NULL,
		field_name VARCHAR(50) NOT NULL,
		field_value VARCHAR(75) NOT NULL,
		PRIMARY KEY (content_type, field_name),
		KEY field_name (field_name)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_conversation_master'] = "
	CREATE TABLE xf_conversation_master (
		conversation_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		title VARCHAR(150) NOT NULL,
		user_id INT UNSIGNED NOT NULL,
		username VARCHAR(50) NOT NULL,
		start_date INT UNSIGNED NOT NULL,
		open_invite TINYINT UNSIGNED NOT NULL DEFAULT 0,
		conversation_open TINYINT UNSIGNED NOT NULL DEFAULT 1,
		reply_count INT UNSIGNED NOT NULL DEFAULT 0,
		recipient_count INT UNSIGNED NOT NULL DEFAULT 0,
		first_message_id INT UNSIGNED NOT NULL,
		last_message_date INT UNSIGNED NOT NULL,
		last_message_id INT UNSIGNED NOT NULL,
		last_message_user_id INT UNSIGNED NOT NULL,
		last_message_username VARCHAR(50) NOT NULL,
		PRIMARY KEY (conversation_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_conversation_message'] = "
	CREATE TABLE xf_conversation_message (
		message_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		conversation_id INT UNSIGNED NOT NULL,
		message_date INT UNSIGNED NOT NULL,
		user_id INT UNSIGNED NOT NULL,
		username VARCHAR(50) NOT NULL,
		message MEDIUMTEXT NOT NULL,
		PRIMARY KEY (message_id),
		KEY conversation_id_message_date (conversation_id, message_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_conversation_recipient'] = "
	CREATE TABLE xf_conversation_recipient (
		conversation_id INT UNSIGNED NOT NULL,
		user_id INT UNSIGNED NOT NULL,
		recipient_state ENUM('active', 'deleted', 'deleted_ignored') NOT NULL,
		last_read_date INT UNSIGNED NOT NULL,
		PRIMARY KEY (conversation_id, user_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_conversation_user'] = "
	CREATE TABLE xf_conversation_user (
		conversation_id INT UNSIGNED NOT NULL,
		owner_user_id INT UNSIGNED NOT NULL,
		is_unread TINYINT UNSIGNED NOT NULL,
		reply_count INT UNSIGNED NOT NULL,
		last_message_date INT UNSIGNED NOT NULL,
		last_message_id INT UNSIGNED NOT NULL,
		last_message_user_id INT UNSIGNED NOT NULL,
		last_message_username VARCHAR(50) NOT NULL,
		PRIMARY KEY (conversation_id, owner_user_id),
		KEY owner_user_id_last_message_date (owner_user_id, last_message_date),
		KEY owner_user_id_is_unread (owner_user_id, is_unread)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_cron_entry'] = "
	CREATE TABLE xf_cron_entry (
		entry_id VARCHAR(25) NOT NULL,
		cron_class VARCHAR(75) NOT NULL,
		cron_method VARCHAR(50) NOT NULL,
		run_rules MEDIUMBLOB NOT NULL,
		active TINYINT UNSIGNED NOT NULL,
		next_run INT UNSIGNED NOT NULL,
		addon_id VARCHAR(25) NOT NULL,
		PRIMARY KEY (entry_id),
		KEY active_next_run (active, next_run)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_data_registry'] = "
	CREATE TABLE xf_data_registry (
		data_key VARCHAR(25) NOT NULL PRIMARY KEY,
		data_value MEDIUMBLOB NOT NULL
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_deletion_log'] = "
	CREATE TABLE xf_deletion_log (
		content_type VARCHAR(25) NOT NULL,
		content_id INT(11) NOT NULL,
		delete_date INT(11) NOT NULL,
		delete_user_id INT(11) NOT NULL,
		delete_username VARCHAR(50) NOT NULL,
		delete_reason VARCHAR(100) NOT NULL DEFAULT '',
		PRIMARY KEY (content_type, content_id),
		KEY delete_user_id_date (delete_user_id, delete_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_email_template'] = "
	CREATE TABLE xf_email_template (
		template_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		title VARCHAR(50) NOT NULL,
		custom TINYINT UNSIGNED NOT NULL,
		subject MEDIUMTEXT NOT NULL COMMENT 'User-editable subject with template syntax',
		subject_parsed MEDIUMBLOB NOT NULL,
		body_text MEDIUMTEXT NOT NULL COMMENT 'User-editable plain text body with template syntax',
		body_text_parsed MEDIUMBLOB NOT NULL,
		body_html MEDIUMTEXT NOT NULL COMMENT 'User-editable HTML body t with template syntax',
		body_html_parsed MEDIUMBLOB NOT NULL,
		addon_id VARCHAR(25) NOT NULL DEFAULT '',
		PRIMARY KEY (template_id),
		UNIQUE KEY title_custom (title, custom)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_email_template_compiled'] = "
	CREATE TABLE xf_email_template_compiled (
		language_id INT UNSIGNED NOT NULL,
		title VARCHAR(50) NOT NULL,
		template_compiled MEDIUMBLOB NOT NULL COMMENT 'Executable PHP code from compilation. Outputs 3 vars.',
		PRIMARY KEY (title, language_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_email_template_phrase'] = "
	CREATE TABLE xf_email_template_phrase (
		title VARCHAR(50) NOT NULL,
		phrase_title VARCHAR(75) NOT NULL,
		PRIMARY KEY (title, phrase_title),
		KEY phrase_title (phrase_title)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_error_log'] = "
	CREATE TABLE xf_error_log (
		error_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		exception_date INT UNSIGNED NOT NULL,
		user_id INT UNSIGNED DEFAULT NULL,
		ip_address INT UNSIGNED NOT NULL DEFAULT 0,
		exception_type VARCHAR(75) NOT NULL,
		message TEXT NOT NULL,
		filename VARCHAR(255) NOT NULL,
		line INT UNSIGNED NOT NULL,
		trace_string MEDIUMTEXT NOT NULL,
		request_state MEDIUMBLOB NOT NULL,
		PRIMARY KEY (error_id),
		KEY exception_date (exception_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_feed'] = "
	CREATE TABLE xf_feed (
		feed_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		title VARCHAR(250) NOT NULL,
		url VARCHAR(2083) NOT NULL,
		frequency INT UNSIGNED NOT NULL DEFAULT 1800,
		node_id INT UNSIGNED NOT NULL,
		user_id INT UNSIGNED NOT NULL DEFAULT 0,
		title_template VARCHAR(250) NOT NULL DEFAULT '',
		message_template MEDIUMTEXT NOT NULL,
		discussion_visible TINYINT UNSIGNED NOT NULL DEFAULT 1,
		discussion_open TINYINT UNSIGNED NOT NULL DEFAULT 1,
		discussion_sticky TINYINT UNSIGNED NOT NULL DEFAULT 0,
		last_fetch INT UNSIGNED NOT NULL DEFAULT 0,
		active INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (feed_id),
		KEY active (active)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_feed_log'] = "
	CREATE TABLE xf_feed_log (
		feed_id INT UNSIGNED NOT NULL,
		unique_id VARCHAR(250) NOT NULL,
		hash CHAR(32) NOT NULL COMMENT 'MD5(title + content)',
		thread_id INT UNSIGNED NOT NULL,
		PRIMARY KEY (feed_id,unique_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";


$tables['xf_flood_check'] = "
	CREATE TABLE xf_flood_check (
		user_id INT UNSIGNED NOT NULL,
		flood_action VARCHAR(25) NOT NULL,
		flood_time INT UNSIGNED NOT NULL,
		PRIMARY KEY (user_id, flood_action),
		KEY flood_time (flood_time)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_forum'] = "
	CREATE TABLE xf_forum (
		node_id INT UNSIGNED NOT NULL PRIMARY KEY,
		discussion_count INT UNSIGNED NOT NULL DEFAULT 0,
		message_count INT UNSIGNED NOT NULL DEFAULT 0,
		last_post_id INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Most recent post_id',
		last_post_date INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Date of most recent post',
		last_post_user_id INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'User_id of user posting most recently',
		last_post_username VARCHAR(50) NOT NULL DEFAULT '' COMMENT 'Username of most recently-posting user',
		last_thread_title VARCHAR(150) NOT NULL DEFAULT '' COMMENT 'Title of thread most recent post is in',
		moderate_messages TINYINT UNSIGNED NOT NULL DEFAULT 0,
		allow_posting TINYINT UNSIGNED NOT NULL DEFAULT 1
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_forum_read'] = "
	CREATE TABLE xf_forum_read (
		forum_read_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
		user_id INT UNSIGNED NOT NULL,
		node_id INT UNSIGNED NOT NULL,
		forum_read_date INT UNSIGNED NOT NULL,
		UNIQUE KEY user_id_node_id (user_id, node_id),
		KEY node_id (node_id),
		KEY forum_read_date (forum_read_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_identity_service'] = "
	CREATE TABLE xf_identity_service (
		identity_service_id VARCHAR(25) NOT NULL,
		model_class VARCHAR(75) NOT NULL COMMENT 'Name of PHP model class, extended from XenForo_Model_IdentityService_Abstract',
		PRIMARY KEY (identity_service_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_import_log'] = "
	CREATE TABLE xf_import_log (
		content_type VARBINARY(25) NOT NULL,
		old_id VARBINARY(50) NOT NULL,
		new_id VARBINARY(50) NOT NULL,
		PRIMARY KEY (content_type, old_id)
	) ENGINE = MYISAM CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_ip'] = "
	CREATE TABLE xf_ip (
		ip_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		user_id INT UNSIGNED NOT NULL,
		content_type varbinary(25) NOT NULL,
		content_id INT UNSIGNED NOT NULL,
		action varbinary(25) NOT NULL DEFAULT '',
		ip INT UNSIGNED NOT NULL,
		log_date INT UNSIGNED NOT NULL,
		PRIMARY KEY (ip_id),
		KEY user_id_log_date (user_id, log_date),
		KEY ip_log_date (ip, log_date),
		KEY content_type_content_id (content_type, content_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_ip_match'] = "
	CREATE TABLE xf_ip_match (
		ip VARCHAR(25) NOT NULL,
		match_type ENUM('banned','discouraged') NOT NULL DEFAULT 'banned',
		first_octet TINYINT UNSIGNED NOT NULL,
		start_range INT UNSIGNED NOT NULL COMMENT 'PHP ip2long format',
		end_range INT UNSIGNED NOT NULL COMMENT 'PHP ip2long format',
		PRIMARY KEY (ip, match_type),
		KEY start_range (start_range)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_language'] = "
	CREATE TABLE xf_language (
		language_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		parent_id INT UNSIGNED NOT NULL,
		parent_list VARBINARY(100) NOT NULL,
		title VARCHAR(50) NOT NULL,
		date_format VARCHAR(30) NOT NULL,
		time_format VARCHAR(15) NOT NULL,
		decimal_point VARCHAR(1) NOT NULL,
		thousands_separator VARCHAR(1) NOT NULL,
		phrase_cache MEDIUMBLOB NOT NULL,
		language_code VARCHAR(25) NOT NULL DEFAULT '',
		PRIMARY KEY (language_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_liked_content'] = "
	CREATE TABLE xf_liked_content (
		like_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		content_type VARCHAR(25) NOT NULL,
		content_id INT UNSIGNED NOT NULL,
		like_user_id INT UNSIGNED NOT NULL,
		like_date INT UNSIGNED NOT NULL,
		content_user_id INT UNSIGNED NOT NULL,
		PRIMARY KEY (like_id),
		UNIQUE KEY content_type_id_like_user_id (content_type, content_id, like_user_id),
		KEY content_user_id_like_date (content_user_id, like_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_link_forum'] = "
	CREATE TABLE xf_link_forum (
		node_id INT UNSIGNED NOT NULL,
		link_url VARCHAR(150) NOT NULL,
		redirect_count INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (node_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_login_attempt'] = "
	CREATE TABLE xf_login_attempt (
		login VARCHAR(60) NOT NULL,
		ip_address INT UNSIGNED NOT NULL,
		attempt_date INT UNSIGNED NOT NULL,
		KEY login_check (login, ip_address, attempt_date),
		KEY attempt_date (attempt_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_moderation_queue'] = "
	CREATE TABLE xf_moderation_queue (
		content_type VARCHAR(25) NOT NULL,
		content_id INT UNSIGNED NOT NULL,
		content_date INT UNSIGNED NOT NULL DEFAULT '0',
		PRIMARY KEY (content_type, content_id),
		KEY content_date (content_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_moderator'] = "
	CREATE TABLE xf_moderator (
		user_id INT UNSIGNED NOT NULL,
		is_super_moderator TINYINT UNSIGNED NOT NULL,
		moderator_permissions MEDIUMBLOB NOT NULL,
		extra_user_group_ids VARBINARY(255) NOT NULL,
		PRIMARY KEY (user_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_moderator_content'] = "
	CREATE TABLE xf_moderator_content (
		moderator_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		content_type VARCHAR(25) NOT NULL,
		content_id INT UNSIGNED NOT NULL,
		user_id INT UNSIGNED NOT NULL,
		moderator_permissions MEDIUMBLOB NOT NULL,
		PRIMARY KEY (moderator_id),
		UNIQUE KEY content_user_id (content_type, content_id, user_id),
		KEY user_id (user_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_news_feed'] = "
	CREATE TABLE xf_news_feed (
		news_feed_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		user_id INT UNSIGNED NOT NULL COMMENT 'The user who performed the action',
		username VARCHAR(50) NOT NULL DEFAULT '' COMMENT 'Corresponds to user_id',
		content_type VARCHAR(25) NOT NULL COMMENT 'eg: thread',
		content_id INT UNSIGNED NOT NULL,
		action VARCHAR(25) NOT NULL COMMENT 'eg: edit',
		event_date INT UNSIGNED NOT NULL,
		extra_data MEDIUMBLOB NOT NULL COMMENT 'Serialized. Stores any extra data relevant to the action',
		PRIMARY KEY (news_feed_id),
		KEY userId_eventDate (user_id, event_date),
		KEY contentType_contentId (content_type, content_id),
		KEY event_date (event_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_node'] = "
	CREATE TABLE xf_node (
		node_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		title VARCHAR(50) NOT NULL,
		description TEXT NOT NULL,
 		node_name VARCHAR(50) DEFAULT NULL COMMENT 'Unique column used as string ID by some node types',
		node_type_id VARBINARY(25) NOT NULL,
		parent_node_id INT UNSIGNED NOT NULL DEFAULT 0,
		display_order INT UNSIGNED NOT NULL DEFAULT 1,
		display_in_list TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'If 0, hidden from node list. Still counts for lft/rgt.',
		lft INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Nested set info ''left'' value',
		rgt INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Nested set info ''right'' value',
		depth INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Depth = 0: no parent',
		style_id INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Style override for specific node',
		effective_style_id INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Style override; pushed down tree',
		PRIMARY KEY (node_id),
		UNIQUE KEY node_name_unique (node_name, node_type_id),
		KEY parent_node_id (parent_node_id),
		KEY display_order (display_order),
		KEY lft (lft)
	) ENGINE = InnoDB  DEFAULT CHARSET=utf8 COLLATE utf8_general_ci
";

$tables['xf_node_type'] = "
	CREATE TABLE xf_node_type (
		node_type_id VARBINARY(25) NOT NULL,
		handler_class VARCHAR(75) NOT NULL,
		controller_admin_class VARCHAR(75) NOT NULL COMMENT 'extends XenForo_ControllerAdmin_Abstract',
		datawriter_class VARCHAR(75) NOT NULL COMMENT 'extends XenForo_DataWriter_Node',
		permission_group_id VARCHAR(25) NOT NULL DEFAULT '',
		moderator_interface_group_id VARCHAR(50) NOT NULL DEFAULT '',
		public_route_prefix VARCHAR(25) NOT NULL,
		PRIMARY KEY (node_type_id)
	) ENGINE = InnoDB DEFAULT CHARSET=utf8 COLLATE utf8_general_ci
";

$tables['xf_option'] = "
	CREATE TABLE xf_option (
		option_id VARCHAR(50) NOT NULL,
		option_value MEDIUMBLOB NOT NULL,
		default_value MEDIUMBLOB NOT NULL,
		edit_format ENUM('textbox','spinbox','onoff','radio','select','checkbox','template','callback') NOT NULL,
		edit_format_params MEDIUMTEXT NOT NULL,
		data_type ENUM('string','integer','numeric','array','boolean','positive_integer','unsigned_integer','unsigned_numeric') NOT NULL,
		sub_options MEDIUMTEXT NOT NULL,
		can_backup TINYINT UNSIGNED NOT NULL,
		validation_class VARCHAR(75) NOT NULL,
		validation_method VARCHAR(50) NOT NULL,
		addon_id VARCHAR(25) NOT NULL DEFAULT '',
		PRIMARY KEY (option_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_option_group'] = "
	CREATE TABLE xf_option_group (
		group_id VARCHAR(50) NOT NULL,
		display_order INT UNSIGNED NOT NULL,
		debug_only TINYINT UNSIGNED NOT NULL,
		addon_id VARCHAR(25) NOT NULL DEFAULT '',
		PRIMARY KEY (group_id),
		KEY display_order (display_order)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_option_group_relation'] = "
	CREATE TABLE xf_option_group_relation (
		option_id VARCHAR(50) NOT NULL,
		group_id VARCHAR(50) NOT NULL,
		display_order INT UNSIGNED NOT NULL,
		PRIMARY KEY (option_id,group_id),
		KEY group_id_display_order (group_id,display_order)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_page'] = "
	CREATE TABLE xf_page (
		node_id INT UNSIGNED NOT NULL,
		publish_date INT UNSIGNED NOT NULL,
		modified_date INT UNSIGNED NOT NULL DEFAULT 0,
		view_count INT UNSIGNED NOT NULL DEFAULT 0,
		log_visits TINYINT UNSIGNED NOT NULL DEFAULT 0,
		list_siblings TINYINT UNSIGNED NOT NULL DEFAULT 0,
		list_children TINYINT UNSIGNED NOT NULL DEFAULT 0,
		callback_class VARCHAR(75) NOT NULL DEFAULT '',
		callback_method VARCHAR(75) NOT NULL DEFAULT '',
		PRIMARY KEY (node_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_permission'] = "
	CREATE TABLE xf_permission (
		permission_id VARCHAR(25) NOT NULL,
		permission_group_id VARCHAR(25) NOT NULL,
		permission_type ENUM('flag','integer') NOT NULL,
		interface_group_id VARCHAR(50) NOT NULL,
		depend_permission_id VARCHAR(25) NOT NULL,
		display_order INT UNSIGNED NOT NULL,
		default_value ENUM('allow','deny','unset') NOT NULL,
		default_value_int INT(11) NOT NULL,
		addon_id VARCHAR(25) NOT NULL DEFAULT '',
		PRIMARY KEY (permission_id, permission_group_id),
		KEY display_order (display_order)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_permission_cache_content'] = "
	CREATE TABLE xf_permission_cache_content (
		permission_combination_id INT UNSIGNED NOT NULL,
		content_type VARCHAR(25) NOT NULL,
		content_id INT UNSIGNED NOT NULL,
		cache_value MEDIUMBLOB NOT NULL,
		PRIMARY KEY (permission_combination_id, content_type, content_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_permission_cache_content_type'] = "
	CREATE TABLE xf_permission_cache_content_type (
		permission_combination_id INT UNSIGNED NOT NULL,
		content_type VARCHAR(25) NOT NULL,
		cache_value MEDIUMBLOB NOT NULL,
		PRIMARY KEY (permission_combination_id, content_type)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_permission_cache_global_group'] = "
	CREATE TABLE xf_permission_cache_global_group (
		permission_combination_id INT UNSIGNED NOT NULL,
		permission_group_id VARCHAR(25) NOT NULL,
		cache_value MEDIUMBLOB NOT NULL,
		PRIMARY KEY (permission_combination_id, permission_group_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_permission_combination'] = "
	CREATE TABLE xf_permission_combination (
		permission_combination_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		user_id INT UNSIGNED NOT NULL,
		user_group_list MEDIUMBLOB NOT NULL,
		cache_value MEDIUMBLOB NOT NULL,
		PRIMARY KEY (permission_combination_id),
		KEY user_id (user_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_permission_combination_user_group'] = "
	CREATE TABLE xf_permission_combination_user_group (
		user_group_id INT UNSIGNED NOT NULL,
		permission_combination_id INT UNSIGNED NOT NULL,
		PRIMARY KEY (user_group_id, permission_combination_id),
		KEY permission_combination_id (permission_combination_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_permission_entry'] = "
	CREATE TABLE xf_permission_entry (
		permission_entry_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		user_group_id INT UNSIGNED NOT NULL,
		user_id INT UNSIGNED NOT NULL,
		permission_group_id VARCHAR(25) NOT NULL,
		permission_id VARCHAR(25) NOT NULL,
		permission_value ENUM('unset','allow','deny','use_int') NOT NULL,
		permission_value_int INT NOT NULL,
		PRIMARY KEY (permission_entry_id),
		UNIQUE KEY unique_permission (user_group_id, user_id, permission_group_id, permission_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_permission_entry_content'] = "
	CREATE TABLE xf_permission_entry_content (
		permission_entry_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		content_type VARCHAR(25) NOT NULL,
		content_id INT UNSIGNED NOT NULL,
		user_group_id INT UNSIGNED NOT NULL,
		user_id INT UNSIGNED NOT NULL,
		permission_group_id VARCHAR(25) NOT NULL,
		permission_id VARCHAR(25) NOT NULL,
		permission_value ENUM('unset','reset','content_allow','deny','use_int') NOT NULL,
		permission_value_int INT NOT NULL,
		PRIMARY KEY (permission_entry_id),
		UNIQUE KEY user_group_id_unique (user_group_id, user_id, content_type, content_id, permission_group_id, permission_id),
		KEY content_type_content_id (content_type, content_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_permission_group'] = "
	CREATE TABLE xf_permission_group (
		permission_group_id VARCHAR(25) NOT NULL,
		addon_id VARCHAR(25) NOT NULL DEFAULT '',
		PRIMARY KEY (permission_group_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_permission_interface_group'] = "
	CREATE TABLE xf_permission_interface_group (
		interface_group_id VARCHAR(50) NOT NULL,
		display_order INT UNSIGNED NOT NULL,
		addon_id VARCHAR(25) NOT NULL DEFAULT '',
		PRIMARY KEY (interface_group_id),
		KEY display_order (display_order)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_phrase'] = "
	CREATE TABLE xf_phrase (
		phrase_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
		language_id INT UNSIGNED NOT NULL,
		title VARCHAR(75) NOT NULL,
		phrase_text MEDIUMTEXT NOT NULL,
		global_cache TINYINT UNSIGNED NOT NULL DEFAULT '0',
		addon_id VARCHAR(25) NOT NULL DEFAULT '',
		version_id INT UNSIGNED NOT NULL DEFAULT 0,
		version_string VARCHAR(30) NOT NULL DEFAULT '',
		UNIQUE KEY title (title, language_id),
		KEY language_id_global_cache (language_id, global_cache)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_phrase_compiled'] = "
	CREATE TABLE xf_phrase_compiled (
		language_id INT UNSIGNED NOT NULL,
		title VARCHAR(75) NOT NULL,
		phrase_text MEDIUMTEXT NOT NULL,
		PRIMARY KEY (language_id, title)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_phrase_map'] = "
	CREATE TABLE xf_phrase_map (
		phrase_map_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
		language_id INT UNSIGNED NOT NULL,
		title VARCHAR(75) NOT NULL,
		phrase_id INT UNSIGNED NOT NULL,
		UNIQUE KEY language_id_title (language_id, title),
		KEY phrase_id (phrase_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_poll'] = "
	CREATE TABLE xf_poll (
		poll_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		content_type VARCHAR(25) NOT NULL,
		content_id INT UNSIGNED NOT NULL,
		question VARCHAR(100) NOT NULL,
		responses MEDIUMBLOB NOT NULL,
		voter_count INT UNSIGNED NOT NULL DEFAULT 0,
		public_votes TINYINT UNSIGNED NOT NULL DEFAULT 0,
		multiple TINYINT UNSIGNED NOT NULL DEFAULT 0,
		close_date INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (poll_id),
		UNIQUE KEY content_type_content_id (content_type, content_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_poll_response'] = "
	CREATE TABLE xf_poll_response (
		poll_response_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		poll_id INT UNSIGNED NOT NULL,
		response VARCHAR(100) NOT NULL,
		response_vote_count INT UNSIGNED NOT NULL DEFAULT 0,
		voters MEDIUMBLOB NOT NULL,
		PRIMARY KEY (poll_response_id),
		KEY poll_id_response_id (poll_id, poll_response_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_poll_vote'] = "
	CREATE TABLE xf_poll_vote (
		user_id INT UNSIGNED NOT NULL,
		poll_response_id INT UNSIGNED NOT NULL,
		poll_id INT UNSIGNED NOT NULL,
		vote_date INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (poll_response_id, user_id),
		KEY poll_id_user_id (poll_id, user_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_post'] = "
	CREATE TABLE xf_post (
		post_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
		thread_id INT UNSIGNED NOT NULL,
		user_id INT UNSIGNED NOT NULL,
		username VARCHAR(50) NOT NULL,
		post_date INT UNSIGNED NOT NULL,
		message MEDIUMTEXT NOT NULL,
		ip_id INT UNSIGNED NOT NULL DEFAULT 0,
		message_state ENUM('visible', 'moderated', 'deleted') NOT NULL DEFAULT 'visible',
		attach_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
		position INT UNSIGNED NOT NULL,
		likes INT UNSIGNED NOT NULL DEFAULT 0,
		like_users BLOB NOT NULL,
		KEY thread_id_post_date (thread_id, post_date),
		KEY thread_id_position (thread_id, position)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_profile_post'] = "
	CREATE TABLE xf_profile_post (
		profile_post_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
		profile_user_id INT UNSIGNED NOT NULL,
		user_id INT UNSIGNED NOT NULL,
		username VARCHAR(50) NOT NULL,
		post_date INT UNSIGNED NOT NULL,
		message MEDIUMTEXT NOT NULL,
		ip_id INT UNSIGNED  NOT NULL DEFAULT 0,
		message_state ENUM('visible', 'moderated', 'deleted') NOT NULL DEFAULT 'visible',
		attach_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
		likes INT UNSIGNED NOT NULL DEFAULT 0,
		like_users BLOB NOT NULL,
		comment_count INT UNSIGNED NOT NULL DEFAULT 0,
		first_comment_date INT UNSIGNED NOT NULL DEFAULT 0,
		last_comment_date INT UNSIGNED NOT NULL DEFAULT 0,
		latest_comment_ids VARBINARY(100) NOT NULL DEFAULT '',
		KEY profile_user_id_post_date (profile_user_id, post_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_profile_post_comment'] = "
	CREATE TABLE xf_profile_post_comment (
		profile_post_comment_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		profile_post_id INT UNSIGNED NOT NULL,
		user_id INT UNSIGNED NOT NULL,
		username VARCHAR(50) NOT NULL,
		comment_date INT UNSIGNED NOT NULL,
		message MEDIUMTEXT NOT NULL,
		PRIMARY KEY (profile_post_comment_id),
		KEY profile_post_id_comment_date (profile_post_id, comment_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_captcha_question'] = "
	CREATE TABLE xf_captcha_question (
		captcha_question_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		question VARCHAR(250) NOT NULL,
		answers BLOB NOT NULL COMMENT 'Serialized array of possible correct answers.',
		active TINYINT UNSIGNED NOT NULL DEFAULT 1,
		PRIMARY KEY (captcha_question_id),
		KEY active (active)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_captcha_log'] = "
	CREATE TABLE xf_captcha_log (
		hash CHAR(40) NOT NULL,
		captcha_type VARCHAR(250) NOT NULL,
		captcha_data VARCHAR(250) NOT NULL,
		captcha_date INT UNSIGNED NOT NULL,
		PRIMARY KEY (hash),
		KEY captcha_date (captcha_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_report'] = "
	CREATE TABLE xf_report (
		report_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		content_type VARCHAR(25) NOT NULL,
		content_id INT UNSIGNED NOT NULL,
		content_user_id INT UNSIGNED NOT NULL,
		content_info MEDIUMBLOB NOT NULL,
		first_report_date INT UNSIGNED NOT NULL,
		report_state ENUM('open', 'assigned', 'resolved', 'rejected') NOT NULL,
		assigned_user_id INT UNSIGNED NOT NULL,
		comment_count INT UNSIGNED NOT NULL DEFAULT 0,
		last_modified_date INT UNSIGNED NOT NULL,
		last_modified_user_id INT UNSIGNED NOT NULL DEFAULT 0,
		last_modified_username VARCHAR(50) NOT NULL DEFAULT '',
		PRIMARY KEY (report_id),
		UNIQUE KEY content_type_content_id (content_type, content_id),
		KEY report_state (report_state),
		KEY assigned_user_id_state (assigned_user_id, report_state),
		KEY last_modified_date (last_modified_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_report_comment'] = "
	CREATE TABLE xf_report_comment (
		report_comment_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		report_id INT UNSIGNED NOT NULL,
		comment_date INT UNSIGNED  NOT NULL,
		user_id INT UNSIGNED NOT NULL,
		username VARCHAR(50) NOT NULL,
		message MEDIUMTEXT NOT NULL,
		state_change ENUM('', 'open', 'assigned', 'resolved', 'rejected') NOT NULL DEFAULT '',
		PRIMARY KEY (report_comment_id),
		KEY report_id_date (report_id, comment_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_route_prefix'] = "
	CREATE TABLE xf_route_prefix (
		route_type ENUM('public', 'admin') NOT NULL,
		original_prefix VARCHAR(25) NOT NULL,
		route_class VARCHAR(75) NOT NULL,
		build_link ENUM('all', 'data_only', 'none') NOT NULL DEFAULT 'none',
		addon_id VARCHAR(25) NOT NULL DEFAULT '',
		PRIMARY KEY (route_type, original_prefix)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";


$tables['xf_search'] = "
	CREATE TABLE xf_search (
		search_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		search_results MEDIUMBLOB NOT NULL,
		result_count SMALLINT UNSIGNED NOT NULL,
		search_type VARCHAR(25) NOT NULL,
		search_query VARCHAR(200) NOT NULL,
		search_constraints MEDIUMBLOB NOT NULL,
		search_order VARCHAR(50) NOT NULL,
		search_grouping TINYINT NOT NULL DEFAULT 0,
		warnings MEDIUMBLOB NOT NULL,
		user_id INT UNSIGNED NOT NULL,
		search_date INT UNSIGNED NOT NULL,
		query_hash varchar(32) NOT NULL DEFAULT '',
		PRIMARY KEY (search_id),
		KEY search_date (search_date),
		KEY query_hash (query_hash)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_search_index'] = "
	CREATE TABLE xf_search_index (
		content_type VARCHAR(25) NOT NULL,
		content_id INT UNSIGNED NOT NULL,
		title VARCHAR(250) NOT NULL DEFAULT '',
		message MEDIUMTEXT NOT NULL,
		metadata MEDIUMTEXT NOT NULL,
		user_id INT UNSIGNED NOT NULL DEFAULT 0,
		item_date INT UNSIGNED NOT NULL,
		discussion_id INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (content_type, content_id),
		FULLTEXT KEY title_message_metadata (title, message, metadata),
		FULLTEXT KEY title_metadata (title, metadata),
		KEY user_id_item_date (user_id, item_date)
	) ENGINE = MYISAM CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_session'] = "
	CREATE TABLE xf_session (
		session_id VARBINARY(32) NOT NULL,
		session_data MEDIUMBLOB NOT NULL,
		expiry_date INT UNSIGNED NOT NULL,
		PRIMARY KEY (session_id),
		KEY expiry_date (expiry_date)
	) ENGINE = MyISAM CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_session_activity'] = "
	CREATE TABLE xf_session_activity (
		user_id INT UNSIGNED NOT NULL,
		unique_key INT UNSIGNED NOT NULL,
		ip INT UNSIGNED NOT NULL DEFAULT 0,
		controller_name VARBINARY(50) NOT NULL,
		controller_action VARBINARY(50) NOT NULL,
		view_state ENUM('valid','error') NOT NULL,
		params VARBINARY(100) NOT NULL,
		view_date INT UNSIGNED NOT NULL,
		PRIMARY KEY (user_id, unique_key),
		KEY view_date (view_date)
	) ENGINE = MEMORY CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_session_admin'] = "
	CREATE TABLE xf_session_admin (
		session_id VARBINARY(32) NOT NULL,
		session_data MEDIUMBLOB NOT NULL,
		expiry_date INT UNSIGNED NOT NULL,
		PRIMARY KEY (session_id),
		KEY expiry_date (expiry_date)
	) ENGINE = MyISAM CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_smilie'] = "
	CREATE TABLE xf_smilie (
		smilie_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		title VARCHAR(50) NOT NULL,
		smilie_text TEXT NOT NULL,
		image_url VARCHAR(200) NOT NULL,
		PRIMARY KEY (smilie_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_spam_cleaner_log'] = "
	CREATE TABLE xf_spam_cleaner_log (
		spam_cleaner_log_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		user_id INT UNSIGNED NOT NULL DEFAULT 0,
		username VARCHAR(50) NOT NULL DEFAULT '',
		applying_user_id INT UNSIGNED NOT NULL DEFAULT 0,
		applying_username VARCHAR(50) NOT NULL DEFAULT '',
		application_date INT UNSIGNED NOT NULL DEFAULT 0,
		data mediumblob NOT NULL COMMENT 'Serialized array containing log data for undo purposes',
		restored_date INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (spam_cleaner_log_id),
		KEY application_date (application_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_style'] = "
	CREATE TABLE xf_style (
		style_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		parent_id INT UNSIGNED NOT NULL,
		parent_list VARBINARY(100) NOT NULL COMMENT 'IDs of ancestor styles in order, eg: this,parent,grandparent,root',
		title VARCHAR(50) NOT NULL,
		description VARCHAR(100) NOT NULL DEFAULT '',
		properties MEDIUMBLOB NOT NULL COMMENT 'Serialized array of materialized style properties for this style',
		last_modified_date INT UNSIGNED NOT NULL DEFAULT 0,
		user_selectable TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Unselectable styles are unselectable by non-admin visitors',
		PRIMARY KEY (style_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_style_property'] = "
	CREATE TABLE xf_style_property (
		property_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
		property_definition_id INT UNSIGNED NOT NULL,
		style_id INT NOT NULL,
		property_value MEDIUMBLOB NOT NULL,
		UNIQUE KEY definition_id_style_id (property_definition_id, style_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_style_property_definition'] = "
	CREATE TABLE xf_style_property_definition (
		property_definition_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
		definition_style_id INT NOT NULL,
		group_name VARCHAR(25),
		title VARCHAR(100) NOT NULL,
		description VARCHAR(255) NOT NULL DEFAULT '',
		property_name VARCHAR(100) NOT NULL,
		property_type ENUM('scalar','css') NOT NULL,
		css_components BLOB NOT NULL,
		scalar_type ENUM('','longstring','color','number','boolean','template') NOT NULL DEFAULT '',
		scalar_parameters VARCHAR(250) NOT NULL DEFAULT '' COMMENT 'Additional arguments for the given scalar type',
		addon_id VARCHAR(25) NOT NULL,
		display_order INT UNSIGNED NOT NULL DEFAULT 0,
		sub_group VARCHAR(25) NOT NULL DEFAULT '' COMMENT 'Allows loose grouping of scalars within a group',
		UNIQUE KEY definition_style_id_property_name (definition_style_id, property_name)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_style_property_group'] = "
	CREATE TABLE xf_style_property_group (
		property_group_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
		group_name VARCHAR(25) NOT NULL,
		group_style_id INT NOT NULL,
		title VARCHAR(100) NOT NULL,
		description VARCHAR(255) NOT NULL DEFAULT '',
		display_order INT UNSIGNED NOT NULL,
		addon_id VARCHAR(25) NOT NULL,
		UNIQUE KEY group_name_style_id (group_name, group_style_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_template'] = "
	CREATE TABLE xf_template (
		template_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
		title VARCHAR(50) NOT NULL,
		style_id INT UNSIGNED NOT NULL,
		template MEDIUMTEXT NOT NULL COMMENT 'User-editable HTML and template syntax',
		template_parsed MEDIUMBLOB NOT NULL,
		addon_id VARCHAR(25) NOT NULL DEFAULT '',
		version_id INT UNSIGNED NOT NULL DEFAULT 0,
		version_string VARCHAR(30) NOT NULL DEFAULT '',
		UNIQUE KEY title_style_id (title, style_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_template_compiled'] = "
	CREATE TABLE xf_template_compiled (
		style_id INT UNSIGNED NOT NULL,
		language_id INT UNSIGNED NOT NULL,
		title VARCHAR(50) NOT NULL,
		template_compiled MEDIUMBLOB NOT NULL COMMENT 'Executable PHP code built by template compiler',
		PRIMARY KEY (style_id, language_id, title)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_template_include'] = "
	CREATE TABLE xf_template_include (
		source_map_id INT UNSIGNED NOT NULL,
		target_map_id INT UNSIGNED NOT NULL,
		PRIMARY KEY (source_map_id, target_map_id),
		KEY target (target_map_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_template_map'] = "
	CREATE TABLE xf_template_map (
		template_map_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		style_id INT UNSIGNED NOT NULL,
		title VARCHAR(50) NOT NULL,
		template_id INT UNSIGNED NOT NULL,
		PRIMARY KEY (template_map_id),
		UNIQUE KEY style_id_title (style_id, title),
		KEY template_id (template_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_template_phrase'] = "
	CREATE TABLE xf_template_phrase (
		template_map_id INT UNSIGNED NOT NULL,
		phrase_title VARCHAR(75) NOT NULL,
		PRIMARY KEY (template_map_id, phrase_title),
		KEY phrase_title (phrase_title)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_thread'] = "
	CREATE TABLE xf_thread (
		thread_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
		node_id INT UNSIGNED NOT NULL,
		title VARCHAR(150) NOT NULL,
		reply_count INT UNSIGNED NOT NULL DEFAULT 0,
		view_count INT UNSIGNED NOT NULL DEFAULT 0,
		user_id INT UNSIGNED NOT NULL,
		username VARCHAR(50) NOT NULL,
		post_date INT UNSIGNED NOT NULL,
		sticky TINYINT UNSIGNED NOT NULL DEFAULT 0,
		discussion_state ENUM('visible', 'moderated', 'deleted') NOT NULL DEFAULT 'visible',
		discussion_open TINYINT UNSIGNED NOT NULL DEFAULT 1,
		discussion_type VARCHAR(25) NOT NULL DEFAULT '',
		first_post_id INT UNSIGNED NOT NULL,
		first_post_likes INT UNSIGNED NOT NULL DEFAULT 0,
		last_post_date INT UNSIGNED NOT NULL,
		last_post_id INT UNSIGNED NOT NULL,
		last_post_user_id INT UNSIGNED NOT NULL,
		last_post_username VARCHAR(50) NOT NULL,
		KEY node_id_last_post_date (node_id, last_post_date),
		KEY node_id_sticky_last_post_date (node_id, sticky, last_post_date),
		KEY last_post_date (last_post_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_thread_read'] = "
	CREATE TABLE xf_thread_read (
		thread_read_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
		user_id INT UNSIGNED NOT NULL,
		thread_id INT UNSIGNED NOT NULL,
		thread_read_date INT UNSIGNED NOT NULL,
		UNIQUE KEY user_id_thread_id (user_id, thread_id),
		KEY thread_id (thread_id),
		KEY thread_read_date (thread_read_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_thread_redirect'] = "
	CREATE TABLE xf_thread_redirect (
		thread_id INT UNSIGNED NOT NULL,
		target_url TEXT NOT NULL,
		redirect_key VARCHAR(50) NOT NULL DEFAULT '',
		expiry_date INT UNSIGNED NOT NULL DEFAULT '0',
		PRIMARY KEY (thread_id),
		KEY redirect_key_expiry_date (redirect_key, expiry_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_thread_user_post'] = "
	CREATE TABLE xf_thread_user_post (
		thread_id INT UNSIGNED NOT NULL,
		user_id INT UNSIGNED NOT NULL,
		post_count INT UNSIGNED NOT NULL,
		PRIMARY KEY (thread_id, user_id),
		KEY user_id (user_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_thread_view'] = "
	CREATE TABLE xf_thread_view (
		thread_id INT UNSIGNED NOT NULL,
		KEY thread_id (thread_id)
	) ENGINE=MEMORY CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_thread_watch'] = "
	CREATE TABLE xf_thread_watch (
		user_id INT UNSIGNED NOT NULL,
		thread_id INT UNSIGNED NOT NULL,
		email_subscribe TINYINT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (user_id, thread_id),
		KEY thread_id_email_subscribe (thread_id, email_subscribe)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_trophy'] = "
	CREATE TABLE xf_trophy (
		trophy_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		trophy_points INT UNSIGNED NOT NULL,
		criteria MEDIUMBLOB NOT NULL,
		PRIMARY KEY (trophy_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_trophy_user_title'] = "
	CREATE TABLE xf_trophy_user_title (
		minimum_points INT UNSIGNED NOT NULL,
		title VARCHAR(250) NOT NULL,
		PRIMARY KEY (minimum_points)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_upgrade_log'] = "
	CREATE TABLE xf_upgrade_log (
		version_id INT UNSIGNED NOT NULL,
		completion_date INT UNSIGNED NOT NULL DEFAULT 0,
		user_id INT UNSIGNED NOT NULL DEFAULT 0,
		log_type ENUM('install','upgrade') NOT NULL DEFAULT 'upgrade',
		PRIMARY KEY (version_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_user'] = "
	CREATE TABLE xf_user (
		user_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
		username VARCHAR(50) NOT NULL,
		email VARCHAR(120) NOT NULL,
 		gender ENUM('','male','female') NOT NULL DEFAULT '' COMMENT 'Leave empty for ''unspecified''',
 		custom_title VARCHAR(50) NOT NULL DEFAULT '',
		language_id INT UNSIGNED NOT NULL,
		style_id INT UNSIGNED NOT NULL COMMENT '0 = use system default',
		timezone VARCHAR(50) NOT NULL COMMENT 'Example: ''Europe/London''',
		visible TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Show browsing activity to others',
		user_group_id INT UNSIGNED NOT NULL,
		secondary_group_ids VARBINARY(255) NOT NULL,
		display_style_group_id INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'User group ID that provides user styling',
		permission_combination_id INT UNSIGNED NOT NULL,
		message_count INT UNSIGNED NOT NULL DEFAULT 0,
		conversations_unread SMALLINT UNSIGNED NOT NULL DEFAULT 0,
		register_date INT UNSIGNED NOT NULL DEFAULT 0,
		last_activity INT UNSIGNED NOT NULL DEFAULT 0,
		trophy_points INT UNSIGNED NOT NULL DEFAULT 0,
		alerts_unread SMALLINT UNSIGNED NOT NULL DEFAULT 0,
		avatar_date INT UNSIGNED NOT NULL DEFAULT 0,
		avatar_width SMALLINT UNSIGNED NOT NULL DEFAULT 0,
		avatar_height SMALLINT UNSIGNED NOT NULL DEFAULT 0,
		gravatar VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'If specified, this is an email address corresponding to the user''s ''Gravatar''',
		user_state ENUM('valid', 'email_confirm', 'email_confirm_edit', 'moderated') NOT NULL DEFAULT 'valid',
		is_moderator TINYINT UNSIGNED NOT NULL DEFAULT 0,
		is_admin TINYINT UNSIGNED NOT NULL DEFAULT 0,
		is_banned TINYINT UNSIGNED NOT NULL DEFAULT 0,
		like_count INT UNSIGNED NOT NULL DEFAULT 0,
		UNIQUE KEY username (username),
		KEY email (email),
		KEY user_state (user_state),
		KEY last_activity (last_activity)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_user_alert_optout'] = "
	CREATE TABLE IF NOT EXISTS xf_user_alert_optout (
		user_id INT UNSIGNED NOT NULL,
		alert VARCHAR(50) NOT NULL,
		PRIMARY KEY (user_id,alert)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_user_authenticate'] = "
	CREATE TABLE xf_user_authenticate (
		user_id INT UNSIGNED PRIMARY KEY,
		scheme_class VARCHAR(75) NOT NULL,
		data MEDIUMBLOB NOT NULL,
		remember_key VARBINARY(40) NOT NULL
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_user_ban'] = "
	CREATE TABLE xf_user_ban (
		user_id INT UNSIGNED NOT NULL,
		ban_user_id INT UNSIGNED NOT NULL,
		ban_date INT UNSIGNED NOT NULL DEFAULT '0',
		end_date INT UNSIGNED NOT NULL DEFAULT '0',
		user_reason VARCHAR(255) NOT NULL,
		PRIMARY KEY (user_id),
		KEY ban_date (ban_date),
		KEY end_date (end_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_user_external_auth'] = "
	CREATE TABLE xf_user_external_auth (
		provider VARBINARY(25) NOT NULL,
		provider_key VARBINARY(150) NOT NULL,
		user_id INT UNSIGNED NOT NULL,
		extra_data MEDIUMBLOB NOT NULL,
		PRIMARY KEY (user_id),
		UNIQUE KEY provider (provider, provider_key)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_user_confirmation'] = "
	CREATE TABLE xf_user_confirmation (
		user_id INT UNSIGNED NOT NULL,
		confirmation_type VARCHAR(25) NOT NULL,
		confirmation_key VARCHAR(16) NOT NULL,
		confirmation_date INT UNSIGNED NOT NULL,
		PRIMARY KEY (user_id, confirmation_type)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_user_follow'] = "
	CREATE TABLE xf_user_follow (
		user_id INT UNSIGNED NOT NULL,
		follow_user_id INT UNSIGNED NOT NULL COMMENT 'User being followed',
		follow_date INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (user_id,follow_user_id),
		KEY follow_user_id (follow_user_id)
	) ENGINE = InnoDB DEFAULT CHARSET=utf8 COLLATE utf8_general_ci
";

$tables['xf_user_group'] = "
	CREATE TABLE xf_user_group (
		user_group_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
		title VARCHAR(50) NOT NULL,
		display_style_priority INT UNSIGNED NOT NULL DEFAULT 0,
		username_css TEXT NOT NULL,
		user_title VARCHAR(100) NOT NULL DEFAULT '',
		KEY title (title)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_user_group_change'] = "
	CREATE TABLE xf_user_group_change (
		user_id INT UNSIGNED NOT NULL,
		change_key VARCHAR(50) NOT NULL,
		group_ids VARBINARY(255) NOT NULL,
		PRIMARY KEY (user_id, change_key),
		KEY change_key (change_key)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_user_group_relation'] = "
	CREATE TABLE xf_user_group_relation (
		user_id INT UNSIGNED NOT NULL,
		user_group_id INT UNSIGNED NOT NULL,
		is_primary TINYINT UNSIGNED NOT NULL,
		PRIMARY KEY (user_id,user_group_id),
		KEY user_group_id_is_primary (user_group_id, is_primary)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_user_identity'] = "
	CREATE TABLE xf_user_identity (
		user_id INT UNSIGNED NOT NULL,
		identity_service_id VARCHAR(25) NOT NULL,
		account_name VARCHAR(100) NOT NULL,
		PRIMARY KEY (user_id, identity_service_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_user_news_feed_cache'] = "
	CREATE TABLE xf_user_news_feed_cache (
		user_id INT UNSIGNED NOT NULL,
		news_feed_cache MEDIUMBLOB NOT NULL COMMENT 'Serialized. Contains fetched, parsed news_feed items for user_id',
		news_feed_cache_date INT UNSIGNED NOT NULL COMMENT 'Date at which the cache was last refreshed',
		PRIMARY KEY (user_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_user_alert'] = "
	CREATE TABLE xf_user_alert (
		alert_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		alerted_user_id INT UNSIGNED NOT NULL COMMENT 'User being alerted',
		user_id INT UNSIGNED NOT NULL DEFAULT '0' COMMENT 'User who did the action that caused the alert',
		username VARCHAR(50) NOT NULL DEFAULT '' COMMENT 'Corresponds to user_id',
		content_type VARCHAR(25) NOT NULL COMMENT 'eg: trophy',
		content_id INT UNSIGNED NOT NULL DEFAULT '0',
  		action VARCHAR(25) NOT NULL COMMENT 'eg: edit',
		event_date INT UNSIGNED NOT NULL,
		view_date INT UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Time when this was viewed by the alerted user',
		extra_data MEDIUMBLOB NOT NULL COMMENT 'Serialized. Stores any extra data relevant to the alert',
		PRIMARY KEY (alert_id),
		KEY alertedUserId_eventDate (alerted_user_id, event_date),
		KEY contentType_contentId (content_type, content_id),
		KEY viewDate_eventDate (view_date, event_date)
	) ENGINE = InnoDB DEFAULT CHARSET=utf8 COLLATE utf8_general_ci
";

$tables['xf_user_option'] = "
	CREATE TABLE xf_user_option (
		user_id INT UNSIGNED NOT NULL,
		show_dob_year TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Show date of month year (thus: age)',
		show_dob_date TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Show date of birth day and month',
		content_show_signature TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Show user''s signatures with content',
		receive_admin_email TINYINT UNSIGNED NOT NULL DEFAULT 1,
		email_on_conversation TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Receive an email upon receiving a conversation message',
		is_discouraged TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT  'If non-zero, this user will be subjected to annoying random system failures.',
		default_watch_state ENUM('', 'watch_no_email', 'watch_email') NOT NULL DEFAULT '',
		alert_optout TEXT NOT NULL COMMENT 'Comma-separated list of alerts from which the user has opted out. Example: ''post_like,user_trophy''',
		enable_rte TINYINT UNSIGNED NOT NULL DEFAULT 1,
		PRIMARY KEY (user_id)
	) ENGINE = InnoDB DEFAULT CHARSET=utf8 COLLATE utf8_general_ci
";

$tables['xf_user_privacy'] = "
	CREATE TABLE xf_user_privacy (
		user_id INT UNSIGNED NOT NULL,
		allow_view_profile ENUM('everyone','members','followed','none') NOT NULL DEFAULT 'everyone',
		allow_post_profile ENUM('everyone','members','followed','none') NOT NULL DEFAULT 'everyone',
		allow_send_personal_conversation ENUM('everyone','members','followed','none') NOT NULL DEFAULT 'everyone',
		allow_view_identities ENUM('everyone','members','followed','none') NOT NULL DEFAULT 'everyone',
		allow_receive_news_feed ENUM('everyone','members','followed','none') NOT NULL DEFAULT 'everyone',
		PRIMARY KEY (user_id)
	) ENGINE = InnoDB DEFAULT CHARSET=utf8 COLLATE utf8_general_ci
";

$tables['xf_user_profile'] = "
	CREATE TABLE xf_user_profile (
		user_id INT UNSIGNED NOT NULL,
 		dob_day TINYINT UNSIGNED NOT NULL DEFAULT '0',
		dob_month TINYINT UNSIGNED NOT NULL DEFAULT '0',
		dob_year SMALLINT UNSIGNED NOT NULL DEFAULT '0',
 		status TEXT NOT NULL,
 		status_date INT UNSIGNED NOT NULL DEFAULT 0,
 		status_profile_post_id INT UNSIGNED NOT NULL DEFAULT 0,
 		signature TEXT NOT NULL,
 		homepage TEXT NOT NULL,
 		location VARCHAR(50) NOT NULL DEFAULT '',
 		occupation VARCHAR(50) NOT NULL DEFAULT '',
 		following TEXT NOT NULL COMMENT 'Comma-separated Integers from xf_user_follow',
 		identities BLOB NOT NULL COMMENT 'Serialized array from xf_user_identity',
 		csrf_token VARCHAR(40) NOT NULL COMMENT 'Anti CSRF data key',
 		avatar_crop_x INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'X-Position from which to start the square crop on the m avatar',
		avatar_crop_y INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Y-Position from which to start the square crop on the m avatar',
		about TEXT NOT NULL,
		facebook_auth_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
 		PRIMARY KEY (user_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_user_status'] = "
	CREATE TABLE xf_user_status (
		profile_post_id INT UNSIGNED NOT NULL,
		user_id INT UNSIGNED NOT NULL,
		post_date INT UNSIGNED NOT NULL,
		PRIMARY KEY (profile_post_id),
		KEY post_date (post_date)
	) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_user_trophy'] = "
	CREATE TABLE xf_user_trophy (
		user_id INT UNSIGNED NOT NULL,
		trophy_id INT UNSIGNED NOT NULL,
		award_date INT UNSIGNED NOT NULL,
		PRIMARY KEY (trophy_id, user_id),
		KEY user_id_award_date (user_id, award_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_user_upgrade'] = "
	CREATE TABLE xf_user_upgrade (
		user_upgrade_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		title VARCHAR(50) NOT NULL,
		description TEXT NOT NULL,
		display_order INT UNSIGNED NOT NULL DEFAULT 0,
		extra_group_ids VARBINARY(255) NOT NULL DEFAULT '',
		recurring TINYINT UNSIGNED NOT NULL DEFAULT 0,
		cost_amount DECIMAL(10, 2) UNSIGNED NOT NULL,
		cost_currency VARCHAR(3) NOT NULL,
		length_amount TINYINT UNSIGNED NOT NULL,
		length_unit ENUM('day', 'month', 'year', '') NOT NULL DEFAULT '',
		disabled_upgrade_ids VARBINARY(255) NOT NULL DEFAULT '',
		can_purchase TINYINT UNSIGNED NOT NULL DEFAULT 1,
		PRIMARY KEY (user_upgrade_id),
		KEY display_order (display_order)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_user_upgrade_active'] = "
	CREATE TABLE xf_user_upgrade_active (
		user_upgrade_record_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		user_id INT UNSIGNED NOT NULL,
		user_upgrade_id INT UNSIGNED NOT NULL,
		extra MEDIUMBLOB NOT NULL,
		start_date INT UNSIGNED NOT NULL,
		end_date INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (user_upgrade_record_id),
		UNIQUE KEY user_id_upgrade_id (user_id, user_upgrade_id),
		KEY end_date (end_date),
		KEY start_date (start_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_user_upgrade_expired'] = "
	CREATE TABLE xf_user_upgrade_expired (
		user_upgrade_record_id INT UNSIGNED NOT NULL,
		user_id INT UNSIGNED NOT NULL,
		user_upgrade_id INT UNSIGNED NOT NULL,
		start_date INT UNSIGNED NOT NULL,
		end_date INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (user_upgrade_record_id),
		KEY end_date (end_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_user_upgrade_log'] = "
	CREATE TABLE xf_user_upgrade_log (
		user_upgrade_log_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		user_upgrade_record_id INT UNSIGNED NOT NULL,
		processor VARCHAR(25) NOT NULL,
		transaction_id VARCHAR(50) NOT NULL,
		transaction_type ENUM('payment','cancel','info','error') NOT NULL,
		message VARCHAR(255) NOT NULL default '',
		transaction_details MEDIUMBLOB NOT NULL,
		log_date INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (user_upgrade_log_id),
		KEY transaction_id (transaction_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

		return $tables;
	}

	public static function getData()
	{
		// TODO: key $data with table name $data['style'] for example...

		$data = array();

$data[] = "
	INSERT INTO xf_style
		(style_id, parent_id, parent_list, title, properties)
	VALUES
		(1, 0, '1,0', 'Default Style', '')";

$data[] = "
	INSERT INTO xf_language
		(language_id, parent_id, parent_list, title, date_format, time_format, decimal_point, thousands_separator, phrase_cache, language_code)
	VALUES
		(1, 0, '1,0', 'English (US)', 'M j, Y', 'g:i A', '.', ',', '', 'en-US')
";

$data[] = "
	INSERT INTO xf_node_type
		(node_type_id, handler_class, controller_admin_class, datawriter_class, permission_group_id,
		moderator_interface_group_id, public_route_prefix)
	VALUES
		('Category', 'XenForo_NodeHandler_Category', 'XenForo_ControllerAdmin_Category', 'XenForo_DataWriter_Category', 'category', '', 'categories'),
		('Forum', 'XenForo_NodeHandler_Forum', 'XenForo_ControllerAdmin_Forum', 'XenForo_DataWriter_Forum', 'forum', 'forumModeratorPermissions', 'forums'),
		('LinkForum', 'XenForo_NodeHandler_LinkForum', 'XenForo_ControllerAdmin_LinkForum', 'XenForo_DataWriter_LinkForum', 'linkForum', '', 'link-forums'),
		('Page', 'XenForo_NodeHandler_Page', 'XenForo_ControllerAdmin_Page', 'XenForo_DataWriter_Page', 'page', '', 'pages')
";

$data[] = "
	INSERT INTO xf_content_type
		(content_type, addon_id, fields)
	VALUES
		('conversation', 'XenForo', ''),
		('node', 'XenForo', ''),
		('post', 'XenForo', ''),
		('thread', 'XenForo', ''),
		('user', 'XenForo', ''),
		('profile_post', 'XenForo', '')
";

$data[] = "
	INSERT INTO xf_content_type_field
		(content_type, field_name, field_value)
	VALUES
		('conversation', 'alert_handler_class', 'XenForo_AlertHandler_Conversation'),

		('node', 'permission_handler_class', 'XenForo_ContentPermission_Node'),
		('node', 'moderator_handler_class', 'XenForo_ModeratorHandler_Node'),

		('post', 'news_feed_handler_class', 'XenForo_NewsFeedHandler_DiscussionMessage_Post'),
		('post', 'alert_handler_class', 'XenForo_AlertHandler_DiscussionMessage_Post'),
		('post', 'search_handler_class', 'XenForo_Search_DataHandler_Post'),
		('post', 'attachment_handler_class', 'XenForo_AttachmentHandler_Post'),
		('post', 'like_handler_class', 'XenForo_LikeHandler_Post'),
		('post', 'report_handler_class', 'XenForo_ReportHandler_Post'),
		('post', 'moderation_queue_handler_class', 'XenForo_ModerationQueueHandler_Post'),
		('post', 'spam_handler_class', 'XenForo_SpamHandler_Post'),

		('thread', 'news_feed_handler_class', 'XenForo_NewsFeedHandler_Discussion_Thread'),
		('thread', 'search_handler_class', 'XenForo_Search_DataHandler_Thread'),
		('thread', 'moderation_queue_handler_class', 'XenForo_ModerationQueueHandler_Thread'),
		('thread', 'spam_handler_class', 'XenForo_SpamHandler_Thread'),

		('user', 'news_feed_handler_class', 'XenForo_NewsFeedHandler_User'),
		('user', 'alert_handler_class', 'XenForo_AlertHandler_User'),

		('profile_post', 'news_feed_handler_class', 'XenForo_NewsFeedHandler_DiscussionMessage_ProfilePost'),
		('profile_post', 'alert_handler_class', 'XenForo_AlertHandler_DiscussionMessage_ProfilePost'),
		('profile_post', 'report_handler_class', 'XenForo_ReportHandler_ProfilePost'),
		('profile_post', 'moderation_queue_handler_class', 'XenForo_ModerationQueueHandler_ProfilePost'),
		('profile_post', 'like_handler_class', 'XenForo_LikeHandler_ProfilePost'),
		('profile_post', 'spam_handler_class', 'XenForo_SpamHandler_ProfilePost')
";

$data[] = "
	INSERT INTO xf_identity_service
		(identity_service_id, model_class)
	VALUES
		('aim', 'XenForo_Model_IdentityService_Aim'),
		('facebook', 'XenForo_Model_IdentityService_Facebook'),
		('gtalk', 'XenForo_Model_IdentityService_Gtalk'),
		('icq', 'XenForo_Model_IdentityService_Icq'),
		('msn', 'XenForo_Model_IdentityService_Msn'),
		('skype', 'XenForo_Model_IdentityService_Skype'),
		('twitter', 'XenForo_Model_IdentityService_Twitter'),
		('yahoo', 'XenForo_Model_IdentityService_Yahoo')
";

$data[] = "
	INSERT INTO xf_phrase
		(language_id, title, phrase_text, global_cache, addon_id)
	VALUES
		(0, 'identity_service_name_gtalk', 'Google Talk', 0, ''),
		(0, 'identity_service_hint_gtalk', 'Google Talk ID', 0, ''),
		(0, 'identity_service_hint_aim', 'AIM Screen Name', 0, ''),
		(0, 'identity_service_hint_icq', 'ICQ Number / UIN', 0, ''),
		(0, 'identity_service_hint_msn', 'MSN Messenger ID', 0, ''),
		(0, 'identity_service_hint_skype', 'Skype Name', 0, ''),
		(0, 'identity_service_hint_yahoo', 'Yahoo! ID', 0, ''),
		(0, 'identity_service_name_aim', 'AIM', 0, ''),
		(0, 'identity_service_name_icq', 'ICQ', 0, ''),
		(0, 'identity_service_name_msn', 'Windows Live', 0, ''),
		(0, 'identity_service_name_skype', 'Skype', 0, ''),
		(0, 'identity_service_name_yahoo', 'Yahoo! Messenger', 0, ''),
		(0, 'identity_service_name_facebook', 'Facebook', 0, ''),
		(0, 'identity_service_hint_facebook', 'User ID or vanity name', 0, ''),
		(0, 'identity_service_hint_twitter', '@username', 0, ''),
		(0, 'identity_service_name_twitter', 'Twitter', 0, ''),
		(0, 'trophy_1_description', 'Post a message somewhere on the site to receive this.', 0, ''),
		(0, 'trophy_1_title', 'First Message', 0, ''),
		(0, 'trophy_2_description', '30 messages posted. You must like it here!', 0, ''),
		(0, 'trophy_2_title', 'Keeps Coming Back', 0, ''),
		(0, 'trophy_3_description', 'You''ve posted 100 messages. I hope this took you more than a day!', 0, ''),
		(0, 'trophy_3_title', 'Can''t Stop!', 0, ''),
		(0, 'trophy_4_description', '1000 messages? Impressive!', 0, ''),
		(0, 'trophy_4_title', 'Addicted', 0, ''),
		(0, 'trophy_5_description', 'Somebody out there liked one of your messages. Keep posting like that for more!', 0, ''),
		(0, 'trophy_5_title', 'Somebody Likes You', 0, ''),
		(0, 'trophy_6_description', 'Your messages have been liked 25 times.', 0, ''),
		(0, 'trophy_6_title', 'I Like It a Lot', 0, ''),
		(0, 'trophy_7_description', 'Content you have posted has attracted 100 likes.', 0, ''),
		(0, 'trophy_7_title', 'Seriously Likeable!', 0, ''),
		(0, 'trophy_8_description', 'Your content has been liked 250 times.', 0, ''),
		(0, 'trophy_8_title', 'Can''t Get Enough of Your Stuff', 0, ''),
		(0, 'trophy_9_description', 'Content you have posted has attracted 500 likes.', 0, ''),
		(0, 'trophy_9_title', 'I LOVE IT!', 0, '')
";

$data[] = "
	INSERT INTO xf_user_group
		(user_group_id, title, display_style_priority, username_css, user_title)
	VALUES
		(1, 'Unregistered / Unconfirmed', 0, '', 'Guest'),
		(2, 'Registered', 0, '', ''),
		(3, 'Administrative', 1000, '', 'Administrator'),
		(4, 'Moderating', 900, '', 'Moderator')
";

$data[] = "
	INSERT INTO xf_permission_combination
		(permission_combination_id, user_id, user_group_list, cache_value)
	VALUES
		(1, 0, '1', ''),
		(2, 0, '2', ''),
		(3, 0, '3', ''),
		(4, 0, '4', '')
";

$data[] = "
	INSERT INTO xf_permission_combination_user_group
		(user_group_id, permission_combination_id)
	VALUES
		(1, 1),
		(2, 2),
		(3, 3),
		(4, 4)
";

$data[] = "
	INSERT INTO xf_permission_entry
		(user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
	VALUES
		(1, 0, 'general', 'followModerationRules', 'allow', 0),
		(1, 0, 'general', 'search', 'allow', 0),
		(1, 0, 'general', 'view', 'allow', 0),
		(1, 0, 'general', 'viewNode', 'allow', 0),
		(1, 0, 'general', 'viewProfile', 'allow', 0),
		(1, 0, 'profilePost', 'view', 'allow', 0),
		(2, 0, 'avatar', 'allowed', 'allow', 0),
		(2, 0, 'avatar', 'maxFileSize', 'use_int', 51200),
		(2, 0, 'conversation', 'start', 'allow', 0),
		(2, 0, 'conversation', 'maxRecipients', 'use_int', 5),
		(2, 0, 'conversation', 'editOwnPost', 'allow', 0),
		(2, 0, 'conversation', 'editOwnPostTimeLimit', 'use_int', 5),
		(2, 0, 'forum', 'deleteOwnPost', 'allow', 0),
		(2, 0, 'forum', 'editOwnPost', 'allow', 0),
		(2, 0, 'forum', 'editOwnPostTimeLimit', 'use_int', -1),
		(2, 0, 'forum', 'postReply', 'allow', 0),
		(2, 0, 'forum', 'postThread', 'allow', 0),
		(2, 0, 'forum', 'uploadAttachment', 'allow', 0),
		(2, 0, 'forum', 'viewAttachment', 'allow', 0),
		(2, 0, 'forum', 'votePoll', 'allow', 0),
		(2, 0, 'forum', 'like', 'allow', 0),
		(2, 0, 'general', 'editSignature', 'allow', 0),
		(2, 0, 'general', 'followModerationRules', 'allow', 0),
		(2, 0, 'general', 'search', 'allow', 0),
		(2, 0, 'general', 'view', 'allow', 0),
		(2, 0, 'general', 'viewNode', 'allow', 0),
		(2, 0, 'general', 'viewProfile', 'allow', 0),
		(2, 0, 'profilePost', 'deleteOwn', 'allow', 0),
		(2, 0, 'profilePost', 'editOwn', 'allow', 0),
		(2, 0, 'profilePost', 'manageOwn', 'allow', 0),
		(2, 0, 'profilePost', 'post', 'allow', 0),
		(2, 0, 'profilePost', 'view', 'allow', 0),
		(2, 0, 'profilePost', 'like', 'allow', 0),
		(3, 0, 'avatar', 'allowed', 'allow', 0),
		(3, 0, 'avatar', 'maxFileSize', 'use_int', -1),
		(3, 0, 'conversation', 'maxRecipients', 'use_int', -1),
		(3, 0, 'conversation', 'start', 'allow', 0),
		(3, 0, 'conversation', 'editOwnPost', 'allow', 0),
		(3, 0, 'conversation', 'editAnyPost', 'allow', 0),
		(3, 0, 'conversation', 'alwaysInvite', 'allow', 0),
		(3, 0, 'forum', 'deleteOwnPost', 'allow', 0),
		(3, 0, 'forum', 'deleteOwnThread', 'allow', 0),
		(3, 0, 'forum', 'editOwnPost', 'allow', 0),
		(3, 0, 'forum', 'editOwnPostTimeLimit', 'use_int', -1),
		(3, 0, 'forum', 'postReply', 'allow', 0),
		(3, 0, 'forum', 'postThread', 'allow', 0),
		(3, 0, 'forum', 'uploadAttachment', 'allow', 0),
		(3, 0, 'forum', 'viewAttachment', 'allow', 0),
		(3, 0, 'forum', 'votePoll', 'allow', 0),
		(3, 0, 'forum', 'like', 'allow', 0),
		(3, 0, 'general', 'bypassFloodCheck', 'allow', 0),
		(3, 0, 'general', 'editCustomTitle', 'allow', 0),
		(3, 0, 'general', 'editSignature', 'allow', 0),
		(3, 0, 'general', 'followModerationRules', 'allow', 0),
		(3, 0, 'general', 'search', 'allow', 0),
		(3, 0, 'general', 'view', 'allow', 0),
		(3, 0, 'general', 'viewNode', 'allow', 0),
		(3, 0, 'general', 'viewProfile', 'allow', 0),
		(3, 0, 'profilePost', 'deleteOwn', 'allow', 0),
		(3, 0, 'profilePost', 'editOwn', 'allow', 0),
		(3, 0, 'profilePost', 'manageOwn', 'allow', 0),
		(3, 0, 'profilePost', 'post', 'allow', 0),
		(3, 0, 'profilePost', 'view', 'allow', 0),
		(3, 0, 'profilePost', 'like', 'allow', 0),
		(4, 0, 'avatar', 'allowed', 'allow', 0),
		(4, 0, 'avatar', 'maxFileSize', 'use_int', -1),
		(4, 0, 'conversation', 'maxRecipients', 'use_int', -1),
		(4, 0, 'conversation', 'start', 'allow', 0),
		(4, 0, 'forum', 'deleteOwnPost', 'allow', 0),
		(4, 0, 'forum', 'deleteOwnThread', 'allow', 0),
		(4, 0, 'forum', 'editOwnPost', 'allow', 0),
		(4, 0, 'forum', 'editOwnPostTimeLimit', 'use_int', -1),
		(4, 0, 'forum', 'postReply', 'allow', 0),
		(4, 0, 'forum', 'postThread', 'allow', 0),
		(4, 0, 'forum', 'uploadAttachment', 'allow', 0),
		(4, 0, 'forum', 'viewAttachment', 'allow', 0),
		(4, 0, 'forum', 'votePoll', 'allow', 0),
		(4, 0, 'forum', 'like', 'allow', 0),
		(4, 0, 'general', 'bypassFloodCheck', 'allow', 0),
		(4, 0, 'general', 'editCustomTitle', 'allow', 0),
		(4, 0, 'general', 'editSignature', 'allow', 0),
		(4, 0, 'general', 'followModerationRules', 'allow', 0),
		(4, 0, 'general', 'search', 'allow', 0),
		(4, 0, 'general', 'view', 'allow', 0),
		(4, 0, 'general', 'viewNode', 'allow', 0),
		(4, 0, 'general', 'viewProfile', 'allow', 0),
		(4, 0, 'profilePost', 'deleteOwn', 'allow', 0),
		(4, 0, 'profilePost', 'editOwn', 'allow', 0),
		(4, 0, 'profilePost', 'manageOwn', 'allow', 0),
		(4, 0, 'profilePost', 'post', 'allow', 0),
		(4, 0, 'profilePost', 'view', 'allow', 0),
		(4, 0, 'profilePost', 'like', 'allow', 0)
";

$data[] = '
INSERT INTO xf_bb_code_media_site
		(media_site_id, site_title, site_url, match_urls, embed_html)
	VALUES
		(\'facebook\', \'Facebook\', \'http://www.facebook.com\', \'facebook.com/*video.php?v={$id:digits}\', \'<object width="500" height="280" data="http://www.facebook.com/v/{$id}" type="application/x-shockwave-flash">\n	<param name="movie" value="http://www.facebook.com/v/{$id}" />\n	<param name="allowfullscreen" value="true" />\n	<param name="wmode" value="opaque" />\n	<embed src="http://www.facebook.com/v/{$id}" type="application/x-shockwave-flash" allowfullscreen="true" wmode="opaque" width="500" height="280" />\n</object>\'),
		(\'vimeo\', \'Vimeo\', \'http://www.vimeo.com\', \'vimeo.com/{$id:digits}\nvimeo.com/groups/*/videos/{$id:digits}\', \'<iframe src="http://player.vimeo.com/video/{$id}" width="500" height="281" frameborder="0"></iframe>\'),
		(\'youtube\', \'YouTube\', \'http://www.youtube.com\', \'youtube.com/watch?v={$id}\nyoutube.com/v/{$id}\nyoutu.be/{$id}\', \'<object width="500" height="300" data="http://www.youtube.com/v/{$id}&amp;fs=1" type="application/x-shockwave-flash">\n	<param name="movie" value="http://www.youtube.com/v/{$id}&amp;fs=1" />\n	<param name="allowFullScreen" value="true" />\n	<param name="wmode" value="opaque" />\n	<embed src="http://www.youtube.com/v/{$id}&amp;fs=1" type="application/x-shockwave-flash" allowfullscreen="true" wmode="opaque" width="500" height="300" />\n</object>\')
';

$data[] = "
	INSERT INTO xf_node
		(node_id, title, description, node_type_id, parent_node_id, display_order, lft, rgt, depth)
	VALUES
		(1, 'Main Category', '', 'Category', 0, 1, 1, 4, 0),
		(2, 'Main Forum', '', 'Forum', 1, 1, 2, 3, 1)
";

$data[] = "
	INSERT INTO xf_forum
		(node_id, discussion_count, message_count, last_post_id, last_post_date, last_post_user_id, last_post_username)
	VALUES
		(2, 0, 0, 0, 0, 0, '')
";

$data[] = '
	REPLACE INTO xf_trophy
		(trophy_id, trophy_points, criteria)
	VALUES
		(1, 1, \'a:1:{i:0;a:2:{s:4:"rule";s:15:"messages_posted";s:4:"data";a:1:{s:8:"messages";s:1:"1";}}}\'),
		(2, 5, \'a:1:{i:0;a:2:{s:4:"rule";s:15:"messages_posted";s:4:"data";a:1:{s:8:"messages";s:2:"30";}}}\'),
		(3, 10, \'a:1:{i:0;a:2:{s:4:"rule";s:15:"messages_posted";s:4:"data";a:1:{s:8:"messages";s:3:"100";}}}\'),
		(4, 20, \'a:1:{i:0;a:2:{s:4:"rule";s:15:"messages_posted";s:4:"data";a:1:{s:8:"messages";s:4:"1000";}}}\'),
		(5, 2, \'a:1:{i:0;a:2:{s:4:"rule";s:10:"like_count";s:4:"data";a:1:{s:5:"likes";s:1:"1";}}}\'),
		(6, 10, \'a:1:{i:0;a:2:{s:4:"rule";s:10:"like_count";s:4:"data";a:1:{s:5:"likes";s:2:"25";}}}\'),
		(7, 15, \'a:1:{i:0;a:2:{s:4:"rule";s:10:"like_count";s:4:"data";a:1:{s:5:"likes";s:3:"100";}}}\'),
		(8, 20, \'a:1:{i:0;a:2:{s:4:"rule";s:10:"like_count";s:4:"data";a:1:{s:5:"likes";s:3:"250";}}}\'),
		(9, 30, \'a:1:{i:0;a:2:{s:4:"rule";s:10:"like_count";s:4:"data";a:1:{s:5:"likes";s:3:"500";}}}\')
';

$data[] = "
	INSERT INTO xf_trophy_user_title
		(minimum_points, title)
	VALUES
		(0, 'New Member'),
		(5, 'Member'),
		(25, 'Active Member'),
		(45, 'Well-Known Member')
";

$data[] = "
	INSERT INTO xf_smilie
		(title, smilie_text, image_url)
	VALUES
		('Smile', ':)\n:-)\n(:', 'styles/default/xenforo/smilies/smile.png'),
		('Wink', ';)', 'styles/default/xenforo/smilies/wink.png'),
		('Frown', ':(', 'styles/default/xenforo/smilies/frown.png'),
		('Mad', ':mad:\r\n>:(\r\n:@', 'styles/default/xenforo/smilies/mad.png'),
		('Confused', ':confused:\no_O\nO_o\no.O\nO.o', 'styles/default/xenforo/smilies/confused.png'),
		('Cool', ':cool:\n8-)', 'styles/default/xenforo/smilies/cool.png'),
		('Stick Out Tongue', ':p\n:P', 'styles/default/xenforo/smilies/tongue.png'),
		('Big Grin', ':D', 'styles/default/xenforo/smilies/biggrin.png'),
		('Eek!', ':eek:\r\n:o', 'styles/default/xenforo/smilies/eek.png'),
		('Oops!', ':oops:', 'styles/default/xenforo/smilies/redface.png'),
		('Roll Eyes', ':rolleyes:', 'styles/default/xenforo/smilies/rolleyes.png')
";

// TODO: additional media sites

		return $data;
	}
}