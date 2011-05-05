<?php

/**
* Database Helper method to support nested transactions
*
* @package XenForo_Core
*/
class XenForo_Db
{
	/**
	* Stack of savepoints for each DB object
	*
	* @var array
	*/
	protected static $_savepointStack = array();

	/**
	* Constant for representing the initial transaction.
	*
	* @var string
	*/
	const INITIAL_TRANSACTION     = 'INITIAL_TRANSACTION';

	/**
	* Starts a new transaction, if already in one it creates a savepoint
	*
	* @param Zend_Db_Adapater_Abstract
	*/
	public static function beginTransaction(Zend_Db_Adapter_Abstract $db = null)
	{
		if ($db === null)
		{
			$db = XenForo_Application::get('db');
		}

		$objectId = spl_object_hash($db);
		if (!isset(self::$_savepointStack[$objectId]))
		{
			self::$_savepointStack[$objectId] = array();
		}

		if (sizeof(self::$_savepointStack[$objectId]) < 1)
		{
			$db->beginTransaction();
			array_push(self::$_savepointStack[$objectId], self::INITIAL_TRANSACTION);
		}
		else
		{
			$savepointName = md5(uniqid());
			self::_execQuery($db, 'SAVEPOINT ' . $savepointName);
			array_push(self::$_savepointStack[$objectId], $savepointName);
		}

	}

	/**
	* Commits the current savepoint or the main transaction
	*
	* @param Zend_Db_Adapater_Abstract
	*/
	public static function commit(Zend_Db_Adapter_Abstract $db = null)
	{
		if ($db === null)
		{
			$db = XenForo_Application::get('db');
		}
		$objectId = spl_object_hash($db);

		if (empty(self::$_savepointStack[$objectId]))
		{
			// we don't have a log of a transaction, but try to commit anyway
			$db->commit();
		}
		else
		{
			$savepointName = array_pop(self::$_savepointStack[$objectId]);
			if ($savepointName == self::INITIAL_TRANSACTION)
			{
				$db->commit();
			}
			else
			{
				self::_execQuery($db, 'RELEASE SAVEPOINT ' . $savepointName);
			}
		}
	}

	/**
	* Rollback the last save point or the entire transaction if needed
	*
	* @param Zend_Db_Adapater_Abstract
	*/
	public static function rollback(Zend_Db_Adapter_Abstract $db = null)
	{
		if ($db === null)
		{
			$db = XenForo_Application::get('db');
		}
		$objectId = spl_object_hash($db);

		if (empty(self::$_savepointStack[$objectId]))
		{
			// we don't have a log of a transaction, but try to rollback anyway
			$db->rollback();
			return;
		}
		else
		{
			$savepointName = array_pop(self::$_savepointStack[$objectId]);
			if ($savepointName === self::INITIAL_TRANSACTION)
			{
				$db->rollback();
			}
			else if ($savepointName)
			{
				self::_execQuery($db, 'ROLLBACK TO SAVEPOINT ' . $savepointName);
			}
		}
	}

	/**
	 * Rolls back all pending transactions/save points.
	 *
	 * @param Zend_Db_Adapter_Abstract|null $db
	 */
	public static function rollbackAll(Zend_Db_Adapter_Abstract $db = null)
	{
		if ($db === null)
		{
			$db = XenForo_Application::get('db');
		}
		$objectId = spl_object_hash($db);

		while (!empty(self::$_savepointStack[$objectId]))
		{
			self::rollback($db);
		}
	}

	protected static function _execQuery(Zend_Db_Adapter_Abstract $db, $query)
	{
		switch (get_class($db))
		{
			case 'Zend_Db_Adapter_Mysqli':
				$db->getConnection()->query($query);
				break;
			case 'Zend_Db_Adapter_Pdo_Mysql':
				$db->getConnection()->exec($query);
				break;
		}
	}

	/**
	 * Quotes for a LIKE clause.
	 *
	 * @param string $string String to quote
	 * @param string $wildCard Where to place a wildcard match (l, r, or lr)
	 * @param Zend_Db_Adapter_Abstract|null $db DB adapter
	 *
	 * @return string
	 */
	public static function quoteLike($string, $wildCard, Zend_Db_Adapter_Abstract $db = null)
	{
		if ($db === null)
		{
			$db = XenForo_Application::get('db');
		}

		switch ($wildCard)
		{
			case 'r':
				$prefix = '';
				$suffix = '%';
				break;

			case 'l':
				$prefix = '%';
				$suffix = '';
				break;

			case 'lr':
				$prefix = '%';
				$suffix = '%';
				break;

			default:
				$prefix = '';
				$suffix = '';
				break;
		}

		$string = str_replace(array('%', '_'), array('\\%', '\\_'), $string);
		return $db->quote($prefix . $string . $suffix);
	}
}