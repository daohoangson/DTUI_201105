<?php

/**
 * General XenForo exception handler. Has support for throwing errors that
 * are specifically targetted at users, and even throwing multiple messages
 * together in one exception. (This latter behavior is primarily used as
 * a convienence in things like the DWs.)
 *
 * @package XenForo_Core
 */
class XenForo_Exception extends Exception
{
	protected $_userPrintable = false;
	protected $_messages = null;

	/**
	 * Constructor.
	 *
	 * @param array|string $message Exception message. May be multiple messages.
	 * @param boolean $userPrintable If true, message is printable to the user.
	 */
	public function __construct($message, $userPrintable = false)
	{
		$this->_userPrintable = (boolean)$userPrintable;

		if (is_array($message) && count($message) > 0)
		{
			$this->_messages = $message;
			$message = reset($message);
		}

		parent::__construct($message);
	}

	/**
	 * Determines whether the exception is printable.
	 *
	 * @return boolean
	 */
	public function isUserPrintable()
	{
		return $this->_userPrintable;
	}

	/**
	 * Gets all messages that are attached to this exception. If an non-empty array
	 * was passed to the constructor, this will return an array; otherwise, it will
	 * return a string.
	 *
	 * @return string|array
	 */
	public function getMessages()
	{
		if (is_array($this->_messages))
		{
			return $this->_messages;
		}
		else
		{
			return $this->getMessage();
		}
	}
}