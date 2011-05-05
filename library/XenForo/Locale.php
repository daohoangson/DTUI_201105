<?php

/**
 * Helper methods to generate locale sensitive output.
 *
 * @package XenForo_Core
 */
class XenForo_Locale
{
	/**
	 * Default language to use for locale-specific output (if not overridden).
	 *
	 * @var array
	 */
	protected static $_language = array();

	/**
	 * Default time zone to use for locale-specific output (if not overridden)
	 *
	 * @var DateTimeZone|null
	 */
	protected static $_timeZone = null;

	/**
	 * A cached DateTime object. This will be set only if setTimestamp exists on it
	 * (PHP 5.3 and newer). This serves as an optimization to avoid object creation
	 * and date parsing overhead.
	 *
	 * @var DateTime|null
	 */
	protected static $_dateObj = null;

	protected static $_dayStartTimestamps = null;

	/**
	 * Translate a numeric day of the week to representation that will be used in phrases.
	 *
	 * @var array
	 */
	protected static $_dowTranslation = array(
		0 => 'sunday',
		1 => 'monday',
		2 => 'tuesday',
		3 => 'wednesday',
		4 => 'thursday',
		5 => 'friday',
		6 => 'saturday'
	);

	/**
	 * Private constructor. Use this class statically.
	 */
	private function __construct()
	{
	}

	/**
	 * Set the default language information and time zone (optionally).
	 *
	 * @param array $language
	 * @param string|null $timeZoneString String time zone (eg, Europe/London)
	 */
	public static function setDefaultLanguage(array $language, $timeZoneString = null)
	{
		self::$_language = $language;
		if ($timeZoneString)
		{
			self::setDefaultTimeZone($timeZoneString);
		}
	}

	/**
	 * Sets the default time zone.
	 *
	 * @param string $timeZoneString String time zone (eg, Europe/London);
	 */
	public static function setDefaultTimeZone($timeZoneString)
	{
		self::$_timeZone = new DateTimeZone($timeZoneString);

		if (method_exists('DateTime', 'setTimestamp'))
		{
			self::$_dateObj = new DateTime('', self::$_timeZone);
		}
	}

	/**
	 * Gets the default time zone.
	 *
	 * @return DateTimeZone|null
	 */
	public static function getDefaultTimeZone()
	{
		return self::$_timeZone;
	}

	/**
	 * Gets the current timezone offset from UTC in seconds
	 *
	 * @return integer
	 */
	public static function getTimeZoneOffset()
	{
		return self::_getDateObject()->getOffset();
	}

	/**
	 * Gets a date object that fits the requirements (correct timestamp and time zone).
	 *
	 * @param integer|DateTime|null $timestamp Unix timestamp or a DateTime object that's already configured
	 * @param string|null $timeZoneString String time zone. If null, uses default (and can use date object optimization if available)
	 *
	 * @return DateTime
	 */
	protected static function _getDateObject($timestamp = null, $timeZoneString = null)
	{
		if ($timestamp instanceof DateTime)
		{
			return $timestamp;
		}
		else if ($timestamp === null)
		{
			$timestamp = XenForo_Application::$time;
		}

		if ($timeZoneString)
		{
			$timeZone = new DateTimeZone($timeZoneString);
		}
		else
		{
			if (!self::$_timeZone)
			{
				self::setDefaultTimeZone('UTC');
			}

			if (self::$_dateObj)
			{
				self::$_dateObj->setTimestamp($timestamp);

				return self::$_dateObj;
			}

			$timeZone = self::$_timeZone;
		}

		$dt = new DateTime('@' . $timestamp);
		$dt->setTimezone($timeZone);
		return $dt;
	}

	/**
	 * Gets the formatted date/time using the given format. String-based
	 * identifiers (months, days of week) need to be passed in.
	 *
	 * @param DateTime $date DateTime object, with correct time set
	 * @param string $format Format to display as; supports a subset of the formats from the built-in date() function
	 * @param array $phrases List of phrases that will be used to replace string-based identifiers
	 *
	 * @return string Formatted date
	 */
	public static function getFormattedDateInternal(DateTime $date, $format, array $phrases)
	{
		$dateParts = explode('|', $date->format('j|w|n|Y|G|i|s|S'));
		list($dayOfMonth, $dayOfWeek, $month, $year, $hour, $minute, $second, $ordinalSuffix) = $dateParts;

		$output = '';

		$formatters = str_split($format);
		$formatterCount = count($formatters);
		for ($i = 0; $i < $formatterCount; $i++)
		{
			$identifier = $formatters[$i];

			switch ($identifier)
			{
				// day of month
				case 'd': $output .= sprintf('%02d', $dayOfMonth); continue;
				case 'j': $output .= $dayOfMonth; continue;

				// day of week
				case 'D': $output .= $phrases['day_' . self::$_dowTranslation[$dayOfWeek] . '_short']; continue;
				case 'l': $output .= $phrases['day_' . self::$_dowTranslation[$dayOfWeek]]; continue;

				// month
				case 'm': $output .= sprintf('%02d', $month); continue;
				case 'n': $output .= $month; continue;
				case 'F': $output .= $phrases['month_' . $month]; continue;
				case 'M': $output .= $phrases['month_' . $month . '_short']; continue;

				// year
				case 'Y': $output .= $year; continue;
				case 'y': $output .= substr($year, 2); continue;

				// am/pm
				case 'a': $output .= $phrases[($hour >= 12 ? 'time_pm_lower' : 'time_am_lower')]; continue;
				case 'A': $output .= $phrases[($hour >= 12 ? 'time_pm_upper' : 'time_am_upper')]; continue;

				// hour
				case 'H': $output .= sprintf('%02d', $hour); continue;
				case 'h': $output .= sprintf('%02d', $hour % 12 ? $hour % 12 : 12); continue;
				case 'G': $output .= $hour; continue;
				case 'g': $output .= ($hour % 12 ? $hour % 12 : 12); continue;

				// minute
				case 'i': $output .= $minute; continue;

				// second
				case 's': $output .= $second; continue;

				// ordinal
				case 'S': $output .= $ordinalSuffix; continue; //TODO: this is English only at present

				case '\\':
					$i++;
					if ($i < $formatterCount)
					{
						$output .= $formatters[$i];
					}
					continue;

				// anything else is printed
				default: $output .= $identifier;
			}
		}

		return $output;
	}

	/**
	 * Gets a date formatted in the requested format.
	 *
	 * @param integer|DateTime $timestamp Unix timestamp or a DateTime object that's already configured
	 * @param string $format Format to display as; compatible with sub-set of date() options
	 * @param array|null $language Language (if overriding default)
	 * @param string|null $timeZoneString Time zone user is in (if overriding default)
	 *
	 * @return string
	 */
	public static function getFormattedDate($timestamp, $format, array $language = null, $timeZoneString = null)
	{
		if (!$language)
		{
			$language = self::$_language;
		}

		$date = self::_getDateObject($timestamp, $timeZoneString);

		if (!$language)
		{
			return $date->format($format);
		}
		else
		{
			return self::getFormattedDateInternal($date, $format, $language['phrase_cache']);
		}
	}

	/**
	 * Formats the given timestamp as a date.
	 *
	 * @param integer|DateTime $timestamp Unix timestamp or a DateTime object that's already configured
	 * @param string $format Format that maps to a known type. Uses default if specified. (Currently ignored.)
	 * @param array|null $language Info about language to override default
	 * @param string|null $timeZoneString String time zone to override default
	 *
	 * @return string
	 */
	public static function date($timestamp, $format = null, array $language = null, $timeZoneString = null)
	{
		if ($timestamp === null || $timestamp === false)
		{
			return '';
		}

		if (!$language)
		{
			$language = self::$_language;
		}

		$date = self::_getDateObject($timestamp, $timeZoneString);

		if (!$language)
		{
			return $date->format('M j, Y');
		}
		else
		{
			if (!$format || $format == 'relative')
			{
				$relativeDate = self::getRelativeDate(
					$date, $language['phrase_cache']
				);

				if ($relativeDate !== false)
				{
					return $relativeDate;
				}
				$format = 'absolute';
			}

			switch ($format)
			{
				case 'year':
					$dateFormat = 'Y';
					break;

				case 'monthDay':
					$dateFormat = 'F j';
					break;

				case 'picker':
					$dateFormat = 'Y-m-d';
					break;

				case 'absolute':
					$dateFormat = $language['date_format'];
					break;

				default:
					$dateFormat = $format;
			}

			return self::getFormattedDateInternal($date, $dateFormat, $language['phrase_cache']);
		}
	}

	/**
	 * Formats the given timestamp as a time.
	 *
	 * @param integer|DateTime $timestamp Unix timestamp or a DateTime object that's already configured
	 * @param string $format Format that maps to a known type. Uses default if specified. (Currently ignored.)
	 * @param array|null $language Info about language to override default
	 * @param string|null $timeZoneString String time zone to override default
	 *
	 * @return string
	 */
	public static function time($timestamp, $format = null, array $language = null, $timeZoneString = null)
	{
		if ($timestamp === null || $timestamp === false)
		{
			return '';
		}

		if (!$language)
		{
			$language = self::$_language;
		}

		$date = self::_getDateObject($timestamp, $timeZoneString);

		if (!$language)
		{
			return $date->format('g:i A');
		}
		else
		{
			if (!$format)
			{
				$format = 'absolute';
			}

			switch ($format)
			{
				case 'absolute':
					$timeFormat = $language['time_format'];
					break;

				default:
					$timeFormat = $format;
			}

			return self::getFormattedDateInternal($date, $timeFormat, $language['phrase_cache']);
		}
	}

	/**
	 * Formats the given timestamp as a date and a time.
	 *
	 * @param integer|DateTime $timestamp Unix timestamp or a DateTime object that's already configured
	 * @param string $format Format that maps to a known type. Uses default if specified.
	 * @param array|null $language Info about language to override default
	 * @param string|null $timeZoneString String time zone to override default
	 *
	 * @return string|array If format 'separate' is specified, returns [dateString, date, time]
	 */
	public static function dateTime($timestamp, $format = null, array $language = null, $timeZoneString = null)
	{
		if (!$language)
		{
			$language = self::$_language;
		}

		$date = self::_getDateObject($timestamp, $timeZoneString);

		if (!$language)
		{
			return $date->format('M j, Y g:i A');
		}
		else
		{
			if (!$format || $format == 'relative')
			{
				$relativeDate = self::getRelativeDateTime(
					$date, $language['time_format'], $language['phrase_cache']
				);

				if ($relativeDate !== false)
				{
					return $relativeDate;
				}
				else
				{
					return self::getFormattedDateInternal($date, $language['date_format'], $language['phrase_cache']);
				}
			}

			switch ($format)
			{
				case 'absolute':
				case 'separate':
				default:
					$dateTimeFormat = $language['date_format'] . '|' . $language['time_format'];
					$formatPhrase = 'date_x_at_time_y';
			}

			$parts = explode('|', self::getFormattedDateInternal($date, $dateTimeFormat, $language['phrase_cache']));

			$dateFind = array(
				'{date}' => $parts[0],
				'{time}' => $parts[1]
			);

			$dateString = strtr($language['phrase_cache'][$formatPhrase], $dateFind);

			if ($format == 'separate')
			{
				$dayStarts = self::getDayStartTimestamps();

				return array(
					'string' => $dateString,
					'date' => $parts[0],
					'time' => $parts[1],
					'relative' => ($timestamp > $dayStarts['week'])
				);
			}
			else
			{
				return $dateString;
			}
		}
	}

	/**
	 * Returns a string representing the given date and time as a relative period before now, in certain circumstances
	 *
	 * @param DateTime $date
	 * @param string $timeFormat
	 * @param array $phrases
	 *
	 * @return string|false
	 */
	public static function getRelativeDateTime(DateTime $date, $timeFormat, array $phrases)
	{
		$timestamp = $date->format('U');
		$interval = XenForo_Application::$time - $timestamp;

		if ($interval < 0)
		{
			//TODO: handle future dates - Tomorrow, later today...
			return false;
		}

		if ($interval <= 60)
		{
			return $phrases['a_moment_ago'];
		}

		if ($interval < 120)
		{
			return $phrases['one_minute_ago'];
		}

		if ($interval < 3600)
		{
			return str_replace(
				'{minutes}', floor($interval / 60), $phrases['x_minutes_ago']
			);
		}

		$dayStartTimestamps = self::getDayStartTimestamps();

		if ($timestamp >= $dayStartTimestamps['today'])
		{
			return str_replace(
				'{time}', self::getFormattedDateInternal($date, $timeFormat, $phrases), $phrases['today_at_x']
			);
		}

		if ($timestamp >= $dayStartTimestamps['yesterday'])
		{
			return str_replace(
				'{time}', self::getFormattedDateInternal($date, $timeFormat, $phrases), $phrases['yesterday_at_x']
			);
		}

		if ($timestamp >= $dayStartTimestamps['week'])
		{
			$dateReplace = explode('|', self::getFormattedDateInternal($date, 'l|' . $timeFormat, $phrases));

			$dateFind = array(
				'{day}' => $dateReplace[0],
				'{time}' => $dateReplace[1]
			);

			return strtr($phrases['day_x_at_time_y'], $dateFind);
		}

		return false;
	}

	/**
	 * Returns a string representing the given date as today, yesterday, dayname (within this past week)
	 *
	 * @param DateTime $date
	 * @param string $timeFormat
	 * @param array $phrases
	 *
	 * @return string|false
	 */
	public static function getRelativeDate(DateTime $date, array $phrases)
	{
		$timestamp = $date->format('U');

		if ($timestamp > XenForo_Application::$time)
		{
			// date in the future... TODO: Tomorrow, Later today
			return false;
		}

		$dayStartTimestamps = self::getDayStartTimestamps();

		if ($timestamp >= $dayStartTimestamps['today'])
		{
			return $phrases['today'];
		}

		if ($timestamp >= $dayStartTimestamps['yesterday'])
		{
			return $phrases['yesterday'];
		}

		if ($timestamp >= $dayStartTimestamps['week'])
		{
			return self::getFormattedDateInternal($date, 'l', $phrases);
		}

		return false;
	}

	/**
	 * Fetches timestamps for the start of today, yesterday or a week ago
	 *
	 * @return array [today, yesterday, week]
	 */
	public static function getDayStartTimestamps()
	{
		if (!self::$_dayStartTimestamps)
		{
			$date = new DateTime('@' . XenForo_Application::$time);
			$date->setTimezone(self::$_timeZone ? self::$_timeZone : new DateTimeZone('UTC'));
			$date->setTime(0, 0, 0);

			list($todayStamp, $todayDow) = explode('|', $date->format('U|w'));

			$date->modify('-1 day');
			$yesterdayStamp = $date->format('U');

			$date->modify('-5 days');
			$weekStamp = $date->format('U');

			self::$_dayStartTimestamps = array(
				'now' => XenForo_Application::$time,
				'today' => $todayStamp,
				'todayDow' => $todayDow,
				'yesterday' => $yesterdayStamp,
				'week' => $weekStamp
			);
		}

		return self::$_dayStartTimestamps;
	}

	/**
	 * Formats the given number for a language/locale. Also used for file size formatting.
	 *
	 * @param float|integer $number Number to format
	 * @param integer|string $precision Number of places to show after decimal point or word "size" for file size
	 * @param array|null $language Language to override default
	 *
	 * @return string Formatted number
	 */
	public static function numberFormat($number, $precision = 0, array $language = null)
	{
		if (!$language)
		{
			$language = self::$_language;
		}

		if (!$language)
		{
			$decimalSep = '.';
			$thousandsSep = ',';
		}
		else
		{
			$decimalSep = $language['decimal_point'];
			$thousandsSep = $language['thousands_separator'];
		}

		if ($precision === 'size')
		{
			// TODO: this may need to be language dependent
			if ($number >= 1048576) // 1 MB
			{
				$number = number_format($number / 1048576, 1, $decimalSep, $thousandsSep);
				$unit = ' MB';
			}
			else if ($number >= 1024) // 1KB
			{
				$number = number_format($number / 1024, 1, $decimalSep, $thousandsSep);
				$unit = ' KB';
			}
			else
			{
				$number = number_format($number, 1, $decimalSep, $thousandsSep);
				$unit = ' bytes';
			}

			// return $number, not $number.0 when the decimal is 0.
			if (substr($number, -2) == '.0')
			{
				$number = substr($number, 0, -2);
			}

			return $number . $unit;
		}
		else
		{
			return number_format($number, $precision, $decimalSep, $thousandsSep);
		}
	}
}