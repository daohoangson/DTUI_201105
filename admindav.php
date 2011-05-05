<?php

$startTime = microtime(true);
$fileDir = dirname(__FILE__);

require($fileDir . '/library/XenForo/Autoloader.php');
XenForo_Autoloader::getInstance()->setupAutoloader($fileDir . '/library');

XenForo_Application::initialize($fileDir . '/library', $fileDir);
XenForo_Application::set('page_start_time', $startTime);

if (!XenForo_Application::debugMode())
{
	echo 'Must be in debug mode.';
	exit;
}

$dependencies = new XenForo_Dependencies_Admin();
$dependencies->preLoadData();

if (!function_exists('mb_detect_encoding'))
{
	// this is a hack to not require the mbstring functions for *1* function call
	function mb_detect_encoding()
	{
		return 'UTF-8';
	}
}

require($fileDir . '/library/Sabre/Sabre.autoload.php');

$request = new Zend_Controller_Request_Http();
$baseUrl = $request->getBaseUrl();

$auth = new Sabre_HTTP_BasicAuth();
$auth->setRealm('XenForo Admin CP WebDAV');
$authData = $auth->getUserPass();

/* @var $userModel XenForo_Model_User */
$userModel = XenForo_Model::create('XenForo_Model_User');
$authValid = false;

$userId = $userModel->validateAuthentication($authData[0], $authData[1]);
if ($userId)
{
	$visitor = XenForo_Visitor::setup($userId);
	if ($visitor['is_admin'])
	{
		$authValid = true;
	}
}

if (!$authValid)
{
	$auth->requireLogin();
	echo "Authentication required";
	exit;
}

$root = new XenForo_SabreDav_RootDirectory();
$tree = new Sabre_DAV_ObjectTree($root);

$server = new Sabre_DAV_Server($tree);
$server->setBaseUri($baseUrl . '/');

// implement but ignore locking, in attempt to allow finder, etc to do writes
$lockBackend = new XenForo_SabreDav_LocksNoOp();
$lockPlugin = new Sabre_DAV_Locks_Plugin($lockBackend);
$server->addPlugin($lockPlugin);

$server->exec();