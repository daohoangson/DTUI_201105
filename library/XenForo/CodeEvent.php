<?php

/**
 * Fires code events and executes event listener callbacks.
 *
 * @package XenForo_CodeEvents
 */
class XenForo_CodeEvent
{
	/**
	 * The list of event listeners for all events.
	 *
	 * @var array Format: [event id][] => callback
	 */
	protected static $_listeners = false;

	/**
	 * Private constructor, use statically.
	 */
	private function __construct()
	{
	}

	/**
	 * Fires the specified code event and calls any listener callbacks that are attached to it.
	 *
	 * The listener may return false, which will prevent any further listeners from
	 * running and cause this function (fire) to return false. This indicates that
	 * the listener handled the job the built-in code was going to do and if applicable,
	 * the built-in code need not run. (This will only apply in limited circumstances.)
	 *
	 * Note that if you want to allow arguments to be modified, they must be passed by reference. Eg:
	 * 		array($value, &$reference)
	 * If you do this, the receiving function will always be given the arguments by reference!
	 *
	 * @param string $event Name of the event to first
	 * @param array $args List of arguments to pass to the callback.
	 * 			If you want to make something modifiable, you must pass it by reference to the array.
	 *
	 * @return boolean
	 */
	public static function fire($event, array $args = array())
	{
		if (!self::$_listeners || empty(self::$_listeners[$event]))
		{
			return true;
		}

		foreach (self::$_listeners[$event] AS $callback)
		{
			//TODO: Need some friendly error handling around this
			if (is_callable($callback))
			{
				$return = call_user_func_array($callback, $args);
				if ($return === false)
				{
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Gets the listeners for a specific event.
	 *
	 * @param string $event
	 *
	 * @return array|false List of listener callbacks or false
	 */
	public static function getEventListeners($event)
	{
		if (isset(self::$_listeners[$event]))
		{
			return self::$_listeners[$event];
		}
		else
		{
			return false;
		}
	}

	/**
	 * Sets the list of listeners for all events.
	 *
	 * @param array $listeners Format: [event id][] => callback
	 * @param boolean $keepExisting
	 */
	public static function setListeners(array $listeners, $keepExisting = true)
	{
		if (!self::$_listeners || !$keepExisting)
		{
			self::$_listeners = $listeners;
		}
		else
		{
			self::$_listeners = array_merge(self::$_listeners, $listeners);
		}
	}

	/**
	 * Adds a listener for the specified event. This method takes an arbitrary
	 * callback, so can be used with more advanced things like object-based
	 * callbacks (and simple function-only callbacks).
	 *
	 * @param string $event Event to listen to
	 * @param callback $callback Function/method to call.
	 */
	public static function addListener($event, $callback)
	{
		if (!is_array(self::$_listeners))
		{
			self::$_listeners = array();
		}

		self::$_listeners[$event][] = $callback;
	}

	/**
	 * Removes all listeners.
	 */
	public static function removeListeners()
	{
		self::$_listeners = false;
	}
}