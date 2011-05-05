<?php

/**
* Helper methods for the core template functions/tags.
*
* @package XenForo_Template
*/
class XenForo_Template_Helper_Core
{
	/**
	 * Holds data about style properties available to the templates.
	 * The first dimension is a group of properties; the second represents
	 * the actual rules. Rules should be keyed by the CSS property name
	 * so they can be output directly. Prefix non-direct CSS vars with
	 * an underscore.
	 *
	 * @var array
	 */
	protected static $_styleProperties = array();

	/**
	 * A list of invalid property accesses. If an invalid group is accessed:
	 * [group] => true; if an invalid property in a valid group is accessed:
	 * [group][property] => true.
	 *
	 * @var array
	 */
	protected static $_invalidStylePropertyAccess = array();

	/**
	 * List of display styles for user title/name markup.
	 *
	 * @var array [user group id] => [username_css, user_title]
	 */
	protected static $_displayStyles = array();

	/**
	 * List of user titles in ladder.
	 *
	 * @var array
	 */
	protected static $_userTitles = array();

	/**
	 * Default language to send to functions that vary per language. Null
	 * uses the visitor's language.
	 *
	 * @var array|null
	 */
	protected static $_language = null;

	/**
	 * List of callbacks for the "helper" template tag. Maps the helper name (key)
	 * to a callback (value).
	 *
	 * Data received by this callback is not escaped!
	 *
	 * @var array
	 */
	public static $helperCallbacks = array(
		'avatar'        => array('self', 'helperAvatarUrl'),
		'avatarcropcss' => array('self', 'helperAvatarCropCss'),
		'username'      => array('self', 'helperUserName'),
		'usertitle'     => array('self', 'helperUserTitle'),
		'richusername'  => array('self', 'helperRichUserName'),
		'userblurb'     => array('self', 'helperUserBlurb'),
		'sortarrow'     => array('self', 'helperSortArrow'),
		'json'          => array('XenForo_ViewRenderer_Json', 'jsonEncodeForOutput'),
		'clearfix'      => array('XenForo_ViewRenderer_Css', 'helperClearfix'),
		'cssimportant'  => array('XenForo_ViewRenderer_Css', 'helperCssImportant'),
		'snippet'       => array('self', 'helperSnippet'),
		'bodytext'      => array('self', 'helperBodyText'),
		'bbcode'        => array('self', 'helperBbCode'),
		'highlight'     => array('XenForo_Helper_String', 'highlightSearchTerm'),
		'striphtml'     => array('self', 'helperStripHtml'),
		'linktitle'     => array('XenForo_Link', 'buildIntegerAndTitleUrlComponent'),
		'wrap'          => array('self', 'helperWrap'),
		'wordtrim'      => array('self', 'helperWordTrim'),
		'pagenumber'    => array('self', 'helperPageNumber'),
		'dump'          => array('self', 'helperDump'),
		'type'          => array('self', 'helperType'),
		'implode'       => array('self', 'helperImplode'),
		'rgba'			=> array('XenForo_Helper_Color', 'rgba'),
		'unrgba'        => array('XenForo_Helper_Color', 'unrgba'),
		'fullurl'       => array('XenForo_Link', 'convertUriToAbsoluteUri'),
		'ismemberof'    => array('self', 'helperIsMemberOf'),
		'twitterlang'   => array('self', 'helperTwitterLanguage'),
		'listitemid'    => array('XenForo_Template_Helper_Admin', 'getListItemId'),
	);

	/**
	 * List of callbacks for the "string" template tag. To support chaining, the
	 * called function should tag the string as the first argument and only require
	 * one argument. If neither constraint is true, the function must be called on its own.
	 *
	 * Data received by this call back may be escaped!
	 *
	 * @var array
	 */
	public static $stringCallbacks = array(
		'repeat'   => 'str_repeat',
		'nl2br'    => 'nl2br',
		'trim'     => 'trim',
		'censor'   => array('XenForo_Helper_String', 'censorString'),
		'wordtrim' => array('XenForo_Helper_String', 'wholeWordTrim'),
		'autolink' => array('XenForo_Helper_String', 'autoLinkPlainText'),
		'wrap'     => array('XenForo_Helper_String', 'wordWrapString')
	);

	/**
	* Private constructor. Don't instantiate this object. Use it statically.
	*/
	private function __construct() {}

	/**
	 * Appends bread crumb entries to the existing list of bread crumbs.
	 *
	 * @param array $existing Existing entries
	 * @param array $new New entries; if this is invalid, nothing will be appended
	 *
	 * @return array Existing entries with new entries appended
	 */
	public static function appendBreadCrumbs(array $existing, $new)
	{
		if (!is_array($new))
		{
			return $existing;
		}

		foreach ($new AS $breadCrumb)
		{
			if (isset($breadCrumb['value']))
			{
				$breadCrumb['value'] = htmlspecialchars($breadCrumb['value']);
			}

			$existing[] = $breadCrumb;
		}

		return $existing;
	}

	/**
	* Date formatting. Format represents a string name of a format.
	*
	* @param integer Unix timestamp to format
	* @param string  Named format (name options TBD)
	*
	* @return string
	*/
	public static function date($timestamp, $format = null)
	{
		return XenForo_Locale::date($timestamp, $format, self::$_language);
	}

	/**
	* Time formatting. Format represents a string name of a format.
	*
	* @param integer Unix timestamp to format
	* @param string  Named format (name options TBD)
	*
	* @return string
	*/
	public static function time($timestamp, $format = null)
	{
		return XenForo_Locale::time($timestamp, $format, self::$_language);
	}

	/**
	* Date and time formatting. Format represents a string name of a format.
	*
	* @param integer Unix timestamp to format
	* @param string  Named format (name options TBD)
	*
	* @return string
	*/
	public static function dateTime($timestamp, $format = null)
	{
		if ($format == 'html')
		{
			return self::dateTimeHtml($timestamp);
		}
		else
		{
			return XenForo_Locale::dateTime($timestamp, $format, self::$_language);
		}
	}

	/**
	 * Returns an <abbr> tag with a date suitable for Javascript refreshing
	 *
	 * @param integer $timestamp
	 * @param array $attributes
	 *
	 * @return string <abbr class="DateTime" data-unixtime="$timestamp"...
	 */
	public static function dateTimeHtml($timestamp, $attributes = array())
	{
		$class = (empty($attributes['class']) ? '' : ' ' . htmlspecialchars($attributes['class']));

		unset($attributes['time'], $attributes['class']);

		$attribs = self::getAttributes($attributes);

		$time = XenForo_Locale::dateTime($timestamp, 'separate', self::$_language);

		if ($time['relative'])
		{
			$tag = 'abbr';
			$data = ' data-time="' . $timestamp . '" data-diff="' . (XenForo_Application::$time - $timestamp)
				. '" data-datestring="' . $time['date'] . '" data-timestring="' . $time['time'] . '"';
			$value = $time['string'];
		}
		else
		{
			$tag = 'span';
			$data = ' title="' . $time['string'] . '"'; // empty this to remove tooltip from non-relative dates
			$value = $time['date'];
		}

		return "<{$tag} class=\"DateTime{$class}\"{$attribs}{$data}>{$value}</{$tag}>";
	}

	/**
	 * Returns a hyperlink to follow or unfollow a user. Designed to work with the XenForo.FollowLink javascript.
	 *
	 * @param array $user
	 * @param array $attributes
	 * @param string $content
	 *
	 * @return string
	 */
	public static function followHtml(array $user, array $attributes, $content = '')
	{
		$visitor = XenForo_Visitor::getInstance();

		if (!$visitor['user_id'] || $visitor['user_id'] == $user['user_id'])
		{
			return '';
		}

		if ($visitor->isFollowing($user['user_id']))
		{
			$action = 'unfollow';
		}
		else
		{
			$action = 'follow';
		}

		$link =  XenForo_Link::buildPublicLink("members/$action", $user, array('_xfToken' => $visitor['csrf_token_page']));

		$class = (empty($attributes['class']) ? '' : ' ' . htmlspecialchars($attributes['class']));

		unset($attributes['user'], $attributes['class']);

		if (!isset($attributes['title']) && isset($user['following']))
		{
			if (XenForo_Model::create('XenForo_Model_User')->isFollowing($visitor['user_id'], $user))
			{
				$attributes['title'] = new XenForo_Phrase('user_is_following_you', array('user' => $user['username']));
			}
			else
			{
				$attributes['title'] = new XenForo_Phrase('user_is_not_following_you', array('user' => $user['username']));
			}
		}

		$attribs = self::getAttributes($attributes);

		return "<a href=\"{$link}\" class=\"FollowLink{$class}\"  {$attribs}>" . new XenForo_Phrase($action) . '</a>';
	}

	/**
	 * Returns an HTML string declaring who likes something
	 *
	 * @param integer Total number of likes
	 * @param string Link to page showing all users who liked this content
	 * @param integer Timestamp at which the visitor liked this content
	 * @param array Array of up to 3 users who liked this content - user_id, username required.
	 *
	 * @return string
	 */
	public static function likesHtml($number, $likesLink, $likeDate = 0, array $users = array())
	{
		$number = intval($number);

		if (empty($users))
		{
			return new XenForo_Phrase('likes_x_people_like_this', array(
				'likes' => self::numberFormat($number),
				'likesLink' => $likesLink
			));
		}

		if ($likeDate)
		{
			$youLikeThis = true;

			$visitorId = XenForo_Visitor::getUserId();
			foreach ($users AS $key => $user)
			{
				if ($user['user_id'] == $visitorId)
				{
					unset($users[$key]);
					break;
				}
			}

			if (count($users) == 3)
			{
				unset($users[2]);
			}

			$users = array_values($users);
		}
		else
		{
			$youLikeThis = false;
		}

		$user1 = $user2 = $user3 = '';

		if ($users[0])
		{
			$user1 = self::helperUserName($users[0]);

			if ($users[1])
			{
				$user2 = self::helperUserName($users[1]);

				if ($users[2])
				{
					$user3 = self::helperUserName($users[2]);
				}
			}
		}

		$phraseParams = array(
			'user1' => $user1,
			'user2' => $user2,
			'user3' => $user3,
			'others' => self::numberFormat($number - 3),
			'likesLink' => $likesLink
		);

		switch ($number)
		{
			case 1: return new XenForo_Phrase(($youLikeThis
				? 'likes_you_like_this'
				: 'likes_user1_likes_this'), $phraseParams, false);

			case 2: return new XenForo_Phrase(($youLikeThis
				? 'likes_you_and_user1_like_this'
				: 'likes_user1_and_user2_like_this'), $phraseParams, false);

			case 3: return new XenForo_Phrase(($youLikeThis
				? 'likes_you_user1_and_user2_like_this'
				: 'likes_user1_user2_and_user3_like_this'), $phraseParams, false);

			case 4: return new XenForo_Phrase(($youLikeThis
				? 'likes_you_user1_user2_and_1_other_like_this'
				: 'likes_user1_user2_user3_and_1_other_like_this'), $phraseParams, false);

			default: return new XenForo_Phrase(($youLikeThis
				? 'likes_you_user1_user2_and_x_others_like_this'
				: 'likes_user1_user2_user3_and_x_others_like_this'), $phraseParams, false);
		}
	}

	/**
	 * Sets the default language for language-specific calls. This should
	 * be unset after the specific context is complete.
	 *
	 * @param array|null $language
	 */
	public static function setDefaultLanguage(array $language = null)
	{
		self::$_language = $language;
	}

	/**
	 * Gets the default language.
	 *
	 * @return array|null
	 */
	public static function getDefaultLanguage()
	{
		return self::$_language;
	}

	/**
	* Escape a string for use within JavaScript. The context represents whether
	* it is within a double- or single-quoted string. This function does not
	* support outputs in other contexts!
	*
	* @param string String to escape
	* @param string Context (double or single)
	*
	* @return string
	*/
	public static function jsEscape($string, $context = 'double')
	{
		$quote = ($context == 'double' ? '"' : "'");

		$string = str_replace(
			array('\\',   $quote,        "\r",  "\n",  '</'),
			array('\\\\', '\\' . $quote, "\\r", "\\n", '<\\/'),
			$string
		);

		$string = preg_replace('/-(?=-)/', '-\\', $string);

		return $string;
	}

	/**
	 * Generates a link to the specified type of public data.
	 *
	 * @param string $type Type of data to link to. May also include a specific action.
	 * @param mixed $data Primary data about this link
	 * @param array $extraParams Extra named params. Unhandled params will become the query string
	 * @param callback|false $escapeCallback Callback method for escaping the link
	 *
	 * @return string
	 */
	public static function link($type, $data = null, array $extraParams = array(), $escapeCallback = 'htmlspecialchars')
	{
		$link = XenForo_Link::buildPublicLink($type, $data, $extraParams);
		if ($escapeCallback)
		{
			$link = call_user_func($escapeCallback, $link);
		}

		return $link;
	}

	/**
	 * Generates an link to the specified type of admin data.
	 *
	 * @param string $type Type of data to link to. May also include a specific action.
	 * @param mixed $data Primary data about this link
	 * @param array $extraParams Extra named params. Unhandled params will become the query string
	 * @param callback|false $escapeCallback Callback method for escaping the link
	 *
	 * @return string
	 */
	public static function adminLink($type, $data = null, array $extraParams = array(), $escapeCallback = 'htmlspecialchars')
	{
		$link = XenForo_Link::buildAdminLink($type, $data, $extraParams);
		if ($escapeCallback)
		{
			$link = call_user_func($escapeCallback, $link);
		}

		return $link;
	}

	/**
	 * Wrapper function for handling pagenav template tags (not functions). This simply
	 * ensures that some of the data is of the expected type.
	 *
	 * @param string $callType Type of page to render: admin or public
	 * @param integer $perPage Items to display per page
	 * @param integer $totalItems Total number of items
	 * @param integer $page Current page number
	 * @param string $linkType Type of link to create
	 * @param mixed $linkData Data for the link
	 * @param array $linkParams List of key value params for the link; page will be set as needed
	 * @param string|false $unreadLink URL for to jump to the first unread
	 * @param array $options Options to control the building
	 *
	 * @return string
	 */
	public static function pageNavTag($callType, $perPage, $totalItems, $page, $linkType,
		$linkData = false, $linkParams = false, $unreadLink = false, $options = false)
	{
		if (!is_array($linkParams))
		{
			$linkParams = array();
		}
		if (!is_array($linkParams))
		{
			$options = array();
		}

		if ($unreadLink)
		{
			$options['unreadLink'] = $unreadLink;
		}

		if ($callType == 'admin')
		{
			return self::adminPageNav($perPage, $totalItems, $page, $linkType, $linkData, $linkParams, $options);
		}
		else
		{
			return self::pageNav($perPage, $totalItems, $page, $linkType, $linkData, $linkParams, $options);
		}
	}

	/**
	 * Gets the page navigation for a public page.
	 *
	 * @param integer $perPage Items to display per page
	 * @param integer $totalItems Total number of items
	 * @param integer $page Current page number
	 * @param string $linkType Type of link to create
	 * @param mixed $linkData Data for the link
	 * @param array $linkParams List of key value params for the link; page will be set as needed
	 * @param array $options Options to control the building
	 *
	 * @return string|XenForo_Template_Public
	 */
	public static function pageNav($perPage, $totalItems, $page, $linkType,
		$linkData = null, array $linkParams = array(), array $options = array()
	)
	{
		return self::_getPageNav('XenForo_Template_Public', 'link', $perPage, $totalItems, $page,
			$linkType, $linkData, $linkParams, $options
		);
	}

	/**
	 * Gets the page navigation for an admin page.
	 *
	 * @param integer $perPage Items to display per page
	 * @param integer $totalItems Total number of items
	 * @param integer $page Current page number
	 * @param string $linkType Type of link to create
	 * @param mixed $linkData Data for the link
	 * @param array $linkParams List of key value params for the link; page will be set as needed
	 * @param array $options Options to control the building
	 *
	 * @return string|XenForo_Template_Public
	 */
	public static function adminPageNav($perPage, $totalItems, $page, $linkType,
		$linkData = null, array $linkParams = array(), array $options = array()
	)
	{
		return self::_getPageNav('XenForo_Template_Admin', 'adminLink', $perPage, $totalItems, $page,
			$linkType, $linkData, $linkParams, $options
		);
	}

	/**
	 * Helper to get page navigation (all pages, for scrolling pagenav version).
	 *
	 * @param string $templateClass Name of the template class to instantiate
	 * @param string $linkFunction Name of the linking function to call (in this class)
	 * @param integer $perPage Items to display per page
	 * @param integer $totalItems Total number of items
	 * @param integer $currentPage Current page number
	 * @param string $linkType Type of link to create
	 * @param mixed $linkData Data for the link
	 * @param array $linkParams List of key value params for the link; page will be set as needed
	 * @param array $options Options to control the building
	 *
	 * @return string|XenForo_Template_Abstract
	 */
	protected static function _getPageNav($templateClass, $linkFunction, $perPage, $totalItems, $currentPage,
		$linkType, $linkData = null, array $linkParams = array(), array $options = array()
	)
	{
		// abort if there are insufficient items to make multiple pages
		if ($totalItems < 1 || $perPage < 1)
		{
			return '';
		}

		$options = array_merge(
			array(
				'unreadLink' => '',
				'template' => 'page_nav',
				'displayRange' => 2 //TODO: make this come from an option?
			),
			$options
		);
		$unreadLinkHtml = htmlspecialchars($options['unreadLink'], ENT_COMPAT, 'iso-8859-1', false);

		$pageTotal = ceil($totalItems / $perPage);

		// abort if there is only one page
		if ($pageTotal <= 1)
		{
			if (!empty($options['unreadLink']))
			{
				return new $templateClass($options['template'], array(
					'unreadLinkHtml' => $unreadLinkHtml,
					'pageTotal' => $pageTotal
				));
			}

			return '';
		}

		$currentPage = min(max($currentPage, 1), $pageTotal);

		// number of pages either side of the current page
		$range = $options['displayRange'];
		$scrollSize = 1 + 2 * $range;
		$scrollThreshold = $scrollSize + 2;

		if ($pageTotal >$scrollThreshold)
		{
			$startPage = max(2, $currentPage - $range);
			$endPage = min($pageTotal, $startPage + $scrollSize);

			$extraPages = $scrollSize - ($endPage - $startPage);
			if ($extraPages > 0)
			{
				$startPage -= $extraPages;
			}
		}
		else
		{
			$startPage = 2;
			$endPage = $pageTotal;
		}

		if ($endPage > $startPage)
		{
			$endPage--;
			$pages = range($startPage, $endPage);
		}
		else
		{
			$pages = array();
		}

		if (isset($linkParams['_params']) && is_array($linkParams['_params']))
		{
			$tempParams = $linkParams['_params'];
			unset($linkParams['_params']);
			$linkParams = array_merge($tempParams, $linkParams);
		}

		$templateVariables = array(
			'pageTotal' => intval($pageTotal),
			'currentPage' => $currentPage,

			'pages' => $pages,
			'range' => $range,
			'scrollThreshold' => $scrollThreshold,

			'startPage' => $startPage,
			'endPage' => $endPage,

			'prevPage' => ($currentPage > 1 ? ($currentPage - 1) : false),
			'nextPage' => ($currentPage < $pageTotal ? ($currentPage + 1) : false),

			'pageNumberSentinel' => XenForo_Application::$integerSentinel,

			'linkType' => $linkType,
			'linkData' => $linkData,
			'linkParams' => $linkParams,

			'maxDigits' => strlen($pageTotal),

			'unreadLinkHtml' => $unreadLinkHtml
		);

		$template = new $templateClass($options['template'], $templateVariables);

		return $template;
	}

	/**
	 * Formats a number based on current user's language. Behaves like PHP's number_format.
	 *
	 * @param mixed $number
	 * @param integer $decimals
	 *
	 * @return string
	 */
	public static function numberFormat($number, $decimals = 0)
	{
		return XenForo_Locale::numberFormat($number, $decimals, self::$_language);
	}

	/**
	 * Calls a general helper as listed in the helper callbacks.
	 *
	 * @param string $helper Name of helper
	 * @param array $args All arguments passed to the helper.
	 *
	 * @return string
	 */
	public static function callHelper($helper, array $args)
	{
		$helper = strtolower(strval($helper));
		if (!isset(self::$helperCallbacks[$helper]))
		{
			return '';
		}

		return call_user_func_array(self::$helperCallbacks[$helper], $args);
	}

	/**
	 * Helper to get the user title for the specified user.
	 *
	 * @param array $user
	 * @param boolean $allowCustomTitle Allows the user title to come from the custom title
	 *
	 * @return string
	 */
	public static function helperUserTitle($user, $allowCustomTitle = true)
	{
		if (!is_array($user) || !array_key_exists('display_style_group_id', $user))
		{
			return '';
		}

		if ($allowCustomTitle && !empty($user['custom_title']))
		{
			return htmlspecialchars($user['custom_title']);
		}

		if (empty($user['user_id']))
		{
			$user['display_style_group_id'] = XenForo_Model_User::$defaultGuestGroupId;
		}

		if (isset($user['display_style_group_id']) && isset(self::$_displayStyles[$user['display_style_group_id']]))
		{
			$style = self::$_displayStyles[$user['display_style_group_id']];
			if ($style['user_title'] !== '')
			{
				return $style['user_title'];
			}
		}

		if (empty($user['user_id']) || !isset($user['trophy_points']))
		{
			return ''; // guest user title or nothing
		}

		foreach (self::$_userTitles AS $points => $title)
		{
			if ($user['trophy_points'] >= $points)
			{
				return $title;
			}
		}

		return '';
	}

	/**
	 * Sets the user titles in the ladder.
	 *
	 * @param array $userTitles
	 */
	public static function setUserTitles($userTitles)
	{
		self::$_userTitles = $userTitles;
	}

	/**
	 * Outputs the necessary HTML for a rich username (includes the display style markup class).
	 *
	 * @param array $user
	 * @param string Alternative username HTML
	 *
	 * @return string
	 */
	public static function helperRichUserName(array $user, $usernameHtml = '')
	{
		if (!is_array($user) || (!isset($user['username']) && $usernameHtml === ''))
		{
			return '';
		}

		if ($usernameHtml === '')
		{
			$usernameHtml = htmlspecialchars($user['username']);
		}

		if (empty($user['user_id']))
		{
			$user['display_style_group_id'] = XenForo_Model_User::$defaultGuestGroupId;
		}

		if (isset($user['display_style_group_id']) && isset(self::$_displayStyles[$user['display_style_group_id']]))
		{
			$style = self::$_displayStyles[$user['display_style_group_id']];
			if ($style['username_css'])
			{
				return '<span class="style' . $user['display_style_group_id'] . '">' . $usernameHtml . '</span>';
			}
		}

		return $usernameHtml;
	}

	/**
	 * Helper, for the user blurb "Title, gender, age, from location".
	 *
	 * @param array $user
	 * @param boolean Include user title in blurb
	 *
	 * @return string
	 */
	public static function helperUserBlurb(array $user, $includeUserTitle = true)
	{
		if (!is_array($user) || empty($user['user_id']))
		{
			return '';
		}

		$parts = array();

		if ($includeUserTitle && $userTitle = self::callHelper('usertitle', array($user)))
		{
			$parts[] = '<span class="userTitle" itemprop="title">' . $userTitle . '</span>';
		}

		if (!empty($user['gender']))
		{
			$parts[] = new XenForo_Phrase($user['gender']);
		}

		if (!isset($user['age']) && !empty($user['show_dob_year']) && !empty($user['dob_year']))
		{
			$user['age'] = XenForo_Model::create('XenForo_Model_UserProfile')->getUserAge($user);
		}

		if (!empty($user['age']))
		{
			$parts[] = $user['age'];
		}

		if (!empty($user['location']))
		{
			$user['locationCensored'] = XenForo_Helper_String::censorString($user['location']);

			$location = '<a href="'
				. XenForo_Link::buildPublicLink('misc/location-info', '', array('location' => $user['locationCensored']))
				. '" class="concealed" target="_blank" rel="nofollow">'
				. htmlspecialchars($user['locationCensored'])
				. '</a>';

			$parts[] = new XenForo_Phrase('from_x_location', array('location' => $location), false);
		}

		return implode(', ', $parts);
	}

	/**
	 * Sets the display styles.
	 *
	 * @param array $displayStyles
	 */
	public static function setDisplayStyles(array $displayStyles)
	{
		self::$_displayStyles = $displayStyles;
	}

	/**
	 * Helper to print out the sort arrow.
	 *
	 * @param string $order Name of the current ordering field
	 * @param string $direction Direction (asc, desc)
	 * @param string $fieldName Name of the field we're looking at
	 * @param string $descOutput HTML to output for descending
	 * @param string $ascOutput HTML to output for ascending
	 *
	 * @return string
	 */
	public static function helperSortArrow($order, $direction, $fieldName, $descOutput = ' &darr;', $ascOutput = ' &uarr;')
	{
		if ($order != $fieldName)
		{
			return '';
		}
		else if ($direction == 'desc')
		{
			return $descOutput;
		}
		else
		{
			return $ascOutput;
		}
	}

	/**
	 * Strips BB Code from a string and word-trims it to a given max length around an optional search term
	 *
	 * @param string $string Input text (bb code)
	 * @param integer $maxLength
	 * @param array $options Key-value options
	 *
	 * @return string HTML
	 */
	public static function helperSnippet($string, $maxLength = 0, array $options = array())
	{
		$options = array_merge(array(
			'term' => '',
			'emClass' => '',
			'stripQuote' => false
		), $options);

		$string = XenForo_Helper_String::bbCodeStrip($string, $options['stripQuote']);

		if ($maxLength)
		{
			$string = XenForo_Helper_String::wholeWordTrimAroundSearchTerm($string, $maxLength, $options['term']);
		}

		$string = trim($string);
		$string = XenForo_Helper_String::censorString($string);

		if ($options['term'] && $options['emClass'])
		{
			return XenForo_Helper_String::highlightSearchTerm($string, $options['term'], $options['emClass']);
		}
		else
		{
			return htmlspecialchars($string);
		}
	}

	/**
	 * Prepares simple body text with word wrap, censoring, and nl2br.
	 * HTML/BB code is not parsed within string.
	 *
	 * @param string $string
	 *
	 * @return string
	 */
	public static function helperBodyText($string)
	{
		$string = XenForo_Helper_String::censorString($string);
		$string = XenForo_Helper_String::autoLinkPlainText(htmlspecialchars($string));

		return nl2br($string);
	}

	/**
	 * Helper to render the specified text as BB code.
	 *
	 * @param XenForo_BbCode_Parser $parser
	 * @param string $text
	 *
	 * @return string
	 */
	public static function helperBbCode($parser, $text)
	{
		if (!($parser instanceof XenForo_BbCode_Parser))
		{
			trigger_error(E_USER_WARNING, 'BB code parser not specified correctly.');
			return '';
		}
		else
		{
			return $parser->render($text);
		}
	}

	/**
	 * Strips HTML from the text and then HTML escapes it without double encoding.
	 *
	 * @param string $string
	 * @param string $allowedTags List of allowed tags for strip_tags
	 *
	 * @return string String with HTML removed or escaped if not removed.
	 */
	public static function helperStripHtml($string, $allowedTags = '')
	{
		return htmlspecialchars(strip_tags($string, $allowedTags), ENT_COMPAT, null, false);
	}

	/**
	 * Wraps and HTML escapes the given string.
	 *
	 * @param string $string
	 * @param integer|null $breakLength
	 */
	public static function helperWrap($string, $breakLength)
	{
		return htmlspecialchars(XenForo_Helper_String::wordWrapString($string, $breakLength));
	}

	/**
	 * Word trims and HTML escapes the given string.
	 *
	 * @param string $string
	 * @param integer $trimLength
	 */
	public static function helperWordTrim($string, $trimLength)
	{
		return htmlspecialchars(XenForo_Helper_String::wholeWordTrim($string, $trimLength));
	}

	/**
	 * Returns the string ' | Page $page' if $page is greater than one.
	 *
	 * @param integer $page
	 *
	 * @return string
	 */
	public static function helperPageNumber($page)
	{
		$page = intval($page);

		if ($page > 1)
		{
			return htmlspecialchars(new XenForo_Phrase('page_x', array('page' => $page)));
		}
	}

	public static function helperDump($data)
	{
		return Zend_Debug::dump($data, null, false);
	}

	public static function helperType($data)
	{
		return gettype($data);
	}

	public static function helperImplode(array $data, $glue = ' ')
	{
		return htmlspecialchars(implode($data, $glue));
	}

	public static function helperIsMemberOf(array $user, $userGroupId)
	{
		return XenForo_Model::create('XenForo_Model_User')->isMemberOfUserGroup($user, $userGroupId);
	}

	public static function helperTwitterLanguage($locale)
	{
		$lang = strtolower(substr($locale, 0, 2));

		switch ($lang)
		{
			case 'en':
			case 'fr':
			case 'de':
			case 'it':
			case 'es':
			case 'ko':
			case 'ja':
				return $lang;

			default:
				return 'en';
		}
	}

	/**
	 * Performs string manipulation functions. Function list is a string
	 * that may be delimited by " " (eg, 'nl2br trim'). Chained functions will
	 * be run from left to right. Chaining can only work when a single argument
	 * is provided. Functions requiring multiple args need separate calls.
	 *
	 * @param string $functionList
	 * @param array $args
	 *
	 * @return string
	 */
	public static function string($functionList, array $args)
	{
		$functions = explode(' ', strval($functionList));
		if (count($functions) > 1 && count($args) > 1)
		{
			return '';
		}

		foreach ($functions AS $function)
		{
			$function = strtolower(trim($function));
			if (!isset(self::$stringCallbacks[$function]))
			{
				continue;
			}

			$args = array(call_user_func_array(self::$stringCallbacks[$function], $args));
		}

		return $args[0];
	}

	/**
	 * Outputs a style property or a group of style properties. See
	 * {@link $_styleProperties} for more information on the format.
	 * The property name may be in format "group" or "group.rule". Scalar
	 * properties cannot have a rule.
	 *
	 * If no rule is specified, an entire group will be outputted, including
	 * rule names. If a rule is specified, only the value will be output.
	 *
	 * @param string $propertyName
	 *
	 * @return string
	 */
	public static function styleProperty($propertyName)
	{
		$props = self::$_styleProperties;

		$parts = explode('.', $propertyName, 2);
		if (!empty($parts[1]))
		{
			$propertyName = $parts[0];
			$propertyComponent = $parts[1];
		}
		else
		{
			$propertyComponent = '';
		}

		if (!isset($props[$propertyName]))
		{
			self::$_invalidStylePropertyAccess[$propertyName] = true;
			return '';
		}

		$property = $props[$propertyName];
		if (!is_array($property))
		{
			// scalar property ...
			if ($propertyComponent)
			{
				// ... with unknown sub component
				self::$_invalidStylePropertyAccess[$propertyName][$propertyComponent] = true;
				return '';
			}
			else
			{
				// ... in total
				return $property;
			}
		}

		// css properties now
		if ($propertyComponent)
		{
			if (isset($property[$propertyComponent]))
			{
				return $property[$propertyComponent];
			}
			else if (preg_match('#^border-.*-(radius|width|style|color)$#', $propertyComponent, $regexMatch))
			{
				$alternative = 'border-' . $regexMatch[1];
				if (isset($property[$alternative]))
				{
					return $property[$alternative];
				}
			}
			else if (preg_match('#^(padding|margin)-#', $propertyComponent, $regexMatch))
			{
				$alternative = $regexMatch[1] . '-all';
				if (isset($property[$alternative]))
				{
					return $property[$alternative];
				}
			}

			return '';
		}
		else
		{
			$output = '';
			foreach (array('font', 'background', 'padding', 'margin', 'border', 'extra') AS $component)
			{
				if (isset($property[$component]))
				{
					$output .= $property[$component] . "\n";
				}
			}
			if (isset($property['width']))
			{
				$output .= "width: $property[width];\n";
			}
			if (isset($property['height']))
			{
				$output .= "height: $property[height];\n";
			}

			return $output;
		}
	}

	/**
	 * Helper to set the available style properties.
	 *
	 * @param array $properties Style properties
	 * @param boolean $merge True to merge with existing set
	 */
	public static function setStyleProperties(array $properties, $merge = false)
	{
		if ($merge)
		{
			self::$_styleProperties = array_merge(self::$_styleProperties, $properties);
		}
		else
		{
			self::$_styleProperties = $properties;
		}
	}

	/**
	 * Returns a list of invalid style property accesses.
	 * @see $_invalidStylePropertyAccess
	 *
	 * @return array
	 */
	public static function getInvalidStylePropertyAccessList()
	{
		return self::$_invalidStylePropertyAccess;
	}

	/**
	 * Resets invalid style property accesses.
	 */
	public static function resetInvalidStylePropertyAccessList()
	{
		self::$_invalidStylePropertyAccess = array();
	}

	/**
	 * Converts a URL into hidden inputs, for use in a GET form when the
	 * action may have a query string. Note that the non-query string part
	 * of the URL will not be output in any way.
	 *
	 * @param string $url
	 *
	 * @return string String of hidden form inputs
	 */
	public static function getHiddenInputsFromUrl($url)
	{
		$converted = self::convertUrlToActionAndNamedParams($url);
		return self::getHiddenInputs($converted['params']);
	}

	/**
	 * Gets hidden inputs from a list of key-value params.
	 *
	 * @param array $params
	 *
	 * @return string
	 */
	public static function getHiddenInputs(array $params)
	{
		$inputs = '';
		foreach ($params AS $name => $value)
		{
			$inputs .= '<input type="hidden" name="' . htmlspecialchars($name)
				. '" value="' . htmlspecialchars($value) . '" />' . "\n";
		}

		return $inputs;
	}

	/**
	 * Constructs ' href="link-to-user"' if appropriate
	 *
	 * @param array $user
	 * @param array $attributes
	 *
	 * @return string ' href="members/example-user.234"' or empty
	 */
	public static function getUserHref(array $user, array $attributes = array())
	{
		if (empty($attributes['href']))
		{
			if ($user['user_id'])
			{
				$href = self::link('members', $user);
			}
			else
			{
				$href = '';
			}
		}
		else
		{
			$href = htmlspecialchars($attributes['href']);
		}

		return ($href ? " href=\"{$href}\"" : '');
	}

	/**
	 * Builds a string of attributes for insertion into an HTML tag.
	 *
	 * @param array $attributes
	 *
	 * @return string ' attr1="abc" attr2="def" attr3="ghi"'
	 */
	public static function getAttributes(array $attributes = array())
	{
		$attributesHtml = '';

		foreach ($attributes AS $attribute => $value)
		{
			$attributesHtml .= ' ' . htmlspecialchars($attribute) . "=\"{$value}\"";
		}

		return $attributesHtml;
	}

	/**
	 * Converts a URL that may have a route/action and named params to a form
	 * action (script name, things before query string) and named params. A route
	 * in the query string is converted to a named param "_".
	 *
	 * @param string $url
	 *
	 * @return array Format: [action] => form action, [params] => key-value param pairs
	 */
	public static function convertUrlToActionAndNamedParams($url)
	{
		$params = array();

		if (($questPos = strpos($url, '?')) !== false)
		{
			$queryString = htmlspecialchars_decode(substr($url, $questPos + 1));
			$url = substr($url, 0, $questPos);

			if (preg_match('/^([^=&]*)(&|$)/', $queryString, $queryStringUrl))
			{
				$route = $queryStringUrl[1];
				$queryString = substr($queryString, strlen($queryStringUrl[0]));
			}
			else
			{
				$route = '';
			}


			if ($route !== '')
			{
				$params['_'] = $route;
			}

			if ($queryString)
			{
				$params = array_merge($params, XenForo_Application::parseQueryString($queryString));
			}
		}

		return array(
			'action' => htmlspecialchars($url),
			'params' => $params
		);
	}

	// -------------------------------------------------
	// Username link method

	/**
	 * Produces a <a href="members/username.123" class="username">Username</a> snippet
	 *
	 * @param array $user
	 * @param string $username Used to override the username from $user
	 * @param boolean Render rich username markup
	 * @param array Attributes for the <a> tag
	 *
	 * @return string
	 */
	public static function userNameHtml(array $user, $username = '', $rich = false, array $attributes = array())
	{
		if ($username == '')
		{
			$username = htmlspecialchars($user['username']);
		}

		if ($rich)
		{
			$username = self::helperRichUserName($user, $username);
		}

		$href = self::getUserHref($user, $attributes);

		$class = (empty($attributes['class']) ? '' : ' ' . htmlspecialchars($attributes['class']));

		unset($attributes['href'], $attributes['class']);

		$attribs = self::getAttributes($attributes);

		return "<a{$href} class=\"username{$class}\"{$attribs}>{$username}</a>";
	}

	public static function helperUserName(array $user, $class = '', $rich = false)
	{
		return self::userNameHtml($user, '', $rich, array('class' => $class));
	}

	// -------------------------------------------------
	// Avatar-related methods

	/**
	 * Returns an <a> tag for use as a user avatar
	 *
	 * @param array $user
	 * @param boolean If true, use an <img> tag, otherwise use a block <span> with the avatar as a background image
	 * @param array Extra tag attributes
	 * @param string Additional tag contents (inserted after image element)
	 */
	public static function avatarHtml(array $user, $img, array $attributes = array(), $content = '')
	{
		if (!empty($attributes['size']))
		{
			$size = strtolower($attributes['size']);

			switch ($size)
			{
				case 'l':
				case 'm':
				case 's':
					break;

				default:
					$size = 'm';
			}
		}
		else
		{
			$size = 'm';
		}

		$forceType = (isset($attributes['forcetype']) ? $attributes['forcetype'] : null);

		$canonical = (isset($attributes['canonical']) && self::attributeTrue($attributes['canonical']));

		$src = self::helperAvatarUrl($user, $size, $forceType, $canonical);

		$href = self::getUserHref($user, $attributes);

		if ($img)
		{
			$username = htmlspecialchars($user['username']);
			$dimension = XenForo_Model_Avatar::getSizeFromCode($size);

			$image = "<img src=\"{$src}\" width=\"{$dimension}\" height=\"{$dimension}\" alt=\"{$username}\" />";
		}
		else
		{
			$text = (empty($attributes['text']) ? '' : htmlspecialchars($attributes['text']));

			$image = "<span class=\"img {$size}\" style=\"background-image: url('{$src}')\">{$text}</span>";
		}

		$class = (empty($attributes['class']) ? '' : ' ' . htmlspecialchars($attributes['class']));

		unset($attributes['user'], $attributes['size'], $attributes['img'], $attributes['text'], $attributes['class']);

		$attribs = self::getAttributes($attributes);

		if ($content !== '')
		{
			$content = " {$content}";
		}

		return "<a{$href} class=\"avatar Av{$user[user_id]}{$size}{$class}\"{$attribs} data-avatarHtml=\"true\">{$image}{$content}</a>";
	}

	/**
	 * Helper to fetch the URL of a user's avatar.
	 *
	 * @param array $user User info
	 * @param string $size Size code
	 * @param boolean Serve the default gender avatar, even if the user has a custom avatar
	 * @param boolean Serve the full canonical URL
	 *
	 * @return string Path to avatar
	 */
	public static function helperAvatarUrl($user, $size, $forceType = null, $canonical = false)
	{
		if (!is_array($user))
		{
			$user = array();
		}

		if ($forceType)
		{
			switch ($forceType)
			{
				case 'default':
				case 'custom':
					break;

				default:
					$forceType = null;
					break;
			}
		}

		$url = self::getAvatarUrl($user, $size, $forceType);

		if ($canonical)
		{
			$url = XenForo_Link::convertUriToAbsoluteUri($url, true);
		}

		return htmlspecialchars($url);
	}

	/**
	 * Returns an array containing the URLs for each avatar size available for the given user
	 *
	 * @param array $user
	 *
	 * @return array [$sizeCode => $url, $sizeCode => $url...]
	 */
	public static function getAvatarUrls(array $user)
	{
		$urls = array();

		foreach (XenForo_Model_Avatar::getSizes() AS $sizeCode => $maxDimensions)
		{
			$urls[$sizeCode] = self::getAvatarUrl($user, $sizeCode);
		}

		return $urls;
	}

	/**
	 * Returns the URL to the appropriate avatar type for the given user
	 *
	 * @param array $user
	 * @param string $size (s,m,l)
	 * @param string Force 'default' or 'custom' type
	 *
	 * @return string
	 */
	public static function getAvatarUrl(array $user, $size, $forceType = '')
	{
		if (!empty($user['user_id']) && $forceType != 'default')
		{
			if ($user['gravatar'] && $forceType != 'custom')
			{
				return self::_getGravatarUrl($user, $size);
			}
			else if (!empty($user['avatar_date']))
			{
				return self::_getCustomAvatarUrl($user, $size);
			}
		}

		return self::_getDefaultAvatarUrl($user, $size);
	}

	/**
	 * Returns the default gender-specific avatar URL
	 *
	 * @param string $gender - male / female / other
	 * @param string $size (s,m,l)
	 *
	 * @return string
	 */
	protected static function _getDefaultAvatarUrl(array $user, $size)
	{
		switch ($user['gender'])
		{
			case 'male':
			case 'female':
				$gender = $user['gender'] . '_';
				break;

			default:
				$gender = '';
				break;
		}

		if (!$imagePath = self::styleProperty('imagePath'))
		{
			$imagePath = 'styles/default';
		}

		return "{$imagePath}/xenforo/avatars/avatar_{$gender}{$size}.png";
	}

	/**
	 * Returns the URL to a user's custom avatar
	 *
	 * @param array $user
	 * @param string $size (s,m,l)
	 *
	 * @return string
	 */
	protected static function _getCustomAvatarUrl(array $user, $size)
	{
		$group = floor($user['user_id'] / 1000);
		return XenForo_Application::$externalDataPath . "/avatars/$size/$group/$user[user_id].jpg?$user[avatar_date]";
	}

	/**
	 * Returns a Gravatar URL for the user
	 *
	 * @param array $user
	 * @param string|integer $size (s,m,l)
	 * @param string Override default (useful to use '404')
	 */
	protected static function _getGravatarUrl(array $user, $size, $default = '')
	{
		$md5 = md5($user['gravatar']);

		if ($default === '')
		{
			$default = '&d=' . urlencode(XenForo_Application::get('options')->boardUrl . '/' . self::_getDefaultAvatarUrl($user, $size));
		}
		else if (!empty($default))
		{
			$default = '&d=' . urlencode($default);
		}

		if (is_string($size))
		{
			$size = XenForo_Model_Avatar::getSizeFromCode($size);
		}

		return "http://www.gravatar.com/avatar/{$md5}.jpg?s={$size}{$default}";
	}

	/**
	 * Helper to fetch the CSS rules to crop a user's avatar to their chosen square aspect
	 *
	 * @param array $user
	 * @param boolean $center
	 */
	public static function helperAvatarCropCss($user, $center = false)
	{
		if (!is_array($user)                             // not a valid user
			|| empty($user['avatar_date'])               // no custom avatar
			|| !array_key_exists('avatar_crop_x', $user) // no x crop info
			|| !array_key_exists('avatar_crop_y', $user) // no y crop info
			|| !empty($user['gravatar'])                 // using Gravatar, which is always square
		)
		{
			return '';
		}

		$css = '';

		foreach (XenForo_ViewPublic_Helper_User::getAvatarCropCss($user, $center) AS $property => $value)
		{
			$css .= "$property: $value; ";
		}

		return $css;
	}

	/**
	 * Helper to determine if an attribute value should be treated as true
	 *
	 * @param mixed $attribute
	 *
	 * @return boolean
	 */
	public static function attributeTrue($attribute)
	{
		if (!empty($attribute))
		{
			switch ((string)strtolower($attribute))
			{
				case 'on':
				case 'yes':
				case 'true':
				case '1':
					return true;
			}
		}

		return false;
	}
}