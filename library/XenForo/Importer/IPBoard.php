<?php


/*
 * New classes:
 *
 * XenForo_Authentication_IPBoard
 *
 */

class XenForo_Importer_IPBoard extends XenForo_Importer_Abstract
{
	/**
	 * Source database connection.
	 *
	 * @var Zend_Db_Adapter_Abstract
	 */
	protected $_sourceDb;

	protected $_prefix;

	protected $_charset = 'windows-1252';

	protected $_config;

	protected $_groupMap = null;

	protected $_adminPermissions = null;

	protected $_profileFieldMap = null;

	protected $_nodePermissionsGrouped = null;

	public static function getName()
	{
		return 'IP.Board 3.1.x (BETA: unsupported)';
	}

	public function configure(XenForo_ControllerAdmin_Abstract $controller, array &$config)
	{
		if ($config)
		{
			$errors = $this->validateConfiguration($config);
			if ($errors)
			{
				return $controller->responseError($errors);
			}

			$this->_bootstrap($config);

			return true;
		}
		else
		{
			$viewParams = array('input' => array
			(
				'sql_host' => 'localhost',
				'sql_user' => '',
				'sql_pass' => '',
				'sql_database' => '',
				'sql_tbl_prefix' => '',

				//'ipboard_path' => getcwd(),
				'ipboard_path' => $_SERVER['DOCUMENT_ROOT'],
			));

			$configPath = getcwd() . '/conf_global.php';
			if (file_exists($configPath))
			{
				include($configPath);

				$viewParams['input'] = array_merge($viewParams['input'], $INFO);
			}

			return $controller->responseView('XenForo_ViewAdmin_Import_IPBoard_Config', 'import_ipboard_config', $viewParams);
		}
	}

	public function validateConfiguration(array &$config)
	{
		$errors = array();

		$config['db']['prefix'] = preg_replace('/[^a-z0-9_]/i', '', $config['db']['prefix']);

		try
		{
			$db = Zend_Db::factory('mysqli',
				array(
					'host' => $config['db']['host'],
					'username' => $config['db']['username'],
					'password' => $config['db']['password'],
					'dbname' => $config['db']['dbname']
				)
			);
			$db->getConnection();
		}
		catch (Zend_Db_Exception $e)
		{
			$errors[] = new XenForo_Phrase('source_database_connection_details_not_correct_x', array('error' => $e->getMessage()));
		}

		if ($errors)
		{
			return $errors;
		}

		try
		{
			$db->query('
				SELECT member_id
				FROM ' . $config['db']['prefix'] . 'members
				LIMIT 1
			');
		}
		catch (Zend_Db_Exception $e)
		{
			if ($config['db']['dbname'] === '')
			{
				$errors[] = new XenForo_Phrase('please_enter_database_name');
			}
			else
			{
				$errors[] = new XenForo_Phrase('table_prefix_or_database_name_is_not_correct');
			}
		}

		if (!empty($config['ipboard_path']))
		{
			if (!file_exists($config['ipboard_path']) || !is_dir($config['ipboard_path'] . '/uploads'))
			{
				$errors[] = new XenForo_Phrase('error_could_not_find_uploads_directory_at_specified_path');
			}
		}

		if (!$errors)
		{
			$defaultCharset = $db->fetchOne("
				SELECT IF(conf_value = '' OR conf_value IS NULL, conf_default, conf_value)
				FROM {$config['db']['prefix']}core_sys_conf_settings
				WHERE conf_key = 'gb_char_set'
			");
			if (!$defaultCharset || str_replace('-', '', strtolower($defaultCharset)) == 'iso88591')
			{
				$config['charset'] = 'windows-1252';
			}
			else
			{
				$config['charset'] = strtolower($defaultCharset);
			}
		}

		return $errors;
	}

	public function getSteps()
	{
		return array(
			'userGroups' => array(
				'title' => new XenForo_Phrase('import_user_groups')
			),
			'users' => array(
				'title' => new XenForo_Phrase('import_users'),
				'depends' => array('userGroups')
			),
			'avatars' => array(
				'title' => new XenForo_Phrase('import_custom_avatars'),
				'depends' => array('users')
			),
			'privateMessages' => array(
				'title' => new XenForo_Phrase('import_private_messages'),
				'depends' => array('users')
			),
			'profileComments' => array(
				'title' => new XenForo_Phrase('import_profile_comments'),
				'depends' => array('users')
			),
			'statusUpdates' => array(
				'title' => new XenForo_Phrase('import_user_status_updates'),
				'depends' => array('users')
			),
			'forums' => array(
				'title' => new XenForo_Phrase('import_forums'),
				'depends' => array('userGroups')
			),
			'forumPermissions' => array(
				'title' => new XenForo_Phrase('import_forum_permissions'),
				'depends' => array('forums')
			),
			'moderators' => array(
				'title' => new XenForo_Phrase('import_moderators'),
				'depends' => array('forums', 'users')
			),
			'threads' => array(
				'title' => new XenForo_Phrase('import_threads_and_posts'),
				'depends' => array('forums', 'users')
			),
			'polls' => array(
				'title' => new XenForo_Phrase('import_polls'),
				'depends' => array('threads')
			),
			'attachments' => array(
				'title' => new XenForo_Phrase('import_attached_files'),
				'depends' => array('threads')
			),
			'reputation' => array(
				'title' => new XenForo_Phrase('import_positive_reputation'),
				'depends' => array('threads')
			),
		);

		// TODO: user upgrades?
		// deferred: albums/comments, announcements, custom bb code, calendars/events, social groups, infractions, tags
	}

	protected function _bootstrap(array $config)
	{
		if ($this->_sourceDb)
		{
			// already run
			return;
		}

		@set_time_limit(0);

		$this->_config = $config;

		$this->_sourceDb = Zend_Db::factory('mysqli',
			array(
				'host' => $config['db']['host'],
				'username' => $config['db']['username'],
				'password' => $config['db']['password'],
				'dbname' => $config['db']['dbname']
			)
		);

		$this->_prefix = preg_replace('/[^a-z0-9_]/i', '', $config['db']['prefix']);

		if (!empty($config['charset']))
		{
			$this->_charset = $config['charset'];
		}
	}

	public function configStepUserGroups(array $options)
	{
		if ($options)
		{
			return false;
		}

		$viewParams = array('input' => array
		(
			'auth_group' => 1,
			'guest_group' => 2,
			'member_group' => 3,
			'admin_group' => 4,
			'banned_group' => 5,
		));

		$config = $this->_session->getConfig();

		$configPath = $config['ipboard_path'] . '/conf_global.php';
		if (file_exists($configPath))
		{
			include($configPath);

			$viewParams['input'] = array_merge($viewParams['input'], $INFO);
		}

		return $this->_controller->responseView('XenForo_ViewAdmin_Import_IPBoard_ConfigUserGroups', 'import_ipboard_config_usergroups', $viewParams);
	}

	public function stepUserGroups($start, array $options)
	{
		$options = array_merge(array
		(
			'auth_group' => 1,
			'guest_group' => 2,
			'member_group' => 3,
			'admin_group' => 4,
			'banned_group' => 5,
		), $options);

		$session =  $this->_session->setExtraData('groups', $options);

		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		$userGroups = $sDb->fetchAll('
			SELECT *
			FROM ' . $prefix . 'groups
			ORDER BY g_id
		');

		$total = 0;

		XenForo_Db::beginTransaction();

		foreach ($userGroups AS $userGroup)
		{
			$titlePriority = 5;
			switch ($userGroup['g_id'])
			{
				case $options['guest_group']: // guests (default 2)
					$model->logImportData('userGroup', $userGroup['g_id'], XenForo_Model_User::$defaultGuestGroupId);
					break;

				case $options['auth_group']: // email confirm / validating (default 1)
				case $options['member_group']: // registered users (default 3)
					$model->logImportData('userGroup', $userGroup['g_id'], XenForo_Model_User::$defaultRegisteredGroupId);
					break;

				case $options['admin_group']: // admins (default 4)
					$model->logImportData('userGroup', $userGroup['g_id'], XenForo_Model_User::$defaultAdminGroupId);
					continue;

				// TODO: make banned users?
				#case 5: // banned
				#	$model->logImportData('userGroup', $userGroup['g_id'], XenForo_Model_User::)
				#	continue;

				case 6: // mods
					$model->logImportData('userGroup', $userGroup['g_id'], XenForo_Model_User::$defaultModeratorGroupId);
					continue;

				default:
					$import = array(
						'title' => $this->_convertToUtf8($userGroup['g_title']),
						'user_title' => $this->_convertToUtf8($userGroup['g_title']),
						'display_style_priority' => $titlePriority,
						'permissions' => $this->_calculateUserGroupPermissions($userGroup)
					);

					if ($model->importUserGroup($userGroup['g_id'], $import))
					{
						$total++;
					}
			}
		}

		XenForo_Model::create('XenForo_Model_UserGroup')->rebuildDisplayStyleCache();

		XenForo_Db::commit();

		$this->_session->incrementStepImportTotal($total);

		return true;
	}

	protected function _calculateUserGroupPermissions(array $userGroup)
	{
		$perms = array();

		if ($userGroup['g_view_board'])
		{
			$perms['general']['view'] = 'allow';
			$perms['general']['viewNode'] = 'allow';
			$perms['forum']['viewAttachment'] = 'allow'; // TODO: this appears to be fixed to board viewing perms
		}

		if ($userGroup['g_mem_info'])
		{
			$perms['general']['viewProfile'] = 'allow';
			$perms['profilePost']['view'] = 'allow';
			$perms['profilePost']['post'] = 'allow';
		}

		if ($userGroup['g_avoid_flood'])
		{
			$perms['general']['bypassFloodCheck'] = 'allow';
		}

		if ($userGroup['g_use_search'])
		{
			$perms['general']['search'] = 'allow';
		}

		// forum permissions

		if ($userGroup['g_post_new_topics'])
		{
			$perms['forum']['postThread'] = 'allow';
		}
		if ($userGroup['g_reply_own_topics'] || $userGroup['g_reply_other_topics'])
		{
			$perms['forum']['postReply'] = 'allow';
		}
		if ($userGroup['g_delete_own_posts'] || $userGroup['g_bitoptions'] & 128) // gbw_soft_delete_own
		{
			$perms['forum']['deleteOwnPost'] = 'allow';
		}
		if ($userGroup['g_delete_own_topics'] || $userGroup['g_bitoptions'] & 256) // gbw_soft_delete_own_topic
		{
			$perms['forum']['delete_own_topics'] = 'allow';
		}
		if ($userGroup['g_edit_posts'])
		{
			$perms['forum']['editOwnPost'] = 'allow';
		}
		if ($userGroup['g_edit_cutoff'])
		{
			$perms['forum']['editOwnPostTimeLimit'] = $userGroup['g_edit_cutoff'];
		}
		if (($userGroup['g_attach_max'] + 0) >= 0)
		{
			$perms['forum']['uploadAttachment'] = 'allow';
		}
		if ($userGroup['g_vote_polls'])
		{
			$perms['forum']['votePoll'] = 'allow';
		}

		// forum moderator permissions

		if ($userGroup['g_open_close_posts'])
		{
			$perms['forum']['lockUnlockThread'] = 'allow';
		}

		if ($userGroup['g_bitoptions'] & 1024 || $userGroup['g_bitoptions'] & 8192) // gbw_soft_delete_see OR gbw_soft_delete_topic_see
		{
			$perms['forum']['viewDeleted'] = 'allow';
		}

		if ($userGroup['g_is_supmod'])
		{
			$perms['forum']['stickUnstickThread'] = 'allow';
			$perms['forum']['manageAnyThread'] = 'allow';
			// TODO: others permissions?
		}

		// this is mapped from max number of +ve reputation points awardable in 24h
		if ($userGroup['g_rep_max_positive'])
		{
			$perms['forum']['like'] = 'allow';
			$perms['profilePost']['like'] = 'allow';
		}

		if ($userGroup['g_use_pm'])
		{
			$perms['conversation']['start'] = 'allow';
			$perms['conversation']['maxRecipients'] = $userGroup['g_max_mass_pm']; // should be max 500
		}

		if ($userGroup['g_avatar_upload'])
		{
			$perms['avatar']['allowed'] = 'allow';
			$perms['avatar']['maxFileSize'] = intval($userGroup['g_photo_max_vars']); // take the first value from '500:170:240'
			if ($perms['avatar']['maxFileSize'] > 2147483647)
			{
				$perms['avatar']['maxFileSize'] = -1;
			}
		}

		return $perms;
	}

	public function configStepUsers(array $options)
	{
		if ($options)
		{
			return false;
		}

		return $this->_controller->responseView('XenForo_ViewAdmin_Import_IPBoard_ConfigUsers', 'import_config_users');
	}

	public function stepUsers($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 100,
			'max' => false,
			// all checkbox options must default to false as they may not be submitted
			'mergeEmail' => false,
			'mergeName' => false,
			'gravatar' => false
		), $options);

		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('
				SELECT MAX(member_id)
				FROM ' . $prefix . 'members
			');
		}

		$users = $sDb->fetchAll(
			$sDb->limit($this->_getSelectUserSql('members.member_id > ' . $sDb->quote($start)), $options['limit'])
		);
		if (!$users)
		{
			return $this->_getNextUserStep();
		}

		XenForo_Db::beginTransaction();

		$next = 0;
		$total = 0;
		foreach ($users AS $user)
		{
			$next = $user['member_id'];

			$imported = $this->_importOrMergeUser($user, $options);
			if ($imported)
			{
				$total++;
			}
		}

		XenForo_Db::commit();

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}

	public function stepUsersMerge($start, array $options)
	{
		$sDb = $this->_sourceDb;

		$manual = $this->_session->getExtraData('userMerge');

		if ($manual)
		{
			$merge = $sDb->fetchAll($this->_getSelectUserSql('members.member_id IN (' . $sDb->quote(array_keys($manual)) . ')'));

			$resolve = $this->_controller->getInput()->filterSingle('resolve', XenForo_Input::ARRAY_SIMPLE);
			if ($resolve && !empty($options['shownForm']))
			{
				$this->_session->unsetExtraData('userMerge');
				$this->_resolveUserConflicts($merge, $resolve);
			}
			else
			{
				// prevents infinite loop if redirected back to step
				$options['shownForm'] = true;
				$this->_session->setStepInfo(0, $options);

				$users = array();
				foreach ($merge AS $user)
				{
					$users[$user['member_id']] = array(
						'username' => $this->_convertToUtf8($user['name'], true),
						'email' => $this->_convertToUtf8($user['email']),
						'message_count' => $user['posts'],
						'register_date' => $user['joined'],
						'conflict' => $manual[$user['member_id']]
					);
				}

				return $this->_controller->responseView(
					'XenForo_ViewAdmin_Import_MergeUsers', 'import_merge_users', array('users' => $users)
				);
			}
		}

		return $this->_getNextUserStep();
	}

	public function stepUsersFailed($start, array $options)
	{
		$sDb = $this->_sourceDb;

		$manual = $this->_session->getExtraData('userFailed');

		if ($manual)
		{
			$users = $this->_sourceDb->fetchAll($this->_getSelectUserSql('members.member_id IN (' . $sDb->quote(array_keys($manual)) . ')'));

			$resolve = $this->_controller->getInput()->filterSingle('resolve', XenForo_Input::ARRAY_SIMPLE);
			if ($resolve && !empty($options['shownForm']))
			{
				$this->_session->unsetExtraData('userFailed');
				$this->_resolveUserConflicts($users, $resolve);
			}
			else
			{
				// prevents infinite loop if redirected back to step
				$options['shownForm'] = true;
				$this->_session->setStepInfo(0, $options);

				$failedUsers = array();
				foreach ($users AS $user)
				{
					$failedUsers[$user['member_id']] = array(
						'username' => $this->_convertToUtf8($user['name'], true),
						'email' => $this->_convertToUtf8($user['email']),
						'message_count' => $user['posts'],
						'register_date' => $user['joined'],
						'failure' => $manual[$user['member_id']]
					);
				}

				return $this->_controller->responseView(
					'XenForo_ViewAdmin_Import_FailedUsers', 'import_failed_users', array('users' => $failedUsers)
				);
			}
		}

		return $this->_getNextUserStep();
	}

	protected function _resolveUserConflicts(array $users, array $resolve)
	{
		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		$total = 0;

		XenForo_Db::beginTransaction();

		foreach ($users AS $user)
		{
			if (empty($resolve[$user['member_id']]))
			{
				continue;
			}

			$info = $resolve[$user['member_id']];

			if (empty($info['action']) || $info['action'] == 'change')
			{
				if (isset($info['email']))
				{
					$user['email'] = $info['email'];
				}
				if (isset($info['username']))
				{
					$user['name'] = $info['username'];
				}

				$imported = $this->_importOrMergeUser($user);
				if ($imported)
				{
					$total++;
				}
			}
			else if ($info['action'] == 'merge')
			{
				$im = $this->_importModel;

				if ($match = $im->getUserIdByEmail($this->_convertToUtf8($user['email'])))
				{
					$this->_mergeUser($user, $match);
				}
				else if ($match = $im->getUserIdByUserName($this->_convertToUtf8($user['name'], true)))
				{
					$this->_mergeUser($user, $match);
				}

				$total++;
			}
		}

		XenForo_Db::commit();

		$this->_session->incrementStepImportTotal($total, 'users');
	}

	protected function _getNextUserStep()
	{
		if ($this->_session->getExtraData('userMerge'))
		{
			return 'usersMerge';
		}

		if ($this->_session->getExtraData('userFailed'))
		{
			return 'usersFailed';
		}

		return true;
	}

	protected function _importOrMergeUser(array $user, array $options = array())
	{
		$im = $this->_importModel;

		if ($user['email'] && $emailMatch = $im->getUserIdByEmail($this->_convertToUtf8($user['email'])))
		{
			if (!empty($options['mergeEmail']))
			{
				return $this->_mergeUser($user, $emailMatch);
			}
			else
			{
				if ($im->getUserIdByUserName($this->_convertToUtf8($user['name'], true)))
				{
					$this->_session->setExtraData('userMerge', $user['member_id'], 'both');
				}
				else
				{
					$this->_session->setExtraData('userMerge', $user['member_id'], 'email');
				}
				return false;
			}
		}

		if ($nameMatch = $im->getUserIdByUserName($this->_convertToUtf8($user['name'], true)))
		{
			if (!empty($options['mergeName']))
			{
				return $this->_mergeUser($user, $nameMatch);
			}
			else
			{
				$this->_session->setExtraData('userMerge', $user['member_id'], 'name');
				return false;
			}
		}

		return $this->_importUser($user, $options);
	}

	protected function _importUser(array $user, array $options)
	{
		if ($this->_groupMap === null)
		{
			$this->_groupMap = $this->_importModel->getImportContentMap('userGroup');
		}

		// unserialize the 'cache' blob
		$user['members_cache'] = unserialize($user['members_cache']);

		$import = array(
			'username' => $this->_convertToUtf8($user['name'], true),
			'email' => $this->_convertToUtf8($user['email']),
			'user_group_id' => $this->_mapLookUp($this->_groupMap, $user['member_group_id'], XenForo_Model_User::$defaultRegisteredGroupId),
			'secondary_group_ids' => $this->_mapLookUpList($this->_groupMap, $this->_ipbExplode($user['mgroup_others'])),
			'authentication' => array(
				'scheme_class' => 'XenForo_Authentication_IPBoard',
				'data' => array(
					'hash' => $user['members_pass_hash'],
					'salt' => $user['members_pass_salt']
				)
			),
			'about' => $this->_convertToUtf8($user['pp_about_me']),
			'homepage' => $this->_getProfileField($user, 'Website URL'),
			'location' => $this->_getProfileField($user, 'Location'),
			'gender'   => $this->_getProfileField($user, 'Gender'),

			'last_activity' => $user['last_activity'],
			'register_date' => $user['joined'],
			'ip' => $user['ip_address'],
			'message_count' => $user['posts'],

			'timezone' => $this->_importModel->resolveTimeZoneOffset($user['time_offset'], $user['dst_in_use']), // TODO: check members.dst_in_use

			'signature' => $this->_parseIPBoardBbCode($user['signature']),
			'content_show_signature' => $user['view_sigs'],

			'receive_admin_email' => $user['allow_admin_mails'],
			'allow_send_personal_conversation' => ($user['members_disable_pm'] ? 'none' : 'everyone'),
			'allow_post_profile' => ($user['pp_setting_count_comments'] ? 'everyone' : 'none'),

			'dob_day'   => $user['bday_day'],
			'dob_month' => $user['bday_month'],
			'dob_year'  => $user['bday_year'],

			'show_dob_year' => 1,
			'show_dob_date' => 1,

			'is_banned' => ($user['member_banned'] || $user['temp_ban']),
		);

		// try to give users without an avatar that have actually posted a gravatar
		if ($user['avatar_type'] == 'gravatar')
		{
			$import['gravatar'] = $this->_convertToUtf8($user['avatar_location']);
		}

		// custom title
		if ($user['title'])
		{
			$import['custom_title'] = strip_tags(
				preg_replace('#<br\s*/?>#i', ', ',
					htmlspecialchars_decode(
						$this->_convertToUtf8($user['title'])
					)
				)
			);
		}

		// identities
		$import['identities'] = array();
		if ($icq = $this->_getProfileField($user, 'ICQ'))
		{
			$import['identities']['icq'] = $icq;
		}
		if ($aim = $this->_getProfileField($user, 'AIM'))
		{
			$import['identities']['aim'] = $aim;
		}
		if ($yahoo = $this->_getProfileField($user, 'Yahoo'))
		{
			$import['identities']['yahoo'] = $yahoo;
		}
		if ($msn = $this->_getProfileField($user, 'MSN'))
		{
			$import['identities']['msn'] = $msn;
		}
		if ($skype = $this->_getProfileField($user, 'Skype'))
		{
			$import['identities']['skype'] = $skype;
		}

		// TODO: potentially import additional custom fields as about
		$import['about'] .= "\n\n" . $this->_getProfileField($user, 'Interests');

		$groups = $this->_session->getExtraData('groups');

		// user state
		switch ($user['member_group_id'])
		{
			case $groups['auth_group']:
				$import['user_state'] = 'email_confirm';
				break;
			default:
				$import['user_state'] = 'valid';
		}

		// default watch state
		switch ($user['auto_track'])
		{
			case '':
			case 0:
				$import['default_watch_state'] = '';
				break;
			case 'none':
				$import['default_watch_state'] = 'watch_no_email';
				break;
			default:
				$import['default_watch_state'] = 'watch_email';
		}

		// is admin
		if ($import['is_admin'] = $this->_isAdmin($user, $adminRestrictions))
		{
			if (empty($adminRestrictions))
			{
				$import['admin_permissions'] = $this->_importModel->getAdminPermissionIds();
			}
			else
			{
				$importAdminPerms = array();

				if ($this->_hasAdminPermission($adminRestrictions, 'core', 'tools'))
				{
					$importAdminPerms[] = 'option';
					$importAdminPerms[] = 'import';
					$importAdminPerms[] = 'upgradeXenForo';
				}

				if ($this->_hasAdminPermission($adminRestrictions, 'core', 'applications'))
				{
					$importAdminPerms[] = 'addOn';
				}

				if ($this->_hasAdminPermission($adminRestrictions, 'core', 'posts', 'bbcode_manage')
				#||	$this->_hasAdminPermission($adminRestrictions, 'core', 'posts', 'media_manage')
				#||	$this->_hasAdminPermission($adminRestrictions, 'core', 'posts', 'emoticons_manage')
				)
				{
					$importAdminPerms[] = 'bbCodeSmilie';
				}

				if ($this->_hasAdminPermission($adminRestrictions, 'core', 'system', 'task_manage'))
				{
					$importAdminPerms[] = 'cron';
				}

				if ($this->_hasAdminPermission($adminRestrictions, 'core', 'templates'))
				{
					$importAdminPerms[] = 'style';
				}

				if ($this->_hasAdminPermission($adminRestrictions, 'core', 'languages'))
				{
					$importAdminPerms[] = 'language';
				}

				if ($this->_hasAdminPermission($adminRestrictions, 'forums', 'forums'))
				{
					$importAdminPerms[] = 'node';
				}

				if ($this->_hasAdminPermission($adminRestrictions, 'members', 'members'))
				{
					$importAdminPerms[] = 'user';
					$importAdminPerms[] = 'trophy';
					$importAdminPerms[] = 'userUpgrade';
				}

				if ($this->_hasAdminPermission($adminRestrictions, 'members', 'members', 'member_ban'))
				{
					$importAdminPerms[] = 'ban';
				}

				if ($this->_hasAdminPermission($adminRestrictions, 'members', 'members', 'profilefields_global'))
				{
					$importAdminPerms[] = 'identityService';
				}

				if ($this->_hasAdminPermission($adminRestrictions, 'members', 'groups'))
				{
					$importAdminPerms[] = 'userGroup';
				}

				$import['admin_permissions'] = $importAdminPerms;
			}
		}

		$importedUserId = $this->_importModel->importUser($user['member_id'], $import, $failedKey);

		if ($importedUserId)
		{
			// import bans
			if ($import['is_banned'])
			{
				if (strpos($user['temp_ban'], ':') !== false)
				{
					// temporary ban / suspended user
					$banBits = explode(':', $user['temp_ban']);
					$endDate = intval($banBits[1]);
				}
				else
				{
					// permanent ban
					$endDate = 0;
				}

				$this->_importModel->importBan(array(
					'user_id' => $importedUserId,
					'ban_user_id' => 0,
					'ban_date' => 0,
					'end_date' => $endDate,
				));
			}

			// import super moderators
			if ($this->_isSuperModerator($user))
			{
				$this->_session->setExtraData('superMods', $user['member_id'], $importedUserId);
			}

			if (!empty($user['members_cache']['friends']) && is_array($user['members_cache']['friends']))
			{
				$friendIds = array_keys($user['members_cache']['friends']);
				$friendIds = $this->_importModel->getImportContentMap('user', $friendIds);
				$this->_importModel->importFollowing($importedUserId, $friendIds);
			}
		}
		else if ($failedKey)
		{
			$this->_session->setExtraData('userFailed', $user['member_id'], $failedKey);
		}

		return $importedUserId;
	}

	/**
	 * Returns the value of a member custom profile field for the specified member
	 *
	 * @param array $user
	 * @param string $title Name of the custom profile field
	 * @param integer If specified, fetch the field by its numeric id instead
	 *
	 * @return string UTF-8 converted
	 */
	protected function _getProfileField(array $user, $title, $id = null)
	{
		if ($id === null && empty($this->_profileFieldMap))
		{
			$map = $this->_sourceDb->fetchPairs('
				SELECT pf_title, pf_id
				FROM ' . $this->_prefix . 'pfields_data
			');

			$this->_profileFieldMap = array();

			foreach ($map AS $title => $id)
			{
				$this->_profileFieldMap[strtolower($title)] = $id;
			}
		}

		if ($id)
		{
			$title = $id;
		}

		$title = strtolower($title);

		if (array_key_exists($title, $this->_profileFieldMap))
		{
			$field = sprintf('field_%d', $this->_profileFieldMap[$title]);

			if (method_exists($this, "_handleProfileField{$title}"))
			{
				return call_user_func(array($this, "_handleProfileField{$title}"), $user[$field]);
			}

			return $this->_convertToUtf8($user[$field]);
		}

		return null;
	}

	/**
	 * Interpret the data stored in IPB's gender field
	 *
	 * @param string $gender
	 *
	 * @return string
	 */
	protected function _handleProfileFieldGender($gender)
	{
		switch ($gender)
		{
			case 'm': return 'male';
			case 'f': return 'female';
			default: return '';
		}
	}

	/**
	 * Fetches an array of all user groups to which the user belongs
	 *
	 * @param array $user
	 *
	 * @return array
	 */
	protected function _getGroupsForUser(array $user)
	{
		$groupCache = $this->_getGroupCache();

		$groups = array(
			$user['member_group_id'] => $groupCache[$user['member_group_id']]
		);

		if ($user['mgroup_others'])
		{
			foreach ($this->_ipbExplode($user['mgroup_others']) AS $groupId)
			{
				if (isset($groupCache[$groupId]))
				{
					$groups[$groupId] = $groupCache[$groupId];
				}
			}
		}

		return $groups;
	}

	/**
	 * Check if the specified user is a super moderator but checking all
	 * their user group memberships for g_is_supmod
	 *
	 * @param array $user
	 *
	 * @return boolean
	 */
	protected function _isSuperModerator(array $user)
	{
		// TODO: auto return true if _isAdmin() ?

		foreach ($this->_getGroupsForUser($user) AS $group)
		{
			if ($group['g_is_supmod'])
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if the specified user is an administrator, by looking at all of their
	 * user group memberships and checking if any of them have cp access privs.
	 *
	 * @param array $user
	 * @param array $adminRestrictions
	 *
	 * @return boolean
	 */
	protected function _isAdmin(array $user, array &$adminRestrictions = null)
	{
		$groups = $this->_session->getExtraData('groups');

		if ($user['member_group_id'] == $groups['admin_group'])
		{
			if (!empty($user['admin_restrictions']))
			{
				$adminRestrictions = unserialize($user['admin_restrictions']);
			}

			return 1;
		}
		else
		{
			foreach ($this->_getGroupsForUser($user) AS $group)
			{
				if ($group['g_access_cp'])
				{
					if (!empty($group['admin_restrictions']))
					{
						$adminRestrictions = unserialize($group['admin_restrictions']);
					}

					return 1;
				}
			}
		}

		return 0;
	}

	/**
	 * Checks that the $permissions array given has the admin permission specified
	 *
	 * @param array $adminRestrictions
	 * @param string $appName
	 * @param string $moduleName
	 * @param string $permName
	 *
	 * @return boolean
	 */
	protected function _hasAdminPermission(array $adminRestrictions, $appName, $moduleName = null, $permName = null)
	{
		$appCache = $this->_getAppCache();

		if (!array_key_exists($appName, $appCache) || !in_array($appCache[$appName]['app_id'], $adminRestrictions['applications']))
		{
			return false;
		}

		if (isset($moduleName))
		{
			$moduleCache = $this->_getModuleCache();

			foreach ($moduleCache[$appName] AS $module)
			{
				if ($module['sys_module_key'] == $moduleName)
				{
					$moduleId = $module['sys_module_id'];

					if (!in_array($moduleId, $adminRestrictions['modules']))
					{
						return false;
					}

					if (isset($permName) && !in_array($permName, $adminRestrictions['items'][$moduleId]))
					{
						return false;
					}

					return true;
				}
			}
		}

		return true;
	}

	protected function _getSelectUserSql($where)
	{
		return '
			SELECT members.*, pfields_content.*, profile_portal.*,
				apr.row_perm_cache AS admin_restrictions
			FROM ' . $this->_prefix . 'members AS members
			INNER JOIN  ' . $this->_prefix . 'pfields_content AS pfields_content ON
				(pfields_content.member_id = members.member_id)
			LEFT JOIN ' . $this->_prefix . 'profile_portal AS profile_portal ON
				(profile_portal.pp_member_id = members.member_id)
			LEFT JOIN ' . $this->_prefix .  'admin_permission_rows AS apr ON
				(apr.row_id = members.member_id AND apr.row_id_type = \'member\')
			WHERE '  . $where . '
			ORDER BY members.member_id
		';
	}

	protected function _mergeUser(array $user, $targetUserId)
	{
		$user['joined'] = max(0, $user['joined']);
		
		$this->_db->query('
			UPDATE xf_user SET
				message_count = message_count + ?,
				register_date = IF(register_date > ?, ?, register_date)
			WHERE user_id = ?
		', array($user['posts'], $user['joined'], $user['joined'], $targetUserId));

		$this->_importModel->logImportData('user', $user['member_id'], $targetUserId);

		return $targetUserId;
	}

	public function configStepAvatars(array $options)
	{
		if ($options)
		{
			return false;
		}

		return $this->_controller->responseView('XenForo_ViewAdmin_Import_IPBoard_ConfigAvatars', 'import_ipboard_config_avatars');
	}

	public function stepAvatars($start, array $options)
	{
		$options = array_merge(array(
			'path' => $this->_config['ipboard_path'] . '/uploads',
			'limit' => 50,
			'max' => false,
			// all checkbox options must default to false as they may not be submitted
			'fetchRemote' => false,
			'importPhotos' => false
		), $options);

		$where = array("pp.avatar_type = 'upload'");

		if ($options['fetchRemote'])
		{
			$where[] = "pp.avatar_type = 'url'";
		}

		if ($options['importPhotos'])
		{
			$where[] = "pp.pp_main_photo <> ''";
		}

		$where = '(' . implode(' OR ', $where) . ')';

		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('
				SELECT MAX(pp_member_id)
				FROM ' . $prefix . 'profile_portal AS pp
				WHERE ' . $where . '
			');
		}

		$avatars = $sDb->fetchAll($sDb->limit(
			'
				SELECT members.member_id,
					pp.pp_main_photo, pp.pp_main_width, pp.pp_main_height,
					pp.avatar_location, pp.avatar_size, pp.avatar_type
				FROM ' . $prefix . 'profile_portal AS pp
				INNER JOIN ' . $prefix . 'members AS members ON
					(members.member_id = pp.pp_member_id)
				WHERE ' . $where . '
					AND pp.pp_member_id > ' . $sDb->quote($start) . '
				ORDER BY pp.pp_member_id
			', $options['limit']
		));
		if (!$avatars)
		{
			return true;
		}

		$userIdMap = $model->getUserIdsMapFromArray($avatars, 'member_id');

		$next = 0;
		$total = 0;

		foreach ($avatars AS $avatar)
		{
			$next = $avatar['member_id'];

			$newUserId = $this->_mapLookUp($userIdMap, $avatar['member_id']);
			if (!$newUserId)
			{
				continue;
			}

			$avatarFile = null;

			// use profile photo instead of avatar
			if (!empty($options['importPhotos'])
				&& $avatar['pp_main_photo']
				&& file_exists("$options[path]/$avatar[pp_main_photo]"))
			{
				$avatarFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
				copy("$options[path]/$avatar[pp_main_photo]", $avatarFile);
			}

			// fetch remote URL avatar if specified
			else if (!empty($options['fetchRemote'])
				&& $avatar['avatar_type'] == 'url'
				&& $avatar['avatar_location']
				&& $avatar['avatar_location'] != 'noavatar'
				&& Zend_Uri::check($avatar['avatar_location']))
			{
				try
				{
					$httpClient = XenForo_Helper_Http::getClient(preg_replace('/\s+/', '%20', $avatar['avatar_location']));

					$response = $httpClient->request('GET');

					if ($response->isSuccessful())
					{
						$avatarFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
						file_put_contents($avatarFile, $response->getBody());
					}
				}
				catch (Zend_Http_Client_Exception $e) {}
			}

			// regular avatar import
			if (empty($avatarFile)
				&& $avatar['avatar_type'] == 'upload'
				&& $avatar['avatar_location']
				&& file_exists("$options[path]/$avatar[avatar_location]"))
			{
				$avatarFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
				copy("$options[path]/$avatar[avatar_location]", $avatarFile);
			}

			$isTemp = true;

			if ($this->_importModel->importAvatar($avatar['member_id'], $newUserId, $avatarFile))
			{
				$total++;
			}

			if ($isTemp)
			{
				@unlink($avatarFile);
			}
		}

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}

	public function stepPrivateMessages($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 300,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('
				SELECT MAX(mt_id)
				FROM ' . $prefix . 'message_topics
				WHERE mt_is_draft = 0
					AND mt_is_deleted = 0
					AND mt_is_system = 0

			');
		}

		$topics = $sDb->fetchAll($sDb->limit(
			'
				SELECT mtopics.*,
					members.name AS mt_starter_name
				FROM ' . $prefix . 'message_topics AS mtopics
				INNER JOIN  ' . $prefix . 'members AS members ON
					(mtopics.mt_starter_id = members.member_id)
				WHERE mtopics.mt_id > ' . $sDb->quote($start) . '
					AND mt_is_draft = 0
					AND mt_is_deleted = 0
					AND mt_is_system = 0
				ORDER BY mtopics.mt_id
			', $options['limit']
		));
		if (!$topics)
		{
			return true;
		}

		$next = 0;
		$total = 0;

		XenForo_Db::beginTransaction();

		foreach ($topics AS $topic)
		{
			$next = $topic['mt_id'];

			$topicUserMap = $sDb->fetchAll('
				SELECT topicUserMap.*,
					members.name AS map_user_name
				FROM ' . $prefix . 'message_topic_user_map AS topicUserMap
				INNER JOIN ' . $prefix . 'members AS members ON
					(topicUserMap.map_user_id = members.member_id)
				WHERE topicUserMap.map_topic_id = ' . $sDb->quote($topic['mt_id'])
			);

			$mapUserIds = $model->getUserIdsMapFromArray($topicUserMap, 'map_user_id');

			$recipients = array();
			foreach ($topicUserMap AS $user)
			{
				$newUserId = $this->_mapLookUp($mapUserIds, $user['map_user_id']);
				if (!$newUserId)
				{
					continue;
				}

				if ($user['map_user_active'] == 0)
				{
					$recipientState = 'deleted_ignored';
				}
				/*else if ($user['map_ignore_notification'])
				{
					$recipientState = 'deleted'; // not actually sure that is an appropriate mapping
				}*/
				else
				{
					$recipientState = 'active';
				}

				$recipients[$newUserId] = array(
					'username' => $this->_convertToUtf8($user['map_user_name'], true),
					'last_read_date' => $user['map_read_time'],
					'recipient_state' => $recipientState
				);
			}

			$conversation = array(
				'title' => $this->_convertToUtf8($topic['mt_title'], true),
				'user_id' => $this->_mapLookUp($mapUserIds, $topic['mt_starter_id']),
				'username' => $this->_convertToUtf8($topic['mt_starter_name'], true),
				'start_date' => $topic['mt_date'],
				'open_invite' => 0,
				'conversation_open' => 1
			);

			$posts = $sDb->fetchAll('
				SELECT messagePosts.*,
					members.name AS msg_author_name
				FROM ' . $prefix . 'message_posts AS messagePosts
				INNER JOIN ' . $prefix . 'members AS members ON
					(messagePosts.msg_author_id = members.member_id)
				WHERE messagePosts.msg_topic_id = ' . $sDb->quote($topic['mt_id']) . '
			');

			$messages = array();

			foreach ($posts AS $post)
			{
				$message = $this->_parseIPBoardBbCode($post['msg_post']);

				if (stripos($message, '[quote ') !== false)
				{
					$message = preg_replace(
						'/\[quote\s+name=(\'|")(.+)\1[^\]]+\]/siU',
						"[quote='\\2']",
						$message
					);
				}

				$messages[] = array(
					'message_date' => $post['msg_date'],
					'user_id' => $this->_mapLookUp($mapUserIds, $post['msg_author_id']),
					'username' => $this->_convertToUtf8($post['msg_author_name'], true),
					'message' => $message
				);
			}

			if ($model->importConversation($topic['mt_id'], $conversation, $recipients, $messages))
			{
				$total++;
			}
		}

		XenForo_Db::commit();

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}

	public function stepProfileComments($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 200,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('
				SELECT MAX(comment_id)
				FROM ' . $prefix . 'profile_comments
			');
		}

		$pcs = $sDb->fetchAll($sDb->limit(
			'
				SELECT pc.*,
					members.name AS comment_by_member_name
				FROM ' . $prefix . 'profile_comments AS pc
				INNER JOIN ' . $prefix . 'members AS members ON
					(pc.comment_by_member_id = members.member_id)
				WHERE pc.comment_id > ' . $sDb->quote($start) . '
				ORDER BY pc.comment_id
			', $options['limit']
		));
		if (!$pcs)
		{
			return true;
		}

		$next = 0;
		$total = 0;

		$userIds = array();
		foreach ($pcs AS $pc)
		{
			$userIds[] = $pc['comment_for_member_id'];
			$userIds[] = $pc['comment_by_member_id'];
		}
		$userIdMap = $model->getImportContentMap('user', $userIds);

		XenForo_Db::beginTransaction();

		foreach ($pcs AS $pc)
		{
			if (trim($pc['comment_by_member_name']) === '')
			{
				continue;
			}

			$next = $pc['comment_id'];

			$profileUserId = $this->_mapLookUp($userIdMap, $pc['comment_for_member_id']);
			if (!$profileUserId)
			{
				continue;
			}

			$postUserId = $this->_mapLookUp($userIdMap, $pc['comment_by_member_id'], 0);

			$import = array(
				'profile_user_id' => $profileUserId,
				'user_id' => $postUserId,
				'username' => $this->_convertToUtf8($pc['comment_by_member_name'], true),
				'post_date' => $pc['comment_date'],
				'message' => $this->_parseIPBoardText($pc['comment_content']),
				'ip' => $pc['comment_ip_address'],
				'message_state' => ($pc['comment_approved'] ? 'visible' : 'moderated'),
			);

			if ($model->importProfilePost($pc['comment_id'], $import))
			{
				$total++;
			}
		}

		XenForo_Db::commit();

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}

	public function stepStatusUpdates($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 200,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('
				SELECT MAX(status_id)
				FROM ' . $prefix . 'member_status_updates
			');
		}

		$statusUpdates = $sDb->fetchAll($sDb->limit(
			'
				SELECT msus.*,
					members.name AS status_member_name
				FROM ' . $prefix . 'member_status_updates AS msus
				INNER JOIN ' . $prefix . 'members AS members ON
					(msus.status_member_id = members.member_id)
				WHERE msus.status_id > ' . $sDb->quote($start) . '
				ORDER BY msus.status_id
			', $options['limit']
		));
		if (!$statusUpdates)
		{
			return true;
		}

		$next = 0;
		$total = 0;

		$userIdMap = $model->getUserIdsMapFromArray($statusUpdates, 'status_member_id');

		XenForo_Db::beginTransaction();

		foreach ($statusUpdates AS $statusUpdate)
		{
			$next = $statusUpdate['status_id'];

			$userId = $this->_mapLookUp($userIdMap, $statusUpdate['status_member_id']);

			$import = array(
				'profile_user_id' => $userId,
				'user_id' => $userId,
				'username' => $this->_convertToUtf8($statusUpdate['status_member_name'], true),
				'post_date' => $statusUpdate['status_date'],
				'message' => $this->_parseIPBoardText($statusUpdate['status_content']),
				'message_state' => 'visible',
				'comment_count' => $statusUpdate['status_replies'],
			);

			if ($profilePostId = $model->importProfilePost($statusUpdate['status_id'], $import))
			{
				$db = XenForo_Application::get('db');

				if ($statusUpdate['status_is_latest'])
				{
					$db->update('xf_user_profile', array
					(
						'status' => $import['message'],
						'status_date' => $import['post_date'],
						'status_profile_post_id' => $profilePostId
					), 'user_id = ' . $db->quote($userId));
				}

				$total++;

				if (!empty($statusUpdate['status_replies']))
				{
					$replies = $sDb->fetchAll('
						SELECT replies.*,
							members.name
						FROM ' . $prefix . 'member_status_replies AS replies
						INNER JOIN  ' . $prefix . 'members AS members ON
							(replies.reply_member_id = members.member_id)
						WHERE replies.reply_status_id = ' . $sDb->quote($statusUpdate['status_id']) . '
						ORDER BY replies.reply_date
					');

					$replyUserIdMap = $model->getUserIdsMapFromArray($replies, 'reply_member_id');

					$lastIds = array();

					foreach ($replies AS $reply)
					{
						$commentImport = array(
							'profile_post_id' => $profilePostId,
							'user_id' => $this->_mapLookUp($replyUserIdMap, $reply['reply_member_id']),
							'username' => $this->_convertToUtf8($reply['name']),
							'comment_date' => $reply['reply_date'],
							'message' => $this->_parseIPBoardText($reply['reply_content']),
						);

						$lastIds[] = $model->importProfilePostComment($reply['reply_id'], $commentImport);
					}

					$firstReply = reset($replies);
					$lastReply = end($replies);

					$db->update('xf_profile_post', array(
						'comment_count' => count($replies),
						'first_comment_date' => $firstReply['reply_date'],
						'last_comment_date' => $lastReply['reply_date'],
						'latest_comment_ids' => implode(',', array_slice($lastIds, -3))
					), 'profile_post_id = ' . $sDb->quote($profilePostId));
				}
			}
		}

		XenForo_Db::commit();

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}

	public function stepForums($start, array $options)
	{
		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		if ($start > 0)
		{
			// after importing everything, rebuild nested set info.
			XenForo_Model::create('XenForo_Model_Node')->updateNestedSetInfo();

			//rebuild the full permission cache so forums appear
			XenForo_Model::create('XenForo_Model_Permission')->rebuildPermissionCache();

			return true;
		}

		$forums = $this->_sourceDb->fetchAll('
			SELECT *
			FROM ' . $this->_prefix . 'forums
		');
		if (!$forums)
		{
			return true;
		}

		$forumTree = array();
		foreach ($forums AS $forum)
		{
			$forumTree[$forum['parent_id']][$forum['id']] = $forum;
		}

		XenForo_Db::beginTransaction();

		$total = $this->_importForumTree(-1, $forumTree);

		XenForo_Db::commit();

		$this->_session->incrementStepImportTotal($total);

		return array(1, array(), '');
	}

	protected function _importForumTree($parentId, array $forumTree, array $forumIdMap = array())
	{
		if (!isset($forumTree[$parentId]))
		{
			return 0;
		}

		XenForo_Db::beginTransaction();

		$total = 0;

		foreach ($forumTree[$parentId] AS $forum)
		{
			$import = array(
				'title' => $this->_convertToUtf8($forum['name'], true),
				'description' => $this->_convertToUtf8($forum['description'], true),
				'display_order' => $forum['position'],
				'parent_node_id' => $this->_mapLookUp($forumIdMap, $forum['parent_id'], 0),
				'display_in_list' => 1 // no equivalent
			);

			if ($forum['redirect_on'] && $forum['redirect_url'])
			{
				$import['node_type_id'] = 'LinkForum';
				$import['link_url'] = $forum['redirect_url'];

				$nodeId = $this->_importModel->importLinkForum($forum['id'], $import);
			}
			else if ($forum['sub_can_post']) // forum
			{
				$import['node_type_id'] = 'Forum';
				$import['discussion_count'] = $forum['topics'];
				$import['message_count'] = $forum['posts'] + $forum['topics'];
				$import['last_post_date'] = $forum['last_post'];
				$import['last_post_username'] = $this->_convertToUtf8($forum['last_poster_name'], true);

				$nodeId = $this->_importModel->importForum($forum['id'], $import);
			}
			else
			{
				$import['node_type_id'] = 'Category';

				$nodeId = $this->_importModel->importCategory($forum['id'], $import);
			}

			if ($nodeId)
			{
				$forumIdMap[$forum['id']] = $nodeId;

				$total++;
				$total += $this->_importForumTree($forum['id'], $forumTree, $forumIdMap);
			}
		}

		XenForo_Db::commit();

		return $total;
	}

	public function configStepForumPermissions(array $options)
	{
		if ($options)
		{
			return false;
		}

		$this->_bootstrap($this->_session->getConfig());

		$nodeMap = $this->_importModel->getImportContentMap('node');

		$forumStates = $this->_guessForumPermissions();

		/* @var $nodeModel XenForo_Model_Node */
		$nodeModel = $this->_importModel->getModelFromCache('XenForo_Model_Node');

		$nodes = $nodeModel->getAllNodes();

		$displayNodes = array();

		foreach ($nodes AS $nodeId => $node)
		{
			if (in_array($nodeId, $nodeMap))
			{
				$node['permissionState'] = $forumStates[$nodeId];

				$displayNodes[$nodeId] = $node;
			}
		}

		$viewParams = array('nodes' => $displayNodes);

		return $this->_controller->responseView(
			'XenForo_ViewAdmin_Import_IPBoard_ConfigForumPermissions',
			'import_ipboard_config_forumpermissions',
			$viewParams
		);
	}

	public function stepForumPermissions($start, array $options)
	{
		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		if ($start > 0)
		{
			//rebuild the full permission cache so forums appear
			XenForo_Model::create('XenForo_Model_Permission')->rebuildPermissionCache();

			return true;
		}

		$reset = array('general' => array('viewNode' => 'reset'));
		$allow = array('general' => array('viewNode' => 'content_allow'));

		$total = 0;

		XenForo_Db::beginTransaction();

		foreach ($options AS $nodeId => $permission)
		{
			switch ($permission)
			{
				case 'memberOnly':
				{
					// revoke view permissions for guests (1)
					$model->insertNodePermissionEntries($nodeId, 1, 0, $reset);

					$total++;

					break;
				}

				case 'staffOnly':
				{
					// revoke view permissions for all but staff
					$model->insertNodePermissionEntries($nodeId, 0, 0, $reset);

					// allow 'Administrating' group (3)
					$model->insertNodePermissionEntries($nodeId, 3, 0, $allow);

					// allow 'Moderating' group (4)
					$model->insertNodePermissionEntries($nodeId, 4, 0, $allow);

					$total++;

					break;
				}

				case 'public':
				default:
					// no change required
			}
		}

		XenForo_Db::commit();

		$this->_session->incrementStepImportTotal($total);

		return array(1, array(), '');
	}

	/**
	 * Forums must have been imported already for this to function.
	 */
	protected function _guessForumPermissions()
	{
		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		$groupIds = $this->_session->getExtraData('groups');

		$groupPermSets = $sDb->fetchPairs('
			SELECT g_id, g_perm_id
			FROM ' . $prefix . 'groups
		');
		foreach ($groupPermSets AS &$permSets)
		{
			$permSets = $this->_ipbExplode($permSets);
		}

		$forumPermissions = array();

		$ipbForumPerms = $sDb->fetchPairs('
			SELECT forums.id, perms.perm_view
			FROM ' . $prefix . 'forums AS forums
			LEFT JOIN ' . $prefix . 'permission_index AS perms ON
				(perms.perm_type_id = forums.id AND perms.perm_type = \'forum\')
		');
		foreach ($ipbForumPerms AS $forumId => $viewPermSets)
		{
			if ($viewPermSets == '*')
			{
				$state = 'public';
			}
			else
			{
				$viewPermSets = $this->_ipbExplode($viewPermSets);

				$guestViews = array_intersect($groupPermSets[$groupIds['guest_group']], $viewPermSets);
				if (empty($guestViews))
				{
					// forum is not viewable by guests
					$state = 'memberOnly';

					$memberViews = array_intersect($groupPermSets[$groupIds['member_group']], $viewPermSets);
					if (empty($memberViews))
					{
						// forum is not viewable by registered members
						$state = 'staffOnly';
					}
				}
				else
				{
					$state = 'public';
				}
			}

			$forumPermissions[$this->_importModel->mapNodeId($forumId)] = $state;
		}

		return $forumPermissions;
	}

	public function stepModerators($start, array $options)
	{
		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		$moderators = array();

		$forumMods = $sDb->fetchAll('
			SELECT moderators.*
			FROM ' . $prefix . 'moderators AS moderators
			INNER JOIN ' . $prefix . 'members AS members ON
				(moderators.member_id = members.member_id)
		');
		foreach ($forumMods AS $forumMod)
		{
			$moderators[$forumMod['member_id']] = $forumMod;
		}

		if ($superMods = $this->_session->getExtraData('superMods'))
		{
			// get the full list of super moderator permissions
			$superModPerms = XenForo_Model::create('XenForo_Model_Moderator')->getFullPermissionSet();

			foreach ($superMods AS $oldUserId => $newUserId)
			{
				$moderators[$oldUserId]['superMod'] = $newUserId;
			}
		}

		if (!$moderators)
		{
			return true;
		}

		$nodeMap = $model->getImportContentMap('node');
		$userIdMap = $model->getImportContentMap('user', array_keys($moderators));

		$total = 0;

		XenForo_Db::beginTransaction();

		foreach ($moderators AS $userId => $moderator)
		{
			$newUserId = $this->_mapLookUp($userIdMap, $userId);
			if (!$newUserId)
			{
				continue;
			}

			if (!empty($moderator['superMod']))
			{
				$globalModPermissions = $superModPerms;
				$superMod = true;
			}
			else
			{
				$globalModPermissions = array();
				$superMod = false;
			}

			if (!empty($moderator['forum_id']))
			{
				$forumPerms = $this->_calculateModeratorPermissions($moderator);

				foreach ($this->_ipbExplode($moderator['forum_id']) AS $forumId)
				{
					$newNodeId = $this->_mapLookUp($nodeMap, $forumId);
					if (!$newNodeId)
					{
						continue;
					}

					$mod = array(
						'content_id' => $newNodeId,
						'user_id' => $newUserId,
						'moderator_permissions' => array('forum' => $forumPerms['forum'])
					);

					$model->importNodeModerator($forumId, $newUserId, $mod);

					$total++;
				}
			}

			$mod = array(
				'user_id' => $newUserId,
				'is_super_moderator' => $superMod,
				'moderator_permissions' => $globalModPermissions
			);
			$model->importGlobalModerator($userId, $mod);
		}

		$this->_session->incrementStepImportTotal($total);

		XenForo_Db::commit();

		return true;
	}

	protected function _calculateModeratorPermissions(array $mod)
	{
		$modBits = intval($mod['mod_bitoptions']);

		$general = array();

		if (!empty($mod['view_ip']))
		{
			$general['viewIps'] = true;
		}

		if ($modBits & 1) // bw_flag_spammers
		{
			$general['cleanSpam'] = true;
		}

		$forum = array
		(
			'viewModerated' => true,
			'approveUnapprove' => true
		);

		if (!empty($mod['edit_post']))
		{
			$forum['editAnyPost'] = true;
		}

		if (!empty($mod['edit_topic']))
		{
			$forum['manageAnyThread'] = true;
		}

		if (!empty($mod['pin_topic'])
		 || !empty($mod['unpin_topic']))
		{
			$forum['stickUnstickThread'] = true;
		}

		if (!empty($mod['close_topic'])
		 || !empty($mod['open_topic']))
		{
			$forum['lockUnlockThread'] = true;
		}

		if ($modBits & 2) // bw_mod_soft_delete
		{
			$forum['deleteAnyPost'] = true;
		}

		if ($mod['delete_post'])
		{
			$forum['hardDeleteAnyPost'] = true;
		}

		if ($modBits & 16) // bw_mod_soft_delete_topic
		{
			$forum['deleteAnyThread'] = true;
		}

		if (!empty($mod['delete_topic']))
		{
			$forum['hardDeleteAnyThread'] = true;
		}

		if ($modBits & 4   // bw_mod_un_soft_delete
		 || $modBits & 32) // bw_mod_un_soft_delete_topic
		{
			$forum['undelete'] = true;
		}

		if ($modBits & 8    // bw_mod_soft_delete_see
		 || $modBits & 64   // bw_mod_soft_delete_topic_see
		 || $modBits & 256) // bw_mod_soft_delete_see_post
		{
			$forum['viewDeleted'] = true;
		}

		return array(
			'general' => $general,
			'forum' => $forum
		);
	}

	public function stepThreads($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 100,
			'postDateStart' => 0,
			'postLimit' => 800,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('
				SELECT MAX(tid)
				FROM ' . $prefix . 'topics
			');
		}

		// pull threads from things we actually imported as forums
		$threads = $sDb->fetchAll($sDb->limit(
			'
				SELECT
					topics.*, IF (members.name IS NULL, topics.starter_name, members.name) AS starter_name,
					IF (lastposters.name IS NULL, topics.last_poster_name, lastposters.name) AS last_poster_name
			#		, sdl.*, sdl_members.name AS sdl_name
				FROM ' . $prefix . 'topics AS topics
				LEFT JOIN ' . $prefix . 'members AS members ON
					(topics.starter_id = members.member_id)
				LEFT JOIN ' . $prefix . 'members AS lastposters ON
					(topics.last_poster_id = lastposters.member_id)
				INNER JOIN ' . $prefix . 'forums AS forums ON
					(topics.forum_id = forums.id AND forums.redirect_on = 0 AND forums.sub_can_post = 1)
			#	LEFT JOIN ' . $prefix . 'core_soft_delete_log AS sdl ON
			#		(topics.tid = sdl.sdl_obj_id AND sdl.sdl_obj_key = \'topic\')
			#	LEFT JOIN ' . $prefix . 'members AS sdl_members ON
			#		(sdl_members.member_id = sdl.sdl_obj_member_id)
				WHERE topics.tid >= ' . $sDb->quote($start) . '
					AND topics.state <> \'link\'
				ORDER BY topics.tid
			', $options['limit']
		));
		if (!$threads)
		{
			return true;
		}

		$next = 0;
		$total = 0;
		$totalPosts = 0;

		$nodeMap = $model->getImportContentMap('node');

		XenForo_Db::beginTransaction();

		foreach ($threads AS $thread)
		{
			if (trim($thread['title']) === '')
			{
				continue;
			}

			$postDateStart = $options['postDateStart'];

			$next = $thread['tid'] + 1; // uses >=, will be moved back down if need to continue
			$options['postDateStart'] = 0;

			$maxPosts = $options['postLimit'] - $totalPosts;
			$posts = $sDb->fetchAll($sDb->limit(
				'
					SELECT posts.*,
						IF (members.name IS NULL, posts.author_name, members.name) AS author_name
					FROM ' . $prefix . 'posts AS posts
					LEFT JOIN ' . $prefix . 'members AS members ON
						(posts.author_id = members.member_id)
					WHERE posts.topic_id = ' . $sDb->quote($thread['tid']) . '
						AND posts.post_date > ' . $sDb->quote($postDateStart) . '
					ORDER BY posts.post_date
				', $maxPosts
			));
			if (!$posts)
			{
				if ($postDateStart)
				{
					// continuing thread but it has no more posts
					$total++;
				}
				continue;
			}

			if ($postDateStart)
			{
				// continuing thread we already imported
				$threadId = $model->mapThreadId($thread['tid']);

				$position = $this->_db->fetchOne('
					SELECT MAX(position)
					FROM xf_post
					WHERE thread_id = ?
				', $threadId);
			}
			else
			{
				$forumId = $this->_mapLookUp($nodeMap, $thread['forum_id']);
				if (!$forumId)
				{
					continue;
				}

				if (trim($thread['starter_name']) === '')
				{
					$thread['starter_name'] = 'Guest';
				}

				$import = array(
					'title' => $this->_convertToUtf8($thread['title'], true),
					'node_id' => $forumId,
					'user_id' => $model->mapUserId($thread['starter_id'], 0),
					'username' => $this->_convertToUtf8($thread['starter_name'], true),
					'discussion_open' => ($thread['state'] == 'open' ? 1 : 0),
					'post_date' => $thread['start_date'],
					'reply_count' => $thread['posts'],
					'view_count' => $thread['views'],
					'sticky' => $thread['pinned'],
					'last_post_date' => $thread['last_post'],
					'last_post_user_id' => $model->mapUserId($thread['last_poster_id'], 0),
					'last_post_username' => $this->_convertToUtf8($thread['last_poster_name'], true)
				);
				switch ($thread['approved'])
				{
					case 0: $import['discussion_state'] = 'moderated'; break;
					case -1: $import['discussion_state'] = 'deleted'; break;
					default: $import['discussion_state'] = 'visible'; break;
				}

				$threadId = $model->importThread($thread['tid'], $import);
				if (!$threadId)
				{
					continue;
				}

				$position = -1;

				$subs = $sDb->fetchPairs('
					SELECT member_id, topic_track_type
					FROM ' . $prefix . 'tracker
					WHERE topic_id = ' . $sDb->quote($thread['tid'])
				);
				if ($subs)
				{
					$userIdMap = $model->getImportContentMap('user', array_keys($subs));
					foreach ($subs AS $userId => $emailUpdate)
					{
						$newUserId = $this->_mapLookUp($userIdMap, $userId);
						if (!$newUserId)
						{
							continue;
						}

						$model->importThreadWatch($newUserId, $threadId, ($emailUpdate == 'none' ? 0 : 1));
					}
				}
			}

			if ($threadId)
			{
				$quotedPostIds = array();

				$threadTitleRegex = '#^(re:\s*)?' . preg_quote($thread['title'], '#') . '$#i';

				$userIdMap = $model->getUserIdsMapFromArray($posts, 'author_id');

				foreach ($posts AS $i => $post)
				{
					if (!is_null($post['post_title']) && $post['post_title'] !== '' && !preg_match($threadTitleRegex, $post['post_title']))
					{
						$post['post'] = '[b]' . htmlspecialchars_decode($post['post_title']) . "[/b]\n\n" . ltrim($post['post']);
					}

					$post['post'] = $this->_parseIPBoardBbCode($post['post']);

					if (trim($post['author_name']) === '')
					{
						$post['username'] = 'Guest';
					}

					//echo "<div>Import message $post[pid]<br /><textarea rows=4 cols=60>" . $this->_strToHex($post['post']) . "</textarea></div>";

					$import = array(
						'thread_id' => $threadId,
						'user_id' => $this->_mapLookUp($userIdMap, $post['author_id'], 0),
						'username' => $this->_convertToUtf8($post['author_name'], true),
						'post_date' => $post['post_date'],
						'message' => $post['post'],
						'ip' => $post['ip_address']
					);
					switch ($post['queued'])
					{
						case 1: $import['message_state'] = 'moderated'; $import['position'] = $position; break;
						case 2: $import['message_state'] = 'deleted'; $import['position'] = $position; break;
						default: $import['message_state'] = 'visible'; $import['position'] = ++$position; break;
					}

					$post['xf_post_id'] = $model->importPost($post['pid'], $import);

					$options['postDateStart'] = $post['post_date'];
					$totalPosts++;

					// look for attributed quotes
					if (stripos($post['post'], '[quote ') !== false) // yes, with the space!
					{
						if (preg_match_all('/\[quote\s+([^"\'\]]+|"[^"]*"|\'[^\']*\')+\]/siU', $post['post'], $quotes))
						{
							$post['quotes'] = array_fill_keys($quotes[0], true);

							foreach ($post['quotes'] AS $quote => $quotedPostId)
							{
								// extract the post id
								if (preg_match('/\spost=(\'|"|)(\d+)\1/si', $quote, $match))
								{
									$quotedPostId = intval($match[2]);

									$quotedPostIds[] = $quotedPostId;
								}
							}
						}
					}

					$posts[$i] = $post;
				}

				$postIdMap = (empty($quotedPostIds) ? array() : $model->getImportContentMap('post', $quotedPostIds));

				$db = XenForo_Application::get('db');

				foreach ($posts AS $post)
				{
					if (!empty($post['quotes']))
					{
						$postQuotesRewrite = $this->_rewriteQuotes($post['post'], $post['quotes'], $postIdMap);

						if ($post['post'] != $postQuotesRewrite)
						{
							$db->update('xf_post', array('message' => $postQuotesRewrite), 'post_id = ' . $db->quote($post['xf_post_id']));
						}
					}
				}
			}

			if (count($posts) < $maxPosts)
			{
				// done this thread
				$total++;
				$options['postDateStart'] = 0;
			}
			else
			{
				// not necessarily done the thread; need to pick it up next page
				break;
			}
		}

		if ($options['postDateStart'])
		{
			// not done this thread, need to continue with it
			$next--;
		}

		XenForo_Db::commit();

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}

	protected function _rewriteQuotes($message, array $quotes, array $postIdMap)
	{
		foreach ($quotes AS $quote => &$replace)
		{
			if (preg_match('/ name=(\'|")(.+)\1/siU', $quote, $nameMatch))
			{
				$name = $nameMatch[2];

				if (preg_match('/ post=(\'|"|)(\d+)\1/siU', $quote, $postMatch))
				{
					$post = $this->_mapLookUp($postIdMap, $postMatch[2]);

					$replace = sprintf('[quote="%s, post: %d"]', $name, $post);
				}
				else
				{
					$replace = sprintf('[quote="%s"]', $name);
				}
			}
			else
			{
				unset($quotes[$quote]);
			}
		}

		if (!empty($quotes))
		{
			return str_replace(array_keys($quotes), $quotes, $message);
		}

		return $message;
	}

	public function configStepPolls(array $options)
	{
		if ($options)
		{
			return false;
		}

		return $this->_controller->responseView('XenForo_ViewAdmin_Import_IPBoard_ConfigPolls', 'import_ipboard_config_polls');
	}

	public function stepPolls($start, array $options)
	{
		$options = array_merge(array(
			'whichQuestion' => 'first',
			'limit' => 100,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('
				SELECT MAX(pid)
				FROM ' . $prefix . 'polls
			');
		}

		$polls = $sDb->fetchAll($sDb->limit(
			'
				SELECT polls.*
				FROM ' . $prefix . 'polls AS polls
				INNER JOIN ' . $prefix . 'topics AS topics ON
					(topics.tid = polls.tid AND topics.state <> \'link\')
				WHERE polls.pid > ' . $sDb->quote($start) . '
				ORDER BY polls.pid
			', $options['limit']
		));
		if (!$polls)
		{
			return true;
		}

		$next = 0;
		$total = 0;

		$threadIdMap = $model->getThreadIdsMapFromArray($polls, 'tid');

		XenForo_Db::beginTransaction();

		foreach ($polls AS $poll)
		{
			$next = $poll['pid'];

			$newThreadId = $this->_mapLookUp($threadIdMap, $poll['tid']);
			if (!$newThreadId)
			{
				continue;
			}

			$questions = unserialize(stripslashes($poll['choices']));
			$pollInfo = ($options['whichQuestion'] == 'last' ? end($questions) : reset($questions));

			if (empty($pollInfo['question']))
			{
				$pollInfo['choice'] = $pollInfo;
				$pollInfo['question'] = $poll['poll_question'];
			}

			$import = array(
				'question' => $this->_convertToUtf8($pollInfo['question']),
				'public_votes' => $poll['poll_view_voters'],
				'multiple' => !empty($pollInfo['multi']),
				'close_date' => 0,
			);

			$newPollId = $model->importThreadPoll($poll['pid'], $newThreadId, $import, array_map(array($this, '_convertToUtf8'), $pollInfo['choice']), $responseIds);
			if ($newPollId)
			{
				$voters = $sDb->fetchAll('
					SELECT member_id, vote_date, member_choices
					FROM ' . $prefix . 'voters
					WHERE tid = ' . $sDb->quote($poll['tid'])
				);

				if ($voters)
				{
					$checkVote = reset($voters);

					// if member_choices is null, we have only vote counts, not dates and users
					if (is_null($checkVote['member_choices']))
					{
						if (!empty($pollInfo['votes']))
						{
							foreach ($pollInfo['votes'] AS $voteOption => $count)
							{
								$voteOption = max(0, $voteOption - 1);

								if (isset($responseIds[$voteOption]))
								{
									for ($i = 0; $i < $count; $i++)
									{
										$model->importPollVote($newPollId, 0, $responseIds[$voteOption], 0);
									}
								}
							}
						}
						else
						{
							// weird polls end up here - log if you like
						}
					}
					// we have vote dates and user ids
					else
					{
						$userIdMap = $model->getUserIdsMapFromArray($voters, 'member_id');
						foreach ($voters AS $voter)
						{
							$userId = $this->_mapLookUp($userIdMap, $voter['member_id']);
							if (!$userId)
							{
								continue;
							}

							$answers = unserialize(stripslashes($voter['member_choices']));
							$votes = ($options['whichQuestion'] == 'last' ? end($answers) : reset($answers));

							foreach ($votes AS $voteOption)
							{
								$voteOption = max(0, $voteOption - 1);

								if (!isset($responseIds[$voteOption]))
								{
									continue;
								}

								$model->importPollVote($newPollId, $userId, $responseIds[$voteOption], $voter['vote_date']);
							}
						}
					}
				}
			}

			$total++;
		}

		XenForo_Db::commit();

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}

	public function stepAttachments($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 50,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('
				SELECT MAX(attach_id)
				FROM ' . $prefix . 'attachments
			');
		}

		$attachments = $sDb->fetchAll($sDb->limit(
			'
				SELECT
					attach_id, attach_date, attach_hits,
					attach_file, attach_location,
					attach_member_id AS member_id,
					attach_rel_id AS post_id
				FROM ' . $prefix . 'attachments
				WHERE attach_id > ' . $sDb->quote($start) . '
					AND attach_rel_module = \'post\'
				ORDER BY attach_id
			', $options['limit']
		));
		if (!$attachments)
		{
			return true;
		}

		$next = 0;
		$total = 0;

		$userIdMap = $model->getUserIdsMapFromArray($attachments, 'member_id');

		$postIdMap = $model->getPostIdsMapFromArray($attachments, 'post_id');
		$posts = $model->getModelFromCache('XenForo_Model_Post')->getPostsByIds($postIdMap);

		foreach ($attachments AS $attachment)
		{
			$next = $attachment['attach_id'];

			$newPostId = $this->_mapLookUp($postIdMap, $attachment['post_id']);
			if (!$newPostId)
			{
				continue;
			}

			$attachFileOrig = $this->_config['ipboard_path'] . '/uploads/' . $attachment['attach_location'];
			if (!file_exists($attachFileOrig))
			{
				continue;
			}

			$attachFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
			copy($attachFileOrig, $attachFile);

			$isTemp = true;

			$success = $model->importPostAttachment(
				$attachment['attach_id'],
				$this->_convertToUtf8($attachment['attach_file']),
				$attachFile,
				$this->_mapLookUp($userIdMap, $attachment['member_id'], 0),
				$newPostId,
				$attachment['attach_date'],
				array('view_count' => $attachment['attach_hits']),
				array($this, 'processAttachmentTags'),
				$posts[$newPostId]['message']
			);
			if ($success)
			{
				$total++;
			}

			if ($isTemp)
			{
				@unlink($attachFile);
			}
		}

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}

	public static function processAttachmentTags($oldAttachmentId, $newAttachmentId, $messageText)
	{
		if (stripos($messageText, '[attachment=') !== false)
		{
			$messageText = preg_replace("/\[attachment={$oldAttachmentId}:[^\]]+\]/siU", "[ATTACH]{$newAttachmentId}.IPB[/ATTACH]", $messageText);
		}

		return $messageText;
	}

	public function stepReputation($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 100,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('
				SELECT MAX(id)
				FROM ' . $prefix . 'reputation_index
				WHERE rep_rating > 0
					AND app = \'forums\'
					AND type = \'pid\'
			');
		}

		$reputations = $sDb->fetchAll($sDb->limit(
			'
				SELECT rep.*,
					posts.author_id
				FROM ' . $prefix . 'reputation_index AS rep
				INNER JOIN ' . $prefix . 'posts AS posts ON
					(posts.pid = rep.type_id AND rep.app = \'forums\' AND rep.type = \'pid\')
				WHERE id > ' . $sDb->quote($start) . '
					AND rep.rep_rating > 0
				ORDER BY rep.id
			', $options['limit']
		));
		if (!$reputations)
		{
			return true;
		}

		$next = 0;
		$total = 0;

		$userIds = array();
		foreach ($reputations AS $rep)
		{
			$userIds[] = $rep['member_id'];
			$userIds[] = $rep['author_id'];
		}

		$postIdMap = $model->getPostIdsMapFromArray($reputations, 'type_id');
		$userIdMap = $model->getImportContentMap('user', $userIds);

		XenForo_Db::beginTransaction();

		foreach ($reputations AS $rep)
		{
			$next = $rep['id'];

			$newPostId = $this->_mapLookUp($postIdMap, $rep['type_id']);
			if (!$newPostId)
			{
				continue;
			}

			$model->importLike(
				'post',
				$newPostId,
				$this->_mapLookUp($userIdMap, $rep['author_id']),
				$this->_mapLookUp($userIdMap, $rep['member_id']),
				$rep['rep_date']
			);

			$total++;
		}

		XenForo_Db::commit();

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}

	// Cache fetchers

	protected $_groupCache = null;

	/**
	 * Fetches an array representing all the source user groups
	 *
	 * @return array [userGroupId => userGroup, userGroupId => userGroup...]
	 */
	protected function _getGroupCache()
	{
		if ($this->_groupCache === null)
		{
			$this->_groupCache = array();

			$groups = $this->_sourceDb->fetchAll('
				SELECT groups.*,
					apr.row_perm_cache AS admin_restrictions
				FROM ' . $this->_prefix . 'groups AS groups
				LEFT JOIN ' . $this->_prefix . 'admin_permission_rows AS apr ON
					(apr.row_id = groups.g_id AND apr.row_id_type = \'group\')
			');

			foreach ($groups AS $group)
			{
				$this->_groupCache[$group['g_id']] = $group;
			}
		}

		return $this->_groupCache;
	}

	protected $_appCache = null;

	protected $_moduleCache = null;

	/**
	 * Caches the app_cache and module_cache from IPB
	 */
	protected function _cacheAppsAndModules()
	{
		$caches = $this->_sourceDb->fetchPairs('
			SELECT cs_key, cs_value
			FROM ' . $this->_prefix . 'cache_store
			WHERE cs_key IN(\'app_cache\', \'module_cache\')
		');

		$this->_appCache = unserialize($caches['app_cache']);
		$this->_moduleCache = unserialize($caches['module_cache']);
	}

	/**
	 * Gets the application cache
	 *
	 * @return array
	 */
	protected function _getAppCache()
	{
		if ($this->_appCache === null)
		{
			$this->_cacheAppsAndModules();
		}

		return $this->_appCache;
	}

	/**
	 * Gets the module cache
	 *
	 * @return array
	 */
	protected function _getModuleCache()
	{
		if ($this->_moduleCache === null)
		{
			$this->_cacheAppsAndModules();
		}

		return $this->_moduleCache;
	}

	// IPB data handling functions

	/**
	 * Remove HTML line breaks and UTF-8 conversion
	 *
	 * @param string $message
	 *
	 * @return string
	 */
	protected function _parseIPBoardText($message)
	{
		// Handle HTML line breaks
		$message = preg_replace('/<br( \/)?>(\s*)/si', "\n", $message);

		return $this->_convertToUtf8($message, true);
	}

	/**
	 * Parse out HTML smilies and other stuff we can't use from IP.Board BB code
	 *
	 * @param string $message
	 * @param boolean Auto-link URLs in IP.Board messages
	 *
	 * @return string
	 */
	protected function _parseIPBoardBbCode($message, $autoLink = true)
	{
		$message = $this->_parseIPBoardText($message);

		if ($autoLink)
		{
			$message = XenForo_Helper_String::autoLinkBbCode($message);
		}

		$search = array(
			// HTML image <img /> smilies
			"/<img\s+src='([^']+)'\s+class='bbc_emoticon'\s+alt='([^']+)'\s+\/>/siU"
				=> '\2',

			// strip anything after a comma in [FONT]
			'/\[(font)=(\'|"|)([^,\]]+)(,[^\]]*)(\2)\]/siU'
				=> '[\1=\2\3\2]'
		);

		return preg_replace(array_keys($search), $search, $message);
	}

	/**
	 * Explodes IPB's ,x,y,z, format into array(x, y, z)
	 *
	 * @param string $commaList
	 *
	 * @return array
	 */
	protected function _ipbExplode($commaList)
	{
		return preg_split('/,/', $commaList, -1, PREG_SPLIT_NO_EMPTY);
	}
}