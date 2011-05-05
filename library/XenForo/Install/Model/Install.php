<?php

class XenForo_Install_Model_Install extends XenForo_Model
{
	public function getRequirementErrors(Zend_Db_Adapter_Abstract $db = null)
	{
		$errors = array();

		$phpVersion = phpversion();
		if (version_compare($phpVersion, '5.2.4', '<'))
		{
			$errors['phpVersion'] = new XenForo_Phrase('php_version_x_does_not_meet_requirements', array('version' => $phpVersion));
		}

		$safeModeIni = @ini_get('safe_mode');
		if (is_bool($safeModeIni) || intval($safeModeIni))
		{
			$isSafeMode = (bool)$safeModeIni;
		}
		else
		{
			$isSafeMode = in_array(strtolower($safeModeIni), array('on', 'yes', 'true'));
		}
		if ($isSafeMode)
		{
			$errors['safe_mode'] = new XenForo_Phrase('php_must_not_be_in_safe_mode');
		}

		if (!function_exists('mysqli_connect'))
		{
			$errors['mysqlPhp'] = new XenForo_Phrase('required_php_extension_x_not_found', array('extension' => 'MySQLi'));
		}

		if (!function_exists('gd_info'))
		{
			$errors['gd'] = new XenForo_Phrase('required_php_extension_x_not_found', array('extension' => 'GD'));
		}
		else if (!function_exists('imagecreatefromjpeg'))
		{
			$errors['gdJpeg'] = new XenForo_Phrase('gd_jpeg_support_missing');
		}

		if (!function_exists('iconv'))
		{
			$errors['iconv'] = new XenForo_Phrase('required_php_extension_x_not_found', array('extension' => 'Iconv'));
		}

		if (!function_exists('ctype_alnum'))
		{
			$errors['ctype'] = new XenForo_Phrase('required_php_extension_x_not_found', array('extension' => 'Ctype'));
		}

		if (!function_exists('preg_replace'))
		{
			$errors['pcre'] = new XenForo_Phrase('required_php_extension_x_not_found', array('extension' => 'PCRE'));
		}

		if (!function_exists('spl_autoload_register'))
		{
			$errors['spl'] = new XenForo_Phrase('required_php_extension_x_not_found', array('extension' => 'SPL'));
		}

		if (!function_exists('json_encode'))
		{
			$errors['json'] = new XenForo_Phrase('required_php_extension_x_not_found', array('extension' => 'JSON'));
		}

		if (!class_exists('DOMDocument') || !class_exists('SimpleXMLElement'))
		{
			$errors['xml'] = new XenForo_Phrase('required_php_xml_extensions_not_found');
		}

		if ($db)
		{
			$mySqlVersion = $db->getServerVersion();
			if ($mySqlVersion && intval($mySqlVersion) < 5)
			{
				$errors['mysqlVersion'] = new XenForo_Phrase('mysql_version_x_does_not_meet_requirements', array('version' => $mySqlVersion));
			}
		}

		$dataDir = XenForo_Application::getInstance()->getRootDir() . '/data';
		if (!is_dir($dataDir) || !is_writable($dataDir))
		{
			$errors['dataDir'] = new XenForo_Phrase('directory_x_must_be_writable', array('directory' => $dataDir));
		}
		else
		{
			foreach (scandir($dataDir) AS $file)
			{
				if ($file[0] == '.')
				{
					continue;
				}

				$fullPath = "$dataDir/$file";
				if (is_dir($fullPath) && !is_writable($fullPath))
				{
					$errors['dataDir'] = new XenForo_Phrase('all_directories_under_x_must_be_writable', array('directory' => $dataDir));
				}
			}
		}

		$internalDataDir = XenForo_Helper_File::getInternalDataPath();
		if (!is_dir($internalDataDir) || !is_writable($internalDataDir))
		{
			$errors['internalDataDir'] = new XenForo_Phrase('directory_x_must_be_writable', array('directory' => $internalDataDir));
		}
		else
		{
			foreach (scandir($internalDataDir) AS $file)
			{
				if ($file[0] == '.')
				{
					continue;
				}

				$fullPath = "$internalDataDir/$file";
				if (is_dir($fullPath) && !is_writable($fullPath))
				{
					$errors['internalDataDir'] = new XenForo_Phrase('all_directories_under_x_must_be_writable', array('directory' => $internalDataDir));
				}
			}
		}

		return $errors;
	}

	public function deleteApplicationTables()
	{
		$db = $this->_getDb();

		$removed = array();
		foreach ($db->listTables() AS $table)
		{
			if ($this->isApplicationTable($table))
			{
				$removed[] = $table;
				$db->query('DROP TABLE ' . $table);
			}
		}

		return $removed;
	}

	public function hasApplicationTables()
	{
		$db = $this->_getDb();

		foreach ($db->listTables() AS $table)
		{
			if ($this->isApplicationTable($table))
			{
				return true;
			}
		}

		return false;
	}

	public function isApplicationTable($table)
	{
		return (substr($table, 0, 3) == 'xf_');
	}

	public function createApplicationTables()
	{
		$db = $this->_getDb();

		$tables = XenForo_Install_Data_MySql::getTables();

		foreach ($tables AS $table)
		{
			$db->query($table);
		}

		return array_keys($tables);
	}

	public function insertDefaultData()
	{
		$db = $this->_getDb();

		$insertData = XenForo_Install_Data_MySql::getData();
		foreach ($insertData AS $data)
		{
			$db->query($data);
		}

		return count($data);
	}

	public function createDirectories()
	{
		$internalDataDir = XenForo_Helper_File::getInternalDataPath();

		$dirs = array(
			$internalDataDir . '/temp',
			$internalDataDir . '/page_cache',
		);
		foreach ($dirs AS $dir)
		{
			XenForo_Helper_File::createDirectory($dir, true);
		}
	}

	public function insertAdministrator(array $data)
	{
		$password = $data['password'];
		$passwordConfirm = $data['password_confirm'];
		unset($data['password'], $data['password_confirm']);

		XenForo_Db::beginTransaction();

		$writer = XenForo_DataWriter::create('XenForo_DataWriter_User');
		$writer->bulkSet($data);
		$writer->set('user_group_id', XenForo_Model_User::$defaultRegisteredGroupId);
		$writer->set('user_state', 'valid');
		$writer->setPassword($password, $passwordConfirm);
		$writer->save();
		$user = $writer->getMergedData();

		$admin = XenForo_DataWriter::create('XenForo_DataWriter_Admin');
		$admin->set('user_id', $user['user_id']);
		$admin->set('extra_user_group_ids', XenForo_Model_User::$defaultAdminGroupId);
		$admin->save();

		$adminModel = $this->getModelFromCache('XenForo_Model_Admin');
		$adminPerms = $adminModel->getAllAdminPermissions();
		$adminModel->updateUserAdminPermissions($user['user_id'], array_keys($adminPerms));

		// insert super mod with all permissions
		/* @var $moderatorModel XenForo_Model_Moderator */
		$moderatorModel = $this->getModelFromCache('XenForo_Model_Moderator');
		$modInterfaceGroupIds = $moderatorModel->getGeneralModeratorInterfaceGroupIds();
		$generalInterfaceGroupIds = $modInterfaceGroupIds;
		foreach($moderatorModel->getContentModeratorHandlers() AS $handler)
		{
			$modInterfaceGroupIds = array_merge($modInterfaceGroupIds, $handler->getModeratorInterfaceGroupIds());
		}

		$modPerms = array();
		foreach ($moderatorModel->getModeratorPermissions($modInterfaceGroupIds) AS $permGroup => $perms)
		{
			foreach ($perms AS $permId => $perm)
			{
				$modPerms[$permGroup][$permId] = 1;
			}
		}

		$extra = array('extra_user_group_ids' => XenForo_Model_User::$defaultModeratorGroupId);
		$moderatorModel->insertOrUpdateGeneralModerator($user['user_id'], $modPerms, true, $extra);

		XenForo_Db::commit();

		return $user;
	}

	public function completeInstallation()
	{
		$this->writeInstallLock();
		$this->_getUpgradeModel()->insertUpgradeLog(null, 'install', 1);
		$this->_getUpgradeModel()->updateVersion();
	}

	public function writeInstallLock()
	{
		$fileName = XenForo_Helper_File::getInternalDataPath() .'/install-lock.php';

		$fp = fopen($fileName, 'w');
		fwrite($fp, '<?php header(\'Location: ../index.php\'); /* Installed: ' . date(DATE_RFC822) . ' */');
		fclose($fp);

		XenForo_Helper_File::makeWritableByFtpUser($fileName);
	}

	public function isInstalled()
	{
		return (
			file_exists(XenForo_Helper_File::getInternalDataPath() . '/install-lock.php')
			&& file_exists(XenForo_Application::getInstance()->getConfigDir() . '/config.php')
		);
	}

	public function generateConfig(array $config)
	{
		$lines = array();
		foreach ($config AS $key => $value)
		{
			if (is_array($value))
			{
				if (empty($value))
				{
					continue;
				}
				foreach ($value AS $subKey => $subValue)
				{
					$lines[] = '$config[\'' . addslashes($key) . '\'][\'' . addslashes($subKey) . '\'] = \'' . addslashes($subValue) . '\';';
				}
				$lines[] = '';
			}
			else
			{
				$lines[] = '$config[\'' . addslashes($key) . '\'] = ' . var_export($value, true) . ';';
			}
		}

		// windows line breaks for notepad
		return "<?php\r\n\r\n"
			. implode("\r\n", $lines)
			. "\r\n\r\n" . '$config[\'superAdmins\'] = \'1\';';
	}

	/**
	 * @return XenForo_Install_Model_Upgrade
	 */
	protected function _getUpgradeModel()
	{
		return $this->getModelFromCache('XenForo_Install_Model_Upgrade');
	}
}